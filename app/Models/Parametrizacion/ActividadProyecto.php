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

class ActividadProyecto extends Model
{
    use HasFactory;

    protected $table = 'actividades_proyectos';

    protected $fillable = [
        'id_tipo_proyecto',
        'id_etapa_proyecto',
        'nombre',
        'secuencia',
        'Indicativo_fecha_vcmto',
        'indicativo_envio_correo',
        'peso_porcentual_etapa',
        'peso_porcentual_proyecto',
        'id_actividad_prerequisito',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('actividades_proyectos')
            ->select(
                'actividades_proyectos.id',
                'actividades_proyectos.id_tipo_proyecto',
                'actividades_proyectos.id_etapa_proyecto',
                'actividades_proyectos.nombre',
                'actividades_proyectos.secuencia',
                'actividades_proyectos.Indicativo_fecha_vcmto',
                'actividades_proyectos.indicativo_envio_correo',
                'actividades_proyectos.peso_porcentual_etapa',
                'actividades_proyectos.peso_porcentual_proyecto',
                'actividades_proyectos.id_actividad_prerequisito',
                'actividades_proyectos.estado',
                'actividades_proyectos.usuario_creacion_id',
                'actividades_proyectos.usuario_creacion_nombre',
                'actividades_proyectos.usuario_modificacion_id',
                'actividades_proyectos.usuario_modificacion_nombre',
            );

        $query->orderBy('nombre', 'asc');
        return $query->get();
    }

