<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un precio (adulto o niño) pasa a tener varias cantidades:
 * cada fila es el valor por persona que aplica desde esa cantidad de personas.
 * Los valores actuales (cantidad, precio) de experiencia_precios se trasladan a la tabla nueva.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('experiencia_precio_cantidades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experiencia_precio_id')->references('id')->on('experiencia_precios')->onDelete('cascade');
            $table->unsignedInteger('cantidad')->default(1);
            $table->decimal('valor', 12, 2);
            $table->timestamps();
            $table->unique(['experiencia_precio_id', 'cantidad'], 'exp_precio_cantidad_unique');
        });

        DB::table('experiencia_precios')->orderBy('id')->each(function ($precio) {
            DB::table('experiencia_precio_cantidades')->insert([
                'experiencia_precio_id' => $precio->id,
                'cantidad' => max(1, (int) $precio->cantidad),
                'valor' => $precio->precio,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Schema::table('experiencia_precios', function (Blueprint $table) {
            $table->dropColumn(['cantidad', 'precio']);
        });
    }

    public function down()
    {
        Schema::table('experiencia_precios', function (Blueprint $table) {
            $table->integer('cantidad')->default(1)->after('tipo');
            $table->decimal('precio', 12, 2)->default(0)->after('cantidad');
        });

        // Se conserva la cantidad más baja de cada precio.
        DB::table('experiencia_precio_cantidades')
            ->orderBy('experiencia_precio_id')->orderBy('cantidad')
            ->get()->groupBy('experiencia_precio_id')
            ->each(function ($cantidades, $precioId) {
                $primera = $cantidades->first();
                DB::table('experiencia_precios')->where('id', $precioId)
                    ->update(['cantidad' => $primera->cantidad, 'precio' => $primera->valor]);
            });

        Schema::dropIfExists('experiencia_precio_cantidades');
    }
};
