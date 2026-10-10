<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Precio pactado con un alumno para un curso (beca, convenio, cortesía): si está definido,
        // ese alumno paga ese valor por ciclo en lugar de la tarifa. 0 = no paga.
        Schema::table('curso_alumno', function (Blueprint $table) {
            $table->decimal('valor_especial', 12, 2)->nullable()->after('pareja_alumno_id');
            $table->string('valor_especial_motivo', 150)->nullable()->after('valor_especial');
        });
    }

    public function down(): void
    {
        Schema::table('curso_alumno', function (Blueprint $table) {
            $table->dropColumn(['valor_especial', 'valor_especial_motivo']);
        });
    }
};
