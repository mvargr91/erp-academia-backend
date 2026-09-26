<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProgramacionPagoExport implements FromCollection, WithHeadings, ShouldAutoSize, WithMapping, WithStyles
{
    protected $datos;

    public function __construct($datos)
    {
        $this->datos = $datos;
    }

    public function collection()
    {
        return collect($this->datos);
    }

    public function map($row): array
    {
        if ($row->tipo_concepto === 'Total') {
            return [
                '', '', $row->nombre_inversionista, 'Total',
                '$ ' . $row->valor_concepto,
                '$ ' . $row->valor_ret_fuente,
                '', // No mostrar porcentaje
                '$ ' . $row->valor_a_pagar,
                '$ ' . $row->total_inversionista,
                $row->banco,
                $row->tipo_cuenta,
                $row->numero_cuenta,
            ];
        }

        // Fila normal: aplicar formato en miles con 3 decimales y símbolo $
        return [
            $row->fecha_vencimiento,
            $row->codigo_proyecto,
            $row->nombre_inversionista,
            $row->tipo_concepto,
            '$ ' . number_format((float)$row->valor_concepto, 0, ',', '.'),
            '$ ' . number_format((float)$row->valor_ret_fuente, 0, ',', '.'),
            $row->porcentaje_ret_fuente . '%',
            '$ ' . number_format((float)$row->valor_a_pagar, 0, ',', '.'),
            '', // Total solo en fila TOTAL
            '',
            '',
            ''
        ];
    }

    public function headings(): array
    {
        return [
            'Fecha vencimiento',
            'Código proyecto',
            'Inversionista/Gestor',
            'Tipo concepto',
            'Valor concepto',
            'Retención en la fuente',
            'Porcentaje Retención',
            'Valor a pagar',
            'Total Inversionista',
            'Banco',
            'Tipo cuenta',
            'Número cuenta',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Estilo para encabezado
        $sheet->getStyle('A1:L1')->getFont()->setBold(true);

        // Resaltar filas con "TOTAL"
        $highestRow = $sheet->getHighestRow();
        for ($i = 2; $i <= $highestRow; $i++) {
            if (strtoupper((string)$sheet->getCell("D{$i}")->getValue()) === 'TOTAL') {
                $sheet->getStyle("A{$i}:L{$i}")->getFont()->setBold(true);
                $sheet->getStyle("A{$i}:L{$i}")->getFill()
                    ->setFillType('solid')
                    ->getStartColor()->setARGB('FFEFEFEF');
            }
        }
    }
}
