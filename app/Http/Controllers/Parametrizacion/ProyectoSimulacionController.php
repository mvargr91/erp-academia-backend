<?php

namespace App\Http\Controllers\Parametrizacion;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Parametrizacion\ProyectoSimulacion;
use App\Services\ConceptosSimulacionService;
use App\Services\ChartPngService;
use App\Exports\Proyectos\SimulacionProyectoExportCompleta;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;

class ProyectoSimulacionController extends Controller
{
   /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request, $id)
    {
        try{
            $datos = $request->all();
            $datos['id'] = $id;
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
                $ProyectoSimulacion = ProyectoSimulacion::obtenerColeccionLigera($datos);
            }else{
                if(isset($datos['ordenar_por'])){
                    $datos['ordenar_por'] = format_order_by_attributes($datos);
                }
                $ProyectoSimulacion = ProyectoSimulacion::obtenerColeccion($datos);
            }
            return response($ProyectoSimulacion, Response::HTTP_OK);
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
                'indicativo_modelo_ccial' => 'numeric|required',
                'porcentaje_perdida_efic' => 'numeric|required',
                'valor_total_proyecto' => 'numeric|required',
                'porcentaje_IPC' => 'numeric|required',
                'Valor_VPN_proyecto' => 'numeric|required',
                'porcentaje_TIR_proyecto' => 'numeric|required',
                'anios_PBT' => 'numeric|required',
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            $ProyectoSimulacion = ProyectoSimulacion::modificarOCrear($datos);
            
            if ($ProyectoSimulacion) {
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["La simulación por proyecto ha sido creada.", 2], $ProyectoSimulacion),
                    Response::HTTP_CREATED
                );
            } else {
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar crear la simulación por proyecto ."]), Response::HTTP_CONFLICT);
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
            $datos['id'] = (int)$id;
            $validator = Validator::make($datos, [
                'id' => 'integer|required|exists:proyectos_simulaciones,id'
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }

            return response(ProyectoSimulacion::cargar($id), Response::HTTP_OK);
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
                'id' => 'integer|required|exists:proyectos_simulaciones,id',
                'id_proyecto' => 'integer|required|exists:proyectos,id',
                'indicativo_modelo_ccial' => 'numeric|required',
                'porcentaje_perdida_efic' => 'numeric|required',
                'valor_total_proyecto' => 'numeric|required',
                'porcentaje_IPC' => 'numeric|required',
                'Valor_VPN_proyecto' => 'numeric|required',
                'porcentaje_TIR_proyecto' => 'numeric|required',
                'anios_PBT' => 'numeric|required',
            ]);

            if($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator))
                    , Response::HTTP_BAD_REQUEST
                );
            }            

            $ProyectoSimulacion = ProyectoSimulacion::modificarOCrear($datos);
            if($ProyectoSimulacion){
                DB::commit(); // Se cierra la transacción correctamente
                return response(
                    get_response_body(["La simulación por proyecto ha sido modificada.", 1], $ProyectoSimulacion),
                    Response::HTTP_OK
                );
            } else {
                DB::rollback(); // Se devuelven los cambios, por que la transacción falla
                return response(get_response_body(["Ocurrió un error al intentar modificar la simulación por proyecto ."]), Response::HTTP_CONFLICT);;
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
        DB::beginTransaction();

        try {
            $datos['id'] = $id;

            $validator = Validator::make($datos, [
                'id' => 'integer|required|exists:proyectos_simulaciones,id'
            ]);

            if ($validator->fails()) {
                return response(
                    get_response_body(format_messages_validator($validator)),
                    Response::HTTP_BAD_REQUEST
                );
            }
            // 3) Eliminar la simulación solicitada
            $eliminado = ProyectoSimulacion::eliminar($id);

            if ($eliminado) {
                DB::commit();

                return response(
                    get_response_body(["La simulación ha sido eliminada.", 3]),
                    Response::HTTP_OK
                );
            } else {
                DB::rollBack();

                return response(
                    get_response_body(["Ocurrió un error al intentar eliminar la simulación."]),
                    Response::HTTP_CONFLICT
                );
            }
        } catch (Exception $e) {
                DB::rollBack();
            return response(null, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }


    public function guardar(Request $request)
    {
        $payload = $request->all();

        foreach ([
            'id',
            'accion',
            'porcentaje_perdida_efic',
            'indicativo_tipo_simulacion',
            'indicativo_beneficio_trib',
            'valor_total_proyecto',
            'porcentaje_IPC',
            'porcentaje_incremento_precio',
            'anios_depreciacion',
            'porcentaje_tasa_oportunidad',
        ] as $k) {
            if (array_key_exists($k, $payload) && $payload[$k] === '') {
                $payload[$k] = null;
            }
        }

        if (!empty($payload['conceptos']) && is_array($payload['conceptos'])) {
            foreach ($payload['conceptos'] as $i => $c) {
                foreach (['valor', 'porcentaje', 'valor_simulado'] as $k) {
                    if (array_key_exists($k, $payload['conceptos'][$i]) && $payload['conceptos'][$i][$k] === '') {
                        $payload['conceptos'][$i][$k] = null;
                    }
                }
            }
        }

        $validator = Validator::make($payload, [
            'id' => 'nullable|integer|exists:proyectos_simulaciones,id',
            'id_proyecto' => 'required|integer|exists:proyectos,id',
            'indicativo_modelo_ccial' => 'required|string',
            'porcentaje_perdida_efic' => 'nullable|numeric',
            'valor_total_proyecto' => 'nullable|numeric',
            'porcentaje_IPC' => 'nullable|numeric',
            'anios_depreciacion' => 'nullable|numeric',
            'porcentaje_tasa_oportunidad' => 'nullable|numeric',
            'conceptos' => 'required|array|min:1',
            'conceptos.*.id_concepto_proyecto' => 'required|integer',
            'conceptos.*.secuencia' => 'required|integer',
            'conceptos.*.valor' => 'nullable',
            'conceptos.*.porcentaje' => 'nullable',
            'conceptos.*.valor_simulado' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response(
                get_response_body(format_messages_validator($validator)),
                Response::HTTP_BAD_REQUEST
            );
        }

        $service = app(ConceptosSimulacionService::class);
        $resp = $service->boton_guardar($payload);

        return response(
            get_response_body(["La simulación por proyecto ha sido creada.", 2], $resp),
            Response::HTTP_OK
        );
    }

    public function initUpdateSimulacion(Request $request)
    {
        $datos = $request->all();
   
        $validator = Validator::make($request->all(), [
            'id_simulacion' => 'required|integer|exists:proyectos_simulaciones,id',
        ], [
            'id_simulacion.required' => 'La simulación es requerida.',
            'id_simulacion.integer'  => 'La simulación debe ser un número entero.',
            'id_simulacion.exists'   => 'La simulación no existe.',
        ]);

        if ($validator->fails()) {
            return response(
                get_response_body(format_messages_validator($validator)),
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $idSimulacion = (int) $request->id_simulacion;

            $resp = ConceptosSimulacionService::Proceso_Arreglo_Conceptos_Modificacion($idSimulacion);

            return response($resp, Response::HTTP_OK);

        } catch (\Throwable $e) {
            return response(
                get_response_body(['Error inicializando edición: ' . $e->getMessage()]),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function download(int $id)
    {
        $sim = ProyectoSimulacion::cargar($id);

        $detalle = $sim['detalle_conceptos'] ?? [];
        $years   = array_values($detalle['years'] ?? []);
        $rows    = $detalle['rows'] ?? [];

        if (count($years) === 0) $years = [(int)date('Y')];

        $anioBase = (int)$years[0];
        $anioFin  = (int)$years[count($years) - 1];

        // ============================================
        // Columnas: cada 5 años + último + TOTAL
        // ============================================
        $columnas = [];
        foreach ($years as $y) {
            $y = (int)$y;
            if ((($y - $anioBase) % 5) === 0) {
                $columnas[] = ['label' => (string)$y, 'anio' => $y];
            }
        }

        $ultimoIncluido = false;
        foreach ($columnas as $c) {
            if ((int)$c['anio'] === $anioFin) { $ultimoIncluido = true; break; }
        }
        if (!$ultimoIncluido) $columnas[] = ['label' => (string)$anioFin, 'anio' => $anioFin];

        $columnas[] = ['label' => 'TOTAL', 'anio' => null];

        

        // ============================================
        // Gráfica: Flujo caja libre acumulado (ID=55)
        // ============================================
        $serieLabels = array_map(fn($y) => (string)$y, $years);
        $flujoAcumValues = null;
        $idCajaLibreAcum = DB::table('parametros_constantes')
                ->where('codigo_parametro', 'ID_CONCEPTO_CAJA_LIBRE_ACUM')
                ->value('valor_parametro');


        foreach ($rows as $r) {
            $idConcepto = (int)($r['id_concepto_proyecto'] ?? 0);
            $nombre = strtoupper(trim((string)($r['concepto'] ?? '')));

            if ($idConcepto == $idCajaLibreAcum) {
                $valores = [];
                foreach ($years as $y) {
                    $valores[] = (float)($r['valores_por_anio'][(int)$y] ?? 0);
                }
                $flujoAcumValues = $valores;
                break;
            }       
        }

        $chartBase64 = null;
        if (extension_loaded('gd') && is_array($flujoAcumValues)) {
            $chartSvc = app(ChartPngService::class);
            $chartBase64 = $chartSvc->areaChartBase64($serieLabels, $flujoAcumValues, 850, 220);
        }

        // =============================
        // Totales por columna: SOLO V/C
        // TOTAL debe sumar TODOS los años ($years), no solo los mostrados en $columnas
        // =============================

        $totalesColumnas = array_fill(0, count($columnas), 0.0);
        $filas = [];

        foreach ($rows as $r) {
            $tipoLinea = strtoupper((string)($r['indicativo_tipo_linea'] ?? ''));
            $tipoValor = strtoupper((string)($r['indicativo_tipo_valor'] ?? ''));
            $seTotaliza = in_array($tipoValor, ['V','C'], true);
            $indent = in_array($tipoLinea, ['D'], true) ? 1 : 0;

            // Total real de la fila sumando TODOS los años disponibles
            $totalFilaTodosLosAnios = 0.0;
            if ($seTotaliza) {
                foreach ($years as $y) {
                    $totalFilaTodosLosAnios += (float)($r['valores_por_anio'][(int)$y] ?? 0);
                }
            }

            $valores = [];

            for ($i = 0; $i < count($columnas); $i++) {
                $anioCol = $columnas[$i]['anio'];

                if ($anioCol === null) {
                    // TOTAL = suma de TODOS los años 
                    if ($seTotaliza) {
                        $valores[] = (int)$totalFilaTodosLosAnios;
                        $totalesColumnas[$i] += (int)$totalFilaTodosLosAnios;
                    } else {
                        $valores[] = null;
                    }
                    continue;
                }

                // años mostrados (cada 5 + último)
                $v = (int)($r['valores_por_anio'][(int)$anioCol] ?? 0);
                $valores[] = (int)$v;

                if ($seTotaliza) {
                    $totalesColumnas[$i] += $v; // totales por columna (solo de las columnas mostradas)
                }
            }

            $filas[] = [
                'label' => (string)($r['concepto'] ?? ''),
                'indent' => $indent,
                'tipo_valor' => $tipoValor,
                'values' => $valores,
            ];
        }


        $data = [
            'titulo' => 'Simulacion- ' . ($sim['indicativo_modelo_ccial'] == 'BO' ? 'Bolsa': 'Comunidad Energetica'),
            'proyecto' => $sim['id_proyecto'] ?? '',
            'codigo_proyecto' => $sim['codigo_proyecto'] ?? '',
            'nombre_proyecto' => $sim['nombre_proyecto'] ?? '',
            'indicativo_tipo_simulacion' => $sim['indicativo_tipo_simulacion'] ?? '',
            'indicativo_beneficio_trib' => $sim['indicativo_beneficio_trib'] ?? '',
            'valor_total_proyecto' => (float)($sim['valor_total_proyecto'] ?? 0),
            'potencia_kwp' => (float)($sim['potencia'] ?? 0),
            'perdida_eficiencia' => (float)($sim['porcentaje_perdida_efic'] ?? 0),
            'tasa_oportunidad' => (float)($sim['porcentaje_tasa_oportunidad'] ?? 0),
            'ipc' => (float)($sim['porcentaje_IPC'] ?? 0),
            'anios_depreciacion' => (int)($sim['anios_depreciacion'] ?? 0),
            'fecha' => (string)($sim['fecha'] ?? ''),
            'vpn' => (float)($sim['Valor_VPN_proyecto'] ?? 0),
            'tir' => (float)($sim['porcentaje_TIR_proyecto'] ?? 0),
            'pbt' => (float)($sim['anios_PBT'] ?? 0),
            'nombre_inversionista' => ($sim['nombre_inversionista'] ?? 0),
            'telefono_inversionista' => ($sim['telefono_inversionista'] ?? 0),
            'email_inversionista' => ($sim['email_inversionista'] ?? 0),
            'porcentaje_part_inversionista' => (float)($sim['porcentaje_part_inversionista'] ?? 0),
            'valor_part_inversionista' => (float)($sim['valor_part_inversionista'] ?? 0),
            'columnas' => $columnas,
            'filas' => $filas,
            // 'totales_columnas' => $totalesColumnas,
            'chart_base64' => $chartBase64,
        ];

        $beneficio_trib = $sim['indicativo_beneficio_trib'] == 'S' ? 'Con_Beneficio_Trib' : 'Sin_Beneficio_Trib';

        if( $sim['indicativo_tipo_simulacion'] == 'I'){
            return Pdf::loadView('pdf.simulacion_inversionista', $data)
                ->setPaper('a4', 'portrait')
                ->setOption('dpi', 96)
                ->stream("Simulacion-{$beneficio_trib}-{$id}.pdf");
        }else{
            return Pdf::loadView('pdf.simulacion', $data)
                ->setPaper('a4', 'portrait')
                ->setOption('dpi', 96)
                ->stream("Simulacion-{$beneficio_trib}-{$id}.pdf");
        }

    }


    public function exportarExcel($id)
    {
        $simulacion = ProyectoSimulacion::findOrFail((int)$id);

        $beneficio_trib = $simulacion->indicativo_beneficio_trib == 'S' ? 'Con_Beneficio_Trib' : 'Sin_Beneficio_Trib';

        $nombreArchivo = 'Simulacion_Proyecto_' .
            $simulacion->id . 
            '_' .
            $beneficio_trib .
            '_' .
            now() .
            '.xlsx';

        return Excel::download(
            new SimulacionProyectoExportCompleta((int)$simulacion->id),
            $nombreArchivo
        );
    }

    public function initSimulacionInversionista(Request $request)
    {
        $payload = $request->all();

        $validator = Validator::make($payload, [
            'id_simulacion' => 'required|integer|exists:proyectos_simulaciones,id',
            'porcentaje_part_inversionista' => 'nullable|numeric|min:0|max:100',
        ], [
            'id_simulacion.required' => 'La simulación es requerida.',
            'id_simulacion.integer' => 'La simulación debe ser numérica.',
            'id_simulacion.exists' => 'La simulación no existe.',
        ]);

        if ($validator->fails()) {
            return response(
                get_response_body(format_messages_validator($validator)),
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $service = app(\App\Services\SimulacionInversionistaService::class);
            $resp = $service->initSimulacionInversionista($payload);

            return response($resp, Response::HTTP_OK);
        } catch (\Throwable $e) {
            return response(
                get_response_body([$e->getMessage()]),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    public function guardarSimulacionInversionista(Request $request)
    {
        $payload = $request->all();

        $validator = Validator::make($payload, [
            'id_simulacion_origen' => 'required|integer|exists:proyectos_simulaciones,id',
            'porcentaje_part_inversionista' => 'required|numeric|min:0|max:100',
            'nombre_inversionista' => 'nullable|string|max:255',
            'telefono_inversionista' => 'nullable|string|max:50',
            'email_inversionista' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response(
                get_response_body(format_messages_validator($validator)),
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            $service = app(\App\Services\SimulacionInversionistaService::class);
            $resp = $service->boton_guardar($payload);            

            return response(
                get_response_body(["La simulación del inversionista ha sido creada.", 2], $resp),
                Response::HTTP_CREATED
            );
        } catch (\Throwable $e) {
            return response(
                get_response_body([$e->getMessage()]),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

 
}
