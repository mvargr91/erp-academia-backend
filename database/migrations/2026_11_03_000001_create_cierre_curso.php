<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cierres por curso: un cierre con cursos solo aplica a esos cursos (el profesor no puede, el salón
 * está ocupado…). Sin cursos sigue siendo de toda la academia o de su sede, como antes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cierre_curso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cierre_id')->constrained('cierres_academia')->cascadeOnDelete();
            $table->foreignId('curso_id')->constrained('cursos')->cascadeOnDelete();
            $table->unique(['cierre_id', 'curso_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cierre_curso');
    }
};
