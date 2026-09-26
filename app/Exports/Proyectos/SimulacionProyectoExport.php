<?php

namespace App\Exports\Proyectos;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SimulacionProyectoExport implements FromArray, WithTitle, ShouldAutoSize, WithStyles
{
    protected int $simulacionId;

    public function __construct(int $simulacionId)
    {
        $this->simulacionId = $simulacionId;
    }

    public function array(): array
    {
        $sim = DB::table('proyectos_simulaciones')
            ->join('proyectos', 'proyectos.id', '=', 'proyectos_simulaciones.id_proyecto')
            ->where('proyectos_simulaciones.id', $this->simulacionId)
            ->select(
                'proyectos_simulaciones.id',
                'proyectos_simulaciones.id_proyecto',
                'proyectos_simulaciones.created_at',
                'proyectos_simulaciones.indicativo_modelo_ccial',
                'proyectos_simulaciones.indicativo_tipo_simulacion',
                'proyectos_simulaciones.nombre_inversionista',
                'proyectos_simulaciones.telefono_inversionista',
                'proyectos_simulaciones.email_inversionista',
                'proyectos_simulaciones.porcentaje_part_inversionista',
                'proyectos_simulaciones.porcentaje_perdida_efic',
                'proyectos_simulaciones.porcentaje_IPC',
                'proyectos_simulaciones.anios_depreciacion',
                'proyectos_simulaciones.porcentaje_tasa_oportunidad',
                'proyectos_simulaciones.Valor_VPN_proyecto',
                'proyectos_simulaciones.porcentaje_TIR_proyecto',
                'proyectos_simulaciones.anios_PBT',
                'proyectos.nombre as proyecto_nombre',
                'proyectos.codigo_proyecto',
                'proyectos.potencia',
                'proyectos.valor_total_proyecto'
            )
            ->first();

        if (!$sim) {
            return [
                ['Error', 'No se encontró la simulación'],
            ];
        }

        $rows = [
            ['Fecha simulación', $this->formatearFecha($sim->created_at)],
            ['Proyecto', $sim->proyecto_nombre],
            ['Código', $sim->codigo_proyecto],
            ['Potencia del sistema kWhp', $sim->potencia],
            ['Valor total proyecto', $sim->valor_total_proyecto],
        ];

        if (($sim->indicativo_tipo_simulacion ?? null) === 'I') {
            $valorParticipacion = null;

            if (
                $sim->valor_total_proyecto !== null &&
                $sim->porcentaje_part_inversionista !== null
            ) {
                $valorParticipacion =
                    (float) $sim->valor_total_proyecto *
                    ((float) $sim->porcentaje_part_inversionista / 100);
            }

            $rows[] = ['Inversionista', $sim->nombre_inversionista];
            $rows[] = ['Telefono inversionista', $sim->telefono_inversionista];
            $rows[] = ['Correo electronico inversionista', $sim->email_inversionista];
            $rows[] = ['Porc. Participacion inversionista', $sim->porcentaje_part_inversionista];
            $rows[] = ['Valor Participacion inversionista', $valorParticipacion];
        }

        $rows[] = ['Modelo comercializacion', $this->descripcionModeloCcial($sim->indicativo_modelo_ccial)];
        $rows[] = ['Tipo simulacion', $this->descripcionTipoSimulacion($sim->indicativo_tipo_simulacion)];
        $rows[] = ['Porc. Perdida eficiencia', $sim->porcentaje_perdida_efic];
        $rows[] = ['Porcentaje IPC', $sim->porcentaje_IPC];
        $rows[] = ['Años depreciacion', $sim->anios_depreciacion];
        $rows[] = ['Tasa oportunidad', $sim->porcentaje_tasa_oportunidad];
        $rows[] = ['Valor VPN', $sim->Valor_VPN_proyecto];
        $rows[] = ['Porcentaje TIR', $sim->porcentaje_TIR_proyecto];
        $rows[] = ['Años PBT', $sim->anios_PBT];

        return $rows;
    }

    public function title(): string
    {
        return 'Datos generales';
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:B1')->getFont()->setBold(true);
        $sheet->getStyle('A:A')->getFont()->setBold(true);

        $highestRow = $sheet->getHighestRow();

        for ($row = 2; $row <= $highestRow; $row++) {
            $label = trim((string) $sheet->getCell("A{$row}")->getValue());

            if (in_array($label, [
                'Valor total proyecto',
                'Valor Participacion inversionista',
                'Valor VPN',
            ])) {
                $sheet->getStyle("B{$row}")
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');
            }

            if (in_array($label, [
                'Porc. Participacion inversionista',
                'Porc. Perdida eficiencia',
                'Porcentaje IPC',
                'Tasa oportunidad',
                'Porcentaje TIR',
            ])) {
                $sheet->getStyle("B{$row}")
                    ->getNumberFormat()
                    ->setFormatCode('0.00');
            }
        }

        return [];
    }

    private function formatearFecha($fecha): ?string
    {
        if (!$fecha) {
            return null;
        }

        try {
            return Carbon::parse($fecha)->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return (string) $fecha;
        }
    }

    private function descripcionModeloCcial(?string $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $map = [
            'CE' => 'Comunidad energetica',
            'BO' => 'Bolsa',
        ];

        return $map[$valor] ?? $valor;
    }

    private function descripcionTipoSimulacion(?string $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $map = [
            'I' => 'Inversionista',
            'P' => 'Proyecto',
        ];

        return $map[$valor] ?? $valor;
    }
}