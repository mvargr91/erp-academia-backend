<?php

namespace App\Models\Seguridad;

use Exception;
use Carbon\Carbon;
use App\Enum\AccionAuditoriaEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\AuditoriaTabla;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Permiso extends Model
{
    protected $table = 'permissions';

    protected $fillable = [
        'name',
        'guard_name',
        'title',
        'option_id',
        'user_creation_name',
        'user_creation_id',
        'user_modification_id',
        'user_modification_name',
    ];

    public static function obtenerColeccionLigera($dto){
        $query = DB::table('permissions')
            ->select(
                'permissions.id',
                'permissions.name',
            );
        $query->orderBy('permissions.name', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $query = DB::table('permissions')
            ->join('opciones_del_sistema','opciones_del_sistema.id','=','permissions.option_id')
            ->select(
                'permissions.id',
                'permissions.name',
                'permissions.title',
                'permissions.option_id',
                'permissions.user_creation_name',
                'permissions.user_creation_id',
                'permissions.user_modification_id',
                'permissions.user_modification_name',
                'permissions.created_at',
                'permissions.updated_at',
                'opciones_del_sistema.nombre AS opcion_sistema',
            );
        if(isset($dto['nombre'])){
            $query->where('permissions.name', 'like', '%' . $dto['nombre'] . '%');
        }

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'name'){
                    $query->orderBy('permissions.name', $value);
                }
                if($attribute == 'opcion_sistema'){
                    $query->orderBy('opciones_del_sistema.nombre', $value);
                }
                if($attribute == 'title'){
                    $query->orderBy('permissions.title', $value);
                }
                if($attribute == 'user_creation_name'){
                    $query->orderBy('permissions.user_creation_name', $value);
                }
                if($attribute == 'user_modification_name'){
                    $query->orderBy('permissions.user_modification_name', $value);
                }
                if($attribute == 'created_at'){
                    $query->orderBy('permissions.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('permissions.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("permissions.updated_at", "asc");
        }

        $permisos = $query->paginate($dto['limite'] ?? 100);
        $datos = [];

        foreach ($permisos ?? [] as $permiso){
            array_push($datos, $permiso);
        }

        $cantidadpermisos = count($permisos);
        $to = isset($permisos) && $cantidadpermisos > 0 ? $permisos->currentPage() * $permisos->perPage() : null;
        $to = isset($to) && isset($permisos) && $to > $permisos->total() && $cantidadpermisos > 0 ? $permisos->total() : $to;
        $from = isset($to) && isset($permisos) && $cantidadpermisos > 0 ?
            ( $permisos->perPage() > $to ? 1 : ($to - $cantidadpermisos) + 1 )
            : null;
        return [
            'datos' => $datos,
            'desde' => $from,
            'hasta' => $to,
            'por_pagina' => isset($permisos) && $cantidadpermisos > 0 ? +$permisos->perPage() : 0,
            'pagina_actual' => isset($permisos) && $cantidadpermisos > 0 ? $permisos->currentPage() : 1,
            'ultima_pagina' => isset($permisos) && $cantidadpermisos > 0 ? $permisos->lastPage() : 0,
            'total' => isset($permisos) && $cantidadpermisos > 0 ? $permisos->total() : 0
        ];
    }

    public static function cargar($id)
    {
        $permiso = Permiso::find($id);
        return [
            'id' => $permiso->id,
            'name' => $permiso->name,
            'title' => $permiso->title,
            'option_id' => $permiso->option_id,
            'user_creation_name' => $permiso->user_creation_name,
            'user_creation_id' => $permiso->user_creation_id,
            'user_modification_id' => $permiso->user_modification_id,
            'user_modification_name' => $permiso->user_modification_name,
            'created_at' => (new Carbon($permiso->created_at))->format("Y-m-d H:i:s"),
            'updated_at' => (new Carbon($permiso->updated_at))->format("Y-m-d H:i:s")
        ];
    }

    public static function modificarOCrear($dto)
    {
        $user = Auth::user();
        $usuario = $user->usuario();

        if(!isset($dto['id'])){
            $dto['user_creation_id'] = $usuario->id ?? ($dto['user_creation_id'] ?? null);
            $dto['user_creation_name'] = $usuario->nombre ?? ($dto['user_creation_name'] ?? null);
        }
        if(isset($usuario) || isset($dto['usuario_modificacion_id'])){
            $dto['user_modification_id'] = $usuario->id ?? ($dto['user_modification_id'] ?? null);
            $dto['user_modification_name'] = $usuario->nombre ?? ($dto['user_modification_name'] ?? null);
        }

        // Consultar módulos
        $permiso = isset($dto['id']) ? Permiso::find($dto['id']) : new permiso();

        $dto['guard_name'] = 'api';
        
        // Guardar objeto original para auditoria
        $permisoOriginal = $permiso->toJson();

        $permiso->fill($dto);

        $guardado = $permiso->save();

        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar el Permiso.", $permiso);
        }

        // Guardar auditoria
        $auditoriaDto = [
            'id_recurso' => $permiso->id,
            'nombre_recurso' => Permiso::class,
            'descripcion_recurso' => $permiso->name,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $permisoOriginal : $permiso->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $permiso->toJson() : null
        ];
        
        AuditoriaTabla::crear($auditoriaDto);
        
        return Permiso::cargar($permiso->id);
    }

    public static function eliminar($id)
    {
        $permiso = Permiso::find($id);

        // Guardar auditoria
        $auditoriaDto = [
            'id_recurso' => $permiso->id,
            'nombre_recurso' => Permiso::class,
            'descripcion_recurso' => $permiso->name,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $permiso->toJson()
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return $permiso->delete();
    }
    
    use HasFactory;
}
