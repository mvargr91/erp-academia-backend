<?php

namespace App\Console\Commands\Academias;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Services\Central\FacturacionAcademias;

class FacturarAcademias extends Command
{
    protected $signature = 'facturacion:academias
        {--academia= : Solo esta academia (código)}
        {--fecha= : Simula que hoy es esta fecha (Y-m-d)}
        {--simular : Solo cuenta lo que haría; no factura, no envía correos ni suspende}';

    protected $description = 'Genera las cuentas de cobro del ERP a las academias, envía avisos y suspende por mora';

    public function handle(): int
    {
        $hoy = $this->option('fecha') ? Carbon::parse($this->option('fecha')) : Carbon::today();
        $simular = (bool) $this->option('simular');

        $filas = (new FacturacionAcademias($hoy, $simular))->ejecutar($this->option('academia'));

        $this->info(($simular ? '[SIMULACIÓN] ' : '') . 'Fecha: ' . $hoy->toDateString());
        $this->table(['Academia', 'Cuentas de cobro', 'Avisos', 'Suspendida', 'Errores'], $filas);
        return self::SUCCESS;
    }
}
