<?php

namespace App\Services\Academia;

use App\Support\Configuracion\Configuracion;
use Exception;
use Carbon\Carbon;
use App\Models\Central\Academia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\Academia\NotificacionAlumnoMail as Correo;

/**
 * Cobro por ciclos de clases y correos a los alumnos de la academia activa.
 *
 * Cada matrícula con plan mensual paga ciclos de N clases (num_clases del plan, 4 por defecto)
 * según el calendario de su curso: una clase semanal que se salta festivos y cierres
 * (CalendarioCurso). Si una clase cae en festivo, el ciclo termina una semana después.
 *  - La matrícula cobra el primer ciclo; el día de la 1.ª clase del ciclo siguiente se causa su valor.
 *  - Recordatorio: faltando N días (config) para la 1.ª clase del próximo ciclo.
 *  - Mora: saldo > 0 con el ciclo ya iniciado; se repite cada N días (config) mientras siga.
 */
class NotificadorAcademia
{
    private const DIAS_SEMANA = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    private const MAX_CICLOS_POR_CORRIDA = 24;

    private CalendarioCurso $calendario;

    public function __construct(private Academia $academia, private Carbon $hoy, private bool $simular = false)
    {
        $this->hoy = $hoy->copy()->startOfDay();
        $this->calendario = new CalendarioCurso();
    }

    public function ejecutar(): array
    {
        $resumen = ['cargos' => 0, 'recordatorio' => 0, 'mora' => 0, 'errores' => 0];
        $diasAviso = Configuracion::entero('DIAS_RECORDATORIO_PAGO', config('academias.notificaciones.dias_recordatorio'));
        $cadaDias = Configuracion::entero('DIAS_ENTRE_AVISOS_MORA', config('academias.notificaciones.dias_entre_avisos_mora'));
        $desdeMora = $this->hoy->copy()->subDays($cadaDias - 1)->toDateString();

        foreach ($this->matriculas()->get() as $m) {
            $porCiclos = $m->periodicidad === 'mensual' && (float) $m->valor_plan > 0;
            $ciclo = $porCiclos ? $this->avanzarCiclo($m, $resumen) : null;

            if (!$m->correo) {
                continue;
            }

            // Recordatorio del próximo ciclo.
            if ($ciclo) {
                $proximo = $ciclo['proximo_pago'];
                $faltan = (int) round($this->hoy->diffInDays($proximo, false));
                if ($faltan > 0 && $faltan <= $diasAviso
                    && !$this->yaEnviado(Correo::RECORDATORIO, $m->id, fn ($q) => $q->where('periodo', $proximo->toDateString()))) {
                    $this->enviar(Correo::RECORDATORIO, $m, $proximo, [
                        'valor' => Tarifas::precio($m->id)['valor'],
                        'saldo' => (float) $m->saldo,
                        'fecha_vencimiento' => $proximo->format('d/m/Y'),
                        'clases_por_ciclo' => $ciclo['clases_por_ciclo'],
                    ], $resumen);
                }
            }

            // Mora: saldo pendiente con el ciclo (o la primera clase) ya iniciado.
            $vence = $ciclo ? $ciclo['inicio'] : $this->calendario->primeraClase($m, Carbon::parse($m->fecha_matricula ?? $this->hoy));
            if ((float) $m->saldo > 0 && $this->hoy->gt($vence)
                && !$this->yaEnviado(Correo::MORA, $m->id, fn ($q) => $q->where('periodo', '>=', $desdeMora))) {
                $this->enviar(Correo::MORA, $m, $this->hoy, [
                    'saldo' => (float) $m->saldo,
                    'fecha_vencimiento' => $vence->format('d/m/Y'),
                ], $resumen);
            }
        }

        $this->avisosPaquetes($resumen);
        $this->recordatoriosClasePrivada($resumen);
        $this->cumpleanos($resumen);

        return $resumen;
    }

