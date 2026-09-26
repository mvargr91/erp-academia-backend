<?php

namespace App\Models\Turismo;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Favorito extends Model
{
    use HasFactory;

    protected $table = 'favoritos';

    protected $fillable = [
        'usuario_id',
        'experiencia_id',
    ];

    public static function obtenerColeccion($dto)
    {
        return DB::table('favoritos')
            ->join('experiencias', 'experiencias.id', '=', 'favoritos.experiencia_id')
            ->where('favoritos.usuario_id', $dto['usuario_id'])
            ->select(
                'favoritos.id',
                'favoritos.usuario_id',
                'favoritos.experiencia_id',
                'experiencias.nombre as experiencia_nombre',
                'experiencias.slug as experiencia_slug',
                'experiencias.precio_desde',
                'favoritos.created_at as fecha_creacion',
            )
            ->orderBy('favoritos.created_at', 'desc')
            ->get();
    }

    public static function crear($dto)
    {
        $existe = Favorito::where('usuario_id', $dto['usuario_id'])
            ->where('experiencia_id', $dto['experiencia_id'])
            ->first();

        if ($existe) {
            return $existe;
        }

        $favorito = new Favorito();
        $favorito->fill($dto);
        $guardado = $favorito->save();
        if (!$guardado) {
            throw new Exception('Ocurrió un error al intentar guardar el favorito.');
        }

        return $favorito;
    }

    public static function eliminar($id)
    {
        $favorito = Favorito::find($id);
        return $favorito->delete();
    }
}
