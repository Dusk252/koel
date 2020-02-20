<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreatePlayInteractionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('play_interactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('interaction_id')->unsigned();;
            $table->timestamp('created_at');

            $table->foreign('interaction_id')->references('id')->on('interactions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('play_interactions');
    }
}