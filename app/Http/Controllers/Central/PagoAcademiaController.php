<?php

namespace App\Http\Controllers\Central;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Central\PagoAcademia;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Services\Central\FacturacionAcademias;

/** Pagos recibidos de las academias (registro manual con soporte opcional). */
class PagoAcademiaController extends Controller
{
    public const METODOS = ['transferencia', 'consignacion', 'nequi', 'daviplata', 'efectivo', 'tarjeta', 'otro'];

    private function formatear(PagoAcademia $p): array
    {
        return [
            'id' => $p->id,
            'academia_id' => $p->academia_id,
            'academia_nombre' => $p->academia?->nombre,
            'factura_id' => $p->factura_id,
            'factura_numero' => $p->factura?->numero(),
            'valor' => $p->valor,
            'fecha_pago' => $p->fecha_pago->format('Y-m-d'),
            'metodo' => $p->metodo,
            'referencia' => $p->referencia,
            'soporte' => $p->soporte,
            'soporte_url' => $p->soporte ? Storage::disk('public')->url($p->soporte) : null,
            'observacion' => $p->observacion,
            'usuario_creacion_nombre' => $p->registrado_por,
            'fecha_creacion' => $p->created_at?->format('Y-m-d H:i:s'),
            'fecha_modificacion' => $p->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = PagoAcademia::with(['academia', 'factura']);
            if ($request->filled('academia_id')) {
                $query->where('academia_id', $request->academia_id);
            }
            if ($request->filled('fecha_desde')) {
                $query->whereDate('fecha_pago', '>=', $request->fecha_desde);
            }
            if ($request->filled('fecha_hasta')) {
                $query->whereDate('fecha_pago', '<=', $request->fecha_hasta);
            }
            $query->orderBy('fecha_pago', 'desc')->orderBy('id', 'desc');

            $pagina = $query->paginate((int) ($request->limite ?? 100));
            return response([
                'datos' => $pagina->getCollection()->map(fn ($p) => $this->formatear($p))->all(),
                'desde' => $pagina->firstItem(),
                'hasta' => $pagina->lastItem(),
                'por_pagina' => $pagina->perPage(),
                'pagina_actual' => $pagina->currentPage(),
                'ultima_pagina' => $pagina->lastPage(),
                'total' => $pagina->total(),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            return response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        $pago = PagoAcademia::with(['academia', 'factura'])->find($id);
        if (!$pago) {
            return response(get_response_body(['El pago no existe.']), Response::HTTP_NOT_FOUND);
        }
        return response($this->formatear($pago), Response::HTTP_OK);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'factura_id' => 'required|integer|exists:central.facturas_academia,id',
            'valor' => 'required|numeric|min:1',
            'fecha_pago' => 'required|date',
            'metodo' => 'required|in:' . implode(',', self::METODOS),
            'referencia' => 'nullable|string|max:100',
            'observacion' => 'nullable|string',
            'soporte' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }

        $datos = $request->only(['factura_id', 'valor', 'fecha_pago', 'metodo', 'referencia', 'observacion']);
        $datos['registrado_por'] = Auth::user()?->name;
        if ($request->hasFile('soporte')) {
            $datos['soporte'] = $request->file('soporte')->store('soportes-pagos-academias', 'public');
        }

        try {
            $pago = FacturacionAcademias::registrarPago($datos);
        } catch (Exception $e) {
            if (!empty($datos['soporte'])) {
                Storage::disk('public')->delete($datos['soporte']);
            }
            return response(get_response_body([$e->getMessage()]), Response::HTTP_CONFLICT);
        }

        $mensaje = 'El pago ha sido registrado.' . ($pago->reactivada ? ' La academia fue reactivada.' : '');
        return response(get_response_body([$mensaje, 2], $this->formatear($pago->fresh(['academia', 'factura']))), Response::HTTP_CREATED);
    }

    public function destroy($id)
    {
        $pago = PagoAcademia::find($id);
        if (!$pago) {
            return response(get_response_body(['El pago no existe.']), Response::HTTP_NOT_FOUND);
        }
        FacturacionAcademias::eliminarPago($pago);
        if ($pago->soporte) {
            Storage::disk('public')->delete($pago->soporte);
        }
        return response(get_response_body(['El pago ha sido eliminado.', 3]), Response::HTTP_OK);
    }
}
