<?php

namespace App\Models\Promociones;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PromocionExperiencia extends Model
{
    use HasFactory;

    protected $table = 'promocion_experiencia';

    protected $fillable = [
        'promocion_id',
        'experiencia_id',
    ];

    public static function obtenerColeccion($dto)
    {
        return DB::table('promocion_experiencia')
            ->join('experiencias', 'experiencias.id', '=', 'promocion_experiencia.experiencia_id')
            ->where('promocion_experiencia.promocion_id', $dto['promocion_id'])
            ->select(
                'promocion_experiencia.id',
                'promocion_experiencia.promocion_id',
                'promocion_experiencia.experiencia_id',
                'experiencias.nombre as experiencia_nombre',
            )
            ->get();
    }

    public static function crear($dto)
    {
        $existe = PromocionExperiencia::where('promocion_id', $dto['promocion_id'])
            ->where('experiencia_id', $dto['experiencia_id'])
            ->first();

        if ($existe) {
            return $existe;
        }

        $relacion = new PromocionExperiencia();
        $relacion->fill($dto);
        $guardado = $relacion->save();
        if (!$guardado) {
            throw new Exception('Ocurrió un error al intentar asignar la experiencia a la promoción.');
        }

        return $relacion;
    }

    public static function eliminar($id)
    {
        $relacion = PromocionExperiencia::find($id);
        return $relacion->delete();
    }
}
