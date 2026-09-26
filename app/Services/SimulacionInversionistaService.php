<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use App\Models\Parametrizacion\ProyectoSimulacion;
use App\Enum\AccionAuditoriaEnum;
use App\Models\Seguridad\AuditoriaTabla;

class SimulacionInversionistaService
{
    public function initSimulacionInversionista(array $dto): array
    {
        $accion = $dto['accion'];
        $idSimulacionOrigen = null;
        $idSimulacion = null;
        if($accion == 'crear' ){
            $idSimulacionOrigen =  (int)($dto['id_simulacion'] ?? 0);
        }else{
            $idSimulacionOrigen = (int)($dto['id'] ?? 0);
        }
        
        $idSimulacion = (int)($dto['id_simulacion'] ?? 0);
        $porcentaje = (float)($dto['porcentaje_part_inversionista'] ?? 100);

        $sim = DB::table('proyectos_simulaciones as ps')
            ->join('proyectos as p', 'p.id', '=', 'ps.id_proyecto')
            ->select(
                'ps.id',
                'ps.id_proyecto',
                'p.valor_total_proyecto',
                'ps.indicativo_modelo_ccial',
                'ps.indicativo_tipo_simulacion',
                'ps.nombre_inversionista',
                'ps.telefono_inversionista',
                'ps.email_inversionista',
                'ps.porcentaje_part_inversionista',
                'ps.valor_part_inversionista',
                'ps.Valor_VPN_proyecto',
                'ps.porcentaje_TIR_proyecto',
                'ps.anios_PBT',
                'ps.updated_at'
            )
            ->where('ps.id',  $idSimulacionOrigen )
            ->first();

        if (!$sim) {
            throw new Exception("No existe la simulación con id {$idSimulacion}.");
        }



        // if ((string)$sim->indicativo_tipo_simulacion !== 'P') {
        //     throw new Exception("Solo se puede tomar como base una simulación tipo proyecto.");
        // }

        $anio1 = DB::table('proyectos_simulaciones_conceptos')
            ->where('id_simulacion', $idSimulacion)
            ->min('anio');

        if (!$anio1) {
            throw new Exception("La simulación no tiene conceptos asociados.");
        }

        $conceptos = DB::table('proyectos_simulaciones_conceptos as psc')
            ->join('conceptos_proyectos as cp', 'cp.id', '=', 'psc.id_concepto_proyecto')
            ->select(
                'psc.id',
                'psc.id_simulacion',
                'psc.id_proyecto',
                'psc.anio',
                'psc.secuencia',
                'psc.id_concepto_proyecto',
                'psc.valor_mensual_concepto',
                'psc.porcentaje',
                'psc.valor_concepto_proyecto',
                'cp.nombre as concepto',
                'cp.indicativo_tipo_linea',
                'cp.indicativo_tipo_concepto',
                'cp.indicativo_tipo_valor',
                'cp.indicativo_concepto_editable'
            )
            ->where('psc.id_simulacion', $idSimulacion)
            ->where('psc.anio', $anio1)
            ->orderBy('psc.secuencia', 'asc')
            ->get();

        $detalle = [];

        foreach ($conceptos as $c) {
            $valorOriginal = (float)($c->valor_concepto_proyecto ?? 0);
            $valorCalculado = $valorOriginal;

            if (in_array((string)$c->indicativo_tipo_valor, ['C', 'V'], true)) {
                $valorCalculado = ($valorOriginal * $porcentaje) / 100;
            }

            $detalle[] = [
                'id_concepto_proyecto' => (int)$c->id_concepto_proyecto,
                'anio' => (int)$c->anio,
                'secuencia' => (int)$c->secuencia,
                'concepto' => (string)$c->concepto,
                'indicativo_tipo_linea' => (string)$c->indicativo_tipo_linea,
                'indicativo_tipo_concepto' => (string)$c->indicativo_tipo_concepto,
                'indicativo_tipo_valor' => (string)$c->indicativo_tipo_valor,
                'es_editable' => ((string)$c->indicativo_concepto_editable === 'S'),
                'valor_original' => $valorOriginal,
                'valor_simulado' => $valorCalculado,
                'valor_mensual_concepto' => $c->valor_mensual_concepto !== null ? (float)$c->valor_mensual_concepto : null,
                'porcentaje' => $c->porcentaje !== null ? (float)$c->porcentaje : null,
            ];
        }

        $porcentajeInit = $accion == 'editar' ? $sim->porcentaje_part_inversionista : $porcentaje;

        return [
            'id_simulacion_origen' => (int)$sim->id,
            'id_proyecto' => (int)$sim->id_proyecto,
            'indicativo_modelo_ccial' => (string)$sim->indicativo_modelo_ccial,
            'indicativo_tipo_simulacion' => 'I',
            'anio_1' => (int)$anio1,
            'porcentaje_part_inversionista' => (float)$porcentajeInit,
            'nombre_inversionista' => $sim->nombre_inversionista,
            'telefono_inversionista' => $sim->telefono_inversionista,
            'email_inversionista' => $sim->email_inversionista,
            'valor_total_proyecto' => (float)($sim->valor_total_proyecto ?? 0),
            'valor_part_inversionista' => ((float)($sim->valor_total_proyecto ?? 0) * $porcentajeInit) / 100,
            'Valor_VPN_proyecto' => $sim->Valor_VPN_proyecto,
            'porcentaje_TIR_proyecto' => $sim->porcentaje_TIR_proyecto,
            'anios_PBT' => $sim->anios_PBT,
            'updated_at' => $sim->updated_at,
            'conceptos' => $detalle,
        ];
    }

