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
        Schema::create('opciones_del_sistema', function (Blueprint $table) {
            $table->id();
            $table->string('nombre',128);
            $table->foreignId('modulo_id')->references('id')->on('modulos');
            $table->integer('posicion');
            $table->string('icono_menu',128)->nullable();
            $table->string('url',128);
            $table->string('url_ayuda',128)->nullable();
            $table->boolean('estado')->default(true);
            
            // Auditoria
            $table->bigInteger('usuario_creacion_id');
            $table->string('usuario_creacion_nombre',128);
            $table->bigInteger('usuario_modificacion_id');
            $table->string('usuario_modificacion_nombre',128);
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
        Schema::dropIfExists('opciones_del_sistema');
    }
};
