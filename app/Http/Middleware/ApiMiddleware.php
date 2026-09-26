<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApiMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar si el token es válido usando el guard 'api'
        $tokenIsValid = Auth::guard("api")->check();

        // Si el token no es válido
        if (!$tokenIsValid) {

            // Permitir las rutas de oauth y las del portal público (publico.*) sin token
            if ($request->routeIs('oauth.*') || $request->routeIs('publico.*')) {
                return $next($request);
            }

            // Si el token no es válido y no es la ruta 'v1.oauth/token', devolver respuesta de no autorizado
            return response()->json([
                'message' => 'Unauthorized'
            ], 401); // Establecer el código de estado HTTP 401
        }

        // Si el token es válido, continuar con la solicitud
        return $next($request);
    }    
}
