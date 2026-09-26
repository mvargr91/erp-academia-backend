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

class DesembolsoProyecto extends Model
{
    use HasFactory;

    protected $table = 'proyectos_desembolsos';

    protected $fillable = [
        'id_proyecto',
        'fecha_desembolso',
        'concepto_desembolso',
        'proveedor_desembolso',
        'valor_desembolso',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('proyectos_desembolsos')
            ->select(
                'proyectos_desembolsos.id',
                'proyectos_desembolsos.id_proyecto',
                'proyectos_desembolsos.fecha_desembolso',
                'proyectos_desembolsos.concepto_desembolso',
                'proyectos_desembolsos.proveedor_desembolso',
                'proyectos_desembolsos.valor_desembolso',
            )->where('proyectos_desembolsos.id_proyecto', $dto['id_proyecto']);
        $query->orderBy('fecha_desembolso', 'desc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('proyectos_desembolsos')
            ->select(
           'proyectos_desembolsos.id',
            'proyectos_desembolsos.id_proyecto',
            'proyectos_desembolsos.fecha_desembolso',
            'proyectos_desembolsos.concepto_desembolso',
            'proyectos_desembolsos.proveedor_desembolso',
            'proyectos_desembolsos.valor_desembolso',
            'proyectos_desembolsos.usuario_creacion_id',
            'proyectos_desembolsos.usuario_creacion_nombre',
            'proyectos_desembolsos.usuario_modificacion_id',
            'proyectos_desembolsos.usuario_modificacion_nombre',
            'proyectos_desembolsos.created_at as fecha_creacion',
            'proyectos_desembolsos.updated_at as fecha_modificacion',
        )->where('proyectos_desembolsos.id_proyecto', $dto['id_proyecto']);
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'fecha_desembolso'){
                    $query->orderBy('proyectos_desembolsos.fecha_desembolso', $value);
                }              
                if($attribute == 'concepto_desembolso'){
                    $query->orderBy('proyectos_desembolsos.concepto_desembolso', $value);
                }
                if($attribute == 'proveedor_desembolso'){
                    $query->orderBy('proyectos_desembolsos.proveedor_desembolso', $value);
                }
                if($attribute == 'valor_desembolso'){
                    $query->orderBy('proyectos_desembolsos.valor_desembolso', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('proyectos_desembolsos.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('proyectos_desembolsos.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('proyectos_desembolsos.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('proyectos_desembolsos.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("proyectos_desembolsos.fecha_desembolso", "asc");
        }

        $DesembolsoProyecto = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $DesembolsoProyecto->items();
    
        return [
            'datos' => $data,
            'desde' => $DesembolsoProyecto->firstItem(),
            'hasta' => $DesembolsoProyecto->lastItem(),
            'por_pagina' => $DesembolsoProyecto->perPage(),
            'pagina_actual' => $DesembolsoProyecto->currentPage(),
            'ultima_pagina' => $DesembolsoProyecto->lastPage(),
            'total' => $DesembolsoProyecto->total(),
        ];
    }

    public static function cargar($id)
    {
        $DesembolsoProyecto = DesembolsoProyecto::find($id);

        return [
            'id' => $DesembolsoProyecto->id,
            'id_proyecto' => $DesembolsoProyecto->id_proyecto,
            'fecha_desembolso' => $DesembolsoProyecto->fecha_desembolso,
            'concepto_desembolso' => $DesembolsoProyecto->concepto_desembolso,
            'proveedor_desembolso' => $DesembolsoProyecto->proveedor_desembolso,
            'valor_desembolso' => $DesembolsoProyecto->valor_desembolso,
            'usuario_creacion_id' => $DesembolsoProyecto->usuario_creacion_id,
            'usuario_creacion_nombre' => $DesembolsoProyecto->usuario_creacion_nombre,
            'usuario_modificacion_id' => $DesembolsoProyecto->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $DesembolsoProyecto->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($DesembolsoProyecto->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($DesembolsoProyecto->updated_at))->format("Y-m-d H:i:s")
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
        $DesembolsoProyecto = isset($dto['id']) ? DesembolsoProyecto::find($dto['id']) : new DesembolsoProyecto();

        // Guardar objeto original para auditoria
        $DesembolsoProyectoOriginal = $DesembolsoProyecto->toJson();

        $DesembolsoProyecto->fill($dto);
        $guardado = $DesembolsoProyecto->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la compañia.", $DesembolsoProyecto);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $DesembolsoProyecto->id,
            'nombre_recurso' => DesembolsoProyecto::class,
            'descripcion_recurso' => $DesembolsoProyecto->concepto_desembolso,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $DesembolsoProyectoOriginal : $DesembolsoProyecto->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $DesembolsoProyecto->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return DesembolsoProyecto::cargar($DesembolsoProyecto->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $DesembolsoProyecto = DesembolsoProyecto::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $DesembolsoProyecto->id,
            'nombre_recurso' => DesembolsoProyecto::class,
            'descripcion_recurso' => $DesembolsoProyecto->concepto_desembolso,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $DesembolsoProyecto->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $DesembolsoProyecto->delete();
    }
}
