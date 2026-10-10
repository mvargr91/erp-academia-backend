<?php

namespace App\Console\Commands\Academias;

use Exception;
use Illuminate\Console\Command;
use App\Models\Central\Academia;
use Illuminate\Support\Facades\Artisan;
use App\Support\Academias\GestorAcademias;
use Database\Seeders\AcademiaMenuSeeder;
use Database\Seeders\ConfiguracionAcademiaSeeder;
use Database\Seeders\AdministracionAcademiasMenuSeeder;

class ActualizarAcademias extends Command
{
    protected $signature = 'academia:actualizar {codigo? : Solo esta academia (por defecto todas las activas)}';

    protected $description = 'Deja cada academia al día tras un despliegue: migraciones, menú y permisos, y parámetros';

    public function handle(): int
    {
        $fallos = 0;
        GestorAcademias::paraCada(function (Academia $academia) use (&$fallos) {
            $this->info("== {$academia->codigo} ({$academia->base_datos})");
            try {
                $this->line(trim(GestorAcademias::migrar()));

                // Los seeders son idempotentes: solo agregan lo que falte.
                $seeders = [AcademiaMenuSeeder::class, ConfiguracionAcademiaSeeder::class];
                if ($academia->es_administradora) {
                    $seeders[] = AdministracionAcademiasMenuSeeder::class;
                }
                foreach ($seeders as $seeder) {
                    Artisan::call('db:seed', ['--class' => $seeder, '--force' => true]);
                    $this->line('   ' . class_basename($seeder) . ': listo');
                }
            } catch (Exception $e) {
                $fallos++;
                $this->error($e->getMessage());
            }
        }, $this->argument('codigo'));

        return $fallos ? self::FAILURE : self::SUCCESS;
    }
}
