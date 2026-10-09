<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El curso se marca como individual o en pareja: es lo que decide qué escala de precios se cobra
 * a sus alumnos (App\Services\Academia\Tarifas). Reemplaza la pareja asignada alumno por alumno
 * (curso_alumno.pareja_alumno_id, que queda sin uso).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->boolean('en_pareja')->default(false)->after('plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropColumn('en_pareja');
        });
    }
};
