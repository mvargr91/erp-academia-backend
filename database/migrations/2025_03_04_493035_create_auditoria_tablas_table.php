<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('auditoria_tablas', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_recurso');
            $table->string('nombre_recurso');
            $table->string('descripcion_recurso')->nullable();
            $table->string('accion');
            $table->bigInteger('responsable_id');
            $table->string('responsable_nombre');
            $table->text('recurso_original');
            $table->text('recurso_resultante')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('auditoria_tablas');
    }
};
