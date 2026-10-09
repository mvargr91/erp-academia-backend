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

    /**
     * Marca de la academia para sus correos: los mismos colores y logo de Configuración → Apariencia.
     *
     * La cabecera del correo usa el color del menú con el logo para fondos oscuros; si la academia solo
     * subió el logo normal (pensado para fondo claro), la cabecera va en blanco con una franja del color
     * primario; sin logo, muestra el nombre sobre el color del menú.
     *
     * `logo_ruta` es el archivo en disco (para incrustarlo en el correo y que se vea aunque el cliente
     * de correo no pueda descargar imágenes del servidor); `logo_url`, su dirección pública.
     */
    public static function paraCorreo(?Academia $academia = null): array
    {
        $academia ??= GestorAcademias::actual();
        $guardada = $academia?->apariencia ?? [];
        $color = fn (string $campo) => $guardada[$campo] ?? self::DEFECTO[$campo];

        $campoLogo = !empty($guardada['logo_oscuro']) ? 'logo_oscuro' : (!empty($guardada['logo']) ? 'logo' : null);
        $ruta = $campoLogo ? Storage::disk('public')->path(self::carpeta($academia->codigo) . '/' . $guardada[$campoLogo]) : null;
        $hayLogo = $ruta && is_file($ruta);
        $cabeceraClara = $hayLogo && $campoLogo === 'logo';

        return [
            'color_primario' => $color('color_primario'),
            'color_acento' => $color('color_acento'),
            'color_cabecera' => $cabeceraClara ? '#FFFFFF' : $color('color_menu'),
            'color_texto_cabecera' => $cabeceraClara ? $color('color_menu') : '#FFFFFF',
            'cabecera_clara' => $cabeceraClara,
            'logo_ruta' => $hayLogo ? $ruta : null,
            'logo_url' => $hayLogo ? self::url($academia, $guardada[$campoLogo]) : null,
        ];
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
