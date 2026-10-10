<?php

namespace App\Models\Academia;

use Exception;
use DomainException;
use Carbon\Carbon;
use App\Enum\AccionAuditoriaEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\AuditoriaTabla;
use App\Services\Academia\Tarifas;
use App\Services\Academia\CalendarioCurso;
use App\Services\Academia\NotificadorAcademia;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Curso extends Model
{
    use HasFactory;

    protected $table = 'cursos';

    protected $fillable = [
        'nombre',
        'sede_id',
        'ritmo_id',
        'profesor_id',
        'dia',
        'hora',
        'fecha_inicio',
        'cupo_max',
        'activo',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    public static function obtenerColeccionLigera($dto)
    {
        // Con varias sedes el nombre lleva la sede, para distinguir cursos del mismo ritmo y hora.
        $nombre = DB::table('sedes')->count() > 1
            ? "CONCAT(ritmos.nombre,' - ',DATE_FORMAT(cursos.hora,'%H:%i'),' · ',COALESCE(sedes.nombre,''))"
            : "CONCAT(ritmos.nombre,' - ',DATE_FORMAT(cursos.hora,'%H:%i'))";
        $query = DB::table('cursos')
            ->leftJoin('ritmos', 'ritmos.id', '=', 'cursos.ritmo_id')
            ->leftJoin('sedes', 'sedes.id', '=', 'cursos.sede_id')
            ->select('cursos.id', DB::raw("{$nombre} as nombre"), 'cursos.sede_id')
            ->where('cursos.estado', 1)
            ->orderBy('ritmos.nombre', 'asc');
        return Sede::filtrar($query, 'cursos.sede_id')->get();
    }

    public static function obtenerColeccion($dto)
    {
        $query = DB::table('cursos')
            ->leftJoin('ritmos', 'ritmos.id', '=', 'cursos.ritmo_id')
            ->leftJoin('profesores', 'profesores.id', '=', 'cursos.profesor_id')
            ->leftJoin('sedes', 'sedes.id', '=', 'cursos.sede_id')
            ->select(
                'cursos.id',
                'cursos.nombre',
                'cursos.sede_id',
                'sedes.nombre as sede_nombre',
                'cursos.ritmo_id',
                'ritmos.nombre as ritmo_nombre',
                'cursos.profesor_id',
                DB::raw("CONCAT(COALESCE(profesores.nombres,''),' ',COALESCE(profesores.apellidos,'')) as profesor_nombre"),
                'cursos.dia',
                'cursos.hora',
                'cursos.fecha_inicio',
                'cursos.cupo_max',
                'cursos.activo',
                DB::raw('(SELECT COUNT(*) FROM curso_alumno WHERE curso_alumno.curso_id = cursos.id AND curso_alumno.estado = 1) as matriculados'),
                'cursos.estado',
                'cursos.usuario_creacion_id',
                'cursos.usuario_creacion_nombre',
                'cursos.usuario_modificacion_id',
                'cursos.usuario_modificacion_nombre',
                'cursos.created_at as fecha_creacion',
                'cursos.updated_at as fecha_modificacion',
            );

        Sede::filtrar($query, 'cursos.sede_id');

        if (isset($dto['nombre'])) {
            $query->where('ritmos.nombre', 'like', '%' . $dto['nombre'] . '%');
        }
        if (isset($dto['ritmo_id'])) {
            $query->where('cursos.ritmo_id', $dto['ritmo_id']);
        }
        if (isset($dto['profesor_id'])) {
            $query->where('cursos.profesor_id', $dto['profesor_id']);
        }

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0) {
            foreach ($dto['ordenar_por'] as $attribute => $value) {
                if ($attribute == 'ritmo_nombre') {
                    $query->orderBy('ritmos.nombre', $value);
                }
                if ($attribute == 'sede_nombre') {
                    $query->orderBy('sedes.nombre', $value);
                }
                if ($attribute == 'profesor_nombre') {
                    $query->orderBy('profesores.nombres', $value);
                }
                if (in_array($attribute, ['dia', 'hora', 'fecha_inicio', 'activo', 'estado', 'usuario_creacion_nombre'])) {
                    $query->orderBy('cursos.' . $attribute, $value);
                }
                if ($attribute == 'fecha_creacion') {
                    $query->orderBy('cursos.created_at', $value);
                }
                if ($attribute == 'fecha_modificacion') {
                    $query->orderBy('cursos.updated_at', $value);
                }
            }
        } else {
            $query->orderBy('cursos.updated_at', 'desc');
        }

        $cursos = $query->paginate($dto['limite'] ?? 100);

        return [
            'datos' => $cursos->items(),
            'desde' => $cursos->firstItem(),
            'hasta' => $cursos->lastItem(),
            'por_pagina' => $cursos->perPage(),
            'pagina_actual' => $cursos->currentPage(),
            'ultima_pagina' => $cursos->lastPage(),
            'total' => $cursos->total(),
        ];
    }

    public static function cargar($id)
    {
        $curso = Curso::find($id);

        // Alumnos matriculados en el curso (para el multiselect del formulario).
        $matriculados = DB::table('curso_alumno')
            ->join('alumnos', 'alumnos.id', '=', 'curso_alumno.alumno_id')
            ->where('curso_alumno.curso_id', $id)
            ->where('curso_alumno.estado', 1)
            ->leftJoin('alumnos as pareja', 'pareja.id', '=', 'curso_alumno.pareja_alumno_id')
            ->select(
                'alumnos.id',
                DB::raw("CONCAT(alumnos.nombres,' ',alumnos.apellidos) as nombre"),
                'curso_alumno.saldo',
                'curso_alumno.ultima_fecha_pago',
                'curso_alumno.pareja_alumno_id',
                DB::raw("CONCAT(pareja.nombres,' ',pareja.apellidos) as pareja_nombre"),
                'curso_alumno.valor_especial',
                'curso_alumno.valor_especial_motivo',
                'curso_alumno.id as curso_alumno_id',
            )
            ->orderBy('alumnos.nombres')
            ->get()
            // Lo que paga hoy por ciclo (tarifa, pareja o valor especial).
            ->map(function ($m) {
                $precio = Tarifas::precio($m->curso_alumno_id);
                $m->precio_ciclo = $precio['valor'];
                $m->precio_regla = $precio['regla'];
                return $m;
            });

        return [
            'id' => $curso->id,
            'nombre' => $curso->nombre,
            'sede_id' => $curso->sede_id,
            'ritmo_id' => $curso->ritmo_id,
            'profesor_id' => $curso->profesor_id,
            'dia' => $curso->dia,
            'hora' => $curso->hora ? substr($curso->hora, 0, 5) : null,
            'fecha_inicio' => $curso->fecha_inicio,
            'cupo_max' => $curso->cupo_max,
            'activo' => $curso->activo,
            'alumnos' => $matriculados->pluck('id')->all(),
            'matriculados' => $matriculados,
            'estado' => $curso->estado,
            'usuario_creacion_id' => $curso->usuario_creacion_id,
            'usuario_creacion_nombre' => $curso->usuario_creacion_nombre,
            'usuario_modificacion_id' => $curso->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $curso->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($curso->created_at))->format('Y-m-d H:i:s'),
            'fecha_modificacion' => (new Carbon($curso->updated_at))->format('Y-m-d H:i:s'),
        ];
    }

    public static function modificarOCrear($dto)
    {
        $user = Auth::user();
        $usuario = $user->usuario();

        if (!isset($dto['id'])) {
            $dto['usuario_creacion_id'] = $usuario->id ?? null;
            $dto['usuario_creacion_nombre'] = $usuario->nombre ?? null;
        }
        $dto['usuario_modificacion_id'] = $usuario->id ?? null;
        $dto['usuario_modificacion_nombre'] = $usuario->nombre ?? null;

        $curso = isset($dto['id']) ? Curso::find($dto['id']) : new Curso();
        $original = $curso->toJson();
        $estabaInactivo = $curso->exists && !($curso->estado && $curso->activo);

        $curso->fill($dto);
        if (!$curso->save()) {
            throw new Exception('Ocurrió un error al intentar guardar el curso.');
        }
        if ($estabaInactivo && $curso->estado && $curso->activo) {
            Curso::reanudarCiclos($curso);
        }

        // Sincroniza la matrícula de alumnos si viene la lista.
        if (isset($dto['alumnos']) && is_array($dto['alumnos'])) {
            Curso::sincronizarMatricula($curso, $dto['alumnos']);
        }

        AuditoriaTabla::crear([
            'id_recurso' => $curso->id,
            'nombre_recurso' => Curso::class,
            'descripcion_recurso' => $curso->nombre ?? ('Curso #' . $curso->id),
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $original : $curso->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $curso->toJson() : null,
        ]);

        return Curso::cargar($curso->id);
    }

    /**
     * Matricula/retira alumnos del curso. A los nuevos les asigna como saldo el precio de su
     * primer ciclo (Tarifas: escala individual o de pareja).
     */
    private static function sincronizarMatricula($curso, array $alumnosIds)
    {
        $actuales = DB::table('curso_alumno')
            ->where('curso_id', $curso->id)
            ->pluck('alumno_id')
            ->all();

        $porAgregar = array_diff($alumnosIds, $actuales);
        $porQuitar = array_diff($actuales, $alumnosIds);

        // El primer ciclo empieza en la próxima clase del curso (sin festivos ni cierres).
        $primeraClase = (new CalendarioCurso())->primeraClase($curso, Carbon::today())->toDateString();

        foreach ($porAgregar as $alumnoId) {
            Curso::matricular($curso, (int) $alumnoId, $primeraClase);
        }

        if (count($porQuitar) > 0) {
            DB::table('curso_alumno')
                ->where('curso_id', $curso->id)
                ->whereIn('alumno_id', $porQuitar)
                ->delete();
            // Quien pagaba en pareja con un retirado vuelve a pagar individual.
            DB::table('curso_alumno')
                ->where('curso_id', $curso->id)
                ->whereIn('pareja_alumno_id', $porQuitar)
                ->update(['pareja_alumno_id' => null, 'updated_at' => Carbon::now()]);
        }
    }

    /**
     * Al reactivar un curso no se cobran los ciclos del tiempo en que estuvo inactivo (mientras lo
     * está, el notificador no causa ciclos). Las matrículas cuyo ciclo ya terminó empiezan uno
     * nuevo en la próxima clase y se les cobra solo ese; las que siguen dentro de su ciclo no cambian.
     */
    private static function reanudarCiclos($curso): void
    {
        if (!Tarifas::cobra()) {
            return;
        }
        $calendario = new CalendarioCurso();
        $hoy = Carbon::today();
        $proximaClase = $calendario->primeraClase($curso, $hoy)->toDateString();
        $matriculas = DB::table('curso_alumno')->where('curso_id', $curso->id)->where('estado', 1)->get();

        foreach ($matriculas as $m) {
            $ciclo = $calendario->ciclo($curso, $m, CalendarioCurso::clasesPorCiclo());
            if ($hoy->lt($ciclo['proximo_pago'])) {
                continue;
            }
            $precio = Tarifas::precio($m->id);
            DB::table('cargos_mensuales')->insertOrIgnore([
                'curso_alumno_id' => $m->id, 'periodo' => $proximaClase, 'valor' => $precio['valor'],
                'valor_base' => $precio['base'], 'regla' => $precio['regla'],
                'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ]);
            DB::table('curso_alumno')->where('id', $m->id)->update([
                'ciclo_inicio' => $proximaClase,
                'saldo' => (float) $m->saldo + $precio['valor'],
                'updated_at' => Carbon::now(),
            ]);
        }
    }

    /**
     * Matricula a un alumno en el curso y devuelve el id de la matrícula (curso_alumno).
     * $primeraClase: 1.ª clase del primer ciclo (por defecto, la próxima del curso).
     * $notificar = false cuando quien llama envía su propio correo (matrícula rápida).
     * $parejaId: alumno ya matriculado en el curso con quien paga en pareja (ver emparejar).
     */
    public static function matricular($curso, int $alumnoId, ?string $primeraClase = null, bool $notificar = true,
        ?int $parejaId = null, bool $ajustarCicloPareja = false): int
    {
        $primeraClase ??= (new CalendarioCurso())->primeraClase($curso, Carbon::today())->toDateString();
        // Un curso se paga siempre por ciclos de clases: los paquetes son solo de clases personalizadas.
        $cursoAlumnoId = DB::table('curso_alumno')->insertGetId([
            'curso_id' => $curso->id,
            'alumno_id' => $alumnoId,
            'fecha_matricula' => Carbon::now()->toDateString(),
            'ciclo_inicio' => $primeraClase,
            'saldo' => 0,
            'estado' => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        // La pareja se enlaza antes de cobrar, para que el primer ciclo salga ya con precio de pareja.
        if ($parejaId) {
            self::emparejar((int) $curso->id, $alumnoId, $parejaId, $ajustarCicloPareja);
        }
        // El primer ciclo se cobra con su precio según el orden de matrícula (2.º, 3.º curso…).
        DB::table('curso_alumno')->where('id', $cursoAlumnoId)
            ->update(['saldo' => Tarifas::precio($cursoAlumnoId)['valor']]);
        if ($notificar) {
            NotificadorAcademia::bienvenida($cursoAlumnoId);
        }
        return $cursoAlumnoId;
    }

    /**
     * Define con quién paga en pareja un alumno este curso ($parejaId null = paga individual).
     * El enlace se guarda en las dos matrículas; si alguno ya tenía otra pareja, esa queda individual.
     *
     * El precio nuevo aplica desde el siguiente ciclo de cada uno. Con $ajustarCiclo también se corrige
     * el ciclo en curso: al saldo de cada afectado se le suma la diferencia entre el precio nuevo y el anterior.
     */
    public static function emparejar(int $cursoId, int $alumnoId, ?int $parejaId, bool $ajustarCiclo = false): void
    {
        $matricula = fn (int $id) => DB::table('curso_alumno')
            ->where('curso_id', $cursoId)->where('alumno_id', $id)->where('estado', 1)->first();

        $propia = $matricula($alumnoId);
        if (!$propia) {
            throw new DomainException('El alumno no está matriculado en este curso.');
        }
        $otra = null;
        if ($parejaId) {
            if ($parejaId === $alumnoId) {
                throw new DomainException('Un alumno no puede ser su propia pareja.');
            }
            $otra = $matricula($parejaId);
            if (!$otra) {
                throw new DomainException('La pareja debe estar matriculada en este mismo curso.');
            }
        }

        // Cambian de precio: los dos y las parejas que tuvieran antes.
        $afectados = array_values(array_unique(array_filter([
            $alumnoId, $parejaId, $propia->pareja_alumno_id, $otra->pareja_alumno_id ?? null,
        ])));
        $filas = DB::table('curso_alumno')->where('curso_id', $cursoId)->whereIn('alumno_id', $afectados)->get();
        $antes = $filas->mapWithKeys(fn ($m) => [$m->id => Tarifas::precio($m->id)['valor']]);

        $enlazar = fn (int $de, ?int $con) => DB::table('curso_alumno')
            ->where('curso_id', $cursoId)->where('alumno_id', $de)
            ->update(['pareja_alumno_id' => $con, 'updated_at' => Carbon::now()]);
        foreach ($afectados as $id) {
            $enlazar((int) $id, null);
        }
        if ($parejaId) {
            $enlazar($alumnoId, $parejaId);
            $enlazar($parejaId, $alumnoId);
        }

        if ($ajustarCiclo) {
            foreach ($filas as $m) {
                $delta = Tarifas::precio($m->id)['valor'] - $antes[$m->id];
                if (abs($delta) > 0.001) {
                    DB::table('curso_alumno')->where('id', $m->id)->update(['saldo' => DB::raw('saldo + ' . (float) $delta)]);
                }
            }
        }
    }

    /**
     * Define (o quita, con null) el valor especial que paga un alumno por ciclo en este curso.
     * $ajustarCiclo: además suma o resta al saldo la diferencia con lo que pagaba, para el ciclo actual.
     */
    public static function valorEspecial(int $cursoId, int $alumnoId, ?float $valor, ?string $motivo, bool $ajustarCiclo = false): void
    {
        $m = DB::table('curso_alumno')->where('curso_id', $cursoId)->where('alumno_id', $alumnoId)->where('estado', 1)->first();
        if (!$m) {
            throw new DomainException('El alumno no está matriculado en este curso.');
        }
        $antes = Tarifas::precio($m->id)['valor'];
        DB::table('curso_alumno')->where('id', $m->id)->update([
            'valor_especial' => $valor,
            'valor_especial_motivo' => $valor === null ? null : $motivo,
            'updated_at' => Carbon::now(),
        ]);
        $delta = Tarifas::precio($m->id)['valor'] - $antes;
        if ($ajustarCiclo && abs($delta) > 0.001) {
            DB::table('curso_alumno')->where('id', $m->id)->update(['saldo' => DB::raw('saldo + ' . (float) $delta)]);
        }
        AuditoriaTabla::crear([
            'id_recurso' => $cursoId,
            'nombre_recurso' => Curso::class,
            'descripcion_recurso' => "Valor especial del alumno #{$alumnoId} en el curso #{$cursoId}",
            'accion' => AccionAuditoriaEnum::MODIFICAR,
            'recurso_original' => json_encode(['valor_especial' => $m->valor_especial, 'motivo' => $m->valor_especial_motivo]),
            'recurso_resultante' => json_encode(['valor_especial' => $valor, 'motivo' => $motivo, 'ajuste_saldo' => $ajustarCiclo ? $delta : 0]),
        ]);
    }

    public static function eliminar($id)
    {
        $curso = Curso::find($id);

        AuditoriaTabla::crear([
            'id_recurso' => $curso->id,
            'nombre_recurso' => Curso::class,
            'descripcion_recurso' => $curso->nombre ?? ('Curso #' . $curso->id),
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $curso->toJson(),
        ]);

        return $curso->delete();
    }
}
