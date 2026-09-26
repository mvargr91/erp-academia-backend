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

class Proveedor extends Model
{
    use HasFactory;

    protected $table = 'proveedores';

    protected $fillable = [
        'nombre',
        'tipo_documento',
        'numero_documento',
        'direccion',
        'id_ciudad',
        'telefono',
        'pagina_web',
        'tipo_Persona',
        'contacto_nombre',
        'contacto_telefono',
        'contacto_email',
        'id_banco',
        'tipo_cuenta_Bancaria',
        'numero_cuenta_Bancaria',
        'observaciones',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('proveedores')
            ->select(
                'proveedores.id',
                'proveedores.nombre',
                'proveedores.tipo_documento',
                'proveedores.numero_documento',
                'proveedores.direccion',
                'proveedores.id_ciudad',
                'proveedores.telefono',
                'proveedores.pagina_web',
                'proveedores.tipo_Persona',
                'proveedores.contacto_nombre',
                'proveedores.contacto_telefono',
                'proveedores.contacto_email',
                'proveedores.id_banco',
                'proveedores.tipo_cuenta_Bancaria',
                'proveedores.numero_cuenta_Bancaria',
                'proveedores.observaciones',
                'proveedores.estado',
            );
        $query->orderBy('nombre', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('proveedores')
        ->join('ciudades', 'ciudades.id', 'proveedores.id_ciudad')
        ->leftJoin('bancos', 'bancos.id', 'proveedores.id_banco')
            ->select(
            'proveedores.id',            
            'proveedores.nombre',
            'proveedores.tipo_documento',
            'proveedores.numero_documento',
            'proveedores.direccion',
            'proveedores.id_ciudad',
            'ciudades.nombre as ciudad', 
            'proveedores.telefono',
            'proveedores.pagina_web',
            'proveedores.tipo_Persona',
            'proveedores.contacto_nombre',
            'proveedores.contacto_telefono',
            'proveedores.contacto_email',
            'proveedores.id_banco',
            'bancos.nombre as banco',
            'proveedores.tipo_cuenta_Bancaria',
            'proveedores.numero_cuenta_Bancaria',
            'proveedores.observaciones',
            'proveedores.estado',
            'proveedores.usuario_creacion_id',
            'proveedores.usuario_creacion_nombre',
            'proveedores.usuario_modificacion_id',
            'proveedores.usuario_modificacion_nombre',
            'proveedores.created_at as fecha_creacion',
            'proveedores.updated_at as fecha_modificacion' ,
        );

        if(isset($dto['nombre'])){
            $query->where('proveedores.nombre', 'like', '%' . $dto['nombre'] . '%');
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre'){
                    $query->orderBy('proveedores.nombre', $value);
                }
                if($attribute == 'tipo_documento'){
                    $query->orderBy('proveedores.tipo_documento', $value);
                }
                if($attribute == 'digito_verificacion'){
                    $query->orderBy('proveedores.digito_verificacion', $value);
                }  
                if($attribute == 'numero_documento'){
                    $query->orderBy('proveedores.numero_documento', $value);
                }  
                if($attribute == 'direccion'){
                    $query->orderBy('proveedores.direccion', $value);
                }  
                if($attribute == 'ciudad'){
                    $query->orderBy('ciudades.nombre', $value);
                }  
                if($attribute == 'telefono'){
                    $query->orderBy('proveedores.telefono', $value);
                }  
                if($attribute == 'pagina_web'){
                    $query->orderBy('proveedores.pagina_web', $value);
                }  
                if($attribute == 'tipo_Persona'){
                    $query->orderBy('proveedores.tipo_Persona', $value);
                }          
                if($attribute == 'contacto_nombre'){
                    $query->orderBy('proveedores.contacto_nombre', $value);
                }
                if($attribute == 'contacto_telefono'){
                    $query->orderBy('proveedores.contacto_telefono', $value);
                } 
                if($attribute == 'contacto_email'){
                    $query->orderBy('proveedores.contacto_email', $value);
                } 
                if($attribute == 'banco'){
                    $query->orderBy('bancos.nombre', $value);
                } 
                if($attribute == 'tipo_cuenta_Bancaria'){
                    $query->orderBy('proveedores.tipo_cuenta_Bancaria', $value);
                } 
                if($attribute == 'numero_cuenta_Bancaria'){
                    $query->orderBy('proveedores.numero_cuenta_Bancaria', $value);
                }    
                if($attribute == 'numero_cuenta_Bancaria'){
                    $query->orderBy('proveedores.numero_cuenta_Bancaria', $value);
                }    
                if($attribute == 'estado'){
                    $query->orderBy('proveedores.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('proveedores.usuario_creacion_nombre', $value);
                }
                if($attribute == 'observaciones'){
                    $query->orderBy('proveedores.observaciones', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('proveedores.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('proveedores.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("proveedores.updated_at", "desc");
        }

        $proveedores = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $proveedores->items();
    
        return [
            'datos' => $data,
            'desde' => $proveedores->firstItem(),
            'hasta' => $proveedores->lastItem(),
            'por_pagina' => $proveedores->perPage(),
            'pagina_actual' => $proveedores->currentPage(),
            'ultima_pagina' => $proveedores->lastPage(),
            'total' => $proveedores->total(),
        ];
    }

    public static function cargar($id)
    {
        $proveedores = Proveedor::find($id);

        return [
            'id' => $proveedores->id,
            'nombre' => $proveedores->nombre,
            'tipo_documento' => $proveedores->tipo_documento,
            'numero_documento' => $proveedores->numero_documento,
            'direccion' => $proveedores->direccion,
            'id_ciudad' => $proveedores->id_ciudad,
            'telefono' => $proveedores->telefono,
            'pagina_web' => $proveedores->pagina_web,
            'tipo_Persona' => $proveedores->tipo_Persona,
            'contacto_nombre' => $proveedores->contacto_nombre,
            'contacto_telefono' => $proveedores->contacto_telefono,
            'contacto_email' => $proveedores->contacto_email,
            'id_banco' => $proveedores->id_banco,
            'tipo_cuenta_Bancaria' => $proveedores->tipo_cuenta_Bancaria,
            'numero_cuenta_Bancaria' => $proveedores->numero_cuenta_Bancaria,
            'observaciones' => $proveedores->observaciones,
            'estado' => $proveedores->estado,
            'usuario_creacion_id' => $proveedores->usuario_creacion_id,
            'usuario_creacion_nombre' => $proveedores->usuario_creacion_nombre,
            'usuario_modificacion_id' => $proveedores->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $proveedores->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($proveedores->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($proveedores->updated_at))->format("Y-m-d H:i:s")
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
        $proveedores = isset($dto['id']) ? Proveedor::find($dto['id']) : new Proveedor();

        // Guardar objeto original para auditoria
        $companiasOriginal = $proveedores->toJson();

        $proveedores->fill($dto);
        $guardado = $proveedores->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la compañia.", $proveedores);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $proveedores->id,
            'nombre_recurso' => Proveedor::class,
            'descripcion_recurso' => $proveedores->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $companiasOriginal : $proveedores->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $proveedores->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return Proveedor::cargar($proveedores->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $Proveedor = Proveedor::find($id);
        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $Proveedor->id,
            'nombre_recurso' => Proveedor::class,
            'descripcion_recurso' => $Proveedor->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $Proveedor->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $Proveedor->delete();
    }
}
