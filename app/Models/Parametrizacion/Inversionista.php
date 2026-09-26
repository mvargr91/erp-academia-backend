<?php

namespace App\Models\Parametrizacion;

use Exception;
use Carbon\Carbon;
use App\Enum\AccionAuditoriaEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\AuditoriaTabla;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Inversionista extends Model
{
    use HasFactory;

    protected $table = 'inversionistas';

    protected $fillable = [
        'nombre',
        'tipo_documento',
        'numero_documento',
        'tipo_persona',
        'codigo_ciiu',
        'descripcion_act_ecca',
        'direccion',
        'ciudad_id',
        'telefono',
        'email',
        'indicativo_socio',
        'porcentaje_ret_fuente_rendimientos',
        'id_tipo_inversion',
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
     
        $query = DB::table('inversionistas')
            ->select(
                'inversionistas.id',
                'inversionistas.nombre',
                'inversionistas.tipo_documento',
                'inversionistas.numero_documento',
                'inversionistas.tipo_persona',
                'inversionistas.codigo_ciiu',
                'inversionistas.descripcion_act_ecca',
                'inversionistas.direccion',
                'inversionistas.ciudad_id',
                'inversionistas.telefono',
                'inversionistas.email',
                'inversionistas.indicativo_socio',
                'inversionistas.porcentaje_ret_fuente_rendimientos',
                'inversionistas.id_tipo_inversion',
                'inversionistas.id_banco',
                'inversionistas.tipo_cuenta',
                'inversionistas.numero_cuenta',
                'inversionistas.observaciones',
                'inversionistas.estado',
            );
        $query->orderBy('nombre', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('inversionistas')
        ->leftJoin('ciudades', 'ciudades.id', 'inversionistas.ciudad_id')
        ->leftJoin('bancos', 'bancos.id', 'inversionistas.id_banco')
        ->join('tipos_inversion', 'tipos_inversion.id', 'inversionistas.id_tipo_inversion')
            ->select(
            'inversionistas.id',            
            'inversionistas.nombre',
            'inversionistas.tipo_documento',
            'inversionistas.numero_documento',
            'inversionistas.tipo_persona',
            'inversionistas.codigo_ciiu',
            'inversionistas.descripcion_act_ecca',
            'inversionistas.direccion',
            'inversionistas.ciudad_id',
            'ciudades.nombre as ciudad',
            'inversionistas.telefono',
            'inversionistas.email',
            'inversionistas.indicativo_socio',
            'inversionistas.porcentaje_ret_fuente_rendimientos',
            'inversionistas.id_tipo_inversion',
            'tipos_inversion.nombre as tipo_inversion',
            'inversionistas.id_banco',
            'bancos.nombre as banco',
            'inversionistas.tipo_cuenta',
            'inversionistas.numero_cuenta',
            'inversionistas.observaciones',
            'inversionistas.estado',
            'inversionistas.usuario_creacion_id',
            'inversionistas.usuario_creacion_nombre',
            'inversionistas.usuario_modificacion_id',
            'inversionistas.usuario_modificacion_nombre',
        );

        if(isset($dto['nombre'])){
            $query->where('inversionistas.nombre', 'like', '%' . $dto['nombre'] . '%');
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre'){
                    $query->orderBy('inversionistas.nombre', $value);
                }
                if($attribute == 'tipo_documento'){
                    $query->orderBy('inversionistas.tipo_documento', $value);
                }
                if($attribute == 'numero_documento'){
                    $query->orderBy('inversionistas.numero_documento', $value);
                }  
                if($attribute == 'direccion'){
                    $query->orderBy('inversionistas.direccion', $value);
                }  
                if($attribute == 'ciudad'){
                    $query->orderBy('ciudades.nombre', $value);
                }  
                if($attribute == 'telefono'){
                    $query->orderBy('inversionistas.telefono', $value);
                }  
                if($attribute == 'email'){
                    $query->orderBy('inversionistas.email', $value);
                }  
                if($attribute == 'indicativo_socio'){
                    $query->orderBy('inversionistas.indicativo_socio', $value);
                } 
                if($attribute == 'porcentaje_ret_fuente_rendimientos'){
                    $query->orderBy('inversionistas.porcentaje_ret_fuente_rendimientos', $value);
                } 
                if($attribute == 'telefono'){
                    $query->orderBy('inversionistas.telefono', $value);
                }  
                if($attribute == 'tipo_inversion'){
                    $query->orderBy('tipos_inversion.nombre', $value);
                } 
                if($attribute == 'id_banco'){
                    $query->orderBy('inversionistas.id_banco', $value);
                }  
                if($attribute == 'tipo_cuenta'){
                    $query->orderBy('inversionistas.tipo_cuenta', $value);
                }  
                if($attribute == 'numero_cuenta'){
                    $query->orderBy('inversionistas.numero_cuenta', $value);
                }      
                if($attribute == 'estado'){
                    $query->orderBy('inversionistas.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('inversionistas.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('inversionistas.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('inversionistas.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('inversionistas.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("inversionistas.updated_at", "desc");
        }

        $inversionistas = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $inversionistas->items();
    
        return [
            'datos' => $data,
            'desde' => $inversionistas->firstItem(),
            'hasta' => $inversionistas->lastItem(),
            'por_pagina' => $inversionistas->perPage(),
            'pagina_actual' => $inversionistas->currentPage(),
            'ultima_pagina' => $inversionistas->lastPage(),
            'total' => $inversionistas->total(),
        ];
    }

    public static function cargar($id)
    {
        $inversionistas = Inversionista::find($id);

        return [
            'id' => $inversionistas->id,
            'nombre' => $inversionistas->nombre,
            'tipo_documento' => $inversionistas->tipo_documento,
            'numero_documento' => $inversionistas->numero_documento,
            'tipo_persona' => $inversionistas->tipo_persona,
            'codigo_ciiu' => $inversionistas->codigo_ciiu,
            'descripcion_act_ecca' => $inversionistas->descripcion_act_ecca,
            'direccion' => $inversionistas->direccion,
            'ciudad_id' => $inversionistas->ciudad_id,
            'telefono' => $inversionistas->telefono,
            'email' => $inversionistas->email,
            'indicativo_socio' => $inversionistas->indicativo_socio,
            'porcentaje_ret_fuente_rendimientos' => $inversionistas->porcentaje_ret_fuente_rendimientos,
            'id_tipo_inversion' => $inversionistas->id_tipo_inversion,
            'id_banco' => $inversionistas->id_banco,
            'tipo_cuenta' => $inversionistas->tipo_cuenta,
            'numero_cuenta' => $inversionistas->numero_cuenta,
            'observaciones' => $inversionistas->observaciones,
            'estado' => $inversionistas->estado,
            'usuario_creacion_id' => $inversionistas->usuario_creacion_id,
            'usuario_creacion_nombre' => $inversionistas->usuario_creacion_nombre,
            'usuario_modificacion_id' => $inversionistas->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $inversionistas->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($inversionistas->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($inversionistas->updated_at))->format("Y-m-d H:i:s")
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
        $inversionistas = isset($dto['id']) ? Inversionista::find($dto['id']) : new Inversionista();

        // Guardar objeto original para auditoria
        $inversionistasOriginal = $inversionistas->toJson();

        $inversionistas->fill($dto);
        $guardado = $inversionistas->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la compañia.", $inversionistas);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $inversionistas->id,
            'nombre_recurso' => Inversionista::class,
            'descripcion_recurso' => $inversionistas->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $inversionistasOriginal : $inversionistas->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $inversionistas->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return Inversionista::cargar($inversionistas->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $inversionistas = Inversionista::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $inversionistas->id,
            'nombre_recurso' => Inversionista::class,
            'descripcion_recurso' => $inversionistas->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $inversionistas->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $inversionistas->delete();
    }
}
