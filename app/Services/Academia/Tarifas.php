<?php

namespace App\Services\Academia;

use Illuminate\Support\Facades\DB;
use App\Support\Configuracion\Configuracion;

/**
 * Precio por ciclo de una matrícula de curso grupal.
 *
 * Posición del curso entre las matrículas por ciclo del alumno (orden de matrícula):
 *  1.º  → precio del plan
 *  2.º  → PRECIO_SEGUNDO_CURSO (valor fijo; nunca más que el plan; 0 = precio del plan)
 *  3.º  → plan − DESC_TERCER_CURSO %
 *  4.º+ → plan − DESC_CUARTO_CURSO % (100 = gratis)
 * En pareja → PRECIO_PAREJA por persona (0 = no aplica); si también aplica la regla por
 * cantidad de cursos, se cobra el menor de los dos (nunca se acumulan).
 *
 * Se calcula al cobrar cada ciclo, así que si el alumno se retira de un curso los demás
 * se recalculan solos desde el siguiente ciclo.
 */
class Tarifas
{
    /**
     * @param int $cursoAlumnoId  matrícula
     * @return array{valor: float, base: float, regla: string, posicion: int}
     */
    public static function precio(int $cursoAlumnoId): array
    {
        $m = DB::table('curso_alumno as ca')
            ->join('cursos as c', 'c.id', '=', 'ca.curso_id')
            ->leftJoin('planes as p', 'p.id', '=', 'c.plan_id')
            ->where('ca.id', $cursoAlumnoId)
            ->select('ca.id', 'ca.alumno_id', 'ca.pareja_alumno_id', 'p.valor as valor_plan')
            ->first();
        if (!$m) {
            return ['valor' => 0.0, 'base' => 0.0, 'regla' => 'Sin matrícula', 'posicion' => 0];
        }
        $base = (float) ($m->valor_plan ?? 0);
        $posicion = self::posicion($m->alumno_id, $m->id);

        // Regla por cantidad de cursos.
        [$valor, $regla] = match (true) {
            $posicion <= 1 => [$base, 'Precio del plan'],
            $posicion === 2 => self::segundoCurso($base),
            $posicion === 3 => self::conDescuento($base, 'DESC_TERCER_CURSO', 50, '3.er curso'),
            default => self::conDescuento($base, 'DESC_CUARTO_CURSO', 100, "{$posicion}.º curso"),
        };

        // Pareja: se cobra el menor entre el precio de pareja y el de la regla anterior.
        $precioPareja = (float) Configuracion::parametro('PRECIO_PAREJA', 0);
        if ($m->pareja_alumno_id && $precioPareja > 0 && $precioPareja < $valor) {
            [$valor, $regla] = [$precioPareja, 'Precio pareja'];
        }

        return ['valor' => round(max(0, $valor), 2), 'base' => $base, 'regla' => $regla, 'posicion' => $posicion];
    }

    /** Lugar (1, 2, 3…) de la matrícula entre los cursos que el alumno paga por ciclo, por orden de matrícula. */
    public static function posicion(int $alumnoId, int $cursoAlumnoId): int
    {
        $ids = DB::table('curso_alumno as ca')
            ->join('cursos as c', 'c.id', '=', 'ca.curso_id')
            ->join('planes as p', 'p.id', '=', 'c.plan_id')
            ->where('ca.alumno_id', $alumnoId)
            ->where('ca.estado', 1)
            ->where('ca.modalidad', 'ciclo')
            ->where('c.estado', 1)
            ->where('c.activo', 1)
            ->where('p.periodicidad', 'mensual')
            ->where('p.valor', '>', 0)
            ->orderBy('ca.fecha_matricula')
            ->orderBy('ca.id')
            ->pluck('ca.id')
            ->all();
        $i = array_search($cursoAlumnoId, $ids);
        return $i === false ? count($ids) + 1 : $i + 1;
    }

    private static function segundoCurso(float $base): array
    {
        $fijo = (float) Configuracion::parametro('PRECIO_SEGUNDO_CURSO', 0);
        return $fijo > 0 && $fijo < $base ? [$fijo, '2.º curso'] : [$base, 'Precio del plan'];
    }

    private static function conDescuento(float $base, string $parametro, float $defecto, string $etiqueta): array
    {
        $porcentaje = min(100, max(0, (float) Configuracion::parametro($parametro, $defecto)));
        $regla = $porcentaje >= 100 ? "{$etiqueta} (gratis)" : "{$etiqueta} (-" . rtrim(rtrim(number_format($porcentaje, 2, '.', ''), '0'), '.') . '%)';
        return [$base * (1 - $porcentaje / 100), $regla];
    }
}
