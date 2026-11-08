<?php

namespace App\Services\Academia;

use Throwable;
use Carbon\Carbon;
use DomainException;
use App\Models\Academia\Curso;
use App\Models\Academia\Alumno;
use App\Enum\AccionAuditoriaEnum;
use Illuminate\Support\Facades\DB;
use App\Models\Seguridad\AuditoriaTabla;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Services\Academia\ExportacionAlumnos as X;

/**
 * Aplica las correcciones del Excel de alumnos por curso (ExportacionAlumnos) que el dueño devolvió:
 *  - "Alumnos por curso": cambios de pareja, valor especial y retiros, por id de matrícula.
 *  - "Nuevas matrículas": alumno + curso (y opcionalmente pareja y valor especial).
 *
 * Nunca escribe precios ni deudas: cambia solo los datos de los que sale el precio (cursos del alumno,
 * pareja, valor especial) usando las mismas funciones de la pantalla del curso, y Tarifas calcula el
 * precio. El ciclo actual se ajusta con la diferencia: a cada matrícula se le suma al saldo
 * (precio después − precio antes), como la opción "ajustar ciclo" de la pantalla del curso.
 * Así un 3.er curso, un retiro que sube el precio de los demás o una pareja nueva quedan bien cobrados.
 *
 * Todo o nada: con cualquier error no se cambia nada. Sin $aplicar se simula y se deshace (vista previa).
 */
class ImportacionCorrecciones
{
    private array $errores = [];
    private array $cambios = [];
    private array $alumnos = [];
    private array $cursos = [];

    public function procesar(string $archivo, bool $aplicar): array
    {
        $this->alumnos = DB::table('alumnos')->select('id', DB::raw("TRIM(CONCAT(COALESCE(nombres,''),' ',COALESCE(apellidos,''))) as nombre"), 'estado')
            ->get()->keyBy('id')->all();
        $this->cursos = DB::table('cursos as c')->leftJoin('ritmos as r', 'r.id', '=', 'c.ritmo_id')
            ->select('c.id', 'c.dia', 'c.hora', 'c.estado', 'c.activo', 'c.modalidad_id', DB::raw('COALESCE(c.nombre, r.nombre) as nombre'))
            ->get()->keyBy('id')->all();

        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $libro = $reader->load($archivo);
        } catch (Throwable $e) {
            return $this->resultado(false, [], ['No se pudo leer el archivo. Debe ser el Excel (.xlsx) descargado de "Alumnos por curso y deuda".']);
        }
        $existentes = $this->leerHoja($libro, X::HOJA_CURSOS, [X::COL_ID, X::COL_PAREJA, X::COL_VALOR_ESPECIAL, X::COL_MOTIVO, X::COL_RETIRAR]);
        $nuevas = $this->leerHoja($libro, X::HOJA_NUEVAS, [X::COL_ALUMNO, X::COL_CURSO, X::COL_PAREJA, X::COL_VALOR_ESPECIAL, X::COL_MOTIVO]);
        if ($existentes === null || $nuevas === null) {
            return $this->resultado(false, [], $this->errores);
        }

        $plan = $this->planear($existentes, $nuevas);
        if ($this->errores) {
            return $this->resultado(false, [], $this->errores);
        }
        if (!$plan['retiros'] && !$plan['altas'] && !$plan['parejas'] && !$plan['valores']) {
            return $this->resultado(false, [], [], 'El archivo no tiene cambios: las columnas azules están igual que en el sistema.');
        }

