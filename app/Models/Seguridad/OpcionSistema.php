<?php

namespace App\Models\Seguridad;

use Carbon\Carbon;
use App\Enum\AccionAuditoriaEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\AuditoriaTabla;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OpcionSistema extends Model
{
    protected $table = 'opciones_del_sistema';

    protected $fillable = [
        'nombre',
        'modulo_id',
        'posicion',
        'icono_menu',
        'url',
        'url_ayuda',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    public static function obtenerColeccionLigera($dto){
        $query = DB::table('opciones_del_sistema')
            ->select(
                'opciones_del_sistema.id',
                'opciones_del_sistema.nombre',
                'opciones_del_sistema.estado',
            );
        $query->orderBy('opciones_del_sistema.nombre', 'asc');
        if(isset($dto['modulo_id'])){
            $query->where('opciones_del_sistema.modulo_id', '=',$dto['modulo_id']);
        }
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $query = DB::table('opciones_del_sistema')
            ->join('modulos','modulos.id','=','opciones_del_sistema.modulo_id')
            ->select(
                'opciones_del_sistema.id',
                'opciones_del_sistema.nombre',
                'opciones_del_sistema.modulo_id',
                'opciones_del_sistema.posicion',
                'opciones_del_sistema.icono_menu',
                'opciones_del_sistema.url',
                'opciones_del_sistema.url_ayuda',
                'opciones_del_sistema.estado',
                'opciones_del_sistema.usuario_creacion_id',
                'opciones_del_sistema.usuario_creacion_nombre',
                'opciones_del_sistema.usuario_modificacion_id',
                'opciones_del_sistema.usuario_modificacion_nombre',
                'opciones_del_sistema.created_at AS fecha_creacion',
                'opciones_del_sistema.updated_at AS fecha_modificacion',
                'modulos.nombre AS modulo',
            );

        if(isset($dto['nombre'])){
            $query->where('opciones_del_sistema.nombre', 'like', '%' . $dto['nombre'] . '%');
        }

        if(isset($dto['modulo'])){
            $query->where('modulos.id', '=', $dto['modulo']);
        }

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre'){
                    $query->orderBy('opciones_del_sistema.nombre', $value);
                }
                if($attribute == 'modulo'){
                    $query->orderBy('modulos.nombre', $value);
                }
                if($attribute == 'posicion'){
                    $query->orderBy('opciones_del_sistema.posicion', $value);
                }
                if($attribute == 'icono_menu'){
                    $query->orderBy('opciones_del_sistema.icono_menu', $value);
                }
                if($attribute == 'url'){
                    $query->orderBy('opciones_del_sistema.url', $value);
                }
                if($attribute == 'url_ayuda'){
                    $query->orderBy('opciones_del_sistema.url_ayuda', $value);
                }
                if($attribute == 'estado'){
                    $query->orderBy('opciones_del_sistema.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('opciones_del_sistema.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('opciones_del_sistema.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('opciones_del_sistema.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('opciones_del_sistema.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("opciones_del_sistema.updated_at", "asc");
        }

        $opcionesSistema = $query->paginate($dto['limite'] ?? 100);
        $datos = [];

        foreach ($opcionesSistema ?? [] as $opcionSistema){
            array_push($datos, $opcionSistema);
        }

        $cantidadOpcionesSistema = count($opcionesSistema);
        $to = isset($opcionesSistema) && $cantidadOpcionesSistema > 0 ? $opcionesSistema->currentPage() * $opcionesSistema->perPage() : null;
        $to = isset($to) && isset($opcionesSistema) && $to > $opcionesSistema->total() && $cantidadOpcionesSistema > 0 ? $opcionesSistema->total() : $to;
        $from = isset($to) && isset($opcionesSistema) && $cantidadOpcionesSistema > 0 ?
            ( $opcionesSistema->perPage() > $to ? 1 :($to - $cantidadOpcionesSistema) + 1 ) 
            : null;
        return [
            'datos' => $datos,
            'desde' => $from,
            'hasta' => $to,
            'por_pagina' => isset($opcionesSistema) && $cantidadOpcionesSistema > 0 ? +$opcionesSistema->perPage() : 0,
            'pagina_actual' => isset($opcionesSistema) && $cantidadOpcionesSistema > 0 ? $opcionesSistema->currentPage() : 1,
            'ultima_pagina' => isset($opcionesSistema) && $cantidadOpcionesSistema > 0 ? $opcionesSistema->lastPage() : 0,
            'total' => isset($opcionesSistema) && $cantidadOpcionesSistema > 0 ? $opcionesSistema->total() : 0,
            'trial' => $opcionesSistema->perPage(),
        ];
    }

    public static function cargar($id)
    {
        $opcionSistema = OpcionSistema::find($id);
        return [
            'id' => $opcionSistema->id,
            'nombre' => $opcionSistema->nombre,
            'modulo_id' => $opcionSistema->modulo_id,
            'posicion' => $opcionSistema->posicion,
            'icono_menu' => $opcionSistema->icono_menu,
            'url' => $opcionSistema->url,
            'url_ayuda' => $opcionSistema->url_ayuda,
            'estado' => $opcionSistema->estado,
            'usuario_creacion_id' => $opcionSistema->usuario_creacion_id,
            'usuario_creacion_nombre' => $opcionSistema->usuario_creacion_nombre,
            'usuario_modificacion_id' => $opcionSistema->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $opcionSistema->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($opcionSistema->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($opcionSistema->updated_at))->format("Y-m-d H:i:s")
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
        $opcionSistema = isset($dto['id']) ? OpcionSistema::find($dto['id']) : new OpcionSistema();

        // Guardar objeto original para auditoria
        $opcionSistemaOriginal = $opcionSistema->toJson();

        $opcionSistema->fill($dto);
        $guardado = $opcionSistema->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar el módulo.", $opcionSistema);
        }


        // Guardar auditoria
        $auditoriaDto = [
            'id_recurso' => $opcionSistema->id,
            'nombre_recurso' => OpcionSistema::class,
            'descripcion_recurso' => $opcionSistema->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $opcionSistemaOriginal : $opcionSistema->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $opcionSistema->toJson() : null
        ];
        
        AuditoriaTabla::crear($auditoriaDto);
        
        return OpcionSistema::cargar($opcionSistema->id);
    }

    public static function eliminar($id)
    {
        $opcionSistema = OpcionSistema::find($id);

        // Guardar auditoria
        $auditoriaDto = [
            'id_recurso' => $opcionSistema->id,
            'nombre_recurso' => OpcionSistema::class,
            'descripcion_recurso' => $opcionSistema->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $opcionSistema->toJson()
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return $opcionSistema->delete();
    }

    use HasFactory;
}
