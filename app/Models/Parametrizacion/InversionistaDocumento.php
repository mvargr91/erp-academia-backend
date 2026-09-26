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

class InversionistaDocumento extends Model
{
    use HasFactory;

    protected $table = 'inversionistas_documentos';

    protected $fillable = [
        'id_inversionista',
        'id_lista_documento',
        'nombre_archivo',
        'estado_verificacion',
        'usuario_verificacion_id',
        'usuario_verificacion_nombre',
        'fecha_verificacion',
        'usuario_aprobacion_id',
        'usuario_aprobacion_nombre',
        'fecha_aprobacion',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('inversionistas_documentos')
            ->select(
                'inversionistas_documentos.id',
                'inversionistas_documentos.id_inversionista',
                'inversionistas_documentos.id_lista_documento',
                'inversionistas_documentos.nombre_archivo',
                'inversionistas_documentos.estado_verificacion',
                'inversionistas_documentos.usuario_verificacion_id',
                'inversionistas_documentos.usuario_verificacion_nombre',
                'inversionistas_documentos.fecha_verificacion',
                'inversionistas_documentos.usuario_aprobacion_id',
                'inversionistas_documentos.usuario_aprobacion_nombre',
                'inversionistas_documentos.fecha_aprobacion',
            );

        $query->orderBy('nombre_archivo', 'asc');
        return $query->get();
    }


