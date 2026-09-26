<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ConceptosService
{
    /** Parámetros leídos de parametros_constantes */
    protected array $parametros = [];

    /** Lista plana de conceptos (cada elemento es un array asociativo) */
    protected array $conceptos = [];

    /** Totales */
    protected float $valorTotalIngresos = 0.0;
    protected float $valorTotalEgresos  = 0.0;

    /** Valor total del proyecto (proyectos.valor_total_proyecto) */
    protected float $valorTotalProyecto = 0.0;

    /** Datos auxiliares del proyecto */
    protected int $proyectoId = 0;
    protected float $porcentajeAdmon = 0.0;       // ya no se usan para cálculo directo, pero los dejamos por si acaso
    protected float $porcentajeComercial = 0.0;
    protected float $porcentajeMantenimiento = 0.0;
    protected int $aniosDepreciacion = 0;

    /** DTO original (para leer precios, energia, año/mes, etc.) */
    protected array $dtoOriginal = [];

    /**
     * Punto de entrada: ejecuta los procesos en el orden definido.
     */
    public function proceso_principal(array $dto): array
    {
        $this->dtoOriginal = $dto;

        // Lee parámetros de la tabla parametros_constantes
        $this->Proceso_Lecturas_parametros();

        // ID proyecto, año y mes de proceso (para depreciación, etc.)
        $this->proyectoId = (int)($dto['id_proyecto'] ?? 0);
        $anioProceso      = (int)($dto['anio'] ?? 0);
        $mesProceso       = (int)($dto['mes'] ?? 0);

        // VALOR TOTAL PROYECTO + porcentajes
        $proyectoRow = DB::table('proyectos')
            ->where('id', $this->proyectoId)
            ->select(
                'valor_total_proyecto',
                'anios_depreciacion'
            )
            ->first();

        if ($proyectoRow) {
            $this->valorTotalProyecto      = (float)($proyectoRow->valor_total_proyecto ?? 0);
            $this->aniosDepreciacion       = (int)($proyectoRow->anios_depreciacion ?? 0);
        }

        // =========================================================
        //  PROCESO INICIAL - NORMALIZAR CONCEPTOS
        // =========================================================
        $conceptosRaw = $dto['conceptos'] ?? [];

        // Por si viene anidado tipo ['conceptos' => [...]]
        if (isset($conceptosRaw['conceptos']) && is_array($conceptosRaw['conceptos'])) {
            $conceptosRaw = $conceptosRaw['conceptos'];
        }

        // Normalizar conceptos: asegurar array y valor numérico en 'valor'
        $this->conceptos = collect($conceptosRaw)
            ->map(function ($c) {
                $c = (array) $c;

                // valor o valor_concepto_proyecto
                $rawValor = $c['valor'] ?? $c['valor_concepto_proyecto'] ?? 0;
                $c['valor'] = (float) str_replace(',', '', (string) $rawValor);

                // NUEVO: normalizar valor_mensual_concepto
                $rawMensual = $c['valor_mensual_concepto'] ?? 0;
                $c['valor_mensual_concepto'] = (float) str_replace(',', '', (string) $rawMensual);

                // NUEVO: normalizar porcentaje
                $rawPorcentaje = $c['porcentaje'] ?? 0;
                $c['porcentaje'] = (float) str_replace(',', '', (string) $rawPorcentaje);

                return $c;
            })
            ->values()
            ->all();

        // =========================================================
        //  Asegurar existencia de conceptos precio/kwh (comunidad/bolsa)
        //  para que se puedan actualizar y guardar en BD
        // =========================================================
        $idsNecesarios = [
            'precio_comunidad' => $this->parametros['id_concepto_precio_comunidad'] ?? 0,
            'kwh_comunidad'    => $this->parametros['id_concepto_kwh_comunidad']    ?? 0,
            'precio_bolsa'     => $this->parametros['id_concepto_precio_bolsa']     ?? 0,
            'kwh_bolsa'        => $this->parametros['id_concepto_kwh_bolsa']        ?? 0,
        ];

        foreach ($idsNecesarios as $key => $idConcepto) {

            if (!$idConcepto) {
                continue;
            }

            // ¿Ya existe ese concepto en el array normalizado?
            $yaExiste = collect($this->conceptos)->firstWhere('id_concepto', $idConcepto);

            if (!$yaExiste) {
                $this->conceptos[] = [
                    'id_concepto'              => $idConcepto,
                    'valor'                    => 0,
                    // P = precio, C = kWh
                    'indicativo_tipo_valor'    => ($key === 'kwh_comunidad' || $key === 'kwh_bolsa') ? 'C' : 'P',
                    // Para no romper nada, los marcamos como ING/D y calculados
                    'indicativo_tipo_concepto' => 'ING',
                    'indicativo_tipo_linea'    => 'D',
                    'indicativo_concepto_calculado' => 'S',
                    'es_editable'              => 1,
                ];
            }
        }

        // Sincronizar esos conceptos con los valores que vienen del DTO
        $this->Proceso_Actualizar_Conceptos();

        // 1) Calculados (ING/EGR, totales, fórmulas especiales, parametrizados)
        $this->Proceso_Conceptos_Calculados();

        // 2) Digitados / derivados (rendimientos, depreciación, renta, desc., impuesto, caja)
        $this->Proceso_Conceptos_Digitados();

        // Resultados 
        $dto['conceptos'] = collect($this->conceptos)
            ->map(function ($c) {
                $c['valor'] = isset($c['valor']) ? (float) $c['valor'] : 0.0;
                $c['valor_concepto_proyecto'] = number_format($c['valor'], 2, '.', '');
                return $c;
            })
            ->values()
            ->all();

        return $dto;
    }

    // =========================================================
    //  Proceso_Lecturas_parametros
    // =========================================================

    protected function Proceso_Lecturas_parametros(): void
    {
        $codigos = [
            'ID_CONCEPTO_VENTA_ENERGIA',
            'ID_CONCEPTO_VENTA_ENERGIA_COM',
            'ID_CONCEPTO_VENTA_ENERGIA_BOLSA',
            'ID_CONCEPTO_TOTAL_INGRESOS',
            'ID_CONCEPTO_TOTAL_EGRESOS',
            'ID_CONCEPTO_RENDIMIENTOS',
            'ID_CONCEPTO_DEPRECIACION',
            'ID_CONCEPTO_RENTA_LIQUIDA',
            'ID_CONCEPTO_DESCUENTO_RENTA',
            'ID_CONCEPTO_IMPUESTO_RENTA',
            'ID_CONCEPTO_CAJA_LIBRE',
            'ID_CONCEPTO_ADMINISTRACION',
            'ID_CONCEPTO_COMERCIAL',
            'ID_CONCEPTO_MANTENIMIENTO',
            'ID_CONCEPTO_PRECIO_COMUNIDAD',
            'ID_CONCEPTO_KWH_COMUNIDAD',
            'ID_CONCEPTO_PRECIO_BOLSA',
            'ID_CONCEPTO_KWH_BOLSA',
        ];

        $rows = DB::table('parametros_constantes')
            ->whereIn('codigo_parametro', $codigos)
            ->pluck('valor_parametro', 'codigo_parametro');

        // Guardamos en $this->parametros 
        $this->parametros = [
            'id_concepto_venta_e'          => (int) ($rows['ID_CONCEPTO_VENTA_ENERGIA']      ?? 0),
            'id_concepto_ventas_en_com'    => (int) ($rows['ID_CONCEPTO_VENTA_ENERGIA_COM']  ?? 0),
            'id_concepto_ventas_en_bolsa'  => (int) ($rows['ID_CONCEPTO_VENTA_ENERGIA_BOLSA']?? 0),
            'id_concepto_total_ingresos'   => (int) ($rows['ID_CONCEPTO_TOTAL_INGRESOS']     ?? 0),
            'id_concepto_total_egresos'    => (int) ($rows['ID_CONCEPTO_TOTAL_EGRESOS']      ?? 0),
            'id_concepto_rendimientos'     => (int) ($rows['ID_CONCEPTO_RENDIMIENTOS']       ?? 0),
            'id_concepto_depreciacion'     => (int) ($rows['ID_CONCEPTO_DEPRECIACION']       ?? 0),
            'id_concepto_renta_liq'        => (int) ($rows['ID_CONCEPTO_RENTA_LIQUIDA']      ?? 0),
            'id_concepto_descto_renta'     => (int) ($rows['ID_CONCEPTO_DESCUENTO_RENTA']    ?? 0),
            'id_concepto_impto_renta'      => (int) ($rows['ID_CONCEPTO_IMPUESTO_RENTA']     ?? 0),
            'id_concepto_caja_libre'       => (int) ($rows['ID_CONCEPTO_CAJA_LIBRE']         ?? 0),
            'id_concepto_admon'            => (int) ($rows['ID_CONCEPTO_ADMINISTRACION']     ?? 0),
            'id_concepto_comercial'        => (int) ($rows['ID_CONCEPTO_COMERCIAL']          ?? 0),
            'id_concepto_mtto'             => (int) ($rows['ID_CONCEPTO_MANTENIMIENTO']      ?? 0),
            'id_concepto_precio_comunidad' => (int) ($rows['ID_CONCEPTO_PRECIO_COMUNIDAD']   ?? 0),
            'id_concepto_kwh_comunidad'    => (int) ($rows['ID_CONCEPTO_KWH_COMUNIDAD']      ?? 0),
            'id_concepto_precio_bolsa'     => (int) ($rows['ID_CONCEPTO_PRECIO_BOLSA']       ?? 0),
            'id_concepto_kwh_bolsa'        => (int) ($rows['ID_CONCEPTO_KWH_BOLSA']          ?? 0),
        ];
    }

    // =========================================================
    //  Proceso_Conceptos_Calculados
    // =========================================================

    protected function Proceso_Conceptos_Calculados(): void
    {
        // Índice id_concepto -> concepto (por referencia)
        $Concepto = $this->ConceptoId();

        $idVentasCom      = $this->parametros['id_concepto_ventas_en_com']   ?? 0;
        $idVentasBolsa    = $this->parametros['id_concepto_ventas_en_bolsa'] ?? 0;
        $idTotalIngresos  = $this->parametros['id_concepto_total_ingresos']  ?? 0;
        $idTotalEgresos   = $this->parametros['id_concepto_total_egresos']   ?? 0;
        $idRentaLiq       = $this->parametros['id_concepto_renta_liq']       ?? 0;
        $idAdmon          = $this->parametros['id_concepto_admon']           ?? 0;
        $idComercial      = $this->parametros['id_concepto_comercial']       ?? 0;
        $idMtto           = $this->parametros['id_concepto_mtto']            ?? 0;

        // Marcar tipo_valor_concepto por defecto
        foreach ($this->conceptos as &$c) {
            if (empty($c['indicativo_tipo_valor'])) {
                $c['indicativo_tipo_valor'] = 'V';
            }
        }
        unset($c);

        // =========================================================
        //  1) DETALLE DE INGRESOS (ING, línea D)
        // =========================================================
        $totalIngresos = 0.0;

        foreach ($this->conceptos as &$c) {
            if (($c['indicativo_tipo_concepto'] ?? null) !== 'ING') {
                continue;
            }
            if (($c['indicativo_tipo_linea'] ?? null) !== 'D') {
                continue;
            }

            $esCalculado = ($c['indicativo_concepto_calculado'] ?? 'N') === 'S';
            $valorActual = (float) ($c['valor'] ?? 0.0);
            $idConcepto  = (int) ($c['id_concepto'] ?? 0);

            // Ventas COMUNIDAD
            if ($idConcepto === $idVentasCom && $idVentasCom > 0) {
                $precio  = (float) ($this->dtoOriginal['precio_energia_comunidad'] ?? 0);
                $energia = (float) ($this->dtoOriginal['energia_comercializada_comunidad'] ?? 0);
                $c['valor'] = $precio * $energia;
                $valorActual = (float) $c['valor'];
            }

            // Ventas BOLSA
            if ($idConcepto === $idVentasBolsa && $idVentasBolsa > 0) {
                $precio  = (float) ($this->dtoOriginal['precio_energia_bolsa'] ?? 0);
                $energia = (float) ($this->dtoOriginal['energia_comercializada_bolsa'] ?? 0);
                $c['valor'] = $precio * $energia;
                $valorActual = (float) $c['valor'];
            }

            if ($esCalculado /*& $valorActual == 0.0*/) {

                // prioridad valor_mensual_concepto / porcentaje
                $calculado = $this->calcularPorValorMensualOPorcentaje(
                    $c,
                    $totalIngresos,
                    $Concepto,
                    $idRentaLiq
                );

                // Si no se pudo calcular con los nuevos campos, se usa (parametro_referencia)
                if (!$calculado) {
                    $paramRef = $c['parametro_referencia']  ?? null;
                    $tipoBase = $c['indicativo_valor_base'] ?? null;
                    $operador = $c['indicativo_operador']   ?? null;

                    if (!empty($paramRef)) {
                        $valorParametroConcepto = DB::table('parametros_constantes')
                            ->where('codigo_parametro', $paramRef)
                            ->value('valor_parametro');

                        $valorParametroConcepto = (float) $valorParametroConcepto;

                        [$valorBase, $valorParam] = $this->calcularBaseConceptoGenericoIngresos(
                            $tipoBase,
                            $valorParametroConcepto,
                            $totalIngresos,
                            $Concepto,
                            $idRentaLiq
                        );

                        $c['valor'] = $this->aplicarOperacion(
                            $valorBase,
                            $valorParam,
                            $operador
                        );
                    }
                }
            }

            $totalIngresos += (float) ($c['valor'] ?? 0.0);
        }
        unset($c);

        $this->valorTotalIngresos = $totalIngresos;
        if ($idTotalIngresos && isset($Concepto[$idTotalIngresos])) {
            $Concepto[$idTotalIngresos]['valor'] = $this->valorTotalIngresos;
        }

        // =========================================================
        //  2) DETALLE DE EGRESOS (EGR, línea D)
        // =========================================================
        $totalEgresos = 0.0;

        foreach ($this->conceptos as &$c) {
            if (($c['indicativo_tipo_concepto'] ?? null) !== 'EGR') {
                continue;
            }
            if (($c['indicativo_tipo_linea'] ?? null) !== 'D') {
                continue;
            }

            $esCalculado = ($c['indicativo_concepto_calculado'] ?? 'N') === 'S';
            $valorActual = (float) ($c['valor'] ?? 0.0);

            if ($esCalculado /*& $valorActual == 0.0*/) {

                // prioridad valor_mensual_concepto / porcentaje
                $calculado = $this->calcularPorValorMensualOPorcentaje(
                    $c,
                    $this->valorTotalIngresos,
                    $Concepto,
                    $idRentaLiq
                );

                if (!$calculado) {
                    $paramRef = $c['parametro_referencia']  ?? null;
                    $tipoBase = $c['indicativo_valor_base'] ?? null;
                    $operador = $c['indicativo_operador']   ?? null;

                    if (!empty($paramRef)) {
                        $valorParametroConcepto = DB::table('parametros_constantes')
                            ->where('codigo_parametro', $paramRef)
                            ->value('valor_parametro');

                        $valorParametroConcepto = (float) $valorParametroConcepto;

                        [$valorBase, $valorParam] = $this->calcularBaseYParametroDescuentoRenta(
                            $tipoBase,
                            $valorParametroConcepto,
                            $Concepto,
                            $idRentaLiq
                        );

                        $c['valor'] = $this->aplicarOperacion(
                            $valorBase,
                            $valorParam,
                            $operador
                        );
                    }
                }
            }

            $totalEgresos += (float) ($c['valor'] ?? 0.0);
        }
        unset($c);

        $this->valorTotalEgresos = $totalEgresos;
        if ($idTotalEgresos && isset($Concepto[$idTotalEgresos])) {
            $Concepto[$idTotalEgresos]['valor'] = $this->valorTotalEgresos;
        }
    }

    // =========================================================
    //  Proceso_Conceptos_Digitados
    // =========================================================

    protected function Proceso_Conceptos_Digitados(): void
    {
        $Concepto =  $this->ConceptoId();

        $idTotalIngresos   = $this->parametros['id_concepto_total_ingresos'] ?? 0;
        $idTotalEgresos    = $this->parametros['id_concepto_total_egresos']  ?? 0;
        $idRendimientos    = $this->parametros['id_concepto_rendimientos']   ?? 0;
        $idDepreciacion    = $this->parametros['id_concepto_depreciacion']   ?? 0;
        $idRentaLiq        = $this->parametros['id_concepto_renta_liq']      ?? 0;
        $idDesctoRenta     = $this->parametros['id_concepto_descto_renta']   ?? 0;
        $idImptoRenta      = $this->parametros['id_concepto_impto_renta']    ?? 0;
        $idCajaLibre       = $this->parametros['id_concepto_caja_libre']     ?? 0;

        // Total ingresos
        $this->valorTotalIngresos = $this->sumarPorTipo('ING', 'D');
        if ($idTotalIngresos && isset($Concepto[$idTotalIngresos])) {
            $Concepto[$idTotalIngresos]['valor'] = $this->valorTotalIngresos;
        }

        // Total egresos
        $this->valorTotalEgresos = $this->sumarPorTipo('EGR', 'D');
        if ($idTotalEgresos && isset($Concepto[$idTotalEgresos])) {
            $Concepto[$idTotalEgresos]['valor'] = $this->valorTotalEgresos;
        }

        // Rendimientos
        if ($idRendimientos) {
            $valorRend = $this->valorTotalIngresos - $this->valorTotalEgresos;
            if (isset($Concepto[$idRendimientos])) {
                $Concepto[$idRendimientos]['valor'] = $valorRend;
            }
        }

        // Depreciación
        $valorDepreciacion = 0.0;
        if ($idDepreciacion && isset($Concepto[$idDepreciacion])) {
            $proyectoId = $this->proyectoId;
            $anioProc   = (int)($this->dtoOriginal['anio'] ?? 0);
            $mesProc    = (int)($this->dtoOriginal['mes'] ?? 0);

            $valorDepreciacion = (float)($Concepto[$idDepreciacion]['valor'] ?? 0.0);

            if (
                $proyectoId &&
                $anioProc > 0 &&
                $mesProc > 0 &&
                $this->aniosDepreciacion > 0 &&
                $this->valorTotalProyecto > 0
            ) {
                $inicio = DB::table('valores_conceptos_por_proyecto')
                    ->where('id_proyecto', $proyectoId)
                    ->orderBy('anio')
                    ->orderBy('mes')
                    ->select('anio', 'mes')
                    ->first();

                if ($inicio) {
                    $anioIni = (int)$inicio->anio;
                    $mesIni  = (int)$inicio->mes;

                    $fechaIni   = Carbon::create($anioIni, $mesIni, 1, 0, 0, 0);
                    $fechaProc  = Carbon::create($anioProc, $mesProc, 1, 0, 0, 0);

                    $nroMesesDepreciados = $fechaIni->diffInMonths($fechaProc);
                    $nroMesesADepreciar  = $this->aniosDepreciacion * 12;

                    $valorDepreciadoAcum = DB::table('valores_conceptos_por_proyecto')
                        ->where('id_proyecto', $proyectoId)
                        ->where('id_concepto_proyecto', $idDepreciacion)
                        ->sum('valor_concepto_proyecto');

                    if (
                        $nroMesesDepreciados <= $nroMesesADepreciar &&
                        $valorDepreciadoAcum < $this->valorTotalProyecto
                    ) {
                        $valorDepreciacion = $this->valorTotalProyecto / $nroMesesADepreciar;
                        $Concepto[$idDepreciacion]['valor'] = $valorDepreciacion;
                    }
                }
            }
        }

        // Renta líquida
        if ($idRentaLiq) {
            $valorRend = $idRendimientos && isset($Concepto[$idRendimientos])
                ? (float) $Concepto[$idRendimientos]['valor']
                : 0.0;

            $valorRentaLiq = $valorRend - $valorDepreciacion;

            if (isset($Concepto[$idRentaLiq])) {
                $Concepto[$idRentaLiq]['valor'] = $valorRentaLiq;
            }
        }

        // Descuento renta
        if ($idDesctoRenta && isset($Concepto[$idDesctoRenta])) {
            $c =& $Concepto[$idDesctoRenta];

            $paramRef = $c['parametro_referencia']  ?? null;
            $operador = $c['indicativo_operador']   ?? null;
            $tipoBase = $c['indicativo_valor_base'] ?? null;

            if (!empty($paramRef)) {
                $valorParametroConcepto = DB::table('parametros_constantes')
                    ->where('codigo_parametro', $paramRef)
                    ->value('valor_parametro');

                $valorParametroConcepto = (float) $valorParametroConcepto;

                [$valorBase, $valorParametro] = $this->calcularBaseYParametroDescuentoRenta(
                    $tipoBase,
                    $valorParametroConcepto,
                    $Concepto,
                    $idRentaLiq
                );

                $c['valor'] = $this->aplicarOperacion(
                    $valorBase,
                    $valorParametro,
                    $operador
                );
            }
            unset($c);
        }

        // Impuesto renta
        if ($idImptoRenta && isset($Concepto[$idImptoRenta])) {
            $c =& $Concepto[$idImptoRenta];

            $paramRef = $c['parametro_referencia']  ?? null;
            $operador = $c['indicativo_operador']   ?? null;

            if (!empty($paramRef)) {
                $valorParametro = DB::table('parametros_constantes')
                    ->where('codigo_parametro', $paramRef)
                    ->value('valor_parametro');

                $valorParametro = (float) $valorParametro;

                $valorRentaLiq = $idRentaLiq && isset($Concepto[$idRentaLiq])
                    ? (float) $Concepto[$idRentaLiq]['valor']
                    : 0.0;

                $valorDesctoRenta = $idDesctoRenta && isset($Concepto[$idDesctoRenta])
                    ? (float) $Concepto[$idDesctoRenta]['valor']
                    : 0.0;

                $valorBase = $valorRentaLiq - $valorDesctoRenta;

                $c['valor'] = $this->aplicarOperacion(
                    $valorBase,
                    $valorParametro,
                    $operador
                );
            }
            unset($c);
        }

        // Caja libre
        if ($idCajaLibre) {
            $valorRend = $idRendimientos && isset($Concepto[$idRendimientos])
                ? (float) $Concepto[$idRendimientos]['valor']
                : 0.0;

            $ImptoRenta = $idImptoRenta && isset($Concepto[$idImptoRenta])
                ? (float) $Concepto[$idImptoRenta]['valor']
                : 0.0;

            $valorCajaLibre = $valorRend - $ImptoRenta;

            if (isset($Concepto[$idCajaLibre])) {
                $Concepto[$idCajaLibre]['valor'] = $valorCajaLibre;
            }
        }
    }


    /**
     * Sincroniza los conceptos de precio/kWh comunidad/bolsa
     * con los valores del DTO original, para que se guarden en BD.
     *
     * Regla importante:
     * - Si el DTO trae 0 o vacío, NO pisamos el valor que venía de BD.
     *   Solo actualizamos cuando trae un valor > 0.
     */
    protected function Proceso_Actualizar_Conceptos(): void
    {
        $Concepto = $this->ConceptoId();

        $idPrecioCom  = $this->parametros['id_concepto_precio_comunidad'] ?? 0;
        $idKwhCom     = $this->parametros['id_concepto_kwh_comunidad']    ?? 0;
        $idPrecioBol  = $this->parametros['id_concepto_precio_bolsa']     ?? 0;
        $idKwhBol     = $this->parametros['id_concepto_kwh_bolsa']        ?? 0;

        // -----------------------------
        // Flags para saber si viene valor en el DTO
        // -----------------------------
        $tienePrecioCom = array_key_exists('precio_energia_comunidad', $this->dtoOriginal)
            && $this->dtoOriginal['precio_energia_comunidad'] !== null
            && $this->dtoOriginal['precio_energia_comunidad'] !== ''
            && (float) str_replace(',', '', $this->dtoOriginal['precio_energia_comunidad']) > 0;

        $tienePrecioBol = array_key_exists('precio_energia_bolsa', $this->dtoOriginal)
            && $this->dtoOriginal['precio_energia_bolsa'] !== null
            && $this->dtoOriginal['precio_energia_bolsa'] !== ''
            && (float) str_replace(',', '', $this->dtoOriginal['precio_energia_bolsa']) > 0;

        $precioComunidad = $tienePrecioCom
            ? (float) str_replace(',', '', $this->dtoOriginal['precio_energia_comunidad'])
            : null;

        $precioBolsa = $tienePrecioBol
            ? (float) str_replace(',', '', $this->dtoOriginal['precio_energia_bolsa'])
            : null;

        // --- kWh: SOLO se actualizan si vienen en DTO y son > 0 ---
        $tieneKwhCom = array_key_exists('energia_comercializada_comunidad', $this->dtoOriginal)
            && $this->dtoOriginal['energia_comercializada_comunidad'] !== null
            && $this->dtoOriginal['energia_comercializada_comunidad'] !== ''
            && (float) str_replace(',', '', $this->dtoOriginal['energia_comercializada_comunidad']) > 0;

        $tieneKwhBolsa = array_key_exists('energia_comercializada_bolsa', $this->dtoOriginal)
            && $this->dtoOriginal['energia_comercializada_bolsa'] !== null
            && $this->dtoOriginal['energia_comercializada_bolsa'] !== ''
            && (float) str_replace(',', '', $this->dtoOriginal['energia_comercializada_bolsa']) > 0;

        $kwhComunidad = $tieneKwhCom
            ? (float) str_replace(',', '', $this->dtoOriginal['energia_comercializada_comunidad'])
            : null;

        $kwhBolsa = $tieneKwhBolsa
            ? (float) str_replace(',', '', $this->dtoOriginal['energia_comercializada_bolsa'])
            : null;

        // Datos contexto
        $idProyecto = $this->proyectoId;
        $anioProc   = (int) ($this->dtoOriginal['anio'] ?? 0);
        $mesProc    = (int) ($this->dtoOriginal['mes']  ?? 0);

        // Auditoría
        $user    = Auth::user();
        $usuario = $user?->usuario();
        $usuarioId  = $usuario->id     ?? null;
        $usuarioNom = $usuario->nombre ?? null;
        $now        = now();

        // ======================================================
        // 1. Actualizar EN MEMORIA (solo si viene valor > 0 en el DTO)
        // ======================================================

        if ($idPrecioCom && $tienePrecioCom && isset($Concepto[$idPrecioCom])) {
            $Concepto[$idPrecioCom]['valor'] = $precioComunidad;
        }

        if ($idKwhCom && $tieneKwhCom && isset($Concepto[$idKwhCom])) {
            $Concepto[$idKwhCom]['valor'] = $kwhComunidad;
        }

        if ($idPrecioBol && $tienePrecioBol && isset($Concepto[$idPrecioBol])) {
            $Concepto[$idPrecioBol]['valor'] = $precioBolsa;
        }

        if ($idKwhBol && $tieneKwhBolsa && isset($Concepto[$idKwhBol])) {
            $Concepto[$idKwhBol]['valor'] = $kwhBolsa;
        }

        // ======================================================
        // 2. Actualizar EN BD (solo cuando realmente viene dato nuevo)
        // ======================================================

        if ($idProyecto && $anioProc && $mesProc) {

            // Precio energía comunidad
            if ($idPrecioCom && $tienePrecioCom && $tieneKwhCom) {
                DB::table('valores_conceptos_por_proyecto')
                    ->where('id_proyecto', $idProyecto)
                    ->where('anio', $anioProc)
                    ->where('mes', $mesProc)
                    ->where('id_concepto_proyecto', $idPrecioCom)
                    ->update([
                        'valor_concepto_proyecto'     => $precioComunidad,
                        'usuario_modificacion_id'     => $usuarioId,
                        'usuario_modificacion_nombre' => $usuarioNom,
                        'updated_at'                  => $now,
                    ]);
            }

            // kWh comunidad SOLO si llegó y es > 0
            if ($idKwhCom && $tieneKwhCom) {
                DB::table('valores_conceptos_por_proyecto')
                    ->where('id_proyecto', $idProyecto)
                    ->where('anio', $anioProc)
                    ->where('mes', $mesProc)
                    ->where('id_concepto_proyecto', $idKwhCom)
                    ->update([
                        'valor_concepto_proyecto'     => $kwhComunidad,
                        'usuario_modificacion_id'     => $usuarioId,
                        'usuario_modificacion_nombre' => $usuarioNom,
                        'updated_at'                  => $now,
                    ]);
            }

            // Precio energía bolsa
            if ($idPrecioBol && $tienePrecioBol && $tieneKwhBolsa) {
                DB::table('valores_conceptos_por_proyecto')
                    ->where('id_proyecto', $idProyecto)
                    ->where('anio', $anioProc)
                    ->where('mes', $mesProc)
                    ->where('id_concepto_proyecto', $idPrecioBol)
                    ->update([
                        'valor_concepto_proyecto'     => $precioBolsa,
                        'usuario_modificacion_id'     => $usuarioId,
                        'usuario_modificacion_nombre' => $usuarioNom,
                        'updated_at'                  => $now,
                    ]);
            }

            // kWh bolsa SOLO si llegó y es > 0
            if ($idKwhBol && $tieneKwhBolsa) {
                DB::table('valores_conceptos_por_proyecto')
                    ->where('id_proyecto', $idProyecto)
                    ->where('anio', $anioProc)
                    ->where('mes', $mesProc)
                    ->where('id_concepto_proyecto', $idKwhBol)
                    ->update([
                        'valor_concepto_proyecto'     => $kwhBolsa,
                        'usuario_modificacion_id'     => $usuarioId,
                        'usuario_modificacion_nombre' => $usuarioNom,
                        'updated_at'                  => $now,
                    ]);
            }
        }
    }


    // =========================================================
    //  HELPERS
    // =========================================================

    /**
     * Construye un índice por id_concepto.
     *
     * @return array<int, array<string,mixed>>
     */
    protected function ConceptoId(): array
    {
        $Concepto = [];

        foreach ($this->conceptos as $i => &$c) {
            $idConcepto = $c['id_concepto'] ?? null;

            if ($idConcepto !== null) {
                $idConcepto = (int) $idConcepto;
                $Concepto[$idConcepto] = &$this->conceptos[$i];
            }
        }
        unset($c);

        return $Concepto;
    }

    /**
     * Suma valores según tipo de concepto y tipo de línea.
     */
    protected function sumarPorTipo(string $tipoConcepto, string $tipoLinea): float
    {
        $total = 0.0;

        foreach ($this->conceptos as $c) {
            if (
                ($c['indicativo_tipo_concepto'] ?? null) === $tipoConcepto &&
                ($c['indicativo_tipo_linea'] ?? null) === $tipoLinea
            ) {
                $total += (float) ($c['valor'] ?? 0);
            }
        }

        return $total;
    }

    /**
     * Calcula un concepto usando valor_mensual_concepto o porcentaje.
     *
     * Devuelve true si pudo calcular el valor del concepto,
     * false si no (en ese caso se usa la lógica antigua con parametro_referencia).
     */
    protected function calcularPorValorMensualOPorcentaje(
        array &$c,
        float $totalIngresosBase,
        array $Concepto,
        int $idRentaLiq
    ): bool {

        $valorMensual = (float) ($c['valor_mensual_concepto'] ?? 0.0);
        $porcentaje   = (float) ($c['porcentaje'] ?? 0.0);
        $tipoBase     = $c['indicativo_valor_base'] ?? null;
        $operador     = $c['indicativo_operador'] ?? null;

        // ======================================================
        // 1) PRIORIDAD: valor_mensual_concepto
        // ======================================================
        if ($valorMensual != 0.0) {
            $c['valor'] = $valorMensual;
            return true;
        }

        // ======================================================
        // 2) SEGUNDO: porcentaje  (si porcentaje ≠ 0)
        // ======================================================
        if ($porcentaje != 0.0) {

            // Si no hay tipo de base, no se puede calcular
            if ($tipoBase === null) {
                return false;
            }

            // ------------------------------------------------------
            // Definir valor_base según P / I / R   
            // ------------------------------------------------------
            $valorBase      = 0.0;
            $valorParametro = $porcentaje / 12.0;

            switch ($tipoBase) {
                case 'P':   // Valor total proyecto
                    $valorBase = $this->valorTotalProyecto;
                    break;

                case 'I':   // Total ingresos
                    $valorBase = $totalIngresosBase;
                    break;

                case 'R':   // Valor renta líquida (concepto R)
                    $valorBase = ($idRentaLiq && isset($Concepto[$idRentaLiq]))
                        ? (float) ($Concepto[$idRentaLiq]['valor'] ?? 0.0)
                        : 0.0;
                    break;

                default:
                    return false;
            }

            // ------------------------------------------------------
            // Aplicar operación según indicativo_operador
            // Si NO VIENE operador → asumir operador '%'
            // ------------------------------------------------------
            if ($operador === null) {
                $operador = '%';
            }

            $c['valor'] = $this->aplicarOperacion(
                $valorBase,
                $valorParametro,
                $operador
            );

            return true;
        }

        // ======================================================
        // 3) No hay ni valor_mensual_concepto ni porcentaje
        // ======================================================
        return false;
    }

    /**
     * Helper GENÉRICO para calcular (base, parámetro) en función de:
     *  - P: valor_total_proyecto  (param se divide /12)
     *  - I: ingresos (puede venir por parámetro o usa this->valorTotalIngresos)
     *  - R: renta líquida (idRentaLiq)
     */
    protected function calcularBaseYParametroGenerico(
        ?string $tipoBase,
        float $valorParametroConcepto,
        array $Concepto,
        int $idRentaLiq,
        ?float $totalIngresosBase = null
    ): array {
        $base  = 0.0;
        $param = $valorParametroConcepto;

        switch ($tipoBase) {
            case 'P':
                $base  = $this->valorTotalProyecto;
                $param = $valorParametroConcepto / 12.0;
                break;

            case 'I':
                // Si me pasan una base de ingresos, la uso (caso Ingresos genéricos).
                // Si no, uso this->valorTotalIngresos (Descuento renta).
                $base  = $totalIngresosBase ?? $this->valorTotalIngresos;
                $param = $valorParametroConcepto;
                break;

            case 'R':
                $base = ($idRentaLiq && isset($Concepto[$idRentaLiq]))
                    ? (float) ($Concepto[$idRentaLiq]['valor'] ?? 0.0)
                    : 0.0;
                $param = $valorParametroConcepto;
                break;

            default:
                $base  = 0.0;
                $param = $valorParametroConcepto;
                break;
        }

        return [$base, $param];
    }

    /**
     * Alias semántico para los conceptos genéricos de INGRESOS.
     * Mantiene la firma original.
     */
    protected function calcularBaseConceptoGenericoIngresos(
        ?string $tipoBase,
        float $valorParametroConcepto,
        float $totalIngresosAcumulado,
        array $Concepto,
        int $idRentaLiq
    ): array {
        return $this->calcularBaseYParametroGenerico(
            $tipoBase,
            $valorParametroConcepto,
            $Concepto,
            $idRentaLiq,
            $totalIngresosAcumulado
        );
    }

    /**
     * Alias semántico para Descuento en renta.
     * Mantiene la firma original.
     */
    protected function calcularBaseYParametroDescuentoRenta(
        ?string $tipoBase,
        float $valorParametroConcepto,
        array $Concepto,
        int $idRentaLiq
    ): array {
        return $this->calcularBaseYParametroGenerico(
            $tipoBase,
            $valorParametroConcepto,
            $Concepto,
            $idRentaLiq,
            null // aquí 'I' usa this->valorTotalIngresos internamente
        );
    }

    /**
     * Aplica la operación (+, -, *, /, %) entre base y valor_parametro.
     */
    protected function aplicarOperacion(
        float $base,
        float $valorParametro,
        ?string $operador
    ): float {
        switch ($operador) {
            case '+':
                return $base + $valorParametro;
            case '-':
                return $base - $valorParametro;
            case '*':
                return $base * $valorParametro;
            case '/':
                return $valorParametro != 0.0 ? $base / $valorParametro : 0.0;
            case '%':
                return ($base * $valorParametro) / 100.0;
            default:
                return $base;
        }
    }
}
