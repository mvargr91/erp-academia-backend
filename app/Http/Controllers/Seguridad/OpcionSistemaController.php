<?php

namespace App\Http\Controllers\Seguridad;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Seguridad\OpcionSistema;
use Illuminate\Support\Facades\Validator;

class OpcionSistemaController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        try{
            $datos = $request->all();
            if(!$request->ligera){
                $validator = Validator::make($datos, [
                    'limite' => 'integer|between:1,500'
                ]);

                if($validator->fails()) {
                    return response(
                        get_response_body(format_messages_validator($validator))
                        , Response::HTTP_BAD_REQUEST
                    );
                }
            }

            if($request->ligera){
                $opcionesSistema = OpcionSistema::obtenerColeccionLigera($datos);
            }else{
                if(isset($datos['ordenar_por'])){
                    $datos['ordenar_por'] = format_order_by_attributes($datos);
                }
                $opcionesSistema = OpcionSistema::obtenerColeccion($datos);
            }
            return response($opcionesSistema, Response::HTTP_OK);
        }catch(Exception $e){
            return response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        DB::beginTransaction(); // Se abre la transacción
        try {
            $datos = $request->all();
            $validator = Validator::make($datos, [
                'nombre' => 'string|required|min:3|max:128|unique:opciones_del_sistema,nombre',
                'modulo_id' => [
                    'integer',
                    'required',
                    Rule::exists('modulos','id')->where(function ($query) {
                        $query->where('estado', 1);
                    }),
                ],
                'posicion' => 'integer|required',
                'icono_menu' => 'string|nullable',
                'url' => 'string|required|max:128',
                'url_ayuda' => 'string|nullable|max:128',

            ],$messages=['aplicacion_id.exists'=>'La opción del sistema seleccionado no existe o está en estado inactivo']);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            $opcionSistema = OpcionSistema::modificarOCrear($datos);
            
            if ($opcionSistema) {
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["La opción del sistema ha sido creada.", 2], $opcionSistema),
                    Response::HTTP_CREATED
                );
            } else {
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar crear la opción del sistema."]), Response::HTTP_CONFLICT);
            }
        }catch (Exception $e){
            DB::rollback(); // Se devuelven los cambios, por que la transacción falla
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try{
            $datos['id'] = $id;
            $validator = Validator::make($datos, [
                'id' => 'integer|required|exists:opciones_del_sistema,id'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }
            return response(OpcionSistema::cargar($id), Response::HTTP_OK);
        }catch (Exception $e){
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        DB::beginTransaction(); // Se abre la transacción
        try{
            $datos = $request->all();
            $datos['id'] = $id;

            $validator = Validator::make($datos, [
                'id' => 'integer|required|exists:opciones_del_sistema,id',
                'nombre' => 'string|required|min:3|max:128|unique:opciones_del_sistema,nombre,'. $id,
                'modulo_id' => [
                    'integer',
                    'required',
                    Rule::exists('modulos','id')->where(function ($query) {
                        $query->where('estado', 1);
                    }),
                ],
                'posicion' => 'integer|required',
                'icono_menu' => 'string|nullable',
                'url' => 'string|required|max:128',
                'url_ayuda' => 'string|nullable|max:128',
            ],$messages=['aplicacion_id.exists'=>'La opción del sistema seleccionado no existe o está en estado inactivo']);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            $opcionSistema = OpcionSistema::modificarOCrear($datos);
            if($opcionSistema){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["La opción del sistema ha sido modificada.", 1], $opcionSistema),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar modificar la opción del sistema."]), Response::HTTP_CONFLICT);;
            }
        }catch (Exception $e){
            DB::rollback(); // Se devuelven los cambios, por que la transacción falla
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        DB::beginTransaction(); // Se abre la transacción
        try{
            $datos['id'] = $id;
            $validator = Validator::make($datos, [
                'id' => 'integer|required|exists:opciones_del_sistema,id'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            $eliminado = OpcionSistema::eliminar($id);
            if($eliminado){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["La opción del sistema ha sido eliminada.", 3]),
                    Response::HTTP_OK
                );
            }else{
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar eliminar La opción del sistema."]), Response::HTTP_CONFLICT);
            }
        }catch (Exception $e){
            DB::rollback(); // Se devuelven los cambios, por que la transacción falla
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
