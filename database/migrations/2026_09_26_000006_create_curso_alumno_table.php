<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Matrícula de un alumno en un curso.
        Schema::create('curso_alumno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->references('id')->on('cursos')->cascadeOnDelete();
            $table->foreignId('alumno_id')->references('id')->on('alumnos')->cascadeOnDelete();
            $table->date('fecha_matricula')->nullable();
            // Saldo pendiente de pago del alumno en este curso.
            $table->decimal('saldo', 12, 2)->default(0);
            $table->date('ultima_fecha_pago')->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();

            $table->unique(['curso_id', 'alumno_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('curso_alumno');
    }
};
