<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('documentos_proveedor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_id')->references('id')->on('proveedores_turisticos')->onDelete('cascade');
            $table->string('tipo_documento', 100);
            $table->string('nombre_archivo', 255);
            $table->string('ruta_archivo', 255);
            $table->string('estado', 30)->default('pendiente');
            $table->date('fecha_vencimiento')->nullable();
            $table->bigInteger('revisado_por')->nullable();
            $table->timestamp('revisado_en')->nullable();
            $table->text('motivo_rechazo')->nullable();

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
        Schema::dropIfExists('documentos_proveedor');
    }
};
