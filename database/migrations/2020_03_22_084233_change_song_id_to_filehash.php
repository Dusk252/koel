<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class ChangeSongIdToFileHash extends Migration
{
    private $getID3;

    public function __construct()
    {
        $this->getID3 = new getID3();
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        //update foreign keys that reference songId to update on change
        Schema::table('playlist_song', function (Blueprint $table) {
            $table->dropForeign('playlist_song_song_id_foreign');
            $table->foreign('song_id')->references('id')->on('songs')->onDelete('cascade')->onUpdate('cascade');
        });
        Schema::table('interactions', function (Blueprint $table) {
            $table->dropForeign('interactions_song_id_foreign');
            $table->foreign('song_id')->references('id')->on('songs')->onDelete('cascade')->onUpdate('cascade');
        });

        //for each entry, use path to get to file and retrieve the new id
        $results = DB::table('songs')->select('id','path')->get();
    
        foreach ($results as $result){
            $new_id = $this->getHashFromData($result->path);
            DB::table('songs')
                ->where('id', $result->id)
                ->update([
                    "id" => $new_id
            ]);
        }

        //get the foreign keys back to only cascade on delete
        Schema::table('playlist_song', function (Blueprint $table) {
            $table->dropForeign('playlist_song_song_id_foreign');
            $table->foreign('song_id')->references('id')->on('songs')->onDelete('cascade');
        });
        Schema::table('interactions', function (Blueprint $table) {
            $table->dropForeign('interactions_song_id_foreign');
            $table->foreign('song_id')->references('id')->on('songs')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('playlist_song', function (Blueprint $table) {
            $table->dropForeign('playlist_song_song_id_foreign');
            $table->foreign('song_id')->references('id')->on('songs')->onDelete('cascade')->onUpdate('cascade');
        });
        Schema::table('interactions', function (Blueprint $table) {
            $table->dropForeign('interactions_song_id_foreign');
            $table->foreign('song_id')->references('id')->on('songs')->onDelete('cascade')->onUpdate('cascade');
        });

        $results = DB::table('songs')->select('id','path')->get();
    
        foreach ($results as $result){
            $new_id = $this->getHashFromPath($result->path);
            DB::table('songs')
                ->where('id',$result->id)
                ->update([
                    "id" => $new_id
            ]);
        }

        Schema::table('playlist_song', function (Blueprint $table) {
            $table->dropForeign('playlist_song_song_id_foreign');
            $table->foreign('song_id')->references('id')->on('songs')->onDelete('cascade');
        });
        Schema::table('interactions', function (Blueprint $table) {
            $table->dropForeign('interactions_song_id_foreign');
            $table->foreign('song_id')->references('id')->on('songs')->onDelete('cascade');
        });
    }

    private function getHashFromData(string $path): string {
        $info = $this->getID3->analyze($path);
        $fileHash = array_get($info, 'md5_data');
        return $fileHash;
    }

    private function getHashFromPath(string $path): string {
        return md5(config('app.key').$path);
    }
}