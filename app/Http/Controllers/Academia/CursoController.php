<?php

namespace App\Http\Controllers\Academia;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Academia\Curso;
use App\Models\Academia\Sede;
use Illuminate\Support\Facades\Validator;

class CursoController extends Controller
{
    private const MENSAJES = ['sede_id.required' => 'Elige la sede del curso.'];

    private function reglas($id = null)
    {
        return [
            'nombre' => 'string|nullable|max:120',
            'sede_id' => 'bail|required|integer|exists:sedes,id',
            'ritmo_id' => 'integer|required|exists:ritmos,id',
            'profesor_id' => 'integer|nullable|exists:profesores,id',
            'dia' => 'integer|required|between:0,6',
            'hora' => 'required|date_format:H:i',
            'fecha_inicio' => 'date|nullable',
            'cupo_max' => 'integer|nullable|min:1',
            'activo' => 'boolean|required',
            'alumnos' => 'array|nullable',
            'alumnos.*' => 'integer|exists:alumnos,id',
            'estado' => 'boolean|required',
        ];
    }

    public function index(Request $request)
    {
        try {
            $datos = $request->all();
            if (!$request->ligera) {
                $validator = Validator::make($datos, ['limite' => 'integer|between:1,500']);
                if ($validator->fails()) {
                    return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
                }
            }

            if ($request->ligera) {
                $cursos = Curso::obtenerColeccionLigera($datos);
            } else {
                if (isset($datos['ordenar_por'])) {
                    $datos['ordenar_por'] = format_order_by_attributes($datos);
                }
                $cursos = Curso::obtenerColeccion($datos);
            }
            return response($cursos, Response::HTTP_OK);
        } catch (Exception $e) {
            return response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $datos = $request->all();
            $datos['sede_id'] = Sede::resolver($datos['sede_id'] ?? null);
            $validator = Validator::make($datos, $this->reglas(), self::MENSAJES);
            if ($validator->fails()) {
                return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
            }

            $curso = Curso::modificarOCrear($datos);
            if ($curso) {
                DB::commit();
                return response(get_response_body(['El curso ha sido creado.', 2], $curso), Response::HTTP_CREATED);
            }
            DB::rollback();
            return response(get_response_body(['Ocurrió un error al intentar crear el curso.']), Response::HTTP_CONFLICT);
        } catch (Exception $e) {
            DB::rollback();
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        try {
            $datos['id'] = $id;
            $validator = Validator::make($datos, ['id' => 'integer|required|exists:cursos,id']);
            if ($validator->fails()) {
                return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
            }
            return response(Curso::cargar($id), Response::HTTP_OK);
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
            $datos['sede_id'] = Sede::resolver($datos['sede_id'] ?? null);
            $validator = Validator::make($datos, array_merge(
                ['id' => 'integer|required|exists:cursos,id'],
                $this->reglas($id)
            ), self::MENSAJES);
            if ($validator->fails()) {
                return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
            }

            $curso = Curso::modificarOCrear($datos);
            if ($curso) {
                DB::commit();
                return response(get_response_body(['El curso ha sido modificado.', 1], $curso), Response::HTTP_OK);
            }
            DB::rollback();
            return response(get_response_body(['Ocurrió un error al intentar modificar el curso.']), Response::HTTP_CONFLICT);
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
            $validator = Validator::make($datos, ['id' => 'integer|required|exists:cursos,id']);
            if ($validator->fails()) {
                return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
            }

            // Los pagos se conservan: un curso que ya recibió pagos se desactiva, no se elimina.
            if (DB::table('pagos')->where('curso_id', $id)->exists()) {
                DB::rollback();
                return response(
                    get_response_body(['El curso tiene pagos registrados y no se puede eliminar. Desactívalo: así deja de cobrar ciclos a sus alumnos.']),
                    Response::HTTP_CONFLICT
                );
            }

            $eliminado = Curso::eliminar($id);
            if ($eliminado) {
                DB::commit();
                return response(get_response_body(['El curso ha sido eliminado.', 3]), Response::HTTP_OK);
            }
            DB::rollback();
            return response(get_response_body(['Ocurrió un error al intentar eliminar el curso.']), Response::HTTP_CONFLICT);
        } catch (Exception $e) {
            DB::rollback();
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
