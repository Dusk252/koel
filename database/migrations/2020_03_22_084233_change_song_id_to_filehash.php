<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class ChangeSongIdToFileHash extends Migration
{
    private $getID3;

    public function __construct()
    {
        $this->getID3 = new getID3;
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
            if ($new_id == null) {
                DB::table('songs')
                    ->where('id',$result->id)
                    ->delete();
            }
            else {
                DB::table('songs')
                ->where('id',$result->id)
                ->update([
                    "id" => $new_id
            ]);
            }
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
            ->where('id', $result->id)
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

    private function getHashFromData(string $path): ?string {
        $info = $this->getID3->analyze($path);
        $uid = "uid=".uniqid('', true);
        if ($this->writeuidToFile($path, $uid, (array_get($info, 'audio.dataformat') ?: ''), $info))
            return md5($uid);
        else
            return null;
    }

    private function getHashFromPath(string $path): string {
        return md5(config('app.key').$path);
    }

    private function writeuidToFile(string $path, string $uid, string $dataformat, array $info): bool
    {
        $getID3 = new getID3;
        $getID3->setOption(array('encoding'=>'UTF-8'));

        getid3_lib::IncludeDependency(GETID3_INCLUDEPATH.'write.php', __FILE__, true);

        $tagwriter = new getid3_writetags;
        $tagwriter->filename = $path;

        $tagwriter->tag_encoding   = 'UTF-8';
        $tagwriter->remove_other_tags = false;
        $tagwriter->overwrite_tags = true;

        $tagdata = array(
            'comment' => array($uid)
        );

        if ($dataformat == 'mp3')
            $tagwriter->tagformats = array('id3v1');
        else if ($dataformat == 'flac' || $dataformat == 'vorbis') {
            $tagdata = array_merge(array_get($info, 'tags.vorbiscomment'), array(
                'comment' => array(array_get($info, "tags.vorbiscomment.comment", ["."])[0], $uid)
            ));
            if ($dataformat == 'flac')
                $tagwriter->tagformats = array('metaflac');
            else
                $tagwriter->tagformats = array('vorbiscomment');
        }
        else
            return false;

        $tagwriter->tag_data = $tagdata;
        if ($tagwriter->WriteTags()) {
            return true;
        }
        return false;
    }
}