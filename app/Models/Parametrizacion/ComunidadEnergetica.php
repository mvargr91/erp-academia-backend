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

class ComunidadEnergetica extends Model
{
    use HasFactory;

    protected $table = 'comunidades_energeticas';

    protected $fillable = [
        'nombre',
        'numero_resolucion',
        'fecha_resolucion',
        'nurin',
        'tipo_comunidad',
        'numero_registro_comunidad',
        'nombre_comercializador',
        'nombre_operador',
        'nombre_rep_principal',
        'tipo_documento_rep_ppal',
        'numero_documento_rep_ppal',
        'nombre_rep_legal',
        'tipo_documento_rep_legal',
        'numero_documento_rep_legal',
        'telefono_rep_legal',
        'email_rep_legal',
        'nombre_rep_suplente',
        'tipo_documento_rep_supl',
        'numero_documento_rep_supl',
        'telefono_rep_supl',
        'email_rep_supl',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('comunidades_energeticas')
            ->select(
                'comunidades_energeticas.id',
                'comunidades_energeticas.nombre',
                'comunidades_energeticas.numero_resolucion',
                'comunidades_energeticas.fecha_resolucion',
                'comunidades_energeticas.nurin',
                'comunidades_energeticas.tipo_comunidad',
                'comunidades_energeticas.numero_registro_comunidad',
                'comunidades_energeticas.nombre_comercializador',
                'comunidades_energeticas.nombre_operador',
                'comunidades_energeticas.nombre_rep_principal',
                'comunidades_energeticas.tipo_documento_rep_ppal',
                'comunidades_energeticas.numero_documento_rep_ppal',
                'comunidades_energeticas.nombre_rep_legal',
                'comunidades_energeticas.tipo_documento_rep_legal',
                'comunidades_energeticas.numero_documento_rep_legal',
                'comunidades_energeticas.telefono_rep_legal',
                'comunidades_energeticas.email_rep_legal',
                'comunidades_energeticas.nombre_rep_suplente',
                'comunidades_energeticas.tipo_documento_rep_supl',
                'comunidades_energeticas.numero_documento_rep_supl',
                'comunidades_energeticas.telefono_rep_supl',
                'comunidades_energeticas.email_rep_supl',
                'comunidades_energeticas.estado',
            );
        $query->orderBy('nombre', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('comunidades_energeticas')
            ->select(
            'comunidades_energeticas.id',            
            'comunidades_energeticas.nombre',
            'comunidades_energeticas.numero_resolucion',
            'comunidades_energeticas.fecha_resolucion',
            'comunidades_energeticas.nurin',
            'comunidades_energeticas.tipo_comunidad',
            'comunidades_energeticas.numero_registro_comunidad',
            'comunidades_energeticas.nombre_comercializador',
            'comunidades_energeticas.nombre_operador',
            'comunidades_energeticas.nombre_rep_principal',
            'comunidades_energeticas.tipo_documento_rep_ppal',
            'comunidades_energeticas.numero_documento_rep_ppal',
            'comunidades_energeticas.nombre_rep_legal',
            'comunidades_energeticas.tipo_documento_rep_legal',
            'comunidades_energeticas.numero_documento_rep_legal',
            'comunidades_energeticas.telefono_rep_legal',
            'comunidades_energeticas.email_rep_legal',
            'comunidades_energeticas.nombre_rep_suplente',
            'comunidades_energeticas.tipo_documento_rep_supl',
            'comunidades_energeticas.numero_documento_rep_supl',
            'comunidades_energeticas.telefono_rep_supl',
            'comunidades_energeticas.email_rep_supl',
            'comunidades_energeticas.estado',
            'comunidades_energeticas.usuario_creacion_id',
            'comunidades_energeticas.usuario_creacion_nombre',
            'comunidades_energeticas.usuario_modificacion_id',
            'comunidades_energeticas.usuario_modificacion_nombre',
        );

        if(isset($dto['nombre'])){
            $query->where('comunidades_energeticas.nombre', 'like', '%' . $dto['nombre'] . '%');
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre'){
                    $query->orderBy('comunidades_energeticas.nombre', $value);
                }     
                
                if($attribute == 'nombre'){
                    $query->orderBy('comunidades_energeticas.nombre', $value);
                }
                if($attribute == 'numero_resolucion'){
                    $query->orderBy('comunidades_energeticas.numero_resolucion', $value);
                }
                if($attribute == 'fecha_resolucion'){
                    $query->orderBy('comunidades_energeticas.fecha_resolucion', $value);
                }
                if($attribute == 'nurin'){
                    $query->orderBy('comunidades_energeticas.nurin', $value);
                }
                if($attribute == 'tipo_comunidad'){
                    $query->orderBy('comunidades_energeticas.tipo_comunidad', $value);
                }
                if($attribute == 'numero_registro_comunidad'){
                    $query->orderBy('comunidades_energeticas.numero_registro_comunidad', $value);
                }
                if($attribute == 'nombre_comercializador'){
                    $query->orderBy('comunidades_energeticas.nombre_comercializador', $value);
                }
                if($attribute == 'nombre_operador'){
                    $query->orderBy('comunidades_energeticas.nombre_operador', $value);
                }
                if($attribute == 'nombre_rep_principal'){
                    $query->orderBy('comunidades_energeticas.nombre_rep_principal', $value);
                }
                if($attribute == 'tipo_documento_rep_legal'){
                    $query->orderBy('comunidades_energeticas.tipo_documento_rep_legal', $value);
                }
                if($attribute == 'numero_documento_rep_legal'){
                    $query->orderBy('comunidades_energeticas.numero_documento_rep_legal', $value);
                }
                if($attribute == 'telefono_rep_legal'){
                    $query->orderBy('comunidades_energeticas.telefono_rep_legal', $value);
                }
                if($attribute == 'email_rep_legal'){
                    $query->orderBy('comunidades_energeticas.email_rep_legal', $value);
                }
                if($attribute == 'nombre_rep_suplente'){
                    $query->orderBy('comunidades_energeticas.nombre_rep_suplente', $value);
                }
                if($attribute == 'tipo_documento_rep_supl'){
                    $query->orderBy('comunidades_energeticas.tipo_documento_rep_supl', $value);
                }
                if($attribute == 'numero_documento_rep_supl'){
                    $query->orderBy('comunidades_energeticas.numero_documento_rep_supl', $value);
                }
                if($attribute == 'telefono_rep_supl'){
                    $query->orderBy('comunidades_energeticas.telefono_rep_supl', $value);
                }
                if($attribute == 'email_rep_supl'){
                    $query->orderBy('comunidades_energeticas.email_rep_supl', $value);
                }
                if($attribute == 'estado'){
                    $query->orderBy('comunidades_energeticas.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('comunidades_energeticas.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('comunidades_energeticas.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('comunidades_energeticas.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('comunidades_energeticas.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("comunidades_energeticas.updated_at", "desc");
        }

        $ComunidadEnergetica = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $ComunidadEnergetica->items();
    
        return [
            'datos' => $data,
            'desde' => $ComunidadEnergetica->firstItem(),
            'hasta' => $ComunidadEnergetica->lastItem(),
            'por_pagina' => $ComunidadEnergetica->perPage(),
            'pagina_actual' => $ComunidadEnergetica->currentPage(),
            'ultima_pagina' => $ComunidadEnergetica->lastPage(),
            'total' => $ComunidadEnergetica->total(),
        ];
    }

    public static function cargar($id)
    {
        $ComunidadEnergetica = ComunidadEnergetica::find($id);

        return [
            'id' => $ComunidadEnergetica->id,
            'nombre' => $ComunidadEnergetica->nombre,
            'numero_resolucion' => $ComunidadEnergetica->numero_resolucion,
            'fecha_resolucion' => $ComunidadEnergetica->fecha_resolucion,
            'nurin' => $ComunidadEnergetica->nurin,
            'tipo_comunidad' => $ComunidadEnergetica->tipo_comunidad,
            'numero_registro_comunidad' => $ComunidadEnergetica->numero_registro_comunidad,
            'nombre_comercializador' => $ComunidadEnergetica->nombre_comercializador,
            'nombre_operador' => $ComunidadEnergetica->nombre_operador,
            'nombre_rep_principal' => $ComunidadEnergetica->nombre_rep_principal,
            'tipo_documento_rep_ppal' => $ComunidadEnergetica->tipo_documento_rep_ppal,
            'numero_documento_rep_ppal' => $ComunidadEnergetica->numero_documento_rep_ppal,
            'nombre_rep_legal' => $ComunidadEnergetica->nombre_rep_legal,
            'tipo_documento_rep_legal' => $ComunidadEnergetica->tipo_documento_rep_legal,
            'numero_documento_rep_legal' => $ComunidadEnergetica->numero_documento_rep_legal,
            'telefono_rep_legal' => $ComunidadEnergetica->telefono_rep_legal,
            'email_rep_legal' => $ComunidadEnergetica->email_rep_legal,
            'nombre_rep_suplente' => $ComunidadEnergetica->nombre_rep_suplente,
            'tipo_documento_rep_supl' => $ComunidadEnergetica->tipo_documento_rep_supl,
            'numero_documento_rep_supl' => $ComunidadEnergetica->numero_documento_rep_supl,
            'telefono_rep_supl' => $ComunidadEnergetica->telefono_rep_supl,
            'email_rep_supl' => $ComunidadEnergetica->email_rep_supl,
            'estado' => $ComunidadEnergetica->estado,
            'usuario_creacion_id' => $ComunidadEnergetica->usuario_creacion_id,
            'usuario_creacion_nombre' => $ComunidadEnergetica->usuario_creacion_nombre,
            'usuario_modificacion_id' => $ComunidadEnergetica->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $ComunidadEnergetica->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($ComunidadEnergetica->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($ComunidadEnergetica->updated_at))->format("Y-m-d H:i:s")
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
        $ComunidadEnergetica = isset($dto['id']) ? ComunidadEnergetica::find($dto['id']) : new ComunidadEnergetica();

        // Guardar objeto original para auditoria
        $CiudadOriginal = $ComunidadEnergetica->toJson();

        $ComunidadEnergetica->fill($dto);
        $guardado = $ComunidadEnergetica->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la compañia.", $ComunidadEnergetica);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $ComunidadEnergetica->id,
            'nombre_recurso' => ComunidadEnergetica::class,
            'descripcion_recurso' => $ComunidadEnergetica->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $CiudadOriginal : $ComunidadEnergetica->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $ComunidadEnergetica->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return ComunidadEnergetica::cargar($ComunidadEnergetica->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $ComunidadEnergetica = ComunidadEnergetica::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $ComunidadEnergetica->id,
            'nombre_recurso' => ComunidadEnergetica::class,
            'descripcion_recurso' => $ComunidadEnergetica->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $ComunidadEnergetica->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $ComunidadEnergetica->delete();
    }
}
