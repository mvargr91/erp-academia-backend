<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('experiencia_disponibilidad', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experiencia_id')->references('id')->on('experiencias')->onDelete('cascade');
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_fin')->nullable();
            $table->integer('capacidad');
            $table->integer('cupos_disponibles');
            $table->string('estado', 30)->default('disponible');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('experiencia_disponibilidad');
    }
};
