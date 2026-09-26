<?php

namespace App\Models\Experiencias;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExperienciaHorario extends Model
{
    use HasFactory;

    protected $table = 'experiencia_horarios';

    protected $fillable = [
        'experiencia_id',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
        'capacidad',
        'estado',
    ];

    public static function obtenerColeccion($dto)
    {
        $query = DB::table('experiencia_horarios')
            ->select(
                'experiencia_horarios.id',
                'experiencia_horarios.experiencia_id',
                'experiencia_horarios.dia_semana',
                'experiencia_horarios.hora_inicio',
                'experiencia_horarios.hora_fin',
                'experiencia_horarios.capacidad',
                'experiencia_horarios.estado',
            )
            ->where('experiencia_horarios.experiencia_id', $dto['experiencia_id'])
            ->orderBy('experiencia_horarios.dia_semana', 'asc');

        return $query->get();
    }

    public static function cargar($id)
    {
        return ExperienciaHorario::find($id);
    }

    public static function modificarOCrear($dto)
    {
        $horario = isset($dto['id']) ? ExperienciaHorario::find($dto['id']) : new ExperienciaHorario();
        $horario->fill($dto);
        $guardado = $horario->save();
        if (!$guardado) {
            throw new Exception('Ocurrió un error al intentar guardar el horario de la experiencia.');
        }

        return $horario;
    }

    public static function eliminar($id)
    {
        $horario = ExperienciaHorario::find($id);
        return $horario->delete();
    }
}
