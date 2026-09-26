<?php

namespace App\Models\Parametrizacion;

use Exception;
use Carbon\Carbon;
use App\Enum\AccionAuditoriaEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\AuditoriaTabla;
use App\Models\Parametrizacion\ProyectoSimulacionConcepto;
use App\Models\Parametrizacion\Proyecto;
use App\Services\ProyectoSimulacionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProyectoSimulacion extends Model
{
    use HasFactory;

    protected $table = 'proyectos_simulaciones';

    protected $fillable = [
        'id_proyecto',
        'indicativo_modelo_ccial',
        'indicativo_tipo_simulacion',
        'indicativo_beneficio_trib',
        'porcentaje_perdida_efic',
        'porcentaje_IPC',
        'anios_depreciacion',
        'porcentaje_tasa_oportunidad',
        'Valor_VPN_proyecto',
        'porcentaje_TIR_proyecto',
        'anios_PBT',
        'nombre_inversionista',
        'telefono_inversionista',
        'email_inversionista',
        'porcentaje_part_inversionista',
        'valor_part_inversionista',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    public function conceptos()
    {
        return $this->hasMany(ProyectoSimulacionConcepto::class, 'id_simulacion', 'id');
    }
   

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('proyectos_simulaciones')
        ->where('proyectos_simulaciones.id_proyecto', $dto['id'])
            ->select(
                'proyectos_simulaciones.id',
                'proyectos_simulaciones.id_proyecto',
                'proyectos_simulaciones.indicativo_modelo_ccial',
                'proyectos_simulaciones.indicativo_tipo_simulacion',
                'proyectos_simulaciones.indicativo_beneficio_trib',
                'proyectos_simulaciones.porcentaje_perdida_efic',
                'proyectos_simulaciones.porcentaje_IPC',
                'proyectos_simulaciones.anios_depreciacion',
                'proyectos_simulaciones.porcentaje_tasa_oportunidad',
                'proyectos_simulaciones.Valor_VPN_proyecto',
                'proyectos_simulaciones.porcentaje_TIR_proyecto',
                'proyectos_simulaciones.anios_PBT',
                'proyectos_simulaciones.nombre_inversionista',
                'proyectos_simulaciones.telefono_inversionista',
                'proyectos_simulaciones.email_inversionista',
                'proyectos_simulaciones.porcentaje_part_inversionista',
                'proyectos_simulaciones.valor_part_inversionista',
                'proyectos_simulaciones.usuario_creacion_id',
                'proyectos_simulaciones.usuario_creacion_nombre',
                'proyectos_simulaciones.usuario_modificacion_id',
                'proyectos_simulaciones.usuario_modificacion_nombre',
            );

        if (isset($dto['indicativo_tipo_simulacion'])) {
            $query->where(
                'proyectos_simulaciones.indicativo_tipo_simulacion',
                $dto['indicativo_tipo_simulacion']
            );
        }

        if (isset($dto['indicativo_modelo_ccial'])) {
            $query->where(
                'proyectos_simulaciones.indicativo_modelo_ccial',
                $dto['indicativo_modelo_ccial']
            );
        }


        $query->orderBy('indicativo_modelo_ccial', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('proyectos_simulaciones')
        ->join('proyectos', 'proyectos.id', '=', 'proyectos_simulaciones.id_proyecto')
        ->where('proyectos_simulaciones.id_proyecto', $dto['id'])
            ->select(
            'proyectos_simulaciones.id',
            'proyectos_simulaciones.id_proyecto',
            'proyectos.nombre as proyecto',
            'proyectos.codigo_proyecto',
            'proyectos.valor_total_proyecto',
            'proyectos_simulaciones.indicativo_modelo_ccial',
            'proyectos_simulaciones.indicativo_tipo_simulacion',
            'proyectos_simulaciones.indicativo_beneficio_trib',
            'proyectos_simulaciones.porcentaje_perdida_efic',
            'proyectos_simulaciones.porcentaje_IPC',
            'proyectos_simulaciones.anios_depreciacion',
            'proyectos_simulaciones.porcentaje_tasa_oportunidad',
            'proyectos_simulaciones.Valor_VPN_proyecto',
            'proyectos_simulaciones.porcentaje_TIR_proyecto',
            'proyectos_simulaciones.anios_PBT',
            'proyectos_simulaciones.nombre_inversionista',
            'proyectos_simulaciones.telefono_inversionista',
            'proyectos_simulaciones.email_inversionista',
            'proyectos_simulaciones.porcentaje_part_inversionista',
            'proyectos_simulaciones.valor_part_inversionista',
            'proyectos_simulaciones.usuario_creacion_id',
            'proyectos_simulaciones.usuario_creacion_nombre',
            'proyectos_simulaciones.usuario_modificacion_id',
            'proyectos_simulaciones.usuario_modificacion_nombre',
            'proyectos_simulaciones.created_at as fecha_creacion',
            'proyectos_simulaciones.updated_at as fecha_modificacion' ,
        );

        if (isset($dto['indicativo_tipo_simulacion'])) {
            $query->where(
                'proyectos_simulaciones.indicativo_tipo_simulacion',
                $dto['indicativo_tipo_simulacion']
            );
        }

        if (isset($dto['indicativo_modelo_ccial'])) {
            $query->where(
                'proyectos_simulaciones.indicativo_modelo_ccial',
                $dto['indicativo_modelo_ccial']
            );
        }

        if(isset($dto['anio'])){
            $query->where('proyectos_simulaciones.anio', 'like', '%' . $dto['anio'] . '%');
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'proyecto'){
                    $query->orderBy('proyectos.nombre', $value);
                }
                if($attribute == 'codigo_proyecto'){
                    $query->orderBy('proyectos.codigo_proyecto', $value);
                }
                if($attribute == 'indicativo_modelo_ccial'){
                    $query->orderBy('proyectos_simulaciones.indicativo_modelo_ccial', $value);
                }
               if($attribute == 'indicativo_tipo_simulacion'){
                    $query->orderBy('proyectos_simulaciones.indicativo_tipo_simulacion', $value);
                }
                if($attribute == 'indicativo_beneficio_trib'){
                    $query->orderBy('proyectos_simulaciones.indicativo_beneficio_trib', $value);
                }
                if($attribute == 'porcentaje_perdida_efic'){
                    $query->orderBy('proyectos_simulaciones.porcentaje_perdida_efic', $value);
                }
                if($attribute == 'porcentaje_IPC'){
                    $query->orderBy('proyectos_simulaciones.porcentaje_IPC', $value);
                }
                if($attribute == 'anios_depreciacion'){
                    $query->orderBy('proyectos_simulaciones.anios_depreciacion', $value);
                }
                if($attribute == 'porcentaje_tasa_oportunidad'){
                    $query->orderBy('proyectos_simulaciones.porcentaje_tasa_oportunidad', $value);
                }
                if($attribute == 'Valor_VPN_proyecto'){
                    $query->orderBy('proyectos_simulaciones.Valor_VPN_proyecto', $value);
                }
                if($attribute == 'porcentaje_TIR_proyecto'){
                    $query->orderBy('proyectos_simulaciones.porcentaje_TIR_proyecto', $value);
                }
                if($attribute == 'anios_PBT'){
                    $query->orderBy('proyectos_simulaciones.anios_PBT', $value);
                }
                if($attribute == 'nombre_inversionista'){
                    $query->orderBy('proyectos_simulaciones.nombre_inversionista', $value);
                }
                if($attribute == 'telefono_inversionista'){
                    $query->orderBy('proyectos_simulaciones.telefono_inversionista', $value);
                }
                if($attribute == 'email_inversionista'){
                    $query->orderBy('proyectos_simulaciones.email_inversionista', $value);
                }
                if($attribute == 'porcentaje_part_inversionista'){
                    $query->orderBy('proyectos_simulaciones.porcentaje_part_inversionista', $value);
                }
                if($attribute == 'valor_part_inversionista'){
                    $query->orderBy('proyectos_simulaciones.valor_part_inversionista', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('proyectos_simulaciones.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('proyectos_simulaciones.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('proyectos_simulaciones.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('proyectos_simulaciones.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("proyectos_simulaciones.updated_at", "desc");
        }

        $proyectos_simulaciones = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $proyectos_simulaciones->items();
    
        return [
            'datos' => $data,
            'desde' => $proyectos_simulaciones->firstItem(),
            'hasta' => $proyectos_simulaciones->lastItem(),
            'por_pagina' => $proyectos_simulaciones->perPage(),
            'pagina_actual' => $proyectos_simulaciones->currentPage(),
            'ultima_pagina' => $proyectos_simulaciones->lastPage(),
            'total' => $proyectos_simulaciones->total(),
        ];
    }

    public static function cargar($id)
    {
        $proyectos_simulaciones = ProyectoSimulacion::findOrFail($id);
        $proyecto = Proyecto::findOrFail((int)$proyectos_simulaciones->id_proyecto);

        $svc = app(\App\Services\ProyectoSimulacionService::class);
        $datos = $svc->cargarDetalleConceptosPorAnio((int)$proyectos_simulaciones->id);

        // Fuerza a que sea 100% serializable y muestra error exacto si no lo es
        try {
            // Normaliza a array puro
            $datos = json_decode(json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), true);
        } catch (\Throwable $e) {
            abort(500, 'Error serializando detalle_conceptos: ' . $e->getMessage());
        }

        return [
            'id' => (int)$proyectos_simulaciones->id,
            'id_proyecto' => (int)$proyectos_simulaciones->id_proyecto,
            'nombre_proyecto' => $proyecto->nombre,
            'codigo_proyecto' => $proyecto->codigo_proyecto,
            'indicativo_modelo_ccial' => $proyectos_simulaciones->indicativo_modelo_ccial,
            'indicativo_tipo_simulacion' => $proyectos_simulaciones->indicativo_tipo_simulacion,
            'indicativo_beneficio_trib' => $proyectos_simulaciones->indicativo_beneficio_trib,
            'fecha' =>  optional($proyectos_simulaciones->updated_at)->format('Y-m-d'),
            'potencia' => $proyecto->potencia,
            'porcentaje_perdida_efic' => $proyectos_simulaciones->porcentaje_perdida_efic,
            'valor_total_proyecto' => $proyecto->valor_total_proyecto,
            'porcentaje_IPC' => $proyectos_simulaciones->porcentaje_IPC,
            'anios_depreciacion' => $proyectos_simulaciones->anios_depreciacion,
            'porcentaje_tasa_oportunidad' => $proyectos_simulaciones->porcentaje_tasa_oportunidad,
            'Valor_VPN_proyecto' => $proyectos_simulaciones->Valor_VPN_proyecto,
            'porcentaje_TIR_proyecto' => $proyectos_simulaciones->porcentaje_TIR_proyecto,
            'anios_PBT' => $proyectos_simulaciones->anios_PBT,
            'nombre_inversionista' => $proyectos_simulaciones->nombre_inversionista,
            'telefono_inversionista' => $proyectos_simulaciones->telefono_inversionista,
            'email_inversionista' => $proyectos_simulaciones->email_inversionista,
            'porcentaje_part_inversionista' => $proyectos_simulaciones->porcentaje_part_inversionista,
            'valor_part_inversionista' => $proyectos_simulaciones->valor_part_inversionista,
            'usuario_creacion_id' => $proyectos_simulaciones->usuario_creacion_id,
            'usuario_creacion_nombre' => $proyectos_simulaciones->usuario_creacion_nombre,
            'usuario_modificacion_id' => $proyectos_simulaciones->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $proyectos_simulaciones->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($proyectos_simulaciones->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($proyectos_simulaciones->updated_at))->format("Y-m-d H:i:s"),
            'detalle_conceptos' => $datos,
        ];
    }


    public static function modificarOCrear($dto)
    {
        $user = Auth::user();
        $usuario = $user->usuario();
        if (!isset($dto['id'])) {
            $dto['usuario_creacion_id'] = $usuario->id ?? ($dto['usuario_creacion_id'] ?? null);
            $dto['usuario_creacion_nombre'] = $usuario->nombre ?? ($dto['usuario_creacion_nombre'] ?? null);
        }
        if (isset($usuario) || isset($dto['usuario_modificacion_id'])) {
            $dto['usuario_modificacion_id'] = $usuario->id ?? ($dto['usuario_modificacion_id'] ?? null);
            $dto['usuario_modificacion_nombre'] = $usuario->nombre ?? ($dto['usuario_modificacion_nombre'] ?? null);
        }

        // Consultar el servicio
        $proyectos_simulaciones = isset($dto['id']) ? ProyectoSimulacion::find($dto['id']) : new ProyectoSimulacion();

        // Guardar objeto original para auditoria
        $proyectos_simulacionesOriginal = $proyectos_simulaciones->toJson();

        $proyectos_simulaciones->fill($dto);
        $guardado = $proyectos_simulaciones->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la condición plazo.", $proyectos_simulaciones);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $proyectos_simulaciones->id,
            'nombre_recurso' => ProyectoSimulacion::class,
            'descripcion_recurso' => 'Proyecto-'. $proyectos_simulaciones->id_proyecto. '-Modelo-'.$proyectos_simulaciones->indicativo_modelo_ccial,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $proyectos_simulacionesOriginal : $proyectos_simulaciones->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $proyectos_simulaciones->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return ProyectoSimulacion::cargar($proyectos_simulaciones->id);
    }

    public static function eliminar($id)
    {
        $user = Auth::user();
        $usuario = $user?->usuario();

         // 1) Buscar la simulación origen para tomar proyecto y modelo
        $simulacion = ProyectoSimulacion::find($id);

        if (!$simulacion) {
            DB::rollBack();
            return response(
                get_response_body(["La simulación no existe."]),
                Response::HTTP_NOT_FOUND
            );
        }

        $idProyecto = (int)($simulacion->id_proyecto ?? 0);
        $modelo     = trim((string)($simulacion->indicativo_modelo_ccial ?? ''));

        // 2.1) Eliminar simulaciones relacionadas de tipo I
        DB::transaction(function () use ($idProyecto, $modelo, $simulacion) {
            if ($idProyecto > 0 && $modelo !== '' && $simulacion->indicativo_tipo_simulacion == 'I') {
                $simulacionesTipoI = ProyectoSimulacion::query()
                    ->where('id_proyecto', $idProyecto)
                    ->where('indicativo_modelo_ccial', $modelo)
                    ->where('indicativo_tipo_simulacion', 'I')
                    ->where('indicativo_beneficio_trib', 'N')
                    ->get();

                foreach ($simulacionesTipoI as $simI) {
                    DB::table('proyectos_simulaciones_conceptos')
                        ->where('id_simulacion', $simI->id)
                        ->delete();

                    DB::table('proyectos_simulaciones')
                        ->where('id', $simI->id)
                        ->delete();
                }
            }
        });

        // 2) Eliminar simulaciones relacionadas de tipo I
        //    del mismo proyecto y mismo modelo comercial
        DB::transaction(function () use ($idProyecto, $modelo, $simulacion) {
            if ($idProyecto > 0 && $modelo !== '' && $simulacion->indicativo_tipo_simulacion == 'P') {

                $simulacionesTipoI = ProyectoSimulacion::query()
                        ->where('id_proyecto', $idProyecto)
                        ->where('indicativo_modelo_ccial', $modelo)
                        ->where('indicativo_tipo_simulacion', 'I')
                        ->get();

                foreach ($simulacionesTipoI as $simI) {
                    DB::table('proyectos_simulaciones_conceptos')
                        ->where('id_simulacion', $simI->id)
                        ->delete();

                    DB::table('proyectos_simulaciones')
                        ->where('id', $simI->id)
                        ->delete();
                }

                $simulacionesTipoP = ProyectoSimulacion::query()
                    ->where('id_proyecto', $idProyecto)
                    ->where('indicativo_modelo_ccial', $modelo)
                    ->where('indicativo_tipo_simulacion', 'P')
                    ->where('indicativo_beneficio_trib', 'N')
                    ->get();

                foreach ($simulacionesTipoP as $simI) {
                    DB::table('proyectos_simulaciones_conceptos')
                        ->where('id_simulacion', $simI->id)
                        ->delete();

                    ProyectoSimulacion::where('id', $simI->id)->delete();
                }
            }
        });


        return DB::transaction(function () use ($id, $usuario) {

            /** @var ProyectoSimulacion|null $sim */
            $sim = ProyectoSimulacion::find($id);
            if (!$sim) {
                throw new Exception("No existe la simulación con id {$id}.");
            }
            

            // Guardar auditoria ANTES de borrar
            $auditoriaDto = array(
                'id_recurso' => $sim->id,
                'nombre_recurso' => ProyectoSimulacion::class,
                'descripcion_recurso' => 'Proyecto-Simulacion' . $sim->id_proyecto . '-Modelo-' . $sim->indicativo_modelo_ccial,
                'accion' => AccionAuditoriaEnum::ELIMINAR,
                'recurso_original' => $sim->toJson()
            );
            AuditoriaTabla::crear($auditoriaDto);

            // 1) Borrar conceptos (tabla hija)
            DB::table('proyectos_simulaciones_conceptos')
                ->where('id_simulacion', $sim->id)
                ->delete();

            // 2) Borrar simulación (tabla padre)
            return $sim->delete();
        });
    }




}
