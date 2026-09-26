<?php

namespace App\Http\Controllers\Reservas;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AlcancePorRol;
use Illuminate\Support\Facades\Validator;
use App\Models\Reservas\Reserva;

class ReservaController extends Controller
{
    use AlcancePorRol;

    public function index(Request $request)
    {
        try {
            $datos = $this->aplicarAlcance($request->all());
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
                $reservas = Reserva::obtenerColeccionLigera($datos);
            } else {
                if (isset($datos['ordenar_por'])) {
                    $datos['ordenar_por'] = format_order_by_attributes($datos);
                }
                $reservas = Reserva::obtenerColeccion($datos);
            }
            return response($reservas, Response::HTTP_OK);
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
                'proveedor_id' => 'integer|required|exists:proveedores_turisticos,id',
                'disponibilidad_id' => 'integer|nullable|exists:experiencia_disponibilidad,id',
                'fecha' => 'date|required',
                'residencia' => 'string|nullable|max:20',
                'nombre' => 'string|required|max:150',
                'correo' => 'email|required|max:150',
                'telefono' => 'string|required|max:30',
                'cantidad_personas' => 'integer|required|min:1',
                'cantidad_chicos' => 'integer|nullable',
                'idioma' => 'string|nullable|max:50',
                'cupon_id' => 'integer|nullable|exists:cupones,id',
                'valor_total' => 'numeric|required',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            // El proveedor de la reserva siempre es el de la experiencia (no el que envíe el cliente HTTP).
            $datos['proveedor_id'] = DB::table('experiencias')->where('id', $datos['experiencia_id'])->value('proveedor_id');
            if (!$this->enAlcance($datos['proveedor_id'], $datos['usuario_id'])) {
                return $this->respuestaSinAcceso();
            }

            $reserva = Reserva::modificarOCrear($datos);

            if ($reserva) {
                DB::commit();
                return response(
                    get_response_body(['La reserva ha sido creada.', 2], $reserva),
                    Response::HTTP_CREATED
                );
            } else {
                DB::rollback();
                return response(get_response_body(['Ocurrió un error al intentar crear la reserva.']), Response::HTTP_CONFLICT);
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
                'id' => 'integer|required|exists:reservas,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->reservaEnAlcance($id)) {
                return $this->respuestaSinAcceso();
            }

            return response(Reserva::cargar($id), Response::HTTP_OK);
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
                'id' => 'integer|required|exists:reservas,id',
                'fecha' => 'date|required',
                'nombre' => 'string|required|max:150',
                'correo' => 'email|required|max:150',
                'telefono' => 'string|required|max:30',
                'cantidad_personas' => 'integer|required|min:1',
                'cantidad_chicos' => 'integer|nullable',
                'valor_total' => 'numeric|required',
                'observaciones' => 'string|nullable',
                'notas' => 'string|nullable',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->reservaEnAlcance($id)) {
                return $this->respuestaSinAcceso();
            }

            $reserva = Reserva::modificarOCrear($datos);
            if ($reserva) {
                DB::commit();
                return response(
                    get_response_body(['La reserva ha sido modificada.', 1], $reserva),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback();
                return response(get_response_body(['Ocurrió un error al intentar modificar la reserva.']), Response::HTTP_CONFLICT);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function cambiarEstado(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $datos = $request->all();
            $datos['id'] = $id;
            $validator = Validator::make($datos, [
                'id' => 'integer|required|exists:reservas,id',
                'estado' => 'string|required|in:pendiente,aceptada,rechazada,cancelada,finalizada,no_asistio',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->reservaEnAlcance($id)) {
                return $this->respuestaSinAcceso();
            }
            // El cliente solo puede cancelar su reserva.
            if ($this->esCliente() && $datos['estado'] !== 'cancelada') {
                return $this->respuestaSinAcceso();
            }

            $reserva = Reserva::cambiarEstado($id, $datos['estado']);
            DB::commit();
            return response(
                get_response_body(['El estado de la reserva ha sido actualizado.', 1], $reserva),
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
                'id' => 'integer|required|exists:reservas,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->reservaEnAlcance($id)) {
                return $this->respuestaSinAcceso();
            }

            $eliminado = Reserva::eliminar($id);
            if ($eliminado) {
                DB::commit();
                return response(
                    get_response_body(['La reserva ha sido eliminada.', 3]),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback();
                return response(get_response_body(['Ocurrió un error al intentar eliminar la reserva.']), Response::HTTP_CONFLICT);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
