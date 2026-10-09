<?php

namespace App\Services\Academia;

use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use App\Models\Academia\Curso;
use App\Models\Academia\Alumno;
use App\Models\Academia\Pago;

/**
 * Matrícula rápida: en un solo paso crea el alumno (o usa uno existente), lo matricula en uno o
 * varios cursos y registra el pago.
 *
 * Los cursos se matriculan en el orden recibido, que es el que usa Tarifas para el precio del
 * 2.º, 3.er… curso. El pago se reparte entre los cursos en ese mismo orden (un pago por curso,
 * como en el módulo de Pagos) y el alumno recibe un único correo con el resumen.
 *
 * Los errores de negocio se lanzan como DomainException (el controlador responde 409).
 */
class Matriculas
{
    private const CAMPOS_ALUMNO = [
        'sede_id', 'nombres', 'apellidos', 'documento', 'telefono', 'correo', 'fecha_nacimiento',
        'direccion', 'contacto_emergencia', 'telefono_emergencia',
    ];

    /**
     * Precio del primer ciclo de cada curso, sin guardar nada. $alumnoId null = alumno nuevo.
     * $parejas: curso_id => alumno con quien pagará ese curso en pareja (los demás, individual).
     *
     * @return array{cursos: array<int, array>, total: float}
     */
    public static function cotizar(?int $alumnoId, array $cursoIds, array $parejas = []): array
    {
        $cursos = DB::table('cursos as c')
            ->leftJoin('ritmos as r', 'r.id', '=', 'c.ritmo_id')
            ->leftJoin('planes as p', 'p.id', '=', 'c.plan_id')
            ->leftJoin('sedes as s', 's.id', '=', 'c.sede_id')
            ->whereIn('c.id', $cursoIds)
            ->select(
                'c.id', 'c.cupo_max', 'c.activo', 'c.estado', 's.nombre as sede',
                DB::raw("CONCAT(COALESCE(c.nombre, r.nombre), ' - ', DATE_FORMAT(c.hora, '%H:%i')) as curso"),
                'p.nombre as plan', 'p.valor', 'p.periodicidad',
                DB::raw('(SELECT COUNT(*) FROM curso_alumno WHERE curso_alumno.curso_id = c.id AND curso_alumno.estado = 1) as matriculados'),
            )
            ->get()
            ->keyBy('id');

        $yaMatriculado = $alumnoId
            ? DB::table('curso_alumno')->where('alumno_id', $alumnoId)->whereIn('curso_id', $cursoIds)->pluck('curso_id')->all()
            : [];
        // Igual que Curso::matricular: con paquete vigente descuenta clases y no causa ciclos.
        $porPaquete = $alumnoId && Paquetes::vigente($alumnoId, Carbon::today());
        // Cursos que el alumno ya paga por ciclo: los nuevos siguen después de esos.
        $porCiclo = $alumnoId ? count(Tarifas::matriculasPorCiclo($alumnoId)) : 0;

        $filas = [];
        foreach ($cursoIds as $id) {
            $c = $cursos[$id] ?? null;
            if (!$c) {
                continue;
            }
            $fila = [
                'curso_id' => $c->id,
                'curso' => $c->curso,
                'sede' => $c->sede,
                'plan' => $c->plan,
                'en_pareja' => !empty($parejas[$c->id]),
                'modalidad' => $porPaquete ? 'paquete' : 'ciclo',
                'ya_matriculado' => in_array($c->id, $yaMatriculado),
                'cupo_disponible' => $c->cupo_max === null ? null : max(0, (int) $c->cupo_max - (int) $c->matriculados),
            ];
            if ($fila['ya_matriculado']) {
                $precio = ['valor' => 0.0, 'base' => (float) $c->valor, 'regla' => 'Ya está matriculado'];
            } elseif ($porPaquete) {
                $precio = ['valor' => 0.0, 'base' => (float) $c->valor, 'regla' => 'Paga con su paquete de clases'];
            } else {
                $precio = Tarifas::calcular((float) $c->valor, $porCiclo + 1, !empty($parejas[$c->id]));
                // Solo estos cursos ocupan lugar en el orden (mismo criterio de Tarifas::matriculasPorCiclo).
                if ($c->estado && $c->activo && $c->periodicidad === 'mensual' && (float) $c->valor > 0) {
                    $porCiclo++;
                }
            }
            $filas[] = $fila + Arr::only($precio, ['valor', 'base', 'regla']);
        }

        return ['cursos' => $filas, 'total' => round(array_sum(array_column($filas, 'valor')), 2)];
    }

