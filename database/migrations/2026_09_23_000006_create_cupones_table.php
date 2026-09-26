<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cupones', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 30);
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->integer('cantidad')->nullable();
            $table->decimal('descuento', 5, 2)->nullable();
            $table->decimal('valor', 12, 2)->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');

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
        Schema::dropIfExists('cupones');
    }
};
