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
use Illuminate\Support\Facades\Storage;

class ActividadPorProyecto extends Model
{
    use HasFactory;

    protected $table = 'actividades_por_proyecto';

    protected $fillable = [
        'id_proyecto',
        'id_etapa_proyecto',
        'id_actividad_proyecto',
        'fecha_ejecucion',
        'fecha_compromiso',
        'observaciones',
        'nombre_archivo',
        'estado_actividad',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('actividades_por_proyecto')
            ->select(
                'actividades_por_proyecto.id',
                'actividades_por_proyecto.id_proyecto',
                'actividades_por_proyecto.id_etapa_proyecto',
                'actividades_por_proyecto.id_actividad_proyecto',
                'actividades_por_proyecto.fecha_ejecucion',
                'actividades_por_proyecto.fecha_compromiso',
                'actividades_por_proyecto.observaciones',
                'actividades_por_proyecto.nombre_archivo',
                'actividades_por_proyecto.estado_actividad',
                'actividades_por_proyecto.usuario_creacion_id',
                'actividades_por_proyecto.usuario_creacion_nombre',
                'actividades_por_proyecto.usuario_modificacion_id',
                'actividades_por_proyecto.usuario_modificacion_nombre',
            );

        $query->orderBy('id_proyecto', 'asc');
        return $query->get();
    }

    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('proyectos as p')
            ->join('tipos_proyectos as tp', 'tp.id', '=', 'p.id_tipo_proyecto')
            ->leftJoin('ciudades as ciu', 'ciu.id', '=', 'p.ciudad_id')
            ->join('actividades_proyectos as ap', 'ap.id_tipo_proyecto', '=', 'p.id_tipo_proyecto')
            ->leftJoin('etapas_proyectos as ep', 'ep.id', '=', 'ap.id_etapa_proyecto')
            ->leftJoin('actividades_por_proyecto as app', function ($j) {
                $j->on('app.id_proyecto', '=', 'p.id')
                ->on('app.id_actividad_proyecto', '=', 'ap.id')
                ->on('app.id_etapa_proyecto', '=', 'ap.id_etapa_proyecto');
            })
            ->where('p.id', $dto['id'])
            ->select([
                'app.id as id',
                'p.id as id_proyecto',
                'p.codigo_proyecto',
                'p.nombre as nombre_proyecto',
                'tp.id as id_tipo_proyecto',
                'tp.nombre as tipo_proyecto',
                'ciu.id as ciudad_id',
                'ciu.nombre as ciudad_proyecto',
                'ep.id as id_etapa_proyecto',
                'ep.nombre as etapa_proyecto',
                'ep.secuencia as etapa_secuencia',
                'ap.id as id_actividad_proyecto',
                'ap.nombre as nombre_actividad',
                'ap.secuencia as secuencia_actividad',
                'ap.Indicativo_fecha_vcmto as indicativo_fecha_vcmto',
                'ap.id_actividad_prerequisito as id_actividad_prerequisito',
                'app.id as actividades_por_proyecto_id',
                'app.fecha_ejecucion',
                'app.fecha_compromiso',
                'app.observaciones',
                'app.nombre_archivo',
                'app.estado_actividad',
                'app.usuario_creacion_id',
                'app.usuario_creacion_nombre',
                'app.usuario_modificacion_id',
                'app.usuario_modificacion_nombre',
                'app.updated_at as fecha_modificacion',
                'app.created_at as fecha_creacion',
            ]);

