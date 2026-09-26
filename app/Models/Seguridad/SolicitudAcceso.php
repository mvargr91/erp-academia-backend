<?php

namespace App\Models\Seguridad;

use Exception;
use Carbon\Carbon;
use App\Enum\AccionAuditoriaEnum;
use App\Models\Seguridad\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\Parametrizacion\Cliente;
use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\AuditoriaTabla;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SolicitudAcceso extends Model
{
    use HasFactory;

    protected $table = 'solicitudes_acceso';

    protected $fillable = [
        'cliente_id',
        'usuario_id',
        'fecha_solicitud',
        'fecha_expiracion',
        'observaciones',
        'estado_acceso',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre'
    ];

    public function cliente(){
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function usuario(){
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public static function obtenerColeccionLigera($dto){
        $query = DB::table('solicitudes_acceso')
            ->join('usuarios', 'usuarios.id', 'solicitudes_acceso.usuario_id')
            ->select(
                'solicitudes_acceso.id',
                'usuarios.nombres AS nombre',
                'solicitudes_acceso.fecha_solicitud',
                'solicitudes_acceso.estado_acceso',
            );

        $query->orderBy('usuarios.nombres', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();
        $query = DB::table('solicitudes_acceso AS mt')
            ->join('clientes AS t1', 't1.id', 'mt.cliente_id')
            ->join('usuarios AS t2', 't2.id', 'mt.usuario_id')
            ->select(
                'mt.id',
                't1.nombre AS cliente',
                't2.nombre AS usuario',
                'mt.fecha_solicitud',
                'mt.fecha_expiracion',
                'mt.observaciones',
                'mt.estado_acceso',
                'mt.usuario_creacion_id',
                'mt.usuario_creacion_nombre',
                'mt.usuario_modificacion_id',
                'mt.usuario_modificacion_nombre',
                'mt.created_at AS fecha_creacion',
                'mt.updated_at AS fecha_modificacion'
            );
        if($rol->type !== 'IN' || ($rol->type === 'IN' && !isset($dto['cliente']))){
            $query->where('mt.cliente_id', $usuario->cliente_id);
        }
        if(isset($dto['usuario'])){
            $query->where('t2.nombre', 'like', '%'.$dto['usuario'].'%');
        }
        if(isset($dto['cliente'])){
            $query->where('mt.cliente_id', $dto['cliente']);
        }
        if(isset($dto['estado'])){
            $query->where('mt.estado_acceso', $dto['estado']);
        } else {
            $query->whereIn('mt.estado_acceso', ['PDTE', 'APRB']);
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'cliente'){
                    $query->orderBy('t1.nombre', $value);
                }
                if($attribute == 'usuario'){
                    $query->orderBy('t2.nombre', $value);
                }
                if($attribute == 'fecha_solicitud'){
                    $query->orderBy('mt.fecha_solicitud', $value);
                }
                if($attribute == 'fecha_expiracion'){
                    $query->orderBy('mt.fecha_expiracion', $value);
                }
                if($attribute == 'observaciones'){
                    $query->orderBy('mt.observaciones', $value);
                }
                if($attribute == 'estado_acceso'){
                    $query->orderBy('mt.estado_acceso', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('mt.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('mt.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('mt.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('mt.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("mt.updated_at", "desc");
        }

        $solicitudesAcceso = $query->paginate($dto['limite'] ?? 100);
        $data = [];
        foreach ($solicitudesAcceso ?? [] as $solicitudAcceso){
            array_push($data, $solicitudAcceso);
        }

        $cantidadSolicitudes = count($solicitudesAcceso);
        $to = isset($solicitudesAcceso) && $cantidadSolicitudes > 0 ? $solicitudesAcceso->currentPage() * $solicitudesAcceso->perPage() : null;
        $to = isset($to) && isset($solicitudesAcceso) && $to > $solicitudesAcceso->total() && $cantidadSolicitudes> 0 ? $solicitudesAcceso->total() : $to;
        $from = isset($to) && isset($solicitudesAcceso) && $cantidadSolicitudes > 0 ?
            ( $solicitudesAcceso->perPage() > $to ? 1 : ($to - $cantidadSolicitudes) + 1 )
            : null;
        return [
            'datos' => $data,
            'desde' => $from,
            'hasta' => $to,
            'por_pagina' => isset($solicitudesAcceso) && $cantidadSolicitudes > 0 ? +$solicitudesAcceso->perPage() : 0,
            'pagina_actual' => isset($solicitudesAcceso) && $cantidadSolicitudes > 0 ? $solicitudesAcceso->currentPage() : 1,
            'ultima_pagina' => isset($solicitudesAcceso) && $cantidadSolicitudes > 0 ? $solicitudesAcceso->lastPage() : 0,
            'total' => isset($solicitudesAcceso) && $cantidadSolicitudes > 0 ? $solicitudesAcceso->total() : 0
        ];
    }

    public static function cargar($id)
    {
        $solicitudAcceso = SolicitudAcceso::find($id);
        $cliente = $solicitudAcceso->cliente;
        $usuario = $solicitudAcceso->usuario;
        return [
            'id' => $solicitudAcceso->id,
            'fecha_solicitud' => $solicitudAcceso->fecha_solicitud,
            'fecha_expiracion' => $solicitudAcceso->fecha_expiracion,
            'observaciones' => $solicitudAcceso->observaciones,
            'estado_acceso' => $solicitudAcceso->estado_acceso,
            'usuario_creacion_id' => $solicitudAcceso->usuario_creacion_id,
            'usuario_creacion_nombre' => $solicitudAcceso->usuario_creacion_nombre,
            'usuario_modificacion_id' => $solicitudAcceso->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $solicitudAcceso->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($solicitudAcceso->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($solicitudAcceso->updated_at))->format("Y-m-d H:i:s"),
            'cliente' => isset($cliente) ? [
                'id' => $cliente->id,
                'nombre' => $cliente->nombre
            ] : null,
            'usuario' => isset($usuario) ? [
                'id' => $usuario->id,
                'nombre' => $usuario->nombre
            ] : null,
        ];
    }

    public static function consultar($usuario_id){
        $solicitudAcceso = SolicitudAcceso::where('usuario_id', $usuario_id)
            ->orderBy('id', 'desc')
            ->first();
        $cliente = $solicitudAcceso->cliente;
        $usuario = $solicitudAcceso->usuario;
        return [
            'id' => $solicitudAcceso->id,
            'fecha_solicitud' => $solicitudAcceso->fecha_solicitud,
            'fecha_expiracion' => $solicitudAcceso->fecha_expiracion,
            'observaciones' => $solicitudAcceso->observaciones,
            'estado_acceso' => $solicitudAcceso->estado_acceso,
            'usuario_creacion_id' => $solicitudAcceso->usuario_creacion_id,
            'usuario_creacion_nombre' => $solicitudAcceso->usuario_creacion_nombre,
            'usuario_modificacion_id' => $solicitudAcceso->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $solicitudAcceso->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($solicitudAcceso->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($solicitudAcceso->updated_at))->format("Y-m-d H:i:s"),
            'cliente' => isset($cliente) ? [
                'id' => $cliente->id,
                'nombre' => $cliente->nombre
            ] : null,
            'usuario' => isset($usuario) ? [
                'id' => $usuario->id,
                'nombre' => $usuario->nombre
            ] : null,
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
        $solicitudAcceso = isset($dto['id']) ? SolicitudAcceso::find($dto['id']) : new SolicitudAcceso();

        // Guardar objeto original para auditoria
        $solicitudAccesoOriginal = $solicitudAcceso->toJson();

        $solicitudAcceso->fill($dto);
        $guardado = $solicitudAcceso->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la solicitud de acceso.", $solicitudAcceso);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $solicitudAcceso->id,
            'nombre_recurso' => SolicitudAcceso::class,
            'descripcion_recurso' => $solicitudAcceso->usuario->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $solicitudAccesoOriginal : $solicitudAcceso->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $solicitudAcceso->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return SolicitudAcceso::cargar($solicitudAcceso->id);
    }

    

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $solicitudAcceso = SolicitudAcceso::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $solicitudAcceso->id,
            'nombre_recurso' => SolicitudAcceso::class,
            'descripcion_recurso' => $solicitudAcceso->usuario->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $solicitudAcceso->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $solicitudAcceso->delete();
    }
}
