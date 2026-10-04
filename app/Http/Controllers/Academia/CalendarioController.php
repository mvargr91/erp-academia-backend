<?php

namespace App\Http\Controllers\Academia;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Academia\Curso;
use App\Services\Academia\Tarifas;
use App\Services\Academia\Paquetes;
use Illuminate\Support\Facades\Validator;
use App\Services\Academia\CalendarioCurso;

/** Calendario de clases de un curso con festivos/cierres y el ciclo de pago de cada alumno. */
class CalendarioController extends Controller
{
    private const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    public function curso(Request $request, $id)
    {
        $curso = DB::table('cursos as c')
            ->leftJoin('ritmos as r', 'r.id', '=', 'c.ritmo_id')
            ->leftJoin('planes as p', 'p.id', '=', 'c.plan_id')
            ->where('c.id', $id)
            ->select('c.id', 'c.nombre', 'c.sede_id', 'c.dia', 'c.hora', 'c.fecha_inicio', 'r.nombre as ritmo',
                'p.nombre as plan', 'p.num_clases', 'p.periodicidad')
            ->first();
        if (!$curso) {
            return response(get_response_body(['El curso no existe.']), Response::HTTP_NOT_FOUND);
        }

        $calendario = new CalendarioCurso();
        $desde = $request->filled('desde') ? Carbon::parse($request->desde) : Carbon::today()->subWeeks(2);
        $semanas = min(max((int) ($request->semanas ?? 16), 1), 104);
        $hasta = $desde->copy()->addWeeks($semanas);

        // Cada semana sin clase indica a qué fecha se corre (la siguiente clase).
        $lista = $calendario->semanas($curso, $desde, $hasta);
        foreach ($lista as &$semana) {
            if ($semana['estado'] !== 'clase') {
                $semana['se_corre_a'] = $calendario->proximasClases($curso, Carbon::parse($semana['fecha']), 1)[0]?->toDateString();
            }
        }
        unset($semana);

        $clasesPorCiclo = CalendarioCurso::clasesPorCiclo($curso->num_clases);
        $alumnos = DB::table('curso_alumno as ca')
            ->join('alumnos as a', 'a.id', '=', 'ca.alumno_id')
            ->where('ca.curso_id', $id)
            ->where('ca.estado', 1)
            ->select('a.id as alumno_id', DB::raw("CONCAT(a.nombres,' ',a.apellidos) as nombre"),
                'ca.id as curso_alumno_id', 'ca.fecha_matricula', 'ca.ciclo_inicio', 'ca.saldo', 'ca.modalidad', 'ca.pareja_alumno_id')
            ->orderBy('a.nombres')
            ->get()
            ->map(function ($m) use ($calendario, $curso, $clasesPorCiclo) {
                $fila = ['alumno_id' => $m->alumno_id, 'nombre' => $m->nombre, 'modalidad' => $m->modalidad,
                    'saldo' => (float) $m->saldo, 'pareja_alumno_id' => $m->pareja_alumno_id];
                if ($m->modalidad === 'paquete') {
                    return $fila + ['paquete' => Paquetes::disponiblesHoy($m->alumno_id)];
                }
                $ciclo = $calendario->ciclo($curso, $m, $clasesPorCiclo);
                $precio = Tarifas::precio($m->curso_alumno_id);
                return $fila + [
                    'precio_ciclo' => $precio['valor'],
                    'precio_regla' => $precio['regla'],
                    'ciclo_inicio' => $ciclo['inicio']->toDateString(),
                    'ciclo_fin' => $ciclo['fin']->toDateString(),
                    'proximo_pago' => $ciclo['proximo_pago']->toDateString(),
                ];
            });

        return response([
            'curso' => [
                'id' => $curso->id,
                'nombre' => $curso->nombre ?: $curso->ritmo,
                'horario' => self::DIAS[(int) $curso->dia] . ' ' . substr((string) $curso->hora, 0, 5),
                'plan' => $curso->plan,
                'clases_por_ciclo' => $clasesPorCiclo,
                'fecha_inicio' => $curso->fecha_inicio,
            ],
            'semanas' => $lista,
            'alumnos' => $alumnos,
        ], Response::HTTP_OK);
    }

    /** Cambia cómo paga un alumno este curso: por ciclos de clases o con su paquete. */
    public function modalidad(Request $request, $id, $alumnoId)
    {
        $validator = Validator::make($request->all(), ['modalidad' => 'required|in:ciclo,paquete']);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }
        if ($request->modalidad === 'paquete' && Paquetes::disponiblesHoy((int) $alumnoId)['restantes'] <= 0) {
            return response(get_response_body(['El alumno no tiene un paquete vigente con clases disponibles.']), Response::HTTP_CONFLICT);
        }
        DB::transaction(fn () => Curso::cambiarModalidad((int) $id, (int) $alumnoId, $request->modalidad));
        $mensaje = $request->modalidad === 'paquete'
            ? 'El alumno ahora asiste con su paquete: cada clase descuenta 1.'
            : 'El alumno ahora paga por ciclos de clases; se cargó el primer ciclo a su saldo.';
        return response(get_response_body([$mensaje, 1]), Response::HTTP_OK);
    }

    /**
     * Asigna (o quita) la pareja de un alumno en este curso. Se guarda en ambas matrículas,
     * deshaciendo parejas anteriores, y aplica desde el siguiente ciclo (precio pareja).
     */
    public function pareja(Request $request, $id, $alumnoId)
    {
        $validator = Validator::make($request->all(), ['pareja_alumno_id' => 'nullable|integer']);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }
        $parejaId = $request->pareja_alumno_id ? (int) $request->pareja_alumno_id : null;
        $matriculados = DB::table('curso_alumno')->where('curso_id', $id)->where('estado', 1)->pluck('alumno_id')->map(fn ($x) => (int) $x)->all();
        if (!in_array((int) $alumnoId, $matriculados)) {
            return response(get_response_body(['El alumno no está matriculado en este curso.']), Response::HTTP_BAD_REQUEST);
        }
        if ($parejaId && ($parejaId === (int) $alumnoId || !in_array($parejaId, $matriculados))) {
            return response(get_response_body(['La pareja debe ser otro alumno matriculado en el mismo curso.']), Response::HTTP_BAD_REQUEST);
        }

        DB::transaction(function () use ($id, $alumnoId, $parejaId) {
            $involucrados = array_filter([(int) $alumnoId, $parejaId]);
            // Deshace parejas anteriores de ambos.
            DB::table('curso_alumno')->where('curso_id', $id)
                ->where(fn ($q) => $q->whereIn('alumno_id', $involucrados)->orWhereIn('pareja_alumno_id', $involucrados))
                ->update(['pareja_alumno_id' => null, 'updated_at' => now()]);
            if ($parejaId) {
                DB::table('curso_alumno')->where('curso_id', $id)->where('alumno_id', $alumnoId)->update(['pareja_alumno_id' => $parejaId]);
                DB::table('curso_alumno')->where('curso_id', $id)->where('alumno_id', $parejaId)->update(['pareja_alumno_id' => $alumnoId]);
            }
        });
        $mensaje = $parejaId
            ? 'Pareja asignada: desde el siguiente ciclo cada uno paga el precio de pareja (si es menor que su precio actual).'
            : 'Se quitó la pareja: desde el siguiente ciclo vuelven a su precio normal.';
        return response(get_response_body([$mensaje, 1]), Response::HTTP_OK);
    }
}
