<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->references('id')->on('usuarios');
            $table->foreignId('experiencia_id')->references('id')->on('experiencias');
            $table->foreignId('proveedor_id')->references('id')->on('proveedores_turisticos');
            $table->foreignId('disponibilidad_id')->nullable()->references('id')->on('experiencia_disponibilidad');
            $table->string('codigo_reserva', 30)->unique();
            $table->date('fecha');
            $table->string('residencia', 20)->nullable();
            $table->string('nombre', 150);
            $table->string('correo', 150);
            $table->string('telefono', 30);
            $table->time('hora_inicio')->nullable();
            $table->integer('cantidad_personas')->default(1);
            $table->integer('cantidad_chicos')->default(0);
            $table->string('idioma', 50)->nullable();
            $table->foreignId('cupon_id')->nullable()->references('id')->on('cupones');
            $table->decimal('valor_total', 12, 2)->default(0);
            $table->string('estado', 30)->default('pendiente');
            $table->text('observaciones')->nullable();
            $table->text('notas')->nullable();

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
        Schema::dropIfExists('reservas');
    }
};
