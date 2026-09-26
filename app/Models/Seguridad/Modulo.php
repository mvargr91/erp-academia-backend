<?php

namespace App\Models\Seguridad;

use Carbon\Carbon;
use App\Enum\AccionAuditoriaEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\AuditoriaTabla;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Modulo extends Model
{
    protected $table = 'modulos';

    protected $fillable = [
        'nombre',
        'aplicacion_id',
        'icono_menu',
        'posicion',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    public static function obtenerColeccionLigera($dto){
        $query = DB::table('modulos')
            ->select(
                'modulos.id',
                'modulos.nombre',
                'modulos.estado',
            );
        $query->orderBy('modulos.nombre', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $query = DB::table('modulos')
            ->join('aplicaciones','aplicaciones.id','=','modulos.aplicacion_id')
            ->select(
                'modulos.id',
                'modulos.nombre',
                'modulos.aplicacion_id',
                'modulos.icono_menu',
                'modulos.posicion',
                'modulos.estado',
                'modulos.usuario_creacion_id',
                'modulos.usuario_creacion_nombre',
                'modulos.usuario_modificacion_id',
                'modulos.usuario_modificacion_nombre',
                'modulos.created_at AS fecha_creacion',
                'modulos.updated_at AS fecha_modificacion',
                'aplicaciones.nombre AS aplicacion',
            );

        if(isset($dto['nombre'])){
            $query->where('modulos.nombre', 'like', '%' . $dto['nombre'] . '%');
        }

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre'){
                    $query->orderBy('modulos.nombre', $value);
                }
                if($attribute == 'aplicacion'){
                    $query->orderBy('aplicaciones.nombre', $value);
                }
                if($attribute == 'posicion'){
                    $query->orderBy('modulos.posicion', $value);
                }
                if($attribute == 'estado'){
                    $query->orderBy('modulos.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('modulos.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('modulos.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('modulos.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('modulos.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("modulos.updated_at", "desc");
        }
        $modulos = $query->paginate($dto['limite'] ?? 100);
        $datos = [];

        foreach ($modulos ?? [] as $modulo){
            array_push($datos, $modulo);
        }

        $cantidadModulos = count($modulos);
        $to = isset($modulos) && $cantidadModulos > 0 ? $modulos->currentPage() * $modulos->perPage() : null;
        $to = isset($to) && isset($modulos) && $to > $modulos->total() && $cantidadModulos > 0 ? $modulos->total() : $to;
        $from = isset($to) && isset($modulos) && $cantidadModulos > 0 ?
            ( $modulos->perPage() > $to ? 1 : ($to - $cantidadModulos) + 1 )
            : null;
        return [
            'datos' => $datos,
            'desde' => $from,
            'hasta' => $to,
            'por_pagina' => isset($modulos) && $cantidadModulos > 0 ? +$modulos->perPage() : 0,
            'pagina_actual' => isset($modulos) && $cantidadModulos > 0 ? $modulos->currentPage() : 1,
            'ultima_pagina' => isset($modulos) && $cantidadModulos > 0 ? $modulos->lastPage() : 0,
            'total' => isset($modulos) && $cantidadModulos > 0 ? $modulos->total() : 0
        ];
    }

    public static function cargar($id)
    {
        $modulo = Modulo::find($id);
        return [
            'id' => $modulo->id,
            'nombre' => $modulo->nombre,
            'aplicacion_id' => $modulo->aplicacion_id,
            'icono_menu' => $modulo->icono_menu,
            'posicion' => $modulo->posicion,
            'estado' => $modulo->estado,
            'usuario_creacion_id' => $modulo->usuario_creacion_id,
            'usuario_creacion_nombre' => $modulo->usuario_creacion_nombre,
            'usuario_modificacion_id' => $modulo->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $modulo->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($modulo->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($modulo->updated_at))->format("Y-m-d H:i:s")
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

        // Consultar módulos
        $modulo = isset($dto['id']) ? Modulo::find($dto['id']) : new Modulo();

        // Guardar objeto original para auditoria
        $moduloOriginal = $modulo->toJson();

        $modulo->fill($dto);
        $guardado = $modulo->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar el módulo.", $modulo);
        }

        // Guardar auditoria
        $auditoriaDto = [
            'id_recurso' => $modulo->id,
            'nombre_recurso' => Modulo::class,
            'descripcion_recurso' => $modulo->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $moduloOriginal : $modulo->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $modulo->toJson() : null
        ];
        
        AuditoriaTabla::crear($auditoriaDto);
        
        return Modulo::cargar($modulo->id);
    }

    public static function eliminar($id)
    {
        $modulo = Modulo::find($id);

        // Guardar auditoria
        $auditoriaDto = [
            'id_recurso' => $modulo->id,
            'nombre_recurso' => Modulo::class,
            'descripcion_recurso' => $modulo->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $modulo->toJson()
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return $modulo->delete();
    }
    
    use HasFactory;
}
