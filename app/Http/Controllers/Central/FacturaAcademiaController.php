<?php

namespace App\Http\Controllers\Central;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Models\Central\FacturaAcademia;
use Illuminate\Support\Facades\Validator;

/** Cuentas de cobro del ERP a las academias. Se generan con facturacion:academias. */
class FacturaAcademiaController extends Controller
{
    private const ESTADOS = [
        'pendiente' => 'Pendiente',
        'vencida' => 'Vencida',
        'pagada' => 'Pagada',
        'anulada' => 'Anulada',
    ];

    public static function formatear(FacturaAcademia $f): array
    {
        $estado = $f->estado;
        if ($estado === FacturaAcademia::PENDIENTE && $f->fecha_vencimiento->lt(today())) {
            $estado = 'vencida';
        }
        return [
            'id' => $f->id,
            'numero' => $f->numero(),
            'academia_id' => $f->academia_id,
            'academia_nombre' => $f->academia?->nombre,
            'periodo' => $f->periodo->format('Y-m-d'),
            'periodo_nombre' => ucfirst($f->periodo->copy()->locale('es')->translatedFormat('F Y')),
            'fecha_vencimiento' => $f->fecha_vencimiento->format('Y-m-d'),
            'valor' => $f->valor,
            'saldo' => $f->saldo,
            'estado' => $estado,
            'estado_nombre' => self::ESTADOS[$estado],
            'observacion' => $f->observacion,
            'nombre' => $f->numero() . ' · ' . $f->academia?->nombre . ' · saldo $' . number_format($f->saldo, 0, ',', '.'),
            'fecha_creacion' => $f->created_at?->format('Y-m-d H:i:s'),
            'fecha_modificacion' => $f->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = FacturaAcademia::with('academia');
            if ($request->filled('academia_id')) {
                $query->where('academia_id', $request->academia_id);
            }
            if ($request->filled('estado')) {
                match ($request->estado) {
                    'vencida' => $query->where('estado', FacturaAcademia::PENDIENTE)->whereDate('fecha_vencimiento', '<', today()),
                    'por_cobrar' => $query->where('estado', FacturaAcademia::PENDIENTE),
                    default => $query->where('estado', $request->estado),
                };
            }

            // Lista para el selector del pago: cuentas con saldo.
            if ($request->ligera) {
                return response(
                    $query->where('estado', FacturaAcademia::PENDIENTE)->orderBy('fecha_vencimiento')->get()
                        ->map(fn ($f) => self::formatear($f))->values(),
                    Response::HTTP_OK
                );
            }

            $ordenables = ['periodo', 'fecha_vencimiento', 'valor', 'saldo', 'estado', 'created_at'];
            if ($request->filled('ordenar_por')) {
                foreach (explode(',', $request->ordenar_por) as $orden) {
                    [$campo, $dir] = array_pad(explode(':', $orden), 2, 'asc');
                    $campo = $campo === 'fecha_modificacion' ? 'updated_at' : $campo;
                    if (in_array($campo, array_merge($ordenables, ['updated_at']), true)) {
                        $query->orderBy($campo, $dir === 'desc' ? 'desc' : 'asc');
                    }
                }
            }
            $query->orderBy('periodo', 'desc');

            $pagina = $query->paginate((int) ($request->limite ?? 100));
            return response([
                'datos' => $pagina->getCollection()->map(fn ($f) => self::formatear($f))->all(),
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
        $factura = FacturaAcademia::with(['academia', 'pagos'])->find($id);
        if (!$factura) {
            return response(get_response_body(['La cuenta de cobro no existe.']), Response::HTTP_NOT_FOUND);
        }
        $datos = self::formatear($factura);
        $datos['pagos'] = $factura->pagos->map(fn ($p) => [
            'id' => $p->id,
            'valor' => $p->valor,
            'fecha_pago' => $p->fecha_pago->format('Y-m-d'),
            'metodo' => $p->metodo,
            'referencia' => $p->referencia,
        ]);
        return response($datos, Response::HTTP_OK);
    }

    /** Solo se permite anular (p. ej. si se cobró por error) o ajustar la observación. */
    public function update(Request $request, $id)
    {
        $factura = FacturaAcademia::find($id);
        if (!$factura) {
            return response(get_response_body(['La cuenta de cobro no existe.']), Response::HTTP_NOT_FOUND);
        }
        $validator = Validator::make($request->all(), [
            'anular' => 'boolean',
            'observacion' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
        }
        if ($request->boolean('anular')) {
            if ($factura->pagos()->exists()) {
                return response(get_response_body(['No se puede anular: tiene pagos registrados. Elimina primero los pagos.']), Response::HTTP_CONFLICT);
            }
            $factura->estado = FacturaAcademia::ANULADA;
            $factura->saldo = 0;
        }
        $factura->observacion = $request->observacion;
        $factura->save();

        \App\Services\Central\FacturacionAcademias::reactivarSiCorresponde($factura->academia);
        return response(get_response_body(['La cuenta de cobro ha sido modificada.', 1], self::formatear($factura)), Response::HTTP_OK);
    }
}
