<?php

namespace App\Models\Academia;

use Exception;
use Carbon\Carbon;
use App\Enum\AccionAuditoriaEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\AuditoriaTabla;
use App\Services\Academia\CalendarioCurso;
use App\Services\Academia\NotificadorAcademia;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Alumno extends Model
{
    use HasFactory;

    protected $table = 'alumnos';

    protected $fillable = [
        'usuario_id',
        'sede_id',
        'nombres',
        'apellidos',
        'documento',
        'telefono',
        'correo',
        'fecha_nacimiento',
        'direccion',
        'contacto_emergencia',
        'telefono_emergencia',
        'estado',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    public static function obtenerColeccionLigera($dto)
    {
        return DB::table('alumnos')
            ->select(
                'id',
                DB::raw("CONCAT(nombres,' ',apellidos) as nombre"),
                // Ya toma un curso grupal: le aplica el precio de alumno en los paquetes.
                DB::raw('EXISTS (SELECT 1 FROM curso_alumno ca JOIN cursos c ON c.id = ca.curso_id WHERE ca.alumno_id = alumnos.id AND ca.estado = 1 AND c.estado = 1 AND c.activo = 1) as con_curso'),
            )
            ->where('estado', 1)
            ->orderBy('nombres', 'asc')
            ->get();
    }

    public static function obtenerColeccion($dto)
    {
        $query = DB::table('alumnos')
            ->select(
                'id',
                'usuario_id',
                'sede_id',
                DB::raw('(SELECT nombre FROM sedes WHERE sedes.id = alumnos.sede_id) as sede_nombre'),
                // Lo que debe hoy: saldos de sus cursos y de sus paquetes de clases.
                DB::raw("(SELECT COALESCE(SUM(curso_alumno.saldo), 0) FROM curso_alumno WHERE curso_alumno.alumno_id = alumnos.id AND curso_alumno.estado = 1 AND curso_alumno.saldo > 0) + (SELECT COALESCE(SUM(paquetes_alumno.saldo), 0) FROM paquetes_alumno WHERE paquetes_alumno.alumno_id = alumnos.id AND paquetes_alumno.estado = 'activo' AND paquetes_alumno.saldo > 0) as saldo_pendiente"),
                'nombres',
                'apellidos',
                'documento',
                'telefono',
                'correo',
                'fecha_nacimiento',
                'direccion',
                'contacto_emergencia',
                'telefono_emergencia',
                'estado',
                'usuario_creacion_id',
                'usuario_creacion_nombre',
                'usuario_modificacion_id',
                'usuario_modificacion_nombre',
                'created_at as fecha_creacion',
                'updated_at as fecha_modificacion',
            );

        // Con una sede elegida: los alumnos de esa sede o matriculados en alguno de sus cursos.
        if ($sedeId = Sede::actual()) {
            $query->where(function ($q) use ($sedeId) {
                $q->where('sede_id', $sedeId)
                    ->orWhereExists(fn ($sub) => $sub->from('curso_alumno')
                        ->join('cursos', 'cursos.id', '=', 'curso_alumno.curso_id')
                        ->whereColumn('curso_alumno.alumno_id', 'alumnos.id')
                        ->where('curso_alumno.estado', 1)
                        ->where('cursos.sede_id', $sedeId));
            });
        }

        if (isset($dto['nombre'])) {
            $query->where(function ($q) use ($dto) {
                $q->where('nombres', 'like', "%{$dto['nombre']}%")
                    ->orWhere('apellidos', 'like', "%{$dto['nombre']}%");
            });
        }

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0) {
            foreach ($dto['ordenar_por'] as $attribute => $value) {
                if (in_array($attribute, ['nombres', 'apellidos', 'documento', 'correo', 'estado', 'usuario_creacion_nombre'])) {
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

        $alumnos = $query->paginate($dto['limite'] ?? 100);

        return [
            'datos' => $alumnos->items(),
            'desde' => $alumnos->firstItem(),
            'hasta' => $alumnos->lastItem(),
            'por_pagina' => $alumnos->perPage(),
            'pagina_actual' => $alumnos->currentPage(),
            'ultima_pagina' => $alumnos->lastPage(),
            'total' => $alumnos->total(),
        ];
    }

    public static function cargar($id)
    {
        $alumno = Alumno::find($id);

        return [
            'id' => $alumno->id,
            'usuario_id' => $alumno->usuario_id,
            'sede_id' => $alumno->sede_id,
            'nombres' => $alumno->nombres,
            'apellidos' => $alumno->apellidos,
            'documento' => $alumno->documento,
            'telefono' => $alumno->telefono,
            'correo' => $alumno->correo,
            'fecha_nacimiento' => $alumno->fecha_nacimiento,
            'direccion' => $alumno->direccion,
            'contacto_emergencia' => $alumno->contacto_emergencia,
            'telefono_emergencia' => $alumno->telefono_emergencia,
            'estado' => $alumno->estado,
            'usuario_creacion_id' => $alumno->usuario_creacion_id,
            'usuario_creacion_nombre' => $alumno->usuario_creacion_nombre,
            'usuario_modificacion_id' => $alumno->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $alumno->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($alumno->created_at))->format('Y-m-d H:i:s'),
            'fecha_modificacion' => (new Carbon($alumno->updated_at))->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Estado de cuenta del alumno: lo que debe (cursos y paquetes), lo que ha pagado,
     * los ciclos que se le han cobrado y su historial de pagos.
     */
    public static function estadoCuenta($id): array
    {
        $alumno = DB::table('alumnos')->where('id', $id)->first();

        $cursos = DB::table('curso_alumno as ca')
            ->join('cursos as c', 'c.id', '=', 'ca.curso_id')
            ->leftJoin('ritmos as r', 'r.id', '=', 'c.ritmo_id')
            ->leftJoin('planes as p', 'p.id', '=', 'c.plan_id')
            ->leftJoin('sedes as s', 's.id', '=', 'c.sede_id')
            ->where('ca.alumno_id', $id)
            ->where('ca.estado', 1)
            ->orderBy('ca.fecha_matricula')
            ->select(
                'ca.id as curso_alumno_id', 'ca.curso_id', 'ca.modalidad', 'ca.saldo', 'ca.fecha_matricula', 'ca.ultima_fecha_pago',
                DB::raw("CONCAT(COALESCE(c.nombre, r.nombre), ' - ', DATE_FORMAT(c.hora, '%H:%i')) as curso"),
                'p.nombre as plan', 's.nombre as sede',
                // Para el avance del ciclo (CalendarioCurso).
                'ca.ciclo_inicio', 'c.dia', 'c.fecha_inicio', 'c.sede_id', 'c.estado as curso_estado', 'c.activo as curso_activo',
                'p.num_clases', 'p.periodicidad',
                DB::raw('(SELECT COALESCE(SUM(pg.monto), 0) FROM pagos pg WHERE pg.alumno_id = ca.alumno_id AND pg.curso_id = ca.curso_id AND pg.paquete_id IS NULL) as pagado'),
            )
            ->get();
        $calendario = new CalendarioCurso();
        $cursos = $cursos->map(function ($c) use ($calendario) {
            $fila = [
                'curso_alumno_id' => $c->curso_alumno_id, 'curso_id' => $c->curso_id, 'modalidad' => $c->modalidad,
                'fecha_matricula' => $c->fecha_matricula, 'ultima_fecha_pago' => $c->ultima_fecha_pago,
                'curso' => $c->curso, 'plan' => $c->plan, 'sede' => $c->sede,
                // Cobrado hasta hoy = lo pagado más lo que aún debe.
                'saldo' => (float) $c->saldo, 'pagado' => (float) $c->pagado, 'cobrado' => (float) $c->pagado + (float) $c->saldo,
                // Curso desactivado: ya no se le cobran más ciclos.
                'curso_activo' => (bool) ($c->curso_estado && $c->curso_activo),
            ];
            // Clases del ciclo vigente que ya se dictaron (solo si paga por ciclos).
            if ($c->modalidad === 'ciclo' && $c->periodicidad === 'mensual') {
                $ciclo = $calendario->ciclo($c, $c, CalendarioCurso::clasesPorCiclo($c->num_clases));
                $fila += CalendarioCurso::avance($ciclo, Carbon::today()) + ['proximo_pago' => $ciclo['proximo_pago']->toDateString()];
            }
            return $fila;
        });

        $paquetes = DB::table('paquetes_alumno as pa')
            ->leftJoin('planes as p', 'p.id', '=', 'pa.plan_id')
            ->where('pa.alumno_id', $id)
            ->where('pa.estado', 'activo')
            ->orderBy('pa.fecha_compra', 'desc')
            ->select('pa.id', 'pa.fecha_compra', 'pa.fecha_vencimiento', 'pa.clases_total', 'pa.valor', 'pa.saldo',
                DB::raw("COALESCE(p.nombre, 'Paquete') as plan"))
            ->get()
            ->map(fn ($p) => array_merge((array) $p, [
                'valor' => (float) $p->valor, 'saldo' => (float) $p->saldo, 'pagado' => (float) $p->valor - (float) $p->saldo,
            ]));

        $cargos = DB::table('cargos_mensuales as cm')
            ->join('curso_alumno as ca', 'ca.id', '=', 'cm.curso_alumno_id')
            ->join('cursos as c', 'c.id', '=', 'ca.curso_id')
            ->leftJoin('ritmos as r', 'r.id', '=', 'c.ritmo_id')
            ->where('ca.alumno_id', $id)
            ->orderBy('cm.periodo', 'desc')
            ->limit(100)
            ->select('cm.id', 'cm.periodo', 'cm.valor', 'cm.regla', DB::raw('COALESCE(c.nombre, r.nombre) as curso'))
            ->get();

        $pagos = DB::table('pagos as pg')
            ->leftJoin('cursos as c', 'c.id', '=', 'pg.curso_id')
            ->leftJoin('ritmos as r', 'r.id', '=', 'c.ritmo_id')
            ->leftJoin('paquetes_alumno as pa', 'pa.id', '=', 'pg.paquete_id')
            ->leftJoin('planes as pp', 'pp.id', '=', 'pa.plan_id')
            ->leftJoin('sedes as s', 's.id', '=', 'pg.sede_id')
            ->where('pg.alumno_id', $id)
            ->orderBy('pg.fecha_pago', 'desc')
            ->orderBy('pg.id', 'desc')
            ->select('pg.id', 'pg.fecha_pago', 'pg.monto', 'pg.metodo_pago', 'pg.referencia', 's.nombre as sede',
                DB::raw("CASE WHEN pg.paquete_id IS NOT NULL THEN CONCAT('Paquete ', COALESCE(pp.nombre, CONCAT('#', pg.paquete_id))) "
                    . "ELSE COALESCE(c.nombre, r.nombre, 'Pago') END as concepto"))
            ->get();

        $debeCursos = (float) $cursos->where('saldo', '>', 0)->sum('saldo');
        $debePaquetes = (float) $paquetes->where('saldo', '>', 0)->sum('saldo');

        return [
            'alumno' => [
                'id' => $alumno->id,
                'nombre' => trim("{$alumno->nombres} {$alumno->apellidos}"),
                'documento' => $alumno->documento,
                'telefono' => $alumno->telefono,
                'correo' => $alumno->correo,
                'estado' => $alumno->estado,
            ],
            'resumen' => [
                'saldo_pendiente' => $debeCursos + $debePaquetes,
                'saldo_cursos' => $debeCursos,
                'saldo_paquetes' => $debePaquetes,
                // Pagó de más en algún curso (saldo negativo).
                'saldo_a_favor' => (float) abs($cursos->where('saldo', '<', 0)->sum('saldo')),
                'total_pagado' => (float) $pagos->sum('monto'),
                'ultimo_pago' => $pagos->first()?->fecha_pago,
            ],
            'cursos' => $cursos->values(),
            'paquetes' => $paquetes->values(),
            'cargos' => $cargos,
            'pagos' => $pagos,
        ];
    }

    /** $notificar = false cuando quien llama envía su propio correo (matrícula rápida). */
    public static function modificarOCrear($dto, bool $notificar = true)
    {
        $user = Auth::user();
        $usuario = $user->usuario();

        if (!isset($dto['id'])) {
            $dto['usuario_creacion_id'] = $usuario->id ?? null;
            $dto['usuario_creacion_nombre'] = $usuario->nombre ?? null;
        }
        $dto['usuario_modificacion_id'] = $usuario->id ?? null;
        $dto['usuario_modificacion_nombre'] = $usuario->nombre ?? null;

        $alumno = isset($dto['id']) ? Alumno::find($dto['id']) : new Alumno();
        $original = $alumno->toJson();

        $alumno->fill($dto);
        if (!$alumno->save()) {
            throw new Exception('Ocurrió un error al intentar guardar el alumno.');
        }

        // Bienvenida a la academia solo al crear el alumno.
        if (!isset($dto['id']) && $notificar) {
            NotificadorAcademia::bienvenidaAlumno($alumno->id);
        }

        AuditoriaTabla::crear([
            'id_recurso' => $alumno->id,
            'nombre_recurso' => Alumno::class,
            'descripcion_recurso' => "$alumno->nombres $alumno->apellidos",
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $original : $alumno->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $alumno->toJson() : null,
        ]);

        return Alumno::cargar($alumno->id);
    }

    public static function eliminar($id)
    {
        $alumno = Alumno::find($id);

        AuditoriaTabla::crear([
            'id_recurso' => $alumno->id,
            'nombre_recurso' => Alumno::class,
            'descripcion_recurso' => "$alumno->nombres $alumno->apellidos",
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $alumno->toJson(),
        ]);

        return $alumno->delete();
    }
}
