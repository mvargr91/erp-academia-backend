<?php

namespace App\Http\Controllers\Parametrizacion;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Parametrizacion\GestorDocumento;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class GestorDocumentoController extends Controller
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
                $GestorDocumento = GestorDocumento::obtenerColeccionLigera($datos);
            }else if($request->tipo){
                $GestorDocumento = GestorDocumento::obtenerColeccionLigeraTipo($datos);
            }
            else{
                if(isset($datos['ordenar_por'])){
                    $datos['ordenar_por'] = format_order_by_attributes($datos);
                }
                $GestorDocumento = GestorDocumento::obtenerColeccion($datos);
            }
            return response($GestorDocumento, Response::HTTP_OK);
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
                'id_gestor' => 'integer|required|exists:gestores,id', 
                'archivo' => 'file|required|mimes:pdf',
                'estado' => 'boolean|required',
            ]);
    
            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $idGestor = $datos['id_gestor'];

            if ($request->hasFile('archivo')) {
                $archivo = $request->file('archivo');
                $nombreArchivo = time() . '_' . $archivo->getClientOriginalName(); 
                $rutaAlmacenamiento = "gestores/{$idGestor}/{$nombreArchivo}";
                $archivo->storeAs("public/gestores/{$idGestor}", $nombreArchivo);
            } else {
                return response(get_response_body(["No se ha recibido ningún archivo."]), Response::HTTP_BAD_REQUEST);
            }
    
            $datos['nombre_archivo'] = $nombreArchivo;
            $datos['ruta_archivo'] = $rutaAlmacenamiento;
    
            $GestorDocumento = GestorDocumento::modificarOCrear($datos);
    
            if ($GestorDocumento) {
                DB::commit();
                return response(
                    get_response_body(["El documento ha sido guardado exitosamente.", 2], $GestorDocumento),
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
                'id' => 'integer|required|exists:gestores_documentos,id'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            return response(GestorDocumento::cargar($id), Response::HTTP_OK);
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
                'id' => 'integer|required|exists:gestores_documentos,id',
                'nombre' => 'string|required|max:128',
                'tipo_lista' => 'string|required|max:1',
                'estado' => 'boolean|required',
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            $GestorDocumento = GestorDocumento::modificarOCrear($datos);
            if($GestorDocumento){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["El documento ha sido modificado.", 1], $GestorDocumento),
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
    public function destroy($id, $gestor)
    {

        DB::beginTransaction(); // Se abre la transacción
        try{
            $datos['id'] = $id;
            $validator = Validator::make($datos, [
                'id' => 'integer|required|exists:gestores_documentos,id'
            ]);
            
            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }
            
            $eliminado = GestorDocumento::eliminar($id,$gestor);
            if($eliminado){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["El documento ha sido eliminado.", 3]),
                    Response::HTTP_OK
                );
            }else{
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar eliminar el GestorDocumento."]), Response::HTTP_CONFLICT);
            }
        }catch (Exception $e){
            DB::rollback(); // Se devuelven los cambios, por que la transacción falla
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    public function descargarArchivo($idGestor, $nombreArchivo)
    {
        // Ruta real dentro de storage
        $path = "private/public/gestores/{$idGestor}/{$nombreArchivo}";

        // Verificar si el archivo existe en storage
        if (!Storage::exists($path)) {
            return response()->json(['error' => 'Archivo no encontrado en el servidor'], 404);
        }

        // Descargar el archivo
        return response()->download(storage_path("app/" . $path));
    }
    

}
