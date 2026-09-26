<?php

namespace App\Models\Parametrizacion;

use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enum\AccionAuditoriaEnum;
use App\Models\Seguridad\AuditoriaTabla;

class ParametroCorreo extends Model
{
    protected $table = 'parametros_correos'; // nombre de la tabla en la base de datos

    protected $fillable = [
        'nombre',
        'asunto',
        'texto',
        'parametros',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    public static function obtenerColeccionLigera($dto) 
    {
        $query = DB::table('parametros_correos')
            ->select(
                'id',
                'nombre',
                'estado',
            )->where('estado', 1);
        $query->orderBy('nombre', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto) 
    {
        $query = DB::table('parametros_correos')
            ->select(
                'id',
                'nombre',
                'asunto',
                'texto',
                'parametros',
                'estado',
                'usuario_creacion_id',
                'usuario_creacion_nombre',
                'usuario_modificacion_id',
                'usuario_modificacion_nombre',
                'created_at AS fecha_creacion',
                'updated_at AS fecha_modificacion',
            );

        if (isset($dto['nombre']))
            $query->where('nombre', 'like', '%' . $dto['nombre'] . '%');

        if (isset($dto['asunto']))
            $query->where('asunto', 'like', '%' . $dto['asunto'] . '%');

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0)
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if ($attribute == 'nombre')
                    $query->orderBy('parametros_correos.nombre', $value);
                if ($attribute == 'asunto')
                    $query->orderBy('parametros_correos.asunto', $value);
                if ($attribute == 'texto')
                    $query->orderBy('parametros_correos.texto', $value);
                if ($attribute == 'parametros')
                    $query->orderBy('parametros_correos.parametros', $value);
                if ($attribute == 'estado')
                    $query->orderBy('parametros_correos.estado', $value);
                if ($attribute == 'usuario_creacion_nombre')
                    $query->orderBy('parametros_correos.usuario_creacion_nombre', $value);
                if ($attribute == 'usuario_modificacion_nombre')
                    $query->orderBy('parametros_correos.usuario_modificacion_nombre', $value);
                if ($attribute == 'fecha_creacion')
                    $query->orderBy('parametros_correos.created_at', $value);
                if ($attribute == 'fecha_modificacion')
                    $query->orderBy('parametros_correos.updated_at', $value);
            }
        else {
            $query->orderBy("parametros_correos.updated_at", "desc");
        }

        $pag = $query->paginate($dto['limite'] ?? 100);
        $datos = [];

        foreach ($pag ?? [] as $pagTmp)
            array_push($datos, $pagTmp);

        $totReg = count($pag);
        $to = isset($pag) && $totReg > 0 ? $pag->currentPage() * $pag->perPage() : null;
        $to = isset($to) && isset($pag) && $to > $pag->total() && $totReg > 0 ? $pag->total() : $to;
        $from = isset($to) && isset($pag) && $totReg > 0 ? ( $pag->perPage() > $to ? 1 : ($to - $totReg) + 1 ) : null;

        return ['datos' => $datos,
                'desde' => $from,
                'hasta' => $to,
                'por_pagina' => isset($pag) && $totReg > 0 ? + $pag->perPage() : 0,
                'pagina_actual' => isset($pag) && $totReg > 0 ? $pag->currentPage() : 1,
                'ultima_pagina' => isset($pag) && $totReg > 0 ? $pag->lastPage() : 0,
                'total' => isset($pag) && $totReg > 0 ? $pag->total() : 0];
    }

    public static function cargar($id)
    {
        $regCargar = ParametroCorreo::find($id);
        return ['id' => $regCargar->id,
                'nombre' => $regCargar->nombre,
                'asunto' => $regCargar->asunto,
                'texto' => $regCargar->texto,
                'parametros' => $regCargar->parametros,
                'estado' => $regCargar->estado,
                'usuario_creacion_id' => $regCargar->usuario_creacion_id,
                'usuario_creacion_nombre' => $regCargar->usuario_creacion_nombre,
                'usuario_modificacion_id' => $regCargar->usuario_modificacion_id,
                'usuario_modificacion_nombre' => $regCargar->usuario_modificacion_nombre,
                'fecha_creacion' => (new Carbon($regCargar->created_at))->format("Y-m-d H:i:s"),
                'fecha_modificacion' => (new Carbon($regCargar->updated_at))->format("Y-m-d H:i:s")];
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
 
        // Consultar aplicación
        $reg = isset($dto['id']) ? ParametroCorreo::find($dto['id']) : new ParametroCorreo();
 
        // Guardar objeto original para auditoria
        $regOri = $reg->toJson();
 
        $reg->fill($dto);
        $guardado = $reg->save();
        if (!$guardado) 
            throw new Exception("Ocurrió un error al intentar guardar el parámetro correo.", $reg);
 
        // Guardar auditoria
        $auditoriaDto = ['id_recurso' => $reg->id,
                         'nombre_recurso' => ParametroCorreo::class,
                         'descripcion_recurso' => $reg->nombre,
                         'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
                         'recurso_original' => isset($dto['id']) ? $regOri : $reg->toJson(),
                         'recurso_resultante' => isset($dto['id']) ? $reg->toJson() : null];
 
        AuditoriaTabla::crear($auditoriaDto);
 
        return ParametroCorreo::cargar($reg->id);
    }

    public static function eliminar($id)
    {
       $regEli = ParametroCorreo::find($id);

        // Guardar auditoria
        $auditoriaDto = ['id_recurso' => $regEli->id,
                        'nombre_recurso' => ParametroCorreo::class,
                        'descripcion_recurso' => $regEli->nombre,
                        'accion' => AccionAuditoriaEnum::ELIMINAR,
                        'recurso_original' => $regEli->toJson()];
        AuditoriaTabla::crear($auditoriaDto);

        return $regEli->delete();
    }
 
    use HasFactory;
}
