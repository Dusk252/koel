<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class AddComposerYearGenreCommentsIntoSongs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('songs', function (Blueprint $table) {
            $table->integer('year')->after('lyrics')->nullable();
            $table->string('genre')->after('year');
            $table->string('composer')->after('genre');
            $table->text('comments')->after('composer');
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
            $table->dropColumn('year');
            $table->dropColumn('genre');
            $table->dropColumn('composer');
            $table->dropColumn('comments');
        });
    }
}