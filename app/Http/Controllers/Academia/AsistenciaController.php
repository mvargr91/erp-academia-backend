<?php

namespace App\Http\Controllers\Academia;

use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\Academia\Paquetes;
use App\Services\Academia\CalendarioCurso;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Academia\Asistencia;
use Illuminate\Support\Facades\Validator;

class AsistenciaController extends Controller
{
    private function reglas()
    {
        return [
            'curso_id' => 'integer|required|exists:cursos,id',
            'fecha_sesion' => 'date|required',
            'observacion' => 'string|nullable',
            'asistentes' => 'array|nullable',
            'asistentes.*.alumno_id' => 'integer|required|exists:alumnos,id',
            'asistentes.*.presente' => 'boolean',
        ];
    }

    public function index(Request $request)
    {
        try {
            $datos = $request->all();
            $validator = Validator::make($datos, ['limite' => 'integer|between:1,500']);
            if ($validator->fails()) {
                return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
            }
            if (isset($datos['ordenar_por'])) {
                $datos['ordenar_por'] = format_order_by_attributes($datos);
            }
            return response(Asistencia::obtenerColeccion($datos), Response::HTTP_OK);
        } catch (Exception $e) {
            return response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $datos = $request->all();
            $validator = Validator::make($datos, $this->reglas());
            if ($validator->fails()) {
                return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
            }

            if ($error = $this->errorDeFecha($datos)) {
                DB::rollback();
                return response(get_response_body([$error]), Response::HTTP_BAD_REQUEST);
            }

            // Una sola sesión por curso y fecha.
            $existe = DB::table('asistencias')
                ->where('curso_id', $datos['curso_id'])
                ->where('fecha_sesion', $datos['fecha_sesion'])
                ->exists();
            if ($existe) {
                DB::rollback();
                return response(get_response_body(['Ya existe una toma de asistencia para este curso en esa fecha.']), Response::HTTP_CONFLICT);
            }

            $asistencia = Asistencia::modificarOCrear($datos);
            if ($asistencia) {
                DB::commit();
                return response(get_response_body(['La asistencia ha sido registrada.', 2], $asistencia), Response::HTTP_CREATED);
            }
            DB::rollback();
            return response(get_response_body(['Ocurrió un error al intentar registrar la asistencia.']), Response::HTTP_CONFLICT);
        } catch (Exception $e) {
            DB::rollback();
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /** La asistencia solo se toma en fechas de clase del curso (su día, sin festivos ni cierres). */
    private function errorDeFecha(array $datos): ?string
    {
        $curso = DB::table('cursos')->where('id', $datos['curso_id'])->first();
        $validacion = (new CalendarioCurso())->validarFecha($curso, Carbon::parse($datos['fecha_sesion']));
        return $validacion['valida'] ? null : $validacion['motivo'];
    }

    /**
     * Datos para tomar lista: si la fecha es de clase (o cuál sugerir) y, por alumno matriculado,
     * en qué clase de su ciclo va (p. ej. 2 de 4).
     */
    public function preparar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'curso_id' => 'integer|required|exists:cursos,id',
            'fecha' => 'date|required',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }

        $curso = DB::table('cursos as c')
            ->leftJoin('planes as p', 'p.id', '=', 'c.plan_id')
            ->where('c.id', $request->curso_id)
            ->select('c.id', 'c.sede_id', 'c.dia', 'c.fecha_inicio', 'p.num_clases', 'p.periodicidad')
            ->first();
        $calendario = new CalendarioCurso();
        $fecha = Carbon::parse($request->fecha);
        $validacion = $calendario->validarFecha($curso, $fecha);
        $clasesPorCiclo = CalendarioCurso::clasesPorCiclo($curso->num_clases);

        $alumnos = DB::table('curso_alumno as ca')
            ->join('alumnos as a', 'a.id', '=', 'ca.alumno_id')
            ->where('ca.curso_id', $curso->id)
            ->where('ca.estado', 1)
            ->select('a.id as alumno_id', DB::raw("CONCAT(a.nombres,' ',a.apellidos) as nombre"),
                'ca.fecha_matricula', 'ca.ciclo_inicio', 'ca.saldo', 'ca.modalidad')
            ->orderBy('a.nombres')
            ->get()
            ->map(function ($m) use ($calendario, $curso, $clasesPorCiclo, $fecha, $validacion) {
                $numero = null;
                if ($m->modalidad === 'paquete') {
                    return [
                        'alumno_id' => $m->alumno_id,
                        'nombre' => $m->nombre,
                        'modalidad' => 'paquete',
                        'paquete' => Paquetes::disponiblesHoy($m->alumno_id),
                        'saldo' => (float) $m->saldo,
                    ];
                }
                if ($validacion['valida']) {
                    $ciclo = $calendario->ciclo($curso, $m, $clasesPorCiclo);
                    // Si la fecha ya pasó el ciclo registrado, se cuenta dentro del ciclo siguiente.
                    for ($i = 0; $i < 12 && !($numero = $calendario->numeroEnCiclo($ciclo, $fecha))
                        && $fecha->gte($ciclo['proximo_pago']); $i++) {
                        $m->ciclo_inicio = $ciclo['proximo_pago']->toDateString();
                        $ciclo = $calendario->ciclo($curso, $m, $clasesPorCiclo);
                    }
                }
                return [
                    'alumno_id' => $m->alumno_id,
                    'nombre' => $m->nombre,
                    'clase_numero' => $numero,
                    'clases_ciclo' => $clasesPorCiclo,
                    'modalidad' => 'ciclo',
                    'saldo' => (float) $m->saldo,
                ];
            });

        return response(array_merge($validacion, ['alumnos' => $alumnos]), Response::HTTP_OK);
    }

