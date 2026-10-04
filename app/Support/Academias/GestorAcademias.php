<?php

namespace App\Support\Academias;

use Database\Seeders\ConfiguracionAcademiaSeeder;
use App\Services\Central\FacturacionAcademias;
use Exception;
use Carbon\Carbon;
use App\Models\Central\Academia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\PermissionRegistrar;
use Database\Seeders\AcademiaMenuSeeder;

/**
 * Multi-academia con una BD por academia.
 *
 * La conexión por defecto 'mysql' se re-apunta a la BD de la academia activa, por eso
 * los modelos y DB::table() existentes funcionan sin cambios. La BD central
 * (conexión 'central') guarda el registro de academias y la cola de trabajos.
 */
class GestorAcademias
{
    private static ?Academia $actual = null;

    public static function actual(): ?Academia
    {
        return self::$actual;
    }

    /**
     * Resuelve la academia de la petición, en este orden:
     * cabecera X-Academia (la envía el frontend) → subdominio del Host → academia por defecto (.env).
     */
    public static function resolver(Request $request): ?Academia
    {
        $codigo = $request->header('X-Academia') ?: self::codigoDesdeHost($request->getHost());
        $codigo = $codigo ?: config('academias.por_defecto');

        if (!$codigo) {
            return null;
        }
        return Academia::where('codigo', strtolower(trim($codigo)))->first();
    }

    private static function codigoDesdeHost(string $host): ?string
    {
        $dominio = config('academias.dominio');
        if (!$dominio || !str_ends_with($host, '.' . $dominio)) {
            return null;
        }
        $sub = substr($host, 0, -strlen('.' . $dominio));
        // Solo el primer nivel: salsa.miacademia.com → salsa (api.salsa... no aplica).
        if ($sub === '' || str_contains($sub, '.') || in_array($sub, config('academias.subdominios_reservados'), true)) {
            return null;
        }
        return $sub;
    }

    /** Apunta la conexión por defecto a la BD de la academia. */
    public static function activar(Academia $academia): void
    {
        config(['database.connections.mysql.database' => $academia->base_datos]);
        DB::purge('mysql');
        DB::setDefaultConnection('mysql');

        // La caché de permisos de Spatie es por academia (la BD de roles es distinta).
        config(['permission.cache.key' => 'spatie.permission.cache.' . $academia->codigo]);
        $registrar = app(PermissionRegistrar::class);
        $registrar->initializeCache();
        $registrar->clearPermissionsCollection();

        self::$actual = $academia;
    }

    /**
     * Borra la caché de permisos de Spatie de la BD actual. Por consola (seeders) no hay academia
     * activa y la clave por defecto no es la que usan las peticiones web, así que se limpian las
     * claves de todas las academias que apuntan a esta BD.
     */
    public static function olvidarCachePermisos(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        try {
            $baseDatos = config('database.connections.mysql.database');
            $codigos = Academia::where('base_datos', $baseDatos)->pluck('codigo');
        } catch (Exception $e) {
            return; // BD central aún sin crear
        }
        foreach ($codigos as $codigo) {
            $registrar->getCacheRepository()->forget('spatie.permission.cache.' . $codigo);
        }
    }

    /** Ejecuta $callback con cada academia activa y deja activa la que estaba. */
    public static function paraCada(callable $callback, ?string $codigo = null): void
    {
        $anterior = self::$actual;
        $query = Academia::where('activa', true)->orderBy('id');
        if ($codigo) {
            $query->where('codigo', $codigo);
        }
        try {
            foreach ($query->get() as $academia) {
                self::activar($academia);
                $callback($academia);
            }
        } finally {
            if ($anterior) {
                self::activar($anterior);
            }
        }
    }

    public static function nombreBaseDatos(string $codigo): string
    {
        return config('academias.prefijo_bd') . str_replace('-', '_', $codigo);
    }

