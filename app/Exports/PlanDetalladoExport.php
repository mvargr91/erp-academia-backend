<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PlanDetalladoExport implements FromCollection, WithHeadings
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

    public function headings(): array
    {
        return [
            'Inversionista',
            'Inversión',
            'Proyecto',
            'Gestor',
            'Fecha vcmto.',
            'Tipo',
            'Valor',
            'Valor ret. fuente',
            'Porc. ret fuente',
            'Valor a pagar',
            'Fecha pago',
            'Estado',
            'Usuario última actualización',
            'Fecha última actualización',
        ];
    }
}

