<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function auditoria(Blueprint $table): void
    {
        $table->bigInteger('usuario_creacion_id');
        $table->string('usuario_creacion_nombre', 128);
        $table->bigInteger('usuario_modificacion_id');
        $table->string('usuario_modificacion_nombre', 128);
        $table->timestamps();
    }

    public function up(): void
    {
        // Planes tipo paquete: días de vigencia desde la compra (null = no vence).
        Schema::table('planes', function (Blueprint $table) {
            $table->integer('vigencia_dias')->nullable()->after('num_clases');
        });

        // Paquete de clases comprado por un alumno: sirve para cursos grupales y clases privadas.
        Schema::create('paquetes_alumno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumno_id')->references('id')->on('alumnos');
            $table->foreignId('plan_id')->nullable()->references('id')->on('planes')->nullOnDelete();
            $table->integer('clases_total');
            $table->date('fecha_compra');
            $table->date('fecha_vencimiento')->nullable();
            $table->decimal('valor', 12, 2)->default(0);
            $table->decimal('saldo', 12, 2)->default(0);
            $table->string('estado', 15)->default('activo'); // activo | anulado (vencido/agotado se calculan)
            $table->text('observacion')->nullable();
            $this->auditoria($table);
            $table->index(['alumno_id', 'estado']);
        });

        // Matrícula en un curso grupal: por ciclo (mensualidad) o por paquete (descuenta clases).
        Schema::table('curso_alumno', function (Blueprint $table) {
            $table->string('modalidad', 10)->default('ciclo')->after('ciclo_inicio');
        });

        // Los pagos pueden abonar a un paquete.
        Schema::table('pagos', function (Blueprint $table) {
            $table->foreignId('paquete_id')->nullable()->after('plan_id')->references('id')->on('paquetes_alumno')->nullOnDelete();
        });

        // Clases personalizadas (privadas o de pareja), fuera del horario fijo de los cursos.
        Schema::create('clases_privadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profesor_id')->nullable()->references('id')->on('profesores')->nullOnDelete();
            $table->date('fecha');
            $table->time('hora');
            $table->unsignedSmallInteger('duracion_min')->default(60);
            $table->string('estado', 15)->default('programada'); // programada | realizada | cancelada
            $table->text('observacion')->nullable();
            $this->auditoria($table);
            $table->index('fecha');
        });

        Schema::create('clase_privada_alumno', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clase_privada_id')->references('id')->on('clases_privadas')->cascadeOnDelete();
            $table->foreignId('alumno_id')->references('id')->on('alumnos');
            // pendiente | asistio | no_asistio | cancelo
            $table->string('resultado', 15)->default('pendiente');
            $table->dateTime('cancelado_en')->nullable();
            $table->boolean('descuenta')->default(false);
            $table->timestamps();
            $table->unique(['clase_privada_id', 'alumno_id']);
        });

        // Cada clase descontada de un paquete (grupal por asistencia o privada).
        Schema::create('consumos_paquete', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paquete_id')->references('id')->on('paquetes_alumno')->cascadeOnDelete();
            $table->foreignId('alumno_id')->references('id')->on('alumnos');
            $table->date('fecha');
            $table->string('origen', 10); // grupal | privada
            $table->foreignId('asistencia_id')->nullable()->references('id')->on('asistencias')->cascadeOnDelete();
            $table->foreignId('clase_privada_id')->nullable()->references('id')->on('clases_privadas')->cascadeOnDelete();
            $table->string('motivo', 30); // asistio | no_asistio | cancelacion_tardia
            $table->timestamps();
            $table->index(['alumno_id', 'asistencia_id']);
            $table->index(['alumno_id', 'clase_privada_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumos_paquete');
        Schema::dropIfExists('clase_privada_alumno');
        Schema::dropIfExists('clases_privadas');
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paquete_id');
        });
        Schema::table('curso_alumno', function (Blueprint $table) {
            $table->dropColumn('modalidad');
        });
        Schema::dropIfExists('paquetes_alumno');
        Schema::table('planes', function (Blueprint $table) {
            $table->dropColumn('vigencia_dias');
        });
    }
};
