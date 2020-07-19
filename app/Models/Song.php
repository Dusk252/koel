<?php

namespace App\Models;

use App\Events\LibraryChanged;
use App\Traits\SupportsDeleteWhereIDsNotIn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use getID3;
use getid3_lib;
use getid3_writetags;
use Symfony\Component\Finder\Finder;
use SplFileInfo;

/**
 * @property string $path
 * @property string $title
 * @property Album  $album
 * @property Artist $artist
 * @property string[] $s3_params
 * @property float  $length
 * @property string $lyrics
 * @property string $genre
 * @property string $composer
 * @property int    $year
 * @property string $comments
 * @property int    $track
 * @property int    $disc
 * @property int    $album_id
 * @property string $id
 * @property int    $artist_id
 * @property int    $mtime
 */
class Song extends Model
{
    use SupportsDeleteWhereIDsNotIn;

    protected $guarded = [];

    /**
     * Attributes to be hidden from JSON outputs.
     * Here we specify to hide lyrics as well to save some bandwidth (actually, lots of it).
     * Lyrics can then be queried on demand.
     *
     * @var array
     */
    protected $hidden = ['lyrics', 'updated_at', 'path', 'mtime'];

    /**
     * @var array
     */
    protected $casts = [
        'length' => 'float',
        'mtime' => 'int',
        'track' => 'int',
        'disc' => 'int',
        'year' => 'int'
    ];

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;
    public $syncSuccess = true;

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class);
    }

    /**
     * Update song info.
     *
     * @param string[] $ids
     * @param string[] $data The data array, with these supported fields:
     *                       - title
     *                       - artistName
     *                       - albumName
     *                       - lyrics
     *                       All of these are optional, in which case the info will not be changed
     *                       (except for lyrics, which will be emptied).
     */
    public static function updateInfo(array $ids, array $data): Collection
    {
        /*
         * A collection of the updated songs.
         *
         * @var Collection
         */
        $updatedSongs = collect();

        $ids = (array) $ids;

        $writeCover = $data['albumCover']['edit'] && $data['albumCover']['value'];
        $writeCoverToFile = $writeCover;

        if ($writeCover) {
            $song = self::with('album', 'album.artist')->find(head($ids));
            $album = $song->album;
            $album->setCoverFromUrl(trim($data['albumCover']['value']));
        }

        foreach ($ids as $id) {
            if (!$song = self::with('album', 'album.artist')->find($id)) {
                continue;
            }

            $cover = array_get($song->album->attributes, 'cover');

            $updatedSongs->push($song->updateSingle(
                $data['title']['edit'] ? trim($data['title']['value']) : $song->title,
                $data['albumName']['edit'] ? trim($data['albumName']['value']) : $song->album->name,
                $data['artistName']['edit'] ? trim($data['artistName']['value']) : $song->artist->name,
                $data['lyrics']['edit'] ? trim($data['lyrics']['value']) : $song->lyrics,
                $data['track']['edit'] ? (int) $data['track']['value'] : $song->track,
                $data['disc']['edit'] ? (int) $data['disc']['value'] : $song->disc,
                $data['year']['edit'] ? (int) $data['year']['value'] : $song->year,
                $data['composer']['edit'] ? trim($data['composer']['value']) : $song->composer,
                $data['genre']['edit'] ? trim($data['genre']['value']) : $song->genre,
                $data['comments']['edit'] ? trim($data['comments']['value']) : $song->comments,
                (int) $data['compilationState'],
                $cover,
                $writeCover,
                $writeCoverToFile
            ));

            $writeCoverToFile = false;
        }

        // Our library may have been changed. Broadcast an event to tidy it up if need be.
        if ($updatedSongs->count()) {
            event(new LibraryChanged());
        }

        return $updatedSongs;
    }

    public function updateSingle(
        string $title,
        string $albumName,
        string $artistName,
        string $lyrics,
        int $track,
        int $disc,
        ?int $year,
        string $composer,
        string $genre,
        string $comments,
        int $compilationState,
        string $cover,
        bool $writeCover,
        bool $writeCoverToFile
    ): self {
        if ($artistName === Artist::VARIOUS_NAME) {
            // If the artist name is "Various Artists", it's a compilation song no matter what.
            $compilationState = 1;
            // and since we can't determine the real contributing artist, it's "Unknown"
            $artistName = Artist::UNKNOWN_NAME;
        }

        $artist = Artist::get($artistName);

        switch ($compilationState) {
            case 1: // ALL, or forcing compilation status to be Yes
                $isCompilation = true;
                break;
            case 2: // Keep current compilation status
                $isCompilation = $this->album->artist_id === Artist::VARIOUS_ID;
                break;
            default:
                $isCompilation = false;
                break;
        }

        $album = Album::get($artist, $albumName, $isCompilation, $cover);

        $this->artist_id = $artist->id;
        $this->album_id = $album->id;
        $this->title = $title;
        $this->lyrics = $lyrics;
        $this->track = (string) $track;
        $this->disc = (string) $disc;
        $this->year = (string) $year;
        $this->composer = $composer;
        $this->genre = $genre;
        $this->comments = $comments;

        $this->syncSuccess = $this->writeSongToFile($writeCover, $writeCoverToFile);
        $this->mtime = time();

        $this->save();

        // Clean up unnecessary data from the object
        unset($this->album);
        unset($this->artist);
        // and make sure the lyrics is shown
        $this->makeVisible('lyrics');

        return $this ;
    }

    /**
     * Scope a query to only include songs in a given directory.
     */
    public function scopeInDirectory(Builder $query, string $path): Builder
    {
        // Make sure the path ends with a directory separator.
        $path = rtrim(trim($path), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;

        return $query->where('path', 'LIKE', "$path%");
    }

    /**
     * Sometimes the tags extracted from getID3 are HTML entity encoded.
     * This makes sure they are always sane.
     */
    public function setTitleAttribute(string $value): void
    {
        $this->attributes['title'] = html_entity_decode($value);
    }

    /**
     * Some songs don't have a title.
     * Fall back to the file name (without extension) for such.
     */
    public function getTitleAttribute(?string $value): string
    {
        return $value ?: pathinfo($this->path, PATHINFO_FILENAME);
    }

    /**
     * Prepare the lyrics for displaying.
     */
    public function getLyricsAttribute(string $value): string
    {
        // We don't use nl2br() here, because the function actually preserves line breaks -
        // it just _appends_ a "<br />" after each of them. This would cause our client
        // implementation of br2nl to fail with duplicated line breaks.
        return str_replace(["\r\n", "\r", "\n"], '<br />', $value);
    }

    /**
     * Get the bucket and key name of an S3 object.
     *
     * @return string[]|null
     */
    public function getS3ParamsAttribute(): ?array
    {
        if (!preg_match('/^s3:\\/\\/(.*)/', $this->path, $matches)) {
            return null;
        }

        list($bucket, $key) = explode('/', $matches[1], 2);

        return compact('bucket', 'key');
    }

    /**
     * Return the ID of the song when it's converted to string.
     */
    public function __toString()
    {
        return $this->id;
    }

    /**
     * Set all applicable ID3 info from the file.
     */
    private function writeSongToFile(bool $writeCover, bool $writeCoverToFile): bool
    {

        $getID3 = new getID3;
        $getID3->setOption(array('encoding'=>'UTF-8'));

        getid3_lib::IncludeDependency(GETID3_INCLUDEPATH.'write.php', __FILE__, true);

        $tagwriter = new getid3_writetags;
        $tagwriter->filename = $this->path;

        $tagdata = array(
            'artist' => array($this->artist->name),
            'band' => array($this->artist->name),
            'album' => array($this->album->name),
            //'albumartist' => '',
            //'part_of_a_compilation' => '',
            'title' => array($this->title),
            'track' => array($this->track),
            'disc' => array($this->disc),
            'unsynchronised_lyric' => array($this->lyrics),
            'lyrics' => array($this->lyrics),
            'genre' => array($this->genre),
            'composer' => array($this->composer),
            'year' => array($this->year),
            'comment' => array($this->comments)
        );

        if ($this->dataformat == 'mp3')
            $tagwriter->tagformats = array('id3v2.3');
        else if ($this->dataformat == 'flac' || $this->dataformat == 'vorbis') {
            $uid = null;
            $commentArray = array_get($getID3->analyze($this->path), "tags.vorbiscomment.comment", [null]);
            if (count($commentArray) > 1)
                $uid = $commentArray[1];
            $tagdata['comment'] = array($this->comments, $uid);
            
            if ($this->dataformat == 'flac')
                $tagwriter->tagformats = array('metaflac');
            else
                $tagwriter->tagformats = array('vorbiscomment');
        }
        else
            return false;
		$tagwriter->overwrite_tags = true;
        $tagwriter->tag_encoding   = 'UTF-8';
        $tagwriter->remove_other_tags = false;

        //write cover to folder
        if ($writeCover && $this->album->getHasCoverAttribute()) {
            $cover = $this->album->getCoverPathAttribute();
            $extension = last(explode('.', $cover));
            $extension = head(explode(':', $extension));
            $extension = trim(strtolower($extension), '. ');
            $filefolder = implode('\\', explode('\\', $this->path, -1));
            $destination = sprintf('%s\cover.%s', $filefolder, $extension);

            if ($coverData = file_get_contents($cover)) {
                if ($exif_imagetype = exif_imagetype($cover)) {

                    //change embedded cover info
                    $tagdata['attached_picture'][0]['data']          = $coverData;
                    $tagdata['attached_picture'][0]['picturetypeid'] = 2;
                    $tagdata['attached_picture'][0]['description']   = 'cover';
                    $tagdata['attached_picture'][0]['mime']          = image_type_to_mime_type($exif_imagetype);

                    if ($writeCoverToFile) {
                        //delete any existing cover files
                        $matches = array_keys(iterator_to_array(
                            Finder::create()
                                ->depth(0)
                                ->ignoreUnreadableDirs()
                                ->files()
                                ->followLinks()
                                ->name('/(cov|fold)er\.(jpe?g|png)$/i')
                                ->in(dirname($this->path))
                        ));
        
                        foreach($matches as $file){ // iterate files
                            if($this->isImage($file))
                                unlink($file); // delete file
                        }
        
                        //save new cover
                        file_put_contents($destination, $coverData);
                    }
                }
            }
        }

        $tagwriter->tag_data = $tagdata;
        if ($tagwriter->WriteTags()) {
            return true;
        }
        return false;
    }

    private function isImage(string $path): bool
    {
        try {
            return (bool) exif_imagetype($path);
        } catch (Exception $e) {
            return false;
        }
    }
}