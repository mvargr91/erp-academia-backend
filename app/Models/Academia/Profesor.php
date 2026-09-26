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

class Profesor extends Model
{
    use HasFactory;

    protected $table = 'profesores';

    protected $fillable = [
        'usuario_id',
        'nombres',
        'apellidos',
        'documento',
        'telefono',
        'correo',
        'especialidad',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    public static function obtenerColeccionLigera($dto)
    {
        return DB::table('profesores')
            ->select('id', DB::raw("CONCAT(nombres,' ',apellidos) as nombre"))
            ->where('estado', 1)
            ->orderBy('nombres', 'asc')
            ->get();
    }

    public static function obtenerColeccion($dto)
    {
        $query = DB::table('profesores')
            ->select(
                'id',
                'usuario_id',
                'nombres',
                'apellidos',
                'documento',
                'telefono',
                'correo',
                'especialidad',
                'estado',
                'usuario_creacion_id',
                'usuario_creacion_nombre',
                'usuario_modificacion_id',
                'usuario_modificacion_nombre',
                'created_at as fecha_creacion',
                'updated_at as fecha_modificacion',
            );

        if (isset($dto['nombre'])) {
            $query->where(function ($q) use ($dto) {
                $q->where('nombres', 'like', "%{$dto['nombre']}%")
                    ->orWhere('apellidos', 'like', "%{$dto['nombre']}%");
            });
        }

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0) {
            foreach ($dto['ordenar_por'] as $attribute => $value) {
                if (in_array($attribute, ['nombres', 'apellidos', 'documento', 'correo', 'especialidad', 'estado', 'usuario_creacion_nombre'])) {
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

        $profesores = $query->paginate($dto['limite'] ?? 100);

        return [
            'datos' => $profesores->items(),
            'desde' => $profesores->firstItem(),
            'hasta' => $profesores->lastItem(),
            'por_pagina' => $profesores->perPage(),
            'pagina_actual' => $profesores->currentPage(),
            'ultima_pagina' => $profesores->lastPage(),
            'total' => $profesores->total(),
        ];
    }

    public static function cargar($id)
    {
        $profesor = Profesor::find($id);

        return [
            'id' => $profesor->id,
            'usuario_id' => $profesor->usuario_id,
            'nombres' => $profesor->nombres,
            'apellidos' => $profesor->apellidos,
            'documento' => $profesor->documento,
            'telefono' => $profesor->telefono,
            'correo' => $profesor->correo,
            'especialidad' => $profesor->especialidad,
            'estado' => $profesor->estado,
            'usuario_creacion_id' => $profesor->usuario_creacion_id,
            'usuario_creacion_nombre' => $profesor->usuario_creacion_nombre,
            'usuario_modificacion_id' => $profesor->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $profesor->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($profesor->created_at))->format('Y-m-d H:i:s'),
            'fecha_modificacion' => (new Carbon($profesor->updated_at))->format('Y-m-d H:i:s'),
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

        $profesor = isset($dto['id']) ? Profesor::find($dto['id']) : new Profesor();
        $original = $profesor->toJson();

        $profesor->fill($dto);
        if (!$profesor->save()) {
            throw new Exception('Ocurrió un error al intentar guardar el profesor.');
        }

        AuditoriaTabla::crear([
            'id_recurso' => $profesor->id,
            'nombre_recurso' => Profesor::class,
            'descripcion_recurso' => "$profesor->nombres $profesor->apellidos",
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $original : $profesor->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $profesor->toJson() : null,
        ]);

        return Profesor::cargar($profesor->id);
    }

    public static function eliminar($id)
    {
        $profesor = Profesor::find($id);

        AuditoriaTabla::crear([
            'id_recurso' => $profesor->id,
            'nombre_recurso' => Profesor::class,
            'descripcion_recurso' => "$profesor->nombres $profesor->apellidos",
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $profesor->toJson(),
        ]);

        return $profesor->delete();
    }
}
