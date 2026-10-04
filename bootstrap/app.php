<?php

use App\Http\Middleware\ApiMiddleware;
use App\Http\Middleware\IdentificarAcademia;
use App\Http\Middleware\SoloAcademiaAdministradora;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Multi-academia: activa la BD de la academia (subdominio / cabecera X-Academia)
        // antes de cualquier autenticación. append = después de HandleCors.
        $middleware->append(IdentificarAcademia::class);

        $middleware->api(prepend: [
            ApiMiddleware::class,
        ]);

        // Middleware de Spatie para proteger rutas por permiso / rol.
        $middleware->alias([
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'academia.administradora' => SoloAcademiaAdministradora::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
