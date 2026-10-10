<?php

namespace App\Http\Controllers\Academia;

use Carbon\Carbon;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Academia\Curso;
use Illuminate\Support\Facades\Validator;
use App\Services\Academia\CalendarioCurso;

/** Calendario de clases de un curso con sus festivos y cierres. */
class CalendarioController extends Controller
{
    private const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    public function curso(Request $request, $id)
    {
        $curso = DB::table('cursos as c')
            ->leftJoin('ritmos as r', 'r.id', '=', 'c.ritmo_id')
            ->where('c.id', $id)
            ->select('c.id', 'c.nombre', 'c.sede_id', 'c.dia', 'c.hora', 'c.fecha_inicio', 'r.nombre as ritmo')
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

        $clasesPorCiclo = CalendarioCurso::clasesPorCiclo();
        return response([
            'curso' => [
                'id' => $curso->id,
                'nombre' => $curso->nombre ?: $curso->ritmo,
                'horario' => self::DIAS[(int) $curso->dia] . ' ' . substr((string) $curso->hora, 0, 5),
                'clases_por_ciclo' => $clasesPorCiclo,
                'fecha_inicio' => $curso->fecha_inicio,
            ],
            'semanas' => $lista,
        ], Response::HTTP_OK);
    }

    /**
     * Define si un alumno paga este curso individual o en pareja con otro alumno del mismo curso.
     * { pareja_alumno_id: id|null, ajustar_ciclo: bool } → devuelve los matriculados actualizados.
     */
    public function pareja(Request $request, $id, $alumnoId)
    {
        $validator = Validator::make($request->all(), [
            'pareja_alumno_id' => 'nullable|integer|exists:alumnos,id',
            'ajustar_ciclo' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }
        $parejaId = $request->filled('pareja_alumno_id') ? (int) $request->pareja_alumno_id : null;
        try {
            DB::transaction(fn () => Curso::emparejar((int) $id, (int) $alumnoId, $parejaId, $request->boolean('ajustar_ciclo')));
        } catch (DomainException $e) {
            return response(get_response_body([$e->getMessage()]), Response::HTTP_CONFLICT);
        }
        $cuando = $request->boolean('ajustar_ciclo') ? 'Se ajustó el saldo del ciclo actual.' : 'El precio aplica desde el siguiente ciclo.';
        $mensaje = $parejaId ? "Los dos alumnos ahora pagan en pareja. {$cuando}" : "El alumno ahora paga individual. {$cuando}";
        return response(get_response_body([$mensaje, 1], Curso::cargar($id)['matriculados']), Response::HTTP_OK);
    }

    /**
     * Valor especial que paga un alumno por ciclo en este curso, en lugar de la tarifa.
     * { valor: número|null (null = vuelve a la tarifa), motivo, ajustar_ciclo: bool } → matriculados actualizados.
     */
    public function valorEspecial(Request $request, $id, $alumnoId)
    {
        $validator = Validator::make($request->all(), [
            'valor' => 'nullable|numeric|min:0',
            'motivo' => 'nullable|string|max:150',
            'ajustar_ciclo' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }
        $valor = $request->filled('valor') ? (float) $request->valor : null;
        try {
            DB::transaction(fn () => Curso::valorEspecial((int) $id, (int) $alumnoId, $valor, $request->motivo, $request->boolean('ajustar_ciclo')));
        } catch (DomainException $e) {
            return response(get_response_body([$e->getMessage()]), Response::HTTP_CONFLICT);
        }
        $cuando = $request->boolean('ajustar_ciclo') ? 'Se ajustó el saldo del ciclo actual.' : 'Aplica desde el siguiente ciclo.';
        $mensaje = $valor === null ? "El alumno vuelve a pagar la tarifa. {$cuando}" : "Se guardó el valor especial del alumno. {$cuando}";
        return response(get_response_body([$mensaje, 1], Curso::cargar($id)['matriculados']), Response::HTTP_OK);
    }

}
