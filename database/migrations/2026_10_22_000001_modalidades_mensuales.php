<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modalidades que se cobran por mes calendario (mensualidad) además de por ciclo de N clases.
 * En una mensual las clases por ciclo no aplican: el mes trae las clases que traiga (4 o 5 semanas)
 * y se paga lo mismo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modalidades', function (Blueprint $table) {
            // ciclo | mensual
            $table->string('tipo_cobro', 10)->default('ciclo')->after('nombre');
            $table->unsignedTinyInteger('clases_por_ciclo')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Las mensuales vuelven como ciclo de 4 clases (lo más parecido a un mes).
        DB::table('modalidades')->where('tipo_cobro', 'mensual')->update(['clases_por_ciclo' => 4]);
        Schema::table('modalidades', function (Blueprint $table) {
            $table->dropColumn('tipo_cobro');
            $table->unsignedTinyInteger('clases_por_ciclo')->nullable(false)->change();
        });
    }
};
