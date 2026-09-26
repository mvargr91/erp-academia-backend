<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles del portal: Proveedor y Cliente, con su menú y permisos.
 * Requiere haber ejecutado antes TurismoMenuSeeder (opciones /experiencias y /reservas).
 *
 * Es idempotente:
 *   php artisan db:seed --class=PortalRolesSeeder
 *
 * El alcance de los datos (solo lo propio) lo impone el backend con el trait AlcancePorRol.
 */
class PortalRolesSeeder extends Seeder
{
    // Opciones nuevas exclusivas del cliente: modulo => [icono, posicion, opciones[nombre, url, icono, entidad, permisos]]
    private const MENU_CLIENTE = [
        'Mi Cuenta' => ['account_circle', 1, [
            ['Mis Reservas', '/mis-reservas', 'event_note', 'MiReserva', ['Listar', 'Modificar']],
            ['Mis Favoritos', '/mis-favoritos', 'favorite', 'MiFavorito', ['Listar', 'Eliminar']],
        ]],
    ];

    // Permisos existentes (creados por TurismoMenuSeeder) que se conceden al proveedor.
    private const PERMISOS_PROVEEDOR = [
        'ListarExperiencia', 'CrearExperiencia', 'ModificarExperiencia',
        'ListarReserva', 'ModificarReserva',
    ];

    public function run()
    {
        DB::transaction(function () {
            $ahora = Carbon::now();

            $proveedorRolId = $this->rol('Proveedor', $ahora);
            $clienteRolId = $this->rol('Cliente', $ahora);

            foreach (self::PERMISOS_PROVEEDOR as $nombre) {
                $permisoId = DB::table('permissions')->where('name', $nombre)->where('guard_name', 'api')->value('id');
                if ($permisoId) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permisoId, 'role_id' => $proveedorRolId]);
                }
            }

            $aplicacionId = DB::table('aplicaciones')->orderBy('id')->value('id');
            $auditoria = [
                'usuario_creacion_id' => 1,
                'usuario_creacion_nombre' => 'SuperUser',
                'usuario_modificacion_id' => 1,
                'usuario_modificacion_nombre' => 'SuperUser',
            ];

            foreach (self::MENU_CLIENTE as $nombreModulo => [$iconoModulo, $posicionModulo, $opciones]) {
                DB::table('modulos')->updateOrInsert(
                    ['nombre' => $nombreModulo, 'aplicacion_id' => $aplicacionId],
                    array_merge($auditoria, [
                        'icono_menu' => $iconoModulo,
                        'posicion' => $posicionModulo,
                        'estado' => true,
                        'created_at' => $ahora,
                        'updated_at' => $ahora,
                    ])
                );
                $moduloId = DB::table('modulos')->where('nombre', $nombreModulo)->where('aplicacion_id', $aplicacionId)->value('id');

                foreach ($opciones as $posicion => [$nombreOpcion, $url, $iconoOpcion, $entidad, $permisos]) {
                    DB::table('opciones_del_sistema')->updateOrInsert(
                        ['url' => $url],
                        array_merge($auditoria, [
                            'nombre' => $nombreOpcion,
                            'modulo_id' => $moduloId,
                            'posicion' => $posicion + 1,
                            'icono_menu' => $iconoOpcion,
                            'estado' => true,
                            'created_at' => $ahora,
                            'updated_at' => $ahora,
                        ])
                    );
                    $opcionId = DB::table('opciones_del_sistema')->where('url', $url)->value('id');

                    foreach ($permisos as $titulo) {
                        $nombrePermiso = $titulo . $entidad;
                        DB::table('permissions')->updateOrInsert(
                            ['name' => $nombrePermiso, 'guard_name' => 'api'],
                            [
                                'option_id' => $opcionId,
                                'title' => $titulo,
                                'user_creation_id' => 1,
                                'user_creation_name' => 'SuperUser',
                                'user_modification_id' => 1,
                                'user_modification_name' => 'SuperUser',
                                'created_at' => $ahora,
                                'updated_at' => $ahora,
                            ]
                        );
                        $permisoId = DB::table('permissions')->where('name', $nombrePermiso)->where('guard_name', 'api')->value('id');
                        DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permisoId, 'role_id' => $clienteRolId]);
                    }
                }
            }
        });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    private function rol($nombre, $ahora)
    {
        DB::table('roles')->updateOrInsert(
            ['name' => $nombre, 'guard_name' => 'api'],
            [
                'type' => 'EX',
                'status' => true,
                'creation_user_id' => 1,
                'creation_user_name' => 'SuperUser',
                'modification_user_id' => 1,
                'modification_user_name' => 'SuperUser',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]
        );
        return DB::table('roles')->where('name', $nombre)->where('guard_name', 'api')->value('id');
    }
}
