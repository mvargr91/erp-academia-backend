<?php

namespace App\Console\Commands\Academias;

use Illuminate\Console\Command;
use App\Support\Academias\HostsLocales;

class HostsAcademias extends Command
{
    protected $signature = 'academia:hosts';

    protected $description = 'Desarrollo local: agrega <codigo>.<dominio> de cada academia al archivo hosts';

    public function handle(): int
    {
        $lineas = HostsLocales::lineas();

        if (@HostsLocales::escribir()) {
            $this->info('Archivo hosts actualizado (' . HostsLocales::ruta() . '):');
            $this->line(implode(PHP_EOL, $lineas));
            return self::SUCCESS;
        }

        $this->warn('Sin permisos para escribir ' . HostsLocales::ruta() . '.');
        $this->line('Ejecuta este comando desde una terminal abierta "como administrador", o agrega a mano:');
        $this->newLine();
        $this->line(implode(PHP_EOL, $lineas));
        return self::FAILURE;
    }
}
