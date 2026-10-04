<?php

namespace App\Jobs;

use Exception;
use Carbon\Carbon;
use App\Models\Central\Academia;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Support\Academias\GestorAcademias;
use App\Support\Configuracion\Configuracion;

/**
 * Envía un correo de un envío masivo a un destinatario. Corre en el worker de la cola central,
 * así que primero activa la BD de la academia; personaliza el texto con sus variables.
 */
class EnviarCorreoMasivo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public string $academiaCodigo, public int $destinatarioId)
    {
    }

    public function handle(): void
    {
        $academia = Academia::where('codigo', $this->academiaCodigo)->first();
        if (!$academia) {
            return;
        }
        GestorAcademias::activar($academia);

        $d = DB::table('envio_correo_destinatarios')->find($this->destinatarioId);
        if (!$d || $d->estado === 'enviado') {
            return;
        }
        $envio = DB::table('envios_correo')->find($d->envio_id);
        $variables = json_decode($d->variables ?? '{}', true) ?: [];
        $correo = Configuracion::renderizar($envio->asunto, $envio->texto, $variables);

        $html = view('emails.academia.plantilla', [
            'cuerpo' => $correo['html'],
            'academia' => ['nombre' => $academia->nombre, 'correo' => $academia->correo, 'telefono' => $academia->telefono],
            'datos' => ['alumno' => $d->nombre],
        ])->render();

        try {
            Mail::html($html, function ($m) use ($d, $correo, $academia) {
                $m->to($d->correo, $d->nombre)
                    ->subject($correo['asunto'])
                    ->from(config('mail.from.address'), $academia->nombre);
                if ($academia->correo) {
                    $m->replyTo($academia->correo, $academia->nombre);
                }
            });
            DB::table('envio_correo_destinatarios')->where('id', $d->id)
                ->update(['estado' => 'enviado', 'error' => null, 'enviado_en' => Carbon::now(), 'updated_at' => Carbon::now()]);
        } catch (Exception $e) {
            DB::table('envio_correo_destinatarios')->where('id', $d->id)
                ->update(['estado' => 'error', 'error' => mb_substr($e->getMessage(), 0, 500), 'updated_at' => Carbon::now()]);
        }

        // Cuando no quedan pendientes, el envío queda completado.
        if (!DB::table('envio_correo_destinatarios')->where('envio_id', $d->envio_id)->where('estado', 'pendiente')->exists()) {
            DB::table('envios_correo')->where('id', $d->envio_id)->update(['estado' => 'completado', 'updated_at' => Carbon::now()]);
        }
    }
}