    public function show($id)
    {
        try {
            $datos['id'] = $id;
            $validator = Validator::make($datos, ['id' => 'integer|required|exists:asistencias,id']);
            if ($validator->fails()) {
                return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
            }
            return response(Asistencia::cargar($id), Response::HTTP_OK);
        } catch (Exception $e) {
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $datos = $request->all();
            $datos['id'] = $id;
            $validator = Validator::make($datos, array_merge(
                ['id' => 'integer|required|exists:asistencias,id'],
                $this->reglas()
            ));
            if ($validator->fails()) {
                return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
            }

            // Solo se valida el calendario si cambia la fecha o el curso (las ya tomadas se pueden corregir).
            $actual = DB::table('asistencias')->where('id', $id)->first();
            $cambioFecha = Carbon::parse($actual->fecha_sesion)->toDateString() !== Carbon::parse($datos['fecha_sesion'])->toDateString()
                || (int) $actual->curso_id !== (int) $datos['curso_id'];
            if ($cambioFecha && ($error = $this->errorDeFecha($datos))) {
                DB::rollback();
                return response(get_response_body([$error]), Response::HTTP_BAD_REQUEST);
            }

            $asistencia = Asistencia::modificarOCrear($datos);
            if ($asistencia) {
                DB::commit();
                return response(get_response_body(['La asistencia ha sido modificada.', 1], $asistencia), Response::HTTP_OK);
            }
            DB::rollback();
            return response(get_response_body(['Ocurrió un error al intentar modificar la asistencia.']), Response::HTTP_CONFLICT);
        } catch (Exception $e) {
            DB::rollback();
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $datos['id'] = $id;
            $validator = Validator::make($datos, ['id' => 'integer|required|exists:asistencias,id']);
            if ($validator->fails()) {
                return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
            }

            $eliminado = Asistencia::eliminar($id);
            if ($eliminado) {
                DB::commit();
                return response(get_response_body(['La asistencia ha sido eliminada.', 3]), Response::HTTP_OK);
            }
            DB::rollback();
            return response(get_response_body(['Ocurrió un error al intentar eliminar la asistencia.']), Response::HTTP_CONFLICT);
        } catch (Exception $e) {
            DB::rollback();
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
