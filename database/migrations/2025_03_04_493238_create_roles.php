<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->enum('type', ['IN', 'EX'])->nullable();
            $table->boolean('status')->default(true);
            $table->bigInteger('creation_user_id')->nullable();
            $table->string('creation_user_name')->nullable();
            $table->bigInteger('modification_user_id')->nullable();
            $table->string('modification_user_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('roles');
    }
};
