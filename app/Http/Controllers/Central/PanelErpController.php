<?php

namespace App\Http\Controllers\Central;

use Carbon\Carbon;
use Illuminate\Http\Response;
use App\Models\Central\Academia;
use App\Models\Central\PagoAcademia;
use App\Http\Controllers\Controller;
use App\Models\Central\FacturaAcademia;
use App\Services\Central\FacturacionAcademias;

/** Indicadores del negocio del dueño del ERP. */
class PanelErpController extends Controller
{
    public function index()
    {
        $clientes = Academia::where('es_administradora', false)->orderBy('nombre')->get();
        $conEstado = $clientes->map(fn ($a) => [$a, FacturacionAcademias::estadoCuenta($a)]);

        $conteo = ['al_dia' => 0, 'pendiente' => 0, 'en_mora' => 0, 'suspendida' => 0];
        foreach ($conEstado as [, $cuenta]) {
            $conteo[$cuenta['estado']] = ($conteo[$cuenta['estado']] ?? 0) + 1;
        }

        $inicioMes = Carbon::today()->startOfMonth();
        $recaudoMes = (float) PagoAcademia::whereDate('fecha_pago', '>=', $inicioMes)->sum('valor');

        // Recaudo de los últimos 6 meses.
        $recaudoMensual = collect(range(5, 0))->map(function ($atras) {
            $mes = Carbon::today()->startOfMonth()->subMonths($atras);
            return [
                'mes' => ucfirst($mes->copy()->locale('es')->translatedFormat('M Y')),
                'valor' => (float) PagoAcademia::whereBetween('fecha_pago', [$mes->toDateString(), $mes->copy()->endOfMonth()->toDateString()])->sum('valor'),
            ];
        })->values();

        $morosas = $conEstado
            ->filter(fn ($x) => in_array($x[1]['estado'], ['en_mora', 'suspendida'], true))
            ->map(fn ($x) => [
                'id' => $x[0]->id,
                'nombre' => $x[0]->nombre,
                'estado' => $x[1]['estado'],
                'saldo' => $x[1]['saldo'],
                'fecha_suspension' => $x[1]['fecha_suspension'],
                'correo' => $x[0]->correo,
                'telefono' => $x[0]->telefono,
            ])->values();

        return response([
            // Ingreso mensual recurrente: suma de tarifas de las academias que se facturan.
            'mrr' => (float) $clientes->where('activa', true)->sum('tarifa_mensual'),
            'academias_total' => $clientes->count(),
            'academias_activas' => $clientes->where('activa', true)->count(),
            'estados' => $conteo,
            'cartera' => (float) FacturaAcademia::where('estado', FacturaAcademia::PENDIENTE)->sum('saldo'),
            'cartera_vencida' => (float) FacturaAcademia::where('estado', FacturaAcademia::PENDIENTE)
                ->whereDate('fecha_vencimiento', '<', Carbon::today())->sum('saldo'),
            'recaudo_mes' => $recaudoMes,
            'recaudo_mensual' => $recaudoMensual,
            'morosas' => $morosas,
        ], Response::HTTP_OK);
    }
}
