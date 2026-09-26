<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Parametrizacion\ParametroCorreo;
use App\Models\Parametrizacion\ParametroConstante;

class PagosConfirmadosMail extends Mailable
{
    use Queueable, SerializesModels;

    public $nombre_tercero;
    public $valor_total;
    public $detalles;

    public function __construct($nombreTercero, $valorTotal, $detalles)
    {
        $this->nombre_tercero = $nombreTercero;
        $this->valor_total = $valorTotal;
        $this->detalles = $detalles;
    }

    public function build()
    {
        $parametros = ParametroConstante::cargarParametros();
        $correoId = $parametros['ID_CORREO_NOTIFICACION_PAGO'] ?? null;

        if (!$correoId) {
            throw new \Exception("No se encontró el parámetro ID_CORREO_NOTIFICACION_PAGO");
        }

        $plantilla = ParametroCorreo::find($correoId);
        $tablaHtml = $this->generarTablaHTML();

        // Decodifica entidades HTML (por si están como &amp;1)
        $textoBase = html_entity_decode($plantilla->texto);

        // Reemplazos
        $textoProcesado = str_replace('&1', $this->nombre_tercero, $textoBase);
        $textoProcesado = str_replace('&2', number_format($this->valor_total, 0, ',', '.'), $textoProcesado);
        $textoProcesado = str_replace('&3', $tablaHtml, $textoProcesado);

        return $this->subject($plantilla->asunto)
        ->view('emails.pagos_confirmados')
        ->with([
            'texto' => $textoProcesado
        ]);
    }

    private function generarTablaHTML()
    {
        $html = '<table width="100%" cellpadding="5" cellspacing="0" style="border-collapse: collapse; margin-top: 15px;">
            <thead>
                <tr style="background-color: #4aab3d; color: white;">                    
                    <th style="border: 1px solid #ddd;">Fecha inversión</th>
                    <th style="border: 1px solid #ddd;">Proyecto</th>
                    <th style="border: 1px solid #ddd;  text-aling: rigth;">Valor inversión</th>
                    <th style="border: 1px solid #ddd;">Concepto</th>
                    <th style="border: 1px solid #ddd;">Fecha vcmto</th>
                    <th style="border: 1px solid #ddd;  text-aling: rigth;">Valor</th>
                    <th style="border: 1px solid #ddd;  text-aling: rigth;">Ret fuente</th>
                    <th style="border: 1px solid #ddd;">% Ret fuente</th>
                    <th style="border: 1px solid #ddd;  text-aling: rigth;">Valor a pagar</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($this->detalles as $detalle) {
            $html .= '<tr>
                <td style="border: 1px solid #ddd; ">' . $detalle->fecha_inversion . '</td>
                <td style="border: 1px solid #ddd;">' . $detalle->proyecto . '</td>
                <td style="border: 1px solid #ddd; text-aling: rigth; float: rigth;"> $ ' . number_format($detalle->valor_inversion, 0, ',', '.') . '</td>
                <td style="border: 1px solid #ddd;">' . $detalle->concepto . '</td>
                <td style="border: 1px solid #ddd;">' . $detalle->fecha_vencimiento . '</td>
                <td style="border: 1px solid #ddd; text-aling: rigth;"> $ ' . number_format($detalle->valor_concepto, 0, ',', '.') . '</td>
                <td style="border: 1px solid #ddd; text-aling: rigth;"> $ ' . number_format($detalle->valor_ret_fuente, 0, ',', '.') . '</td>
                <td style="border: 1px solid #ddd;">' . $detalle->porcentaje_ret_fuente . '%</td>
                <td style="border: 1px solid #ddd; text-aling: rigth;"> $ ' . number_format($detalle->valor_a_pagar, 0, ',', '.') . '</td>
            </tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }
}
