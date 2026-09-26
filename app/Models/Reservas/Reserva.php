<?php

namespace App\Models\Reservas;

use Exception;
use Carbon\Carbon;
use App\Enum\AccionAuditoriaEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\AuditoriaTabla;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Reserva extends Model
{
    use HasFactory;

    protected $table = 'reservas';

    protected $fillable = [
        'usuario_id',
        'experiencia_id',
        'proveedor_id',
        'disponibilidad_id',
        'codigo_reserva',
        'fecha',
        'residencia',
        'nombre',
        'correo',
        'telefono',
        'hora_inicio',
        'cantidad_personas',
        'cantidad_chicos',
        'idioma',
        'cupon_id',
        'valor_total',
        'estado',
        'observaciones',
        'notas',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    public static function obtenerColeccionLigera($dto)
    {
        $query = DB::table('reservas')
            ->select(
                'reservas.id',
                'reservas.codigo_reserva',
                'reservas.estado',
            );

        $query->orderBy('reservas.id', 'desc');
        return $query->get();
    }

    public static function obtenerColeccion($dto)
    {
        $query = DB::table('reservas')
            ->join('experiencias', 'experiencias.id', '=', 'reservas.experiencia_id')
            ->join('proveedores_turisticos', 'proveedores_turisticos.id', '=', 'reservas.proveedor_id')
            ->select(
                'reservas.id',
                'reservas.usuario_id',
                'reservas.experiencia_id',
                'experiencias.nombre as experiencia_nombre',
                'reservas.proveedor_id',
                'proveedores_turisticos.nombre_comercial as proveedor_nombre',
                'reservas.disponibilidad_id',
                'reservas.codigo_reserva',
                'reservas.fecha',
                'reservas.residencia',
                'reservas.nombre',
                'reservas.correo',
                'reservas.telefono',
                'reservas.hora_inicio',
                'reservas.cantidad_personas',
                'reservas.cantidad_chicos',
                'reservas.idioma',
                'reservas.cupon_id',
                'reservas.valor_total',
                'reservas.estado',
                'reservas.observaciones',
                'reservas.notas',
                'reservas.usuario_creacion_id',
                'reservas.usuario_creacion_nombre',
                'reservas.usuario_modificacion_id',
                'reservas.usuario_modificacion_nombre',
                'reservas.created_at as fecha_creacion',
                'reservas.updated_at as fecha_modificacion',
            );

        if (isset($dto['nombre'])) {
            $query->where('reservas.nombre', 'like', '%' . $dto['nombre'] . '%');
        }
        if (isset($dto['codigo_reserva'])) {
            $query->where('reservas.codigo_reserva', 'like', '%' . $dto['codigo_reserva'] . '%');
        }
        if (isset($dto['usuario_id'])) {
            $query->where('reservas.usuario_id', $dto['usuario_id']);
        }
        if (isset($dto['proveedor_id'])) {
            $query->where('reservas.proveedor_id', $dto['proveedor_id']);
        }
        if (isset($dto['estado'])) {
            $query->where('reservas.estado', $dto['estado']);
        }

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0) {
            foreach ($dto['ordenar_por'] as $attribute => $value) {
                if ($attribute == 'codigo_reserva') {
                    $query->orderBy('reservas.codigo_reserva', $value);
                }
                if ($attribute == 'fecha') {
                    $query->orderBy('reservas.fecha', $value);
                }
                if ($attribute == 'experiencia_nombre') {
                    $query->orderBy('experiencias.nombre', $value);
                }
                if ($attribute == 'proveedor_nombre') {
                    $query->orderBy('proveedores_turisticos.nombre_comercial', $value);
                }
                if ($attribute == 'valor_total') {
                    $query->orderBy('reservas.valor_total', $value);
                }
                if ($attribute == 'estado') {
                    $query->orderBy('reservas.estado', $value);
                }
                if ($attribute == 'fecha_creacion') {
                    $query->orderBy('reservas.created_at', $value);
                }
                if ($attribute == 'fecha_modificacion') {
                    $query->orderBy('reservas.updated_at', $value);
                }
            }
        } else {
            $query->orderBy('reservas.updated_at', 'desc');
        }

        $reservas = $query->paginate($dto['limite'] ?? 100);
        $data = $reservas->items();

        return [
            'datos' => $data,
            'desde' => $reservas->firstItem(),
            'hasta' => $reservas->lastItem(),
            'por_pagina' => $reservas->perPage(),
            'pagina_actual' => $reservas->currentPage(),
            'ultima_pagina' => $reservas->lastPage(),
            'total' => $reservas->total(),
        ];
    }

    public static function cargar($id)
    {
        $reserva = Reserva::find($id);

        $acompanantes = DB::table('acompanantes_reserva')
            ->where('reserva_id', $id)
            ->get();

        return [
            'id' => $reserva->id,
            'usuario_id' => $reserva->usuario_id,
            'experiencia_id' => $reserva->experiencia_id,
            'proveedor_id' => $reserva->proveedor_id,
            'disponibilidad_id' => $reserva->disponibilidad_id,
            'codigo_reserva' => $reserva->codigo_reserva,
            'fecha' => $reserva->fecha,
            'residencia' => $reserva->residencia,
            'nombre' => $reserva->nombre,
            'correo' => $reserva->correo,
            'telefono' => $reserva->telefono,
            'hora_inicio' => $reserva->hora_inicio,
            'cantidad_personas' => $reserva->cantidad_personas,
            'cantidad_chicos' => $reserva->cantidad_chicos,
            'idioma' => $reserva->idioma,
            'cupon_id' => $reserva->cupon_id,
            'valor_total' => $reserva->valor_total,
            'estado' => $reserva->estado,
            'observaciones' => $reserva->observaciones,
            'notas' => $reserva->notas,
            'acompanantes' => $acompanantes,
            'usuario_creacion_id' => $reserva->usuario_creacion_id,
            'usuario_creacion_nombre' => $reserva->usuario_creacion_nombre,
            'usuario_modificacion_id' => $reserva->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $reserva->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($reserva->created_at))->format('Y-m-d H:i:s'),
            'fecha_modificacion' => (new Carbon($reserva->updated_at))->format('Y-m-d H:i:s'),
        ];
    }

    public static function modificarOCrear($dto)
    {
        $user = Auth::user();
        $usuario = $user->usuario();

        if (!isset($dto['id'])) {
            $dto['usuario_creacion_id'] = $usuario->id ?? ($dto['usuario_creacion_id'] ?? null);
            $dto['usuario_creacion_nombre'] = $usuario->nombre ?? ($dto['usuario_creacion_nombre'] ?? null);
            $dto['codigo_reserva'] = $dto['codigo_reserva'] ?? strtoupper('RES-' . uniqid());
            $dto['estado'] = $dto['estado'] ?? 'pendiente';
        }
        if (isset($usuario) || isset($dto['usuario_modificacion_id'])) {
            $dto['usuario_modificacion_id'] = $usuario->id ?? ($dto['usuario_modificacion_id'] ?? null);
            $dto['usuario_modificacion_nombre'] = $usuario->nombre ?? ($dto['usuario_modificacion_nombre'] ?? null);
        }

        $reserva = isset($dto['id']) ? Reserva::find($dto['id']) : new Reserva();

        $reservaOriginal = $reserva->toJson();

        $reserva->fill($dto);
        $guardado = $reserva->save();
        if (!$guardado) {
            throw new Exception('Ocurrió un error al intentar guardar la reserva.');
        }

        $auditoriaDto = [
            'id_recurso' => $reserva->id,
            'nombre_recurso' => Reserva::class,
            'descripcion_recurso' => $reserva->codigo_reserva,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $reservaOriginal : $reserva->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $reserva->toJson() : null,
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return Reserva::cargar($reserva->id);
    }

    public static function cambiarEstado($id, $estado)
    {
        $reserva = Reserva::find($id);
        $reservaOriginal = $reserva->toJson();

        $reserva->estado = $estado;
        $guardado = $reserva->save();
        if (!$guardado) {
            throw new Exception('Ocurrió un error al intentar cambiar el estado de la reserva.');
        }

        $auditoriaDto = [
            'id_recurso' => $reserva->id,
            'nombre_recurso' => Reserva::class,
            'descripcion_recurso' => $reserva->codigo_reserva,
            'accion' => AccionAuditoriaEnum::MODIFICAR,
            'recurso_original' => $reservaOriginal,
            'recurso_resultante' => $reserva->toJson(),
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return Reserva::cargar($reserva->id);
    }

    public static function eliminar($id)
    {
        $reserva = Reserva::find($id);

        $auditoriaDto = [
            'id_recurso' => $reserva->id,
            'nombre_recurso' => Reserva::class,
            'descripcion_recurso' => $reserva->codigo_reserva,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $reserva->toJson(),
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return $reserva->delete();
    }
}
