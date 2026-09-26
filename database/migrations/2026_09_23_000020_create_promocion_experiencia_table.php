<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('promocion_experiencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promocion_id')->references('id')->on('promociones')->onDelete('cascade');
            $table->foreignId('experiencia_id')->references('id')->on('experiencias')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['promocion_id', 'experiencia_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('promocion_experiencia');
    }
};
