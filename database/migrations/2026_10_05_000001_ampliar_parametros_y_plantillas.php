<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parámetros del sistema y plantillas de correo por academia. Los códigos los define el sistema
 * (App\Support\Configuracion\CatalogoConfiguracion); cada academia solo edita valores y textos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parametros_constantes', function (Blueprint $table) {
            $table->string('tipo', 15)->default('texto')->after('valor_parametro'); // numero | porcentaje | texto
            $table->string('grupo', 40)->nullable()->after('tipo');
            $table->unsignedSmallInteger('orden')->default(0)->after('grupo');
        });

        Schema::table('parametros_correos', function (Blueprint $table) {
            $table->string('codigo', 40)->nullable()->unique()->after('id');
            $table->text('variables')->nullable()->after('parametros'); // JSON: { variable: descripción }
            $table->unsignedSmallInteger('orden')->default(0)->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('parametros_correos', function (Blueprint $table) {
            $table->dropUnique(['codigo']);
            $table->dropColumn(['codigo', 'variables', 'orden']);
        });
        Schema::table('parametros_constantes', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'grupo', 'orden']);
        });
    }
};
