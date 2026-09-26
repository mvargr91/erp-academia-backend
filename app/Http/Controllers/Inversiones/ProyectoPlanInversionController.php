<?php

namespace App\Http\Controllers\Inversiones;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Inversiones\Inversiones;
use App\Models\Inversiones\ProyectoPlanInversion;
use App\Models\Inversiones\Proyecto;
use Illuminate\Support\Facades\Validator;

class ProyectoPlanInversionController extends Controller
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
                $inversion = ProyectoPlanInversion::obtenerColeccionLigera($datos);
            }else{
                if(isset($datos['ordenar_por'])){
                    $datos['ordenar_por'] = format_order_by_attributes($datos);
                }
                $inversion = ProyectoPlanInversion::obtenerColeccion($datos);
            }
            return response($inversion, Response::HTTP_OK);
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
                'id_inversionista' => 'required|integer|exists:inversionistas,id',
                'id_proyecto' => 'required|integer|exists:proyectos,id',
                'plan_pagos' => 'required|array|min:1',
                'plan_pagos.*.fecha_planeada' => 'required|date',
                'plan_pagos.*.valor' => 'required|numeric',
                'plan_pagos.*.fecha_pago' => 'nullable|date',
                'plan_pagos.*.estado_plan_inversion' => 'required|string|max:3',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $resultado = ProyectoPlanInversion::modificarOCrear($datos);

            DB::commit();

            return response(
                get_response_body(['El proyecto plan inversión ha sido creado', 2], $resultado),
                Response::HTTP_CREATED
            );
        } catch (Exception $e) {
            DB::rollBack();

            return response([
                'message' => 'Error en el proceso de guardado',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
        
    

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id_inversionista, $id_proyecto)
    {
        try{
            $datos['id_inversionista'] = (int) $id_inversionista;
            $datos['id_proyecto'] = (int) $id_proyecto;
            $validator = Validator::make($datos, [
                'id_inversionista' => 'required|exists:proyectos_plan_inversiones,id_inversionista',
                'id_proyecto' => 'required|exists:proyectos_plan_inversiones,id_proyecto'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            return response(ProyectoPlanInversion::cargar($id_proyecto, $id_inversionista), Response::HTTP_OK);
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


    public function update(Request $request, $id_inversionista, $id_proyecto)
    {
        DB::beginTransaction();

        try {
            $datos = $request->all();
            $datos['id_inversionista'] = (int) $id_inversionista;
            $datos['id_proyecto'] = (int) $id_proyecto;
            $validator = Validator::make($datos, [
                'id_inversionista' => 'required|integer|exists:inversionistas,id',
                'id_proyecto' => 'required|integer|exists:proyectos,id',
                'plan_pagos' => 'required|array|min:1',

                'plan_pagos.*.fecha_planeada' => 'required|date',
                'plan_pagos.*.valor' => 'required|numeric',
                'plan_pagos.*.fecha_pago' => 'nullable|date',
                'plan_pagos.*.estado_plan_inversion' => 'required|string|max:3',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }

            $resultado = ProyectoPlanInversion::modificarOCrear($datos);

            DB::commit();

            return response(
                get_response_body(['El proyecto plan inversión ha sido actualizado.', 2], $resultado),
                Response::HTTP_OK
            );
        } catch (Exception $e) {
            DB::rollBack();

            return response([
                'message' => 'Error en el proceso de guardado',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id_inversionista, $id_proyecto)
    {
        DB::beginTransaction(); // Se abre la transacción
        try{
            $datos['id_inversionista'] = (int) $id_inversionista;
            $datos['id_proyecto'] = (int) $id_proyecto;
            $validator = Validator::make($datos, [
                'id_inversionista' => 'required|exists:proyectos_plan_inversiones,id_inversionista',
                'id_proyecto' => 'required|exists:proyectos_plan_inversiones,id_proyecto'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            $eliminado = ProyectoPlanInversion::eliminar($id_inversionista, $id_proyecto);
            if($eliminado){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["El proyecto plan inversión ha sido elimado.", 3]),
                    Response::HTTP_OK
                );
            }else{
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar eliminar el proyecto plan inversión."]), Response::HTTP_CONFLICT);
            }
        }catch (Exception $e){
            DB::rollback(); // Se devuelven los cambios, por que la transacción falla
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


}
