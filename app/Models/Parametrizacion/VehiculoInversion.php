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

class VehiculoInversion extends Model
{
    use HasFactory;

    protected $table = 'vehiculos_inversion';

    protected $fillable = [
        'nombre',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('vehiculos_inversion')
            ->select(
                'vehiculos_inversion.id',
                'vehiculos_inversion.nombre',
                'vehiculos_inversion.estado',
                'vehiculos_inversion.usuario_creacion_id',
                'vehiculos_inversion.usuario_creacion_nombre',
                'vehiculos_inversion.usuario_modificacion_id',
                'vehiculos_inversion.usuario_modificacion_nombre',
            );

        $query->orderBy('nombre', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('vehiculos_inversion')
            ->select(
            'vehiculos_inversion.id',
            'vehiculos_inversion.nombre',
            'vehiculos_inversion.estado',
            'vehiculos_inversion.usuario_creacion_id',
            'vehiculos_inversion.usuario_creacion_nombre',
            'vehiculos_inversion.usuario_modificacion_id',
            'vehiculos_inversion.usuario_modificacion_nombre',
            'vehiculos_inversion.created_at as fecha_creacion',
            'vehiculos_inversion.updated_at as fecha_modificacion' ,
        );

        if(isset($dto['nombre'])){
            $query->where('vehiculos_inversion.nombre', 'like', '%' . $dto['nombre'] . '%');
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre'){
                    $query->orderBy('vehiculos_inversion.nombre', $value);
                }
                if($attribute == 'nombre'){
                    $query->orderBy('vehiculos_inversion.nombre', $value);
                }
                if($attribute == 'estado'){
                    $query->orderBy('vehiculos_inversion.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('vehiculos_inversion.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('vehiculos_inversion.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('vehiculos_inversion.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('vehiculos_inversion.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("vehiculos_inversion.updated_at", "desc");
        }

        $vehiculos_inversion = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $vehiculos_inversion->items();
    
        return [
            'datos' => $data,
            'desde' => $vehiculos_inversion->firstItem(),
            'hasta' => $vehiculos_inversion->lastItem(),
            'por_pagina' => $vehiculos_inversion->perPage(),
            'pagina_actual' => $vehiculos_inversion->currentPage(),
            'ultima_pagina' => $vehiculos_inversion->lastPage(),
            'total' => $vehiculos_inversion->total(),
        ];
    }

    public static function cargar($id)
    {
        $vehiculos_inversion = VehiculoInversion::find($id);

        return [
            'id' => $vehiculos_inversion->id,
            'nombre' => $vehiculos_inversion->nombre,
            'estado' => $vehiculos_inversion->estado,
            'usuario_creacion_id' => $vehiculos_inversion->usuario_creacion_id,
            'usuario_creacion_nombre' => $vehiculos_inversion->usuario_creacion_nombre,
            'usuario_modificacion_id' => $vehiculos_inversion->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $vehiculos_inversion->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($vehiculos_inversion->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($vehiculos_inversion->updated_at))->format("Y-m-d H:i:s")
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
        $vehiculos_inversion = isset($dto['id']) ? VehiculoInversion::find($dto['id']) : new VehiculoInversion();

        // Guardar objeto original para auditoria
        $inversionistas_contactosOriginal = $vehiculos_inversion->toJson();

        $vehiculos_inversion->fill($dto);
        $guardado = $vehiculos_inversion->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la compañia.", $vehiculos_inversion);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $vehiculos_inversion->id,
            'nombre_recurso' => VehiculoInversion::class,
            'descripcion_recurso' => $vehiculos_inversion->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $inversionistas_contactosOriginal : $vehiculos_inversion->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $vehiculos_inversion->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return VehiculoInversion::cargar($vehiculos_inversion->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $vehiculos_inversion = VehiculoInversion::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $vehiculos_inversion->id,
            'nombre_recurso' => VehiculoInversion::class,
            'descripcion_recurso' => $vehiculos_inversion->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $vehiculos_inversion->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $vehiculos_inversion->delete();
    }
}
