<?php

namespace App\Models\Experiencias;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExperienciaCaracteristica extends Model
{
    use HasFactory;

    protected $table = 'experiencia_caracteristica';

    protected $fillable = [
        'experiencia_id',
        'caracteristica_id',
        'incluida',
    ];

    public static function obtenerColeccion($dto)
    {
        return DB::table('experiencia_caracteristica')
            ->join('caracteristicas', 'caracteristicas.id', '=', 'experiencia_caracteristica.caracteristica_id')
            ->where('experiencia_caracteristica.experiencia_id', $dto['experiencia_id'])
            ->select(
                'experiencia_caracteristica.id',
                'experiencia_caracteristica.experiencia_id',
                'experiencia_caracteristica.caracteristica_id',
                'caracteristicas.nombre as caracteristica_nombre',
                'experiencia_caracteristica.incluida',
            )
            ->get();
    }

    public static function modificarOCrear($dto)
    {
        $relacion = isset($dto['id'])
            ? ExperienciaCaracteristica::find($dto['id'])
            : ExperienciaCaracteristica::where('experiencia_id', $dto['experiencia_id'])
                ->where('caracteristica_id', $dto['caracteristica_id'])
                ->first();

        if (!$relacion) {
            $relacion = new ExperienciaCaracteristica();
        }

        $relacion->fill($dto);
        $guardado = $relacion->save();
        if (!$guardado) {
            throw new Exception('Ocurrió un error al intentar guardar la característica de la experiencia.');
        }

        return $relacion;
    }

    public static function eliminar($id)
    {
        $relacion = ExperienciaCaracteristica::find($id);
        return $relacion->delete();
    }
}
