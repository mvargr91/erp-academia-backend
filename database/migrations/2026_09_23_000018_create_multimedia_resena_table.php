<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('multimedia_resena', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resena_id')->references('id')->on('resenas')->onDelete('cascade');
            $table->string('ruta_archivo', 255);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('multimedia_resena');
    }
};
