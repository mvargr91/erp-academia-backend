<?php

use Carbon\Carbon;
use App\Services\Academia\Tarifas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\Academia\CalendarioCurso;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Un curso y un paquete de clases son cosas distintas: los cursos se pagan siempre por ciclos
        // y los paquetes son solo de clases personalizadas. Las matrículas que estaban "por paquete"
        // pasan a ciclo desde la próxima clase del curso (se les carga ese primer ciclo) y las clases
        // de curso que se habían descontado de un paquete se le devuelven.
        $calendario = new CalendarioCurso();
        $porPaquete = DB::table('curso_alumno')->where('modalidad', 'paquete')->orderBy('id')->get();
        DB::table('curso_alumno')->where('modalidad', 'paquete')->update(['modalidad' => 'ciclo']);
        foreach ($porPaquete->where('estado', 1) as $m) {
            $curso = DB::table('cursos')->where('id', $m->curso_id)->first();
            if (!$curso) {
                continue;
            }
            DB::table('curso_alumno')->where('id', $m->id)->update([
                'ciclo_inicio' => $calendario->primeraClase($curso, Carbon::today())->toDateString(),
            ]);
            DB::table('curso_alumno')->where('id', $m->id)->update([
                'saldo' => (float) $m->saldo + Tarifas::precio($m->id)['valor'],
            ]);
        }
        DB::table('consumos_paquete')->where('origen', 'grupal')->delete();

        Schema::table('curso_alumno', function (Blueprint $table) {
            $table->dropColumn('modalidad');
        });
    }

    public function down(): void
    {
        Schema::table('curso_alumno', function (Blueprint $table) {
            $table->string('modalidad', 10)->default('ciclo')->after('ciclo_inicio');
        });
    }
};
