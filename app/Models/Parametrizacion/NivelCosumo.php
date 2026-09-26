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

class NivelCosumo extends Model
{
    use HasFactory;

    protected $table = 'niveles_consumo';

    protected $fillable = [
        'numero_nivel',
        'limite_inferior',
        'limite_superior',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('niveles_consumo')
            ->select(
                'niveles_consumo.id',
                'niveles_consumo.numero_nivel',
                'niveles_consumo.limite_inferior',
                'niveles_consumo.limite_superior',
                'niveles_consumo.estado',
            );
        $query->orderBy('numero_nivel', 'desc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('niveles_consumo')
            ->select(
           'niveles_consumo.id',
            'niveles_consumo.numero_nivel',
            'niveles_consumo.limite_inferior',
            'niveles_consumo.limite_superior',
            'niveles_consumo.estado',
            'niveles_consumo.usuario_creacion_id',
            'niveles_consumo.usuario_creacion_nombre',
            'niveles_consumo.usuario_modificacion_id',
            'niveles_consumo.usuario_modificacion_nombre',
            'niveles_consumo.created_at as fecha_creacion',
            'niveles_consumo.updated_at as fecha_modificacion',
        );

        if(isset($dto['nivel'])){
            $query->where('niveles_consumo.numero_nivel', $dto['nivel']);
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'numero_nivel'){
                    $query->orderBy('niveles_consumo.numero_nivel', $value);
                }              
                if($attribute == 'limite_inferior'){
                    $query->orderBy('niveles_consumo.limite_inferior', $value);
                }
                if($attribute == 'limite_superior'){
                    $query->orderBy('niveles_consumo.limite_superior', $value);
                }
                if($attribute == 'estado'){
                    $query->orderBy('niveles_consumo.estado', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('niveles_consumo.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('niveles_consumo.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('niveles_consumo.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("niveles_consumo.numero_nivel", "asc");
        }

        $NivelCosumo = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $NivelCosumo->items();
    
        return [
            'datos' => $data,
            'desde' => $NivelCosumo->firstItem(),
            'hasta' => $NivelCosumo->lastItem(),
            'por_pagina' => $NivelCosumo->perPage(),
            'pagina_actual' => $NivelCosumo->currentPage(),
            'ultima_pagina' => $NivelCosumo->lastPage(),
            'total' => $NivelCosumo->total(),
        ];
    }

    public static function cargar($id)
    {
        $NivelCosumo = NivelCosumo::find($id);

        return [
            'id' => $NivelCosumo->id,
            'numero_nivel' => $NivelCosumo->numero_nivel,
            'limite_inferior' => $NivelCosumo->limite_inferior,
            'limite_superior' => $NivelCosumo->limite_superior,
            'estado' => $NivelCosumo->estado,
            'usuario_creacion_id' => $NivelCosumo->usuario_creacion_id,
            'usuario_creacion_nombre' => $NivelCosumo->usuario_creacion_nombre,
            'usuario_modificacion_id' => $NivelCosumo->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $NivelCosumo->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($NivelCosumo->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($NivelCosumo->updated_at))->format("Y-m-d H:i:s")
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
        $NivelCosumo = isset($dto['id']) ? NivelCosumo::find($dto['id']) : new NivelCosumo();

        // Guardar objeto original para auditoria
        $DesembolsoProyectoOriginal = $NivelCosumo->toJson();

        $NivelCosumo->fill($dto);
        $guardado = $NivelCosumo->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la compañia.", $NivelCosumo);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $NivelCosumo->id,
            'nombre_recurso' => NivelCosumo::class,
            'descripcion_recurso' => $NivelCosumo->numero_nivel,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $DesembolsoProyectoOriginal : $NivelCosumo->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $NivelCosumo->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return NivelCosumo::cargar($NivelCosumo->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $NivelCosumo = NivelCosumo::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $NivelCosumo->id,
            'nombre_recurso' => NivelCosumo::class,
            'descripcion_recurso' => $NivelCosumo->numero_nivel,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $NivelCosumo->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $NivelCosumo->delete();
    }
}
