<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('proveedores_turisticos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->references('id')->on('usuarios');
            $table->string('nombre_comercial', 150);
            $table->string('razon_social', 150)->nullable();
            $table->string('nit', 50)->nullable();
            $table->text('descripcion')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('correo', 150)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->foreignId('destino_id')->nullable()->references('id')->on('destinos');
            $table->string('sitio_web', 255)->nullable();
            $table->string('instagram', 150)->nullable();
            $table->string('facebook', 150)->nullable();
            $table->string('rnt', 50)->nullable();
            $table->string('estado_verificacion', 30)->default('pendiente');
            $table->text('observaciones_verificacion')->nullable();
            $table->timestamp('verificado_en')->nullable();
            $table->bigInteger('verificado_por')->nullable();

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
        Schema::dropIfExists('proveedores_turisticos');
    }
};
