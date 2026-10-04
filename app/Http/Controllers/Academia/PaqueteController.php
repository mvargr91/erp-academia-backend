<?php

namespace App\Http\Controllers\Academia;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\Academia\Sede;
use App\Services\Academia\Paquetes;
use Illuminate\Support\Facades\Validator;

/** Paquetes de clases comprados por los alumnos (para cursos grupales y clases privadas). */
class PaqueteController extends Controller
{
    private function consulta()
    {
        return DB::table('paquetes_alumno as pa')
            ->join('alumnos as a', 'a.id', '=', 'pa.alumno_id')
            ->leftJoin('planes as p', 'p.id', '=', 'pa.plan_id')
            ->leftJoin('sedes as s', 's.id', '=', 'pa.sede_id')
            ->select('pa.*', DB::raw("CONCAT(a.nombres,' ',a.apellidos) as alumno_nombre"), 'p.nombre as plan_nombre', 's.nombre as sede_nombre');
    }

    private function formatear(object $p, ?int $usadas = null): array
    {
        $resumen = Paquetes::resumen($p, $usadas);
        return array_merge([
            'id' => $p->id,
            'alumno_id' => $p->alumno_id,
            'alumno_nombre' => $p->alumno_nombre,
            'sede_id' => $p->sede_id,
            'sede_nombre' => $p->sede_nombre,
            'plan_id' => $p->plan_id,
            'plan_nombre' => $p->plan_nombre,
            'fecha_compra' => $p->fecha_compra,
            'fecha_vencimiento' => $p->fecha_vencimiento,
            'valor' => (float) $p->valor,
            'saldo' => (float) $p->saldo,
            'estado' => $p->estado,
            'observacion' => $p->observacion,
            // Para selectores (pagos): texto corto identificable.
            'nombre' => "#{$p->id} · " . ($p->plan_nombre ?? 'Paquete') . " · {$resumen['clases_restantes']} de {$p->clases_total} clases",
            'usuario_creacion_nombre' => $p->usuario_creacion_nombre,
            'usuario_modificacion_nombre' => $p->usuario_modificacion_nombre,
            'fecha_creacion' => Carbon::parse($p->created_at)->format('Y-m-d H:i:s'),
            'fecha_modificacion' => Carbon::parse($p->updated_at)->format('Y-m-d H:i:s'),
        ], $resumen);
    }

