<?php

namespace App\Jobs;

use App\Models\Central\Academia;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use App\Support\Academias\Certificado;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Amplía el certificado SSL con el subdominio de una academia recién creada (o al reintentar). Va en cola
 * porque certbot tarda: la academia se crea al instante y el certificado queda listo en unos segundos.
 */
class AmpliarCertificado implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Un solo intento: si falla, queda el error en la academia y se reintenta desde el ERP.
    public int $tries = 1;

    public int $timeout = 330;

    public function __construct(public int $academiaId)
    {
    }

    public function handle(): void
    {
        if ($academia = Academia::find($this->academiaId)) {
            Certificado::ampliar($academia);
        }
    }
}
