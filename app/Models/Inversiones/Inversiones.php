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
use Illuminate\Support\Facades\Log;

class Inversiones extends Model
{
    use HasFactory;

    protected $table = 'inversiones';

    protected $fillable = [
        'id_inversionista',
        'porcentaje_ret_fuente_rendimientos',
        'id_tipo_inversion',
        'valor_inversion',
        'fecha_inversion',
        'estado_inversion',
        'id_gestor',
        'porcentaje_comision',
        'porcentaje_ret_fuente',
        'plazo_capital',
        'plazo_interes',
        'periodos_muertos',
        'periodos_gracia',
        'tasa_interes_inversion',
        'indicativo_forma_pago_int',
        'indicativo_reinversion',
        'observaciones',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('inversiones')
            ->select(
                'inversiones.id',
                'inversiones.id_inversionista',
                'inversiones.porcentaje_ret_fuente_rendimientos',
                'inversiones.id_tipo_inversion',
                'inversiones.valor_inversion',
                'inversiones.fecha_inversion',
                'inversiones.estado_inversion',
                'inversiones.id_gestor',
                'inversiones.porcentaje_comision',
                'inversiones.porcentaje_ret_fuente',
                'inversiones.plazo_capital',
                'inversiones.plazo_interes',
                'inversiones.periodos_muertos',
                'inversiones.periodos_gracia',
                'inversiones.tasa_interes_inversion',
                'inversiones.indicativo_forma_pago_int',
                'inversiones.indicativo_reinversion',
                'inversiones.observaciones',
            );
        $query->orderBy('nombre', 'asc');
        return $query->get();
    }


    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('inversiones')
        ->join('inversionistas', 'inversionistas.id', 'inversiones.id_inversionista')
        ->join('tipos_inversion', 'tipos_inversion.id', 'inversiones.id_tipo_inversion')
        ->leftJoin('gestores', 'gestores.id', 'inversiones.id_gestor')
            ->select(
            'inversiones.id',            
            'inversiones.id_inversionista',
            'inversiones.porcentaje_ret_fuente_rendimientos',
            'inversionistas.nombre as inversionista',
            'inversiones.id_tipo_inversion',
            'tipos_inversion.nombre as tipo_inversion',
            'inversiones.valor_inversion',
            'inversiones.fecha_inversion',
            'inversiones.estado_inversion',
            'inversiones.id_gestor',
            'gestores.nombre as gestor',
            'inversiones.porcentaje_comision',
            'inversiones.porcentaje_ret_fuente',
            'inversiones.plazo_capital',
            'inversiones.plazo_interes',
            'inversiones.periodos_muertos',
            'inversiones.periodos_gracia',
            'inversiones.tasa_interes_inversion',
            'inversiones.indicativo_forma_pago_int',
            'inversiones.indicativo_reinversion',
            'inversiones.observaciones',
            'inversiones.usuario_creacion_id',
            'inversiones.usuario_creacion_nombre',
            'inversiones.usuario_modificacion_id',
            'inversiones.usuario_modificacion_nombre',
            DB::raw('EXISTS (
                SELECT 1 FROM inversiones_plan_detallado 
                WHERE inversiones_plan_detallado.id_inversion = inversiones.id 
                AND inversiones_plan_detallado.estado_plan IN ("PAG", "REI")
            ) as tiene_detalle_pagado'),
        );

        if(isset($dto['nombre'])){
            $query->where('inversionistas.nombre', 'like', '%' . $dto['nombre'] . '%');
        }

        if(isset($dto['estado_inversion'])){
            $query->where('inversiones.estado_inversion', 'like', '%' . $dto['estado_inversion'] . '%');
        }

        if(isset($dto['fechaInicial'])){
            $query->whereDate('inversiones.fecha_inversion', '>=', $dto['fechaInicial']);
        }

