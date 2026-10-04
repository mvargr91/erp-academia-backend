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
 */
class CierreController extends Controller
{
    private function formatear(object $c): array
    {
        $dias = Carbon::parse($c->fecha_desde)->diffInDays(Carbon::parse($c->fecha_hasta)) + 1;
        return [
            'id' => $c->id,
            'fecha_desde' => $c->fecha_desde,
            'fecha_hasta' => $c->fecha_hasta,
            'motivo' => $c->motivo,
            'sede_id' => $c->sede_id,
            'sede_nombre' => $c->sede_id ? DB::table('sedes')->where('id', $c->sede_id)->value('nombre') : 'Todas',
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
        return response([
            'datos' => $pagina->getCollection()->map(fn ($c) => $this->formatear($c))->all(),
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
        ], ['fecha_hasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.']);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }

        $usuario = Auth::user()->usuario();
        $datos = [
            'fecha_desde' => Carbon::parse($request->fecha_desde)->toDateString(),
            'fecha_hasta' => Carbon::parse($request->fecha_hasta)->toDateString(),
            'motivo' => $request->motivo,
            'sede_id' => $request->sede_id ?: null,
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
        return $id;
    }

    public function store(Request $request)
    {
        $id = $this->guardar($request);
        if ($id instanceof \Illuminate\Http\Response) {
            return $id;
        }
        return response(get_response_body(['El cierre ha sido registrado. Las clases de esas fechas se corren a la semana siguiente.', 2],
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
