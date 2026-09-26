<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('alumnos', function (Blueprint $table) {
            $table->id();
            // Cuenta (opcional) con la que el alumno entra al portal.
            $table->foreignId('usuario_id')->nullable()->references('id')->on('usuarios');
            $table->string('nombres', 100);
            $table->string('apellidos', 100);
            $table->string('documento', 30)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('correo', 150)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('contacto_emergencia', 150)->nullable();
            $table->string('telefono_emergencia', 30)->nullable();

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
        Schema::dropIfExists('alumnos');
    }
};
