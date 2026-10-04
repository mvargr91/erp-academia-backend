<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Support\Academias\GestorAcademias;

/**
 * Registra en el menú los módulos, opciones y permisos de la Academia
 * y los asigna al rol administrador (id 1).
 *
 * Es idempotente: se puede ejecutar varias veces sin duplicar registros.
 *   php artisan db:seed --class=AcademiaMenuSeeder
 *
 * Las url deben coincidir con las rutas del frontend (src/@crema/core/AppRoutes).
 */
class AcademiaMenuSeeder extends Seeder
{
    private const ROL_ADMINISTRADOR_ID = 1;

    private const PERMISOS = ['Crear', 'Modificar', 'Eliminar', 'Listar'];

    // modulo => [icono, posicion, opciones[nombre, url, icono, entidad del permiso]]
    private const MENU = [
        'Academia' => ['school', 5, [
            ['Panel Academia', '/dashboard-academia', 'dashboard', 'DashboardAcademia'],
            ['Alumnos', '/alumnos', 'groups', 'Alumno'],
            ['Profesores', '/profesores', 'person', 'Profesor'],
            ['Cursos', '/cursos', 'event', 'Curso'],
            ['Asistencia', '/asistencias', 'fact_check', 'Asistencia'],
            ['Clases personalizadas', '/clases-privadas', 'person_pin', 'ClasePrivada'],
            ['Paquetes de clases', '/paquetes', 'card_membership', 'Paquete'],
            ['Envíos de correo', '/envios-correo', 'campaign', 'EnvioCorreo'],
            ['Pagos', '/pagos', 'payments', 'Pago'],
        ]],
        'Configuración Academia' => ['settings', 6, [
            ['Sedes', '/sedes', 'store', 'Sede'],
            ['Ritmos', '/ritmos', 'music_note', 'Ritmo'],
            ['Planes', '/planes', 'sell', 'Plan'],
            ['Cierres de la academia', '/cierres', 'event_busy', 'Cierre'],
            ['Parámetros', '/parametros', 'tune', 'ParametroSistema'],
            ['Plantillas de correo', '/plantillas-correo', 'mail', 'PlantillaCorreo'],
            ['Apariencia', '/apariencia', 'palette', 'Apariencia'],
        ]],
    ];

    /** Menú a registrar; las subclases lo reemplazan para sembrar otros módulos. */
    protected function menu(): array
    {
        return self::MENU;
    }

    public function run()
    {
        DB::transaction(function () {
            $ahora = Carbon::now();
            $auditoria = [
                'usuario_creacion_id' => 1,
                'usuario_creacion_nombre' => 'SuperUser',
                'usuario_modificacion_id' => 1,
                'usuario_modificacion_nombre' => 'SuperUser',
            ];
            $aplicacionId = DB::table('aplicaciones')->orderBy('id')->value('id');

            foreach ($this->menu() as $nombreModulo => [$iconoModulo, $posicionModulo, $opciones]) {
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
                $moduloId = DB::table('modulos')
                    ->where('nombre', $nombreModulo)
                    ->where('aplicacion_id', $aplicacionId)
                    ->value('id');

                foreach ($opciones as $posicion => [$nombreOpcion, $url, $iconoOpcion, $entidad]) {
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

                    foreach (self::PERMISOS as $titulo) {
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
                        $permisoId = DB::table('permissions')
                            ->where('name', $nombrePermiso)
                            ->where('guard_name', 'api')
                            ->value('id');

                        DB::table('role_has_permissions')->insertOrIgnore([
                            'permission_id' => $permisoId,
                            'role_id' => self::ROL_ADMINISTRADOR_ID,
                        ]);
                    }
                }
            }
        });

        GestorAcademias::olvidarCachePermisos();
    }
}
