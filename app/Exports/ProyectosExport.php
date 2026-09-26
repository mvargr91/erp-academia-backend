<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

class ProyectosExport implements FromQuery, WithHeadings, ShouldAutoSize, WithStyles, WithStrictNullComparison
{
  /**
   * @return \Illuminate\Support\Collection
   */
  use Exportable;

  public function __construct($dto){
    $this->dto = $dto;
    $this->rows = [];
  }

  public function query(){

    $query = DB::table('proyectos')
        ->join('ciudades', 'ciudades.id', 'proyectos.ciudad_id')
        ->join('tipos_proyectos', 'tipos_proyectos.id', 'proyectos.id_tipo_proyecto')
        ->join('sectores_proyectos', 'sectores_proyectos.id', 'proyectos.id_sector_proyecto')
        ->join('tipos_inversion', 'tipos_inversion.id', 'proyectos.id_tipo_inversion')
            ->select(
            'proyectos.id',            
            'proyectos.nombre',
            'proyectos.codigo_proyecto',
            'ciudades.nombre as ciudad', 
            'proyectos.fecha_inicio_proyecto',
            'tipos_proyectos.nombre as tipo_proyecto',
            'sectores_proyectos.nombre as sector_proyecto',
            'proyectos.potencia',
            'tipos_inversion.nombre as tipo_inversion',
            'proyectos.valor_total_proyecto',
            'proyectos.valor_inversion_proyecto',
            'proyectos.observaciones',
            DB::raw("CASE proyectos.estado_proyecto
              WHEN 1 THEN 'Activo'
              WHEN 0 THEN 'Inactivo'
              ELSE '' END AS estado
            "),
        );  

    $query->orderBy('proyectos.nombre', 'desc');

    $this->rows = clone $query->get();
    return $query;
  }

  public function styles(Worksheet $sheet){
    $sheet->getStyle('A1:P1')->getFont()->setBold(true);
    $i = 2;
    // foreach($this->rows as $row){
    //   $sheet->getCell('P'.$i)->setValue('Ver Ubicación')->getHyperlink()->setUrl($row->ubicacion);
    //   $i++;
    // }
  }

  public function headings(): array{
    return [
      "id",
      "nombre", 
      "codigo_proyecto", 
      "ciudad", 
      "fecha_inicio_proyecto", 
      "tipo_proyecto", 
      "sector_proyecto", 
      "potencia", 
      "tipo_inversion", 
      "valor_total_proyecto", 
      "valor_inversion_proyecto", 
      "observaciones",
      "estado",    
    ];
  } 
}
