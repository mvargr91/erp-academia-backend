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

class ParametroConstante extends Model
{
    protected $table = 'parametros_constantes';

    protected $fillable = [
        'codigo_parametro',
        'descripcion_parametro',
        'valor_parametro',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre'
    ];

    public static function obtenerColeccion($dto){
        $query = DB::table('parametros_constantes')
            ->select(
                'parametros_constantes.id',
                'parametros_constantes.codigo_parametro',
                'parametros_constantes.descripcion_parametro',
                'parametros_constantes.valor_parametro',
                'parametros_constantes.estado',
                'parametros_constantes.usuario_creacion_id',
                'parametros_constantes.usuario_creacion_nombre',
                'parametros_constantes.usuario_modificacion_id',
                'parametros_constantes.usuario_modificacion_nombre',
                'parametros_constantes.created_at AS fecha_creacion',
                'parametros_constantes.updated_at AS fecha_modificacion'
            );

        if(isset($dto['codigo_parametro'])){
            $query->where('parametros_constantes.codigo_parametro', 'like', '%' . $dto['codigo_parametro'] . '%');
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'codigo_parametro'){
                    $query->orderBy('parametros_constantes.codigo_parametro', $value);
                }
                if($attribute == 'descripcion_parametro'){
                    $query->orderBy('parametros_constantes.descripcion_parametro', $value);
                }
                if($attribute == 'valor_parametro'){
                    $query->orderBy('parametros_constantes.valor_parametro', $value);
                }
                if($attribute == 'estado'){
                    $query->orderBy('parametros_constantes.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('parametros_constantes.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('parametros_constantes.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('parametros_constantes.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('parametros_constantes.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("parametros_constantes.updated_at", "desc");
        }

        $parametros = $query->paginate($dto['limite'] ?? 100);
        $data = [];
        foreach ($parametros ?? [] as $parametro){
            array_push($data, $parametro);
        }

        $cantidadParametros = count($parametros);
        $to = isset($parametros) && $cantidadParametros > 0 ? $parametros->currentPage() * $parametros->perPage() : null;
        $to = isset($to) && isset($parametros) && $to > $parametros->total() && $cantidadParametros> 0 ? $parametros->total() : $to;
        $from = isset($to) && isset($parametros) && $cantidadParametros > 0 ?
            ( $parametros->perPage() > $to ? 1 : ($to - $cantidadParametros) + 1 )
            : null;
        return [
            'datos' => $data,
            'desde' => $from,
            'hasta' => $to,
            'por_pagina' => isset($parametros) && $cantidadParametros > 0 ? +$parametros->perPage() : 0,
            'pagina_actual' => isset($parametros) && $cantidadParametros > 0 ? $parametros->currentPage() : 1,
            'ultima_pagina' => isset($parametros) && $cantidadParametros > 0 ? $parametros->lastPage() : 0,
            'total' => isset($parametros) && $cantidadParametros > 0 ? $parametros->total() : 0
        ];
        return $parametros_constantes;
    }

    /**
     * Cargar los parametros del sistema
     * @return array
     */
    public static function cargarParametros(){
        $parametros = [];
        $variables = ParametroConstante::get();
        foreach ($variables ?? [] as $variable){
            $parametros[$variable->codigo_parametro] = $variable->valor_parametro;
        }

        return $parametros;
    }

    public static function cargar($id)
    {
        $parametro = ParametroConstante::find($id);

        return [
            'id' => $parametro->id,
            'codigo_parametro' => $parametro->codigo_parametro,
            'descripcion_parametro' =>$parametro->descripcion_parametro,
            'valor_parametro' => $parametro->valor_parametro,
            'estado' => $parametro->estado,
            'usuario_creacion_id' => $parametro->usuario_creacion_id,
            'usuario_creacion_nombre' => $parametro->usuario_creacion_nombre,
            'usuario_modificacion_id' => $parametro->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $parametro->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($parametro->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($parametro->updated_at))->format("Y-m-d H:i:s"),
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
        $parametro = isset($dto['id']) ? ParametroConstante::find($dto['id']) : new ParametroConstante();

        // Guardar objeto original para auditoria
        $parametroOriginal = $parametro->toJson();

        $parametro->fill($dto);
        $guardado = $parametro->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar el parametro.", $parametro);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $parametro->id,
            'nombre_recurso' => ParametroConstante::class,
            'descripcion_recurso' => $parametro->codigo_parametro,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $parametroOriginal : $parametro->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $parametro->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return ParametroConstante::cargar($parametro->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $parametro = ParametroConstante::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $parametro->id,
            'nombre_recurso' => ParametroConstante::class,
            'descripcion_recurso' => $parametro->codigo_parametro,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $parametro->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $parametro->delete();
    }
    
    use HasFactory;
}
