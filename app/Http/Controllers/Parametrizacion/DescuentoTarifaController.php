<?php

namespace App\Http\Controllers\Parametrizacion;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Parametrizacion\DescuentoTarifa;
use Illuminate\Support\Facades\Validator;

class DescuentoTarifaController extends Controller
{

     /**
     * Obtener matriz para la pantalla
     */
    public function obtenerMatriz()
    {
        try {
            $data = DescuentoTarifa::obtenerMatriz();
            return response($data, Response::HTTP_OK);      
            
        } catch (\Throwable $e) {
           return response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Guardar matriz completa
     * Regla:
     * - valor con dato => crea o actualiza
     * - valor null o '' => elimina si existe
     */
    public function guardarMatriz(Request $request)
    {
        try {
            $dto = $request->validate([
                'matriz' => 'required|array',
                'matriz.*.numero_anio' => 'required|integer|min:1',
                'matriz.*.valores' => 'required|array',
                'matriz.*.valores.*.numero_nivel' => 'required|integer|min:1',
                'matriz.*.valores.*.id_nivel_consumo' => 'required|integer|min:1',
                'matriz.*.valores.*.porcentaje_descuento' => 'nullable',
            ]);

            $data = DescuentoTarifa::guardarMatriz($dto);

            return response(
                get_response_body(["El Descuento Tarifa ha sido modificado.", 1], $data),
                Response::HTTP_OK
            );
        } catch (\Throwable $e) {
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
