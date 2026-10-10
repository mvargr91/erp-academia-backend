<?php

namespace App\Console\Commands\Academias;

use Exception;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use App\Models\Academia\Curso;
use App\Models\Central\Academia;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Services\Academia\CalendarioCurso;
use App\Support\Academias\GestorAcademias;

/**
 * Carga inicial de una academia desde los Excel de su sistema anterior:
 *  - estudiantes: una hoja con las columnas Nombre, Correo electrónico y Teléfono.
 *  - cursos (opcional): una sola columna con bloques "Ritmo - Monday 20:00 - PROFESOR" seguidos de
 *    los nombres de sus alumnos. Crea los ritmos, profesores y cursos que falten y matricula.
 *
 * Se puede repetir: lo que ya existe (alumno, curso, matrícula) no se duplica. No envía correos.
 */
class ImportarAlumnos extends Command
{
    protected $signature = 'academia:importar-alumnos
        {codigo : Código de la academia}
        {estudiantes : Excel de estudiantes (Nombre, Correo electrónico, Teléfono)}
        {--cursos= : Excel de estudiantes por curso}
        {--sede= : Id de la sede de los alumnos y cursos (obligatorio si la academia tiene varias)}
        {--sin-correos : No guarda los correos (para academias de prueba: así no les llegan avisos)}
        {--cobrar : Carga el primer ciclo como deuda; por defecto las matrículas entran con saldo 0}
        {--simular : Hace todo el proceso y lo deshace: solo muestra el resultado}';

    protected $description = 'Importa alumnos, cursos y matrículas de una academia desde Excel';

    private const DIAS = [
        'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6,
        'domingo' => 0, 'lunes' => 1, 'martes' => 2, 'miercoles' => 3, 'jueves' => 4, 'viernes' => 5, 'sabado' => 6,
    ];

    // Segundos nombres frecuentes: "Ana María Herrera" es nombre compuesto, "Manuela Giraldo Mejía" no.
    private const SEGUNDOS_NOMBRES = [
        'maria', 'jose', 'pablo', 'david', 'camilo', 'andres', 'carlos', 'felipe', 'esteban', 'fernando', 'alejandro',
        'alejandra', 'sebastian', 'manuel', 'antonio', 'eduardo', 'alberto', 'enrique', 'mario', 'dario', 'fernanda',
        'cristina', 'carolina', 'marcela', 'patricia', 'isabel', 'sofia', 'camila', 'andrea', 'paola', 'milena',
        'valentina', 'daniel', 'diego', 'angel', 'mauricio', 'ignacio', 'elena', 'lucia', 'del', 'de', 'stiven',
        'steven', 'alexander', 'alexandra', 'yaneth', 'janeth', 'natalia', 'tatiana', 'johana', 'yuliana', 'liliana',
    ];

    private array $auditoria = [];
    private array $avisos = [];

