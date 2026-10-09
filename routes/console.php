<?php

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Academias: ciclos de clases, recordatorios de pago y avisos de mora (todas las academias activas).
Schedule::command('notificaciones:enviar')->dailyAt('08:00')->withoutOverlapping();

// Festivos de Colombia del año siguiente (y repaso del actual), cada 1 de diciembre.
Schedule::command('festivos:generar')->yearlyOn(12, 1, '06:00');

// Cobro del ERP a las academias: cuentas de cobro, avisos y suspensión por mora.
Schedule::command('facturacion:academias')->dailyAt('07:00')->withoutOverlapping();

// Procesa la cola (correos de bienvenida) sin necesidad de un worker permanente:
// basta con el cron de schedule:run cada minuto.
Schedule::command('queue:work --stop-when-empty --tries=3')->everyMinute()->withoutOverlapping();
