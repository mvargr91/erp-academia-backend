<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use App\Models\Parametrizacion\ProyectoSimulacion;
use App\Enum\AccionAuditoriaEnum;
use App\Models\Seguridad\AuditoriaTabla;

class ConceptosSimulacionService
{
    // =========================================================
    // Variables de "Proceso_Lecturas_parametros"
    // =========================================================
    private float $irradiancia_promedio = 0.0;
    private float $parametro_porcentaje_perdida_eficiencia = 0.0;
    private float $parametro_porcentaje_descuento_tarifa = 0.0;
    private float $parametro_porcentaje_ipc = 0.0;
    private float $parametro_porcentaje_incremento_precio = 0.0;
    private float $parametro_numero_anios_simulacion = 0.0;

    private int $parametro_id_concepto_kwh_bolsa = 0;
    private int $parametro_id_concepto_precio_bolsa = 0;
    private int $parametro_id_concepto_kwh_comunidad = 0;
    private int $parametro_id_concepto_precio_comunidad = 0;

    private int $parametro_id_concepto_total_ingresos = 0;
    private int $parametro_id_concepto_total_egresos = 0;
    private int $parametro_id_concepto_rendimientos = 0;
    private int $parametro_id_concepto_depreciacion = 0;
    private int $parametro_id_concepto_renta_liq = 0;
    private int $parametro_id_concepto_descto_renta = 0;
    private int $parametro_id_concepto_impto_renta = 0;
    private int $parametro_id_concepto_caja_libre = 0;
    private int $parametro_id_concepto_caja_libre_acum = 0;
    private int $parametro_id_concepto_rentab_anual = 0;

    private int $parametro_id_concepto_venta_com = 0;
    private int $parametro_id_concepto_venta_bolsa = 0;
    private $parametro_IPC = '';

    // r = valor i del arreglo de conceptos donde id_concepto(i)=parametro_id_concepto_renta_liq
    private int $rIdx = 0;

    // Cache de parámetros por código (para evitar queries repetidas)
    private array $paramCache = [];

    private float $caja_libre_acum = 0.0;

    private float $depreciacion_acum = 0.0;
    private float $valor_maximo_descto_renta = 0.0;

    // =========================================================
    // Proceso principal
    // =========================================================
    public function ejecutar(array $dto): array
    {
        // reset global para una corrida completa (Año 1 + proyectados)
        $this->Proceso_Lecturas_Parametros($dto);
        $dto = $this->Proceso_Conceptos_Calculados($dto);
        return $dto;
    }

    // =========================================================
    // Proceso_Boton_Guardar
    // =========================================================
    public function boton_guardar(array $dto): array
    {
        return DB::transaction(function () use ($dto) {
            $respPrincipal = $this->Proceso_Actualizar_Simulacion($dto);
            $this->Proceso_Actualizar_Simulacion_Sin_Beneficio_Trib($dto);
            return $respPrincipal;
        });
    }

    // =========================================================
    // Proceso_Lecturas_parametros
    // =========================================================
    private function Proceso_Lecturas_Parametros(array $dto): void
    {
        $this->irradiancia_promedio = $this->getParametroConstanteFloat('IRRADIANCIA_PROMEDIO');
        $this->parametro_porcentaje_perdida_eficiencia = $this->getParametroConstanteFloat('PORCENTAJE_PERDIDA_EFICIENCIA');
        $this->parametro_porcentaje_descuento_tarifa = $this->getParametroConstanteFloat('PORCENTAJE_DESCUENTO_TARIFA');
        $this->parametro_porcentaje_ipc = $this->getParametroConstanteFloat('PORCENTAJE_IPC');
        $this->parametro_porcentaje_incremento_precio = $this->getParametroConstanteFloat('PORCENTAJE_INCREMENTO_PRECIO');
        $this->parametro_porcentaje_descuento_renta = $this->getParametroConstanteFloat('PORCENTAJE_DESCUENTO_RENTA');
        $this->parametro_numero_anios_simulacion = $this->getParametroConstanteFloat('NUMERO_ANIOS_SIMULACION');

        $this->parametro_id_concepto_total_ingresos = (int)$this->getParametroConstanteFloat('ID_CONCEPTO_TOTAL_INGRESOS');
        $this->parametro_id_concepto_total_egresos  = (int)$this->getParametroConstanteFloat('ID_CONCEPTO_TOTAL_EGRESOS');
        $this->parametro_id_concepto_rendimientos   = (int)$this->getParametroConstanteFloat('ID_CONCEPTO_RENDIMIENTOS');
        $this->parametro_id_concepto_depreciacion   = (int)$this->getParametroConstanteFloat('ID_CONCEPTO_DEPRECIACION');
        $this->parametro_id_concepto_renta_liq      = (int)$this->getParametroConstanteFloat('ID_CONCEPTO_RENTA_LIQUIDA');
        $this->parametro_id_concepto_descto_renta   = (int)$this->getParametroConstanteFloat('ID_CONCEPTO_DESCUENTO_RENTA');
        $this->parametro_id_concepto_impto_renta    = (int)$this->getParametroConstanteFloat('ID_CONCEPTO_IMPUESTO_RENTA');
        $this->parametro_id_concepto_caja_libre     = (int)$this->getParametroConstanteFloat('ID_CONCEPTO_CAJA_LIBRE');
        $this->parametro_id_concepto_caja_libre_acum= (int)$this->getParametroConstanteFloat('ID_CONCEPTO_CAJA_LIBRE_ACUM');
        $this->parametro_id_concepto_rentab_anual = (int)$this->getParametroConstanteFloat('ID_CONCEPTO_RENTABILIDAD_ANUAL');

        $this->parametro_id_concepto_kwh_bolsa        = (int)$this->getParametroConstanteFloat('ID_CONCEPTO_KWH_BOLSA');
        $this->parametro_id_concepto_precio_bolsa     = (int)$this->getParametroConstanteFloat('ID_CONCEPTO_PRECIO_BOLSA');
        $this->parametro_id_concepto_kwh_comunidad    = (int)$this->getParametroConstanteFloat('ID_CONCEPTO_KWH_COMUNIDAD');
        $this->parametro_id_concepto_precio_comunidad = (int)$this->getParametroConstanteFloat('ID_CONCEPTO_PRECIO_COMUNIDAD');

        $this->parametro_id_concepto_venta_com   = (int)$this->getParametroConstanteFloat('ID_CONCEPTO_VENTA_ENERGIA_COM');
        $this->parametro_id_concepto_venta_bolsa = (int)$this->getParametroConstanteFloat('ID_CONCEPTO_VENTA_ENERGIA_BOLSA');
        $this->parametro_IPC = 'PORCENTAJE_IPC';

        // rIdx
        $this->rIdx = 0;
        $conceptos = $dto['conceptos'] ?? [];
        foreach ($conceptos as $idx => $c) {
            $id = (int)($c['id_concepto'] ?? $c['id_concepto_proyecto'] ?? 0);
            if ($id === $this->parametro_id_concepto_renta_liq) {
                $this->rIdx = (int)$idx;
                break;
            }
        }
    }

    // =========================================================
    // Proceso_Conceptos_Calculados
    // =========================================================
    private function Proceso_Conceptos_Calculados(array $dto): array
    {
        $conceptos = $dto['conceptos'] ?? [];
        if (!is_array($conceptos) || count($conceptos) === 0) {
            return $dto;
        }

        // Valor total proyecto
        $valor_total_proyecto = $this->toFloat($dto['valor_total_proyecto'] ?? 0);

        // Totales (se deben recalcular SIEMPRE desde cero)
        $Valor_total_ingresos = 0.0;
        $Valor_total_egresos  = 0.0;

        // Depreciación acumulada (en ESTE método solo aplica “año 1”) 
        $Valor_depreciado_acum = 0.0; 
        $Nro_anios_depreciados_acum = 0;

        // Desct renta 
        $Valor_descto_renta_acum = 0.0; 
        $Valor_descto_renta_siguiente = 0.0;
        $Valor_maximo_descto_renta= $valor_total_proyecto  * ($this->parametro_porcentaje_descuento_renta/100);
       
        // Caja libre acumulada base
        $caja_libre_acum_base = $valor_total_proyecto * -1;

        // VALOR ACUMULADO DESCT RENTA
        $this->valor_maximo_descto_renta = $valor_total_proyecto / 2;

        $j = 1;
        $anio = [];
        $anio[$j] = (int)date('Y');

        $i = 0;
        $m = 0;
        $r = $this->rIdx ?? 0;

        $modelo = (string)($dto['indicativo_modelo_ccial'] ?? '');
        $idProyecto = (int)($dto['id_proyecto'] ?? 0);
        $anios_depreciacion = (int)($dto['anios_depreciacion'] ?? 0);
        $parametro_accion = (string)($dto['accion'] ?? '');

        $generacion_anual = $this->getGeneracionAnualProyecto($idProyecto);

        // Índice por id_concepto -> idx
        $idxById = [];
        foreach ($conceptos as $idx => $c) {
            $idp = (int)($c['id_concepto_proyecto'] ?? 0);
            if ($idp) $idxById[$idp] = $idx;
        }

        // === Helpers
        $getValorById = function (int $id) use (&$conceptos, &$idxById): float {
            if (!isset($idxById[$id])) return 0.0;
            $idx = $idxById[$id];

            if (!isset($conceptos[$idx])) {
                return 0.0;
            }

            return $this->toFloat($conceptos[$idx]['valor_simulado'] ?? 0);
        };

        $setValorByIdx = function (?int $idx, float $val) use (&$conceptos): void {
            if ($idx === null) return;
            if (!isset($conceptos[$idx])) return;

            $conceptos[$idx]['valor_simulado'] = $val;
        };

        $esManualByIdx = function (?int $idx) use (&$conceptos): bool {
            if ($idx === null) {
                return false;
            }

            if (!isset($conceptos[$idx])) {
                return false;
            }

            return (bool)($conceptos[$idx]['simulado_manual'] ?? false);
        };

        // === CLAVE: Setear id_concepto y refrescar idxById para que getValorById() no quede viejo
        $setConceptIdByIdx = function (?int $idx, int $id) use (&$conceptos, &$idxById): void {
            if ($idx === null) return;
            if (!isset($conceptos[$idx])) return;

            $conceptos[$idx]['id_concepto_proyecto'] = $id;
            $idxById[$id] = $idx;
        };

        $aplicarOperador = function (float $base, float $param, string $op, string $baseIndic): float {
            $baseIndic = (string)$baseIndic;

            // === REGLA CLAVE: si la base es 'A', el resultado se suma al valor copiado (base) ===
            // Es decir: base + (cálculo)
            if ($baseIndic === 'A') {
                switch ($op) {
                    case '+': return $base + $param; // ya es "sumar" al copiado
                    case '-': return $base - $param; // restar al copiado
                    case '*': return $base + ($base * $param); // si lo usas así: suma el producto
                    case '/': return ($param == 0.0) ? 0.0 : ($base + ($base / $param));
                    case '%': return $base + (($base * $param) / 100.0); // 
                    default:  return $base; // conserva el copiado
                }
            }

            // === Para P / I / R se deja TAL CUAL el seudocódigo (sin sumar base) ===
            switch ($op) {
                case '+': 

                    return $base + $param;
                case '-': return $base - $param;
                case '*': return $base * $param;
                case '/': return ($param == 0.0) ? 0.0 : ($base / $param);
                case '%': return ($base * $param) / 100.0;
                default:  return 0.0;
            }
        };

        // =========================================================
        // TARIFAS: SIEMPRE calcular (si no es manual)
        // =========================================================
        $idxKwhCom    = $idxById[$this->parametro_id_concepto_kwh_comunidad]     ?? null;
        $idxPrecioCom = $idxById[$this->parametro_id_concepto_precio_comunidad] ?? null;
        $idxKwhBol    = $idxById[$this->parametro_id_concepto_kwh_bolsa]         ?? null;
        $idxPrecioBol = $idxById[$this->parametro_id_concepto_precio_bolsa]      ?? null;
        $precio = 0;

        if ($idxKwhCom !== null && !$esManualByIdx($idxKwhCom) && $modelo === 'CE') {
            $setValorByIdx($idxKwhCom, $generacion_anual);
        }else{
            if($esManualByIdx($idxKwhCom)) {
                $setValorByIdx($idxKwhCom, $conceptos[$idxKwhCom]['valor_simulado']);
            }else{
                $setValorByIdx($idxKwhCom, 0);
            }
        }

        if ($idxPrecioCom !== null && !$esManualByIdx($idxPrecioCom) && $modelo === 'CE') {
            $precio = $this->getPrecioProyectado($anio[$j], 'precio_comunidad');
            if ($precio != 0.0) $setValorByIdx($idxPrecioCom, $precio);
        }else{
            if($esManualByIdx($idxPrecioCom)) {
                $setValorByIdx($idxPrecioCom, $conceptos[$idxPrecioCom]['valor_simulado']);
            }else{
                $setValorByIdx($idxPrecioCom, 0);
            }
        }

        if ($idxKwhBol !== null && !$esManualByIdx($idxKwhBol) && $modelo === 'BO') {
            $setValorByIdx($idxKwhBol, $generacion_anual);
        }else{
            if($esManualByIdx($idxKwhBol)) {
                $setValorByIdx($idxKwhBol, $conceptos[$idxKwhBol]['valor_simulado']);
            }else{
                $setValorByIdx($idxKwhBol, 0);
            }
        }

        if ($idxPrecioBol !== null && !$esManualByIdx($idxPrecioBol) && $modelo === 'BO') {
            $precio = $this->getPrecioProyectado($anio[$j], 'precio_bolsa');
            if ($precio != 0.0) $setValorByIdx($idxPrecioBol, $precio);
        }else{
            if($esManualByIdx($idxPrecioBol)) {
                $setValorByIdx($idxPrecioBol, $conceptos[$idxPrecioBol]['valor_simulado']);
            }else{
                $setValorByIdx($idxPrecioBol, 0);
            }
        }

        // =========================================================
        // MQ concepto(i) <> ' '
        // =========================================================
        while (isset($conceptos[$i])) {

             // ---------------------------------------------------------
            // TARIFAS según orden esperado
            // ---------------------------------------------------------
            $parametro_id_concepto_KWh_com      = $this->parametro_id_concepto_kwh_comunidad;
            $parametro_id_concepto_precio_com   = $this->parametro_id_concepto_precio_comunidad;
            $parametro_id_concepto_KWh_bolsa    = $this->parametro_id_concepto_kwh_bolsa;
            $parametro_id_concepto_precio_bolsa = $this->parametro_id_concepto_precio_bolsa;

            if (isset($conceptos[$i]) && isset($conceptos[$i]['id_concepto_proyecto']) && $conceptos[$i]['id_concepto_proyecto'] == $parametro_id_concepto_KWh_com ) {
                if($parametro_id_concepto_KWh_com && $modelo === 'CE'){
                    if( $parametro_accion  == 'crear'){
                        if (!$esManualByIdx($i)) $setValorByIdx($i, $generacion_anual);
                    }
                    if($conceptos[$i]['valor_simulado'] == 0){
                        $conceptos[$i]['error'] = 'Energia generada kWh comunidad energetica requerida.'; 
                    }
                }
                $i++;
            }

            if (isset($conceptos[$i]) && isset($conceptos[$i]['id_concepto_proyecto']) && $conceptos[$i]['id_concepto_proyecto'] == $parametro_id_concepto_precio_com) {
                if($parametro_id_concepto_precio_com && $modelo === 'CE'){
                    if( $parametro_accion  == 'crear'){
                        if (!$esManualByIdx($i)) {
                            $precio = $this->getPrecioProyectado( $anio[$j], 'precio_comunidad');
                            if ($precio != 0.0) $setValorByIdx($i, $precio);
                        }
                    }
                    if($conceptos[$i]['valor_simulado'] == 0){
                        $conceptos[$i]['error'] = 'Precio energia comunidad energetica requerido.'; 
                    }
                }
                $i++;
            }

            if (isset($conceptos[$i]) && isset($conceptos[$i]['id_concepto_proyecto']) && $conceptos[$i]['id_concepto_proyecto'] == $parametro_id_concepto_KWh_bolsa) {
                if($parametro_id_concepto_KWh_bolsa && $modelo === 'BO'){
                    if( $parametro_accion  == 'crear'){
                        if (!$esManualByIdx($i)) $setValorByIdx($i, $generacion_anual);
                    }
                    if($conceptos[$i]['valor_simulado'] == 0){
                        $conceptos[$i]['error'] = 'Energia generada kWh bolsa requerida.'; 
                    }
                }
                $i++;
            }

            if (isset($conceptos[$i]) && isset($conceptos[$i]['id_concepto_proyecto']) && $conceptos[$i]['id_concepto_proyecto'] == $parametro_id_concepto_precio_bolsa) {
                if($parametro_id_concepto_precio_bolsa && $modelo === 'BO'){
                    if( $parametro_accion  == 'crear'){
                        if (!$esManualByIdx($i)) {
                            $precio = $this->getPrecioProyectado($anio[$j], 'precio_bolsa');
                            if ($precio != 0.0) $setValorByIdx($i, $precio);
                        }
                    }
                    if($conceptos[$i]['valor_simulado'] == 0){
                        $conceptos[$i]['error'] = 'Precio energia comunidad energetica requerido.'; 
                    }
                }
                $i++;
            }
            // =========================================================
            // DETALLE INGRESOS
            // =========================================================
            while (
                isset($conceptos[$i]) &&
                (string)($conceptos[$i]['indicativo_tipo_concepto'] ?? '') === 'ING' &&
                (string)($conceptos[$i]['indicativo_tipo_linea'] ?? '') === 'D'
            ) {
                // Manual: se usa valor_simulado digitado (ignora valor mensual)
                if ($esManualByIdx($i)) {
                    $Valor_total_ingresos += $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0);
                    $i++;
                    continue;
                }

                // Ventas
                $idConcepto = (int)($conceptos[$i]['id_concepto_proyecto'] ?? 0);

                // Venta Energía Comunidad
                if ($idConcepto === $this->parametro_id_concepto_venta_com) {
                    if ($modelo === 'CE') {
                        $kwh = $getValorById($this->parametro_id_concepto_kwh_comunidad);
                        $pre = $getValorById($this->parametro_id_concepto_precio_comunidad);
                        $setValorByIdx($i, $kwh * $pre);
                    } else {
                        $setValorByIdx($i, 0.0);
                    }
                }

                // Venta Energía Bolsa
                if ($idConcepto === $this->parametro_id_concepto_venta_bolsa) {
                    if ($modelo === 'BO') {
                        $kwh = $getValorById($this->parametro_id_concepto_kwh_bolsa);
                        $pre = $getValorById($this->parametro_id_concepto_precio_bolsa);
                        $setValorByIdx($i, $kwh * $pre);
                    } else {
                        $setValorByIdx($i, 0.0);
                    }
                }

                // Resto calculados por porcentaje/parametro
                // =========================================================
                // Si Indicativo_concepto_calculado = 'S'
                // =========================================================
                $numero_anio_inicial_proy = $conceptos[$i]['numero_anio_inicial_proy'] ?? 0;
                $numero_anios_proy = $conceptos[$i]['numero_anios_proy'] ?? 999;

                if ((string)($conceptos[$i]['indicativo_concepto_calculado'] ?? '') === 'S'
                && $j >= $numero_anio_inicial_proy
                && $j <= $numero_anios_proy
                ) {

                    $valorMensual = $this->toFloat($conceptos[$i]['valor'] ?? 0);
                    $pct          = $this->toFloat($conceptos[$i]['porcentaje'] ?? 0);
                    $pref         = trim((string)($conceptos[$i]['parametro_referencia'] ?? ''));

                    // ---------------------------------------------------------
                    // Si valor_mensual_concepto(i) <> 0
                    // Valor_concepto(i) = valor_mensual_concepto(i) * 12
                    // ---------------------------------------------------------
                    if ($valorMensual != 0.0) {

                        $setValorByIdx($i, $valorMensual * 12.0);

                    } else {

                        // ---------------------------------------------------------
                        // Sino, si porcentaje(i) <> 0
                        // ---------------------------------------------------------
                        if ($pct != 0.0) {

                            $baseIndic  = (string)($conceptos[$i]['indicativo_valor_base'] ?? '');
                            $valor_base = 0.0;
                            $valor_parametro = $pct;

                            // Caso Indicativo_valor_base(i)
                            switch ($baseIndic) {
                                case 'P': // proyectos.Valor_total_proyecto
                                    $valor_base = (float)$valor_total_proyecto;
                                    break;

                                case 'I': // Valor_concepto(m)
                                    $valor_base = $this->toFloat($conceptos[$m]['valor_simulado'] ?? 0);
                                    break;

                                case 'R': // Valor_concepto(r)
                                    $valor_base = $this->toFloat($conceptos[$r]['valor_simulado'] ?? 0);
                                    break;

                                case 'A': // Valor_concepto(i)
                                    $valor_base = $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0);
                                    break;

                                default:
                                    $valor_base = 0.0;
                                    break;
                            }

                            // Caso Indicativo_operador(i)  
                            $op = (string)($conceptos[$i]['indicativo_operador'] ?? '');

                            if ($op === '+') {
                                $setValorByIdx($i, $valor_base + $valor_parametro);
                            } elseif ($op === '-') {
                                $setValorByIdx($i, $valor_base - $valor_parametro);
                            } elseif ($op === '*') {
                                $setValorByIdx($i, $valor_base * $valor_parametro);
                            } elseif ($op === '/') {
                                $setValorByIdx($i, ($valor_parametro == 0.0) ? 0.0 : ($valor_base / $valor_parametro));
                            } elseif ($op === '%') {
                                $setValorByIdx($i, ($valor_base * $valor_parametro) / 100.0);
                            }
                            // Si operador vacío o inválido → NO calcula (tal cual )

                        } else {

                            // ---------------------------------------------------------
                            // Sino, si parametro_referencia(i) <> ''
                            // ---------------------------------------------------------
                            if ($pref !== '') {

                                // valor_parametro_concepto(i)
                                $valor_parametro_concepto = (float)$this->getParametroConstanteFloat($pref);

                                $baseIndic  = (string)($conceptos[$i]['indicativo_valor_base'] ?? '');
                                $valor_base = 0.0;
                                $valor_parametro = $valor_parametro_concepto;

                                // Caso Indicativo_valor_base(i)
                                switch ($baseIndic) {
                                    case 'P': // proyectos.Valor_total_proyecto
                                        $valor_base = (float)$valor_total_proyecto;
                                        break;

                                    case 'I': // total_ingresos
                                        $valor_base = (float)$Valor_total_ingresos;
                                        break;

                                    case 'R': // Valor_concepto(r)
                                        $valor_base = $this->toFloat($conceptos[$r]['valor_simulado'] ?? 0);
                                        break;

                                    case 'A': // Valor_concepto(i)
                                        $valor_base = $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0);
                                        break;

                                    default:
                                        $valor_base = 0.0;
                                        break;
                                }

                                // Caso Indicativo_operador(i)  
                                $op = (string)($conceptos[$i]['indicativo_operador'] ?? '');

                                if ($op === '+') {
                                    $setValorByIdx($i, $valor_base + $valor_parametro);
                                } elseif ($op === '-') {
                                    $setValorByIdx($i, $valor_base - $valor_parametro);
                                } elseif ($op === '*') {
                                    $setValorByIdx($i, $valor_base * $valor_parametro);
                                } elseif ($op === '/') {
                                    $setValorByIdx($i, ($valor_parametro == 0.0) ? 0.0 : ($valor_base / $valor_parametro));
                                } elseif ($op === '%') {
                                    $setValorByIdx($i, ($valor_base * $valor_parametro) / 100.0);
                                }
                                // Si operador vacío o inválido → NO calcula
                            }
                        }
                    }

                }

