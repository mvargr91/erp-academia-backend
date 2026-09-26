<?php

namespace App\Models\Experiencias;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExperienciaCategoria extends Model
{
    use HasFactory;

    protected $table = 'experiencia_categoria';

    protected $fillable = [
        'experiencia_id',
        'categoria_id',
    ];

    public static function obtenerColeccion($dto)
    {
        return DB::table('experiencia_categoria')
            ->join('categorias', 'categorias.id', '=', 'experiencia_categoria.categoria_id')
            ->where('experiencia_categoria.experiencia_id', $dto['experiencia_id'])
            ->select(
                'experiencia_categoria.id',
                'experiencia_categoria.experiencia_id',
                'experiencia_categoria.categoria_id',
                'categorias.nombre as categoria_nombre',
            )
            ->get();
    }

    public static function crear($dto)
    {
        $existe = ExperienciaCategoria::where('experiencia_id', $dto['experiencia_id'])
            ->where('categoria_id', $dto['categoria_id'])
            ->first();

        if ($existe) {
            return $existe;
        }

        $relacion = new ExperienciaCategoria();
        $relacion->fill($dto);
        $guardado = $relacion->save();
        if (!$guardado) {
            throw new Exception('Ocurrió un error al intentar asignar la categoría a la experiencia.');
        }

        return $relacion;
    }

    public static function eliminar($id)
    {
        $relacion = ExperienciaCategoria::find($id);
        return $relacion->delete();
    }
}
