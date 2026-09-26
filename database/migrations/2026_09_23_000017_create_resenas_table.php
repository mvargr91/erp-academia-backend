<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('resenas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->references('id')->on('usuarios');
            $table->foreignId('experiencia_id')->references('id')->on('experiencias');
            $table->foreignId('reserva_id')->nullable()->references('id')->on('reservas');
            $table->unsignedTinyInteger('calificacion');
            $table->text('comentario')->nullable();
            $table->string('estado', 30)->default('pendiente');

            // Auditoria
            $table->bigInteger('usuario_creacion_id');
            $table->string('usuario_creacion_nombre', 128);
            $table->bigInteger('usuario_modificacion_id');
            $table->string('usuario_modificacion_nombre', 128);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('resenas');
    }
};
