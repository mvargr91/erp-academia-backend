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
        Schema::table('permissions', function (Blueprint $table) {
            $table->string('title')->nullable();
            $table->foreignId('option_id')->references('id')->on('opciones_del_sistema');

            // Auditoria
            $table->bigInteger('user_creation_id');
            $table->string('user_creation_name',128);
            $table->bigInteger('user_modification_id');
            $table->string('user_modification_name',128);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn('title');
            $table->dropForeign(['option_id']);
            $table->dropColumn('option_id');

            // Auditoria
            $table->dropColumn('user_creation_id');
            $table->dropColumn('user_creation_name');
            $table->dropColumn('user_modification_id');
            $table->dropColumn('user_modification_name');
        });
    }
};
