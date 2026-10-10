<?php

namespace App\Http\Controllers\Academia;

use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\Academia\ClasesPrivadas;
use App\Services\Academia\CalendarioCurso;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\Academia\Pago;
use App\Models\Academia\Sede;
use App\Services\Academia\Paquetes;
use Illuminate\Support\Facades\Validator;

/** Paquetes de clases personalizadas comprados por los alumnos. */
class PaqueteController extends Controller
{
    private function consulta()
    {
        return DB::table('paquetes_alumno as pa')
            ->join('alumnos as a', 'a.id', '=', 'pa.alumno_id')
            ->leftJoin('planes as p', 'p.id', '=', 'pa.plan_id')
            ->leftJoin('sedes as s', 's.id', '=', 'pa.sede_id')
            ->select('pa.*', DB::raw("CONCAT(a.nombres,' ',a.apellidos) as alumno_nombre"), 'p.nombre as plan_nombre', 's.nombre as sede_nombre');
    }

    private function formatear(object $p, ?int $usadas = null): array
    {
        $resumen = Paquetes::resumen($p, $usadas);
        return array_merge([
            'id' => $p->id,
            'alumno_id' => $p->alumno_id,
            'alumno_nombre' => $p->alumno_nombre,
            'sede_id' => $p->sede_id,
            'sede_nombre' => $p->sede_nombre,
            'plan_id' => $p->plan_id,
            'plan_nombre' => $p->plan_nombre,
            'fecha_compra' => $p->fecha_compra,
            'fecha_vencimiento' => $p->fecha_vencimiento,
            'valor' => (float) $p->valor,
            'saldo' => (float) $p->saldo,
            'estado' => $p->estado,
            'observacion' => $p->observacion,
            // Para selectores (pagos): texto corto identificable.
            'nombre' => "#{$p->id} · " . ($p->plan_nombre ?? 'Paquete') . " · {$resumen['clases_restantes']} de {$p->clases_total} clases",
            'usuario_creacion_nombre' => $p->usuario_creacion_nombre,
            'usuario_modificacion_nombre' => $p->usuario_modificacion_nombre,
            'fecha_creacion' => Carbon::parse($p->created_at)->format('Y-m-d H:i:s'),
            'fecha_modificacion' => Carbon::parse($p->updated_at)->format('Y-m-d H:i:s'),
        ], $resumen);
    }

    public function index(Request $request)
    {
        $query = $this->consulta();
        if ($request->filled('alumno_id')) {
            $query->where('pa.alumno_id', $request->alumno_id);
        }
        if ($request->filled('nombre')) {
            $query->where(DB::raw("CONCAT(a.nombres,' ',a.apellidos)"), 'like', '%' . $request->nombre . '%');
        }
        $query->orderBy('pa.fecha_compra', 'desc')->orderBy('pa.id', 'desc');

        // Selector de pagos: paquetes del alumno con saldo por pagar (de cualquier sede: el paquete sirve en todas).
        if ($request->ligera) {
            $paquetes = $query->where('pa.estado', Paquetes::ACTIVO)->get();
            $usadas = Paquetes::usadas($paquetes->pluck('id')->all());
            return response($paquetes->map(fn ($p) => $this->formatear($p, $usadas[$p->id] ?? 0))->values(), Response::HTTP_OK);
        }

        $pagina = Sede::filtrar($query, 'pa.sede_id')->paginate((int) ($request->limite ?? 100));
        $usadas = Paquetes::usadas($pagina->getCollection()->pluck('id')->all());
        $datos = $pagina->getCollection()->map(fn ($p) => $this->formatear($p, $usadas[$p->id] ?? 0));
        if ($request->filled('estado')) {
            $datos = $datos->where('estado_efectivo', $request->estado);
        }
        return response([
            'datos' => $datos->values()->all(),
            'desde' => $pagina->firstItem(),
            'hasta' => $pagina->lastItem(),
            'por_pagina' => $pagina->perPage(),
            'pagina_actual' => $pagina->currentPage(),
            'ultima_pagina' => $pagina->lastPage(),
            'total' => $pagina->total(),
        ], Response::HTTP_OK);
    }

    public function show($id)
    {
        $paquete = $this->consulta()->where('pa.id', $id)->first();
        if (!$paquete) {
            return response(get_response_body(['El paquete no existe.']), Response::HTTP_NOT_FOUND);
        }
        return response($this->detalle($paquete), Response::HTTP_OK);
    }

    private function detalle(object $paquete): array
    {
        $datos = $this->formatear($paquete);
        $datos['clases'] = $this->clasesDe($paquete);
        $datos['horas_cancelacion'] = ClasesPrivadas::horasCancelacion();
        $datos['pagos'] = DB::table('pagos')->where('paquete_id', $paquete->id)->orderBy('fecha_pago')
            ->get(['id', 'monto', 'fecha_pago', 'metodo_pago', 'referencia']);
        return $datos;
    }

