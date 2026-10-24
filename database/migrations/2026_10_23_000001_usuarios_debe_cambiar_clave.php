<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contraseña inicial: cuando la da el administrador (acceso de un profesor), el usuario debe cambiarla
 * al entrar. Mientras tanto la API solo le deja ver su sesión y cambiar la contraseña (ClaveVigente).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->boolean('debe_cambiar_clave')->default(false)->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('debe_cambiar_clave');
        });
    }
};
