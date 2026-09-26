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

class CompaniaDocumento extends Model
{
    use HasFactory;

    protected $table = 'companias_documentos';

    protected $fillable = [
        'id_compania',
        'id_lista_documento',
        'nombre_archivo',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('companias_documentos')
            ->select(
                'companias_documentos.id',
                'companias_documentos.id_compania',
                'companias_documentos.id_lista_documento',
                'companias_documentos.nombre_archivo',
                'companias_documentos.estado',
            )->where('companias_documentos.estado', 1);
        $query->orderBy('nombre_archivo', 'asc');
        return $query->get();
    }


    public static function obtenerColeccionLigeraTipo($dto){
     
        $query = DB::table('listas_documentos')
        ->leftJoin('companias_documentos', function ($join) use ($dto) {
            $join->on('companias_documentos.id_lista_documento', '=', 'listas_documentos.id')
                 ->where('companias_documentos.id_compania', $dto['id_compania']);
        })
        ->select(
            'companias_documentos.id',
            'companias_documentos.id_compania',
            'listas_documentos.id as id_lista_documento',            
            'listas_documentos.nombre',
            'listas_documentos.tipo_lista',
            DB::raw("COALESCE(companias_documentos.nombre_archivo, '-') as nombre_archivo"),
            'listas_documentos.estado',
            'listas_documentos.usuario_creacion_id',
            'listas_documentos.usuario_creacion_nombre',
            'listas_documentos.usuario_modificacion_id',
            'listas_documentos.usuario_modificacion_nombre'
        )
        ->where('listas_documentos.tipo_lista', $dto['tipo_lista']);

        if(isset($dto['nombre'])){
            $query->where('companias_documentos.nombre', 'like', '%' . $dto['nombre'] . '%');
        }
        
        if (isset($dto['ordenar_por']) && is_array($dto['ordenar_por']) && count($dto['ordenar_por']) > 0) {
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre'){
                    $query->orderBy('companias_documentos.nombre', $value);
                }
                if($attribute == 'numero_nit'){
                    $query->orderBy('companias_documentos.tipo_lista', $value);
                }              
                if($attribute == 'estado'){
                    $query->orderBy('companias_documentos.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('companias_documentos.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('companias_documentos.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('companias_documentos.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('companias_documentos.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("companias_documentos.updated_at", "desc");
        }

        $CompaniaDocumento = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $CompaniaDocumento->items();
    
        return [
            'datos' => $data,
            'desde' => $CompaniaDocumento->firstItem(),
            'hasta' => $CompaniaDocumento->lastItem(),
            'por_pagina' => $CompaniaDocumento->perPage(),
            'pagina_actual' => $CompaniaDocumento->currentPage(),
            'ultima_pagina' => $CompaniaDocumento->lastPage(),
            'total' => $CompaniaDocumento->total(),
        ];
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('companias_documentos')
            ->select(
            'companias_documentos.id',            
            'companias_documentos.id_compania',
            'companias_documentos.id_lista_documento',
            'companias_documentos.nombre_archivo',
            'companias_documentos.estado',
            'companias_documentos.usuario_creacion_id',
            'companias_documentos.usuario_creacion_nombre',
            'companias_documentos.usuario_modificacion_id',
            'companias_documentos.usuario_modificacion_nombre',
        );

        if(isset($dto['nombre'])){
            $query->where('companias_documentos.nombre', 'like', '%' . $dto['nombre'] . '%');
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'compania'){
                    $query->orderBy('companias_documentos.compania', $value);
                }
                if($attribute == 'id_lista_documento'){
                    $query->orderBy('companias_documentos.id_lista_documento', $value);
                }      
                if($attribute == 'nombre_archivo'){
                    $query->orderBy('companias_documentos.nombre_archivo', $value);
                }            
                if($attribute == 'estado'){
                    $query->orderBy('companias_documentos.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('companias_documentos.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('companias_documentos.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('companias_documentos.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('companias_documentos.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("companias_documentos.updated_at", "desc");
        }

        $CompaniaDocumento = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $CompaniaDocumento->items();
    
        return [
            'datos' => $data,
            'desde' => $CompaniaDocumento->firstItem(),
            'hasta' => $CompaniaDocumento->lastItem(),
            'por_pagina' => $CompaniaDocumento->perPage(),
            'pagina_actual' => $CompaniaDocumento->currentPage(),
            'ultima_pagina' => $CompaniaDocumento->lastPage(),
            'total' => $CompaniaDocumento->total(),
        ];
    }

    public static function cargar($id)
    {
        $CompaniaDocumento = CompaniaDocumento::find($id);

        return [
            'id' => $CompaniaDocumento->id,
            'id_compania' => $CompaniaDocumento->id_compania,
            'id_lista_documento' => $CompaniaDocumento->id_lista_documento,
            'nombre_archivo' => $CompaniaDocumento->nombre_archivo,
            'estado' => $CompaniaDocumento->estado,
            'usuario_creacion_id' => $CompaniaDocumento->usuario_creacion_id,
            'usuario_creacion_nombre' => $CompaniaDocumento->usuario_creacion_nombre,
            'usuario_modificacion_id' => $CompaniaDocumento->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $CompaniaDocumento->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($CompaniaDocumento->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($CompaniaDocumento->updated_at))->format("Y-m-d H:i:s")
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


        // Consultar el documento compañia
        $CompaniaDocumento = isset($dto['id']) ? CompaniaDocumento::find($dto['id']) : new CompaniaDocumento();

        // Guardar objeto original para auditoria
        $CompaniaDocumentoOriginal = $CompaniaDocumento->toJson();
    
        $CompaniaDocumento->fill($dto);
        $guardado = $CompaniaDocumento->save();
    
        if (!$guardado) {
            throw new Exception("Ocurrió un error al intentar guardar el documento.");
        }    

        $auditoriaDto = [
            'id_recurso' => $CompaniaDocumento->id,
            'nombre_recurso' => CompaniaDocumento::class,
            'descripcion_recurso' => $CompaniaDocumento->nombre_archivo,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => $CompaniaDocumentoOriginal,
            'recurso_resultante' => $CompaniaDocumento->toJson()
        ];
        
        AuditoriaTabla::crear($auditoriaDto);
    
        return true;
        return CompaniaDocumento::cargar($CompaniaDocumento->id);
    }
    

    public static function eliminar($id, $compania)
    {
        DB::beginTransaction();
        
        try {
            // Consultar el objeto
            $CompaniaDocumento = CompaniaDocumento::find($id);
            
            if (!$CompaniaDocumento) {
                return false; // Si no existe, no hacer nada
            }
            
            $rutaArchivo = "public/companias/$compania/" . $CompaniaDocumento->nombre_archivo;

            if (Storage::exists($rutaArchivo)) {
                Storage::delete($rutaArchivo);
            } else {
                dump("Archivo no encontrado:", $rutaArchivo);
            }
    
            // Guardar auditoría antes de eliminar
            $auditoriaDto = [
                'id_recurso' => $CompaniaDocumento->id,
                'nombre_recurso' => CompaniaDocumento::class,
                'descripcion_recurso' => $CompaniaDocumento->nombre_archivo,
                'accion' => AccionAuditoriaEnum::ELIMINAR,
                'recurso_original' => $CompaniaDocumento ? $CompaniaDocumento->toJson() : '{}'
            ];
            AuditoriaTabla::crear($auditoriaDto);
    
            // Eliminar el registro en la base de datos
            $CompaniaDocumento->delete();
    
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollback();
            throw new Exception("Error al eliminar el documento: " . $e->getMessage());
        }
    }
    
}
