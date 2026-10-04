<?php

namespace App\Console\Commands\Academias;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use App\Support\Academias\GestorAcademias;

class CrearAcademia extends Command
{
    protected $signature = 'academia:crear
        {codigo : Subdominio de la academia (minúsculas, números y guiones)}
        {nombre : Nombre comercial}
        {--correo= : Correo de contacto (reply-to de las notificaciones)}
        {--telefono= : Teléfono de contacto}
        {--dia-pago=5 : Día del mes en que vence la mensualidad de sus alumnos (1-28)}
        {--tarifa= : Tarifa mensual del ERP para esta academia (por defecto ERP_TARIFA_MENSUAL)}
        {--inicio-cobro= : Fecha desde la que se le cobra (Y-m-d); sirve de periodo de prueba}
        {--usuario=00000000 : Usuario del administrador de la academia}
        {--clave= : Clave del administrador (si se omite se genera una)}';

    protected $description = 'Crea una academia nueva con su propia base de datos';

    public function handle(): int
    {
        $codigo = strtolower($this->argument('codigo'));
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,38}$/', $codigo)) {
            $this->error('Código inválido: usa minúsculas, números y guiones (2 a 39 caracteres).');
            return self::FAILURE;
        }
        $diaPago = (int) $this->option('dia-pago');
        if ($diaPago < 1 || $diaPago > 28) {
            $this->error('El día de pago debe estar entre 1 y 28.');
            return self::FAILURE;
        }

        $clave = $this->option('clave') ?: Str::password(12, symbols: false);

        $this->info("Creando la academia {$codigo}...");
        try {
            $academia = GestorAcademias::crear([
                'codigo' => $codigo,
                'nombre' => $this->argument('nombre'),
                'correo' => $this->option('correo'),
                'telefono' => $this->option('telefono'),
                'dia_pago' => $diaPago,
                'tarifa_mensual' => $this->option('tarifa'),
                'fecha_inicio_cobro' => $this->option('inicio-cobro'),
            ], $this->option('usuario'), $clave);
        } catch (Exception $e) {
            $this->error('No se pudo crear la academia: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->table(['Dato', 'Valor'], [
            ['Academia', $academia->nombre],
            ['URL', $academia->url()],
            ['Base de datos', $academia->base_datos],
            ['Usuario admin', $this->option('usuario')],
            ['Clave admin', $clave],
        ]);
        $this->warn('Guarda la clave: no se vuelve a mostrar.');
        return self::SUCCESS;
    }
}