    /** Un aviso por alumno y fecha (periodo = fecha del evento) para no repetir. */
    private function avisoAlumnoEnviado(string $tipo, int $alumnoId, string $fecha): bool
    {
        return DB::table('notificaciones_enviadas')->where('tipo', $tipo)->where('alumno_id', $alumnoId)
            ->where('periodo', $fecha)->where('estado', 'enviado')->exists();
    }

    /** El día anterior a cada clase personalizada programada, a cada uno de sus alumnos. */
    private function recordatoriosClasePrivada(array &$resumen): void
    {
        $manana = $this->hoy->copy()->addDay();
        $clases = DB::table('clases_privadas as c')
            ->join('clase_privada_alumno as cpa', 'cpa.clase_privada_id', '=', 'c.id')
            ->join('alumnos as a', 'a.id', '=', 'cpa.alumno_id')
            ->leftJoin('profesores as p', 'p.id', '=', 'c.profesor_id')
            ->where('c.fecha', $manana->toDateString())
            ->where('c.estado', 'programada')
            ->where('cpa.resultado', 'pendiente')
            ->whereNotNull('a.correo')->where('a.correo', '<>', '')
            ->select('c.id as clase_id', 'c.hora', 'c.duracion_min', 'a.id as alumno_id', 'a.correo',
                DB::raw("CONCAT(a.nombres, ' ', a.apellidos) as alumno"), DB::raw("CONCAT(p.nombres, ' ', p.apellidos) as profesor"))
            ->get();
        $horas = Configuracion::entero('HORAS_CANCELACION_CLASE', 24);

        foreach ($clases as $c) {
            if ($this->avisoAlumnoEnviado(Correo::CLASE_PRIVADA, $c->alumno_id, $manana->toDateString())) {
                continue;
            }
            $m = (object) ['id' => null, 'alumno_id' => $c->alumno_id, 'curso_id' => null, 'correo' => $c->correo,
                'alumno' => $c->alumno, 'curso' => 'Clase personalizada'];
            $this->enviar(Correo::CLASE_PRIVADA, $m, $manana, [
                'fecha' => $manana->format('d/m/Y'),
                'hora' => substr((string) $c->hora, 0, 5),
                'duracion' => $c->duracion_min,
                'profesor' => $c->profesor ?? 'por confirmar',
                'horas_cancelacion' => $horas,
            ], $resumen);
        }
    }

    /** Saludo de cumpleaños (los nacidos un 29 de febrero se saludan el 28 en años no bisiestos). */
    private function cumpleanos(array &$resumen): void
    {
        $febrero28NoBisiesto = $this->hoy->month === 2 && $this->hoy->day === 28 && !$this->hoy->isLeapYear();
        $alumnos = DB::table('alumnos')
            ->where('estado', 1)
            ->whereNotNull('correo')->where('correo', '<>', '')
            ->whereNotNull('fecha_nacimiento')
            ->whereMonth('fecha_nacimiento', $this->hoy->month)
            ->where(fn ($q) => $q->whereDay('fecha_nacimiento', $this->hoy->day)
                ->when($febrero28NoBisiesto, fn ($qq) => $qq->orWhereDay('fecha_nacimiento', 29)))
            ->get();

        foreach ($alumnos as $a) {
            if ($this->avisoAlumnoEnviado(Correo::CUMPLEANOS, $a->id, $this->hoy->toDateString())) {
                continue;
            }
            $m = (object) ['id' => null, 'alumno_id' => $a->id, 'curso_id' => null, 'correo' => $a->correo,
                'alumno' => trim("{$a->nombres} {$a->apellidos}"), 'curso' => ''];
            $this->enviar(Correo::CUMPLEANOS, $m, $this->hoy, [], $resumen);
        }
    }

