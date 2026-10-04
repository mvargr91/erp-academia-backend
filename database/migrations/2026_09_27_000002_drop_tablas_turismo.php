<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina las tablas del proyecto anterior de turismo (el código y sus migraciones
 * ya se quitaron). También borra sus registros de la tabla migrations para que
 * migrate:status / rollback no busquen archivos que ya no existen.
 * Irreversible: los datos se respaldaron antes de ejecutarla.
 */
return new class extends Migration
{
    private const TABLAS = [
        'promocion_experiencia', 'promociones', 'multimedia_resena', 'resenas', 'favoritos',
        'acompanantes_reserva', 'reservas', 'experiencia_disponibilidad', 'experiencia_horarios',
        'experiencia_caracteristica', 'experiencia_multimedia', 'experiencia_precio_cantidades',
        'experiencia_precios', 'experiencia_categoria', 'experiencias', 'cupones',
        'documentos_proveedor', 'proveedores_turisticos', 'caracteristicas', 'categorias',
        'destinos', 'notificaciones',
    ];

    private const MIGRACIONES = [
        '2026_09_23_000001_create_destinos_table',
        '2026_09_23_000002_create_categorias_table',
        '2026_09_23_000003_create_caracteristicas_table',
        '2026_09_23_000004_create_proveedores_turisticos_table',
        '2026_09_23_000005_create_documentos_proveedor_table',
        '2026_09_23_000006_create_cupones_table',
        '2026_09_23_000007_create_experiencias_table',
        '2026_09_23_000008_create_experiencia_categoria_table',
        '2026_09_23_000009_create_experiencia_precios_table',
        '2026_09_23_000010_create_experiencia_multimedia_table',
        '2026_09_23_000011_create_experiencia_caracteristica_table',
        '2026_09_23_000012_create_experiencia_horarios_table',
        '2026_09_23_000013_create_experiencia_disponibilidad_table',
        '2026_09_23_000014_create_reservas_table',
        '2026_09_23_000015_create_acompanantes_reserva_table',
        '2026_09_23_000016_create_favoritos_table',
        '2026_09_23_000017_create_resenas_table',
        '2026_09_23_000018_create_multimedia_resena_table',
        '2026_09_23_000019_create_promociones_table',
        '2026_09_23_000020_create_promocion_experiencia_table',
        '2026_09_23_000021_create_notificaciones_table',
        '2026_09_24_000001_create_experiencia_precio_cantidades_table',
    ];

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (self::TABLAS as $tabla) {
            Schema::dropIfExists($tabla);
        }
        Schema::enableForeignKeyConstraints();

        DB::table('migrations')->whereIn('migration', self::MIGRACIONES)->delete();
    }

    public function down(): void
    {
        // Sin reversa: las tablas pertenecían a otro proyecto.
    }
};
