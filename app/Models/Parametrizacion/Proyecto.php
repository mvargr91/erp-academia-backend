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

class Proyecto extends Model
{
    use HasFactory;

    protected $table = 'proyectos';

    protected $fillable = [
        'nombre',
        'codigo_proyecto',
        'ciudad_id',
        'coordenadas_ubicacion',
        'fecha_inicio_proyecto',
        'id_tipo_proyecto',
        'id_sector_proyecto',
        'potencia',
        'generacion_anual',
        'generacion_mensual',
        'nombre_comercializador',
        'nombre_operador',
        'id_tipo_inversion',
        'indicativo_plan_padrino',
        'valor_total_proyecto',
        'valor_inversion_proyecto',
        'id_vehiculo_inversion',
        'id_comunidad_energetica',
        'tipo_generacion',
        'costo_nivelado',
        'factor_planta',
        'id_ultima_etapa_proyecto',
        'anios_depreciacion',
        'observaciones',
        'estado_proyecto',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto)
    {
        $query = DB::table('proyectos')
            ->select(
                'proyectos.id',
                'proyectos.nombre',
                'proyectos.codigo_proyecto',
                'proyectos.ciudad_id',
                DB::raw('ST_AsText(proyectos.coordenadas_ubicacion) as coordenadas_ubicacion'),
                'proyectos.fecha_inicio_proyecto',
                'proyectos.id_tipo_proyecto',
                'proyectos.id_sector_proyecto',
                'proyectos.potencia',
                'proyectos.generacion_anual',
                'proyectos.generacion_mensual',
                'proyectos.nombre_comercializador',
                'proyectos.nombre_operador',
                'proyectos.id_tipo_inversion',
                'proyectos.indicativo_plan_padrino',
                'proyectos.valor_total_proyecto',
                'proyectos.valor_inversion_proyecto',
                'proyectos.id_vehiculo_inversion',
                'proyectos.id_comunidad_energetica',
                'proyectos.tipo_generacion',
                'proyectos.costo_nivelado',
                'proyectos.factor_planta',
                'proyectos.id_ultima_etapa_proyecto',
                'proyectos.anios_depreciacion',
                'proyectos.observaciones',
                'proyectos.estado_proyecto',
            )
            ->orderBy('nombre', 'asc');

        $data = $query->get();

        // "POINT(lng lat)" a "lat,lng"
        foreach ($data as $item) {
            if (!empty($item->coordenadas_ubicacion)) {

                // Ejemplo: POINT(-75.56359 6.25184)
                if (sscanf($item->coordenadas_ubicacion, 'POINT(%f %f)', $lng, $lat) === 2) {
                    $item->coordenadas_ubicacion = $lat . ',' . $lng; // -> "6.25184,-75.56359"
                }

            } else {
                $item->coordenadas_ubicacion = null;
            }
        }

        return $data;
    }