    /**
     * Clases del paquete, para la planilla "una fila por clase": las que ya descontaron y las
     * registradas aquí que no descuentan (programadas o canceladas a tiempo).
     * `editable` = se registró desde el paquete y se puede corregir en la planilla.
     */
    private function clasesDe(object $paquete)
    {
        $consumos = DB::table('consumos_paquete as c')
            ->leftJoin('clases_privadas as cp', 'cp.id', '=', 'c.clase_privada_id')
            ->leftJoin('clase_privada_alumno as cpa', fn ($j) => $j->on('cpa.clase_privada_id', '=', 'c.clase_privada_id')
                ->on('cpa.alumno_id', '=', 'c.alumno_id'))
            ->leftJoin('profesores as p', 'p.id', '=', 'cp.profesor_id')
            ->where('c.paquete_id', $paquete->id)
            ->select('c.fecha', 'c.origen', 'c.motivo', 'c.clase_privada_id', 'cp.hora', 'cp.duracion_min', 'cp.profesor_id',
                'cp.observacion', 'cpa.paquete_id as registrada_en', DB::raw("CONCAT(p.nombres,' ',p.apellidos) as profesor_nombre"))
            ->get()
            ->map(fn ($c) => [
                'clase_id' => $c->clase_privada_id,
                'origen' => $c->origen,
                'fecha' => $c->fecha,
                'hora' => $c->hora ? substr((string) $c->hora, 0, 5) : null,
                'duracion_min' => $c->duracion_min,
                'profesor_id' => $c->profesor_id,
                'profesor_nombre' => $c->profesor_nombre,
                'observacion' => $c->observacion,
                'resultado' => $c->motivo === 'cancelacion_tardia' ? 'cancelo_tarde' : $c->motivo,
                'descuenta' => true,
                'editable' => (int) $c->registrada_en === (int) $paquete->id,
            ]);

        $sinDescontar = DB::table('clase_privada_alumno as cpa')
            ->join('clases_privadas as cp', 'cp.id', '=', 'cpa.clase_privada_id')
            ->leftJoin('profesores as p', 'p.id', '=', 'cp.profesor_id')
            ->where('cpa.paquete_id', $paquete->id)
            ->where('cpa.descuenta', false)
            ->select('cp.*', 'cpa.resultado', DB::raw("CONCAT(p.nombres,' ',p.apellidos) as profesor_nombre"))
            ->get()
            ->map(fn ($c) => [
                'clase_id' => $c->id,
                'origen' => 'privada',
                'fecha' => $c->fecha,
                'hora' => substr((string) $c->hora, 0, 5),
                'duracion_min' => $c->duracion_min,
                'profesor_id' => $c->profesor_id,
                'profesor_nombre' => $c->profesor_nombre,
                'observacion' => $c->observacion,
                'resultado' => $c->resultado === 'cancelo' ? 'cancelo_a_tiempo' : 'pendiente',
                'descuenta' => false,
                'editable' => true,
            ]);

        return $consumos->concat($sinDescontar)->sortBy(fn ($c) => $c['fecha'] . ' ' . ($c['hora'] ?? ''))->values();
    }

    /**
     * Registra (o corrige) una clase personalizada del paquete desde su planilla.
     * resultado: pendiente (programada) | asistio | no_asistio | cancelo_a_tiempo | cancelo_tarde.
     */
    public function guardarClase(Request $request, $id, $claseId = null)
    {
        $paquete = $this->consulta()->where('pa.id', $id)->first();
        if (!$paquete) {
            return response(get_response_body(['El paquete no existe.']), Response::HTTP_NOT_FOUND);
        }
        $validator = Validator::make($request->all(), [
            'fecha' => 'required|date',
            'hora' => 'required|date_format:H:i',
            'duracion_min' => 'nullable|integer|between:15,300',
            'profesor_id' => 'nullable|integer|exists:profesores,id',
            'resultado' => 'required|in:pendiente,asistio,no_asistio,cancelo_a_tiempo,cancelo_tarde',
            'observacion' => 'nullable|string',
        ], ['fecha.required' => 'Indica la fecha de la clase.', 'hora.required' => 'Indica la hora de la clase.']);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }
        $fila = $claseId ? DB::table('clase_privada_alumno')->where('clase_privada_id', $claseId)->where('paquete_id', $id)->first() : null;
        if ($claseId && !$fila) {
            return response(get_response_body(['Esa clase no se registró desde este paquete.']), Response::HTTP_NOT_FOUND);
        }
        $fecha = Carbon::parse($request->fecha);
        $sedeId = $paquete->sede_id ?: Sede::porDefecto();
        if ($noLectivo = (new CalendarioCurso())->noLectivo($fecha, (int) $sedeId)) {
            $tipo = $noLectivo['tipo'] === 'festivo' ? 'es festivo' : 'la academia está cerrada';
            return response(get_response_body([$fecha->format('d/m/Y') . " {$tipo} ({$noLectivo['motivo']})."]), Response::HTTP_BAD_REQUEST);
        }

