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

class Compania extends Model
{
    use HasFactory;

    protected $table = 'companias';

    protected $fillable = [
        'nombre',
        'numero_nit',
        'digito_verificacion',
        'direccion',
        'ciudad_id',
        'telefono_1',
        'telefono_2',
        'representante_legal',
        'email_representante',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('companias')
            ->select(
                'companias.id',
                'companias.nombre',
                'companias.numero_nit',
                'companias.digito_verificacion',
                'companias.direccion',
                'companias.ciudad_id',
                'companias.telefono_1',
                'companias.telefono_2',
                'companias.representante_legal',
                'companias.email_representante',
                'companias.estado',
            );
        $query->orderBy('nombre', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('companias')
        ->join('ciudades', 'ciudades.id', 'companias.ciudad_id')
            ->select(
            'companias.id',            
            'companias.nombre',
            'companias.numero_nit',
            'companias.digito_verificacion',
            'companias.direccion',
            'companias.ciudad_id',
            'ciudades.nombre as ciudad', 
            'companias.telefono_1',
            'companias.telefono_2',
            'companias.representante_legal',
            'companias.email_representante',
            'companias.estado',
            'companias.usuario_creacion_id',
            'companias.usuario_creacion_nombre',
            'companias.usuario_modificacion_id',
            'companias.usuario_modificacion_nombre',
        );

        if(isset($dto['nombre'])){
            $query->where('companias.nombre', 'like', '%' . $dto['nombre'] . '%');
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre'){
                    $query->orderBy('companias.nombre', $value);
                }
                if($attribute == 'numero_nit'){
                    $query->orderBy('companias.numero_nit', $value);
                }
                if($attribute == 'digito_verificacion'){
                    $query->orderBy('companias.digito_verificacion', $value);
                }  
                if($attribute == 'direccion'){
                    $query->orderBy('companias.direccion', $value);
                }  
                if($attribute == 'ciudad'){
                    $query->orderBy('companias.ciudad', $value);
                }  
                if($attribute == 'telefono_1'){
                    $query->orderBy('companias.telefono_1', $value);
                }  
                if($attribute == 'telefono_2'){
                    $query->orderBy('companias.telefono_2', $value);
                }  
                if($attribute == 'representante_legal'){
                    $query->orderBy('companias.representante_legal', $value);
                }          
                if($attribute == 'email_representante'){
                    $query->orderBy('companias.email_representante', $value);
                }       
                if($attribute == 'estado'){
                    $query->orderBy('companias.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('companias.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('companias.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('companias.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('companias.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("companias.updated_at", "desc");
        }

        $companias = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $companias->items();
    
        return [
            'datos' => $data,
            'desde' => $companias->firstItem(),
            'hasta' => $companias->lastItem(),
            'por_pagina' => $companias->perPage(),
            'pagina_actual' => $companias->currentPage(),
            'ultima_pagina' => $companias->lastPage(),
            'total' => $companias->total(),
        ];
    }

    public static function cargar($id)
    {
        $companias = Compania::find($id);

        return [
            'id' => $companias->id,
            'nombre' => $companias->nombre,
            'numero_nit' => $companias->numero_nit,
            'digito_verificacion' => $companias->digito_verificacion,
            'direccion' => $companias->direccion,
            'ciudad_id' => $companias->ciudad_id,
            'telefono_1' => $companias->telefono_1,
            'telefono_2' => $companias->telefono_2,
            'representante_legal' => $companias->representante_legal,
            'email_representante' => $companias->email_representante,
            'estado' => $companias->estado,
            'usuario_creacion_id' => $companias->usuario_creacion_id,
            'usuario_creacion_nombre' => $companias->usuario_creacion_nombre,
            'usuario_modificacion_id' => $companias->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $companias->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($companias->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($companias->updated_at))->format("Y-m-d H:i:s")
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
        $companias = isset($dto['id']) ? Compania::find($dto['id']) : new Compania();

        // Guardar objeto original para auditoria
        $companiasOriginal = $companias->toJson();

        $companias->fill($dto);
        $guardado = $companias->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la compañia.", $companias);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $companias->id,
            'nombre_recurso' => Compania::class,
            'descripcion_recurso' => $companias->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $companiasOriginal : $companias->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $companias->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return Compania::cargar($companias->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $companias = Compania::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $companias->id,
            'nombre_recurso' => Compania::class,
            'descripcion_recurso' => $companias->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $companias->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $companias->delete();
    }
}