    public static function obtenerColeccionLigeraProyectosDisponibles($dto)
    {
       $query = DB::table('proyectos')
        ->leftJoin('inversiones_proyectos', 'inversiones_proyectos.id_proyecto', '=', 'proyectos.id')
        ->leftJoin('inversiones', function ($join) {
            $join->on('inversiones.id', '=', 'inversiones_proyectos.id_inversion')
                ->whereNotIn('inversiones.estado_inversion', ['ANU', 'PAG']);
        })
        ->select(
            'proyectos.id',
            'proyectos.nombre',
            'proyectos.codigo_proyecto',
            'proyectos.ciudad_id',
             DB::raw('ST_AsText(proyectos.coordenadas_ubicacion) as coordenadas_ubicacion'),
            'proyectos.fecha_inicio_proyecto',
            'proyectos.id_tipo_proyecto',
            'proyectos.id_sector_proyecto',
            'proyectos.potencia',
            'proyectos.generacion_anual',
            'proyectos.generacion_mensual',
            'proyectos.nombre_comercializador',
            'proyectos.nombre_operador',
            'proyectos.id_tipo_inversion',
            'proyectos.indicativo_plan_padrino',
            'proyectos.valor_total_proyecto',
            'proyectos.valor_inversion_proyecto',
            'proyectos.id_vehiculo_inversion',
            'proyectos.id_comunidad_energetica',
            'proyectos.tipo_generacion',
            'proyectos.costo_nivelado',
            'proyectos.factor_planta',
            'proyectos.id_ultima_etapa_proyecto',
            'proyectos.anios_depreciacion',
            'proyectos.observaciones',
            'proyectos.estado_proyecto',
            DB::raw('COALESCE(SUM(CASE WHEN inversiones.estado_inversion NOT IN ("ANU", "PAG") THEN inversiones_proyectos.valor_inversion_por_proyecto ELSE 0 END), 0) as valor_invertido'),
            DB::raw('(proyectos.valor_inversion_proyecto - COALESCE(SUM(CASE WHEN inversiones.estado_inversion NOT IN ("ANU", "PAG") THEN inversiones_proyectos.valor_inversion_por_proyecto ELSE 0 END), 0)) as saldo_por_invertir')
        )
        ->where('proyectos.estado_proyecto', 1)
        ->groupBy(
            'proyectos.id',
            'proyectos.nombre',
            'proyectos.codigo_proyecto',
            'proyectos.ciudad_id',
            'proyectos.coordenadas_ubicacion',
            'proyectos.fecha_inicio_proyecto',
            'proyectos.id_tipo_proyecto',
            'proyectos.id_sector_proyecto',
            'proyectos.potencia',
            'proyectos.generacion_anual',
            'proyectos.generacion_mensual',
            'proyectos.nombre_comercializador',
            'proyectos.nombre_operador',
            'proyectos.id_tipo_inversion',
            'proyectos.indicativo_plan_padrino',
            'proyectos.valor_total_proyecto',
            'proyectos.valor_inversion_proyecto',
            'proyectos.id_vehiculo_inversion',
            'proyectos.id_comunidad_energetica',
            'proyectos.tipo_generacion',
            'proyectos.costo_nivelado',
            'proyectos.factor_planta',
            'proyectos.id_ultima_etapa_proyecto',
            'proyectos.anios_depreciacion',
            'proyectos.observaciones',
            'proyectos.estado_proyecto'
        )
        ->havingRaw('(proyectos.valor_inversion_proyecto - COALESCE(SUM(CASE WHEN inversiones.estado_inversion NOT IN ("ANU", "PAG") THEN inversiones_proyectos.valor_inversion_por_proyecto ELSE 0 END), 0)) > 0')
        ->orderBy('nombre', 'asc');
    
        return $query->get();
    }
    

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('proyectos')
        ->join('ciudades', 'ciudades.id', 'proyectos.ciudad_id')
        ->join('tipos_proyectos', 'tipos_proyectos.id', 'proyectos.id_tipo_proyecto')
        ->join('sectores_proyectos', 'sectores_proyectos.id', 'proyectos.id_sector_proyecto')
        ->leftJoin('tipos_inversion', 'tipos_inversion.id', 'proyectos.id_tipo_inversion')
        ->leftJoin('vehiculos_inversion', 'vehiculos_inversion.id', '=', 'proyectos.id_vehiculo_inversion')
        ->leftJoin('comunidades_energeticas', 'comunidades_energeticas.id', '=', 'proyectos.id_comunidad_energetica')
            ->select(
            'proyectos.id',            
            'proyectos.nombre',
            'proyectos.codigo_proyecto',
            'proyectos.ciudad_id',
             DB::raw('ST_AsText(proyectos.coordenadas_ubicacion) as coordenadas_ubicacion'),
            'ciudades.nombre as ciudad', 
            'proyectos.fecha_inicio_proyecto',
            'proyectos.id_tipo_proyecto',
            'tipos_proyectos.nombre as tipo_proyecto',
            'proyectos.id_sector_proyecto',
            'sectores_proyectos.nombre as sector_proyecto',
            'proyectos.potencia',
            'proyectos.generacion_anual',
            'proyectos.generacion_mensual',
            'proyectos.nombre_comercializador',
            'proyectos.nombre_operador',
            'proyectos.id_tipo_inversion',
            'proyectos.indicativo_plan_padrino',
            'tipos_inversion.nombre as tipo_inversion',
            'proyectos.valor_total_proyecto',
            'proyectos.valor_inversion_proyecto',
            'proyectos.id_vehiculo_inversion',
            'vehiculos_inversion.nombre as vehiculo_inversion',
            'proyectos.id_comunidad_energetica',
            'comunidades_energeticas.nombre as comunidad_energetica',
            'proyectos.tipo_generacion',
            'proyectos.costo_nivelado',
            'proyectos.factor_planta',
            'proyectos.id_ultima_etapa_proyecto',
            'proyectos.anios_depreciacion',
            'proyectos.observaciones',
            'proyectos.estado_proyecto',
            'proyectos.usuario_creacion_id',
            'proyectos.usuario_creacion_nombre',
            'proyectos.usuario_modificacion_id',
            'proyectos.usuario_modificacion_nombre',
            'proyectos.created_at as fecha_creacion',
            'proyectos.updated_at as fecha_modificacion',
        );

