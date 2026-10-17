<?php

namespace App\Models\Academia;

use Exception;
use Carbon\Carbon;
use App\Enum\AccionAuditoriaEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\AuditoriaTabla;
use App\Services\Academia\Tarifas;
use App\Services\Academia\CalendarioCurso;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Modalidad de un curso: cómo se cobra (por ciclo de N clases o por mes) y cuánto dura cada clase.
 * Su precio está en Tarifas (escalas_precio.modalidad_id).
 */
class Modalidad extends Model
{
    use HasFactory;

    protected $table = 'modalidades';

    protected $fillable = [
        'nombre',
        'tipo_cobro',
        'clases_por_ciclo',
        'duracion_minutos',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    /** Incluye las inactivas (con su estado): un curso que ya tiene una la sigue mostrando. */
    public static function obtenerColeccionLigera($dto)
    {
        return DB::table('modalidades')
            ->select('id', 'nombre', 'tipo_cobro', 'clases_por_ciclo', 'duracion_minutos', 'estado')
            ->orderBy('nombre', 'asc')
            ->get();
    }

    public static function obtenerColeccion($dto)
    {
        $query = DB::table('modalidades')
            ->select(
                'id',
                'nombre',
                'tipo_cobro',
                'clases_por_ciclo',
                'duracion_minutos',
                DB::raw('(SELECT COUNT(*) FROM cursos WHERE cursos.modalidad_id = modalidades.id AND cursos.estado = 1) as cursos'),
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
                if (in_array($attribute, ['nombre', 'tipo_cobro', 'clases_por_ciclo', 'duracion_minutos', 'estado', 'usuario_creacion_nombre'])) {
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
            $query->orderBy('nombre', 'asc');
        }

        $modalidades = $query->paginate($dto['limite'] ?? 100);

        return [
            'datos' => $modalidades->items(),
            'desde' => $modalidades->firstItem(),
            'hasta' => $modalidades->lastItem(),
            'por_pagina' => $modalidades->perPage(),
            'pagina_actual' => $modalidades->currentPage(),
            'ultima_pagina' => $modalidades->lastPage(),
            'total' => $modalidades->total(),
        ];
    }

    public static function cargar($id)
    {
        $modalidad = Modalidad::find($id);

        return [
            'id' => $modalidad->id,
            'nombre' => $modalidad->nombre,
            'tipo_cobro' => $modalidad->tipo_cobro,
            'clases_por_ciclo' => $modalidad->clases_por_ciclo,
            'duracion_minutos' => $modalidad->duracion_minutos,
            'estado' => $modalidad->estado,
            'usuario_creacion_id' => $modalidad->usuario_creacion_id,
            'usuario_creacion_nombre' => $modalidad->usuario_creacion_nombre,
            'usuario_modificacion_id' => $modalidad->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $modalidad->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($modalidad->created_at))->format('Y-m-d H:i:s'),
            'fecha_modificacion' => (new Carbon($modalidad->updated_at))->format('Y-m-d H:i:s'),
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

        // Una mensualidad no tiene clases por ciclo: el mes trae las que traiga.
        if (($dto['tipo_cobro'] ?? null) === CalendarioCurso::MENSUAL) {
            $dto['clases_por_ciclo'] = null;
        }

        $modalidad = isset($dto['id']) ? Modalidad::find($dto['id']) : new Modalidad();
        $original = $modalidad->toJson();

        $modalidad->fill($dto);
        if (!$modalidad->save()) {
            throw new Exception('Ocurrió un error al intentar guardar la modalidad.');
        }
        CalendarioCurso::olvidarModalidades();

        AuditoriaTabla::crear([
            'id_recurso' => $modalidad->id,
            'nombre_recurso' => Modalidad::class,
            'descripcion_recurso' => $modalidad->nombre,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $original : $modalidad->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $modalidad->toJson() : null,
        ]);

        return Modalidad::cargar($modalidad->id);
    }

    /** Cursos (activos o no) que usan la modalidad: mientras tenga, no se puede eliminar. */
    public static function cursos($id): int
    {
        return DB::table('cursos')->where('modalidad_id', $id)->count();
    }

    public static function eliminar($id)
    {
        $modalidad = Modalidad::find($id);

        AuditoriaTabla::crear([
            'id_recurso' => $modalidad->id,
            'nombre_recurso' => Modalidad::class,
            'descripcion_recurso' => $modalidad->nombre,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $modalidad->toJson(),
        ]);

        // Sus tarifas se borran con ella (escalas_precio en cascada).
        $eliminado = $modalidad->delete();
        CalendarioCurso::olvidarModalidades();
        Tarifas::olvidarCache();
        return $eliminado;
    }
}
