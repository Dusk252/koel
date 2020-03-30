<?php

namespace App\Services;

use getID3;
use getid3_lib;
use getid3_writetags;

class HelperService
{
    /**
     * Get a unique hash from a file.
     * ID3tags can be changed upon synchronizing so they aren't taken into consideration.
     * This hash can then be used as the Song record's ID.
     */
    private $getID3;

    public function __construct(getID3 $getID3)
    {
        setlocale(LC_CTYPE, "en_US.UTF-8");
        $this->getID3 = $getID3;
    }

    public function getFileHash(string $path): ?string
    {
        $info = $this->getID3->analyze($path);
        $uid = null;
        $dataformat = array_get($info, 'audio.dataformat');
        if ($dataformat != null) {
            if ($dataformat == 'mp3')
                $uid = array_get($info, "id3v1.comments.comment", [null])[0];
            else if ($dataformat == 'flac' || $dataformat == 'vorbis') {
                $commentArray = array_get($info, "tags.vorbiscomment.comment", [null]);
                if (count($commentArray) > 1)
                    $uid = $commentArray[1];
            }     
            
            if ($uid == null || substr($uid, 0, 4) != "uid=") {
                $uid = "uid=".uniqid('', true);
                if (!$this->writeuidToFile($path, $uid, $dataformat, $info))
                    return null;
            }
        }
        $test = md5($uid);
        return $test;
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
