<?php

namespace App\Console\Commands\Academias;

use Exception;
use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Models\Central\Academia;
use App\Support\Academias\GestorAcademias;
use App\Services\Academia\NotificadorAcademia;

class EnviarNotificacionesAcademias extends Command
{
    protected $signature = 'notificaciones:enviar
        {--academia= : Solo esta academia (código)}
        {--fecha= : Simula que hoy es esta fecha (Y-m-d)}
        {--simular : Solo cuenta lo que haría; no cobra ni envía correos}';

    protected $description = 'Causa mensualidades y envía recordatorios de pago y avisos de mora de cada academia';

    public function handle(): int
    {
        $hoy = $this->option('fecha') ? Carbon::parse($this->option('fecha')) : Carbon::today();
        $simular = (bool) $this->option('simular');
        $filas = [];

        GestorAcademias::paraCada(function (Academia $academia) use ($hoy, $simular, &$filas) {
            try {
                $r = (new NotificadorAcademia($academia, $hoy, $simular))->ejecutar();
                $filas[] = [$academia->codigo, $r['cargos'], $r['recordatorio'], $r['mora'], $r['paquetes'] ?? 0, $r['otros'] ?? 0, $r['errores']];
            } catch (Exception $e) {
                $filas[] = [$academia->codigo, '-', '-', '-', '-', '-', $e->getMessage()];
            }
        }, $this->option('academia'));

        $this->info(($simular ? '[SIMULACIÓN] ' : '') . 'Fecha: ' . $hoy->toDateString());
        $this->table(['Academia', 'Ciclos causados', 'Recordatorios', 'Mora', 'Avisos paquete', 'Clases/cumpleaños', 'Errores'], $filas);
        return self::SUCCESS;
    }
}
