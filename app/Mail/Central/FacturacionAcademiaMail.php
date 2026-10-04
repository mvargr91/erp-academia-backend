<?php

namespace App\Mail\Central;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Support\Configuracion\Configuracion;

/**
 * Correos del dueño del ERP a una academia cliente: cuenta de cobro, recordatorio,
 * mora, suspensión y confirmación de pago.
 */
class FacturacionAcademiaMail extends Mailable
{
    use Queueable, SerializesModels;

    public const FACTURA = 'factura';
    public const RECORDATORIO = 'recordatorio';
    public const MORA = 'mora';
    public const SUSPENSION = 'suspension';
    public const PAGO = 'pago';

    private const ASUNTOS = [
        self::FACTURA => 'Cuenta de cobro :numero - :marca (:periodo)',
        self::RECORDATORIO => 'Recordatorio: tu cuenta de cobro :numero vence el :vencimiento',
        self::MORA => 'Tienes un saldo vencido con :marca',
        self::SUSPENSION => 'Servicio suspendido por falta de pago - :marca',
        self::PAGO => 'Pago recibido - :marca',
    ];

    private const PLANTILLAS = [
        self::FACTURA => 'ERP_CUENTA_COBRO',
        self::RECORDATORIO => 'ERP_RECORDATORIO',
        self::MORA => 'ERP_MORA',
        self::SUSPENSION => 'ERP_SUSPENSION',
        self::PAGO => 'ERP_PAGO',
    ];

    /** Plantilla editable de la academia administradora ya armada, o null para la vista por defecto. */
    public ?array $plantilla;

    public function __construct(public string $tipo, public array $datos)
    {
        $dinero = fn ($v) => $v === null || $v === '' ? '' : '$' . number_format((float) $v, 0, ',', '.');
        $variables = array_merge($datos, [
            'periodo' => ucfirst((string) ($datos['periodo'] ?? '')),
            'valor' => $dinero($datos['valor'] ?? null),
            'saldo' => $dinero($datos['saldo'] ?? null),
            'saldo_total' => $dinero($datos['saldo_total'] ?? null),
            'pago_valor' => $dinero($datos['pago_valor'] ?? null),
        ]);
        $this->plantilla = Configuracion::plantillaAdministradora(self::PLANTILLAS[$tipo], $variables);
    }

    public function build()
    {
        if ($this->plantilla) {
            $correo = $this->from(config('mail.from.address'), $this->datos['marca'])
                ->subject($this->plantilla['asunto'])
                ->view('emails.facturacion.plantilla', ['cuerpo' => $this->plantilla['html']]);
            if (!empty($this->datos['contacto'])) {
                $correo->replyTo($this->datos['contacto'], $this->datos['marca']);
            }
            return $correo;
        }

        $asunto = strtr(self::ASUNTOS[$this->tipo], [
            ':numero' => $this->datos['numero'],
            ':marca' => $this->datos['marca'],
            ':periodo' => $this->datos['periodo'],
            ':vencimiento' => $this->datos['vencimiento'],
        ]);

        $correo = $this->from(config('mail.from.address'), $this->datos['marca'])
            ->subject($asunto)
            ->view('emails.facturacion.' . $this->tipo);

        if (!empty($this->datos['contacto'])) {
            $correo->replyTo($this->datos['contacto'], $this->datos['marca']);
        }
        return $correo;
    }
}