        try {
            DB::transaction(function () use ($request, $paquete, $claseId, $fila, $fecha, $sedeId) {
                $usuario = Auth::user()->usuario();
                $datos = [
                    'fecha' => $fecha->toDateString(),
                    'hora' => $request->hora,
                    'duracion_min' => $request->duracion_min ?: 60,
                    'profesor_id' => $request->profesor_id,
                    'observacion' => $request->observacion,
                    'usuario_modificacion_id' => $usuario->id,
                    'usuario_modificacion_nombre' => $usuario->nombre,
                    'updated_at' => Carbon::now(),
                ];
                if ($claseId) {
                    DB::table('clases_privadas')->where('id', $claseId)->update($datos);
                } else {
                    $claseId = DB::table('clases_privadas')->insertGetId(array_merge($datos, [
                        'sede_id' => $sedeId,
                        'estado' => 'programada',
                        'usuario_creacion_id' => $usuario->id,
                        'usuario_creacion_nombre' => $usuario->nombre,
                        'created_at' => Carbon::now(),
                    ]));
                    $filaId = DB::table('clase_privada_alumno')->insertGetId([
                        'clase_privada_id' => $claseId, 'alumno_id' => $paquete->alumno_id, 'paquete_id' => $paquete->id,
                        'resultado' => 'pendiente', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
                    ]);
                    $fila = DB::table('clase_privada_alumno')->find($filaId);
                }

                $cancelo = str_starts_with($request->resultado, 'cancelo');
                $descontada = ClasesPrivadas::aplicarResultado(
                    DB::table('clases_privadas')->find($claseId),
                    $fila,
                    $cancelo ? 'cancelo' : $request->resultado,
                    $cancelo ? $request->resultado === 'cancelo_a_tiempo' : null,
                );
                if (!$descontada) {
                    throw new Exception('El paquete no tiene clases disponibles para esa fecha (está agotado, vencido o anulado).');
                }
                ClasesPrivadas::actualizarEstado((int) $claseId);
            });
        } catch (Exception $e) {
            return response(get_response_body([$e->getMessage()]), Response::HTTP_CONFLICT);
        }

