<?php

namespace App\Models\Resenas;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MultimediaResena extends Model
{
    use HasFactory;

    protected $table = 'multimedia_resena';

    protected $fillable = [
        'resena_id',
        'ruta_archivo',
    ];

    public static function obtenerColeccion($dto)
    {
        return DB::table('multimedia_resena')
            ->where('resena_id', $dto['resena_id'])
            ->get();
    }

    public static function crear($dto)
    {
        $media = new MultimediaResena();
        $media->fill($dto);
        $guardado = $media->save();
        if (!$guardado) {
            throw new Exception('Ocurrió un error al intentar guardar la multimedia de la reseña.');
        }

        return $media;
    }

    public static function eliminar($id)
    {
        $media = MultimediaResena::find($id);

        if (Storage::exists($media->ruta_archivo)) {
            Storage::delete($media->ruta_archivo);
        }

        return $media->delete();
    }
}
