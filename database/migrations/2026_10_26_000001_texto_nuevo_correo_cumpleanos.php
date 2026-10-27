<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El correo de cumpleaños estrena diseño de celebración: su texto por defecto cambia. Solo se
 * actualiza en las academias que no lo habían editado (las que lo personalizaron conservan el suyo).
 */
return new class extends Migration
{
    private const ANTERIOR = '<p>Todo el equipo de <strong>{academia}</strong> te desea un muy feliz cumpleaños.</p>'
        . '<p>¡Que lo celebres bailando! Te esperamos en clase.</p>';

    private const NUEVO = '<p>Hoy en <strong>{academia}</strong> celebramos contigo y te deseamos un día lleno de música y alegría.</p>'
        . '<p>Que este nuevo año venga con muchos pasos nuevos. Gracias por bailar con nosotros.</p>'
        . '<p><strong>¡Te esperamos en la pista para celebrarlo!</strong></p>';

    public function up(): void
    {
        DB::table('parametros_correos')->where('codigo', 'CUMPLEANOS')->where('texto', self::ANTERIOR)
            ->update(['texto' => self::NUEVO, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('parametros_correos')->where('codigo', 'CUMPLEANOS')->where('texto', self::NUEVO)
            ->update(['texto' => self::ANTERIOR, 'updated_at' => now()]);
    }
};
