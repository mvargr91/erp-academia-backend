<?php

namespace App\Http\Controllers\Parametrizacion;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Parametrizacion\Proyecto;
use App\Exports\ProyectosExport;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ProyectoController extends Controller
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
                $proyecto = Proyecto::obtenerColeccionLigera($datos);
            }elseif($request->disponible){                
                $proyecto = Proyecto::obtenerColeccionLigeraProyectosDisponibles($datos);
            }else{
                if(isset($datos['ordenar_por'])){
                    $datos['ordenar_por'] = format_order_by_attributes($datos);
                }
                $proyecto = Proyecto::obtenerColeccion($datos);
            }
            return response($proyecto, Response::HTTP_OK);
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
                'codigo_proyecto' => 'string|required|max:128',
                'ciudad_id' => 'integer|required|exists:ciudades,id',
                'id_tipo_proyecto' => 'integer|required|exists:tipos_proyectos,id',
                'id_sector_proyecto' => 'integer|required|exists:sectores_proyectos,id',
                'potencia' => 'numeric|required',
                'generacion_anual' => 'numeric|required',
                'generacion_mensual' => 'numeric|required',
                'id_tipo_inversion' => 'integer|nullable|exists:tipos_inversion,id',
                'indicativo_plan_padrino' => 'string|required|max:1',
                'valor_total_proyecto' => 'numeric|required',
                'valor_inversion_proyecto' => 'numeric|nullable',
                'id_vehiculo_inversion' => 'integer|required|exists:vehiculos_inversion,id',
                'estado_proyecto' => 'boolean|required',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            // Formatear fecha si existe
            if (!empty($request->fecha_inicio_proyecto)) {
                $formattedDate = Carbon::parse($request->input('fecha_inicio_proyecto'))->format('Y-m-d');
                $datos['fecha_inicio_proyecto'] = $formattedDate;
            }

            $proyecto = Proyecto::modificarOCrear($datos);
            
            if ($proyecto) {
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["El proyecto ha sido creado.", 2], $proyecto),
                    Response::HTTP_CREATED
                );
            } else {
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar crear el proyecto."]), Response::HTTP_CONFLICT);
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
                'id' => 'integer|required|exists:proyectos,id'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            return response(Proyecto::cargar($id), Response::HTTP_OK);
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
                'id' => 'integer|required|exists:proyectos,id',
                'nombre' => 'string|required|max:128',
                'codigo_proyecto' => 'string|required|max:128',
                'ciudad_id' => 'integer|required|exists:ciudades,id',
                'id_tipo_proyecto' => 'integer|required|exists:tipos_proyectos,id',
                'id_sector_proyecto' => 'integer|required|exists:sectores_proyectos,id',
                'potencia' => 'numeric|required',
                'generacion_anual' => 'numeric|required',
                'generacion_mensual' => 'numeric|required',
                'id_tipo_inversion' => 'integer|nullable|exists:tipos_inversion,id',
                'indicativo_plan_padrino' => 'string|required|max:1',
                'valor_total_proyecto' => 'numeric|required',
                'valor_inversion_proyecto' => 'numeric|nullable',
                'id_vehiculo_inversion' => 'integer|required|exists:vehiculos_inversion,id',
                'estado_proyecto' => 'boolean|required',
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            // Formatear fecha si existe
            if (!empty($request->fecha_inicio_proyecto)) {
                $formattedDate = Carbon::parse($request->input('fecha_inicio_proyecto'))->format('Y-m-d');
                $datos['fecha_inicio_proyecto'] = $formattedDate;
            }

            $proyecto = Proyecto::modificarOCrear($datos);
            if($proyecto){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["El proyecto ha sido modificado.", 1], $proyecto),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar modificar el proyecto."]), Response::HTTP_CONFLICT);;
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
                'id' => 'integer|required|exists:proyectos,id'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            $eliminado = Proyecto::eliminar($id);
            if($eliminado){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["El proyecto ha sido eliminado.", 3]),
                    Response::HTTP_OK
                );
            }else{
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar eliminar el proyecto."]), Response::HTTP_CONFLICT);
            }
        }catch (Exception $e){
            DB::rollback(); // Se devuelven los cambios, por que la transacción falla
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    // Metodo para realizar la exportación de los proyectos en formato excel.
    public function exportarProyectos(Request $request)
    {
        $export = new ProyectosExport($request->all());
        $guias = $export->query()->get();
        // Nombres de los archivos Excel
        $nombreArchivoInversionistas = 'proyectos-' . time() . '.xlsx';

        // Guardar el archivo de guías generales
        $export->store($nombreArchivoInversionistas, 'local');


        // Descargar solo el archivo de guías de transporte
        return response()->download(storage_path('app/' . $nombreArchivoInversionistas))->deleteFileAfterSend(true);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id opcional
     * @return \Illuminate\Http\Response
     */
    public function avanceProyectos(Request $request)
    {
        $datos = $request->all();
        $proyectoAvances = Proyecto::avanceProyectos($datos);
        return response($proyectoAvances, Response::HTTP_OK);
    }
}
