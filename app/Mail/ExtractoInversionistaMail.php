<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Parametrizacion\ParametroCorreo;
use App\Models\Parametrizacion\ParametroConstante;

class ExtractoInversionistaMail extends Mailable
{
    use Queueable, SerializesModels;

    public $inversionista;
    public $pdf;

    public function __construct($pdf, $inversionista)
    {
        $this->pdf = $pdf;
        $this->inversionista = $inversionista;
    }

    public function build()
    {

        $parametros = ParametroConstante::cargarParametros();
        $correoId = $parametros['ID_CORREO_EXTRACTO'] ?? null;

        $año = date("Y");
        $mes = date("m");
        $dia = date("d"); 

        if (!$correoId) {
            throw new \Exception("No se encontró el parámetro ID_CORREO_EXTRACTO");
        }

        $plantilla = ParametroCorreo::find($correoId);

        // Decodifica entidades HTML (por si están como &amp;1)
        $textoBase = html_entity_decode($plantilla->texto);

        // Reemplazos
        $textoProcesado = str_replace('&1', $this->inversionista->nombre, $textoBase);

        return $this->subject($plantilla->asunto)
            ->view('emails.extracto_inversionista')
            ->with([
                'texto' => $textoProcesado
            ])
            ->attachData($this->pdf, 'Extracto_'.$this->inversionista->nombre.'_'. $año .'_'. $mes .'_'. $dia .'.pdf', [
                'mime' => 'application/pdf',
            ]);
    }
}