    public function handle(): int
    {
        $simular = (bool) $this->option('simular');
        try {
            $personas = $this->leerEstudiantes($this->argument('estudiantes'));
            $bloques = $this->option('cursos') ? $this->leerCursos($this->option('cursos')) : [];
        } catch (Exception $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if (!Academia::where('codigo', $this->argument('codigo'))->where('activa', true)->exists()) {
            $this->error("No hay una academia activa con el código {$this->argument('codigo')}.");
            return self::FAILURE;
        }

        $estado = self::SUCCESS;
        GestorAcademias::paraCada(function (Academia $academia) use ($personas, $bloques, $simular, &$estado) {
            $this->info(($simular ? '[SIMULACIÓN] ' : '') . "{$academia->nombre} ({$academia->base_datos})");
            DB::beginTransaction();
            try {
                $resumen = $this->importar($personas, $bloques);
                $simular ? DB::rollBack() : DB::commit();
                $this->table(['Concepto', 'Cantidad'], collect($resumen)->map(fn ($v, $k) => [$k, $v])->values());
                $this->mostrarAvisos();
            } catch (Exception $e) {
                DB::rollBack();
                $this->error('No se importó nada: ' . $e->getMessage());
                $estado = self::FAILURE;
            }
        }, $this->argument('codigo'));

        return $estado;
    }

    private function importar(array $personas, array $bloques): array
    {
        $sedeId = $this->sede();
        $usuario = DB::table('usuarios')->orderBy('id')->first();
        $ahora = Carbon::now();
        $this->auditoria = [
            'usuario_creacion_id' => $usuario->id ?? 0,
            'usuario_creacion_nombre' => 'Importación',
            'usuario_modificacion_id' => $usuario->id ?? 0,
            'usuario_modificacion_nombre' => 'Importación',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ];
        $resumen = ['Alumnos nuevos' => 0, 'Alumnos que ya estaban' => 0];

        // Un alumno ya existe si coincide el nombre y además el correo o el teléfono.
        $existentes = [];
        foreach (DB::table('alumnos')->get(['id', 'nombres', 'apellidos', 'correo', 'telefono']) as $a) {
            $existentes[self::normalizar("{$a->nombres} {$a->apellidos}")][] = $a;
        }
        $porNombre = [];
        foreach ($personas as $p) {
            $clave = self::normalizar($p['nombre']);
            $igual = collect($existentes[$clave] ?? [])->first(fn ($a) => ($p['correo'] && self::normalizar($a->correo) === $p['correo'])
                || ($p['telefono'] && $a->telefono === $p['telefono']));
            if ($igual) {
                $id = $igual->id;
                $resumen['Alumnos que ya estaban']++;
            } else {
                [$nombres, $apellidos] = self::partirNombre($p['nombre']);
                $id = DB::table('alumnos')->insertGetId([
                    'sede_id' => $sedeId,
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                    'telefono' => $p['telefono'] ?: null,
                    'correo' => $this->option('sin-correos') ? null : ($p['correo'] ?: null),
                    'estado' => 1,
                ] + $this->auditoria);
                $resumen['Alumnos nuevos']++;
            }
            $porNombre[$clave][] = $id;
        }
        foreach ($porNombre as $clave => $ids) {
            if (count($ids) > 1) {
                $this->avisos['Homónimos (mismo nombre, distinto correo y teléfono): quedan como alumnos distintos'][] = $clave;
            }
        }

        if ($bloques) {
            $resumen += $this->importarCursos($bloques, $porNombre, $sedeId);
        }
        return $resumen;
    }

    private function importarCursos(array $bloques, array $porNombre, ?int $sedeId): array
    {
        $resumen = ['Ritmos nuevos' => 0, 'Profesores nuevos' => 0, 'Cursos nuevos' => 0, 'Cursos que ya estaban' => 0,
            'Matrículas nuevas' => 0, 'Matrículas que ya estaban' => 0, 'Matrículas sin cargar' => 0];
        $ritmos = DB::table('ritmos')->get(['id', 'nombre'])->mapWithKeys(fn ($r) => [self::normalizar($r->nombre) => $r->id])->all();
        $profesores = DB::table('profesores')->get(['id', 'nombres', 'apellidos'])
            ->mapWithKeys(fn ($p) => [self::normalizar("{$p->nombres} {$p->apellidos}") => $p->id])->all();
        $calendario = new CalendarioCurso();

        foreach ($bloques as $b) {
            $ritmoId = $ritmos[self::normalizar($b['ritmo'])] ?? null;
            if (!$ritmoId) {
                $ritmoId = $ritmos[self::normalizar($b['ritmo'])] = DB::table('ritmos')
                    ->insertGetId(['nombre' => $b['ritmo'], 'estado' => 1] + $this->auditoria);
                $resumen['Ritmos nuevos']++;
            }
            $profesorId = $profesores[self::normalizar($b['profesor'])] ?? null;
            if (!$profesorId) {
                [$nombres, $apellidos] = self::partirNombre(mb_convert_case($b['profesor'], MB_CASE_TITLE, 'UTF-8'));
                $profesorId = $profesores[self::normalizar($b['profesor'])] = DB::table('profesores')
                    ->insertGetId(['nombres' => $nombres, 'apellidos' => $apellidos, 'estado' => 1] + $this->auditoria);
                $resumen['Profesores nuevos']++;
            }

            $curso = Curso::where('ritmo_id', $ritmoId)->where('profesor_id', $profesorId)->where('sede_id', $sedeId)
                ->where('dia', $b['dia'])->where('hora', $b['hora'])->first();
            if ($curso) {
                $resumen['Cursos que ya estaban']++;
            } else {
                $curso = Curso::create([
                    'nombre' => $b['ritmo'], 'sede_id' => $sedeId, 'ritmo_id' => $ritmoId, 'profesor_id' => $profesorId,
                    'dia' => $b['dia'], 'hora' => $b['hora'], 'activo' => 1, 'estado' => 1,
                ] + $this->auditoria);
                $resumen['Cursos nuevos']++;
            }

            $matriculados = DB::table('curso_alumno')->where('curso_id', $curso->id)->pluck('alumno_id')->flip();
            $primeraClase = $calendario->primeraClase($curso, Carbon::today())->toDateString();
            foreach ($b['alumnos'] as $nombre) {
                $ids = array_values(array_unique($porNombre[self::normalizar($nombre)] ?? []));
                if (count($ids) !== 1) {
                    $motivo = $ids ? 'Matrículas sin cargar: hay varios alumnos con ese nombre' : 'Matrículas sin cargar: el alumno no está en el Excel de estudiantes';
                    $this->avisos[$motivo][] = trim($nombre) . " → {$b['titulo']}";
                    $resumen['Matrículas sin cargar']++;
                    continue;
                }
                if ($matriculados->has($ids[0])) {
                    $resumen['Matrículas que ya estaban']++;
                    continue;
                }
                $matriculaId = Curso::matricular($curso, $ids[0], $primeraClase, false);
                if (!$this->option('cobrar')) {
                    DB::table('curso_alumno')->where('id', $matriculaId)->update(['saldo' => 0]);
                }
                $matriculados[$ids[0]] = true;
                $resumen['Matrículas nuevas']++;
            }
        }
        return $resumen;
    }

    private function sede(): ?int
    {
        $sedes = DB::table('sedes')->where('estado', 1)->orderBy('id')->pluck('nombre', 'id');
        if ($this->option('sede')) {
            if (!$sedes->has((int) $this->option('sede'))) {
                throw new Exception('La sede ' . $this->option('sede') . ' no existe en esta academia.');
            }
            return (int) $this->option('sede');
        }
        if ($sedes->count() > 1) {
            throw new Exception('La academia tiene varias sedes: indique --sede= (' . $sedes->map(fn ($n, $id) => "{$id} = {$n}")->implode(', ') . ').');
        }
        return $sedes->keys()->first();
    }

    /** Filas del Excel de estudiantes, sin repetidos (mismo nombre y mismo correo o teléfono). */
    private function leerEstudiantes(string $ruta): array
    {
        $filas = $this->leerHoja($ruta);
        $titulos = array_map(fn ($t) => self::normalizar($t), array_shift($filas) ?? []);
        $columna = fn (string $palabra) => collect($titulos)->search(fn ($t) => str_contains($t, $palabra));
        [$cNombre, $cCorreo, $cTelefono] = [$columna('nombre'), $columna('correo'), $columna('telefono')];
        if ($cNombre === false) {
            throw new Exception("El Excel de estudiantes no tiene la columna Nombre: {$ruta}");
        }

        $personas = [];
        $porNombre = [];
        foreach ($filas as $fila) {
            $nombre = self::limpiar($fila[$cNombre] ?? '');
            if ($nombre === '') {
                continue;
            }
            $correo = $cCorreo === false ? '' : mb_strtolower(self::limpiar($fila[$cCorreo] ?? ''));
            $telefono = $cTelefono === false ? '' : self::limpiar($fila[$cTelefono] ?? '');
            if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                $this->avisos['Correos mal escritos (se guardan tal cual: corríjalos)'][] = "{$nombre}: {$correo}";
            }
            if ($telefono !== '' && !preg_match('/^3\d{9}$/', $telefono)) {
                $this->avisos['Teléfonos que no parecen un celular colombiano'][] = "{$nombre}: {$telefono}";
            }

            $clave = self::normalizar($nombre);
            foreach ($porNombre[$clave] ?? [] as $i) {
                if (($correo !== '' && $personas[$i]['correo'] === $correo) || ($telefono !== '' && $personas[$i]['telefono'] === $telefono)) {
                    $personas[$i]['correo'] = $personas[$i]['correo'] ?: $correo;
                    $personas[$i]['telefono'] = $personas[$i]['telefono'] ?: $telefono;
                    $this->avisos['Repetidos en el Excel (se cargan una sola vez)'][] = $nombre;
                    continue 2;
                }
            }
            $porNombre[$clave][] = count($personas);
            $personas[] = ['nombre' => $nombre, 'correo' => $correo, 'telefono' => $telefono];
        }
        return $personas;
    }

