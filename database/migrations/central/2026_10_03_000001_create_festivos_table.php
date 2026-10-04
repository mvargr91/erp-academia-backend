<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Festivos nacionales de Colombia, compartidos por todas las academias.
 * Se generan por ley con `php artisan festivos:generar` (origen 'ley'); el dueño del ERP
 * puede agregar festivos extraordinarios (origen 'manual').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('festivos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha')->unique();
            $table->string('nombre', 120);
            $table->string('origen', 10)->default('ley');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('festivos');
    }
};
