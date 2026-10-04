<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Temporadas en que la academia no dicta clases (vacaciones, eventos): igual que un festivo,
        // esas semanas no cuentan y el ciclo de clases se corre.
        Schema::create('cierres_academia', function (Blueprint $table) {
            $table->id();
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->string('motivo', 150);
            $table->bigInteger('usuario_creacion_id');
            $table->string('usuario_creacion_nombre', 128);
            $table->bigInteger('usuario_modificacion_id');
            $table->string('usuario_modificacion_nombre', 128);
            $table->timestamps();
        });

        // Fecha de la 1.ª clase del ciclo que el alumno tiene pago/causado. El fin del ciclo
        // (y su próximo pago) se calcula con el calendario del curso, saltando festivos y cierres.
        Schema::table('curso_alumno', function (Blueprint $table) {
            $table->date('ciclo_inicio')->nullable()->after('fecha_matricula');
        });
    }

    public function down(): void
    {
        Schema::table('curso_alumno', function (Blueprint $table) {
            $table->dropColumn('ciclo_inicio');
        });
        Schema::dropIfExists('cierres_academia');
    }
};
