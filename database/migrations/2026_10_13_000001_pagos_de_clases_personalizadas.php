<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pago de una clase personalizada que se cobra aparte (sin paquete). Si la tomó una persona
        // no registrada no hay alumno: se guarda su nombre en pagador_nombre.
        Schema::table('pagos', function (Blueprint $table) {
            $table->unsignedBigInteger('alumno_id')->nullable()->change();
            $table->foreignId('clase_privada_id')->nullable()->after('paquete_id')->references('id')->on('clases_privadas')->nullOnDelete();
            $table->string('pagador_nombre', 150)->nullable()->after('clase_privada_id');
        });
    }

    public function down(): void
    {
        // Los pagos sin alumno no caben en el esquema anterior.
        DB::table('pagos')->whereNull('alumno_id')->delete();
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clase_privada_id');
            $table->dropColumn('pagador_nombre');
            $table->unsignedBigInteger('alumno_id')->nullable(false)->change();
        });
    }
};
