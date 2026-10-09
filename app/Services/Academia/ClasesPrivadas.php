<?php

namespace App\Services\Academia;

use App\Support\Configuracion\Configuracion;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Resultado de las clases personalizadas y su descuento del paquete.
 *
 *  - asistió / no asistió: descuenta 1 clase;
 *  - canceló a tiempo: no descuenta; canceló tarde: descuenta;
 *  - pendiente (programada): todavía no descuenta.
 *
 * "A tiempo" lo indica quien registra; si no lo indica, se calcula con la hora en que avisó
 * y el parámetro HORAS_CANCELACION_CLASE.
 */
class ClasesPrivadas
{
    public const RESULTADOS = [
        'pendiente' => 'Pendiente',
        'asistio' => 'Asistió',
        'no_asistio' => 'No asistió',
        'cancelo' => 'Canceló',
    ];

    /** Resultados de una persona no registrada (clase suelta): no hay paquete del que descontar. */
    public const RESULTADOS_EXTERNO = [
        'pendiente' => 'Pendiente',
        'asistio' => 'Asistió',
        'no_asistio' => 'No asistió',
        'cancelo_a_tiempo' => 'Canceló a tiempo',
        'cancelo_tarde' => 'Canceló tarde',
    ];

    /** Horas mínimas para cancelar sin descuento. */
    public static function horasCancelacion(): int
    {
        return Configuracion::entero('HORAS_CANCELACION_CLASE', 24);
    }

    /**
     * Guarda el resultado de un alumno en la clase y descuenta (o devuelve) la clase de su paquete.
     * Devuelve false si debía descontar y no había paquete con clases disponibles.
     */
    public static function aplicarResultado(object $clase, object $fila, string $resultado, ?bool $aTiempo = null, ?string $canceladoEn = null): bool
    {
        $cancelado = $resultado === 'cancelo' ? Carbon::parse($canceladoEn ?? Carbon::now()) : null;
        $descuenta = match ($resultado) {
            'asistio', 'no_asistio' => true,
            'cancelo' => $aTiempo !== null
                ? !$aTiempo
                : $cancelado->diffInHours(Carbon::parse($clase->fecha . ' ' . $clase->hora), false) < self::horasCancelacion(),
            default => false,
        };
        DB::table('clase_privada_alumno')->where('id', $fila->id)->update([
            'resultado' => $resultado,
            'cancelado_en' => $cancelado,
            'descuenta' => $descuenta,
            'updated_at' => Carbon::now(),
        ]);

        if (!$descuenta) {
            Paquetes::devolver((int) $fila->alumno_id, null, $clase->id);
            return true;
        }
        $motivo = $resultado === 'cancelo' ? 'cancelacion_tardia' : $resultado;
        return (bool) Paquetes::consumir((int) $fila->alumno_id, Carbon::parse($clase->fecha), 'privada', null, $clase->id, $motivo,
            $fila->paquete_id ? (int) $fila->paquete_id : null);
    }

    /** Estado de la clase según sus resultados: cancelada si nadie la tomó, programada si falta alguno. */
    public static function actualizarEstado(int $claseId): void
    {
        $clase = DB::table('clases_privadas')->find($claseId);
        $resultados = DB::table('clase_privada_alumno')->where('clase_privada_id', $claseId)->pluck('resultado');
        if ($clase->externo_nombre) {
            $resultados->push(str_starts_with($clase->externo_resultado, 'cancelo') ? 'cancelo' : $clase->externo_resultado);
        }
        $estado = $resultados->isEmpty() || $resultados->contains('pendiente') ? 'programada'
            : ($resultados->every(fn ($r) => $r === 'cancelo') ? 'cancelada' : 'realizada');
        DB::table('clases_privadas')->where('id', $claseId)->update(['estado' => $estado, 'updated_at' => Carbon::now()]);
    }
}
