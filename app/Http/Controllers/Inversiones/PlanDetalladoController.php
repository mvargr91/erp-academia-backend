<?php

namespace App\Http\Controllers\Inversiones;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Inversiones\Inversiones;
use App\Models\Inversiones\InversionProyecto;
use App\Models\Inversiones\PlanDetallado;
use App\Models\Inversiones\Proyecto;
use Illuminate\Support\Facades\Validator;
use App\Exports\ProgramacionPagoExport;
use App\Exports\PlanDetalladoExport;
use Maatwebsite\Excel\Facades\Excel;
use App\Mail\PagosConfirmadosMail;
use Illuminate\Support\Facades\Mail;


class PlanDetalladoController extends Controller
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
                $inversion = PlanDetallado::obtenerColeccionLigera($datos);
            }else{
                if(isset($datos['ordenar_por'])){
                    $datos['ordenar_por'] = format_order_by_attributes($datos);
                }
                if($request->proyecto){
                    $inversion = PlanDetallado::obtenerColeccionProyecto($datos);
                }else{
                    $inversion = PlanDetallado::obtenerColeccion($datos);
                }
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
    
            
            $inversion = PlanDetallado::modificarOCrear($datos['inversiones'][0]);
    
           
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
                'id' => 'integer|required|exists:inversiones_plan_detallado,id'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            return response(PlanDetallado::cargar($id), Response::HTTP_OK);
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

            $inversion = PlanDetallado::modificarOCrear($datos);
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

            $eliminado = PlanDetallado::eliminar($id);
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

    public function ProgramacionInv(Request $request)
    {
        $datos = $request->all();
        $validator = Validator::make($datos, [
            'fechaProgramacion' => 'string|required'
        ]);

        if($validator->fails()) {
            return response(
                get_response_body(format_messages_validator($validator))
                , Response::HTTP_BAD_REQUEST
            );
        }

        $fechaActual = now()->toDateString();

        if ($datos['fechaProgramacion'] < $fechaActual) {            
            return response()->json([
                'data' => [
                    'datos'=> [], 
                    'desde'=> 0, 
                    'hasta'=> 0, 
                    'ultima_pagina'=> 0, 
                    'total'=> 0 
                ]
            ]);
        }
        
        $resultados = PlanDetallado::obtenerProgramacionInv($datos);

        return response()->json([
            'data' => $resultados
        ]);
    }


    public function ModificarProgramacionInv(Request $request)
    {

        DB::beginTransaction(); // Se abre la transacción
        try{
            $datos = $request->all();

            $programacionModificada = PlanDetallado::modificarProgramacionInv($datos);
            if($programacionModificada){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["La programación fue exitosa.", 1]),
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

    public function exportarProgramacion(Request $request)
    {
        $fechaFiltro = $request->input('fecha'); // Asegúrate de pasar 'fecha' en el request
        if (!$fechaFiltro) {
            return response()->json(['error' => 'Fecha requerida'], 422);
        }

        $datos = PlanDetallado::obtenerProgramacionParaExportar($fechaFiltro);

        return Excel::download(new ProgramacionPagoExport($datos), 'programacion-pago-' . time() . '.xlsx');
        
    }

    public function listarPagosProgramados(Request $request)
    {
        try {
            $dto = $request->all();           
            $respuesta = PlanDetallado::obtenerPagosProgramados($dto);

            return response($respuesta, Response::HTTP_OK);
        } catch (Exception $e) {
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function confirmarPagoIndividual(Request $request)
    {
        DB::beginTransaction();
        try {
            $datos = $request->all();
    
            $validator = Validator::make($datos, [
                'tipo_tercero' => 'required|in:I,G',
                'id_inversionista' => 'nullable|integer|exists:inversionistas,id',
                'id_gestor' => 'nullable|integer|exists:gestores,id',
            ]);
    
            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }
    
            $usuario = auth()->user();
    
            $resultado = PlanDetallado::marcarPagos(
                $datos['tipo_tercero'],
                $datos['tipo_tercero'] === 'I' ? $datos['id_inversionista'] : $datos['id_gestor'],
                $usuario->id,
                $usuario->name
            );
            
            $idsPagados = $resultado['ids'];
            $nombreTercero = $resultado['nombre_tercero'];
            $emailTercero = $resultado['email_tercero'];

            
            
            if (!empty($idsPagados)) {
                DB::commit();

                $detalles = PlanDetallado::obtenerDetallesPagos($idsPagados);
                $valorTotalPagado = collect($detalles)->sum(fn($p) => $p->valor_concepto - $p->valor_ret_fuente);
                Mail::to($emailTercero)
                ->bcc(env('MAIL_USERNAME')) 
                ->send(new PagosConfirmadosMail($nombreTercero, $valorTotalPagado, $detalles)
                );
            
                return response(
                    get_response_body(["Pago confirmado correctamente.", 1]),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback();
                return response(
                    get_response_body(["No se encontraron registros con estado PRG para marcar como PAG.", 4]),
                    Response::HTTP_NOT_FOUND
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

    public function listarResumenSemanalaDetallado(Request $request)
    {
        try {
            $dto = $request->all();           
            $respuesta = PlanDetallado::obtenerResumenSemanalaDetallado($dto);

            return response($respuesta, Response::HTTP_OK);
        } catch (Exception $e) {
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function exportarPlanDetallado(Request $request)
    {
        $filtros = $request->only([
            'id_proyecto',
            'id_inversionista',
            'tipo',
            'estado',
            'fechaDesde',
            'fechaHasta',
            'fechaPagoDesde',
            'fechaPagoHasta',
        ]);
      

        $datos = PlanDetallado::obtenerPlanDetalladoParaExportar($filtros);

        return Excel::download(new PlanDetalladoExport($datos), 'plan_detallado-' . time() . '.xlsx');
    }

    public function listarHcaPagosDetallado(Request $request)
    {
        try {
            $dto = $request->all();           
            $respuesta = PlanDetallado::obtenerHcaPagosDetallado($dto);

            return response($respuesta, Response::HTTP_OK);
        } catch (Exception $e) {
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }



}
