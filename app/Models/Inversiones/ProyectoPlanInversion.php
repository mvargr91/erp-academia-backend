<?php

namespace App\Models\Inversiones;

use Exception;
use Carbon\Carbon;
use App\Enum\AccionAuditoriaEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use App\Models\Seguridad\AuditoriaTabla;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProyectoPlanInversion extends Model
{

    use HasFactory;

    protected $table = 'proyectos_plan_inversiones';

    protected $fillable = [
        'id_inversionista',
        'id_proyecto',
        'fecha_plan_inversion',
        'valor_plan_inversion',
        'fecha_pago_inversion',
        'estado_plan_inversion',
        'usuario_creacion_id',
        'usuario_creacion_nombre',
        'usuario_modificacion_id',
        'usuario_modificacion_nombre',
    ];

    public static function obtenerColeccionLigera($dto){
     
        $query = DB::table('proyectos_plan_inversiones')
            ->select(
                'proyectos_plan_inversiones.id',
                'proyectos_plan_inversiones.id_inversionista',
                'proyectos_plan_inversiones.id_proyecto',
                'proyectos_plan_inversiones.fecha_plan_inversion',
                'proyectos_plan_inversiones.valor_plan_inversion',
                'proyectos_plan_inversiones.fecha_pago_inversion',
                'proyectos_plan_inversiones.estado_plan_inversion',
            );
        $query->orderBy('fecha_plan_inversion', 'asc');
        return $query->get();
    }


    public static function obtenerColeccion($dto){
        $user = Auth::user();
        $usuario = $user->usuario();
        $rol = $user->rol();

        $query = DB::table('proyectos_plan_inversiones')
        ->join('proyectos', 'proyectos.id', 'proyectos_plan_inversiones.id_proyecto')
        ->join('inversionistas', 'inversionistas.id', 'proyectos_plan_inversiones.id_inversionista')
        ->select(     
            DB::raw("CONCAT(proyectos_plan_inversiones.id_inversionista, '-', proyectos_plan_inversiones.id_proyecto) as id"),
            'proyectos_plan_inversiones.id_proyecto',
            'proyectos.nombre as nombre_proyecto',
            'proyectos.codigo_proyecto',
            'proyectos.valor_inversion_proyecto',
            'proyectos_plan_inversiones.id_inversionista',
            'inversionistas.nombre as inversionista',
            DB::raw('SUM(proyectos_plan_inversiones.valor_plan_inversion) as total_plan_inversion'),     
            'proyectos_plan_inversiones.updated_at as fecha_modificacion',
            'proyectos_plan_inversiones.created_at as fecha_creacion',
            'proyectos_plan_inversiones.usuario_creacion_nombre',
            'proyectos_plan_inversiones.usuario_modificacion_nombre',
        )->groupBy(
            'proyectos_plan_inversiones.id_proyecto',
            'proyectos.nombre',
            'proyectos.codigo_proyecto',
            'proyectos.valor_inversion_proyecto',
            'proyectos_plan_inversiones.id_inversionista',
            'inversionistas.nombre',
            'proyectos_plan_inversiones.updated_at',
            'proyectos_plan_inversiones.created_at',
            'proyectos_plan_inversiones.usuario_creacion_nombre',
            'proyectos_plan_inversiones.usuario_modificacion_nombre',
        );

        if(isset($dto['inversionista'])){
            $query->where('inversionistas.nombre', $dto['inversionista'] );
        }

        if(isset($dto['id_proyecto'])){
            $query->where('proyectos_plan_inversiones.id_proyecto', $dto['id_proyecto'] );
        }       
        
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'codigo_proyecto'){
                    $query->orderBy('proyectos.codigo_proyecto', $value);
                }
                if($attribute == 'nombre_proyecto'){
                    $query->orderBy('proyectos.nombre', $value);
                } 
                if($attribute == 'valor_inversion_proyecto'){
                    $query->orderBy('proyectos.valor_inversion_proyecto', $value);
                }
                if($attribute == 'inversionista'){
                    $query->orderBy('inversionistas.nombre', $value);
                }
                if($attribute == 'valor_plan_inversion'){
                    $query->orderBy('proyectos_plan_inversiones.valor_plan_inversion', $value);
                }
                if($attribute == 'fecha_pago_inversion'){
                    $query->orderBy('proyectos_plan_inversiones.fecha_pago_inversion', $value);
                }
                if($attribute == 'estado_plan_inversion'){
                    $query->orderBy('inversiones.estado_plan_inversion', $value);
                }
                if($attribute == 'usuario_creacion_nombre'){
                    $query->orderBy('proyectos_plan_inversiones.usuario_creacion_nombre', $value);
                }
                if($attribute == 'usuario_modificacion_nombre'){
                    $query->orderBy('proyectos_plan_inversiones.usuario_modificacion_nombre', $value);
                }
                if($attribute == 'fecha_creacion'){
                    $query->orderBy('proyectos_plan_inversiones.created_at', $value);
                }
                if($attribute == 'fecha_modificacion'){
                    $query->orderBy('proyectos_plan_inversiones.updated_at', $value);
                }
            }
        }else{
            $query->orderBy("proyectos_plan_inversiones.fecha_plan_inversion", "asc");
        }

        $proyectoPlanInversio = $query->paginate($dto['limite'] ?? 100);
    
        // Aquí simplemente conviertes el objeto paginator a array, sin contar manualmente
        $data = $proyectoPlanInversio->items();
    
        return [
            'datos' => $data,
            'desde' => $proyectoPlanInversio->firstItem(),
            'hasta' => $proyectoPlanInversio->lastItem(),
            'por_pagina' => $proyectoPlanInversio->perPage(),
            'pagina_actual' => $proyectoPlanInversio->currentPage(),
            'ultima_pagina' => $proyectoPlanInversio->lastPage(),
            'total' => $proyectoPlanInversio->total(),
        ];
    }

    public static function cargar($id_proyecto, $id_inversionista)
    {
        $registros = ProyectoPlanInversion::where('id_proyecto', $id_proyecto)
            ->where('id_inversionista', $id_inversionista)
            ->orderBy('fecha_plan_inversion')
            ->get();

        $planPagos = $registros->map(function ($item) {
            return [
                'id' => $item->id,
                'id_proyecto' => $item->id_proyecto,
                'id_inversionista' => $item->id_inversionista,
                'fecha_planeada' => $item->fecha_plan_inversion,
                'valor' => $item->valor_plan_inversion,
                'fecha_pago' => $item->fecha_pago_inversion,
                'estado_plan_inversion' => $item->estado_plan_inversion,
            ];
        });

        return [
            'id_proyecto' => $id_proyecto,
            'id_inversionista' => $id_inversionista,
            'plan_pagos' => $planPagos->values(),
        ];
    }




    public static function modificarOCrear(array $dto)
    {
        $user = Auth::user();
        $usuario = $user ? $user->usuario() : null;

        $idProyecto = $dto['id_proyecto'] ?? null;
        $idInversionista = $dto['id_inversionista'] ?? null;
        $planPagos = $dto['plan_pagos'] ?? [];

        if (!$idProyecto || !$idInversionista) {
            throw new Exception('Los campos id_proyecto e id_inversionista son obligatorios.');
        }

        if (!is_array($planPagos)) {
            throw new Exception('El campo plan_pagos debe ser un arreglo.');
        }

        // Consultar registros existentes
        // $registrosExistentes = ProyectoPlanInversion::where('id_proyecto', $idProyecto)
        //     ->where('id_inversionista', $idInversionista)
        //     ->get();

        // // Auditar eliminación si existen
        // foreach ($registrosExistentes as $registro) {
        //     $auditoriaDto = [
        //         'id_recurso' => $registro->id,
        //         'nombre_recurso' => ProyectoPlanInversion::class,
        //         'descripcion_recurso' => 'Plan Inversion Proyecto id ' . $registro->id_proyecto . ' Inversionista id ' . $registro->id_inversionista,
        //         'accion' => AccionAuditoriaEnum::ELIMINAR,
        //         'recurso_original' => $registro->toJson(),
        //         'recurso_resultante' => null,
        //     ];
        //     AuditoriaTabla::crear($auditoriaDto);
        // }

        // Eliminar todos los existentes
        ProyectoPlanInversion::where('id_proyecto', $idProyecto)
            ->where('id_inversionista', $idInversionista)
            ->delete();

        // Crear los nuevos
        foreach ($planPagos as $item) {
            $nuevo = new ProyectoPlanInversion();

            $nuevo->fill([
                'id_proyecto' => $idProyecto,
                'id_inversionista' => $idInversionista,
                'fecha_plan_inversion' => $item['fecha_planeada'] ?? null,
                'valor_plan_inversion' => $item['valor'] ?? 0,
                'fecha_pago_inversion' => $item['fecha_pago'] ?? null,
                'estado_plan_inversion' => $item['estado_plan_inversion'] ?? 'PLA',
                'usuario_creacion_id' => $usuario->id ?? null,
                'usuario_creacion_nombre' => $usuario->nombre ?? null,
                'usuario_modificacion_id' => $usuario->id ?? null,
                'usuario_modificacion_nombre' => $usuario->nombre ?? null,
            ]);

            $guardado = $nuevo->save();

            if (!$guardado) {
                throw new Exception('Ocurrió un error al intentar guardar el plan de inversión.');
            }

            $auditoriaDto = [
                'id_recurso' => $nuevo->id,
                'nombre_recurso' => ProyectoPlanInversion::class,
                'descripcion_recurso' => 'Plan Inversion Proyecto id ' . $nuevo->id_proyecto . ' Inversionista id ' . $nuevo->id_inversionista,
                'accion' => AccionAuditoriaEnum::CREAR,
                'recurso_original' => $nuevo->toJson(),
                'recurso_resultante' => null,
            ];
            AuditoriaTabla::crear($auditoriaDto);
        }

        return ProyectoPlanInversion::cargar($idProyecto, $idInversionista);
    }

    public static function eliminar($idInversionista, $idProyecto)
    {
        $registros = ProyectoPlanInversion::where('id_proyecto', $idProyecto)
            ->where('id_inversionista', $idInversionista)
            ->get();

        if ($registros->isEmpty()) {
            return false;
        }

        foreach ($registros as $registro) {
            $auditoriaDto = [
                'id_recurso' => $registro->id,
                'nombre_recurso' => ProyectoPlanInversion::class,
                'descripcion_recurso' => 'Plan Inversion Proyecto id ' . $registro->id_proyecto . ' Inversionista id ' . $registro->id_inversionista,
                'accion' => AccionAuditoriaEnum::ELIMINAR,
                'recurso_original' => $registro->toJson(),
                'recurso_resultante' => null,
            ];

            AuditoriaTabla::crear($auditoriaDto);
        }

        return ProyectoPlanInversion::where('id_proyecto', $idProyecto)
            ->where('id_inversionista', $idInversionista)
            ->delete();
    }
}
