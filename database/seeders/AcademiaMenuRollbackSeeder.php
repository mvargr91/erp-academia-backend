<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Revierte AcademiaMenuSeeder: quita del menú los módulos de Academia
 * (y sus opciones/permisos). Idempotente.
 *
 *   php artisan db:seed --class=AcademiaMenuRollbackSeeder
 */
class AcademiaMenuRollbackSeeder extends Seeder
{
    private const MODULOS_A_QUITAR = [
        'Academia',
        'Configuración Academia',
    ];

    public function run()
    {
        DB::transaction(function () {
            $moduloIds = DB::table('modulos')
                ->whereIn('nombre', self::MODULOS_A_QUITAR)
                ->pluck('id')
                ->all();

            if (empty($moduloIds)) {
                return;
            }

            $opcionIds = DB::table('opciones_del_sistema')
                ->whereIn('modulo_id', $moduloIds)
                ->pluck('id')
                ->all();

            if (!empty($opcionIds)) {
                $permisoIds = DB::table('permissions')
                    ->whereIn('option_id', $opcionIds)
                    ->pluck('id')
                    ->all();

                if (!empty($permisoIds)) {
                    DB::table('role_has_permissions')->whereIn('permission_id', $permisoIds)->delete();
                    DB::table('model_has_permissions')->whereIn('permission_id', $permisoIds)->delete();
                    DB::table('permissions')->whereIn('id', $permisoIds)->delete();
                }

                DB::table('opciones_del_sistema')->whereIn('id', $opcionIds)->delete();
            }

            DB::table('modulos')->whereIn('id', $moduloIds)->delete();
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
