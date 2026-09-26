<?php

namespace App\Http\Controllers\Academia;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Academia\Asistencia;
use Illuminate\Support\Facades\Validator;

class AsistenciaController extends Controller
{
    private function reglas()
    {
        return [
            'curso_id' => 'integer|required|exists:cursos,id',
            'fecha_sesion' => 'date|required',
            'observacion' => 'string|nullable',
            'asistentes' => 'array|nullable',
            'asistentes.*.alumno_id' => 'integer|required|exists:alumnos,id',
            'asistentes.*.presente' => 'boolean',
        ];
    }

    public function index(Request $request)
    {
        try {
            $datos = $request->all();
            $validator = Validator::make($datos, ['limite' => 'integer|between:1,500']);
            if ($validator->fails()) {
                return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
            }
            if (isset($datos['ordenar_por'])) {
                $datos['ordenar_por'] = format_order_by_attributes($datos);
            }
            return response(Asistencia::obtenerColeccion($datos), Response::HTTP_OK);
        } catch (Exception $e) {
            return response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $datos = $request->all();
            $validator = Validator::make($datos, $this->reglas());
            if ($validator->fails()) {
                return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
            }

            // Una sola sesión por curso y fecha.
            $existe = DB::table('asistencias')
                ->where('curso_id', $datos['curso_id'])
                ->where('fecha_sesion', $datos['fecha_sesion'])
                ->exists();
            if ($existe) {
                DB::rollback();
                return response(get_response_body(['Ya existe una toma de asistencia para este curso en esa fecha.']), Response::HTTP_CONFLICT);
            }

            $asistencia = Asistencia::modificarOCrear($datos);
            if ($asistencia) {
                DB::commit();
                return response(get_response_body(['La asistencia ha sido registrada.', 2], $asistencia), Response::HTTP_CREATED);
            }
            DB::rollback();
            return response(get_response_body(['Ocurrió un error al intentar registrar la asistencia.']), Response::HTTP_CONFLICT);
        } catch (Exception $e) {
            DB::rollback();
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        try {
            $datos['id'] = $id;
            $validator = Validator::make($datos, ['id' => 'integer|required|exists:asistencias,id']);
            if ($validator->fails()) {
                return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
            }
            return response(Asistencia::cargar($id), Response::HTTP_OK);
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
                ['id' => 'integer|required|exists:asistencias,id'],
                $this->reglas()
            ));
            if ($validator->fails()) {
                return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
            }

            $asistencia = Asistencia::modificarOCrear($datos);
            if ($asistencia) {
                DB::commit();
                return response(get_response_body(['La asistencia ha sido modificada.', 1], $asistencia), Response::HTTP_OK);
            }
            DB::rollback();
            return response(get_response_body(['Ocurrió un error al intentar modificar la asistencia.']), Response::HTTP_CONFLICT);
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
            $validator = Validator::make($datos, ['id' => 'integer|required|exists:asistencias,id']);
            if ($validator->fails()) {
                return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
            }

            $eliminado = Asistencia::eliminar($id);
            if ($eliminado) {
                DB::commit();
                return response(get_response_body(['La asistencia ha sido eliminada.', 3]), Response::HTTP_OK);
            }
            DB::rollback();
            return response(get_response_body(['Ocurrió un error al intentar eliminar la asistencia.']), Response::HTTP_CONFLICT);
        } catch (Exception $e) {
            DB::rollback();
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
