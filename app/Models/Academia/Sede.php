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

class Sede extends Model
{
    use HasFactory;

    protected $table = 'sedes';

    protected $fillable = [
        'nombre',
        'direccion',
        'ciudad',
        'telefono',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    // Tablas que referencian una sede: mientras tengan registros, la sede no se puede eliminar.
    private const USOS = [
        'cursos' => 'cursos',
        'clases_privadas' => 'clases personalizadas',
        'pagos' => 'pagos',
        'paquetes_alumno' => 'paquetes',
        'alumnos' => 'alumnos',
        'planes' => 'planes',
        'cierres_academia' => 'cierres',
    ];

    private static $actual = false;

    /**
     * Sede elegida en el selector del encabezado (cabecera X-Sede), o null si se ven todas.
     * Una sede inexistente o inactiva se ignora.
     */
    public static function actual(): ?int
    {
        if (self::$actual === false) {
            $id = (int) request()->header('X-Sede');
            self::$actual = $id > 0 && DB::table('sedes')->where('id', $id)->where('estado', 1)->exists() ? $id : null;
        }
        return self::$actual;
    }

    /** Sede para un registro nuevo que no la trae: la del selector o, si no hay, la primera activa. */
    public static function porDefecto(): ?int
    {
        return self::actual() ?? DB::table('sedes')->where('estado', 1)->orderBy('id')->value('id');
    }

    /**
     * Sede de un registro que la exige: la indicada; si no viene, la del selector o la única
     * sede activa. Con varias sedes y ninguna elegida devuelve null (la validación la pedirá).
     */
    public static function resolver($sedeId): ?int
    {
        if ($sedeId) {
            return (int) $sedeId;
        }
        if (self::actual()) {
            return self::actual();
        }
        $activas = DB::table('sedes')->where('estado', 1)->limit(2)->pluck('id');
        return $activas->count() === 1 ? (int) $activas[0] : null;
    }

    /** Restringe la consulta a la sede del selector (si hay una elegida). */
    public static function filtrar($query, string $columna, bool $incluirGenerales = false)
    {
        $sedeId = self::actual();
        if ($sedeId) {
            $query->where(function ($q) use ($columna, $sedeId, $incluirGenerales) {
                $q->where($columna, $sedeId);
                if ($incluirGenerales) {
                    $q->orWhereNull($columna);
                }
            });
        }
        return $query;
    }

    /** Motivo por el que la sede no se puede eliminar, o null si se puede. */
    public static function motivoNoEliminable($id): ?string
    {
        if (DB::table('sedes')->count() <= 1) {
            return 'La academia debe tener al menos una sede.';
        }
        $usos = [];
        foreach (self::USOS as $tabla => $nombre) {
            if (DB::table($tabla)->where('sede_id', $id)->exists()) {
                $usos[] = $nombre;
            }
        }
        return $usos
            ? 'No se puede eliminar: la sede tiene ' . implode(', ', $usos) . '. Puedes dejarla inactiva.'
            : null;
    }

    public static function obtenerColeccionLigera($dto)
    {
        return DB::table('sedes')
            ->select('id', 'nombre', 'direccion', 'ciudad')
            ->where('estado', 1)
            ->orderBy('nombre', 'asc')
            ->get();
    }

    public static function obtenerColeccion($dto)
    {
        $query = DB::table('sedes')
            ->select(
                'id',
                'nombre',
                'direccion',
                'ciudad',
                'telefono',
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
                if (in_array($attribute, ['nombre', 'ciudad', 'estado', 'usuario_creacion_nombre'])) {
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

        $sedes = $query->paginate($dto['limite'] ?? 100);

        return [
            'datos' => $sedes->items(),
            'desde' => $sedes->firstItem(),
            'hasta' => $sedes->lastItem(),
            'por_pagina' => $sedes->perPage(),
            'pagina_actual' => $sedes->currentPage(),
            'ultima_pagina' => $sedes->lastPage(),
            'total' => $sedes->total(),
        ];
    }

    public static function cargar($id)
    {
        $sede = Sede::find($id);

        return [
            'id' => $sede->id,
            'nombre' => $sede->nombre,
            'direccion' => $sede->direccion,
            'ciudad' => $sede->ciudad,
            'telefono' => $sede->telefono,
            'estado' => $sede->estado,
            'usuario_creacion_id' => $sede->usuario_creacion_id,
            'usuario_creacion_nombre' => $sede->usuario_creacion_nombre,
            'usuario_modificacion_id' => $sede->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $sede->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($sede->created_at))->format('Y-m-d H:i:s'),
            'fecha_modificacion' => (new Carbon($sede->updated_at))->format('Y-m-d H:i:s'),
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

        $sede = isset($dto['id']) ? Sede::find($dto['id']) : new Sede();
        $original = $sede->toJson();

        $sede->fill($dto);
        if (!$sede->save()) {
            throw new Exception('Ocurrió un error al intentar guardar la sede.');
        }

        AuditoriaTabla::crear([
            'id_recurso' => $sede->id,
            'nombre_recurso' => Sede::class,
            'descripcion_recurso' => $sede->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $original : $sede->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $sede->toJson() : null,
        ]);

        return Sede::cargar($sede->id);
    }

    public static function eliminar($id)
    {
        $sede = Sede::find($id);

        AuditoriaTabla::crear([
            'id_recurso' => $sede->id,
            'nombre_recurso' => Sede::class,
            'descripcion_recurso' => $sede->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $sede->toJson(),
        ]);

        return $sede->delete();
    }
}
