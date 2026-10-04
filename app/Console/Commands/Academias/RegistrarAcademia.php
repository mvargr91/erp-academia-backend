<?php

namespace App\Console\Commands\Academias;

use Illuminate\Console\Command;
use App\Models\Central\Academia;
use Illuminate\Support\Facades\DB;

/**
 * Registra en la BD central una academia cuya base de datos ya existe
 * (p. ej. la BD actual erp_academia), sin crearla ni sembrarla.
 */
class RegistrarAcademia extends Command
{
    protected $signature = 'academia:registrar
        {codigo : Subdominio de la academia}
        {nombre : Nombre comercial}
        {base_datos : Nombre de la base de datos existente}
        {--correo= : Correo de contacto}
        {--dia-pago=5 : Día del mes en que vence la mensualidad (1-28)}
        {--administradora : Marca la academia desde la que se gestionan las demás}';

    protected $description = 'Registra una academia con una base de datos ya existente';

    public function handle(): int
    {
        $baseDatos = $this->argument('base_datos');
        $existe = DB::connection('central')->selectOne(
            'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$baseDatos]
        );
        if (!$existe) {
            $this->error("La base de datos {$baseDatos} no existe.");
            return self::FAILURE;
        }

        $academia = Academia::updateOrCreate(
            ['codigo' => strtolower($this->argument('codigo'))],
            [
                'nombre' => $this->argument('nombre'),
                'base_datos' => $baseDatos,
                'correo' => $this->option('correo'),
                'dia_pago' => (int) $this->option('dia-pago'),
                'activa' => true,
                'es_administradora' => (bool) $this->option('administradora'),
            ]
        );

        $this->info("Academia {$academia->codigo} registrada sobre {$academia->base_datos}.");
        return self::SUCCESS;
    }
}
