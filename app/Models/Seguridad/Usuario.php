<?php

namespace App\Models\Seguridad;

use Exception;
use Carbon\Carbon;
use App\Models\User;
use App\Enum\AccionAuditoriaEnum;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\AuditoriaTabla;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Usuario extends Model
{
    protected $fillable = [
        'identificacion_usuario',
        'nombre',
        'correo_electronico',
        'user_id',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre'
    ];

    
    public function user(){
        return $this->belongsTo(User::class);
    }

    public static function cargar($id)
    {
        $usuario = Usuario::find($id);
        $rol = $usuario->rol();
        return [
            'id' => $usuario->id,
            'identificacion_usuario' => $usuario->identificacion_usuario,
            'nombre' => $usuario->nombre,
            'rol_id'=> isset($rol)?$rol->id:null,
            'correo_electronico' => $usuario->correo_electronico,
            'estado' => $usuario->estado,
            'usuario_creacion_id' => $usuario->usuario_creacion_id,
            'usuario_creacion_nombre' => $usuario->usuario_creacion_nombre,
            'usuario_modificacion_id' => $usuario->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $usuario->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($usuario->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($usuario->updated_at))->format("Y-m-d H:i:s"),
        ];
    }

    public static function modificarOCrear($dto)
    {
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();


        if (!isset($dto['id'])) {
            $dto['usuario_creacion_id'] = $usuario->id ?? ($dto['usuario_creacion_id'] ?? null);
            $dto['usuario_creacion_nombre'] = $usuario->nombre ?? ($dto['usuario_creacion_nombre'] ?? null);
        }
        if (isset($usuario) || isset($dto['usuario_modificacion_id'])) {
            $dto['usuario_modificacion_id'] = $usuario->id ?? ($dto['usuario_modificacion_id'] ?? null);
            $dto['usuario_modificacion_nombre'] = $usuario->nombre ?? ($dto['usuario_modificacion_nombre'] ?? null);
        }

        $usuario = isset($dto['id']) ? Usuario::find($dto['id']) : new Usuario();
        if(!isset($dto['id'])){
            $userSesion = User::create([
                'name' => $dto['nombre'],
                'email' => $dto['identificacion_usuario'],
                'password' => Hash::make($dto['clave'])
            ]);
            if(isset($userSesion)){
                $dto['user_id'] = $userSesion->id;
                $rol = Role::find($dto['rol_id']);
                $userSesion->assignRole($rol);
            }else{
                throw new Exception("Ocurrió un error al intentar guardar el usuario.", $userSesion);
            }
        }else{
            $userSesion = $usuario->user;
            $userSesion->fill([
                'name' => $dto['nombre'],
                'email' => $dto['identificacion_usuario']
            ]);
            $guardado = $userSesion->save();
            if(!$guardado){
                throw new Exception("Ocurrió un error al intentar guardar el usuario.", $userSesion);
            }

            // Asignar nuevo rol
            $rol = Role::find($dto['rol_id']);

            $userSesion->syncRoles($rol);
        }

        // Guardar objeto original para auditoria
        $usuarioOriginal = $usuario->toJson();

        $usuario->fill($dto);
        $guardado = $usuario->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar el usuario.", $usuario);
        }
        
        $auditoriaDto = [
            'id_recurso' => $usuario->id,
            'nombre_recurso' => Usuario::class,
            'descripcion_recurso' => $usuario->nombre,
            'accion' => isset($dto['id']) ? 'Modificar' : 'Crear',
            'recurso_original' => isset($dto['id']) ? $usuarioOriginal : $usuario->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $usuario->toJson() : null
        ];

        AuditoriaTabla::crear($auditoriaDto);

        return Usuario::cargar($usuario->id);
    }

    
    public static function findBy($identificacionUsuario){
        return Usuario::where('identificacion_usuario', $identificacionUsuario)->first();
    }

    public static function findByEmail($identificacionUsuario){
        return Usuario::where('correo_electronico', $identificacionUsuario)->first();
    }


    public function rol(){
        $user = $this->user;
        return DB::table('roles')
            ->join('model_has_roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_type', User::class)
            ->where('model_has_roles.model_id', $user->id)
            ->select('roles.*')
            ->first();
    }

    public static function obtenerColeccionLigera($dto){
        $user = Auth::user();
        $rol = $user->rol();
        
        $query = DB::table('usuarios')
            ->select(
                'usuarios.id', 
                'usuarios.nombre',
                'usuarios.estado',
            );
        if (isset($dto['rol'])) {
            $query->whereExists(function ($q) use ($dto) {
                $q->select(DB::raw(1))
                    ->from('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->whereColumn('model_has_roles.model_id', 'usuarios.user_id')
                    ->where('model_has_roles.model_type', User::class)
                    ->where('roles.name', $dto['rol']);
            });
        }
        return $query->orderBy('usuarios.nombre')->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();
        $query = DB::table('usuarios')
             ->join('model_has_roles', function ($join) use($dto) {
                 $join->on('model_has_roles.model_id', '=', 'usuarios.user_id');
             })
             ->join('roles', function ($join) use($dto){
                 $join->on('roles.id', '=', 'model_has_roles.role_id');
             })
            ->select(
                'usuarios.id',
                'usuarios.identificacion_usuario',
                'usuarios.nombre',
                'usuarios.correo_electronico',
                'roles.name AS rol_nombre',
                'usuarios.estado',
                'usuarios.usuario_creacion_id',
                'usuarios.usuario_creacion_nombre',
                'usuarios.usuario_modificacion_id',
                'usuarios.usuario_modificacion_nombre',
                'usuarios.created_at AS fecha_creacion',
                'usuarios.updated_at AS fecha_modificacion',
            );

        if(isset($dto['nombre'])){
            $query->where('usuarios.nombre', 'like', '%' . $dto['nombre'] . '%');
        }

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'id'){
                    $query->orderBy('usuarios.id', $value);
                }
                if($attribute == 'identificacion_usuario'){
                    $query->orderBy('usuarios.identificacion_usuario', $value);
                }
                if($attribute == 'nombre'){
                    $query->orderBy('usuarios.nombre', $value);
                }
                if($attribute == 'correo_electronico'){
                    $query->orderBy('usuarios.correo_electronico', $value);
                }
                if($attribute == 'rol_nombre'){
                    $query->orderBy('roles.name', $value);
                }
                if($attribute == 'estado'){
                    $query->orderBy('usuarios.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('usuarios.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('usuarios.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('usuarios.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('usuarios.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("usuarios.updated_at", "desc");
        }

        $usuarios = $query->paginate($dto['limite'] ?? 100);
        $datos = [];
        foreach ($usuarios ?? [] as $usuario){
            array_push($datos, $usuario);
        }

        $cantidadUsuarios = count($usuarios ?? []);
        $to = isset($usuarios) && $cantidadUsuarios > 0 ? $usuarios->currentPage() * $usuarios->perPage() : null;
        $to = isset($to) && isset($usuarios) && $to > $usuarios->total() && $cantidadUsuarios > 0 ? $usuarios->total() : $to;
        $from = isset($to) && isset($usuarios) && $cantidadUsuarios > 0 ?
            ( $usuarios->perPage() > $to ? 1 : ($to - $cantidadUsuarios) + 1 )
            : null;
        return [
            'datos' => $datos,
            'desde' => $from,
            'hasta' => $to,
            'por_pagina' => isset($usuarios) && $cantidadUsuarios > 0 ? +$usuarios->perPage() : 0,
            'pagina_actual' => isset($usuarios) && $cantidadUsuarios > 0 ? $usuarios->currentPage() : 1,
            'ultima_pagina' => isset($usuarios) && $cantidadUsuarios > 0 ? $usuarios->lastPage() : 0,
            'total' => isset($usuarios) && $cantidadUsuarios > 0 ? $usuarios->total() : 0
        ];
    }

    public static function obtener($dto){
        $query = DB::table('usuarios')
             ->join('users', 'users.id', '=', 'usuarios.user_id')
             ->join('model_has_roles', 'model_has_roles.model_id', '=', 'users.id')
             ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->select(
                'usuarios.id',
                'usuarios.identificacion_usuario',
                'usuarios.nombre',
                'roles.name as rol',
                'usuarios.correo_electronico',
                'usuarios.estado',
                'usuarios.usuario_creacion_id',
                'usuarios.usuario_creacion_nombre',
                'usuarios.usuario_modificacion_id',
                'usuarios.usuario_modificacion_nombre',
                'usuarios.created_at AS fecha_creacion',
                'usuarios.updated_at AS fecha_modificacion',
            )
            ->orderBy("usuarios.updated_at", "desc")
            ->get();

        return $query;
    }

    public static function obtenerRolInterno($dto){
        $query = DB::table('usuarios')
             ->join('users', 'users.id', '=', 'usuarios.user_id')
             ->join('model_has_roles', 'model_has_roles.model_id', '=', 'users.id')
             ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->select(
                'usuarios.id',
                'usuarios.identificacion_usuario',
                'usuarios.nombre',
                'roles.name as rol',
                'roles.type as type',
                'usuarios.correo_electronico',
                'usuarios.estado',
            )
            ->where('roles.type', 'IN')
            ->where('usuarios.estado', 1)
            ->orderBy("roles.type", "desc")
            ->get();

        return $query;
    }

    public static function cambiarClave($id, $dto){
        $usuario = Usuario::find($id);
        $user = $usuario->user;
        $user->fill([
            'id' => $id,
            'password' => Hash::make($dto['nueva_clave'])
        ]);
        return $user->save();
    }

    public static function eliminar($id)
    {
        $usuario = Usuario::find($id);
        // Guardar auditoria
        $auditoriaDto = [
            'id_recurso' => $usuario->id,
            'nombre_recurso' => Usuario::class,
            'descripcion_recurso' => $usuario->nombre,
            'accion' => 'Eliminar',
            'recurso_original' => $usuario->toJson()
        ];
        AuditoriaTabla::crear($auditoriaDto);

        // Borrar User
        $user = $usuario->user;
        $usuario->delete();
        return $user->delete();
    }
    
    public static function changePassword($datos){
        $userSesion = Auth::user();
        $usuarioSesion = $userSesion->usuario();
        $usuario = Usuario::find($datos['id']);
        $user = User::find($usuario->user_id);
        $user->password = Hash::make($datos['password']);
        $user->save();
        $auditoriaDto = [
            'id_recurso' => $usuario->id,
            'nombre_recurso' => Usuario::class,
            'descripcion_recurso' => $usuario->nombre,
            'accion' => 'Eliminar',
            'recurso_original' => $usuario->toJson()
        ];
        $auditoriaDto = [
            'id_recurso' => $usuario->id,
            'nombre_recurso' => Usuario::class,
            'descripcion_recurso' => $usuario->nombre,
            'accion' => AccionAuditoriaEnum::CAMBIO_CONTRASENA,
            'recurso_original' => $usuario->toJson(),
            'responsable_id' => $usuarioSesion->id,
            'responsable_nombre' => $usuarioSesion->nombre,
        ];

        $auditoria = AuditoriaTabla::create($auditoriaDto);
        return true;
    }
    use HasFactory;
}
