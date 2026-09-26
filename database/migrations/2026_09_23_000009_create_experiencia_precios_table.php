<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('experiencia_precios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experiencia_id')->references('id')->on('experiencias')->onDelete('cascade');
            $table->string('descripcion', 150);
            $table->string('tipo', 50);
            $table->integer('cantidad')->default(1);
            $table->decimal('precio', 12, 2);
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('experiencia_precios');
    }
};
