<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Seguridad;
use App\Http\Controllers\Parametrizacion;
use App\Http\Controllers\Inversiones;
use App\Http\Controllers\Turismo;
use App\Http\Controllers\Experiencias;
use App\Http\Controllers\Reservas;
use App\Http\Controllers\Promociones;
use App\Http\Controllers\Proveedores;
use App\Http\Controllers\Academia;
use App\Http\Controllers\Resenas;
use App\Http\Controllers\Publico;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\ExtractoController;
use App\Http\Controllers\PortafolioController;
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


// ---------------------- Portal público (sin autenticación) -------------------------- //
Route::group(["prefix" => "publico", "middleware" => ["throttle:120,1"]], function () {
    Route::get('/destinos', [Publico\PortalController::class, 'destinos'])->name('publico.destinos');
    Route::get('/categorias', [Publico\PortalController::class, 'categorias'])->name('publico.categorias');
    Route::get('/experiencias', [Publico\PortalController::class, 'experiencias'])->name('publico.experiencias');
    Route::get('/experiencias/{slug}', [Publico\PortalController::class, 'experiencia'])->name('publico.experiencia');
    Route::get('/promociones', [Publico\PortalController::class, 'promociones'])->name('publico.promociones');
    Route::post('/registro', [Publico\RegistroController::class, 'store'])->middleware('throttle:10,1,registro')->name('publico.registro');
});

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
        Route::put('/proveedor', [Seguridad\CuentaController::class, 'actualizarProveedor'])->name('cuenta.proveedor');
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

    // ---------------------- Parametrizacion -------------------------- //

    // Parametros correos
    Route::group(["prefix" => "parametros-correo", "middleware" => ["role:SuperSu"]],function(){
        Route::get('/', [Parametrizacion\ParametroCorreoController::class,'index'])->name('parametros_correo.index');
        Route::post('/', [Parametrizacion\ParametroCorreoController::class,'store'])->name('parametros_correo.store');
        Route::get('/{id}', [Parametrizacion\ParametroCorreoController::class,'show'])->name('parametros_correo.show');
        Route::put('/{id}', [Parametrizacion\ParametroCorreoController::class,'update'])->name('parametros_correo.update');
        Route::delete('/{id}', [Parametrizacion\ParametroCorreoController::class,'destroy'])->name('parametros_correo.delete');
    });

    // Parametros Constantes
    Route::group(["prefix" => "parametros-constantes", "middleware" => ["role:SuperSu"]],function(){
        Route::get('/', [Parametrizacion\ParametroConstanteController::class,'index'])->name('parametros-constantes.index');
        Route::post('/', [Parametrizacion\ParametroConstanteController::class,'store'])->name('parametros-constantes.store');
        Route::get('/consultar', [Parametrizacion\ParametroConstanteController::class,'consultar'])->name('parametros-constantes.consultar');
        Route::get('/{id}', [Parametrizacion\ParametroConstanteController::class,'show'])->name('parametros-constantes.show');
        Route::put('/{id}', [Parametrizacion\ParametroConstanteController::class,'update'])->name('parametros-constantes.update');
        Route::delete('/{id}', [Parametrizacion\ParametroConstanteController::class,'destroy'])->name('parametros-constantes.delete');
    });


    // Ciudades
    Route::group(["prefix" => "ciudades"],function(){
        Route::get('/', [Parametrizacion\CiudadController::class,'index'])->name('ciudades.index');
        Route::post('/', [Parametrizacion\CiudadController::class,'store'])->name('ciudades.store');
        Route::get('/{id}', [Parametrizacion\CiudadController::class,'show'])->name('ciudades.show');
        Route::put('/{id}', [Parametrizacion\CiudadController::class,'update'])->name('ciudades.update');
        Route::delete('/{id}', [Parametrizacion\CiudadController::class,'destroy'])->name('ciudades.delete');
    });

    // Tipos de Proyectos
    Route::group(["prefix" => "tipos-proyectos"],function(){
        Route::get('/', [Parametrizacion\TipoProyectoController::class,'index'])->name('tipos-proyectos.index');
        Route::post('/', [Parametrizacion\TipoProyectoController::class,'store'])->name('tipos-proyectos.store');
        Route::get('/{id}', [Parametrizacion\TipoProyectoController::class,'show'])->name('tipos-proyectos.show');
        Route::put('/{id}', [Parametrizacion\TipoProyectoController::class,'update'])->name('tipos-proyectos.update');
        Route::delete('/{id}', [Parametrizacion\TipoProyectoController::class,'destroy'])->name('tipos-proyectos.delete');
    });

     // Sectores de Proyectos
     Route::group(["prefix" => "sectores-proyectos"],function(){
        Route::get('/', [Parametrizacion\SectorProyectoController::class,'index'])->name('sectores-proyectos.index');
        Route::post('/', [Parametrizacion\SectorProyectoController::class,'store'])->name('sectores-proyectos.store');
        Route::get('/{id}', [Parametrizacion\SectorProyectoController::class,'show'])->name('sectores-proyectos.show');
        Route::put('/{id}', [Parametrizacion\SectorProyectoController::class,'update'])->name('sectores-proyectos.update');
        Route::delete('/{id}', [Parametrizacion\SectorProyectoController::class,'destroy'])->name('sectores-proyectos.delete');
    });


    // Lista de documentos
    Route::group(["prefix" => "listas-documentos"],function(){
        Route::get('/', [Parametrizacion\ListaDocumentoController::class,'index'])->name('listas-documentos.index');
        Route::post('/', [Parametrizacion\ListaDocumentoController::class,'store'])->name('listas-documentos.store');
        Route::get('/{id}', [Parametrizacion\ListaDocumentoController::class,'show'])->name('listas-documentos.show');
        Route::put('/{id}', [Parametrizacion\ListaDocumentoController::class,'update'])->name('listas-documentos.update');
        Route::delete('/{id}', [Parametrizacion\ListaDocumentoController::class,'destroy'])->name('listas-documentos.delete');
    });
    
    // Compañias
    Route::group(["prefix" => "companias"],function(){
        Route::get('/', [Parametrizacion\CompaniaController::class,'index'])->name('companias.index');
        Route::post('/', [Parametrizacion\CompaniaController::class,'store'])->name('companias.store');
        Route::get('//{id}', [Parametrizacion\CompaniaController::class,'show'])->name('companias.show');
        Route::put('/{id}', [Parametrizacion\CompaniaController::class,'update'])->name('companias.update');
        Route::delete('/{id}', [Parametrizacion\CompaniaController::class,'destroy'])->name('companias.delete');
    });

    // Compañias Documentos
    Route::group(["prefix" => "companias-documentos"],function(){
        Route::get('/', [Parametrizacion\CompaniaDocumentoController::class,'index'])->name('companias-documentos.index');
        Route::post('/', [Parametrizacion\CompaniaDocumentoController::class,'store'])->name('companias-documentos.store');
        Route::get('/{id}', [Parametrizacion\CompaniaDocumentoController::class,'show'])->name('companias-documentos.show');
        Route::put('/{id}', [Parametrizacion\CompaniaDocumentoController::class,'update'])->name('companias-documentos.update');
        Route::delete('/{id}/{compania_id}', [Parametrizacion\CompaniaDocumentoController::class,'destroy'])->name('companias-documentos.delete');
    });

    // Gestor
    Route::group(["prefix" => "gestores"],function(){
        Route::get('/', [Parametrizacion\GestorController::class,'index'])->name('gestores.index');
        Route::post('/', [Parametrizacion\GestorController::class,'store'])->name('gestores.store');
        Route::get('/{id}', [Parametrizacion\GestorController::class,'show'])->name('gestores.show');
        Route::put('/{id}', [Parametrizacion\GestorController::class,'update'])->name('gestores.update');
        Route::delete('/{id}', [Parametrizacion\GestorController::class,'destroy'])->name('gestores.delete');
    });

    // Gestor Documentos
    Route::group(["prefix" => "gestores-documentos"],function(){
        Route::get('/', [Parametrizacion\GestorDocumentoController::class,'index'])->name('gestores-documentos.index');
        Route::post('/', [Parametrizacion\GestorDocumentoController::class,'store'])->name('gestores-documentos.store');
        Route::get('/{id}', [Parametrizacion\GestorDocumentoController::class,'show'])->name('gestores-documentos.show');
        Route::put('/{id}', [Parametrizacion\GestorDocumentoController::class,'update'])->name('gestores-documentos.update');
        Route::delete('/{id}/{gestor_id}', [Parametrizacion\GestorDocumentoController::class,'destroy'])->name('gestores-documentos.delete');
    });

    // Categorias de Inversión
    Route::group(["prefix" => "tipos-inversiones"],function(){
        Route::get('/', [Parametrizacion\TipoInversionController::class,'index'])->name('tipos-inversiones.index');
        Route::post('/', [Parametrizacion\TipoInversionController::class,'store'])->name('tipos-inversiones.store');
        Route::get('/{id}', [Parametrizacion\TipoInversionController::class,'show'])->name('tipos-inversiones.show');
        Route::put('/{id}', [Parametrizacion\TipoInversionController::class,'update'])->name('tipos-inversiones.update');
        Route::delete('/{id}', [Parametrizacion\TipoInversionController::class,'destroy'])->name('tipos-inversiones.delete');
    });

    // Proyectos
    Route::group(["prefix" => "proyectos"],function(){
        Route::get('/', [Parametrizacion\ProyectoController::class,'index'])->name('proyectos.index');
        Route::get('/avances', [Parametrizacion\ProyectoController::class, 'avanceProyectos'])->name('proyectos.avanceProyectos');
        Route::post('/', [Parametrizacion\ProyectoController::class,'store'])->name('proyectos.store');
        Route::get('/{id}', [Parametrizacion\ProyectoController::class,'show'])->name('proyectos.show');
        Route::put('/{id}', [Parametrizacion\ProyectoController::class,'update'])->name('proyectos.update');
        Route::delete('/{id}', [Parametrizacion\ProyectoController::class,'destroy'])->name('proyectos.delete');
    });

    // Proyectos Documentos
    Route::group(["prefix" => "proyectos-documentos"],function(){
        Route::get('/', [Parametrizacion\ProyectoDocumentoController::class,'index'])->name('proyectos-documentos.index');
        Route::post('/', [Parametrizacion\ProyectoDocumentoController::class,'store'])->name('proyectos-documentos.store');
        Route::get('/{id}', [Parametrizacion\ProyectoDocumentoController::class,'show'])->name('proyectos-documentos.show');
        Route::put('/{id}', [Parametrizacion\ProyectoDocumentoController::class,'update'])->name('proyectos-documentos.update');
        Route::delete('/{id}/{proyecto_id}', [Parametrizacion\ProyectoDocumentoController::class,'destroy'])->name('proyectos-documentos.delete');
    });

    // Inversionistas
    Route::group(["prefix" => "inversionistas"],function(){
        Route::get('/', [Parametrizacion\InversionistaController::class,'index'])->name('inversionistas.index');
        Route::post('/', [Parametrizacion\InversionistaController::class,'store'])->name('inversionistas.store');
        Route::get('/{id}', [Parametrizacion\InversionistaController::class,'show'])->name('inversionistas.show');
        Route::put('/{id}', [Parametrizacion\InversionistaController::class,'update'])->name('inversionistas.update');
        Route::delete('/{id}', [Parametrizacion\InversionistaController::class,'destroy'])->name('inversionistas.delete');
    });

    // Inversionistas contactos
    Route::group(["prefix" => "inversionistas-contactos"],function(){
        Route::get('/{inversionista_id}', [Parametrizacion\InversionistaContactoController::class,'index'])->name('inversionistas-contactos.index');
        Route::post('/', [Parametrizacion\InversionistaContactoController::class,'store'])->name('inversionistas-contactos.store');
        Route::get('/{inversionista_id}/{id}', [Parametrizacion\InversionistaContactoController::class,'show'])->name('inversionistas-contactos.show');
        Route::put('/{id}', [Parametrizacion\InversionistaContactoController::class,'update'])->name('inversionistas-contactos.update');
        Route::delete('/{id}', [Parametrizacion\InversionistaContactoController::class,'destroy'])->name('inversionistas-contactos.delete');
    });

    // Inversionistas contactos legal
    Route::group(["prefix" => "inversionistas-contactos-legales"],function(){
        Route::get('/contacto', [Parametrizacion\InversionistaContactoLegalController::class,'indexDoc'])->name('inversionistas-contactos-legales.indexDoc');
        Route::get('/{inversionista_id}', [Parametrizacion\InversionistaContactoLegalController::class,'index'])->name('inversionistas-contactos-legales.index');
        Route::post('/', [Parametrizacion\InversionistaContactoLegalController::class,'store'])->name('inversionistas-contactos-legales.store');
        Route::get('/{inversionista_id}/{id}', [Parametrizacion\InversionistaContactoLegalController::class,'show'])->name('inversionistas-contactos-legales.show');
        Route::put('/{id}', [Parametrizacion\InversionistaContactoLegalController::class,'update'])->name('inversionistas-contactos-legales.update');
        Route::delete('/{id}', [Parametrizacion\InversionistaContactoLegalController::class,'destroy'])->name('inversionistas-contactos-legales.delete');
    });
    
    // Inversionistas Documentos
    Route::group(["prefix" => "inversionistas-documentos"],function(){
        Route::get('/', [Parametrizacion\InversionistaDocumentoController::class,'index'])->name('inversionistas-documentos.index');
        Route::post('/', [Parametrizacion\InversionistaDocumentoController::class,'store'])->name('inversionistas-documentos.store');
        Route::get('/{id}', [Parametrizacion\InversionistaDocumentoController::class,'show'])->name('inversionistas-documentos.show');
        Route::put('/', [Parametrizacion\InversionistaDocumentoController::class,'update'])->name('inversionistas-documentos.update');
        Route::delete('/{id}/{proyecto_id}', [Parametrizacion\InversionistaDocumentoController::class,'destroy'])->name('inversionistas-documentos.delete');
    });

    // Vehiculos Inversión
    Route::group(["prefix" => "vehiculos-inversion"],function(){
        Route::get('/', [Parametrizacion\VehiculoInversionController::class,'index'])->name('vehiculos-inversion.index');
        Route::post('/', [Parametrizacion\VehiculoInversionController::class,'store'])->name('vehiculos-inversion.store');
        Route::get('/{id}', [Parametrizacion\VehiculoInversionController::class,'show'])->name('vehiculos-inversion.show');
        Route::put('/{id}', [Parametrizacion\VehiculoInversionController::class,'update'])->name('vehiculos-inversion.update');
        Route::delete('/{id}', [Parametrizacion\VehiculoInversionController::class,'destroy'])->name('vehiculos-inversion.delete');
    });

    // Etapas Proyectos
    Route::group(["prefix" => "etapas-proyectos"],function(){
        Route::get('/', [Parametrizacion\EtapaProyectoController::class,'index'])->name('etapas-proyectos.index');
        Route::post('/', [Parametrizacion\EtapaProyectoController::class,'store'])->name('etapas-proyectos.store');
        Route::get('/{id}', [Parametrizacion\EtapaProyectoController::class,'show'])->name('etapas-proyectos.show');
        Route::put('/{id}', [Parametrizacion\EtapaProyectoController::class,'update'])->name('etapas-proyectos.update');
        Route::delete('/{id}', [Parametrizacion\EtapaProyectoController::class,'destroy'])->name('etapas-proyectos.delete');
    });

    // Actividades Proyectos
    Route::group(["prefix" => "actividades-proyectos"],function(){
        Route::get('/', [Parametrizacion\ActividadProyectoController::class,'index'])->name('actividades-proyectos.index');
        Route::post('/', [Parametrizacion\ActividadProyectoController::class,'store'])->name('actividades-proyectos.store');
        Route::get('/{id}', [Parametrizacion\ActividadProyectoController::class,'show'])->name('actividades-proyectos.show');
        Route::put('/{id}', [Parametrizacion\ActividadProyectoController::class,'update'])->name('actividades-proyectos.update');
        Route::delete('/{id}', [Parametrizacion\ActividadProyectoController::class,'destroy'])->name('actividades-proyectos.delete');
    });

    // Desembolsos Proyectos
    Route::group(["prefix" => "desembolsos-proyectos"],function(){
        Route::get('/', [Parametrizacion\DesembolsoProyectoController::class,'index'])->name('desembolsos-proyectos.index');
        Route::post('/', [Parametrizacion\DesembolsoProyectoController::class,'store'])->name('desembolsos-proyectos.store');
        Route::get('/{id}', [Parametrizacion\DesembolsoProyectoController::class,'show'])->name('desembolsos-proyectos.show');
        Route::put('/{id}', [Parametrizacion\DesembolsoProyectoController::class,'update'])->name('desembolsos-proyectos.update');
        Route::delete('/{id}', [Parametrizacion\DesembolsoProyectoController::class,'destroy'])->name('desembolsos-proyectos.delete');
    });

    // Condiciones plazo
    Route::group(["prefix" => "condiciones-plazo"],function(){
        Route::get('/', [Parametrizacion\CondicionPlazoController::class,'index'])->name('condiciones-plazo.index');
        Route::post('/', [Parametrizacion\CondicionPlazoController::class,'store'])->name('condiciones-plazo.store');
        Route::get('/{id}', [Parametrizacion\CondicionPlazoController::class,'show'])->name('condiciones-plazo.show');
        Route::put('/{id}', [Parametrizacion\CondicionPlazoController::class,'update'])->name('condiciones-plazo.update');
        Route::delete('/{id}', [Parametrizacion\CondicionPlazoController::class,'destroy'])->name('condiciones-plazo.delete');
    });

    // Conceptos Proyectos
    Route::group(["prefix" => "conceptos-proyectos"],function(){
        Route::get('/', [Parametrizacion\ConceptoProyectoController::class,'index'])->name('conceptos-proyectos.index');
        Route::post('/', [Parametrizacion\ConceptoProyectoController::class,'store'])->name('conceptos-proyectos.store');
        Route::get('/{id}', [Parametrizacion\ConceptoProyectoController::class,'show'])->name('conceptos-proyectos.show');
        Route::put('/{id}', [Parametrizacion\ConceptoProyectoController::class,'update'])->name('conceptos-proyectos.update');
        Route::delete('/{id}', [Parametrizacion\ConceptoProyectoController::class,'destroy'])->name('conceptos-proyectos.delete');
    });
    
    // Parametros Mensuales
    Route::group(["prefix" => "parametros-mensuales"],function(){
        Route::get('/', [Parametrizacion\ParametroMensualController::class,'index'])->name('parametros-mensuales.index');
        Route::post('/', [Parametrizacion\ParametroMensualController::class,'store'])->name('parametros-mensuales.store');
        Route::get('/{id}', [Parametrizacion\ParametroMensualController::class,'show'])->name('parametros-mensuales.show');
        Route::put('/{id}', [Parametrizacion\ParametroMensualController::class,'update'])->name('parametros-mensuales.update');
        Route::delete('/{id}', [Parametrizacion\ParametroMensualController::class,'destroy'])->name('parametros-mensuales.delete');
    });

    // Actividades Por Proyectos
    Route::group(["prefix" => "actividades-por-proyectos"],function(){
        Route::get('/{proyecto_id}', [Parametrizacion\ActividadPorProyectoController::class,'index'])->name('actividades-por-proyectos.index');
        Route::post('/', [Parametrizacion\ActividadPorProyectoController::class,'store'])->name('actividades-por-proyectos.store');
        Route::get('/show/{id}', [Parametrizacion\ActividadPorProyectoController::class,'show'])->name('actividades-por-proyectos.show');
        Route::put('/{id}', [Parametrizacion\ActividadPorProyectoController::class,'update'])->name('actividades-por-proyectos.update');
        Route::delete('/{id}', [Parametrizacion\ActividadPorProyectoController::class,'destroy'])->name('actividades-por-proyectos.delete');
    });

    // Simulación Por Proyectos
    Route::group(["prefix" => "simulaciones-por-proyectos"],function(){
        Route::get('/{proyecto_id}', [Parametrizacion\ProyectoSimulacionController::class,'index'])->name('simulaciones-por-proyectos.index');
        Route::post('/', [Parametrizacion\ProyectoSimulacionController::class,'store'])->name('simulaciones-por-proyectos.store');
        Route::post('/guardar', [Parametrizacion\ProyectoSimulacionController::class,'guardar'])->name('simulaciones-por-proyectos.guardar');
        Route::post('/inicializar-edicion', [Parametrizacion\ProyectoSimulacionController::class, 'initUpdateSimulacion'])->name('simulaciones-por-proyectos.initUpdateSimulacion');
        Route::post('/inicializar-inversionista', [Parametrizacion\ProyectoSimulacionController::class, 'initSimulacionInversionista'])->name('simulaciones-por-proyectos.initSimulacionInversionista');
        Route::post('/guardar-inversionista', [Parametrizacion\ProyectoSimulacionController::class, 'guardarSimulacionInversionista'])->name('simulaciones-por-proyectos.guardarSimulacionInversionista');
        Route::get('/show/{id}', [Parametrizacion\ProyectoSimulacionController::class,'show'])->name('simulaciones-por-proyectos.show');
        Route::put('/{id}', [Parametrizacion\ProyectoSimulacionController::class,'update'])->name('simulaciones-por-proyectos.update');
        Route::delete('/{id}', [Parametrizacion\ProyectoSimulacionController::class,'destroy'])->name('simulaciones-por-proyectos.delete');
    });

    // Conceptos Por Proyectos
    Route::group(["prefix" => "conceptos-por-proyectos"],function(){
        Route::get('/', [Parametrizacion\ConceptoPorProyectoController::class,'index'])->name('conceptos-por-proyectos.index');
        Route::post('/', [Parametrizacion\ConceptoPorProyectoController::class,'store'])->name('conceptos-por-proyectos.store');
        Route::post('/copiar', [Parametrizacion\ConceptoPorProyectoController::class, 'copiar'])->name('conceptos-por-proyectos.copiar');
        Route::post('/calcular', [Parametrizacion\ConceptoPorProyectoController::class, 'calcular'])->name('conceptos-por-proyectos.calcular');
        Route::post('/guardar', [Parametrizacion\ConceptoPorProyectoController::class, 'guardar'])->name('conceptos-por-proyectos.guardar');
        Route::get('/show/{id}', [Parametrizacion\ConceptoPorProyectoController::class,'show'])->name('conceptos-por-proyectos.show');
        Route::get('/conceptos-simulacion', [Parametrizacion\ConceptoPorProyectoController::class, 'conceptosSimulacion'])->name('conceptos-por-proyectos.simulaciones');
        Route::post('/calcular-simulacion', [Parametrizacion\ConceptoPorProyectoController::class, 'calcularSimulacion'])->name('conceptos-por-proyectos.calcularSimulacion');       
        Route::put('/{id}', [Parametrizacion\ConceptoPorProyectoController::class,'update'])->name('conceptos-por-proyectos.update');
        Route::delete('/{id}', [Parametrizacion\ConceptoPorProyectoController::class,'destroy'])->name('conceptos-por-proyectos.delete');
    });

    // Comunidades Energeticas
    Route::group(["prefix" => "comunidades-energeticas"],function(){
        Route::get('/', [Parametrizacion\ComunidadEnergeticaController::class,'index'])->name('comunidades-energeticas.index');
        Route::post('/', [Parametrizacion\ComunidadEnergeticaController::class,'store'])->name('comunidades-energeticas.store');
        Route::get('/{id}', [Parametrizacion\ComunidadEnergeticaController::class,'show'])->name('comunidades-energeticas.show');
        Route::put('/{id}', [Parametrizacion\ComunidadEnergeticaController::class,'update'])->name('comunidades-energeticas.update');
        Route::delete('/{id}', [Parametrizacion\ComunidadEnergeticaController::class,'destroy'])->name('comunidades-energeticas.delete');
    });

    // Fotos Por Proyectos
    Route::group(["prefix" => "proyectos-fotos"],function(){
        Route::get('/', [Parametrizacion\ProyectoFotoController::class,'index'])->name('proyectos-fotos.index');
        Route::post('/', [Parametrizacion\ProyectoFotoController::class,'store'])->name('proyectos-fotos.store');
        Route::get('/{id}', [Parametrizacion\ProyectoFotoController::class,'show'])->name('proyectos-fotos.show');
        Route::put('/{id}', [Parametrizacion\ProyectoFotoController::class,'update'])->name('proyectos-fotos.update');
        Route::delete('/{id}', [Parametrizacion\ProyectoFotoController::class,'destroy'])->name('proyectos-fotos.delete');
    });

    // Bancos
    Route::group(["prefix" => "bancos"],function(){
        Route::get('/', [Parametrizacion\BancoController::class,'index'])->name('bancos.index');
        Route::post('/', [Parametrizacion\BancoController::class,'store'])->name('bancos.store');
        Route::get('//{id}', [Parametrizacion\BancoController::class,'show'])->name('bancos.show');
        Route::put('/{id}', [Parametrizacion\BancoController::class,'update'])->name('bancos.update');
        Route::delete('/{id}', [Parametrizacion\BancoController::class,'destroy'])->name('bancos.delete');
    });

    // Proveedores
    Route::group(["prefix" => "proveedores"],function(){
        Route::get('/', [Parametrizacion\ProveedorController::class,'index'])->name('proveedores.index');
        Route::post('/', [Parametrizacion\ProveedorController::class,'store'])->name('proveedores.store');
        Route::get('//{id}', [Parametrizacion\ProveedorController::class,'show'])->name('proveedores.show');
        Route::put('/{id}', [Parametrizacion\ProveedorController::class,'update'])->name('proveedores.update');
        Route::delete('/{id}', [Parametrizacion\ProveedorController::class,'destroy'])->name('proveedores.delete');
    });

    // Proveedores Documentos
    Route::group(["prefix" => "proveedores-documentos"],function(){
        Route::get('/', [Parametrizacion\ProveedorDocumentoController::class,'index'])->name('proveedores-documentos.index');
        Route::post('/', [Parametrizacion\ProveedorDocumentoController::class,'store'])->name('proveedores-documentos.store');
        Route::get('/{id}', [Parametrizacion\ProveedorDocumentoController::class,'show'])->name('proveedores-documentos.show');
        Route::put('/{id}', [Parametrizacion\ProveedorDocumentoController::class,'update'])->name('proveedores-documentos.update');
        Route::delete('/{id}/{proveedor_id}', [Parametrizacion\ProveedorDocumentoController::class,'destroy'])->name('proveedores-documentos.delete');
    });

    // Precios Proyectados
    Route::group(["prefix" => "precios-proyectados"],function(){
        Route::get('/', [Parametrizacion\PrecioProyectadoController::class,'index'])->name('precios-proyectados.index');
        Route::post('/', [Parametrizacion\PrecioProyectadoController::class,'store'])->name('precios-proyectados.store');
        Route::get('//{id}', [Parametrizacion\PrecioProyectadoController::class,'show'])->name('precios-proyectados.show');
        Route::put('/{id}', [Parametrizacion\PrecioProyectadoController::class,'update'])->name('precios-proyectados.update');
        Route::delete('/{id}', [Parametrizacion\PrecioProyectadoController::class,'destroy'])->name('precios-proyectados.delete');
    });

    // Nieveles Consumos
    Route::group(["prefix" => "niveles-consumos"],function(){
        Route::get('/', [Parametrizacion\NivelCosumoController::class,'index'])->name('niveles-consumos.index');
        Route::post('/', [Parametrizacion\NivelCosumoController::class,'store'])->name('niveles-consumos.store');
        Route::get('//{id}', [Parametrizacion\NivelCosumoController::class,'show'])->name('niveles-consumos.show');
        Route::put('/{id}', [Parametrizacion\NivelCosumoController::class,'update'])->name('niveles-consumos.update');
        Route::delete('/{id}', [Parametrizacion\NivelCosumoController::class,'destroy'])->name('niveles-consumos.delete');
    });


    // Descuentos Tarifas
    Route::group(["prefix" => "descuentos-tarifas"],function(){
        Route::get('/matriz/data', [Parametrizacion\DescuentoTarifaController::class, 'obtenerMatriz'])->name('descuentos-tarifas.obtenerMatriz');
        Route::post('/matriz/guardar', [Parametrizacion\DescuentoTarifaController::class, 'guardarMatriz'])->name('descuentos-tarifas.guardarMatriz');

    });
    
    // ---------------------- Inversiones -------------------------- //

    // Inversiones 
    Route::group(["prefix" => "inversiones"],function(){
        Route::get('/', [Inversiones\InversionesController::class,'index'])->name('inversiones.index');
        Route::post('/', [Inversiones\InversionesController::class,'store'])->name('inversiones.store');
        Route::get('/{id}', [Inversiones\InversionesController::class,'show'])->name('inversiones.show');
        Route::put('/anular/{id}', [Inversiones\InversionesController::class,'destroy'])->name('inversiones.delete');
        Route::put('/{id}', [Inversiones\InversionesController::class,'update'])->name('inversiones.update');
    });

    // Proyecto Plan Inversión 
    Route::group(["prefix" => "proyectos-plan-inversiones"],function(){
        Route::get('/', [Inversiones\ProyectoPlanInversionController::class,'index'])->name('proyectos-plan-inversiones.index');
        Route::post('/', [Inversiones\ProyectoPlanInversionController::class,'store'])->name('proyectos-plan-inversiones.store');
        Route::get('/{id_inversionista}/{id_proyecto}', [Inversiones\ProyectoPlanInversionController::class,'show'])->name('proyectos-plan-inversiones.show');
        Route::put('/{id_inversionista}/{id_proyecto}', [Inversiones\ProyectoPlanInversionController::class,'update'])->name('proyectos-plan-inversiones.update');
        Route::delete('/{id_inversionista}/{id_proyecto}', [Inversiones\ProyectoPlanInversionController::class,'destroy'])->name('proyectos-plan-inversiones.delete');
    });

    // Plan Detallado
    Route::group(["prefix" => "plan-detallado"],function(){
        Route::get('/', [Inversiones\PlanDetalladoController::class,'index'])->name('plan-detallado.index');
        Route::post('/', [Inversiones\PlanDetalladoController::class,'store'])->name('plan-detallado.store');
        Route::get('/{id}', [Inversiones\PlanDetalladoController::class,'show'])->name('plan-detallado.show');
        Route::put('/{id}', [Inversiones\PlanDetalladoController::class,'update'])->name('plan-detallado.update');
        Route::delete('/{id}/{inversion_id}', [Inversiones\PlanDetalladoController::class,'destroy'])->name('plan-detallado.delete');
    });

    // Inversión Proyecto
    Route::group(["prefix" => "inversiones-proyectos"],function(){
        Route::get('/', [Inversiones\InversionProyectoController::class,'index'])->name('inversiones-proyectos.index');
        Route::post('/', [Inversiones\InversionProyectoController::class,'store'])->name('inversiones-proyectos.store');
        Route::get('/{id}', [Inversiones\InversionProyectoController::class,'show'])->name('inversiones-proyectos.show');
        Route::put('/{id}', [Inversiones\InversionProyectoController::class,'update'])->name('inversiones-proyectos.update');
        Route::delete('/{id}/{inversion_id}', [Inversiones\InversionProyectoController::class,'destroy'])->name('inversiones-proyectos.delete');
    });


    Route::group(["prefix" => "programaciones"], function () {
        Route::get('/lista', [Inversiones\PlanDetalladoController::class, 'ProgramacionInv'])->name('programaciones.lista');
        Route::get('/programadas', [Inversiones\PlanDetalladoController::class,'listarPagosProgramados'])->name('programaciones.programadas');
        Route::get('/resumenSemanal', [Inversiones\PlanDetalladoController::class,'listarResumenSemanalaDetallado'])->name('programaciones.resumenSemanal');
        Route::put('/guardar', [Inversiones\PlanDetalladoController::class,'ModificarProgramacionInv'])->name('programaciones.guardar');
        Route::put('/pagos', [Inversiones\PlanDetalladoController::class,'confirmarPagoIndividual'])->name('programaciones.pagos');
        Route::get('/historico-pagos', [Inversiones\PlanDetalladoController::class,'listarHcaPagosDetallado'])->name('programaciones.HcaPagos');
    });

    Route::post('/backup', [BackupController::class, 'createBackup']);
    Route::post('/extractos', [ExtractoController::class, 'enviarExtractos']);
    Route::get('/exportar-portafolio', [PortafolioController::class, 'generarPDF']);

    // ---------------------- Turismo -------------------------- //

    // Destinos
    Route::group(["prefix" => "destinos"], function () {
        Route::get('/', [Turismo\DestinoController::class, 'index'])->name('destinos.index');
        Route::post('/', [Turismo\DestinoController::class, 'store'])->name('destinos.store')->middleware('permission:CrearDestino');
        Route::get('/{id}', [Turismo\DestinoController::class, 'show'])->name('destinos.show');
        Route::put('/{id}', [Turismo\DestinoController::class, 'update'])->name('destinos.update')->middleware('permission:ModificarDestino');
        Route::delete('/{id}', [Turismo\DestinoController::class, 'destroy'])->name('destinos.delete')->middleware('permission:EliminarDestino');
    });

    // Categorias
    Route::group(["prefix" => "categorias"], function () {
        Route::get('/', [Turismo\CategoriaController::class, 'index'])->name('categorias.index');
        Route::post('/', [Turismo\CategoriaController::class, 'store'])->name('categorias.store')->middleware('permission:CrearCategoria');
        Route::get('/{id}', [Turismo\CategoriaController::class, 'show'])->name('categorias.show');
        Route::put('/{id}', [Turismo\CategoriaController::class, 'update'])->name('categorias.update')->middleware('permission:ModificarCategoria');
        Route::delete('/{id}', [Turismo\CategoriaController::class, 'destroy'])->name('categorias.delete')->middleware('permission:EliminarCategoria');
    });

    // Caracteristicas
    Route::group(["prefix" => "caracteristicas"], function () {
        Route::get('/', [Turismo\CaracteristicaController::class, 'index'])->name('caracteristicas.index');
        Route::post('/', [Turismo\CaracteristicaController::class, 'store'])->name('caracteristicas.store')->middleware('permission:CrearCaracteristica');
        Route::get('/{id}', [Turismo\CaracteristicaController::class, 'show'])->name('caracteristicas.show');
        Route::put('/{id}', [Turismo\CaracteristicaController::class, 'update'])->name('caracteristicas.update')->middleware('permission:ModificarCaracteristica');
        Route::delete('/{id}', [Turismo\CaracteristicaController::class, 'destroy'])->name('caracteristicas.delete')->middleware('permission:EliminarCaracteristica');
    });

    // Proveedores turisticos
    Route::group(["prefix" => "proveedores-turisticos"], function () {
        Route::get('/', [Proveedores\ProveedorTuristicoController::class, 'index'])->name('proveedores-turisticos.index');
        Route::post('/', [Proveedores\ProveedorTuristicoController::class, 'store'])->name('proveedores-turisticos.store')->middleware('permission:CrearProveedorTuristico');
        Route::get('/{id}', [Proveedores\ProveedorTuristicoController::class, 'show'])->name('proveedores-turisticos.show');
        Route::put('/{id}', [Proveedores\ProveedorTuristicoController::class, 'update'])->name('proveedores-turisticos.update')->middleware('permission:ModificarProveedorTuristico');
        Route::put('/{id}/verificar', [Proveedores\ProveedorTuristicoController::class, 'verificar'])->name('proveedores-turisticos.verificar')->middleware('permission:ModificarProveedorTuristico');
        Route::delete('/{id}', [Proveedores\ProveedorTuristicoController::class, 'destroy'])->name('proveedores-turisticos.delete')->middleware('permission:EliminarProveedorTuristico');
    });

    // Documentos proveedor
    Route::group(["prefix" => "documentos-proveedor"], function () {
        Route::get('/', [Proveedores\DocumentoProveedorController::class, 'index'])->name('documentos-proveedor.index');
        Route::post('/', [Proveedores\DocumentoProveedorController::class, 'store'])->name('documentos-proveedor.store')->middleware('permission:CrearProveedorTuristico');
        Route::get('/{id}', [Proveedores\DocumentoProveedorController::class, 'show'])->name('documentos-proveedor.show');
        Route::get('/{id}/archivo', [Proveedores\DocumentoProveedorController::class, 'archivo'])->name('documentos-proveedor.archivo');
        Route::put('/{id}', [Proveedores\DocumentoProveedorController::class, 'update'])->name('documentos-proveedor.update')->middleware('permission:ModificarProveedorTuristico');
        Route::delete('/{id}', [Proveedores\DocumentoProveedorController::class, 'destroy'])->name('documentos-proveedor.delete')->middleware('permission:EliminarProveedorTuristico');
    });

    // Cupones
    Route::group(["prefix" => "cupones"], function () {
        Route::get('/', [Turismo\CuponController::class, 'index'])->name('cupones.index');
        Route::post('/', [Turismo\CuponController::class, 'store'])->name('cupones.store')->middleware('permission:CrearCupon');
        Route::get('/{id}', [Turismo\CuponController::class, 'show'])->name('cupones.show');
        Route::put('/{id}', [Turismo\CuponController::class, 'update'])->name('cupones.update')->middleware('permission:ModificarCupon');
        Route::delete('/{id}', [Turismo\CuponController::class, 'destroy'])->name('cupones.delete')->middleware('permission:EliminarCupon');
    });

    // Experiencias
    Route::group(["prefix" => "experiencias"], function () {
        Route::get('/', [Experiencias\ExperienciaController::class, 'index'])->name('experiencias.index');
        Route::post('/', [Experiencias\ExperienciaController::class, 'store'])->name('experiencias.store');
        Route::get('/{id}', [Experiencias\ExperienciaController::class, 'show'])->name('experiencias.show');
        Route::put('/{id}', [Experiencias\ExperienciaController::class, 'update'])->name('experiencias.update');
        Route::put('/{id}/estado', [Experiencias\ExperienciaController::class, 'cambiarEstado'])->name('experiencias.cambiarEstado');
        Route::delete('/{id}', [Experiencias\ExperienciaController::class, 'destroy'])->name('experiencias.delete');
    });

    // Experiencia categorias
    Route::group(["prefix" => "experiencia-categorias"], function () {
        Route::get('/', [Experiencias\ExperienciaCategoriaController::class, 'index'])->name('experiencia-categorias.index');
        Route::post('/', [Experiencias\ExperienciaCategoriaController::class, 'store'])->name('experiencia-categorias.store');
        Route::delete('/{id}', [Experiencias\ExperienciaCategoriaController::class, 'destroy'])->name('experiencia-categorias.delete');
    });

    // Experiencia precios
    Route::group(["prefix" => "experiencia-precios"], function () {
        Route::get('/', [Experiencias\ExperienciaPrecioController::class, 'index'])->name('experiencia-precios.index');
        Route::post('/', [Experiencias\ExperienciaPrecioController::class, 'store'])->name('experiencia-precios.store');
        Route::get('/{id}', [Experiencias\ExperienciaPrecioController::class, 'show'])->name('experiencia-precios.show');
        Route::put('/{id}', [Experiencias\ExperienciaPrecioController::class, 'update'])->name('experiencia-precios.update');
        Route::delete('/{id}', [Experiencias\ExperienciaPrecioController::class, 'destroy'])->name('experiencia-precios.delete');
    });

    // Experiencia multimedia
    Route::group(["prefix" => "experiencia-multimedia"], function () {
        Route::get('/', [Experiencias\ExperienciaMultimediaController::class, 'index'])->name('experiencia-multimedia.index');
        Route::post('/', [Experiencias\ExperienciaMultimediaController::class, 'store'])->name('experiencia-multimedia.store');
        Route::get('/{id}', [Experiencias\ExperienciaMultimediaController::class, 'show'])->name('experiencia-multimedia.show');
        Route::put('/{id}', [Experiencias\ExperienciaMultimediaController::class, 'update'])->name('experiencia-multimedia.update');
        Route::delete('/{id}', [Experiencias\ExperienciaMultimediaController::class, 'destroy'])->name('experiencia-multimedia.delete');
    });

    // Experiencia caracteristicas
    Route::group(["prefix" => "experiencia-caracteristicas"], function () {
        Route::get('/', [Experiencias\ExperienciaCaracteristicaController::class, 'index'])->name('experiencia-caracteristicas.index');
        Route::post('/', [Experiencias\ExperienciaCaracteristicaController::class, 'store'])->name('experiencia-caracteristicas.store');
        Route::put('/{id}', [Experiencias\ExperienciaCaracteristicaController::class, 'update'])->name('experiencia-caracteristicas.update');
        Route::delete('/{id}', [Experiencias\ExperienciaCaracteristicaController::class, 'destroy'])->name('experiencia-caracteristicas.delete');
    });

    // Experiencia horarios
    Route::group(["prefix" => "experiencia-horarios"], function () {
        Route::get('/', [Experiencias\ExperienciaHorarioController::class, 'index'])->name('experiencia-horarios.index');
        Route::post('/', [Experiencias\ExperienciaHorarioController::class, 'store'])->name('experiencia-horarios.store');
        Route::get('/{id}', [Experiencias\ExperienciaHorarioController::class, 'show'])->name('experiencia-horarios.show');
        Route::put('/{id}', [Experiencias\ExperienciaHorarioController::class, 'update'])->name('experiencia-horarios.update');
        Route::delete('/{id}', [Experiencias\ExperienciaHorarioController::class, 'destroy'])->name('experiencia-horarios.delete');
    });

    // Experiencia disponibilidad
    Route::group(["prefix" => "experiencia-disponibilidad"], function () {
        Route::get('/', [Experiencias\ExperienciaDisponibilidadController::class, 'index'])->name('experiencia-disponibilidad.index');
        Route::post('/', [Experiencias\ExperienciaDisponibilidadController::class, 'store'])->name('experiencia-disponibilidad.store');
        Route::get('/{id}', [Experiencias\ExperienciaDisponibilidadController::class, 'show'])->name('experiencia-disponibilidad.show');
        Route::put('/{id}', [Experiencias\ExperienciaDisponibilidadController::class, 'update'])->name('experiencia-disponibilidad.update');
        Route::delete('/{id}', [Experiencias\ExperienciaDisponibilidadController::class, 'destroy'])->name('experiencia-disponibilidad.delete');
    });

    // Reservas
    Route::group(["prefix" => "reservas"], function () {
        Route::get('/', [Reservas\ReservaController::class, 'index'])->name('reservas.index');
        Route::post('/', [Reservas\ReservaController::class, 'store'])->name('reservas.store');
        Route::get('/{id}', [Reservas\ReservaController::class, 'show'])->name('reservas.show');
        Route::put('/{id}', [Reservas\ReservaController::class, 'update'])->name('reservas.update');
        Route::put('/{id}/estado', [Reservas\ReservaController::class, 'cambiarEstado'])->name('reservas.cambiarEstado');
        Route::delete('/{id}', [Reservas\ReservaController::class, 'destroy'])->name('reservas.delete');
    });

    // Acompanantes reserva
    Route::group(["prefix" => "acompanantes-reserva"], function () {
        Route::get('/', [Reservas\AcompananteReservaController::class, 'index'])->name('acompanantes-reserva.index');
        Route::post('/', [Reservas\AcompananteReservaController::class, 'store'])->name('acompanantes-reserva.store');
        Route::get('/{id}', [Reservas\AcompananteReservaController::class, 'show'])->name('acompanantes-reserva.show');
        Route::put('/{id}', [Reservas\AcompananteReservaController::class, 'update'])->name('acompanantes-reserva.update');
        Route::delete('/{id}', [Reservas\AcompananteReservaController::class, 'destroy'])->name('acompanantes-reserva.delete');
    });

    // Favoritos
    Route::group(["prefix" => "favoritos"], function () {
        Route::get('/', [Turismo\FavoritoController::class, 'index'])->name('favoritos.index');
        Route::post('/', [Turismo\FavoritoController::class, 'store'])->name('favoritos.store');
        Route::delete('/{id}', [Turismo\FavoritoController::class, 'destroy'])->name('favoritos.delete');
    });

    // Resenas
    Route::group(["prefix" => "resenas"], function () {
        Route::get('/', [Resenas\ResenaController::class, 'index'])->name('resenas.index');
        Route::post('/', [Resenas\ResenaController::class, 'store'])->name('resenas.store');
        Route::get('/{id}', [Resenas\ResenaController::class, 'show'])->name('resenas.show');
        Route::put('/{id}', [Resenas\ResenaController::class, 'update'])->name('resenas.update');
        Route::delete('/{id}', [Resenas\ResenaController::class, 'destroy'])->name('resenas.delete');
    });

    // Multimedia resena
    Route::group(["prefix" => "multimedia-resena"], function () {
        Route::get('/', [Resenas\MultimediaResenaController::class, 'index'])->name('multimedia-resena.index');
        Route::post('/', [Resenas\MultimediaResenaController::class, 'store'])->name('multimedia-resena.store');
        Route::delete('/{id}', [Resenas\MultimediaResenaController::class, 'destroy'])->name('multimedia-resena.delete');
    });

    // Promociones
    Route::group(["prefix" => "promociones"], function () {
        Route::get('/', [Promociones\PromocionController::class, 'index'])->name('promociones.index');
        Route::post('/', [Promociones\PromocionController::class, 'store'])->name('promociones.store')->middleware('permission:CrearPromocion');
        Route::get('/{id}', [Promociones\PromocionController::class, 'show'])->name('promociones.show');
        Route::put('/{id}', [Promociones\PromocionController::class, 'update'])->name('promociones.update')->middleware('permission:ModificarPromocion');
        Route::delete('/{id}', [Promociones\PromocionController::class, 'destroy'])->name('promociones.delete')->middleware('permission:EliminarPromocion');
    });

    // Promocion experiencias
    Route::group(["prefix" => "promocion-experiencias"], function () {
        Route::get('/', [Promociones\PromocionExperienciaController::class, 'index'])->name('promocion-experiencias.index');
        Route::post('/', [Promociones\PromocionExperienciaController::class, 'store'])->name('promocion-experiencias.store')->middleware('permission:CrearPromocion');
        Route::delete('/{id}', [Promociones\PromocionExperienciaController::class, 'destroy'])->name('promocion-experiencias.delete')->middleware('permission:EliminarPromocion');
    });

    // Notificaciones
    Route::group(["prefix" => "notificaciones"], function () {
        Route::get('/', [Turismo\NotificacionController::class, 'index'])->name('notificaciones.index');
        Route::post('/', [Turismo\NotificacionController::class, 'store'])->name('notificaciones.store');
        Route::put('/{id}/leida', [Turismo\NotificacionController::class, 'marcarLeida'])->name('notificaciones.marcarLeida');
        Route::delete('/{id}', [Turismo\NotificacionController::class, 'destroy'])->name('notificaciones.delete');
    });

    // ---------------------- Academia -------------------------- //

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
        Route::get('/{id}', [Academia\AlumnoController::class, 'show'])->name('alumnos.show');
        Route::put('/{id}', [Academia\AlumnoController::class, 'update'])->name('alumnos.update')->middleware('permission:ModificarAlumno');
        Route::delete('/{id}', [Academia\AlumnoController::class, 'destroy'])->name('alumnos.delete')->middleware('permission:EliminarAlumno');
    });

    // Cursos
    Route::group(["prefix" => "cursos"], function () {
        Route::get('/', [Academia\CursoController::class, 'index'])->name('cursos.index');
        Route::post('/', [Academia\CursoController::class, 'store'])->name('cursos.store')->middleware('permission:CrearCurso');
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
        Route::get('/{id}', [Academia\AsistenciaController::class, 'show'])->name('asistencias.show');
        Route::put('/{id}', [Academia\AsistenciaController::class, 'update'])->name('asistencias.update')->middleware('permission:ModificarAsistencia');
        Route::delete('/{id}', [Academia\AsistenciaController::class, 'destroy'])->name('asistencias.delete')->middleware('permission:EliminarAsistencia');
    });

    // Dashboard de academia
    Route::get('academia/dashboard', [Academia\DashboardController::class, 'index'])->name('academia.dashboard');
});