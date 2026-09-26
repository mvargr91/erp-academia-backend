<?php

namespace App\Http\Controllers\Proveedores;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\ArchivosService;
use Illuminate\Support\Facades\Validator;
use App\Models\Proveedores\ProveedorTuristico;

class ProveedorTuristicoController extends Controller
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
                $proveedores = ProveedorTuristico::obtenerColeccionLigera($datos);
            } else {
                if (isset($datos['ordenar_por'])) {
                    $datos['ordenar_por'] = format_order_by_attributes($datos);
                }
                $proveedores = ProveedorTuristico::obtenerColeccion($datos);
            }
            return response($proveedores, Response::HTTP_OK);
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
                'usuario_id' => 'integer|nullable|exists:usuarios,id|unique:proveedores_turisticos,usuario_id',
                'nombre_comercial' => 'string|required|max:150',
                'razon_social' => 'string|nullable|max:150',
                'nit' => 'string|nullable|max:50',
                'telefono' => 'string|nullable|max:30',
                'correo' => 'email|nullable|max:150',
                'destino_id' => 'integer|nullable|exists:destinos,id',
                'estado_verificacion' => 'string|nullable|max:30',
                'estado' => 'boolean|required',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $proveedor = ProveedorTuristico::modificarOCrear($datos);

            if ($proveedor) {
                DB::commit();
                ArchivosService::confirmar();
                return response(
                    get_response_body(['El proveedor turístico ha sido creado.', 2], $proveedor),
                    Response::HTTP_CREATED
                );
            } else {
                DB::rollback();
                ArchivosService::revertir();
                return response(get_response_body(['Ocurrió un error al intentar crear el proveedor turístico.']), Response::HTTP_CONFLICT);
            }
        } catch (Exception $e) {
            DB::rollback();
            ArchivosService::revertir();
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        try {
            $datos['id'] = $id;
            $validator = Validator::make($datos, [
                'id' => 'integer|required|exists:proveedores_turisticos,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            return response(ProveedorTuristico::cargar($id), Response::HTTP_OK);
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
                'id' => 'integer|required|exists:proveedores_turisticos,id',
                'usuario_id' => 'integer|nullable|exists:usuarios,id|unique:proveedores_turisticos,usuario_id,' . $id,
                'nombre_comercial' => 'string|required|max:150',
                'razon_social' => 'string|nullable|max:150',
                'nit' => 'string|nullable|max:50',
                'telefono' => 'string|nullable|max:30',
                'correo' => 'email|nullable|max:150',
                'destino_id' => 'integer|nullable|exists:destinos,id',
                'estado_verificacion' => 'string|nullable|max:30',
                'estado' => 'boolean|required',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $proveedor = ProveedorTuristico::modificarOCrear($datos);
            if ($proveedor) {
                DB::commit();
                ArchivosService::confirmar();
                return response(
                    get_response_body(['El proveedor turístico ha sido modificado.', 1], $proveedor),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback();
                ArchivosService::revertir();
                return response(get_response_body(['Ocurrió un error al intentar modificar el proveedor turístico.']), Response::HTTP_CONFLICT);
            }
        } catch (Exception $e) {
            DB::rollback();
            ArchivosService::revertir();
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function verificar(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $datos = $request->all();
            $datos['id'] = $id;
            $validator = Validator::make($datos, [
                'id' => 'integer|required|exists:proveedores_turisticos,id',
                'estado_verificacion' => 'string|required|in:pendiente,aprobado,rechazado',
                'observaciones_verificacion' => 'string|nullable',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $proveedor = ProveedorTuristico::verificar($datos);
            DB::commit();
            ArchivosService::confirmar();
            return response(
                get_response_body(['El proveedor turístico ha sido verificado.', 1], $proveedor),
                Response::HTTP_OK
            );
        } catch (Exception $e) {
            DB::rollback();
            ArchivosService::revertir();
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $datos['id'] = $id;
            $validator = Validator::make($datos, [
                'id' => 'integer|required|exists:proveedores_turisticos,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            ArchivosService::programarEliminacionCarpeta('proveedores', $id, ArchivosService::PRIVADO);
            $eliminado = ProveedorTuristico::eliminar($id);
            if ($eliminado) {
                DB::commit();
                ArchivosService::confirmar();
                return response(
                    get_response_body(['El proveedor turístico ha sido eliminado.', 3]),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback();
                ArchivosService::revertir();
                return response(get_response_body(['Ocurrió un error al intentar eliminar el proveedor turístico.']), Response::HTTP_CONFLICT);
            }
        } catch (Exception $e) {
            DB::rollback();
            ArchivosService::revertir();
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
