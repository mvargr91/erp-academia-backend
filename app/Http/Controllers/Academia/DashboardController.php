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
        return $this->consultaAlumnosActivos($sedeId)->count();
    }

    private function consultaAlumnosActivos(?int $sedeId)
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
        return $query;
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
                    'clave' => $mes->format('Y-m'),
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

    /**
     * Lo que hay detrás de cada número del panel, con los mismos filtros (sede del encabezado):
     * GET /v1/academia/dashboard/detalle?indicador=…[&mes=YYYY-MM][&ritmo=…]
     * indicador: alumnos_activos | cursos_activos | ingresos_mes | matriculas_activas | profesores_activos
     *            | morosos (también saldo_pendiente) | ingresos (con mes) | ritmo (con ritmo)
     * → { titulo, columnas: [{ id, label, tipo }], filas: [...] }
     */
    public function detalle(Request $request)
    {
        $sedeId = Sede::actual();
        $nombre = "CONCAT(alumnos.nombres,' ',alumnos.apellidos)";
        $curso = "CONCAT(ritmos.nombre,' · ',ELT(cursos.dia + 1,'Dom','Lun','Mar','Mié','Jue','Vie','Sáb'),' ',DATE_FORMAT(cursos.hora,'%H:%i'))";
        $profesor = "TRIM(CONCAT(COALESCE(profesores.nombres,''),' ',COALESCE(profesores.apellidos,'')))";
        $col = fn ($id, $label, $tipo = 'texto') => ['id' => $id, 'label' => $label, 'tipo' => $tipo];
        // Matrículas con alumno, curso y profesor (base de matrículas, morosos y ritmos).
        $matriculas = fn () => $this->matriculas($sedeId)
            ->join('alumnos', 'alumnos.id', '=', 'curso_alumno.alumno_id')
            ->leftJoin('ritmos', 'ritmos.id', '=', 'cursos.ritmo_id')
            ->leftJoin('profesores', 'profesores.id', '=', 'cursos.profesor_id')
            ->select(DB::raw("{$nombre} as alumno"), DB::raw("{$curso} as curso"), DB::raw("{$profesor} as profesor"),
                'curso_alumno.ciclo_inicio', 'curso_alumno.saldo', 'alumnos.telefono', 'alumnos.correo')
            ->orderBy('alumnos.nombres')->orderBy('alumnos.apellidos');
        $pagosDe = fn (string $desde, string $hasta) => $this->pagos($sedeId)
            ->leftJoin('alumnos', 'alumnos.id', '=', 'pagos.alumno_id')
            ->leftJoin('cursos', 'cursos.id', '=', 'pagos.curso_id')
            ->leftJoin('ritmos', 'ritmos.id', '=', 'cursos.ritmo_id')
            ->whereBetween('pagos.fecha_pago', [$desde, $hasta])
            ->select('pagos.fecha_pago', DB::raw("COALESCE({$nombre}, pagos.pagador_nombre) as alumno"),
                DB::raw("CASE WHEN pagos.curso_id IS NOT NULL THEN {$curso} WHEN pagos.paquete_id IS NOT NULL THEN 'Paquete de clases'"
                    . " WHEN pagos.clase_privada_id IS NOT NULL THEN 'Clase personalizada' ELSE '' END as concepto"),
                'pagos.metodo_pago', 'pagos.referencia', 'pagos.monto')
            ->orderBy('pagos.fecha_pago', 'desc')->orderBy('pagos.id', 'desc');
        $mesActual = [Carbon::now()->startOfMonth()->toDateString(), Carbon::now()->endOfMonth()->toDateString()];

        switch ($request->indicador) {
            case 'alumnos_activos':
                $titulo = 'Alumnos activos';
                $columnas = [$col('alumno', 'Alumno'), $col('telefono', 'Teléfono'), $col('correo', 'Correo'), $col('cursos', 'Cursos activos', 'numero')];
                $filas = $this->consultaAlumnosActivos($sedeId)
                    ->select(DB::raw("{$nombre} as alumno"), 'alumnos.telefono', 'alumnos.correo',
                        DB::raw('(SELECT COUNT(*) FROM curso_alumno ca WHERE ca.alumno_id = alumnos.id AND ca.estado = 1) as cursos'))
                    ->orderBy('alumnos.nombres')->orderBy('alumnos.apellidos')->get();
                break;
            case 'cursos_activos':
                $titulo = 'Cursos activos';
                $columnas = [$col('curso', 'Curso'), $col('profesor', 'Profesor'), $col('sede', 'Sede'), $col('matriculados', 'Matriculados', 'numero')];
                $filas = $this->cursosActivos($sedeId)
                    ->leftJoin('ritmos', 'ritmos.id', '=', 'cursos.ritmo_id')
                    ->leftJoin('profesores', 'profesores.id', '=', 'cursos.profesor_id')
                    ->leftJoin('sedes', 'sedes.id', '=', 'cursos.sede_id')
                    ->select(DB::raw("{$curso} as curso"), DB::raw("{$profesor} as profesor"), 'sedes.nombre as sede',
                        DB::raw('(SELECT COUNT(*) FROM curso_alumno ca WHERE ca.curso_id = cursos.id AND ca.estado = 1) as matriculados'))
                    ->orderBy('cursos.dia')->orderBy('cursos.hora')->get();
                break;
            case 'profesores_activos':
                $titulo = 'Profesores activos';
                $columnas = [$col('profesor', 'Profesor'), $col('telefono', 'Teléfono'), $col('correo', 'Correo'), $col('cursos', 'Cursos activos', 'numero')];
                $query = DB::table('profesores')->where('profesores.estado', 1)
                    ->select(DB::raw("{$profesor} as profesor"), 'profesores.telefono', 'profesores.correo',
                        DB::raw('(SELECT COUNT(*) FROM cursos c WHERE c.profesor_id = profesores.id AND c.activo = 1 AND c.estado = 1'
                            . ($sedeId ? ' AND c.sede_id = ' . (int) $sedeId : '') . ') as cursos'))
                    ->orderBy('profesores.nombres');
                if ($sedeId) {
                    $query->whereIn('profesores.id', $this->cursosActivos($sedeId)->whereNotNull('cursos.profesor_id')->select('cursos.profesor_id'));
                }
                $filas = $query->get();
                break;
            case 'matriculas_activas':
                $titulo = 'Matrículas activas';
                $columnas = [$col('alumno', 'Alumno'), $col('curso', 'Curso'), $col('profesor', 'Profesor'), $col('ciclo_inicio', 'Ciclo desde', 'fecha'), $col('saldo', 'Debe', 'dinero')];
                $filas = $matriculas()->get();
                break;
            case 'morosos':
            case 'saldo_pendiente':
                $titulo = 'Alumnos morosos (saldo pendiente)';
                $columnas = [$col('alumno', 'Alumno'), $col('curso', 'Curso'), $col('telefono', 'Teléfono'), $col('correo', 'Correo'), $col('saldo', 'Debe', 'dinero')];
                $filas = $matriculas()->where('curso_alumno.saldo', '>', 0)->reorder('curso_alumno.saldo', 'desc')->get();
                break;
            case 'ingresos_mes':
            case 'ingresos':
                $mes = $request->filled('mes') ? Carbon::createFromFormat('Y-m', $request->mes)->startOfMonth() : Carbon::now()->startOfMonth();
                [$desde, $hasta] = $request->filled('mes') ? [$mes->toDateString(), $mes->copy()->endOfMonth()->toDateString()] : $mesActual;
                $titulo = 'Ingresos de ' . $mes->locale('es')->isoFormat('MMMM YYYY');
                $columnas = [$col('fecha_pago', 'Fecha', 'fecha'), $col('alumno', 'Alumno'), $col('concepto', 'Concepto'), $col('metodo_pago', 'Método'), $col('referencia', 'Referencia'), $col('monto', 'Monto', 'dinero')];
                $filas = $pagosDe($desde, $hasta)->get();
                break;
            case 'ritmo':
                $titulo = 'Matrículas de ' . $request->ritmo;
                $columnas = [$col('alumno', 'Alumno'), $col('curso', 'Curso'), $col('profesor', 'Profesor'), $col('saldo', 'Debe', 'dinero')];
                $filas = $matriculas()->where('ritmos.nombre', $request->ritmo)->get();
                break;
            default:
                return response(get_response_body(['Indicador no válido.']), Response::HTTP_BAD_REQUEST);
        }
        return response(['titulo' => $titulo, 'columnas' => $columnas, 'filas' => $filas], Response::HTTP_OK);
    }
}
