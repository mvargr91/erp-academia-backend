<?php

namespace App\Http\Controllers\Academia;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Models\Academia\Plan;
use Illuminate\Support\Facades\Validator;

/** Tipos de paquete de clases personalizadas. Se crean y editan en Tarifas (TarifaController). */
class PlanController extends Controller
{
    public function index(Request $request)
    {
        try {
            $datos = $request->all();
            if (!$request->ligera) {
                $validator = Validator::make($datos, [
                    'limite' => 'integer|between:1,500'
                ]);

                if ($validator->fails()) {
                    return response(
                        get_response_body(format_messages_validator($validator)),
                        Response::HTTP_BAD_REQUEST
                    );
                }
            }

            if ($request->ligera) {
                $planes = Plan::obtenerColeccionLigera($datos);
            } else {
                if (isset($datos['ordenar_por'])) {
                    $datos['ordenar_por'] = format_order_by_attributes($datos);
                }
                $planes = Plan::obtenerColeccion($datos);
            }
            return response($planes, Response::HTTP_OK);
        } catch (Exception $e) {
            return response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
