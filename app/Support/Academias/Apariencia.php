<?php

namespace App\Support\Academias;

use App\Models\Central\Academia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Apariencia del ERP de una academia: colores, logos, pantalla de ingreso y modo por defecto.
 * En `academias.apariencia` solo se guarda lo que la academia cambió; lo demás sale de DEFECTO.
 * Las imágenes van al disco público en apariencia/<codigo>/ y se sirven por /marca/<codigo>/<archivo>
 * (ruta web, no depende de `storage:link`).
 */
class Apariencia
{
    public const DEFECTO = [
        'color_primario' => '#00A1CC',
        'color_menu' => '#1C4A59',
        'color_acento' => '#00A1CC',
        'modo' => 'claro',
        'login_titulo' => '',
        'login_subtitulo' => '',
        'login_posicion' => 'izquierda',
    ];

    public const MODOS = ['claro', 'oscuro'];

    public const POSICIONES = ['izquierda', 'centro', 'derecha'];

    /** Imágenes configurables: campo => prefijo del archivo. */
    public const IMAGENES = ['logo' => 'logo', 'logo_oscuro' => 'logo-oscuro', 'login_fondo' => 'login'];

    /** Apariencia completa (con valores por defecto y URLs de las imágenes) para el frontend. */
    public static function de(Academia $academia): array
    {
        $guardada = $academia->apariencia ?? [];
        $datos = ['nombre' => $academia->nombre];
        foreach (self::DEFECTO as $campo => $defecto) {
            $datos[$campo] = $guardada[$campo] ?? $defecto;
        }
        foreach (array_keys(self::IMAGENES) as $campo) {
            $archivo = $guardada[$campo] ?? null;
            $datos[$campo . '_url'] = $archivo ? self::url($academia, $archivo) : null;
        }
        return $datos;
    }

    public static function url(Academia $academia, string $archivo): string
    {
        return rtrim(config('app.url'), '/') . '/marca/' . $academia->codigo . '/' . $archivo;
    }

    public static function carpeta(string $codigo): string
    {
        return 'apariencia/' . $codigo;
    }

    /** Guarda la imagen con nombre único (la URL cambia, así el navegador no muestra la anterior). */
    public static function guardarImagen(Academia $academia, string $campo, UploadedFile $archivo): string
    {
        $nombre = self::IMAGENES[$campo] . '-' . Str::lower(Str::random(10)) . '.' . $archivo->extension();
        $archivo->storeAs(self::carpeta($academia->codigo), $nombre, 'public');
        return $nombre;
    }

    public static function borrarImagen(Academia $academia, ?string $archivo): void
    {
        if ($archivo) {
            Storage::disk('public')->delete(self::carpeta($academia->codigo) . '/' . $archivo);
        }
    }
}
