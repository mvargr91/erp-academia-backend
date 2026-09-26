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

class Ciudad extends Model
{
    use HasFactory;

    protected $table = 'ciudades';

    protected $fillable = [
        'nombre',
        'nombre_departamento',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('ciudades')
            ->select(
                'ciudades.id',
                'ciudades.nombre',
                'ciudades.nombre_departamento',
                'ciudades.estado',
            );
        $query->orderBy('nombre', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('ciudades')
            ->select(
            'ciudades.id',            
            'ciudades.nombre',
            'ciudades.nombre_departamento',
            'ciudades.estado',
            'ciudades.usuario_creacion_id',
            'ciudades.usuario_creacion_nombre',
            'ciudades.usuario_modificacion_id',
            'ciudades.usuario_modificacion_nombre',
        );

        if(isset($dto['nombre'])){
            $query->where('ciudades.nombre', 'like', '%' . $dto['nombre'] . '%');
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre'){
                    $query->orderBy('ciudades.nombre', $value);
                }
                if($attribute == 'numero_nit'){
                    $query->orderBy('ciudades.nombre_departamento', $value);
                }              
                if($attribute == 'estado'){
                    $query->orderBy('ciudades.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('ciudades.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('ciudades.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('ciudades.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('ciudades.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("ciudades.updated_at", "desc");
        }

        $Ciudad = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $Ciudad->items();
    
        return [
            'datos' => $data,
            'desde' => $Ciudad->firstItem(),
            'hasta' => $Ciudad->lastItem(),
            'por_pagina' => $Ciudad->perPage(),
            'pagina_actual' => $Ciudad->currentPage(),
            'ultima_pagina' => $Ciudad->lastPage(),
            'total' => $Ciudad->total(),
        ];
    }

    public static function cargar($id)
    {
        $Ciudad = Ciudad::find($id);

        return [
            'id' => $Ciudad->id,
            'nombre' => $Ciudad->nombre,
            'nombre_departamento' => $Ciudad->nombre_departamento,
            'estado' => $Ciudad->estado,
            'usuario_creacion_id' => $Ciudad->usuario_creacion_id,
            'usuario_creacion_nombre' => $Ciudad->usuario_creacion_nombre,
            'usuario_modificacion_id' => $Ciudad->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $Ciudad->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($Ciudad->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($Ciudad->updated_at))->format("Y-m-d H:i:s")
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
        $Ciudad = isset($dto['id']) ? Ciudad::find($dto['id']) : new Ciudad();

        // Guardar objeto original para auditoria
        $CiudadOriginal = $Ciudad->toJson();

        $Ciudad->fill($dto);
        $guardado = $Ciudad->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la compañia.", $Ciudad);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $Ciudad->id,
            'nombre_recurso' => Ciudad::class,
            'descripcion_recurso' => $Ciudad->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $CiudadOriginal : $Ciudad->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $Ciudad->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return Ciudad::cargar($Ciudad->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $Ciudad = Ciudad::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $Ciudad->id,
            'nombre_recurso' => Ciudad::class,
            'descripcion_recurso' => $Ciudad->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $Ciudad->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $Ciudad->delete();
    }
}
