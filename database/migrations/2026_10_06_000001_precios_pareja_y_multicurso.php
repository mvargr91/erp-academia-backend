<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Precios especiales por ciclo: curso tomado en pareja y descuentos por varios cursos
 * (App\Services\Academia\Tarifas). Cada cobro guarda su desglose para saber por qué se cobró ese valor.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Pareja del alumno en este curso (se guarda en ambas matrículas).
        Schema::table('curso_alumno', function (Blueprint $table) {
            $table->foreignId('pareja_alumno_id')->nullable()->after('modalidad')
                ->references('id')->on('alumnos')->nullOnDelete();
        });

        Schema::table('cargos_mensuales', function (Blueprint $table) {
            $table->decimal('valor_base', 12, 2)->nullable()->after('valor');
            $table->string('regla', 60)->nullable()->after('valor_base');
        });
    }

    public function down(): void
    {
        Schema::table('cargos_mensuales', function (Blueprint $table) {
            $table->dropColumn(['valor_base', 'regla']);
        });
        Schema::table('curso_alumno', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pareja_alumno_id');
        });
    }
};
