<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Asistencia de vacacionales: cada clase que toma un alumno (fecha de su inscripción) guarda si
 * asistió. Vacío = todavía no se ha tomado la asistencia de ese día.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacacional_inscripcion_fechas', function (Blueprint $table) {
            $table->boolean('asistio')->nullable()->after('fecha');
            $table->unsignedBigInteger('asistencia_usuario_id')->nullable()->after('asistio');
            $table->string('asistencia_usuario_nombre', 128)->nullable()->after('asistencia_usuario_id');
            $table->dateTime('asistencia_en')->nullable()->after('asistencia_usuario_nombre');
        });
    }

    public function down(): void
    {
        Schema::table('vacacional_inscripcion_fechas', function (Blueprint $table) {
            $table->dropColumn(['asistio', 'asistencia_usuario_id', 'asistencia_usuario_nombre', 'asistencia_en']);
        });
    }
};
