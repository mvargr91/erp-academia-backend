<?php

namespace App\Models\Academia;

use Exception;
use Carbon\Carbon;
use App\Enum\AccionAuditoriaEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\AuditoriaTabla;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Curso extends Model
{
    use HasFactory;

    protected $table = 'cursos';

    protected $fillable = [
        'nombre',
        'ritmo_id',
        'profesor_id',
        'plan_id',
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
        return DB::table('cursos')
            ->leftJoin('ritmos', 'ritmos.id', '=', 'cursos.ritmo_id')
            ->select('cursos.id', DB::raw("CONCAT(ritmos.nombre,' - ',DATE_FORMAT(cursos.hora,'%H:%i')) as nombre"))
            ->where('cursos.estado', 1)
            ->orderBy('ritmos.nombre', 'asc')
            ->get();
    }

    public static function obtenerColeccion($dto)
    {
        $query = DB::table('cursos')
            ->leftJoin('ritmos', 'ritmos.id', '=', 'cursos.ritmo_id')
            ->leftJoin('profesores', 'profesores.id', '=', 'cursos.profesor_id')
            ->leftJoin('planes', 'planes.id', '=', 'cursos.plan_id')
            ->select(
                'cursos.id',
                'cursos.nombre',
                'cursos.ritmo_id',
                'ritmos.nombre as ritmo_nombre',
                'cursos.profesor_id',
                DB::raw("CONCAT(COALESCE(profesores.nombres,''),' ',COALESCE(profesores.apellidos,'')) as profesor_nombre"),
                'cursos.plan_id',
                'planes.nombre as plan_nombre',
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
            ->select(
                'alumnos.id',
                DB::raw("CONCAT(alumnos.nombres,' ',alumnos.apellidos) as nombre"),
                'curso_alumno.saldo',
                'curso_alumno.ultima_fecha_pago',
            )
            ->orderBy('alumnos.nombres')
            ->get();

        return [
            'id' => $curso->id,
            'nombre' => $curso->nombre,
            'ritmo_id' => $curso->ritmo_id,
            'profesor_id' => $curso->profesor_id,
            'plan_id' => $curso->plan_id,
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

        $curso->fill($dto);
        if (!$curso->save()) {
            throw new Exception('Ocurrió un error al intentar guardar el curso.');
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
     * Matricula/retira alumnos del curso. A los nuevos les asigna como saldo
     * el valor del plan del curso (0 si no hay plan).
     */
    private static function sincronizarMatricula($curso, array $alumnosIds)
    {
        $valorPlan = 0;
        if ($curso->plan_id) {
            $valorPlan = (float) (DB::table('planes')->where('id', $curso->plan_id)->value('valor') ?? 0);
        }

        $actuales = DB::table('curso_alumno')
            ->where('curso_id', $curso->id)
            ->pluck('alumno_id')
            ->all();

        $porAgregar = array_diff($alumnosIds, $actuales);
        $porQuitar = array_diff($actuales, $alumnosIds);

        foreach ($porAgregar as $alumnoId) {
            DB::table('curso_alumno')->insert([
                'curso_id' => $curso->id,
                'alumno_id' => $alumnoId,
                'fecha_matricula' => Carbon::now()->toDateString(),
                'saldo' => $valorPlan,
                'estado' => 1,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }

        if (count($porQuitar) > 0) {
            DB::table('curso_alumno')
                ->where('curso_id', $curso->id)
                ->whereIn('alumno_id', $porQuitar)
                ->delete();
        }
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
