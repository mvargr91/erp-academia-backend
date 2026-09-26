<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class PortafolioController extends Controller
{
    public function generarPDF()
    {
        $fecha = Carbon::now()->toDateString();

        // Leer el valor mínimo de inversión permitido
        $valorMinimo = DB::table('parametros_constantes')
            ->where('codigo_parametro', 'VALOR_MINIMO_CATALOGO_PROYECTOS')
            ->value('valor_parametro');

        // Obtener proyectos activos con saldo por invertir (excluyendo inversiones ANU)
        $proyectos = DB::table('proyectos')
        ->leftJoin('inversiones_proyectos', 'inversiones_proyectos.id_proyecto', '=', 'proyectos.id')
        ->leftJoin('inversiones', function ($join) {
            $join->on('inversiones.id', '=', 'inversiones_proyectos.id_inversion')
                ->whereNotIn('inversiones.estado_inversion', ['ANU', 'PAG']);
        })
        ->select(
            'proyectos.id',
            'proyectos.codigo_proyecto',
            'proyectos.potencia',
            'proyectos.ciudad_id',
            'proyectos.id_sector_proyecto',
            'proyectos.id_tipo_inversion',
            'proyectos.valor_inversion_proyecto',
            DB::raw('COALESCE(SUM(CASE WHEN inversiones.estado_inversion NOT IN ("ANU", "PAG") THEN inversiones_proyectos.valor_inversion_por_proyecto ELSE 0 END), 0) as valor_invertido'),
            DB::raw('(proyectos.valor_inversion_proyecto - COALESCE(SUM(CASE WHEN inversiones.estado_inversion NOT IN ("ANU", "PAG") THEN inversiones_proyectos.valor_inversion_por_proyecto ELSE 0 END), 0)) as saldo_por_invertir')
        )
        ->where('proyectos.estado_proyecto', 1)
        ->groupBy(
            'proyectos.id',
            'proyectos.codigo_proyecto',
            'proyectos.potencia',
            'proyectos.ciudad_id',
            'proyectos.id_sector_proyecto',
            'proyectos.id_tipo_inversion',
            'proyectos.valor_inversion_proyecto'
        )
        ->havingRaw('(proyectos.valor_inversion_proyecto - COALESCE(SUM(CASE WHEN inversiones.estado_inversion NOT IN ("ANU", "PAG") THEN inversiones_proyectos.valor_inversion_por_proyecto ELSE 0 END), 0)) > 0')
        ->get();

        $cuentaInversion = DB::table('parametros_constantes')
            ->where('codigo_parametro', 'DATOS_CUENTA_BANCARIA_INVERSION')
            ->value('valor_parametro');

        $compania = DB::table('companias')->first(); 
        

        $resultados = [];

        foreach ($proyectos as $proyecto) {
            $valorInversion = $proyecto->saldo_por_invertir;

            if ($valorInversion <= $valorMinimo) continue;

            $ciudad = $proyecto->ciudad_id
                ? DB::table('ciudades')->where('id', $proyecto->ciudad_id)->value('nombre')
                : 'N/D';

            $sector = $proyecto->id_sector_proyecto
                ? DB::table('sectores_proyectos')->where('id', $proyecto->id_sector_proyecto)->value('nombre')
                : 'N/D';

            $tipoInversion = DB::table('tipos_inversion')->where('id', $proyecto->id_tipo_inversion)->first();

            if (!$tipoInversion) continue;

            $valorUtilidad = $valorInversion * ($tipoInversion->tasa_interes_inversion / 100) * $tipoInversion->plazo_capital;
            $valorLiquidar = $valorInversion + $valorUtilidad;

            $resultados[] = [
                'codigo_proyecto' => $proyecto->codigo_proyecto,
                'ciudad' => $ciudad,
                'sector' => $sector,
                'potencia' => $proyecto->potencia,
                'plazo' => "{$tipoInversion->plazo_capital}/{$tipoInversion->plazo_interes}",
                'plazo_capital' => "{$tipoInversion->plazo_capital}",
                'plazo_interes' => "{$tipoInversion->plazo_interes}",
                'tasa' => number_format($tipoInversion->tasa_interes_inversion, 2) . '%',
                'valor_inversion' => number_format($valorInversion, 0, ',', '.'),
                'valor_utilidad' => number_format($valorUtilidad, 0, ',', '.'),
                'valor_liquidar' => number_format($valorLiquidar, 0, ',', '.'),
            ];
        }

        // Generar el PDF con la vista correspondiente
        $pdf = Pdf::loadView('pdf.portafolio_inversiones', [
            'fecha' => $fecha,
            'proyectos' => $resultados,
            'tipoInversion' => $tipoInversion,
            'cuentaInversion' => $cuentaInversion, 
            'compania' => $compania, 
            'plazo_capital' => $resultados[0]['plazo_capital'],
            'plazo_interes' => $resultados[0]['plazo_interes']
        ]);

        return $pdf->download('portafolio_inversiones_' . time() . '.pdf');
    }
}
