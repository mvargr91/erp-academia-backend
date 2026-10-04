<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BD central. Se ejecuta aparte de las migraciones de cada academia:
 *   php artisan migrate --database=central --path=database/migrations/central
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('central')->create('academias', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->string('nombre', 150);
            $table->string('base_datos', 64)->unique();
            $table->string('correo', 150)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->unsignedTinyInteger('dia_pago')->default(5);
            $table->boolean('activa')->default(true);
            $table->boolean('es_administradora')->default(false);
            $table->timestamps();
        });

        // Cola compartida por todas las academias (un solo worker).
        Schema::connection('central')->create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::connection('central')->create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::connection('central')->create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('failed_jobs');
        Schema::connection('central')->dropIfExists('job_batches');
        Schema::connection('central')->dropIfExists('jobs');
        Schema::connection('central')->dropIfExists('academias');
    }
};
