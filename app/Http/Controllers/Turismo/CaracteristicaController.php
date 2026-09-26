<?php

namespace App\Http\Controllers\Turismo;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\Turismo\Caracteristica;

class CaracteristicaController extends Controller
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
                $caracteristicas = Caracteristica::obtenerColeccionLigera($datos);
            } else {
                if (isset($datos['ordenar_por'])) {
                    $datos['ordenar_por'] = format_order_by_attributes($datos);
                }
                $caracteristicas = Caracteristica::obtenerColeccion($datos);
            }
            return response($caracteristicas, Response::HTTP_OK);
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
                'nombre' => 'string|required|max:128',
                'icono' => 'string|nullable|max:100',
                'estado' => 'boolean|required',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $caracteristica = Caracteristica::modificarOCrear($datos);

            if ($caracteristica) {
                DB::commit();
                return response(
                    get_response_body(['La característica ha sido creada.', 2], $caracteristica),
                    Response::HTTP_CREATED
                );
            } else {
                DB::rollback();
                return response(get_response_body(['Ocurrió un error al intentar crear la característica.']), Response::HTTP_CONFLICT);
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
                'id' => 'integer|required|exists:caracteristicas,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            return response(Caracteristica::cargar($id), Response::HTTP_OK);
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
                'id' => 'integer|required|exists:caracteristicas,id',
                'nombre' => 'string|required|max:128',
                'icono' => 'string|nullable|max:100',
                'estado' => 'boolean|required',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $caracteristica = Caracteristica::modificarOCrear($datos);
            if ($caracteristica) {
                DB::commit();
                return response(
                    get_response_body(['La característica ha sido modificada.', 1], $caracteristica),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback();
                return response(get_response_body(['Ocurrió un error al intentar modificar la característica.']), Response::HTTP_CONFLICT);
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
                'id' => 'integer|required|exists:caracteristicas,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $eliminado = Caracteristica::eliminar($id);
            if ($eliminado) {
                DB::commit();
                return response(
                    get_response_body(['La característica ha sido eliminada.', 3]),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback();
                return response(get_response_body(['Ocurrió un error al intentar eliminar la característica.']), Response::HTTP_CONFLICT);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
