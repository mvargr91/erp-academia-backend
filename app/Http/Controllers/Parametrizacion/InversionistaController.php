<?php

namespace App\Http\Controllers\Parametrizacion;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Parametrizacion\Inversionista;
use App\Exports\InversionistasExport;
use Illuminate\Support\Facades\Validator;

class InversionistaController extends Controller
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
                $compania = Inversionista::obtenerColeccionLigera($datos);
            }else{
                if(isset($datos['ordenar_por'])){
                    $datos['ordenar_por'] = format_order_by_attributes($datos);
                }
                $compania = Inversionista::obtenerColeccion($datos);
            }
            return response($compania, Response::HTTP_OK);
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
                'tipo_documento' => 'string|required|max:3',
                'numero_documento' => 'string|required|max:128',
                'email' => 'string|required|max:128',
                'indicativo_socio' => 'string|required|max:1',
                'id_tipo_inversion' => 'integer|required|exists:tipos_inversion,id',
                'id_banco' => 'integer|nullable|exists:bancos,id',
                'tipo_cuenta' => 'string|max:1|nullable',
                'numero_cuenta' => 'string|max:128|nullable',
                'estado' => 'boolean|required',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            // Validación manual personalizada
            $existe = Inversionista::where('numero_documento', $datos['numero_documento'])->exists();
            if ($existe) {
                return response()->json([
                    'mensajes' => ['El número de documento ya está registrado por otro inversionista.'],
                ], Response::HTTP_BAD_REQUEST);
                
            }

            $compania = Inversionista::modificarOCrear($datos);
            
            if ($compania) {
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["El inversionista ha sido creado.", 2], $compania),
                    Response::HTTP_CREATED
                );
            } else {
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar crear el inversionista"]), Response::HTTP_CONFLICT);
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
                'id' => 'integer|required|exists:inversionistas,id'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            return response(Inversionista::cargar($id), Response::HTTP_OK);
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
                'id' => 'integer|required|exists:inversionistas,id',
                'nombre' => 'string|required|max:128',
                'tipo_documento' => 'string|required|max:3',
                'numero_documento' => 'string|required|max:128',
                'email' => 'string|required|max:128',
                'indicativo_socio' => 'string|required|max:1',
                'id_tipo_inversion' => 'integer|required|exists:tipos_inversion,id',
                'id_banco' => 'integer|nullable|exists:bancos,id',
                'tipo_cuenta' => 'string|max:1|nullable',
                'numero_cuenta' => 'string|max:128|nullable',
                'estado' => 'boolean|required',
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            $compania = Inversionista::modificarOCrear($datos);
            if($compania){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["El inversionista ha sido modificado.", 1], $compania),
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
                'id' => 'integer|required|exists:inversionistas,id'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            $eliminado = Inversionista::eliminar($id);
            if($eliminado){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["El inversionista ha sido eliminado.", 3]),
                    Response::HTTP_OK
                );
            }else{
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar eliminar el inversionista."]), Response::HTTP_CONFLICT);
            }
        }catch (Exception $e){
            DB::rollback(); // Se devuelven los cambios, por que la transacción falla
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    // Metodo para realizar la exportación de los inversionistas en formato excel.
    public function exportarInversionistas(Request $request)
    {
        $export = new InversionistasExport($request->all());
        $guias = $export->query()->get();
        // Nombres de los archivos Excel
        $nombreArchivoInversionistas = 'inversionistas-' . time() . '.xlsx';

        // Guardar el archivo de guías generales
        $export->store($nombreArchivoInversionistas, 'local');


        // Descargar solo el archivo de guías de transporte
        return response()->download(storage_path('app/' . $nombreArchivoInversionistas))->deleteFileAfterSend(true);
    }
 
}
