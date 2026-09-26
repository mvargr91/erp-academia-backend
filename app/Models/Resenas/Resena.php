<?php

namespace App\Models\Resenas;

use Exception;
use Carbon\Carbon;
use App\Enum\AccionAuditoriaEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\AuditoriaTabla;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Resena extends Model
{
    use HasFactory;

    protected $table = 'resenas';

    protected $fillable = [
        'usuario_id',
        'experiencia_id',
        'reserva_id',
        'calificacion',
        'comentario',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    public static function obtenerColeccion($dto)
    {
        $query = DB::table('resenas')
            ->join('experiencias', 'experiencias.id', '=', 'resenas.experiencia_id')
            ->select(
                'resenas.id',
                'resenas.usuario_id',
                'resenas.experiencia_id',
                'experiencias.nombre as experiencia_nombre',
                'resenas.reserva_id',
                'resenas.calificacion',
                'resenas.comentario',
                'resenas.estado',
                'resenas.usuario_creacion_id',
                'resenas.usuario_creacion_nombre',
                'resenas.usuario_modificacion_id',
                'resenas.usuario_modificacion_nombre',
                'resenas.created_at as fecha_creacion',
                'resenas.updated_at as fecha_modificacion',
            );

        if (isset($dto['experiencia_id'])) {
            $query->where('resenas.experiencia_id', $dto['experiencia_id']);
        }
        if (isset($dto['usuario_id'])) {
            $query->where('resenas.usuario_id', $dto['usuario_id']);
        }
        if (isset($dto['estado'])) {
            $query->where('resenas.estado', $dto['estado']);
        }

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0) {
            foreach ($dto['ordenar_por'] as $attribute => $value) {
                if ($attribute == 'calificacion') {
                    $query->orderBy('resenas.calificacion', $value);
                }
                if ($attribute == 'estado') {
                    $query->orderBy('resenas.estado', $value);
                }
                if ($attribute == 'fecha_creacion') {
                    $query->orderBy('resenas.created_at', $value);
                }
                if ($attribute == 'fecha_modificacion') {
                    $query->orderBy('resenas.updated_at', $value);
                }
            }
        } else {
            $query->orderBy('resenas.updated_at', 'desc');
        }

        $resenas = $query->paginate($dto['limite'] ?? 100);
        $data = $resenas->items();

        return [
            'datos' => $data,
            'desde' => $resenas->firstItem(),
            'hasta' => $resenas->lastItem(),
            'por_pagina' => $resenas->perPage(),
            'pagina_actual' => $resenas->currentPage(),
            'ultima_pagina' => $resenas->lastPage(),
            'total' => $resenas->total(),
        ];
    }

    public static function cargar($id)
    {
        $resena = Resena::find($id);

        $multimedia = DB::table('multimedia_resena')
            ->where('resena_id', $id)
            ->get();

        return [
            'id' => $resena->id,
            'usuario_id' => $resena->usuario_id,
            'experiencia_id' => $resena->experiencia_id,
            'reserva_id' => $resena->reserva_id,
            'calificacion' => $resena->calificacion,
            'comentario' => $resena->comentario,
            'estado' => $resena->estado,
            'multimedia' => $multimedia,
            'usuario_creacion_id' => $resena->usuario_creacion_id,
            'usuario_creacion_nombre' => $resena->usuario_creacion_nombre,
            'usuario_modificacion_id' => $resena->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $resena->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($resena->created_at))->format('Y-m-d H:i:s'),
            'fecha_modificacion' => (new Carbon($resena->updated_at))->format('Y-m-d H:i:s'),
        ];
    }

    public static function modificarOCrear($dto)
    {
        $user = Auth::user();
        $usuario = $user->usuario();

        if (!isset($dto['id'])) {
            $dto['usuario_creacion_id'] = $usuario->id ?? ($dto['usuario_creacion_id'] ?? null);
            $dto['usuario_creacion_nombre'] = $usuario->nombre ?? ($dto['usuario_creacion_nombre'] ?? null);
            $dto['estado'] = $dto['estado'] ?? 'pendiente';
        }
        if (isset($usuario) || isset($dto['usuario_modificacion_id'])) {
            $dto['usuario_modificacion_id'] = $usuario->id ?? ($dto['usuario_modificacion_id'] ?? null);
            $dto['usuario_modificacion_nombre'] = $usuario->nombre ?? ($dto['usuario_modificacion_nombre'] ?? null);
        }

        $resena = isset($dto['id']) ? Resena::find($dto['id']) : new Resena();

        $resenaOriginal = $resena->toJson();

        $resena->fill($dto);
        $guardado = $resena->save();
        if (!$guardado) {
            throw new Exception('Ocurrió un error al intentar guardar la reseña.');
        }

        $auditoriaDto = [
            'id_recurso' => $resena->id,
            'nombre_recurso' => Resena::class,
            'descripcion_recurso' => 'Reseña #' . $resena->id,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $resenaOriginal : $resena->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $resena->toJson() : null,
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return Resena::cargar($resena->id);
    }

    public static function eliminar($id)
    {
        $resena = Resena::find($id);

        $auditoriaDto = [
            'id_recurso' => $resena->id,
            'nombre_recurso' => Resena::class,
            'descripcion_recurso' => 'Reseña #' . $resena->id,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $resena->toJson(),
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return $resena->delete();
    }
}
