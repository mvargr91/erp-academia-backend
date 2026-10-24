<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Quien entra con una contraseña inicial (la que le dio el administrador) debe cambiarla antes de
 * usar el ERP: hasta entonces solo puede consultar su sesión y su cuenta y cambiar la contraseña.
 */
class ClaveVigente
{
    private const PERMITIDAS = ['session.show', 'cuenta.show', 'cuenta.update', 'cuenta.clave'];

    public function handle(Request $request, Closure $next)
    {
        $usuario = $request->user()?->usuario();
        if ($usuario && !empty($usuario->debe_cambiar_clave)
            && !in_array($request->route()?->getName(), self::PERMITIDAS, true)) {
            return response(
                get_response_body(['Debes cambiar tu contraseña antes de continuar.']) + ['debe_cambiar_clave' => true],
                Response::HTTP_FORBIDDEN
            );
        }
        return $next($request);
    }
}
