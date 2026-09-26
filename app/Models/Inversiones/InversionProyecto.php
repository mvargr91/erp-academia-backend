<?php

namespace App\Models\Inversiones;

use Exception;
use Carbon\Carbon;
use App\Enum\AccionAuditoriaEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\AuditoriaTabla;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InversionProyecto extends Model
{

    use HasFactory;

    protected $table = 'inversiones_proyectos';

    protected $fillable = [
        'id_inversion',
        'id_proyecto',
        'id_inversionista',
        'id_gestor',
        'codigo_proyecto',
        'fecha_inversion',
        'valor_inversion_por_proyecto',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('inversiones_proyectos')
            ->select(
                'inversiones_proyectos.id',
                'inversiones_proyectos.id_inversion',
                'inversiones_proyectos.id_proyecto',
                'inversiones_proyectos.id_inversionista',
                'inversiones_proyectos.id_gestor',
                'inversiones_proyectos.codigo_proyecto',
                'inversiones_proyectos.fecha_inversion',
                'inversiones_proyectos.valor_inversion_por_proyecto',
            );
        $query->orderBy('codigo_proyecto', 'asc');
        return $query->get();
    }


    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('inversiones_proyectos')
        ->join('inversiones', 'inversiones.id', 'inversiones_proyectos.id_inversion')
        ->join('proyectos', 'proyectos.id', 'inversiones_proyectos.id_proyecto')
        ->join('inversionistas', 'inversionistas.id', 'inversiones_proyectos.id_inversionista')
        ->leftJoin('gestores', 'gestores.id', 'inversiones_proyectos.id_gestor')
            ->select(
            'inversiones_proyectos.id',            
            'inversiones_proyectos.id_inversion',
            'inversiones.valor_inversion as valor_inversion',
            'proyectos.valor_inversion_proyecto as valor_total_inversion',
            'inversiones.fecha_inversion as fecha_inversion',
            'inversiones.estado_inversion as estado_inversion',
            'inversiones_proyectos.id_proyecto',
            'proyectos.nombre as nombre_proyecto',
            'proyectos.codigo_proyecto as codigo_proyecto',
            'inversiones_proyectos.id_inversionista',
            'inversionistas.nombre as inversionista',
            'inversiones_proyectos.id_gestor',
            'gestores.nombre as gestor',
            'inversiones_proyectos.fecha_inversion as fecha_inversion_proyecto',
            'inversiones_proyectos.valor_inversion_por_proyecto',
            'inversiones_proyectos.usuario_creacion_id',
            'inversiones_proyectos.usuario_creacion_nombre',
            'inversiones_proyectos.usuario_modificacion_id',
            'inversiones_proyectos.usuario_modificacion_nombre',
        );

       

        if(isset($dto['id_inversion'])){
            $query->where('inversiones_proyectos.id_inversion', $dto['id_inversion'] );
        }

        if(isset($dto['id_inversionista'])){
            $query->where('inversiones_proyectos.id_inversionista', $dto['id_inversionista'] );
        }

        if(isset($dto['id_proyecto'])){
            $query->where('inversiones_proyectos.id_proyecto', $dto['id_proyecto'] );
        }

        if(isset($dto['estado_inversion'])){
            $query->where('inversiones.estado_inversion', '<>' ,$dto['estado_inversion'] );
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'codigo_proyecto'){
                    $query->orderBy('proyectos.codigo_proyecto', $value);
                }
                if($attribute == 'nombre_proyecto'){
                    $query->orderBy('proyectos.nombre', $value);
                } 
                if($attribute == 'valor_total_inversion'){
                    $query->orderBy('proyectos.valor_inversion_proyecto', $value);
                }
                if($attribute == 'inversionista'){
                    $query->orderBy('inversionistas.nombre', $value);
                }
                if($attribute == 'id_inversion'){
                    $query->orderBy('inversiones_proyectos.id_inversion', $value);
                }
                if($attribute == 'fecha_inversion_proyecto'){
                    $query->orderBy('inversiones_proyectos.fecha_inversion', $value);
                }
                if($attribute == 'valor_inversion_por_proyecto'){
                    $query->orderBy('inversiones_proyectos.valor_inversion_por_proyecto', $value);
                }
                if($attribute == 'estado_inversion'){
                    $query->orderBy('inversiones.estado_inversion', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('inversiones_proyectos.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('inversiones_proyectos.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('inversiones_proyectos.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('inversiones_proyectos.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("inversiones_proyectos.nombre", "asc");
        }

        $inversionProyecto = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $inversionProyecto->items();
    
        return [
            'datos' => $data,
            'desde' => $inversionProyecto->firstItem(),
            'hasta' => $inversionProyecto->lastItem(),
            'por_pagina' => $inversionProyecto->perPage(),
            'pagina_actual' => $inversionProyecto->currentPage(),
            'ultima_pagina' => $inversionProyecto->lastPage(),
            'total' => $inversionProyecto->total(),
        ];
    }

    public static function cargar($id)
    {
        $inversionProyecto = Inversiones::find($id);

        return [
            'id' => $inversionProyecto->id,
            'id_inversion' => $inversionProyecto->id_inversion,
            'id_proyecto' => $inversionProyecto->id_tiid_proyectopo_inversion,
            'id_inversionista' => $inversionProyecto->id_inversionista,
            'id_gestor' => $inversionProyecto->id_gestor,
            'codigo_proyecto' => $inversionProyecto->codigo_proyecto,
            'fecha_inversion' => $inversionProyecto->fecha_inversion,
            'valor_inversion_por_proyecto' => $inversionProyecto->valor_inversion_por_proyecto,
            'usuario_creacion_id' => $inversionProyecto->usuario_creacion_id,
            'usuario_creacion_nombre' => $inversionProyecto->usuario_creacion_nombre,
            'usuario_modificacion_id' => $inversionProyecto->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $inversionProyecto->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($inversionProyecto->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($inversionProyecto->updated_at))->format("Y-m-d H:i:s")
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
        $inversionProyecto = isset($dto['id']) ? Inversiones::find($dto['id']) : new Inversiones();
        // Guardar objeto original para auditoria
        $inversionProyectoOriginal = $inversionProyecto->toJson();        
        
        $inversionProyecto->fill($dto);
        $guardado = $inversionProyecto->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la inversión.", $inversionProyecto);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $inversionProyecto->id,
            'nombre_recurso' => Inversiones::class,
            'descripcion_recurso' => $inversionProyecto->id,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $inversionProyectoOriginal : $inversionProyecto->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $inversionProyecto->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);
        
        return Inversiones::cargar($inversionProyecto->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $inversionProyecto = Inversiones::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $inversionProyecto->id,
            'nombre_recurso' => Inversiones::class,
            'descripcion_recurso' => $inversionProyecto->id,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $inversionProyecto->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $inversionProyecto->delete();
    }
}