    /** Bloques del Excel de cursos: [titulo, ritmo, dia, hora, profesor, alumnos[]]. */
    private function leerCursos(string $ruta): array
    {
        $bloques = [];
        $actual = null;
        foreach ($this->leerHoja($ruta) as $fila) {
            $texto = self::limpiar($fila[0] ?? '');
            if ($texto === '') {
                continue;
            }
            if (preg_match('/^(.+?)\s+-\s+(\p{L}+)\s+(\d{1,2}):(\d{2})\s+-\s+(.+)$/u', $texto, $m)
                && isset(self::DIAS[self::normalizar($m[2])])) {
                $dia = self::DIAS[self::normalizar($m[2])];
                $hora = sprintf('%02d:%s:00', $m[3], $m[4]);
                // Un mismo curso escrito dos veces (mayúsculas, espacios) es un solo bloque.
                $clave = self::normalizar("{$m[1]}|{$dia}|{$hora}|{$m[5]}");
                $bloques[$clave] ??= ['titulo' => $texto, 'ritmo' => self::limpiar($m[1]), 'dia' => $dia, 'hora' => $hora,
                    'profesor' => self::limpiar($m[5]), 'alumnos' => []];
                $actual = $clave;
            } elseif ($actual === null) {
                throw new Exception("El Excel de cursos debe empezar con un curso (\"Ritmo - Monday 20:00 - Profesor\"): {$texto}");
            } else {
                $bloques[$actual]['alumnos'][] = $texto;
            }
        }
        return array_values($bloques);
    }

