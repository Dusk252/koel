<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddUpdatedAtIntoPlayInteractions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('play_interactions', function (Blueprint $table) {
            $table->timestamp('updated_at')->after('created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('play_interactions', function (Blueprint $table) {
            $table->dropColumn('updated_at');
        });
    }
}