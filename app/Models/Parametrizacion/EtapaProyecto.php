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

class EtapaProyecto extends Model
{
    use HasFactory;

    protected $table = 'etapas_proyectos';

    protected $fillable = [
        'id_tipo_proyecto',
        'nombre',
        'secuencia',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){     

         if(isset($dto['tipoProyecto']) && $dto['tipoProyecto'] != null){
             $query = DB::table('etapas_proyectos')
             ->select(
                'etapas_proyectos.id',            
                'etapas_proyectos.id_tipo_proyecto',           
                'etapas_proyectos.nombre',
                'etapas_proyectos.secuencia',
                'etapas_proyectos.estado'
            )->where('etapas_proyectos.id_tipo_proyecto', $dto['tipoProyecto']);
        }else{
            $query = DB::table('etapas_proyectos')
            ->select(
                DB::raw('MIN(id) as id'),
                'nombre',
                DB::raw('MIN(id_tipo_proyecto) as id_tipo_proyecto'),
                DB::raw('MIN(secuencia) as secuencia'),
                DB::raw('MIN(estado) as estado')
            )
            ->groupBy('nombre')
            ->orderBy('nombre', 'asc');
        }

        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('etapas_proyectos')
        ->join('tipos_proyectos', 'tipos_proyectos.id', 'etapas_proyectos.id_tipo_proyecto')
            ->select(
            'etapas_proyectos.id',            
            'etapas_proyectos.id_tipo_proyecto',            
            'tipos_proyectos.nombre as tipo_proyecto', 
            'etapas_proyectos.nombre',
            'etapas_proyectos.secuencia',
            'etapas_proyectos.estado',
            'etapas_proyectos.usuario_creacion_id',
            'etapas_proyectos.usuario_creacion_nombre',
            'etapas_proyectos.usuario_modificacion_id',
            'etapas_proyectos.usuario_modificacion_nombre',
        );

        if(isset($dto['etapaProyecto'])){
            $query->where('etapas_proyectos.nombre', 'like', $dto['etapaProyecto']);
        }

        if(isset($dto['tipoProyecto'])){
            $query->where('etapas_proyectos.id_tipo_proyecto', $dto['tipoProyecto']);
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre'){
                    $query->orderBy('etapas_proyectos.nombre', $value);
                }
                if($attribute == 'id_tipo_proyecto'){
                    $query->orderBy('etapas_proyectos.id_tipo_proyecto', $value);
                }
                if($attribute == 'tipo_proyecto'){
                    $query->orderBy('tipos_proyectos.nombre', $value);
                }  
                if($attribute == 'secuencia'){
                    $query->orderBy('etapas_proyectos.secuencia', $value);
                }     
                if($attribute == 'estado'){
                    $query->orderBy('etapas_proyectos.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('etapas_proyectos.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('etapas_proyectos.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('etapas_proyectos.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('etapas_proyectos.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("etapas_proyectos.id_tipo_proyecto", "desc");
            $query->orderBy("etapas_proyectos.secuencia", "asc");
        }

        $etapas_proyectos = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $etapas_proyectos->items();
    
        return [
            'datos' => $data,
            'desde' => $etapas_proyectos->firstItem(),
            'hasta' => $etapas_proyectos->lastItem(),
            'por_pagina' => $etapas_proyectos->perPage(),
            'pagina_actual' => $etapas_proyectos->currentPage(),
            'ultima_pagina' => $etapas_proyectos->lastPage(),
            'total' => $etapas_proyectos->total(),
        ];
    }

    public static function cargar($id)
    {
        $etapas_proyectos = EtapaProyecto::find($id);

        return [
            'id' => $etapas_proyectos->id,
            'id_tipo_proyecto' => $etapas_proyectos->id_tipo_proyecto,
            'nombre' => $etapas_proyectos->nombre,
            'secuencia' => $etapas_proyectos->secuencia,
            'estado' => $etapas_proyectos->estado,
            'usuario_creacion_id' => $etapas_proyectos->usuario_creacion_id,
            'usuario_creacion_nombre' => $etapas_proyectos->usuario_creacion_nombre,
            'usuario_modificacion_id' => $etapas_proyectos->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $etapas_proyectos->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($etapas_proyectos->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($etapas_proyectos->updated_at))->format("Y-m-d H:i:s")
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
        $etapas_proyectos = isset($dto['id']) ? EtapaProyecto::find($dto['id']) : new EtapaProyecto();

        // Guardar objeto original para auditoria
        $companiasOriginal = $etapas_proyectos->toJson();

        $etapas_proyectos->fill($dto);
        $guardado = $etapas_proyectos->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la compañia.", $etapas_proyectos);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $etapas_proyectos->id,
            'nombre_recurso' => EtapaProyecto::class,
            'descripcion_recurso' => $etapas_proyectos->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $companiasOriginal : $etapas_proyectos->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $etapas_proyectos->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return EtapaProyecto::cargar($etapas_proyectos->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $etapas_proyectos = EtapaProyecto::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $etapas_proyectos->id,
            'nombre_recurso' => EtapaProyecto::class,
            'descripcion_recurso' => $etapas_proyectos->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $etapas_proyectos->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $etapas_proyectos->delete();
    }
}
