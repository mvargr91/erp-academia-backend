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

class ProyectoFoto extends Model
{
    use HasFactory;

    protected $table = 'proyectos_fotos';

    protected $fillable = [
        'id_proyecto',
        'nombre_foto',
        'nombre_archivo_foto',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('proyectos_fotos')
            ->select(
                'proyectos_fotos.id',
                'proyectos_fotos.id_proyecto',
                'proyectos_fotos.nombre_foto',
                'proyectos_fotos.nombre_archivo_foto',
                'proyectos_fotos.usuario_creacion_id',
                'proyectos_fotos.usuario_creacion_nombre',
                'proyectos_fotos.usuario_modificacion_id',
                'proyectos_fotos.usuario_modificacion_nombre',
            );

        $query->orderBy('nombre', 'asc');
        return $query->get();
    }

    public static function obtenerColeccionLigeraXTipo($dto){
     
        $query = DB::table('proyectos_fotos')
            ->select(
                'proyectos_fotos.id',
                'proyectos_fotos.id_proyecto',
                'proyectos_fotos.nombre_foto',
                'proyectos_fotos.nombre_archivo_foto',
                'proyectos_fotos.usuario_creacion_id',
                'proyectos_fotos.usuario_creacion_nombre',
                'proyectos_fotos.usuario_modificacion_id',
                'proyectos_fotos.usuario_modificacion_nombre',
                'proyectos_fotos.created_at as fecha_creacion',
                'proyectos_fotos.updated_at as fecha_modificacion' ,
            );

        $query->orderBy('nombre', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('proyectos_fotos')
        ->join('proyectos', 'proyectos.id', 'proyectos_fotos.id_proyecto')
            ->select(
            'proyectos_fotos.id',
            'proyectos_fotos.id_proyecto',
            'proyectos_fotos.nombre_foto',
            'proyectos_fotos.nombre_archivo_foto',
            'proyectos_fotos.usuario_creacion_id',
            'proyectos_fotos.usuario_creacion_nombre',
            'proyectos_fotos.usuario_modificacion_id',
            'proyectos_fotos.usuario_modificacion_nombre',
        )->where('proyectos_fotos.id_proyecto', $dto['id_proyecto']);

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre_foto'){
                    $query->orderBy('proyectos_fotos.nombre_foto', $value);
                }
                if($attribute == 'nombre_archivo_foto'){
                    $query->orderBy('proyectos_fotos.nombre_archivo_foto', $value);
                }                
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('proyectos_fotos.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('proyectos_fotos.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('proyectos_fotos.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('proyectos_fotos.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("tipos_proyectos.nombre", "asc");
            $query->orderBy("etapas_proyectos.secuencia", "asc");
            $query->orderBy("proyectos_fotos.secuencia", "asc");
        }

        $proyectos_fotos = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $proyectos_fotos->items();
    
        return [
            'datos' => $data,
            'desde' => $proyectos_fotos->firstItem(),
            'hasta' => $proyectos_fotos->lastItem(),
            'por_pagina' => $proyectos_fotos->perPage(),
            'pagina_actual' => $proyectos_fotos->currentPage(),
            'ultima_pagina' => $proyectos_fotos->lastPage(),
            'total' => $proyectos_fotos->total(),
        ];
    }

    public static function cargar($id)
    {
        $proyectos_fotos = ProyectoFoto::find($id);

        return [
            'id' => $proyectos_fotos->id,
            'id_proyecto' => $proyectos_fotos->id_proyecto,
            'nombre_foto' => $proyectos_fotos->nombre_foto,
            'nombre_archivo_foto' => $proyectos_fotos->nombre_archivo_foto,
            'usuario_creacion_id' => $proyectos_fotos->usuario_creacion_id,
            'usuario_creacion_nombre' => $proyectos_fotos->usuario_creacion_nombre,
            'usuario_modificacion_id' => $proyectos_fotos->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $proyectos_fotos->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($proyectos_fotos->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($proyectos_fotos->updated_at))->format("Y-m-d H:i:s")
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
        $proyectos_fotos = isset($dto['id']) ? ProyectoFoto::find($dto['id']) : new ProyectoFoto();

        // Guardar objeto original para auditoria
        $inversionistas_contactosOriginal = $proyectos_fotos->toJson();

        $proyectos_fotos->fill($dto);
        $guardado = $proyectos_fotos->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la foto.", $proyectos_fotos);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $proyectos_fotos->id,
            'nombre_recurso' => ProyectoFoto::class,
            'descripcion_recurso' => $proyectos_fotos->nombre_foto,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $inversionistas_contactosOriginal : $proyectos_fotos->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $proyectos_fotos->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return ProyectoFoto::cargar($proyectos_fotos->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $proyectos_fotos = ProyectoFoto::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $proyectos_fotos->id,
            'nombre_recurso' => ProyectoFoto::class,
            'descripcion_recurso' => $proyectos_fotos->nombre_foto,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $proyectos_fotos->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $proyectos_fotos->delete();
    }
}
