<?php

namespace App\Http\Controllers\Academia;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Support\CorreoUnico;
use App\Models\Academia\Profesor;
use Illuminate\Support\Facades\Validator;

class ProfesorController extends Controller
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
                $profesores = Profesor::obtenerColeccionLigera($datos);
            } else {
                if (isset($datos['ordenar_por'])) {
                    $datos['ordenar_por'] = format_order_by_attributes($datos);
                }
                $profesores = Profesor::obtenerColeccion($datos);
            }
            return response($profesores, Response::HTTP_OK);
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
                'usuario_id' => 'integer|nullable|exists:usuarios,id',
                'nombres' => 'string|required|max:100',
                'apellidos' => 'string|required|max:100',
                'documento' => 'string|nullable|max:30',
                'telefono' => 'string|nullable|max:30',
                'correo' => 'email|nullable|max:150',
                'especialidad' => 'string|nullable|max:150',
                'estado' => 'boolean|required',
            ]);
            CorreoUnico::validar($validator, 'profesores', $datos['correo'] ?? null);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $profesor = Profesor::modificarOCrear($datos);

            if ($profesor) {
                DB::commit();
                return response(
                    get_response_body(['El profesor ha sido creado.', 2], $profesor),
                    Response::HTTP_CREATED
                );
            } else {
                DB::rollback();
                return response(get_response_body(['Ocurrió un error al intentar crear el profesor.']), Response::HTTP_CONFLICT);
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
                'id' => 'integer|required|exists:profesores,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            return response(Profesor::cargar($id), Response::HTTP_OK);
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
                'id' => 'integer|required|exists:profesores,id',
                'usuario_id' => 'integer|nullable|exists:usuarios,id',
                'nombres' => 'string|required|max:100',
                'apellidos' => 'string|required|max:100',
                'documento' => 'string|nullable|max:30',
                'telefono' => 'string|nullable|max:30',
                'correo' => 'email|nullable|max:150',
                'especialidad' => 'string|nullable|max:150',
                'estado' => 'boolean|required',
            ]);
            CorreoUnico::validar($validator, 'profesores', $datos['correo'] ?? null, (int) $id);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $profesor = Profesor::modificarOCrear($datos);
            if ($profesor) {
                DB::commit();
                return response(
                    get_response_body(['El profesor ha sido modificado.', 1], $profesor),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback();
                return response(get_response_body(['Ocurrió un error al intentar modificar el profesor.']), Response::HTTP_CONFLICT);
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
                'id' => 'integer|required|exists:profesores,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $eliminado = Profesor::eliminar($id);
            if ($eliminado) {
                DB::commit();
                return response(
                    get_response_body(['El profesor ha sido eliminado.', 3]),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback();
                return response(get_response_body(['Ocurrió un error al intentar eliminar el profesor.']), Response::HTTP_CONFLICT);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
