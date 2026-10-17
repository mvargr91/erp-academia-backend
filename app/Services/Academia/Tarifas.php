<?php

namespace App\Services\Academia;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Precio por ciclo de una matrícula de curso grupal.
 *
 * Es la única fuente del precio de un curso (Configuración → Tarifas); los cursos no tienen precio
 * propio. Cada modalidad (ciclo de 4 clases, de 8…) tiene su tarifa: el total que paga un alumno por
 * ciclo según cuántos cursos de esa modalidad toma, y otro para quienes pagan en pareja (total de los dos).
 * Individual o en pareja es de cada matrícula, no del curso: en un mismo curso unos alumnos pagan
 * solos y otros dos pagan juntos (curso_alumno.pareja_alumno_id, enlazado en ambas matrículas).
 *   Individual: 1 curso 100.000 · 2 cursos 180.000 · 3 cursos 230.000 …
 *   Pareja:     1 curso 180.000 · 2 cursos 340.000 …
 *
 * Como cada curso tiene su propio ciclo, el total se reparte por posición (orden de matrícula entre
 * los cursos de la misma modalidad; los de otra modalidad no suman descuento):
 * el curso N cobra total(N) − total(N−1). Con el ejemplo, el 3.er curso suma 50.000 y el alumno
 * paga 230.000 entre los tres. En pareja cada persona paga la mitad de lo que suma el curso.
 *
 * El primer valor de la tarifa individual es el precio de un curso. Si la tarifa no llega hasta esa
 * cantidad de cursos, el curso adicional cobra ese precio completo (academia sin descuento: basta
 * con definir el precio de 1 curso). Sin tarifa individual los cursos de esa modalidad no causan cobro.
 *
 * Excepción: una matrícula puede tener un valor especial pactado con el alumno
 * (curso_alumno.valor_especial: beca, convenio, cortesía). Ese alumno paga ese valor por ciclo en
 * ese curso en lugar de la tarifa; el curso sigue contando en el orden de sus demás cursos.
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

    /** @var array<string, array<int, array>> Escalas ya leídas: base de datos (una por academia) => modalidad => escalas. */
    private static array $cache = [];

    /**
     * @param int $cursoAlumnoId  matrícula
     * @return array{valor: float, base: float, regla: string, posicion: int}
     */
    public static function precio(int $cursoAlumnoId): array
    {
        $m = DB::table('curso_alumno as ca')
            ->join('cursos as c', 'c.id', '=', 'ca.curso_id')
            ->where('ca.id', $cursoAlumnoId)
            ->select('ca.id', 'ca.alumno_id', 'ca.valor_especial', 'c.modalidad_id', DB::raw(self::SQL_EN_PAREJA . ' as en_pareja'))
            ->first();
        if (!$m) {
            return ['valor' => 0.0, 'base' => 0.0, 'regla' => 'Sin matrícula', 'posicion' => 0];
        }
        $modalidadId = (int) $m->modalidad_id;
        $posicion = self::posicion($m->alumno_id, $m->id, $modalidadId);
        if ($m->valor_especial !== null) {
            return ['valor' => round((float) $m->valor_especial, 2), 'base' => self::precioBase($modalidadId), 'regla' => 'Valor especial', 'posicion' => $posicion];
        }
        return self::calcular($modalidadId, $posicion, (bool) $m->en_pareja);
    }

    /**
     * Precio de la matrícula para el periodo que empieza en $inicio. Solo cambia en modalidad mensual
     * (ver alMes); por ciclo de clases es el de siempre.
     * @return array{valor: float, base: float, regla: string, posicion: int}
     */
    public static function precioDesde(int $cursoAlumnoId, object $curso, Carbon $inicio): array
    {
        return self::alMes(self::precio($cursoAlumnoId), $curso, $inicio);
    }

    /**
     * Mensualidad: se cobra completa aunque el mes tenga 4 o 5 semanas. Si el periodo empieza a mitad de
     * mes (primer mes del alumno, curso reactivado) se cobran solo las clases que le quedan a ese mes, y un
     * mes sin ninguna clase (academia cerrada) no se cobra.
     */
    public static function alMes(array $precio, object $curso, Carbon $inicio): array
    {
        if (!CalendarioCurso::esMensual($curso)) {
            return $precio;
        }
        [$quedan, $total] = (new CalendarioCurso())->clasesDelMes($curso, $inicio);
        if ($quedan === 0 || $total === 0) {
            return ['valor' => 0.0, 'regla' => 'Mes sin clases'] + $precio;
        }
        if ($quedan < $total) {
            return ['valor' => round($precio['valor'] * $quedan / $total, 2), 'regla' => "{$precio['regla']} · {$quedan} de {$total} clases del mes"] + $precio;
        }
        return $precio;
    }

    /** Precio de un curso de la modalidad (tarifa individual de 1 curso); 0 si aún no tiene tarifa. */
    public static function precioBase(int $modalidadId): float
    {
        return (float) (self::escalas($modalidadId)[self::INDIVIDUAL][1] ?? 0);
    }

    /** ¿Los cursos de la modalidad causan cobro? Solo si tiene tarifa. */
    public static function cobra(int $modalidadId): bool
    {
        return self::precioBase($modalidadId) > 0;
    }

    /**
     * Precio de un ciclo según la posición del curso entre los del alumno (`base` = precio de un curso).
     * Sirve también para cotizar antes de matricular (Matriculas::cotizar).
     *
     * @return array{valor: float, base: float, regla: string, posicion: int}
     */
    public static function calcular(int $modalidadId, int $posicion, bool $enPareja = false): array
    {
        $escalas = self::escalas($modalidadId);
        $base = self::precioBase($modalidadId);
        if ($base <= 0) {
            return ['valor' => 0.0, 'base' => 0.0, 'regla' => 'Sin tarifa', 'posicion' => $posicion];
        }
        // Más cursos de los que cubre la tarifa: el adicional cobra el precio completo de un curso.
        [$valor, $regla] = [$base, $posicion === 1 ? 'Tarifa individual' : "{$posicion}.º curso"];

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
     * Escalas de una modalidad en la academia activa: tipo => [cantidad de cursos => total].
     * @return array{individual: array<int, float>, pareja: array<int, float>}
     */
    public static function escalas(int $modalidadId): array
    {
        $baseDatos = DB::connection()->getDatabaseName();
        if (!isset(self::$cache[$baseDatos])) {
            $porModalidad = [];
            foreach (DB::table('escalas_precio')->orderBy('cantidad')->get() as $fila) {
                $porModalidad[(int) $fila->modalidad_id][$fila->tipo][(int) $fila->cantidad] = (float) $fila->total;
            }
            self::$cache[$baseDatos] = $porModalidad;
        }
        return (self::$cache[$baseDatos][$modalidadId] ?? []) + array_fill_keys(self::TIPOS, []);
    }

    /**
     * Reemplaza las escalas de una modalidad. Cada escala es la lista de totales en orden:
     * el primero para 1 curso, el segundo para 2, etc.
     *
     * @param array<string, float[]> $escalas  tipo => totales
     */
    public static function guardarEscalas(int $modalidadId, array $escalas): void
    {
        DB::table('escalas_precio')->where('modalidad_id', $modalidadId)->delete();
        $ahora = now();
        foreach (self::TIPOS as $tipo) {
            foreach (array_values($escalas[$tipo] ?? []) as $i => $total) {
                DB::table('escalas_precio')->insert([
                    'modalidad_id' => $modalidadId, 'tipo' => $tipo, 'cantidad' => $i + 1, 'total' => $total,
                    'created_at' => $ahora, 'updated_at' => $ahora,
                ]);
            }
        }
        self::olvidarCache();
    }

    public static function olvidarCache(): void
    {
        unset(self::$cache[DB::connection()->getDatabaseName()]);
    }

    /** Lugar (1, 2, 3…) de la matrícula entre los cursos activos del alumno en la modalidad, por orden de matrícula. */
    public static function posicion(int $alumnoId, int $cursoAlumnoId, int $modalidadId): int
    {
        $ids = self::matriculasPorCiclo($alumnoId, $modalidadId);
        $i = array_search($cursoAlumnoId, $ids);
        return $i === false ? count($ids) + 1 : $i + 1;
    }

    /** Matrículas (curso_alumno.id) del alumno en cursos activos de la modalidad, en orden de matrícula. */
    public static function matriculasPorCiclo(int $alumnoId, int $modalidadId): array
    {
        return DB::table('curso_alumno as ca')
            ->join('cursos as c', 'c.id', '=', 'ca.curso_id')
            ->where('ca.alumno_id', $alumnoId)
            ->where('c.modalidad_id', $modalidadId)
            ->where('ca.estado', 1)
            ->where('c.estado', 1)
            ->where('c.activo', 1)
            ->orderBy('ca.fecha_matricula')
            ->orderBy('ca.id')
            ->pluck('ca.id')
            ->all();
    }
}
