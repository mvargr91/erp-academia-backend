<?php

namespace App\Models\Proveedores;

use Exception;
use Carbon\Carbon;
use App\Enum\AccionAuditoriaEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\AuditoriaTabla;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProveedorTuristico extends Model
{
    use HasFactory;

    protected $table = 'proveedores_turisticos';

    protected $fillable = [
        'usuario_id',
        'nombre_comercial',
        'razon_social',
        'nit',
        'descripcion',
        'telefono',
        'correo',
        'direccion',
        'destino_id',
        'sitio_web',
        'instagram',
        'facebook',
        'rnt',
        'estado_verificacion',
        'observaciones_verificacion',
        'verificado_en',
        'verificado_por',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    public static function obtenerColeccionLigera($dto)
    {
        $query = DB::table('proveedores_turisticos')
            ->select(
                'proveedores_turisticos.id',
                'proveedores_turisticos.nombre_comercial',
                'proveedores_turisticos.estado',
            )
            ->where('proveedores_turisticos.estado', 1);

        $query->orderBy('nombre_comercial', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto)
    {
        $query = DB::table('proveedores_turisticos')
            ->leftJoin('destinos', 'destinos.id', '=', 'proveedores_turisticos.destino_id')
            ->select(
                'proveedores_turisticos.id',
                'proveedores_turisticos.usuario_id',
                'proveedores_turisticos.nombre_comercial',
                'proveedores_turisticos.razon_social',
                'proveedores_turisticos.nit',
                'proveedores_turisticos.descripcion',
                'proveedores_turisticos.telefono',
                'proveedores_turisticos.correo',
                'proveedores_turisticos.direccion',
                'proveedores_turisticos.destino_id',
                'destinos.nombre as destino_nombre',
                'proveedores_turisticos.sitio_web',
                'proveedores_turisticos.instagram',
                'proveedores_turisticos.facebook',
                'proveedores_turisticos.rnt',
                'proveedores_turisticos.estado_verificacion',
                'proveedores_turisticos.observaciones_verificacion',
                'proveedores_turisticos.verificado_en',
                'proveedores_turisticos.verificado_por',
                'proveedores_turisticos.estado',
                'proveedores_turisticos.usuario_creacion_id',
                'proveedores_turisticos.usuario_creacion_nombre',
                'proveedores_turisticos.usuario_modificacion_id',
                'proveedores_turisticos.usuario_modificacion_nombre',
                'proveedores_turisticos.created_at as fecha_creacion',
                'proveedores_turisticos.updated_at as fecha_modificacion',
            );

        if (isset($dto['nombre'])) {
            $query->where('proveedores_turisticos.nombre_comercial', 'like', '%' . $dto['nombre'] . '%');
        }

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0) {
            foreach ($dto['ordenar_por'] as $attribute => $value) {
                if ($attribute == 'nombre_comercial') {
                    $query->orderBy('proveedores_turisticos.nombre_comercial', $value);
                }
                if ($attribute == 'nit') {
                    $query->orderBy('proveedores_turisticos.nit', $value);
                }
                if ($attribute == 'destino_nombre') {
                    $query->orderBy('destinos.nombre', $value);
                }
                if ($attribute == 'estado_verificacion') {
                    $query->orderBy('proveedores_turisticos.estado_verificacion', $value);
                }
                if ($attribute == 'estado') {
                    $query->orderBy('proveedores_turisticos.estado', $value);
                }
                if ($attribute == 'usuario_creacion_nombre') {
                    $query->orderBy('proveedores_turisticos.usuario_creacion_nombre', $value);
                }
                if ($attribute == 'fecha_creacion') {
                    $query->orderBy('proveedores_turisticos.created_at', $value);
                }
                if ($attribute == 'fecha_modificacion') {
                    $query->orderBy('proveedores_turisticos.updated_at', $value);
                }
            }
        } else {
            $query->orderBy('proveedores_turisticos.updated_at', 'desc');
        }

        $proveedores = $query->paginate($dto['limite'] ?? 100);
        $data = $proveedores->items();

        return [
            'datos' => $data,
            'desde' => $proveedores->firstItem(),
            'hasta' => $proveedores->lastItem(),
            'por_pagina' => $proveedores->perPage(),
            'pagina_actual' => $proveedores->currentPage(),
            'ultima_pagina' => $proveedores->lastPage(),
            'total' => $proveedores->total(),
        ];
    }

    public static function cargar($id)
    {
        $proveedor = ProveedorTuristico::find($id);

        return [
            'id' => $proveedor->id,
            'usuario_id' => $proveedor->usuario_id,
            'nombre_comercial' => $proveedor->nombre_comercial,
            'razon_social' => $proveedor->razon_social,
            'nit' => $proveedor->nit,
            'descripcion' => $proveedor->descripcion,
            'telefono' => $proveedor->telefono,
            'correo' => $proveedor->correo,
            'direccion' => $proveedor->direccion,
            'destino_id' => $proveedor->destino_id,
            'sitio_web' => $proveedor->sitio_web,
            'instagram' => $proveedor->instagram,
            'facebook' => $proveedor->facebook,
            'rnt' => $proveedor->rnt,
            'estado_verificacion' => $proveedor->estado_verificacion,
            'observaciones_verificacion' => $proveedor->observaciones_verificacion,
            'verificado_en' => $proveedor->verificado_en,
            'verificado_por' => $proveedor->verificado_por,
            'estado' => $proveedor->estado,
            'usuario_creacion_id' => $proveedor->usuario_creacion_id,
            'usuario_creacion_nombre' => $proveedor->usuario_creacion_nombre,
            'usuario_modificacion_id' => $proveedor->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $proveedor->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($proveedor->created_at))->format('Y-m-d H:i:s'),
            'fecha_modificacion' => (new Carbon($proveedor->updated_at))->format('Y-m-d H:i:s'),
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

        $proveedor = isset($dto['id']) ? ProveedorTuristico::find($dto['id']) : new ProveedorTuristico();

        $proveedorOriginal = $proveedor->toJson();

        $proveedor->fill($dto);
        $guardado = $proveedor->save();
        if (!$guardado) {
            throw new Exception('Ocurrió un error al intentar guardar el proveedor turístico.');
        }

        $auditoriaDto = [
            'id_recurso' => $proveedor->id,
            'nombre_recurso' => ProveedorTuristico::class,
            'descripcion_recurso' => $proveedor->nombre_comercial,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $proveedorOriginal : $proveedor->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $proveedor->toJson() : null,
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return ProveedorTuristico::cargar($proveedor->id);
    }

    public static function verificar($dto)
    {
        $user = Auth::user();
        $usuario = $user->usuario();

        $proveedor = ProveedorTuristico::find($dto['id']);
        $proveedorOriginal = $proveedor->toJson();

        $proveedor->fill([
            'estado_verificacion' => $dto['estado_verificacion'],
            'observaciones_verificacion' => $dto['observaciones_verificacion'] ?? null,
            'verificado_en' => now(),
            'verificado_por' => $usuario->id ?? null,
        ]);
        $guardado = $proveedor->save();
        if (!$guardado) {
            throw new Exception('Ocurrió un error al intentar verificar el proveedor turístico.');
        }

        $auditoriaDto = [
            'id_recurso' => $proveedor->id,
            'nombre_recurso' => ProveedorTuristico::class,
            'descripcion_recurso' => $proveedor->nombre_comercial,
            'accion' => AccionAuditoriaEnum::MODIFICAR,
            'recurso_original' => $proveedorOriginal,
            'recurso_resultante' => $proveedor->toJson(),
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return ProveedorTuristico::cargar($proveedor->id);
    }

    public static function eliminar($id)
    {
        $proveedor = ProveedorTuristico::find($id);

        $auditoriaDto = [
            'id_recurso' => $proveedor->id,
            'nombre_recurso' => ProveedorTuristico::class,
            'descripcion_recurso' => $proveedor->nombre_comercial,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $proveedor->toJson(),
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return $proveedor->delete();
    }
}
