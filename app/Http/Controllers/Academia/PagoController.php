<?php

namespace App\Http\Controllers\Academia;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Academia\Pago;
use Illuminate\Support\Facades\Validator;

class PagoController extends Controller
{
    private function reglas()
    {
        return [
            'alumno_id' => 'integer|required|exists:alumnos,id',
            'curso_id' => 'integer|nullable|exists:cursos,id',
            'plan_id' => 'integer|nullable|exists:planes,id',
            'monto' => 'numeric|required|min:0',
            'fecha_pago' => 'date|required',
            'metodo_pago' => 'string|required|in:efectivo,transferencia,tarjeta,otro',
            'referencia' => 'string|nullable|max:100',
            'observacion' => 'string|nullable',
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
            return response(Pago::obtenerColeccion($datos), Response::HTTP_OK);
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

            $pago = Pago::modificarOCrear($datos);
            if ($pago) {
                DB::commit();
                return response(get_response_body(['El pago ha sido registrado.', 2], $pago), Response::HTTP_CREATED);
            }
            DB::rollback();
            return response(get_response_body(['Ocurrió un error al intentar registrar el pago.']), Response::HTTP_CONFLICT);
        } catch (Exception $e) {
            DB::rollback();
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function show($id)
    {
        try {
            $datos['id'] = $id;
            $validator = Validator::make($datos, ['id' => 'integer|required|exists:pagos,id']);
            if ($validator->fails()) {
                return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
            }
            return response(Pago::cargar($id), Response::HTTP_OK);
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
                ['id' => 'integer|required|exists:pagos,id'],
                $this->reglas()
            ));
            if ($validator->fails()) {
                return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
            }

            $pago = Pago::modificarOCrear($datos);
            if ($pago) {
                DB::commit();
                return response(get_response_body(['El pago ha sido modificado.', 1], $pago), Response::HTTP_OK);
            }
            DB::rollback();
            return response(get_response_body(['Ocurrió un error al intentar modificar el pago.']), Response::HTTP_CONFLICT);
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
            $validator = Validator::make($datos, ['id' => 'integer|required|exists:pagos,id']);
            if ($validator->fails()) {
                return response(get_response_body(format_messages_validator($validator)), Response::HTTP_BAD_REQUEST);
            }

            $eliminado = Pago::eliminar($id);
            if ($eliminado) {
                DB::commit();
                return response(get_response_body(['El pago ha sido eliminado.', 3]), Response::HTTP_OK);
            }
            DB::rollback();
            return response(get_response_body(['Ocurrió un error al intentar eliminar el pago.']), Response::HTTP_CONFLICT);
        } catch (Exception $e) {
            DB::rollback();
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
