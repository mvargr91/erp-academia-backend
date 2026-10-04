<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sedes de la academia. Lo que ocurre en un lugar físico lleva su sede (cursos, clases privadas,
 * pagos, paquetes); alumnos, profesores y ritmos son de toda la academia. En planes y cierres la
 * sede es opcional: vacía = aplica a todas.
 * Todo lo existente queda en una sede "Principal", así una academia de una sola sede no nota el cambio.
 */
return new class extends Migration
{
    // tabla => columna tras la que se agrega sede_id
    private const CON_SEDE = [
        'cursos' => 'nombre',
        'clases_privadas' => 'profesor_id',
        'pagos' => 'alumno_id',
        'paquetes_alumno' => 'alumno_id',
        'alumnos' => 'usuario_id',
        'planes' => 'nombre',
        'cierres_academia' => 'motivo',
    ];

    // Tablas cuyos registros existentes pasan a la sede Principal (planes y cierres quedan generales).
    private const A_PRINCIPAL = ['cursos', 'clases_privadas', 'pagos', 'paquetes_alumno', 'alumnos'];

    public function up()
    {
        Schema::create('sedes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->string('direccion', 255)->nullable();
            $table->string('ciudad', 100)->nullable();
            $table->string('telefono', 30)->nullable();

            // Estado
            $table->boolean('estado')->default(true);

            // Auditoria
            $table->bigInteger('usuario_creacion_id');
            $table->string('usuario_creacion_nombre', 128);
            $table->bigInteger('usuario_modificacion_id');
            $table->string('usuario_modificacion_nombre', 128);
            $table->timestamps();
        });

        $principalId = DB::table('sedes')->insertGetId([
            'nombre' => 'Principal',
            'estado' => true,
            'usuario_creacion_id' => 1,
            'usuario_creacion_nombre' => 'SuperUser',
            'usuario_modificacion_id' => 1,
            'usuario_modificacion_nombre' => 'SuperUser',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        foreach (self::CON_SEDE as $tabla => $despuesDe) {
            Schema::table($tabla, function (Blueprint $table) use ($despuesDe) {
                $table->foreignId('sede_id')->nullable()->after($despuesDe)->references('id')->on('sedes');
            });
        }

        foreach (self::A_PRINCIPAL as $tabla) {
            DB::table($tabla)->update(['sede_id' => $principalId]);
        }
    }

    public function down()
    {
        foreach (array_keys(self::CON_SEDE) as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropConstrainedForeignId('sede_id');
            });
        }
        Schema::dropIfExists('sedes');
    }
};
