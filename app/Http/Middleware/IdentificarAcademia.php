<?php

namespace App\Http\Middleware;

use App\Services\Central\FacturacionAcademias;
use Closure;
use Illuminate\Http\Request;
use App\Support\Academias\GestorAcademias;
use Symfony\Component\HttpFoundation\Response;

/**
 * Activa la BD de la academia antes de autenticar. Es global (se agrega después de
 * HandleCors para que los errores lleven cabeceras CORS) y solo exige academia
 * en el API (v1/*) y en los endpoints de Passport (oauth/*).
 */
class IdentificarAcademia
{
    public function handle(Request $request, Closure $next): Response
    {
        $requiereAcademia = $request->is('v1/*') || $request->is('oauth/*');

        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }

        $academia = GestorAcademias::resolver($request);

        if (!$academia) {
            return $requiereAcademia
                ? response()->json(['messages' => ['Academia no encontrada.']], Response::HTTP_NOT_FOUND)
                : $next($request);
        }

        if (!$academia->activa) {
            $mensaje = $academia->suspendida_por_mora
                ? 'El acceso está suspendido por falta de pago. Se reactiva automáticamente al registrar el pago; escribe a '
                    . FacturacionAcademias::cfg('contacto') . ' con el soporte.'
                : 'La academia está suspendida. Comunícate con el administrador del sistema.';
            return response()->json(['messages' => [$mensaje]], Response::HTTP_FORBIDDEN);
        }

        GestorAcademias::activar($academia);

        return $next($request);
    }
}
