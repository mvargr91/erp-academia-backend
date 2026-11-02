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
 * calendario, y sus clases personalizadas por registrar. Quien ve todos los cursos (no es un
 * profesor restringido) recibe todos.
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

        return response([
            'profesor' => $profesorId !== null,
            'cursos' => $cursos,
            'clases_privadas' => $this->clasesPrivadas($profesorId, $hoy),
        ], Response::HTTP_OK);
    }

    /**
     * Clases personalizadas para registrar asistencia: las programadas de los últimos días (las que el
     * profesor aún puede registrar) y las de la próxima semana, sin cobro. Primero las de hoy.
     */
    private function clasesPrivadas(?int $profesorId, Carbon $hoy): array
    {
        $query = DB::table('clases_privadas as c')
            ->leftJoin('sedes as s', 's.id', '=', 'c.sede_id')
            ->leftJoin('profesores as p', 'p.id', '=', 'c.profesor_id')
            ->where('c.estado', 'programada')
            ->whereBetween('c.fecha', [$hoy->copy()->subDays(Profesor::DIAS_CORRECCION_ASISTENCIA)->toDateString(), $hoy->copy()->addDays(7)->toDateString()])
            ->when($profesorId, fn ($q) => $q->where('c.profesor_id', $profesorId))
            ->orderBy('c.fecha')->orderBy('c.hora')
            ->select('c.id', 'c.fecha', 'c.hora', 'c.duracion_min', 'c.externo_nombre', 's.nombre as sede',
                DB::raw("CONCAT(p.nombres,' ',p.apellidos) as profesor"));
        Sede::filtrar($query, 'c.sede_id');
        $clases = $query->get();

        $alumnos = DB::table('clase_privada_alumno as cpa')
            ->join('alumnos as a', 'a.id', '=', 'cpa.alumno_id')
            ->whereIn('cpa.clase_privada_id', $clases->pluck('id'))
            ->orderBy('a.nombres')
            ->get(['cpa.clase_privada_id', DB::raw("CONCAT(a.nombres,' ',a.apellidos) as nombre")])
            ->groupBy('clase_privada_id');

        return $clases->map(fn ($c) => [
            'id' => $c->id,
            'fecha' => $c->fecha,
            'hora' => substr((string) $c->hora, 0, 5),
            'duracion_min' => (int) $c->duracion_min,
            'sede' => $c->sede,
            'profesor' => $c->profesor,
            'alumnos' => collect($alumnos[$c->id] ?? [])->pluck('nombre')
                ->concat($c->externo_nombre ? ["{$c->externo_nombre} (no registrado)"] : [])->implode(', '),
            'hoy' => $c->fecha === $hoy->toDateString(),
            'pasada' => $c->fecha < $hoy->toDateString(),
        ])->sortByDesc('hoy')->values()->all();
    }
}
