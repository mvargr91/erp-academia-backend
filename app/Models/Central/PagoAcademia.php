<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/** Pago recibido de una academia, aplicado a una cuenta de cobro. */
class PagoAcademia extends Model
{
    protected $connection = 'central';

    protected $table = 'pagos_academia';

    protected $fillable = [
        'academia_id', 'factura_id', 'valor', 'fecha_pago', 'metodo',
        'referencia', 'soporte', 'observacion', 'registrado_por',
    ];

    protected $casts = [
        'fecha_pago' => 'date',
        'valor' => 'float',
    ];

    public function academia()
    {
        return $this->belongsTo(Academia::class);
    }

    public function factura()
    {
        return $this->belongsTo(FacturaAcademia::class, 'factura_id');
    }
}
