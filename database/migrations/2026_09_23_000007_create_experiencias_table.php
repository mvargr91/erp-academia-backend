<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('experiencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->references('id')->on('proveedores_turisticos')->onDelete('cascade');
            $table->foreignId('destino_id')->references('id')->on('destinos');
            $table->string('nombre', 180);
            $table->string('slug', 200)->unique();
            $table->text('descripcion')->nullable();
            $table->string('idioma', 50)->nullable();
            $table->text('incluye')->nullable();
            $table->text('no_incluye')->nullable();
            $table->decimal('precio_desde', 12, 2)->default(0);
            $table->string('duracion', 100)->nullable();
            $table->integer('capacidad_maxima')->nullable();
            $table->string('punto_encuentro', 255)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->decimal('latitud', 10, 7)->nullable();
            $table->decimal('longitud', 10, 7)->nullable();
            $table->string('estado', 30)->default('borrador');
            $table->boolean('destacada')->default(false);
            $table->boolean('verificada')->default(false);

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
        Schema::dropIfExists('experiencias');
    }
};