    public function boton_guardar(array $dto): array
    {
        $resp = $this->guardarSimulacionInversionista($dto);
        $this->guardarSimulacionInversionistaSinBeneficioTributario($dto);

        // GUARDAR AUDITORIA DE CREACIÓN/MODIFICACIÓN


        return $resp ;
    }


    private function guardarSimulacionInversionista(array $dto): array
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $usuario = $user?->usuario();

        $uid  = $usuario->id ?? null;
        $unom = $usuario->nombre ?? null;
        $now  = now();

        return DB::transaction(function () use ($dto, $uid, $unom, $now) {

            $accion = (string)($dto['accion'] ?? 'crear');

            $idSimOrigen = (int)($dto['id_simulacion_origen'] ?? 0);
            $idSimulacion = (int)($dto['id_simulacion'] ?? 0);
            $porcentaje = (float)($dto['porcentaje_part_inversionista'] ?? 0);

            $simOrigen = DB::table('proyectos_simulaciones as ps')
                ->join('proyectos as p', 'p.id', '=', 'ps.id_proyecto')
                ->select(
                    'ps.id',
                    'ps.id_proyecto',
                    'p.valor_total_proyecto',
                    'ps.indicativo_modelo_ccial',
                    'ps.indicativo_tipo_simulacion',
                    'ps.indicativo_beneficio_trib',
                    'ps.porcentaje_perdida_efic',
                    'ps.porcentaje_IPC',
                    'ps.anios_depreciacion',
                    'ps.porcentaje_tasa_oportunidad',
                    'ps.nombre_inversionista',
                    'ps.telefono_inversionista',
                    'ps.email_inversionista',
                    'ps.valor_part_inversionista',
                    'ps.Valor_VPN_proyecto',
                    'ps.porcentaje_TIR_proyecto',
                    'ps.anios_PBT',
                    'ps.updated_at'
                )
                ->where('ps.id', $idSimOrigen)
                ->first();

            if (!$simOrigen) {
                throw new Exception("No existe la simulación origen.");
            }

            if ((string)$simOrigen->indicativo_tipo_simulacion !== 'P') {
                throw new Exception("La simulación origen debe ser tipo P.");
            }

            $valorPartInversionista = ((float)$simOrigen->valor_total_proyecto * $porcentaje) / 100;

            $conceptosOrigen = DB::table('proyectos_simulaciones_conceptos as psc')
                ->join('conceptos_proyectos as cp', 'cp.id', '=', 'psc.id_concepto_proyecto')
                ->select(
                    'psc.*',
                    'cp.indicativo_tipo_valor'
                )
                ->where('psc.id_simulacion', $idSimOrigen)
                ->orderBy('psc.anio', 'asc')
                ->orderBy('psc.secuencia', 'asc')
                ->get();

            if ($conceptosOrigen->isEmpty()) {
                throw new Exception("La simulación origen no tiene conceptos.");
            }

            if ($accion === 'editar') {
                if (!$idSimulacion) {
                    throw new Exception("Para editar debe enviar id_simulacion.");
                }

                $simExistente = ProyectoSimulacion::find($idSimulacion);

                if (!$simExistente) {
                    throw new Exception("No existe la simulación inversionista a editar.");
                }

                // ESTADO ORIGINAL ANTES DEL UPDATE
                $original = $simExistente->toJson();

                $simExistente->update([
                    'id_proyecto' => $simOrigen->id_proyecto,
                    'indicativo_modelo_ccial' => $simOrigen->indicativo_modelo_ccial,
                    'indicativo_tipo_simulacion' => 'I',
                    'indicativo_beneficio_trib' => $simOrigen->indicativo_beneficio_trib,
                    'porcentaje_perdida_efic' => $simOrigen->porcentaje_perdida_efic,
                    'porcentaje_IPC' => $simOrigen->porcentaje_IPC,
                    'anios_depreciacion' => $simOrigen->anios_depreciacion,
                    'porcentaje_tasa_oportunidad' => $simOrigen->porcentaje_tasa_oportunidad,
                    'Valor_VPN_proyecto' => $simOrigen->Valor_VPN_proyecto,
                    'porcentaje_TIR_proyecto' => $simOrigen->porcentaje_TIR_proyecto,
                    'anios_PBT' => $simOrigen->anios_PBT,
                    'nombre_inversionista' => $dto['nombre_inversionista'] ?? null,
                    'telefono_inversionista' => $dto['telefono_inversionista'] ?? null,
                    'email_inversionista' => $dto['email_inversionista'] ?? null,
                    'porcentaje_part_inversionista' => $porcentaje,
                    'valor_part_inversionista' => $valorPartInversionista,
                    'usuario_modificacion_id' => $uid,
                    'usuario_modificacion_nombre' => $unom,
                    'updated_at' => $now,
                ]);

                DB::table('proyectos_simulaciones_conceptos')
                    ->where('id_simulacion', $idSimulacion)
                    ->delete();

                $rows = [];

                foreach ($conceptosOrigen as $c) {
                    $valor = (float)($c->valor_concepto_proyecto ?? 0);

                    if (in_array((string)$c->indicativo_tipo_valor, ['C', 'V'], true)) {
                        $valor = ($valor * $porcentaje) / 100;
                    }

                    $rows[] = [
                        'id_simulacion' => $idSimulacion,
                        'id_proyecto' => $c->id_proyecto,
                        'anio' => $c->anio,
                        'secuencia' => $c->secuencia,
                        'id_concepto_proyecto' => $c->id_concepto_proyecto,
                        'valor_mensual_concepto' => $c->valor_mensual_concepto,
                        'porcentaje' => $c->porcentaje,
                        'valor_concepto_proyecto' => $valor,
                        'usuario_creacion_id' => $c->usuario_creacion_id ?? $uid,
                        'usuario_creacion_nombre' => $c->usuario_creacion_nombre ?? $unom,
                        'usuario_modificacion_id' => $uid,
                        'usuario_modificacion_nombre' => $unom,
                        'created_at' => $c->created_at ?? $now,
                        'updated_at' => $now,
                    ];
                }

                if (!empty($rows)) {
                    DB::table('proyectos_simulaciones_conceptos')->insert($rows);
                }

                $simExistente->refresh();

                $this->guardarAuditoriaSimulacion(
                    $simExistente,
                    AccionAuditoriaEnum::MODIFICAR,
                    $original,
                    $simExistente->toJson(),
                    'Simulación inversionista - Proyecto-' . $simExistente->id_proyecto
                        . '-Modelo-' . $simExistente->indicativo_modelo_ccial
                );

                return ProyectoSimulacion::cargar($idSimulacion);
            }

            $simulacionExistenteI = DB::table('proyectos_simulaciones')
                ->where('id_proyecto', $simOrigen->id_proyecto)
                ->where('indicativo_modelo_ccial', $simOrigen->indicativo_modelo_ccial)
                ->where('indicativo_tipo_simulacion', 'I')
                ->first();

            if ($simulacionExistenteI) {
                DB::table('proyectos_simulaciones_conceptos')
                    ->where('id_simulacion', $simulacionExistenteI->id)
                    ->delete();

                DB::table('proyectos_simulaciones')
                    ->where('id', $simulacionExistenteI->id)
                    ->delete();
            }

            $nueva = ProyectoSimulacion::create([
                'id_proyecto' => $simOrigen->id_proyecto,
                'indicativo_modelo_ccial' => $simOrigen->indicativo_modelo_ccial,
                'indicativo_tipo_simulacion' => 'I',
                'indicativo_beneficio_trib' => 'S',
                'porcentaje_perdida_efic' => $simOrigen->porcentaje_perdida_efic,
                'porcentaje_IPC' => $simOrigen->porcentaje_IPC,
                'anios_depreciacion' => $simOrigen->anios_depreciacion,
                'porcentaje_tasa_oportunidad' => $simOrigen->porcentaje_tasa_oportunidad,
                'Valor_VPN_proyecto' => $simOrigen->Valor_VPN_proyecto,
                'porcentaje_TIR_proyecto' => $simOrigen->porcentaje_TIR_proyecto,
                'anios_PBT' => $simOrigen->anios_PBT,
                'nombre_inversionista' => $dto['nombre_inversionista'] ?? null,
                'telefono_inversionista' => $dto['telefono_inversionista'] ?? null,
                'email_inversionista' => $dto['email_inversionista'] ?? null,
                'porcentaje_part_inversionista' => $porcentaje,
                'valor_part_inversionista' => $valorPartInversionista,
                'usuario_creacion_id' => $uid,
                'usuario_creacion_nombre' => $unom,
                'usuario_modificacion_id' => $uid,
                'usuario_modificacion_nombre' => $unom,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $rows = [];

            foreach ($conceptosOrigen as $c) {
                $valor = (float)($c->valor_concepto_proyecto ?? 0);

                if (in_array((string)$c->indicativo_tipo_valor, ['C', 'V'], true)) {
                    $valor = ($valor * $porcentaje) / 100;
                }

                $rows[] = [
                    'id_simulacion' => $nueva->id,
                    'id_proyecto' => $c->id_proyecto,
                    'anio' => $c->anio,
                    'secuencia' => $c->secuencia,
                    'id_concepto_proyecto' => $c->id_concepto_proyecto,
                    'valor_mensual_concepto' => $c->valor_mensual_concepto,
                    'porcentaje' => $c->porcentaje,
                    'valor_concepto_proyecto' => $valor,
                    'usuario_creacion_id' => $uid,
                    'usuario_creacion_nombre' => $unom,
                    'usuario_modificacion_id' => $uid,
                    'usuario_modificacion_nombre' => $unom,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($rows)) {
                DB::table('proyectos_simulaciones_conceptos')->insert($rows);
            }

            $this->guardarAuditoriaSimulacion(
                $nueva,
                AccionAuditoriaEnum::CREAR,
                null,
                $nueva->toJson(),
                'Simulación inversionista - Proyecto-' . $nueva->id_proyecto
                    . '-Modelo-' . $nueva->indicativo_modelo_ccial
            );

            return ProyectoSimulacion::cargar((int)$nueva->id);
        });
    }


    private function guardarSimulacionInversionistaSinBeneficioTributario(array $dto): array
    {
        $user = \Illuminate\Support\Facades\Auth::user();
        $usuario = $user?->usuario();

        $uid  = $usuario->id ?? null;
        $unom = $usuario->nombre ?? null;
        $now  = now();

        return DB::transaction(function () use ($dto, $uid, $unom, $now) {

            $accion = (string)($dto['accion'] ?? 'crear');

            // Simulación base (normalmente tipo P) desde donde se copian los conceptos
            $idSimOrigen = (int)($dto['id_simulacion_origen'] ?? 0);

            // Simulación inversionista existente que se actualiza cuando es editar
            $idSimulacion = (int)($dto['id_simulacion'] ?? 0);

            $porcentaje = (float)($dto['porcentaje_part_inversionista'] ?? 0);

            $simOrigen = DB::table('proyectos_simulaciones as ps')
                ->join('proyectos as p', 'p.id', '=', 'ps.id_proyecto')
                ->select(
                    'ps.id',
                    'ps.id_proyecto',
                    'p.valor_total_proyecto',
                    'ps.indicativo_modelo_ccial',
                    'ps.indicativo_tipo_simulacion',
                    'ps.indicativo_beneficio_trib',
                    'ps.porcentaje_perdida_efic',
                    'ps.porcentaje_IPC',
                    'ps.anios_depreciacion',
                    'ps.porcentaje_tasa_oportunidad',
                    'ps.nombre_inversionista',
                    'ps.telefono_inversionista',
                    'ps.email_inversionista',
                    'ps.valor_part_inversionista',
                    'ps.Valor_VPN_proyecto',
                    'ps.porcentaje_TIR_proyecto',
                    'ps.anios_PBT',
                    'ps.updated_at'
                )
                ->where('ps.id_proyecto', $dto['id_proyecto'])
                ->where('ps.indicativo_modelo_ccial', $dto['indicativo_modelo_ccial'])
                ->where('ps.indicativo_tipo_simulacion', 'P')
                ->where('ps.indicativo_beneficio_trib', 'N')
                ->first();

            if (!$simOrigen) {
                throw new Exception("No existe la simulación origen.");
            }

            if ((string)$simOrigen->indicativo_tipo_simulacion !== 'P') {
                throw new Exception("La simulación origen debe ser tipo P.");
            }

            $valorPartInversionista = ((float)$simOrigen->valor_total_proyecto * $porcentaje) / 100;

            // Traer TODOS los conceptos del origen, TODOS los años
            $conceptosOrigen = DB::table('proyectos_simulaciones_conceptos as psc')
                ->join('conceptos_proyectos as cp', 'cp.id', '=', 'psc.id_concepto_proyecto')
                ->select(
                    'psc.*',
                    'cp.indicativo_tipo_valor'
                )
                ->where('psc.id_simulacion', $simOrigen->id)
                ->orderBy('psc.anio', 'asc')
                ->orderBy('psc.secuencia', 'asc')
                ->get();

            if ($conceptosOrigen->isEmpty()) {
                throw new Exception("La simulación origen no tiene conceptos.");
            }

            // =========================
            // CREAR
            // =========================

            // Si quieres mantener la lógica actual de una sola simulación I por proyecto/modelo
            $simulacionExistenteI = DB::table('proyectos_simulaciones')
                ->where('id_proyecto', $simOrigen->id_proyecto)
                ->where('indicativo_modelo_ccial', $simOrigen->indicativo_modelo_ccial)
                ->where('indicativo_beneficio_trib', $simOrigen->indicativo_beneficio_trib)
                ->where('indicativo_tipo_simulacion', 'I')
                ->first();

            if ($simulacionExistenteI) {
                DB::table('proyectos_simulaciones_conceptos')
                    ->where('id_simulacion', $simulacionExistenteI->id)
                    ->delete();

                DB::table('proyectos_simulaciones')
                    ->where('id', $simulacionExistenteI->id)
                    ->delete();
            }

            $nueva = ProyectoSimulacion::create([
                'id_proyecto' => $simOrigen->id_proyecto,
                'indicativo_modelo_ccial' => $simOrigen->indicativo_modelo_ccial,
                'indicativo_tipo_simulacion' => 'I',
                'indicativo_beneficio_trib' => $simOrigen->indicativo_beneficio_trib,
                'porcentaje_perdida_efic' => $simOrigen->porcentaje_perdida_efic,
                'porcentaje_IPC' => $simOrigen->porcentaje_IPC,
                'anios_depreciacion' => $simOrigen->anios_depreciacion,
                'porcentaje_tasa_oportunidad' => $simOrigen->porcentaje_tasa_oportunidad,
                'Valor_VPN_proyecto' => $simOrigen->Valor_VPN_proyecto,
                'porcentaje_TIR_proyecto' => $simOrigen->porcentaje_TIR_proyecto,
                'anios_PBT' => $simOrigen->anios_PBT,
                'nombre_inversionista' => $dto['nombre_inversionista'] ?? null,
                'telefono_inversionista' => $dto['telefono_inversionista'] ?? null,
                'email_inversionista' => $dto['email_inversionista'] ?? null,
                'porcentaje_part_inversionista' => $porcentaje,
                'valor_part_inversionista' => $valorPartInversionista,
                'usuario_creacion_id' => $uid,
                'usuario_creacion_nombre' => $unom,
                'usuario_modificacion_id' => $uid,
                'usuario_modificacion_nombre' => $unom,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $rows = [];

            foreach ($conceptosOrigen as $c) {
                $valor = (float)($c->valor_concepto_proyecto ?? 0);

                // aplica porcentaje a TODOS los años
                if (in_array((string)$c->indicativo_tipo_valor, ['C', 'V'], true)) {
                    $valor = ($valor * $porcentaje) / 100;
                }

                $rows[] = [
                    'id_simulacion' => $nueva->id,
                    'id_proyecto' => $c->id_proyecto,
                    'anio' => $c->anio,
                    'secuencia' => $c->secuencia,
                    'id_concepto_proyecto' => $c->id_concepto_proyecto,
                    'valor_mensual_concepto' => $c->valor_mensual_concepto,
                    'porcentaje' => $c->porcentaje,
                    'valor_concepto_proyecto' => $valor,
                    'usuario_creacion_id' => $uid,
                    'usuario_creacion_nombre' => $unom,
                    'usuario_modificacion_id' => $uid,
                    'usuario_modificacion_nombre' => $unom,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($rows)) {
                DB::table('proyectos_simulaciones_conceptos')->insert($rows);
            }

            return ProyectoSimulacion::cargar((int)$nueva->id);
        });
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