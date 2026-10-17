<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Modalidades: lo que la academia vende (ciclo de 4 clases, de 8…), con su duración de clase y su
 * propia tarifa. Cada curso tiene una modalidad; una academia puede tener varias a la vez.
 *
 * Reemplaza el parámetro global CLASES_POR_CICLO: cada academia queda con una modalidad
 * "Ciclo de N clases" con ese N, sus tarifas actuales y todos sus cursos, así que nada cambia.
 */
return new class extends Migration
{
    private const AUDITORIA = [
        'usuario_creacion_id' => 1,
        'usuario_creacion_nombre' => 'SuperUser',
        'usuario_modificacion_id' => 1,
        'usuario_modificacion_nombre' => 'SuperUser',
    ];

    public function up(): void
    {
        Schema::create('modalidades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            // Clases dictadas que paga cada ciclo (festivos y cierres no cuentan: el ciclo se corre).
            $table->unsignedTinyInteger('clases_por_ciclo');
            // Duración de cada clase; un curso puede tener la suya.
            $table->unsignedSmallInteger('duracion_minutos');
            $table->boolean('estado')->default(true);
            $table->bigInteger('usuario_creacion_id');
            $table->string('usuario_creacion_nombre', 128);
            $table->bigInteger('usuario_modificacion_id');
            $table->string('usuario_modificacion_nombre', 128);
            $table->timestamps();
        });

        $clases = (int) (DB::table('parametros_constantes')->where('codigo_parametro', 'CLASES_POR_CICLO')->value('valor_parametro') ?: 4);
        $clases = max(1, min(255, $clases));
        $modalidadId = DB::table('modalidades')->insertGetId(self::AUDITORIA + [
            'nombre' => "Ciclo de {$clases} clases",
            'clases_por_ciclo' => $clases,
            'duracion_minutos' => 60,
            'estado' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('escalas_precio', function (Blueprint $table) {
            $table->foreignId('modalidad_id')->nullable()->after('id')->references('id')->on('modalidades')->cascadeOnDelete();
        });
        DB::table('escalas_precio')->update(['modalidad_id' => $modalidadId]);
        Schema::table('escalas_precio', function (Blueprint $table) {
            $table->foreignId('modalidad_id')->nullable(false)->change();
            $table->dropUnique(['tipo', 'cantidad']);
            $table->unique(['modalidad_id', 'tipo', 'cantidad']);
        });

        Schema::table('cursos', function (Blueprint $table) {
            $table->foreignId('modalidad_id')->nullable()->after('profesor_id')->references('id')->on('modalidades');
            // Vacío: la duración de la modalidad.
            $table->unsignedSmallInteger('duracion_minutos')->nullable()->after('hora');
        });
        DB::table('cursos')->update(['modalidad_id' => $modalidadId]);
        Schema::table('cursos', function (Blueprint $table) {
            $table->foreignId('modalidad_id')->nullable(false)->change();
        });

        DB::table('parametros_constantes')->where('codigo_parametro', 'CLASES_POR_CICLO')->delete();
    }

    public function down(): void
    {
        $clases = DB::table('modalidades')->orderBy('id')->value('clases_por_ciclo') ?? 4;

        Schema::table('cursos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('modalidad_id');
            $table->dropColumn('duracion_minutos');
        });
        // Solo sobreviven las tarifas de la primera modalidad (antes había una sola tarifa).
        $primera = DB::table('modalidades')->orderBy('id')->value('id');
        DB::table('escalas_precio')->where('modalidad_id', '!=', $primera)->delete();
        Schema::table('escalas_precio', function (Blueprint $table) {
            $table->dropUnique(['modalidad_id', 'tipo', 'cantidad']);
            $table->dropConstrainedForeignId('modalidad_id');
            $table->unique(['tipo', 'cantidad']);
        });
        Schema::dropIfExists('modalidades');

        DB::table('parametros_constantes')->updateOrInsert(['codigo_parametro' => 'CLASES_POR_CICLO'], self::AUDITORIA + [
            'descripcion_parametro' => 'Clases que tiene cada ciclo de pago de un curso',
            'valor_parametro' => (string) $clases,
            'tipo' => 'numero',
            'grupo' => 'Cobro a alumnos',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