    public function index(Request $request)
    {
        $query = $this->consulta();
        if ($request->filled('alumno_id')) {
            $query->where('pa.alumno_id', $request->alumno_id);
        }
        if ($request->filled('nombre')) {
            $query->where(DB::raw("CONCAT(a.nombres,' ',a.apellidos)"), 'like', '%' . $request->nombre . '%');
        }
        $query->orderBy('pa.fecha_compra', 'desc')->orderBy('pa.id', 'desc');

        // Selector de pagos: paquetes del alumno con saldo por pagar (de cualquier sede: el paquete sirve en todas).
        if ($request->ligera) {
            $paquetes = $query->where('pa.estado', Paquetes::ACTIVO)->get();
            $usadas = Paquetes::usadas($paquetes->pluck('id')->all());
            return response($paquetes->map(fn ($p) => $this->formatear($p, $usadas[$p->id] ?? 0))->values(), Response::HTTP_OK);
        }

        $pagina = Sede::filtrar($query, 'pa.sede_id')->paginate((int) ($request->limite ?? 100));
        $usadas = Paquetes::usadas($pagina->getCollection()->pluck('id')->all());
        $datos = $pagina->getCollection()->map(fn ($p) => $this->formatear($p, $usadas[$p->id] ?? 0));
        if ($request->filled('estado')) {
            $datos = $datos->where('estado_efectivo', $request->estado);
        }
        return response([
            'datos' => $datos->values()->all(),
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
        $paquete = $this->consulta()->where('pa.id', $id)->first();
        if (!$paquete) {
            return response(get_response_body(['El paquete no existe.']), Response::HTTP_NOT_FOUND);
        }
        $datos = $this->formatear($paquete);
        $datos['consumos'] = DB::table('consumos_paquete as c')
            ->leftJoin('asistencias as s', 's.id', '=', 'c.asistencia_id')
            ->leftJoin('cursos as cu', 'cu.id', '=', 's.curso_id')
            ->leftJoin('ritmos as r', 'r.id', '=', 'cu.ritmo_id')
            ->where('c.paquete_id', $id)
            ->orderBy('c.fecha')
            ->select('c.id', 'c.fecha', 'c.origen', 'c.motivo', 'c.clase_privada_id',
                DB::raw("COALESCE(cu.nombre, r.nombre) as curso"))
            ->get();
        $datos['pagos'] = DB::table('pagos')->where('paquete_id', $id)->orderBy('fecha_pago')
            ->get(['id', 'monto', 'fecha_pago', 'metodo_pago']);
        return response($datos, Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'alumno_id' => 'required|integer|exists:alumnos,id',
            'sede_id' => 'nullable|integer|exists:sedes,id',
            'plan_id' => 'nullable|integer|exists:planes,id',
            'clases_total' => 'required|integer|min:1|max:500',
            'fecha_compra' => 'required|date',
            'fecha_vencimiento' => 'nullable|date|after_or_equal:fecha_compra',
            'valor' => 'required|numeric|min:0',
            'observacion' => 'nullable|string',
        ], ['fecha_vencimiento.after_or_equal' => 'El vencimiento no puede ser anterior a la compra.']);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }

        $usuario = Auth::user()->usuario();
        $id = DB::table('paquetes_alumno')->insertGetId([
            'alumno_id' => $request->alumno_id,
            'sede_id' => $request->sede_id ?: Sede::porDefecto(),
            'plan_id' => $request->plan_id,
            'clases_total' => $request->clases_total,
            'fecha_compra' => Carbon::parse($request->fecha_compra)->toDateString(),
            'fecha_vencimiento' => $request->fecha_vencimiento ? Carbon::parse($request->fecha_vencimiento)->toDateString() : null,
            'valor' => $request->valor,
            'saldo' => $request->valor,
            'estado' => Paquetes::ACTIVO,
            'observacion' => $request->observacion,
            'usuario_creacion_id' => $usuario->id,
            'usuario_creacion_nombre' => $usuario->nombre,
            'usuario_modificacion_id' => $usuario->id,
            'usuario_modificacion_nombre' => $usuario->nombre,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        return response(get_response_body(['El paquete ha sido registrado. Registra su pago en Pagos eligiendo este paquete.', 2],
            $this->formatear($this->consulta()->where('pa.id', $id)->first())), Response::HTTP_CREATED);
    }

    /** Se permite extender el vencimiento, anular/reactivar y la observación (no se cambian clases ni valor). */
    public function update(Request $request, $id)
    {
        $paquete = DB::table('paquetes_alumno')->find($id);
        if (!$paquete) {
            return response(get_response_body(['El paquete no existe.']), Response::HTTP_NOT_FOUND);
        }
        $validator = Validator::make($request->all(), [
            'fecha_vencimiento' => 'nullable|date|after_or_equal:' . $paquete->fecha_compra,
            'estado' => 'required|in:activo,anulado',
            'observacion' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }
        $usuario = Auth::user()->usuario();
        DB::table('paquetes_alumno')->where('id', $id)->update([
            'fecha_vencimiento' => $request->fecha_vencimiento ? Carbon::parse($request->fecha_vencimiento)->toDateString() : null,
            'estado' => $request->estado,
            'observacion' => $request->observacion,
            'usuario_modificacion_id' => $usuario->id,
            'usuario_modificacion_nombre' => $usuario->nombre,
            'updated_at' => Carbon::now(),
        ]);
        return response(get_response_body(['El paquete ha sido modificado.', 1],
            $this->formatear($this->consulta()->where('pa.id', $id)->first())), Response::HTTP_OK);
    }

    public function destroy($id)
    {
        if (DB::table('consumos_paquete')->where('paquete_id', $id)->exists() || DB::table('pagos')->where('paquete_id', $id)->exists()) {
            return response(get_response_body(['No se puede eliminar: el paquete tiene clases usadas o pagos. Puedes anularlo.']), Response::HTTP_CONFLICT);
        }
        DB::table('paquetes_alumno')->where('id', $id)->delete();
        return response(get_response_body(['El paquete ha sido eliminado.', 3]), Response::HTTP_OK);
    }
}
