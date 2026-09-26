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

class ParametroMensual extends Model
{
    use HasFactory;

    protected $table = 'parametros_mensuales';

    protected $fillable = [
        'anio',
        'mes',
        'valor_energia_bolsa',
        'valor_energia_mercado',
        'costo_compra',
        'cargo_transporte_nacional',
        'cargo_transporte_local',
        'margen_comercializacion',
        'costo_perdidas',
        'costo_restricciones',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('parametros_mensuales')
            ->select(
                'parametros_mensuales.id',
                'parametros_mensuales.anio',
                'parametros_mensuales.mes',
                'parametros_mensuales.valor_energia_bolsa',
                'parametros_mensuales.valor_energia_mercado',
                'parametros_mensuales.costo_compra',
                'parametros_mensuales.cargo_transporte_nacional',
                'parametros_mensuales.cargo_transporte_local',
                'parametros_mensuales.margen_comercializacion',
                'parametros_mensuales.costo_perdidas',
                'parametros_mensuales.costo_restricciones',
                'parametros_mensuales.estado',
                'parametros_mensuales.usuario_creacion_id',
                'parametros_mensuales.usuario_creacion_nombre',
                'parametros_mensuales.usuario_modificacion_id',
                'parametros_mensuales.usuario_modificacion_nombre',
            );

        $query->orderBy('anio', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('parametros_mensuales')
            ->select(
            'parametros_mensuales.id',
            'parametros_mensuales.anio',
            'parametros_mensuales.mes',
            'parametros_mensuales.valor_energia_bolsa',
            'parametros_mensuales.valor_energia_mercado',
            'parametros_mensuales.costo_compra',
            'parametros_mensuales.cargo_transporte_nacional',
            'parametros_mensuales.cargo_transporte_local',
            'parametros_mensuales.margen_comercializacion',
            'parametros_mensuales.costo_perdidas',
            'parametros_mensuales.costo_restricciones',
            'parametros_mensuales.estado',
            'parametros_mensuales.usuario_creacion_id',
            'parametros_mensuales.usuario_creacion_nombre',
            'parametros_mensuales.usuario_modificacion_id',
            'parametros_mensuales.usuario_modificacion_nombre',
            'parametros_mensuales.created_at as fecha_creacion',
            'parametros_mensuales.updated_at as fecha_modificacion' ,
        );

        if(isset($dto['anio'])){
            $query->where('parametros_mensuales.anio', '<=' , $dto['anio']);
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'anio'){
                    $query->orderBy('parametros_mensuales.anio', $value);
                }
                if($attribute == 'mes'){
                    $query->orderBy('parametros_mensuales.mes', $value);
                }
                if($attribute == 'valor_energia_bolsa'){
                    $query->orderBy('parametros_mensuales.valor_energia_bolsa', $value);
                }
                if($attribute == 'valor_energia_mercado'){
                    $query->orderBy('parametros_mensuales.valor_energia_mercado', $value);
                }
                if($attribute == 'costo_compra'){
                    $query->orderBy('parametros_mensuales.costo_compra', $value);
                }
                if($attribute == 'cargo_transporte_nacional'){
                    $query->orderBy('parametros_mensuales.cargo_transporte_nacional', $value);
                }
                if($attribute == 'cargo_transporte_local'){
                    $query->orderBy('parametros_mensuales.cargo_transporte_local', $value);
                }
                if($attribute == 'margen_comercializacion'){
                    $query->orderBy('parametros_mensuales.margen_comercializacion', $value);
                }
                if($attribute == 'costo_perdidas'){
                    $query->orderBy('parametros_mensuales.costo_perdidas', $value);
                }
                if($attribute == 'costo_restricciones'){
                    $query->orderBy('parametros_mensuales.costo_restricciones', $value);
                }
                if($attribute == 'estado'){
                    $query->orderBy('parametros_mensuales.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('parametros_mensuales.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('parametros_mensuales.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('parametros_mensuales.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('parametros_mensuales.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("parametros_mensuales.anio", "desc");
            $query->orderBy("parametros_mensuales.mes", "desc");
        }

        $parametros_mensuales = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $parametros_mensuales->items();
    
        return [
            'datos' => $data,
            'desde' => $parametros_mensuales->firstItem(),
            'hasta' => $parametros_mensuales->lastItem(),
            'por_pagina' => $parametros_mensuales->perPage(),
            'pagina_actual' => $parametros_mensuales->currentPage(),
            'ultima_pagina' => $parametros_mensuales->lastPage(),
            'total' => $parametros_mensuales->total(),
        ];
    }

    public static function cargar($id)
    {
        $parametros_mensuales = ParametroMensual::find($id);

        return [
            'id' => $parametros_mensuales->id,
            'anio' => $parametros_mensuales->anio,
            'mes' => $parametros_mensuales->mes,
            'valor_energia_bolsa' => $parametros_mensuales->valor_energia_bolsa,
            'valor_energia_mercado' => $parametros_mensuales->valor_energia_mercado,
            'costo_compra' => $parametros_mensuales->costo_compra,
            'cargo_transporte_nacional' => $parametros_mensuales->cargo_transporte_nacional,
            'cargo_transporte_local' => $parametros_mensuales->cargo_transporte_local,
            'margen_comercializacion' => $parametros_mensuales->margen_comercializacion,
            'costo_perdidas' => $parametros_mensuales->costo_perdidas,
            'costo_restricciones' => $parametros_mensuales->costo_restricciones,
            'estado' => $parametros_mensuales->estado,
            'usuario_creacion_id' => $parametros_mensuales->usuario_creacion_id,
            'usuario_creacion_nombre' => $parametros_mensuales->usuario_creacion_nombre,
            'usuario_modificacion_id' => $parametros_mensuales->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $parametros_mensuales->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($parametros_mensuales->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($parametros_mensuales->updated_at))->format("Y-m-d H:i:s")
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
        $parametros_mensuales = isset($dto['id']) ? ParametroMensual::find($dto['id']) : new ParametroMensual();

        // Guardar objeto original para auditoria
        $inversionistas_contactosOriginal = $parametros_mensuales->toJson();

        $parametros_mensuales->fill($dto);
        $guardado = $parametros_mensuales->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la compañia.", $parametros_mensuales);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $parametros_mensuales->id,
            'nombre_recurso' => ParametroMensual::class,
            'descripcion_recurso' => $parametros_mensuales->anio . '-' . $parametros_mensuales->mes ,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $inversionistas_contactosOriginal : $parametros_mensuales->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $parametros_mensuales->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return ParametroMensual::cargar($parametros_mensuales->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $parametros_mensuales = ParametroMensual::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $parametros_mensuales->id,
            'nombre_recurso' => ParametroMensual::class,
            'descripcion_recurso' => $parametros_mensuales->anio . '-' . $parametros_mensuales->mes ,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $parametros_mensuales->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $parametros_mensuales->delete();
    }
}
