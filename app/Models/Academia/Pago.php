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

class Pago extends Model
{
    use HasFactory;

    protected $table = 'pagos';

    protected $fillable = [
        'alumno_id',
        'curso_id',
        'plan_id',
        'monto',
        'fecha_pago',
        'metodo_pago',
        'referencia',
        'observacion',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    public static function obtenerColeccion($dto)
    {
        $query = DB::table('pagos')
            ->join('alumnos', 'alumnos.id', '=', 'pagos.alumno_id')
            ->leftJoin('cursos', 'cursos.id', '=', 'pagos.curso_id')
            ->leftJoin('ritmos', 'ritmos.id', '=', 'cursos.ritmo_id')
            ->leftJoin('planes', 'planes.id', '=', 'pagos.plan_id')
            ->select(
                'pagos.id',
                'pagos.alumno_id',
                DB::raw("CONCAT(alumnos.nombres,' ',alumnos.apellidos) as alumno_nombre"),
                'pagos.curso_id',
                'ritmos.nombre as curso_nombre',
                'pagos.plan_id',
                'planes.nombre as plan_nombre',
                'pagos.monto',
                'pagos.fecha_pago',
                'pagos.metodo_pago',
                'pagos.referencia',
                'pagos.observacion',
                'pagos.usuario_creacion_id',
                'pagos.usuario_creacion_nombre',
                'pagos.usuario_modificacion_id',
                'pagos.usuario_modificacion_nombre',
                'pagos.created_at as fecha_creacion',
                'pagos.updated_at as fecha_modificacion',
            );

        if (isset($dto['nombre'])) {
            $query->where(function ($q) use ($dto) {
                $q->where('alumnos.nombres', 'like', '%' . $dto['nombre'] . '%')
                    ->orWhere('alumnos.apellidos', 'like', '%' . $dto['nombre'] . '%');
            });
        }
        if (isset($dto['alumno_id'])) {
            $query->where('pagos.alumno_id', $dto['alumno_id']);
        }
        if (isset($dto['curso_id'])) {
            $query->where('pagos.curso_id', $dto['curso_id']);
        }
        if (isset($dto['fecha_desde'])) {
            $query->where('pagos.fecha_pago', '>=', $dto['fecha_desde']);
        }
        if (isset($dto['fecha_hasta'])) {
            $query->where('pagos.fecha_pago', '<=', $dto['fecha_hasta']);
        }

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0) {
            foreach ($dto['ordenar_por'] as $attribute => $value) {
                if ($attribute == 'alumno_nombre') {
                    $query->orderBy('alumnos.nombres', $value);
                }
                if ($attribute == 'curso_nombre') {
                    $query->orderBy('ritmos.nombre', $value);
                }
                if (in_array($attribute, ['monto', 'fecha_pago', 'metodo_pago', 'usuario_creacion_nombre'])) {
                    $query->orderBy('pagos.' . $attribute, $value);
                }
                if ($attribute == 'fecha_creacion') {
                    $query->orderBy('pagos.created_at', $value);
                }
                if ($attribute == 'fecha_modificacion') {
                    $query->orderBy('pagos.updated_at', $value);
                }
            }
        } else {
            $query->orderBy('pagos.fecha_pago', 'desc');
        }

        $pagos = $query->paginate($dto['limite'] ?? 100);

        return [
            'datos' => $pagos->items(),
            'desde' => $pagos->firstItem(),
            'hasta' => $pagos->lastItem(),
            'por_pagina' => $pagos->perPage(),
            'pagina_actual' => $pagos->currentPage(),
            'ultima_pagina' => $pagos->lastPage(),
            'total' => $pagos->total(),
        ];
    }

    public static function cargar($id)
    {
        $pago = Pago::find($id);

        return [
            'id' => $pago->id,
            'alumno_id' => $pago->alumno_id,
            'curso_id' => $pago->curso_id,
            'plan_id' => $pago->plan_id,
            'monto' => $pago->monto,
            'fecha_pago' => $pago->fecha_pago,
            'metodo_pago' => $pago->metodo_pago,
            'referencia' => $pago->referencia,
            'observacion' => $pago->observacion,
            'usuario_creacion_id' => $pago->usuario_creacion_id,
            'usuario_creacion_nombre' => $pago->usuario_creacion_nombre,
            'usuario_modificacion_id' => $pago->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $pago->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($pago->created_at))->format('Y-m-d H:i:s'),
            'fecha_modificacion' => (new Carbon($pago->updated_at))->format('Y-m-d H:i:s'),
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

        $pago = isset($dto['id']) ? Pago::find($dto['id']) : new Pago();
        $original = $pago->toJson();

        // Al modificar, primero revierte el efecto del monto anterior sobre el saldo.
        if (isset($dto['id'])) {
            Pago::ajustarSaldo($pago->curso_id, $pago->alumno_id, (float) $pago->monto);
        }

        $pago->fill($dto);
        if (!$pago->save()) {
            throw new Exception('Ocurrió un error al intentar guardar el pago.');
        }

        // Descuenta el nuevo monto del saldo de la matrícula y marca la fecha de pago.
        Pago::ajustarSaldo($pago->curso_id, $pago->alumno_id, -1 * (float) $pago->monto, $pago->fecha_pago);

        AuditoriaTabla::crear([
            'id_recurso' => $pago->id,
            'nombre_recurso' => Pago::class,
            'descripcion_recurso' => 'Pago #' . $pago->id,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $original : $pago->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $pago->toJson() : null,
        ]);

        return Pago::cargar($pago->id);
    }

    /**
     * Suma $delta al saldo de la matrícula del alumno en el curso.
     * $delta negativo = pago (baja el saldo); positivo = reverso.
     */
    private static function ajustarSaldo($cursoId, $alumnoId, $delta, $fechaPago = null)
    {
        if (!$cursoId || !$alumnoId) {
            return;
        }
        $matricula = DB::table('curso_alumno')
            ->where('curso_id', $cursoId)
            ->where('alumno_id', $alumnoId)
            ->first();
        if (!$matricula) {
            return;
        }
        $datos = ['saldo' => (float) $matricula->saldo + $delta, 'updated_at' => Carbon::now()];
        if ($fechaPago) {
            $datos['ultima_fecha_pago'] = $fechaPago;
        }
        DB::table('curso_alumno')
            ->where('id', $matricula->id)
            ->update($datos);
    }

    public static function eliminar($id)
    {
        $pago = Pago::find($id);

        // Revierte el saldo (devuelve el monto pagado).
        Pago::ajustarSaldo($pago->curso_id, $pago->alumno_id, (float) $pago->monto);

        AuditoriaTabla::crear([
            'id_recurso' => $pago->id,
            'nombre_recurso' => Pago::class,
            'descripcion_recurso' => 'Pago #' . $pago->id,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $pago->toJson(),
        ]);

        return $pago->delete();
    }
}