    public static function obtenerColeccionLigeraXTipo($dto){
     
        $query = DB::table('actividades_proyectos')
            ->select(
                'actividades_proyectos.id',
                'actividades_proyectos.id_tipo_proyecto',
                'actividades_proyectos.id_etapa_proyecto',
                'actividades_proyectos.nombre',
                'actividades_proyectos.secuencia',
                'actividades_proyectos.Indicativo_fecha_vcmto',
                'actividades_proyectos.indicativo_envio_correo',
                'actividades_proyectos.peso_porcentual_etapa',
                'actividades_proyectos.peso_porcentual_proyecto',
                'actividades_proyectos.id_actividad_prerequisito',
                'actividades_proyectos.estado',
                'actividades_proyectos.usuario_creacion_id',
                'actividades_proyectos.usuario_creacion_nombre',
                'actividades_proyectos.usuario_modificacion_id',
                'actividades_proyectos.usuario_modificacion_nombre',
            )->where('actividades_proyectos.id_tipo_proyecto', $dto['tipoProyecto']);

        $query->orderBy('nombre', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('actividades_proyectos')
        ->join('tipos_proyectos', 'tipos_proyectos.id', 'actividades_proyectos.id_tipo_proyecto')
        ->join('etapas_proyectos', 'etapas_proyectos.id', 'actividades_proyectos.id_etapa_proyecto')
        ->leftJoin('actividades_proyectos as ap', 'ap.id', 'actividades_proyectos.id_actividad_prerequisito')
            ->select(
            'actividades_proyectos.id',
            'actividades_proyectos.id_tipo_proyecto',
            'tipos_proyectos.nombre as tipo_proyecto',
            'actividades_proyectos.id_etapa_proyecto',
            'etapas_proyectos.nombre as etapa_proyecto',
            'etapas_proyectos.secuencia as etapa_secuencia',
            'actividades_proyectos.nombre',
            'actividades_proyectos.secuencia',
            'actividades_proyectos.Indicativo_fecha_vcmto',
            'actividades_proyectos.indicativo_envio_correo',
            'actividades_proyectos.peso_porcentual_etapa',
            'actividades_proyectos.peso_porcentual_proyecto',
            'actividades_proyectos.id_actividad_prerequisito',
            'ap.nombre as actividad_prerequisito',
            'actividades_proyectos.estado',
            'actividades_proyectos.usuario_creacion_id',
            'actividades_proyectos.usuario_creacion_nombre',
            'actividades_proyectos.usuario_modificacion_id',
            'actividades_proyectos.usuario_modificacion_nombre',
        );

        if(isset($dto['tipoProyecto'])){
            $query->where('actividades_proyectos.id_tipo_proyecto', $dto['tipoProyecto']);
        }

        if(isset($dto['etapaProyecto'])){
            $query->where('etapas_proyectos.nombre',  $dto['etapaProyecto']);
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'tipo_proyecto'){
                    $query->orderBy('tipos_proyectos.nombre', $value);
                }
                if($attribute == 'secuencia'){
                    $query->orderBy('actividades_proyectos.secuencia', $value);
                }
                if($attribute == 'etapa_proyecto'){
                    $query->orderBy('etapas_proyectos.nombre', $value);
                }
                if($attribute == 'nombre'){
                    $query->orderBy('actividades_proyectos.nombre', $value);
                }
                if($attribute == 'Indicativo_fecha_vcmto'){
                    $query->orderBy('actividades_proyectos.Indicativo_fecha_vcmto', $value);
                }
                if($attribute == 'indicativo_envio_correo'){
                    $query->orderBy('actividades_proyectos.indicativo_envio_correo', $value);
                }
                if($attribute == 'peso_porcentual_etapa'){
                    $query->orderBy('actividades_proyectos.peso_porcentual_etapa', $value);
                }
                if($attribute == 'peso_porcentual_proyecto'){
                    $query->orderBy('actividades_proyectos.peso_porcentual_proyecto', $value);
                }
                if($attribute == 'estado'){
                    $query->orderBy('actividades_proyectos.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('actividades_proyectos.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('actividades_proyectos.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('actividades_proyectos.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('actividades_proyectos.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("tipos_proyectos.nombre", "asc");
            $query->orderBy("etapas_proyectos.secuencia", "asc");
            $query->orderBy("actividades_proyectos.secuencia", "asc");
        }

        $actividades_proyectos = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $actividades_proyectos->items();
    
        return [
            'datos' => $data,
            'desde' => $actividades_proyectos->firstItem(),
            'hasta' => $actividades_proyectos->lastItem(),
            'por_pagina' => $actividades_proyectos->perPage(),
            'pagina_actual' => $actividades_proyectos->currentPage(),
            'ultima_pagina' => $actividades_proyectos->lastPage(),
            'total' => $actividades_proyectos->total(),
        ];
    }

    public static function cargar($id)
    {
        $actividades_proyectos = ActividadProyecto::find($id);

        return [
            'id' => $actividades_proyectos->id,
            'id_tipo_proyecto' => $actividades_proyectos->id_tipo_proyecto,
            'id_etapa_proyecto' => $actividades_proyectos->id_etapa_proyecto,
            'nombre' => $actividades_proyectos->nombre,
            'secuencia' => $actividades_proyectos->secuencia,
            'Indicativo_fecha_vcmto' => $actividades_proyectos->Indicativo_fecha_vcmto,
            'indicativo_envio_correo' => $actividades_proyectos->indicativo_envio_correo,
            'peso_porcentual_etapa' => $actividades_proyectos->peso_porcentual_etapa,
            'peso_porcentual_proyecto' => $actividades_proyectos->peso_porcentual_proyecto,
            'id_actividad_prerequisito' => $actividades_proyectos->id_actividad_prerequisito,
            'estado' => $actividades_proyectos->estado,
            'usuario_creacion_id' => $actividades_proyectos->usuario_creacion_id,
            'usuario_creacion_nombre' => $actividades_proyectos->usuario_creacion_nombre,
            'usuario_modificacion_id' => $actividades_proyectos->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $actividades_proyectos->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($actividades_proyectos->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($actividades_proyectos->updated_at))->format("Y-m-d H:i:s")
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
        $actividades_proyectos = isset($dto['id']) ? ActividadProyecto::find($dto['id']) : new ActividadProyecto();

        // Guardar objeto original para auditoria
        $inversionistas_contactosOriginal = $actividades_proyectos->toJson();

        $actividades_proyectos->fill($dto);
        $guardado = $actividades_proyectos->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la compañia.", $actividades_proyectos);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $actividades_proyectos->id,
            'nombre_recurso' => ActividadProyecto::class,
            'descripcion_recurso' => $actividades_proyectos->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $inversionistas_contactosOriginal : $actividades_proyectos->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $actividades_proyectos->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return ActividadProyecto::cargar($actividades_proyectos->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $actividades_proyectos = ActividadProyecto::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $actividades_proyectos->id,
            'nombre_recurso' => ActividadProyecto::class,
            'descripcion_recurso' => $actividades_proyectos->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $actividades_proyectos->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $actividades_proyectos->delete();
    }
}
