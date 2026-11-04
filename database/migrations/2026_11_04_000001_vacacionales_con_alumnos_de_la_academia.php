<?php

use Illuminate\Support\Str;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los vacacionales usan los alumnos de la academia (una sola ficha por persona) en lugar de su tabla
 * propia. Cada alumno de vacacionales se enlaza con el alumno de la academia que tenga el mismo nombre
 * y el mismo documento, correo o teléfono; si no hay, se crea (el acudiente queda como contacto de
 * emergencia). Las inscripciones pasan a apuntar a alumnos. vacacional_alumnos se conserva con el
 * enlace (alumno_id) para poder revertir.
 */
return new class extends Migration
{
    private static function normalizar(?string $texto): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii((string) $texto))));
    }

    public function up(): void
    {
        Schema::table('vacacional_alumnos', function (Blueprint $table) {
            $table->foreignId('alumno_id')->nullable()->after('id')->references('id')->on('alumnos');
        });

        $porNombre = [];
        foreach (DB::table('alumnos')->get(['id', 'nombres', 'apellidos', 'documento', 'correo', 'telefono']) as $a) {
            $porNombre[self::normalizar("{$a->nombres} {$a->apellidos}")][] = $a;
        }
        foreach (DB::table('vacacional_alumnos')->orderBy('id')->get() as $v) {
            $igual = collect($porNombre[self::normalizar("{$v->nombres} {$v->apellidos}")] ?? [])->first(fn ($a) =>
                ($v->documento && trim($a->documento ?? '') === trim($v->documento))
                || ($v->correo && self::normalizar($a->correo) === self::normalizar($v->correo))
                || ($v->telefono && trim($a->telefono ?? '') === trim($v->telefono)));
            $alumnoId = $igual->id ?? DB::table('alumnos')->insertGetId([
                // La sede del alumno: la del primer curso vacacional en que se inscribió.
                'sede_id' => DB::table('vacacional_inscripciones as i')->join('vacacional_cursos as c', 'c.id', '=', 'i.curso_id')
                    ->where('i.alumno_id', $v->id)->orderBy('i.id')->value('c.sede_id'),
                'nombres' => $v->nombres,
                'apellidos' => $v->apellidos,
                'documento' => $v->documento,
                'fecha_nacimiento' => $v->fecha_nacimiento,
                'telefono' => $v->telefono,
                'correo' => $v->correo,
                'contacto_emergencia' => $v->acudiente_nombre,
                'telefono_emergencia' => $v->acudiente_telefono,
                'estado' => $v->estado,
                'usuario_creacion_id' => $v->usuario_creacion_id,
                'usuario_creacion_nombre' => $v->usuario_creacion_nombre,
                'usuario_modificacion_id' => $v->usuario_modificacion_id,
                'usuario_modificacion_nombre' => $v->usuario_modificacion_nombre,
                'created_at' => $v->created_at,
                'updated_at' => $v->updated_at,
            ]);
            DB::table('vacacional_alumnos')->where('id', $v->id)->update(['alumno_id' => $alumnoId]);
        }

        // El índice único (curso_id, alumno_id) se queda: lo usa la llave de curso_id.
        Schema::table('vacacional_inscripciones', function (Blueprint $table) {
            $table->dropForeign(['alumno_id']);
        });
        DB::statement('UPDATE vacacional_inscripciones i JOIN vacacional_alumnos v ON v.id = i.alumno_id SET i.alumno_id = v.alumno_id');
        Schema::table('vacacional_inscripciones', function (Blueprint $table) {
            $table->foreign('alumno_id')->references('id')->on('alumnos');
        });
    }

    public function down(): void
    {
        Schema::table('vacacional_inscripciones', function (Blueprint $table) {
            $table->dropForeign(['alumno_id']);
        });
        // Solo se pueden devolver las inscripciones de alumnos que venían de vacacional_alumnos.
        DB::statement('UPDATE vacacional_inscripciones i JOIN vacacional_alumnos v ON v.alumno_id = i.alumno_id SET i.alumno_id = v.id');
        Schema::table('vacacional_inscripciones', function (Blueprint $table) {
            $table->foreign('alumno_id')->references('id')->on('vacacional_alumnos');
        });
        Schema::table('vacacional_alumnos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('alumno_id');
        });
    }
};
