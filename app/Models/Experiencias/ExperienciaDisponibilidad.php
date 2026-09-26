<?php

namespace App\Models\Experiencias;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExperienciaDisponibilidad extends Model
{
    use HasFactory;

    protected $table = 'experiencia_disponibilidad';

    protected $fillable = [
        'experiencia_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'capacidad',
        'cupos_disponibles',
        'estado',
    ];

    public static function obtenerColeccion($dto)
    {
        $query = DB::table('experiencia_disponibilidad')
            ->select(
                'experiencia_disponibilidad.id',
                'experiencia_disponibilidad.experiencia_id',
                'experiencia_disponibilidad.fecha',
                'experiencia_disponibilidad.hora_inicio',
                'experiencia_disponibilidad.hora_fin',
                'experiencia_disponibilidad.capacidad',
                'experiencia_disponibilidad.cupos_disponibles',
                'experiencia_disponibilidad.estado',
            )
            ->where('experiencia_disponibilidad.experiencia_id', $dto['experiencia_id']);

        if (isset($dto['fecha_desde'])) {
            $query->where('experiencia_disponibilidad.fecha', '>=', $dto['fecha_desde']);
        }
        if (isset($dto['fecha_hasta'])) {
            $query->where('experiencia_disponibilidad.fecha', '<=', $dto['fecha_hasta']);
        }

        $query->orderBy('experiencia_disponibilidad.fecha', 'asc');

        return $query->get();
    }

    public static function cargar($id)
    {
        return ExperienciaDisponibilidad::find($id);
    }

    public static function modificarOCrear($dto)
    {
        $disponibilidad = isset($dto['id']) ? ExperienciaDisponibilidad::find($dto['id']) : new ExperienciaDisponibilidad();

        if (!isset($dto['id']) && !isset($dto['cupos_disponibles'])) {
            $dto['cupos_disponibles'] = $dto['capacidad'];
        }

        $disponibilidad->fill($dto);
        $guardado = $disponibilidad->save();
        if (!$guardado) {
            throw new Exception('Ocurrió un error al intentar guardar la disponibilidad de la experiencia.');
        }

        return $disponibilidad;
    }

    public static function eliminar($id)
    {
        $disponibilidad = ExperienciaDisponibilidad::find($id);
        return $disponibilidad->delete();
    }
}
