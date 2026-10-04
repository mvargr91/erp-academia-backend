<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Apariencia del ERP por academia (colores, logos, login, modo claro/oscuro).
 * Vive en la BD central porque el login la necesita antes de autenticar.
 * Solo se guardan los valores que la academia cambió; el resto sale de App\Support\Academias\Apariencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('academias', function (Blueprint $table) {
            $table->json('apariencia')->nullable()->after('telefono');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('academias', function (Blueprint $table) {
            $table->dropColumn('apariencia');
        });
    }
};
