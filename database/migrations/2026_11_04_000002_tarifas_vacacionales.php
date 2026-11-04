<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarifa de los vacacionales, con la misma dinámica de los cursos: una sola escala para todos los
 * cursos vacacionales con el total según cuántos toma el alumno en la temporada, individual y en
 * pareja (total de los dos). La inscripción guarda con quién paga en pareja (enlazada en ambas).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('escalas_precio_vacacional', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20); // individual | pareja
            $table->unsignedSmallInteger('cantidad');
            $table->decimal('total', 12, 2);
            $table->timestamps();
            $table->unique(['tipo', 'cantidad']);
        });

        Schema::table('vacacional_inscripciones', function (Blueprint $table) {
            $table->foreignId('pareja_alumno_id')->nullable()->after('alumno_id')->references('id')->on('alumnos');
        });
    }

    public function down(): void
    {
        Schema::table('vacacional_inscripciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pareja_alumno_id');
        });
        Schema::dropIfExists('escalas_precio_vacacional');
    }
};
