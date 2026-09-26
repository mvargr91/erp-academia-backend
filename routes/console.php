<?php

use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('extractos:enviar', function () {
    $this->call(\App\Console\Commands\EnviarExtractosInversionistas::class);
})->describe('Genera y envía extractos mensuales a los inversionistas');

// Programación del comando cada 1ro del mes a la 1:00 AM
Schedule::command('extractos:enviar')->monthlyOn(1, '01:00');
