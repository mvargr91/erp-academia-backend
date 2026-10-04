<?php

namespace App\Console\Commands\Academias;

use Exception;
use Illuminate\Console\Command;
use App\Models\Central\Academia;
use App\Support\Academias\GestorAcademias;

class MigrarAcademias extends Command
{
    protected $signature = 'academia:migrar {codigo? : Solo esta academia (por defecto todas las activas)}';

    protected $description = 'Aplica las migraciones pendientes en la base de datos de cada academia';

    public function handle(): int
    {
        $fallos = 0;
        GestorAcademias::paraCada(function (Academia $academia) use (&$fallos) {
            $this->info("== {$academia->codigo} ({$academia->base_datos})");
            try {
                $this->line(trim(GestorAcademias::migrar()));
            } catch (Exception $e) {
                $fallos++;
                $this->error($e->getMessage());
            }
        }, $this->argument('codigo'));

        return $fallos ? self::FAILURE : self::SUCCESS;
    }
}