        if(isset($dto['nombre'])){
            $query->where('proyectos.nombre', 'like', '%' . $dto['nombre'] . '%');
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre'){
                    $query->orderBy('proyectos.nombre', $value);
                }
                if($attribute == 'codigo_proyecto'){
                    $query->orderBy('proyectos.codigo_proyecto', $value);
                }
                if($attribute == 'ciudad'){
                    $query->orderBy('ciudades.nombre', $value);
                }  
                if($attribute == 'fecha_inicio_proyecto'){
                    $query->orderBy('proyectos.fecha_inicio_proyecto', $value);
                }  
                if($attribute == 'ciudad'){
                    $query->orderBy('proyectos.ciudad', $value);
                }  
                if($attribute == 'tipo_proyecto'){
                    $query->orderBy('tipos_proyectos.nombre', $value);
                }  
                if($attribute == 'sector_proyecto'){
                    $query->orderBy('sectores_proyectos.nombre', $value);
                }  
                if($attribute == 'potencia'){
                    $query->orderBy('proyectos.potencia', $value);
                }          
                if($attribute == 'tipo_inversion'){
                    $query->orderBy('tipos_inversion.nombre', $value);
                }     
                if($attribute == 'valor_total_proyecto'){
                    $query->orderBy('proyectos.valor_total_proyecto', $value);
                }
                if($attribute == 'indicativo_plan_padrino'){
                    $query->orderBy('proyectos.indicativo_plan_padrino', $value);
                }
                if($attribute == 'valor_inversion_proyecto'){
                    $query->orderBy('proyectos.valor_inversion_proyecto', $value);
                }
                if($attribute == 'observaciones'){
                    $query->orderBy('proyectos.observaciones', $value);
                }
                if($attribute == 'estado_proyecto'){
                    $query->orderBy('proyectos.estado_proyecto', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('proyectos.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('proyectos.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('proyectos.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('proyectos.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("proyectos.updated_at", "desc");
        }

        $proyectos = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $proyectos->items();

        foreach ($data as $item) {
            if (!empty($item->coordenadas_ubicacion)) {
                if (sscanf($item->coordenadas_ubicacion, 'POINT(%f %f)', $lng, $lat) === 2) {
                    $item->coordenadas_ubicacion = $lat . ',' . $lng;
                }
            } else {
                $item->coordenadas_ubicacion = null;
            }
        }
    
        return [
            'datos' => $data,
            'desde' => $proyectos->firstItem(),
            'hasta' => $proyectos->lastItem(),
            'por_pagina' => $proyectos->perPage(),
            'pagina_actual' => $proyectos->currentPage(),
            'ultima_pagina' => $proyectos->lastPage(),
            'total' => $proyectos->total(),
        ];
    }

    

    public static function cargar($id)
    {
        $proyectos = Proyecto::select(
            'proyectos.*',
            DB::raw('ST_AsText(coordenadas_ubicacion) as coordenadas_wkt')
        )->find($id);

        $coordenadas = null;

        if (!empty($proyectos->coordenadas_wkt)) {
            if (sscanf($proyectos->coordenadas_wkt, 'POINT(%f %f)', $lng, $lat) === 2) {
                $coordenadas = $lat . ',' . $lng;
            }
        }

        return [
            'id' => $proyectos->id,
            'nombre' => $proyectos->nombre,
            'codigo_proyecto' => $proyectos->codigo_proyecto,
            'digito_verificacion' => $proyectos->digito_verificacion,
            'ciudad_id' => $proyectos->ciudad_id,
            'coordenadas_ubicacion' => $coordenadas,
            'fecha_inicio_proyecto' => $proyectos->fecha_inicio_proyecto,
            'id_tipo_proyecto' => $proyectos->id_tipo_proyecto,
            'id_sector_proyecto' => $proyectos->id_sector_proyecto,
            'potencia' => $proyectos->potencia,
            'generacion_anual' => $proyectos->generacion_anual,
            'generacion_mensual' => $proyectos->generacion_mensual,
            'nombre_comercializador' => $proyectos->nombre_comercializador,
            'nombre_operador' => $proyectos->nombre_operador,
            'id_tipo_inversion' => $proyectos->id_tipo_inversion,
            'indicativo_plan_padrino' => $proyectos->indicativo_plan_padrino,
            'valor_total_proyecto' => $proyectos->valor_total_proyecto,
            'valor_inversion_proyecto' => $proyectos->valor_inversion_proyecto,
            'id_vehiculo_inversion' => $proyectos->id_vehiculo_inversion,
            'id_comunidad_energetica' => $proyectos->id_comunidad_energetica,
            'tipo_generacion' => $proyectos->tipo_generacion,
            'costo_nivelado' => $proyectos->costo_nivelado,
            'factor_planta' => $proyectos->factor_planta,
            'id_ultima_etapa_proyecto' => $proyectos->id_ultima_etapa_proyecto,
            'anios_depreciacion' => $proyectos->anios_depreciacion,
            'observaciones' => $proyectos->observaciones,
            'estado_proyecto' => $proyectos->estado_proyecto,
            'usuario_creacion_id' => $proyectos->usuario_creacion_id,
            'usuario_creacion_nombre' => $proyectos->usuario_creacion_nombre,
            'usuario_modificacion_id' => $proyectos->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $proyectos->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($proyectos->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($proyectos->updated_at))->format("Y-m-d H:i:s")
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

        // Cargar proyecto o crear nuevo
        $proyectos = isset($dto['id']) ? Proyecto::find($dto['id']) : new Proyecto();

        // Guardar copia ORIGINAL (sin campo binario POINT)
        $originalArray = $proyectos->toArray();
        unset($originalArray['coordenadas_ubicacion']);
        $proyectosOriginalJson = json_encode($originalArray, JSON_UNESCAPED_UNICODE);

        // Manejo especial de coordenadas
        $tieneCoordenadas = array_key_exists('coordenadas_ubicacion', $dto);
        $coordenadasTexto = $tieneCoordenadas ? $dto['coordenadas_ubicacion'] : null;

        $datosFill = $dto;
        unset($datosFill['coordenadas_ubicacion']);

        // Rellenar datos normales
        $proyectos->fill($datosFill);

        // Solo actualizar coordinates si vienen en la petición
        if ($tieneCoordenadas) {
            if (!empty($coordenadasTexto)) {
                $partes = explode(',', $coordenadasTexto);

                if (count($partes) >= 2) {
                    $lat = trim($partes[0]);
                    $lng = trim($partes[1]);

                    if (is_numeric($lat) && is_numeric($lng)) {
                        $proyectos->coordenadas_ubicacion = DB::raw("ST_GeomFromText('POINT($lng $lat)')");
                    } else {
                        $proyectos->coordenadas_ubicacion = null;
                    }
                } else {
                    $proyectos->coordenadas_ubicacion = null;
                }
            } else {
                // Viene vacío => borrar coordenadas
                $proyectos->coordenadas_ubicacion = null;
            }
        }
        // Si NO viene el campo, no se modifica

        // Guardar
        $guardado = $proyectos->save();

        if (!$guardado) {
            throw new Exception("Ocurrió un error al intentar guardar el proyecto.", $proyectos);
        }

        // Recargar modelo para auditoría
        $proyectoRefrescado = Proyecto::find($proyectos->id);

        $resultanteArray = $proyectoRefrescado->toArray();
        unset($resultanteArray['coordenadas_ubicacion']);
        $proyectosResultanteJson = json_encode($resultanteArray, JSON_UNESCAPED_UNICODE);

        // Guardar auditoría
        $auditoriaDto = [
            'id_recurso' => $proyectos->id,
            'nombre_recurso' => Proyecto::class,
            'descripcion_recurso' => $proyectos->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $proyectosOriginalJson : $proyectosResultanteJson,
            'recurso_resultante' => isset($dto['id']) ? $proyectosResultanteJson : null
        ];

        AuditoriaTabla::crear($auditoriaDto);

        return Proyecto::cargar($proyectos->id);
    }




    public static function eliminar($id)
    {
        // Connsultar el objeto
        $proyectos = Proyecto::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $proyectos->id,
            'nombre_recurso' => Proyecto::class,
            'descripcion_recurso' => $proyectos->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $proyectos->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $proyectos->delete();
    }

    public static function avanceProyectos($dto)
    {
        $query = DB::table('proyectos as p')
            ->join('actividades_por_proyecto as ap', function ($j) {
                $j->on('ap.id_proyecto', '=', 'p.id')
                ->where('ap.estado_actividad', '=', 'TER');
            })
            ->join('actividades_proyectos as a', 'a.id', '=', 'ap.id_actividad_proyecto')
            ->where('p.estado_proyecto', 1)
            ->groupBy('p.id', 'p.nombre')
            ->select([
                'p.id',
                DB::raw('p.nombre as nombre_proyecto'),
                DB::raw('LEAST(COALESCE(SUM(a.peso_porcentual_proyecto),0), 100) as avance_porcentual'),

                DB::raw("(SELECT a2.nombre
                        FROM actividades_por_proyecto ap2
                        JOIN actividades_proyectos a2 ON a2.id = ap2.id_actividad_proyecto
                        WHERE ap2.id = (
                            SELECT MAX(ap3.id)
                            FROM actividades_por_proyecto ap3
                            WHERE ap3.id_proyecto = p.id
                                AND ap3.estado_actividad = 'TER'
                        )
                        ) as ultima_actividad"),

                DB::raw("(SELECT e2.nombre
                        FROM actividades_por_proyecto ap2
                        LEFT JOIN etapas_proyectos e2 ON e2.id = ap2.id_etapa_proyecto
                        WHERE ap2.id = (
                            SELECT MAX(ap3.id)
                            FROM actividades_por_proyecto ap3
                            WHERE ap3.id_proyecto = p.id
                                AND ap3.estado_actividad = 'TER'
                        )
                        ) as ultima_etapa"),
            ]);

        if (!is_null($dto['tipo'])) {
            $query->where('p.id_tipo_proyecto', $dto['tipo']);
        }
        
        if (!empty($dto['etapa'])) {

            $query->whereRaw("
                (SELECT e2.nombre
                FROM actividades_por_proyecto ap2
                LEFT JOIN etapas_proyectos e2 ON e2.id = ap2.id_etapa_proyecto
                WHERE ap2.id = (
                SELECT MAX(ap3.id)
                FROM actividades_por_proyecto ap3
                WHERE ap3.id_proyecto = p.id
                    AND ap3.estado_actividad = 'TER'
                )
                ) = ?
            ", [ $dto['etapa'] ]);
        }


        return $query->get();
    }


}
