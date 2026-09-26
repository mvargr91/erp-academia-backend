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

class Caracteristica extends Model
{
    use HasFactory;

    protected $table = 'caracteristicas';

    protected $fillable = [
        'nombre',
        'icono',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    public static function obtenerColeccionLigera($dto)
    {
        $query = DB::table('caracteristicas')
            ->select(
                'caracteristicas.id',
                'caracteristicas.nombre',
                'caracteristicas.icono',
                'caracteristicas.estado',
            )
            ->where('caracteristicas.estado', 1);

        $query->orderBy('nombre', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto)
    {
        $query = DB::table('caracteristicas')
            ->select(
                'caracteristicas.id',
                'caracteristicas.nombre',
                'caracteristicas.icono',
                'caracteristicas.estado',
                'caracteristicas.usuario_creacion_id',
                'caracteristicas.usuario_creacion_nombre',
                'caracteristicas.usuario_modificacion_id',
                'caracteristicas.usuario_modificacion_nombre',
                'caracteristicas.created_at as fecha_creacion',
                'caracteristicas.updated_at as fecha_modificacion',
            );

        if (isset($dto['nombre'])) {
            $query->where('caracteristicas.nombre', 'like', '%' . $dto['nombre'] . '%');
        }

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0) {
            foreach ($dto['ordenar_por'] as $attribute => $value) {
                if ($attribute == 'nombre') {
                    $query->orderBy('caracteristicas.nombre', $value);
                }
                if ($attribute == 'estado') {
                    $query->orderBy('caracteristicas.estado', $value);
                }
                if ($attribute == 'fecha_creacion') {
                    $query->orderBy('caracteristicas.created_at', $value);
                }
                if ($attribute == 'fecha_modificacion') {
                    $query->orderBy('caracteristicas.updated_at', $value);
                }
            }
        } else {
            $query->orderBy('caracteristicas.updated_at', 'desc');
        }

        $caracteristicas = $query->paginate($dto['limite'] ?? 100);
        $data = $caracteristicas->items();

        return [
            'datos' => $data,
            'desde' => $caracteristicas->firstItem(),
            'hasta' => $caracteristicas->lastItem(),
            'por_pagina' => $caracteristicas->perPage(),
            'pagina_actual' => $caracteristicas->currentPage(),
            'ultima_pagina' => $caracteristicas->lastPage(),
            'total' => $caracteristicas->total(),
        ];
    }

    public static function cargar($id)
    {
        $caracteristica = Caracteristica::find($id);

        return [
            'id' => $caracteristica->id,
            'nombre' => $caracteristica->nombre,
            'icono' => $caracteristica->icono,
            'estado' => $caracteristica->estado,
            'usuario_creacion_id' => $caracteristica->usuario_creacion_id,
            'usuario_creacion_nombre' => $caracteristica->usuario_creacion_nombre,
            'usuario_modificacion_id' => $caracteristica->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $caracteristica->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($caracteristica->created_at))->format('Y-m-d H:i:s'),
            'fecha_modificacion' => (new Carbon($caracteristica->updated_at))->format('Y-m-d H:i:s'),
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

        $caracteristica = isset($dto['id']) ? Caracteristica::find($dto['id']) : new Caracteristica();

        $caracteristicaOriginal = $caracteristica->toJson();

        $caracteristica->fill($dto);
        $guardado = $caracteristica->save();
        if (!$guardado) {
            throw new Exception('Ocurrió un error al intentar guardar la característica.');
        }

        $auditoriaDto = [
            'id_recurso' => $caracteristica->id,
            'nombre_recurso' => Caracteristica::class,
            'descripcion_recurso' => $caracteristica->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $caracteristicaOriginal : $caracteristica->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $caracteristica->toJson() : null,
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return Caracteristica::cargar($caracteristica->id);
    }

    public static function eliminar($id)
    {
        $caracteristica = Caracteristica::find($id);

        $auditoriaDto = [
            'id_recurso' => $caracteristica->id,
            'nombre_recurso' => Caracteristica::class,
            'descripcion_recurso' => $caracteristica->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $caracteristica->toJson(),
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return $caracteristica->delete();
    }
}
