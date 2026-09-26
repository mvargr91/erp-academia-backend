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

class ConceptoProyecto extends Model
{
    use HasFactory;

    protected $table = 'conceptos_proyectos';

    protected $fillable = [
        'nombre',
        'secuencia',
        'id_tipo_proyecto',
        'indicativo_tipo_concepto',
        'indicativo_tipo_valor',
        'indicativo_tipo_linea',
        'indicativo_presentacion_cons',
        'indicativo_concepto_calculado',
        'indicativo_permite_copia',
        'indicativo_concepto_editable',
        'valor_mensual_concepto',
        'porcentaje',
        'parametro_referencia',
        'indicativo_valor_base',
        'indicativo_operador',
        'porcentaje_proy',
        'parametro_referencia_proy',
        'indicativo_valor_base_proy',
        'indicativo_operador_proy',
        'numero_anio_inicial_proy',
        'numero_anios_proy',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('conceptos_proyectos')
            ->select(
                'conceptos_proyectos.id',
                'conceptos_proyectos.nombre',
                'conceptos_proyectos.secuencia',
                'conceptos_proyectos.id_tipo_proyecto',
                'conceptos_proyectos.indicativo_tipo_concepto',
                'conceptos_proyectos.indicativo_tipo_valor',
                'conceptos_proyectos.indicativo_tipo_linea',
                'conceptos_proyectos.indicativo_presentacion_cons',
                'conceptos_proyectos.indicativo_concepto_calculado',
                'conceptos_proyectos.indicativo_permite_copia',
                'conceptos_proyectos.indicativo_concepto_editable',
                'conceptos_proyectos.valor_mensual_concepto',
                'conceptos_proyectos.porcentaje',
                'conceptos_proyectos.parametro_referencia',
                'conceptos_proyectos.indicativo_valor_base',
                'conceptos_proyectos.indicativo_operador',
                'conceptos_proyectos.porcentaje_proy',
                'conceptos_proyectos.parametro_referencia_proy',
                'conceptos_proyectos.indicativo_valor_base_proy',
                'conceptos_proyectos.indicativo_operador_proy',
                'conceptos_proyectos.numero_anio_inicial_proy',
                'conceptos_proyectos.numero_anios_proy',
                'conceptos_proyectos.estado',
                'conceptos_proyectos.usuario_creacion_id',
                'conceptos_proyectos.usuario_creacion_nombre',
                'conceptos_proyectos.usuario_modificacion_id',
                'conceptos_proyectos.usuario_modificacion_nombre',
            );

        $query->orderBy('nombre', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('conceptos_proyectos')
        ->leftJoin('tipos_proyectos', 'tipos_proyectos.id', 'conceptos_proyectos.id_tipo_proyecto')
            ->select(
            'conceptos_proyectos.id',
            'conceptos_proyectos.nombre',
            'conceptos_proyectos.secuencia',
            'conceptos_proyectos.id_tipo_proyecto',
            'tipos_proyectos.nombre as tipo_proyecto', 
            'conceptos_proyectos.indicativo_tipo_concepto',
            'conceptos_proyectos.indicativo_tipo_valor',
            'conceptos_proyectos.indicativo_tipo_linea',
            'conceptos_proyectos.indicativo_presentacion_cons',
            'conceptos_proyectos.indicativo_concepto_calculado',
            'conceptos_proyectos.indicativo_permite_copia',
            'conceptos_proyectos.indicativo_concepto_editable',
            'conceptos_proyectos.valor_mensual_concepto',
            'conceptos_proyectos.porcentaje',
            'conceptos_proyectos.parametro_referencia',
            'conceptos_proyectos.indicativo_valor_base',
            'conceptos_proyectos.indicativo_operador',
            'conceptos_proyectos.porcentaje_proy',
            'conceptos_proyectos.parametro_referencia_proy',
            'conceptos_proyectos.indicativo_valor_base_proy',
            'conceptos_proyectos.indicativo_operador_proy',
            'conceptos_proyectos.numero_anio_inicial_proy',
            'conceptos_proyectos.numero_anios_proy',
            'conceptos_proyectos.estado',
            'conceptos_proyectos.usuario_creacion_id',
            'conceptos_proyectos.usuario_creacion_nombre',
            'conceptos_proyectos.usuario_modificacion_id',
            'conceptos_proyectos.usuario_modificacion_nombre',
        );

        if(isset($dto['nombre'])){
            $query->where('conceptos_proyectos.nombre', 'like', '%' . $dto['nombre'] . '%');
        }

        if(isset($dto['tipoProyecto'])){
            $query->where('etapas_proyectos.id_tipo_proyecto', $dto['tipoProyecto']);
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'secuencia'){
                    $query->orderBy('conceptos_proyectos.secuencia', $value);
                }
                if($attribute == 'id_tipo_proyecto'){
                    $query->orderBy('conceptos_proyectos.id_tipo_proyecto', $value);
                }
                if($attribute == 'tipo_proyecto'){
                    $query->orderBy('tipos_proyectos.nombre', $value);
                } 
                if($attribute == 'nombre'){
                    $query->orderBy('conceptos_proyectos.nombre', $value);
                }
                if($attribute == 'indicativo_tipo_concepto'){
                    $query->orderBy('conceptos_proyectos.indicativo_tipo_concepto', $value);
                }
                if($attribute == 'indicativo_tipo_valor'){
                    $query->orderBy('conceptos_proyectos.indicativo_tipo_valor', $value);
                }
                if($attribute == 'indicativo_tipo_linea'){
                    $query->orderBy('conceptos_proyectos.indicativo_tipo_linea', $value);
                }
                if($attribute == 'indicativo_presentacion_cons'){
                    $query->orderBy('conceptos_proyectos.indicativo_presentacion_cons', $value);
                }
                if($attribute == 'indicativo_concepto_calculado'){
                    $query->orderBy('conceptos_proyectos.indicativo_concepto_calculado', $value);
                }
                if($attribute == 'indicativo_concepto_editable'){
                    $query->orderBy('conceptos_proyectos.indicativo_concepto_editable', $value);
                }
                if($attribute == 'valor_mensual_concepto'){
                    $query->orderBy('conceptos_proyectos.valor_mensual_concepto', $value);
                }
                if($attribute == 'porcentaje'){
                    $query->orderBy('conceptos_proyectos.porcentaje', $value);
                }
                if($attribute == 'parametro_referencia'){
                    $query->orderBy('conceptos_proyectos.parametro_referencia', $value);
                }
                if($attribute == 'indicativo_valor_base'){
                    $query->orderBy('conceptos_proyectos.indicativo_valor_base', $value);
                }
                if($attribute == 'indicativo_operador'){
                    $query->orderBy('conceptos_proyectos.indicativo_operador', $value);
                }
                if($attribute == 'porcentaje_proy'){
                    $query->orderBy('conceptos_proyectos.porcentaje_proy', $value);
                }
                if($attribute == 'parametro_referencia_proy'){
                    $query->orderBy('conceptos_proyectos.parametro_referencia_proy', $value);
                }
                if($attribute == 'indicativo_valor_base_proy'){
                    $query->orderBy('conceptos_proyectos.indicativo_valor_base_proy', $value);
                }
                if($attribute == 'indicativo_operador_proy'){
                    $query->orderBy('conceptos_proyectos.indicativo_operador_proy', $value);
                }
                if($attribute == 'numero_anio_inicial_proy'){
                    $query->orderBy('conceptos_proyectos.numero_anio_inicial_proy', $value);
                }
                if($attribute == 'numero_anios_proy'){
                    $query->orderBy('conceptos_proyectos.numero_anios_proy', $value);
                }
                if($attribute == 'estado'){
                    $query->orderBy('conceptos_proyectos.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('conceptos_proyectos.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('conceptos_proyectos.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('conceptos_proyectos.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('conceptos_proyectos.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("conceptos_proyectos.id_tipo_proyecto", "desc");
            $query->orderBy("conceptos_proyectos.secuencia", "asc");
        }

        $conceptos_proyectos = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $conceptos_proyectos->items();
    
        return [
            'datos' => $data,
            'desde' => $conceptos_proyectos->firstItem(),
            'hasta' => $conceptos_proyectos->lastItem(),
            'por_pagina' => $conceptos_proyectos->perPage(),
            'pagina_actual' => $conceptos_proyectos->currentPage(),
            'ultima_pagina' => $conceptos_proyectos->lastPage(),
            'total' => $conceptos_proyectos->total(),
        ];
    }

    public static function cargar($id)
    {
        $conceptos_proyectos = ConceptoProyecto::find($id);

        return [
            'id' => $conceptos_proyectos->id,
            'nombre' => $conceptos_proyectos->nombre,
            'secuencia' => $conceptos_proyectos->secuencia,
            'id_tipo_proyecto' => $conceptos_proyectos->id_tipo_proyecto,
            'indicativo_tipo_concepto' => $conceptos_proyectos->indicativo_tipo_concepto,
            'indicativo_tipo_valor' => $conceptos_proyectos->indicativo_tipo_valor,
            'indicativo_tipo_linea' => $conceptos_proyectos->indicativo_tipo_linea,
            'indicativo_presentacion_cons' => $conceptos_proyectos->indicativo_presentacion_cons,
            'indicativo_concepto_calculado' => $conceptos_proyectos->indicativo_concepto_calculado,
            'indicativo_permite_copia' => $conceptos_proyectos->indicativo_permite_copia,
            'indicativo_concepto_editable' => $conceptos_proyectos->indicativo_concepto_editable,
            'valor_mensual_concepto' => $conceptos_proyectos->valor_mensual_concepto,
            'porcentaje' => $conceptos_proyectos->porcentaje,
            'parametro_referencia' => $conceptos_proyectos->parametro_referencia,
            'indicativo_valor_base' => $conceptos_proyectos->indicativo_valor_base,
            'indicativo_operador' => $conceptos_proyectos->indicativo_operador,
            'porcentaje_proy' => $conceptos_proyectos->porcentaje_proy,
            'parametro_referencia_proy' => $conceptos_proyectos->parametro_referencia_proy,
            'indicativo_valor_base_proy' => $conceptos_proyectos->indicativo_valor_base_proy,
            'indicativo_operador_proy' => $conceptos_proyectos->indicativo_operador_proy,
            'numero_anio_inicial_proy' => $conceptos_proyectos->numero_anio_inicial_proy,
            'numero_anios_proy' => $conceptos_proyectos->numero_anios_proy,
            'estado' => $conceptos_proyectos->estado,
            'usuario_creacion_id' => $conceptos_proyectos->usuario_creacion_id,
            'usuario_creacion_nombre' => $conceptos_proyectos->usuario_creacion_nombre,
            'usuario_modificacion_id' => $conceptos_proyectos->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $conceptos_proyectos->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($conceptos_proyectos->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($conceptos_proyectos->updated_at))->format("Y-m-d H:i:s")
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
        $conceptos_proyectos = isset($dto['id']) ? ConceptoProyecto::find($dto['id']) : new ConceptoProyecto();

        // Guardar objeto original para auditoria
        $inversionistas_contactosOriginal = $conceptos_proyectos->toJson();

        $conceptos_proyectos->fill($dto);
        $guardado = $conceptos_proyectos->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la compañia.", $conceptos_proyectos);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $conceptos_proyectos->id,
            'nombre_recurso' => ConceptoProyecto::class,
            'descripcion_recurso' => $conceptos_proyectos->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $inversionistas_contactosOriginal : $conceptos_proyectos->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $conceptos_proyectos->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return ConceptoProyecto::cargar($conceptos_proyectos->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $conceptos_proyectos = ConceptoProyecto::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $conceptos_proyectos->id,
            'nombre_recurso' => ConceptoProyecto::class,
            'descripcion_recurso' => $conceptos_proyectos->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $conceptos_proyectos->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $conceptos_proyectos->delete();
    }
}
