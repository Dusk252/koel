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
        $this->getID3 = $getID3;
    }

    public function getFileHash(string $path): string
    {
        $info = $this->getID3->analyze($path);
        $fileHash = array_get($info, 'md5_data');
        $salt = "";
        $dataformat = array_get($info, 'audio.dataformat');
        if ($dataformat != null) {
            if ($dataformat == 'mp3' || $dataformat == 'm4a')
                $salt = array_get($info, "id3v1.comments.comment", [null])[0];
            else if ($dataformat == 'flac' || $dataformat == 'vorbis' || $dataformat == 'ogg')
                $salt = array_get($info, "tags.vorbiscomment.comment", [null])[0];
            
            if ($salt == null || substr($salt, 0, 5) != "salt=") {
                $salt = "salt=".uniqid('', true);
                $this->writeSaltToFile($path, $salt, $dataformat, $info);
            }
        }
        return md5($fileHash.$salt);
    }

    private function writeSaltToFile(string $path, string $salt, string $dataformat, array $info): bool
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
            'comment' => array($salt)
        );

        if ($dataformat == 'mp3')
            $tagwriter->tagformats = array('id3v1');
        else if ($dataformat == 'flac') {
            $tagwriter->tagformats = array('metaflac');
            $tagdata = array_merge(array_get($info, 'flac.comments'), array(
                'comment' => array($salt)
            ));
        }
        else if ($dataformat == 'vorbis' || $dataformat == 'ogg')
            $tagwriter->tagformats = array('vorbiscomment');
        else
            return false;

        $tagwriter->tag_data = $tagdata;
        if ($tagwriter->WriteTags()) {
            return true;
        }
        return false;
    }
}
