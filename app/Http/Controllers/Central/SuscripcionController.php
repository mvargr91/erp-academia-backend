<?php

namespace App\Http\Controllers\Central;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Support\Academias\GestorAcademias;
use App\Services\Central\FacturacionAcademias;

/**
 * Estado de la suscripción de la academia actual, para el aviso dentro del ERP.
 * Solo se muestra al administrador de la academia (rol SuperSu de su BD).
 */
class SuscripcionController extends Controller
{
    public function show()
    {
        $academia = GestorAcademias::actual();
        if (!$academia || $academia->es_administradora || !Auth::user()?->hasRole('SuperSu')) {
            return response(['mostrar' => false], Response::HTTP_OK);
        }

        $cuenta = FacturacionAcademias::estadoCuenta($academia);
        return response(array_merge($cuenta, [
            'mostrar' => in_array($cuenta['estado'], ['pendiente', 'en_mora'], true),
            'datos_pago' => FacturacionAcademias::cfg('datos_pago'),
            'contacto' => FacturacionAcademias::cfg('contacto'),
        ]), Response::HTTP_OK);
    }
}
