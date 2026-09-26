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

class Banco extends Model
{
    use HasFactory;

    protected $table = 'bancos';

    protected $fillable = [
        'nombre',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];
   

    public static function obtenerColeccionLigera($dto){     
        $query = DB::table('bancos')
            ->select(
                'bancos.id',
                'bancos.nombre',
                'bancos.estado',
            );
        $query->orderBy('nombre', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('bancos')
            ->select(
            'bancos.id',            
            'bancos.nombre',
            'bancos.estado',
            'bancos.usuario_creacion_id',
            'bancos.usuario_creacion_nombre',
            'bancos.usuario_modificacion_id',
            'bancos.usuario_modificacion_nombre',
            'bancos.created_at as fecha_creacion',
            'bancos.updated_at as fecha_modificacion' ,
        );

        if(isset($dto['nombre'])){
            $query->where('bancos.nombre', 'like', '%' . $dto['nombre'] . '%');
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre'){
                    $query->orderBy('bancos.nombre', $value);
                }           
                if($attribute == 'estado'){
                    $query->orderBy('bancos.estado', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('bancos.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('bancos.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('bancos.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('bancos.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("bancos.updated_at", "desc");
        }

        $Banco = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $Banco->items();
    
        return [
            'datos' => $data,
            'desde' => $Banco->firstItem(),
            'hasta' => $Banco->lastItem(),
            'por_pagina' => $Banco->perPage(),
            'pagina_actual' => $Banco->currentPage(),
            'ultima_pagina' => $Banco->lastPage(),
            'total' => $Banco->total(),
        ];
    }

    public static function cargar($id)
    {
        $Banco = Banco::find($id);

        return [
            'id' => $Banco->id,
            'nombre' => $Banco->nombre,
            'estado' => $Banco->estado,
            'usuario_creacion_id' => $Banco->usuario_creacion_id,
            'usuario_creacion_nombre' => $Banco->usuario_creacion_nombre,
            'usuario_modificacion_id' => $Banco->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $Banco->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($Banco->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($Banco->updated_at))->format("Y-m-d H:i:s")
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
        $Banco = isset($dto['id']) ? Banco::find($dto['id']) : new Banco();
        // Guardar objeto original para auditoria
        $BancoOriginal = $Banco->toJson();
        $Banco->fill($dto);
        $guardado = $Banco->save();
       
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar el banco.", $Banco);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $Banco->id,
            'nombre_recurso' => Banco::class,
            'descripcion_recurso' => $Banco->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $BancoOriginal : $Banco->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $Banco->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return Banco::cargar($Banco->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $Banco = Banco::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $Banco->id,
            'nombre_recurso' => Banco::class,
            'descripcion_recurso' => $Banco->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $Banco->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $Banco->delete();
    }
}
