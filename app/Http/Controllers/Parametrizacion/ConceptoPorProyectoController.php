<?php

namespace App\Http\Controllers\Parametrizacion;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Parametrizacion\ConceptoPorProyecto;
use App\Models\Parametrizacion\ProyectoSimulacion;
use App\Services\ConceptosService;
use App\Services\ConceptosSimulacionService;
use Illuminate\Support\Facades\Validator;

class ConceptoPorProyectoController extends Controller
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
                $ConceptoPorProyecto = ConceptoPorProyecto::obtenerColeccionLigera($datos);
            }else if($request->head){                
                $ConceptoPorProyecto = ConceptoPorProyecto::cargarHead($datos);
            }else if($request->ejecutadas){                
                $ConceptoPorProyecto = ConceptoPorProyecto::obtenerColeccionEjecutadas($datos);
            }else if($request->previos){                
                $ConceptoPorProyecto = ConceptoPorProyecto::cargarDatosPrevios($datos);
            }else if($request->conceptos){                
                $ConceptoPorProyecto = ConceptoPorProyecto::conceptosParaPeriodo($datos);
            }else{
                if(isset($datos['ordenar_por'])){
                    $datos['ordenar_por'] = format_order_by_attributes($datos);
                }
                $ConceptoPorProyecto = ConceptoPorProyecto::obtenerColeccion($datos);
            }
            return response($ConceptoPorProyecto, Response::HTTP_OK);
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
                'id_proyecto' => 'integer|required|exists:proyectos,id',
                'anio' => 'integer|required',
                'mes' => 'integer|required',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            $ConceptoPorProyecto = ConceptoPorProyecto::modificarOCrear($datos);
            
            if ($ConceptoPorProyecto) {
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["El concepto por proyecto ha sido creado.", 2], $ConceptoPorProyecto),
                    Response::HTTP_CREATED
                );
            } else {
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar crear el concepto por proyecto."]), Response::HTTP_CONFLICT);
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
                'id' => 'integer|required|exists:valores_conceptos_por_proyectos,id'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            return response(ConceptoPorProyecto::cargar($id), Response::HTTP_OK);
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
                'id' => 'integer|required|exists:valores_conceptos_por_proyectos,id',
                'id_proyecto' => 'integer|required|exists:proyectos,id',
                'anio' => 'integer|required',
                'mes' => 'integer|required',
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }            

            $ConceptoPorProyecto = ConceptoPorProyecto::modificarOCrear($datos);
            if($ConceptoPorProyecto){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["El concepto por proyecto ha sido modificado.", 1], $ConceptoPorProyecto),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar modificar el concepto por proyecto."]), Response::HTTP_CONFLICT);
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
                'id' => 'integer|required|exists:valores_conceptos_por_proyectos,id'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            $eliminado = ConceptoPorProyecto::eliminar($id);
            if($eliminado){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["El concepto por proyecto ha sido eliminado.", 3]),
                    Response::HTTP_OK
                );
            }else{
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar eliminar el concepto por proyecto."]), Response::HTTP_CONFLICT);
            }
        }catch (Exception $e){
            DB::rollback(); // Se devuelven los cambios, por que la transacción falla
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function copiar(Request $request)
    {
        // --- Validación directa en el controlador ---
        $validator = Validator::make($request->all(), [
            'id_proyecto'  => 'required|integer',
            'anio_origen'  => 'required|integer',
            'mes_origen'   => 'required|integer|min:1|max:12',
            'anio_destino' => 'integer',
            'mes_destino'  => 'integer|min:1|max:12',
            'replace'      => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 'error',
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $data = $validator->validated();

        $idProyecto  = (int) $data['id_proyecto'];
        $anioOrigen  = (int) $data['anio_origen'];
        $mesOrigen   = (int) $data['mes_origen'];
        $anioDestino = (int) $data['anio_destino'];
        $mesDestino  = (int) $data['mes_destino'];
        $replace     = (bool) ($data['replace'] ?? false);

        // if ($anioOrigen === $anioDestino && $mesOrigen === $mesDestino) {
        //     return response()->json([
        //         'status'  => 'error',
        //         'message' => 'Hola',
        //     ], 422);
        // }

        // --- Validar existencia previa en DESTINO ---
        $existeDestino = ConceptoPorProyecto::existsInPeriod($idProyecto, $anioDestino, $mesDestino);

        if ($existeDestino && !$replace) {
            return response()->json([
                'status'  => 'exists',
                'message' => 'Ya existe información en el período destino.',
            ]);
        }

        // --- Validar existencia en ORIGEN ---
        $existeOrigen = ConceptoPorProyecto::existsInPeriod($idProyecto, $anioOrigen, $mesOrigen);

        DB::beginTransaction();
        try {
            if ($existeOrigen) {
                // copiar desde mes origen
                $inserted = ConceptoPorProyecto::copiarConceptos(
                    $idProyecto,
                    $anioOrigen,
                    $mesOrigen,
                    $anioDestino,
                    $mesDestino,
                    $replace
                );
            } else {
                // Generar todos los registros de todos los conceptos con valor 0
                $inserted = ConceptoPorProyecto::inicializarMesConCeros(
                    $idProyecto,
                    $anioDestino,
                    $mesDestino,
                    $replace
                );
            }

            if ($inserted > 0) {
                DB::commit();
                return response(
                    get_response_body(["Los concepto(s) por proyecto han sido creados.", 1], $inserted),
                    Response::HTTP_OK
                );
            } else {
                DB::commit();
                return response(
                    get_response_body(["No hay concepto(s) por proyecto para copiarlos o generarlos.", 4], $inserted),
                    Response::HTTP_OK
                );
            }

        } catch (\Throwable $e) {
            DB::rollback();
            return response(
                get_response_body(["Ocurrió un error al intentar crear los concepto por proyecto."]),
                Response::HTTP_CONFLICT
            );
        }
    }

    public function calcular(Request $request)
    {
        $datos = $request->all();

        $validator = Validator::make($datos, [
            'id_proyecto' => 'required|integer|exists:proyectos,id',
            'anio'        => 'required|integer',
            'mes'         => 'required|integer|min:1|max:12',
            'conceptos_digitados' => 'array',
            'conceptos_digitados.*.id_concepto_proyecto' => 'required|integer',
            'conceptos_digitados.*.valor'               => 'numeric',
        ], [
            'id_proyecto.integer' => 'El campo id_proyecto debe ser un número entero.',
            'anio.integer'        => 'El campo año debe ser un número entero.',
            'mes.integer'         => 'El campo mes debe ser un número entero.',
            'conceptos_digitados.*.id_concepto_proyecto.integer' => 'Cada id_concepto_proyecto debe ser un número entero.',
        ]);

        if ($validator->fails()) {
            return response(
                get_response_body(format_messages_validator($validator)),
                Response::HTTP_BAD_REQUEST
            );
        }

        $service = app(ConceptosService::class);
        $resultado = $service->proceso_principal($datos);


        return response($resultado, Response::HTTP_OK);
    }   

    public function guardar(Request $request)
    {
        // Validar
        $validator = Validator::make($request->all(), [
            'id_proyecto' => 'required|integer|exists:proyectos,id',
            'anio'        => 'required|integer',
            'mes'         => 'required|integer|min:1|max:12',
            'conceptos'   => 'required|array|min:1',
            'conceptos.*.id'    => 'required|integer',
            'conceptos.*.valor' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response(
                get_response_body(format_messages_validator($validator)),
                Response::HTTP_BAD_REQUEST
            );
        }

        $data = $request->all();

        DB::beginTransaction();
        try {

            // Guardar en la tabla valores_conceptos_por_proyecto
            ConceptoPorProyecto::guardarValoresPeriodo($data);

            DB::commit();

             return response(
                get_response_body(["Los concepto(s) valores han sido guardados.", 1], ),
                Response::HTTP_OK
            );

        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'status'  => 'error',
                'message' => 'Error guardando valores: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function conceptosSimulacion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_tipo_proyecto' => 'required|integer|exists:tipos_proyectos,id',
            'id_simulacion'    => 'nullable|integer|exists:proyectos_simulaciones,id',
        ]);

        if ($validator->fails()) {
            return response(
                get_response_body(format_messages_validator($validator)),
                Response::HTTP_BAD_REQUEST
            );
        }

        $idTipoProyecto = (int) $request->id_tipo_proyecto;
        $idSimulacion   = $request->id_simulacion ? (int) $request->id_simulacion : null;

        // 1) Conceptos base a pintar
        $conceptosBase = DB::table('conceptos_proyectos as cp')
            ->select(
                'cp.id',
                'cp.secuencia',
                'cp.nombre',
                'cp.indicativo_tipo_linea',
                'cp.indicativo_tipo_concepto',
                'cp.indicativo_permite_copia',
                'cp.indicativo_tipo_valor',
                'cp.porcentaje',
                'cp.parametro_referencia',
                'cp.indicativo_valor_base',
                'cp.indicativo_operador',
                'cp.porcentaje_proy',
                'cp.parametro_referencia_proy',
                'cp.indicativo_valor_base_proy',
                'cp.indicativo_operador_proy',
                'cp.numero_anio_inicial_proy',
                'cp.numero_anios_proy',
                'cp.indicativo_concepto_calculado',
                'cp.indicativo_concepto_editable',
                'cp.valor_mensual_concepto'
            )
            ->where('cp.id_tipo_proyecto', $idTipoProyecto)
            ->where('cp.estado', 1)
            // ->where('cp.indicativo_tipo_valor', 'V')
            ->orderBy('cp.secuencia', 'asc')
            ->get();

        // 2) Si existe simulación, traer datos guardados para sobreescribir
        $mapSim = collect();
        if ($idSimulacion) {
            $mapSim = DB::table('proyectos_simulaciones_conceptos as psc')
                ->select(
                    'psc.id_concepto_proyecto',
                    'psc.porcentaje',
                    'psc.valor_mensual_concepto as valor',
                    'psc.valor_concepto_proyecto as valor_simulado'
                )
                ->where('psc.id_simulacion', $idSimulacion)
                ->get()
                ->keyBy('id_concepto_proyecto');
        }

        // 3) Arreglo final para UI
        $conceptos = [];

        foreach ($conceptosBase as $cp) {
            $esEditable = ($cp->indicativo_concepto_editable ?? 'N') === 'S';
            $esDetalle  = ($cp->indicativo_tipo_linea ?? '') === 'D';

            // Defaults (simulación nueva)
            $porcentaje = $cp->porcentaje ?? null;

            $valorMensual = is_null($cp->valor_mensual_concepto) ? null : (float) $cp->valor_mensual_concepto;
            $valor        = is_null($valorMensual) ? null : round($valorMensual , 2);

            $valorSimulado = null;

            // Si existe simulación, sobreescribir porcentaje/valor/valor_simulado desde psc
            if ($idSimulacion && $mapSim->has($cp->id)) {
                $rowSim = $mapSim->get($cp->id);

                $porcentaje    = $rowSim->porcentaje ?? $porcentaje;
                $valor         = $rowSim->valor ?? $valor;
                $valorSimulado = $rowSim->valor_simulado ?? null;
            }

            // valor_simulado solo entero
            if (!is_null($valorSimulado)) {
                $valorSimulado = (float) $valorSimulado;
            }

            $conceptos[] = [
                'id_concepto_proyecto'  => (int) $cp->id,
                'secuencia'             => (int) ($cp->secuencia ?? 0),
                'concepto'              => (string) ($cp->nombre ?? ''),
                'indicativo_tipo_linea' => (string) ($cp->indicativo_tipo_linea ?? ''),
                'indicativo_tipo_valor' => (string) ($cp->indicativo_tipo_valor ?? ''),
                'indicativo_tipo_concepto' => (string) ($cp->indicativo_tipo_concepto ?? ''),
                'indicativo_permite_copia' => (string) ($cp->indicativo_permite_copia ?? ''),
                'porcentaje'            => $porcentaje,
                'parametro_referencia' => (string) ($cp->parametro_referencia ?? ''),
                'indicativo_valor_base' => (string) ($cp->indicativo_valor_base ?? ''),
                'indicativo_operador' => (string) ($cp->indicativo_operador ?? ''),
                'porcentaje_proy'            => $cp->porcentaje_proy,
                'parametro_referencia_proy' => (string) ($cp->parametro_referencia_proy ?? ''),
                'indicativo_valor_base_proy' => (string) ($cp->indicativo_valor_base_proy ?? ''),
                'indicativo_operador_proy' => (string) ($cp->indicativo_operador_proy ?? ''),
                'numero_anio_inicial_proy' => $cp->numero_anio_inicial_proy,
                'numero_anios_proy' => $cp->numero_anios_proy,
                'indicativo_concepto_calculado' => (string) ($cp->indicativo_concepto_calculado ?? ''),
                'indentado'             => $esDetalle,
                'es_editable'           => $esEditable,
                'valor'                 => $valor,
                'valor_simulado'        => $valorSimulado,
            ];
        }

        return response([
            'conceptos' => $conceptos,
        ], Response::HTTP_OK);
    }

    public function calcularSimulacion(Request $request)
    {
        $datos = $request->all();

        $validator = Validator::make($datos, [
            'id_proyecto' => 'required|integer|exists:proyectos,id',
            'indicativo_modelo_ccial'          => 'required|string',
            'porcentaje_perdida_efic'      => 'nullable',
            'porcentaje_IPC'               => 'nullable',
            'porcentaje_incremento_precio' => 'nullable',
            'anios_depreciacion'           => 'nullable',
            'porcentaje_tasa_oportunidad'  => 'nullable',
            'precio_venta_energia'         => 'nullable',
            'conceptos'                        => 'required|array|min:1',
            'conceptos.*.id_concepto_proyecto' => 'required|integer',
            'conceptos.*.indicativo_tipo_concepto' => 'required|string',
            'conceptos.*.indicativo_tipo_linea'    => 'required|string',
            'conceptos.*.porcentaje'           => 'nullable',
            'conceptos.*.valor'                => 'nullable',
            'conceptos.*.valor_simulado'       => 'nullable',
            'conceptos.*.es_editable'          => 'nullable',
            'conceptos.*.indicativo_concepto_calculado' => 'nullable',
            'conceptos.*.indicativo_valor_base' => 'nullable',
            'conceptos.*.indicativo_operador'   => 'nullable',
            'conceptos.*.parametro_referencia'  => 'nullable',
        ], [
            'id_proyecto.required' => 'El proyecto es requerido.',
            'conceptos.required'   => 'Debe enviar los conceptos de simulación.',
        ]);

        if ($validator->fails()) {
            return response(
                get_response_body(format_messages_validator($validator)),
                Response::HTTP_BAD_REQUEST
            );
        }

        $service = app(ConceptosSimulacionService::class);
        $resultado = $service->ejecutar($datos);

        return response($resultado, Response::HTTP_OK);
    }

 
}
