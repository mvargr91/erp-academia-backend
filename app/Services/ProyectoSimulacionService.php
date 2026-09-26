<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ProyectoSimulacionService
{
    /**
     * Devuelve estructura para grilla:
     * - years: [2026, 2027, ...]
     * - rows:  [
     *    [
     *      id_concepto_proyecto, secuencia, concepto,
     *      valores_por_anio: [2026=>123, 2027=>456, ...],
     *      total => 579
     *    ], ...
     *  ]
     */
    public function cargarDetalleConceptosPorAnio(int $idSimulacion): array
    {
        // 1) Traer TODO con INNER JOIN a conceptos_proyectos
        $items = DB::table('proyectos_simulaciones_conceptos as psc')
            ->join(
                'conceptos_proyectos as cp',
                'cp.id',
                '=',
                'psc.id_concepto_proyecto'
            )
            ->select([
                'psc.id_simulacion',
                'psc.anio',
                'psc.id_concepto_proyecto',
                'psc.secuencia',
                'cp.nombre as concepto',   
                'cp.indicativo_tipo_linea',                 
                'cp.indicativo_tipo_valor',                 
                'psc.valor_concepto_proyecto as valor',
            ])
            ->where('psc.id_simulacion', $idSimulacion)
            ->orderBy('psc.id_simulacion', 'asc')
            ->orderBy('psc.anio', 'asc')
            ->orderBy('psc.id_concepto_proyecto', 'asc')
            ->orderBy('psc.secuencia', 'asc')
            ->get();

        // 2) Lista de años (ascendente)
        $years = $items
            ->pluck('anio')
            ->unique()
            ->sort()
            ->values()
            ->all();

        // 3) Pivot por concepto
        $rowsMap = [];

        foreach ($items as $it) {
            $idConcepto = (int)$it->id_concepto_proyecto;
            $anio       = (int)$it->anio;
            $valor      = (float)($it->valor ?? 0);

            if (!isset($rowsMap[$idConcepto])) {
                $rowsMap[$idConcepto] = [
                    'id_concepto_proyecto' => $idConcepto,
                    'secuencia' => (int)($it->secuencia ?? 0),
                    'concepto' => (string)($it->concepto ?? ''),
                    'indicativo_tipo_linea' =>  (string)($it->indicativo_tipo_linea ?? ''),              
                    'indicativo_tipo_valor'  => (string)($it->indicativo_tipo_valor ?? ''), 
                    'valores_por_anio' => [],
                    'total' => 0.0,
                ];

                // Inicializar todos los años en 0
                foreach ($years as $y) {
                    $rowsMap[$idConcepto]['valores_por_anio'][(int)$y] = 0.0;
                }
            }

            // Acumular valor por año
            $rowsMap[$idConcepto]['valores_por_anio'][$anio] += $valor;
            $rowsMap[$idConcepto]['total'] += $valor;
        }

        // 4) Orden por secuencia asc
        $rows = array_values($rowsMap);

        usort($rows, fn ($a, $b) =>
            ((int)$a['secuencia'] <=> (int)$b['secuencia'])
        );

        // 5) Totales por año (opcional)
        $totalesPorAnio = [];
        foreach ($years as $y) $totalesPorAnio[(int)$y] = 0.0;

        foreach ($rows as $r) {
            foreach ($years as $y) {
                $totalesPorAnio[(int)$y] += (float)$r['valores_por_anio'][(int)$y];
            }
        }

        return [
            'id_simulacion' => $idSimulacion,
            'years' => array_values($years),         
            'rows'  => array_values($rows),           
            'totales_por_anio' => $totalesPorAnio,
        ];
    }

}
