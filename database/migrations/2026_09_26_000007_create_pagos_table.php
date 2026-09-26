<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->references('id')->on('alumnos');
            $table->foreignId('curso_id')->nullable()->references('id')->on('cursos');
            $table->foreignId('plan_id')->nullable()->references('id')->on('planes');
            $table->decimal('monto', 12, 2);
            $table->date('fecha_pago');
            // efectivo | transferencia | tarjeta | otro
            $table->string('metodo_pago', 30)->default('efectivo');
            $table->string('referencia', 100)->nullable();
            $table->text('observacion')->nullable();

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
        Schema::dropIfExists('pagos');
    }
};