                // ---------------------------------------------------------
                // total_ingresos = total_ingresos + valor_concepto(i)
                // ---------------------------------------------------------
                $Valor_total_ingresos += $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0);
                $i++;
            }

            // TOTAL INGRESOS (refresca idxById)
            if (isset($conceptos[$i])) {
                $setConceptIdByIdx($i, $this->parametro_id_concepto_total_ingresos);
                if (!$esManualByIdx($i)) $setValorByIdx($i, $Valor_total_ingresos);
            }
            $m = $i;
            $i++;

            // =========================================================
            // DETALLE EGRESOS
            // =========================================================
            while (
                isset($conceptos[$i]) &&
                (string)($conceptos[$i]['indicativo_tipo_concepto'] ?? '') === 'EGR' &&
                (string)($conceptos[$i]['indicativo_tipo_linea'] ?? '') === 'D'
            ) {

                // ---------------------------------------------------------
                // Si Indicativo_concepto_calculado = 'S'
                // ---------------------------------------------------------
                $numero_anio_inicial_proy = $conceptos[$i]['numero_anio_inicial_proy'] ?? 0;
                $numero_anios_proy = $conceptos[$i]['numero_anios_proy'] ?? 999;

                if ((string)($conceptos[$i]['indicativo_concepto_calculado'] ?? '') === 'S'
                 && $j >= $numero_anio_inicial_proy
                 && $j <= $numero_anios_proy
                ) {

                    $valorMensual = $this->toFloat($conceptos[$i]['valor'] ?? 0);
                    $pct          = $this->toFloat($conceptos[$i]['porcentaje'] ?? 0);
                    $pref         = trim((string)($conceptos[$i]['parametro_referencia'] ?? ''));

                    if ($esManualByIdx($i)) {
                        $Valor_total_egresos += $this->toFloat($conceptos[$i]['valor_simulado'] );
                        $i++;
                        continue;
                    }

                    // Si valor_mensual_concepto(i) <> 0
                    if ($valorMensual != 0.0) {

                        // Valor_concepto(i) = valor_mensual * 12
                        $setValorByIdx($i, $valorMensual * 12.0);

                    } else {

                        // Sino si porcentaje(i) <> 0
                        if ($pct != 0.0) {

                            // -----------------------------
                            // Caso Indicativo_valor_base(i)
                            // -----------------------------
                            $baseIndic  = (string)($conceptos[$i]['indicativo_valor_base'] ?? '');
                            $valor_base = 0.0;
                            $valor_parametro = $pct;

                            if ($baseIndic === 'P') $valor_base = (float)$valor_total_proyecto;                          // proyectos.Valor_total_proyecto
                            if ($baseIndic === 'I') $valor_base = $this->toFloat($conceptos[$m]['valor_simulado'] ?? 0); // Valor_concepto(m)
                            if ($baseIndic === 'R') $valor_base = $this->toFloat($conceptos[$r]['valor_simulado'] ?? 0); // Valor_concepto(r)
                            if ($baseIndic === 'A') $valor_base = $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0); // Valor_concepto(i)

                            // -----------------------------
                            // Caso Indicativo_operador(i) 
                            // -----------------------------
                            $op = (string)($conceptos[$i]['indicativo_operador'] ?? '');

                            if ($op === '+') {
                                $setValorByIdx($i, $valor_base + $valor_parametro);
                            } elseif ($op === '-') {
                                $setValorByIdx($i, $valor_base - $valor_parametro);
                            } elseif ($op === '*') {
                                $setValorByIdx($i, $valor_base * $valor_parametro);
                            } elseif ($op === '/') {
                                $setValorByIdx($i, ($valor_parametro == 0.0) ? 0.0 : ($valor_base / $valor_parametro));
                            } elseif ($op === '%') {
                                $setValorByIdx($i, ($valor_base * $valor_parametro) / 100.0);
                            }
                            // operador vacío/no válido => NO calcula (tal cual )

                        } else {

                            // Sino si parametro_referencia(i) <> ' '
                            if ($pref !== '') {

                                // valor_parametro_concepto(i) = SELECT valor_parametro FROM parametros_constantes WHERE codigo_parametro = pref
                                $valor_parametro_concepto = (float)$this->getParametroConstanteFloat($pref);

                                // -----------------------------
                                // Caso Indicativo_valor_base(i)
                                // -----------------------------
                                $baseIndic  = (string)($conceptos[$i]['indicativo_valor_base'] ?? '');
                                $valor_base = 0.0;

                                // En EGRESOS ():
                                // P/I/R => valor_parametro = valor_parametro_concepto(i)
                                // A     => valor_parametro = porcentaje(i)
                                $valor_parametro = 0.0;

                                if ($baseIndic === 'P') {
                                    $valor_base = (float)$valor_total_proyecto;
                                    $valor_parametro = $valor_parametro_concepto;
                                }
                                if ($baseIndic === 'I') {
                                    $valor_base = $this->toFloat($conceptos[$m]['valor_simulado'] ?? 0); // Valor_concepto(m)
                                    $valor_parametro = $valor_parametro_concepto;
                                }
                                if ($baseIndic === 'R') {
                                    $valor_base = $this->toFloat($conceptos[$r]['valor_simulado'] ?? 0); // Valor_concepto(r)
                                    $valor_parametro = $valor_parametro_concepto;
                                }
                                if ($baseIndic === 'A') {
                                    $valor_base = $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0); // Valor_concepto(i)
                                    $valor_parametro = $pct; // <-- TAL CUAL  (porcentaje(i))
                                }

                                // -----------------------------
                                // Caso Indicativo_operador(i)  
                                // -----------------------------
                                $op = (string)($conceptos[$i]['indicativo_operador'] ?? '');

                                if ($op === '+') {
                                    $setValorByIdx($i, $valor_base + $valor_parametro);
                                } elseif ($op === '-') {
                                    $setValorByIdx($i, $valor_base - $valor_parametro);
                                } elseif ($op === '*') {
                                    $setValorByIdx($i, $valor_base * $valor_parametro);
                                } elseif ($op === '/') {
                                    $setValorByIdx($i, ($valor_parametro == 0.0) ? 0.0 : ($valor_base / $valor_parametro));
                                } elseif ($op === '%') {
                                    $setValorByIdx($i, ($valor_base * $valor_parametro) / 100.0);
                                }
                                // operador vacío/no válido => NO calcula
                            }
                        }
                    }
                }

                // ---------------------------------------------------------
                // total_egresos = total_egresos + valor_concepto(i)
                // ---------------------------------------------------------
                $Valor_total_egresos += $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0.0);

                // i = i + 1
                $i++;
            }
            // ========================= Fin MQ =========================


            // TOTAL EGRESOS (refresca idxById)
            if (isset($conceptos[$i])) {
                $setConceptIdByIdx($i, $this->parametro_id_concepto_total_egresos);
                if (!$esManualByIdx($i)) $setValorByIdx($i, $Valor_total_egresos);
            }
            $i++;

            // RENDIMIENTOS (refresca idxById)
            if (isset($conceptos[$i])) {
                $setConceptIdByIdx($i, $this->parametro_id_concepto_rendimientos);
                if (!$esManualByIdx($i)) {
                    $valIng = $getValorById($this->parametro_id_concepto_total_ingresos);
                    $valEgr = $getValorById($this->parametro_id_concepto_total_egresos);
                    $setValorByIdx($i, $valIng - $valEgr);
                }
            }
            $i++;

            // Depreciación
            if (isset($conceptos[$i])) {
                $setConceptIdByIdx($i, $this->parametro_id_concepto_depreciacion);

                // if (!$esManualByIdx($i)) {

                    $indicCalc = (string)($conceptos[$i]['indicativo_concepto_calculado'] ?? '');
                    $valorConcepto = $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0);
                    
                    // Si calculado='S' AND valor(i)=0 AND anios_dep>0
                    // AND Nro_anios_dep_acum < anios_dep
                    // AND Valor_dep_acum < Valor_total_proyecto
                    $Valor_depreciado_acum = 0.0;
                    if (
                        $indicCalc === 'S' &&
                        $Valor_depreciado_acum < $valor_total_proyecto
                    ) {

                        // Valor_depreciacion_año = Valor_total_proyecto / anios_depreciacion
                        $depAnio = $valor_total_proyecto / $anios_depreciacion;

                        // Tope: Si depAnio > Valor_concepto(r) => depAnio = Valor_concepto(r)
                        // En tu orden, "r" usable aquí es RENDIMIENTOS (ya calculado)
                        $rend = $getValorById($this->parametro_id_concepto_rendimientos);

                        if ($depAnio > $rend) {
                            $depAnio = $rend;
                        }


                        $depSiguiente = $Valor_depreciado_acum + $depAnio;

                        // Si depSiguiente < Valor_total_proyecto
                        if ($depSiguiente < $valor_total_proyecto) {

                            $setValorByIdx($i, $depAnio);
                            $Valor_depreciado_acum += $depAnio;
                            $this->depreciacion_acum += $depAnio;

                        } else {

                            // Sino: Valor_concepto(i) = Valor_total_proyecto - Valor_dep_acum
                            $restante = $valor_total_proyecto - $Valor_depreciado_acum;
                            $setValorByIdx($i, $restante);
                            $Valor_depreciado_acum += $restante;
                            $this->depreciacion_acum += $restante;
                        }
                    }
                // }
            }
            $i++;

            // RENTA LIQUIDA (refresca idxById)
            if (isset($conceptos[$i])) {
                $setConceptIdByIdx($i, $this->parametro_id_concepto_renta_liq);
                if (!$esManualByIdx($i)) {
                    $rend = $getValorById($this->parametro_id_concepto_rendimientos);
                    $dep  = $getValorById($this->parametro_id_concepto_depreciacion);
                    $setValorByIdx($i, $rend - $dep);
                }
                $r = $i;
            }
            $i++;

            // DESCUENTO RENTA (refresca idxById)
            if (isset($conceptos[$i])) {
                $setConceptIdByIdx($i, $this->parametro_id_concepto_descto_renta);              

                if (!$esManualByIdx($i)) {
                    $pref = (string)($conceptos[$i]['parametro_referencia'] ?? '');
                    if ($pref !== '') {
                        $valor_parametro = $this->getParametroConstanteFloat($pref);

                        $baseIndic = (string)($conceptos[$i]['indicativo_valor_base'] ?? '');
                        $valor_base = 0.0;

                        if ($baseIndic === 'P') $valor_base = $valor_total_proyecto;
                        if ($baseIndic === 'I') $valor_base = $getValorById($this->parametro_id_concepto_total_ingresos);
                        if ($baseIndic === 'R') $valor_base = $this->toFloat($conceptos[$r]['valor_simulado'] ?? 0);
                        if ($baseIndic === 'A') $valor_base = $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0);

                        $op = $conceptos[$i]['indicativo_operador'] ?? null;

                        if ($op === '+') {
                            $setValorByIdx($i, $valor_base + $valor_parametro);
                        } elseif ($op === '-') {
                            $setValorByIdx($i, $valor_base - $valor_parametro);
                        } elseif ($op === '*') {
                            $setValorByIdx($i, $valor_base * $valor_parametro);
                        } elseif ($op === '/') {
                            $setValorByIdx($i, ($valor_parametro == 0.0) ? 0.0 : ($valor_base / $valor_parametro));
                        } elseif ($op === '%') {
                            $setValorByIdx($i, ($valor_base * $valor_parametro) / 100.0);
                        }
                    }
                }

                // ==================================
                // CONTROL VALOR MAX DESCT RENTA
                // ==================================

                if($Valor_descto_renta_acum < $Valor_maximo_descto_renta && $j <= $conceptos[$i]['numero_anios_proy']){
                    $Valor_descto_renta_siguiente = $Valor_descto_renta_acum  + $conceptos[$i]['valor_simulado'];
                    if($Valor_descto_renta_siguiente < $Valor_maximo_descto_renta){
                       
                        $Valor_descto_renta_acum =  $Valor_descto_renta_acum  + $conceptos[$i]['valor_simulado'];
                    }else{
                        $restante = $Valor_maximo_descto_renta - $Valor_descto_renta_acum;
                        $Valor_descto_renta_acum += $restante;
                        $setValorByIdx($i, $restante);
                    }
                }else{
                    $setValorByIdx($i, 0);
                }
            }
            $i++;

            // IMPUESTO RENTA (refresca idxById)
            if (isset($conceptos[$i])) {
                $setConceptIdByIdx($i, $this->parametro_id_concepto_impto_renta);

                if (!$esManualByIdx($i)) {
                    $pref = (string)($conceptos[$i]['parametro_referencia'] ?? '');
                    if ($pref !== '') {
                        $valor_parametro = $this->getParametroConstanteFloat($pref);

                        $valor_base = $getValorById($this->parametro_id_concepto_renta_liq)
                            - $getValorById($this->parametro_id_concepto_descto_renta);

                        $op = $conceptos[$i]['indicativo_operador'] ?? null;
                        if ($op === '+') {
                            $setValorByIdx($i, $valor_base + $valor_parametro);
                        } elseif ($op === '-') {
                            $setValorByIdx($i, $valor_base - $valor_parametro);
                        } elseif ($op === '*') {
                            $setValorByIdx($i, $valor_base * $valor_parametro);
                        } elseif ($op === '/') {
                            $setValorByIdx($i, ($valor_parametro == 0.0) ? 0.0 : ($valor_base / $valor_parametro));
                        } elseif ($op === '%') {
                            $setValorByIdx($i, ($valor_base * $valor_parametro) / 100.0);
                        }
                    }
                }
            }
            $i++;

            // CAJA LIBRE (refresca idxById)
            if (isset($conceptos[$i])) {
                $setConceptIdByIdx($i, $this->parametro_id_concepto_caja_libre);

                if (!$esManualByIdx($i)) {
                    $rend = $getValorById($this->parametro_id_concepto_rendimientos);
                    $imp  = $getValorById($this->parametro_id_concepto_impto_renta);
                    $setValorByIdx($i, $rend - $imp);
                }
            }
            $i++;

            // CAJA LIBRE ACUMULADA (AÑO 1): SIEMPRE desde base fija (NO re-acumular sobre valor_simulado anterior)
            if (isset($conceptos[$i])) {
                $setConceptIdByIdx($i, $this->parametro_id_concepto_caja_libre_acum);

                if (!$esManualByIdx($i)) {
                    $caja = $getValorById($this->parametro_id_concepto_caja_libre);
                    // acumulado_1 = -valor_total_proyecto + caja_libre_1
                    $setValorByIdx($i, $caja_libre_acum_base + $caja);
                    $this->caja_libre_acum = $caja_libre_acum_base + $caja;
                }
            }
            $i++;
            // RENTABILIDAD ANUAL (ROI) = caja_libre / valor_total_proyecto
            if (isset($conceptos[$i])) {
                $setConceptIdByIdx($i, $this->parametro_id_concepto_rentab_anual); 

                if (!$esManualByIdx($i)) {
                    $caja = $getValorById($this->parametro_id_concepto_caja_libre);
                    $vtp  = (float)$valor_total_proyecto;

                    $roi = ($vtp == 0.0) ? 0.0 : ($caja / $vtp);

                    // si en tu sistema lo guardas como porcentaje:
                    $roi = $roi * 100.0;

                    $setValorByIdx($i, $roi);
                }
            }
            $i++;

            // En tu estructura, aquí normalmente termina el año 1.
            // Los años 2..N se calculan en tu otro proceso por año (conceptosPrev).
        }

        $dto['conceptos'] = $conceptos;
        return $dto;
    }

    // PROYECTADOS

    /**
     * =========================================================
     * Proceso_Actualizar_Simulacion 
     *
     * IMPORTANTE:
     * - ESTE método recibe el $dto con el AÑO 1 YA CALCULADO.
     *   Es decir: $dto['conceptos'] ya viene con valor_simulado correcto.
     * - Aquí SOLO:
     *   1) Proyecta años (2..N) con ese año1 calculado
     *   2) Upsert encabezado
     *   3) Inserta conceptos por año
     *   4) Calcula VPN / TIR / PBT
     * =========================================================
     */
    public function Proceso_Actualizar_Simulacion(array $dto): array
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $usuario = $user?->usuario();

        $uid  = $usuario->id ?? null;
        $unom = $usuario->nombre ?? null;

        $now = now();
        $this->Proceso_Lecturas_Parametros($dto);
        return DB::transaction(function () use ($dto, $uid, $unom, $now) {

            $idProyecto = (int)($dto['id_proyecto'] ?? 0);
            $modelo     = trim((string)($dto['indicativo_modelo_ccial'] ?? ''));

            $porcTasaOportunidad = $this->toFloat(
                $dto['porcentaje_tasa_oportunidad']
                ?? 0
            );

            // // 1) Proyectar años 2..N usando como base el año 1 ya calculado en $dto['conceptos']
            // $dto = $this->Proceso_Conceptos_Calculados_proyectados($dto);

            // // 2) Guardar encabezado (upsert)
            // $conceptosPorAnio = $this->Proceso_Conceptos_Calculados_proyectados($dto);
            $dto = $this->Proceso_Conceptos_Calculados_proyectados($dto);
            $conceptosPorAnio = $dto['conceptos_por_anio'] ?? [];
            $tirVpnData = $this->Proceso_calcular_TIR_VPN($conceptosPorAnio, $dto);
            $tir =  ($tirVpnData['tir'] ); 
            $Vpn =  $tirVpnData['vpn']; 
            $pbtData = $this->Proceso_calcular_aniosPBT($dto);
            $simId = $this->Proceso_Actualizar_simulacion_proyecto(
                $dto,
                $tir,
                $Vpn,
                $pbtData,
                $uid,
                $unom
            );
            $vpnLocal = number_format($Vpn, 2, '.', '');
            $tirLocal =  round($tir * 100, 2);

            // 3) Guardar conceptos por año
            $this->Proceso_Actualizar_Conceptos_anio($simId, $dto, $uid, $unom, $now);

            // RETORNAMOS EL TIR/VPN/PBT CALCULADOS A LA RESPUESTA 
            $dto['id'] = $simId;
            $dto['tir'] = $tirLocal;
            $dto['vpn'] = $vpnLocal;
            $dto['pbt'] = $pbtData;
            return $dto;

        });
    }

    public function Proceso_Actualizar_Simulacion_Sin_Beneficio_Trib(array $dto): array
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $usuario = $user?->usuario();

        $uid  = $usuario->id ?? null;
        $unom = $usuario->nombre ?? null;

        $now = now();
        $this->Proceso_Lecturas_Parametros($dto);
        return DB::transaction(function () use ($dto, $uid, $unom, $now) {

            $dto = $this->Proceso_Simulacion_Conceptos_Sin_Beneficio_Trib($dto);		
            $dto = $this->Proceso_Conceptos_Calculados_proyectados($dto);
            $conceptosPorAnio = $dto['conceptos_por_anio'] ?? [];
            $tirVpnData = $this->Proceso_calcular_TIR_VPN($conceptosPorAnio, $dto);
            $tir =  ($tirVpnData['tir'] ); 
            $Vpn =  $tirVpnData['vpn']; 
            $pbtData = $this->Proceso_calcular_aniosPBT($dto);
            $simId = $this->Proceso_Actualizar_simulacion_proyecto_Sin_Beneficio_Trib(
                $dto,
                $tir,
                $Vpn,
                $pbtData,
                $uid,
                $unom
            );
            $vpnLocal = number_format($Vpn, 2, '.', '');
            $tirLocal =  round($tir * 100, 2);
            
            // 3) Guardar conceptos por año
            $this->Proceso_Actualizar_Conceptos_anio($simId, $dto, $uid, $unom, $now);

            // RETORNAMOS EL TIR/VPN/PBT CALCULADOS A LA RESPUESTA 
            $dto['id'] = $simId;
            $dto['tir'] = $tirLocal;
            $dto['vpn'] = $vpnLocal;
            $dto['pbt'] = $pbtData;           
                	
            return $dto;	
        });	
    }


    private function Proceso_Simulacion_Conceptos_Sin_Beneficio_Trib(array $dto): array
    {
        $dto['forzar_valor_maximo_descto_renta_cero'] = true;

        $valorTotalProyecto = (float) $this->normalizarNumero($dto['valor_total_proyecto'] ?? 0);

        $conceptos = array_values($dto['conceptos'] ?? []);

        // IDs de conceptos desde parámetros constantes
        $parametro_id_concepto_descto_renta    = (int) $this->obtenerParametroConstante('ID_CONCEPTO_DESCUENTO_RENTA');
        $parametro_id_concepto_impto_renta     = (int) $this->obtenerParametroConstante('ID_CONCEPTO_IMPUESTO_RENTA');
        $parametro_id_concepto_renta_liq       = (int) $this->obtenerParametroConstante('ID_CONCEPTO_RENTA_LIQUIDA');
        $parametro_id_concepto_rendimientos    = (int) $this->obtenerParametroConstante('ID_CONCEPTO_RENDIMIENTOS');
        $parametro_id_concepto_caja_libre      = (int) $this->obtenerParametroConstante('ID_CONCEPTO_CAJA_LIBRE');
        $parametro_id_concepto_caja_libre_acum = (int) $this->obtenerParametroConstante('ID_CONCEPTO_CAJA_LIBRE_ACUM');
        $parametro_id_concepto_rentab_anual    = (int) $this->obtenerParametroConstante('ID_CONCEPTO_RENTABILIDAD_ANUAL');

        // Reindexar por id_concepto_proyecto manteniendo acceso al índice real
        $map = [];

        foreach ($conceptos as $idx => $item) {
            $idConcepto = (int) ($item['id_concepto_proyecto'] ?? 0);

            if (!isset($conceptos[$idx]['valor_simulado']) || $conceptos[$idx]['valor_simulado'] === '') {
                $conceptos[$idx]['valor_simulado'] = 0;
            }

            $conceptos[$idx]['valor_simulado'] = (float) $this->normalizarNumero($conceptos[$idx]['valor_simulado']);

            $map[$idConcepto] = $idx;
        }

        /*
        * *** Genera concepto descuento en renta
        * Valor_maximo_descto_renta = 0
        * Si id_concepto(i) = parametro_id_concepto_descto_renta
        *    Valor_concepto(i) = 0
        */
        if (isset($map[$parametro_id_concepto_descto_renta])) {
            $idx = $map[$parametro_id_concepto_descto_renta];
            $conceptos[$idx]['valor_simulado'] = 0;
        }

        /*
        * *** Genera impuesto de renta
        */
        if (isset($map[$parametro_id_concepto_impto_renta])) {
            $idxImpuesto = $map[$parametro_id_concepto_impto_renta];

            $parametroReferencia = trim((string) ($conceptos[$idxImpuesto]['parametro_referencia'] ?? ''));
            $operador = trim((string) ($conceptos[$idxImpuesto]['indicativo_operador'] ?? ''));

            if ($parametroReferencia !== '') {
                $valorParametro = (float) $this->normalizarNumero(
                    $this->obtenerParametroConstante($parametroReferencia)
                );

                $valorRentaLiquida = 0;
                if (isset($map[$parametro_id_concepto_renta_liq])) {
                    $valorRentaLiquida = (float) $conceptos[$map[$parametro_id_concepto_renta_liq]]['valor_simulado'];
                }

                $valorDesctoRenta = 0;
                if (isset($map[$parametro_id_concepto_descto_renta])) {
                    $valorDesctoRenta = (float) $conceptos[$map[$parametro_id_concepto_descto_renta]]['valor_simulado'];
                }

                $valorBase = $valorRentaLiquida - $valorDesctoRenta;
                $valorImpuesto = 0;

                switch ($operador) {
                    case '+':
                        $valorImpuesto = $valorBase + $valorParametro;
                        break;
                    case '-':
                        $valorImpuesto = $valorBase - $valorParametro;
                        break;
                    case '*':
                        $valorImpuesto = $valorBase * $valorParametro;
                        break;
                    case '/':
                        $valorImpuesto = $valorParametro != 0 ? ($valorBase / $valorParametro) : 0;
                        break;
                    case '%':
                        $valorImpuesto = ($valorBase * $valorParametro) / 100;
                        break;
                    default:
                        $valorImpuesto = $valorBase;
                        break;
                }

                $conceptos[$idxImpuesto]['valor_simulado'] = $valorImpuesto;
            }
        }

        /*
        * *** Genera concepto flujo caja libre
        * Valor_concepto(i) = rendimientos - impuesto_renta
        */
        if (isset($map[$parametro_id_concepto_caja_libre])) {
            $idxCajaLibre = $map[$parametro_id_concepto_caja_libre];

            $valorRendimientos = 0;
            if (isset($map[$parametro_id_concepto_rendimientos])) {
                $valorRendimientos = (float) $conceptos[$map[$parametro_id_concepto_rendimientos]]['valor_simulado'];
            }

            $valorImpuesto = 0;
            if (isset($map[$parametro_id_concepto_impto_renta])) {
                $valorImpuesto = (float) $conceptos[$map[$parametro_id_concepto_impto_renta]]['valor_simulado'];
            }

            $conceptos[$idxCajaLibre]['valor_simulado'] = $valorRendimientos - $valorImpuesto;
        }

        /*
        * *** Genera concepto flujo caja libre acumulado
        * Valor_concepto(i) = Valor_concepto(i) - Valor_concepto.caja_libre()
        */
        if (isset($map[$parametro_id_concepto_caja_libre_acum])) {
            $idxCajaLibreAcum = $map[$parametro_id_concepto_caja_libre_acum];

            $valorActual = (float) $conceptos[$idxCajaLibreAcum]['valor_simulado'];

            $valorCajaLibre = 0;
            if (isset($map[$parametro_id_concepto_caja_libre])) {
                $valorCajaLibre = (float) $conceptos[$map[$parametro_id_concepto_caja_libre]]['valor_simulado'];
            }

            $conceptos[$idxCajaLibreAcum]['valor_simulado'] = $valorActual - $valorCajaLibre;
        }

        /*
        * *** Genera concepto rentabilidad (ROI)
        * Valor_concepto(i) = caja_libre / valor_total_proyecto
        */
        if (isset($map[$parametro_id_concepto_rentab_anual])) {
            $idxRoi = $map[$parametro_id_concepto_rentab_anual];

            $valorCajaLibre = 0;
            if (isset($map[$parametro_id_concepto_caja_libre])) {
                $valorCajaLibre = (float) $conceptos[$map[$parametro_id_concepto_caja_libre]]['valor_simulado'];
            }

            $conceptos[$idxRoi]['valor_simulado'] =
                $valorTotalProyecto != 0 ? ($valorCajaLibre / $valorTotalProyecto) * 100.0 : 0;
        }

        $dto['conceptos'] = $conceptos;

        return $dto;
    }



    // =========================================================
    // - j arranca en 2
    // - NO recalcula año 1 dentro del while
    // - año(j) = año(j-1) + 1
    // =========================================================

    // =========================================
    // Proceso_Conceptos_Calculados_proyectados
    // =========================================
    private function Proceso_Conceptos_Calculados_proyectados(array $dto): array
    {
        $this->Proceso_Lecturas_Parametros($dto);

        $conceptosAnio1 = $dto['conceptos'] ?? [];
        if (!is_array($conceptosAnio1) || count($conceptosAnio1) === 0) {
            return $dto;
        }

        // Normalizar valor_simulado del año 1 (por si llega string)
        foreach ($conceptosAnio1 as &$c) {
            $c = (array)$c;
            $c['valor_simulado'] = $this->toFloat($c['valor_simulado'] ?? 0);
            $c['valor'] = $this->toFloat($c['valor'] ?? 0);
        }
        unset($c);

        $anioBase = (int) date('Y');
        $n = (int) $this->parametro_numero_anios_simulacion;

        // año 1 siempre existe
        $conceptosPorAnio = [];
        $conceptosPorAnio[$anioBase] = $conceptosAnio1;

        $valorTotalProyecto = $this->toFloat($dto['valor_total_proyecto'] ?? 0);
        $aniosDepreciacion = (int)($dto['anios_depreciacion'] ?? 0);
        // Estado acumulado
        $valorDepreciadoAcum = 0.0; 
        $nroAniosDepAcum = 0;
        $Valor_maximo_descto_renta = 0;

        // Desct en Renta
        $Valor_descto_renta_acum = 0.0; 
        if(isset($dto['forzar_valor_maximo_descto_renta_cero']) && !empty($dto['forzar_valor_maximo_descto_renta_cero'])){
            $Valor_maximo_descto_renta = 0;
        }else{
            $Valor_maximo_descto_renta = $valorTotalProyecto  * ($this->parametro_porcentaje_descuento_renta/100);
        }


        if ($aniosDepreciacion > 0 && $valorTotalProyecto > 0) {
            $depAnio1 = 0.0;

            foreach ($conceptosAnio1 as $c) {
                $idp = (int)($c['id_concepto_proyecto'] ?? $c['id_concepto'] ?? 0);
                if ($idp === (int)$this->parametro_id_concepto_depreciacion) {
                    $depAnio1 = $this->toFloat($c['valor_simulado'] ?? 0);
                    break;
                }
            }

            if ($depAnio1 > 0.0) {
                // tope por seguridad
                if ($depAnio1 > $valorTotalProyecto) $depAnio1 = $valorTotalProyecto;

                $valorDepreciadoAcum = $depAnio1;
                $this->depreciacion_acum = $depAnio1;
                $nroAniosDepAcum     = 1;
            }
        }

        // Desct Renta acumulado
        $DescRentaAnio1 = 0.0;

        foreach ($conceptosAnio1 as $c) {
            $idp = (int)($c['id_concepto_proyecto'] ?? $c['id_concepto'] ?? 0);
            if ($idp === (int)$this->parametro_id_concepto_descto_renta) {
                $DescRentaAnio1 = $this->toFloat($c['valor_simulado'] ?? 0);
                break;
            }
        }

        if ($DescRentaAnio1 > 0.0) {
            $Valor_descto_renta_acum = $DescRentaAnio1;
        }

        // Caja libre acumulada: arranca en -valor_total_proyecto

        // Base para año 2 = año 1 (ya normalizado)
        $conceptosPrev = $conceptosAnio1;

        $j = 2;
        while ($j <= $n) {

            $anio = $anioBase + ($j - 1);

            $conceptosAnio = $this->Proceso_Conceptos_Calculados_anio_proyectado(
                $conceptosPrev,
                $anio,
                $anioBase,
                $dto,
                $valorDepreciadoAcum,
                $nroAniosDepAcum,
                $j,
                $Valor_descto_renta_acum,
                $Valor_maximo_descto_renta
            );

            // Blindaje: normalizar valor_simulado del año calculado
            // (por si en algún cálculo/concatenación quedó string)
            if (is_array($conceptosAnio)) {
                foreach ($conceptosAnio as &$c2) {
                    $c2 = (array)$c2;
                    $c2['valor_simulado'] = $this->toFloat($c2['valor_simulado'] ?? 0);
                }
                unset($c2);
            }

            $conceptosPorAnio[$anio] = $conceptosAnio;
            $conceptosPrev = $conceptosAnio;

            $j++;
        }

        $dto['conceptos_por_anio'] = $conceptosPorAnio;
        return $dto;
    }



    // ========================================================

    private function Proceso_Conceptos_Calculados_anio_proyectado(
        array $conceptosPrev,
        int $anioProyectado,
        int $anioBase,
        array $dto,
        float &$valorDepreciadoAcum,
        int   &$nroAniosDepAcum,
        int $j,
        float &$Valor_descto_renta_acum,
        float $Valor_maximo_descto_renta
                ): array {
        $this->Proceso_Lecturas_Parametros($dto);
        // =========================================================
        // 1) Copiar todo el arreglo año (j-1) -> año (j)
        //    Indicativo_permite_copia = 'S' mantiene valor,
        //    si no, deja valor_simulado = 0
        // =========================================================
        $conceptos = [];
        foreach ($conceptosPrev as $cPrev) {
            $cPrev = (array)$cPrev;
            $permite = (string)($cPrev['indicativo_permite_copia'] ?? 'S');
            $cPrev['valor_simulado'] = $this->toFloat($cPrev['valor_simulado'] ?? 0);
            // COPIA DE ARREGLO.
            $numero_anio_inicial_proy = $cPrev['numero_anio_inicial_proy'] ?? 0;
            $numero_anios_proy = $cPrev['numero_anios_proy'] ?? 999;
            if ($permite === 'S' && $j >= $numero_anio_inicial_proy && $j <= $numero_anios_proy) {
                $conceptos[] = $cPrev;
            } else {
                $cPrev['valor_simulado'] = 0.0;
                $cPrev['valor'] = 0.0;
                $conceptos[] = $cPrev;
            }
        }


        // Orden por secuencia
        // usort($conceptos, fn($a,$b) => ((int)($a['secuencia'] ?? 0)) <=> ((int)($b['secuencia'] ?? 0)));

        // =========================================================
        // Índice por id para buscar conceptos por id (KWH, PRECIOS, etc.)
        // =========================================================
        $idxById = [];
        foreach ($conceptos as $idx => $c) {
            $idp = (int)($c['id_concepto_proyecto'] ?? 0);
            if ($idp) $idxById[$idp] = $idx;
        }

        $esManualByIdx = function (int $idx) use (&$conceptos): bool {
            return (bool)($conceptos[$idx]['simulado_manual'] ?? false);
        };

        // =========================================================
        // DTO
        // =========================================================
        $modelo             = (string)($dto['indicativo_modelo_ccial'] ?? '');
        $idProyecto         = (int)($dto['id_proyecto'] ?? 0);
        $valorTotalProyecto = $this->toFloat($dto['valor_total_proyecto'] ?? 0);
        $aniosDepreciacion  = (int)($dto['anios_depreciacion'] ?? 0);

        
        // =========================================================
        // 3) MQ principal: MQ concepto(i) <> ' '
        // =========================================================
        $total_ingresos = 0.0;
        $total_egresos  = 0.0;

        $i = 0;
        $m = 0;
        $r = $this->rIdx ?? 0;
        while (isset($conceptos[$i])) {

            // =========================================================
            // 2) Procesa Conceptos de tarifas
            // =========================================================
            $lossPct    = (float)($this->parametro_porcentaje_perdida_eficiencia ?? 0.0);
            $factorEfic = 1.0 - ($lossPct / 100.0);


            $parametro_id_concepto_KWh_com      = $this->parametro_id_concepto_kwh_comunidad;
            $parametro_id_concepto_precio_com   = $this->parametro_id_concepto_precio_comunidad;
            $parametro_id_concepto_KWh_bolsa    = $this->parametro_id_concepto_kwh_bolsa;
            $parametro_id_concepto_precio_bolsa = $this->parametro_id_concepto_precio_bolsa;

            // Si concepto = parametro_id_concepto_KWh_com => valor = valor * (1 - perdida/100)
            if (isset($idxById[$this->parametro_id_concepto_kwh_comunidad]) ) {
                // $k = $idxById[$this->parametro_id_concepto_kwh_comunidad];
                if($modelo === 'CE'){
                    $val = $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0);
                    $conceptos[$i]['valor_simulado'] = $val * $factorEfic;
                }
                $i++;
            }

            // Si concepto = parametro_id_concepto_precio_com => traer precio proyectado
            if (isset($idxById[$this->parametro_id_concepto_precio_comunidad])) {
                if($modelo === 'CE'){
                    $k = $idxById[$this->parametro_id_concepto_precio_comunidad];
                    $precio = $this->getPrecioProyectado($anioProyectado, 'precio_comunidad');
                    $conceptos[$i]['valor_simulado'] = (float)$precio;
                }
                $i++;   
            }
            // Si concepto = parametro_id_concepto_KWh_bolsa => valor = valor * (1 - perdida/100)
            if (isset($idxById[$this->parametro_id_concepto_kwh_bolsa])) {
                if($modelo === 'BO'){
                    $k = $idxById[$this->parametro_id_concepto_kwh_bolsa];
                    $val = $this->toFloat($conceptos[$k]['valor_simulado'] ?? 0);
                    $conceptos[$i]['valor_simulado'] = $val * $factorEfic;
                }
                $i++;
            }
            // Si concepto = parametro_id_concepto_precio_bolsa => traer precio proyectado
            if (isset($idxById[$this->parametro_id_concepto_precio_bolsa])) {
                if($modelo === 'BO'){
                    $k = $idxById[$this->parametro_id_concepto_precio_bolsa];
                    $precio = $this->getPrecioProyectado($anioProyectado, 'precio_bolsa');
                    $conceptos[$k]['valor_simulado'] = (float)$precio;
                }
                $i++;
            }
            // =====================================================
            // MQ ING D
            // MQ Indicativo_tipo_concepto='ING' AND Indicativo_tipo_linea='D'
            // =====================================================
            while (
                isset($conceptos[$i]) &&
                (string)($conceptos[$i]['indicativo_tipo_concepto'] ?? '') === 'ING' &&
                (string)($conceptos[$i]['indicativo_tipo_linea'] ?? '') === 'D') {

                $idFila = (int)($conceptos[$i]['id_concepto'] ?? $conceptos[$i]['id_concepto_proyecto'] ?? 0);

                // =====================================================
                // VENTAS COMUNIDAD (SIEMPRE con precio proyectado del año)
                // =====================================================
                if ($idFila === (int)$this->parametro_id_concepto_venta_com && $modelo === 'CE') {

                    $kwh = 0.0;
                    if (isset($idxById[$this->parametro_id_concepto_kwh_comunidad])) {
                        $idx = $idxById[$this->parametro_id_concepto_kwh_comunidad];
                        $kwh = $this->toFloat($conceptos[$idx]['valor_simulado'] ?? 0);
                    }

                    $pre = (float)$this->getPrecioProyectado($anioProyectado, 'precio_comunidad');

                    $conceptos[$i]['valor_simulado'] = $kwh * $pre;

                    $total_ingresos += $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0);
                    $i++;
                    continue; 
                }

                // =====================================================
                // VENTAS BOLSA (SIEMPRE con precio proyectado del año)
                // =====================================================
                if ($idFila === (int)$this->parametro_id_concepto_venta_bolsa && $modelo === 'BO') {

                    $kwh = 0.0;
                    if (isset($idxById[$this->parametro_id_concepto_kwh_bolsa])) {
                        $idx = $idxById[$this->parametro_id_concepto_kwh_bolsa];
                        $kwh = $this->toFloat($conceptos[$idx]['valor_simulado'] ?? 0);
                    }

                    $pre = (float)$this->getPrecioProyectado($anioProyectado, 'precio_bolsa');

                    $conceptos[$i]['valor_simulado'] = $kwh * $pre;

                    $total_ingresos += $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0);
                    $i++;
                    continue;
                }

                // =====================================================
                // PROYECCION INGRESOS
                // =====================================================
                $valorActual = $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0);
                $pctProy     = $this->toFloat($conceptos[$i]['porcentaje_proy'] ?? 0);
                $refProy     = trim((string)($conceptos[$i]['parametro_referencia_proy'] ?? ''));

                $numero_anio_inicial_proy = $conceptos[$i]['numero_anio_inicial_proy'] ?? 0;
                $numero_anios_proy = $conceptos[$i]['numero_anios_proy'] ?? 999;

                if($j >= $numero_anio_inicial_proy && $j <= $numero_anios_proy){
                    if($valorActual != 0.0 && ($pctProy != 0.0 || $refProy !== '')) {
    
                        // =====================================================
                        // Si (porcentaje_proy(i) <> 0)  => valor_parametro = porcentaje_proy(i)
                        // Sino => usa parametro_referencia_proy(i)
                        // =====================================================
                        if ($pctProy != 0.0) {                        
    
                            // Caso Indicativo_valor_base_proy(i)
                            $baseIndic = (string)($conceptos[$i]['indicativo_valor_base_proy'] ?? '');
                            $valor_anterior = $conceptos[$i]['valor_simulado'];
                            $valor_base = 0.0;
    
                            if ($baseIndic === 'P') $valor_base = (float)$valorTotalProyecto;                          // proyectos.Valor_total_proyecto
                            if ($baseIndic === 'I') $valor_base = $this->toFloat($conceptos[$m]['valor_simulado'] ?? 0); // Valor_concepto(m)
                            if ($baseIndic === 'R') $valor_base = $this->toFloat($conceptos[$r]['valor_simulado'] ?? 0); // Valor_concepto(r)
                            if ($baseIndic === 'A') $valor_base = $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0); // Valor_concepto(i)
    
                            $valor_parametro =  ($valor_base * $pctProy) / 100;
                            // Caso Indicativo_operador_proy(i) (SIN FALLBACK)
                            $op = (string)($conceptos[$i]['indicativo_operador_proy'] ?? '');
    
                            if ($op === '+') {
                                $conceptos[$i]['valor_simulado'] = $valor_base + $valor_parametro;
                            } elseif ($op === '-') {
                                $conceptos[$i]['valor_simulado'] = $valor_base - $valor_parametro;
                            } elseif ($op === '*') {
                                $conceptos[$i]['valor_simulado'] = $valor_base * $valor_parametro;
                            } elseif ($op === '/') {
                                $conceptos[$i]['valor_simulado'] = ($valor_parametro == 0.0) ? 0.0 : ($valor_base / $valor_parametro);
                            } elseif ($op === '%') {
                                $conceptos[$i]['valor_simulado'] = ($valor_base * $pctProy) / 100.0;
                            }
                            // operador vacío/no válido => NO calcula (tal cual )
    
                        } else {
    
                            // ===============================================================================
                            // Si parametro_referencia_proy(i) <> ' '
                            // valor_parametro_concepto(i) = SELECT valor_parametro FROM parametros_constantes
                            // ===============================================================================
                            if ($refProy !== '') {
    
                                $valor_parametro = (float)$this->getParametroConstanteFloat($refProy);
                                if($refProy == $this->parametro_IPC){
                                    $valor_parametro = $dto['porcentaje_IPC'];
                                }
    
                                // Caso Indicativo_valor_base_proy(i)
                                $baseIndic = (string)($conceptos[$i]['indicativo_valor_base_proy'] ?? '');
                                $valor_anterior = $conceptos[$i]['valor_simulado'];
                                $valor_base = 0.0;
    
                                if ($baseIndic === 'P') $valor_base = (float)$valorTotalProyecto;                          // proyectos.Valor_total_proyecto
                                if ($baseIndic === 'I') $valor_base = $this->toFloat($conceptos[$m]['valor_simulado'] ?? 0); // Valor_concepto(m)
                                if ($baseIndic === 'R') $valor_base = $this->toFloat($conceptos[$r]['valor_simulado'] ?? 0); // Valor_concepto(r)
                                if ($baseIndic === 'A') $valor_base = $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0); // Valor_concepto(i)
    
                                // Caso Indicativo_operador_proy(i) (SIN FALLBACK)
                                $op = (string)($conceptos[$i]['indicativo_operador_proy'] ?? '');
    
                                if ($op === '+') {
                                    $conceptos[$i]['valor_simulado'] = $valor_base + $valor_parametro;
                                } elseif ($op === '-') {
                                    $conceptos[$i]['valor_simulado'] = $valor_base - $valor_parametro;
                                } elseif ($op === '*') {
                                    $conceptos[$i]['valor_simulado'] = $valor_base * $valor_parametro;
                                } elseif ($op === '/') {
                                    $conceptos[$i]['valor_simulado'] = ($valor_parametro == 0.0) ? 0.0 : ($valor_base / $valor_parametro);
                                } elseif ($op === '%') {
                                    $conceptos[$i]['valor_simulado'] = $valor_base + (($valor_base * $valor_parametro) / 100.0);
                                }
                                // operador vacío/no válido => NO calcula
                            }
                        }
                    } else {
                        if($conceptos[$i]['valor_simulado'] == 0){
                            // =====================================================
                            // SINO calcular con los parametros del concepto (INGRESOS)
                            // =====================================================
    
                            // Si valor_mensual_concepto(i) <> 0 => valor = valor_mensual * 12
                            $valorMensual = $this->toFloat($conceptos[$i]['valor'] ?? 0);
    
                            if ($valorMensual != 0.0) {
    
                                $conceptos[$i]['valor_simulado'] = $valorMensual * 12.0;
    
                            } else {
    
                                $pct = $this->toFloat($conceptos[$i]['porcentaje'] ?? 0);
                                $ref = trim((string)($conceptos[$i]['parametro_referencia'] ?? ''));
    
                                // -----------------------------
                                // Si porcentaje(i) <> 0
                                // -----------------------------
                                if ($pct != 0.0) {
    
                                    $baseIndic = (string)($conceptos[$i]['indicativo_valor_base'] ?? '');
                                    $valor_base = 0.0;
                                    $valor_parametro = $pct;
    
                                    if ($baseIndic === 'P') $valor_base = (float)$valorTotalProyecto;                          // proyectos.Valor_total_proyecto
                                    if ($baseIndic === 'I') $valor_base = $this->toFloat($conceptos[$m]['valor_simulado'] ?? 0); // Valor_concepto(m)
                                    if ($baseIndic === 'R') $valor_base = $this->toFloat($conceptos[$r]['valor_simulado'] ?? 0); // Valor_concepto(r)
                                    if ($baseIndic === 'A') $valor_base = $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0); // Valor_concepto(i)
    
                                    $op = (string)($conceptos[$i]['indicativo_operador'] ?? '');
    
                                    if ($op === '+') {
                                        $conceptos[$i]['valor_simulado'] = $valor_base + $valor_parametro;
                                    } elseif ($op === '-') {
                                        $conceptos[$i]['valor_simulado'] = $valor_base - $valor_parametro;
                                    } elseif ($op === '*') {
                                        $conceptos[$i]['valor_simulado'] = $valor_base * $valor_parametro;
                                    } elseif ($op === '/') {
                                        $conceptos[$i]['valor_simulado'] = ($valor_parametro == 0.0) ? 0.0 : ($valor_base / $valor_parametro);
                                    } elseif ($op === '%') {
                                        $conceptos[$i]['valor_simulado'] = ($valor_base * $valor_parametro) / 100.0;
                                    }
                                    // operador vacío/no válido => NO calcula
    
                                } else {
    
                                    // -----------------------------
                                    // Sino: parametro_referencia(i) <> blanks
                                    // (INGRESOS: base A => valor_parametro = porcentaje(i) )
                                    // -----------------------------
                                    if ($ref !== '') {
    
                                        $valor_parametro_concepto = (float)$this->getParametroConstanteFloat($ref);
                                        if($ref == $this->parametro_IPC){
                                            $valor_parametro = $dto['porcentaje_IPC'];
                                        }
    
                                        $baseIndic = (string)($conceptos[$i]['indicativo_valor_base'] ?? '');
                                        $valor_base = 0.0;
    
                                        $valor_parametro = 0.0;
    
                                        if ($baseIndic === 'P') {
                                            $valor_base = (float)$valorTotalProyecto;
                                            $valor_parametro = $valor_parametro_concepto;
                                        }
                                        if ($baseIndic === 'I') {
                                            $valor_base = $this->toFloat($conceptos[$m]['valor_simulado'] ?? 0); // Valor_concepto(m)
                                            $valor_parametro = $valor_parametro_concepto;
                                        }
                                        if ($baseIndic === 'R') {
                                            $valor_base = $this->toFloat($conceptos[$r]['valor_simulado'] ?? 0); // Valor_concepto(r)
                                            $valor_parametro = $valor_parametro_concepto;
                                        }
                                        if ($baseIndic === 'A') {
                                            $valor_base = $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0); // Valor_concepto(i)
                                            $valor_parametro = $pct; 
                                        }
    
                                        $op = (string)($conceptos[$i]['indicativo_operador'] ?? '');
    
                                        if ($op === '+') {
                                            $conceptos[$i]['valor_simulado'] = $valor_base + $valor_parametro;
                                        } elseif ($op === '-') {
                                            $conceptos[$i]['valor_simulado'] = $valor_base - $valor_parametro;
                                        } elseif ($op === '*') {
                                            $conceptos[$i]['valor_simulado'] = $valor_base * $valor_parametro;
                                        } elseif ($op === '/') {
                                            $conceptos[$i]['valor_simulado'] = ($valor_parametro == 0.0) ? 0.0 : ($valor_base / $valor_parametro);
                                        } elseif ($op === '%') {
                                            $conceptos[$i]['valor_simulado'] = ($valor_base * $valor_parametro) / 100.0;
                                        }
                                        // operador vacío/no válido => NO calcula
                                    }
                                }
                            }                            
                        }
                    }
                }

                $total_ingresos += $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0);
                $i++;
            }


            // =====================================================
            // Genera concepto de total ingresos
            // Si id_concepto(i) = parametro_id_concepto_total_ingresos
            // =====================================================
            if (isset($conceptos[$i]) && trim((string)($conceptos[$i]['concepto'] ?? '')) !== '') {
                $idFila = (int)($conceptos[$i]['id_concepto'] ?? $conceptos[$i]['id_concepto_proyecto'] ?? 0);
                if ($idFila === (int)$this->parametro_id_concepto_total_ingresos) {
                    if (!((bool)($conceptos[$i]['simulado_manual'] ?? false))) {
                        $conceptos[$i]['valor_simulado'] = $total_ingresos;
                    }
                    $m = $i;
                    $i++;
                }
            }

            // =====================================================
            // MQ EGRESOS D
            // MQ Indicativo_tipo_concepto='EGR' AND Indicativo_tipo_linea='D'
            // =====================================================
            while (
                isset($conceptos[$i]) &&
                trim((string)($conceptos[$i]['concepto'] ?? '')) !== '' &&
                (string)($conceptos[$i]['indicativo_tipo_concepto'] ?? '') === 'EGR' &&
                (string)($conceptos[$i]['indicativo_tipo_linea'] ?? '') === 'D'
            ) {

                // Si Indicativo_concepto_calculado='S'
                $indicCalc = (string)($conceptos[$i]['indicativo_concepto_calculado'] ?? 'N');
                $numero_anio_inicial_proy = $conceptos[$i]['numero_anio_inicial_proy'] ?? 0;
                $numero_anios_proy = $conceptos[$i]['numero_anios_proy'] ?? 999;

                if($indicCalc === 'S' && $j >= $numero_anio_inicial_proy && $j <= $numero_anios_proy){
                    // =====================================================
                    // PROYECCION (ajustada al seudocódigo: SIN lógica extra,
                    // SIN % especial A, SIN else=0)
                    // =====================================================
                    $valorActual = $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0);
                    $pctProy     = $this->toFloat($conceptos[$i]['porcentaje_proy'] ?? 0);
                    $refProy     = trim((string)($conceptos[$i]['parametro_referencia_proy'] ?? ''));

                    if ($valorActual != 0.0 && ($pctProy != 0.0 || $refProy !== '')) {

                        if ($pctProy != 0.0) {

                            // Si porcentaje_proy(i) <> 0                            

                            // Caso Indicativo_valor_base_proy(i)
                            $baseIndic = (string)($conceptos[$i]['indicativo_valor_base_proy'] ?? '');
                            $valor_anterior = $conceptos[$i]['valor_simulado'];
                            $valor_base = 0.0;

                            if ($baseIndic === 'P') $valor_base = (float)$valorTotalProyecto;                          // proyectos.Valor_total_proyecto
                            if ($baseIndic === 'I') $valor_base = $this->toFloat($conceptos[$m]['valor_simulado'] ?? 0); // Valor_concepto(m)
                            if ($baseIndic === 'R') $valor_base = $this->toFloat($conceptos[$r]['valor_simulado'] ?? 0); // Valor_concepto(r)
                            if ($baseIndic === 'A') $valor_base = $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0); // Valor_concepto(i)

                            $valor_parametro =( $valor_base * $pctProy) / 100;
                            // Caso Indicativo_operador_proy(i) (SIN FALLBACK)
                            $op = (string)($conceptos[$i]['indicativo_operador_proy'] ?? '');

                            if ($op === '+') {
                                $conceptos[$i]['valor_simulado'] = $valor_base + $valor_parametro;
                            } elseif ($op === '-') {
                                $conceptos[$i]['valor_simulado'] = $valor_base - $valor_parametro;
                            } elseif ($op === '*') {
                                $conceptos[$i]['valor_simulado'] = $valor_base * $valor_parametro;
                            } elseif ($op === '/') {
                                $conceptos[$i]['valor_simulado'] = ($valor_parametro == 0.0) ? 0.0 : ($valor_base / $valor_parametro);
                            } elseif ($op === '%') {
                                $conceptos[$i]['valor_simulado'] = ($valor_base * $valor_parametro) / 100.0;
                            }
                            // operador vacío/no válido => NO calcula

                        } else {

                            // Sino: parametro_referencia_proy(i) <> blanks
                            if ($refProy !== '') {

                                $valor_parametro = (float)$this->getParametroConstanteFloat($refProy);
                                if($refProy == $this->parametro_IPC){
                                    $valor_parametro = $dto['porcentaje_IPC'];
                                }

                                // Caso Indicativo_valor_base_proy(i)
                                $baseIndic = (string)($conceptos[$i]['indicativo_valor_base_proy'] ?? '');
                                $valor_anterior = $conceptos[$i]['valor_simulado'];
                                $valor_base = 0.0;

                                if ($baseIndic === 'P') $valor_base = (float)$valorTotalProyecto;                          // proyectos.Valor_total_proyecto
                                if ($baseIndic === 'I') $valor_base = $this->toFloat($conceptos[$m]['valor_simulado'] ?? 0); // Valor_concepto(m)
                                if ($baseIndic === 'R') $valor_base = $this->toFloat($conceptos[$r]['valor_simulado'] ?? 0); // Valor_concepto(r)
                                if ($baseIndic === 'A') $valor_base = $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0); // Valor_concepto(i)

                                // Caso Indicativo_operador_proy(i) (SIN FALLBACK)
                                $op = (string)($conceptos[$i]['indicativo_operador_proy'] ?? '');

                                if ($op === '+') {
                                    $conceptos[$i]['valor_simulado'] = $valor_base + $valor_parametro;
                                } elseif ($op === '-') {
                                    $conceptos[$i]['valor_simulado'] = $valor_base - $valor_parametro;
                                } elseif ($op === '*') {
                                    $conceptos[$i]['valor_simulado'] = $valor_base * $valor_parametro;
                                } elseif ($op === '/') {
                                    $conceptos[$i]['valor_simulado'] = ($valor_parametro == 0.0) ? 0.0 : ($valor_base / $valor_parametro);
                                } elseif ($op === '%') {
                                    $conceptos[$i]['valor_simulado'] = $valor_base + (($valor_base * $valor_parametro) / 100.0);
                                }
                                // operador vacío/no válido => NO calcula
                            }
                        }

                    } else {

                        if($conceptos[$i]['valor_simulado'] == 0){
                            // =====================================================
                            // SINO calcular con los parametros del concepto (EGRESOS)
                            // =====================================================
    
                            // Si valor_mensual_concepto(i) <> 0 => valor = valor_mensual * 12
                            $valorMensual = $this->toFloat($conceptos[$i]['valor'] ?? 0);
    
                            if ($valorMensual != 0.0) {
    
                                $conceptos[$i]['valor_simulado'] = $valorMensual * 12.0;
    
                            } else {
    
                                $pct = $this->toFloat($conceptos[$i]['porcentaje'] ?? 0);
                                $ref = trim((string)($conceptos[$i]['parametro_referencia'] ?? ''));
    
                                // -----------------------------
                                // Si porcentaje(i) <> 0
                                // -----------------------------
                                if ($pct != 0.0) {
    
                                    $baseIndic = (string)($conceptos[$i]['indicativo_valor_base'] ?? '');
                                    $valor_base = 0.0;
                                    $valor_parametro = $pct;
    
                                    if ($baseIndic === 'P') $valor_base = (float)$valorTotalProyecto;                          // proyectos.Valor_total_proyecto
                                    if ($baseIndic === 'I') $valor_base = $this->toFloat($conceptos[$m]['valor_simulado'] ?? 0); // Valor_concepto(m)
                                    if ($baseIndic === 'R') $valor_base = $this->toFloat($conceptos[$r]['valor_simulado'] ?? 0); // Valor_concepto(r)
                                    if ($baseIndic === 'A') $valor_base = $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0); // Valor_concepto(i)
    
                                    $op = (string)($conceptos[$i]['indicativo_operador'] ?? '');
    
                                    if ($op === '+') {
                                        $conceptos[$i]['valor_simulado'] = $valor_base + $valor_parametro;
                                    } elseif ($op === '-') {
                                        $conceptos[$i]['valor_simulado'] = $valor_base - $valor_parametro;
                                    } elseif ($op === '*') {
                                        $conceptos[$i]['valor_simulado'] = $valor_base * $valor_parametro;
                                    } elseif ($op === '/') {
                                        $conceptos[$i]['valor_simulado'] = ($valor_parametro == 0.0) ? 0.0 : ($valor_base / $valor_parametro);
                                    } elseif ($op === '%') {
                                        $conceptos[$i]['valor_simulado'] = ($valor_base * $valor_parametro) / 100.0;
                                    }
                                    // operador vacío/no válido => NO calcula
    
                                } else {
    
                                    // -----------------------------
                                    // Sino: parametro_referencia(i) <> blanks
                                    // (EGRESOS: base A => valor_parametro = porcentaje(i) )
                                    // -----------------------------
                                    if ($ref !== '') {
    
                                        $valor_parametro_concepto = (float)$this->getParametroConstanteFloat($ref);
                                        if($ref == $this->parametro_IPC){
                                            $valor_parametro = $dto['porcentaje_IPC'];
                                        }
    
                                        $baseIndic = (string)($conceptos[$i]['indicativo_valor_base'] ?? '');
                                        $valor_base = 0.0;
    
                                        $valor_parametro = 0.0;
    
                                        if ($baseIndic === 'P') {
                                            $valor_base = (float)$valorTotalProyecto;
                                            $valor_parametro = $valor_parametro_concepto;
                                        }
                                        if ($baseIndic === 'I') {
                                            $valor_base = $this->toFloat($conceptos[$m]['valor_simulado'] ?? 0); // Valor_concepto(m)
                                            $valor_parametro = $valor_parametro_concepto;
                                        }
                                        if ($baseIndic === 'R') {
                                            $valor_base = $this->toFloat($conceptos[$r]['valor_simulado'] ?? 0); // Valor_concepto(r)
                                            $valor_parametro = $valor_parametro_concepto;
                                        }
                                        if ($baseIndic === 'A') {
                                            $valor_base = $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0); // Valor_concepto(i)
                                            $valor_parametro = $pct; 
                                        }
    
                                        $op = (string)($conceptos[$i]['indicativo_operador'] ?? '');
    
                                        if ($op === '+') {
                                            $conceptos[$i]['valor_simulado'] = $valor_base + $valor_parametro;
                                        } elseif ($op === '-') {
                                            $conceptos[$i]['valor_simulado'] = $valor_base - $valor_parametro;
                                        } elseif ($op === '*') {
                                            $conceptos[$i]['valor_simulado'] = $valor_base * $valor_parametro;
                                        } elseif ($op === '/') {
                                            $conceptos[$i]['valor_simulado'] = ($valor_parametro == 0.0) ? 0.0 : ($valor_base / $valor_parametro);
                                        } elseif ($op === '%') {
                                            $conceptos[$i]['valor_simulado'] = ($valor_base * $valor_parametro) / 100.0;
                                        }
                                        // operador vacío/no válido => NO calcula
                                    }
                                }
                            }                            
                        }
                    }
                }

                $total_egresos += $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0);
                $i++;
            }


            // =====================================================
            // Total egresos
            // =====================================================
            if (isset($conceptos[$i]) && trim((string)($conceptos[$i]['concepto'] ?? '')) !== '') {
                $idFila = (int)($conceptos[$i]['id_concepto'] ?? $conceptos[$i]['id_concepto_proyecto'] ?? 0);
                if ($idFila === (int)$this->parametro_id_concepto_total_egresos) {
                    if (!((bool)($conceptos[$i]['simulado_manual'] ?? false))) {
                        $conceptos[$i]['valor_simulado'] = $total_egresos;
                    }
                    $i++;
                }
            }

            // =====================================================
            // Rendimientos = total_ingresos - total_egresos
            // =====================================================
            if (isset($conceptos[$i]) && trim((string)($conceptos[$i]['concepto'] ?? '')) !== '') {
                $idFila = (int)($conceptos[$i]['id_concepto'] ?? $conceptos[$i]['id_concepto_proyecto'] ?? 0);
                if ($idFila === (int)$this->parametro_id_concepto_rendimientos) {
                    if (!((bool)($conceptos[$i]['simulado_manual'] ?? false))) {
                        $conceptos[$i]['valor_simulado'] = $total_ingresos - $total_egresos;
                    }
                    $i++;
                }
            }

            // =====================================================
            // Depreciación
            // =====================================================
            if (isset($conceptos[$i]) && trim((string)($conceptos[$i]['concepto'] ?? '')) !== '') {
                $idFila = (int)($conceptos[$i]['id_concepto'] ?? $conceptos[$i]['id_concepto_proyecto'] ?? 0);
                if ($idFila === (int)$this->parametro_id_concepto_depreciacion) {

                    // if (!((bool)($conceptos[$i]['simulado_manual'] ?? false))) {

                        $indicCalc = (string)($conceptos[$i]['indicativo_concepto_calculado'] ?? 'N');
                        $valActual = $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0);

                        if (
                            $indicCalc === 'S' &&
                            $this->depreciacion_acum < $valorTotalProyecto
                        ) {
                            $depAnio = $valorTotalProyecto / $aniosDepreciacion;

                            // Tope: depAnio no puede ser mayor a rendimientos (Valor_concepto(r))
                            $rend = 0.0;
                            if (isset($idxById[$this->parametro_id_concepto_rendimientos])) {
                                $idx = $idxById[$this->parametro_id_concepto_rendimientos];
                                $rend = $this->toFloat($conceptos[$idx]['valor_simulado'] ?? 0);
                            }
                            if ($depAnio > $rend) $depAnio = $rend;

                            $depSgte = $this->depreciacion_acum + $depAnio;
                            $depSgte = number_format($depSgte, 2, '.', '') ;

                            if ($depSgte  < $valorTotalProyecto) {
                                $conceptos[$i]['valor_simulado'] = $depAnio;
                                $valorDepreciadoAcum += $depAnio;
                                $this->depreciacion_acum += $depAnio;
                                
                            } else {
                                $restante = $valorTotalProyecto - $this->depreciacion_acum;
                                $conceptos[$i]['valor_simulado'] = $restante;
                                $valorDepreciadoAcum += $restante;
                                $this->depreciacion_acum += $restante;
                            }
                        }

                    // }

                    $i++;
                }
            }

            // =====================================================
            // Renta líquida = rendimientos - depreciación
            // =====================================================
            if (isset($conceptos[$i]) && trim((string)($conceptos[$i]['concepto'] ?? '')) !== '') {
                $idFila = (int)($conceptos[$i]['id_concepto'] ?? $conceptos[$i]['id_concepto_proyecto'] ?? 0);
                if ($idFila === (int)$this->parametro_id_concepto_renta_liq) {

                    if (!((bool)($conceptos[$i]['simulado_manual'] ?? false))) {
                        $rend = 0.0;
                        $dep  = 0.0;

                        if (isset($idxById[$this->parametro_id_concepto_rendimientos])) {
                            $idx = $idxById[$this->parametro_id_concepto_rendimientos];
                            $rend = $this->toFloat($conceptos[$idx]['valor_simulado'] ?? 0);
                        }
                        if (isset($idxById[$this->parametro_id_concepto_depreciacion])) {
                            $idx = $idxById[$this->parametro_id_concepto_depreciacion];
                            $dep = $this->toFloat($conceptos[$idx]['valor_simulado'] ?? 0);
                        }

                        $conceptos[$i]['valor_simulado'] = $rend - $dep;
                    }

                    $i++;
                }
            }

            // =====================================================
            // Descuento renta (por parametro referencia + operador)
            // =====================================================
            if (isset($conceptos[$i]) && trim((string)($conceptos[$i]['concepto'] ?? '')) !== '') {
                $idFila = (int)($conceptos[$i]['id_concepto'] ?? $conceptos[$i]['id_concepto_proyecto'] ?? 0);
                if ($idFila === (int)$this->parametro_id_concepto_descto_renta) {

                    if (!((bool)($conceptos[$i]['simulado_manual'] ?? false))) {

                        $ref = trim((string)($conceptos[$i]['parametro_referencia'] ?? ''));
                        if ($ref !== '') {

                            $valor_parametro_concepto = (float)$this->getParametroConstanteFloat($ref);

                            $baseIndic  = (string)($conceptos[$i]['indicativo_valor_base'] ?? '');
                            $valor_base = 0.0;

                            // En ESTE : SIEMPRE es el parametro (P/I/R/A)
                            $valor_parametro = 0.0;

                            if ($baseIndic === 'P') {
                                $valor_base = (float)$valorTotalProyecto;
                                $valor_parametro = $valor_parametro_concepto;
                            }

                            if ($baseIndic === 'I') {
                                // Valor_concepto.parametro_id_concepto_total_ingresos()
                                if (isset($idxById[$this->parametro_id_concepto_total_ingresos])) {
                                    $idx = $idxById[$this->parametro_id_concepto_total_ingresos];
                                    $valor_base = $this->toFloat($conceptos[$idx]['valor_simulado'] ?? 0);
                                } else {
                                    $valor_base = (float)$total_ingresos;
                                }
                                $valor_parametro = $valor_parametro_concepto;
                            }

                            if ($baseIndic === 'R') {
                                // Valor_concepto(r) ()
                                if (isset($idxById[$this->parametro_id_concepto_renta_liq])) {
                                    $idx = $idxById[$this->parametro_id_concepto_renta_liq];
                                    $valor_base = $this->toFloat($conceptos[$idx]['valor_simulado'] ?? 0);
                                } else {
                                    $valor_base = 0.0;
                                }
                                $valor_parametro = $valor_parametro_concepto;
                            }

                            if ($baseIndic === 'A') {
                                $valor_base = $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0); 
                                $valor_parametro = $valor_parametro_concepto;
                            }

                            $op = (string)($conceptos[$i]['indicativo_operador'] ?? '');

                            if ($op === '+') {
                                $conceptos[$i]['valor_simulado'] = $valor_base + $valor_parametro;
                            } elseif ($op === '-') {
                                $conceptos[$i]['valor_simulado'] = $valor_base - $valor_parametro;
                            } elseif ($op === '*') {
                                $conceptos[$i]['valor_simulado'] = $valor_base * $valor_parametro;
                            } elseif ($op === '/') {
                                $conceptos[$i]['valor_simulado'] = ($valor_parametro == 0.0) ? 0.0 : ($valor_base / $valor_parametro);
                            } elseif ($op === '%') {
                                $conceptos[$i]['valor_simulado'] = ($valor_base * $valor_parametro) / 100.0;
                            }
                            // operador vacío/no válido => NO calcula
                        }
                    }

                    // ==================================
                    // CONTROL VALOR MAX DESCT RENTA
                    // ==================================

                    if($Valor_descto_renta_acum < $Valor_maximo_descto_renta && $j <= $conceptos[$i]['numero_anios_proy']){
                        $Valor_descto_renta_siguiente = $Valor_descto_renta_acum  + $conceptos[$i]['valor_simulado'];
                        if($Valor_descto_renta_siguiente < $Valor_maximo_descto_renta){
                        
                            $Valor_descto_renta_acum =  $Valor_descto_renta_acum  + $conceptos[$i]['valor_simulado'];
                        }else{
                            $restante = $Valor_maximo_descto_renta - $Valor_descto_renta_acum;
                            $Valor_descto_renta_acum += $restante;
                            $conceptos[$i]['valor_simulado'] = $restante;
                        }
                    }else{
                        $conceptos[$i]['valor_simulado'] = 0;
                    }

                        $i++;
                    }
            }

            // =====================================================
            // Impuesto renta:
            // base = renta_liq - descto_renta ; operador con parametro_referencia
            // =====================================================
            if (isset($conceptos[$i]) && trim((string)($conceptos[$i]['concepto'] ?? '')) !== '') {
                $idFila = (int)($conceptos[$i]['id_concepto'] ?? $conceptos[$i]['id_concepto_proyecto'] ?? 0);
                if ($idFila === (int)$this->parametro_id_concepto_impto_renta) {

                    if (!((bool)($conceptos[$i]['simulado_manual'] ?? false))) {

                        // IMPUESTO RENTA (ajustado 1:1 al seudocódigo, sin % especial A, sin else=0)
                        $ref = trim((string)($conceptos[$i]['parametro_referencia'] ?? ''));
                        if ($ref !== '') {

                            // valor_parametro_concepto(i) = SELECT valor_parametro ...
                            $valor_parametro = (float)$this->getParametroConstanteFloat($ref);

                            // valor_base(i) = renta_liq - descto_renta
                            $renta  = 0.0;
                            $descto = 0.0;

                            if (isset($idxById[$this->parametro_id_concepto_renta_liq])) {
                                $idx = $idxById[$this->parametro_id_concepto_renta_liq];
                                $renta = $this->toFloat($conceptos[$idx]['valor_simulado'] ?? 0);
                            }

                            if (isset($idxById[$this->parametro_id_concepto_descto_renta])) {
                                $idx = $idxById[$this->parametro_id_concepto_descto_renta];
                                $descto = $this->toFloat($conceptos[$idx]['valor_simulado'] ?? 0);
                            }

                            $valor_base = $renta - $descto;

                            // Caso Indicativo_operador(i) (SIN fallback, SIN % especial A, SIN else=0)
                            $op = (string)($conceptos[$i]['indicativo_operador'] ?? '');

                            if ($op === '+') {
                                $conceptos[$i]['valor_simulado'] = $valor_base + $valor_parametro;
                            } elseif ($op === '-') {
                                $conceptos[$i]['valor_simulado'] = $valor_base - $valor_parametro;
                            } elseif ($op === '*') {
                                $conceptos[$i]['valor_simulado'] = $valor_base * $valor_parametro;
                            } elseif ($op === '/') {
                                $conceptos[$i]['valor_simulado'] = ($valor_parametro == 0.0) ? 0.0 : ($valor_base / $valor_parametro);
                            } elseif ($op === '%') {
                                $conceptos[$i]['valor_simulado'] = ($valor_base * $valor_parametro) / 100.0;
                            }
                            // operador vacío/no válido => NO calcula (tal cual )
                        }

                    }

                    $i++;
                }
            }

            // =====================================================
            // Caja libre = rendimientos - impuesto renta
            // =====================================================
            if (isset($conceptos[$i]) && trim((string)($conceptos[$i]['concepto'] ?? '')) !== '') {
                $idFila = (int)($conceptos[$i]['id_concepto'] ?? $conceptos[$i]['id_concepto_proyecto'] ?? 0);
                if ($idFila === (int)$this->parametro_id_concepto_caja_libre) {

                    if (!((bool)($conceptos[$i]['simulado_manual'] ?? false))) {

                        $rend = 0.0;
                        $imp  = 0.0;

                        if (isset($idxById[$this->parametro_id_concepto_rendimientos])) {
                            $idx = $idxById[$this->parametro_id_concepto_rendimientos];
                            $rend = $this->toFloat($conceptos[$idx]['valor_simulado'] ?? 0);
                        }
                        if (isset($idxById[$this->parametro_id_concepto_impto_renta])) {
                            $idx = $idxById[$this->parametro_id_concepto_impto_renta];
                            $imp = $this->toFloat($conceptos[$idx]['valor_simulado'] ?? 0);
                        }

                        $conceptos[$i]['valor_simulado'] = $rend - $imp;
                    }

                    $i++;
                }
            }

            // =====================================================
            // Caja libre acumulado
            // Valor_concepto(i) = Valor_concepto(i) - Valor_concepto(caja_libre)
            // =====================================================
            if (isset($conceptos[$i]) && trim((string)($conceptos[$i]['concepto'] ?? '')) !== '') {
                $idFila = (int)($conceptos[$i]['id_concepto'] ?? $conceptos[$i]['id_concepto_proyecto'] ?? 0);
                if ($idFila === (int)$this->parametro_id_concepto_caja_libre_acum) {



                        $prevAcum = $this->toFloat($conceptos[$i]['valor_simulado'] ?? 0);
                        $caja     = 0.0;

                        if (isset($idxById[$this->parametro_id_concepto_caja_libre])) {
                            $idx = $idxById[$this->parametro_id_concepto_caja_libre];
                            $caja = $this->toFloat($conceptos[$idx]['valor_simulado'] ?? 0);
                        }

                        $conceptos[$i]['valor_simulado'] = $prevAcum + $caja;
                        // $cajaLibreAcum = $conceptos[$i]['valor_simulado'];


                    $i++;
                }
            }

            if (isset($conceptos[$i]) && trim((string)($conceptos[$i]['concepto'] ?? '')) !== '') {
            $idFila = (int)($conceptos[$i]['id_concepto'] ?? $conceptos[$i]['id_concepto_proyecto'] ?? 0);

            if ($idFila === (int)$this->parametro_id_concepto_rentab_anual) {

                if (!((bool)($conceptos[$i]['simulado_manual'] ?? false))) {

                    $caja = 0.0;
                    if (isset($idxById[$this->parametro_id_concepto_caja_libre])) {
                        $idx = $idxById[$this->parametro_id_concepto_caja_libre];
                        $caja = $this->toFloat($conceptos[$idx]['valor_simulado'] ?? 0);
                    }

                    $vtp = (float)$valorTotalProyecto;
                    $roi = ($vtp == 0.0) ? 0.0 : ($caja / $vtp);
                    $roi = $roi * 100.0;

                    $conceptos[$i]['valor_simulado'] = $roi;
                }

                $i++;
            }
        }


            // Si por alguna razón no entró en ningún bloque y no avanzó, avanzamos 1 para no quedar en loop
            if (isset($conceptos[$i]) && trim((string)($conceptos[$i]['concepto'] ?? '')) !== '') {
                $i++;
            }
        }

        return $conceptos;
    }
 
    // =========================================================
    // Proceso_Actualizar_simulacion_proyecto  
    // (INSERT/UPDATE de proyectos_simulaciones)
    // =========================================================