        DB::beginTransaction();
        try {
            $ajustes = $this->ejecutar($plan);
            if ($aplicar) {
                AuditoriaTabla::crear([
                    'id_recurso' => 0,
                    'nombre_recurso' => Alumno::class,
                    'descripcion_recurso' => 'Carga de correcciones de alumnos por curso',
                    'accion' => AccionAuditoriaEnum::MODIFICAR,
                    'recurso_original' => json_encode($this->cambios, JSON_UNESCAPED_UNICODE),
                    'recurso_resultante' => json_encode($ajustes, JSON_UNESCAPED_UNICODE),
                ]);
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (DomainException $e) {
            DB::rollBack();
            return $this->resultado(false, [], [$e->getMessage()]);
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
        return $this->resultado($aplicar, $ajustes);
    }

    /** Lee una hoja como [n.º de fila => [columna => valor]] con las columnas pedidas (por encabezado). */
    private function leerHoja($libro, string $titulo, array $columnas): ?array
    {
        $hoja = $libro->getSheetByName($titulo);
        if (!$hoja) {
            $this->errores[] = "Falta la hoja \"{$titulo}\". Suba el Excel descargado de \"Alumnos por curso y deuda\" sin cambiar los nombres de las hojas.";
            return null;
        }
        $datos = $hoja->toArray(null, false, false, true);
        $letras = array_flip(array_map(fn ($v) => trim((string) $v), $datos[1] ?? []));
        foreach ($columnas as $col) {
            if (!isset($letras[$col])) {
                $this->errores[] = "En la hoja \"{$titulo}\" falta la columna \"{$col}\". No cambie los encabezados ni borre columnas.";
                return null;
            }
        }
        $filas = [];
        foreach ($datos as $n => $fila) {
            if ($n === 1) {
                continue;
            }
            $filas[$n] = [];
            foreach ($columnas as $col) {
                $v = $fila[$letras[$col]] ?? null;
                $filas[$n][$col] = is_string($v) ? trim($v) : $v;
            }
        }
        return $filas;
    }

    private function planear(array $existentes, array $nuevas): array
    {
        $plan = ['retiros' => [], 'altas' => [], 'parejas' => [], 'valores' => []];
        $activas = DB::table('curso_alumno')->where('estado', 1)->get()->keyBy('id');
        $porCursoAlumno = $activas->keyBy(fn ($m) => "{$m->curso_id}-{$m->alumno_id}");

        foreach ($existentes as $n => $f) {
            if (!is_numeric($f[X::COL_ID]) || !(int) $f[X::COL_ID]) {
                continue; // fila de total o vacía
            }
            $donde = X::HOJA_CURSOS . ", fila {$n}";
            $m = $activas[(int) $f[X::COL_ID]] ?? null;
            if (!$m) {
                $this->errores[] = "{$donde}: esa matrícula ya no existe (el alumno fue retirado del curso). Descargue el archivo de nuevo.";
                continue;
            }
            if ($this->esSi($f[X::COL_RETIRAR])) {
                if (abs((float) $m->saldo) > 0.009) {
                    $this->errores[] = "{$donde}: no se puede retirar a {$this->nombreAlumno($m->alumno_id)} de {$this->nombreCurso($m->curso_id)} porque tiene "
                        . ((float) $m->saldo > 0 ? 'saldo pendiente' : 'saldo a favor') . ' de $' . number_format(abs((float) $m->saldo), 0, ',', '.')
                        . '. Registre el pago o la condonación primero.';
                    continue;
                }
                $plan['retiros'][] = $m;
                continue;
            }
            $pareja = $this->resolver($f[X::COL_PAREJA], $this->alumnos, 'alumno', $donde);
            if ($pareja !== false && $pareja !== ($m->pareja_alumno_id ? (int) $m->pareja_alumno_id : null)) {
                $plan['parejas'][] = ['curso' => (int) $m->curso_id, 'alumno' => (int) $m->alumno_id, 'pareja' => $pareja, 'donde' => $donde];
            }
            $valor = $this->numero($f[X::COL_VALOR_ESPECIAL], $donde);
            $motivo = $f[X::COL_MOTIVO] !== null && $f[X::COL_MOTIVO] !== '' ? mb_substr((string) $f[X::COL_MOTIVO], 0, 150) : null;
            $actual = $m->valor_especial !== null ? (float) $m->valor_especial : null;
            if ($valor !== false && ($this->distinto($valor, $actual) || ($valor !== null && $motivo !== $m->valor_especial_motivo))) {
                $plan['valores'][] = ['curso' => (int) $m->curso_id, 'alumno' => (int) $m->alumno_id, 'valor' => $valor, 'motivo' => $motivo, 'donde' => $donde];
            }
        }

        $altasVistas = [];
        foreach ($nuevas as $n => $f) {
            if (collect($f)->every(fn ($v) => $v === null || $v === '')) {
                continue;
            }
            $donde = X::HOJA_NUEVAS . ", fila {$n}";
            $alumno = $this->resolver($f[X::COL_ALUMNO], $this->alumnos, 'alumno', $donde);
            $curso = $this->resolver($f[X::COL_CURSO], $this->cursos, 'curso', $donde);
            if (!$alumno || !$curso) {
                if ($alumno === null || $curso === null) {
                    $this->errores[] = "{$donde}: elija el alumno y el curso.";
                }
                continue;
            }
            $c = $this->cursos[$curso];
            if (!$c->estado || !$c->activo) {
                $this->errores[] = "{$donde}: el curso {$this->nombreCurso($curso)} está inactivo.";
                continue;
            }
            if (isset($porCursoAlumno["{$curso}-{$alumno}"]) || isset($altasVistas["{$curso}-{$alumno}"])) {
                $this->errores[] = "{$donde}: {$this->nombreAlumno($alumno)} ya está matriculado en {$this->nombreCurso($curso)}.";
                continue;
            }
            $altasVistas["{$curso}-{$alumno}"] = true;
            $plan['altas'][] = ['curso' => $curso, 'alumno' => $alumno, 'donde' => $donde];
            $pareja = $this->resolver($f[X::COL_PAREJA], $this->alumnos, 'alumno', $donde);
            if ($pareja) {
                $plan['parejas'][] = ['curso' => $curso, 'alumno' => $alumno, 'pareja' => $pareja, 'donde' => $donde];
            }
            $valor = $this->numero($f[X::COL_VALOR_ESPECIAL], $donde);
            if ($valor !== false && $valor !== null) {
                $motivo = $f[X::COL_MOTIVO] !== null && $f[X::COL_MOTIVO] !== '' ? mb_substr((string) $f[X::COL_MOTIVO], 0, 150) : null;
                $plan['valores'][] = ['curso' => $curso, 'alumno' => $alumno, 'valor' => $valor, 'motivo' => $motivo, 'donde' => $donde];
            }
        }

        $this->validarParejas($plan, $porCursoAlumno, $altasVistas);
        return $plan;
    }

    /** La pareja debe quedar matriculada en el mismo curso y las parejas del archivo no pueden contradecirse. */
    private function validarParejas(array $plan, $porCursoAlumno, array $altas): void
    {
        $retirados = collect($plan['retiros'])->mapWithKeys(fn ($m) => ["{$m->curso_id}-{$m->alumno_id}" => true])->all();
        $deseo = [];
        $elegida = []; // curso => pareja => quién la eligió
        foreach ($plan['parejas'] as $p) {
            $deseo[$p['curso']][$p['alumno']] = $p['pareja'];
            if ($p['pareja'] === null) {
                continue;
            }
            $otro = $elegida[$p['curso']][$p['pareja']] ?? null;
            // A→B y B→A es la misma pareja; A→B y C→B no.
            if ($otro !== null && $otro !== $p['alumno'] && $otro !== $p['pareja']) {
                $this->errores[] = "{$p['donde']}: {$this->nombreAlumno($p['alumno'])} y {$this->nombreAlumno($otro)} eligieron la misma pareja "
                    . "({$this->nombreAlumno($p['pareja'])}) en {$this->nombreCurso($p['curso'])}.";
            }
            $elegida[$p['curso']][$p['pareja']] ??= $p['alumno'];
        }
        foreach ($plan['parejas'] as $p) {
            $b = $p['pareja'];
            if ($b === null) {
                continue;
            }
            $quien = $this->nombreAlumno($p['alumno']);
            if ($b === $p['alumno']) {
                $this->errores[] = "{$p['donde']}: {$quien} no puede ser su propia pareja.";
                continue;
            }
            $clave = "{$p['curso']}-{$b}";
            if ((!isset($porCursoAlumno[$clave]) && !isset($altas[$clave])) || isset($retirados[$clave])) {
                $this->errores[] = "{$p['donde']}: la pareja de {$quien} ({$this->nombreAlumno($b)}) no queda matriculada en {$this->nombreCurso($p['curso'])}. Matricúlela también en \"Nuevas matrículas\".";
                continue;
            }
            if (array_key_exists($b, $deseo[$p['curso']]) && $deseo[$p['curso']][$b] !== $p['alumno']) {
                $otra = $deseo[$p['curso']][$b];
                $this->errores[] = "{$p['donde']}: {$quien} queda en pareja con {$this->nombreAlumno($b)}, pero en el archivo {$this->nombreAlumno($b)} "
                    . ($otra ? "queda en pareja con {$this->nombreAlumno($otra)}" : 'queda individual') . " en {$this->nombreCurso($p['curso'])}.";
            }
        }
    }

    /** Aplica el plan (dentro de la transacción) y ajusta el saldo del ciclo actual con la diferencia de precio. */
    private function ejecutar(array $plan): array
    {
        $precios = fn () => DB::table('curso_alumno as ca')->join('cursos as c', 'c.id', '=', 'ca.curso_id')
            ->where('ca.estado', 1)->get(['ca.id', 'ca.curso_id', 'ca.alumno_id', 'c.modalidad_id'])
            ->filter(fn ($m) => Tarifas::cobra((int) $m->modalidad_id))
            ->mapWithKeys(fn ($m) => [$m->id => ['curso' => (int) $m->curso_id, 'alumno' => (int) $m->alumno_id] + Tarifas::precio((int) $m->id)]);
        $antes = $precios();

        foreach ($plan['retiros'] as $m) {
            DB::table('curso_alumno')->where('id', $m->id)->delete();
            // Quien pagaba en pareja con el retirado vuelve a pagar individual.
            DB::table('curso_alumno')->where('curso_id', $m->curso_id)->where('pareja_alumno_id', $m->alumno_id)
                ->update(['pareja_alumno_id' => null, 'updated_at' => Carbon::now()]);
            $this->cambio($m->alumno_id, $m->curso_id, 'Se retira del curso');
        }

        $nuevas = [];
        foreach ($plan['altas'] as $a) {
            $curso = Curso::find($a['curso']);
            $nuevas[] = Curso::matricular($curso, $a['alumno'], null, false);
            $this->cambio($a['alumno'], $a['curso'], 'Se matricula en el curso');
        }

        // Primero las que quedan individuales; luego los enlaces nuevos (emparejar enlaza a los dos).
        $parejas = collect($plan['parejas'])->sortBy(fn ($p) => $p['pareja'] === null ? 0 : 1);
        foreach ($parejas as $p) {
            Curso::emparejar($p['curso'], $p['alumno'], $p['pareja'], false);
            $this->cambio($p['alumno'], $p['curso'], $p['pareja'] ? "Paga en pareja con {$this->nombreAlumno($p['pareja'])}" : 'Paga individual (sin pareja)');
        }

        foreach ($plan['valores'] as $v) {
            Curso::valorEspecial($v['curso'], $v['alumno'], $v['valor'], $v['motivo'], false);
            $this->cambio($v['alumno'], $v['curso'], $v['valor'] === null
                ? 'Quita el valor especial (vuelve a la tarifa)'
                : 'Valor especial $' . number_format($v['valor'], 0, ',', '.') . ($v['motivo'] ? " ({$v['motivo']})" : ''));
        }

        // Matrículas nuevas: su primer ciclo con el precio final (ya con pareja o valor especial).
        $ajustes = [];
        foreach ($nuevas as $id) {
            $m = DB::table('curso_alumno')->where('id', $id)->first();
            $precio = Tarifas::precioDesde($id, Curso::find($m->curso_id), Carbon::parse($m->ciclo_inicio));
            DB::table('curso_alumno')->where('id', $id)->update(['saldo' => $precio['valor'], 'updated_at' => Carbon::now()]);
            $ajustes[] = $this->fila((int) $m->alumno_id, (int) $m->curso_id, null, $precio, $precio['valor'], 'Primer ciclo desde ' . Carbon::parse($m->ciclo_inicio)->format('d/m/Y'));
        }

        // Las demás: el ciclo actual se ajusta con la diferencia (como "ajustar ciclo" de la pantalla del curso).
        foreach ($precios() as $id => $despues) {
            if (in_array($id, $nuevas) || !isset($antes[$id])) {
                continue;
            }
            $delta = round($despues['valor'] - $antes[$id]['valor'], 2);
            if (abs($delta) >= 0.01) {
                DB::table('curso_alumno')->where('id', $id)->update(['saldo' => DB::raw('saldo + ' . (float) $delta), 'updated_at' => Carbon::now()]);
                $ajustes[] = $this->fila($despues['alumno'], $despues['curso'], $antes[$id], $despues, $delta, 'Ajuste del ciclo actual');
            } elseif ($despues['regla'] !== $antes[$id]['regla']) {
                // Cambia la forma de cobro pero no el valor (p. ej. valor especial → pareja con el mismo precio).
                $ajustes[] = $this->fila($despues['alumno'], $despues['curso'], $antes[$id], $despues, 0, 'Mismo valor, otra forma de cobro');
            }
        }

        return collect($ajustes)->sortBy(['alumno', 'curso'])->values()->all();
    }

    private function fila(int $alumnoId, int $cursoId, ?array $antes, array $despues, float $ajuste, string $nota): array
    {
        return [
            'alumno' => $this->nombreAlumno($alumnoId),
            'curso' => $this->nombreCurso($cursoId),
            'precio_antes' => $antes ? $antes['valor'] : null,
            'regla_antes' => $antes['regla'] ?? null,
            'precio_despues' => $despues['valor'],
            'regla_despues' => $despues['regla'],
            'ajuste_saldo' => $ajuste,
            'nota' => $nota,
        ];
    }

    private function cambio($alumnoId, $cursoId, string $texto): void
    {
        $this->cambios[] = ['alumno' => $this->nombreAlumno((int) $alumnoId), 'curso' => $this->nombreCurso((int) $cursoId), 'cambio' => $texto];
    }

    private function resultado(bool $aplicado, array $ajustes, array $errores = [], ?string $aviso = null): array
    {
        return [
            'aplicado' => $aplicado,
            'errores' => array_values($errores),
            'aviso' => $aviso,
            'cambios' => $this->cambios,
            'ajustes' => $ajustes,
            'total_ajuste' => round(collect($ajustes)->sum('ajuste_saldo'), 2),
        ];
    }

    /**
     * Id de un alumno o curso a partir de la celda: "Nombre [#12]" (de la lista) o el nombre exacto.
     * null = celda vacía; false = no se reconoce (deja el error).
     */
    private function resolver($texto, array $catalogo, string $tipo, string $donde): int|null|false
    {
        if ($texto === null || $texto === '') {
            return null;
        }
        $texto = (string) $texto;
        if (preg_match('/\[#(\d+)\]\s*$/', $texto, $m) && isset($catalogo[(int) $m[1]])) {
            return (int) $m[1];
        }
        $buscado = mb_strtolower(trim(preg_replace('/\s*\[#\d+\]\s*$/', '', $texto)));
        $iguales = array_filter($catalogo, fn ($x) => mb_strtolower(trim($x->nombre)) === $buscado);
        if (count($iguales) === 1) {
            return (int) array_key_first($iguales);
        }
        $this->errores[] = "{$donde}: " . (count($iguales) > 1 ? "hay varios {$tipo}s llamados \"{$texto}\"" : "no se encontró el {$tipo} \"{$texto}\"")
            . '. Elíjalo de la lista desplegable.';
        return false;
    }

    /** Valor de dinero de la celda: número, "$90.000" o "90000". null = vacía; false = inválido. */
    private function numero($valor, string $donde): float|null|false
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_numeric($valor)) {
            $n = (float) $valor;
        } else {
            $limpio = preg_replace('/[^\d]/', '', preg_replace('/[,.]\d{1,2}$/', '', (string) $valor));
            if ($limpio === '') {
                $this->errores[] = "{$donde}: el valor especial \"{$valor}\" no es un número.";
                return false;
            }
            $n = (float) $limpio;
        }
        if ($n < 0) {
            $this->errores[] = "{$donde}: el valor especial no puede ser negativo.";
            return false;
        }
        return round($n, 2);
    }

    private function distinto(?float $a, ?float $b): bool
    {
        return ($a === null) !== ($b === null) || ($a !== null && abs($a - $b) > 0.009);
    }

    private function esSi($v): bool
    {
        $v = mb_strtolower(trim((string) $v));
        return in_array($v, ['si', 'sí', 'x', '1', 'yes'], true);
    }

    private function nombreAlumno(int $id): string
    {
        return $this->alumnos[$id]->nombre ?? "Alumno #{$id}";
    }

    private function nombreCurso(int $id): string
    {
        $c = $this->cursos[$id] ?? null;
        if (!$c) {
            return "Curso #{$id}";
        }
        $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        return $c->nombre . ' (' . ($dias[$c->dia] ?? '') . ' ' . substr((string) $c->hora, 0, 5) . ')';
    }
}
