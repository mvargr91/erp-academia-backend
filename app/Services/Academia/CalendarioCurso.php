<?php

namespace App\Services\Academia;

use App\Support\Configuracion\Configuracion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Calendario de clases de un curso (una clase por semana en su día) y ciclos de pago.
 *
 * Una fecha es "de clase" si cae en el día del curso, desde su fecha de inicio, y no es
 * festivo nacional (BD central) ni cierre de la academia (general o de la sede del curso).
 * Los festivos no cuentan si la academia los dicta (parámetro DICTA_FESTIVOS) o si el curso
 * habilitó esa fecha (curso_festivos_habilitados); los cierres siempre cuentan.
 * Las semanas no lectivas no cuentan:
 * el ciclo de N clases del alumno termina una semana después por cada una ("la clase se corre").
 * Todo se calcula al vuelo, así que agregar un festivo o un cierre corrige los ciclos solo.
 */
class CalendarioCurso
{
    private const MAX_SEMANAS = 260;

    /** @var array<string, array{tipo: string, motivo: string}> Festivos nacionales por fecha. */
    private array $noLectivos = [];
    /** @var array<string, array<int, string>> Cierres por fecha: sede (0 = todas) => motivo. */
    private array $cierres = [];
    /** @var array<string, array<int, true>> Festivos habilitados por fecha: curso => true. */
    private array $habilitados = [];
    private ?string $cargadoDesde = null;
    private ?string $cargadoHasta = null;

    /** Clases por ciclo de pago: las del plan, o 4 por defecto. */
    public static function clasesPorCiclo(): int
    {
        return max(1, Configuracion::entero('CLASES_POR_CICLO', 4));
    }

    /** Festivos y cierres entre dos fechas: fecha => [tipo, motivo]. */
    private function cargarNoLectivos(Carbon $desde, Carbon $hasta): void
    {
        $desdeTxt = $desde->toDateString();
        $hastaTxt = $hasta->toDateString();
        if ($this->cargadoDesde && $this->cargadoDesde <= $desdeTxt && $this->cargadoHasta >= $hastaTxt) {
            return;
        }
        // Se recarga la unión con lo ya cargado para que la ventana quede continua.
        if ($this->cargadoDesde) {
            $desdeTxt = min($desdeTxt, $this->cargadoDesde);
            $hastaTxt = max($hastaTxt, $this->cargadoHasta);
        }
        $this->noLectivos = [];
        $this->cierres = [];
        $this->habilitados = [];

        if (!Configuracion::siNo('DICTA_FESTIVOS', false)) {
            foreach (DB::connection('central')->table('festivos')->whereBetween('fecha', [$desdeTxt, $hastaTxt])->get() as $f) {
                $this->noLectivos[$f->fecha] = ['tipo' => 'festivo', 'motivo' => $f->nombre];
            }
            $habilitados = DB::table('curso_festivos_habilitados')->whereBetween('fecha', [$desdeTxt, $hastaTxt])->get();
            foreach ($habilitados as $h) {
                $this->habilitados[$h->fecha][(int) $h->curso_id] = true;
            }
        }
        $cierres = DB::table('cierres_academia')
            ->where('fecha_desde', '<=', $hastaTxt)
            ->where('fecha_hasta', '>=', $desdeTxt)
            ->get();
        foreach ($cierres as $c) {
            $dia = Carbon::parse(max($c->fecha_desde, $desdeTxt));
            $fin = Carbon::parse(min($c->fecha_hasta, $hastaTxt));
            for (; $dia->lte($fin); $dia->addDay()) {
                $this->cierres[$dia->toDateString()][(int) ($c->sede_id ?? 0)] ??= $c->motivo;
            }
        }
        $this->cargadoDesde = $desdeTxt;
        $this->cargadoHasta = $hastaTxt;
    }

    /** Cierre (general o de la sede) o festivo no habilitado para el curso, de una fecha ya cargada, o null. */
    private function noLectivoEn(string $fecha, ?int $sedeId, ?int $cursoId = null): ?array
    {
        $motivo = $this->cierres[$fecha][0] ?? ($sedeId ? ($this->cierres[$fecha][$sedeId] ?? null) : null);
        if ($motivo !== null) {
            return ['tipo' => 'cierre', 'motivo' => $motivo];
        }
        if (isset($this->noLectivos[$fecha]) && !($cursoId && isset($this->habilitados[$fecha][$cursoId]))) {
            return $this->noLectivos[$fecha];
        }
        return null;
    }

