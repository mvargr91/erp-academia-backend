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

class Categoria extends Model
{
    use HasFactory;

    protected $table = 'categorias';

    protected $fillable = [
        'categoria_padre_id',
        'nombre',
        'slug',
        'descripcion',
        'imagen',
        'orden',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    public static function obtenerColeccionLigera($dto)
    {
        $query = DB::table('categorias')
            ->select(
                'categorias.id',
                'categorias.nombre',
                'categorias.estado',
            )
            ->where('categorias.estado', 1);

        $query->orderBy('nombre', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto)
    {
        $query = DB::table('categorias')
            ->leftJoin('categorias as padres', 'padres.id', '=', 'categorias.categoria_padre_id')
            ->select(
                'categorias.id',
                'categorias.categoria_padre_id',
                'padres.nombre as categoria_padre_nombre',
                'categorias.nombre',
                'categorias.slug',
                'categorias.descripcion',
                'categorias.imagen',
                'categorias.orden',
                'categorias.estado',
                'categorias.usuario_creacion_id',
                'categorias.usuario_creacion_nombre',
                'categorias.usuario_modificacion_id',
                'categorias.usuario_modificacion_nombre',
                'categorias.created_at as fecha_creacion',
                'categorias.updated_at as fecha_modificacion',
            );

        if (isset($dto['nombre'])) {
            $query->where('categorias.nombre', 'like', '%' . $dto['nombre'] . '%');
        }

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0) {
            foreach ($dto['ordenar_por'] as $attribute => $value) {
                if ($attribute == 'nombre') {
                    $query->orderBy('categorias.nombre', $value);
                }
                if ($attribute == 'orden') {
                    $query->orderBy('categorias.orden', $value);
                }
                if ($attribute == 'estado') {
                    $query->orderBy('categorias.estado', $value);
                }
                if ($attribute == 'usuario_creacion_nombre') {
                    $query->orderBy('categorias.usuario_creacion_nombre', $value);
                }
                if ($attribute == 'fecha_creacion') {
                    $query->orderBy('categorias.created_at', $value);
                }
                if ($attribute == 'fecha_modificacion') {
                    $query->orderBy('categorias.updated_at', $value);
                }
            }
        } else {
            $query->orderBy('categorias.orden', 'asc');
        }

        $categorias = $query->paginate($dto['limite'] ?? 100);
        $data = $categorias->items();

        return [
            'datos' => $data,
            'desde' => $categorias->firstItem(),
            'hasta' => $categorias->lastItem(),
            'por_pagina' => $categorias->perPage(),
            'pagina_actual' => $categorias->currentPage(),
            'ultima_pagina' => $categorias->lastPage(),
            'total' => $categorias->total(),
        ];
    }

    public static function cargar($id)
    {
        $categoria = Categoria::find($id);

        return [
            'id' => $categoria->id,
            'categoria_padre_id' => $categoria->categoria_padre_id,
            'nombre' => $categoria->nombre,
            'slug' => $categoria->slug,
            'descripcion' => $categoria->descripcion,
            'imagen' => $categoria->imagen,
            'orden' => $categoria->orden,
            'estado' => $categoria->estado,
            'usuario_creacion_id' => $categoria->usuario_creacion_id,
            'usuario_creacion_nombre' => $categoria->usuario_creacion_nombre,
            'usuario_modificacion_id' => $categoria->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $categoria->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($categoria->created_at))->format('Y-m-d H:i:s'),
            'fecha_modificacion' => (new Carbon($categoria->updated_at))->format('Y-m-d H:i:s'),
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

        $categoria = isset($dto['id']) ? Categoria::find($dto['id']) : new Categoria();

        $categoriaOriginal = $categoria->toJson();

        $categoria->fill($dto);
        $guardado = $categoria->save();
        if (!$guardado) {
            throw new Exception('Ocurrió un error al intentar guardar la categoría.');
        }

        $auditoriaDto = [
            'id_recurso' => $categoria->id,
            'nombre_recurso' => Categoria::class,
            'descripcion_recurso' => $categoria->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $categoriaOriginal : $categoria->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $categoria->toJson() : null,
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return Categoria::cargar($categoria->id);
    }

    public static function eliminar($id)
    {
        $categoria = Categoria::find($id);

        $auditoriaDto = [
            'id_recurso' => $categoria->id,
            'nombre_recurso' => Categoria::class,
            'descripcion_recurso' => $categoria->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $categoria->toJson(),
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return $categoria->delete();
    }


    // ---------------------- Portal público -------------------------- //

    public static function obtenerColeccionPublica($dto)
    {
        return DB::table('categorias')
            ->where('categorias.estado', 1)
            ->select(
                'categorias.id',
                'categorias.categoria_padre_id',
                'categorias.nombre',
                'categorias.slug',
                'categorias.imagen',
            )
            ->orderBy('categorias.orden')
            ->orderBy('categorias.nombre')
            ->get();
    }
}
