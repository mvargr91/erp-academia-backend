<?php

namespace App\Http\Controllers\Parametrizacion;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\Parametrizacion\ParametroConstante;

class ParametroConstanteController extends Controller
{
    public function index(Request $request)
    {
        try{
            $datos = $request->all();
            $validator = Validator::make($datos, [
                'limite' => 'integer|between:1,500'
            ]);
            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }
            if (isset($datos['ordenar_por'])) {
                $datos['ordenar_por'] = format_order_by_attributes($datos);
            }
            $parametros = ParametroConstante::obtenerColeccion($datos);

            return response($parametros, Response::HTTP_OK);
        }catch(Exception $e){
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
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
        try{
            $datos = $request->all();
            $validator = Validator::make($datos, [
                'codigo_parametro' => 'string|required|min:1|max:128|unique:parametros_constantes,codigo_parametro',
                'descripcion_parametro' => 'string|required|min:1|max:128',
                'valor_parametro' => 'string|required|min:1|max:2000',
                'estado' => 'boolean'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            $parametro = ParametroConstante::modificarOCrear($datos);
            if(isset($parametro)){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["El parámetro ha sido creado.", 2], $parametro),
                    Response::HTTP_CREATED
                );
            } else {
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar crear el parámetro."]), Response::HTTP_CONFLICT);
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
                'id' => 'integer|required|exists:parametros_constantes,id'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            return response(ParametroConstante::cargar($id), Response::HTTP_OK);
        }catch (Exception $e){
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function consultar(Request $request)
    {
        try{
            $datos= $request->all();
            $validator = Validator::make($datos, [
                'codigo_parametro' => 'string|required|exists:parametros_constantes,codigo_parametro'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }
            $id = ParametroConstante::where('codigo_parametro','=', $datos['codigo_parametro'])->first();
            return response(ParametroConstante::cargar($id->id), Response::HTTP_OK);
        }catch (Exception $e){
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function consultarLugarInterno(Request $request)
    {
        try{
            $datos= $request->all();
            $validator = Validator::make($datos, [
                'lugar_id' => 'string|required|exists:lugares,id'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }
            $parametro = ParametroConstante::where('codigo_parametro', 'ID_CLIENTE_SECSEL')->first();
            if(!$parametro){
                return response('Debe definirse parámetro constante ID_CLIENTE_SECSEL', Response::HTTP_BAD_REQUEST); 
            }
            $pertenece = ParametroConstante::consultarLugarInterno($datos);
            return response($pertenece, Response::HTTP_OK);
        }catch (Exception $e){
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function tiposRol(Request $request)
    {
        try{
            $tiposRol = ParametroConstante::tiposRol();
            return response($tiposRol, Response::HTTP_OK);
        }catch (Exception $e){
            return response(get_response_body([$e->getMessage()]), Response::HTTP_INTERNAL_SERVER_ERROR);
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
                'id' => 'integer|required|exists:parametros_constantes,id',
                'codigo_parametro' => 'string|required|min:2|max:128|unique:parametros_constantes,codigo_parametro,' . $request->id,
                'descripcion_parametro' => 'string|required|min:2|max:128',
                'valor_parametro' => 'string|required|min:1|max:2000',
                'estado' => 'boolean'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            $parametro = ParametroConstante::modificarOCrear($datos);
            if(isset($parametro)){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["El parámetro ha sido modificado.", 1], $parametro),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar modificar el parámetro."]), Response::HTTP_CONFLICT);
            }
        }catch (Exception $e){
            DB::rollback(); // Se devuelven los cambios, por que la transacción falla
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
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
                'id' => 'integer|required|exists:parametros_constantes,id'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            $eliminado = ParametroConstante::eliminar($id);
            if($eliminado){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["El parámetro ha sido eliminado.", 3]),
                    Response::HTTP_OK
                );
            }else{
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar eliminar el parámetro."]), Response::HTTP_CONFLICT);
            }
        }catch (Exception $e){
            DB::rollback(); // Se devuelven los cambios, por que la transacción falla
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