        if (isset($dto['estado']) && $dto['estado'] !== '') {
            $estado = strtoupper($dto['estado']);

            if ($estado === 'PEN') {
                $query->where(function ($q) {
                    $q->whereNull('app.estado_actividad')
                    ->orWhere('app.estado_actividad', '');
                });
            }
        }
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre_proyecto'){
                    $query->orderBy('p.nombre', $value);
                }
                if($attribute == 'tipo_proyecto'){
                    $query->orderBy('tp.nombre', $value);
                }
                if($attribute == 'secuencia'){
                    $query->orderBy('app.secuencia', $value);
                }
                if($attribute == 'etapa_proyecto'){
                    $query->orderBy('ep.nombre', $value);
                }
                if($attribute == 'etapa_secuencia'){
                    $query->orderBy('ep.secuencia', $value);
                }
                if($attribute == 'nombre_actividad'){
                    $query->orderBy('ap.nombre', $value);
                }
                if($attribute == 'secuencia_actividad'){
                    $query->orderBy('ap.secuencia', $value);
                }
                if($attribute == 'Indicativo_fecha_vcmto'){
                    $query->orderBy('ap.Indicativo_fecha_vcmto', $value);
                }
                if($attribute == 'fecha_ejecucion'){
                    $query->orderBy('app.fecha_ejecucion', $value);
                }
                if($attribute == 'fecha_compromiso'){
                    $query->orderBy('app.fecha_compromiso', $value);
                }
                if($attribute == 'observaciones'){
                    $query->orderBy('app.observaciones', $value);
                }
                if($attribute == 'nombre_archivo'){
                    $query->orderBy('app.nombre_archivo', $value);
                }
                if($attribute == 'estado_actividad'){
                    $query->orderBy('app.estado_actividad', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('app.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('app.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('app.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('app.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("ep.secuencia", "asc");
            $query->orderBy("ep.nombre", "asc");
            $query->orderBy("ap.secuencia", "asc");
        }

        $actividades_por_proyecto = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $actividades_por_proyecto->items();
    
        return [
            'datos' => $data,
            'desde' => $actividades_por_proyecto->firstItem(),
            'hasta' => $actividades_por_proyecto->lastItem(),
            'por_pagina' => $actividades_por_proyecto->perPage(),
            'pagina_actual' => $actividades_por_proyecto->currentPage(),
            'ultima_pagina' => $actividades_por_proyecto->lastPage(),
            'total' => $actividades_por_proyecto->total(),
        ];
    }

    public static function obtenerColeccionEjecutadas($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('proyectos as p')
            ->join('tipos_proyectos as tp', 'tp.id', '=', 'p.id_tipo_proyecto')
            ->leftJoin('ciudades as ciu', 'ciu.id', '=', 'p.ciudad_id')
            // Catálogo de actividades para el tipo de proyecto del proyecto
            ->join('actividades_proyectos as ap', 'ap.id_tipo_proyecto', '=', 'p.id_tipo_proyecto')
            // Etapa a la que pertenece la actividad del catálogo
            ->leftJoin('etapas_proyectos as ep', 'ep.id', '=', 'ap.id_etapa_proyecto')
            // Lo que tiene el proyecto (si ya registró esa actividad)
            ->leftJoin('actividades_por_proyecto as app', function ($j) {
                $j->on('app.id_proyecto', '=', 'p.id')
                ->on('app.id_actividad_proyecto', '=', 'ap.id')
                ->on('app.id_etapa_proyecto', '=', 'ap.id_etapa_proyecto');
            })
            ->where('p.id', $dto['id'])
            ->whereIn('app.estado_actividad', ['EJE', 'TER'])
            ->select([
                'app.id as id',
                'p.id as id_proyecto',
                'p.codigo_proyecto',
                'p.nombre as nombre_proyecto',
                'tp.id as id_tipo_proyecto',
                'tp.nombre as tipo_proyecto',
                'ciu.id as ciudad_id',
                'ciu.nombre as ciudad_proyecto',
                'ep.id as id_etapa_proyecto',
                'ep.nombre as etapa_proyecto',
                'ep.secuencia as etapa_secuencia',
                'ap.id as id_actividad_proyecto',
                'ap.nombre as nombre_actividad',
                'ap.secuencia as secuencia_actividad',
                'ap.Indicativo_fecha_vcmto as indicativo_fecha_vcmto',
                'ap.id_actividad_prerequisito as id_actividad_prerequisito',
                'app.id as actividades_por_proyecto_id',
                'app.fecha_ejecucion',
                'app.fecha_compromiso',
                'app.observaciones',
                'app.nombre_archivo',
                'app.estado_actividad',
                'app.usuario_creacion_id',
                'app.usuario_creacion_nombre',
                'app.usuario_modificacion_id',
                'app.usuario_modificacion_nombre',
                'app.updated_at as fecha_modificacion',
                'app.created_at as fecha_creacion',
            ]);
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'nombre_proyecto'){
                    $query->orderBy('p.nombre', $value);
                }
                if($attribute == 'tipo_proyecto'){
                    $query->orderBy('tp.nombre', $value);
                }
                if($attribute == 'secuencia'){
                    $query->orderBy('app.secuencia', $value);
                }
                if($attribute == 'etapa_proyecto'){
                    $query->orderBy('ep.nombre', $value);
                }
                if($attribute == 'etapa_secuencia'){
                    $query->orderBy('ep.secuencia', $value);
                }
                if($attribute == 'nombre_actividad'){
                    $query->orderBy('ap.nombre', $value);
                }
                if($attribute == 'secuencia_actividad'){
                    $query->orderBy('ap.secuencia', $value);
                }
                if($attribute == 'Indicativo_fecha_vcmto'){
                    $query->orderBy('ap.Indicativo_fecha_vcmto', $value);
                }
                if($attribute == 'fecha_ejecucion'){
                    $query->orderBy('app.fecha_ejecucion', $value);
                }
                if($attribute == 'fecha_compromiso'){
                    $query->orderBy('app.fecha_compromiso', $value);
                }
                if($attribute == 'observaciones'){
                    $query->orderBy('app.observaciones', $value);
                }
                if($attribute == 'nombre_archivo'){
                    $query->orderBy('app.nombre_archivo', $value);
                }
                if($attribute == 'estado_actividad'){
                    $query->orderBy('app.estado_actividad', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('app.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('app.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('app.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('app.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("ep.secuencia", "asc");
            $query->orderBy("ep.nombre", "asc");
            $query->orderBy("ap.secuencia", "asc");
        }

        $actividades_por_proyecto = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $actividades_por_proyecto->items();
    
        return [
            'datos' => $data,
            'desde' => $actividades_por_proyecto->firstItem(),
            'hasta' => $actividades_por_proyecto->lastItem(),
            'por_pagina' => $actividades_por_proyecto->perPage(),
            'pagina_actual' => $actividades_por_proyecto->currentPage(),
            'ultima_pagina' => $actividades_por_proyecto->lastPage(),
            'total' => $actividades_por_proyecto->total(),
        ];
    }

    public static function cargar($id)
    {
        $actividades_por_proyecto = ActividadPorProyecto::find($id);

        return [
            'id' => $actividades_por_proyecto->id,
            'id_proyecto' => $actividades_por_proyecto->id_proyecto, 
            'id_etapa_proyecto' => $actividades_por_proyecto->id_etapa_proyecto,
            'id_actividad_proyecto' => $actividades_por_proyecto->id_actividad_proyecto,
            'fecha_ejecucion' => $actividades_por_proyecto->fecha_ejecucion,
            'fecha_compromiso' => $actividades_por_proyecto->fecha_compromiso,
            'observaciones' => $actividades_por_proyecto->observaciones,
            'nombre_archivo' => $actividades_por_proyecto->nombre_archivo,
            'estado_actividad' => $actividades_por_proyecto->estado_actividad,
            'usuario_creacion_id' => $actividades_por_proyecto->usuario_creacion_id,
            'usuario_creacion_nombre' => $actividades_por_proyecto->usuario_creacion_nombre,
            'usuario_modificacion_id' => $actividades_por_proyecto->usuario_modificacion_id,
            'usuario_modificacion_nombre' => $actividades_por_proyecto->usuario_modificacion_nombre,
            'fecha_creacion' => (new Carbon($actividades_por_proyecto->created_at))->format("Y-m-d H:i:s"),
            'fecha_modificacion' => (new Carbon($actividades_por_proyecto->updated_at))->format("Y-m-d H:i:s")
        ];
    }

    public static function cargarHead($datos)
    {

       $actividades_por_proyecto = DB::table('proyectos as p')
        ->join('tipos_proyectos as tp', 'tp.id', '=', 'p.id_tipo_proyecto')
        ->leftJoin('ciudades as ciu', 'ciu.id', '=', 'p.ciudad_id')

        // Catálogo de actividades para el tipo de proyecto del proyecto
        ->leftJoin('actividades_proyectos as ap', 'ap.id_tipo_proyecto', '=', 'p.id_tipo_proyecto')

        // Etapa a la que pertenece la actividad del catálogo
        ->leftJoin('etapas_proyectos as ep', 'ep.id', '=', 'ap.id_etapa_proyecto')

        ->where('p.id', $datos['id'])

        ->select([
            // Proyecto
            'p.id as id_proyecto',
            'p.codigo_proyecto',
            'p.nombre as nombre_proyecto',
            'p.valor_total_proyecto',
            'p.potencia',
            'p.generacion_anual',
            'p.generacion_mensual',
            'p.anios_depreciacion',
            'tp.id as id_tipo_proyecto',
            'tp.nombre as tipo_proyecto',
            'ciu.nombre as ciudad_proyecto',
            // Etapa (catálogo)
            'ep.id as id_etapa_proyecto',
            'ep.nombre as etapa_proyecto',
            'ep.secuencia as etapa_secuencia',
            // Actividad (catálogo)
            'ap.id as id_actividad_proyecto',
            'ap.nombre as nombre_actividad',
            'ap.secuencia as secuencia_actividad',
            'ap.Indicativo_fecha_vcmto as indicativo_fecha_vcmto',
            // Registro en el proyecto (si existe)
        ])

        // Orden lógico: etapas y luego actividades
        ->orderBy('ep.secuencia')
        ->orderBy('ap.secuencia')
        ->first();

        return [
            'id_proyecto' => $actividades_por_proyecto->id_proyecto,
            'codigo_proyecto' => $actividades_por_proyecto->codigo_proyecto,
            'nombre_proyecto' => $actividades_por_proyecto->nombre_proyecto,
            'id_tipo_proyecto' => $actividades_por_proyecto->id_tipo_proyecto,
            'tipo_proyecto' => $actividades_por_proyecto->tipo_proyecto,
            'ciudad_proyecto' => $actividades_por_proyecto->ciudad_proyecto,
            'valor_total_proyecto' => $actividades_por_proyecto->valor_total_proyecto,
            'potencia' => $actividades_por_proyecto->potencia,
            'generacion_anual' => $actividades_por_proyecto->generacion_anual,
            'generacion_mensual' => $actividades_por_proyecto->generacion_mensual,
            'anios_depreciacion' => $actividades_por_proyecto->anios_depreciacion,
            'id_etapa_proyecto' => $actividades_por_proyecto->id_etapa_proyecto,
            'etapa_proyecto' => $actividades_por_proyecto->etapa_proyecto,
            'etapa_secuencia' => $actividades_por_proyecto->etapa_secuencia,
            'id_actividad_proyecto' => $actividades_por_proyecto->id_actividad_proyecto,
            'nombre_actividad' => $actividades_por_proyecto->nombre_actividad,
            'secuencia_actividad' => $actividades_por_proyecto->secuencia_actividad
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
        $actividades_por_proyecto = isset($dto['id']) ? ActividadPorProyecto::find($dto['id']) : new ActividadPorProyecto();

        // Guardar objeto original para auditoria
        $inversionistas_contactosOriginal = $actividades_por_proyecto->toJson();
        
        // guardar o eliminar archivo de actividades
        $idProyecto = $dto['id_proyecto'] ?? $actividades_por_proyecto->id_proyecto;
        $idEtapa = $dto['id_etapa_proyecto'] ?? $actividades_por_proyecto->id_etapa_proyecto;
        $idActividad = $dto['id_actividad_proyecto'] ?? $actividades_por_proyecto->id_actividad_proyecto;
        if (!$idProyecto) {
            throw new \RuntimeException('id_proyecto es obligatorio para gestionar el archivo.');
        }
     
        $oldName = $actividades_por_proyecto->nombre_archivo;
        $newName = $dto['nombre_archivo'] ?? null;
        $archivo = $dto['archivo'] ?? null;

        // === CASO 1: BORRAR ARCHIVO (nombre_archivo viene vacío o null) ===
        if (($newName === '' || $newName === null) && !$archivo) {
            if ($oldName) {
                $rutaArchivo = "public/actividades_proyecto/P{$idProyecto}/E{$idEtapa}/A{$idActividad}/{$oldName}";
                if (Storage::exists($rutaArchivo)) {
                    Storage::delete($rutaArchivo);
                }
                $actividades_por_proyecto->nombre_archivo = null;
            }
        }

        // === CASO 2: SUBIR O REEMPLAZAR ARCHIVO ===
        if ($archivo) {
            $archivo = $dto['archivo'];

            // Genera nombre único (para evitar colisiones con otro del mismo nombre)
            $nombreFinal = $dto['nombre_archivo'];

            // RUTA BASE (misma que ya usas para borrar/guardar)
            $rutaCarpeta = "public/actividades_proyecto/P{$idProyecto}/E{$idEtapa}/A{$idActividad}";

            // Crear la carpeta 
            Storage::makeDirectory($rutaCarpeta, 0755, true);
            

            // Borra el anterior si existía
            if ($oldName) {
                $rutaArchivoViejo = "public/actividades_proyecto/P{$idProyecto}/E{$idEtapa}/A{$idActividad}/{$oldName}";
                if (Storage::exists($rutaArchivoViejo)) {
                    Storage::delete($rutaArchivoViejo);
                }
            }

            // Guarda el nuevo
            $archivo->storeAs("public/actividades_proyecto/P{$idProyecto}/E{$idEtapa}/A{$idActividad}", $nombreFinal);
            $actividades_por_proyecto->nombre_archivo = $nombreFinal;
            $dto['nombre_archivo'] = $nombreFinal;
        }
      

        $actividades_por_proyecto->fill($dto);
        $guardado = $actividades_por_proyecto->save();
        if(!$guardado){
            throw new Exception("Ocurrió un error al intentar guardar la Actividad Por Proyecto.", $actividades_por_proyecto);
        }

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $actividades_por_proyecto->id,
            'nombre_recurso' => ActividadPorProyecto::class,
            'descripcion_recurso' => 'Proyecto-'.$actividades_por_proyecto->id_proyecto .'Etapa-'. $actividades_por_proyecto->id_etapa_proyecto .'Actividad-'. $actividades_por_proyecto->id_actividad_proyecto ,
            'accion' => isset($dto['id']) ? AccionAuditoriaEnum::MODIFICAR : AccionAuditoriaEnum::CREAR,
            'recurso_original' => isset($dto['id']) ? $inversionistas_contactosOriginal : $actividades_por_proyecto->toJson(),
            'recurso_resultante' => isset($dto['id']) ? $actividades_por_proyecto->toJson() : null
        );
        AuditoriaTabla::crear($auditoriaDto);

        return ActividadPorProyecto::cargar($actividades_por_proyecto->id);
    }

    public static function eliminar($id)
    {
        // Connsultar el objeto
        $actividades_por_proyecto = ActividadPorProyecto::find($id);

        // Guardar auditoria
        $auditoriaDto = array(
            'id_recurso' => $actividades_por_proyecto->id,
            'nombre_recurso' => ActividadPorProyecto::class,
            'descripcion_recurso' => 'Proyecto-'.$actividades_por_proyecto->id_proyecto .'Etapa-'. $actividades_por_proyecto->id_etapa_proyecto .'Actividad-'. $actividades_por_proyecto->id_actividad_proyecto,
            'accion' => AccionAuditoriaEnum::ELIMINAR,
            'recurso_original' => $actividades_por_proyecto->toJson()
        );
        AuditoriaTabla::crear($auditoriaDto);

        return $actividades_por_proyecto->delete();
    }
}
