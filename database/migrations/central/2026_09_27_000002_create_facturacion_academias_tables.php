<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facturación del dueño del ERP a sus academias clientes (BD central).
 *   php artisan migrate --database=central --path=database/migrations/central
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->table('academias', function (Blueprint $table) {
            $table->decimal('tarifa_mensual', 12, 2)->default(0)->after('dia_pago');
            $table->unsignedTinyInteger('dia_corte')->default(1)->after('tarifa_mensual');
            $table->date('fecha_inicio_cobro')->nullable()->after('dia_corte');
            // true = la suspendió el sistema por mora (se reactiva sola al pagar).
            $table->boolean('suspendida_por_mora')->default(false)->after('activa');
        });

        Schema::connection('central')->create('facturas_academia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academia_id')->constrained('academias')->cascadeOnDelete();
            $table->date('periodo');
            $table->date('fecha_vencimiento');
            $table->decimal('valor', 12, 2);
            $table->decimal('saldo', 12, 2);
            $table->string('estado', 20)->default('pendiente'); // pendiente | pagada | anulada
            $table->string('observacion', 255)->nullable();
            $table->timestamps();
            $table->unique(['academia_id', 'periodo']);
        });

        Schema::connection('central')->create('pagos_academia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academia_id')->constrained('academias')->cascadeOnDelete();
            $table->foreignId('factura_id')->constrained('facturas_academia')->cascadeOnDelete();
            $table->decimal('valor', 12, 2);
            $table->date('fecha_pago');
            $table->string('metodo', 30)->default('transferencia');
            $table->string('referencia', 100)->nullable();
            $table->string('soporte', 255)->nullable();
            $table->text('observacion')->nullable();
            $table->string('registrado_por', 128)->nullable();
            $table->timestamps();
        });

        Schema::connection('central')->create('notificaciones_academia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academia_id')->constrained('academias')->cascadeOnDelete();
            $table->foreignId('factura_id')->nullable()->constrained('facturas_academia')->nullOnDelete();
            $table->string('tipo', 20);
            $table->date('fecha');
            $table->string('correo', 150)->nullable();
            $table->string('estado', 20)->default('enviado');
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['academia_id', 'tipo', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('notificaciones_academia');
        Schema::connection('central')->dropIfExists('pagos_academia');
        Schema::connection('central')->dropIfExists('facturas_academia');
        Schema::connection('central')->table('academias', function (Blueprint $table) {
            $table->dropColumn(['tarifa_mensual', 'dia_corte', 'fecha_inicio_cobro', 'suspendida_por_mora']);
        });
    }
};