private function Proceso_Actualizar_simulacion_proyecto(
    array $dto,
    float $tirData,
    float $vpnData,
    float $pbtData,
    int $uid,
    string $unom
): int {

    $idSimulacion = (int)($dto['id'] ?? 0);
    $idProyecto   = (int)($dto['id_proyecto'] ?? 0);
    $modelo       = trim((string)($dto['indicativo_modelo_ccial'] ?? ''));

    if ($idProyecto <= 0) {
        throw new \Exception('id_proyecto es requerido');
    }

    if ($modelo === '') {
        throw new \Exception('indicativo_modelo_ccial es requerido');
    }

    $vpnLocal = number_format($vpnData, 2, '.', '');
    $tirLocal = round($tirData * 100, 2);

    $porcTasaOportunidad = $this->toFloat(
        $dto['porcentaje_tasa_oportunidad']
        ?? $dto['tasa_oportunidad_pantalla']
        ?? 0
    );

    $data = [
        'id_proyecto'                  => $idProyecto,
        'indicativo_modelo_ccial'      => $modelo,
        'porcentaje_perdida_efic'      => $this->toFloat($dto['porcentaje_perdida_efic'] ?? 0),
        'indicativo_tipo_simulacion'   => ($dto['indicativo_tipo_simulacion'] ?? 0),
        'indicativo_beneficio_trib'    => ($dto['indicativo_beneficio_trib'] ?? 0),
        'valor_total_proyecto'         => $this->toFloat($dto['valor_total_proyecto'] ?? 0),
        'porcentaje_IPC'               => $this->toFloat($dto['porcentaje_IPC'] ?? 0),
        'anios_depreciacion'           => (int)($dto['anios_depreciacion'] ?? 0),
        'porcentaje_tasa_oportunidad'  => $porcTasaOportunidad,
        'Valor_VPN_proyecto'           => $vpnLocal,
        'porcentaje_TIR_proyecto'      => $tirLocal,
        'anios_PBT'                    => $pbtData,
        'usuario_modificacion_id'      => $uid,
        'usuario_modificacion_nombre'  => $unom,
    ];

    // =========================
    // EDITAR
    // =========================
    if ($idSimulacion > 0 && ($dto['accion'] ?? '') === 'editar') {
        $sim = ProyectoSimulacion::where('id', $idSimulacion)->first();

        if (!$sim) {
            throw new \Exception("No existe la simulación con id {$idSimulacion}.");
        }

        if ((int)$sim->id_proyecto !== $idProyecto) {
            throw new \Exception("La simulación {$idSimulacion} no pertenece al proyecto {$idProyecto}.");
        }

        // TOMAR ESTADO ORIGINAL ANTES DEL UPDATE
        $original = $sim->toJson();

        $ok = $sim->update($data);

        if (!$ok) {
            throw new \Exception('No se pudo actualizar la simulación');
        }

        // REFRESCAR PARA TENER EL ESTADO RESULTANTE REAL
        $sim->refresh();

        $this->guardarAuditoriaSimulacion(
            $sim,
            AccionAuditoriaEnum::MODIFICAR,
            $original,
            $sim->toJson()
        );

        return (int)$sim->id;
    }

    // =========================
    // CREAR
    // =========================
    $data['usuario_creacion_id']     = $uid;
    $data['usuario_creacion_nombre'] = $unom;

    $sim = ProyectoSimulacion::create($data);

    if (!$sim || !$sim->id) {
        throw new \Exception('No se pudo crear la simulación');
    }

    $this->guardarAuditoriaSimulacion(
        $sim,
        AccionAuditoriaEnum::CREAR,
        null,
        $sim->toJson()
    );

    return (int)$sim->id;
}

    // =========================================================
    // Proceso_Actualizar_simulacion_proyecto con beneficio tributario
    // (INSERT/UPDATE de proyectos_simulaciones)
    // =========================================================
    private function Proceso_Actualizar_simulacion_proyecto_Sin_Beneficio_Trib(
        array $dto,
        float $tirData,
        float $vpnData,
        float $pbtData,
        int $uid,
        string $unom
    ): int {

        // =========================
        // 1) Extraer y validar datos base
        // =========================
        $idProyecto = (int)($dto['id_proyecto'] ?? 0);
        $modelo     = trim((string)($dto['indicativo_modelo_ccial'] ?? ''));

        if ($idProyecto <= 0) {
            throw new \Exception('id_proyecto es requerido');
        }

        if ($modelo === '') {
            throw new \Exception('indicativo_modelo_ccial es requerido');
        }

        // =========================
        // 2) Eliminar simulaciones previas del mismo proyecto/modelo
        //    cuando:
        //    - indicativo_beneficio_trib = 'S'
        //    - o indicativo_tipo_simulacion = 'I'
        // =========================
        $simulacionesPrevias = ProyectoSimulacion::query()
            ->where('id_proyecto', $idProyecto)
            ->where('indicativo_modelo_ccial', $modelo)
            ->where(function ($q) {
                $q->where('indicativo_tipo_simulacion', 'I');
            })
            ->get();

        foreach ($simulacionesPrevias as $simPrevia) {
            DB::table('proyectos_simulaciones_conceptos')
                ->where('id_simulacion', $simPrevia->id)
                ->delete();

            $simPrevia->delete();
        }

        // Eliminar simulacion tipo N
        $this->eliminarSimulacionDerivadaTributaria($dto);

        // =========================
        // 3) Preparar data de la nueva simulación
        // =========================
        $vpnLocal = number_format($vpnData, 2, '.', '');
        $tirLocal = round($tirData * 100, 2);

        $porcTasaOportunidad = $this->toFloat(
            $dto['porcentaje_tasa_oportunidad']
            ?? $dto['tasa_oportunidad_pantalla']
            ?? 0
        );

        $data = [
            'id_proyecto'                 => $idProyecto,
            'indicativo_modelo_ccial'     => $modelo,
            'porcentaje_perdida_efic'     => $this->toFloat($dto['porcentaje_perdida_efic'] ?? 0),
            'indicativo_tipo_simulacion'  => ($dto['indicativo_tipo_simulacion'] ?? 'P'),
            'indicativo_beneficio_trib'   => 'N',
            'valor_total_proyecto'        => $this->toFloat($dto['valor_total_proyecto'] ?? 0),
            'porcentaje_IPC'              => $this->toFloat($dto['porcentaje_IPC'] ?? 0),
            'anios_depreciacion'          => (int)($dto['anios_depreciacion'] ?? 0),
            'porcentaje_tasa_oportunidad' => $porcTasaOportunidad,
            'Valor_VPN_proyecto'          => $vpnLocal,
            'porcentaje_TIR_proyecto'     => $tirLocal,
            'anios_PBT'                   => $pbtData,
            'usuario_modificacion_id'     => $uid,
            'usuario_modificacion_nombre' => $unom,
            'usuario_creacion_id'         => $uid,
            'usuario_creacion_nombre'     => $unom,
        ];

        // =========================
        // 4) Crear nueva simulación
        // =========================
        $sim = ProyectoSimulacion::create($data);

        if (!$sim || !$sim->id) {
            throw new \Exception('No se pudo crear la simulación');
        }

        return (int)$sim->id;
    }

    // =========================================================
    // Proceso_Actualizar_Conceptos_año
    // MQ anio(j) <> '0'  => while por años
    // MQ concepto(i) <> ' ' => while por conceptos
    // =========================================================
    private function Proceso_Actualizar_Conceptos_anio(int $simId, array $dto, $uid, $unom, $now): void
    {
        $idProyecto = (int)($dto['id_proyecto'] ?? 0);
        $modelo     = trim((string)($dto['indicativo_modelo_ccial'] ?? ''));

        // EDITAR: limpiar conceptos previos de esa simulación

        if ($dto['accion'] == 'editar' ) {
            DB::table('proyectos_simulaciones_conceptos')
                ->where('id_simulacion', $simId)
                ->delete();
        }

        // Debe venir de Proceso_Conceptos_Calculados_proyectados
        $conceptosPorAnio = $dto['conceptos_por_anio'] ?? null;

        // fallback: si no hay proyectados, guarda solo año actual con dto['conceptos']
        if (!is_array($conceptosPorAnio) || count($conceptosPorAnio) === 0) {
            $anio = (int)date('Y');
            $conceptosPorAnio = [$anio => ($dto['conceptos'] ?? [])];
        }

        // 2) MQ anio(j) <> '0'  (while)
        $anios = array_keys($conceptosPorAnio);
        $j = 0;

        while (isset($anios[$j])) {
            $anioKey = $anios[$j];
            $anio = (int)$anioKey;

            $conceptos = $conceptosPorAnio[$anioKey] ?? [];
            if (!is_array($conceptos)) { $j++; continue; }

            $rows = [];

            $i = 0;
            while (isset($conceptos[$i])) {
                $c = (array)$conceptos[$i];

                $idConceptoProyecto = (int)($c['id_concepto_proyecto'] ?? $c['id_concepto'] ?? 0);
                if ($idConceptoProyecto <= 0) { $i++; continue; }

                // =========================================================
                // FILTRO CLAVE: NO guardar conceptos que no aplican al modelo
                // =========================================================
                if (!$this->conceptoPerteneceAlModelo($idConceptoProyecto, $modelo)) {
                    $i++;
                    continue;
                }

                // porcentaje: nunca null
                $porcentajeRaw = $c['porcentaje'] ?? 0;
                if ($porcentajeRaw === '' || $porcentajeRaw === null) $porcentajeRaw = 0;
                $porcentaje = (float)$porcentajeRaw;

                // valor mensual: nullable (según tu tabla)
                $valorMensualRaw = $c['valor'] ?? null;
                $valorMensual = ($valorMensualRaw === '' || $valorMensualRaw === null) ? null : (float)$valorMensualRaw;

                // valor simulado: nunca null
                $valorSimRaw = $c['valor_simulado'] ?? 0;
                if ($valorSimRaw === '' || $valorSimRaw === null) $valorSimRaw = 0;
                $valorSim = (float)$valorSimRaw;

                $rows[] = [
                    'id_simulacion'               => $simId,
                    'id_proyecto'                 => $idProyecto,
                    'anio'                        => $anio,
                    'secuencia'                   => (int)($c['secuencia'] ?? 0),
                    'id_concepto_proyecto'        => $idConceptoProyecto,
                    'valor_mensual_concepto'      => $valorMensual,
                    'porcentaje'                  => $porcentaje,
                    'valor_concepto_proyecto'     => $valorSim,
                    'usuario_creacion_id'         => $uid,
                    'usuario_creacion_nombre'     => $unom,
                    'usuario_modificacion_id'     => $uid,
                    'usuario_modificacion_nombre' => $unom,
                    'created_at'                  => $now,
                    'updated_at'                  => $now,
                ];

                $i++;
            }

            // insert por año (en lote)
            if (count($rows) > 0) {
                DB::table('proyectos_simulaciones_conceptos')->insert($rows);
            }

            $j++;
        }
    }



    // ======================================================== 
    // CONCEPTOS PARA MODIFICACIÓN
    // ========================================================
        // =========================================================
    // Estructura de calcularSimulacion
    // =========================================================
    public static function Proceso_Arreglo_Conceptos_Modificacion(int $idSimulacion): array
    {
        // ###################################
        // INFO SIMULACIÓN
        // ###################################
        $sim = DB::table('proyectos_simulaciones')
            ->where('id', $idSimulacion)
            ->first();

        if (!$sim) {
            throw new \Exception("No existe la simulación con id {$idSimulacion}.");
        }

        // ###################################
        // INFO PROYECTO
        // ###################################
        $proy = DB::table('proyectos')
            ->where('id', (int) $sim->id_proyecto)
            ->first();

        if (!$proy) {
            throw new \Exception("No existe el proyecto con id {$sim->id_proyecto} asociado a la simulación.");
        }

        // ###################################
        // INFO TIPO DE PROYECTO
        // ###################################
        $idTipoProyecto = (int) ($proy->id_tipo_proyecto ?? 0);
        if ($idTipoProyecto <= 0) {
            throw new \Exception("El proyecto no tiene id_tipo_proyecto válido.");
        }

        // ###################################
        // DEFINIR AÑO UNO
        // ###################################
        $schema = DB::getSchemaBuilder();
        $pscHasAnio = $schema->hasColumn('proyectos_simulaciones_conceptos', 'anio');

        $anio1 = null;
        if ($pscHasAnio) {
            $anio1 = DB::table('proyectos_simulaciones_conceptos')
                ->where('id_simulacion', $idSimulacion)
                ->min('anio');

            if (!$anio1) {
                $anio1 = null;
            }
        }

        // ###################################
        // TRAER SOLO LOS CONCEPTOS EXISTENTES 
        // ###################################
        $q = DB::table('proyectos_simulaciones_conceptos as psc')
            ->join('conceptos_proyectos as cp', 'cp.id', '=', 'psc.id_concepto_proyecto')
            ->select(
                // ---- metadata cp ----
                'cp.id as id_concepto_proyecto',
                'cp.secuencia',
                'cp.nombre',
                'cp.indicativo_tipo_linea',
                'cp.indicativo_tipo_concepto',
                'cp.indicativo_permite_copia',
                'cp.indicativo_tipo_valor',
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
                'psc.porcentaje as porcentaje_guardado',
                'psc.valor_mensual_concepto as valor_guardado',
                'psc.valor_concepto_proyecto as valor_simulado_guardado'
            )
            ->where('psc.id_simulacion', $idSimulacion)
            ->where('cp.id_tipo_proyecto', $idTipoProyecto);

        if (!is_null($anio1)) {
            $q->where('psc.anio', $anio1);
        }

        // Importante: orden por secuencia del concepto
        $rows = $q->orderBy('cp.secuencia', 'asc')->get();

        // ###################################
        // ARMAR ARREGLO FINAL
        // ###################################
        $conceptos = [];
        foreach ($rows as $r) {
            $esEditable = ($r->indicativo_concepto_editable ?? 'N') === 'S';
            $esDetalle  = ($r->indicativo_tipo_linea ?? '') === 'D';

            $porcentaje    = self::nullIfZero($r->porcentaje_guardado);
            $valor         = self::nullIfZero($r->valor_guardado, 2);
            $valorSimulado = self::nullIfZero($r->valor_simulado_guardado);

            $conceptos[] = [
                'id_concepto_proyecto' => (int) $r->id_concepto_proyecto,
                'secuencia'            => (int) ($r->secuencia ?? 0),
                'concepto'             => (string) ($r->nombre ?? ''),
                'indicativo_tipo_linea' => (string) ($r->indicativo_tipo_linea ?? ''),
                'indicativo_tipo_valor' => (string) ($r->indicativo_tipo_valor ?? ''),
                'indicativo_tipo_concepto' => (string) ($r->indicativo_tipo_concepto ?? ''),
                'indicativo_permite_copia' => (string) ($r->indicativo_permite_copia ?? ''),
                'porcentaje'           => $porcentaje,
                'parametro_referencia' => (string) ($r->parametro_referencia ?? ''),
                'indicativo_valor_base' => (string) ($r->indicativo_valor_base ?? ''),
                'indicativo_operador'  => (string) ($r->indicativo_operador ?? ''),
                'porcentaje_proy'            => $r->porcentaje_proy,
                'parametro_referencia_proy'  => (string) ($r->parametro_referencia_proy ?? ''),
                'indicativo_valor_base_proy' => (string) ($r->indicativo_valor_base_proy ?? ''),
                'indicativo_operador_proy'   => (string) ($r->indicativo_operador_proy ?? ''),
                'numero_anio_inicial_proy'   => (string) ($r->numero_anio_inicial_proy ?? ''),
                'numero_anios_proy'   => (string) ($r->numero_anios_proy ?? ''),
                'indicativo_concepto_calculado' => (string) ($r->indicativo_concepto_calculado ?? ''),
                'indentado'            => (bool) $esDetalle,
                'es_editable'          => (bool) $esEditable,
                'valor'                => $valor,          
                'valor_simulado'       => $valorSimulado,
            ];
        }

        // ##########################################
        // RESPUESTA PARA EL FRONT
        // ##########################################
        return [
            'id_simulacion'                => $idSimulacion,
            'id_proyecto'                  => (int) $sim->id_proyecto,
            'indicativo_modelo_ccial'      => (string) ($sim->indicativo_modelo_ccial ?? ''),
            'indicativo_tipo_simulacion'   => (string) ($sim->indicativo_tipo_simulacion ?? ''),
            'indicativo_beneficio_trib'    => (string) ($sim->indicativo_beneficio_trib ?? ''),
            'porcentaje_perdida_efic'      => $sim->porcentaje_perdida_efic,
            'porcentaje_IPC'               => $sim->porcentaje_IPC,
            'anios_depreciacion'           => $sim->anios_depreciacion,
            'porcentaje_tasa_oportunidad'  => $sim->porcentaje_tasa_oportunidad,
            'Valor_VPN_proyecto'           => $sim->Valor_VPN_proyecto ?? null,
            'porcentaje_TIR_proyecto'      => $sim->porcentaje_TIR_proyecto ?? null,
            'anios_PBT'                    => $sim->anios_PBT ?? null,
            'anio_1'                       => $anio1,
            'conceptos'                    => $conceptos, 
        ];
    }


    private function eliminarSimulacionDerivadaTributaria(array $dto): void
    {
        $idProyecto = (int)($dto['id_proyecto'] ?? 0);
        $modelo = trim((string)($dto['indicativo_modelo_ccial'] ?? ''));

        if ($idProyecto <= 0 || $modelo === '') {
            return;
        }

        $simulaciones = ProyectoSimulacion::query()
            ->where('id_proyecto', $idProyecto)
            ->where('indicativo_modelo_ccial', $modelo)
            ->where('indicativo_beneficio_trib', 'N')
            ->get();

        foreach ($simulaciones as $sim) {
            DB::table('proyectos_simulaciones_conceptos')
                ->where('id_simulacion', $sim->id)
                ->delete();

            $sim->delete();
        }
    }



    // =========================================================
    // Consultas
    // =========================================================
    private function getParametroConstanteFloat(string $codigo): float
    {
        if (array_key_exists($codigo, $this->paramCache)) {
            return (float)$this->paramCache[$codigo];
        }

        $val = DB::table('parametros_constantes')
            ->where('codigo_parametro', $codigo)
            ->value('valor_parametro');

        $num = $this->toFloat($val ?? 0);
        $this->paramCache[$codigo] = $num;
        return $num;
    }

    private function getGeneracionAnualProyecto(int $idProyecto): float
    {
        if ($idProyecto <= 0) return 0.0;

        $val = DB::table('proyectos')
            ->where('id', $idProyecto)
            ->value('generacion_anual');

        return $this->toFloat($val ?? 0);
    }

    private function getPrecioProyectado( int $anio, string $campo): float
    {

        $val = DB::table('precios_proyectados')
            ->where('anio', $anio)
            ->value($campo);

        return $this->toFloat($val ?? 0);
    }

    private function getValorMensualConcepto(array $c): float
    {
        return $this->toFloat($c['valor'] ?? 0);
    }

        // =========================================================
    // VPN (incluye t0)
    // =========================================================
    private static function vpn(float $tasa, array $flujos): float {
        $valorPresente = 0.0;

        foreach ($flujos as $periodo => $flujo) {
            $valorPresente += $flujo / pow(1 + $tasa, $periodo);
        }

        return $valorPresente;
    }

    // =========================================================
    // TIR (Newton + fallback binario) - tal cual tu código
    // =========================================================
    private static function tir(array $flujos, float $suposicionInicial = 0.1): float {
        $tolerancia = 1e-7;
        $maxIteraciones = 100;

        // Función del VPN en función de la tasa
        $funcionVPN = function(float $tasa) use ($flujos): float {
            $resultado = 0.0;
            foreach ($flujos as $periodo => $flujo) {
                $resultado += $flujo / pow(1 + $tasa, $periodo);
            }
            return $resultado;
        };

        // Derivada del VPN (para método Newton-Raphson)
        $derivadaVPN = function(float $tasa) use ($flujos): float {
            $resultado = 0.0;
            foreach ($flujos as $periodo => $flujo) {
                if ($periodo == 0) continue;
                $resultado += -$periodo * $flujo / pow(1 + $tasa, $periodo + 1);
            }
            return $resultado;
        };

        // Iteración de Newton-Raphson
        $tasa = $suposicionInicial;
        for ($i = 0; $i < $maxIteraciones; $i++) {
            $valorFunc = $funcionVPN($tasa);
            if (abs($valorFunc) < $tolerancia) return $tasa;

            $valorDerivada = $derivadaVPN($tasa);
            if (abs($valorDerivada) < 1e-14) break;

            $tasaSiguiente = $tasa - $valorFunc / $valorDerivada;
            if (abs($tasaSiguiente - $tasa) < $tolerancia) return $tasaSiguiente;

            $tasa = $tasaSiguiente;
        }

        // Si Newton no converge, usa búsqueda binaria
        $bajo = -0.999999;
        $alto = 10.0;
        $valorBajo = $funcionVPN($bajo);
        $valorAlto = $funcionVPN($alto);

        while ($valorBajo * $valorAlto > 0 && $alto < 1e6) {
            $alto *= 1.5;
            $valorAlto = $funcionVPN($alto);
        }

        for ($i = 0; $i < 200; $i++) {
            $medio = ($bajo + $alto) / 2.0;
            $valorMedio = $funcionVPN($medio);

            if (abs($valorMedio) < $tolerancia) return $medio;

            if ($valorBajo * $valorMedio <= 0) {
                $alto = $medio;
                $valorAlto = $valorMedio;
            } else {
                $bajo = $medio;
                $valorBajo = $valorMedio;
            }
        }

        return ($bajo + $alto) / 2.0;
    }


    // =========================================================
    // PROCESO: calcular TIR
    // - Valorproyecto = dto.valor_total_proyecto
    // - Flujos = caja libre por cada año proyectado
    // =========================================================
    private function Proceso_calcular_TIR_VPN(array $conceptosPorAnio, array $dto): array
    {
        // =============================================
        // FLUJOS: t0 + caja libre por año (POR ID)
        // =============================================
        $conceptosPorAnio = $dto['conceptos_por_anio'] ?? [];
        if (!is_array($conceptosPorAnio)) {
            $conceptosPorAnio = [];
        }

        $flujos = [];

        // t0 inversión inicial negativa
        $flujos[] = -1.0 * (float)($dto['valor_total_proyecto'] ?? 0);

        // ordenar años
        $anios = array_keys($conceptosPorAnio);
        sort($anios, SORT_NUMERIC);

        // recorrer años
        foreach ($anios as $anio) {

            $cajaLibre = 0.0;

            $lista = $conceptosPorAnio[$anio] ?? [];
            if (!is_array($lista)) $lista = [];

            foreach ($lista as $c) {

                // SOLO por id_concepto_proyecto
                $idp = (int)($c['id_concepto_proyecto'] ?? 0);

                if ($idp === (int)$this->parametro_id_concepto_caja_libre) {
                    $cajaLibre = (float)($c['valor_simulado'] ?? 0);
                    break;
                }
            }

            $flujos[] = $cajaLibre;
        }

        $tasa = ((float)($dto['porcentaje_tasa_oportunidad'] ?? 0)) / 100.0;
        $vpn = $this->vpn($tasa, $flujos);
        $tir = $this->tir($flujos);

        return [
            'flujos' => $flujos,
            'tir'    => $tir, 
            'vpn' => $vpn,
        ];
    }


    private function Proceso_calcular_aniosPBT(array $dto): float
    {
        $idCajaLibreAcum = (int)($this->parametro_id_concepto_caja_libre_acum ?? 0);
        $idCajaLibre     = (int)($this->parametro_id_concepto_caja_libre ?? 0);

        $conceptosPorAnio = $dto['conceptos_por_anio'] ?? [];
        if (!is_array($conceptosPorAnio) || $idCajaLibreAcum <= 0 || $idCajaLibre <= 0) {
            return 0.0;
        }

        $anios = array_keys($conceptosPorAnio);
        sort($anios, SORT_NUMERIC);

        $J = 1;
        $nro_aniosPBT = 0.0;
        $fraccion_anioPBT = 0.0;
        $Valor_caja_libre_aux = 0.0;
        $Fin_calculo_aniosPBT = false;

        $parametro_numero_anios_simulacion = (int)($this->parametro_numero_anios_simulacion ?? 0);
        if ($parametro_numero_anios_simulacion <= 0) {
            $parametro_numero_anios_simulacion = count($anios);
        }

        while ($J < $parametro_numero_anios_simulacion && $Fin_calculo_aniosPBT === false) {

            $idxAnio = $J - 1;
            $anioKey = $anios[$idxAnio] ?? null;
            if ($anioKey === null) break;

            $lista = $conceptosPorAnio[$anioKey] ?? [];
            if (!is_array($lista)) $lista = [];

            $Valor_caja_libre_acum = 0.0;
            $Valor_caja_libre = 0.0;

            foreach ($lista as $c) {
                $idp = (int)($c['id_concepto_proyecto'] ?? 0);

                if ($idp === $idCajaLibreAcum) {
                    $Valor_caja_libre_acum = (float)($c['valor_simulado'] ?? 0);
                }

                if ($idp === $idCajaLibre) {
                    $Valor_caja_libre = (float)($c['valor_simulado'] ?? 0);
                }
            }

            if ($Valor_caja_libre_acum <= 0) {

                $nro_aniosPBT = $nro_aniosPBT + 1;
                $Valor_caja_libre_aux = $Valor_caja_libre_acum;

            } else {

                $fraccion_anioPBT = ($Valor_caja_libre == 0.0)
                    ? 0.0
                    : (abs($Valor_caja_libre_aux) / $Valor_caja_libre);

                $nro_aniosPBT = $nro_aniosPBT + $fraccion_anioPBT;
                $Fin_calculo_aniosPBT = true;
            }

            $J = $J + 1;
        }


        return round($nro_aniosPBT, 2);
    }
    
    // =========================================================
    // Parse numérico robusto (mismo criterio “front”)
    // =========================================================
    private function toFloat($v): float
    {
        if ($v === null) return 0.0;

        if (is_int($v) || is_float($v)) {
            return (float)$v;
        }

        $s = trim((string)$v);
        if ($s === '') return 0.0;

        // Limpieza básica
        $s = str_replace(["\u{00A0}", ' ', '$'], '', $s);

        // (1.234,56) => -1.234,56
        if (preg_match('/^\((.*)\)$/', $s, $m)) {
            $s = '-' . $m[1];
        }

        $hasDot = strpos($s, '.') !== false;
        $hasComma = strpos($s, ',') !== false;

        if ($hasDot && $hasComma) {
            // 1.234.567,89 => 1234567.89
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif ($hasComma && !$hasDot) {
            // 1234,56 => 1234.56
            $s = str_replace(',', '.', $s);
        } elseif ($hasDot && !$hasComma) {
            // Puede ser miles (100.000) o decimal (100.25)
            // Regla: si hay varios puntos -> miles. Si hay 1 punto y 3 dígitos a la derecha -> miles.
            $parts = explode('.', $s);
            if (count($parts) > 2) {
                // 1.234.567 => miles
                $s = str_replace('.', '', $s);
            } else {
                // 100.000 (miles) vs 100.25 (decimal)
                $right = $parts[1] ?? '';
                if (strlen($right) === 3) {
                    $s = str_replace('.', '', $s);
                }
                // si no, lo dejamos como decimal
            }
        }

        // Dejar solo dígitos, punto decimal y signo
        $s = preg_replace('/[^0-9\.\-]/', '', $s);

        // Evitar "-" suelto o "."
        if ($s === '' || $s === '-' || $s === '.' || $s === '-.') return 0.0;

        return (float)$s;
    }

    private static function nullIfZero($value, int $round = null)
    {
        if (is_null($value)) return null;

        $num = (float) $value;

        if ($num == 0.0) return null;

        return is_null($round) ? $num : round($num, $round);
    }

    // =========================================================
    // Helper: excluir conceptos que NO aplican al modelo
    // =========================================================
    private function conceptoPerteneceAlModelo(int $idConceptoProyecto, string $modelo): bool
    {
        $modelo = strtoupper(trim($modelo));

        // Conceptos exclusivos de CE (Comunidad Energética)
        $soloCE = [
            (int)$this->parametro_id_concepto_kwh_comunidad,
            (int)$this->parametro_id_concepto_precio_comunidad,
            (int)$this->parametro_id_concepto_venta_com,
        ];

        // Conceptos exclusivos de BO (Bolsa)
        $soloBO = [
            (int)$this->parametro_id_concepto_kwh_bolsa,
            (int)$this->parametro_id_concepto_precio_bolsa,
            (int)$this->parametro_id_concepto_venta_bolsa,
        ];

        if ($modelo === 'CE') {
            // En CE NO guardes bolsa
            return !in_array($idConceptoProyecto, $soloBO, true);
        }

        if ($modelo === 'BO') {
            // En BO NO guardes comunidad
            return !in_array($idConceptoProyecto, $soloCE, true);
        }

        // Si llega un modelo raro, por seguridad guarda todo
        return true;
    }

    private function normalizarNumero($valor): float
    {
        if ($valor === null || $valor === '') {
            return 0;
        }

        if (is_numeric($valor)) {
            return (float) $valor;
        }

        $valor = (string) $valor;
        $valor = str_replace(['$', ' '], '', $valor);
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);

        return is_numeric($valor) ? (float) $valor : 0;
    }

    private function obtenerParametroConstante(string $codigo)
    {
        return DB::table('parametros_constantes')
            ->where('codigo_parametro', $codigo)
            ->value('valor_parametro');
    }

    private function guardarAuditoriaSimulacion(
        ProyectoSimulacion $simulacion,
        string $accion,
        ?string $recursoOriginal = null,
        ?string $recursoResultante = null,
        ?string $descripcionExtra = null
    ): void {
        $descripcion = $descripcionExtra
            ?: 'Proyecto-' . $simulacion->id_proyecto
                . '-Modelo-' . $simulacion->indicativo_modelo_ccial
                . '-Tipo-' . $simulacion->indicativo_tipo_simulacion;

        if ($accion === AccionAuditoriaEnum::CREAR) {
            $recursoOriginal = $recursoOriginal ?? $simulacion->toJson();
            $recursoResultante = '{}';
        }

        if ($accion === AccionAuditoriaEnum::MODIFICAR) {
            $recursoOriginal = $recursoOriginal ?? '{}';
            $recursoResultante = $recursoResultante ?? $simulacion->toJson();
        }

        $auditoriaDto = [
            'id_recurso'          => $simulacion->id,
            'nombre_recurso'      => ProyectoSimulacion::class,
            'descripcion_recurso' => $descripcion,
            'accion'              => $accion,
            'recurso_original'    => $recursoOriginal,
            'recurso_resultante'  => $recursoResultante,
        ];

        AuditoriaTabla::crear($auditoriaDto);
    }


    
}
