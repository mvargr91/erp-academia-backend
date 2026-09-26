<?php

namespace App\Models\Reservas;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AcompananteReserva extends Model
{
    use HasFactory;

    protected $table = 'acompanantes_reserva';

    protected $fillable = [
        'reserva_id',
        'nombre',
        'correo',
        'telefono',
        'documento',
        'edad',
    ];

    public static function obtenerColeccion($dto)
    {
        return DB::table('acompanantes_reserva')
            ->where('reserva_id', $dto['reserva_id'])
            ->get();
    }

    public static function cargar($id)
    {
        return AcompananteReserva::find($id);
    }

    public static function modificarOCrear($dto)
    {
        $acompanante = isset($dto['id']) ? AcompananteReserva::find($dto['id']) : new AcompananteReserva();
        $acompanante->fill($dto);
        $guardado = $acompanante->save();
        if (!$guardado) {
            throw new Exception('Ocurrió un error al intentar guardar el acompañante de la reserva.');
        }

        return $acompanante;
    }

    public static function eliminar($id)
    {
        $acompanante = AcompananteReserva::find($id);
        return $acompanante->delete();
    }
}
