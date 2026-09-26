<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Almacenamiento de archivos subidos (imágenes, videos y documentos).
 *
 * Cada archivo se guarda en una carpeta que dice a qué registro pertenece:
 *   destinos/{id}-{slug}/imagen/
 *   categorias/{id}-{slug}/imagen/
 *   experiencias/{id}-{slug}/fotos/   · videos/   · resenas/{resena_id}/
 *   proveedores/{id}-{slug}/documentos/{tipo_documento}/   (disco privado)
 * y el nombre lleva fecha, el nombre original normalizado y un sufijo aleatorio:
 *   20260925-101530-atardecer-en-guatape-k3x9p.jpg
 *
 * En BD se guarda la ruta relativa al disco. Las rutas que empiezan por http(s) son URLs externas
 * (ej. un video de YouTube) y nunca se tocan en disco.
 *
 * Transacciones: los archivos anteriores se borran solo al confirmar (confirmar()) y los nuevos
 * se borran si la operación falla (revertir()), para que disco y BD no queden desalineados.
 */
class ArchivosService
{
    public const PUBLICO = 'public';   // storage/app/public, servido en /storage
    public const PRIVADO = 'local';    // storage/app/private, solo por endpoints con sesión
    private const RAIZ_PRIVADA = 'private';

    public const REGLA_IMAGEN = 'file|mimes:jpg,jpeg,png,webp|max:5120';           // 5 MB
    public const REGLA_VIDEO = 'file|mimes:mp4,webm|max:51200';                    // 50 MB
    public const REGLA_DOCUMENTO = 'file|mimes:pdf,jpg,jpeg,png|max:10240';        // 10 MB

    /** Archivos guardados en esta petición: [disco, ruta] (se borran si se revierte). */
    private static array $nuevos = [];
    /** Archivos a borrar cuando se confirme la transacción: [disco, ruta]. */
    private static array $porEliminar = [];

    /** Carpeta de un registro: "{entidad}/{id}-{slug}/{subcarpeta}". */
    public static function carpeta(string $entidad, $id, ?string $nombre = null, ?string $subcarpeta = null): string
    {
        $registro = $nombre ? $id . '-' . Str::limit(Str::slug($nombre), 60, '') : (string) $id;
        return trim(implode('/', array_filter([$entidad, $registro, $subcarpeta])), '/');
    }

    public static function esLocal(?string $ruta): bool
    {
        return !empty($ruta) && !preg_match('#^https?://#i', $ruta);
    }

    /** Guarda el archivo en la carpeta y devuelve la ruta relativa. */
    public static function guardar(UploadedFile $archivo, string $carpeta, string $disco = self::PUBLICO): string
    {
        $original = Str::limit(Str::slug(pathinfo($archivo->getClientOriginalName(), PATHINFO_FILENAME)), 60, '') ?: 'archivo';
        $extension = strtolower($archivo->getClientOriginalExtension() ?: $archivo->extension());
        $nombre = now()->format('Ymd-His') . "-{$original}-" . Str::lower(Str::random(5)) . ".{$extension}";

        $ruta = $archivo->storeAs(self::enDisco($carpeta, $disco), $nombre, $disco);
        if (!$ruta) {
            throw new \Exception('No se pudo guardar el archivo en el servidor.');
        }
        $relativa = "{$carpeta}/{$nombre}";
        self::$nuevos[] = [$disco, $relativa];

        return $relativa;
    }

    /** Valor actual de la columna, para tomarlo antes de modificar el registro (null si es nuevo). */
    public static function rutaActual(string $tabla, $id, string $columna): ?string
    {
        return $id ? DB::table($tabla)->where('id', $id)->value($columna) : null;
    }

    /**
     * Llamar después de guardar el registro, con la ruta que tenía antes ($anterior, de rutaActual()).
     * - Si la petición trae el archivo: lo guarda en $carpeta y actualiza la columna.
     * - Si la ruta cambió (archivo nuevo o se reemplazó por una URL): el archivo anterior se borra al confirmar.
     * Devuelve la ruta nueva si se subió un archivo, o null.
     */
    public static function adjuntar(Request $request, string $tabla, int $id, string $columna, string $carpeta,
                                    ?string $anterior, string $campo = 'archivo', string $disco = self::PUBLICO): ?string
    {
        $ruta = null;
        if ($request->hasFile($campo)) {
            $ruta = self::guardar($request->file($campo), $carpeta, $disco);
            DB::table($tabla)->where('id', $id)->update([$columna => $ruta]);
        }

        $actual = DB::table($tabla)->where('id', $id)->value($columna);
        if ($anterior && $anterior !== $actual) {
            self::programarEliminacion($anterior, $disco);
        }

        return $ruta;
    }

    public static function programarEliminacion(?string $ruta, string $disco = self::PUBLICO): void
    {
        if (self::esLocal($ruta)) {
            self::$porEliminar[] = [$disco, $ruta];
        }
    }

    /** Programa el borrado de la carpeta completa de un registro ("{entidad}/{id}-*"). */
    public static function programarEliminacionCarpeta(string $entidad, $id, string $disco = self::PUBLICO): void
    {
        foreach (Storage::disk($disco)->directories(self::enDisco($entidad, $disco)) as $directorio) {
            $nombre = basename($directorio);
            if ($nombre === (string) $id || str_starts_with($nombre, "{$id}-")) {
                self::$porEliminar[] = [$disco, self::sinRaiz($directorio, $disco) . '/'];
            }
        }
    }

    /** Llamar después de DB::commit(): borra los archivos reemplazados o eliminados. */
    public static function confirmar(): void
    {
        foreach (self::$porEliminar as [$disco, $ruta]) {
            $enDisco = self::enDisco(rtrim($ruta, '/'), $disco);
            str_ends_with($ruta, '/')
                ? Storage::disk($disco)->deleteDirectory($enDisco)
                : Storage::disk($disco)->delete($enDisco);
        }
        self::$nuevos = [];
        self::$porEliminar = [];
    }

    /** Llamar en el catch/rollback: borra los archivos subidos en esta petición. */
    public static function revertir(): void
    {
        foreach (self::$nuevos as [$disco, $ruta]) {
            Storage::disk($disco)->delete(self::enDisco($ruta, $disco));
        }
        self::$nuevos = [];
        self::$porEliminar = [];
    }

    /** Ruta absoluta de un archivo privado (para descargas con sesión). */
    public static function rutaPrivada(string $ruta): ?string
    {
        $enDisco = self::enDisco($ruta, self::PRIVADO);
        return Storage::disk(self::PRIVADO)->exists($enDisco) ? Storage::disk(self::PRIVADO)->path($enDisco) : null;
    }

    // El disco local tiene raíz en storage/app: los privados van dentro de storage/app/private.
    private static function enDisco(string $ruta, string $disco): string
    {
        return $disco === self::PRIVADO ? self::RAIZ_PRIVADA . '/' . $ruta : $ruta;
    }

    private static function sinRaiz(string $ruta, string $disco): string
    {
        return $disco === self::PRIVADO ? Str::after($ruta, self::RAIZ_PRIVADA . '/') : $ruta;
    }
}
