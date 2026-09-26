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

class Destino extends Model
{
    use HasFactory;

    protected $table = 'destinos';

    protected $fillable = [
        'nombre',
        'slug',
        'pais',
        'departamento',
        'ciudad',
        'descripcion',
        'imagen',
        'latitud',
        'longitud',
        'destacado',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    public static function obtenerColeccionLigera($dto)
    {
        $query = DB::table('destinos')
            ->select(
                'destinos.id',
                'destinos.nombre',
                'destinos.estado',
            )
            ->where('destinos.estado', 1);

        $query->orderBy('nombre', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto)
    {
        $query = DB::table('destinos')
            ->select(
                'destinos.id',
                'destinos.nombre',
                'destinos.slug',
                'destinos.pais',
                'destinos.departamento',
                'destinos.ciudad',
                'destinos.descripcion',
                'destinos.imagen',
                'destinos.latitud',
                'destinos.longitud',
                'destinos.destacado',
                'destinos.estado',
                'destinos.usuario_creacion_id',
                'destinos.usuario_creacion_nombre',
                'destinos.usuario_modificacion_id',
                'destinos.usuario_modificacion_nombre',
                'destinos.created_at as fecha_creacion',
                'destinos.updated_at as fecha_modificacion',
            );

        if (isset($dto['nombre'])) {
            $query->where('destinos.nombre', 'like', '%' . $dto['nombre'] . '%');
        }

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0) {
            foreach ($dto['ordenar_por'] as $attribute => $value) {
                if ($attribute == 'nombre') {
                    $query->orderBy('destinos.nombre', $value);
                }
                if ($attribute == 'ciudad') {
                    $query->orderBy('destinos.ciudad', $value);
                }
                if ($attribute == 'departamento') {
                    $query->orderBy('destinos.departamento', $value);
                }
                if ($attribute == 'destacado') {
                    $query->orderBy('destinos.destacado', $value);
                }
                if ($attribute == 'estado') {
                    $query->orderBy('destinos.estado', $value);
                }
                if ($attribute == 'usuario_creacion_nombre') {
                    $query->orderBy('destinos.usuario_creacion_nombre', $value);
                }
                if ($attribute == 'fecha_creacion') {
                    $query->orderBy('destinos.created_at', $value);
                }
                if ($attribute == 'fecha_modificacion') {
                    $query->orderBy('destinos.updated_at', $value);
                }
            }
        } else {
            $query->orderBy('destinos.updated_at', 'desc');
        }

        $destinos = $query->paginate($dto['limite'] ?? 100);
        $data = $destinos->items();

        return [
            'datos' => $data,
            'desde' => $destinos->firstItem(),
            'hasta' => $destinos->lastItem(),
            'por_pagina' => $destinos->perPage(),
            'pagina_actual' => $destinos->currentPage(),
            'ultima_pagina' => $destinos->lastPage(),
            'total' => $destinos->total(),
        ];
    }

    public static function cargar($id)
    {
        $destino = Destino::find($id);

        return [
            'id' => $destino->id,
            'nombre' => $destino->nombre,
            'slug' => $destino->slug,
            'pais' => $destino->pais,
            'departamento' => $destino->departamento,
            'ciudad' => $destino->ciudad,
            'descripcion' => $destino->descripcion,
            'imagen' => $destino->imagen,
            'latitud' => $destino->latitud,
            'longitud' => $destino->longitud,
            'destacado' => $destino->destacado,
            'estado' => $destino->estado,
            'usuario_creacion_id' => $destino->usuario_creacion_id,
            'usuario_creacion_nombre' => $destino->usuario_creacion_nombre,
            'usuario_modificacion_id' => $destino->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $destino->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($destino->created_at))->format('Y-m-d H:i:s'),
            'fecha_modificacion' => (new Carbon($destino->updated_at))->format('Y-m-d H:i:s'),
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

        $destino = isset($dto['id']) ? Destino::find($dto['id']) : new Destino();

        $destinoOriginal = $destino->toJson();

        $destino->fill($dto);
        $guardado = $destino->save();
        if (!$guardado) {
            throw new Exception('Ocurrió un error al intentar guardar el destino.');
        }

        $auditoriaDto = [
            'id_recurso' => $destino->id,
            'nombre_recurso' => Destino::class,
            'descripcion_recurso' => $destino->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $destinoOriginal : $destino->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $destino->toJson() : null,
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return Destino::cargar($destino->id);
    }

    public static function eliminar($id)
    {
        $destino = Destino::find($id);

        $auditoriaDto = [
            'id_recurso' => $destino->id,
            'nombre_recurso' => Destino::class,
            'descripcion_recurso' => $destino->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $destino->toJson(),
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return $destino->delete();
    }


    // ---------------------- Portal público -------------------------- //

    public static function obtenerColeccionPublica($dto)
    {
        $query = DB::table('destinos')
            ->where('destinos.estado', 1)
            ->select(
                'destinos.id',
                'destinos.nombre',
                'destinos.slug',
                'destinos.ciudad',
                'destinos.departamento',
                'destinos.descripcion',
                'destinos.imagen',
                'destinos.destacado',
                DB::raw("(SELECT COUNT(*) FROM experiencias e
                    WHERE e.destino_id = destinos.id AND e.estado = 'publicada') AS total_experiencias"),
            );

        if (isset($dto['destacado'])) {
            $query->where('destinos.destacado', 1);
        }

        return $query->orderBy('destinos.destacado', 'desc')->orderBy('destinos.nombre')->get();
    }
}