        if(isset($dto['fechaFinal'])){
            $query->whereDate('inversiones.fecha_inversion', '<=', $dto['fechaFinal']);
        }

        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'inversionista'){
                    $query->orderBy('inversionistas.nombre', $value);
                }
                if($attribute == 'tipo_inversion'){
                    $query->orderBy('tipos_inversion.nombre', $value);
                } 
                if($attribute == 'tipo_documento'){
                    $query->orderBy('inversiones.tipo_documento', $value);
                }
                if($attribute == 'valor_inversion'){
                    $query->orderBy('inversiones.valor_inversion', $value);
                }  
                if($attribute == 'fecha_inversion'){
                    $query->orderBy('inversiones.fecha_inversion', $value);
                }  
                if($attribute == 'estado_inversion'){
                    $query->orderBy('inversiones.estado_inversion', $value);
                }  
                if($attribute == 'gestor'){
                    $query->orderBy('gestores.nombre', $value);
                }  
                if($attribute == 'porcentaje_comision'){
                    $query->orderBy('inversiones.porcentaje_comision', $value);
                }  
                if($attribute == 'plazo_capital'){
                    $query->orderBy('inversiones.plazo_capital', $value);
                } 
                if($attribute == 'plazo_interes'){
                    $query->orderBy('inversiones.plazo_interes', $value);
                }  
                
                if($attribute == 'periodos_muertos'){
                    $query->orderBy('inversiones.periodos_muertos', $value);
                }  
                if($attribute == 'periodos_gracia'){
                    $query->orderBy('inversiones.periodos_gracia', $value);
                }  
                if($attribute == 'tasa_interes_inversion'){
                    $query->orderBy('inversiones.tasa_interes_inversion', $value);
                }      
                if($attribute == 'indicativo_forma_pago_int'){
                    $query->orderBy('inversiones.indicativo_forma_pago_int', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('inversiones.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('inversiones.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('inversiones.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('inversiones.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("inversiones.updated_at", "desc");
        }

        $inversiones = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $inversiones->items();
    
        return [
            'datos' => $data,
            'desde' => $inversiones->firstItem(),
            'hasta' => $inversiones->lastItem(),
            'por_pagina' => $inversiones->perPage(),
            'pagina_actual' => $inversiones->currentPage(),
            'ultima_pagina' => $inversiones->lastPage(),
            'total' => $inversiones->total(),
        ];
    }

    public static function cargar($id)
    {
        $inversiones = Inversiones::find($id);

        return [
            'id' => $inversiones->id,
            'id_inversionista' => $inversiones->id_inversionista,
            'porcentaje_ret_fuente_rendimientos' => $inversiones->porcentaje_ret_fuente_rendimientos,
            'id_tipo_inversion' => $inversiones->id_tipo_inversion,
            'valor_inversion' => $inversiones->valor_inversion,
            'fecha_inversion' => $inversiones->fecha_inversion,
            'estado_inversion' => $inversiones->estado_inversion,
            'id_gestor' => $inversiones->id_gestor,
            'porcentaje_comision' => $inversiones->porcentaje_comision,
            'porcentaje_ret_fuente' => $inversiones->porcentaje_ret_fuente,
            'plazo_capital' => $inversiones->plazo_capital,
            'plazo_interes' => $inversiones->plazo_interes,
            'periodos_muertos' => $inversiones->periodos_muertos,
            'periodos_gracia' => $inversiones->periodos_gracia,
            'tasa_interes_inversion' => $inversiones->tasa_interes_inversion,
            'indicativo_forma_pago_int' => $inversiones->indicativo_forma_pago_int,
            'indicativo_reinversion' => $inversiones->indicativo_reinversion,
            'observaciones' => $inversiones->observaciones,
            'usuario_creacion_id' => $inversiones->usuario_creacion_id,
            'usuario_creacion_nombre' => $inversiones->usuario_creacion_nombre,
            'usuario_modificacion_id' => $inversiones->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $inversiones->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($inversiones->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($inversiones->updated_at))->format("Y-m-d H:i:s")
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
        $inversiones = isset($dto['id']) ? Inversiones::find($dto['id']) : new Inversiones();
        // Guardar objeto original para auditoria
        $inversionesOriginal = $inversiones->toJson();
        
        
        $inversiones->fill($dto);
        $guardado = $inversiones->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la inversión.", $inversiones);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $inversiones->id,
            'nombre_recurso' => Inversiones::class,
            'descripcion_recurso' => 'Creación inversión-'.$inversiones->id,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $inversionesOriginal : $inversiones->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $inversiones->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return Inversiones::cargar($inversiones->id);
    }

    public static function eliminar($id)
    {
        $user = Auth::user();
        $usuario = $user->usuario();
        $inversion = Inversiones::find($id);
        $inversionOriginal = $inversion->toJson();
    
        $inversion->estado_inversion = 'ANU';
        $inversion->updated_at = Carbon::now();
        $inversion->usuario_modificacion_id = $usuario->id;
        $inversion->usuario_modificacion_nombre = $usuario->nombre;
    
        $guardar = $inversion->save();
    
        if (!$guardar) {
            throw new Exception("Ocurrió un error al intentar anular la inversión.", $inversion);
        }
    
        // Actualizar registros en inversiones_plan_detallado
        $registros = DB::table('inversiones_plan_detallado')
            ->where('id_inversion', $id)
            ->whereIn('estado_plan', ['GEN', 'PRG'])
            ->get();
    
        foreach ($registros as $registro) {
            $original = (array) $registro;
    
            DB::table('inversiones_plan_detallado')
                ->where('id', $registro->id)
                ->update([
                    'estado_plan' => 'ANU',
                    'usuario_modificacion_id' => $usuario->id,
                    'usuario_modificacion_nombre' => $usuario->nombre,
                    'updated_at' => now()
                ]);
    
            AuditoriaTabla::crear([
                'id_recurso' => $registro->id,
                'nombre_recurso' => 'inversiones_plan_detallado',
                'descripcion_recurso' => 'Anulación inversión-'.$id,
                'accion' => AccionAuditoriaEnum::MODIFICAR,
                'recurso_original' => json_encode($original),
                'recurso_resultante' => json_encode(array_merge($original, [
                    'estado_plan' => 'ANU',
                    'usuario_modificacion_id' => $usuario->id,
                    'usuario_modificacion_nombre' => $usuario->nombre,
                    'updated_at' => now()
                ])),
            ]);
        }
    
        // Guardar auditoría de la inversión
        $auditoriaDto = [
            'id_recurso' => $inversion->id,
            'nombre_recurso' => Inversiones::class,
            'descripcion_recurso' => 'Anulación inversion-'.$inversion->id,
            'accion' => AccionAuditoriaEnum::MODIFICAR,
            'recurso_original' => $inversionOriginal,
            'recurso_resultante' => $inversion->toJson()
        ];
        AuditoriaTabla::crear($auditoriaDto);
    
        return true;
    }    

    public static function actualizarProyectosConAuditoria($idInversion, $datos)
    {
        $proyectos = DB::table('inversiones_proyectos')
            ->where('id_inversion', $idInversion)
            ->get();
    
        $idInversionistaNuevo = isset($datos['id_inversionista']) ? (int) $datos['id_inversionista'] : null;
        $idGestorNuevo = isset($datos['id_gestor']) ? (int) $datos['id_gestor'] : null;
        $fechaInversionNueva = isset($datos['fecha_inversion']) ? $datos['fecha_inversion'] : null;

        $user = Auth::user();
        $usuario = $user->usuario();

        foreach ($proyectos as $proyecto) {
            $cambios = [];
    
            // Cast explícito y comparación
            if ((int) $proyecto->id_inversionista !== $idInversionistaNuevo) {
                $cambios['id_inversionista'] = $idInversionistaNuevo;
            }
    
            if ((int) $proyecto->id_gestor !== $idGestorNuevo) {
                $cambios['id_gestor'] = $idGestorNuevo;
            }

            // Comparar fecha de inversión
            if (!empty($fechaInversionNueva) && $proyecto->fecha_inversion != $fechaInversionNueva) {
                $cambios['fecha_inversion'] = $fechaInversionNueva;
            }
    
            if (!empty($cambios)) {
                $original = (array) $proyecto;

                DB::table('inversiones_proyectos')
                    ->where('id', $proyecto->id)
                    ->update(array_merge($cambios, [
                        'usuario_modificacion_id' => $usuario->id,
                        'usuario_creacion_nombre' => $usuario->nombre,
                        'updated_at' => now()
                    ]));
    
                AuditoriaTabla::crear([
                    'id_recurso' => $proyecto->id,
                    'nombre_recurso' => 'inversiones_proyectos',
                    'descripcion_recurso' => $proyecto->id,
                    'accion' => AccionAuditoriaEnum::MODIFICAR,
                    'recurso_original' => json_encode($original),
                    'recurso_resultante' => json_encode(array_merge($original, $cambios))
                ]);
            }
        }
    
        return $proyectos;
    }    

    public static function auditarPlanDetallado($idInversion)
    {
        $plan = DB::table('inversiones_plan_detallado')
            ->where('id_inversion', $idInversion)
            ->get();

        foreach ($plan as $registro) {
            AuditoriaTabla::crear([
                'id_recurso' => $registro->id,
                'nombre_recurso' => 'inversiones_plan_detallado-'.$idInversion,
                'descripcion_recurso' => 'Creación inversión-'.$idInversion,
                'accion' => AccionAuditoriaEnum::CREAR,
                'recurso_original' => json_encode($registro),
                'recurso_resultante' => '{}'
            ]);
        }
    }

}
