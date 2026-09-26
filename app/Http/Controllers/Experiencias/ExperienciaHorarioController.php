<?php

namespace App\Http\Controllers\Experiencias;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AlcancePorRol;
use Illuminate\Support\Facades\Validator;
use App\Models\Experiencias\ExperienciaHorario;

class ExperienciaHorarioController extends Controller
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

            return response(ExperienciaHorario::obtenerColeccion($datos), Response::HTTP_OK);
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
                'dia_semana' => 'integer|required|between:0,6',
                'hora_inicio' => 'date_format:H:i|required',
                'hora_fin' => 'date_format:H:i|required|after:hora_inicio',
                'capacidad' => 'integer|nullable',
                'estado' => 'boolean|nullable',
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

            $horario = ExperienciaHorario::modificarOCrear($datos);
            DB::commit();
            return response(
                get_response_body(['El horario ha sido creado.', 2], $horario),
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
                'id' => 'integer|required|exists:experiencia_horarios,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->registroDeExperienciaEnAlcance('experiencia_horarios', $id)) {
                return $this->respuestaSinAcceso();
            }

            return response(ExperienciaHorario::cargar($id), Response::HTTP_OK);
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
                'id' => 'integer|required|exists:experiencia_horarios,id',
                'dia_semana' => 'integer|required|between:0,6',
                'hora_inicio' => 'date_format:H:i|required',
                'hora_fin' => 'date_format:H:i|required|after:hora_inicio',
                'capacidad' => 'integer|nullable',
                'estado' => 'boolean|nullable',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->registroDeExperienciaEnAlcance('experiencia_horarios', $id)) {
                return $this->respuestaSinAcceso();
            }

            $horario = ExperienciaHorario::modificarOCrear($datos);
            DB::commit();
            return response(
                get_response_body(['El horario ha sido modificado.', 1], $horario),
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
                'id' => 'integer|required|exists:experiencia_horarios,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->registroDeExperienciaEnAlcance('experiencia_horarios', $id)) {
                return $this->respuestaSinAcceso();
            }

            $eliminado = ExperienciaHorario::eliminar($id);
            if ($eliminado) {
                DB::commit();
                return response(
                    get_response_body(['El horario ha sido eliminado.', 3]),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback();
                return response(get_response_body(['Ocurrió un error al intentar eliminar el horario.']), Response::HTTP_CONFLICT);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
