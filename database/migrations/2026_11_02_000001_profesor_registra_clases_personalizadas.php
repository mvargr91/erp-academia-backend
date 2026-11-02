<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Support\Academias\GestorAcademias;

/**
 * El rol Profesor registra la asistencia de sus clases personalizadas (solo las suyas y sin el cobro,
 * ver ClasePrivadaController). AcademiaMenuSeeder ya lo incluye al crear el rol; aquí se agrega al rol
 * de las academias que ya lo tenían.
 */
return new class extends Migration
{
    private const PERMISO = 'RegistrarAsistenciaClasePrivada';

    public function up(): void
    {
        $rolId = DB::table('roles')->where('name', 'Profesor')->where('guard_name', 'api')->value('id');
        $permisoId = DB::table('permissions')->where('name', self::PERMISO)->where('guard_name', 'api')->value('id');
        if ($rolId && $permisoId) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permisoId, 'role_id' => $rolId]);
            GestorAcademias::olvidarCachePermisos();
        }
    }

    public function down(): void
    {
        $rolId = DB::table('roles')->where('name', 'Profesor')->where('guard_name', 'api')->value('id');
        $permisoId = DB::table('permissions')->where('name', self::PERMISO)->where('guard_name', 'api')->value('id');
        if ($rolId && $permisoId) {
            DB::table('role_has_permissions')->where(['permission_id' => $permisoId, 'role_id' => $rolId])->delete();
            GestorAcademias::olvidarCachePermisos();
        }
    }
};