    private static function sedeDe(object $curso): ?int
    {
        return isset($curso->sede_id) ? (int) $curso->sede_id : null;
    }

    /** Id del curso: llega como curso (id) o como fila de matrícula (curso_id). */
    private static function cursoDe(object $curso): ?int
    {
        $id = $curso->curso_id ?? $curso->id ?? null;
        return $id ? (int) $id : null;
    }

    /**
     * Cierre de la academia en una fecha y sede, o null. Para clases fuera del calendario de un
     * curso (personalizadas, paquetes): esas sí se pueden dar en festivo porque se agendan a mano.
     */
    public function cierreEn(Carbon $fecha, ?int $sedeId = null): ?string
    {
        $this->cargarNoLectivos($fecha, $fecha);
        $no = $this->noLectivoEn($fecha->toDateString(), $sedeId);
        return ($no && $no['tipo'] === 'cierre') ? $no['motivo'] : null;
    }

    /** Nombre del festivo de una fecha si la academia descansa los festivos, o null. */
    public function festivoEn(Carbon $fecha): ?string
    {
        $this->cargarNoLectivos($fecha, $fecha);
        return $this->noLectivos[$fecha->toDateString()]['motivo'] ?? null;
    }

    /** Primera fecha >= $desde que cae en el día del curso (0 = domingo … 6 = sábado). */
    private static function primerDiaDeCurso(object $curso, Carbon $desde): Carbon
    {
        $fecha = $desde->copy()->startOfDay();
        if ($curso->fecha_inicio && $fecha->lt(Carbon::parse($curso->fecha_inicio))) {
            $fecha = Carbon::parse($curso->fecha_inicio)->startOfDay();
        }
        while ($fecha->dayOfWeek !== (int) $curso->dia) {
            $fecha->addDay();
        }
        return $fecha;
    }

    /**
     * Semanas del curso entre dos fechas, con su estado.
     * festivo_habilitado: nombre del festivo cuando el curso sí dicta clase ese día.
     * @return array<int, array{fecha: string, estado: string, motivo: ?string, festivo_habilitado: ?string}>  estado: clase | festivo | cierre
     */
    public function semanas(object $curso, Carbon $desde, Carbon $hasta): array
    {
        $this->cargarNoLectivos($desde, $hasta);
        $cursoId = self::cursoDe($curso);
        $resultado = [];
        for ($f = self::primerDiaDeCurso($curso, $desde); $f->lte($hasta); $f->addWeek()) {
            $fecha = $f->toDateString();
            $no = $this->noLectivoEn($fecha, self::sedeDe($curso), $cursoId);
            $resultado[] = [
                'fecha' => $fecha,
                'estado' => $no['tipo'] ?? 'clase',
                'motivo' => $no['motivo'] ?? null,
                'festivo_habilitado' => $no ? null : ($this->noLectivos[$fecha]['motivo'] ?? null),
            ];
        }
        return $resultado;
    }

    /** Las próximas $n fechas de clase a partir de $desde (inclusive). @return Carbon[] */
    public function proximasClases(object $curso, Carbon $desde, int $n): array
    {
        $clases = [];
        $f = self::primerDiaDeCurso($curso, $desde);
        $this->cargarNoLectivos($f, $f->copy()->addWeeks($n + 30));
        for ($i = 0; count($clases) < $n && $i < self::MAX_SEMANAS; $i++, $f->addWeek()) {
            if ($f->toDateString() > $this->cargadoHasta) {
                $this->cargarNoLectivos($f, $f->copy()->addWeeks(52));
            }
            if (!$this->noLectivoEn($f->toDateString(), self::sedeDe($curso), self::cursoDe($curso))) {
                $clases[] = $f->copy();
            }
        }
        return $clases;
    }