    /**
     * Paquetes: aviso cuando faltan N días para vencer y aún tiene clases, y cuando le queda 1 clase.
     * Un aviso de cada tipo por paquete (periodo = id del paquete codificado en la fecha de compra).
     */
    private function avisosPaquetes(array &$resumen): void
    {
        $resumen['paquetes'] = 0;
        $diasAviso = Configuracion::entero('DIAS_AVISO_VENCE_PAQUETE', config('academias.notificaciones.dias_recordatorio'));
        $paquetes = DB::table('paquetes_alumno as pa')
            ->join('alumnos as a', 'a.id', '=', 'pa.alumno_id')
            ->leftJoin('planes as p', 'p.id', '=', 'pa.plan_id')
            ->where('pa.estado', Paquetes::ACTIVO)
            ->where('a.estado', 1)
            ->whereNotNull('a.correo')
            ->where('a.correo', '<>', '')
            ->where(fn ($q) => $q->whereNull('pa.fecha_vencimiento')->orWhere('pa.fecha_vencimiento', '>=', $this->hoy->toDateString()))
            ->select('pa.*', 'a.correo', DB::raw("CONCAT(a.nombres, ' ', a.apellidos) as alumno"), 'p.nombre as plan')
            ->get();
        $usadas = Paquetes::usadas($paquetes->pluck('id')->all());

        foreach ($paquetes as $p) {
            $restantes = $p->clases_total - ($usadas[$p->id] ?? 0);
            if ($restantes <= 0) {
                continue;
            }
            $vence = $p->fecha_vencimiento ? Carbon::parse($p->fecha_vencimiento) : null;
            $faltan = $vence ? (int) round($this->hoy->diffInDays($vence, false)) : null;
            $datos = [
                'curso' => $p->plan ?? 'tu paquete de clases',
                'restantes' => $restantes,
                'clases_total' => (int) $p->clases_total,
                'fecha_vencimiento' => $vence?->format('d/m/Y'),
            ];
            $m = (object) ['id' => null, 'alumno_id' => $p->alumno_id, 'curso_id' => null, 'correo' => $p->correo,
                'alumno' => $p->alumno, 'curso' => $datos['curso']];

            if ($faltan !== null && $faltan >= 0 && $faltan <= $diasAviso && !$this->avisoPaqueteEnviado(Correo::PAQUETE_VENCE, $p)) {
                $this->enviar(Correo::PAQUETE_VENCE, $m, Carbon::parse($p->fecha_compra), $datos, $resumen);
            } elseif ($restantes === 1 && !$this->avisoPaqueteEnviado(Correo::PAQUETE_ULTIMA, $p)) {
                $this->enviar(Correo::PAQUETE_ULTIMA, $m, Carbon::parse($p->fecha_compra), $datos, $resumen);
            }
        }
    }

    private function avisoPaqueteEnviado(string $tipo, object $paquete): bool
    {
        return DB::table('notificaciones_enviadas')
            ->where('tipo', $tipo)
            ->where('alumno_id', $paquete->alumno_id)
            ->where('periodo', $paquete->fecha_compra)
            ->where('estado', 'enviado')
            ->exists();
    }

    /** Matrículas activas con su curso (día, inicio), alumno y plan. */
    private function matriculas()
    {
        return DB::table('curso_alumno as ca')
            ->join('cursos as c', 'c.id', '=', 'ca.curso_id')
            ->join('alumnos as a', 'a.id', '=', 'ca.alumno_id')
            ->leftJoin('planes as p', 'p.id', '=', 'c.plan_id')
            ->where('ca.estado', 1)
            ->where('ca.modalidad', 'ciclo') // las matrículas por paquete descuentan clases, no causan ciclos
            ->where('c.estado', 1)
            ->where('c.activo', 1)
            ->where('a.estado', 1)
            ->select(
                'ca.id', 'ca.curso_id', 'ca.alumno_id', 'ca.saldo', 'ca.fecha_matricula', 'ca.ciclo_inicio',
                'c.nombre as curso', 'c.sede_id', 'c.dia', 'c.fecha_inicio', 'a.correo',
                DB::raw("CONCAT(a.nombres, ' ', a.apellidos) as alumno"),
                'p.valor as valor_plan', 'p.periodicidad', 'p.num_clases'
            );
    }

