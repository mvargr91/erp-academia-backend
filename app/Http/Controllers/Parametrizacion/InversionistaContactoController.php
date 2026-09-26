<?php

namespace App\Http\Controllers\Parametrizacion;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Parametrizacion\InversionistaContacto;
use Illuminate\Support\Facades\Validator;

class InversionistaContactoController extends Controller
{
   /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request,  $inversionista_id)
    {
        try{
            $datos = $request->all();
            $datos['id_inversionista'] = $inversionista_id;
            if(!$request->ligera){
                $validator = Validator::make($datos, [
                    'limite' => 'integer|between:1,500',
                    'id_inversionista' => 'integer|exists:inversionistas,id|required' 
                ]);

                if($validator->fails()) {
                    return response(
                        get_response_body(format_messages_validator($validator))
                        , Response::HTTP_BAD_REQUEST
                    );
                }
            }

            if($request->ligera){
                $contacto = InversionistaContacto::obtenerColeccionLigera($datos);
            }else{
                if(isset($datos['ordenar_por'])){
                    $datos['ordenar_por'] = format_order_by_attributes($datos);
                }
                $contacto = InversionistaContacto::obtenerColeccion($datos);
            }
            return response($contacto, Response::HTTP_OK);
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
                'id_inversionista' => 'integer|required|exists:inversionistas,id',
                'tipo_contacto' => 'string|required|max:3',
                'nombre_contacto' => 'string|required|max:128',
                'email_contacto' => 'string|required|max:128',
                'estado' => 'boolean|required',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            // Validación manual personalizada
            $existe = InversionistaContacto::where('nombre_contacto', $datos['nombre_contacto'])
            ->where('id_inversionista', $datos['id_inversionista'])
            ->exists();

            if ($existe) {
                return response()->json([
                    'mensajes' => ['El nombre del contacto ya está registrado.'],
                ], Response::HTTP_BAD_REQUEST);
                
            }

            $contacto = InversionistaContacto::modificarOCrear($datos);
            
            if ($contacto) {
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["El contacto inversionista ha sido creado.", 2], $contacto),
                    Response::HTTP_CREATED
                );
            } else {
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar crear el contacto inversionista"]), Response::HTTP_CONFLICT);
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
    public function show($inversionista, $id)
    {
        try{
            $datos['id'] = $id;
            $validator = Validator::make($datos, [
                'id' => 'integer|required|exists:inversionistas_contactos,id'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            return response(InversionistaContacto::cargar($id), Response::HTTP_OK);
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
                'id' => 'integer|required|exists:inversionistas_contactos,id',
                'id_inversionista' => 'integer|required|exists:inversionistas,id',
                'tipo_contacto' => 'string|required|max:3',
                'nombre_contacto' => 'string|required|max:128',
                'email_contacto' => 'string|required|max:128',
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            // Validación manual personalizada
            $existe = InversionistaContacto::where('nombre_contacto', $datos['nombre_contacto'])
            ->where('id', '!=', $datos['id'])
            ->where('id_inversionista', $datos['id_inversionista'])
            ->exists();

            if ($existe) {
                return response()->json([
                    'mensajes' => ['El nombre del contacto ya está registrado.'],
                ], Response::HTTP_BAD_REQUEST);
                
            }

            $contacto = InversionistaContacto::modificarOCrear($datos);
            if($contacto){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["El contacto inversionista ha sido modificado.", 1], $contacto),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar modificar el inversionista."]), Response::HTTP_CONFLICT);;
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
                'id' => 'integer|required|exists:inversionistas_contactos,id'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            $eliminado = InversionistaContacto::eliminar($id);
            if($eliminado){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["El contacto inversionista ha sido eliminado.", 3]),
                    Response::HTTP_OK
                );
            }else{
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar eliminar el contacto inversionista."]), Response::HTTP_CONFLICT);
            }
        }catch (Exception $e){
            DB::rollback(); // Se devuelven los cambios, por que la transacción falla
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

 
}