    /**
     * Crea una academia nueva: registro central + BD + migraciones + datos base
     * (usuario administrador, roles, menú) + clientes OAuth copiados de la BD plantilla.
     * Si algo falla se elimina la BD y el registro.
     */
    public static function crear(array $datos, string $usuarioAdmin, string $claveAdmin): Academia
    {
        $codigo = strtolower($datos['codigo']);
        $baseDatos = self::nombreBaseDatos($codigo);

        $existeBd = DB::connection('central')->selectOne(
            'SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?',
            [$baseDatos]
        );
        if ($existeBd) {
            throw new Exception("La base de datos {$baseDatos} ya existe.");
        }

        $academia = Academia::create([
            'codigo' => $codigo,
            'nombre' => $datos['nombre'],
            'base_datos' => $baseDatos,
            'correo' => $datos['correo'] ?? null,
            'telefono' => $datos['telefono'] ?? null,
            'dia_pago' => $datos['dia_pago'] ?? 5,
            // Cobro del ERP: por defecto la tarifa base y el corte el día del alta.
            'tarifa_mensual' => $datos['tarifa_mensual'] ?? FacturacionAcademias::cfg('tarifa_base'),
            'dia_corte' => $datos['dia_corte'] ?? min(Carbon::today()->day, 28),
            'fecha_inicio_cobro' => $datos['fecha_inicio_cobro'] ?? Carbon::today()->toDateString(),
            'activa' => true,
        ]);

        $anterior = self::$actual;
        DB::connection('central')->statement(
            "CREATE DATABASE `{$baseDatos}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );

        try {
            self::activar($academia);
            self::migrar();

            Artisan::call('db:seed', ['--force' => true]);
            Artisan::call('db:seed', ['--class' => AcademiaMenuSeeder::class, '--force' => true]);
            Artisan::call('db:seed', ['--class' => ConfiguracionAcademiaSeeder::class, '--force' => true]);

            self::copiarClientesOAuth($baseDatos);
            self::configurarAdministrador($academia, $usuarioAdmin, $claveAdmin);
        } catch (Exception $e) {
            DB::connection('central')->statement("DROP DATABASE IF EXISTS `{$baseDatos}`");
            $academia->delete();
            throw $e;
        } finally {
            if ($anterior) {
                self::activar($anterior);
            }
        }

        HostsLocales::intentarActualizar();
        return $academia;
    }

    /** Corre las migraciones pendientes en la academia activa. Devuelve la salida de Artisan. */
    public static function migrar(): string
    {
        Artisan::call('migrate', ['--database' => 'mysql', '--force' => true]);
        return Artisan::output();
    }

    /**
     * Passport valida client_id/secret contra la BD de la academia: se copian los mismos
     * clientes de la BD plantilla para que PASSWORD_CLIENT_ID/SECRET del .env sirvan en todas.
     */
    private static function copiarClientesOAuth(string $destino): void
    {
        $plantilla = config('academias.bd_plantilla');
        if (!$plantilla || $plantilla === $destino) {
            return;
        }
        foreach (['oauth_clients', 'oauth_personal_access_clients'] as $tabla) {
            DB::statement("INSERT INTO `{$destino}`.`{$tabla}` SELECT * FROM `{$plantilla}`.`{$tabla}`");
        }
    }

    /** El seeder crea el usuario 00000000 / SuperUser0: se le asignan usuario y clave propios. */
    private static function configurarAdministrador(Academia $academia, string $usuario, string $clave): void
    {
        $ahora = Carbon::now();
        DB::table('users')->where('id', 1)->update([
            'name' => 'Administrador',
            'email' => $usuario,
            'password' => Hash::make($clave),
            'updated_at' => $ahora,
        ]);
        DB::table('usuarios')->where('user_id', 1)->update([
            'identificacion_usuario' => $usuario,
            'nombre' => 'Administrador ' . $academia->nombre,
            'correo_electronico' => $academia->correo ?: 'correo@correo.com',
            'updated_at' => $ahora,
        ]);
    }
}
