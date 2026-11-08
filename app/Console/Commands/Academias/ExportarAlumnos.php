<?php

namespace App\Console\Commands\Academias;

use Exception;
use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Models\Central\Academia;
use App\Support\Academias\GestorAcademias;
use App\Services\Academia\ExportacionAlumnos;

/** Excel de validación de alumnos por curso y deuda (ver ExportacionAlumnos). Solo lee. */
class ExportarAlumnos extends Command
{
    protected $signature = 'academia:exportar-alumnos
        {codigo : Código de la academia}
        {--archivo= : Ruta del Excel (por defecto storage/app/exportes/alumnos-<codigo>-<fecha>.xlsx)}';

    protected $description = 'Exporta a Excel los alumnos de una academia por curso, con lo que debe cada uno';

    public function handle(): int
    {
        $codigo = $this->argument('codigo');
        if (!Academia::where('codigo', $codigo)->where('activa', true)->exists()) {
            $this->error("No hay una academia activa con el código {$codigo}.");
            return self::FAILURE;
        }

        $archivo = $this->option('archivo')
            ?: storage_path('app/exportes/alumnos-' . $codigo . '-' . Carbon::now()->format('Y-m-d_His') . '.xlsx');
        if (!is_dir(dirname($archivo))) {
            mkdir(dirname($archivo), 0775, true);
        }

        $estado = self::SUCCESS;
        GestorAcademias::paraCada(function (Academia $academia) use ($archivo, &$estado) {
            try {
                $r = (new ExportacionAlumnos())->generar($archivo, "Alumnos {$academia->nombre}");
                $pesos = fn ($v) => number_format($v, 0, ',', '.');
                $this->info("{$academia->nombre}: Excel generado en {$archivo}");
                $this->table(['Concepto', 'Valor'], [
                    ['Matrículas activas', $r['matriculas']],
                    ['Alumnos con curso', $r['alumnos_con_curso']],
                    ['Deuda en cursos', $pesos($r['deuda_cursos'])],
                    ['Deuda en paquetes', $pesos($r['deuda_paquetes'])],
                    ['Deuda en vacacionales', $pesos($r['deuda_vacacionales'])],
                ]);
            } catch (Exception $e) {
                $estado = self::FAILURE;
                $this->error($e->getMessage());
            }
        }, $codigo);

        return $estado;
    }
}
