<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Correos manuales/masivos: plantillas propias de la academia (tipo 'manual') y envíos a un
 * grupo de destinatarios, con el estado de cada uno.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 'sistema' = las envía el sistema (no se borran); 'manual' = creadas por la academia.
        Schema::table('parametros_correos', function (Blueprint $table) {
            $table->string('tipo', 10)->default('sistema')->after('codigo');
        });

        Schema::create('envios_correo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plantilla_id')->nullable()->references('id')->on('parametros_correos')->nullOnDelete();
            $table->string('asunto', 150);
            $table->longText('texto'); // copia de lo enviado (la plantilla puede cambiar después)
            $table->string('audiencia', 20); // todos | curso | mora | seleccion | academias
            $table->text('filtro')->nullable(); // JSON (curso_id, alumnos[])
            $table->unsignedInteger('total')->default(0);
            $table->string('estado', 15)->default('enviando'); // enviando | completado
            $table->bigInteger('usuario_creacion_id');
            $table->string('usuario_creacion_nombre', 128);
            $table->bigInteger('usuario_modificacion_id');
            $table->string('usuario_modificacion_nombre', 128);
            $table->timestamps();
        });

        Schema::create('envio_correo_destinatarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('envio_id')->references('id')->on('envios_correo')->cascadeOnDelete();
            $table->unsignedBigInteger('alumno_id')->nullable();
            $table->string('nombre', 200);
            $table->string('correo', 150)->nullable();
            $table->text('variables')->nullable(); // JSON con los valores para personalizar
            $table->string('estado', 15)->default('pendiente'); // pendiente | enviado | error
            $table->text('error')->nullable();
            $table->dateTime('enviado_en')->nullable();
            $table->timestamps();
            $table->index(['envio_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envio_correo_destinatarios');
        Schema::dropIfExists('envios_correo');
        Schema::table('parametros_correos', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
