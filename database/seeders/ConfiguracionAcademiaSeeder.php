<?php

namespace Database\Seeders;

use Exception;
use Illuminate\Database\Seeder;
use App\Models\Central\Academia;
use App\Support\Configuracion\Configuracion;

/**
 * Parámetros del sistema y plantillas de correo por defecto en la academia activa.
 * Idempotente: solo crea los códigos que falten, sin tocar valores/textos ya editados.
 *   php artisan db:seed --class=ConfiguracionAcademiaSeeder
 */
class ConfiguracionAcademiaSeeder extends Seeder
{
    public function run()
    {
        $baseDatos = config('database.connections.mysql.database');
        try {
            $esAdministradora = (bool) Academia::where('base_datos', $baseDatos)->value('es_administradora');
        } catch (Exception $e) {
            $esAdministradora = false;
        }
        $creados = Configuracion::sembrar($esAdministradora);
        $this->command?->info("{$baseDatos}: {$creados['parametros']} parámetros y {$creados['plantillas']} plantillas nuevas.");
    }
}
