<?php

namespace App\Http\Controllers\Academia;

use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    /**
     * Métricas para el panel de academia.
     * GET /v1/academia/dashboard
     */
    public function index(Request $request)
    {
        try {
            $inicioMes = Carbon::now()->startOfMonth()->toDateString();
            $finMes = Carbon::now()->endOfMonth()->toDateString();

            $alumnosActivos = DB::table('alumnos')->where('estado', 1)->count();
            $profesoresActivos = DB::table('profesores')->where('estado', 1)->count();
            $cursosActivos = DB::table('cursos')->where('activo', 1)->where('estado', 1)->count();

            $ingresosMes = (float) DB::table('pagos')
                ->whereBetween('fecha_pago', [$inicioMes, $finMes])
                ->sum('monto');

            $matriculasActivas = DB::table('curso_alumno')->where('estado', 1)->count();

            // Alumnos con saldo pendiente (morosos).
            $morosos = DB::table('curso_alumno')->where('estado', 1)->where('saldo', '>', 0)->count();
            $saldoPendiente = (float) DB::table('curso_alumno')->where('estado', 1)->where('saldo', '>', 0)->sum('saldo');

            // Ingresos de los últimos 6 meses (para la gráfica).
            $ingresosPorMes = [];
            for ($i = 5; $i >= 0; $i--) {
                $mes = Carbon::now()->subMonths($i);
                $total = (float) DB::table('pagos')
                    ->whereYear('fecha_pago', $mes->year)
                    ->whereMonth('fecha_pago', $mes->month)
                    ->sum('monto');
                $ingresosPorMes[] = [
                    'mes' => $mes->locale('es')->isoFormat('MMM YYYY'),
                    'total' => $total,
                ];
            }

            // Alumnos por ritmo (matrículas activas agrupadas por ritmo).
            $alumnosPorRitmo = DB::table('curso_alumno')
                ->join('cursos', 'cursos.id', '=', 'curso_alumno.curso_id')
                ->join('ritmos', 'ritmos.id', '=', 'cursos.ritmo_id')
                ->where('curso_alumno.estado', 1)
                ->select('ritmos.nombre', DB::raw('COUNT(*) as total'))
                ->groupBy('ritmos.nombre')
                ->orderByDesc('total')
                ->get();

            return response([
                'tarjetas' => [
                    'alumnos_activos' => $alumnosActivos,
                    'profesores_activos' => $profesoresActivos,
                    'cursos_activos' => $cursosActivos,
                    'ingresos_mes' => $ingresosMes,
                    'matriculas_activas' => $matriculasActivas,
                    'morosos' => $morosos,
                    'saldo_pendiente' => $saldoPendiente,
                ],
                'ingresos_por_mes' => $ingresosPorMes,
                'alumnos_por_ritmo' => $alumnosPorRitmo,
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
