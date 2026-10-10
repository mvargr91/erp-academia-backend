<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * El precio de los cursos sale solo de Tarifas (App\Services\Academia\Tarifas). Antes cada curso
 * tenía además un plan con su propio valor, que chocaba con la tarifa. Se quitan los planes de
 * curso y la opción Planes; la tabla planes queda solo para los tipos de paquete de clases
 * personalizadas, que se definen en Tarifas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $planesDeCurso = DB::table('planes')->where('periodicidad', '!=', 'paquete')->pluck('id');

        // Lo que definían los planes y aún no está en la configuración de la academia se conserva:
        // el precio de un curso (si no había tarifa individual) y las clases por ciclo.
        $enUso = DB::table('cursos as c')
            ->join('planes as p', 'p.id', '=', 'c.plan_id')
            ->where('p.periodicidad', 'mensual')
            ->where('p.valor', '>', 0);
        if (!DB::table('escalas_precio')->where('tipo', 'individual')->exists()) {
            $valor = (clone $enUso)->groupBy('p.valor')->orderByRaw('COUNT(*) DESC')->value('p.valor');
            if ($valor) {
                DB::table('escalas_precio')->insert([
                    'tipo' => 'individual', 'cantidad' => 1, 'total' => $valor, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        $clases = (clone $enUso)->whereNotNull('p.num_clases')->distinct()->pluck('p.num_clases');
        if ($clases->count() === 1) {
            DB::table('parametros_constantes')->where('codigo_parametro', 'CLASES_POR_CICLO')
                ->update(['valor_parametro' => (string) $clases->first()]);
        }

        DB::table('pagos')->whereIn('plan_id', $planesDeCurso)->update(['plan_id' => null]);
        Schema::table('cursos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            // Quedó sin uso cuando pagar en pareja pasó a ser de cada matrícula.
            $table->dropColumn('en_pareja');
        });
        DB::table('planes')->whereIn('id', $planesDeCurso)->delete();

        // Opción de menú Planes y sus permisos.
        $opcionIds = DB::table('opciones_del_sistema')->where('url', '/planes')->pluck('id');
        $permisoIds = DB::table('permissions')->whereIn('option_id', $opcionIds)->pluck('id');
        DB::table('role_has_permissions')->whereIn('permission_id', $permisoIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $permisoIds)->delete();
        DB::table('permissions')->whereIn('id', $permisoIds)->delete();
        DB::table('opciones_del_sistema')->whereIn('id', $opcionIds)->delete();
    }

    public function down(): void
    {
        // Los planes de curso eliminados no se pueden recuperar: solo se restauran las columnas.
        Schema::table('cursos', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('profesor_id')->references('id')->on('planes');
            $table->boolean('en_pareja')->default(false)->after('plan_id');
        });
    }
};
