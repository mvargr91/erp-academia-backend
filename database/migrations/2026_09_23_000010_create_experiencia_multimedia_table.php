<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('experiencia_multimedia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experiencia_id')->references('id')->on('experiencias')->onDelete('cascade');
            $table->string('tipo', 20);
            $table->string('ruta_archivo', 255);
            $table->string('titulo', 150)->nullable();
            $table->string('texto_alternativo', 255)->nullable();
            $table->integer('orden')->default(0);
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('experiencia_multimedia');
    }
};