    /**
     * Causa los ciclos cuyo inicio ya llegó (idempotente por cargos_mensuales.periodo = 1.ª clase
     * del ciclo) y deja la matrícula en su ciclo vigente. Devuelve ese ciclo.
     */
    private function avanzarCiclo(object $m, array &$resumen): array
    {
        $n = CalendarioCurso::clasesPorCiclo($m->num_clases);
        $ciclo = $this->calendario->ciclo($m, $m, $n);

        if (!$m->ciclo_inicio && !$this->simular) {
            DB::table('curso_alumno')->where('id', $m->id)->update(['ciclo_inicio' => $ciclo['inicio']->toDateString()]);
        }

        for ($i = 0; $this->hoy->gte($ciclo['proximo_pago']) && $i < self::MAX_CICLOS_POR_CORRIDA; $i++) {
            $periodo = $ciclo['proximo_pago']->toDateString();
            // Precio del ciclo según su posición entre los cursos del alumno y si el curso es en pareja.
            $precio = Tarifas::precio($m->id);
            $resumen['cargos']++;
            if (!$this->simular) {
                DB::transaction(function () use ($m, $periodo, $precio) {
                    $insertado = DB::table('cargos_mensuales')->insertOrIgnore([
                        'curso_alumno_id' => $m->id,
                        'periodo' => $periodo,
                        'valor' => $precio['valor'],
                        'valor_base' => $precio['base'],
                        'regla' => $precio['regla'],
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                    ]);
                    $datos = ['ciclo_inicio' => $periodo, 'updated_at' => Carbon::now()];
                    if ($insertado) {
                        $datos['saldo'] = DB::raw('saldo + ' . (float) $precio['valor']);
                    }
                    DB::table('curso_alumno')->where('id', $m->id)->update($datos);
                });
            }
            $m->saldo = (float) $m->saldo + (float) $precio['valor'];
            $m->ciclo_inicio = $periodo;
            $ciclo = $this->calendario->ciclo($m, $m, $n);
        }
        return $ciclo;
    }

    private function yaEnviado(string $tipo, int $cursoAlumnoId, callable $filtro): bool
    {
        $query = DB::table('notificaciones_enviadas')
            ->where('tipo', $tipo)
            ->where('curso_alumno_id', $cursoAlumnoId)
            ->where('estado', 'enviado');
        $filtro($query);
        return $query->exists();
    }

