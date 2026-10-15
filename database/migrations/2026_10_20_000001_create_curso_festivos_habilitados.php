<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Festivos en que un curso sí dicta clase: esa fecha cuenta como clase normal del ciclo
        // en lugar de correrse a la semana siguiente. (Si la academia dicta todos los festivos,
        // se usa el parámetro DICTA_FESTIVOS.)
        Schema::create('curso_festivos_habilitados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->references('id')->on('cursos')->cascadeOnDelete();
            $table->date('fecha');
            $table->unsignedBigInteger('usuario_creacion_id')->nullable();
            $table->string('usuario_creacion_nombre')->nullable();
            $table->timestamps();
            $table->unique(['curso_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curso_festivos_habilitados');
    }
};
