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

class TipoProyecto extends Model
{
    use HasFactory;

    protected $table = 'tipos_proyectos';

    protected $fillable = [
        'nombre',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('tipos_proyectos')
            ->select(
                'tipos_proyectos.id',
                'tipos_proyectos.nombre',
                'tipos_proyectos.estado',
            );
        $query->orderBy('nombre', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('tipos_proyectos')
            ->select(
            'tipos_proyectos.id',            
            'tipos_proyectos.nombre',
            'tipos_proyectos.estado',
            'tipos_proyectos.usuario_creacion_id',
            'tipos_proyectos.usuario_creacion_nombre',
            'tipos_proyectos.usuario_modificacion_id',
            'tipos_proyectos.usuario_modificacion_nombre',
        );

        if(isset($dto['nombre'])){
            $query->where('tipos_proyectos.nombre', 'like', '%' . $dto['nombre'] . '%');
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre'){
                    $query->orderBy('tipos_proyectos.nombre', $value);
                }
                if($attribute == 'estado'){
                    $query->orderBy('tipos_proyectos.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('tipos_proyectos.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('tipos_proyectos.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('tipos_proyectos.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('tipos_proyectos.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("tipos_proyectos.updated_at", "desc");
        }

        $TipoProyecto = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $TipoProyecto->items();
    
        return [
            'datos' => $data,
            'desde' => $TipoProyecto->firstItem(),
            'hasta' => $TipoProyecto->lastItem(),
            'por_pagina' => $TipoProyecto->perPage(),
            'pagina_actual' => $TipoProyecto->currentPage(),
            'ultima_pagina' => $TipoProyecto->lastPage(),
            'total' => $TipoProyecto->total(),
        ];
    }

    public static function cargar($id)
    {
        $TipoProyecto = TipoProyecto::find($id);

        return [
            'id' => $TipoProyecto->id,
            'nombre' => $TipoProyecto->nombre,
            'estado' => $TipoProyecto->estado,
            'usuario_creacion_id' => $TipoProyecto->usuario_creacion_id,
            'usuario_creacion_nombre' => $TipoProyecto->usuario_creacion_nombre,
            'usuario_modificacion_id' => $TipoProyecto->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $TipoProyecto->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($TipoProyecto->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($TipoProyecto->updated_at))->format("Y-m-d H:i:s")
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
        $TipoProyecto = isset($dto['id']) ? TipoProyecto::find($dto['id']) : new TipoProyecto();
     
        // Guardar objeto original para auditoria
        $TipoProyectoOriginal = $TipoProyecto->toJson();

        $TipoProyecto->fill($dto);
        $guardado = $TipoProyecto->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar.", $TipoProyecto);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $TipoProyecto->id,
            'nombre_recurso' => TipoProyecto::class,
            'descripcion_recurso' => $TipoProyecto->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $TipoProyectoOriginal : $TipoProyecto->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $TipoProyecto->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return TipoProyecto::cargar($TipoProyecto->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $TipoProyecto = TipoProyecto::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $TipoProyecto->id,
            'nombre_recurso' => TipoProyecto::class,
            'descripcion_recurso' => $TipoProyecto->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $TipoProyecto->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $TipoProyecto->delete();
    }
}
