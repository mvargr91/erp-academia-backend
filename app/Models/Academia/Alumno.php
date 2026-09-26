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

class Alumno extends Model
{
    use HasFactory;

    protected $table = 'alumnos';

    protected $fillable = [
        'usuario_id',
        'nombres',
        'apellidos',
        'documento',
        'telefono',
        'correo',
        'fecha_nacimiento',
        'direccion',
        'contacto_emergencia',
        'telefono_emergencia',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    public static function obtenerColeccionLigera($dto)
    {
        return DB::table('alumnos')
            ->select('id', DB::raw("CONCAT(nombres,' ',apellidos) as nombre"))
            ->where('estado', 1)
            ->orderBy('nombres', 'asc')
            ->get();
    }

    public static function obtenerColeccion($dto)
    {
        $query = DB::table('alumnos')
            ->select(
                'id',
                'usuario_id',
                'nombres',
                'apellidos',
                'documento',
                'telefono',
                'correo',
                'fecha_nacimiento',
                'direccion',
                'contacto_emergencia',
                'telefono_emergencia',
                'estado',
                'usuario_creacion_id',
                'usuario_creacion_nombre',
                'usuario_modificacion_id',
                'usuario_modificacion_nombre',
                'created_at as fecha_creacion',
                'updated_at as fecha_modificacion',
            );

        if (isset($dto['nombre'])) {
            $query->where(function ($q) use ($dto) {
                $q->where('nombres', 'like', "%{$dto['nombre']}%")
                    ->orWhere('apellidos', 'like', "%{$dto['nombre']}%");
            });
        }

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0) {
            foreach ($dto['ordenar_por'] as $attribute => $value) {
                if (in_array($attribute, ['nombres', 'apellidos', 'documento', 'correo', 'estado', 'usuario_creacion_nombre'])) {
                    $query->orderBy($attribute, $value);
                }
                if ($attribute == 'fecha_creacion') {
                    $query->orderBy('created_at', $value);
                }
                if ($attribute == 'fecha_modificacion') {
                    $query->orderBy('updated_at', $value);
                }
            }
        } else {
            $query->orderBy('updated_at', 'desc');
        }

        $alumnos = $query->paginate($dto['limite'] ?? 100);

        return [
            'datos' => $alumnos->items(),
            'desde' => $alumnos->firstItem(),
            'hasta' => $alumnos->lastItem(),
            'por_pagina' => $alumnos->perPage(),
            'pagina_actual' => $alumnos->currentPage(),
            'ultima_pagina' => $alumnos->lastPage(),
            'total' => $alumnos->total(),
        ];
    }

    public static function cargar($id)
    {
        $alumno = Alumno::find($id);

        return [
            'id' => $alumno->id,
            'usuario_id' => $alumno->usuario_id,
            'nombres' => $alumno->nombres,
            'apellidos' => $alumno->apellidos,
            'documento' => $alumno->documento,
            'telefono' => $alumno->telefono,
            'correo' => $alumno->correo,
            'fecha_nacimiento' => $alumno->fecha_nacimiento,
            'direccion' => $alumno->direccion,
            'contacto_emergencia' => $alumno->contacto_emergencia,
            'telefono_emergencia' => $alumno->telefono_emergencia,
            'estado' => $alumno->estado,
            'usuario_creacion_id' => $alumno->usuario_creacion_id,
            'usuario_creacion_nombre' => $alumno->usuario_creacion_nombre,
            'usuario_modificacion_id' => $alumno->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $alumno->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($alumno->created_at))->format('Y-m-d H:i:s'),
            'fecha_modificacion' => (new Carbon($alumno->updated_at))->format('Y-m-d H:i:s'),
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

        $alumno = isset($dto['id']) ? Alumno::find($dto['id']) : new Alumno();
        $original = $alumno->toJson();

        $alumno->fill($dto);
        if (!$alumno->save()) {
            throw new Exception('Ocurrió un error al intentar guardar el alumno.');
        }

        AuditoriaTabla::crear([
            'id_recurso' => $alumno->id,
            'nombre_recurso' => Alumno::class,
            'descripcion_recurso' => "$alumno->nombres $alumno->apellidos",
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $original : $alumno->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $alumno->toJson() : null,
        ]);

        return Alumno::cargar($alumno->id);
    }

    public static function eliminar($id)
    {
        $alumno = Alumno::find($id);

        AuditoriaTabla::crear([
            'id_recurso' => $alumno->id,
            'nombre_recurso' => Alumno::class,
            'descripcion_recurso' => "$alumno->nombres $alumno->apellidos",
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $alumno->toJson(),
        ]);

        return $alumno->delete();
    }
}
