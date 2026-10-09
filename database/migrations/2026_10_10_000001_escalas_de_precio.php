<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tarifas configurables por academia (App\Services\Academia\Tarifas), en lugar de los parámetros
 * fijos de 2.º/3.er/4.º curso y precio de pareja:
 *  - escalas_precio: total por ciclo según cuántos cursos toma el alumno, individual o en pareja.
 *    Es opcional: sin escala cada curso cobra el precio de su plan.
 *  - planes.valor_alumno: precio del paquete para quien ya es alumno de un curso grupal (opcional).
 */
return new class extends Migration
{
    private const PARAMETROS_REEMPLAZADOS = ['PRECIO_PAREJA', 'PRECIO_SEGUNDO_CURSO', 'DESC_TERCER_CURSO', 'DESC_CUARTO_CURSO'];

    public function up(): void
    {
        Schema::create('escalas_precio', function (Blueprint $table) {
            $table->id();
            // individual | pareja (en pareja el total es el de las dos personas)
            $table->string('tipo', 20);
            // Número de cursos que toma el alumno (o la pareja).
            $table->unsignedTinyInteger('cantidad');
            $table->decimal('total', 12, 2);
            $table->timestamps();
            $table->unique(['tipo', 'cantidad']);
        });

        Schema::table('planes', function (Blueprint $table) {
            $table->decimal('valor_alumno', 12, 2)->nullable()->after('valor');
        });

        DB::table('parametros_constantes')->whereIn('codigo_parametro', self::PARAMETROS_REEMPLAZADOS)->delete();
    }

    public function down(): void
    {
        Schema::table('planes', function (Blueprint $table) {
            $table->dropColumn('valor_alumno');
        });
        Schema::dropIfExists('escalas_precio');
    }
};
