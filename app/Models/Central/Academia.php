<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/**
 * Academia cliente del ERP (BD central). Cada una tiene su propia base de datos.
 */
class Academia extends Model
{
    protected $connection = 'central';

    protected $table = 'academias';

    protected $fillable = [
        'codigo',
        'nombre',
        'base_datos',
        'correo',
        'telefono',
        'apariencia',
        'dia_pago',
        'tarifa_mensual',
        'dia_corte',
        'fecha_inicio_cobro',
        'activa',
        'suspendida_por_mora',
        'es_administradora',
    ];

    protected $casts = [
        'apariencia' => 'array',
        'dia_pago' => 'integer',
        'tarifa_mensual' => 'float',
        'dia_corte' => 'integer',
        'fecha_inicio_cobro' => 'date',
        'activa' => 'boolean',
        'suspendida_por_mora' => 'boolean',
        'es_administradora' => 'boolean',
    ];

    public function facturas()
    {
        return $this->hasMany(FacturaAcademia::class);
    }

    /** URL pública de la academia (subdominio). */
    public function url(): string
    {
        return config('academias.protocolo') . '://' . $this->codigo . '.' . config('academias.dominio');
    }
}
