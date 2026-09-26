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

class GestorDocumento extends Model
{
    use HasFactory;

    protected $table = 'gestores_documentos';

    protected $fillable = [
        'id_gestor',
        'id_lista_documento',
        'nombre_archivo',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('gestores_documentos')
            ->select(
                'gestores_documentos.id',
                'gestores_documentos.id_gestor',
                'gestores_documentos.id_lista_documento',
                'gestores_documentos.nombre_archivo',
                'gestores_documentos.estado',
            )->where('gestores_documentos.estado', 1);
        $query->orderBy('nombre_archivo', 'asc');
        return $query->get();
    }


    public static function obtenerColeccionLigeraTipo($dto){
     
        $query = DB::table('listas_documentos')
        ->leftJoin('gestores_documentos', function ($join) use ($dto) {
            $join->on('gestores_documentos.id_lista_documento', '=', 'listas_documentos.id')
                 ->where('gestores_documentos.id_gestor', $dto['id_gestor']);
        })
        ->select(
            'gestores_documentos.id',
            'gestores_documentos.id_gestor',
            'listas_documentos.id as id_lista_documento',            
            'listas_documentos.nombre',
            'listas_documentos.tipo_lista',
            DB::raw("COALESCE(gestores_documentos.nombre_archivo, '-') as nombre_archivo"),
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
                    $query->orderBy('gestores_documentos.nombre_archivo', $value);
                }              
                if($attribute == 'estado'){
                    $query->orderBy('gestores_documentos.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('gestores_documentos.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('gestores_documentos.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('gestores_documentos.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('gestores_documentos.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("gestores_documentos.updated_at", "desc");
        }

        $GestorDocumento = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $GestorDocumento->items();
    
        return [
            'datos' => $data,
            'desde' => $GestorDocumento->firstItem(),
            'hasta' => $GestorDocumento->lastItem(),
            'por_pagina' => $GestorDocumento->perPage(),
            'pagina_actual' => $GestorDocumento->currentPage(),
            'ultima_pagina' => $GestorDocumento->lastPage(),
            'total' => $GestorDocumento->total(),
        ];
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('gestores_documentos')
            ->select(
            'gestores_documentos.id',            
            'gestores_documentos.id_gestor',
            'gestores_documentos.id_lista_documento',
            'gestores_documentos.nombre_archivo',
            'gestores_documentos.estado',
            'gestores_documentos.usuario_creacion_id',
            'gestores_documentos.usuario_creacion_nombre',
            'gestores_documentos.usuario_modificacion_id',
            'gestores_documentos.usuario_modificacion_nombre',
        );

        if(isset($dto['nombre'])){
            $query->where('gestores_documentos.nombre', 'like', '%' . $dto['nombre'] . '%');
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'gestor'){
                    $query->orderBy('gestores_documentos.gestor', $value);
                }
                if($attribute == 'id_lista_documento'){
                    $query->orderBy('gestores_documentos.id_lista_documento', $value);
                }      
                if($attribute == 'nombre_archivo'){
                    $query->orderBy('gestores_documentos.nombre_archivo', $value);
                }            
                if($attribute == 'estado'){
                    $query->orderBy('gestores_documentos.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('gestores_documentos.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('gestores_documentos.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('gestores_documentos.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('gestores_documentos.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("gestores_documentos.updated_at", "desc");
        }

        $GestorDocumento = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $GestorDocumento->items();
    
        return [
            'datos' => $data,
            'desde' => $GestorDocumento->firstItem(),
            'hasta' => $GestorDocumento->lastItem(),
            'por_pagina' => $GestorDocumento->perPage(),
            'pagina_actual' => $GestorDocumento->currentPage(),
            'ultima_pagina' => $GestorDocumento->lastPage(),
            'total' => $GestorDocumento->total(),
        ];
    }

    public static function cargar($id)
    {
        $GestorDocumento = GestorDocumento::find($id);

        return [
            'id' => $GestorDocumento->id,
            'id_gestor' => $GestorDocumento->id_gestor,
            'id_lista_documento' => $GestorDocumento->id_lista_documento,
            'nombre_archivo' => $GestorDocumento->nombre_archivo,
            'estado' => $GestorDocumento->estado,
            'usuario_creacion_id' => $GestorDocumento->usuario_creacion_id,
            'usuario_creacion_nombre' => $GestorDocumento->usuario_creacion_nombre,
            'usuario_modificacion_id' => $GestorDocumento->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $GestorDocumento->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($GestorDocumento->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($GestorDocumento->updated_at))->format("Y-m-d H:i:s")
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


        // Consultar el documento gestor
        $GestorDocumento = isset($dto['id']) ? GestorDocumento::find($dto['id']) : new GestorDocumento();

        // Guardar objeto original para auditoria
        $GestorDocumentoOriginal = $GestorDocumento->toJson();
    
        $GestorDocumento->fill($dto);
        $guardado = $GestorDocumento->save();
    
        if (!$guardado) {
            throw new Exception("Ocurrió un error al intentar guardar el documento.");
        }    

        $auditoriaDto = [
            'id_recurso' => $GestorDocumento->id,
            'nombre_recurso' => GestorDocumento::class,
            'descripcion_recurso' => $GestorDocumento->nombre_archivo,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => $GestorDocumentoOriginal,
            'recurso_resultante' => $GestorDocumento->toJson()
        ];
        
        AuditoriaTabla::crear($auditoriaDto);
    
        return true;
        return GestorDocumento::cargar($GestorDocumento->id);
    }
    

    public static function eliminar($id, $gestor)
    {
        DB::beginTransaction();
        
        try {
            // Consultar el objeto
            $GestorDocumento = GestorDocumento::find($id);
            
            if (!$GestorDocumento) {
                return false; // Si no existe, no hacer nada
            }
            
            $rutaArchivo = "public/gestores/$gestor/" . $GestorDocumento->nombre_archivo;

            if (Storage::exists($rutaArchivo)) {
                Storage::delete($rutaArchivo);
            } else {
                dump("Archivo no encontrado:", $rutaArchivo);
            }
    
            // Guardar auditoría antes de eliminar
            $auditoriaDto = [
                'id_recurso' => $GestorDocumento->id,
                'nombre_recurso' => GestorDocumento::class,
                'descripcion_recurso' => $GestorDocumento->nombre_archivo,
                'accion' => AccionAuditoriaEnum::ELIMINAR,
                'recurso_original' => $GestorDocumento ? $GestorDocumento->toJson() : '{}'
            ];
            AuditoriaTabla::crear($auditoriaDto);
    
            // Eliminar el registro en la base de datos
            $GestorDocumento->delete();
    
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollback();
            throw new Exception("Error al eliminar el documento: " . $e->getMessage());
        }
    }
    
}
