<?php

namespace App\Exports\Proyectos;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SimulacionConceptoProyectoExport implements FromArray, WithTitle, ShouldAutoSize, WithStyles
{
    protected int $simulacionId;
    protected array $conceptosIndentados = [];

    public function __construct(int $simulacionId)
    {
        $this->simulacionId = $simulacionId;
    }

    public function array(): array
    {
        $detalle = $this->obtenerDetalleConceptos();
        $totales = $this->obtenerTotalesConceptos();

        $anios = $this->obtenerAnios($detalle);

        if ($detalle->isEmpty()) {
            return [
                ['Datos proyectados de los conceptos'],
                [],
                ['Concepto', 'Sin información'],
            ];
        }

        $headers = ['Concepto'];

        foreach (array_values($anios) as $index => $anioReal) {
            $headers[] = 'Año(' . ($index + 1) . ')';
        }

        $headers[] = 'Totales';

        $mapDetalle = $this->mapearDetalle($detalle);
        $mapTotales = $this->mapearTotales($totales);

        $rows = [];
        $rows[] = ['Datos proyectados de los conceptos'];
        $rows[] = [];
        $rows[] = $headers;

        foreach ($mapDetalle as $concepto => $dataConcepto) {
            $row = [$concepto];

            foreach ($anios as $anio) {
                $row[] = $dataConcepto['anios'][$anio] ?? null;
            }

            $row[] = $mapTotales[$concepto] ?? null;

            $rows[] = $row;
        }

        $rows[] = [];

        return $rows;
    }

    public function title(): string
    {
        return 'Conceptos proyectados';
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1')->getFont()->setBold(true);

        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        if ($highestRow >= 3) {
            // $sheet->getStyle("A3:{$highestColumn}3")->getFont()->setBold(true);

            $ultimaFilaTabla = $highestRow;
            if (trim((string) $sheet->getCell("A{$highestRow}")->getValue()) === '') {
                $ultimaFilaTabla--;
            }

            if ($ultimaFilaTabla >= 3) {
                $sheet->getStyle("A3:{$highestColumn}{$ultimaFilaTabla}")
                    ->getBorders()
                    ->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN);

                for ($row = 3; $row <= $ultimaFilaTabla; $row++) {

                    $conceptoCelda = trim((string) $sheet->getCell("A{$row}")->getValue());
                    $tipo = $this->tiposConcepto[$conceptoCelda] ?? null;

                    $formato = '#,##0';

                    if ($tipo === 'R') {
                        $formato = '#,##0.00';
                    }

                    $sheet->getStyle("B{$row}:{$highestColumn}{$row}")
                        ->getNumberFormat()
                        ->setFormatCode($formato);

                    if (in_array($conceptoCelda, $this->conceptosIndentados)) {
                        $sheet->getStyle("A{$row}")
                            ->getAlignment()
                            ->setIndent(2);
                    }
                }
            }
        }

        return [];
    }

    private function obtenerDetalleConceptos()
    {
        return DB::table('proyectos_simulaciones_conceptos as psc')
            ->join('conceptos_proyectos as cp', 'cp.id', '=', 'psc.id_concepto_proyecto')
            ->where('psc.id_simulacion', $this->simulacionId)
            ->select(
                'cp.nombre as concepto',
                'cp.indicativo_tipo_valor',
                'cp.indicativo_tipo_linea',
                'psc.anio',
                'psc.secuencia',
                'psc.valor_concepto_proyecto'
            )
            ->orderBy('psc.secuencia')
            ->orderBy('psc.anio')
            ->get();
    }
    

    private function obtenerTotalesConceptos()
    {
        return DB::table('proyectos_simulaciones_conceptos as psc')
            ->join('conceptos_proyectos as cp', 'cp.id', '=', 'psc.id_concepto_proyecto')
            ->where('psc.id_simulacion', $this->simulacionId)
            ->whereIn('cp.indicativo_tipo_valor', ['V', 'C'])
            ->select(
                'cp.nombre as concepto',
                DB::raw('SUM(psc.valor_concepto_proyecto) as total')
            )
            ->groupBy('cp.nombre')
            ->get();
    }

    private function obtenerAnios($detalle): array
    {
        $anios = [];

        foreach ($detalle as $item) {
            if ($item->anio !== null) {
                $anios[] = (int) $item->anio;
            }
        }

        $anios = array_values(array_unique($anios));
        sort($anios);

        return $anios;
    }

    private function mapearDetalle($detalle): array
    {
        $map = [];

        foreach ($detalle as $item) {
            $concepto = trim((string) $item->concepto);
            $anio = (int) $item->anio;

            if (!isset($map[$concepto])) {
                $map[$concepto] = [
                    'anios' => [],
                ];

                $this->tiposConcepto[$concepto] = $item->indicativo_tipo_valor;
            }

            if ($item->indicativo_tipo_linea === 'D') {
                $this->conceptosIndentados[] = $concepto;
            }

            $map[$concepto]['anios'][$anio] = $item->valor_concepto_proyecto;
        }

        return $map;
    }

    private function mapearTotales($totales): array
    {
        $map = [];

        foreach ($totales as $item) {
            $map[trim((string) $item->concepto)] = $item->total;
        }

        return $map;
    }

    
}