    public static function obtenerColeccionLigeraTipo($dto){
     
        $query = DB::table('listas_documentos')
        ->leftJoin('inversionistas_documentos', function ($join) use ($dto) {
            $join->on('inversionistas_documentos.id_lista_documento', '=', 'listas_documentos.id')
                 ->where('inversionistas_documentos.id_inversionista', $dto['id_inversionista']);
        })
        ->select(
            'inversionistas_documentos.id',
            'inversionistas_documentos.id_inversionista',
            'listas_documentos.id as id_lista_documento',            
            'listas_documentos.nombre',
            'listas_documentos.tipo_lista',
            DB::raw("COALESCE(inversionistas_documentos.nombre_archivo, '-') as nombre_archivo"),
            'inversionistas_documentos.estado_verificacion',
            'inversionistas_documentos.usuario_verificacion_id',
            'inversionistas_documentos.usuario_verificacion_nombre',
            'inversionistas_documentos.fecha_verificacion',
            'inversionistas_documentos.usuario_aprobacion_id',
            'inversionistas_documentos.usuario_aprobacion_nombre',
            'inversionistas_documentos.fecha_aprobacion',
            'inversionistas_documentos.usuario_creacion_id',
            'inversionistas_documentos.usuario_creacion_nombre',
            'inversionistas_documentos.usuario_modificacion_id',
            'inversionistas_documentos.usuario_modificacion_nombre'
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
                    $query->orderBy('inversionistas_documentos.nombre_archivo', $value);
                }              
                if($attribute == 'estado'){
                    $query->orderBy('inversionistas_documentos.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('inversionistas_documentos.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('inversionistas_documentos.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('inversionistas_documentos.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('inversionistas_documentos.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("inversionistas_documentos.updated_at", "desc");
        }

        $InversionistaDocumento = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $InversionistaDocumento->items();
    
        return [
            'datos' => $data,
            'desde' => $InversionistaDocumento->firstItem(),
            'hasta' => $InversionistaDocumento->lastItem(),
            'por_pagina' => $InversionistaDocumento->perPage(),
            'pagina_actual' => $InversionistaDocumento->currentPage(),
            'ultima_pagina' => $InversionistaDocumento->lastPage(),
            'total' => $InversionistaDocumento->total(),
        ];
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('inversionistas_documentos')
            ->select(
            'inversionistas_documentos.id',            
            'inversionistas_documentos.id_inversionista',
            'inversionistas_documentos.id_lista_documento',
            'inversionistas_documentos.nombre_archivo',
            'inversionistas_documentos.estado',
            'inversionistas_documentos.usuario_creacion_id',
            'inversionistas_documentos.usuario_creacion_nombre',
            'inversionistas_documentos.usuario_modificacion_id',
            'inversionistas_documentos.usuario_modificacion_nombre',
        );

        if(isset($dto['nombre'])){
            $query->where('inversionistas_documentos.nombre', 'like', '%' . $dto['nombre'] . '%');
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'inversionista'){
                    $query->orderBy('inversionistas_documentos.inversionista', $value);
                }
                if($attribute == 'id_lista_documento'){
                    $query->orderBy('inversionistas_documentos.id_lista_documento', $value);
                }      
                if($attribute == 'nombre_archivo'){
                    $query->orderBy('inversionistas_documentos.nombre_archivo', $value);
                }            
                if($attribute == 'estado'){
                    $query->orderBy('inversionistas_documentos.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('inversionistas_documentos.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('inversionistas_documentos.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('inversionistas_documentos.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('inversionistas_documentos.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("inversionistas_documentos.updated_at", "desc");
        }

        $InversionistaDocumento = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $InversionistaDocumento->items();
    
        return [
            'datos' => $data,
            'desde' => $InversionistaDocumento->firstItem(),
            'hasta' => $InversionistaDocumento->lastItem(),
            'por_pagina' => $InversionistaDocumento->perPage(),
            'pagina_actual' => $InversionistaDocumento->currentPage(),
            'ultima_pagina' => $InversionistaDocumento->lastPage(),
            'total' => $InversionistaDocumento->total(),
        ];
    }

    public static function cargar($id)
    {
        $InversionistaDocumento = InversionistaDocumento::find($id);

        return [
            'id' => $InversionistaDocumento->id,
            'id_inversionista' => $InversionistaDocumento->id_inversionista,
            'id_lista_documento' => $InversionistaDocumento->id_lista_documento,
            'nombre_archivo' => $InversionistaDocumento->nombre_archivo,
            'estado' => $InversionistaDocumento->estado,
            'usuario_creacion_id' => $InversionistaDocumento->usuario_creacion_id,
            'usuario_creacion_nombre' => $InversionistaDocumento->usuario_creacion_nombre,
            'usuario_modificacion_id' => $InversionistaDocumento->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $InversionistaDocumento->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($InversionistaDocumento->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($InversionistaDocumento->updated_at))->format("Y-m-d H:i:s")
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


        // Consultar el documento inversionista
        $InversionistaDocumento = isset($dto['id']) ? InversionistaDocumento::find($dto['id']) : new InversionistaDocumento();
        
        // Guardar objeto original para auditoria
        $InversionistaDocumentoOriginal = $InversionistaDocumento->toJson();
    
        $InversionistaDocumento->fill($dto);
        $guardado = $InversionistaDocumento->save();
    
        if (!$guardado) {
            throw new Exception("Ocurrió un error al intentar guardar el documento.");
        }    

        $auditoriaDto = [
            'id_recurso' => $InversionistaDocumento->id,
            'nombre_recurso' => InversionistaDocumento::class,
            'descripcion_recurso' => $InversionistaDocumento->nombre_archivo,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => $InversionistaDocumentoOriginal,
            'recurso_resultante' => $InversionistaDocumento->toJson()
        ];
        
        AuditoriaTabla::crear($auditoriaDto);
    
        return true;
        return InversionistaDocumento::cargar($InversionistaDocumento->id);
    }
    

    public static function eliminar($id, $inversionista)
    {
        DB::beginTransaction();
        
        try {
            // Consultar el objeto
            $InversionistaDocumento = InversionistaDocumento::find($id);
            
            if (!$InversionistaDocumento) {
                return false; // Si no existe, no hacer nada
            }
            
            $rutaArchivo = "public/inversionistas/$inversionista/" . $InversionistaDocumento->nombre_archivo;

            if (Storage::exists($rutaArchivo)) {
                Storage::delete($rutaArchivo);
            } else {
                dump("Archivo no encontrado:", $rutaArchivo);
            }
    
            // Guardar auditoría antes de eliminar
            $auditoriaDto = [
                'id_recurso' => $InversionistaDocumento->id,
                'nombre_recurso' => InversionistaDocumento::class,
                'descripcion_recurso' => $InversionistaDocumento->nombre_archivo,
                'accion' => AccionAuditoriaEnum::ELIMINAR,
                'recurso_original' => $InversionistaDocumento ? $InversionistaDocumento->toJson() : '{}'
            ];
            AuditoriaTabla::crear($auditoriaDto);
    
            // Eliminar el registro en la base de datos
            $InversionistaDocumento->delete();
    
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollback();
            throw new Exception("Error al eliminar el documento: " . $e->getMessage());
        }
    }
    
}
