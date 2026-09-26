<?php

namespace App\Models\Parametrizacion;

use Exception;
use Carbon\Carbon;
use App\Enum\AccionAuditoriaEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\AuditoriaTabla;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ConceptoPorProyecto extends Model
{
    use HasFactory;

    protected $table = 'valores_conceptos_por_proyecto';

    protected $fillable = [
        'id_proyecto',
        'anio',
        'mes',
        'secuencia',
        'indicativo_tipo_valor',
        'id_concepto_proyecto',
        'valor_concepto_proyecto',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    // =========================================================
    //  COLECCIONES / LISTADOS
    // =========================================================

    public static function obtenerColeccionLigera($dto)
    {
        $query = DB::table('valores_conceptos_por_proyecto')
            ->select(
                'valores_conceptos_por_proyecto.id',
                'valores_conceptos_por_proyecto.id_proyecto',
                'valores_conceptos_por_proyecto.anio',
                'valores_conceptos_por_proyecto.mes',
                'valores_conceptos_por_proyecto.secuencia',
                'valores_conceptos_por_proyecto.id_concepto_proyecto',
                'valores_conceptos_por_proyecto.valor_concepto_proyecto',
                'valores_conceptos_por_proyecto.usuario_creacion_id',
                'valores_conceptos_por_proyecto.usuario_creacion_nombre',
                'valores_conceptos_por_proyecto.usuario_modificacion_id',
                'valores_conceptos_por_proyecto.usuario_modificacion_nombre',
            )
            ->orderBy('valores_conceptos_por_proyecto.id_proyecto', 'asc');

        return $query->get();
    }

    public static function obtenerColeccion($dto)
    {
        $user   = Auth::user();
        $usuario = $user->usuario();
        $rol    = $user->rol();

        $query = DB::table('proyectos as p')
            ->join('valores_conceptos_por_proyecto as v', 'p.id', '=', 'v.id_proyecto')
            ->join('tipos_proyectos as tp', 'tp.id', '=', 'p.id_tipo_proyecto')
            ->select([
                DB::raw('MAX(v.id) as id'),
                DB::raw('MAX(p.id_tipo_proyecto) as id_tipo_proyecto'),
                DB::raw('MAX(p.ciudad_id) as ciudad_id'),
                'v.id_proyecto',
                'p.codigo_proyecto',
                'p.nombre as nombre_proyecto',
                DB::raw('MAX(tp.nombre) as tipo_proyecto'),
                'v.anio',
                'v.mes',
            ])
            ->groupBy('v.id_proyecto', 'p.codigo_proyecto', 'p.nombre', 'v.anio', 'v.mes');

        if (isset($dto['nombre']) && $dto['nombre'] !== '') {
            $query->where('p.nombre', 'like', '%' . $dto['nombre'] . '%');
        }
        if (isset($dto['proyecto_id']) && $dto['proyecto_id'] !== '') {
            $query->where('v.id_proyecto',  $dto['proyecto_id']);
        }
        if (isset($dto['anio']) && $dto['anio'] !== '') {
            $query->where('v.anio', $dto['anio']);
        }
        if (isset($dto['mes']) && $dto['mes'] !== '') {
            $query->where('v.mes', $dto['mes']);
        }

        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0) {
            foreach ($dto['ordenar_por'] as $attribute => $value) {
                if ($attribute == 'codigo_proyecto') {
                    $query->orderBy('p.codigo_proyecto', $value);
                }
                if ($attribute == 'nombre_proyecto') {
                    $query->orderBy('p.nombre', $value);
                }
                if ($attribute == 'tipo_proyecto') {
                    $query->orderBy('tp.nombre', $value);
                }
                if ($attribute == 'anio') {
                    $query->orderBy('v.anio', $value);
                }
                if ($attribute == 'mes') {
                    $query->orderBy('v.mes', $value);
                }
            }
        } else {
            $query->orderBy('v.anio', 'desc')
                ->orderBy('v.mes', 'desc')
                ->orderBy('v.id_proyecto', 'desc')
                ->orderBy('tp.nombre', 'desc');
        }

        $valores = $query->paginate($dto['limite'] ?? 100);

        return [
            'datos'          => $valores->items(),
            'desde'          => $valores->firstItem(),
            'hasta'          => $valores->lastItem(),
            'por_pagina'     => $valores->perPage(),
            'pagina_actual'  => $valores->currentPage(),
            'ultima_pagina'  => $valores->lastPage(),
            'total'          => $valores->total(),
        ];
    }

    // =========================================================
    //  CARGAR / HEAD
    // =========================================================

    public static function cargar($id)
    {
        $v = ConceptoPorProyecto::find($id);

        return [
            'id'                         => $v->id,
            'id_proyecto'                => $v->id_proyecto,
            'anio'                       => $v->anio,
            'mes'                        => $v->mes,
            'secuencia'                  => $v->secuencia,
            'id_concepto_proyecto'       => $v->id_concepto_proyecto,
            'valor_concepto_proyecto'    => $v->valor_concepto_proyecto,
            'usuario_creacion_id'        => $v->usuario_creacion_id,
            'usuario_creacion_nombre'    => $v->usuario_creacion_nombre,
            'usuario_modificacion_id'    => $v->usuario_modificacion_id,
            'usuario_modificacion_nombre'=> $v->usuario_modificacion_nombre,
            'fecha_creacion'             => (new Carbon($v->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion'         => (new Carbon($v->updated_at))->format("Y-m-d H:i:s"),
        ];
    }

    public static function cargarHead($datos)
    {
        $v = DB::table('proyectos as p')
            ->join('tipos_proyectos as tp', 'tp.id', '=', 'p.id_tipo_proyecto')
            ->leftJoin('ciudades as ciu', 'ciu.id', '=', 'p.ciudad_id')
            ->where('p.id', $datos['proyecto_id'])
            ->select([
                'p.id as id_proyecto',
                'p.codigo_proyecto',
                'p.nombre as nombre_proyecto',
                'p.id_tipo_proyecto as id_tipo_proyecto',
                'tp.nombre as tipo_proyecto',
                'p.ciudad_id as ciudad_id',
                'ciu.nombre as ciudad_proyecto',
            ])
            ->first();

        return [
            'id_proyecto'     => $v->id_proyecto,
            'codigo_proyecto' => $v->codigo_proyecto,
            'nombre_proyecto' => $v->nombre_proyecto,
            'id_tipo_proyecto'=> $v->id_tipo_proyecto,
            'tipo_proyecto'   => $v->tipo_proyecto,
            'ciudad_id'       => $v->ciudad_id,
            'ciudad_proyecto' => $v->ciudad_proyecto,
        ];
    }

    public static function cargarDatosPrevios(array $dto)
    {
        $q = DB::table('valores_conceptos_por_proyecto as v')
            ->where('v.id_proyecto', $dto['proyecto_id'])
            ->whereNotNull('v.anio')
            ->whereNotNull('v.mes');

        $ultimoYM = $q->select('v.anio', 'v.mes')
            ->orderByDesc('v.anio')
            ->orderByDesc('v.mes')
            ->first();

        if (!$ultimoYM) {
            return null;
        }

        $anioOrigen = (int) $ultimoYM->anio;
        $mesOrigen  = (int) $ultimoYM->mes;

        return [
            'proyecto_id'      => $dto['proyecto_id'],
            'anio_origen'      => $anioOrigen,
            'mes_origen'       => $mesOrigen,
        ];
    }

    // =========================================================
    //  CRUD BÁSICO
    // =========================================================

    public static function modificarOCrear($dto)
    {
        $user    = Auth::user();
        $usuario = $user->usuario();

        if (!isset($dto['id'])) {
            $dto['usuario_creacion_id']     = $usuario->id     ?? ($dto['usuario_creacion_id'] ?? null);
            $dto['usuario_creacion_nombre'] = $usuario->nombre ?? ($dto['usuario_creacion_nombre'] ?? null);
        }
        if (isset($usuario) || isset($dto['usuario_modificacion_id'])) {
            $dto['usuario_modificacion_id']     = $usuario->id     ?? ($dto['usuario_modificacion_id'] ?? null);
            $dto['usuario_modificacion_nombre'] = $usuario->nombre ?? ($dto['usuario_modificacion_nombre'] ?? null);
        }

        $v = isset($dto['id'])
            ? ConceptoPorProyecto::find($dto['id'])
            : new ConceptoPorProyecto();

        $originalJson = $v->toJson();

        $v->fill($dto);
        $guardado = $v->save();

        if (!$guardado) {
            throw new Exception("Ocurrió un error al intentar guardar la Actividad Por Proyecto.", $v);
        }

        $auditoriaDto = [
            'id_recurso'          => $v->id,
            'nombre_recurso'      => ConceptoPorProyecto::class,
            'descripcion_recurso' => 'Proyecto-' . $v->id_proyecto
                . '-Concepto-' . $v->id_concepto_proyecto
                . '-Secuencia-' . $v->secuencia
                . '-Año-' . $v->anio
                . '-Mes-' . $v->mes,
            'accion'              => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original'    => isset($dto['id']) ? $originalJson : $v->toJson(),
            'recurso_resultante'  => isset($dto['id']) ? $v->toJson() : null,
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return ConceptoPorProyecto::cargar($v->id);
    }

    public static function eliminar($id)
    {
        $v = ConceptoPorProyecto::find($id);

        $auditoriaDto = [
            'id_recurso'          => $v->id,
            'nombre_recurso'      => ConceptoPorProyecto::class,
            'descripcion_recurso' => 'Proyecto-' . $v->id_proyecto
                . '-Concepto-' . $v->id_concepto_proyecto
                . '-Secuencia-' . $v->secuencia
                . '-Año-' . $v->anio
                . '-Mes-' . $v->mes,
            'accion'              => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original'    => $v->toJson(),
        ];
        AuditoriaTabla::crear($auditoriaDto);

        return $v->delete();
    }

    public static function existsInPeriod(int $proyectoId, int $anio, int $mes): bool
    {
        return DB::table('valores_conceptos_por_proyecto')
            ->where('id_proyecto', $proyectoId)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->exists();
    }

    // =========================================================
    //  COPIAR / INICIALIZAR
    // =========================================================

    public static function copiarConceptos(
            int $proyectoId,
            int $anioOrigen,
            int $mesOrigen,
            int $anioDestino,
            int $mesDestino,
            bool $replace = false
        ): int {
        $user      = Auth::user();
        $userId    = $user?->id;
        $userName  = $user?->name;
        $timestamp = Carbon::now();

        return DB::transaction(function () use (
            $proyectoId, $anioOrigen, $mesOrigen, $anioDestino, $mesDestino, $replace, $userId, $userName, $timestamp
        ) {
            if ($replace) {
                DB::table('valores_conceptos_por_proyecto')
                    ->where('id_proyecto', $proyectoId)
                    ->where('anio', $anioDestino)
                    ->where('mes', $mesDestino)
                    ->delete();
            }

            $select = DB::table('conceptos_proyectos as cp')
                ->join('proyectos as p', 'p.id_tipo_proyecto', '=', 'cp.id_tipo_proyecto')
                ->leftJoin('valores_conceptos_por_proyecto as vcpp', function ($j) use ($proyectoId, $anioOrigen, $mesOrigen) {
                    $j->on('vcpp.id_concepto_proyecto', '=', 'cp.id')
                        ->where('vcpp.id_proyecto', '=', $proyectoId)
                        ->where('vcpp.anio', '=', $anioOrigen)
                        ->where('vcpp.mes', '=', $mesOrigen);
                })
                ->where('p.id', '=', $proyectoId)
                ->where('cp.estado', '=', 1)
                ->selectRaw('
                    ? as anio,
                    ? as mes,
                    ? as id_proyecto,
                    cp.secuencia,
                    \'V\' as indicativo_tipo_valor,
                    cp.id as id_concepto_proyecto,
                    CASE
                        WHEN cp.indicativo_permite_copia = \'S\'
                            THEN COALESCE(vcpp.valor_concepto_proyecto, 0)
                        ELSE 0
                    END as valor_concepto_proyecto,
                    ? as usuario_creacion_id,
                    ? as usuario_creacion_nombre,
                    ? as usuario_modificacion_id,
                    ? as usuario_modificacion_nombre,
                    ? as created_at,
                    ? as updated_at
                ', [
                    $anioDestino,
                    $mesDestino,
                    $proyectoId,
                    $userId,
                    $userName,
                    $userId,
                    $userName,
                    $timestamp,
                    $timestamp,
                ]);

            $columns = [
                'anio',
                'mes',
                'id_proyecto',
                'secuencia',
                'indicativo_tipo_valor',
                'id_concepto_proyecto',
                'valor_concepto_proyecto',
                'usuario_creacion_id',
                'usuario_creacion_nombre',
                'usuario_modificacion_id',
                'usuario_modificacion_nombre',
                'created_at',
                'updated_at',
            ];

            DB::table('valores_conceptos_por_proyecto')->insertUsing($columns, $select);

            $insertados = DB::table('valores_conceptos_por_proyecto as v')
                ->where('v.id_proyecto', $proyectoId)
                ->where('v.anio', $anioDestino)
                ->where('v.mes', $mesDestino)
                ->where('v.usuario_creacion_id', $userId)
                ->whereBetween('v.created_at', [
                    $timestamp->copy()->subSecond(),
                    $timestamp->copy()->addSecond(),
                ])
                ->get([
                    'v.id',
                    'v.id_proyecto',
                    'v.id_concepto_proyecto',
                    'v.secuencia',
                    'v.anio',
                    'v.mes',
                    'v.valor_concepto_proyecto',
                    'v.usuario_creacion_id',
                    'v.usuario_creacion_nombre',
                    'v.usuario_modificacion_id',
                    'v.usuario_modificacion_nombre',
                    'v.created_at',
                    'v.updated_at',
                ]);

            foreach ($insertados as $row) {
                $auditoriaDto = [
                    'id_recurso'          => $row->id,
                    'nombre_recurso'      => ConceptoPorProyecto::class,
                    'descripcion_recurso' => 'Proyecto-' . $row->id_proyecto
                        . '-Concepto-' . $row->id_concepto_proyecto
                        . '-Secuencia-' . ($row->secuencia ?? 'NULL')
                        . '-Año-' . $row->anio
                        . '-Mes-' . $row->mes,
                    'accion'              => AccionAuditoriaEnum::CREAR,
                    'recurso_original'    => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'recurso_resultante'  => null,
                ];

                AuditoriaTabla::crear($auditoriaDto);
            }

            // 👇 Ajustar tipo P/C para los 4 conceptos especiales
            self::ajustarTipoValorPeriodo($proyectoId, $anioDestino, $mesDestino);

            return $insertados->count();
        });
    }

    public static function inicializarMesConCeros(
            int $proyectoId,
            int $anioDestino,
            int $mesDestino,
            bool $replace = false
        ): int {
        $user      = Auth::user();
        $userId    = $user?->id;
        $userName  = $user?->name;
        $timestamp = Carbon::now();

        return DB::transaction(function () use (
            $proyectoId, $anioDestino, $mesDestino, $replace, $userId, $userName, $timestamp
        ) {
            if ($replace) {
                DB::table('valores_conceptos_por_proyecto')
                    ->where('id_proyecto', $proyectoId)
                    ->where('anio', $anioDestino)
                    ->where('mes', $mesDestino)
                    ->delete();
            }

            $select = DB::table('conceptos_proyectos as cp')
                ->join('proyectos as p', 'p.id_tipo_proyecto', '=', 'cp.id_tipo_proyecto')
                ->where('p.id', '=', $proyectoId)
                ->where('cp.estado', '=', 1)
                ->selectRaw('
                    ? as anio,
                    ? as mes,
                    ? as id_proyecto,
                    cp.secuencia,
                    \'V\' as indicativo_tipo_valor,
                    cp.id as id_concepto_proyecto,
                    0 as valor_concepto_proyecto,
                    ? as usuario_creacion_id,
                    ? as usuario_creacion_nombre,
                    ? as usuario_modificacion_id,
                    ? as usuario_modificacion_nombre,
                    ? as created_at,
                    ? as updated_at
                ', [
                    $anioDestino,
                    $mesDestino,
                    $proyectoId,
                    $userId,
                    $userName,
                    $userId,
                    $userName,
                    $timestamp,
                    $timestamp,
                ]);

            $columns = [
                'anio',
                'mes',
                'id_proyecto',
                'secuencia',
                'indicativo_tipo_valor',
                'id_concepto_proyecto',
                'valor_concepto_proyecto',
                'usuario_creacion_id',
                'usuario_creacion_nombre',
                'usuario_modificacion_id',
                'usuario_modificacion_nombre',
                'created_at',
                'updated_at',
            ];

            DB::table('valores_conceptos_por_proyecto')->insertUsing($columns, $select);

            $insertados = DB::table('valores_conceptos_por_proyecto as v')
                ->where('v.id_proyecto', $proyectoId)
                ->where('v.anio', $anioDestino)
                ->where('v.mes', $mesDestino)
                ->where('v.usuario_creacion_id', $userId)
                ->whereBetween('v.created_at', [
                    $timestamp->copy()->subSecond(),
                    $timestamp->copy()->addSecond(),
                ])
                ->get([
                    'v.id',
                    'v.id_proyecto',
                    'v.id_concepto_proyecto',
                    'v.secuencia',
                    'v.anio',
                    'v.mes',
                    'v.valor_concepto_proyecto',
                    'v.usuario_creacion_id',
                    'v.usuario_creacion_nombre',
                    'v.usuario_modificacion_id',
                    'v.usuario_modificacion_nombre',
                    'v.created_at',
                    'v.updated_at',
                ]);

            foreach ($insertados as $row) {
                $auditoriaDto = [
                    'id_recurso'          => $row->id,
                    'nombre_recurso'      => ConceptoPorProyecto::class,
                    'descripcion_recurso' => 'Proyecto-' . $row->id_proyecto
                        . '-Concepto-' . $row->id_concepto_proyecto
                        . '-Secuencia-' . ($row->secuencia ?? 'NULL')
                        . '-Año-' . $row->anio
                        . '-Mes-' . $row->mes,
                    'accion'              => AccionAuditoriaEnum::CREAR,
                    'recurso_original'    => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'recurso_resultante'  => null,
                ];

                AuditoriaTabla::crear($auditoriaDto);
            }

            // 👇 Ajustar tipo P/C para los 4 conceptos especiales
            self::ajustarTipoValorPeriodo($proyectoId, $anioDestino, $mesDestino);

            return (clone $select)->count();
        });
    }

    // =========================================================
    //  CONSULTA PARA FORM (separa precio/kWh y conceptos)
    // =========================================================

    public static function conceptosParaPeriodo($dto)
    {
        $proyectoId = $dto['proyecto_id'];
        $anio       = $dto['anio'];
        $mes        = $dto['mes'];

        $rows = DB::table('conceptos_proyectos as cp')
            ->join('proyectos as p', 'p.id_tipo_proyecto', '=', 'cp.id_tipo_proyecto')
            ->leftJoin('valores_conceptos_por_proyecto as v', function ($join) use ($proyectoId, $anio, $mes) {
                $join->on('v.id_concepto_proyecto', '=', 'cp.id')
                    ->where('v.id_proyecto', '=', $proyectoId)
                    ->where('v.anio', '=', $anio)
                    ->where('v.mes', '=', $mes);
            })
            ->where('p.id', '=', $proyectoId)
            ->where('cp.estado', '=', 1)
            ->select([
                'cp.*',
                'cp.id as id_concepto',
                'p.id as id_proyecto',
                DB::raw('COALESCE(v.valor_concepto_proyecto, 0) as valor_concepto_proyecto'),
                DB::raw("CASE WHEN cp.indicativo_concepto_editable = 'N' THEN 0 ELSE 1 END as es_editable"),
                DB::raw("CASE WHEN cp.indicativo_concepto_editable = 'N' THEN 1 ELSE 0 END as protegido"),
                'v.id as valor_id',
                'v.secuencia as secuencia_valor',
                'v.anio',
                'v.mes',
                'v.indicativo_tipo_valor',
            ])
            ->orderBy('cp.secuencia', 'asc')
            ->get();

        // IDs especiales desde parametros_constantes
        $paramRows = DB::table('parametros_constantes')
            ->whereIn('codigo_parametro', [
                'ID_CONCEPTO_PRECIO_COMUNIDAD',
                'ID_CONCEPTO_KWH_COMUNIDAD',
                'ID_CONCEPTO_PRECIO_BOLSA',
                'ID_CONCEPTO_KWH_BOLSA',
            ])
            ->pluck('valor_parametro', 'codigo_parametro');

        $idPrecioComunidad = (int)($paramRows['ID_CONCEPTO_PRECIO_COMUNIDAD'] ?? 0);
        $idKwhComunidad    = (int)($paramRows['ID_CONCEPTO_KWH_COMUNIDAD']    ?? 0);
        $idPrecioBolsa     = (int)($paramRows['ID_CONCEPTO_PRECIO_BOLSA']     ?? 0);
        $idKwhBolsa        = (int)($paramRows['ID_CONCEPTO_KWH_BOLSA']        ?? 0);

        $response = [
            'precio_energia_comunidad'         => 0.0,
            'energia_comercializada_comunidad' => 0.0,
            'precio_energia_bolsa'             => 0.0,
            'energia_comercializada_bolsa'     => 0.0,
            'conceptos'                        => [],
        ];

        foreach ($rows as $row) {
            $idConcepto = (int) $row->id_concepto;
            $valor      = (float) $row->valor_concepto_proyecto;

            if ($idConcepto === $idPrecioComunidad) {
                $response['precio_energia_comunidad'] = $valor;
                continue;
            }
            if ($idConcepto === $idKwhComunidad) {
                $response['energia_comercializada_comunidad'] = $valor;
                continue;
            }
            if ($idConcepto === $idPrecioBolsa) {
                $response['precio_energia_bolsa'] = $valor;
                continue;
            }
            if ($idConcepto === $idKwhBolsa) {
                $response['energia_comercializada_bolsa'] = $valor;
                continue;
            }

            // Solo mostramos los tipo V en la tabla
            $tipoValor = $row->indicativo_tipo_valor ?? 'V';
            if ($tipoValor === 'V') {
                $response['conceptos'][] = $row;
            }
        }

        return $response;
    }

    // =========================================================
    //  GUARDAR VALORES DE PERIODO (conceptos tipo V)
    // =========================================================

    public static function guardarValoresPeriodo(array $dto): void
    {
        $proyectoId     = (int) $dto['id_proyecto'];
        $anio           = (int) $dto['anio'];
        $mes            = (int) $dto['mes'];
        $idTipoProyecto = (int) ($dto['id_tipo_proyecto'] ?? 0);

        $conceptos  = collect($dto['conceptos'] ?? [])
            ->map(fn ($c) => (array) $c);

        $user    = Auth::user();
        $usuario = $user?->usuario();

        $usuarioId  = $usuario->id     ?? null;
        $usuarioNom = $usuario->nombre ?? null;
        $now        = now();

        DB::transaction(function () use (
            $proyectoId,
            $anio,
            $mes,
            $idTipoProyecto, 
            $conceptos,
            $usuarioId,
            $usuarioNom,
            $now
        ) {
            // Traemos lo que YA existe para ese proyecto/año/mes
            $existentes = DB::table('valores_conceptos_por_proyecto')
                ->where('id_proyecto', $proyectoId)
                ->where('anio', $anio)
                ->where('mes', $mes)
                ->get()
                ->keyBy(function ($row) {
                    return implode('|', [
                        $row->id_concepto_proyecto,
                        $row->secuencia ?? '',
                    ]);
                });

            foreach ($conceptos as $c) {
                // --------------------------------------------
                // 1) Resolver id_concepto_proyecto
                // --------------------------------------------

                // id_concepto_proyecto (lo que antes era $c['id'])
                $idConceptoProyecto = $c['id'] ?? null;

                // id del concepto maestro (conceptos.id)
                $idConceptoMaestro  = $c['id_concepto'] ?? null;

                // Si NO tenemos id_concepto_proyecto pero SÍ id_concepto,
                // es el caso de los 4 conceptos de PRECIO/KWH que creaste
                // en ConceptosService (solo con id_concepto).
                if (!$idConceptoProyecto && $idConceptoMaestro) {

                    // Intentamos buscar si ya existe conceptos_por_proyecto
                    $idExistente = DB::table('conceptos_por_proyecto')
                        ->where('id_proyecto', $proyectoId)
                        ->where('id_concepto', $idConceptoMaestro)
                        ->value('id');

                    if ($idExistente) {
                        $idConceptoProyecto = $idExistente;
                    } else {
                        // Creamos un conceptos_por_proyecto mínimo
                        $cppData = [
                            'id_concepto'                    => $idConceptoMaestro,
                            'id_proyecto'                    => $proyectoId,
                            'secuencia'                      => $c['secuencia'] ?? ($c['secuencia_valor'] ?? 0),
                            'id_tipo_proyecto'               => $idTipoProyecto ?: null,
                            'indicativo_tipo_concepto'       => $c['indicativo_tipo_concepto']       ?? 'ING',
                            'indicativo_tipo_linea'          => $c['indicativo_tipo_linea']          ?? 'D',
                            'indicativo_concepto_calculado'  => $c['indicativo_concepto_calculado']  ?? 'S',
                            'indicativo_permite_copia'       => $c['indicativo_permite_copia']       ?? 'N',
                            'indicativo_concepto_editable'   => $c['es_editable']                    ?? 1,
                            'estado'                         => 1,
                            'usuario_creacion_id'            => $usuarioId,
                            'usuario_creacion_nombre'        => $usuarioNom,
                            'usuario_modificacion_id'        => $usuarioId,
                            'usuario_modificacion_nombre'    => $usuarioNom,
                            'created_at'                     => $now,
                            'updated_at'                     => $now,
                        ];

                        $idConceptoProyecto = DB::table('conceptos_por_proyecto')
                            ->insertGetId($cppData);

                        // (Opcional) auditoría de creación de conceptos_por_proyecto
                        // AuditoriaTabla::crear([...]);
                    }
                }

                
                if (!$idConceptoProyecto) {
                    continue;
                }

                // --------------------------------------------
                // 2) Datos para valor_conceptos_por_proyecto
                // --------------------------------------------
                $secuencia  = $c['secuencia'] ?? ($c['secuencia_valor'] ?? null);
                $valorNuevo = (float) ($c['valor'] ?? 0);
                $tipoValor  = $c['indicativo_tipo_valor'] ?? 'V';

                $clave = implode('|', [
                    $idConceptoProyecto,
                    $secuencia ?? '',
                ]);

                $row = $existentes->get($clave);

                // --------------------------------------------
                // INSERT
                // --------------------------------------------
                if (!$row) {
                    $insertData = [
                        'id_proyecto'                 => $proyectoId,
                        'id_concepto_proyecto'        => $idConceptoProyecto,
                        'anio'                        => $anio,
                        'mes'                         => $mes,
                        'secuencia'                   => $secuencia,
                        'valor_concepto_proyecto'     => $valorNuevo,
                        'indicativo_tipo_valor'       => $tipoValor,
                        'usuario_creacion_id'         => $usuarioId,
                        'usuario_creacion_nombre'     => $usuarioNom,
                        'usuario_modificacion_id'     => $usuarioId,
                        'usuario_modificacion_nombre' => $usuarioNom,
                        'created_at'                  => $now,
                        'updated_at'                  => $now,
                    ];

                    $nuevoId = DB::table('valores_conceptos_por_proyecto')
                        ->insertGetId($insertData);

                    $rowResultante = (object) array_merge(['id' => $nuevoId], $insertData);

                    AuditoriaTabla::crear([
                        'id_recurso'          => $rowResultante->id,
                        'nombre_recurso'      => ConceptoPorProyecto::class,
                        'descripcion_recurso' => 'Proyecto-' . $rowResultante->id_proyecto
                            . '-Concepto-' . $rowResultante->id_concepto_proyecto
                            . '-Secuencia-' . ($rowResultante->secuencia ?? 'NULL')
                            . '-Año-' . $rowResultante->anio
                            . '-Mes-' . $rowResultante->mes,
                        'accion'              => AccionAuditoriaEnum::CREAR,
                        'recurso_original'    => '{}',
                        'recurso_resultante'  => json_encode($rowResultante, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'responsable_id'      => $usuarioId,
                        'responsable_nombre'  => $usuarioNom,
                    ]);

                    continue;
                }

                // --------------------------------------------
                // UPDATE
                // --------------------------------------------
                $valorActual     = (float) $row->valor_concepto_proyecto;
                $secuenciaActual = $row->secuencia;
                $tipoValorActual = $row->indicativo_tipo_valor ?? 'V';

                $cambioValor     = $valorActual !== $valorNuevo;
                $cambioSecuencia = (string) $secuenciaActual !== (string) ($secuencia ?? null);
                $cambioTipoValor = (string) $tipoValorActual   !== (string) $tipoValor;

                if (!$cambioValor && !$cambioSecuencia && !$cambioTipoValor) {
                    continue;
                }

                $rowOriginal   = clone $row;
                $rowResultante = clone $row;

                $rowResultante->secuencia                   = $secuencia;
                $rowResultante->valor_concepto_proyecto     = $valorNuevo;
                $rowResultante->indicativo_tipo_valor       = $tipoValor;
                $rowResultante->usuario_modificacion_id     = $usuarioId;
                $rowResultante->usuario_modificacion_nombre = $usuarioNom;
                $rowResultante->updated_at                  = $now;

                DB::table('valores_conceptos_por_proyecto')
                    ->where('id', $row->id)
                    ->update([
                        'secuencia'                   => $secuencia,
                        'valor_concepto_proyecto'     => $valorNuevo,
                        'indicativo_tipo_valor'       => $tipoValor,
                        'usuario_modificacion_id'     => $usuarioId,
                        'usuario_modificacion_nombre' => $usuarioNom,
                        'updated_at'                  => $now,
                    ]);

                AuditoriaTabla::crear([
                    'id_recurso'          => $rowResultante->id,
                    'nombre_recurso'      => ConceptoPorProyecto::class,
                    'descripcion_recurso' => 'Proyecto-' . $rowResultante->id_proyecto
                        . '-Concepto-' . $rowResultante->id_concepto_proyecto
                        . '-Secuencia-' . ($rowResultante->secuencia ?? 'NULL')
                        . '-Año-' . $rowResultante->anio
                        . '-Mes-' . $rowResultante->mes,
                    'accion'              => AccionAuditoriaEnum::MODIFICAR,
                    'recurso_original'    => json_encode($rowOriginal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'recurso_resultante'  => json_encode($rowResultante, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'responsable_id'      => $usuarioId,
                    'responsable_nombre'  => $usuarioNom,
                ]);
            }
        });
    }


    // =========================================================
    //  NUEVO: GUARDAR PRECIOS Y KWH (tipo P / C)
    // =========================================================

    protected static function obtenerIdsConceptosPrecioKwh(): array
    {
        $paramRows = DB::table('parametros_constantes')
            ->whereIn('codigo_parametro', [
                'ID_CONCEPTO_PRECIO_COMUNIDAD',
                'ID_CONCEPTO_KWH_COMUNIDAD',
                'ID_CONCEPTO_PRECIO_BOLSA',
                'ID_CONCEPTO_KWH_BOLSA',
            ])
            ->pluck('valor_parametro', 'codigo_parametro');

        return [
            'precio_comunidad' => (int)($paramRows['ID_CONCEPTO_PRECIO_COMUNIDAD'] ?? 0),
            'kwh_comunidad'    => (int)($paramRows['ID_CONCEPTO_KWH_COMUNIDAD']    ?? 0),
            'precio_bolsa'     => (int)($paramRows['ID_CONCEPTO_PRECIO_BOLSA']     ?? 0),
            'kwh_bolsa'        => (int)($paramRows['ID_CONCEPTO_KWH_BOLSA']        ?? 0),
        ];
    }

    protected static function tipoValorParaConceptoId(int $idConcepto, array $ids): string
    {
        if (in_array($idConcepto, [$ids['precio_comunidad'], $ids['precio_bolsa']], true)) {
            return 'P';
        }

        if (in_array($idConcepto, [$ids['kwh_comunidad'], $ids['kwh_bolsa']], true)) {
            return 'C';
        }

        return 'V';
    }

    // Ajuste masivo después de copiar / inicializar
    protected static function ajustarTipoValorPeriodo(int $proyectoId, int $anio, int $mes): void
    {
        $ids = self::obtenerIdsConceptosPrecioKwh();

        // precios -> P
        DB::table('valores_conceptos_por_proyecto')
            ->where('id_proyecto', $proyectoId)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->whereIn('id_concepto_proyecto', [$ids['precio_comunidad'], $ids['precio_bolsa']])
            ->update(['indicativo_tipo_valor' => 'P']);

        // kWh -> C
        DB::table('valores_conceptos_por_proyecto')
            ->where('id_proyecto', $proyectoId)
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->whereIn('id_concepto_proyecto', [$ids['kwh_comunidad'], $ids['kwh_bolsa']])
            ->update(['indicativo_tipo_valor' => 'C']);
    }
}
