<?php

namespace App\Http\Controllers\Academia;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Academia\Sede;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\Academia\Vacacionales;
use Illuminate\Support\Facades\Validator;

/**
 * Inscripciones a cursos vacacionales: el alumno, las clases (fechas) que toma y sus pagos.
 * Los pagos son propios de vacacionales: no entran en Pagos ni en el estado de cuenta de la academia.
 */
class VacacionalInscripcionController extends Controller
{
    private function consulta()
    {
        return DB::table('vacacional_inscripciones as i')
            ->join('vacacional_alumnos as a', 'a.id', '=', 'i.alumno_id')
            ->join('vacacional_cursos as c', 'c.id', '=', 'i.curso_id')
            ->leftJoin('sedes as s', 's.id', '=', 'c.sede_id')
            ->select('i.*', 'c.nombre as curso_nombre', 'c.clases_por_alumno', 's.nombre as sede_nombre',
                'a.correo as alumno_correo', 'a.telefono as alumno_telefono', DB::raw("CONCAT(a.nombres,' ',a.apellidos) as alumno_nombre"));
    }

    private function formatear(object $i): array
    {
        $clases = DB::table('vacacional_inscripcion_fechas')->where('inscripcion_id', $i->id)->orderBy('fecha')->get(['fecha', 'asistio']);
        $fechas = $clases->pluck('fecha')->all();
        return array_merge([
            'id' => $i->id,
            'alumno_id' => $i->alumno_id,
            'alumno_nombre' => $i->alumno_nombre,
            'alumno_correo' => $i->alumno_correo,
            'alumno_telefono' => $i->alumno_telefono,
            'curso_id' => $i->curso_id,
            'curso_nombre' => $i->curso_nombre,
            'sede_nombre' => $i->sede_nombre,
            'fecha_inscripcion' => $i->fecha_inscripcion,
            'valor' => (float) $i->valor,
            'pagado' => (float) $i->valor - (float) $i->saldo,
            'saldo' => (float) $i->saldo,
            'observacion' => $i->observacion,
            'estado' => $i->estado,
            'clases_por_alumno' => (int) $i->clases_por_alumno,
            'fechas' => $fechas,
            'fechas_texto' => Vacacionales::fechasTexto($fechas),
            'asistencias' => $clases->where('asistio', 1)->count(),
            'faltas' => $clases->filter(fn ($c) => $c->asistio !== null && !$c->asistio)->count(),
        ], Vacacionales::auditoriaDe($i));
    }

    private function detalle(int $id): ?array
    {
        $inscripcion = $this->consulta()->where('i.id', $id)->first();
        if (!$inscripcion) {
            return null;
        }
        $datos = $this->formatear($inscripcion);
        $datos['fechas_curso'] = Vacacionales::fechasCurso($inscripcion->curso_id);
        $datos['pagos'] = DB::table('vacacional_pagos')->where('inscripcion_id', $id)->orderBy('fecha_pago')->orderBy('id')
            ->get(['id', 'monto', 'fecha_pago', 'metodo_pago', 'referencia']);
        return $datos;
    }

    public function index(Request $request)
    {
        $query = $this->consulta();
        if ($request->filled('curso_id')) {
            $query->where('i.curso_id', $request->curso_id);
        }
        if ($request->filled('alumno_id')) {
            $query->where('i.alumno_id', $request->alumno_id);
        }
        if ($request->filled('nombre')) {
            $query->where(DB::raw("CONCAT(a.nombres,' ',a.apellidos)"), 'like', '%' . $request->nombre . '%');
        }
        if ($request->pago === 'pendiente') {
            $query->where('i.saldo', '>', 0)->where('i.estado', 1);
        } elseif ($request->pago === 'pagado') {
            $query->where('i.saldo', '<=', 0);
        }
        Sede::filtrar($query, 'c.sede_id')->orderBy('i.id', 'desc');
        return response(Vacacionales::paginar($query, $request, fn ($i) => $this->formatear($i)), Response::HTTP_OK);
    }

    public function show($id)
    {
        $datos = $this->detalle((int) $id);
        return $datos
            ? response($datos, Response::HTTP_OK)
            : response(get_response_body(['La inscripción no existe.']), Response::HTTP_NOT_FOUND);
    }

