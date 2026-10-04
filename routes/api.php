<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Seguridad;
use App\Http\Controllers\Academia;
use App\Http\Controllers\Central;
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
        // El formulario de reservas lista clientes: se permite también a quien gestiona reservas.
        Route::get('/', [Seguridad\UsuarioController::class,'index'])->name('usuarios.index')->middleware('permission:ListarUsuario|ListarReserva');
        Route::post('/', [Seguridad\UsuarioController::class,'store'])->name('usuarios.store')->middleware('permission:CrearUsuario');
        Route::get('/{id}', [Seguridad\UsuarioController::class,'show'])->name('usuarios.show')->middleware('permission:ListarUsuario');
        Route::put('/cambio-clave', [Seguridad\UsuarioController::class,'changePassword'])->name('usuarios.changePassword')->middleware('permission:CambiarClave');
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

    // Auditoría y Parametrización no tienen permisos propios en BD: quedan solo para el administrador.
    // Auditoria Tablas
    Route::group(["prefix" => "auditoria-tablas", "middleware" => ["role:SuperSu"]],function(){
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
        Route::get('/', [Academia\PlantillaCorreoController::class, 'index'])->name('plantillas-correo.index')->middleware('permission:ListarPlantillaCorreo');
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

    // Paquetes de clases de los alumnos (cursos grupales y clases privadas)
    Route::group(["prefix" => "paquetes"], function () {
        Route::get('/', [Academia\PaqueteController::class, 'index'])->name('paquetes.index')->middleware('permission:ListarPaquete|CrearPago');
        Route::post('/', [Academia\PaqueteController::class, 'store'])->name('paquetes.store')->middleware('permission:CrearPaquete');
        Route::get('/{id}', [Academia\PaqueteController::class, 'show'])->name('paquetes.show')->middleware('permission:ListarPaquete');
        Route::put('/{id}', [Academia\PaqueteController::class, 'update'])->name('paquetes.update')->middleware('permission:ModificarPaquete');
        Route::delete('/{id}', [Academia\PaqueteController::class, 'destroy'])->name('paquetes.delete')->middleware('permission:EliminarPaquete');
    });

    // Clases personalizadas (privadas o de pareja)
    Route::group(["prefix" => "clases-privadas"], function () {
        Route::get('/', [Academia\ClasePrivadaController::class, 'index'])->name('clases-privadas.index')->middleware('permission:ListarClasePrivada');
        Route::post('/', [Academia\ClasePrivadaController::class, 'store'])->name('clases-privadas.store')->middleware('permission:CrearClasePrivada');
        Route::get('/{id}', [Academia\ClasePrivadaController::class, 'show'])->name('clases-privadas.show')->middleware('permission:ListarClasePrivada');
        Route::put('/{id}', [Academia\ClasePrivadaController::class, 'update'])->name('clases-privadas.update')->middleware('permission:ModificarClasePrivada');
        Route::put('/{id}/registrar', [Academia\ClasePrivadaController::class, 'registrar'])->name('clases-privadas.registrar')->middleware('permission:ModificarClasePrivada');
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

    // Sedes de la academia (la lista ligera alimenta el selector del encabezado)
    Route::group(["prefix" => "sedes"], function () {
        Route::get('/', [Academia\SedeController::class, 'index'])->name('sedes.index');
        Route::post('/', [Academia\SedeController::class, 'store'])->name('sedes.store')->middleware('permission:CrearSede');
        Route::get('/{id}', [Academia\SedeController::class, 'show'])->name('sedes.show');
        Route::put('/{id}', [Academia\SedeController::class, 'update'])->name('sedes.update')->middleware('permission:ModificarSede');
        Route::delete('/{id}', [Academia\SedeController::class, 'destroy'])->name('sedes.delete')->middleware('permission:EliminarSede');
    });

    // Ritmos
    Route::group(["prefix" => "ritmos"], function () {
        Route::get('/', [Academia\RitmoController::class, 'index'])->name('ritmos.index');
        Route::post('/', [Academia\RitmoController::class, 'store'])->name('ritmos.store')->middleware('permission:CrearRitmo');
        Route::get('/{id}', [Academia\RitmoController::class, 'show'])->name('ritmos.show');
        Route::put('/{id}', [Academia\RitmoController::class, 'update'])->name('ritmos.update')->middleware('permission:ModificarRitmo');
        Route::delete('/{id}', [Academia\RitmoController::class, 'destroy'])->name('ritmos.delete')->middleware('permission:EliminarRitmo');
    });

    // Planes
    Route::group(["prefix" => "planes"], function () {
        Route::get('/', [Academia\PlanController::class, 'index'])->name('planes.index');
        Route::post('/', [Academia\PlanController::class, 'store'])->name('planes.store')->middleware('permission:CrearPlan');
        Route::get('/{id}', [Academia\PlanController::class, 'show'])->name('planes.show');
        Route::put('/{id}', [Academia\PlanController::class, 'update'])->name('planes.update')->middleware('permission:ModificarPlan');
        Route::delete('/{id}', [Academia\PlanController::class, 'destroy'])->name('planes.delete')->middleware('permission:EliminarPlan');
    });

    // Profesores
    Route::group(["prefix" => "profesores"], function () {
        Route::get('/', [Academia\ProfesorController::class, 'index'])->name('profesores.index');
        Route::post('/', [Academia\ProfesorController::class, 'store'])->name('profesores.store')->middleware('permission:CrearProfesor');
        Route::get('/{id}', [Academia\ProfesorController::class, 'show'])->name('profesores.show');
        Route::put('/{id}', [Academia\ProfesorController::class, 'update'])->name('profesores.update')->middleware('permission:ModificarProfesor');
        Route::delete('/{id}', [Academia\ProfesorController::class, 'destroy'])->name('profesores.delete')->middleware('permission:EliminarProfesor');
    });

    // Alumnos
    Route::group(["prefix" => "alumnos"], function () {
        Route::get('/', [Academia\AlumnoController::class, 'index'])->name('alumnos.index');
        Route::post('/', [Academia\AlumnoController::class, 'store'])->name('alumnos.store')->middleware('permission:CrearAlumno');
        Route::get('/{id}/estado-cuenta', [Academia\AlumnoController::class, 'estadoCuenta'])->name('alumnos.estado-cuenta');
        Route::get('/{id}', [Academia\AlumnoController::class, 'show'])->name('alumnos.show');
        Route::put('/{id}', [Academia\AlumnoController::class, 'update'])->name('alumnos.update')->middleware('permission:ModificarAlumno');
        Route::delete('/{id}', [Academia\AlumnoController::class, 'destroy'])->name('alumnos.delete')->middleware('permission:EliminarAlumno');
    });

    // Cursos
    Route::group(["prefix" => "cursos"], function () {
        Route::get('/', [Academia\CursoController::class, 'index'])->name('cursos.index');
        Route::post('/', [Academia\CursoController::class, 'store'])->name('cursos.store')->middleware('permission:CrearCurso');
        Route::get('/{id}/calendario', [Academia\CalendarioController::class, 'curso'])->name('cursos.calendario');
        Route::put('/{id}/alumnos/{alumnoId}/modalidad', [Academia\CalendarioController::class, 'modalidad'])->name('cursos.modalidad')->middleware('permission:ModificarCurso');
        Route::put('/{id}/alumnos/{alumnoId}/pareja', [Academia\CalendarioController::class, 'pareja'])->name('cursos.pareja')->middleware('permission:ModificarCurso');
        Route::get('/{id}', [Academia\CursoController::class, 'show'])->name('cursos.show');
        Route::put('/{id}', [Academia\CursoController::class, 'update'])->name('cursos.update')->middleware('permission:ModificarCurso');
        Route::delete('/{id}', [Academia\CursoController::class, 'destroy'])->name('cursos.delete')->middleware('permission:EliminarCurso');
    });

    // Pagos
    Route::group(["prefix" => "pagos"], function () {
        Route::get('/', [Academia\PagoController::class, 'index'])->name('pagos.index');
        Route::post('/', [Academia\PagoController::class, 'store'])->name('pagos.store')->middleware('permission:CrearPago');
        Route::get('/{id}', [Academia\PagoController::class, 'show'])->name('pagos.show');
        Route::put('/{id}', [Academia\PagoController::class, 'update'])->name('pagos.update')->middleware('permission:ModificarPago');
        Route::delete('/{id}', [Academia\PagoController::class, 'destroy'])->name('pagos.delete')->middleware('permission:EliminarPago');
    });

    // Asistencias
    Route::group(["prefix" => "asistencias"], function () {
        Route::get('/', [Academia\AsistenciaController::class, 'index'])->name('asistencias.index');
        Route::post('/', [Academia\AsistenciaController::class, 'store'])->name('asistencias.store')->middleware('permission:CrearAsistencia');
        Route::get('/preparar', [Academia\AsistenciaController::class, 'preparar'])->name('asistencias.preparar');
        Route::get('/{id}', [Academia\AsistenciaController::class, 'show'])->name('asistencias.show');
        Route::put('/{id}', [Academia\AsistenciaController::class, 'update'])->name('asistencias.update')->middleware('permission:ModificarAsistencia');
        Route::delete('/{id}', [Academia\AsistenciaController::class, 'destroy'])->name('asistencias.delete')->middleware('permission:EliminarAsistencia');
    });

    // Dashboard de academia
    Route::get('academia/dashboard', [Academia\DashboardController::class, 'index'])->name('academia.dashboard');
});