<?php

namespace App\Support\Calendario;

use Carbon\Carbon;

/**
 * Festivos de Colombia calculados por ley (18 por año):
 *  - 6 fijos.
 *  - 7 de la Ley Emiliani (Ley 51 de 1983): se trasladan al lunes siguiente.
 *  - 5 que dependen del Domingo de Pascua (Ascensión, Corpus y Sagrado Corazón también van a lunes).
 */
class FestivosColombia
{
    /** @return array<string, string> fecha (Y-m-d) => nombre */
    public static function delAnio(int $anio): array
    {
        $fecha = fn (int $mes, int $dia) => Carbon::create($anio, $mes, $dia)->startOfDay();
        $lunes = fn (Carbon $f) => $f->isMonday() ? $f : $f->copy()->next(Carbon::MONDAY);
        $pascua = Carbon::create($anio, 3, 21)->startOfDay()->addDays(self::diasHastaPascua($anio));
        $desdePascua = fn (int $dias) => $pascua->copy()->addDays($dias);

        $festivos = [
            'Año Nuevo' => $fecha(1, 1),
            'Reyes Magos' => $lunes($fecha(1, 6)),
            'San José' => $lunes($fecha(3, 19)),
            'Jueves Santo' => $desdePascua(-3),
            'Viernes Santo' => $desdePascua(-2),
            'Día del Trabajo' => $fecha(5, 1),
            'Ascensión del Señor' => $lunes($desdePascua(39)),
            'Corpus Christi' => $lunes($desdePascua(60)),
            'Sagrado Corazón' => $lunes($desdePascua(68)),
            'San Pedro y San Pablo' => $lunes($fecha(6, 29)),
            'Día de la Independencia' => $fecha(7, 20),
            'Batalla de Boyacá' => $fecha(8, 7),
            'Asunción de la Virgen' => $lunes($fecha(8, 15)),
            'Día de la Raza' => $lunes($fecha(10, 12)),
            'Todos los Santos' => $lunes($fecha(11, 1)),
            'Independencia de Cartagena' => $lunes($fecha(11, 11)),
            'Inmaculada Concepción' => $fecha(12, 8),
            'Navidad' => $fecha(12, 25),
        ];

        $resultado = [];
        foreach ($festivos as $nombre => $dia) {
            $resultado[$dia->toDateString()] = $nombre;
        }
        ksort($resultado);
        return $resultado;
    }

    /** Días desde el 21 de marzo hasta el Domingo de Pascua (algoritmo de Gauss/Meeus). */
    private static function diasHastaPascua(int $anio): int
    {
        if (function_exists('easter_days')) {
            return easter_days($anio);
        }
        $a = $anio % 19;
        $b = intdiv($anio, 100);
        $c = $anio % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;
        return (int) round(Carbon::create($anio, 3, 21)->diffInDays(Carbon::create($anio, $mes, $dia)));
    }
}
