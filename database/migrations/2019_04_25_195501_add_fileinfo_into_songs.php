<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddFileInfoIntoSongs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->string('dataformat')->after('comments')->nullable();
            $table->string('bitrate_mode')->after('dataformat')->nullable();
            $table->integer('bitrate')->after('bitrate_mode')->unsigned()->nullable();
            $table->integer('sample_rate')->after('bitrate')->unsigned()->nullable();;
            $table->string('channel_mode')->after('sample_rate')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->dropColumn('dataformat');
            $table->dropColumn('bitrate_mode');
            $table->dropColumn('bitrate');
            $table->dropColumn('sample_rate');
            $table->dropColumn('channel_mode');
        });
    }
}