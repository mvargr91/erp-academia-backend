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

class Plan extends Model
{
    use HasFactory;

    protected $table = 'planes';

    protected $fillable = [
        'nombre',
        'sede_id',
        'descripcion',
        'valor',
        'periodicidad',
        'num_clases',
        'vigencia_dias',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    public static function obtenerColeccionLigera($dto)
    {
        // Planes generales (sin sede) y los de la sede pedida (?sede_id=) o la del selector.
        $query = DB::table('planes')
            ->select('id', 'nombre', 'valor', 'periodicidad', 'num_clases', 'vigencia_dias', 'sede_id')
            ->where('estado', 1)
            ->orderBy('nombre', 'asc');
        if (!empty($dto['sede_id'])) {
            $query->where(fn ($q) => $q->whereNull('sede_id')->orWhere('sede_id', $dto['sede_id']));
        } else {
            Sede::filtrar($query, 'sede_id', true);
        }
        return $query->get();
    }

    public static function obtenerColeccion($dto)
    {
        $query = DB::table('planes')
            ->select(
                'id',
                'nombre',
                'sede_id',
                DB::raw("COALESCE((SELECT nombre FROM sedes WHERE sedes.id = planes.sede_id), 'Todas') as sede_nombre"),
                'descripcion',
                'valor',
                'periodicidad',
                'num_clases',
                'vigencia_dias',
                'estado',
                'usuario_creacion_id',
                'usuario_creacion_nombre',
                'usuario_modificacion_id',
                'usuario_modificacion_nombre',
                'created_at as fecha_creacion',
                'updated_at as fecha_modificacion',
            );

        Sede::filtrar($query, 'sede_id', true);

        if (isset($dto['nombre'])) {
            $query->where('nombre', 'like', '%' . $dto['nombre'] . '%');
        }

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0) {
            foreach ($dto['ordenar_por'] as $attribute => $value) {
                if (in_array($attribute, ['nombre', 'valor', 'periodicidad', 'estado', 'usuario_creacion_nombre'])) {
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

        $planes = $query->paginate($dto['limite'] ?? 100);

        return [
            'datos' => $planes->items(),
            'desde' => $planes->firstItem(),
            'hasta' => $planes->lastItem(),
            'por_pagina' => $planes->perPage(),
            'pagina_actual' => $planes->currentPage(),
            'ultima_pagina' => $planes->lastPage(),
            'total' => $planes->total(),
        ];
    }

    public static function cargar($id)
    {
        $plan = Plan::find($id);

        return [
            'id' => $plan->id,
            'nombre' => $plan->nombre,
            'sede_id' => $plan->sede_id,
            'descripcion' => $plan->descripcion,
            'valor' => $plan->valor,
            'periodicidad' => $plan->periodicidad,
            'num_clases' => $plan->num_clases,
            'vigencia_dias' => $plan->vigencia_dias,
            'estado' => $plan->estado,
            'usuario_creacion_id' => $plan->usuario_creacion_id,
            'usuario_creacion_nombre' => $plan->usuario_creacion_nombre,
            'usuario_modificacion_id' => $plan->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $plan->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($plan->created_at))->format('Y-m-d H:i:s'),
            'fecha_modificacion' => (new Carbon($plan->updated_at))->format('Y-m-d H:i:s'),
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

        $plan = isset($dto['id']) ? Plan::find($dto['id']) : new Plan();
        $original = $plan->toJson();

        $plan->fill($dto);
        if (!$plan->save()) {
            throw new Exception('Ocurrió un error al intentar guardar el plan.');
        }

        AuditoriaTabla::crear([
            'id_recurso' => $plan->id,
            'nombre_recurso' => Plan::class,
            'descripcion_recurso' => $plan->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $original : $plan->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $plan->toJson() : null,
        ]);

        return Plan::cargar($plan->id);
    }

    public static function eliminar($id)
    {
        $plan = Plan::find($id);

        AuditoriaTabla::crear([
            'id_recurso' => $plan->id,
            'nombre_recurso' => Plan::class,
            'descripcion_recurso' => $plan->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $plan->toJson(),
        ]);

        return $plan->delete();
    }
}
