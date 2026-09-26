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

class InversionistaContacto extends Model
{
    use HasFactory;

    protected $table = 'inversionistas_contactos';

    protected $fillable = [
        'id_inversionista',
        'tipo_contacto',
        'tipo_documento_cont',
        'numero_documento_cont',
        'nombre_contacto',
        'cargo_contacto',
        'email_contacto',
        'telefono_contacto',
        'telefono_celular_contacto',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('inversionistas_contactos')
            ->select(
                'inversionistas_contactos.id',
                'inversionistas_contactos.id_inversionista',
                'inversionistas_contactos.tipo_contacto',
                'inversionistas_contactos.tipo_documento_cont',
                'inversionistas_contactos.numero_documento_cont',
                'inversionistas_contactos.nombre_contacto',
                'inversionistas_contactos.cargo_contacto',
                'inversionistas_contactos.email_contacto',
                'inversionistas_contactos.telefono_contacto',
                'inversionistas_contactos.telefono_celular_contacto',
                'inversionistas_contactos.estado',
                'inversionistas_contactos.usuario_creacion_id',
                'inversionistas_contactos.usuario_creacion_nombre',
                'inversionistas_contactos.usuario_modificacion_id',
                'inversionistas_contactos.usuario_modificacion_nombre',
            )->where('id_inversionista', $dto['id_inversionista']);

        $query->orderBy('nombre_contacto', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('inversionistas_contactos')
        ->join('inversionistas', 'inversionistas.id', 'inversionistas_contactos.id_inversionista')
            ->select(
            'inversionistas_contactos.id',
            'inversionistas_contactos.id_inversionista',
            'inversionistas.nombre as nombre',
            'inversionistas_contactos.tipo_contacto',
            'inversionistas_contactos.tipo_documento_cont',
            'inversionistas_contactos.numero_documento_cont',
            'inversionistas_contactos.nombre_contacto',
            'inversionistas_contactos.cargo_contacto',
            'inversionistas_contactos.email_contacto',
            'inversionistas_contactos.telefono_contacto',
            'inversionistas_contactos.telefono_celular_contacto',
            'inversionistas_contactos.estado',
            'inversionistas_contactos.usuario_creacion_id',
            'inversionistas_contactos.usuario_creacion_nombre',
            'inversionistas_contactos.usuario_modificacion_id',
            'inversionistas_contactos.usuario_modificacion_nombre',
            'inversionistas_contactos.created_at as fecha_creacion',
            'inversionistas_contactos.updated_at as fecha_modificacion' ,
        )
        ->where('inversionistas_contactos.id_inversionista', $dto['id_inversionista']);
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre'){
                    $query->orderBy('inversionistas.nombre', $value);
                }
                if($attribute == 'tipo_contacto'){
                    $query->orderBy('inversionistas_contactos.tipo_contacto', $value);
                }
                if($attribute == 'tipo_documento_cont'){
                    $query->orderBy('inversionistas_contactos.tipo_documento_cont', $value);
                }  
                if($attribute == 'numero_documento_cont'){
                    $query->orderBy('inversionistas_contactos.numero_documento_cont', $value);
                }  
                if($attribute == 'nombre_contacto'){
                    $query->orderBy('inversionistas_contactos.nombre_contacto', $value);
                }  
                if($attribute == 'cargo_contacto'){
                    $query->orderBy('inversionistas_contactos.cargo_contacto', $value);
                }  
                if($attribute == 'email_contacto'){
                    $query->orderBy('inversionistas_contactos.email_contacto', $value);
                }  
                if($attribute == 'telefono_contacto'){
                    $query->orderBy('inversionistas_contactos.telefono_contacto', $value);
                } 
                if($attribute == 'telefono_celular_contacto'){
                    $query->orderBy('inversionistas_contactos.telefono_celular_contacto', $value);
                }     
                if($attribute == 'estado'){
                    $query->orderBy('inversionistas_contactos.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('inversionistas_contactos.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('inversionistas_contactos.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('inversionistas_contactos.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('inversionistas_contactos.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("inversionistas_contactos.updated_at", "desc");
        }

        $inversionistas_contactos = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $inversionistas_contactos->items();
    
        return [
            'datos' => $data,
            'desde' => $inversionistas_contactos->firstItem(),
            'hasta' => $inversionistas_contactos->lastItem(),
            'por_pagina' => $inversionistas_contactos->perPage(),
            'pagina_actual' => $inversionistas_contactos->currentPage(),
            'ultima_pagina' => $inversionistas_contactos->lastPage(),
            'total' => $inversionistas_contactos->total(),
        ];
    }

    public static function cargar($id)
    {
        $inversionistas_contactos = InversionistaContacto::find($id);

        return [
            'id' => $inversionistas_contactos->id,
            'id_inversionista' => $inversionistas_contactos->id_inversionista,
            'tipo_contacto' => $inversionistas_contactos->tipo_contacto,
            'tipo_documento_cont' => $inversionistas_contactos->tipo_documento_cont,
            'numero_documento_cont' => $inversionistas_contactos->numero_documento_cont,
            'nombre_contacto' => $inversionistas_contactos->nombre_contacto,
            'cargo_contacto' => $inversionistas_contactos->cargo_contacto,
            'email_contacto' => $inversionistas_contactos->email_contacto,
            'telefono_contacto' => $inversionistas_contactos->telefono_contacto,
            'telefono_celular_contacto' => $inversionistas_contactos->telefono_celular_contacto,
            'estado' => $inversionistas_contactos->estado,
            'usuario_creacion_id' => $inversionistas_contactos->usuario_creacion_id,
            'usuario_creacion_nombre' => $inversionistas_contactos->usuario_creacion_nombre,
            'usuario_modificacion_id' => $inversionistas_contactos->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $inversionistas_contactos->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($inversionistas_contactos->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($inversionistas_contactos->updated_at))->format("Y-m-d H:i:s")
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
        $inversionistas_contactos = isset($dto['id']) ? InversionistaContacto::find($dto['id']) : new InversionistaContacto();

        // Guardar objeto original para auditoria
        $inversionistas_contactosOriginal = $inversionistas_contactos->toJson();

        $inversionistas_contactos->fill($dto);
        $guardado = $inversionistas_contactos->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la compañia.", $inversionistas_contactos);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $inversionistas_contactos->id,
            'nombre_recurso' => InversionistaContacto::class,
            'descripcion_recurso' => $inversionistas_contactos->nombre_contacto,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $inversionistas_contactosOriginal : $inversionistas_contactos->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $inversionistas_contactos->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return InversionistaContacto::cargar($inversionistas_contactos->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $inversionistas_contactos = InversionistaContacto::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $inversionistas_contactos->id,
            'nombre_recurso' => InversionistaContacto::class,
            'descripcion_recurso' => $inversionistas_contactos->nombre_contacto,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $inversionistas_contactos->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $inversionistas_contactos->delete();
    }
}
