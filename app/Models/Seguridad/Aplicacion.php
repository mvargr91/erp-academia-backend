<?php

namespace App\Models\Seguridad;

use Exception;
use Carbon\Carbon;
use App\Enum\AccionAuditoriaEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\AuditoriaTabla;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Aplicacion extends Model
{
    protected $table = 'aplicaciones';

    protected $fillable = [
        'nombre',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    public static function obtenerColeccionLigera($dto){
        $query = DB::table('aplicaciones')
            ->select(
                'aplicaciones.id',
                'aplicaciones.nombre',
                'aplicaciones.estado',
            );
        $query->orderBy('aplicaciones.nombre', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $query = DB::table('aplicaciones')
            ->select(
                'aplicaciones.id',
                'aplicaciones.nombre',
                'aplicaciones.estado',
                'aplicaciones.usuario_creacion_id',
                'aplicaciones.usuario_creacion_nombre',
                'aplicaciones.usuario_modificacion_id',
                'aplicaciones.usuario_modificacion_nombre',
                'aplicaciones.created_at AS fecha_creacion',
                'aplicaciones.updated_at AS fecha_modificacion',
            );

        if(isset($dto['nombre'])){
            $query->where('aplicaciones.nombre', 'like', '%' . $dto['nombre'] . '%');
        }

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre'){
                    $query->orderBy('aplicaciones.nombre', $value);
                }
                if($attribute == 'estado'){
                    $query->orderBy('aplicaciones.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('aplicaciones.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('aplicaciones.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('aplicaciones.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('aplicaciones.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("aplicaciones.updated_at", "desc");
        }

        $aplicaciones = $query->paginate($dto['limite'] ?? 100);
        $datos = [];

        foreach ($aplicaciones ?? [] as $aplicacion){
            array_push($datos, $aplicacion);
        }

        $cantidadAplicaciones = count($aplicaciones);
        $to = isset($aplicaciones) && $cantidadAplicaciones > 0 ? $aplicaciones->currentPage() * $aplicaciones->perPage() : null;
        $to = isset($to) && isset($aplicaciones) && $to > $aplicaciones->total() && $cantidadAplicaciones > 0 ? $aplicaciones->total() : $to;
        $from = isset($to) && isset($aplicaciones) && $cantidadAplicaciones > 0 ?
            ( $aplicaciones->perPage() > $to ? 1 : ($to - $cantidadAplicaciones) + 1 )
            : null;
        return [
            'datos' => $datos,
            'desde' => $from,
            'hasta' => $to,
            'por_pagina' => isset($aplicaciones) && $cantidadAplicaciones > 0 ? +$aplicaciones->perPage() : 0,
            'pagina_actual' => isset($aplicaciones) && $cantidadAplicaciones > 0 ? $aplicaciones->currentPage() : 1,
            'ultima_pagina' => isset($aplicaciones) && $cantidadAplicaciones > 0 ? $aplicaciones->lastPage() : 0,
            'total' => isset($aplicaciones) && $cantidadAplicaciones > 0 ? $aplicaciones->total() : 0
        ];
    }

    public static function cargar($id)
    {
        $aplicacion = Aplicacion::find($id);
        return [
            'id' => $aplicacion->id,
            'nombre' => $aplicacion->nombre,
            'estado' => $aplicacion->estado,
            'usuario_creacion_id' => $aplicacion->usuario_creacion_id,
            'usuario_creacion_nombre' => $aplicacion->usuario_creacion_nombre,
            'usuario_modificacion_id' => $aplicacion->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $aplicacion->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($aplicacion->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($aplicacion->updated_at))->format("Y-m-d H:i:s")
        ];
    }

    public static function modificarOCrear($dto)
    {
        $user = Auth::user();
        $usuario = $user->usuario();

        if(!isset($dto['id'])){
            $dto['usuario_creacion_id'] = $usuario->id ?? ($dto['usuario_creacion_id'] ?? null);
            $dto['usuario_creacion_nombre'] = $usuario->nombre ?? ($dto['usuario_creacion_nombre'] ?? null);
        }
        if(isset($usuario) || isset($dto['usuario_modificacion_id'])){
            $dto['usuario_modificacion_id'] = $usuario->id ?? ($dto['usuario_modificacion_id'] ?? null);
            $dto['usuario_modificacion_nombre'] = $usuario->nombre ?? ($dto['usuario_modificacion_nombre'] ?? null);
        }

        // Consultar aplicación
        $aplicacion = isset($dto['id']) ? Aplicacion::find($dto['id']) : new Aplicacion();

        // Guardar objeto original para auditoria
        $aplicacionOriginal = $aplicacion->toJson();

        $aplicacion->fill($dto);
        $guardado = $aplicacion->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la aplicación.", $aplicacion);
        }


        // Guardar auditoria
        $auditoriaDto = [
            'id_recurso' => $aplicacion->id,
            'nombre_recurso' => Aplicacion::class,
            'descripcion_recurso' => $aplicacion->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $aplicacionOriginal : $aplicacion->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $aplicacion->toJson() : null
        ];
        
        AuditoriaTabla::crear($auditoriaDto);
        
        return Aplicacion::cargar($aplicacion->id);
    }

    public static function eliminar($id)
    {
        $aplicacion = Aplicacion::findOrFail($id);
        
        // Guardar auditoria
        $auditoriaDto = [
            'id_recurso' => $aplicacion->id,
            'nombre_recurso' => Aplicacion::class,
            'descripcion_recurso' => $aplicacion->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $aplicacion->toJson()
        ];
        AuditoriaTabla::crear($auditoriaDto);
        DB::table('aplicaciones')->where('id', $id)->delete();
        return $aplicacion->delete();
    }

    use HasFactory;
}
