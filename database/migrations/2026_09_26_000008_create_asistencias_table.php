<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Una sesión de clase de un curso en una fecha concreta.
        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->references('id')->on('cursos')->cascadeOnDelete();
            $table->date('fecha_sesion');
            $table->text('observacion')->nullable();

            // Auditoria
            $table->bigInteger('usuario_creacion_id');
            $table->string('usuario_creacion_nombre', 128);
            $table->bigInteger('usuario_modificacion_id');
            $table->string('usuario_modificacion_nombre', 128);
            $table->timestamps();

            $table->unique(['curso_id', 'fecha_sesion']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('asistencias');
    }
};
