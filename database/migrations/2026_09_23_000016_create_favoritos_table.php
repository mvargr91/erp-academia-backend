<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('favoritos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->references('id')->on('usuarios')->onDelete('cascade');
            $table->foreignId('experiencia_id')->references('id')->on('experiencias')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['usuario_id', 'experiencia_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('favoritos');
    }
};
