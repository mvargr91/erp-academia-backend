<?php

namespace App\Http\Controllers\Parametrizacion;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Parametrizacion\ActividadProyecto;
use Illuminate\Support\Facades\Validator;

class ActividadProyectoController extends Controller
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
                    'limite' => 'integer|between:1,500',
                ]);

                if($validator->fails()) {
                    return response(
                        get_response_body(format_messages_validator($validator))
                        , Response::HTTP_BAD_REQUEST
                    );
                }
            }

            if($request->ligera){
                $ActividadProyecto = ActividadProyecto::obtenerColeccionLigera($datos);
            }else  if($request->tipo){
                $ActividadProyecto = ActividadProyecto::obtenerColeccionLigeraXTipo($datos);
            }else{
                if(isset($datos['ordenar_por'])){
                    $datos['ordenar_por'] = format_order_by_attributes($datos);
                }
                $ActividadProyecto = ActividadProyecto::obtenerColeccion($datos);
            }
            return response($ActividadProyecto, Response::HTTP_OK);
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
                'id_tipo_proyecto' => 'integer|required|exists:tipos_proyectos,id',
                'id_etapa_proyecto' => 'integer|required|exists:etapas_proyectos,id',
                'nombre' => 'string|required|max:128',
                'secuencia' => 'integer|required',
                'Indicativo_fecha_vcmto' => 'string|required|max:1',
                'indicativo_envio_correo' => 'string|required|max:1',
                'estado' => 'boolean|required',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            // Validación de duplicado
            $existe = ActividadProyecto::where('id_tipo_proyecto', $datos['id_tipo_proyecto'])
                ->where('id_etapa_proyecto', $datos['id_etapa_proyecto'])
                ->where('secuencia', $datos['secuencia'])
                ->exists();

            if ($existe) {
                return response(get_response_body(["Ya existe una actividad con esta combinación de tipo de proyecto, etapa y secuencia", 4]), Response::HTTP_OK);
            }

            $ActividadProyecto = ActividadProyecto::modificarOCrear($datos);
            
            if ($ActividadProyecto) {
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["La actividad proyecto ha sido creada.", 2], $ActividadProyecto),
                    Response::HTTP_CREATED
                );
            } else {
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar crear la actividad proyecto."]), Response::HTTP_CONFLICT);
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
                'id' => 'integer|required|exists:actividades_proyectos,id'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            return response(ActividadProyecto::cargar($id), Response::HTTP_OK);
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
                'id' => 'integer|required|exists:actividades_proyectos,id',
                'id_tipo_proyecto' => 'integer|required|exists:tipos_proyectos,id',
                'id_etapa_proyecto' => 'integer|required|exists:etapas_proyectos,id',
                'nombre' => 'string|required|max:128',
                'secuencia' => 'integer|required',
                'Indicativo_fecha_vcmto' => 'string|required|max:1',
                'indicativo_envio_correo' => 'string|required|max:1',
                'estado' => 'boolean|required',
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            // Validación de duplicado excluyendo el actual
            $existe = ActividadProyecto::where('id_tipo_proyecto', $datos['id_tipo_proyecto'])
                ->where('id_etapa_proyecto', $datos['id_etapa_proyecto'])
                ->where('secuencia', $datos['secuencia'])
                ->where('id', '!=', $datos['id'])
                ->exists();

            if ($existe) {
                return response(get_response_body(["Ya existe una actividad con esta combinación de tipo de proyecto, etapa y secuencia", 4]), Response::HTTP_OK);
            }


            $ActividadProyecto = ActividadProyecto::modificarOCrear($datos);
            if($ActividadProyecto){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["La actividad proyecto ha sido modificada.", 1], $ActividadProyecto),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar modificar la actividad proyecto."]), Response::HTTP_CONFLICT);;
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
                'id' => 'integer|required|exists:actividades_proyectos,id'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            $eliminado = ActividadProyecto::eliminar($id);
            if($eliminado){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["La actividad proyecto ha sido eliminada.", 3]),
                    Response::HTTP_OK
                );
            }else{
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar eliminar la actividad proyecto."]), Response::HTTP_CONFLICT);
            }
        }catch (Exception $e){
            DB::rollback(); // Se devuelven los cambios, por que la transacción falla
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

 
}
