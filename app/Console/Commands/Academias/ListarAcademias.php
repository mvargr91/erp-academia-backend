<?php

namespace App\Console\Commands\Academias;

use Illuminate\Console\Command;
use App\Models\Central\Academia;

class ListarAcademias extends Command
{
    protected $signature = 'academia:listar';

    protected $description = 'Lista las academias registradas';

    public function handle(): int
    {
        $this->table(
            ['Código', 'Nombre', 'Base de datos', 'Día pago', 'Activa', 'Admin', 'URL'],
            Academia::orderBy('id')->get()->map(fn ($a) => [
                $a->codigo,
                $a->nombre,
                $a->base_datos,
                $a->dia_pago,
                $a->activa ? 'Sí' : 'No',
                $a->es_administradora ? 'Sí' : '',
                $a->url(),
            ])
        );
        return self::SUCCESS;
    }
}
