<?php

namespace App\Services\Academia;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Paquetes de clases de los alumnos.
 *
 * Un paquete es de clases personalizadas; no tiene que ver con los cursos, que se pagan por ciclos.
 * Cada clase usada es un registro en consumos_paquete; se descuenta del paquete vigente que vence
 * primero. Vencido = pasó su fecha de vencimiento; agotado = usó todas sus clases.
 */
class Paquetes
{
    public const ACTIVO = 'activo';
    public const ANULADO = 'anulado';

    public const ESTADOS = [
        'activo' => 'Activo',
        'agotado' => 'Agotado',
        'vencido' => 'Vencido',
        'anulado' => 'Anulado',
    ];

    /** Clases usadas por paquete. @return array<int, int> */
    public static function usadas(array $paqueteIds): array
    {
        if (!$paqueteIds) {
            return [];
        }
        return DB::table('consumos_paquete')
            ->whereIn('paquete_id', $paqueteIds)
            ->groupBy('paquete_id')
            ->select('paquete_id', DB::raw('COUNT(*) as n'))
            ->pluck('n', 'paquete_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /** Estado efectivo del paquete en una fecha. */
    public static function estado(object $paquete, int $usadas, ?Carbon $fecha = null): string
    {
        $fecha ??= Carbon::today();
        if ($paquete->estado === self::ANULADO) {
            return 'anulado';
        }
        if ($usadas >= $paquete->clases_total) {
            return 'agotado';
        }
        if ($paquete->fecha_vencimiento && $fecha->gt(Carbon::parse($paquete->fecha_vencimiento))) {
            return 'vencido';
        }
        return 'activo';
    }

    /** Resumen listo para mostrar. */
    public static function resumen(object $paquete, ?int $usadas = null): array
    {
        $usadas ??= self::usadas([$paquete->id])[$paquete->id] ?? 0;
        $estado = self::estado($paquete, $usadas);
        return [
            'clases_total' => (int) $paquete->clases_total,
            'clases_usadas' => $usadas,
            'clases_restantes' => max(0, (int) $paquete->clases_total - $usadas),
            'estado_efectivo' => $estado,
            'estado_nombre' => self::ESTADOS[$estado],
        ];
    }

    /** Paquete con el que se paga una clase en $fecha: vigente, con clases, el que vence primero. */
    public static function vigente(int $alumnoId, Carbon $fecha): ?object
    {
        $candidatos = DB::table('paquetes_alumno')
            ->where('alumno_id', $alumnoId)
            ->where('estado', self::ACTIVO)
            ->where('fecha_compra', '<=', $fecha->toDateString())
            ->where(fn ($q) => $q->whereNull('fecha_vencimiento')->orWhere('fecha_vencimiento', '>=', $fecha->toDateString()))
            ->orderByRaw('fecha_vencimiento IS NULL, fecha_vencimiento')
            ->orderBy('id')
            ->get();
        $usadas = self::usadas($candidatos->pluck('id')->all());
        return $candidatos->first(fn ($p) => ($usadas[$p->id] ?? 0) < $p->clases_total);
    }

    /** Clases que le quedan al alumno en paquetes vigentes hoy (para avisos en pantalla). */
    public static function disponiblesHoy(int $alumnoId): array
    {
        $hoy = Carbon::today();
        $paquetes = DB::table('paquetes_alumno')
            ->where('alumno_id', $alumnoId)
            ->where('estado', self::ACTIVO)
            ->where(fn ($q) => $q->whereNull('fecha_vencimiento')->orWhere('fecha_vencimiento', '>=', $hoy->toDateString()))
            ->get();
        $usadas = self::usadas($paquetes->pluck('id')->all());
        $restantes = $paquetes->sum(fn ($p) => max(0, $p->clases_total - ($usadas[$p->id] ?? 0)));
        $vence = $paquetes->filter(fn ($p) => ($usadas[$p->id] ?? 0) < $p->clases_total)->min('fecha_vencimiento');
        return ['restantes' => (int) $restantes, 'vence' => $vence];
    }

    /** El paquete indicado, si en $fecha se le puede descontar una clase (activo, sin vencer y con clases). */
    public static function disponible(int $paqueteId, Carbon $fecha): ?object
    {
        $paquete = DB::table('paquetes_alumno')->find($paqueteId);
        if (!$paquete) {
            return null;
        }
        $usadas = self::usadas([$paqueteId])[$paqueteId] ?? 0;
        return self::estado($paquete, $usadas, $fecha) === 'activo' ? $paquete : null;
    }

    /**
     * Descuenta una clase (idempotente por clase privada y alumno).
     * Con $paqueteId se descuenta de ese paquete; si no, del vigente que vence primero.
     * Devuelve el consumo o null si no hay paquete del que descontar.
     */
    public static function consumir(int $alumnoId, Carbon $fecha, string $origen, ?int $asistenciaId, ?int $clasePrivadaId, string $motivo, ?int $paqueteId = null): ?object
    {
        $existente = self::buscarConsumo($alumnoId, $asistenciaId, $clasePrivadaId);
        if ($existente) {
            $cambios = array_filter([
                'motivo' => $existente->motivo !== $motivo ? $motivo : null,
                'fecha' => $existente->fecha !== $fecha->toDateString() ? $fecha->toDateString() : null,
            ]);
            if ($cambios) {
                DB::table('consumos_paquete')->where('id', $existente->id)->update($cambios + ['updated_at' => Carbon::now()]);
            }
            return $existente;
        }
        $paquete = $paqueteId ? self::disponible($paqueteId, $fecha) : self::vigente($alumnoId, $fecha);
        if (!$paquete) {
            return null;
        }
        $id = DB::table('consumos_paquete')->insertGetId([
            'paquete_id' => $paquete->id,
            'alumno_id' => $alumnoId,
            'fecha' => $fecha->toDateString(),
            'origen' => $origen,
            'asistencia_id' => $asistenciaId,
            'clase_privada_id' => $clasePrivadaId,
            'motivo' => $motivo,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        return DB::table('consumos_paquete')->find($id);
    }

    /** Devuelve la clase al paquete (si se había descontado). */
    public static function devolver(int $alumnoId, ?int $asistenciaId, ?int $clasePrivadaId): void
    {
        $consumo = self::buscarConsumo($alumnoId, $asistenciaId, $clasePrivadaId);
        if ($consumo) {
            DB::table('consumos_paquete')->where('id', $consumo->id)->delete();
        }
    }

    private static function buscarConsumo(int $alumnoId, ?int $asistenciaId, ?int $clasePrivadaId): ?object
    {
        $q = DB::table('consumos_paquete')->where('alumno_id', $alumnoId);
        if ($asistenciaId) {
            $q->where('asistencia_id', $asistenciaId);
        } else {
            $q->where('clase_privada_id', $clasePrivadaId);
        }
        return $q->first();
    }
}
