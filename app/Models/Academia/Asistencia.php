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

class Asistencia extends Model
{
    use HasFactory;

    protected $table = 'asistencias';

    protected $fillable = [
        'curso_id',
        'fecha_sesion',
        'observacion',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    public static function obtenerColeccion($dto)
    {
        $query = DB::table('asistencias')
            ->join('cursos', 'cursos.id', '=', 'asistencias.curso_id')
            ->leftJoin('ritmos', 'ritmos.id', '=', 'cursos.ritmo_id')
            ->leftJoin('sedes', 'sedes.id', '=', 'cursos.sede_id')
            ->select(
                'asistencias.id',
                'asistencias.curso_id',
                'sedes.nombre as sede_nombre',
                DB::raw("CONCAT(ritmos.nombre,' - ',DATE_FORMAT(cursos.hora,'%H:%i')) as curso_nombre"),
                'asistencias.fecha_sesion',
                'asistencias.observacion',
                DB::raw('(SELECT COUNT(*) FROM asistencia_alumno WHERE asistencia_alumno.asistencia_id = asistencias.id AND asistencia_alumno.presente = 1) as presentes'),
                DB::raw('(SELECT COUNT(*) FROM asistencia_alumno WHERE asistencia_alumno.asistencia_id = asistencias.id) as total_alumnos'),
                'asistencias.usuario_creacion_id',
                'asistencias.usuario_creacion_nombre',
                'asistencias.usuario_modificacion_id',
                'asistencias.usuario_modificacion_nombre',
                'asistencias.created_at as fecha_creacion',
                'asistencias.updated_at as fecha_modificacion',
            );

        Sede::filtrar($query, 'cursos.sede_id');

        if (isset($dto['curso_id'])) {
            $query->where('asistencias.curso_id', $dto['curso_id']);
        }
        if (isset($dto['fecha_sesion'])) {
            $query->where('asistencias.fecha_sesion', $dto['fecha_sesion']);
        }

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0) {
            foreach ($dto['ordenar_por'] as $attribute => $value) {
                if ($attribute == 'curso_nombre') {
                    $query->orderBy('ritmos.nombre', $value);
                }
                if (in_array($attribute, ['fecha_sesion', 'usuario_creacion_nombre'])) {
                    $query->orderBy('asistencias.' . $attribute, $value);
                }
                if ($attribute == 'fecha_creacion') {
                    $query->orderBy('asistencias.created_at', $value);
                }
                if ($attribute == 'fecha_modificacion') {
                    $query->orderBy('asistencias.updated_at', $value);
                }
            }
        } else {
            $query->orderBy('asistencias.fecha_sesion', 'desc');
        }

        $asistencias = $query->paginate($dto['limite'] ?? 100);

        return [
            'datos' => $asistencias->items(),
            'desde' => $asistencias->firstItem(),
            'hasta' => $asistencias->lastItem(),
            'por_pagina' => $asistencias->perPage(),
            'pagina_actual' => $asistencias->currentPage(),
            'ultima_pagina' => $asistencias->lastPage(),
            'total' => $asistencias->total(),
        ];
    }

    public static function cargar($id)
    {
        $asistencia = Asistencia::find($id);

        $asistentes = DB::table('asistencia_alumno')
            ->join('alumnos', 'alumnos.id', '=', 'asistencia_alumno.alumno_id')
            ->where('asistencia_alumno.asistencia_id', $id)
            ->select(
                'alumnos.id as alumno_id',
                DB::raw("CONCAT(alumnos.nombres,' ',alumnos.apellidos) as nombre"),
                'asistencia_alumno.presente',
            )
            ->orderBy('alumnos.nombres')
            ->get();

        return [
            'id' => $asistencia->id,
            'curso_id' => $asistencia->curso_id,
            'fecha_sesion' => $asistencia->fecha_sesion,
            'observacion' => $asistencia->observacion,
            'asistentes' => $asistentes,
            'usuario_creacion_id' => $asistencia->usuario_creacion_id,
            'usuario_creacion_nombre' => $asistencia->usuario_creacion_nombre,
            'usuario_modificacion_id' => $asistencia->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $asistencia->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($asistencia->created_at))->format('Y-m-d H:i:s'),
            'fecha_modificacion' => (new Carbon($asistencia->updated_at))->format('Y-m-d H:i:s'),
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

        $asistencia = isset($dto['id']) ? Asistencia::find($dto['id']) : new Asistencia();
        $original = $asistencia->toJson();

        $asistencia->fill($dto);
        if (!$asistencia->save()) {
            throw new Exception('Ocurrió un error al intentar guardar la asistencia.');
        }

        // Reemplaza el detalle de presentes/ausentes.
        if (isset($dto['asistentes']) && is_array($dto['asistentes'])) {
            DB::table('asistencia_alumno')->where('asistencia_id', $asistencia->id)->delete();
            foreach ($dto['asistentes'] as $asistente) {
                DB::table('asistencia_alumno')->insert([
                    'asistencia_id' => $asistencia->id,
                    'alumno_id' => $asistente['alumno_id'],
                    'presente' => !empty($asistente['presente']) ? 1 : 0,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        }

        AuditoriaTabla::crear([
            'id_recurso' => $asistencia->id,
            'nombre_recurso' => Asistencia::class,
            'descripcion_recurso' => 'Asistencia #' . $asistencia->id,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $original : $asistencia->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $asistencia->toJson() : null,
        ]);

        return Asistencia::cargar($asistencia->id);
    }

    public static function eliminar($id)
    {
        $asistencia = Asistencia::find($id);

        AuditoriaTabla::crear([
            'id_recurso' => $asistencia->id,
            'nombre_recurso' => Asistencia::class,
            'descripcion_recurso' => 'Asistencia #' . $asistencia->id,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $asistencia->toJson(),
        ]);

        return $asistencia->delete();
    }
}
