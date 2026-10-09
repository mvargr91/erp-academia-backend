<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Clase suelta: la toma una persona que no se crea como alumno (solo nombre, teléfono y valor).
        Schema::table('clases_privadas', function (Blueprint $table) {
            $table->string('externo_nombre', 150)->nullable()->after('observacion');
            $table->string('externo_telefono', 30)->nullable()->after('externo_nombre');
            // pendiente | asistio | no_asistio | cancelo_a_tiempo | cancelo_tarde
            $table->string('externo_resultado', 20)->default('pendiente')->after('externo_telefono');
            $table->decimal('valor', 12, 2)->nullable()->after('externo_resultado');
        });

        // Clase registrada desde un paquete: se descuenta de ese paquete y no del que venza primero.
        Schema::table('clase_privada_alumno', function (Blueprint $table) {
            $table->foreignId('paquete_id')->nullable()->after('alumno_id')->references('id')->on('paquetes_alumno')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('clase_privada_alumno', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paquete_id');
        });
        Schema::table('clases_privadas', function (Blueprint $table) {
            $table->dropColumn(['externo_nombre', 'externo_telefono', 'externo_resultado', 'valor']);
        });
    }
};
