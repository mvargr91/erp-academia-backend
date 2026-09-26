<?php

namespace App\Models\Inversiones;

use Exception;
use Carbon\Carbon;
use App\Enum\AccionAuditoriaEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\Inversiones\Inversiones;
use App\Models\Seguridad\AuditoriaTabla;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PlanDetallado extends Model
{
    use HasFactory;

    protected $table = 'inversiones_plan_detallado';

    protected $fillable = [
        'id_inversion',
        'id_proyecto',
        'id_inversionista',
        'id_gestor',
        'fecha_vencimiento',
        'fecha_pago',
        'tipo_concepto',
        'valor_concepto',
        'valor_ret_fuente',
        'porcentaje_ret_fuente',
        'estado_plan',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('inversiones_plan_detallado')
            ->select(
                'inversiones_plan_detallado.id',
                'inversiones_plan_detallado.id_inversion',
                'inversiones_plan_detallado.id_proyecto',
                'inversiones_plan_detallado.id_inversionista',
                'inversiones_plan_detallado.id_gestor',
                'inversiones_plan_detallado.fecha_vencimiento',
                'inversiones_plan_detallado.fecha_pago',
                'inversiones_plan_detallado.tipo_concepto',
                'inversiones_plan_detallado.valor_concepto',
                'inversiones_plan_detallado.valor_ret_fuente',
                'inversiones_plan_detallado.porcentaje_ret_fuente',
            );
        $query->orderBy('id_proyecto', 'asc');
        return $query->get();
    }


    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('inversiones_plan_detallado')
        ->join('inversiones', 'inversiones.id', 'inversiones_plan_detallado.id_inversion')
        ->join('proyectos', 'proyectos.id', 'inversiones_plan_detallado.id_proyecto')
        ->join('inversionistas', 'inversionistas.id', 'inversiones_plan_detallado.id_inversionista')
        ->leftJoin('gestores', 'gestores.id', 'inversiones_plan_detallado.id_gestor')
            ->select(
            'inversiones_plan_detallado.id',            
            'inversiones_plan_detallado.id_inversion',
            'inversiones.valor_inversion as valor_inversion',
            'inversiones.fecha_inversion as fecha_inversion',
            'inversiones_plan_detallado.id_proyecto',
            'proyectos.nombre as nombre_proyecto',
            'proyectos.codigo_proyecto as codigo_proyecto',
            'inversiones_plan_detallado.id_inversionista',
            'inversionistas.nombre as inversionista',
            'inversiones_plan_detallado.id_gestor',
            'gestores.nombre as gestor',
             DB::raw("CASE WHEN inversiones_plan_detallado.tipo_concepto = 'C' THEN gestores.nombre ELSE inversionistas.nombre END AS inversionista_gestor"),
             DB::raw("CASE WHEN inversiones_plan_detallado.tipo_concepto = 'C' THEN gestores.id ELSE inversionistas.id END AS id_cliente"),
            'inversiones_plan_detallado.fecha_vencimiento',
            'inversiones_plan_detallado.fecha_pago',
            'inversiones_plan_detallado.tipo_concepto',
            'inversiones_plan_detallado.valor_concepto',
            'inversiones_plan_detallado.valor_ret_fuente',
            'inversiones_plan_detallado.porcentaje_ret_fuente',
            DB::raw('(inversiones_plan_detallado.valor_concepto - inversiones_plan_detallado.valor_ret_fuente) as valor_calculado'),
            'inversiones_plan_detallado.estado_plan',
            'inversiones_plan_detallado.usuario_creacion_id',
            'inversiones_plan_detallado.usuario_creacion_nombre',
            'inversiones_plan_detallado.usuario_modificacion_id',
            'inversiones_plan_detallado.usuario_modificacion_nombre',
        );

        if(isset($dto['id_inversion'])){
            $query->where('inversiones_plan_detallado.id_inversion', $dto['id_inversion'] );
        }

        if(isset($dto['id_proyecto'])){
            $query->where('inversiones_plan_detallado.id_proyecto', $dto['id_proyecto'] );
        }

        if(isset($dto['id_inversionista'])){
            $query->where('inversiones_plan_detallado.id_inversionista', $dto['id_inversionista'] );
        }

        if(isset($dto['estado'])){
            $query->where('inversiones_plan_detallado.estado_plan', $dto['estado'] );
        }

        if(isset($dto['tipo'])){
            $query->where('inversiones_plan_detallado.tipo_concepto', $dto['tipo'] );
        }

        if(isset($dto['fechaDesde'])){
            $query->whereDate('inversiones_plan_detallado.fecha_vencimiento', '>=', $dto['fechaDesde']);
        }

        if(isset($dto['fechaHasta'])){
            $query->whereDate('inversiones_plan_detallado.fecha_vencimiento', '<=', $dto['fechaHasta']);
        }

        if(isset($dto['fechaPagoDesde'])){
            $query->whereDate('inversiones_plan_detallado.fecha_pago', '>=', $dto['fechaPagoDesde']);
        }

        if(isset($dto['fechaPagoHasta'])){
            $query->whereDate('inversiones_plan_detallado.fecha_pago', '<=', $dto['fechaPagoHasta']);
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'inversionista_gestor'){
                    $query->orderByRaw("CASE WHEN inversiones_plan_detallado.tipo_concepto = 'C' THEN gestores.nombre ELSE inversionistas.nombre END $value");
                }
                 if($attribute == 'id_inversion'){
                    $query->orderBy('inversiones_plan_detallado.id_inversion', $value);
                }
                if($attribute == 'codigo_proyecto'){
                    $query->orderBy('proyectos.codigo_proyecto', $value);
                }
                if($attribute == 'fecha_vencimiento'){
                    $query->orderBy('inversiones_plan_detallado.fecha_vencimiento', $value);
                } 
                if($attribute == 'fecha_pago'){
                    $query->orderBy('inversiones_plan_detallado.fecha_pago', $value);
                } 
                if($attribute == 'tipo_concepto'){
                    $query->orderBy('inversiones_plan_detallado.tipo_concepto', $value);
                }
                if($attribute == 'valor_concepto'){
                    $query->orderBy('inversiones_plan_detallado.valor_concepto', $value);
                }  
                if($attribute == 'valor_ret_fuente'){
                    $query->orderBy('inversiones_plan_detallado.valor_ret_fuente', $value);
                }  
                if($attribute == 'porcentaje_ret_fuente'){
                    $query->orderBy('inversiones_plan_detallado.porcentaje_ret_fuente', $value);
                }  
                if($attribute == 'estado_plan'){
                    $query->orderBy('inversiones_plan_detallado.estado_plan', $value);
                }              
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('inversiones_plan_detallado.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('inversiones_plan_detallado.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('inversiones_plan_detallado.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('inversiones_plan_detallado.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("inversiones_plan_detallado.tipo_concepto", "asc");
            $query->orderBy("inversiones_plan_detallado.fecha_vencimiento", "asc");
        }

        $inversiones_plan_detallado = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $inversiones_plan_detallado->items();
    
        return [
            'datos' => $data,
            'desde' => $inversiones_plan_detallado->firstItem(),
            'hasta' => $inversiones_plan_detallado->lastItem(),
            'por_pagina' => $inversiones_plan_detallado->perPage(),
            'pagina_actual' => $inversiones_plan_detallado->currentPage(),
            'ultima_pagina' => $inversiones_plan_detallado->lastPage(),
            'total' => $inversiones_plan_detallado->total(),
        ];
    }

    public static function obtenerColeccionProyecto($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('inversiones_plan_detallado')
        ->join('inversiones', 'inversiones.id', 'inversiones_plan_detallado.id_inversion')
        ->join('proyectos', 'proyectos.id', 'inversiones_plan_detallado.id_proyecto')
        ->join('inversiones_proyectos', function ($j) {
            $j->on('inversiones_proyectos.id_inversion', '=', 'inversiones_plan_detallado.id_inversion')
            ->on('inversiones_proyectos.id_proyecto',  '=', 'inversiones_plan_detallado.id_proyecto');
        })
        ->join('inversionistas', 'inversionistas.id', 'inversiones_plan_detallado.id_inversionista')
        ->leftJoin('gestores', 'gestores.id', 'inversiones_plan_detallado.id_gestor')
            ->select(
            'inversiones_plan_detallado.id',            
            'inversiones_plan_detallado.id_inversion',
            'inversiones.valor_inversion as valor_inversion',
            'inversiones.fecha_inversion as fecha_inversion',
            'inversiones_plan_detallado.id_proyecto',
            'proyectos.nombre as nombre_proyecto',
            'proyectos.codigo_proyecto as codigo_proyecto',
            'inversiones_plan_detallado.id_inversionista',
            'inversionistas.nombre as inversionista',
            'inversiones_plan_detallado.id_gestor',
            'gestores.nombre as gestor',
            'inversiones_plan_detallado.fecha_vencimiento',
            'inversiones_plan_detallado.fecha_pago',
            'inversiones_plan_detallado.tipo_concepto',
            'inversiones_plan_detallado.valor_concepto',
            'inversiones_plan_detallado.valor_ret_fuente',
            'inversiones_plan_detallado.porcentaje_ret_fuente',
            DB::raw('(inversiones_plan_detallado.valor_concepto - inversiones_plan_detallado.valor_ret_fuente) as valor_calculado'),
            'inversiones_proyectos.valor_inversion_por_proyecto',
            'inversiones_plan_detallado.estado_plan',
            'inversiones_plan_detallado.usuario_creacion_id',
            'inversiones_plan_detallado.usuario_creacion_nombre',
            'inversiones_plan_detallado.usuario_modificacion_id',
            'inversiones_plan_detallado.usuario_modificacion_nombre',
        )
        ->where('inversiones_plan_detallado.id_inversion',  $dto['id_inversion'])
        ->where('inversiones_plan_detallado.id_proyecto', $dto['id_proyecto'] );

      
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'codigo_proyecto'){
                    $query->orderBy('proyectos.codigo_proyecto', $value);
                }
                if($attribute == 'fecha_vencimiento'){
                    $query->orderBy('inversiones_plan_detallado.fecha_vencimiento', $value);
                } 
                if($attribute == 'fecha_pago'){
                    $query->orderBy('inversiones_plan_detallado.fecha_pago', $value);
                } 
                if($attribute == 'tipo_concepto'){
                    $query->orderBy('inversiones_plan_detallado.tipo_concepto', $value);
                }
                if($attribute == 'valor_concepto'){
                    $query->orderBy('inversiones_plan_detallado.valor_concepto', $value);
                }  
                if($attribute == 'valor_ret_fuente'){
                    $query->orderBy('inversiones_plan_detallado.valor_ret_fuente', $value);
                }  
                if($attribute == 'porcentaje_ret_fuente'){
                    $query->orderBy('inversiones_plan_detallado.porcentaje_ret_fuente', $value);
                }  
                if($attribute == 'estado_plan'){
                    $query->orderBy('inversiones_plan_detallado.estado_plan', $value);
                }              
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('inversiones_plan_detallado.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('inversiones_plan_detallado.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('inversiones_plan_detallado.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('inversiones_plan_detallado.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("inversiones_plan_detallado.tipo_concepto", "asc");
            $query->orderBy("inversiones_plan_detallado.fecha_vencimiento", "asc");
        }

        $inversiones_plan_detallado = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $inversiones_plan_detallado->items();
    
        return [
            'datos' => $data,
            'desde' => $inversiones_plan_detallado->firstItem(),
            'hasta' => $inversiones_plan_detallado->lastItem(),
            'por_pagina' => $inversiones_plan_detallado->perPage(),
            'pagina_actual' => $inversiones_plan_detallado->currentPage(),
            'ultima_pagina' => $inversiones_plan_detallado->lastPage(),
            'total' => $inversiones_plan_detallado->total(),
        ];
    }

    public static function cargar($id)
    {
        $inversiones_plan_detallado = PlanDetallado::find($id);
        $inversion =  Inversiones::find($inversiones_plan_detallado->id_inversion);

        return [
            'id' => $inversiones_plan_detallado->id,
            'id_inversion' => $inversiones_plan_detallado->id_inversion,
            'id_proyecto' => $inversiones_plan_detallado->id_proyecto,
            'id_inversionista' => $inversiones_plan_detallado->id_inversionista,
            'id_gestor' => $inversiones_plan_detallado->id_gestor,
            'fecha_inversion' => $inversion->fecha_inversion,
            'porcentaje_ret_fuente_rendimientos' => $inversion->porcentaje_ret_fuente_rendimientos,
            'porcentaje_comision' => $inversiones_plan_detallado->tipo_concepto == 'C' ? $inversion->porcentaje_comision : '',
            'fecha_vencimiento' => $inversiones_plan_detallado->fecha_vencimiento,
            'fecha_pago' => $inversiones_plan_detallado->fecha_pago,
            'tipo_concepto' => $inversiones_plan_detallado->tipo_concepto,
            'valor_concepto' => $inversiones_plan_detallado->valor_concepto,
            'valor_ret_fuente' => $inversiones_plan_detallado->valor_ret_fuente,
            'valor_a_pagar' => $inversiones_plan_detallado->valor_concepto - $inversiones_plan_detallado->valor_ret_fuente,
            'porcentaje_ret_fuente' => $inversiones_plan_detallado->porcentaje_ret_fuente,
            'estado_plan' => $inversiones_plan_detallado->estado_plan,
            'usuario_creacion_id' => $inversiones_plan_detallado->usuario_creacion_id,
            'usuario_creacion_nombre' => $inversiones_plan_detallado->usuario_creacion_nombre,
            'usuario_modificacion_id' => $inversiones_plan_detallado->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $inversiones_plan_detallado->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($inversiones_plan_detallado->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($inversiones_plan_detallado->updated_at))->format("Y-m-d H:i:s")
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
        $inversiones_plan_detallado = isset($dto['id']) ? PlanDetallado::find($dto['id']) : new PlanDetallado();
        // Guardar objeto original para auditoria
        
        // Guardar objeto original para auditoria
        $inversiones_plan_detalladoOriginal = $inversiones_plan_detallado->toJson();
        
        $inversiones_plan_detallado->fill($dto);
        $guardado = $inversiones_plan_detallado->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la inversión.", $inversiones_plan_detallado);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $inversiones_plan_detallado->id,
            'nombre_recurso' => PlanDetallado::class,
            'descripcion_recurso' => $inversiones_plan_detallado->id,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $inversiones_plan_detalladoOriginal : $inversiones_plan_detallado->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $inversiones_plan_detallado->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return PlanDetallado::cargar($inversiones_plan_detallado->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $inversiones_plan_detallado = PlanDetallado::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $inversiones_plan_detallado->id,
            'nombre_recurso' => PlanDetallado::class,
            'descripcion_recurso' => 'Anulación inversion-'.$inversiones_plan_detallado->id,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $inversiones_plan_detallado->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $inversiones_plan_detallado->delete();
    }

    public static function obtenerProgramacionInv($dto)
    {
        $queryBase = DB::table('inversiones_plan_detallado as ipd')
        ->leftJoin('gestores as g', 'ipd.id_gestor', '=', 'g.id')
        ->leftJoin('inversionistas as i', 'ipd.id_inversionista', '=', 'i.id')
        ->leftJoin('proyectos as p', 'ipd.id_proyecto', '=', 'p.id')
        ->where('ipd.estado_plan', '=', 'GEN')
        ->when(isset($dto['fechaProgramacion']), function ($q) use ($dto) {
            $q->whereDate('ipd.fecha_vencimiento', '<=', $dto['fechaProgramacion']);
        })
        ->when(
            (isset($dto['nombre']) && !empty($dto['nombre'])) ||
            (isset($dto['inversionista_gestor']) && !empty($dto['inversionista_gestor'])),
            function ($q) use ($dto) {
                $nombre = $dto['nombre'] ?? $dto['inversionista_gestor'];
                $q->where(function ($subQuery) use ($nombre) {
                    $subQuery->where(function ($query) use ($nombre) {
                        $query->where('ipd.tipo_concepto', 'C')
                            ->where('g.nombre', 'like', '%' . $nombre . '%');
                    })->orWhere(function ($query) use ($nombre) {
                        $query->where('ipd.tipo_concepto', '<>', 'C')
                            ->where('i.nombre', 'like', '%' . $nombre . '%');
                    });
                });
            }
        );

        // Totales
        $totales = (clone $queryBase)->selectRaw("
            SUM(ipd.valor_concepto) as total_general,
            SUM(ipd.valor_ret_fuente) as total_ret_fuente,
            SUM(ipd.valor_concepto - ipd.valor_ret_fuente) as total_neto
        ")->first();

        // Ordenamiento desde 'ordenar_por=campo:direccion'
        if (isset($dto['ordenar_por']) && str_contains($dto['ordenar_por'], ':')) {
            [$ordenarPor, $direccion] = explode(':', $dto['ordenar_por']);
            $direccion = strtolower($direccion);
        } else {
            $ordenarPor = $dto['ordenar_por'] ?? 'fecha';
            $direccion = strtolower($dto['direccion'] ?? 'asc');
        }
        $direccion = in_array($direccion, ['asc', 'desc']) ? $direccion : 'asc';

        // Consulta con ordenamiento aplicado
        $resultados = (clone $queryBase)
            ->when($ordenarPor, function ($query) use ($ordenarPor, $direccion) {
                switch ($ordenarPor) {
                    case 'inversionista_gestor':
                        $query->orderByRaw("CASE WHEN ipd.tipo_concepto = 'C' THEN g.nombre ELSE i.nombre END $direccion");
                        break;
                    case 'proyecto':
                        $query->orderBy('p.codigo_proyecto', $direccion);
                        break;
                    case 'fecha':
                        $query->orderBy('ipd.fecha_vencimiento', $direccion);
                        break;
                    case 'tipo':
                        $query->orderBy('ipd.tipo_concepto', $direccion);
                        break;
                    case 'valor':
                        $query->orderBy('ipd.valor_concepto', $direccion);
                        break;
                    case 'ret_fuente':
                        $query->orderBy('ipd.valor_ret_fuente', $direccion);
                        break;
                    default:
                        $query->orderBy('ipd.fecha_vencimiento', 'asc')->orderBy('ipd.tipo_concepto', 'asc');
                        break;
                }
            })
            ->select([
                'ipd.id as id',
                DB::raw("CASE WHEN ipd.tipo_concepto = 'C' THEN g.nombre ELSE i.nombre END AS inversionista_gestor"),
                DB::raw("CASE WHEN ipd.tipo_concepto = 'C' THEN g.id ELSE i.id END AS id_cliente"),
                'p.codigo_proyecto as proyecto',
                'ipd.fecha_vencimiento as fecha',
                'ipd.tipo_concepto as tipo',
                'ipd.valor_concepto as valor',
                'ipd.valor_ret_fuente as ret_fuente',
                DB::raw('(ipd.valor_concepto - ipd.valor_ret_fuente) AS valor_a_pagar'),
            ])
            ->paginate($dto['limite'] ?? 100);

        return [
            'datos' => $resultados->items(),
            'desde' => $resultados->firstItem(),
            'hasta' => $resultados->lastItem(),
            'por_pagina' => $resultados->perPage(),
            'pagina_actual' => $resultados->currentPage(),
            'ultima_pagina' => $resultados->lastPage(),
            'total' => $resultados->total(),
            'total_general' => $totales->total_general ?? 0,
            'total_ret_fuente' => $totales->total_ret_fuente ?? 0,
            'total_neto' => $totales->total_neto ?? 0,
        ];
    }
    
    public static function modificarProgramacionInv($dto){
        $user = Auth::user();
        $usuario = $user->usuario();

        foreach ($dto as $registro) {
            // Consultar el plan detallado
            $inversiones_plan_detallado = PlanDetallado::find($registro['id']);
            // Guardar objeto original para auditoria
            $inversiones_plan_detalladoOriginal = $inversiones_plan_detallado->toJson();

            // DB::table('inversiones_plan_detallado')
            //     ->where('id', $registro['id'])
            //     ->update([
            //         'estado_plan' => $registro['estado_plan'],
            //         'usuario_modificacion_id' => $registro['usuario_modificacion_id'],
            //         'usuario_modificacion_nombre' => $registro['usuario_modificacion_nombre'],
            //         'updated_at' => now(),
            //     ]);

            if (isset($usuario) || isset($registro['usuario_modificacion_id'])) {
                $registro['usuario_modificacion_id'] = $usuario->id ?? ($registro['usuario_modificacion_id'] ?? null);
                $registro['usuario_modificacion_nombre'] = $usuario->nombre ?? ($registro['usuario_modificacion_nombre'] ?? null);
            }    
                
            $inversiones_plan_detallado->fill($registro);
            $guardado = $inversiones_plan_detallado->save();
            if(!$guardado){
                throw new Exception("Ocurrió un error al intentar guardar la inversión.", $inversiones_plan_detallado);
            }

            // Guardar auditoria
            $auditoriaDto = array(
                'id_recurso' => $inversiones_plan_detallado->id,
                'nombre_recurso' => PlanDetallado::class,
                'descripcion_recurso' => 'Programacion-'.$inversiones_plan_detallado->id_inversion.'-'.$inversiones_plan_detallado->id_inversionista.'-'.$inversiones_plan_detallado->id_proyecto.'-'.$inversiones_plan_detallado->fecha_vencimiento,
                'accion' => isset($registro['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
                'recurso_original' => isset($registro['id']) ? $inversiones_plan_detalladoOriginal : $inversiones_plan_detallado->toJson(),
                'recurso_resultante' => isset($registro['id']) ? $inversiones_plan_detallado->toJson() : null
            );
            AuditoriaTabla::crear($auditoriaDto);
        }

        
    
        return true;
    }

    public static function obtenerProgramacionParaExportar($fechaFiltro)
    {
        $registros = DB::table('inversiones_plan_detallado as ipd')
            ->leftJoin('gestores as g', 'ipd.id_gestor', '=', 'g.id')
            ->leftJoin('inversionistas as i', 'ipd.id_inversionista', '=', 'i.id')
            ->leftJoin('proyectos as p', 'ipd.id_proyecto', '=', 'p.id')
            ->select([
                'ipd.fecha_vencimiento',
                'p.codigo_proyecto',
                DB::raw("
                    CASE ipd.tipo_concepto
                        WHEN 'K' THEN 'Reintegro Capital' 
                        WHEN 'I' THEN 'Rendimientos' 
                        WHEN 'C' THEN 'Comision Gestion' 
                        ELSE '' 
                    END AS tipo_concepto
                "),
                'ipd.valor_concepto as valor_concepto',
                'ipd.valor_ret_fuente as valor_ret_fuente',
                'ipd.porcentaje_ret_fuente',
    
                // Inversionista
                'i.nombre as nombre_inversionista',
                'i.banco as banco_inversionista',
                DB::raw("
                    CASE i.tipo_cuenta 
                        WHEN 'A' THEN 'ahorros' 
                        WHEN 'C' THEN 'corriente' 
                        ELSE '' 
                    END AS tipo_cuenta_inversionista
                "),
                'i.numero_cuenta as numero_cuenta_inversionista',
    
                // Gestor
                'g.nombre as nombre_gestor',
                'g.banco as banco_gestor',
                DB::raw("
                    CASE g.tipo_cuenta 
                        WHEN 'A' THEN 'ahorros' 
                        WHEN 'C' THEN 'corriente' 
                        ELSE '' 
                    END AS tipo_cuenta_gestor
                "),
                'g.numero_cuenta as numero_cuenta_gestor',
            ])
            ->where('ipd.estado_plan', '=', 'GEN')
            ->whereDate('ipd.fecha_vencimiento', '<=', $fechaFiltro)
            ->orderBy('ipd.fecha_vencimiento')
            ->get();
    
        $agrupados = [];
    
        foreach ($registros as $item) {
            $esComision = $item->tipo_concepto === 'Comision Gestion';
    
            $nombrePersona = $esComision ? ($item->nombre_gestor ?? 'GESTOR SIN NOMBRE') : ($item->nombre_inversionista ?? 'INVERS. SIN NOMBRE');
            $banco = $esComision ? $item->banco_gestor : $item->banco_inversionista;
            $tipoCuenta = $esComision ? $item->tipo_cuenta_gestor : $item->tipo_cuenta_inversionista;
            $numeroCuenta = $esComision ? $item->numero_cuenta_gestor : $item->numero_cuenta_inversionista;
    
            // Validación segura de valores numéricos
            $valor_concepto = is_numeric($item->valor_concepto) ? floatval($item->valor_concepto) : 0;
            $valor_ret_fuente = is_numeric($item->valor_ret_fuente) ? floatval($item->valor_ret_fuente) : 0;
            $valor_a_pagar = $valor_concepto - $valor_ret_fuente;
    
            $registro = (object)[
                'fecha_vencimiento' => $item->fecha_vencimiento,
                'codigo_proyecto' => $item->codigo_proyecto,
                'nombre_inversionista' => $nombrePersona,
                'tipo_concepto' => $item->tipo_concepto,
                'valor_concepto' => $valor_concepto,
                'valor_ret_fuente' => $valor_ret_fuente,
                'porcentaje_ret_fuente' => $item->porcentaje_ret_fuente,
                'valor_a_pagar' => $valor_a_pagar,
                'banco' => $banco,
                'tipo_cuenta' => $tipoCuenta,
                'numero_cuenta' => $numeroCuenta,
                'total_inversionista' => '',
            ];
    
            // Clave robusta: tipo + nombre
            $clave = ($esComision ? 'G|' : 'I|') . $nombrePersona;
    
            $agrupados[$clave]['nombre'] = $nombrePersona;
            $agrupados[$clave]['registros'][] = $registro;
    
            $agrupados[$clave]['total_valor_concepto'] = ($agrupados[$clave]['total_valor_concepto'] ?? 0) + $valor_concepto;
            $agrupados[$clave]['total_ret_fuente'] = ($agrupados[$clave]['total_ret_fuente'] ?? 0) + $valor_ret_fuente;
            $agrupados[$clave]['total_valor_a_pagar'] = ($agrupados[$clave]['total_valor_a_pagar'] ?? 0) + $valor_a_pagar;
        }
    
        $datosFinales = [];
    
        foreach ($agrupados as $grupo) {
            foreach ($grupo['registros'] as $r) {
                $datosFinales[] = $r;
            }
            $primerRegistro = $grupo['registros'][0]; // Tomamos el primero del grupo
            $filaTotal = (object)[
                'fecha_vencimiento' => '',
                'codigo_proyecto' => '',
                'nombre_inversionista' => $grupo['nombre'],
                'tipo_concepto' => 'Total',
                'valor_concepto' => number_format($grupo['total_valor_concepto'], 0, ',', '.'),
                'valor_ret_fuente' => number_format($grupo['total_ret_fuente'], 0, ',', '.'),
                'porcentaje_ret_fuente' => '',
                'valor_a_pagar' => number_format($grupo['total_valor_a_pagar'], 0, ',', '.'),
                'banco' => $primerRegistro->banco,
                'tipo_cuenta' => $primerRegistro->tipo_cuenta,
                'numero_cuenta' => $primerRegistro->numero_cuenta,
                'total_inversionista' => number_format($grupo['total_valor_a_pagar'], 0, ',', '.'),
            ];
    
            $datosFinales[] = $filaTotal;
        }
    
        return $datosFinales;
    }
    
    public static function obtenerPagosProgramados($dto = [])
    {
        // Subconsulta para tipo_concepto IN ('K', 'I')
        $subQueryInversionistas = DB::table('inversiones_plan_detallado as ipd')
            ->leftJoin('inversionistas as i', 'ipd.id_inversionista', '=', 'i.id')
            ->where('ipd.estado_plan', 'PRG')
            ->whereIn('ipd.tipo_concepto', ['K', 'I'])
            ->groupBy(
                'ipd.id_inversionista',
                'i.nombre', 'i.numero_documento', 'i.banco', 'i.tipo_cuenta', 'i.numero_cuenta'
            )
            ->select([
                'ipd.id_inversionista as id',
                DB::raw("'I' as tipo_tercero"),
                'ipd.id_inversionista',
                DB::raw("null as id_gestor"),
                'i.nombre as nombre_tercero',
                'i.numero_documento as documento',
                'i.banco',
                'i.tipo_cuenta',
                'i.numero_cuenta',
                DB::raw('SUM(ipd.valor_concepto) as total_valor'),
                DB::raw('SUM(ipd.valor_ret_fuente) as total_ret_fuente'),
                DB::raw('SUM(ipd.valor_concepto - ipd.valor_ret_fuente) as valor_total_pagar'),
            ]);

        // Subconsulta para tipo_concepto = 'C'
        $subQueryGestores = DB::table('inversiones_plan_detallado as ipd')
            ->leftJoin('gestores as g', 'ipd.id_gestor', '=', 'g.id')
            ->where('ipd.estado_plan', 'PRG')
            ->where('ipd.tipo_concepto', 'C')
            ->groupBy(
                'ipd.id_gestor',
                'g.nombre', 'g.numero_documento', 'g.banco', 'g.tipo_cuenta', 'g.numero_cuenta'
            )
            ->select([
                'ipd.id_gestor as id',
                DB::raw("'G' as tipo_tercero"),
                DB::raw("null as id_inversionista"),
                'ipd.id_gestor',
                'g.nombre as nombre_tercero',
                'g.numero_documento as documento',
                'g.banco',
                'g.tipo_cuenta',
                'g.numero_cuenta',
                DB::raw('SUM(ipd.valor_concepto) as total_valor'),
                DB::raw('SUM(ipd.valor_ret_fuente) as total_ret_fuente'),
                DB::raw('SUM(ipd.valor_concepto - ipd.valor_ret_fuente) as valor_total_pagar'),
            ]);

        // Unión
        $unido = $subQueryInversionistas->unionAll($subQueryGestores);

        // Subconsulta para paginar
        $queryFinal = DB::table(DB::raw("({$unido->toSql()}) as pagos_programados"))
            ->mergeBindings($unido);

        // Subconsulta para totales
        $totales = DB::table(DB::raw("({$unido->toSql()}) as pagos_programados"))
            ->mergeBindings($unido)
            ->selectRaw('
                SUM(total_valor) as total_general_valor,
                SUM(total_ret_fuente) as total_general_ret_fuente,
                SUM(valor_total_pagar) as total_general_a_pagar
            ')
            ->first();

        // Paginación
        $limite = isset($dto['limite']) ? (int) $dto['limite'] : 100;
        $resultados = $queryFinal->paginate($limite);

        return [
            'datos' => $resultados->items(),
            'desde' => $resultados->firstItem(),
            'hasta' => $resultados->lastItem(),
            'por_pagina' => $resultados->perPage(),
            'pagina_actual' => $resultados->currentPage(),
            'ultima_pagina' => $resultados->lastPage(),
            'total' => $resultados->total(),

            // Totales agregados
            'total_general_valor' => $totales->total_general_valor ?? 0,
            'total_general_ret_fuente' => $totales->total_general_ret_fuente ?? 0,
            'total_general_a_pagar' => $totales->total_general_a_pagar ?? 0,
        ];
    }
    
    public static function marcarPagos($tipo, $terceroId, $usuarioId, $usuarioNombre)
    {
        $user = Auth::user();
        $usuario = $user->usuario();

        if (!$terceroId) {
            return [
                'ids' => [],
                'nombre_tercero' => null,
                'email_tercero' => null,
            ];
        }
    
        $query = DB::table('inversiones_plan_detallado')
            ->where('estado_plan', 'PRG');
    
        if ($tipo === 'I') {
            $query->where('id_inversionista', $terceroId)
             ->whereNull('id_gestor');
        } elseif ($tipo === 'G') {
            $query->where('id_gestor', $terceroId);
        } else {
            return [
                'ids' => [],
                'nombre_tercero' => null,
                'email_tercero' => null,
            ];
        }
    
        $ids = $query->pluck('id')->toArray();
    
        if (!empty($ids)) {
            // Obtener los ids de inversión afectados
            $idsInversiones = [];

            foreach ($ids as $id) {
                $registro = PlanDetallado::find($id);
                if (!$registro) continue;

                $registroOriginal = $registro->toJson();

                // Actualizar campos
                $registro->estado_plan = 'PAG';
                $registro->fecha_pago = now();
                $registro->usuario_modificacion_id = $usuarioId;
                $registro->usuario_modificacion_nombre = $usuarioNombre;
                $registro->updated_at = now();

                $guardado = $registro->save();

                if (!$guardado) {
                    throw new \Exception("Error al marcar como pagado el ID: {$id}");
                }

                // Auditoría individual
                AuditoriaTabla::crear([
                    'id_recurso' => $registro->id,
                    'nombre_recurso' => PlanDetallado::class,
                    'descripcion_recurso' => 'Pago-' . $registro->id_inversion . '-' . $registro->id_inversionista . '-' . $registro->id_proyecto . '-' . $registro->fecha_vencimiento,
                    'accion' => AccionAuditoriaEnum::MODIFICAR,
                    'recurso_original' => $registroOriginal,
                    'recurso_resultante' => $registro->toJson(),
                ]);

                // Acumular id_inversion para validación posterior
                $idsInversiones[] = $registro->id_inversion;
            }

            // Verificar si todas las cuotas de cada inversión ya fueron pagadas
            foreach (array_unique($idsInversiones) as $idInversion) {
                $cuotasPendientes = PlanDetallado::where('id_inversion', $idInversion)
                    ->whereIn('estado_plan', ['GEN', 'PRG'])
                    ->count();

                if ($cuotasPendientes === 0) {
                    DB::table('inversiones')
                        ->where('id', $idInversion)
                        ->update([
                            'estado_inversion' => 'PAG',
                            'usuario_modificacion_id' => $usuarioId,
                            'usuario_modificacion_nombre' => $usuarioNombre,
                            'updated_at' => now(),
                        ]);
                }
            }
        }

    
        // Consultar nombre y email del tercero
        $tercero = null;
    
        if ($tipo === 'I') {
            $tercero = DB::table('inversionistas')->select('nombre', 'email')->find($terceroId);
        } elseif ($tipo === 'G') {
            $tercero = DB::table('gestores')->select('nombre', 'email')->find($terceroId);
        }
    
        return [
            'ids' => $ids,
            'nombre_tercero' => $tercero?->nombre,
            'email_tercero' => $tercero?->email,
        ];
    }

    public static function marcarComoReinvertidas2(array $ids, int $terceroId, int $usuarioId, string $usuarioNombre)
    {
        if (empty($ids)) return;
        $user = Auth::user();
        $usuario = $user->usuario();
        DB::table('inversiones_plan_detallado')
            ->whereIn('id', $ids)
            ->update([
                'estado_plan' => 'REI',
                'usuario_modificacion_id' => $usuarioId,
                'usuario_modificacion_nombre' => $usuarioNombre,
                'updated_at' => now(),
            ]);

            // Consultar el plan detallado
            $inversiones_plan_detallado = PlanDetallado::find($registro['id']);
            // Guardar objeto original para auditoria
            $inversiones_plan_detalladoOriginal = $inversiones_plan_detallado->toJson();

            if (isset($usuario) || isset($registro['usuario_modificacion_id'])) {
                $registro['usuario_modificacion_id'] = $usuario->id ?? ($registro['usuario_modificacion_id'] ?? null);
                $registro['usuario_modificacion_nombre'] = $usuario->nombre ?? ($registro['usuario_modificacion_nombre'] ?? null);
            }    
                
            $inversiones_plan_detallado->fill($registro);
            $guardado = $inversiones_plan_detallado->save();
            if(!$guardado){
                throw new Exception("Ocurrió un error al intentar guardar la inversión.", $inversiones_plan_detallado);
            }

            // Guardar auditoria
            $auditoriaDto = array(
                'id_recurso' => $inversiones_plan_detallado->id,
                'nombre_recurso' => PlanDetallado::class,
                'descripcion_recurso' => 'Programacion-'.$inversiones_plan_detallado->id_inversion.'-'.$inversiones_plan_detallado->id_inversionista.'-'.$inversiones_plan_detallado->id_proyecto.'-'.$inversiones_plan_detallado->fecha_vencimiento,
                'accion' => isset($registro['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
                'recurso_original' => isset($registro['id']) ? $inversiones_plan_detalladoOriginal : $inversiones_plan_detallado->toJson(),
                'recurso_resultante' => isset($registro['id']) ? $inversiones_plan_detallado->toJson() : null
            );
            AuditoriaTabla::crear($auditoriaDto);

            

        // 2. Verificar si ya no quedan cuotas GEN/PRG para esas inversiones
        foreach ($ids as $idInversion) {
            $cuotasPendientes = DB::table('inversiones_plan_detallado')
                ->where('id_inversion', $idInversion)
                ->whereIn('estado_plan', ['GEN', 'PRG'])
                ->count();

            if ($cuotasPendientes === 0) {
                DB::table('inversiones')
                    ->where('id', $idInversion)
                    ->update([
                        'estado_inversion' => 'PAG',
                        'usuario_modificacion_id' => $usuarioId,
                        'usuario_modificacion_nombre' => $usuarioNombre,
                        'updated_at' => now(),
                    ]);
            }
        }

        // Consultar nombre y email del tercero
        $tercero = null;
        $tercero = DB::table('inversionistas')->select('nombre', 'email')->find($terceroId);

        return [
            'ids' => $ids,
            'nombre_tercero' => $tercero?->nombre,
            'email_tercero' => $tercero?->email,
        ];
    }

    public static function marcarComoReinvertidas(array $ids, int $terceroId, int $usuarioId, string $usuarioNombre)
    {
        if (empty($ids)) return;

        $user = Auth::user();
        $usuario = $user->usuario();

        foreach ($ids as $id) {
            $registro = PlanDetallado::find($id);
            if (!$registro) {
                continue; // O puedes lanzar una excepción si quieres forzar que todos existan
            }

            $registroOriginal = $registro->toJson();

            // Actualizar los campos
            $registro->estado_plan = 'REI';
            $registro->usuario_modificacion_id = $usuarioId;
            $registro->usuario_modificacion_nombre = $usuarioNombre;
            $registro->updated_at = now();

            $guardado = $registro->save();

            if (!$guardado) {
                throw new \Exception("Error al guardar la inversión con ID: {$id}");
            }

            // Registrar auditoría
            AuditoriaTabla::crear([
                'id_recurso' => $registro->id,
                'nombre_recurso' => PlanDetallado::class,
                'descripcion_recurso' => 'Reinversion-' . $registro->id_inversion . '-' . $registro->id_inversionista . '-' . $registro->id_proyecto . '-' . $registro->fecha_vencimiento,
                'accion' => AccionAuditoriaEnum::MODIFICAR,
                'recurso_original' => $registroOriginal,
                'recurso_resultante' => $registro->toJson(),
            ]);
        }

        // 2. Verificar si ya no quedan cuotas GEN/PRG para esas inversiones
        foreach ($ids as $idPlan) {
            $registro = PlanDetallado::find($idPlan);
            if (!$registro) continue;

            $idInversion = $registro->id_inversion;

            $cuotasPendientes = PlanDetallado::where('id_inversion', $idInversion)
                ->whereIn('estado_plan', ['GEN', 'PRG'])
                ->count();

            if ($cuotasPendientes === 0) {
                DB::table('inversiones')
                    ->where('id', $idInversion)
                    ->update([
                        'estado_inversion' => 'PAG',
                        'usuario_modificacion_id' => $usuarioId,
                        'usuario_modificacion_nombre' => $usuarioNombre,
                        'updated_at' => now(),
                    ]);
            }
        }

        // Consultar nombre y email del tercero
        $tercero = DB::table('inversionistas')->select('nombre', 'email')->find($terceroId);

        return [
            'ids' => $ids,
            'nombre_tercero' => $tercero?->nombre,
            'email_tercero' => $tercero?->email,
        ];
    }
    
    public static function obtenerDetallesPagos($ids)
    {
        return self::query()
            ->from('inversiones_plan_detallado')
            ->whereIn('inversiones_plan_detallado.id', $ids)
            ->leftJoin('inversiones_proyectos as ip', function ($join) {
                $join->on('inversiones_plan_detallado.id_inversion', '=', 'ip.id_inversion')
                    ->on('inversiones_plan_detallado.id_proyecto', '=', 'ip.id_proyecto');
            })
            ->leftJoin('proyectos as p', 'ip.id_proyecto', '=', 'p.id')
            ->select([
                'ip.valor_inversion_por_proyecto as valor_inversion',
                'ip.fecha_inversion',
                'p.codigo_proyecto as proyecto',
                DB::raw("
                    CASE inversiones_plan_detallado.tipo_concepto
                        WHEN 'K' THEN 'Reintegro Capital' 
                        WHEN 'I' THEN 'Rendimientos' 
                        WHEN 'C' THEN 'Comision Gestion' 
                        ELSE '' 
                    END AS concepto
                "),
                'inversiones_plan_detallado.fecha_vencimiento',
                'inversiones_plan_detallado.valor_concepto',
                'inversiones_plan_detallado.valor_ret_fuente',
                'inversiones_plan_detallado.porcentaje_ret_fuente',
                DB::raw('(inversiones_plan_detallado.valor_concepto - inversiones_plan_detallado.valor_ret_fuente) as valor_a_pagar')
            ])
            ->get();
    }

    public static function obtenerResumenSemanalaDetallado($dto = [])
    {
        $query = DB::table('inversiones_plan_detallado')
            ->join('inversiones', 'inversiones.id', 'inversiones_plan_detallado.id_inversion')
            ->join('proyectos', 'proyectos.id', 'inversiones_plan_detallado.id_proyecto')
            ->join('inversionistas', 'inversionistas.id', 'inversiones_plan_detallado.id_inversionista')
            ->leftJoin('gestores', 'gestores.id', 'inversiones_plan_detallado.id_gestor')
            ->select(
                'inversiones_plan_detallado.id as id_plan_detallado',
                'inversiones_plan_detallado.id_inversion',
                'inversiones_plan_detallado.id_proyecto',
                'proyectos.codigo_proyecto as proyecto',
                'inversiones_plan_detallado.id_inversionista',
                'inversiones_plan_detallado.id_gestor',
                DB::raw("CASE 
                            WHEN inversiones_plan_detallado.tipo_concepto = 'C' 
                            THEN gestores.nombre 
                            ELSE inversionistas.nombre 
                        END AS inversionista_gestor"),
                DB::raw('YEAR(fecha_vencimiento) as anio'),
                DB::raw('WEEK(fecha_vencimiento, 6) as semana_iso'),
                'inversiones_plan_detallado.fecha_vencimiento as fecha',
                DB::raw("DATE_FORMAT(
                    DATE_ADD(
                        DATE_SUB(DATE(fecha_vencimiento), INTERVAL ((WEEKDAY(fecha_vencimiento) + 2) % 7) DAY),
                        INTERVAL 6 DAY
                    ), '%Y-%m-%d') AS viernes_semana"),

                DB::raw("DAY(
                    DATE_ADD(
                        DATE_SUB(DATE(fecha_vencimiento), INTERVAL ((WEEKDAY(fecha_vencimiento) + 2) % 7) DAY),
                        INTERVAL 6 DAY
                    )
                ) AS dia_viernes"),
                DB::raw("CASE 
                            WHEN inversiones_plan_detallado.tipo_concepto = 'K' THEN 'Reintegro Capital' 
                            WHEN inversiones_plan_detallado.tipo_concepto = 'I' THEN 'Rendimientos' 
                            WHEN inversiones_plan_detallado.tipo_concepto = 'C' THEN 'Comision Gestion' 
                            ELSE '' 
                        END AS tipo"),
                'inversiones_plan_detallado.valor_concepto as valor',
                'inversiones_plan_detallado.valor_ret_fuente as ret_fuente',
                DB::raw('(inversiones_plan_detallado.valor_concepto - inversiones_plan_detallado.valor_ret_fuente) AS valor_a_pagar'),
                'inversiones_plan_detallado.estado_plan'
            )
            ->whereIn('inversiones_plan_detallado.estado_plan', ['PRG', 'GEN'])
            ->orderBy('inversiones_plan_detallado.fecha_vencimiento');

        // Filtros
        if (!empty($dto['nombre'])) {
            $query->where('inversiones_plan_detallado.id_inversionista', $dto['nombre']);
        }

        if (!empty($dto['concepto'])) {
            $query->where('inversiones_plan_detallado.tipo_concepto', $dto['concepto']);
        }

        if (!empty($dto['fechaInicial'])) {
            $query->whereDate('inversiones_plan_detallado.fecha_vencimiento', '>=', $dto['fechaInicial']);
        }

        if (!empty($dto['fechaFinal'])) {
            $query->whereDate('inversiones_plan_detallado.fecha_vencimiento', '<=', $dto['fechaFinal']);
        }

        return [
            'datos' => $query->get(),
        ];
    }

    public static function obtenerPlanDetalladoParaExportar($filtros)
    {
         $query = DB::table('inversiones_plan_detallado as ipd')
            ->leftJoin('inversionistas as inv', 'ipd.id_inversionista', '=', 'inv.id')
            ->leftJoin('gestores as g', 'ipd.id_gestor', '=', 'g.id')
            ->leftJoin('proyectos as p', 'ipd.id_proyecto', '=', 'p.id')
            ->select([
                'inv.nombre as inversionista',
                'ipd.id_inversion as inversion',
                'p.codigo_proyecto as proyecto',
                'g.nombre as gestor',
                'ipd.fecha_vencimiento',
                DB::raw("CASE ipd.tipo_concepto
                            WHEN 'K' THEN 'Reintegro Capital' 
                            WHEN 'I' THEN 'Rendimientos' 
                            WHEN 'C' THEN 'Comisión Gestión' 
                            ELSE 'Sin Tipo'
                        END AS tipo"),
                'ipd.valor_concepto as valor',
                'ipd.valor_ret_fuente',
                'ipd.porcentaje_ret_fuente',
                DB::raw('(ipd.valor_concepto - ipd.valor_ret_fuente) AS valor_a_pagar'),
                'ipd.fecha_pago',
                DB::raw("CASE ipd.estado_plan
                            WHEN 'GEN' THEN 'Generada'
                            WHEN 'PAG' THEN 'Pagada'
                            WHEN 'PRG' THEN 'Programada'
                            WHEN 'LIQ' THEN 'Liquidada'
                            WHEN 'ANU' THEN 'Anulada'
                            WHEN 'REI' THEN 'Cancelada por reinversión'
                            ELSE 'Sin Estado'
                        END AS estado"),
                'ipd.usuario_modificacion_nombre as usuario_ultima_actualizacion',
                'ipd.updated_at as fecha_ultima_actualizacion',
            ]);

        // Filtros dinámicos
        $query->when($filtros['id_proyecto'] ?? null, fn($q, $v) => $q->where('ipd.id_proyecto', $v));
        $query->when($filtros['id_inversionista'] ?? null, fn($q, $v) => $q->where('ipd.id_inversionista', $v));
        $query->when($filtros['tipo'] ?? null, fn($q, $v) => $q->where('ipd.tipo_concepto', $v));
        $query->when($filtros['estado'] ?? null, fn($q, $v) => $q->where('ipd.estado_plan', $v));

        $query->when($filtros['fechaDesde'] ?? null, fn($q, $v) => $q->whereDate('ipd.fecha_vencimiento', '>=', $v));
        $query->when($filtros['fechaHasta'] ?? null, fn($q, $v) => $q->whereDate('ipd.fecha_vencimiento', '<=', $v));

        $query->when($filtros['fechaPagoDesde'] ?? null, fn($q, $v) => $q->whereDate('ipd.fecha_pago', '>=', $v));
        $query->when($filtros['fechaPagoHasta'] ?? null, fn($q, $v) => $q->whereDate('ipd.fecha_pago', '<=', $v));

        return $query->get();
    }

    public static function obtenerHcaPagosDetallado($dto = [])
    {
        $query = DB::table('inversiones_plan_detallado')
            ->join('inversiones', 'inversiones.id', 'inversiones_plan_detallado.id_inversion')
            ->join('proyectos', 'proyectos.id', 'inversiones_plan_detallado.id_proyecto')
            ->join('inversionistas', 'inversionistas.id', 'inversiones_plan_detallado.id_inversionista')
            ->leftJoin('gestores', 'gestores.id', 'inversiones_plan_detallado.id_gestor')
            ->select(
                'inversiones_plan_detallado.id as id_plan_detallado',
                'inversiones_plan_detallado.id_inversion',
                'inversiones_plan_detallado.id_proyecto',
                'proyectos.codigo_proyecto as proyecto',
                'inversiones_plan_detallado.id_inversionista',
                'inversiones_plan_detallado.id_gestor',
                DB::raw("CASE 
                            WHEN inversiones_plan_detallado.tipo_concepto = 'C' 
                            THEN gestores.nombre 
                            ELSE inversionistas.nombre 
                        END AS inversionista_gestor"),
                DB::raw('YEAR(fecha_pago) as anio'),
                DB::raw('WEEK(fecha_pago, 6) as semana_iso'),
                'inversiones_plan_detallado.fecha_pago as fecha',
                DB::raw("DATE_FORMAT(
                    DATE_ADD(
                        DATE_SUB(DATE(fecha_pago), INTERVAL ((WEEKDAY(fecha_pago) + 2) % 7) DAY),
                        INTERVAL 6 DAY
                    ), '%Y-%m-%d') AS viernes_semana"),

                DB::raw("DAY(
                    DATE_ADD(
                        DATE_SUB(DATE(fecha_pago), INTERVAL ((WEEKDAY(fecha_pago) + 2) % 7) DAY),
                        INTERVAL 6 DAY
                    )
                ) AS dia_viernes"),
                DB::raw("CASE 
                            WHEN inversiones_plan_detallado.tipo_concepto = 'K' THEN 'Reintegro Capital' 
                            WHEN inversiones_plan_detallado.tipo_concepto = 'I' THEN 'Rendimientos' 
                            WHEN inversiones_plan_detallado.tipo_concepto = 'C' THEN 'Comision Gestion' 
                            ELSE '' 
                        END AS tipo"),
                'inversiones_plan_detallado.valor_concepto as valor',
                'inversiones_plan_detallado.valor_ret_fuente as ret_fuente',
                DB::raw('(inversiones_plan_detallado.valor_concepto - inversiones_plan_detallado.valor_ret_fuente) AS valor_a_pagar'),
                'inversiones_plan_detallado.estado_plan'
            )
            ->whereNotNull('inversiones_plan_detallado.fecha_pago') 
            ->orderBy('inversiones_plan_detallado.fecha_pago');

        // Filtros
        if (!empty($dto['nombre'])) {
            $query->where('inversiones_plan_detallado.id_inversionista', $dto['nombre']);
        }

        if (!empty($dto['concepto'])) {
            $query->where('inversiones_plan_detallado.tipo_concepto', $dto['concepto']);
        }

        if (!empty($dto['fechaInicial'])) {
            $query->whereDate('inversiones_plan_detallado.fecha_pago', '>=', $dto['fechaInicial']);
        }

        if (!empty($dto['fechaFinal'])) {
            $query->whereDate('inversiones_plan_detallado.fecha_pago', '<=', $dto['fechaFinal']);
        }

        return [
            'datos' => $query->get(),
        ];
    }

    
}
