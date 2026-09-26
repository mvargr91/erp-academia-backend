<?php

namespace App\Http\Controllers\Experiencias;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AlcancePorRol;
use Illuminate\Support\Facades\Validator;
use App\Models\Experiencias\ExperienciaDisponibilidad;

class ExperienciaDisponibilidadController extends Controller
{
    use AlcancePorRol;

    public function index(Request $request)
    {
        try {
            $datos = $request->all();
            $validator = Validator::make($datos, [
                'experiencia_id' => 'integer|required|exists:experiencias,id',
                'fecha_desde' => 'date|nullable',
                'fecha_hasta' => 'date|nullable',
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

            return response(ExperienciaDisponibilidad::obtenerColeccion($datos), Response::HTTP_OK);
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
                'fecha' => 'date|required',
                'hora_inicio' => 'date_format:H:i|required',
                'hora_fin' => 'date_format:H:i|nullable',
                'capacidad' => 'integer|required',
                'cupos_disponibles' => 'integer|nullable',
                'estado' => 'string|nullable|max:30',
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

            $disponibilidad = ExperienciaDisponibilidad::modificarOCrear($datos);
            DB::commit();
            return response(
                get_response_body(['La disponibilidad ha sido creada.', 2], $disponibilidad),
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
                'id' => 'integer|required|exists:experiencia_disponibilidad,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->registroDeExperienciaEnAlcance('experiencia_disponibilidad', $id)) {
                return $this->respuestaSinAcceso();
            }

            return response(ExperienciaDisponibilidad::cargar($id), Response::HTTP_OK);
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
                'id' => 'integer|required|exists:experiencia_disponibilidad,id',
                'fecha' => 'date|required',
                'hora_inicio' => 'date_format:H:i|required',
                'hora_fin' => 'date_format:H:i|nullable',
                'capacidad' => 'integer|required',
                'cupos_disponibles' => 'integer|required',
                'estado' => 'string|nullable|max:30',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->registroDeExperienciaEnAlcance('experiencia_disponibilidad', $id)) {
                return $this->respuestaSinAcceso();
            }

            $disponibilidad = ExperienciaDisponibilidad::modificarOCrear($datos);
            DB::commit();
            return response(
                get_response_body(['La disponibilidad ha sido modificada.', 1], $disponibilidad),
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
                'id' => 'integer|required|exists:experiencia_disponibilidad,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->registroDeExperienciaEnAlcance('experiencia_disponibilidad', $id)) {
                return $this->respuestaSinAcceso();
            }

            $eliminado = ExperienciaDisponibilidad::eliminar($id);
            if ($eliminado) {
                DB::commit();
                return response(
                    get_response_body(['La disponibilidad ha sido eliminada.', 3]),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback();
                return response(get_response_body(['Ocurrió un error al intentar eliminar la disponibilidad.']), Response::HTTP_CONFLICT);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
