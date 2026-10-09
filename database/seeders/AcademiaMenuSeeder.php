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

    // modulo => [icono, posicion, opciones[nombre, url, icono, entidad del permiso, permisos propios (opcional)]]
    //
    // Los permisos propios se suman a los cuatro de siempre, uno por cada acción de la opción que no es
    // crear/modificar/eliminar/listar: p. ej. Pagar → PagarPaquete. Escritos como 'Titulo' => 'Padre',
    // al crearse por primera vez se conceden a los roles que ya tenían el permiso padre de esa opción
    // (así quien ya hacía esa acción con «Modificar» o «Listar» no la pierde); después se administran
    // por rol como cualquier otro.
    private const MENU = [
        'Academia' => ['school', 5, [
            ['Panel Academia', '/dashboard-academia', 'dashboard', 'DashboardAcademia'],
            ['Alumnos', '/alumnos', 'groups', 'Alumno', ['EstadoCuenta' => 'Listar', 'Exportar' => 'Listar']],
            ['Matrícula rápida', '/matriculas', 'how_to_reg', 'Matricula'],
            ['Profesores', '/profesores', 'person', 'Profesor', ['Exportar' => 'Listar']],
            ['Cursos', '/cursos', 'event', 'Curso', ['FormaDePago' => 'Modificar', 'Exportar' => 'Listar']],
            ['Tomar asistencia', '/tomar-asistencia', 'checklist', 'TomaAsistencia'],
            ['Asistencia', '/asistencias', 'fact_check', 'Asistencia', ['Exportar' => 'Listar']],
            ['Clases personalizadas', '/clases-privadas', 'person_pin', 'ClasePrivada', ['Pagar', 'RegistrarAsistencia' => 'Modificar', 'Exportar' => 'Listar']],
            ['Paquetes de clases', '/paquetes', 'card_membership', 'Paquete', ['Pagar', 'RegistrarClases' => 'Modificar', 'Exportar' => 'Listar']],
            ['Envíos de correo', '/envios-correo', 'campaign', 'EnvioCorreo', ['Exportar' => 'Listar']],
            ['Pagos', '/pagos', 'payments', 'Pago', ['Exportar' => 'Listar']],
        ]],
        'Configuración Academia' => ['settings', 6, [
            ['Sedes', '/sedes', 'store', 'Sede', ['Exportar' => 'Listar']],
            ['Ritmos', '/ritmos', 'music_note', 'Ritmo', ['Exportar' => 'Listar']],
            ['Planes', '/planes', 'sell', 'Plan', ['Exportar' => 'Listar']],
            ['Tarifas', '/tarifas', 'price_change', 'Tarifa'],
            ['Cierres de la academia', '/cierres', 'event_busy', 'Cierre', ['Exportar' => 'Listar']],
            ['Parámetros', '/parametros', 'tune', 'ParametroSistema', ['Exportar' => 'Listar']],
            ['Plantillas de correo', '/plantillas-correo', 'mail', 'PlantillaCorreo', ['Exportar' => 'Listar']],
            ['Apariencia', '/apariencia', 'palette', 'Apariencia'],
        ]],
    ];

    // Permisos propios de opciones que no siembra este menú (las de Seguridad): url => [entidad, permisos propios].
    private const OTRAS_OPCIONES = [
        '/usuarios' => ['Usuario', ['CambiarClave' => 'Modificar']],
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

                foreach ($opciones as $posicion => $opcion) {
                    [$nombreOpcion, $url, $iconoOpcion, $entidad] = $opcion;
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
                        $this->registrarPermiso($opcionId, $entidad, $titulo, null, $ahora);
                    }
                    $this->registrarPropios($opcionId, $entidad, $opcion[4] ?? [], $ahora);
                }
            }

            foreach (self::OTRAS_OPCIONES as $url => [$entidad, $propios]) {
                if ($opcionId = DB::table('opciones_del_sistema')->where('url', $url)->value('id')) {
                    $this->registrarPropios($opcionId, $entidad, $propios, $ahora);
                }
            }
        });

        GestorAcademias::olvidarCachePermisos();
    }

    /** @param array $propios  ['Titulo'] o ['Titulo' => 'Padre'] */
    private function registrarPropios(int $opcionId, string $entidad, array $propios, Carbon $ahora): void
    {
        foreach ($propios as $clave => $valor) {
            [$titulo, $padre] = is_int($clave) ? [$valor, null] : [$clave, $valor];
            $this->registrarPermiso($opcionId, $entidad, $titulo, $padre, $ahora);
        }
    }

    /**
     * Crea (o actualiza) el permiso {Titulo}{Entidad} de la opción y lo concede al administrador.
     * Si es nuevo y tiene padre, también a los roles que ya tenían {Padre}{Entidad}.
     */
    private function registrarPermiso(int $opcionId, string $entidad, string $titulo, ?string $padre, Carbon $ahora): void
    {
        $nombre = $titulo . $entidad;
        $llave = ['name' => $nombre, 'guard_name' => 'api'];
        $esNuevo = !DB::table('permissions')->where($llave)->exists();
        DB::table('permissions')->updateOrInsert($llave, [
            'option_id' => $opcionId,
            'title' => $titulo,
            'user_creation_id' => 1,
            'user_creation_name' => 'SuperUser',
            'user_modification_id' => 1,
            'user_modification_name' => 'SuperUser',
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
        $permisoId = DB::table('permissions')->where($llave)->value('id');

        $roles = [self::ROL_ADMINISTRADOR_ID];
        if ($esNuevo && $padre) {
            $roles = array_merge($roles, DB::table('role_has_permissions as rp')
                ->join('permissions as p', 'p.id', '=', 'rp.permission_id')
                ->where('p.name', $padre . $entidad)->where('p.guard_name', 'api')
                ->pluck('rp.role_id')->all());
        }
        foreach (array_unique($roles) as $rolId) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permisoId, 'role_id' => $rolId]);
        }
    }
}
