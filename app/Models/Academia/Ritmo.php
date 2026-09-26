<?php

namespace App\Models\Academia;

use Exception;
use Carbon\Carbon;
use App\Enum\AccionAuditoriaEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\AuditoriaTabla;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ritmo extends Model
{
    use HasFactory;

    protected $table = 'ritmos';

    protected $fillable = [
        'nombre',
        'descripcion',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    public static function obtenerColeccionLigera($dto)
    {
        return DB::table('ritmos')
            ->select('id', 'nombre')
            ->where('estado', 1)
            ->orderBy('nombre', 'asc')
            ->get();
    }

    public static function obtenerColeccion($dto)
    {
        $query = DB::table('ritmos')
            ->select(
                'id',
                'nombre',
                'descripcion',
                'estado',
                'usuario_creacion_id',
                'usuario_creacion_nombre',
                'usuario_modificacion_id',
                'usuario_modificacion_nombre',
                'created_at as fecha_creacion',
                'updated_at as fecha_modificacion',
            );

        if (isset($dto['nombre'])) {
            $query->where('nombre', 'like', '%' . $dto['nombre'] . '%');
        }

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0) {
            foreach ($dto['ordenar_por'] as $attribute => $value) {
                if (in_array($attribute, ['nombre', 'estado', 'usuario_creacion_nombre'])) {
                    $query->orderBy($attribute, $value);
                }
                if ($attribute == 'fecha_creacion') {
                    $query->orderBy('created_at', $value);
                }
                if ($attribute == 'fecha_modificacion') {
                    $query->orderBy('updated_at', $value);
                }
            }
        } else {
            $query->orderBy('updated_at', 'desc');
        }

        $ritmos = $query->paginate($dto['limite'] ?? 100);

        return [
            'datos' => $ritmos->items(),
            'desde' => $ritmos->firstItem(),
            'hasta' => $ritmos->lastItem(),
            'por_pagina' => $ritmos->perPage(),
            'pagina_actual' => $ritmos->currentPage(),
            'ultima_pagina' => $ritmos->lastPage(),
            'total' => $ritmos->total(),
        ];
    }

    public static function cargar($id)
    {
        $ritmo = Ritmo::find($id);

        return [
            'id' => $ritmo->id,
            'nombre' => $ritmo->nombre,
            'descripcion' => $ritmo->descripcion,
            'estado' => $ritmo->estado,
            'usuario_creacion_id' => $ritmo->usuario_creacion_id,
            'usuario_creacion_nombre' => $ritmo->usuario_creacion_nombre,
            'usuario_modificacion_id' => $ritmo->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $ritmo->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($ritmo->created_at))->format('Y-m-d H:i:s'),
            'fecha_modificacion' => (new Carbon($ritmo->updated_at))->format('Y-m-d H:i:s'),
        ];
    }

    public static function modificarOCrear($dto)
    {
        $user = Auth::user();
        $usuario = $user->usuario();

        if (!isset($dto['id'])) {
            $dto['usuario_creacion_id'] = $usuario->id ?? null;
            $dto['usuario_creacion_nombre'] = $usuario->nombre ?? null;
        }
        $dto['usuario_modificacion_id'] = $usuario->id ?? null;
        $dto['usuario_modificacion_nombre'] = $usuario->nombre ?? null;

        $ritmo = isset($dto['id']) ? Ritmo::find($dto['id']) : new Ritmo();
        $original = $ritmo->toJson();

        $ritmo->fill($dto);
        if (!$ritmo->save()) {
            throw new Exception('Ocurrió un error al intentar guardar el ritmo.');
        }

        AuditoriaTabla::crear([
            'id_recurso' => $ritmo->id,
            'nombre_recurso' => Ritmo::class,
            'descripcion_recurso' => $ritmo->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $original : $ritmo->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $ritmo->toJson() : null,
        ]);

        return Ritmo::cargar($ritmo->id);
    }

    public static function eliminar($id)
    {
        $ritmo = Ritmo::find($id);

        AuditoriaTabla::crear([
            'id_recurso' => $ritmo->id,
            'nombre_recurso' => Ritmo::class,
            'descripcion_recurso' => $ritmo->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $ritmo->toJson(),
        ]);

        return $ritmo->delete();
    }
}
