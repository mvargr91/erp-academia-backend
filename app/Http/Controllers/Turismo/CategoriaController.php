<?php

namespace App\Http\Controllers\Turismo;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Services\ArchivosService;
use Illuminate\Support\Facades\Validator;
use App\Models\Turismo\Categoria;

class CategoriaController extends Controller
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
                $categorias = Categoria::obtenerColeccionLigera($datos);
            } else {
                if (isset($datos['ordenar_por'])) {
                    $datos['ordenar_por'] = format_order_by_attributes($datos);
                }
                $categorias = Categoria::obtenerColeccion($datos);
            }
            return response($categorias, Response::HTTP_OK);
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
                'categoria_padre_id' => 'integer|nullable|exists:categorias,id',
                'nombre' => 'string|required|max:128',
                'imagen' => 'string|nullable|max:255',
                'archivo' => ArchivosService::REGLA_IMAGEN . '|nullable',
                'slug' => 'string|required|max:150|unique:categorias,slug',
                'orden' => 'integer|nullable',
                'estado' => 'boolean|required',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $anterior = ArchivosService::rutaActual('categorias', $datos['id'] ?? null, 'imagen');
            $categoria = Categoria::modificarOCrear($datos);
            if ($categoria && ArchivosService::adjuntar($request, 'categorias', $categoria['id'], 'imagen',
                ArchivosService::carpeta('categorias', $categoria['id'], $categoria['nombre'], 'imagen'), $anterior)) {
                $categoria = Categoria::cargar($categoria['id']);
            }

            if ($categoria) {
                DB::commit();
                ArchivosService::confirmar();
                return response(
                    get_response_body(['La categoría ha sido creada.', 2], $categoria),
                    Response::HTTP_CREATED
                );
            } else {
                DB::rollback();
                ArchivosService::revertir();
                return response(get_response_body(['Ocurrió un error al intentar crear la categoría.']), Response::HTTP_CONFLICT);
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
                'id' => 'integer|required|exists:categorias,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            return response(Categoria::cargar($id), Response::HTTP_OK);
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
                'id' => 'integer|required|exists:categorias,id',
                'categoria_padre_id' => 'integer|nullable|exists:categorias,id',
                'nombre' => 'string|required|max:128',
                'imagen' => 'string|nullable|max:255',
                'archivo' => ArchivosService::REGLA_IMAGEN . '|nullable',
                'slug' => 'string|required|max:150|unique:categorias,slug,' . $id,
                'orden' => 'integer|nullable',
                'estado' => 'boolean|required',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $anterior = ArchivosService::rutaActual('categorias', $datos['id'] ?? null, 'imagen');
            $categoria = Categoria::modificarOCrear($datos);
            if ($categoria && ArchivosService::adjuntar($request, 'categorias', $categoria['id'], 'imagen',
                ArchivosService::carpeta('categorias', $categoria['id'], $categoria['nombre'], 'imagen'), $anterior)) {
                $categoria = Categoria::cargar($categoria['id']);
            }
            if ($categoria) {
                DB::commit();
                ArchivosService::confirmar();
                return response(
                    get_response_body(['La categoría ha sido modificada.', 1], $categoria),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback();
                ArchivosService::revertir();
                return response(get_response_body(['Ocurrió un error al intentar modificar la categoría.']), Response::HTTP_CONFLICT);
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
                'id' => 'integer|required|exists:categorias,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            ArchivosService::programarEliminacionCarpeta('categorias', $id);
            $eliminado = Categoria::eliminar($id);
            if ($eliminado) {
                DB::commit();
                ArchivosService::confirmar();
                return response(
                    get_response_body(['La categoría ha sido eliminada.', 3]),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback();
                ArchivosService::revertir();
                return response(get_response_body(['Ocurrió un error al intentar eliminar la categoría.']), Response::HTTP_CONFLICT);
            }
        } catch (Exception $e) {
            DB::rollback();
            ArchivosService::revertir();
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
