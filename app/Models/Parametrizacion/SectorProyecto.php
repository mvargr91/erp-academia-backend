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

class SectorProyecto extends Model
{
    use HasFactory;

    protected $table = 'sectores_proyectos';

    protected $fillable = [
        'nombre',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('sectores_proyectos')
            ->select(
                'sectores_proyectos.id',
                'sectores_proyectos.nombre',
                'sectores_proyectos.estado',
            );
        $query->orderBy('nombre', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('sectores_proyectos')
            ->select(
            'sectores_proyectos.id',            
            'sectores_proyectos.nombre',
            'sectores_proyectos.estado',
            'sectores_proyectos.usuario_creacion_id',
            'sectores_proyectos.usuario_creacion_nombre',
            'sectores_proyectos.usuario_modificacion_id',
            'sectores_proyectos.usuario_modificacion_nombre',
        );

        if(isset($dto['nombre'])){
            $query->where('sectores_proyectos.nombre', 'like', '%' . $dto['nombre'] . '%');
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre'){
                    $query->orderBy('sectores_proyectos.nombre', $value);
                }
                if($attribute == 'estado'){
                    $query->orderBy('sectores_proyectos.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('sectores_proyectos.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('sectores_proyectos.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('sectores_proyectos.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('sectores_proyectos.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("sectores_proyectos.updated_at", "desc");
        }

        $SectorProyecto = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $SectorProyecto->items();
    
        return [
            'datos' => $data,
            'desde' => $SectorProyecto->firstItem(),
            'hasta' => $SectorProyecto->lastItem(),
            'por_pagina' => $SectorProyecto->perPage(),
            'pagina_actual' => $SectorProyecto->currentPage(),
            'ultima_pagina' => $SectorProyecto->lastPage(),
            'total' => $SectorProyecto->total(),
        ];
    }

    public static function cargar($id)
    {
        $SectorProyecto = SectorProyecto::find($id);

        return [
            'id' => $SectorProyecto->id,
            'nombre' => $SectorProyecto->nombre,
            'estado' => $SectorProyecto->estado,
            'usuario_creacion_id' => $SectorProyecto->usuario_creacion_id,
            'usuario_creacion_nombre' => $SectorProyecto->usuario_creacion_nombre,
            'usuario_modificacion_id' => $SectorProyecto->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $SectorProyecto->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($SectorProyecto->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($SectorProyecto->updated_at))->format("Y-m-d H:i:s")
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
        $SectorProyecto = isset($dto['id']) ? SectorProyecto::find($dto['id']) : new SectorProyecto();

        // Guardar objeto original para auditoria
        $SectorProyectoOriginal = $SectorProyecto->toJson();

        $SectorProyecto->fill($dto);
        $guardado = $SectorProyecto->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la compañia.", $SectorProyecto);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $SectorProyecto->id,
            'nombre_recurso' => SectorProyecto::class,
            'descripcion_recurso' => $SectorProyecto->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $SectorProyectoOriginal : $SectorProyecto->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $SectorProyecto->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return SectorProyecto::cargar($SectorProyecto->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $SectorProyecto = SectorProyecto::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $SectorProyecto->id,
            'nombre_recurso' => SectorProyecto::class,
            'descripcion_recurso' => $SectorProyecto->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $SectorProyecto->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $SectorProyecto->delete();
    }
}
