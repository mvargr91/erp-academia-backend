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

class InversionistasExport implements FromQuery, WithHeadings, ShouldAutoSize, WithStyles, WithStrictNullComparison
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


    $query = DB::table('inversionistas')
      ->join('ciudades', 'ciudades.id', 'inversionistas.ciudad_id')
      ->join('tipos_inversion', 'tipos_inversion.id', 'inversionistas.id_tipo_inversion')
      ->leftJoin('bancos', 'bancos.id', 'inversionistas.id_banco')
          ->select(        
          'inversionistas.nombre',
          DB::raw("CASE inversionistas.tipo_documento
            WHEN 'CC' THEN 'Cedula ciudadania'
            WHEN 'CE' THEN 'Cedula extranjeria'
            WHEN 'NIT' THEN 'Nit'
            ELSE '' END AS tipo_documento
          "),
          'inversionistas.numero_documento',
          'inversionistas.direccion',
          'ciudades.nombre as ciudad',
          'inversionistas.telefono',
          'inversionistas.email',
          DB::raw("CASE inversionistas.indicativo_socio
            WHEN 'S' THEN 'SI'
            WHEN 'N' THEN 'NO'
            ELSE '' END AS indicativo_socio
          "),
          'inversionistas.porcentaje_ret_fuente_rendimientos',
          'tipos_inversion.nombre as tipo_inversion',
          'bancos.nombre as banco',
          DB::raw("CASE inversionistas.tipo_cuenta
            WHEN 'A' THEN 'Ahorros'
            WHEN 'C' THEN 'Corriente'
            ELSE '' END AS tipo_cuenta
          "),
          'inversionistas.numero_cuenta',
          'inversionistas.observaciones',
          DB::raw("CASE inversionistas.estado
            WHEN 1 THEN 'Activo'
            WHEN 0 THEN 'Inactivo'
            ELSE '' END AS estado
          ")
      );     

    $query->orderBy('inversionistas.nombre', 'desc');

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
      "nombre", 
      "tipo_documento", 
      "numero_documento", 
      "direccion", 
      "ciudad", 
      "telefono", 
      "email", 
      "indicativo_socio", 
      'porcentaje ret. fuente',
      "tipo_inversion", 
      "banco", 
      "tipo_cuenta", 
      "numero_cuenta",
      "observaciones", 
      "estado",    
    ];
  } 
}
