<?php

namespace App\Console\Commands\Academias;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Support\Calendario\FestivosColombia;

class GenerarFestivos extends Command
{
    protected $signature = 'festivos:generar {anios?* : Años a generar (por defecto el actual y el siguiente)}';

    protected $description = 'Registra los festivos de Colombia (por ley) en la BD central; no toca los agregados a mano';

    public function handle(): int
    {
        $anios = $this->argument('anios') ?: [Carbon::today()->year, Carbon::today()->year + 1];
        foreach ($anios as $anio) {
            $nuevos = 0;
            foreach (FestivosColombia::delAnio((int) $anio) as $fecha => $nombre) {
                $nuevos += DB::connection('central')->table('festivos')->insertOrIgnore([
                    'fecha' => $fecha,
                    'nombre' => $nombre,
                    'origen' => 'ley',
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
            $this->info("{$anio}: {$nuevos} festivos nuevos.");
        }
        return self::SUCCESS;
    }
}
