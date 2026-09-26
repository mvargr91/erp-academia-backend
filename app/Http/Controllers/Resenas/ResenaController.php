<?php

namespace App\Http\Controllers\Resenas;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AlcancePorRol;
use Illuminate\Support\Facades\Validator;
use App\Models\Resenas\Resena;

class ResenaController extends Controller
{
    use AlcancePorRol;

    public function index(Request $request)
    {
        try {
            $datos = $this->aplicarAlcance($request->all(), null, 'usuario_id');
            $validator = Validator::make($datos, [
                'limite' => 'integer|between:1,500'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (isset($datos['ordenar_por'])) {
                $datos['ordenar_por'] = format_order_by_attributes($datos);
            }

            return response(Resena::obtenerColeccion($datos), Response::HTTP_OK);
        } catch (Exception $e) {
            return response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $datos = $this->aplicarAlcance($request->all(), null, 'usuario_id');
            $validator = Validator::make($datos, [
                'usuario_id' => 'integer|required|exists:usuarios,id',
                'experiencia_id' => 'integer|required|exists:experiencias,id',
                'reserva_id' => 'integer|nullable|exists:reservas,id',
                'calificacion' => 'integer|required|between:1,5',
                'comentario' => 'string|nullable',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $resena = Resena::modificarOCrear($datos);

            if ($resena) {
                DB::commit();
                return response(
                    get_response_body(['La reseña ha sido creada.', 2], $resena),
                    Response::HTTP_CREATED
                );
            } else {
                DB::rollback();
                return response(get_response_body(['Ocurrió un error al intentar crear la reseña.']), Response::HTTP_CONFLICT);
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
                'id' => 'integer|required|exists:resenas,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->enAlcance(null, DB::table('resenas')->where('id', $id)->value('usuario_id'))) {
                return $this->respuestaSinAcceso();
            }

            return response(Resena::cargar($id), Response::HTTP_OK);
        } catch (Exception $e) {
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $datos = $request->all();
            // Si el cliente edita su reseña, vuelve a moderación.
            if ($this->esCliente()) {
                $datos['estado'] = 'pendiente';
            }
            $datos['id'] = $id;
            $validator = Validator::make($datos, [
                'id' => 'integer|required|exists:resenas,id',
                'calificacion' => 'integer|required|between:1,5',
                'comentario' => 'string|nullable',
                'estado' => 'string|required|in:pendiente,aprobada,rechazada',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->enAlcance(null, DB::table('resenas')->where('id', $id)->value('usuario_id'))) {
                return $this->respuestaSinAcceso();
            }

            $resena = Resena::modificarOCrear($datos);
            if ($resena) {
                DB::commit();
                return response(
                    get_response_body(['La reseña ha sido modificada.', 1], $resena),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback();
                return response(get_response_body(['Ocurrió un error al intentar modificar la reseña.']), Response::HTTP_CONFLICT);
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
                'id' => 'integer|required|exists:resenas,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->enAlcance(null, DB::table('resenas')->where('id', $id)->value('usuario_id'))) {
                return $this->respuestaSinAcceso();
            }

            $eliminado = Resena::eliminar($id);
            if ($eliminado) {
                DB::commit();
                return response(
                    get_response_body(['La reseña ha sido eliminada.', 3]),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback();
                return response(get_response_body(['Ocurrió un error al intentar eliminar la reseña.']), Response::HTTP_CONFLICT);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
