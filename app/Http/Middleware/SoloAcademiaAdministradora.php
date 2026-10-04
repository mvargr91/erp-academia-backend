<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Support\Academias\GestorAcademias;
use Symfony\Component\HttpFoundation\Response;

/**
 * La gestión de academias (panel del dueño del ERP) solo se atiende desde
 * la academia marcada como administradora.
 */
class SoloAcademiaAdministradora
{
    public function handle(Request $request, Closure $next): Response
    {
        $academia = GestorAcademias::actual();
        if (!$academia || !$academia->es_administradora) {
            return response()->json(['messages' => ['No autorizado.']], Response::HTTP_FORBIDDEN);
        }
        return $next($request);
    }
}
