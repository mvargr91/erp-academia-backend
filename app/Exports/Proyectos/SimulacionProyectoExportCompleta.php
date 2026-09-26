<?php

namespace App\Exports\Proyectos;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SimulacionProyectoExportCompleta implements WithMultipleSheets
{
    protected $dto;

    public function __construct($dto)
    {
        $this->dto = $dto;
    }

    public function sheets(): array
    {
        return [
            new SimulacionProyectoExport($this->dto), // hoja 1
            new SimulacionConceptoProyectoExport($this->dto), // hoja 2
        ];
    }
}
