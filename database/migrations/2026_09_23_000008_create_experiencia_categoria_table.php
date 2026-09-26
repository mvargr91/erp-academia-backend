<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('experiencia_categoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experiencia_id')->references('id')->on('experiencias')->onDelete('cascade');
            $table->foreignId('categoria_id')->references('id')->on('categorias')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['experiencia_id', 'categoria_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('experiencia_categoria');
    }
};
