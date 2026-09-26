<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('acompanantes_reserva', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reserva_id')->references('id')->on('reservas')->onDelete('cascade');
            $table->string('nombre', 150);
            $table->string('correo', 150)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('documento', 50)->nullable();
            $table->integer('edad')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('acompanantes_reserva');
    }
};
