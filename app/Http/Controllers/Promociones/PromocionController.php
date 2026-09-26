<?php

namespace App\Http\Controllers\Promociones;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\Promociones\Promocion;

class PromocionController extends Controller
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
                $promociones = Promocion::obtenerColeccionLigera($datos);
            } else {
                if (isset($datos['ordenar_por'])) {
                    $datos['ordenar_por'] = format_order_by_attributes($datos);
                }
                $promociones = Promocion::obtenerColeccion($datos);
            }
            return response($promociones, Response::HTTP_OK);
        } catch (Exception $e) {
            return response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $datos = $request->all();
            $validator = Validator::make($datos, [
                'nombre' => 'string|required|max:150',
                'tipo_descuento' => 'string|required|in:porcentaje,valor_fijo',
                'valor_descuento' => 'numeric|required',
                'fecha_inicio' => 'date|required',
                'fecha_fin' => 'date|required|after_or_equal:fecha_inicio',
                'estado' => 'boolean|required',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $promocion = Promocion::modificarOCrear($datos);

            if ($promocion) {
                DB::commit();
                return response(
                    get_response_body(['La promoción ha sido creada.', 2], $promocion),
                    Response::HTTP_CREATED
                );
            } else {
                DB::rollback();
                return response(get_response_body(['Ocurrió un error al intentar crear la promoción.']), Response::HTTP_CONFLICT);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        try {
            $datos['id'] = $id;
            $validator = Validator::make($datos, [
                'id' => 'integer|required|exists:promociones,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            return response(Promocion::cargar($id), Response::HTTP_OK);
        } catch (Exception $e) {
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $datos = $request->all();
            $datos['id'] = $id;
            $validator = Validator::make($datos, [
                'id' => 'integer|required|exists:promociones,id',
                'nombre' => 'string|required|max:150',
                'tipo_descuento' => 'string|required|in:porcentaje,valor_fijo',
                'valor_descuento' => 'numeric|required',
                'fecha_inicio' => 'date|required',
                'fecha_fin' => 'date|required|after_or_equal:fecha_inicio',
                'estado' => 'boolean|required',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $promocion = Promocion::modificarOCrear($datos);
            if ($promocion) {
                DB::commit();
                return response(
                    get_response_body(['La promoción ha sido modificada.', 1], $promocion),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback();
                return response(get_response_body(['Ocurrió un error al intentar modificar la promoción.']), Response::HTTP_CONFLICT);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $datos['id'] = $id;
            $validator = Validator::make($datos, [
                'id' => 'integer|required|exists:promociones,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $eliminado = Promocion::eliminar($id);
            if ($eliminado) {
                DB::commit();
                return response(
                    get_response_body(['La promoción ha sido eliminada.', 3]),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback();
                return response(get_response_body(['Ocurrió un error al intentar eliminar la promoción.']), Response::HTTP_CONFLICT);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
