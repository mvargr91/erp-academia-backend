<?php

namespace App\Support\Configuracion;

use Exception;
use Carbon\Carbon;
use App\Models\Central\Academia;
use Illuminate\Support\Facades\DB;

/**
 * Lee parámetros del sistema y arma plantillas de correo de la academia activa (o de la
 * academia administradora, para el cobro del ERP). Si algo no está configurado se usa el
 * valor por defecto, para que nunca deje de funcionar un proceso por falta de configuración.
 */
class Configuracion
{
    /** @var array<string, array<string, string>> base de datos => [codigo => valor] */
    private static array $cache = [];

    private const CONEXION_ADMIN = 'academia_administradora';

    public static function parametro(string $codigo, $defecto = null, ?string $conexion = null)
    {
        $conexion ??= 'mysql';
        $bd = config("database.connections.{$conexion}.database");
        if (!isset(self::$cache[$bd])) {
            try {
                self::$cache[$bd] = DB::connection($conexion)->table('parametros_constantes')
                    ->where('estado', 1)->pluck('valor_parametro', 'codigo_parametro')->all();
            } catch (Exception $e) {
                self::$cache[$bd] = [];
            }
        }
        $valor = self::$cache[$bd][$codigo] ?? null;
        return ($valor === null || $valor === '') ? $defecto : $valor;
    }

    public static function entero(string $codigo, int $defecto, ?string $conexion = null): int
    {
        $valor = self::parametro($codigo, $defecto, $conexion);
        return is_numeric($valor) ? (int) $valor : $defecto;
    }

    /** Parámetro de la academia administradora (cobro del ERP), esté activa o no. */
    public static function parametroAdministradora(string $codigo, $defecto = null)
    {
        return self::conexionAdministradora() ? self::parametro($codigo, $defecto, self::CONEXION_ADMIN) : $defecto;
    }

    public static function olvidarCache(): void
    {
        self::$cache = [];
    }

    /**
     * Plantilla lista para enviar: ['asunto' => texto, 'html' => cuerpo] con las variables
     * reemplazadas, o null si no existe o está inactiva (el llamador usa su texto por defecto).
     */
    public static function plantilla(string $codigo, array $variables, ?string $conexion = null): ?array
    {
        try {
            $p = DB::connection($conexion ?? 'mysql')->table('parametros_correos')
                ->where('codigo', $codigo)->where('estado', 1)->first();
        } catch (Exception $e) {
            return null;
        }
        if (!$p || trim(strip_tags((string) $p->texto)) === '') {
            return null;
        }
        return self::renderizar($p->asunto, $p->texto, $variables);
    }

    public static function plantillaAdministradora(string $codigo, array $variables): ?array
    {
        return self::conexionAdministradora() ? self::plantilla($codigo, $variables, self::CONEXION_ADMIN) : null;
    }

    /** Reemplaza {variable}: en el asunto como texto plano, en el cuerpo escapado para HTML. */
    public static function renderizar(string $asunto, string $cuerpo, array $variables): array
    {
        $reemplazo = function (string $texto, bool $html) use ($variables) {
            return preg_replace_callback('/\{([a-z_]+)\}/', function ($m) use ($variables, $html) {
                if (!array_key_exists($m[1], $variables)) {
                    return $m[0];
                }
                $valor = (string) ($variables[$m[1]] ?? '');
                return $html ? e($valor) : $valor;
            }, $texto);
        };
        return ['asunto' => $reemplazo($asunto, false), 'html' => $reemplazo($cuerpo, true)];
    }

    /** Conexión a la BD de la academia administradora (se crea al vuelo con los datos de 'mysql'). */
    private static function conexionAdministradora(): bool
    {
        if (config('database.connections.' . self::CONEXION_ADMIN)) {
            return true;
        }
        try {
            $bd = Academia::where('es_administradora', true)->value('base_datos');
        } catch (Exception $e) {
            return false;
        }
        if (!$bd) {
            return false;
        }
        config(['database.connections.' . self::CONEXION_ADMIN => array_merge(config('database.connections.mysql'), ['database' => $bd])]);
        return true;
    }

    /**
     * Crea en la academia activa los parámetros y plantillas del catálogo que falten
     * (no toca lo que la academia ya editó). Idempotente.
     */
    public static function sembrar(bool $esAdministradora, ?int $usuarioId = 1, string $usuarioNombre = 'Sistema'): array
    {
        $auditoria = [
            'usuario_creacion_id' => $usuarioId, 'usuario_creacion_nombre' => $usuarioNombre,
            'usuario_modificacion_id' => $usuarioId, 'usuario_modificacion_nombre' => $usuarioNombre,
            'created_at' => Carbon::now(), 'updated_at' => Carbon::now(),
        ];
        $creados = ['parametros' => 0, 'plantillas' => 0];

        $orden = 0;
        foreach (CatalogoConfiguracion::parametros() as $codigo => $def) {
            [$descripcion, $tipo, $valor, $grupo] = $def;
            $orden++;
            if (!empty($def[4]) && !$esAdministradora) {
                continue;
            }
            $existe = DB::table('parametros_constantes')->where('codigo_parametro', $codigo)->first();
            if ($existe) {
                // Mantiene el valor editado; actualiza la metadata del catálogo.
                DB::table('parametros_constantes')->where('id', $existe->id)
                    ->update(['descripcion_parametro' => $descripcion, 'tipo' => $tipo, 'grupo' => $grupo, 'orden' => $orden]);
                continue;
            }
            DB::table('parametros_constantes')->insert(array_merge($auditoria, [
                'codigo_parametro' => $codigo, 'descripcion_parametro' => $descripcion, 'valor_parametro' => $valor,
                'tipo' => $tipo, 'grupo' => $grupo, 'orden' => $orden, 'estado' => true,
            ]));
            $creados['parametros']++;
        }

        $orden = 0;
        foreach (CatalogoConfiguracion::plantillas() as $codigo => $def) {
            [$nombre, $asunto, $cuerpo, $variables] = $def;
            $orden++;
            if (!empty($def[4]) && !$esAdministradora) {
                continue;
            }
            $existe = DB::table('parametros_correos')->where('codigo', $codigo)->first();
            $meta = ['nombre' => $nombre, 'variables' => json_encode($variables, JSON_UNESCAPED_UNICODE), 'orden' => $orden];
            if ($existe) {
                DB::table('parametros_correos')->where('id', $existe->id)->update($meta);
                continue;
            }
            DB::table('parametros_correos')->insert(array_merge($auditoria, $meta, [
                'codigo' => $codigo, 'asunto' => $asunto, 'texto' => $cuerpo, 'estado' => true,
            ]));
            $creados['plantillas']++;
        }
        self::olvidarCache();
        return $creados;
    }

    /** Texto por defecto de una plantilla (para "restaurar"). */
    public static function porDefecto(string $codigo): ?array
    {
        $def = CatalogoConfiguracion::plantillas()[$codigo] ?? null;
        return $def ? ['asunto' => $def[1], 'texto' => $def[2]] : null;
    }
}
