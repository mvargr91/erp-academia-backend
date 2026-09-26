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

class PrecioProyectado extends Model
{
    use HasFactory;

    protected $table = 'precios_proyectados';

    protected $fillable = [
        'anio',
        'precio_bolsa',
        'precio_mercado',
        'precio_comunidad',
        'precio_com_ppa',
        'precio_com_representado',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('precios_proyectados')
            ->select(
                'precios_proyectados.id',
                'precios_proyectados.anio',
                'precios_proyectados.precio_bolsa',
                'precios_proyectados.precio_mercado',
                'precios_proyectados.precio_comunidad',
                'precios_proyectados.precio_com_ppa',
                'precios_proyectados.precio_com_representado',
                'precios_proyectados.estado',
                'precios_proyectados.usuario_creacion_id',
                'precios_proyectados.usuario_creacion_nombre',
                'precios_proyectados.usuario_modificacion_id',
                'precios_proyectados.usuario_modificacion_nombre',
            );

        $query->orderBy('anio', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('precios_proyectados')
            ->select(
            'precios_proyectados.id',
            'precios_proyectados.anio',
            'precios_proyectados.precio_bolsa',
            'precios_proyectados.precio_mercado',
            'precios_proyectados.precio_comunidad',
            'precios_proyectados.precio_com_ppa',
            'precios_proyectados.precio_com_representado',
            'precios_proyectados.estado',
            'precios_proyectados.usuario_creacion_id',
            'precios_proyectados.usuario_creacion_nombre',
            'precios_proyectados.usuario_modificacion_id',
            'precios_proyectados.usuario_modificacion_nombre',
            'precios_proyectados.created_at as fecha_creacion',
            'precios_proyectados.updated_at as fecha_modificacion' ,
        );

        if(isset($dto['anio'])){
            $query->where('precios_proyectados.anio', 'like', '%' . $dto['anio'] . '%');
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'anio'){
                    $query->orderBy('precios_proyectados.anio', $value);
                }
                if($attribute == 'precio_bolsa'){
                    $query->orderBy('precios_proyectados.precio_bolsa', $value);
                }
                if($attribute == 'precio_mercado'){
                    $query->orderBy('precios_proyectados.precio_mercado', $value);
                }
                if($attribute == 'precio_comunidad'){
                    $query->orderBy('precios_proyectados.precio_comunidad', $value);
                }
                if($attribute == 'precio_com_ppa'){
                    $query->orderBy('precios_proyectados.precio_com_ppa', $value);
                }
                if($attribute == 'precio_com_representado'){
                    $query->orderBy('precios_proyectados.precio_com_representado', $value);
                }
                if($attribute == 'estado'){
                    $query->orderBy('precios_proyectados.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('precios_proyectados.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('precios_proyectados.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('precios_proyectados.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('precios_proyectados.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("precios_proyectados.updated_at", "desc");
        }

        $precios_proyectados = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $precios_proyectados->items();
    
        return [
            'datos' => $data,
            'desde' => $precios_proyectados->firstItem(),
            'hasta' => $precios_proyectados->lastItem(),
            'por_pagina' => $precios_proyectados->perPage(),
            'pagina_actual' => $precios_proyectados->currentPage(),
            'ultima_pagina' => $precios_proyectados->lastPage(),
            'total' => $precios_proyectados->total(),
        ];
    }

    public static function cargar($id)
    {
        $precios_proyectados = PrecioProyectado::find($id);

        return [
            'id' => $precios_proyectados->id,
            'anio' => $precios_proyectados->anio,
            'precio_bolsa' => $precios_proyectados->precio_bolsa,
            'precio_mercado' => $precios_proyectados->precio_mercado,
            'precio_comunidad' => $precios_proyectados->precio_comunidad,
            'precio_com_ppa' => $precios_proyectados->precio_com_ppa,
            'precio_com_representado' => $precios_proyectados->precio_com_representado,
            'estado' => $precios_proyectados->estado,
            'usuario_creacion_id' => $precios_proyectados->usuario_creacion_id,
            'usuario_creacion_nombre' => $precios_proyectados->usuario_creacion_nombre,
            'usuario_modificacion_id' => $precios_proyectados->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $precios_proyectados->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($precios_proyectados->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($precios_proyectados->updated_at))->format("Y-m-d H:i:s")
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
        $precios_proyectados = isset($dto['id']) ? PrecioProyectado::find($dto['id']) : new PrecioProyectado();

        // Guardar objeto original para auditoria
        $inversionistas_contactosOriginal = $precios_proyectados->toJson();

        $precios_proyectados->fill($dto);
        $guardado = $precios_proyectados->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la condición plazo.", $precios_proyectados);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $precios_proyectados->id,
            'nombre_recurso' => PrecioProyectado::class,
            'descripcion_recurso' => $precios_proyectados->anio,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $inversionistas_contactosOriginal : $precios_proyectados->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $precios_proyectados->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return PrecioProyectado::cargar($precios_proyectados->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $precios_proyectados = PrecioProyectado::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $precios_proyectados->id,
            'nombre_recurso' => PrecioProyectado::class,
            'descripcion_recurso' => $precios_proyectados->anio,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $precios_proyectados->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $precios_proyectados->delete();
    }
}
