<?php

namespace App\Support\Academias;

use Carbon\Carbon;
use App\Models\Central\Academia;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Certificado SSL de los subdominios de las academias.
 *
 * El servidor tiene un solo certificado de Let's Encrypt con un nombre por subdominio. Al crear una
 * academia, el ERP ejecuta el comando configurado (academias.certificado_comando: un script del servidor
 * que solo acepta <codigo>.<dominio>, corre `certbot --expand` y recarga Nginx) con los subdominios de
 * todas las academias. El script conserva los que el certificado ya tiene, así que nunca quita ninguno.
 */
class Certificado
{
    public const LISTO = 'listo';
    public const PENDIENTE = 'pendiente';
    public const ERROR = 'error';

    /** ¿El servidor gestiona el certificado? (en desarrollo no hay comando). */
    public static function activo(): bool
    {
        return trim((string) config('academias.certificado_comando')) !== '';
    }

    /** Subdominios de todas las academias: <codigo>.<dominio>. */
    public static function subdominios(): array
    {
        $dominio = config('academias.dominio');
        return Academia::orderBy('id')->pluck('codigo')->map(fn ($codigo) => "{$codigo}.{$dominio}")->all();
    }

    /** Deja la academia esperando su certificado (lo amplía el trabajo en cola AmpliarCertificado). */
    public static function marcarPendiente(Academia $academia): void
    {
        if (self::activo()) {
            $academia->forceFill(['certificado' => self::PENDIENTE, 'certificado_mensaje' => null, 'certificado_fecha' => null])->save();
        }
    }

    /**
     * Amplía el certificado con los subdominios de todas las academias. Si sale bien, todas quedan
     * «listo»; si falla, la academia que lo pidió queda «error» con el mensaje para reintentar.
     */
    public static function ampliar(Academia $academia): bool
    {
        if (!self::activo()) {
            return false;
        }
        $comando = array_merge(preg_split('/\s+/', trim(config('academias.certificado_comando'))), self::subdominios());
        $resultado = Process::timeout(300)->run($comando);
        $salida = trim($resultado->errorOutput() . "\n" . $resultado->output());

        if ($resultado->successful()) {
            Academia::query()->update(['certificado' => self::LISTO, 'certificado_mensaje' => null, 'certificado_fecha' => Carbon::now()]);
            return true;
        }
        Log::error("Certificado SSL de {$academia->codigo}: {$salida}");
        $academia->forceFill([
            'certificado' => self::ERROR,
            'certificado_mensaje' => mb_substr($salida ?: 'El comando del certificado falló sin mensaje.', -2000),
            'certificado_fecha' => Carbon::now(),
        ])->save();
        return false;
    }
}
