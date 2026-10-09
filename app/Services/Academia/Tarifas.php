<?php

namespace App\Services\Academia;

use Illuminate\Support\Facades\DB;

/**
 * Precio por ciclo de una matrícula de curso grupal.
 *
 * Cada academia puede definir escalas de precio (Configuración → Tarifas): el total que paga un
 * alumno por ciclo según cuántos cursos toma, y otra para quienes pagan en pareja (total de los dos).
 * Individual o en pareja es de cada matrícula, no del curso: en un mismo curso unos alumnos pagan
 * solos y otros dos pagan juntos (curso_alumno.pareja_alumno_id, enlazado en ambas matrículas).
 *   Individual: 1 curso 100.000 · 2 cursos 180.000 · 3 cursos 230.000 …
 *   Pareja:     1 curso 180.000 · 2 cursos 340.000 …
 *
 * Como cada curso tiene su propio ciclo, el total se reparte por posición (orden de matrícula):
 * el curso N cobra total(N) − total(N−1). Con el ejemplo, el 3.er curso suma 50.000 y el alumno
 * paga 230.000 entre los tres. En pareja cada persona paga la mitad de lo que suma el curso.
 *
 * Las escalas son opcionales: si no hay escala (o no llega hasta esa cantidad de cursos), el curso
 * cobra el precio de su plan.
 *
 * Se calcula al cobrar cada ciclo, así que si el alumno se retira de un curso los demás
 * se recalculan solos desde el siguiente ciclo.
 */
class Tarifas
{
    public const INDIVIDUAL = 'individual';
    public const PAREJA = 'pareja';
    public const TIPOS = [self::INDIVIDUAL, self::PAREJA];

    /**
     * ¿La matrícula `ca` paga en pareja? Solo si su pareja sigue matriculada en el mismo curso y la
     * tiene a ella como pareja (así un retiro deja al otro pagando individual sin tocar nada más).
     */
    public const SQL_EN_PAREJA = 'EXISTS (SELECT 1 FROM curso_alumno pj WHERE pj.curso_id = ca.curso_id'
        . ' AND pj.alumno_id = ca.pareja_alumno_id AND pj.pareja_alumno_id = ca.alumno_id AND pj.estado = 1)';

    /** @var array<string, array> Escalas ya leídas, por base de datos (una por academia). */
    private static array $cache = [];

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
            ->select('ca.id', 'ca.alumno_id', 'p.valor as valor_plan', DB::raw(self::SQL_EN_PAREJA . ' as en_pareja'))
            ->first();
        if (!$m) {
            return ['valor' => 0.0, 'base' => 0.0, 'regla' => 'Sin matrícula', 'posicion' => 0];
        }
        return self::calcular(
            (float) ($m->valor_plan ?? 0),
            self::posicion($m->alumno_id, $m->id),
            (bool) $m->en_pareja
        );
    }

    /**
     * Precio de un ciclo dado el valor del plan y la posición del curso entre los del alumno.
     * Sirve también para cotizar antes de matricular (Matriculas::cotizar).
     *
     * @return array{valor: float, base: float, regla: string, posicion: int}
     */
    public static function calcular(float $base, int $posicion, bool $enPareja = false): array
    {
        $escalas = self::escalas();
        [$valor, $regla] = [$base, 'Precio del plan'];

        // En pareja se usa su escala si llega hasta esta posición; si no, la individual.
        foreach ($enPareja ? [self::PAREJA, self::INDIVIDUAL] : [self::INDIVIDUAL] as $tipo) {
            $suma = self::loQueSuma($escalas[$tipo], $posicion);
            if ($suma === null) {
                continue;
            }
            $pareja = $tipo === self::PAREJA;
            $valor = $pareja ? $suma / 2 : $suma;
            $regla = match (true) {
                $posicion === 1 => $pareja ? 'Tarifa pareja' : 'Tarifa individual',
                default => ($pareja ? 'Pareja · ' : '') . "{$posicion}.º curso",
            };
            break;
        }

        return ['valor' => round(max(0, $valor), 2), 'base' => $base, 'regla' => $regla, 'posicion' => $posicion];
    }

    /** Lo que agrega al total el curso en esa posición, o null si la escala no llega hasta ahí. */
    private static function loQueSuma(array $escala, int $posicion): ?float
    {
        if (!isset($escala[$posicion]) || ($posicion > 1 && !isset($escala[$posicion - 1]))) {
            return null;
        }
        return $escala[$posicion] - ($escala[$posicion - 1] ?? 0);
    }

    /**
     * Escalas de la academia activa: tipo => [cantidad de cursos => total].
     * @return array{individual: array<int, float>, pareja: array<int, float>}
     */
    public static function escalas(): array
    {
        $baseDatos = DB::connection()->getDatabaseName();
        if (!isset(self::$cache[$baseDatos])) {
            $escalas = array_fill_keys(self::TIPOS, []);
            foreach (DB::table('escalas_precio')->orderBy('cantidad')->get() as $fila) {
                $escalas[$fila->tipo][(int) $fila->cantidad] = (float) $fila->total;
            }
            self::$cache[$baseDatos] = $escalas;
        }
        return self::$cache[$baseDatos];
    }

    /**
     * Reemplaza las escalas de la academia. Cada escala es la lista de totales en orden:
     * el primero para 1 curso, el segundo para 2, etc.
     *
     * @param array<string, float[]> $escalas  tipo => totales
     */
    public static function guardarEscalas(array $escalas): void
    {
        DB::table('escalas_precio')->delete();
        $ahora = now();
        foreach (self::TIPOS as $tipo) {
            foreach (array_values($escalas[$tipo] ?? []) as $i => $total) {
                DB::table('escalas_precio')->insert([
                    'tipo' => $tipo, 'cantidad' => $i + 1, 'total' => $total, 'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
            }
        }
        unset(self::$cache[DB::connection()->getDatabaseName()]);
    }

    /** Lugar (1, 2, 3…) de la matrícula entre los cursos que el alumno paga por ciclo, por orden de matrícula. */
    public static function posicion(int $alumnoId, int $cursoAlumnoId): int
    {
        $ids = self::matriculasPorCiclo($alumnoId);
        $i = array_search($cursoAlumnoId, $ids);
        return $i === false ? count($ids) + 1 : $i + 1;
    }

    /** Matrículas (curso_alumno.id) que el alumno paga por ciclo, en orden de matrícula. */
    public static function matriculasPorCiclo(int $alumnoId): array
    {
        return DB::table('curso_alumno as ca')
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
    }
}
