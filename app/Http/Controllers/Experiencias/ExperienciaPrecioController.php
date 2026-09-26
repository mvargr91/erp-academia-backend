<?php

namespace App\Http\Controllers\Experiencias;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AlcancePorRol;
use Illuminate\Support\Facades\Validator;
use App\Models\Experiencias\ExperienciaPrecio;

class ExperienciaPrecioController extends Controller
{
    use AlcancePorRol;

    public function index(Request $request)
    {
        try {
            $datos = $request->all();
            $validator = Validator::make($datos, [
                'experiencia_id' => 'integer|required|exists:experiencias,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->experienciaEnAlcance($datos['experiencia_id'])) {
                return $this->respuestaSinAcceso();
            }

            return response(ExperienciaPrecio::obtenerColeccion($datos), Response::HTTP_OK);
        } catch (Exception $e) {
            return response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $datos = $request->all();
            $validator = Validator::make($datos, array_merge(
                ['experiencia_id' => 'integer|required|exists:experiencias,id'],
                ExperienciaPrecio::reglas()
            ), ExperienciaPrecio::mensajes());

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }
            if ($error = ExperienciaPrecio::validarCantidadesUnicas([$datos])) {
                return response(get_response_body([$error]), Response::HTTP_BAD_REQUEST);
            }

            if (!$this->experienciaEnAlcance($datos['experiencia_id'])) {
                return $this->respuestaSinAcceso();
            }

            $precio = ExperienciaPrecio::modificarOCrear($datos);
            DB::commit();
            return response(
                get_response_body(['El precio ha sido creado.', 2], $precio),
                Response::HTTP_CREATED
            );
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
                'id' => 'integer|required|exists:experiencia_precios,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->registroDeExperienciaEnAlcance('experiencia_precios', $id)) {
                return $this->respuestaSinAcceso();
            }

            return response(ExperienciaPrecio::cargar($id), Response::HTTP_OK);
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
            $validator = Validator::make($datos, array_merge(
                ExperienciaPrecio::reglas(),
                ['id' => 'integer|required|exists:experiencia_precios,id']
            ), ExperienciaPrecio::mensajes());

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }
            if ($error = ExperienciaPrecio::validarCantidadesUnicas([$datos])) {
                return response(get_response_body([$error]), Response::HTTP_BAD_REQUEST);
            }
            // Un precio no cambia de experiencia.
            unset($datos['experiencia_id']);

            if (!$this->registroDeExperienciaEnAlcance('experiencia_precios', $id)) {
                return $this->respuestaSinAcceso();
            }

            $precio = ExperienciaPrecio::modificarOCrear($datos);
            DB::commit();
            return response(
                get_response_body(['El precio ha sido modificado.', 1], $precio),
                Response::HTTP_OK
            );
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
                'id' => 'integer|required|exists:experiencia_precios,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->registroDeExperienciaEnAlcance('experiencia_precios', $id)) {
                return $this->respuestaSinAcceso();
            }

            $eliminado = ExperienciaPrecio::eliminar($id);
            if ($eliminado) {
                DB::commit();
                return response(
                    get_response_body(['El precio ha sido eliminado.', 3]),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback();
                return response(get_response_body(['Ocurrió un error al intentar eliminar el precio.']), Response::HTTP_CONFLICT);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
