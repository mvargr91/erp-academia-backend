<?php

namespace App\Http\Controllers\Inversiones;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Inversiones\Inversiones;
use App\Models\Inversiones\InversionProyecto;
use App\Models\Inversiones\Proyecto;
use Illuminate\Support\Facades\Validator;

class InversionProyectoController extends Controller
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
                $inversion = InversionProyecto::obtenerColeccionLigera($datos);
            }else{
                if(isset($datos['ordenar_por'])){
                    $datos['ordenar_por'] = format_order_by_attributes($datos);
                }
                $inversion = InversionProyecto::obtenerColeccion($datos);
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
                'inversiones' => 'required|array|min:1',
                'inversiones.*.id_inversionista' => 'integer|required|exists:inversionistas,id',
                'inversiones.*.id_tipo_inversion' => 'integer|required|exists:tipos_inversion,id',
                'inversiones.*.id_gestor' => 'nullable|integer|exists:gestores,id',
                'inversiones.*.valor_inversion' => 'numeric|required',
                'inversiones.*.fecha_inversion' => 'date|required',
                'inversiones.*.estado_inversion' => 'string|required|max:3',
                'inversiones.*.porcentaje_comision' => 'nullable|numeric',
                'inversiones.*.porcentaje_ret_fuente' => 'nullable|numeric',
                'inversiones.*.plazo_capital' => 'integer|required',
                'inversiones.*.plazo_interes' => 'integer|required',
                'inversiones.*.tasa_interes_inversion' => 'numeric|required',
                'inversiones.*.indicativo_forma_pago_int' => 'string|required|max:1',
            ]);
    
            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }
    
            
            $inversion = InversionProyecto::modificarOCrear($datos['inversiones'][0]);
    
           
            $proyectosExitosos = [];
            foreach ($datos['inversiones'] as $detalle) {
                $codigo = Proyecto::where('id', $detalle['id_proyecto'])->value('codigo_proyecto');
    
                $registro = InversionProyecto::create([
                    'id_inversion' => $inversion['id'],
                    'id_proyecto' => $detalle['id_proyecto'],
                    'id_inversionista' => $inversion['id_inversionista'],
                    'id_gestor' => $detalle['id_gestor'] ?? null,
                    'codigo_proyecto' => $codigo,
                    'fecha_inversion' => $inversion['fecha_inversion'],
                    'valor_inversion_por_proyecto' => $detalle['valor_inversion_proyecto'],
                    'usuario_creacion_id' => auth()->id(),
                    'usuario_creacion_nombre' => auth()->user()->name,
                    'usuario_modificacion_id' => auth()->id(),
                    'usuario_modificacion_nombre' => auth()->user()->name,
                ]);
    
                if (!$registro) {
                    throw new \Exception("Error al insertar el proyecto con ID {$detalle['id_proyecto']}");
                }
    
                $proyectosExitosos[] = [
                    'id_proyecto' => $detalle['id_proyecto'], 
                    'ret_fuente' => $datos['inversiones'][0]['porcentaje_ret_fuente']
                ];
            }
    
            // dd($proyectosExitosos);
            DB::commit();
    
            foreach ($proyectosExitosos as $idProyecto) {
                $ret_fuente = $idProyecto['ret_fuente'] ?? 0;
                // dd($dato );
                switch ($inversion['indicativo_forma_pago_int']) {
                    case 'M':
                        DB::statement('CALL generar_plan_inversion_mensual(?, ?, ?, ?, ?)', [
                            $inversion['id'],
                            $idProyecto['id_proyecto'],
                            $ret_fuente ,
                            auth()->id(),
                            auth()->user()->name,
                        ]);
                        break;
                    case 'T':
                        // Aquí podrías llamar otro SP si lo implementas
                        break;
                    default:
                        // Omitir si es otro tipo
                        break;
                }
            }
    
            return response(
                get_response_body(["Las inversiones han sido creadas exitosamente.", 2]),
                Response::HTTP_CREATED
            );
        } catch (Exception $e) {
            DB::rollBack();
            return response([
                'message' => 'Error en el proceso de guardado',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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
                'id' => 'integer|required|exists:inversiones,id'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            return response(InversionProyecto::cargar($id), Response::HTTP_OK);
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
                'id' => 'integer|required|exists:inversiones,id',
                'id_inversionista' => 'integer|required|exists:inversiones,id',
                'id_tipo_inversion' => 'integer|required|exists:tipos_inversion,id',
                'id_gestor' => 'integer|exists:gestores,id',
                'valor_inversion' => 'numeric|required',
                'fecha_inversion' => 'date|required',                
                'estado_inversion' => 'string|required|max:3',
                'porcentaje_comision' => 'numeric|required',
                'plazo_capital' => 'integer|required',
                'plazo_interes' => 'integer|required',
                'tasa_interes_inversion' => 'numeric|required',
                'indicativo_forma_pago_int' => 'string|required|max:1',
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            $inversion = InversionProyecto::modificarOCrear($datos);
            if($inversion){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["La inversión ha sido modificado.", 1], $inversion),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar modificar la inversión."]), Response::HTTP_CONFLICT);;
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
                'id' => 'integer|required|exists:inversiones,id'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            $eliminado = InversionProyecto::eliminar($id);
            if($eliminado){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["La inversión ha sido elimado.", 3]),
                    Response::HTTP_OK
                );
            }else{
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar eliminar la inversión."]), Response::HTTP_CONFLICT);
            }
        }catch (Exception $e){
            DB::rollback(); // Se devuelven los cambios, por que la transacción falla
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


}