    private function guardar(Request $request, ?int $id)
    {
        $actual = $id ? DB::table('vacacional_inscripciones')->find($id) : null;
        if ($id && !$actual) {
            return response(get_response_body(['La inscripción no existe.']), Response::HTTP_NOT_FOUND);
        }

        $datos = $request->all();
        // El alumno y el curso se eligen al inscribir; después solo cambian clases, valor y estado.
        $datos['alumno_id'] = $actual->alumno_id ?? $request->alumno_id;
        $datos['curso_id'] = $actual->curso_id ?? $request->curso_id;
        $validator = Validator::make($datos, [
            'alumno_id' => 'required|integer|exists:vacacional_alumnos,id',
            'curso_id' => 'required|integer|exists:vacacional_cursos,id',
            'fecha_inscripcion' => 'nullable|date',
            'valor' => 'nullable|numeric|min:0',
            'observacion' => 'nullable|string|max:255',
            'estado' => 'nullable|boolean',
            'fechas' => 'nullable|array',
            'fechas.*' => 'date_format:Y-m-d|distinct',
        ], [
            'alumno_id.required' => 'Elige el alumno.',
            'curso_id.required' => 'Elige el curso vacacional.',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }

        $curso = DB::table('vacacional_cursos')->find($datos['curso_id']);
        $fechasCurso = Vacacionales::fechasCurso($curso->id);
        // Si el curso tiene justo las clases que toma cada alumno, no hay nada que escoger: van todas.
        $fechas = array_values(array_unique((array) $request->fechas));
        if (!$fechas && count($fechasCurso) <= (int) $curso->clases_por_alumno) {
            $fechas = $fechasCurso;
        }

        $errores = [];
        if (!$fechas) {
            $errores[] = 'Elige en el calendario las clases que tomará el alumno.';
        } elseif (array_diff($fechas, $fechasCurso)) {
            $errores[] = 'Alguna de las fechas elegidas no es un día de clase del curso.';
        } elseif (count($fechas) > (int) $curso->clases_por_alumno) {
            $errores[] = "Este curso es de {$curso->clases_por_alumno} clases por alumno: elige máximo esa cantidad.";
        }
        if (!$id && DB::table('vacacional_inscripciones')->where('curso_id', $curso->id)->where('alumno_id', $datos['alumno_id'])->exists()) {
            $errores[] = 'El alumno ya está inscrito en este curso vacacional.';
        }
        $activa = !array_key_exists('estado', $datos) || $datos['estado'] === null || filter_var($datos['estado'], FILTER_VALIDATE_BOOLEAN);
        if (!$errores && $curso->cupo_max && $activa) {
            $llenas = DB::table('vacacional_inscripcion_fechas as f')
                ->join('vacacional_inscripciones as i', 'i.id', '=', 'f.inscripcion_id')
                ->where('i.curso_id', $curso->id)->where('i.estado', 1)->where('i.id', '!=', $id ?? 0)
                ->whereIn('f.fecha', $fechas)->groupBy('f.fecha')->havingRaw('COUNT(*) >= ?', [$curso->cupo_max])
                ->orderBy('f.fecha')->pluck('f.fecha')->all();
            if ($llenas) {
                $errores[] = 'Sin cupo el ' . Vacacionales::fechasTexto($llenas) . " (el cupo es de {$curso->cupo_max} por clase).";
            }
        }
        $pagado = $id ? (float) DB::table('vacacional_pagos')->where('inscripcion_id', $id)->sum('monto') : 0;
        $valor = $request->filled('valor') ? (float) $request->valor : (float) ($actual->valor ?? $curso->valor);
        if ($valor < $pagado) {
            $errores[] = 'El valor no puede ser menor que lo que ya pagó el alumno.';
        }
        if ($errores) {
            return response(get_response_body($errores), Response::HTTP_BAD_REQUEST);
        }

        $id = DB::transaction(function () use ($request, $datos, $id, $fechas, $valor, $pagado, $activa) {
            $fila = array_merge([
                'alumno_id' => $datos['alumno_id'],
                'curso_id' => $datos['curso_id'],
                'fecha_inscripcion' => $request->fecha_inscripcion ?: Carbon::today()->toDateString(),
                'valor' => $valor,
                'saldo' => max(0, $valor - $pagado),
                'observacion' => $request->observacion,
                'estado' => $activa,
            ], Vacacionales::auditoria(!$id));
            if ($id) {
                DB::table('vacacional_inscripciones')->where('id', $id)->update($fila);
            } else {
                $id = DB::table('vacacional_inscripciones')->insertGetId($fila);
            }
            DB::table('vacacional_inscripcion_fechas')->where('inscripcion_id', $id)->whereNotIn('fecha', $fechas)->delete();
            foreach ($fechas as $fecha) {
                DB::table('vacacional_inscripcion_fechas')->insertOrIgnore(['inscripcion_id' => $id, 'fecha' => $fecha]);
            }
            return $id;
        });
        return $this->detalle($id);
    }

    public function store(Request $request)
    {
        $inscripcion = $this->guardar($request, null);
        return is_array($inscripcion)
            ? response(get_response_body(['El alumno quedó inscrito en el curso vacacional.', 2], $inscripcion), Response::HTTP_CREATED)
            : $inscripcion;
    }

    public function update(Request $request, $id)
    {
        $inscripcion = $this->guardar($request, (int) $id);
        return is_array($inscripcion)
            ? response(get_response_body(['La inscripción ha sido modificada.', 1], $inscripcion), Response::HTTP_OK)
            : $inscripcion;
    }

    /**
     * Matrícula rápida de vacacionales: alumno (nuevo o existente) + uno o varios cursos con las
     * clases que toma en cada uno + pago, en un solo guardado. El pago puede ser un abono: se
     * aplica a los cursos en el orden en que llegan.
     */
    public function matricular(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'alumno_id' => 'nullable|integer|exists:vacacional_alumnos,id',
            'alumno' => 'nullable|array|required_without:alumno_id',
            'alumno.nombres' => 'required_without:alumno_id|string|max:100',
            'alumno.apellidos' => 'required_without:alumno_id|string|max:100',
            'alumno.documento' => 'nullable|string|max:30',
            'alumno.fecha_nacimiento' => 'nullable|date',
            'alumno.telefono' => 'nullable|string|max:30',
            'alumno.correo' => 'nullable|email|max:150',
            'alumno.acudiente_nombre' => 'nullable|string|max:150',
            'alumno.acudiente_telefono' => 'nullable|string|max:30',
            'cursos' => 'required|array|min:1',
            'cursos.*.curso_id' => 'required|integer|distinct|exists:vacacional_cursos,id',
            'cursos.*.fechas' => 'nullable|array',
            'cursos.*.valor' => 'nullable|numeric|min:0',
            'pago' => 'nullable|array',
            'pago.monto' => 'required_with:pago|numeric|gt:0',
            'pago.fecha_pago' => 'required_with:pago|date',
            'pago.metodo_pago' => 'required_with:pago|in:' . implode(',', Vacacionales::METODOS_PAGO),
            'pago.referencia' => 'nullable|string|max:100',
        ], [
            'alumno.required_without' => 'Indica los datos del alumno.',
            'cursos.required' => 'Elige al menos un curso vacacional.',
        ], [
            'alumno.nombres' => 'nombres',
            'alumno.apellidos' => 'apellidos',
            'alumno.correo' => 'correo',
            'pago.monto' => 'monto',
            'pago.fecha_pago' => 'fecha de pago',
            'pago.metodo_pago' => 'método de pago',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }

        DB::beginTransaction();
        try {
            $alumnoId = $request->alumno_id;
            if (!$alumnoId) {
                $campos = ['nombres', 'apellidos', 'documento', 'fecha_nacimiento', 'telefono', 'correo', 'acudiente_nombre', 'acudiente_telefono'];
                $alumnoId = DB::table('vacacional_alumnos')->insertGetId(array_merge(
                    array_fill_keys($campos, null),
                    array_intersect_key((array) $request->alumno, array_flip($campos)),
                    ['estado' => true],
                    Vacacionales::auditoria(true)
                ));
            }

            $inscripciones = [];
            foreach ($request->cursos as $curso) {
                $inscripcion = $this->guardar(new Request([
                    'alumno_id' => $alumnoId,
                    'curso_id' => $curso['curso_id'],
                    'fechas' => $curso['fechas'] ?? [],
                    'valor' => $curso['valor'] ?? null,
                ]), null);
                if (!is_array($inscripcion)) {
                    // Algún curso no se pudo inscribir: no se guarda nada (ni el alumno nuevo).
                    DB::rollBack();
                    $nombre = DB::table('vacacional_cursos')->where('id', $curso['curso_id'])->value('nombre');
                    $mensajes = $inscripcion->getOriginalContent()['mensajes'] ?? ['No se pudo inscribir.'];
                    return response(get_response_body(array_map(fn ($m) => "{$nombre}: {$m}", $mensajes)), Response::HTTP_BAD_REQUEST);
                }
                $inscripciones[] = $inscripcion;
            }

            $total = array_sum(array_column($inscripciones, 'valor'));
            $pagado = 0;
            if ($request->pago) {
                $resto = (float) $request->input('pago.monto');
                if ($resto > $total) {
                    DB::rollBack();
                    return response(get_response_body(['El monto supera el total a pagar.']), Response::HTTP_BAD_REQUEST);
                }
                foreach ($inscripciones as $inscripcion) {
                    $abono = min($resto, $inscripcion['saldo']);
                    if ($abono <= 0) {
                        continue;
                    }
                    DB::table('vacacional_pagos')->insert(array_merge([
                        'inscripcion_id' => $inscripcion['id'],
                        'monto' => $abono,
                        'fecha_pago' => $request->input('pago.fecha_pago'),
                        'metodo_pago' => $request->input('pago.metodo_pago'),
                        'referencia' => $request->input('pago.referencia'),
                    ], Vacacionales::auditoria(true)));
                    Vacacionales::recalcularSaldo($inscripcion['id']);
                    $resto -= $abono;
                    $pagado += $abono;
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response(get_response_body(['No se pudo registrar la matrícula: ' . $e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $alumno = DB::table('vacacional_alumnos')->find($alumnoId);
        return response(get_response_body(['La matrícula del vacacional ha sido registrada.', 2], [
            'alumno' => ['id' => $alumno->id, 'nombre' => trim("{$alumno->nombres} {$alumno->apellidos}")],
            'cursos' => array_map(fn ($i) => ['nombre' => $i['curso_nombre'], 'fechas_texto' => $i['fechas_texto'], 'valor' => $i['valor']], $inscripciones),
            'total' => $total,
            'pagado' => $pagado,
            'saldo' => $total - $pagado,
        ]), Response::HTTP_CREATED);
    }

    public function destroy($id)
    {
        if (DB::table('vacacional_pagos')->where('inscripcion_id', $id)->exists()) {
            return response(
                get_response_body(['La inscripción tiene pagos registrados: quítalos primero o márcala como anulada.']),
                Response::HTTP_CONFLICT
            );
        }
        DB::table('vacacional_inscripciones')->where('id', $id)->delete();
        return response(get_response_body(['La inscripción ha sido eliminada.', 3]), Response::HTTP_OK);
    }

    public function registrarPago(Request $request, $id)
    {
        $inscripcion = DB::table('vacacional_inscripciones')->find($id);
        if (!$inscripcion) {
            return response(get_response_body(['La inscripción no existe.']), Response::HTTP_NOT_FOUND);
        }
        $validator = Validator::make($request->all(), [
            'monto' => 'required|numeric|gt:0',
            'fecha_pago' => 'required|date',
            'metodo_pago' => 'required|in:' . implode(',', Vacacionales::METODOS_PAGO),
            'referencia' => 'nullable|string|max:100',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }
        if ((float) $request->monto > (float) $inscripcion->saldo) {
            return response(get_response_body(['El monto supera lo que falta por pagar.']), Response::HTTP_BAD_REQUEST);
        }

        DB::transaction(function () use ($request, $id) {
            DB::table('vacacional_pagos')->insert(array_merge([
                'inscripcion_id' => $id,
                'monto' => $request->monto,
                'fecha_pago' => $request->fecha_pago,
                'metodo_pago' => $request->metodo_pago,
                'referencia' => $request->referencia,
            ], Vacacionales::auditoria(true)));
            Vacacionales::recalcularSaldo((int) $id);
        });
        return response(get_response_body(['Pago registrado.', 2], $this->detalle((int) $id)), Response::HTTP_CREATED);
    }

    public function eliminarPago($id, $pagoId)
    {
        DB::transaction(function () use ($id, $pagoId) {
            DB::table('vacacional_pagos')->where('id', $pagoId)->where('inscripcion_id', $id)->delete();
            Vacacionales::recalcularSaldo((int) $id);
        });
        return response(get_response_body(['Pago eliminado.', 3], $this->detalle((int) $id)), Response::HTTP_OK);
    }
}
