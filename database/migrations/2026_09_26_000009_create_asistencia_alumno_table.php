<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Detalle de presentes/ausentes por sesión de clase.
        Schema::create('asistencia_alumno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asistencia_id')->references('id')->on('asistencias')->cascadeOnDelete();
            $table->foreignId('alumno_id')->references('id')->on('alumnos')->cascadeOnDelete();
            $table->boolean('presente')->default(true);
            $table->timestamps();

            $table->unique(['asistencia_id', 'alumno_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('asistencia_alumno');
    }
};
