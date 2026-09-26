<?php

namespace App\Http\Controllers\Parametrizacion;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Parametrizacion\InversionistaDocumento;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class InversionistaDocumentoController extends Controller
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
                $InversionistaDocumento = InversionistaDocumento::obtenerColeccionLigera($datos);
            }else if($request->tipo){
                $InversionistaDocumento = InversionistaDocumento::obtenerColeccionLigeraTipo($datos);
            }
            else{
                if(isset($datos['ordenar_por'])){
                    $datos['ordenar_por'] = format_order_by_attributes($datos);
                }
                $InversionistaDocumento = InversionistaDocumento::obtenerColeccion($datos);
            }
            return response($InversionistaDocumento, Response::HTTP_OK);
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
        DB::beginTransaction(); 
        try {
            $datos = $request->all();
            
            $validator = Validator::make($datos, [
                'id_lista_documento' => 'integer|required|exists:listas_documentos,id',
                'id_inversionista' => 'integer|required|exists:inversionistas,id', 
                'archivo' => 'file|required|mimes:pdf',                
                'estado_verificacion' => 'string|required|max:3',
            ]);
    
            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $idInversionista = $datos['id_inversionista'];

            if ($request->hasFile('archivo')) {
                $archivo = $request->file('archivo');
                $nombreArchivo = time() . '_' . $archivo->getClientOriginalName(); 
                $rutaAlmacenamiento = "inversionistas/{$idInversionista}/{$nombreArchivo}";
                $archivo->storeAs("public/inversionistas/{$idInversionista}", $nombreArchivo);
            } else {
                return response(get_response_body(["No se ha recibido ningún archivo."]), Response::HTTP_BAD_REQUEST);
            }
    
            $datos['nombre_archivo'] = $nombreArchivo;
            $datos['ruta_archivo'] = $rutaAlmacenamiento;
    
            $InversionistaDocumento = InversionistaDocumento::modificarOCrear($datos);
    
            if ($InversionistaDocumento) {
                DB::commit();
                return response(
                    get_response_body(["El documento ha sido guardado exitosamente.", 2], $InversionistaDocumento),
                    Response::HTTP_CREATED
                );
            } else {
                DB::rollback(); 
                return response(
                    get_response_body(["Ocurrió un error al intentar guardar el documento."]),
                    Response::HTTP_CONFLICT
                );
            }
        } catch (Exception $e) {
            DB::rollback(); 
            return response(
                get_response_body([$e->getMessage()]),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
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
                'id' => 'integer|required|exists:inversionistas_documentos,id'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            return response(InversionistaDocumento::cargar($id), Response::HTTP_OK);
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
    public function update(Request $request)
    {
        DB::beginTransaction(); // Se abre la transacción
        try{
            $datos = $request->all();
           
            $validator = Validator::make($datos, [
                'id' => 'integer|required|exists:inversionistas_documentos,id',
                'id_lista_documento' => 'integer|required|exists:listas_documentos,id',
                'id_inversionista' => 'integer|required|exists:inversionistas,id',         
                'estado_verificacion' => 'string|required|max:3',
            ]);
            
            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $InversionistaDocumento = InversionistaDocumento::modificarOCrear($datos);
            if($InversionistaDocumento){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["El documento ha sido modificado.", 1], $InversionistaDocumento),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar modificar la lista documento."]), Response::HTTP_CONFLICT);;
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
    public function destroy($id, $inversionista)
    {

        DB::beginTransaction(); // Se abre la transacción
        try{
            $datos['id'] = $id;
            $validator = Validator::make($datos, [
                'id' => 'integer|required|exists:inversionistas_documentos,id'
            ]);
            
            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }
            
            $eliminado = InversionistaDocumento::eliminar($id,$inversionista);
            if($eliminado){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["El documento ha sido eliminado.", 3]),
                    Response::HTTP_OK
                );
            }else{
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar eliminar el InversionistaDocumento."]), Response::HTTP_CONFLICT);
            }
        }catch (Exception $e){
            DB::rollback(); // Se devuelven los cambios, por que la transacción falla
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    public function descargarArchivo($idInversionista, $nombreArchivo)
    {
        // Ruta real dentro de storage
        $path = "private/public/inversionistas/{$idInversionista}/{$nombreArchivo}";

        // Verificar si el archivo existe en storage
        if (!Storage::exists($path)) {
            return response()->json(['error' => 'Archivo no encontrado en el servidor'], 404);
        }

        // Descargar el archivo
        return response()->download(storage_path("app/" . $path));
    }
    

}
