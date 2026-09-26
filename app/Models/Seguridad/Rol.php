<?php

namespace App\Models\Seguridad;

use Exception;
use Carbon\Carbon;
use App\Enum\AccionAuditoriaEnum;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\AuditoriaTabla;
use Spatie\Permission\Models\Permission;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Rol extends Model
{
    protected $table = 'roles';

    protected $fillable = [
        'id',
        'name',
        'status',
        'type',
        'guard_name',
        'creation_user_id',
        'creation_user_name',
        'modification_user_id',
        'modification_user_name'
    ];

    public static function obtenerColeccion($dto){
        $query = DB::table('roles')
            ->select(
                'roles.id AS id',
                'roles.name AS nombre',
                'roles.type AS tipo',
                'roles.status AS estado',
                'roles.creation_user_id AS usuario_creacion_id',
                'roles.creation_user_name AS usuario_creacion_nombre',
                'roles.modification_user_id AS usuario_modificacion_id',
                'roles.modification_user_name AS usuario_modificacion_nombre',
                'roles.created_at AS fecha_creacion',
                'roles.updated_at AS fecha_modificacion'
            );

        if(isset($dto['nombre'])){
            $query->where('roles.name', 'like', '%' . $dto['nombre'] . '%');
        }

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre'){
                    $query->orderBy('roles.name', $value);
                }
                if($attribute == 'tipo'){
                    $query->orderBy('roles.type', $value);
                }
                if($attribute == 'estado'){
                    $query->orderBy('roles.status', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('roles.creation_user_name', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('roles.modification_user_name', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('roles.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('roles.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("roles.name", "asc");
        }

        $roles = $query->paginate($dto['limite'] ?? 100);
        $datos = [];

        foreach ($roles ?? [] as $rol){
            array_push($datos, $rol);
        }

        $cantidadRoles = count($roles);
        $to = isset($roles) && $cantidadRoles > 0 ? $roles->currentPage() * $roles->perPage() : null;
        $to = isset($to) && isset($roles) && $to > $roles->total() && $cantidadRoles > 0 ? $roles->total() : $to;
        $from = isset($to) && isset($roles) && $cantidadRoles > 0 ?
            ( $roles->perPage() > $to ? 1 : ($to - $cantidadRoles) + 1 )
            : null;
        return [
            'datos' => $datos,
            'desde' => $from,
            'hasta' => $to,
            'por_pagina' => isset($roles) && $cantidadRoles > 0 ? +$roles->perPage() : 0,
            'pagina_actual' => isset($roles) && $cantidadRoles > 0 ? $roles->currentPage() : 1,
            'ultima_pagina' => isset($roles) && $cantidadRoles > 0 ? $roles->lastPage() : 0,
            'total' => isset($roles) && $cantidadRoles > 0 ? $roles->total() : 0
        ];
    }

    public static function obtenerColeccionLigera($dto){
        $user = Auth::user();
        $rol = $user->rol();
        $query = DB::table('roles')
            ->select(
                'id',
                'name AS nombre',
                'status AS estado',
            );

        return $query
            ->where('status',true)
            ->orderBy('name', 'asc')
            ->get();
    }

    public static function obtenerPermisos($id, $dto){
        $query = DB::table('permissions')
            ->join('opciones_del_sistema', 'opciones_del_sistema.id', '=', 'permissions.option_id')
            ->join('modulos', 'modulos.id', '=', 'opciones_del_sistema.modulo_id')
            ->leftjoin('role_has_permissions', function ($join) use($id) {
                $join->on('role_has_permissions.permission_id', '=', 'permissions.id')
                    ->where('role_has_permissions.role_id', $id);
            })
            ->select(
                'permissions.id',
                'opciones_del_sistema.modulo_id',
                'permissions.option_id',
                'permissions.name AS nombre',
                'permissions.title AS titulo',
                'modulos.nombre AS modulo',
                'opciones_del_sistema.nombre AS opcion',
                DB::raw('IF(role_has_permissions.permission_id IS NOT NULL, 1, 0) AS permitido')
            )
            ->orderBy('modulos.id', 'asc')
            ->orderBy('opciones_del_sistema.posicion', 'asc')
            ->orderBy('permissions.name', 'asc');

        if(isset($dto['modulo_id'])){
            $query->where('opciones_del_sistema.modulo_id', $dto['modulo_id']);
        }

        if(isset($dto['option_id'])){
            $query->where('permissions.option_id', $dto['option_id']);
        }

        $permisos = $query->get();

        $permisosPorModuloDto = [];
        $permisosPorModulo = $permisos->groupBy('modulo');
        foreach ($permisosPorModulo as $modulo => $permisosDelModulo){
            $permisosPorOpcionDto = [];
            $permisosPorOpcion = $permisosDelModulo->groupBy('opcion');
            foreach ($permisosPorOpcion as $opcion => $permisosOpcion){
                $permisosDto = [];
                foreach ($permisosOpcion as $permiso){
                    array_push($permisosDto, [
                        "nombre" => $permiso->titulo,
                        "clave" => $permiso->nombre,
                        "permitido" => !!$permiso->permitido,
                        "id" => $permiso->id,
                    ]);
                }

                array_push($permisosPorOpcionDto, [
                    "nombre" => $opcion,
                    "permisos" => $permisosDto,
                    "id" => $permisosOpcion[0]->option_id,
                ]);
            }

            array_push($permisosPorModuloDto, [
                "nombre" => $modulo,
                "opciones" => $permisosPorOpcionDto,
                "id" => $permisosDelModulo[0]->modulo_id,
            ]);
        }

        return $permisosPorModuloDto;
    }

    public static function cargar($id)
    {
        $rol = Rol::find($id);
        return [
            'id' => $rol->id,
            'nombre' => $rol->name,
            'tipo' => $rol->type,
            'estado' => $rol->status,
            'usuario_creacion_id' => $rol->creation_user_id,
            'usuario_creacion_nombre' => $rol->creation_user_name,
            'usuario_modificacion_id' => $rol->modification_user_id,
            'usuario_modificacion_nombre' => $rol->modification_user_name,
            'fecha_creacion' => (new Carbon($rol->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($rol->updated_at))->format("Y-m-d H:i:s")
        ];
    }

    public static function modificarOCrear($dto)
    {
        $rolDto = [
            'name' => $dto['nombre'],
            'type' =>  $dto['tipo'],
            'status' => $dto['estado'],
            'guard_name' => 'api'
        ];

        if (isset($dto['id'])) {
            $rolDto['id'] = $dto['id'];
        }
        
        $user = Auth::user();
        $usuario = $user->usuario();

        if(!isset($dto['id'])){
            $rolDto['creation_user_id'] = $usuario->id ?? ($dto['usuario_creacion_id'] ?? null);
            $rolDto['creation_user_name'] = $usuario->nombre ?? ($dto['usuario_creacion_nombre'] ?? null);
        }
        if(isset($usuario) || isset($dto['usuario_modificacion_id'])){
            $rolDto['modification_user_id'] = $usuario->id ?? ($dto['usuario_modificacion_id'] ?? null);
            $rolDto['modification_user_name'] = $usuario->nombre ?? ($dto['usuario_modificacion_nombre'] ?? null);
        }

        // Consultar rol
        $rol = isset($rolDto['id']) ? Rol::find($rolDto['id']) : new Rol();

        // Guardar objeto original para auditoria
        $rolOriginal = $rol->toJson();

        $rol->fill($rolDto);
        $guardado = $rol->save();
        //$guardado = Role::create(['name' => $dto['nombre']]);
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar el rol.", $rol);
        }

        // Guardar auditoria
        $auditoriaDto = [
            'id_recurso' => $rol->id,
            'nombre_recurso' => Rol::class,
            'descripcion_recurso' => $rol->name,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $rolOriginal : $rol->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $rol->toJson() : null
        ];
        
        AuditoriaTabla::crear($auditoriaDto);
        
        return Rol::cargar($rol->id);
    }

    public static function eliminar($id)
    {
        $rol = Rol::find($id);

        // Guardar auditoria
        $auditoriaDto = [
            'id_recurso' => $rol->id,
            'nombre_recurso' => Rol::class,
            'descripcion_recurso' => $rol->name,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $rol->toJson()
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return $rol->delete();
    }

    public static function otorgarPermisos($dto){
        $rol = Role::findById($dto['rol_id'],'api');
        $permission = Permission::findById($dto['permission_id']);
        $rol->givePermissionTo($permission);
    }

    public static function revocarPermisos($dto){
        $rol = Role::findById($dto['rol_id'],'api');
        $permission = Permission::findById($dto['permission_id']);
        $rol->revokePermissionTo($permission);
    }
    
    use HasFactory;
}
