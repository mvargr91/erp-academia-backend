<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Seguridad;
use App\Http\Controllers\Academia;
use App\Http\Controllers\Central;
use App\Support\Permisos;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use Laravel\Passport\Http\Controllers\AccessTokenController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

Route::get('/', function () {
    return "hola, tienes acceso";
});

Route::post('/users/token', [UserController::class,'getToken'])->name('oauth.getToken');
Route::post('/forgot-password', [UserController::class,'forgotPassword'])->name('oauth.password.email');
Route::post('/reset-password',[UserController::class,'resetPassword'])->name('oauth.password.update');
Route::post('/register', [UserController::class, 'register'])->name('oauth.register');
Route::post('/login', [UserController::class, 'login'])->name('oauth.login');
// Route::post('oauth/token', [AccessTokenController::class, 'issueToken']);
Route::post('oauth/token', [AccessTokenController::class, 'issueToken'])->name('oauth.token');

// Apariencia de la academia (colores, logos, login): pública porque el login la pinta antes de autenticar.
Route::get('/apariencia', [Academia\AparienciaController::class, 'show'])->name('publico.apariencia');

// Las lecturas de catálogos se perfilan con Permisos::lectura (quien usa el catálogo en su opción puede leerlo).
Route::group(['middleware' => ['auth:api']], function (){
    // User
    Route::group(["prefix" => "users"],function(){
        Route::get('current/session',  [UserController::class,'getSession'])->name('session.show');
    });

    // Mi cuenta: datos del usuario autenticado (cualquier rol, solo los propios)
    Route::group(["prefix" => "cuenta"], function () {
        Route::get('/', [Seguridad\CuentaController::class, 'show'])->name('cuenta.show');
        Route::put('/', [Seguridad\CuentaController::class, 'update'])->name('cuenta.update');
        Route::put('/clave', [Seguridad\CuentaController::class, 'cambiarClave'])->middleware('throttle:10,1,cuenta-clave')->name('cuenta.clave');
    });

    // ---------------------- Seguridad -------------------------- //
    // Todas las rutas exigen el permiso del rol: con el registro público cualquiera obtiene un token,
    // así que sin esto un cliente podría editar usuarios, cambiar claves u otorgarse permisos.

    // Usuarios
    Route::group(["prefix" => "usuarios"],function(){
        Route::get('/', [Seguridad\UsuarioController::class,'index'])->name('usuarios.index')->middleware('permission:ListarUsuario');
        Route::post('/', [Seguridad\UsuarioController::class,'store'])->name('usuarios.store')->middleware('permission:CrearUsuario');
        Route::get('/{id}', [Seguridad\UsuarioController::class,'show'])->name('usuarios.show')->middleware('permission:ListarUsuario');
        Route::put('/cambio-clave', [Seguridad\UsuarioController::class,'changePassword'])->name('usuarios.changePassword')->middleware('permission:CambiarClaveUsuario');
        Route::put('/{id}', [Seguridad\UsuarioController::class,'update'])->name('usuarios.update')->middleware('permission:ModificarUsuario');
        Route::delete('/{id}', [Seguridad\UsuarioController::class,'destroy'])->name('usuarios.delete')->middleware('permission:EliminarUsuario');
    });

    // Roles
    Route::group(["prefix" => "roles"],function(){
        Route::get('/', [Seguridad\RolController::class,'index'])->name('roles.index')->middleware('permission:ListarRol|ListarUsuario');
        Route::get('/permisos/{id}', [Seguridad\RolController::class,'obtenerPermisos'])->name('roles.permisos')->middleware('permission:PermitirRol');
        Route::post('/permisos', [Seguridad\RolController::class,'otorgarPermisos'])->name('roles.otorgarPermisos')->middleware('permission:PermitirRol');
        Route::put('/permisos', [Seguridad\RolController::class,'revocarPermisos'])->name('roles.revocarPermisos')->middleware('permission:PermitirRol');
        Route::post('/', [Seguridad\RolController::class,'store'])->name('roles.store')->middleware('permission:CrearRol');
        Route::get('/{id}', [Seguridad\RolController::class,'show'])->name('roles.show')->middleware('permission:ListarRol');
        Route::put('/{id}', [Seguridad\RolController::class,'update'])->name('roles.update')->middleware('permission:ModificarRol');
        Route::delete('/{id}', [Seguridad\RolController::class,'destroy'])->name('roles.delete')->middleware('permission:EliminarRol');
    });

    // Aplicaciones
    Route::group(["prefix" => "aplicaciones"],function(){
        Route::get('/', [Seguridad\AplicacionController::class,'index'])->name('aplicaciones.index')->middleware('permission:ListarAplicacion|ListarModulo');
        Route::post('/', [Seguridad\AplicacionController::class,'store'])->name('aplicaciones.store')->middleware('permission:CrearAplicacion');
        Route::get('/{id}', [Seguridad\AplicacionController::class,'show'])->name('aplicaciones.show')->middleware('permission:ListarAplicacion');
        Route::put('/{id}', [Seguridad\AplicacionController::class,'update'])->name('aplicaciones.update')->middleware('permission:ModificarAplicacion');
        Route::delete('/{id}', [Seguridad\AplicacionController::class,'destroy'])->name('aplicaciones.delete')->middleware('permission:EliminarAplicacion');
    });

    // Módulos
    Route::group(["prefix" => "modulos"],function(){
        Route::get('/', [Seguridad\ModuloController::class,'index'])->name('modulos.index')->middleware('permission:ListarModulo|ListarOpcionSistema');
        Route::post('/', [Seguridad\ModuloController::class,'store'])->name('modulos.store')->middleware('permission:CrearModulo');
        Route::get('/{id}', [Seguridad\ModuloController::class,'show'])->name('modulos.show')->middleware('permission:ListarModulo');
        Route::put('/{id}', [Seguridad\ModuloController::class,'update'])->name('modulos.update')->middleware('permission:ModificarModulo');
        Route::delete('/{id}', [Seguridad\ModuloController::class,'destroy'])->name('modulos.delete')->middleware('permission:EliminarModulo');
    });

    // Opciones del Sistema
    Route::group(["prefix" => "opciones-del-sistema"],function(){
        Route::get('/', [Seguridad\OpcionSistemaController::class,'index'])->name('opciones-del-sistema.index')->middleware('permission:ListarOpcionSistema|ListarAccionPermiso');
        Route::post('/', [Seguridad\OpcionSistemaController::class,'store'])->name('opciones-del-sistema.store')->middleware('permission:CrearOpcionSistema');
        Route::get('/{id}', [Seguridad\OpcionSistemaController::class,'show'])->name('opciones-del-sistema.show')->middleware('permission:ListarOpcionSistema');
        Route::put('/{id}', [Seguridad\OpcionSistemaController::class,'update'])->name('opciones-del-sistema.update')->middleware('permission:ModificarOpcionSistema');
        Route::delete('/{id}', [Seguridad\OpcionSistemaController::class,'destroy'])->name('opciones-del-sistema.delete')->middleware('permission:EliminarOpcionSistema');
    });

    // Permisos
    Route::group(["prefix" => "permisos"],function(){
        Route::get('/', [Seguridad\PermisoController::class,'index'])->name('permisos.index')->middleware('permission:ListarAccionPermiso|PermitirRol');
        Route::post('/', [Seguridad\PermisoController::class,'store'])->name('permisos.store')->middleware('permission:CrearAccionPermiso');
        Route::get('/{id}', [Seguridad\PermisoController::class,'show'])->name('permisos.show')->middleware('permission:ListarAccionPermiso');
        Route::put('/{id}', [Seguridad\PermisoController::class,'update'])->name('permisos.update')->middleware('permission:ModificarAccionPermiso');
        Route::delete('/{id}', [Seguridad\PermisoController::class,'destroy'])->name('permisos.delete')->middleware('permission:EliminarAccionPermiso');
    });

    // Auditoria Tablas
    Route::group(["prefix" => "auditoria-tablas", "middleware" => ["permission:ListarAuditorias"]],function(){
        Route::get('/', [Seguridad\AuditoriaTablaController::class,'index'])->name('auditoria-tablas.index');
    });

    // ---------------------- Configuración de la academia -------------------------- //

    // Parámetros del sistema (los códigos los define el sistema; solo se edita el valor)
    Route::group(["prefix" => "parametros"], function () {
        Route::get('/', [Academia\ParametroController::class, 'index'])->name('parametros.index')->middleware('permission:ListarParametroSistema');
        Route::get('/{id}', [Academia\ParametroController::class, 'show'])->name('parametros.show')->middleware('permission:ListarParametroSistema');
        Route::put('/{id}', [Academia\ParametroController::class, 'update'])->name('parametros.update')->middleware('permission:ModificarParametroSistema');
    });

    // Apariencia del ERP (colores, logos, pantalla de ingreso). Se envía por POST: lleva imágenes.
    Route::group(["prefix" => "apariencia"], function () {
        Route::post('/', [Academia\AparienciaController::class, 'update'])->name('apariencia.update')->middleware('permission:ModificarApariencia');
        Route::delete('/', [Academia\AparienciaController::class, 'restaurar'])->name('apariencia.restaurar')->middleware('permission:ModificarApariencia');
    });

    // Plantillas de correo
    Route::group(["prefix" => "plantillas-correo"], function () {
        Route::get('/', [Academia\PlantillaCorreoController::class, 'index'])->name('plantillas-correo.index')->middleware('permission:ListarPlantillaCorreo|CrearEnvioCorreo');
        Route::get('/{id}', [Academia\PlantillaCorreoController::class, 'show'])->name('plantillas-correo.show')->middleware('permission:ListarPlantillaCorreo');
        Route::get('/{id}/por-defecto', [Academia\PlantillaCorreoController::class, 'porDefecto'])->name('plantillas-correo.defecto')->middleware('permission:ModificarPlantillaCorreo');
        Route::post('/{id}/vista-previa', [Academia\PlantillaCorreoController::class, 'vistaPrevia'])->name('plantillas-correo.vista')->middleware('permission:ListarPlantillaCorreo');
        Route::post('/{id}/probar', [Academia\PlantillaCorreoController::class, 'probar'])->name('plantillas-correo.probar')->middleware('permission:ModificarPlantillaCorreo');
        Route::put('/{id}', [Academia\PlantillaCorreoController::class, 'update'])->name('plantillas-correo.update')->middleware('permission:ModificarPlantillaCorreo');
        Route::post('/', [Academia\PlantillaCorreoController::class, 'store'])->name('plantillas-correo.store')->middleware('permission:CrearPlantillaCorreo');
        Route::delete('/{id}', [Academia\PlantillaCorreoController::class, 'destroy'])->name('plantillas-correo.delete')->middleware('permission:EliminarPlantillaCorreo');
    });

    // Envíos de correo manuales/masivos
    Route::group(["prefix" => "envios-correo"], function () {
        Route::get('/', [Academia\EnvioCorreoController::class, 'index'])->name('envios-correo.index')->middleware('permission:ListarEnvioCorreo');
        Route::post('/resumen', [Academia\EnvioCorreoController::class, 'resumen'])->name('envios-correo.resumen')->middleware('permission:CrearEnvioCorreo');
        Route::post('/', [Academia\EnvioCorreoController::class, 'store'])->name('envios-correo.store')->middleware('permission:CrearEnvioCorreo');
        Route::get('/{id}', [Academia\EnvioCorreoController::class, 'show'])->name('envios-correo.show')->middleware('permission:ListarEnvioCorreo');
    });

    // ---------------------- Academia -------------------------- //

    // Administración ERP: academias clientes (solo desde la academia administradora)
    Route::group(["prefix" => "academias", "middleware" => ["academia.administradora"]], function () {
        Route::get('/', [Central\AcademiaController::class, 'index'])->name('academias.index')->middleware('permission:ListarAcademia');
        Route::post('/', [Central\AcademiaController::class, 'store'])->name('academias.store')->middleware('permission:CrearAcademia');
        Route::get('/{id}', [Central\AcademiaController::class, 'show'])->name('academias.show')->middleware('permission:ListarAcademia');
        Route::put('/{id}', [Central\AcademiaController::class, 'update'])->name('academias.update')->middleware('permission:ModificarAcademia');
    });
    Route::group(["middleware" => ["academia.administradora"]], function () {
        Route::get('panel-erp', [Central\PanelErpController::class, 'index'])->name('panel-erp.index')->middleware('permission:ListarPanelErp');

        Route::group(["prefix" => "facturas-academias"], function () {
            Route::get('/', [Central\FacturaAcademiaController::class, 'index'])->name('facturas-academias.index')->middleware('permission:ListarFacturaAcademia|CrearPagoAcademia');
            Route::get('/{id}', [Central\FacturaAcademiaController::class, 'show'])->name('facturas-academias.show')->middleware('permission:ListarFacturaAcademia');
            Route::put('/{id}', [Central\FacturaAcademiaController::class, 'update'])->name('facturas-academias.update')->middleware('permission:ModificarFacturaAcademia');
        });

        Route::group(["prefix" => "pagos-academias"], function () {
            Route::get('/', [Central\PagoAcademiaController::class, 'index'])->name('pagos-academias.index')->middleware('permission:ListarPagoAcademia');
            Route::post('/', [Central\PagoAcademiaController::class, 'store'])->name('pagos-academias.store')->middleware('permission:CrearPagoAcademia');
            Route::get('/{id}', [Central\PagoAcademiaController::class, 'show'])->name('pagos-academias.show')->middleware('permission:ListarPagoAcademia');
            Route::delete('/{id}', [Central\PagoAcademiaController::class, 'destroy'])->name('pagos-academias.delete')->middleware('permission:EliminarPagoAcademia');
        });
    });

    // Paquetes de clases personalizadas de los alumnos
    Route::group(["prefix" => "paquetes"], function () {
        Route::get('/', [Academia\PaqueteController::class, 'index'])->name('paquetes.index')->middleware(Permisos::lectura(['Paquete', 'Pago'], ['PagarPaquete']));
        Route::post('/', [Academia\PaqueteController::class, 'store'])->name('paquetes.store')->middleware('permission:CrearPaquete');
        Route::get('/{id}', [Academia\PaqueteController::class, 'show'])->name('paquetes.show')->middleware(Permisos::lectura(['Paquete'], ['PagarPaquete', 'RegistrarClasesPaquete']));
        Route::put('/{id}', [Academia\PaqueteController::class, 'update'])->name('paquetes.update')->middleware('permission:ModificarPaquete');
        // Planilla de clases del paquete (registrar, corregir o quitar cada clase): permiso propio RegistrarClases.
        Route::post('/{id}/clases', [Academia\PaqueteController::class, 'guardarClase'])->name('paquetes.clases.store')->middleware('permission:RegistrarClasesPaquete');
        Route::put('/{id}/clases/{claseId}', [Academia\PaqueteController::class, 'guardarClase'])->name('paquetes.clases.update')->middleware('permission:RegistrarClasesPaquete');
        Route::delete('/{id}/clases/{claseId}', [Academia\PaqueteController::class, 'eliminarClase'])->name('paquetes.clases.delete')->middleware('permission:RegistrarClasesPaquete');
        // Pago del paquete: opción aparte con su propio permiso (Pagar), distinto de crear o modificar el paquete.
        Route::post('/{id}/pagos', [Academia\PaqueteController::class, 'registrarPago'])->name('paquetes.pagos.store')->middleware('permission:PagarPaquete');
        Route::delete('/{id}/pagos/{pagoId}', [Academia\PaqueteController::class, 'eliminarPago'])->name('paquetes.pagos.delete')->middleware('permission:PagarPaquete');
        Route::delete('/{id}', [Academia\PaqueteController::class, 'destroy'])->name('paquetes.delete')->middleware('permission:EliminarPaquete');
    });

    // Clases personalizadas (privadas o de pareja)
    Route::group(["prefix" => "clases-privadas"], function () {
        Route::get('/', [Academia\ClasePrivadaController::class, 'index'])->name('clases-privadas.index')->middleware('permission:ListarClasePrivada');
        Route::post('/', [Academia\ClasePrivadaController::class, 'store'])->name('clases-privadas.store')->middleware('permission:CrearClasePrivada');
        Route::get('/{id}', [Academia\ClasePrivadaController::class, 'show'])->name('clases-privadas.show')->middleware(Permisos::lectura(['ClasePrivada'], ['PagarClasePrivada', 'RegistrarAsistenciaClasePrivada']));
        Route::put('/{id}', [Academia\ClasePrivadaController::class, 'update'])->name('clases-privadas.update')->middleware('permission:ModificarClasePrivada');
        Route::put('/{id}/registrar', [Academia\ClasePrivadaController::class, 'registrar'])->name('clases-privadas.registrar')->middleware('permission:RegistrarAsistenciaClasePrivada');
        // El cobro de la clase tiene su propio permiso (Pagar), distinto del de registrar asistencia.
        Route::put('/{id}/pago', [Academia\ClasePrivadaController::class, 'pago'])->name('clases-privadas.pago')->middleware('permission:PagarClasePrivada');
        Route::delete('/{id}', [Academia\ClasePrivadaController::class, 'destroy'])->name('clases-privadas.delete')->middleware('permission:EliminarClasePrivada');
    });

    // Cierres de la academia (vacaciones/eventos: no hay clase y los ciclos se corren)
    Route::group(["prefix" => "cierres"], function () {
        Route::get('/', [Academia\CierreController::class, 'index'])->name('cierres.index')->middleware('permission:ListarCierre');
        Route::post('/', [Academia\CierreController::class, 'store'])->name('cierres.store')->middleware('permission:CrearCierre');
        Route::get('/{id}', [Academia\CierreController::class, 'show'])->name('cierres.show')->middleware('permission:ListarCierre');
        Route::put('/{id}', [Academia\CierreController::class, 'update'])->name('cierres.update')->middleware('permission:ModificarCierre');
        Route::delete('/{id}', [Academia\CierreController::class, 'destroy'])->name('cierres.delete')->middleware('permission:EliminarCierre');
    });

    // Festivos nacionales (BD central): solo el dueño del ERP los administra
    Route::group(["prefix" => "festivos", "middleware" => ["academia.administradora"]], function () {
        Route::get('/', [Central\FestivoController::class, 'index'])->name('festivos.index')->middleware('permission:ListarFestivo');
        Route::post('/', [Central\FestivoController::class, 'store'])->name('festivos.store')->middleware('permission:CrearFestivo');
        Route::post('/generar', [Central\FestivoController::class, 'generar'])->name('festivos.generar')->middleware('permission:CrearFestivo');
        Route::get('/{id}', [Central\FestivoController::class, 'show'])->name('festivos.show')->middleware('permission:ListarFestivo');
        Route::delete('/{id}', [Central\FestivoController::class, 'destroy'])->name('festivos.delete')->middleware('permission:EliminarFestivo');
    });

    // Estado de la suscripción de la academia actual (aviso de pago dentro del ERP).
    Route::get('mi-suscripcion', [Central\SuscripcionController::class, 'show'])->name('mi-suscripcion.show');

    // Sedes de la academia. Leerlas no exige permiso: la lista alimenta el selector del encabezado de todos los usuarios.
    Route::group(["prefix" => "sedes"], function () {
        Route::get('/', [Academia\SedeController::class, 'index'])->name('sedes.index');
        Route::post('/', [Academia\SedeController::class, 'store'])->name('sedes.store')->middleware('permission:CrearSede');
        Route::get('/{id}', [Academia\SedeController::class, 'show'])->name('sedes.show');
        Route::put('/{id}', [Academia\SedeController::class, 'update'])->name('sedes.update')->middleware('permission:ModificarSede');
        Route::delete('/{id}', [Academia\SedeController::class, 'destroy'])->name('sedes.delete')->middleware('permission:EliminarSede');
    });

    // Ritmos
    Route::group(["prefix" => "ritmos"], function () {
        Route::get('/', [Academia\RitmoController::class, 'index'])->name('ritmos.index')->middleware(Permisos::lectura(['Ritmo', 'Curso']));
        Route::post('/', [Academia\RitmoController::class, 'store'])->name('ritmos.store')->middleware('permission:CrearRitmo');
        Route::get('/{id}', [Academia\RitmoController::class, 'show'])->name('ritmos.show')->middleware(Permisos::lectura(['Ritmo', 'Curso']));
        Route::put('/{id}', [Academia\RitmoController::class, 'update'])->name('ritmos.update')->middleware('permission:ModificarRitmo');
        Route::delete('/{id}', [Academia\RitmoController::class, 'destroy'])->name('ritmos.delete')->middleware('permission:EliminarRitmo');
    });

    // Tarifas: precio de los cursos (individual y pareja) y de las clases personalizadas
    Route::group(["prefix" => "tarifas"], function () {
        Route::get('/', [Academia\TarifaController::class, 'index'])->name('tarifas.index')->middleware(Permisos::lectura(['Tarifa']));
        Route::put('/', [Academia\TarifaController::class, 'update'])->name('tarifas.update')->middleware('permission:ModificarTarifa');
    });

    // Tipos de paquete de clases personalizadas (se definen en Tarifas): lista para vender un paquete.
    Route::get('planes', [Academia\PlanController::class, 'index'])->name('planes.index')->middleware(Permisos::lectura(['Paquete', 'Tarifa'], ['PagarPaquete']));

    // Profesores
    Route::group(["prefix" => "profesores"], function () {
        Route::get('/', [Academia\ProfesorController::class, 'index'])->name('profesores.index')->middleware(Permisos::lectura(['Profesor', 'Curso', 'ClasePrivada', 'Paquete'], ['RegistrarClasesPaquete']));
        Route::post('/', [Academia\ProfesorController::class, 'store'])->name('profesores.store')->middleware('permission:CrearProfesor');
        Route::get('/{id}', [Academia\ProfesorController::class, 'show'])->name('profesores.show')->middleware(Permisos::lectura(['Profesor', 'Curso', 'ClasePrivada', 'Paquete']));
        Route::put('/{id}', [Academia\ProfesorController::class, 'update'])->name('profesores.update')->middleware('permission:ModificarProfesor');
        Route::delete('/{id}', [Academia\ProfesorController::class, 'destroy'])->name('profesores.delete')->middleware('permission:EliminarProfesor');
    });

    // Alumnos
    Route::group(["prefix" => "alumnos"], function () {
        Route::get('/', [Academia\AlumnoController::class, 'index'])->name('alumnos.index')->middleware(Permisos::lectura(['Alumno', 'Curso', 'Pago', 'Paquete', 'ClasePrivada', 'Matricula', 'EnvioCorreo']));
        Route::post('/', [Academia\AlumnoController::class, 'store'])->name('alumnos.store')->middleware('permission:CrearAlumno');
        Route::get('/{id}/estado-cuenta', [Academia\AlumnoController::class, 'estadoCuenta'])->name('alumnos.estado-cuenta')->middleware('permission:EstadoCuentaAlumno|CrearMatricula');
        Route::get('/{id}', [Academia\AlumnoController::class, 'show'])->name('alumnos.show')->middleware(Permisos::lectura(['Alumno', 'Curso', 'Pago', 'Paquete', 'ClasePrivada', 'Matricula', 'EnvioCorreo']));
        Route::put('/{id}', [Academia\AlumnoController::class, 'update'])->name('alumnos.update')->middleware('permission:ModificarAlumno');
        Route::delete('/{id}', [Academia\AlumnoController::class, 'destroy'])->name('alumnos.delete')->middleware('permission:EliminarAlumno');
    });

    // Matrícula rápida: alumno + cursos + pago en un solo paso
    Route::group(["prefix" => "matriculas"], function () {
        Route::post('/cotizar', [Academia\MatriculaController::class, 'cotizar'])->name('matriculas.cotizar')->middleware('permission:CrearMatricula');
        Route::post('/', [Academia\MatriculaController::class, 'store'])->name('matriculas.store')->middleware('permission:CrearMatricula');
    });

    // Cursos
    Route::group(["prefix" => "cursos"], function () {
        Route::get('/', [Academia\CursoController::class, 'index'])->name('cursos.index')->middleware(Permisos::lectura(['Curso', 'Pago', 'Asistencia', 'TomaAsistencia', 'Matricula', 'EnvioCorreo']));
        Route::post('/', [Academia\CursoController::class, 'store'])->name('cursos.store')->middleware('permission:CrearCurso');
        Route::get('/{id}/calendario', [Academia\CalendarioController::class, 'curso'])->name('cursos.calendario')->middleware(Permisos::lectura(['Curso']));
        Route::put('/{id}/alumnos/{alumnoId}/valor-especial', [Academia\CalendarioController::class, 'valorEspecial'])->name('cursos.valor-especial')->middleware('permission:ValorEspecialCurso');
        Route::put('/{id}/alumnos/{alumnoId}/pareja', [Academia\CalendarioController::class, 'pareja'])->name('cursos.pareja')->middleware('permission:FormaDePagoCurso');
        Route::get('/{id}', [Academia\CursoController::class, 'show'])->name('cursos.show')->middleware(Permisos::lectura(['Curso', 'Pago', 'Asistencia', 'TomaAsistencia', 'Matricula', 'EnvioCorreo']));
        Route::put('/{id}', [Academia\CursoController::class, 'update'])->name('cursos.update')->middleware('permission:ModificarCurso');
        Route::delete('/{id}', [Academia\CursoController::class, 'destroy'])->name('cursos.delete')->middleware('permission:EliminarCurso');
    });

    // Pagos
    // Condonar la deuda de un alumno (el saldo queda en 0 sin registrar un pago): permiso propio en Pagos.
    Route::post('condonaciones', [Academia\CondonacionController::class, 'store'])->name('condonaciones.store')->middleware('permission:CondonarPago');
    Route::delete('condonaciones/{id}', [Academia\CondonacionController::class, 'destroy'])->name('condonaciones.delete')->middleware('permission:CondonarPago');

    Route::group(["prefix" => "pagos"], function () {
        Route::get('/', [Academia\PagoController::class, 'index'])->name('pagos.index')->middleware(Permisos::lectura(['Pago']));
        Route::post('/', [Academia\PagoController::class, 'store'])->name('pagos.store')->middleware('permission:CrearPago');
        Route::get('/{id}', [Academia\PagoController::class, 'show'])->name('pagos.show')->middleware(Permisos::lectura(['Pago']));
        Route::put('/{id}', [Academia\PagoController::class, 'update'])->name('pagos.update')->middleware('permission:ModificarPago');
        Route::delete('/{id}', [Academia\PagoController::class, 'destroy'])->name('pagos.delete')->middleware('permission:EliminarPago');
    });

    // Asistencias (la opción "Tomar asistencia" guarda por estas mismas rutas con sus propios permisos)
    Route::group(["prefix" => "asistencias"], function () {
        Route::get('/', [Academia\AsistenciaController::class, 'index'])->name('asistencias.index')->middleware(Permisos::lectura(['Asistencia', 'TomaAsistencia']));
        Route::post('/', [Academia\AsistenciaController::class, 'store'])->name('asistencias.store')->middleware('permission:CrearAsistencia|CrearTomaAsistencia');
        Route::get('/preparar', [Academia\AsistenciaController::class, 'preparar'])->name('asistencias.preparar')->middleware(Permisos::lectura(['Asistencia', 'TomaAsistencia']));
        Route::get('/{id}', [Academia\AsistenciaController::class, 'show'])->name('asistencias.show')->middleware(Permisos::lectura(['Asistencia', 'TomaAsistencia']));
        Route::put('/{id}', [Academia\AsistenciaController::class, 'update'])->name('asistencias.update')->middleware('permission:ModificarAsistencia|ModificarTomaAsistencia');
        Route::delete('/{id}', [Academia\AsistenciaController::class, 'destroy'])->name('asistencias.delete')->middleware('permission:EliminarAsistencia');
    });

    // Dashboard de academia
    Route::get('academia/dashboard', [Academia\DashboardController::class, 'index'])->name('academia.dashboard')->middleware('permission:ListarDashboardAcademia');
});