<?php

namespace App\Support\Academias;

use App\Models\Central\Academia;

/**
 * Solo desarrollo local: mantiene en el archivo hosts de Windows un bloque con
 * <codigo>.<dominio> -> 127.0.0.1 por cada academia (hosts no admite comodines).
 * Escribir el archivo requiere permisos de administrador.
 */
class HostsLocales
{
    private const INICIO = '# >>> ERP Academias (php artisan academia:hosts) >>>';
    private const FIN = '# <<< ERP Academias <<<';

    public static function ruta(): string
    {
        return PHP_OS_FAMILY === 'Windows'
            ? (getenv('SystemRoot') ?: 'C:\\Windows') . '\\System32\\drivers\\etc\\hosts'
            : '/etc/hosts';
    }

    /** @return string[] */
    public static function lineas(): array
    {
        $dominio = config('academias.dominio');
        return Academia::orderBy('codigo')->pluck('codigo')
            ->map(fn ($codigo) => "127.0.0.1\t{$codigo}.{$dominio}")
            ->all();
    }

    /** Reescribe el bloque de academias. Devuelve false si no hay permisos de escritura. */
    public static function escribir(): bool
    {
        $ruta = self::ruta();
        if (!is_writable($ruta)) {
            return false;
        }
        $contenido = file_get_contents($ruta);
        $bloque = self::INICIO . PHP_EOL . implode(PHP_EOL, self::lineas()) . PHP_EOL . self::FIN;

        $patron = '/' . preg_quote(self::INICIO, '/') . '.*?' . preg_quote(self::FIN, '/') . '/s';
        $nuevo = preg_match($patron, $contenido)
            ? preg_replace($patron, $bloque, $contenido)
            : rtrim($contenido) . PHP_EOL . PHP_EOL . $bloque . PHP_EOL;

        return file_put_contents($ruta, $nuevo) !== false;
    }

    /** Intento silencioso tras crear una academia (solo en entorno local). */
    public static function intentarActualizar(): void
    {
        if (app()->environment('local')) {
            try {
                self::escribir();
            } catch (\Throwable $e) {
                // Sin permisos: se agrega a mano con php artisan academia:hosts.
            }
        }
    }
}
