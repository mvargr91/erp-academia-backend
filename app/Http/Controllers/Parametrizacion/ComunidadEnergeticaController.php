<?php

namespace App\Http\Controllers\Parametrizacion;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Parametrizacion\ComunidadEnergetica;
use Illuminate\Support\Facades\Validator;

class ComunidadEnergeticaController extends Controller
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
                $ComunidadEnergetica = ComunidadEnergetica::obtenerColeccionLigera($datos);
            }else{
                if(isset($datos['ordenar_por'])){
                    $datos['ordenar_por'] = format_order_by_attributes($datos);
                }
                $ComunidadEnergetica = ComunidadEnergetica::obtenerColeccion($datos);
            }
            return response($ComunidadEnergetica, Response::HTTP_OK);
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
                'nombre' => 'string|required|max:128',
                'numero_resolucion' => 'string|required|max:64',
                'fecha_resolucion' => 'date|required',
                'nurin' => 'string|required|max:64',
                'tipo_comunidad' => 'string|required|max:3',
                'nombre_rep_principal' => 'string|required|max:128',
                'tipo_documento_rep_ppal' => 'string|required|max:3',
                'numero_documento_rep_ppal' => 'string|required|max:128',
                'nombre_rep_legal' => 'string|required|max:128',
                'tipo_documento_rep_legal' => 'string|required|max:3',
                'numero_documento_rep_legal' => 'string|required|max:128',
                'telefono_rep_legal' => 'string|required|max:128',
                'email_rep_legal' => 'string|required|max:128',
                'estado' => 'boolean|required',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            $ComunidadEnergetica = ComunidadEnergetica::modificarOCrear($datos);
            
            if ($ComunidadEnergetica) {
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["La comunidad energetica ha sido creada.", 2], $ComunidadEnergetica),
                    Response::HTTP_CREATED
                );
            } else {
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar crear la comunidad energetica."]), Response::HTTP_CONFLICT);
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
                'id' => 'integer|required|exists:comunidades_energeticas,id'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            return response(ComunidadEnergetica::cargar($id), Response::HTTP_OK);
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
                'id' => 'integer|required|exists:comunidades_energeticas,id',
                'nombre' => 'string|required|max:128',
                'numero_resolucion' => 'string|required|max:64',
                'fecha_resolucion' => 'date|required',
                'nurin' => 'string|required|max:64',
                'tipo_comunidad' => 'string|required|max:3',
                'nombre_rep_principal' => 'string|required|max:128',
                'tipo_documento_rep_ppal' => 'string|required|max:3',
                'numero_documento_rep_ppal' => 'string|required|max:128',
                'nombre_rep_legal' => 'string|required|max:128',
                'tipo_documento_rep_legal' => 'string|required|max:3',
                'numero_documento_rep_legal' => 'string|required|max:128',
                'telefono_rep_legal' => 'string|required|max:128',
                'email_rep_legal' => 'string|required|max:128',
                'estado' => 'boolean|required',
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            $ComunidadEnergetica = ComunidadEnergetica::modificarOCrear($datos);
            if($ComunidadEnergetica){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["La comunidad energetica ha sido modificada.", 1], $ComunidadEnergetica),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar modificar la comunidad energetica."]), Response::HTTP_CONFLICT);;
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
                'id' => 'integer|required|exists:comunidades_energeticas,id'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            $eliminado = ComunidadEnergetica::eliminar($id);
            if($eliminado){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["La comunidad energetica ha sido eliminada.", 3]),
                    Response::HTTP_OK
                );
            }else{
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar eliminar el ComunidadEnergetica."]), Response::HTTP_CONFLICT);
            }
        }catch (Exception $e){
            DB::rollback(); // Se devuelven los cambios, por que la transacción falla
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
