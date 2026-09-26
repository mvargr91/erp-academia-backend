<?php

namespace App\Models\Turismo;

use Exception;
use Carbon\Carbon;
use App\Enum\AccionAuditoriaEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\AuditoriaTabla;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Cupon extends Model
{
    use HasFactory;

    protected $table = 'cupones';

    protected $fillable = [
        'tipo',
        'nombre',
        'descripcion',
        'cantidad',
        'descuento',
        'valor',
        'fecha_inicio',
        'fecha_fin',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    public static function obtenerColeccionLigera($dto)
    {
        $query = DB::table('cupones')
            ->select(
                'cupones.id',
                'cupones.nombre',
                'cupones.estado',
            )
            ->where('cupones.estado', 1);

        $query->orderBy('nombre', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto)
    {
        $query = DB::table('cupones')
            ->select(
                'cupones.id',
                'cupones.tipo',
                'cupones.nombre',
                'cupones.descripcion',
                'cupones.cantidad',
                'cupones.descuento',
                'cupones.valor',
                'cupones.fecha_inicio',
                'cupones.fecha_fin',
                'cupones.estado',
                'cupones.usuario_creacion_id',
                'cupones.usuario_creacion_nombre',
                'cupones.usuario_modificacion_id',
                'cupones.usuario_modificacion_nombre',
                'cupones.created_at as fecha_creacion',
                'cupones.updated_at as fecha_modificacion',
            );

        if (isset($dto['nombre'])) {
            $query->where('cupones.nombre', 'like', '%' . $dto['nombre'] . '%');
        }

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0) {
            foreach ($dto['ordenar_por'] as $attribute => $value) {
                if ($attribute == 'nombre') {
                    $query->orderBy('cupones.nombre', $value);
                }
                if ($attribute == 'tipo') {
                    $query->orderBy('cupones.tipo', $value);
                }
                if ($attribute == 'fecha_inicio') {
                    $query->orderBy('cupones.fecha_inicio', $value);
                }
                if ($attribute == 'fecha_fin') {
                    $query->orderBy('cupones.fecha_fin', $value);
                }
                if ($attribute == 'estado') {
                    $query->orderBy('cupones.estado', $value);
                }
                if ($attribute == 'fecha_creacion') {
                    $query->orderBy('cupones.created_at', $value);
                }
                if ($attribute == 'fecha_modificacion') {
                    $query->orderBy('cupones.updated_at', $value);
                }
            }
        } else {
            $query->orderBy('cupones.updated_at', 'desc');
        }

        $cupones = $query->paginate($dto['limite'] ?? 100);
        $data = $cupones->items();

        return [
            'datos' => $data,
            'desde' => $cupones->firstItem(),
            'hasta' => $cupones->lastItem(),
            'por_pagina' => $cupones->perPage(),
            'pagina_actual' => $cupones->currentPage(),
            'ultima_pagina' => $cupones->lastPage(),
            'total' => $cupones->total(),
        ];
    }

    public static function cargar($id)
    {
        $cupon = Cupon::find($id);

        return [
            'id' => $cupon->id,
            'tipo' => $cupon->tipo,
            'nombre' => $cupon->nombre,
            'descripcion' => $cupon->descripcion,
            'cantidad' => $cupon->cantidad,
            'descuento' => $cupon->descuento,
            'valor' => $cupon->valor,
            'fecha_inicio' => $cupon->fecha_inicio,
            'fecha_fin' => $cupon->fecha_fin,
            'estado' => $cupon->estado,
            'usuario_creacion_id' => $cupon->usuario_creacion_id,
            'usuario_creacion_nombre' => $cupon->usuario_creacion_nombre,
            'usuario_modificacion_id' => $cupon->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $cupon->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($cupon->created_at))->format('Y-m-d H:i:s'),
            'fecha_modificacion' => (new Carbon($cupon->updated_at))->format('Y-m-d H:i:s'),
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

        $cupon = isset($dto['id']) ? Cupon::find($dto['id']) : new Cupon();

        $cuponOriginal = $cupon->toJson();

        $cupon->fill($dto);
        $guardado = $cupon->save();
        if (!$guardado) {
            throw new Exception('Ocurrió un error al intentar guardar el cupón.');
        }

        $auditoriaDto = [
            'id_recurso' => $cupon->id,
            'nombre_recurso' => Cupon::class,
            'descripcion_recurso' => $cupon->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $cuponOriginal : $cupon->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $cupon->toJson() : null,
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return Cupon::cargar($cupon->id);
    }

    public static function eliminar($id)
    {
        $cupon = Cupon::find($id);

        $auditoriaDto = [
            'id_recurso' => $cupon->id,
            'nombre_recurso' => Cupon::class,
            'descripcion_recurso' => $cupon->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $cupon->toJson(),
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return $cupon->delete();
    }
}
