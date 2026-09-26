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
use Illuminate\Support\Facades\Storage;

class ProyectoDocumento extends Model
{
    use HasFactory;

    protected $table = 'proyectos_documentos';

    protected $fillable = [
        'id_proyecto',
        'id_lista_documento',
        'nombre_archivo',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('proyectos_documentos')
            ->select(
                'proyectos_documentos.id',
                'proyectos_documentos.id_proyecto',
                'proyectos_documentos.id_lista_documento',
                'proyectos_documentos.nombre_archivo',
                'proyectos_documentos.estado',
            )->where('proyectos_documentos.estado', 1);
        $query->orderBy('nombre', 'asc');
        return $query->get();
    }


    public static function obtenerColeccionLigeraTipo($dto){
     
        $query = DB::table('listas_documentos')
        ->leftJoin('proyectos_documentos', function ($join) use ($dto) {
            $join->on('proyectos_documentos.id_lista_documento', '=', 'listas_documentos.id')
                 ->where('proyectos_documentos.id_proyecto', $dto['id_proyecto']);
        })
        ->select(
            'proyectos_documentos.id',
            'proyectos_documentos.id_proyecto',
            'listas_documentos.id as id_lista_documento',            
            'listas_documentos.nombre',
            'listas_documentos.tipo_lista',
            DB::raw("COALESCE(proyectos_documentos.nombre_archivo, '-') as nombre_archivo"),
            'listas_documentos.estado',
            'listas_documentos.usuario_creacion_id',
            'listas_documentos.usuario_creacion_nombre',
            'listas_documentos.usuario_modificacion_id',
            'listas_documentos.usuario_modificacion_nombre'
        )
        ->where('listas_documentos.tipo_lista', $dto['tipo_lista']);

        if(isset($dto['nombre'])){
            $query->where('listas_documentos.nombre', 'like', '%' . $dto['nombre'] . '%');
        }
        
        if (isset($dto['ordenar_por']) && is_array($dto['ordenar_por']) && count($dto['ordenar_por']) > 0) {
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre'){
                    $query->orderBy('listas_documentos.nombre', $value);
                }
                if($attribute == 'nombre_archivo'){
                    $query->orderBy('proyectos_documentos.nombre_archivo', $value);
                }              
                if($attribute == 'estado'){
                    $query->orderBy('proyectos_documentos.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('proyectos_documentos.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('proyectos_documentos.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('proyectos_documentos.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('proyectos_documentos.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("proyectos_documentos.updated_at", "desc");
        }

        $ProyectoDocumento = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $ProyectoDocumento->items();
    
        return [
            'datos' => $data,
            'desde' => $ProyectoDocumento->firstItem(),
            'hasta' => $ProyectoDocumento->lastItem(),
            'por_pagina' => $ProyectoDocumento->perPage(),
            'pagina_actual' => $ProyectoDocumento->currentPage(),
            'ultima_pagina' => $ProyectoDocumento->lastPage(),
            'total' => $ProyectoDocumento->total(),
        ];
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('proyectos_documentos')
            ->select(
            'proyectos_documentos.id',            
            'proyectos_documentos.id_proyecto',
            'proyectos_documentos.id_lista_documento',
            'proyectos_documentos.nombre_archivo',
            'proyectos_documentos.estado',
            'proyectos_documentos.usuario_creacion_id',
            'proyectos_documentos.usuario_creacion_nombre',
            'proyectos_documentos.usuario_modificacion_id',
            'proyectos_documentos.usuario_modificacion_nombre',
        );

        if(isset($dto['nombre'])){
            $query->where('proyectos_documentos.nombre', 'like', '%' . $dto['nombre'] . '%');
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'proyecto'){
                    $query->orderBy('proyectos_documentos.proyecto', $value);
                }
                if($attribute == 'id_lista_documento'){
                    $query->orderBy('proyectos_documentos.id_lista_documento', $value);
                }      
                if($attribute == 'nombre_archivo'){
                    $query->orderBy('proyectos_documentos.nombre_archivo', $value);
                }            
                if($attribute == 'estado'){
                    $query->orderBy('proyectos_documentos.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('proyectos_documentos.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('proyectos_documentos.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('proyectos_documentos.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('proyectos_documentos.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("proyectos_documentos.updated_at", "desc");
        }

        $ProyectoDocumento = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $ProyectoDocumento->items();
    
        return [
            'datos' => $data,
            'desde' => $ProyectoDocumento->firstItem(),
            'hasta' => $ProyectoDocumento->lastItem(),
            'por_pagina' => $ProyectoDocumento->perPage(),
            'pagina_actual' => $ProyectoDocumento->currentPage(),
            'ultima_pagina' => $ProyectoDocumento->lastPage(),
            'total' => $ProyectoDocumento->total(),
        ];
    }

    public static function cargar($id)
    {
        $ProyectoDocumento = ProyectoDocumento::find($id);

        return [
            'id' => $ProyectoDocumento->id,
            'id_proyecto' => $ProyectoDocumento->id_proyecto,
            'id_lista_documento' => $ProyectoDocumento->id_lista_documento,
            'nombre_archivo' => $ProyectoDocumento->nombre_archivo,
            'estado' => $ProyectoDocumento->estado,
            'usuario_creacion_id' => $ProyectoDocumento->usuario_creacion_id,
            'usuario_creacion_nombre' => $ProyectoDocumento->usuario_creacion_nombre,
            'usuario_modificacion_id' => $ProyectoDocumento->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $ProyectoDocumento->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($ProyectoDocumento->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($ProyectoDocumento->updated_at))->format("Y-m-d H:i:s")
        ];
    }

    public static function modificarOCrear($dto)
    {
        $user = Auth::user();
        $usuario = $user ? $user->usuario() : null; 
    
        if (!isset($dto['id'])) {
            $dto['usuario_creacion_id'] = $usuario->id ?? ($dto['usuario_creacion_id'] ?? null);
            $dto['usuario_creacion_nombre'] = $usuario->nombre ?? ($dto['usuario_creacion_nombre'] ?? null);
        }
        if ($usuario || isset($dto['usuario_modificacion_id'])) {
            $dto['usuario_modificacion_id'] = $usuario->id ?? ($dto['usuario_modificacion_id'] ?? null);
            $dto['usuario_modificacion_nombre'] = $usuario->nombre ?? ($dto['usuario_modificacion_nombre'] ?? null);
        }


        // Consultar el documento proyecto
        $ProyectoDocumento = isset($dto['id']) ? ProyectoDocumento::find($dto['id']) : new ProyectoDocumento();

        // Guardar objeto original para auditoria
        $ProyectoDocumentoOriginal = $ProyectoDocumento->toJson();
    
        $ProyectoDocumento->fill($dto);
        $guardado = $ProyectoDocumento->save();
    
        if (!$guardado) {
            throw new Exception("Ocurrió un error al intentar guardar el documento.");
        }    

        $auditoriaDto = [
            'id_recurso' => $ProyectoDocumento->id,
            'nombre_recurso' => ProyectoDocumento::class,
            'descripcion_recurso' => $ProyectoDocumento->nombre_archivo,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => $ProyectoDocumentoOriginal,
            'recurso_resultante' => $ProyectoDocumento->toJson()
        ];
        
        AuditoriaTabla::crear($auditoriaDto);
    
        return true;
        return ProyectoDocumento::cargar($ProyectoDocumento->id);
    }
    

    public static function eliminar($id, $proyecto)
    {
        DB::beginTransaction();
        
        try {
            // Consultar el objeto
            $ProyectoDocumento = ProyectoDocumento::find($id);
            
            if (!$ProyectoDocumento) {
                return false; // Si no existe, no hacer nada
            }
            
            $rutaArchivo = "public/proyectos/$proyecto/" . $ProyectoDocumento->nombre_archivo;

            if (Storage::exists($rutaArchivo)) {
                Storage::delete($rutaArchivo);
            } else {
                dump("Archivo no encontrado:", $rutaArchivo);
            }
    
            // Guardar auditoría antes de eliminar
            $auditoriaDto = [
                'id_recurso' => $ProyectoDocumento->id,
                'nombre_recurso' => ProyectoDocumento::class,
                'descripcion_recurso' => $ProyectoDocumento->nombre_archivo,
                'accion' => AccionAuditoriaEnum::ELIMINAR,
                'recurso_original' => $ProyectoDocumento ? $ProyectoDocumento->toJson() : '{}'
            ];
            AuditoriaTabla::crear($auditoriaDto);
    
            // Eliminar el registro en la base de datos
            $ProyectoDocumento->delete();
    
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollback();
            throw new Exception("Error al eliminar el documento: " . $e->getMessage());
        }
    }
    
}
