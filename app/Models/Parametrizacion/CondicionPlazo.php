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

class CondicionPlazo extends Model
{
    use HasFactory;

    protected $table = 'condiciones_plazo';

    protected $fillable = [
        'nombre',
        'plazo_capital',
        'tasa_interes_inversion',
        'porcentaje_inversion_externa',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('condiciones_plazo')
            ->select(
                'condiciones_plazo.id',
                'condiciones_plazo.nombre',
                'condiciones_plazo.plazo_capital',
                'condiciones_plazo.tasa_interes_inversion',
                'condiciones_plazo.porcentaje_inversion_externa',
                'condiciones_plazo.estado',
                'condiciones_plazo.usuario_creacion_id',
                'condiciones_plazo.usuario_creacion_nombre',
                'condiciones_plazo.usuario_modificacion_id',
                'condiciones_plazo.usuario_modificacion_nombre',
            );

        $query->orderBy('nombre', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('condiciones_plazo')
            ->select(
            'condiciones_plazo.id',
            'condiciones_plazo.nombre',
            'condiciones_plazo.plazo_capital',
            'condiciones_plazo.tasa_interes_inversion',
            'condiciones_plazo.porcentaje_inversion_externa',
            'condiciones_plazo.estado',
            'condiciones_plazo.usuario_creacion_id',
            'condiciones_plazo.usuario_creacion_nombre',
            'condiciones_plazo.usuario_modificacion_id',
            'condiciones_plazo.usuario_modificacion_nombre',
        );

        if(isset($dto['nombre'])){
            $query->where('condiciones_plazo.nombre', 'like', '%' . $dto['nombre'] . '%');
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre'){
                    $query->orderBy('condiciones_plazo.nombre', $value);
                }
                if($attribute == 'plazo_capital'){
                    $query->orderBy('condiciones_plazo.plazo_capital', $value);
                }
                if($attribute == 'tasa_interes_inversion'){
                    $query->orderBy('condiciones_plazo.tasa_interes_inversion', $value);
                }
                if($attribute == 'porcentaje_inversion_externa'){
                    $query->orderBy('condiciones_plazo.porcentaje_inversion_externa', $value);
                }
                if($attribute == 'estado'){
                    $query->orderBy('condiciones_plazo.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('condiciones_plazo.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('condiciones_plazo.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('condiciones_plazo.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('condiciones_plazo.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("condiciones_plazo.updated_at", "desc");
        }

        $condiciones_plazo = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $condiciones_plazo->items();
    
        return [
            'datos' => $data,
            'desde' => $condiciones_plazo->firstItem(),
            'hasta' => $condiciones_plazo->lastItem(),
            'por_pagina' => $condiciones_plazo->perPage(),
            'pagina_actual' => $condiciones_plazo->currentPage(),
            'ultima_pagina' => $condiciones_plazo->lastPage(),
            'total' => $condiciones_plazo->total(),
        ];
    }

    public static function cargar($id)
    {
        $condiciones_plazo = CondicionPlazo::find($id);

        return [
            'id' => $condiciones_plazo->id,
            'nombre' => $condiciones_plazo->nombre,
            'plazo_capital' => $condiciones_plazo->plazo_capital,
            'tasa_interes_inversion' => $condiciones_plazo->tasa_interes_inversion,
            'porcentaje_inversion_externa' => $condiciones_plazo->porcentaje_inversion_externa,
            'estado' => $condiciones_plazo->estado,
            'usuario_creacion_id' => $condiciones_plazo->usuario_creacion_id,
            'usuario_creacion_nombre' => $condiciones_plazo->usuario_creacion_nombre,
            'usuario_modificacion_id' => $condiciones_plazo->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $condiciones_plazo->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($condiciones_plazo->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($condiciones_plazo->updated_at))->format("Y-m-d H:i:s")
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
        $condiciones_plazo = isset($dto['id']) ? CondicionPlazo::find($dto['id']) : new CondicionPlazo();

        // Guardar objeto original para auditoria
        $inversionistas_contactosOriginal = $condiciones_plazo->toJson();

        $condiciones_plazo->fill($dto);
        $guardado = $condiciones_plazo->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la condición plazo.", $condiciones_plazo);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $condiciones_plazo->id,
            'nombre_recurso' => CondicionPlazo::class,
            'descripcion_recurso' => $condiciones_plazo->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $inversionistas_contactosOriginal : $condiciones_plazo->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $condiciones_plazo->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return CondicionPlazo::cargar($condiciones_plazo->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $condiciones_plazo = CondicionPlazo::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $condiciones_plazo->id,
            'nombre_recurso' => CondicionPlazo::class,
            'descripcion_recurso' => $condiciones_plazo->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $condiciones_plazo->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $condiciones_plazo->delete();
    }
}
