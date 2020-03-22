<?php

namespace App\Services;

use getID3;

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
        return $fileHash;
    }
}
