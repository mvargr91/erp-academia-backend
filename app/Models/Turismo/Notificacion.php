<?php

namespace App\Models\Turismo;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Notificacion extends Model
{
    use HasFactory;

    protected $table = 'notificaciones';

    protected $fillable = [
        'usuario_id',
        'tipo',
        'titulo',
        'mensaje',
        'leido',
        'fecha_lectura',
    ];

    public static function obtenerColeccion($dto)
    {
        $query = DB::table('notificaciones')
            ->select(
                'notificaciones.id',
                'notificaciones.usuario_id',
                'notificaciones.tipo',
                'notificaciones.titulo',
                'notificaciones.mensaje',
                'notificaciones.leido',
                'notificaciones.fecha_lectura',
                'notificaciones.created_at as fecha_creacion',
            )
            ->where('notificaciones.usuario_id', $dto['usuario_id']);

        if (isset($dto['leido'])) {
            $query->where('notificaciones.leido', $dto['leido']);
        }

        $query->orderBy('notificaciones.created_at', 'desc');

        $notificaciones = $query->paginate($dto['limite'] ?? 100);
        $data = $notificaciones->items();

        return [
            'datos' => $data,
            'desde' => $notificaciones->firstItem(),
            'hasta' => $notificaciones->lastItem(),
            'por_pagina' => $notificaciones->perPage(),
            'pagina_actual' => $notificaciones->currentPage(),
            'ultima_pagina' => $notificaciones->lastPage(),
            'total' => $notificaciones->total(),
        ];
    }

    public static function crear($dto)
    {
        $notificacion = new Notificacion();
        $notificacion->fill($dto);
        $guardado = $notificacion->save();
        if (!$guardado) {
            throw new Exception('Ocurrió un error al intentar guardar la notificación.');
        }

        return $notificacion;
    }

    public static function marcarLeida($id)
    {
        $notificacion = Notificacion::find($id);
        $notificacion->leido = true;
        $notificacion->fecha_lectura = now();
        return $notificacion->save();
    }

    public static function eliminar($id)
    {
        $notificacion = Notificacion::find($id);
        return $notificacion->delete();
    }
}