    private function leerHoja(string $ruta): array
    {
        if (!is_file($ruta)) {
            throw new Exception("No existe el archivo {$ruta}");
        }
        return IOFactory::load($ruta)->getActiveSheet()->toArray(null, false, false, false);
    }

    private function mostrarAvisos(): void
    {
        foreach ($this->avisos as $titulo => $lineas) {
            $this->warn("{$titulo}: " . count($lineas));
            foreach (array_slice($lineas, 0, $this->output->isVerbose() ? null : 15) as $linea) {
                $this->line("  - {$linea}");
            }
            if (!$this->output->isVerbose() && count($lineas) > 15) {
                $this->line('  … (use -v para verlos todos)');
            }
        }
    }

    /** Celda a texto: sin espacios sobrantes y sin el ".0" de los números. */
    private static function limpiar($valor): string
    {
        if (is_float($valor) && floor($valor) == $valor) {
            $valor = number_format($valor, 0, '', '');
        }
        return trim(preg_replace('/\s+/u', ' ', (string) $valor));
    }

    /** Para comparar: sin tildes, en minúsculas y con espacios simples. */
    private static function normalizar(?string $texto): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii((string) $texto))));
    }

    /** "Ana María Herrera" → [Ana María, Herrera]; "Manuela Giraldo Mejía" → [Manuela, Giraldo Mejía]. */
    private static function partirNombre(string $nombre): array
    {
        $palabras = explode(' ', self::limpiar($nombre));
        $cuantos = match (true) {
            count($palabras) <= 2 => 1,
            count($palabras) === 3 => in_array(self::normalizar($palabras[1]), self::SEGUNDOS_NOMBRES, true) ? 2 : 1,
            default => 2,
        };
        return [implode(' ', array_slice($palabras, 0, $cuantos)), implode(' ', array_slice($palabras, $cuantos))];
    }
}
