<?php

namespace App\Http\Controllers\Publico;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\Turismo\Destino;
use App\Models\Turismo\Categoria;
use App\Models\Promociones\Promocion;
use App\Models\Experiencias\Experiencia;

/**
 * Endpoints de solo lectura para el portal público (sin autenticación).
 * Solo exponen registros activos / publicados / aprobados.
 */
class PortalController extends Controller
{
    public function destinos(Request $request)
    {
        try {
            return response(Destino::obtenerColeccionPublica($request->all()), Response::HTTP_OK);
        } catch (Exception $e) {
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function categorias(Request $request)
    {
        try {
            return response(Categoria::obtenerColeccionPublica($request->all()), Response::HTTP_OK);
        } catch (Exception $e) {
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function experiencias(Request $request)
    {
        try {
            $datos = $request->all();
            $validator = Validator::make($datos, [
                'limite' => 'integer|between:1,60',
                'precio_min' => 'numeric|nullable',
                'precio_max' => 'numeric|nullable',
                'orden' => 'string|nullable|in:relevancia,precio_asc,precio_desc,calificacion,recientes',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            return response(Experiencia::obtenerColeccionPublica($datos), Response::HTTP_OK);
        } catch (Exception $e) {
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function experiencia($slug)
    {
        try {
            $experiencia = Experiencia::cargarPublica($slug);
            if (!$experiencia) {
                return response(get_response_body(['La experiencia no existe o no está publicada.']), Response::HTTP_NOT_FOUND);
            }
            return response()->json($experiencia, Response::HTTP_OK);
        } catch (Exception $e) {
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function promociones(Request $request)
    {
        try {
            return response(Promocion::obtenerVigentes($request->all()), Response::HTTP_OK);
        } catch (Exception $e) {
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
