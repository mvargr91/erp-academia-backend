<?php

namespace App\Http\Controllers\Academia;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Services\Academia\Vacacionales;
use Illuminate\Support\Facades\Validator;

/**
 * Toma de asistencia de vacacionales: por curso y día de clase, la lista de los alumnos que tienen
 * esa clase entre las suyas. Es aparte de la asistencia de los cursos de la academia.
 */
class VacacionalAsistenciaController extends Controller
{
    private function clases(int $cursoId)
    {
        return DB::table('vacacional_inscripcion_fechas as f')
            ->join('vacacional_inscripciones as i', 'i.id', '=', 'f.inscripcion_id')
            ->where('i.curso_id', $cursoId)->where('i.estado', 1);
    }

    /** Días de clase del curso con su avance y, si llega `fecha`, la lista de ese día. */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'curso_id' => 'required|integer|exists:vacacional_cursos,id',
            'fecha' => 'nullable|date_format:Y-m-d',
        ], ['curso_id.required' => 'Elige el curso vacacional.']);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }
        $cursoId = (int) $request->curso_id;

        $porDia = $this->clases($cursoId)->groupBy('f.fecha')
            ->select('f.fecha', DB::raw('COUNT(*) as programados'), DB::raw('SUM(f.asistio = 1) as asistieron'), DB::raw('SUM(f.asistio IS NOT NULL) as marcados'))
            ->get()->keyBy('fecha');
        $dias = array_map(fn ($fecha) => [
            'fecha' => $fecha,
            'programados' => (int) ($porDia[$fecha]->programados ?? 0),
            'asistieron' => (int) ($porDia[$fecha]->asistieron ?? 0),
            'tomada' => (int) ($porDia[$fecha]->marcados ?? 0) > 0,
        ], Vacacionales::fechasCurso($cursoId));

        $alumnos = [];
        if ($request->filled('fecha')) {
            $alumnos = $this->clases($cursoId)->where('f.fecha', $request->fecha)
                ->join('vacacional_alumnos as a', 'a.id', '=', 'i.alumno_id')
                ->orderBy('a.nombres')->orderBy('a.apellidos')
                ->get(['f.inscripcion_id', 'f.asistio', 'i.saldo', 'a.telefono', 'a.acudiente_nombre', 'a.acudiente_telefono',
                    DB::raw("CONCAT(a.nombres,' ',a.apellidos) as alumno_nombre")])
                ->map(fn ($a) => [
                    'inscripcion_id' => $a->inscripcion_id,
                    'alumno_nombre' => $a->alumno_nombre,
                    'telefono' => $a->telefono,
                    'acudiente_nombre' => $a->acudiente_nombre,
                    'acudiente_telefono' => $a->acudiente_telefono,
                    'saldo' => (float) $a->saldo,
                    'asistio' => $a->asistio === null ? null : (bool) $a->asistio,
                ])->all();
        }
        return response(['dias' => $dias, 'alumnos' => $alumnos], Response::HTTP_OK);
    }

    /** Guarda la asistencia de un día: quién asistió y quién no, de los que tenían clase. */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'curso_id' => 'required|integer|exists:vacacional_cursos,id',
            'fecha' => 'required|date_format:Y-m-d',
            'asistencias' => 'required|array|min:1',
            'asistencias.*.inscripcion_id' => 'required|integer|distinct',
            'asistencias.*.asistio' => 'required|boolean',
        ], ['asistencias.required' => 'No hay alumnos con clase ese día.']);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }

        // Solo se marcan las clases de ese curso y ese día.
        $validas = $this->clases((int) $request->curso_id)->where('f.fecha', $request->fecha)->pluck('f.id', 'f.inscripcion_id');
        $usuario = Auth::user()->usuario();
        $ahora = Carbon::now();
        DB::transaction(function () use ($request, $validas, $usuario, $ahora) {
            foreach ($request->asistencias as $a) {
                if (isset($validas[$a['inscripcion_id']])) {
                    DB::table('vacacional_inscripcion_fechas')->where('id', $validas[$a['inscripcion_id']])->update([
                        'asistio' => filter_var($a['asistio'], FILTER_VALIDATE_BOOLEAN),
                        'asistencia_usuario_id' => $usuario->id,
                        'asistencia_usuario_nombre' => $usuario->nombre,
                        'asistencia_en' => $ahora,
                    ]);
                }
            }
        });
        return response(get_response_body(['Asistencia guardada.', 1]), Response::HTTP_OK);
    }
}
