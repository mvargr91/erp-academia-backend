<?php

namespace App\Models\Seguridad;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AuditoriaTabla extends Model
{
    protected $fillable = [
        'id_recurso',
        'nombre_recurso',
        'descripcion_recurso',
        'accion',
        'responsable_id',
        'responsable_nombre',
        'recurso_original',
        'recurso_resultante'
    ];

    public static function crear($dto)
    {
        if (!isset($dto['externo'])){
            $user = Auth::user();
            $usuario = $user->usuario();
            $dto['responsable_id'] = $usuario->id ?? ($dto['responsable_id'] ?? null);
            $dto['responsable_nombre'] = $usuario->nombre ?? ($dto['responsable_nombre'] ?? null);
        } else {
            $dto['responsable_id'] =0;
            $dto['responsable_nombre'] = 'Cliente Externo';
        }
        
        $auditoria = AuditoriaTabla::create($dto);
        
        if(!isset($auditoria)){
            throw new ModelException(
                "Ocurrió un error al intentar guardar la auditoria del recurso.",
                $auditoria
            );
        }

        return null;
    }

    public static function obtenerColeccion($dto){
        $query = DB::table('auditoria_tablas')
            ->select(
                'id',
                'id_recurso',
                'nombre_recurso',
                'descripcion_recurso',
                'accion',
                'responsable_id',
                'responsable_nombre',
                'recurso_original',
                'recurso_resultante',
                'created_at AS fecha'
            );

        if(isset($dto['nombre_recurso'])){
            $query->where('nombre_recurso', 'like', "%" . $dto['nombre_recurso'] . "%");
        }
        if(isset($dto['descripcion_recurso'])){
            $query->where('descripcion_recurso', 'like', '%' . $dto['descripcion_recurso'] . '%');
        }
        if(isset($dto['fecha_desde'])){
            $query->where('created_at', '>=', $dto['fecha_desde'] . ' 00:00:00');
        }
        if(isset($dto['fecha_hasta'])){
            $query->where('created_at', '<=', $dto['fecha_hasta'] . ' 23:59:59');
        }
        if(isset($dto['accion'])){
            $query->where('accion', 'like', "%" . $dto['accion'] . "%");
        }
        if (isset($dto['ordenar_por']) && count($dto['ordenar_por']) > 0){
            foreach ($dto['ordenar_por'] as $attribute => $value){
                if($attribute == 'id'){
                    $query->orderBy('id', $value);
                }
                if($attribute == 'nombre_recurso'){
                    $query->orderBy('nombre_recurso', $value);
                }
                if($attribute == 'descripcion_recurso'){
                    $query->orderBy('descripcion_recurso', $value);
                }
                if($attribute == 'accion'){
                    $query->orderBy('accion', $value);
                }
                if($attribute == 'responsable_nombre'){
                    $query->orderBy('responsable_nombre', $value);
                }
                if($attribute == 'fecha'){
                    $query->orderBy('created_at', $value);
                }
            }
        }else{
            $query->orderBy("created_at", "desc");
        }

        $auditorias = $query->paginate($dto['limite'] ?? 100);
        $datos = [];
        foreach ($auditorias ?? [] as $auditoria){
            array_push($datos, $auditoria);
        }

        $cantidadAuditorias = count($auditorias ?? []);
        $to = isset($auditorias) && $cantidadAuditorias > 0 ? $auditorias->currentPage() * $auditorias->perPage() : null;
        $to = isset($to) && isset($auditorias) && $to > $auditorias->total() && $cantidadAuditorias > 0 ? $auditorias->total() : $to;
        $from = isset($to) && isset($auditorias) && $cantidadAuditorias > 0 ?
            ( $auditorias->perPage() > $to ? 1 : ($to - $cantidadAuditorias) + 1 )
            : null;
        return [
            'datos' => $datos,
            'desde' => $from,
            'hasta' => $to,
            'por_pagina' => isset($auditorias) && $cantidadAuditorias > 0 ? +$auditorias->perPage() : 0,
            'pagina_actual' => isset($auditorias) && $cantidadAuditorias > 0 ? $auditorias->currentPage() : 1,
            'ultima_pagina' => isset($auditorias) && $cantidadAuditorias > 0 ? $auditorias->lastPage() : 0,
            'total' => isset($auditorias) && $cantidadAuditorias > 0 ? $auditorias->total() : 0
        ];
    }
    
    use HasFactory;
}
