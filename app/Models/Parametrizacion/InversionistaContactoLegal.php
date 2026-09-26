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

class InversionistaContactoLegal extends Model
{
    use HasFactory;

    protected $table = 'inversionistas_contactos_legales';

    protected $fillable = [
        'id_inversionista',
        'tipo_contacto_legal',
        'tipo_documento_cont_leg',
        'numero_documento_cont_leg',
        'id_ciudad_docto',
        'nombre_cont_leg',
        'direccion_cont_leg',
        'id_ciudad_cont_leg',
        'telefono_cont_leg',
        'porcentaje_participacion',
        'banco',
        'tipo_cuenta',
        'numero_cuenta',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('inversionistas_contactos_legales')
            ->select(
                'inversionistas_contactos_legales.id',
                'inversionistas_contactos_legales.id_inversionista',
                'inversionistas_contactos_legales.tipo_contacto_legal',
                'inversionistas_contactos_legales.tipo_documento_cont_leg',
                'inversionistas_contactos_legales.numero_documento_cont_leg',
                'inversionistas_contactos_legales.id_ciudad_docto',
                'inversionistas_contactos_legales.nombre_cont_leg',
                'inversionistas_contactos_legales.direccion_cont_leg',
                'inversionistas_contactos_legales.id_ciudad_cont_leg',
                'inversionistas_contactos_legales.telefono_cont_leg',
                'inversionistas_contactos_legales.porcentaje_participacion',
                'inversionistas_contactos_legales.banco',
                'inversionistas_contactos_legales.tipo_cuenta',
                'inversionistas_contactos_legales.numero_cuenta',
                'inversionistas_contactos_legales.estado',
                'inversionistas_contactos_legales.usuario_creacion_id',
                'inversionistas_contactos_legales.usuario_creacion_nombre',
                'inversionistas_contactos_legales.usuario_modificacion_id',
                'inversionistas_contactos_legales.usuario_modificacion_nombre',
                'inversionistas_contactos_legales.created_at as fecha_creacion',
                'inversionistas_contactos_legales.updated_at as fecha_modificacion' ,
            )->where('inversionistas_contactos_legales.id_inversionista', $dto['id_inversionista']);

        $query->orderBy('nombre_cont_leg', 'asc');
        return $query->get();
    }

