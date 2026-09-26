<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('planes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->decimal('valor', 12, 2)->default(0);
            // semanal | mensual | paquete
            $table->string('periodicidad', 20)->default('mensual');
            // Número de clases cuando la periodicidad es un paquete
            $table->integer('num_clases')->nullable();

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
        Schema::dropIfExists('planes');
    }
};