    /**
     * Crea el alumno (si no viene alumno_id), lo matricula en los cursos y registra el pago.
     * Debe ejecutarse dentro de una transacción: si algo falla no queda nada a medias.
     *
     * @param array $dto  alumno_id | alumno{...}, cursos[ids], parejas{curso_id: alumno_id}, ajustar_pareja,
     *                    pago{monto, fecha_pago, metodo_pago, sede_id, referencia, observacion}|null
     */
    public static function registrar(array $dto): array
    {
        $esNuevo = empty($dto['alumno_id']);
        if ($esNuevo) {
            $datosAlumno = Arr::only($dto['alumno'] ?? [], self::CAMPOS_ALUMNO) + ['estado' => 1];
            self::validarDocumento($datosAlumno['documento'] ?? null);
            $alumnoId = (int) Alumno::modificarOCrear($datosAlumno, false)['id'];
        } else {
            $alumnoId = (int) $dto['alumno_id'];
        }

        $cursoIds = array_map('intval', $dto['cursos']);
        $cursos = Curso::whereIn('id', $cursoIds)->get()->keyBy('id');
        $matriculas = []; // curso_alumno.id => curso
        foreach ($cursoIds as $cursoId) {
            $curso = $cursos[$cursoId];
            if (!$curso->estado) {
                throw new DomainException('El curso ' . ($curso->nombre ?? "#{$curso->id}") . ' está inactivo: no admite matrículas.');
            }
            if (DB::table('curso_alumno')->where('curso_id', $curso->id)->where('alumno_id', $alumnoId)->exists()) {
                throw new DomainException('El alumno ya está matriculado en uno de los cursos elegidos. Quítalo de la lista.');
            }
            $parejaId = !empty($dto['parejas'][$cursoId]) ? (int) $dto['parejas'][$cursoId] : null;
            $matriculas[Curso::matricular($curso, $alumnoId, null, false, $parejaId, !empty($dto['ajustar_pareja']))] = $curso;
        }

        // Valor del primer ciclo de cada matrícula (su saldo recién creada).
        $valores = DB::table('curso_alumno')->whereIn('id', array_keys($matriculas))->pluck('saldo', 'id')
            ->map(fn ($saldo) => (float) $saldo)->all();
        $total = round(array_sum($valores), 2);

        $pago = $dto['pago'] ?? null;
        $pagado = $pago ? self::pagar($alumnoId, $matriculas, $valores, $pago, $total) : 0.0;

        NotificadorAcademia::resumenMatricula($alumnoId, $valores, $pagado, $pago, $esNuevo);

        $alumno = DB::table('alumnos')->where('id', $alumnoId)->first();
        return [
            'alumno' => ['id' => $alumnoId, 'nombre' => trim("{$alumno->nombres} {$alumno->apellidos}"), 'nuevo' => $esNuevo],
            'cursos' => count($matriculas),
            'total' => $total,
            'pagado' => $pagado,
            'saldo' => round($total - $pagado, 2),
        ];
    }

    /** Dos alumnos no comparten documento: si ya existe, se debe matricular al alumno existente. */
    private static function validarDocumento(?string $documento): void
    {
        $documento = trim((string) $documento);
        if ($documento === '') {
            return;
        }
        $existente = DB::table('alumnos')->where('documento', $documento)->first();
        if ($existente) {
            throw new DomainException(
                "Ya existe un alumno con el documento {$documento}: {$existente->nombres} {$existente->apellidos}. "
                . 'Elígelo como alumno existente.'
            );
        }
    }

    /**
     * Reparte el monto entre las matrículas en orden, sin pasar de lo que debe cada una.
     * Devuelve lo abonado.
     */
    private static function pagar(int $alumnoId, array $matriculas, array $valores, array $pago, float $total): float
    {
        $monto = round((float) $pago['monto'], 2);
        if ($monto > $total) {
            throw new DomainException(
                'El monto del pago ($' . number_format($monto, 0, ',', '.') . ') supera el total a pagar ($'
                . number_format($total, 0, ',', '.') . ').'
            );
        }

        $restante = $monto;
        foreach ($matriculas as $cursoAlumnoId => $curso) {
            $abono = min($restante, $valores[$cursoAlumnoId]);
            if ($abono <= 0) {
                continue;
            }
            Pago::modificarOCrear([
                'alumno_id' => $alumnoId,
                'curso_id' => $curso->id,
                'plan_id' => $curso->plan_id,
                'sede_id' => $pago['sede_id'] ?? null,
                'monto' => $abono,
                'fecha_pago' => $pago['fecha_pago'],
                'metodo_pago' => $pago['metodo_pago'],
                'referencia' => $pago['referencia'] ?? null,
                'observacion' => $pago['observacion'] ?? null,
            ], false);
            $restante = round($restante - $abono, 2);
        }
        return round($monto - $restante, 2);
    }
}
