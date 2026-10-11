<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Cursos vacacionales: un público aparte del de la academia, con sus propios alumnos, cursos,
 * inscripciones y pagos (no se mezclan con alumnos, cursos ni pagos regulares).
 * Un curso vacacional no tiene horario semanal: sus clases son fechas sueltas elegidas en el
 * calendario (pueden ser días discontinuos) y cada alumno toma un número fijo de ellas.
 */
return new class extends Migration
{
    private function auditoria(Blueprint $table): void
    {
        $table->bigInteger('usuario_creacion_id');
        $table->string('usuario_creacion_nombre', 128);
        $table->bigInteger('usuario_modificacion_id');
        $table->string('usuario_modificacion_nombre', 128);
        $table->timestamps();
    }

    public function up(): void
    {
        Schema::create('vacacional_alumnos', function (Blueprint $table) {
            $table->id();
            $table->string('nombres', 100);
            $table->string('apellidos', 100);
            $table->string('documento', 30)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('correo', 150)->nullable();
            // Los vacacionales suelen ser de menores: quien responde y a quien se le escribe.
            $table->string('acudiente_nombre', 150)->nullable();
            $table->string('acudiente_telefono', 30)->nullable();
            $table->string('observacion', 255)->nullable();
            $table->boolean('estado')->default(true);
            $this->auditoria($table);
        });

        Schema::create('vacacional_cursos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->foreignId('sede_id')->nullable()->references('id')->on('sedes');
            $table->foreignId('ritmo_id')->nullable()->references('id')->on('ritmos'); // género
            $table->foreignId('profesor_id')->nullable()->references('id')->on('profesores');
            $table->time('hora_inicio')->nullable();
            $table->time('hora_fin')->nullable();
            $table->unsignedSmallInteger('clases_por_alumno')->default(4);
            $table->decimal('valor', 12, 2)->default(0);
            $table->unsignedSmallInteger('cupo_max')->nullable();
            $table->string('descripcion', 500)->nullable();
            $table->boolean('estado')->default(true);
            $this->auditoria($table);
        });

        // Fechas en que hay clase (elegidas una a una en el calendario).
        Schema::create('vacacional_curso_fechas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->references('id')->on('vacacional_cursos')->cascadeOnDelete();
            $table->date('fecha');
            $table->unique(['curso_id', 'fecha']);
        });

        Schema::create('vacacional_inscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->references('id')->on('vacacional_cursos');
            $table->foreignId('alumno_id')->references('id')->on('vacacional_alumnos');
            $table->date('fecha_inscripcion');
            $table->decimal('valor', 12, 2);
            $table->decimal('saldo', 12, 2);
            $table->string('observacion', 255)->nullable();
            $table->boolean('estado')->default(true); // false = anulada
            $this->auditoria($table);
            $table->unique(['curso_id', 'alumno_id']);
        });

        // Clases que tomará el alumno, entre las fechas del curso.
        Schema::create('vacacional_inscripcion_fechas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscripcion_id')->references('id')->on('vacacional_inscripciones')->cascadeOnDelete();
            $table->date('fecha');
            $table->unique(['inscripcion_id', 'fecha']);
        });

        Schema::create('vacacional_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscripcion_id')->references('id')->on('vacacional_inscripciones')->cascadeOnDelete();
            $table->decimal('monto', 12, 2);
            $table->date('fecha_pago');
            $table->string('metodo_pago', 20);
            $table->string('referencia', 100)->nullable();
            $this->auditoria($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacacional_pagos');
        Schema::dropIfExists('vacacional_inscripcion_fechas');
        Schema::dropIfExists('vacacional_inscripciones');
        Schema::dropIfExists('vacacional_curso_fechas');
        Schema::dropIfExists('vacacional_cursos');
        Schema::dropIfExists('vacacional_alumnos');
    }
};
