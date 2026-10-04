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
    private ?string $cargadoDesde = null;
    private ?string $cargadoHasta = null;

    /** Clases por ciclo de pago: las del plan, o 4 por defecto. */
    public static function clasesPorCiclo(?int $numClases): int
    {
        return $numClases && $numClases > 0 ? $numClases : Configuracion::entero('CLASES_POR_CICLO', 4);
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

        foreach (DB::connection('central')->table('festivos')->whereBetween('fecha', [$desdeTxt, $hastaTxt])->get() as $f) {
            $this->noLectivos[$f->fecha] = ['tipo' => 'festivo', 'motivo' => $f->nombre];
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

    /** Festivo o cierre (general o de la sede) de una fecha ya cargada, o null. */
    private function noLectivoEn(string $fecha, ?int $sedeId): ?array
    {
        if (isset($this->noLectivos[$fecha])) {
            return $this->noLectivos[$fecha];
        }
        $motivo = $this->cierres[$fecha][0] ?? ($sedeId ? ($this->cierres[$fecha][$sedeId] ?? null) : null);
        return $motivo !== null ? ['tipo' => 'cierre', 'motivo' => $motivo] : null;
    }

    private static function sedeDe(object $curso): ?int
    {
        return isset($curso->sede_id) ? (int) $curso->sede_id : null;
    }

    /** Festivo o cierre de una fecha en una sede (para clases fuera del calendario de un curso), o null. */
    public function noLectivo(Carbon $fecha, ?int $sedeId = null): ?array
    {
        $this->cargarNoLectivos($fecha, $fecha);
        return $this->noLectivoEn($fecha->toDateString(), $sedeId);
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
     * @return array<int, array{fecha: string, estado: string, motivo: ?string}>  estado: clase | festivo | cierre
     */
    public function semanas(object $curso, Carbon $desde, Carbon $hasta): array
    {
        $this->cargarNoLectivos($desde, $hasta);
        $resultado = [];
        for ($f = self::primerDiaDeCurso($curso, $desde); $f->lte($hasta); $f->addWeek()) {
            $no = $this->noLectivoEn($f->toDateString(), self::sedeDe($curso));
            $resultado[] = [
                'fecha' => $f->toDateString(),
                'estado' => $no['tipo'] ?? 'clase',
                'motivo' => $no['motivo'] ?? null,
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
            if (!$this->noLectivoEn($f->toDateString(), self::sedeDe($curso))) {
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
        $no = $this->noLectivoEn($fecha->toDateString(), self::sedeDe($curso));
        if ($no) {
            $tipo = $no['tipo'] === 'festivo' ? 'es festivo' : 'la academia está cerrada';
            return [
                'valida' => false,
                'motivo' => "El {$fecha->format('d/m/Y')} {$tipo} ({$no['motivo']}): la clase se corre al "
                    . ($siguiente ? $siguiente->format('d/m/Y') : 'siguiente semana disponible') . '.',
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