        return response(get_response_body(['La clase ha sido registrada.', 1],
            $this->detalle($this->consulta()->where('pa.id', $id)->first())), Response::HTTP_OK);
    }

    /** Quita una clase registrada desde el paquete; si había descontado, la clase vuelve al paquete. */
    public function eliminarClase($id, $claseId)
    {
        $paquete = $this->consulta()->where('pa.id', $id)->first();
        $esDelPaquete = DB::table('clase_privada_alumno')->where('clase_privada_id', $claseId)->where('paquete_id', $id)->exists();
        if (!$paquete || !$esDelPaquete) {
            return response(get_response_body(['Esa clase no se registró desde este paquete.']), Response::HTTP_NOT_FOUND);
        }
        DB::table('clases_privadas')->where('id', $claseId)->delete();
        return response(get_response_body(['La clase ha sido quitada del paquete.', 3], $this->detalle($paquete)), Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'alumno_id' => 'required|integer|exists:alumnos,id',
            'sede_id' => 'nullable|integer|exists:sedes,id',
            'plan_id' => 'nullable|integer|exists:planes,id',
            'clases_total' => 'required|integer|min:1|max:500',
            'fecha_compra' => 'required|date',
            'fecha_vencimiento' => 'nullable|date|after_or_equal:fecha_compra',
            'valor' => 'required|numeric|min:0',
            'observacion' => 'nullable|string',
        ], ['fecha_vencimiento.after_or_equal' => 'El vencimiento no puede ser anterior a la compra.']);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }

        $usuario = Auth::user()->usuario();
        $id = DB::table('paquetes_alumno')->insertGetId([
            'alumno_id' => $request->alumno_id,
            'sede_id' => $request->sede_id ?: Sede::porDefecto(),
            'plan_id' => $request->plan_id,
            'clases_total' => $request->clases_total,
            'fecha_compra' => Carbon::parse($request->fecha_compra)->toDateString(),
            'fecha_vencimiento' => $request->fecha_vencimiento ? Carbon::parse($request->fecha_vencimiento)->toDateString() : null,
            'valor' => $request->valor,
            'saldo' => $request->valor,
            'estado' => Paquetes::ACTIVO,
            'observacion' => $request->observacion,
            'usuario_creacion_id' => $usuario->id,
            'usuario_creacion_nombre' => $usuario->nombre,
            'usuario_modificacion_id' => $usuario->id,
            'usuario_modificacion_nombre' => $usuario->nombre,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        return response(get_response_body(['El paquete ha sido registrado. Su pago se registra con «Registrar pago» en la lista de paquetes.', 2],
            $this->formatear($this->consulta()->where('pa.id', $id)->first())), Response::HTTP_CREATED);
    }

    /** Se permite extender el vencimiento, anular/reactivar y la observación (no se cambian clases ni valor). */
    public function update(Request $request, $id)
    {
        $paquete = DB::table('paquetes_alumno')->find($id);
        if (!$paquete) {
            return response(get_response_body(['El paquete no existe.']), Response::HTTP_NOT_FOUND);
        }
        $validator = Validator::make($request->all(), [
            'fecha_vencimiento' => 'nullable|date|after_or_equal:' . $paquete->fecha_compra,
            'estado' => 'required|in:activo,anulado',
            'observacion' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }
        $usuario = Auth::user()->usuario();
        DB::table('paquetes_alumno')->where('id', $id)->update([
            'fecha_vencimiento' => $request->fecha_vencimiento ? Carbon::parse($request->fecha_vencimiento)->toDateString() : null,
            'estado' => $request->estado,
            'observacion' => $request->observacion,
            'usuario_modificacion_id' => $usuario->id,
            'usuario_modificacion_nombre' => $usuario->nombre,
            'updated_at' => Carbon::now(),
        ]);
        return response(get_response_body(['El paquete ha sido modificado.', 1],
            $this->formatear($this->consulta()->where('pa.id', $id)->first())), Response::HTTP_OK);
    }

    /** Registra un pago (total o abono) del paquete. Permiso: PagarPaquete. */
    public function registrarPago(Request $request, $id)
    {
        $paquete = $this->consulta()->where('pa.id', $id)->first();
        if (!$paquete) {
            return response(get_response_body(['El paquete no existe.']), Response::HTTP_NOT_FOUND);
        }
        $validator = Validator::make($request->all(), [
            'monto' => 'required|numeric|gt:0',
            'fecha_pago' => 'required|date',
            'metodo_pago' => 'required|in:efectivo,transferencia,tarjeta,otro',
            'referencia' => 'nullable|string|max:100',
            'observacion' => 'nullable|string',
        ], ['monto.gt' => 'El monto debe ser mayor que cero.']);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }
        if ($paquete->estado === Paquetes::ANULADO) {
            return response(get_response_body(['El paquete está anulado: no recibe pagos.']), Response::HTTP_CONFLICT);
        }
        if ((float) $request->monto > (float) $paquete->saldo + 0.001) {
            return response(get_response_body(['El monto supera lo que falta por pagar del paquete ($' . number_format((float) $paquete->saldo, 0, ',', '.') . ').']), Response::HTTP_BAD_REQUEST);
        }
        try {
            DB::transaction(fn () => Pago::modificarOCrear([
                'alumno_id' => $paquete->alumno_id,
                'paquete_id' => $paquete->id,
                'plan_id' => $paquete->plan_id,
                'sede_id' => $paquete->sede_id,
                'monto' => $request->monto,
                'fecha_pago' => Carbon::parse($request->fecha_pago)->toDateString(),
                'metodo_pago' => $request->metodo_pago,
                'referencia' => $request->referencia,
                'observacion' => $request->observacion,
            ]));
        } catch (Exception $e) {
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return response(get_response_body(['El pago del paquete ha sido registrado.', 2],
            $this->detalle($this->consulta()->where('pa.id', $id)->first())), Response::HTTP_CREATED);
    }

    /** Quita un pago del paquete (para corregirlo); lo pagado vuelve a quedar como saldo. Permiso: PagarPaquete. */
    public function eliminarPago($id, $pagoId)
    {
        if (!DB::table('pagos')->where('id', $pagoId)->where('paquete_id', $id)->exists()) {
            return response(get_response_body(['Ese pago no es de este paquete.']), Response::HTTP_NOT_FOUND);
        }
        try {
            DB::transaction(fn () => Pago::eliminar($pagoId));
        } catch (Exception $e) {
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return response(get_response_body(['El pago ha sido eliminado.', 3],
            $this->detalle($this->consulta()->where('pa.id', $id)->first())), Response::HTTP_OK);
    }

    public function destroy($id)
    {
        if (DB::table('consumos_paquete')->where('paquete_id', $id)->exists() || DB::table('pagos')->where('paquete_id', $id)->exists()) {
            return response(get_response_body(['No se puede eliminar: el paquete tiene clases usadas o pagos. Puedes anularlo.']), Response::HTTP_CONFLICT);
        }
        DB::table('paquetes_alumno')->where('id', $id)->delete();
        return response(get_response_body(['El paquete ha sido eliminado.', 3]), Response::HTTP_OK);
    }
}
