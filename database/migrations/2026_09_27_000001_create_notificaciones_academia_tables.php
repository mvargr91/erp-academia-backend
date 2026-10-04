<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Mensualidad causada a cada matrícula en el día de pago de la academia (suma al saldo).
        Schema::create('cargos_mensuales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_alumno_id')->references('id')->on('curso_alumno')->cascadeOnDelete();
            $table->date('periodo');
            $table->decimal('valor', 12, 2);
            $table->timestamps();
            $table->unique(['curso_alumno_id', 'periodo']);
        });

        // Bitácora de correos: evita duplicados y deja rastro de los errores de envío.
        Schema::create('notificaciones_enviadas', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20);
            $table->foreignId('curso_alumno_id')->nullable()->references('id')->on('curso_alumno')->nullOnDelete();
            $table->unsignedBigInteger('alumno_id')->nullable();
            $table->unsignedBigInteger('curso_id')->nullable();
            $table->date('periodo')->nullable();
            $table->string('correo', 150)->nullable();
            $table->string('estado', 20)->default('enviado');
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['tipo', 'curso_alumno_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones_enviadas');
        Schema::dropIfExists('cargos_mensuales');
    }
};
