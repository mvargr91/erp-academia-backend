<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado del certificado SSL del subdominio de cada academia. Al crear una academia el ERP amplía el
 * certificado del servidor con su subdominio (App\Support\Academias\Certificado); aquí queda si salió bien.
 *   php artisan migrate --database=central --path=database/migrations/central
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('academias', function (Blueprint $table) {
            // listo | pendiente | error (null: no se gestiona, p. ej. en desarrollo)
            $table->string('certificado', 10)->nullable()->after('es_administradora');
            $table->text('certificado_mensaje')->nullable()->after('certificado');
            $table->timestamp('certificado_fecha')->nullable()->after('certificado_mensaje');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->table('academias', function (Blueprint $table) {
            $table->dropColumn(['certificado', 'certificado_mensaje', 'certificado_fecha']);
        });
    }
};