    private function enviar(string $tipo, object $m, Carbon $periodo, array $datos, array &$resumen): void
    {
        // Los avisos de paquete se cuentan juntos en 'paquetes'.
        $clave = str_starts_with($tipo, 'paquete') ? 'paquetes'
            : (in_array($tipo, [Correo::CLASE_PRIVADA, Correo::CUMPLEANOS], true) ? 'otros' : $tipo);
        $resumen[$clave] = ($resumen[$clave] ?? 0) + 1;
        if ($this->simular) {
            return;
        }

        $estado = 'enviado';
        $error = null;
        try {
            Mail::to($m->correo)->send(new Correo(
                $tipo,
                self::datosAcademia($this->academia),
                array_merge(['alumno' => $m->alumno, 'curso' => $m->curso], $datos)
            ));
        } catch (Exception $e) {
            $estado = 'error';
            $error = $e->getMessage();
            $resumen['errores']++;
            Log::error("Correo {$tipo} a {$m->correo} ({$this->academia->codigo}): {$error}");
        }

        DB::table('notificaciones_enviadas')->insert([
            'tipo' => $tipo,
            'curso_alumno_id' => $m->id,
            'alumno_id' => $m->alumno_id,
            'curso_id' => $m->curso_id,
            'periodo' => $periodo->toDateString(),
            'correo' => $m->correo,
            'estado' => $estado,
            'error' => $error,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    public static function datosAcademia(Academia $academia): array
    {
        return [
            'nombre' => $academia->nombre,
            'correo' => $academia->correo,
            'telefono' => $academia->telefono,
            'url' => $academia->url(),
        ];
    }

    /**
     * Encola la bienvenida a la academia de un alumno recién creado (si tiene correo). Es distinta
     * de la bienvenida al curso, que sale al matricularlo. Un fallo al encolar no impide crearlo.
     */
    public static function bienvenidaAlumno(int $alumnoId): void
    {
        $academia = \App\Support\Academias\GestorAcademias::actual();
        $alumno = DB::table('alumnos')->where('id', $alumnoId)->first();
        if (!$academia || !$alumno || !$alumno->correo || !$alumno->estado) {
            return;
        }
        $correo = new Correo(Correo::ALUMNO_NUEVO, self::datosAcademia($academia), [
            'alumno' => trim("{$alumno->nombres} {$alumno->apellidos}"),
        ]);

        DB::afterCommit(function () use ($alumno, $correo) {
            $estado = 'encolado';
            $error = null;
            try {
                Mail::to($alumno->correo)->queue($correo);
            } catch (Exception $e) {
                $estado = 'error';
                $error = $e->getMessage();
                Log::error("No se pudo encolar la bienvenida a {$alumno->correo}: {$error}");
            }
            DB::table('notificaciones_enviadas')->insert([
                'tipo' => Correo::ALUMNO_NUEVO, 'curso_alumno_id' => null, 'alumno_id' => $alumno->id, 'curso_id' => null,
                'periodo' => Carbon::now()->toDateString(), 'correo' => $alumno->correo,
                'estado' => $estado, 'error' => $error, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ]);
        });
    }

    /**
     * Encola el correo de bienvenida de una matrícula nueva. Se envía al confirmar la
     * transacción; un fallo al encolar no debe impedir la matrícula.
     */
    public static function bienvenida(int $cursoAlumnoId): void
    {
        $academia = \App\Support\Academias\GestorAcademias::actual();
        if (!$academia) {
            return;
        }

        $m = DB::table('curso_alumno as ca')
            ->join('cursos as c', 'c.id', '=', 'ca.curso_id')
            ->join('alumnos as a', 'a.id', '=', 'ca.alumno_id')
            ->leftJoin('planes as p', 'p.id', '=', 'c.plan_id')
            ->leftJoin('profesores as pr', 'pr.id', '=', 'c.profesor_id')
            ->where('ca.id', $cursoAlumnoId)
            ->select(
                'ca.id', 'ca.curso_id', 'ca.alumno_id', 'ca.fecha_matricula', 'ca.ciclo_inicio', 'ca.modalidad', 'a.correo',
                'c.nombre as curso', 'c.sede_id', 'c.dia', 'c.hora', 'c.fecha_inicio',
                DB::raw("CONCAT(a.nombres, ' ', a.apellidos) as alumno"),
                DB::raw("CONCAT(pr.nombres, ' ', pr.apellidos) as profesor"),
                'p.valor as valor_plan', 'p.periodicidad', 'p.num_clases'
            )
            ->first();

        if (!$m || !$m->correo) {
            return;
        }

        $porCiclos = $m->periodicidad === 'mensual' && $m->modalidad === 'ciclo';
        $ciclo = (new CalendarioCurso())->ciclo($m, $m, CalendarioCurso::clasesPorCiclo($m->num_clases));
        $datos = [
            'alumno' => $m->alumno,
            'curso' => $m->curso,
            'profesor' => $m->profesor,
            'horario' => (self::DIAS_SEMANA[$m->dia] ?? '') . ' ' . substr((string) $m->hora, 0, 5),
            'valor' => $porCiclos ? Tarifas::precio($m->id)['valor'] : null,
            'primera_clase' => $ciclo['inicio']->format('d/m/Y'),
            'clases_por_ciclo' => $porCiclos ? $ciclo['clases_por_ciclo'] : null,
            'proximo_pago' => $porCiclos ? $ciclo['proximo_pago']->format('d/m/Y') : null,
        ];
        $correo = new Correo(Correo::BIENVENIDA, self::datosAcademia($academia), $datos);

        DB::afterCommit(function () use ($m, $correo) {
            $estado = 'encolado';
            $error = null;
            try {
                Mail::to($m->correo)->queue($correo);
            } catch (Exception $e) {
                $estado = 'error';
                $error = $e->getMessage();
                Log::error("No se pudo encolar la bienvenida a {$m->correo}: {$error}");
            }
            DB::table('notificaciones_enviadas')->insert([
                'tipo' => Correo::BIENVENIDA,
                'curso_alumno_id' => $m->id,
                'alumno_id' => $m->alumno_id,
                'curso_id' => $m->curso_id,
                'periodo' => Carbon::now()->toDateString(),
                'correo' => $m->correo,
                'estado' => $estado,
                'error' => $error,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        });
    }

    /**
     * Encola el resumen de una matrícula rápida: un solo correo con los cursos, su valor y el pago,
     * en lugar de una bienvenida por curso y una confirmación por pago.
     *
     * @param array<int, float> $valores  matrícula (curso_alumno.id) => valor de su primer ciclo
     * @param array|null $pago  fecha_pago y metodo_pago del pago recibido, si lo hubo
     */
    public static function resumenMatricula(int $alumnoId, array $valores, float $pagado, ?array $pago, bool $alumnoNuevo): void
    {
        $academia = \App\Support\Academias\GestorAcademias::actual();
        $alumno = DB::table('alumnos')->where('id', $alumnoId)->first();
        if (!$academia || !$alumno || !$alumno->correo) {
            return;
        }

        $matriculas = DB::table('curso_alumno as ca')
            ->join('cursos as c', 'c.id', '=', 'ca.curso_id')
            ->leftJoin('ritmos as r', 'r.id', '=', 'c.ritmo_id')
            ->whereIn('ca.id', array_keys($valores))
            ->orderBy('ca.id')
            ->select('ca.id', 'ca.ciclo_inicio', 'ca.modalidad', 'c.dia', 'c.hora', DB::raw('COALESCE(c.nombre, r.nombre) as curso'))
            ->get();
        $dinero = fn ($v) => '$' . number_format((float) $v, 0, ',', '.');
        // Una línea por curso: nombre, horario, primera clase y valor del ciclo.
        $lineas = $matriculas->map(fn ($m) => implode(' · ', array_filter([
            $m->curso,
            (self::DIAS_SEMANA[$m->dia] ?? '') . ' ' . substr((string) $m->hora, 0, 5),
            $m->ciclo_inicio ? 'primera clase ' . Carbon::parse($m->ciclo_inicio)->format('d/m/Y') : null,
            $m->modalidad === 'paquete' ? 'con tu paquete de clases' : $dinero($valores[$m->id] ?? 0),
        ])));

        $total = array_sum($valores);
        $metodos = ['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'tarjeta' => 'Tarjeta', 'otro' => 'Otro'];
        $correo = new Correo(Correo::MATRICULA, self::datosAcademia($academia), [
            'alumno' => trim("{$alumno->nombres} {$alumno->apellidos}"),
            'curso' => $lineas->count() === 1 ? $matriculas->first()->curso : "{$lineas->count()} cursos",
            'cursos' => $lineas->implode("\n"),
            'valor' => $total,
            'pagado' => $dinero($pagado),
            'saldo' => max(0, $total - $pagado),
            'fecha_pago' => $pagado > 0 ? Carbon::parse($pago['fecha_pago'])->format('d/m/Y') : '',
            'metodo' => $pagado > 0 ? ($metodos[$pago['metodo_pago']] ?? $pago['metodo_pago']) : '',
            'alumno_nuevo' => $alumnoNuevo,
        ]);

        DB::afterCommit(function () use ($alumno, $correo) {
            $estado = 'encolado';
            $error = null;
            try {
                Mail::to($alumno->correo)->queue($correo);
            } catch (Exception $e) {
                $estado = 'error';
                $error = $e->getMessage();
                Log::error("No se pudo encolar el resumen de matrícula a {$alumno->correo}: {$error}");
            }
            DB::table('notificaciones_enviadas')->insert([
                'tipo' => Correo::MATRICULA, 'curso_alumno_id' => null, 'alumno_id' => $alumno->id, 'curso_id' => null,
                'periodo' => Carbon::now()->toDateString(), 'correo' => $alumno->correo,
                'estado' => $estado, 'error' => $error, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ]);
        });
    }

    /**
     * Encola la confirmación de un pago recién registrado (valor, concepto y saldo que queda).
     * Se envía al confirmar la transacción; un fallo al encolar no impide el pago.
     */
    public static function confirmacionPago(int $pagoId): void
    {
        $academia = \App\Support\Academias\GestorAcademias::actual();
        $p = DB::table('pagos as pg')
            ->join('alumnos as a', 'a.id', '=', 'pg.alumno_id')
            ->leftJoin('cursos as c', 'c.id', '=', 'pg.curso_id')
            ->leftJoin('ritmos as r', 'r.id', '=', 'c.ritmo_id')
            ->leftJoin('paquetes_alumno as pa', 'pa.id', '=', 'pg.paquete_id')
            ->leftJoin('planes as pp', 'pp.id', '=', 'pa.plan_id')
            ->where('pg.id', $pagoId)
            ->select('pg.*', 'a.correo', DB::raw("CONCAT(a.nombres, ' ', a.apellidos) as alumno"),
                DB::raw('COALESCE(c.nombre, r.nombre) as curso'), 'pa.saldo as saldo_paquete', 'pp.nombre as paquete')
            ->first();
        if (!$academia || !$p || !$p->correo) {
            return;
        }
        if ($p->paquete_id) {
            $concepto = 'Paquete ' . ($p->paquete ?? "#{$p->paquete_id}");
            $saldo = (float) $p->saldo_paquete;
        } else {
            $concepto = $p->curso ?? 'Pago';
            $saldo = (float) DB::table('curso_alumno')->where('curso_id', $p->curso_id)->where('alumno_id', $p->alumno_id)->value('saldo');
        }
        $metodos = ['efectivo' => 'Efectivo', 'transferencia' => 'Transferencia', 'tarjeta' => 'Tarjeta', 'otro' => 'Otro'];
        $correo = new Correo(Correo::PAGO, self::datosAcademia($academia), [
            'alumno' => $p->alumno,
            'curso' => $concepto,
            'concepto' => $concepto,
            'valor' => (float) $p->monto,
            'saldo' => max(0, $saldo),
            'fecha_pago' => Carbon::parse($p->fecha_pago)->format('d/m/Y'),
            'metodo' => $metodos[$p->metodo_pago] ?? $p->metodo_pago,
        ]);

        DB::afterCommit(function () use ($p, $correo) {
            $estado = 'encolado';
            $error = null;
            try {
                Mail::to($p->correo)->queue($correo);
            } catch (Exception $e) {
                $estado = 'error';
                $error = $e->getMessage();
                Log::error("No se pudo encolar la confirmación de pago a {$p->correo}: {$error}");
            }
            DB::table('notificaciones_enviadas')->insert([
                'tipo' => Correo::PAGO, 'curso_alumno_id' => null, 'alumno_id' => $p->alumno_id, 'curso_id' => $p->curso_id,
                'periodo' => Carbon::parse($p->fecha_pago)->toDateString(), 'correo' => $p->correo,
                'estado' => $estado, 'error' => $error, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
            ]);
        });
    }
}
