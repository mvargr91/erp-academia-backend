<?php

namespace App\Http\Controllers\Parametrizacion;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Parametrizacion\CompaniaDocumento;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class CompaniaDocumentoController extends Controller
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
                $CompaniaDocumento = CompaniaDocumento::obtenerColeccionLigera($datos);
            }else if($request->tipo){
                $CompaniaDocumento = CompaniaDocumento::obtenerColeccionLigeraTipo($datos);
            }
            else{
                if(isset($datos['ordenar_por'])){
                    $datos['ordenar_por'] = format_order_by_attributes($datos);
                }
                $CompaniaDocumento = CompaniaDocumento::obtenerColeccion($datos);
            }
            return response($CompaniaDocumento, Response::HTTP_OK);
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
                'id_compania' => 'integer|required|exists:companias,id', 
                'archivo' => 'file|required|mimes:pdf',
                'estado' => 'boolean|required',
            ]);
    
            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $idCompania = $datos['id_compania'];

            if ($request->hasFile('archivo')) {
                $archivo = $request->file('archivo');
                $nombreArchivo = time() . '_' . $archivo->getClientOriginalName(); 
                $rutaAlmacenamiento = "companias/{$idCompania}/{$nombreArchivo}";
                $archivo->storeAs("public/companias/{$idCompania}", $nombreArchivo);
            } else {
                return response(get_response_body(["No se ha recibido ningún archivo."]), Response::HTTP_BAD_REQUEST);
            }
    
            $datos['nombre_archivo'] = $nombreArchivo;
            $datos['ruta_archivo'] = $rutaAlmacenamiento;
    
            $CompaniaDocumento = CompaniaDocumento::modificarOCrear($datos);
    
            if ($CompaniaDocumento) {
                DB::commit();
                return response(
                    get_response_body(["El documento ha sido guardado exitosamente.", 2], $CompaniaDocumento),
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
                'id' => 'integer|required|exists:companias_documentos,id'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            return response(CompaniaDocumento::cargar($id), Response::HTTP_OK);
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
                'id' => 'integer|required|exists:companias_documentos,id',
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

            $CompaniaDocumento = CompaniaDocumento::modificarOCrear($datos);
            if($CompaniaDocumento){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["El documento ha sido modificado.", 1], $CompaniaDocumento),
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
    public function destroy($id, $compania)
    {

        DB::beginTransaction(); // Se abre la transacción
        try{
            $datos['id'] = $id;
            $validator = Validator::make($datos, [
                'id' => 'integer|required|exists:companias_documentos,id'
            ]);
            
            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }
            
            $eliminado = CompaniaDocumento::eliminar($id,$compania);
            if($eliminado){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["El documento ha sido eliminado.", 3]),
                    Response::HTTP_OK
                );
            }else{
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar eliminar el CompaniaDocumento."]), Response::HTTP_CONFLICT);
            }
        }catch (Exception $e){
            DB::rollback(); // Se devuelven los cambios, por que la transacción falla
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    public function descargarArchivo($idCompania, $nombreArchivo)
    {
        // Ruta real dentro de storage
        $path = "private/public/companias/{$idCompania}/{$nombreArchivo}";

        // Verificar si el archivo existe en storage
        if (!Storage::exists($path)) {
            return response()->json(['error' => 'Archivo no encontrado en el servidor'], 404);
        }

        // Descargar el archivo
        return response()->download(storage_path("app/" . $path));
    }
    

}
