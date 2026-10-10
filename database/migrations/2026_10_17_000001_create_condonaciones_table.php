<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Deuda perdonada a un alumno: el saldo del curso o del paquete queda en 0 sin que entre
        // dinero. No es un pago (no suma a los ingresos); queda registrado quién, cuánto y por qué.
        Schema::create('condonaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->references('id')->on('alumnos')->cascadeOnDelete();
            $table->foreignId('curso_id')->nullable()->references('id')->on('cursos')->nullOnDelete();
            $table->foreignId('paquete_id')->nullable()->references('id')->on('paquetes_alumno')->nullOnDelete();
            $table->decimal('valor', 12, 2);
            $table->string('motivo', 255);
            $table->unsignedBigInteger('usuario_creacion_id')->nullable();
            $table->string('usuario_creacion_nombre')->nullable();
            $table->timestamps();
            $table->index('alumno_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('condonaciones');
    }
};
