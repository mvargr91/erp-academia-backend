<?php

namespace App\Models\Experiencias;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExperienciaMultimedia extends Model
{
    use HasFactory;

    protected $table = 'experiencia_multimedia';

    protected $fillable = [
        'experiencia_id',
        'tipo',
        'ruta_archivo',
        'titulo',
        'texto_alternativo',
        'orden',
        'estado',
    ];

    public static function obtenerColeccion($dto)
    {
        $query = DB::table('experiencia_multimedia')
            ->select(
                'experiencia_multimedia.id',
                'experiencia_multimedia.experiencia_id',
                'experiencia_multimedia.tipo',
                'experiencia_multimedia.ruta_archivo',
                'experiencia_multimedia.titulo',
                'experiencia_multimedia.texto_alternativo',
                'experiencia_multimedia.orden',
                'experiencia_multimedia.estado',
            )
            ->where('experiencia_multimedia.experiencia_id', $dto['experiencia_id'])
            ->orderBy('experiencia_multimedia.orden', 'asc');

        return $query->get();
    }

    public static function cargar($id)
    {
        return ExperienciaMultimedia::find($id);
    }

    public static function modificarOCrear($dto)
    {
        $media = isset($dto['id']) ? ExperienciaMultimedia::find($dto['id']) : new ExperienciaMultimedia();
        $media->fill($dto);
        $guardado = $media->save();
        if (!$guardado) {
            throw new Exception('Ocurrió un error al intentar guardar la multimedia de la experiencia.');
        }

        return $media;
    }

    public static function eliminar($id)
    {
        $media = ExperienciaMultimedia::find($id);

        if (Storage::exists($media->ruta_archivo)) {
            Storage::delete($media->ruta_archivo);
        }

        return $media->delete();
    }
}
