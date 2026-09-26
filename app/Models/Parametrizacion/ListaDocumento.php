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

class ListaDocumento extends Model
{
    use HasFactory;

    protected $table = 'listas_documentos';

    protected $fillable = [
        'nombre',
        'tipo_lista',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('listas_documentos')
            ->select(
                'listas_documentos.id',
                'listas_documentos.nombre',
                'listas_documentos.tipo_lista',
                'listas_documentos.estado',
            )->where('listas_documentos.estado', 1);
        $query->orderBy('nombre', 'asc');
        return $query->get();
    }

    public static function obtenerColeccionLigeraTipo($dto){
     
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('listas_documentos')
        ->leftJoin('companias_documentos', function ($join) use ($dto) {
            $join->on('companias_documentos.id_lista_documento', '=', 'listas_documentos.id')
                 ->where('companias_documentos.id_compania', $dto['id_compania']); // Filtra por compañia
        })
        ->select(
            'listas_documentos.id',            
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
            $query->where('listas_documentos.nombre', 'like', '%' . $dto['nombre'] . '%');
        }
        
        if (isset($dto['ordenar_por']) && is_array($dto['ordenar_por']) && count($dto['ordenar_por']) > 0) {
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre'){
                    $query->orderBy('listas_documentos.nombre', $value);
                }
                if($attribute == 'numero_nit'){
                    $query->orderBy('listas_documentos.tipo_lista', $value);
                }              
                if($attribute == 'estado'){
                    $query->orderBy('listas_documentos.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('listas_documentos.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('listas_documentos.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('listas_documentos.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('listas_documentos.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("listas_documentos.updated_at", "desc");
        }

        $ListaDocumento = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $ListaDocumento->items();
    
        return [
            'datos' => $data,
            'desde' => $ListaDocumento->firstItem(),
            'hasta' => $ListaDocumento->lastItem(),
            'por_pagina' => $ListaDocumento->perPage(),
            'pagina_actual' => $ListaDocumento->currentPage(),
            'ultima_pagina' => $ListaDocumento->lastPage(),
            'total' => $ListaDocumento->total(),
        ];
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('listas_documentos')
            ->select(
            'listas_documentos.id',            
            'listas_documentos.nombre',
            'listas_documentos.tipo_lista',
            'listas_documentos.estado',
            'listas_documentos.usuario_creacion_id',
            'listas_documentos.usuario_creacion_nombre',
            'listas_documentos.usuario_modificacion_id',
            'listas_documentos.usuario_modificacion_nombre',
        );

        if(isset($dto['nombre'])){
            $query->where('listas_documentos.nombre', 'like', '%' . $dto['nombre'] . '%');
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre'){
                    $query->orderBy('listas_documentos.nombre', $value);
                }
                if($attribute == 'tipo_lista'){
                    $query->orderBy('listas_documentos.tipo_lista', $value);
                }              
                if($attribute == 'estado'){
                    $query->orderBy('listas_documentos.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('listas_documentos.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('listas_documentos.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('listas_documentos.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('listas_documentos.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("listas_documentos.updated_at", "desc");
        }

        $ListaDocumento = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $ListaDocumento->items();
    
        return [
            'datos' => $data,
            'desde' => $ListaDocumento->firstItem(),
            'hasta' => $ListaDocumento->lastItem(),
            'por_pagina' => $ListaDocumento->perPage(),
            'pagina_actual' => $ListaDocumento->currentPage(),
            'ultima_pagina' => $ListaDocumento->lastPage(),
            'total' => $ListaDocumento->total(),
        ];
    }

    public static function cargar($id)
    {
        $ListaDocumento = ListaDocumento::find($id);

        return [
            'id' => $ListaDocumento->id,
            'nombre' => $ListaDocumento->nombre,
            'tipo_lista' => $ListaDocumento->tipo_lista,
            'estado' => $ListaDocumento->estado,
            'usuario_creacion_id' => $ListaDocumento->usuario_creacion_id,
            'usuario_creacion_nombre' => $ListaDocumento->usuario_creacion_nombre,
            'usuario_modificacion_id' => $ListaDocumento->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $ListaDocumento->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($ListaDocumento->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($ListaDocumento->updated_at))->format("Y-m-d H:i:s")
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
        $ListaDocumento = isset($dto['id']) ? ListaDocumento::find($dto['id']) : new ListaDocumento();

        // Guardar objeto original para auditoria
        $ListaDocumentoOriginal = $ListaDocumento->toJson();

        $ListaDocumento->fill($dto);
        $guardado = $ListaDocumento->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la lista documento.", $ListaDocumento);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $ListaDocumento->id,
            'nombre_recurso' => ListaDocumento::class,
            'descripcion_recurso' => $ListaDocumento->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $ListaDocumentoOriginal : $ListaDocumento->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $ListaDocumento->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return ListaDocumento::cargar($ListaDocumento->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $ListaDocumento = ListaDocumento::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $ListaDocumento->id,
            'nombre_recurso' => ListaDocumento::class,
            'descripcion_recurso' => $ListaDocumento->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $ListaDocumento->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $ListaDocumento->delete();
    }
}
