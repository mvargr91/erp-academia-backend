<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('caracteristicas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 128);
            $table->string('icono', 100)->nullable();

            // Estado
            $table->boolean('estado')->default(true);

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
        Schema::dropIfExists('caracteristicas');
    }
};
