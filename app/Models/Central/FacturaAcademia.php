<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/** Cuenta de cobro mensual del ERP a una academia. */
class FacturaAcademia extends Model
{
    protected $connection = 'central';

    protected $table = 'facturas_academia';

    public const PENDIENTE = 'pendiente';
    public const PAGADA = 'pagada';
    public const ANULADA = 'anulada';

    protected $fillable = ['academia_id', 'periodo', 'fecha_vencimiento', 'valor', 'saldo', 'estado', 'observacion'];

    protected $casts = [
        'periodo' => 'date',
        'fecha_vencimiento' => 'date',
        'valor' => 'float',
        'saldo' => 'float',
    ];

    public function academia()
    {
        return $this->belongsTo(Academia::class);
    }

    public function pagos()
    {
        return $this->hasMany(PagoAcademia::class, 'factura_id');
    }

    /** Número visible de la cuenta de cobro. */
    public function numero(): string
    {
        return 'CC-' . str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }
}
