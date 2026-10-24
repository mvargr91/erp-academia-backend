<?php

namespace App\Http\Controllers\Academia;

use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Academia\Profesor;
use App\Models\Academia\Sede;
use App\Services\Academia\CalendarioCurso;

/**
 * Home del profesor: sus cursos activos con lo necesario para ir a tomar asistencia o ver el
 * calendario. Quien ve todos los cursos (no es un profesor restringido) recibe todos.
 */
class MisCursosController extends Controller
{
    /**
     * GET /v1/mis-cursos → { profesor: bool, cursos: [{ id, nombre, dia, hora, duracion_minutos, sede, modalidad,
     * matriculados, clase_hoy, asistencia_hoy, proxima_clase }] }, primero los que tienen clase hoy.
     */
    public function index()
    {
        $profesorId = Profesor::soloSusCursos();
        $query = DB::table('cursos as c')
            ->leftJoin('ritmos as r', 'r.id', '=', 'c.ritmo_id')
            ->leftJoin('sedes as s', 's.id', '=', 'c.sede_id')
            ->leftJoin('modalidades as m', 'm.id', '=', 'c.modalidad_id')
            ->where('c.estado', 1)
            ->where('c.activo', 1)
            ->when($profesorId, fn ($q) => $q->where('c.profesor_id', $profesorId))
            ->select(
                'c.id', 'c.sede_id', 'c.dia', 'c.hora', 'c.fecha_inicio', 'c.modalidad_id',
                DB::raw('COALESCE(c.nombre, r.nombre) as nombre'), 'r.nombre as ritmo', 's.nombre as sede', 'm.nombre as modalidad',
                DB::raw('COALESCE(c.duracion_minutos, m.duracion_minutos) as duracion_minutos'),
                DB::raw('(SELECT COUNT(*) FROM curso_alumno ca WHERE ca.curso_id = c.id AND ca.estado = 1) as matriculados'),
            );
        // El selector de sede del encabezado aplica a todos, también al profesor.
        Sede::filtrar($query, 'c.sede_id');

        $hoy = Carbon::today();
        $calendario = new CalendarioCurso();
        $tomadasHoy = DB::table('asistencias')->whereDate('fecha_sesion', $hoy)->pluck('curso_id')->flip();
        $cursos = $query->get()->map(function ($c) use ($calendario, $hoy, $tomadasHoy) {
            $proxima = $calendario->proximasClases($c, $hoy, 1)[0] ?? null;
            return [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'ritmo' => $c->ritmo,
                'dia' => (int) $c->dia,
                'hora' => substr((string) $c->hora, 0, 5),
                'duracion_minutos' => $c->duracion_minutos === null ? null : (int) $c->duracion_minutos,
                'sede' => $c->sede,
                'modalidad' => $c->modalidad,
                'matriculados' => (int) $c->matriculados,
                'clase_hoy' => $proxima?->isSameDay($hoy) ?? false,
                'asistencia_hoy' => $tomadasHoy->has($c->id),
                'proxima_clase' => $proxima?->toDateString(),
            ];
        })->sortBy([
            fn ($a, $b) => $b['clase_hoy'] <=> $a['clase_hoy'],
            fn ($a, $b) => ($a['proxima_clase'] ?? '9999') <=> ($b['proxima_clase'] ?? '9999'),
            fn ($a, $b) => $a['hora'] <=> $b['hora'],
        ])->values();

        return response(['profesor' => $profesorId !== null, 'cursos' => $cursos], Response::HTTP_OK);
    }
}
