<?php

namespace App\Http\Controllers\Reservas;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\AlcancePorRol;
use Illuminate\Support\Facades\Validator;
use App\Models\Reservas\AcompananteReserva;

class AcompananteReservaController extends Controller
{
    use AlcancePorRol;

    public function index(Request $request)
    {
        try {
            $datos = $request->all();
            $validator = Validator::make($datos, [
                'reserva_id' => 'integer|required|exists:reservas,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->reservaEnAlcance($datos['reserva_id'])) {
                return $this->respuestaSinAcceso();
            }

            return response(AcompananteReserva::obtenerColeccion($datos), Response::HTTP_OK);
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
                'reserva_id' => 'integer|required|exists:reservas,id',
                'nombre' => 'string|required|max:150',
                'correo' => 'email|nullable|max:150',
                'telefono' => 'string|nullable|max:30',
                'documento' => 'string|nullable|max:50',
                'edad' => 'integer|nullable',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->reservaEnAlcance($datos['reserva_id'])) {
                return $this->respuestaSinAcceso();
            }

            $acompanante = AcompananteReserva::modificarOCrear($datos);
            DB::commit();
            return response(
                get_response_body(['El acompañante ha sido creado.', 2], $acompanante),
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
                'id' => 'integer|required|exists:acompanantes_reserva,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->reservaEnAlcance(DB::table('acompanantes_reserva')->where('id', $id)->value('reserva_id'))) {
                return $this->respuestaSinAcceso();
            }

            return response(AcompananteReserva::cargar($id), Response::HTTP_OK);
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
                'id' => 'integer|required|exists:acompanantes_reserva,id',
                'nombre' => 'string|required|max:150',
                'correo' => 'email|nullable|max:150',
                'telefono' => 'string|nullable|max:30',
                'documento' => 'string|nullable|max:50',
                'edad' => 'integer|nullable',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->reservaEnAlcance(DB::table('acompanantes_reserva')->where('id', $id)->value('reserva_id'))) {
                return $this->respuestaSinAcceso();
            }

            $acompanante = AcompananteReserva::modificarOCrear($datos);
            DB::commit();
            return response(
                get_response_body(['El acompañante ha sido modificado.', 1], $acompanante),
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
                'id' => 'integer|required|exists:acompanantes_reserva,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            if (!$this->reservaEnAlcance(DB::table('acompanantes_reserva')->where('id', $id)->value('reserva_id'))) {
                return $this->respuestaSinAcceso();
            }

            $eliminado = AcompananteReserva::eliminar($id);
            if ($eliminado) {
                DB::commit();
                return response(
                    get_response_body(['El acompañante ha sido eliminado.', 3]),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback();
                return response(get_response_body(['Ocurrió un error al intentar eliminar el acompañante.']), Response::HTTP_CONFLICT);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
