<?php

namespace App\Models\Parametrizacion;

use Exception;
use Carbon\Carbon;
use App\Enum\AccionAuditoriaEnum;
use App\Models\Seguridad\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\AuditoriaTabla;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Gestor extends Model
{
    use HasFactory;

    protected $table = 'gestores';

    protected $fillable = [
        'nombre',
        'tipo_documento',
        'numero_documento',
        'email',
        'telefono',
        'porcentaje_comision',
        'porcentaje_ret_fuente',
        'id_banco',
        'tipo_cuenta',
        'numero_cuenta',
        'observaciones',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('gestores')
            ->select(
                'gestores.id',
                'gestores.nombre',
                'gestores.tipo_documento',
                'gestores.numero_documento',
                'gestores.email',
                'gestores.telefono',
                'gestores.porcentaje_comision',
                'gestores.porcentaje_ret_fuente',
                'gestores.id_banco',
                'gestores.tipo_cuenta',
                'gestores.numero_cuenta',
                'gestores.observaciones',
                'gestores.estado',
            );
        $query->orderBy('nombre', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('gestores')
        ->leftJoin('bancos', 'bancos.id', 'gestores.id_banco')
            ->select(
            'gestores.id',            
            'gestores.nombre',
            'gestores.tipo_documento',
            'gestores.numero_documento',
            'gestores.email',
            'gestores.telefono',
            'gestores.porcentaje_comision',
            'gestores.porcentaje_ret_fuente',
            'gestores.id_banco',
            'bancos.nombre as banco',
            'gestores.tipo_cuenta',
            'gestores.numero_cuenta',
            'gestores.observaciones',
            'gestores.estado',
            'gestores.usuario_creacion_id',
            'gestores.usuario_creacion_nombre',
            'gestores.usuario_modificacion_id',
            'gestores.usuario_modificacion_nombre',
        );

        if(isset($dto['nombre'])){
            $query->where('gestores.nombre', 'like', '%' . $dto['nombre'] . '%');
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre'){
                    $query->orderBy('gestores.nombre', $value);
                }
                if($attribute == 'tipo_documento'){
                    $query->orderBy('gestores.tipo_documento', $value);
                }
                if($attribute == 'numero_documento'){
                    $query->orderBy('gestores.numero_documento', $value);
                }  
                if($attribute == 'email'){
                    $query->orderBy('gestores.email', $value);
                }  
                if($attribute == 'telefono'){
                    $query->orderBy('gestores.telefono', $value);
                }  
                if($attribute == 'id_banco'){
                    $query->orderBy('gestores.id_banco', $value);
                }  
                if($attribute == 'tipo_cuenta'){
                    $query->orderBy('gestores.tipo_cuenta', $value);
                }  
                if($attribute == 'numero_cuenta'){
                    $query->orderBy('gestores.numero_cuenta', $value);
                }      
                if($attribute == 'estado'){
                    $query->orderBy('gestores.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('gestores.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('gestores.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('gestores.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('gestores.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("gestores.updated_at", "desc");
        }

        $gestores = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $gestores->items();
    
        return [
            'datos' => $data,
            'desde' => $gestores->firstItem(),
            'hasta' => $gestores->lastItem(),
            'por_pagina' => $gestores->perPage(),
            'pagina_actual' => $gestores->currentPage(),
            'ultima_pagina' => $gestores->lastPage(),
            'total' => $gestores->total(),
        ];
    }

    public static function cargar($id)
    {
        $gestores = Gestor::find($id);

        return [
            'id' => $gestores->id,
            'nombre' => $gestores->nombre,
            'tipo_documento' => $gestores->tipo_documento,
            'numero_documento' => $gestores->numero_documento,
            'email' => $gestores->email,
            'telefono' => $gestores->telefono,
            'porcentaje_comision' => $gestores->porcentaje_comision,
            'porcentaje_ret_fuente' => $gestores->porcentaje_ret_fuente,
            'id_banco' => $gestores->id_banco,
            'tipo_cuenta' => $gestores->tipo_cuenta,
            'numero_cuenta' => $gestores->numero_cuenta,
            'observaciones' => $gestores->observaciones,
            'estado' => $gestores->estado,
            'usuario_creacion_id' => $gestores->usuario_creacion_id,
            'usuario_creacion_nombre' => $gestores->usuario_creacion_nombre,
            'usuario_modificacion_id' => $gestores->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $gestores->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($gestores->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($gestores->updated_at))->format("Y-m-d H:i:s")
        ];
    }

    public static function modificarOCrear($dto)
    {
        $user = Auth::user();
        $usuario = $user->usuario();
        if (!isset($dto['id'])) {
            $dto['usuario_creacion_id'] = $usuario->id ?? ($dto['usuario_creacion_id'] ?? null);
            $dto['usuario_creacion_nombre'] = $usuario->nombre ?? ($dto['usuario_creacion_nombre'] ?? null);
        }
        if (isset($usuario) || isset($dto['usuario_modificacion_id'])) {
            $dto['usuario_modificacion_id'] = $usuario->id ?? ($dto['usuario_modificacion_id'] ?? null);
            $dto['usuario_modificacion_nombre'] = $usuario->nombre ?? ($dto['usuario_modificacion_nombre'] ?? null);
        }

        // Consultar el servicio
        $gestores = isset($dto['id']) ? Gestor::find($dto['id']) : new Gestor();

        // Guardar objeto original para auditoria
        $gestoresOriginal = $gestores->toJson();

        $gestores->fill($dto);
        $guardado = $gestores->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la compañia.", $gestores);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $gestores->id,
            'nombre_recurso' => Gestor::class,
            'descripcion_recurso' => $gestores->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $gestoresOriginal : $gestores->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $gestores->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return Gestor::cargar($gestores->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $gestores = Gestor::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $gestores->id,
            'nombre_recurso' => Gestor::class,
            'descripcion_recurso' => $gestores->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $gestores->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $gestores->delete();
    }
}
