<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('cursos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120)->nullable();
            $table->foreignId('ritmo_id')->references('id')->on('ritmos');
            $table->foreignId('profesor_id')->nullable()->references('id')->on('profesores');
            $table->foreignId('plan_id')->nullable()->references('id')->on('planes');
            // Día de la semana: 0=Domingo ... 6=Sábado
            $table->tinyInteger('dia');
            $table->time('hora');
            $table->date('fecha_inicio')->nullable();
            $table->integer('cupo_max')->nullable();
            $table->boolean('activo')->default(true);

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
        Schema::dropIfExists('cursos');
    }
};
