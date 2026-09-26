<?php

namespace App\Models\Seguridad;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InicioSesion extends Model
{
    use HasFactory;
    
    protected $table = 'inicios_sesion';

    protected $fillable = [
        'cliente_id',
        'usuario_id',
        'longitud',
        'latitud',
    ];
}
