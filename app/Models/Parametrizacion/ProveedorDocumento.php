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

class ProveedorDocumento extends Model
{
    use HasFactory;

    protected $table = 'proveedores_documentos';

    protected $fillable = [
        'id_proveedor',
        'id_lista_documento',
        'nombre_archivo',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('proveedores_documentos')
            ->select(
                'proveedores_documentos.id',
                'proveedores_documentos.id_proveedor',
                'proveedores_documentos.id_lista_documento',
                'proveedores_documentos.nombre_archivo',
                'proveedores_documentos.estado',
            )->where('proveedores_documentos.estado', 1);
        $query->orderBy('nombre_archivo', 'asc');
        return $query->get();
    }


    public static function obtenerColeccionLigeraTipo($dto){
     
        $query = DB::table('listas_documentos')
        ->leftJoin('proveedores_documentos', function ($join) use ($dto) {
            $join->on('proveedores_documentos.id_lista_documento', '=', 'listas_documentos.id')
                 ->where('proveedores_documentos.id_proveedor', $dto['id_proveedor']);
        })
        ->select(
            'proveedores_documentos.id',
            'proveedores_documentos.id_proveedor',
            'listas_documentos.id as id_lista_documento',            
            'listas_documentos.nombre',
            'listas_documentos.tipo_lista',
            DB::raw("COALESCE(proveedores_documentos.nombre_archivo, '-') as nombre_archivo"),
            'listas_documentos.estado',
            'listas_documentos.usuario_creacion_id',
            'listas_documentos.usuario_creacion_nombre',
            'listas_documentos.usuario_modificacion_id',
            'listas_documentos.usuario_modificacion_nombre'
        )
        ->where('listas_documentos.tipo_lista', $dto['tipo_lista']);

        if(isset($dto['nombre'])){
            $query->where('proveedores_documentos.nombre', 'like', '%' . $dto['nombre'] . '%');
        }
        
        if (isset($dto['ordenar_por']) && is_array($dto['ordenar_por']) && count($dto['ordenar_por']) > 0) {
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre'){
                    $query->orderBy('proveedores_documentos.nombre', $value);
                }
                if($attribute == 'tipo_lista'){
                    $query->orderBy('proveedores_documentos.tipo_lista', $value);
                }              
                if($attribute == 'estado'){
                    $query->orderBy('proveedores_documentos.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('proveedores_documentos.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('proveedores_documentos.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('proveedores_documentos.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('proveedores_documentos.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("proveedores_documentos.updated_at", "desc");
        }

        $ProveedorDocumento = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $ProveedorDocumento->items();
    
        return [
            'datos' => $data,
            'desde' => $ProveedorDocumento->firstItem(),
            'hasta' => $ProveedorDocumento->lastItem(),
            'por_pagina' => $ProveedorDocumento->perPage(),
            'pagina_actual' => $ProveedorDocumento->currentPage(),
            'ultima_pagina' => $ProveedorDocumento->lastPage(),
            'total' => $ProveedorDocumento->total(),
        ];
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('proveedores_documentos')
            ->select(
            'proveedores_documentos.id',            
            'proveedores_documentos.id_proveedor',
            'proveedores_documentos.id_lista_documento',
            'proveedores_documentos.nombre_archivo',
            'proveedores_documentos.estado',
            'proveedores_documentos.usuario_creacion_id',
            'proveedores_documentos.usuario_creacion_nombre',
            'proveedores_documentos.usuario_modificacion_id',
            'proveedores_documentos.usuario_modificacion_nombre',
        );

        if(isset($dto['nombre'])){
            $query->where('proveedores_documentos.nombre', 'like', '%' . $dto['nombre'] . '%');
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'compania'){
                    $query->orderBy('proveedores_documentos.compania', $value);
                }
                if($attribute == 'id_lista_documento'){
                    $query->orderBy('proveedores_documentos.id_lista_documento', $value);
                }      
                if($attribute == 'nombre_archivo'){
                    $query->orderBy('proveedores_documentos.nombre_archivo', $value);
                }            
                if($attribute == 'estado'){
                    $query->orderBy('proveedores_documentos.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('proveedores_documentos.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('proveedores_documentos.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('proveedores_documentos.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('proveedores_documentos.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("proveedores_documentos.updated_at", "desc");
        }

        $ProveedorDocumento = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $ProveedorDocumento->items();
    
        return [
            'datos' => $data,
            'desde' => $ProveedorDocumento->firstItem(),
            'hasta' => $ProveedorDocumento->lastItem(),
            'por_pagina' => $ProveedorDocumento->perPage(),
            'pagina_actual' => $ProveedorDocumento->currentPage(),
            'ultima_pagina' => $ProveedorDocumento->lastPage(),
            'total' => $ProveedorDocumento->total(),
        ];
    }

    public static function cargar($id)
    {
        $ProveedorDocumento = ProveedorDocumento::find($id);

        return [
            'id' => $ProveedorDocumento->id,
            'id_proveedor' => $ProveedorDocumento->id_proveedor,
            'id_lista_documento' => $ProveedorDocumento->id_lista_documento,
            'nombre_archivo' => $ProveedorDocumento->nombre_archivo,
            'estado' => $ProveedorDocumento->estado,
            'usuario_creacion_id' => $ProveedorDocumento->usuario_creacion_id,
            'usuario_creacion_nombre' => $ProveedorDocumento->usuario_creacion_nombre,
            'usuario_modificacion_id' => $ProveedorDocumento->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $ProveedorDocumento->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($ProveedorDocumento->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($ProveedorDocumento->updated_at))->format("Y-m-d H:i:s")
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
        $ProveedorDocumento = isset($dto['id']) ? ProveedorDocumento::find($dto['id']) : new ProveedorDocumento();

        // Guardar objeto original para auditoria
        $ProveedorDocumentoOriginal = $ProveedorDocumento->toJson();
    
        $ProveedorDocumento->fill($dto);
        $guardado = $ProveedorDocumento->save();
    
        if (!$guardado) {
            throw new Exception("Ocurrió un error al intentar guardar el documento.");
        }    

        $auditoriaDto = [
            'id_recurso' => $ProveedorDocumento->id,
            'nombre_recurso' => ProveedorDocumento::class,
            'descripcion_recurso' => $ProveedorDocumento->nombre_archivo,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => $ProveedorDocumentoOriginal,
            'recurso_resultante' => $ProveedorDocumento->toJson()
        ];
        
        AuditoriaTabla::crear($auditoriaDto);
    
        return true;
        return ProveedorDocumento::cargar($ProveedorDocumento->id);
    }
    

    public static function eliminar($id, $compania)
    {
        DB::beginTransaction();
        
        try {
            // Consultar el objeto
            $ProveedorDocumento = ProveedorDocumento::find($id);
            
            if (!$ProveedorDocumento) {
                return false; // Si no existe, no hacer nada
            }
            
            $rutaArchivo = "public/proveedores/$compania/" . $ProveedorDocumento->nombre_archivo;

            if (Storage::exists($rutaArchivo)) {
                Storage::delete($rutaArchivo);
            } else {
                dump("Archivo no encontrado:", $rutaArchivo);
            }
    
            // Guardar auditoría antes de eliminar
            $auditoriaDto = [
                'id_recurso' => $ProveedorDocumento->id,
                'nombre_recurso' => ProveedorDocumento::class,
                'descripcion_recurso' => $ProveedorDocumento->nombre_archivo,
                'accion' => AccionAuditoriaEnum::ELIMINAR,
                'recurso_original' => $ProveedorDocumento ? $ProveedorDocumento->toJson() : '{}'
            ];
            AuditoriaTabla::crear($auditoriaDto);
    
            // Eliminar el registro en la base de datos
            $ProveedorDocumento->delete();
    
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollback();
            throw new Exception("Error al eliminar el documento: " . $e->getMessage());
        }
    }
    
}