    public static function obtenerContactoLegalDocumento($dto){
     
        $query = DB::table('inversionistas_contactos_legales')
            ->select(
                'inversionistas_contactos_legales.id',
                'inversionistas_contactos_legales.id_inversionista',
                'inversionistas_contactos_legales.tipo_contacto_legal',
                'inversionistas_contactos_legales.tipo_documento_cont_leg',
                'inversionistas_contactos_legales.numero_documento_cont_leg',
                'inversionistas_contactos_legales.id_ciudad_docto',
                'inversionistas_contactos_legales.nombre_cont_leg',
                'inversionistas_contactos_legales.direccion_cont_leg',
                'inversionistas_contactos_legales.id_ciudad_cont_leg',
                'inversionistas_contactos_legales.telefono_cont_leg',
                'inversionistas_contactos_legales.porcentaje_participacion',
                'inversionistas_contactos_legales.banco',
                'inversionistas_contactos_legales.tipo_cuenta',
                'inversionistas_contactos_legales.numero_cuenta',
                'inversionistas_contactos_legales.estado',
                'inversionistas_contactos_legales.usuario_creacion_id',
                'inversionistas_contactos_legales.usuario_creacion_nombre',
                'inversionistas_contactos_legales.usuario_modificacion_id',
                'inversionistas_contactos_legales.usuario_modificacion_nombre',
                'inversionistas_contactos_legales.created_at as fecha_creacion',
                'inversionistas_contactos_legales.updated_at as fecha_modificacion' ,
            )->where('numero_documento_cont_leg', $dto['numero_documento']);

        $query->orderBy('nombre_cont_leg', 'asc');
        $query->limit(1);
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('inversionistas_contactos_legales')
        ->join('inversionistas', 'inversionistas.id', 'inversionistas_contactos_legales.id_inversionista')
        ->join('ciudades as cdcto', 'cdcto.id', 'inversionistas_contactos_legales.id_ciudad_docto')
        ->leftJoin('ciudades as cclegal', 'cclegal.id', 'inversionistas_contactos_legales.id_ciudad_cont_leg')
            ->select(
            'inversionistas_contactos_legales.id',
            'inversionistas_contactos_legales.id_inversionista',
            'inversionistas.nombre as nombre',
            'inversionistas_contactos_legales.tipo_contacto_legal',
            'inversionistas_contactos_legales.tipo_documento_cont_leg',
            'inversionistas_contactos_legales.numero_documento_cont_leg',
            'inversionistas_contactos_legales.id_ciudad_docto',
            'cdcto.nombre as ciudad_docto',
            'inversionistas_contactos_legales.nombre_cont_leg',
            'inversionistas_contactos_legales.direccion_cont_leg',
            'inversionistas_contactos_legales.id_ciudad_cont_leg',
            'cclegal.nombre as ciudad_cont_leg',
            'inversionistas_contactos_legales.telefono_cont_leg',
            'inversionistas_contactos_legales.porcentaje_participacion',
            'inversionistas_contactos_legales.banco',
            'inversionistas_contactos_legales.tipo_cuenta',
            'inversionistas_contactos_legales.numero_cuenta',
            'inversionistas_contactos_legales.estado',
            'inversionistas_contactos_legales.usuario_creacion_id',
            'inversionistas_contactos_legales.usuario_creacion_nombre',
            'inversionistas_contactos_legales.usuario_modificacion_id',
            'inversionistas_contactos_legales.usuario_modificacion_nombre',
            'inversionistas_contactos_legales.created_at as fecha_creacion',
            'inversionistas_contactos_legales.updated_at as fecha_modificacion' ,
        )
        ->where('inversionistas_contactos_legales.id_inversionista', $dto['id_inversionista']);
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre'){
                    $query->orderBy('inversionistas.nombre', $value);
                }
                if($attribute == 'tipo_contacto_legal'){
                    $query->orderBy('inversionistas_contactos_legales.tipo_contacto_legal', $value);
                }
                if($attribute == 'tipo_documento_cont'){
                    $query->orderBy('inversionistas_contactos_legales.tipo_documento_cont', $value);
                }  
                if($attribute == 'tipo_documento_cont_leg'){
                    $query->orderBy('inversionistas_contactos_legales.tipo_documento_cont_leg', $value);
                }  
                if($attribute == 'numero_documento_cont_leg'){
                    $query->orderBy('inversionistas_contactos_legales.numero_documento_cont_leg', $value);
                }  
                if($attribute == 'ciudad_docto'){
                    $query->orderBy('ciudad_docto.nombre', $value);
                }  
                if($attribute == 'nombre_cont_leg'){
                    $query->orderBy('inversionistas_contactos_legales.nombre_cont_leg', $value);
                }  
                if($attribute == 'direccion_cont_leg'){
                    $query->orderBy('inversionistas_contactos_legales.direccion_cont_leg', $value);
                } 
                if($attribute == 'ciudad_cont_leg'){
                    $query->orderBy('ciudades.nombre', $value);
                }     
                if($attribute == 'telefono_cont_leg'){
                    $query->orderBy('inversionistas_contactos_legales.telefono_cont_leg', $value);
                }   
                if($attribute == 'porcentaje_participacion'){
                    $query->orderBy('inversionistas_contactos_legales.porcentaje_participacion', $value);
                }   
                if($attribute == 'banco'){
                    $query->orderBy('inversionistas_contactos_legales.banco', $value);
                } 
                if($attribute == 'tipo_cuenta'){
                    $query->orderBy('inversionistas_contactos_legales.tipo_cuenta', $value);
                } 
                if($attribute == 'numero_cuenta'){
                    $query->orderBy('inversionistas_contactos_legales.numero_cuenta', $value);
                } 
                if($attribute == 'estado'){
                    $query->orderBy('inversionistas_contactos_legales.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('inversionistas_contactos_legales.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('inversionistas_contactos_legales.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('inversionistas_contactos_legales.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('inversionistas_contactos_legales.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("inversionistas_contactos_legales.updated_at", "desc");
        }

        $inversionistas_contactos_legales = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $inversionistas_contactos_legales->items();
    
        return [
            'datos' => $data,
            'desde' => $inversionistas_contactos_legales->firstItem(),
            'hasta' => $inversionistas_contactos_legales->lastItem(),
            'por_pagina' => $inversionistas_contactos_legales->perPage(),
            'pagina_actual' => $inversionistas_contactos_legales->currentPage(),
            'ultima_pagina' => $inversionistas_contactos_legales->lastPage(),
            'total' => $inversionistas_contactos_legales->total(),
        ];
    }

    public static function cargar($id)
    {
        $inversionistas_contactos_legales = InversionistaContactoLegal::find($id);

        return [
            'id' => $inversionistas_contactos_legales->id,
            'id_inversionista' => $inversionistas_contactos_legales->id_inversionista,
            'tipo_contacto_legal' => $inversionistas_contactos_legales->tipo_contacto_legal,
            'tipo_documento_cont_leg' => $inversionistas_contactos_legales->tipo_documento_cont_leg,
            'numero_documento_cont_leg' => $inversionistas_contactos_legales->numero_documento_cont_leg,
            'id_ciudad_docto' => $inversionistas_contactos_legales->id_ciudad_docto,
            'nombre_cont_leg' => $inversionistas_contactos_legales->nombre_cont_leg,
            'direccion_cont_leg' => $inversionistas_contactos_legales->direccion_cont_leg,
            'id_ciudad_cont_leg' => $inversionistas_contactos_legales->id_ciudad_cont_leg,
            'telefono_cont_leg' => $inversionistas_contactos_legales->telefono_cont_leg,
            'porcentaje_participacion' => $inversionistas_contactos_legales->porcentaje_participacion,
            'banco' => $inversionistas_contactos_legales->banco,
            'tipo_cuenta' => $inversionistas_contactos_legales->tipo_cuenta,
            'numero_cuenta' => $inversionistas_contactos_legales->numero_cuenta,
            'estado' => $inversionistas_contactos_legales->estado,
            'usuario_creacion_id' => $inversionistas_contactos_legales->usuario_creacion_id,
            'usuario_creacion_nombre' => $inversionistas_contactos_legales->usuario_creacion_nombre,
            'usuario_modificacion_id' => $inversionistas_contactos_legales->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $inversionistas_contactos_legales->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($inversionistas_contactos_legales->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($inversionistas_contactos_legales->updated_at))->format("Y-m-d H:i:s")
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
        $inversionistas_contactos_legales = isset($dto['id']) ? InversionistaContactoLegal::find($dto['id']) : new InversionistaContactoLegal();

        // Guardar objeto original para auditoria
        $inversionistas_contactos_legalesOriginal = $inversionistas_contactos_legales->toJson();

        $inversionistas_contactos_legales->fill($dto);
        $guardado = $inversionistas_contactos_legales->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la compañia.", $inversionistas_contactos_legales);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $inversionistas_contactos_legales->id,
            'nombre_recurso' => InversionistaContactoLegal::class,
            'descripcion_recurso' => $inversionistas_contactos_legales->nombre_cont_leg,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $inversionistas_contactos_legalesOriginal : $inversionistas_contactos_legales->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $inversionistas_contactos_legales->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return InversionistaContactoLegal::cargar($inversionistas_contactos_legales->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $inversionistas_contactos_legales = InversionistaContactoLegal::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $inversionistas_contactos_legales->id,
            'nombre_recurso' => InversionistaContactoLegal::class,
            'descripcion_recurso' => $inversionistas_contactos_legales->nombre_cont_leg,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $inversionistas_contactos_legales->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $inversionistas_contactos_legales->delete();
    }
}
