<?php

namespace App\Http\Controllers\Turismo;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\ArchivosService;
use Illuminate\Support\Facades\Validator;
use App\Models\Turismo\Destino;

class DestinoController extends Controller
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
                $destinos = Destino::obtenerColeccionLigera($datos);
            } else {
                if (isset($datos['ordenar_por'])) {
                    $datos['ordenar_por'] = format_order_by_attributes($datos);
                }
                $destinos = Destino::obtenerColeccion($datos);
            }
            return response($destinos, Response::HTTP_OK);
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
                'imagen' => 'string|nullable|max:255',
                'archivo' => ArchivosService::REGLA_IMAGEN . '|nullable',
                'slug' => 'string|required|max:150|unique:destinos,slug',
                'pais' => 'string|nullable|max:100',
                'departamento' => 'string|nullable|max:100',
                'ciudad' => 'string|nullable|max:100',
                'destacado' => 'boolean',
                'estado' => 'boolean|required',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $anterior = ArchivosService::rutaActual('destinos', $datos['id'] ?? null, 'imagen');
            $destino = Destino::modificarOCrear($datos);
            if ($destino && ArchivosService::adjuntar($request, 'destinos', $destino['id'], 'imagen',
                ArchivosService::carpeta('destinos', $destino['id'], $destino['nombre'], 'imagen'), $anterior)) {
                $destino = Destino::cargar($destino['id']);
            }

            if ($destino) {
                DB::commit();
                ArchivosService::confirmar();
                return response(
                    get_response_body(['El destino ha sido creado.', 2], $destino),
                    Response::HTTP_CREATED
                );
            } else {
                DB::rollback();
                ArchivosService::revertir();
                return response(get_response_body(['Ocurrió un error al intentar crear el destino.']), Response::HTTP_CONFLICT);
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
                'id' => 'integer|required|exists:destinos,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            return response(Destino::cargar($id), Response::HTTP_OK);
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
                'id' => 'integer|required|exists:destinos,id',
                'nombre' => 'string|required|max:128',
                'imagen' => 'string|nullable|max:255',
                'archivo' => ArchivosService::REGLA_IMAGEN . '|nullable',
                'slug' => 'string|required|max:150|unique:destinos,slug,' . $id,
                'pais' => 'string|nullable|max:100',
                'departamento' => 'string|nullable|max:100',
                'ciudad' => 'string|nullable|max:100',
                'destacado' => 'boolean',
                'estado' => 'boolean|required',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $anterior = ArchivosService::rutaActual('destinos', $datos['id'] ?? null, 'imagen');
            $destino = Destino::modificarOCrear($datos);
            if ($destino && ArchivosService::adjuntar($request, 'destinos', $destino['id'], 'imagen',
                ArchivosService::carpeta('destinos', $destino['id'], $destino['nombre'], 'imagen'), $anterior)) {
                $destino = Destino::cargar($destino['id']);
            }
            if ($destino) {
                DB::commit();
                ArchivosService::confirmar();
                return response(
                    get_response_body(['El destino ha sido modificado.', 1], $destino),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback();
                ArchivosService::revertir();
                return response(get_response_body(['Ocurrió un error al intentar modificar el destino.']), Response::HTTP_CONFLICT);
            }
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
                'id' => 'integer|required|exists:destinos,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            ArchivosService::programarEliminacionCarpeta('destinos', $id);
            $eliminado = Destino::eliminar($id);
            if ($eliminado) {
                DB::commit();
                ArchivosService::confirmar();
                return response(
                    get_response_body(['El destino ha sido eliminado.', 3]),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback();
                ArchivosService::revertir();
                return response(get_response_body(['Ocurrió un error al intentar eliminar el destino.']), Response::HTTP_CONFLICT);
            }
        } catch (Exception $e) {
            DB::rollback();
            ArchivosService::revertir();
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
