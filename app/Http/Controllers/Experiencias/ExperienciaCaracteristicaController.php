<?php

namespace App\Http\Controllers\Experiencias;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AlcancePorRol;
use Illuminate\Support\Facades\Validator;
use App\Models\Experiencias\ExperienciaCaracteristica;

class ExperienciaCaracteristicaController extends Controller
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

            return response(ExperienciaCaracteristica::obtenerColeccion($datos), Response::HTTP_OK);
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
                'experiencia_id' => 'integer|required|exists:experiencias,id',
                'caracteristica_id' => 'integer|required|exists:caracteristicas,id',
                'incluida' => 'boolean|nullable',
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

            $relacion = ExperienciaCaracteristica::modificarOCrear($datos);
            DB::commit();
            return response(
                get_response_body(['La característica ha sido asignada a la experiencia.', 2], $relacion),
                Response::HTTP_CREATED
            );
        } catch (Exception $e) {
            DB::rollback();
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $datos = $request->all();
            $datos['id'] = $id;
            $validator = Validator::make($datos, [
                'id' => 'integer|required|exists:experiencia_caracteristica,id',
                'incluida' => 'boolean|required',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->registroDeExperienciaEnAlcance('experiencia_caracteristica', $id)) {
                return $this->respuestaSinAcceso();
            }

            $relacion = ExperienciaCaracteristica::modificarOCrear($datos);
            DB::commit();
            return response(
                get_response_body(['La característica ha sido modificada.', 1], $relacion),
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
                'id' => 'integer|required|exists:experiencia_caracteristica,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->registroDeExperienciaEnAlcance('experiencia_caracteristica', $id)) {
                return $this->respuestaSinAcceso();
            }

            $eliminado = ExperienciaCaracteristica::eliminar($id);
            if ($eliminado) {
                DB::commit();
                return response(
                    get_response_body(['La característica ha sido removida de la experiencia.', 3]),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback();
                return response(get_response_body(['Ocurrió un error al intentar remover la característica.']), Response::HTTP_CONFLICT);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
