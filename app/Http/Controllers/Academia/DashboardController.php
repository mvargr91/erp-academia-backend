<?php

namespace App\Http\Controllers\Academia;

use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Academia\Sede;

class DashboardController extends Controller
{
    /** Matrículas activas, opcionalmente de los cursos de una sede. */
    private function matriculas(?int $sedeId)
    {
        $query = DB::table('curso_alumno')
            ->join('cursos', 'cursos.id', '=', 'curso_alumno.curso_id')
            ->where('curso_alumno.estado', 1);
        return $sedeId ? $query->where('cursos.sede_id', $sedeId) : $query;
    }

    private function pagos(?int $sedeId)
    {
        $query = DB::table('pagos');
        return $sedeId ? $query->where('pagos.sede_id', $sedeId) : $query;
    }

    private function cursosActivos(?int $sedeId)
    {
        $query = DB::table('cursos')->where('cursos.activo', 1)->where('cursos.estado', 1);
        return $sedeId ? $query->where('cursos.sede_id', $sedeId) : $query;
    }

    /** Alumnos activos: de toda la academia, o los de la sede (por sede principal o por matrícula). */
    private function alumnosActivos(?int $sedeId): int
    {
        $query = DB::table('alumnos')->where('alumnos.estado', 1);
        if ($sedeId) {
            $query->where(function ($q) use ($sedeId) {
                $q->where('alumnos.sede_id', $sedeId)
                    ->orWhereExists(fn ($sub) => $sub->from('curso_alumno')
                        ->join('cursos', 'cursos.id', '=', 'curso_alumno.curso_id')
                        ->whereColumn('curso_alumno.alumno_id', 'alumnos.id')
                        ->where('curso_alumno.estado', 1)
                        ->where('cursos.sede_id', $sedeId));
            });
        }
        return $query->count();
    }

    private function profesoresActivos(?int $sedeId): int
    {
        if (!$sedeId) {
            return DB::table('profesores')->where('estado', 1)->count();
        }
        // Los profesores son de toda la academia: en una sede cuentan los que dictan cursos allí.
        return $this->cursosActivos($sedeId)->whereNotNull('cursos.profesor_id')->distinct()->count('cursos.profesor_id');
    }

    private function tarjetas(?int $sedeId, string $inicioMes, string $finMes): array
    {
        return [
            'alumnos_activos' => $this->alumnosActivos($sedeId),
            'profesores_activos' => $this->profesoresActivos($sedeId),
            'cursos_activos' => $this->cursosActivos($sedeId)->count(),
            'ingresos_mes' => (float) $this->pagos($sedeId)->whereBetween('fecha_pago', [$inicioMes, $finMes])->sum('monto'),
            'matriculas_activas' => $this->matriculas($sedeId)->count(),
            // Alumnos con saldo pendiente (morosos).
            'morosos' => $this->matriculas($sedeId)->where('curso_alumno.saldo', '>', 0)->count(),
            'saldo_pendiente' => (float) $this->matriculas($sedeId)->where('curso_alumno.saldo', '>', 0)->sum('curso_alumno.saldo'),
        ];
    }

    /** Porcentaje de cupo ocupado en los cursos activos que tienen cupo máximo, o null si ninguno lo tiene. */
    private function ocupacion(int $sedeId): ?float
    {
        $cursos = $this->cursosActivos($sedeId)->whereNotNull('cursos.cupo_max')->where('cursos.cupo_max', '>', 0)
            ->select('cursos.cupo_max', DB::raw('(SELECT COUNT(*) FROM curso_alumno WHERE curso_alumno.curso_id = cursos.id AND curso_alumno.estado = 1) as matriculados'))
            ->get();
        $cupo = $cursos->sum('cupo_max');
        return $cupo > 0 ? round($cursos->sum('matriculados') * 100 / $cupo, 1) : null;
    }

    /** Porcentaje de asistencia del mes en los cursos de la sede, o null si no se ha tomado asistencia. */
    private function asistencia(int $sedeId, string $inicioMes, string $finMes): ?float
    {
        $fila = DB::table('asistencia_alumno')
            ->join('asistencias', 'asistencias.id', '=', 'asistencia_alumno.asistencia_id')
            ->join('cursos', 'cursos.id', '=', 'asistencias.curso_id')
            ->where('cursos.sede_id', $sedeId)
            ->whereBetween('asistencias.fecha_sesion', [$inicioMes, $finMes])
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(asistencia_alumno.presente), 0) as presentes')
            ->first();
        return $fila->total > 0 ? round($fila->presentes * 100 / $fila->total, 1) : null;
    }

    /**
     * Métricas para el panel de academia. Con una sede elegida en el encabezado (X-Sede) todo se
     * calcula para esa sede; sin sede es el consolidado y, si hay varias, se agrega la comparación.
     * GET /v1/academia/dashboard
     */
    public function index(Request $request)
    {
        try {
            $inicioMes = Carbon::now()->startOfMonth()->toDateString();
            $finMes = Carbon::now()->endOfMonth()->toDateString();
            $sedeId = Sede::actual();
            $sedes = DB::table('sedes')->where('estado', 1)->orderBy('nombre')->get(['id', 'nombre']);
            $comparar = !$sedeId && $sedes->count() > 1;

            // Ingresos de los últimos 6 meses (para la gráfica); en el consolidado, también por sede.
            $ingresosPorMes = [];
            for ($i = 5; $i >= 0; $i--) {
                $mes = Carbon::now()->startOfMonth()->subMonths($i);
                $delMes = fn (?int $sede) => (float) $this->pagos($sede)
                    ->whereYear('fecha_pago', $mes->year)
                    ->whereMonth('fecha_pago', $mes->month)
                    ->sum('monto');
                $punto = [
                    'mes' => $mes->locale('es')->isoFormat('MMM YYYY'),
                    'total' => $delMes($sedeId),
                ];
                if ($comparar) {
                    $punto['sedes'] = $sedes->mapWithKeys(fn ($s) => [$s->nombre => $delMes($s->id)])->all();
                }
                $ingresosPorMes[] = $punto;
            }

            // Alumnos por ritmo (matrículas activas agrupadas por ritmo).
            $alumnosPorRitmo = $this->matriculas($sedeId)
                ->join('ritmos', 'ritmos.id', '=', 'cursos.ritmo_id')
                ->select('ritmos.nombre', DB::raw('COUNT(*) as total'))
                ->groupBy('ritmos.nombre')
                ->orderByDesc('total')
                ->get();

            $porSede = $comparar
                ? $sedes->map(fn ($s) => array_merge(
                    ['id' => $s->id, 'nombre' => $s->nombre],
                    $this->tarjetas($s->id, $inicioMes, $finMes),
                    ['ocupacion' => $this->ocupacion($s->id), 'asistencia' => $this->asistencia($s->id, $inicioMes, $finMes)],
                ))->all()
                : [];

            return response([
                'sede' => $sedeId ? ['id' => $sedeId, 'nombre' => $sedes->firstWhere('id', $sedeId)?->nombre] : null,
                'tarjetas' => $this->tarjetas($sedeId, $inicioMes, $finMes),
                'ingresos_por_mes' => $ingresosPorMes,
                'alumnos_por_ritmo' => $alumnosPorRitmo,
                'por_sede' => $porSede,
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
