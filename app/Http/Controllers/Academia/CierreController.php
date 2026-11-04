<?php

namespace App\Http\Controllers\Academia;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\Academia\Sede;
use Illuminate\Support\Facades\Validator;

/**
 * Cierres de la academia (vacaciones, eventos): como los festivos, esas fechas no tienen clase
 * y los ciclos de los alumnos se corren. Sin sede, el cierre aplica a todas las sedes.
 * Con cursos (cierre_curso), aplica solo a esos cursos: la sede no se usa.
 */
class CierreController extends Controller
{
    /** Cursos de cada cierre: cierre_id => [{ id, nombre }]. */
    private function cursosDe(array $cierreIds)
    {
        return DB::table('cierre_curso as cc')
            ->join('cursos as c', 'c.id', '=', 'cc.curso_id')
            ->leftJoin('ritmos as r', 'r.id', '=', 'c.ritmo_id')
            ->whereIn('cc.cierre_id', $cierreIds)
            ->orderBy('r.nombre')
            ->get(['cc.cierre_id', 'c.id', 'c.dia', DB::raw('COALESCE(c.nombre, r.nombre) as ritmo'), DB::raw("DATE_FORMAT(c.hora,'%H:%i') as hora")])
            ->groupBy('cierre_id');
    }

    private function formatear(object $c, $cursos = null): array
    {
        $dias = Carbon::parse($c->fecha_desde)->diffInDays(Carbon::parse($c->fecha_hasta)) + 1;
        $cursos = collect($cursos ?? $this->cursosDe([$c->id])[$c->id] ?? []);
        $nombresDia = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        return [
            'id' => $c->id,
            'fecha_desde' => $c->fecha_desde,
            'fecha_hasta' => $c->fecha_hasta,
            'motivo' => $c->motivo,
            'sede_id' => $c->sede_id,
            'sede_nombre' => $cursos->isNotEmpty() ? 'Solo los cursos elegidos'
                : ($c->sede_id ? DB::table('sedes')->where('id', $c->sede_id)->value('nombre') : 'Todas'),
            'cursos' => $cursos->pluck('id')->map(fn ($id) => (int) $id)->values(),
            'cursos_nombres' => $cursos->map(fn ($x) => "{$x->ritmo} {$nombresDia[(int) $x->dia]} {$x->hora}")->implode(', ') ?: 'Todos',
            'dias' => (int) round($dias),
            'usuario_creacion_nombre' => $c->usuario_creacion_nombre,
            'usuario_modificacion_nombre' => $c->usuario_modificacion_nombre,
            'fecha_creacion' => Carbon::parse($c->created_at)->format('Y-m-d H:i:s'),
            'fecha_modificacion' => Carbon::parse($c->updated_at)->format('Y-m-d H:i:s'),
        ];
    }

    public function index(Request $request)
    {
        $query = DB::table('cierres_academia');
        Sede::filtrar($query, 'sede_id', true);
        if ($request->filled('anio')) {
            $query->where(fn ($q) => $q->whereYear('fecha_desde', $request->anio)->orWhereYear('fecha_hasta', $request->anio));
        }
        $pagina = $query->orderBy('fecha_desde', 'desc')->paginate((int) ($request->limite ?? 100));
        $cursos = $this->cursosDe($pagina->getCollection()->pluck('id')->all());
        return response([
            'datos' => $pagina->getCollection()->map(fn ($c) => $this->formatear($c, $cursos[$c->id] ?? []))->all(),
            'desde' => $pagina->firstItem(),
            'hasta' => $pagina->lastItem(),
            'por_pagina' => $pagina->perPage(),
            'pagina_actual' => $pagina->currentPage(),
            'ultima_pagina' => $pagina->lastPage(),
            'total' => $pagina->total(),
        ], Response::HTTP_OK);
    }

    public function show($id)
    {
        $cierre = DB::table('cierres_academia')->find($id);
        if (!$cierre) {
            return response(get_response_body(['El cierre no existe.']), Response::HTTP_NOT_FOUND);
        }
        return response($this->formatear($cierre), Response::HTTP_OK);
    }

    private function guardar(Request $request, ?int $id = null)
    {
        $validator = Validator::make($request->all(), [
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde',
            'motivo' => 'required|string|max:150',
            'sede_id' => 'nullable|integer|exists:sedes,id',
            'cursos' => 'nullable|array',
            'cursos.*' => 'integer|exists:cursos,id',
        ], ['fecha_hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.']);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }

        $usuario = Auth::user()->usuario();
        $datos = [
            'fecha_desde' => Carbon::parse($request->fecha_desde)->toDateString(),
            'fecha_hasta' => Carbon::parse($request->fecha_hasta)->toDateString(),
            'motivo' => $request->motivo,
            // Un cierre por cursos no depende de la sede: cada curso ya tiene la suya.
            'sede_id' => $request->cursos ? null : ($request->sede_id ?: null),
            'usuario_modificacion_id' => $usuario->id,
            'usuario_modificacion_nombre' => $usuario->nombre,
            'updated_at' => Carbon::now(),
        ];
        if ($id) {
            DB::table('cierres_academia')->where('id', $id)->update($datos);
        } else {
            $id = DB::table('cierres_academia')->insertGetId(array_merge($datos, [
                'usuario_creacion_id' => $usuario->id,
                'usuario_creacion_nombre' => $usuario->nombre,
                'created_at' => Carbon::now(),
            ]));
        }
        DB::table('cierre_curso')->where('cierre_id', $id)->delete();
        foreach (array_unique(array_map('intval', $request->cursos ?? [])) as $cursoId) {
            DB::table('cierre_curso')->insert(['cierre_id' => $id, 'curso_id' => $cursoId]);
        }
        return $id;
    }

    public function store(Request $request)
    {
        $id = $this->guardar($request);
        if ($id instanceof \Illuminate\Http\Response) {
            return $id;
        }
        $quienes = $request->cursos ? 'Las clases de esos cursos' : 'Las clases de esas fechas';
        return response(get_response_body(["El cierre ha sido registrado. {$quienes} se corren a la semana siguiente.", 2],
            $this->formatear(DB::table('cierres_academia')->find($id))), Response::HTTP_CREATED);
    }

    public function update(Request $request, $id)
    {
        if (!DB::table('cierres_academia')->where('id', $id)->exists()) {
            return response(get_response_body(['El cierre no existe.']), Response::HTTP_NOT_FOUND);
        }
        $resultado = $this->guardar($request, (int) $id);
        if ($resultado instanceof \Illuminate\Http\Response) {
            return $resultado;
        }
        return response(get_response_body(['El cierre ha sido modificado.', 1],
            $this->formatear(DB::table('cierres_academia')->find($id))), Response::HTTP_OK);
    }

    public function destroy($id)
    {
        DB::table('cierres_academia')->where('id', $id)->delete();
        return response(get_response_body(['El cierre ha sido eliminado.', 3]), Response::HTTP_OK);
    }
}
