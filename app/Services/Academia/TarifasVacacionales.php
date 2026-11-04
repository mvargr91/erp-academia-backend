<?php

namespace App\Services\Academia;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Precio de una inscripción a un curso vacacional, con la misma dinámica de Tarifas (cursos regulares):
 * una sola escala para todos los vacacionales con el total según cuántos cursos toma el alumno en la
 * temporada, individual y en pareja (total de los dos; cada uno paga la mitad de lo que suma su curso).
 *
 * Temporada: los cursos vacacionales que no han terminado (tienen alguna clase de hoy en adelante). El
 * curso N del alumno en la temporada cobra total(N) − total(N−1); si la escala no llega hasta ahí, el
 * precio de 1 curso. Sin escala individual se usa el valor propio del curso (como antes de las tarifas).
 * El valor se fija al inscribir y se puede ajustar a mano en la inscripción.
 */
class TarifasVacacionales
{
    public static function escalas(): array
    {
        $escalas = array_fill_keys(Tarifas::TIPOS, []);
        foreach (DB::table('escalas_precio_vacacional')->orderBy('cantidad')->get() as $fila) {
            $escalas[$fila->tipo][(int) $fila->cantidad] = (float) $fila->total;
        }
        return $escalas;
    }

    /** Reemplaza las escalas: tipo => lista de totales (el primero para 1 curso, el segundo para 2…). */
    public static function guardarEscalas(array $escalas): void
    {
        DB::table('escalas_precio_vacacional')->delete();
        $ahora = Carbon::now();
        foreach (Tarifas::TIPOS as $tipo) {
            foreach (array_values($escalas[$tipo] ?? []) as $i => $total) {
                DB::table('escalas_precio_vacacional')->insert([
                    'tipo' => $tipo, 'cantidad' => $i + 1, 'total' => $total, 'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
            }
        }
    }

    /** Inscripciones activas del alumno en cursos de la temporada (sin terminar), sin contar $excluir. */
    public static function enTemporada(int $alumnoId, ?int $excluir = null): int
    {
        return DB::table('vacacional_inscripciones as i')
            ->where('i.alumno_id', $alumnoId)->where('i.estado', 1)
            ->when($excluir, fn ($q) => $q->where('i.id', '!=', $excluir))
            ->whereExists(fn ($q) => $q->from('vacacional_curso_fechas as f')->whereColumn('f.curso_id', 'i.curso_id')
                ->where('f.fecha', '>=', Carbon::today()->toDateString()))
            ->count();
    }

    /**
     * Precio del curso en esa posición de la temporada.
     * @return array{valor: float, regla: string, posicion: int}
     */
    public static function calcular(object $curso, int $posicion, bool $enPareja = false): array
    {
        $escalas = self::escalas();
        if (!$escalas[Tarifas::INDIVIDUAL]) {
            return ['valor' => (float) $curso->valor, 'regla' => 'Valor del curso', 'posicion' => $posicion];
        }
        [$valor, $regla] = [$escalas[Tarifas::INDIVIDUAL][1], $posicion === 1 ? 'Tarifa individual' : "{$posicion}.º curso"];
        foreach ($enPareja ? [Tarifas::PAREJA, Tarifas::INDIVIDUAL] : [Tarifas::INDIVIDUAL] as $tipo) {
            $escala = $escalas[$tipo];
            if (!isset($escala[$posicion]) || ($posicion > 1 && !isset($escala[$posicion - 1]))) {
                continue;
            }
            $suma = $escala[$posicion] - ($escala[$posicion - 1] ?? 0);
            $pareja = $tipo === Tarifas::PAREJA;
            $valor = $pareja ? $suma / 2 : $suma;
            $regla = ($pareja ? 'Pareja · ' : '') . ($posicion === 1 ? ($pareja ? '1 curso' : 'Tarifa individual') : "{$posicion}.º curso");
            break;
        }
        return ['valor' => round(max(0, $valor), 2), 'regla' => $regla, 'posicion' => $posicion];
    }

    /**
     * Cotiza cursos para un alumno (null = nuevo, sin inscripciones): cada curso toma la posición
     * siguiente a las que ya tiene en la temporada, en el orden en que llegan.
     * @param array $cursos [{ curso_id, pareja_alumno_id? }]
     * @return array<int, array{curso_id: int, valor: float, regla: string, posicion: int}>
     */
    public static function cotizar(?int $alumnoId, array $cursos): array
    {
        $posicion = $alumnoId ? self::enTemporada($alumnoId) : 0;
        $resultado = [];
        foreach ($cursos as $c) {
            $curso = DB::table('vacacional_cursos')->find($c['curso_id'] ?? 0);
            if (!$curso) {
                continue;
            }
            $resultado[] = ['curso_id' => (int) $curso->id] + self::calcular($curso, ++$posicion, !empty($c['pareja_alumno_id']));
        }
        return $resultado;
    }

    /** Precio de una inscripción ya guardada, según su posición en la temporada y si tiene pareja. */
    public static function precioDe(int $inscripcionId): array
    {
        $i = DB::table('vacacional_inscripciones')->find($inscripcionId);
        $curso = DB::table('vacacional_cursos')->find($i->curso_id);
        // Posición: las inscripciones de la temporada hechas antes que esta, más ella.
        $antes = DB::table('vacacional_inscripciones as x')
            ->where('x.alumno_id', $i->alumno_id)->where('x.estado', 1)->where('x.id', '<', $i->id)
            ->whereExists(fn ($q) => $q->from('vacacional_curso_fechas as f')->whereColumn('f.curso_id', 'x.curso_id')
                ->where('f.fecha', '>=', Carbon::today()->toDateString()))
            ->count();
        return self::calcular($curso, $antes + 1, (bool) $i->pareja_alumno_id);
    }
}
