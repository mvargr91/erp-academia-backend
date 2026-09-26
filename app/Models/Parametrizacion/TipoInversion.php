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

class TipoInversion extends Model
{
    use HasFactory;

    protected $table = 'tipos_inversion';

    protected $fillable = [
        'nombre',
        'id_condicion_plazo',
        'plazo_capital',
        'plazo_interes',
        'periodos_muertos',
        'periodos_gracia',
        'tasa_interes_inversion',
        'indicativo_forma_pago_int',
        'indicativo_asegurado',
        'observaciones',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('tipos_inversion')
            ->select(
                'tipos_inversion.id',
                'tipos_inversion.nombre',
                'tipos_inversion.id_condicion_plazo',
                'tipos_inversion.plazo_capital',
                'tipos_inversion.plazo_interes',
                'tipos_inversion.periodos_muertos',
                'tipos_inversion.periodos_gracia',
                'tipos_inversion.tasa_interes_inversion',
                'tipos_inversion.indicativo_forma_pago_int',
                'tipos_inversion.indicativo_asegurado',
                'tipos_inversion.observaciones',
                'tipos_inversion.estado',
            );
        $query->orderBy('nombre', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('tipos_inversion')
        ->Leftjoin('condiciones_plazo', 'condiciones_plazo.id', 'tipos_inversion.id_condicion_plazo')
            ->select(
            'tipos_inversion.id',            
            'tipos_inversion.nombre',
            'condiciones_plazo.nombre as condiciones_plazo',
            'tipos_inversion.id_condicion_plazo',
            'condiciones_plazo.tasa_interes_inversion as condiciones_tasa_intereses_inversion',
            'condiciones_plazo.plazo_capital as condiciones_plazo_capital',
            'condiciones_plazo.porcentaje_inversion_externa as condiciones_porcentaje_inversion_externa',
            'tipos_inversion.plazo_capital',
            'tipos_inversion.plazo_interes',
            'tipos_inversion.periodos_muertos',
            'tipos_inversion.periodos_gracia',
            'tipos_inversion.tasa_interes_inversion',
            'tipos_inversion.indicativo_forma_pago_int',
            'tipos_inversion.indicativo_asegurado',
            'tipos_inversion.observaciones',
            'tipos_inversion.estado',
            'tipos_inversion.usuario_creacion_id',
            'tipos_inversion.usuario_creacion_nombre',
            'tipos_inversion.usuario_modificacion_id',
            'tipos_inversion.usuario_modificacion_nombre',
            'tipos_inversion.created_at as fecha_creacion',
            'tipos_inversion.updated_at as fecha_modificacion' ,
        );

        if(isset($dto['nombre'])){
            $query->where('tipos_inversion.nombre', 'like', '%' . trim($dto['nombre']) . '%');
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre'){
                    $query->orderBy('tipos_inversion.nombre', $value);
                }
                if($attribute == 'condiciones_plazo'){
                    $query->orderBy('condiciones_plazo.nombre', $value);
                }
                if($attribute == 'plazo_capital'){
                    $query->orderBy('tipos_inversion.plazo_capital', $value);
                }
                if($attribute == 'plazo_interes'){
                    $query->orderBy('tipos_inversion.plazo_interes', $value);
                }
                if($attribute == 'periodos_muertos'){
                    $query->orderBy('tipos_inversion.periodos_muertos', $value);
                }  
                if($attribute == 'periodos_gracia'){
                    $query->orderBy('tipos_inversion.periodos_gracia', $value);
                }  
                if($attribute == 'tasa_interes_inversion'){
                    $query->orderBy('tipos_inversion.tasa_interes_inversion', $value);
                }  
                if($attribute == 'indicativo_forma_pago_int'){
                    $query->orderBy('tipos_inversion.indicativo_forma_pago_int', $value);
                }  
                if($attribute == 'indicativo_asegurado'){
                    $query->orderBy('tipos_inversion.indicativo_asegurado', $value);
                } 
                if($attribute == 'observaciones'){
                    $query->orderBy('tipos_inversion.observaciones', $value);
                }        
                if($attribute == 'estado'){
                    $query->orderBy('tipos_inversion.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('tipos_inversion.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('tipos_inversion.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('tipos_inversion.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('tipos_inversion.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("tipos_inversion.updated_at", "desc");
        }

        $tipos_inversion = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $tipos_inversion->items();
    
        return [
            'datos' => $data,
            'desde' => $tipos_inversion->firstItem(),
            'hasta' => $tipos_inversion->lastItem(),
            'por_pagina' => $tipos_inversion->perPage(),
            'pagina_actual' => $tipos_inversion->currentPage(),
            'ultima_pagina' => $tipos_inversion->lastPage(),
            'total' => $tipos_inversion->total(),
        ];
    }

    public static function cargar($id)
    {
        $tipos_inversion = TipoInversion::find($id);

        return [
            'id' => $tipos_inversion->id,
            'nombre' => $tipos_inversion->nombre,
            'id_condicion_plazo' => $tipos_inversion->id_condicion_plazo,
            'plazo_capital' => $tipos_inversion->plazo_capital,
            'plazo_interes' => $tipos_inversion->plazo_interes,
            'periodos_muertos' => $tipos_inversion->periodos_muertos,
            'periodos_gracia' => $tipos_inversion->periodos_gracia,
            'tasa_interes_inversion' => $tipos_inversion->tasa_interes_inversion,
            'indicativo_forma_pago_int' => $tipos_inversion->indicativo_forma_pago_int,
            'indicativo_asegurado' => $tipos_inversion->indicativo_asegurado,
            'observaciones' => $tipos_inversion->observaciones,
            'estado' => $tipos_inversion->estado,
            'usuario_creacion_id' => $tipos_inversion->usuario_creacion_id,
            'usuario_creacion_nombre' => $tipos_inversion->usuario_creacion_nombre,
            'usuario_modificacion_id' => $tipos_inversion->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $tipos_inversion->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($tipos_inversion->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($tipos_inversion->updated_at))->format("Y-m-d H:i:s")
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
        $tipos_inversion = isset($dto['id']) ? TipoInversion::find($dto['id']) : new TipoInversion();

        // Guardar objeto original para auditoria
        $tipos_inversionOriginal = $tipos_inversion->toJson();

        $tipos_inversion->fill($dto);
        $guardado = $tipos_inversion->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la compañia.", $tipos_inversion);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $tipos_inversion->id,
            'nombre_recurso' => TipoInversion::class,
            'descripcion_recurso' => $tipos_inversion->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $tipos_inversionOriginal : $tipos_inversion->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $tipos_inversion->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return TipoInversion::cargar($tipos_inversion->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $tipos_inversion = TipoInversion::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $tipos_inversion->id,
            'nombre_recurso' => TipoInversion::class,
            'descripcion_recurso' => $tipos_inversion->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $tipos_inversion->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $tipos_inversion->delete();
    }
}
