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

class ProyectoSimulacionConcepto extends Model
{
    use HasFactory;

    protected $table = 'proyectos_simulaciones_conceptos';

    protected $fillable = [
        'id_proyecto',
        'anio',
        'secuencia',
        'id_concepto_proyecto',
        'valor_concepto_proyecto',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('proyectos_simulaciones_conceptos')
        ->where('proyectos_simulaciones_conceptos.id_proyecto', $dto['id'])
            ->select(
                'proyectos_simulaciones_conceptos.id',
                'proyectos_simulaciones_conceptos.id_proyecto',
                'proyectos_simulaciones_conceptos.anio',
                'proyectos_simulaciones_conceptos.secuencia',
                'proyectos_simulaciones_conceptos.id_concepto_proyecto',
                'proyectos_simulaciones_conceptos.valor_concepto_proyecto',
                'proyectos_simulaciones_conceptos.usuario_creacion_id',
                'proyectos_simulaciones_conceptos.usuario_creacion_nombre',
                'proyectos_simulaciones_conceptos.usuario_modificacion_id',
                'proyectos_simulaciones_conceptos.usuario_modificacion_nombre',
            );

        $query->orderBy('anio', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('proyectos_simulaciones_conceptos')
        ->where('proyectos_simulaciones_conceptos.id_proyecto', $dto['id'])
            ->select(
            'proyectos_simulaciones_conceptos.id',
            'proyectos_simulaciones_conceptos.id_proyecto',
            'proyectos_simulaciones_conceptos.anio',
            'proyectos_simulaciones_conceptos.secuencia',
            'proyectos_simulaciones_conceptos.id_concepto_proyecto',
            'proyectos_simulaciones_conceptos.valor_concepto_proyecto',
            'proyectos_simulaciones_conceptos.usuario_creacion_id',
            'proyectos_simulaciones_conceptos.usuario_creacion_nombre',
            'proyectos_simulaciones_conceptos.usuario_modificacion_id',
            'proyectos_simulaciones_conceptos.usuario_modificacion_nombre',
            'proyectos_simulaciones_conceptos.created_at as fecha_creacion',
            'proyectos_simulaciones_conceptos.updated_at as fecha_modificacion' ,
        );

        if(isset($dto['anio'])){
            $query->where('proyectos_simulaciones_conceptos.anio', 'like', '%' . $dto['anio'] . '%');
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'anio'){
                    $query->orderBy('proyectos_simulaciones_conceptos.anio', $value);
                }
                if($attribute == 'precio_bolsa'){
                    $query->orderBy('proyectos_simulaciones_conceptos.precio_bolsa', $value);
                }
                if($attribute == 'precio_mercado'){
                    $query->orderBy('proyectos_simulaciones_conceptos.precio_mercado', $value);
                }
                if($attribute == 'precio_comunidad'){
                    $query->orderBy('proyectos_simulaciones_conceptos.precio_comunidad', $value);
                }
                if($attribute == 'precio_com_ppa'){
                    $query->orderBy('proyectos_simulaciones_conceptos.precio_com_ppa', $value);
                }
                if($attribute == 'precio_com_representado'){
                    $query->orderBy('proyectos_simulaciones_conceptos.precio_com_representado', $value);
                }
                if($attribute == 'estado'){
                    $query->orderBy('proyectos_simulaciones_conceptos.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('proyectos_simulaciones_conceptos.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('proyectos_simulaciones_conceptos.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('proyectos_simulaciones_conceptos.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('proyectos_simulaciones_conceptos.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("proyectos_simulaciones_conceptos.updated_at", "desc");
        }

        $proyectos_simulaciones_conceptos = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $proyectos_simulaciones_conceptos->items();
    
        return [
            'datos' => $data,
            'desde' => $proyectos_simulaciones_conceptos->firstItem(),
            'hasta' => $proyectos_simulaciones_conceptos->lastItem(),
            'por_pagina' => $proyectos_simulaciones_conceptos->perPage(),
            'pagina_actual' => $proyectos_simulaciones_conceptos->currentPage(),
            'ultima_pagina' => $proyectos_simulaciones_conceptos->lastPage(),
            'total' => $proyectos_simulaciones_conceptos->total(),
        ];
    }

    public static function cargar($id)
    {
        $proyectos_simulaciones_conceptos = ProyectoSimulacionConcepto::find($id);

        return [
            'id' => $proyectos_simulaciones_conceptos->id,
            'id_proyecto' => $proyectos_simulaciones_conceptos->id_proyecto,
            'anio' => $proyectos_simulaciones_conceptos->anio,
            'secuencia' => $proyectos_simulaciones_conceptos->secuencia,
            'id_concepto_proyecto' => $proyectos_simulaciones_conceptos->id_concepto_proyecto,
            'valor_concepto_proyecto' => $proyectos_simulaciones_conceptos->valor_concepto_proyecto,
            'usuario_creacion_id' => $proyectos_simulaciones_conceptos->usuario_creacion_id,
            'usuario_creacion_nombre' => $proyectos_simulaciones_conceptos->usuario_creacion_nombre,
            'usuario_modificacion_id' => $proyectos_simulaciones_conceptos->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $proyectos_simulaciones_conceptos->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($proyectos_simulaciones_conceptos->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($proyectos_simulaciones_conceptos->updated_at))->format("Y-m-d H:i:s")
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
        $proyectos_simulaciones_conceptos = isset($dto['id']) ? ProyectoSimulacionConcepto::find($dto['id']) : new ProyectoSimulacionConcepto();

        // Guardar objeto original para auditoria
        $proyectos_simulaciones_conceptosOriginal = $proyectos_simulaciones_conceptos->toJson();

        $proyectos_simulaciones_conceptos->fill($dto);
        $guardado = $proyectos_simulaciones_conceptos->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la condición plazo.", $proyectos_simulaciones_conceptos);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $proyectos_simulaciones_conceptos->id,
            'nombre_recurso' => ProyectoSimulacionConcepto::class,
            'descripcion_recurso' => $proyectos_simulaciones_conceptos->anio,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $proyectos_simulaciones_conceptosOriginal : $proyectos_simulaciones_conceptos->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $proyectos_simulaciones_conceptos->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return ProyectoSimulacionConcepto::cargar($proyectos_simulaciones_conceptos->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $proyectos_simulaciones_conceptos = ProyectoSimulacionConcepto::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $proyectos_simulaciones_conceptos->id,
            'nombre_recurso' => ProyectoSimulacionConcepto::class,
            'descripcion_recurso' => $proyectos_simulaciones_conceptos->anio,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $proyectos_simulaciones_conceptos->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $proyectos_simulaciones_conceptos->delete();
    }
}