    /**
     * ¿Se puede dictar (y tomar asistencia de) la clase en esta fecha?
     * @return array{valida: bool, motivo: ?string, sugerida: ?string}
     */
    public function validarFecha(object $curso, Carbon $fecha): array
    {
        $fecha = $fecha->copy()->startOfDay();
        $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
        $siguiente = $this->proximasClases($curso, $fecha, 1)[0] ?? null;
        $sugerida = $siguiente?->toDateString();

        if ($curso->fecha_inicio && $fecha->lt(Carbon::parse($curso->fecha_inicio))) {
            return ['valida' => false, 'motivo' => 'El curso inicia el ' . Carbon::parse($curso->fecha_inicio)->format('d/m/Y') . '.', 'sugerida' => $sugerida];
        }
        if ($fecha->dayOfWeek !== (int) $curso->dia) {
            return ['valida' => false, 'motivo' => 'El curso no tiene clase ese día: sus clases son los ' . $dias[(int) $curso->dia] . '.', 'sugerida' => $sugerida];
        }
        $this->cargarNoLectivos($fecha, $fecha->copy()->addWeeks(30));
        $no = $this->noLectivoEn($fecha->toDateString(), self::sedeDe($curso), self::cursoDe($curso));
        if ($no) {
            $tipo = $no['tipo'] === 'festivo' ? 'es festivo' : 'la academia está cerrada';
            return [
                'valida' => false,
                'motivo' => "El {$fecha->format('d/m/Y')} {$tipo} ({$no['motivo']}): la clase se corre al "
                    . ($siguiente ? $siguiente->format('d/m/Y') : 'siguiente semana disponible') . '.'
                    . ($no['tipo'] === 'festivo' ? ' Si el curso sí tiene clase ese día, habilítalo en el calendario del curso.' : ''),
                'sugerida' => $sugerida,
            ];
        }
        return ['valida' => true, 'motivo' => null, 'sugerida' => $fecha->toDateString()];
    }

    /**
     * Ciclo actual de una matrícula.
     * @return array{inicio: Carbon, fin: Carbon, proximo_pago: Carbon, clases: Carbon[], clases_por_ciclo: int}
     */
    public function ciclo(object $curso, object $matricula, int $clasesPorCiclo): array
    {
        $inicio = $matricula->ciclo_inicio
            ? Carbon::parse($matricula->ciclo_inicio)
            : $this->primeraClase($curso, Carbon::parse($matricula->fecha_matricula ?? Carbon::today()));
        $clases = $this->proximasClases($curso, $inicio, $clasesPorCiclo + 1);
        $delCiclo = array_slice($clases, 0, $clasesPorCiclo);
        return [
            'inicio' => $delCiclo[0] ?? $inicio,
            'fin' => end($delCiclo) ?: $inicio,
            'proximo_pago' => $clases[$clasesPorCiclo] ?? $inicio->copy()->addWeeks($clasesPorCiclo),
            'clases' => $delCiclo,
            'clases_por_ciclo' => $clasesPorCiclo,
        ];
    }

    /**
     * Avance de un ciclo a $hoy: cuántas de sus clases ya se dictaron (la de hoy cuenta).
     * Va por calendario, no por asistencia: la clase se consume aunque el alumno falte.
     * @return array{clases_consumidas: int, clases_ciclo: int}
     */
    public static function avance(array $ciclo, Carbon $hoy): array
    {
        $hoy = $hoy->copy()->endOfDay();
        return [
            'clases_consumidas' => count(array_filter($ciclo['clases'], fn (Carbon $clase) => $clase->lte($hoy))),
            'clases_ciclo' => $ciclo['clases_por_ciclo'],
        ];
    }

    /** Primera clase del curso desde una fecha (para iniciar el ciclo de una matrícula nueva). */
    public function primeraClase(object $curso, Carbon $desde): Carbon
    {
        return $this->proximasClases($curso, $desde, 1)[0] ?? self::primerDiaDeCurso($curso, $desde);
    }

    /** Número de la clase (1..N) de una fecha dentro del ciclo de la matrícula, o null si está fuera. */
    public function numeroEnCiclo(array $ciclo, Carbon $fecha): ?int
    {
        foreach ($ciclo['clases'] as $i => $clase) {
            if ($clase->isSameDay($fecha)) {
                return $i + 1;
            }
        }
        return null;
    }
}
