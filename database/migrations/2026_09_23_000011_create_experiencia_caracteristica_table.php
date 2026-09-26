<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('experiencia_caracteristica', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experiencia_id')->references('id')->on('experiencias')->onDelete('cascade');
            $table->foreignId('caracteristica_id')->references('id')->on('caracteristicas')->onDelete('cascade');
            $table->boolean('incluida')->default(true);
            $table->timestamps();

            $table->unique(['experiencia_id', 'caracteristica_id'], 'experiencia_caracteristica_unique');
        });
    }

    public function down()
    {
        Schema::dropIfExists('experiencia_caracteristica');
    }
};
