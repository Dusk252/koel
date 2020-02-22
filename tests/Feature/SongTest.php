<?php

namespace Tests\Feature;

use App\Events\LibraryChanged;
use App\Models\Album;
use App\Models\Artist;
use App\Models\Song;
use App\Models\User;
use Exception;

class SongTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->createSampleMediaSet();
    }

    /**
     * @throws Exception
     */
    public function testSingleUpdateAllInfoNoCompilation(): void
    {
        $this->expectsEvents(LibraryChanged::class);
        $song = Song::orderBy('id', 'desc')->first();

        $user = factory(User::class, 'admin')->create();
        $this->putAsUser('/api/songs', [
            'songs' => [$song->id],
            'data' => [
                'title' => [
                    'value' => 'Foo Bar',
                    'edit' => true
                ],
                'artistName' => [
                    'value' => 'Band',
                    'edit' => true
                ],
                'albumName' => [
                    'value' => 'Album',
                    'edit' => true
                ],
                'lyrics' => [
                    'value' => 'Lorem ipsum dolor sic amet.',
                    'edit' => true
                ],
                'track' => [
                    'value' => 1,
                    'edit' => true
                ],
                'disc' => [
                    'value' => 1,
                    'edit' => true
                ],
                'year' => [
                    'value' => 2020,
                    'edit' => true
                ],
                'composer' => [
                    'value' => 'Composer',
                    'edit' => true
                ],
                'genre' => [
                    'value' => 'Stop labelling stuff',
                    'edit' => true
                ],
                'comments' => [
                    'value' => '',
                    'edit' => true
                ],
                'compilationState' => 0,
            ],
        ], $user)
            ->seeStatusCode(200);

        $artist = Artist::whereName('Band')->first();
        $this->assertNotNull($artist);

        $album = Album::whereName('Album')->first();
        $this->assertNotNull($album);

        $this->seeInDatabase('songs', [
            'id' => $song->id,
            'album_id' => $album->id,
            'lyrics' => 'Lorem ipsum dolor sic amet.',
            'track' => 1,
            'disc' => 1,
            'composer' => 'Composer',
            'genre' => 'Stop labelling stuff',
            'comments' => '',
            'compilationState' => 0
        ]);
    }

    public function testSingleUpdateSomeInfoNoCompilation(): void
    {
        $song = Song::orderBy('id', 'desc')->first();
        $originalArtistId = $song->album->artist->id;

        $user = factory(User::class, 'admin')->create();
        $this->putAsUser('/api/songs', [
            'songs' => [$song->id],
            'data' => [
                'title' => [
                    'value' => 'Foo Bar',
                    'edit' => true
                ],
                'artistName' => [
                    'value' => 'A Band',
                    'edit' => false
                ],
                'albumName' => [
                    'value' => 'Album',
                    'edit' => true
                ],
                'lyrics' => [
                    'value' => 'Lorem ipsum dolor sic amet.',
                    'edit' => true
                ],
                'track' => [
                    'value' => 1,
                    'edit' => true
                ],
                'disc' => [
                    'value' => 1,
                    'edit' => true
                ],
                'year' => [
                    'value' => 2020,
                    'edit' => true
                ],
                'composer' => [
                    'value' => 'Composer',
                    'edit' => true
                ],
                'genre' => [
                    'value' => 'Stop labelling stuff',
                    'edit' => true
                ],
                'comments' => [
                    'value' => '',
                    'edit' => true
                ],
                'compilationState' => 0,
            ],
        ], $user)
            ->seeStatusCode(200);

        // We don't expect the song's artist to change
        $this->assertEquals($originalArtistId, Song::find($song->id)->album->artist->id);

        // But we expect a new album to be created for this artist and contain this song
        $this->assertEquals('Album', Song::find($song->id)->album->name);
    }

    public function testMultipleUpdateAllInfoNoCompilation(): void
    {
        $songIds = Song::orderBy('id', 'desc')->take(3)->pluck('id')->toArray();

        $user = factory(User::class, 'admin')->create();
        $this->putAsUser('/api/songs', [
            'songs' => $songIds,
            'data' => [
                'title' => [
                    'value' => 'foo',
                    'edit' => false
                ],
                'artistName' => [
                    'value' => 'A Band',
                    'edit' => false
                ],
                'albumName' => [
                    'value' => 'An Album',
                    'edit' => true
                ],
                'lyrics' => [
                    'value' => 'bar',
                    'edit' => false
                ],
                'track' => [
                    'value' => 9999,
                    'edit' => false
                ],
                'disc' => [
                    'value' => 1,
                    'edit' => true
                ],
                'year' => [
                    'value' => 2020,
                    'edit' => true
                ],
                'composer' => [
                    'value' => 'Composer',
                    'edit' => true
                ],
                'genre' => [
                    'value' => 'Stop labelling stuff',
                    'edit' => true
                ],
                'comments' => [
                    'value' => '',
                    'edit' => true
                ],
                'compilationState' => 0,
            ],
        ], $user)
            ->seeStatusCode(200);

        $songs = Song::orderBy('id', 'desc')->take(3)->get();

        // Even though we post the title, lyrics, and tracks, we don't expect them to take any effect
        // because we're updating multiple songs here.
        $this->assertNotEquals('Foo', $songs[0]->title);
        $this->assertNotEquals('bar', $songs[2]->lyrics);
        $this->assertNotEquals(9999, $songs[2]->track);

        // But all of these songs must now belong to a new album and artist set
        $this->assertEquals('An Album', $songs[0]->album->name);
        $this->assertEquals('An Album', $songs[1]->album->name);
        $this->assertEquals('An Album', $songs[2]->album->name);

        $this->assertEquals('A Band', $songs[0]->album->artist->name);
        $this->assertEquals('A Band', $songs[1]->album->artist->name);
        $this->assertEquals('A Band', $songs[2]->album->artist->name);
    }

    public function testMultipleUpdateSomeInfoNoCompilation(): void
    {
        $originalSongs = Song::orderBy('id', 'desc')->take(3)->get();
        $songIds = $originalSongs->pluck('id')->toArray();

        $user = factory(User::class, 'admin')->create();
        $this->putAsUser('/api/songs', [
            'songs' => $songIds,
            'data' => [
                'title' => [
                    'value' => 'foo',
                    'edit' => false
                ],
                'artistName' => [
                    'value' => 'A Band',
                    'edit' => false
                ],
                'albumName' => [
                    'value' => '',
                    'edit' => false
                ],
                'lyrics' => [
                    'value' => 'bar',
                    'edit' => false
                ],
                'track' => [
                    'value' => 9999,
                    'edit' => false
                ],
                'disc' => [
                    'value' => 1,
                    'edit' => true
                ],
                'year' => [
                    'value' => 2020,
                    'edit' => true
                ],
                'composer' => [
                    'value' => 'Composer',
                    'edit' => true
                ],
                'genre' => [
                    'value' => 'Stop labelling stuff',
                    'edit' => true
                ],
                'comments' => [
                    'value' => '',
                    'edit' => true
                ],
                'compilationState' => 0,
            ],
        ], $user)
            ->seeStatusCode(200);

        $songs = Song::orderBy('id', 'desc')->take(3)->get();

        // Even though the album name doesn't change, a new artist should have been created
        // and thus, a new album with the same name was created as well.
        $this->assertEquals($songs[0]->album->name, $originalSongs[0]->album->name);
        $this->assertNotEquals($songs[0]->album->id, $originalSongs[0]->album->id);
        $this->assertEquals($songs[1]->album->name, $originalSongs[1]->album->name);
        $this->assertNotEquals($songs[1]->album->id, $originalSongs[1]->album->id);
        $this->assertEquals($songs[2]->album->name, $originalSongs[2]->album->name);
        $this->assertNotEquals($songs[2]->album->id, $originalSongs[2]->album->id);

        // And of course, the new artist is...
        $this->assertEquals('A Band', $songs[0]->album->artist->name);
        $this->assertEquals('A Band', $songs[1]->album->artist->name);
        $this->assertEquals('A Band', $songs[2]->album->artist->name);
    }

    public function testSingleUpdateAllInfoYesCompilation(): void
    {
        $song = Song::orderBy('id', 'desc')->first();

        $user = factory(User::class, 'admin')->create();
        $this->putAsUser('/api/songs', [
            'songs' => [$song->id],
            'data' => [
                'title' => [
                    'value' => 'Foo Bar',
                    'edit' => true
                ],
                'artistName' => [
                    'value' => 'Band',
                    'edit' => true
                ],
                'albumName' => [
                    'value' => 'Album',
                    'edit' => true
                ],
                'lyrics' => [
                    'value' => 'Lorem ipsum dolor sic amet.',
                    'edit' => true
                ],
                'track' => [
                    'value' => 1,
                    'edit' => true
                ],
                'disc' => [
                    'value' => 1,
                    'edit' => true
                ],
                'year' => [
                    'value' => 2020,
                    'edit' => true
                ],
                'composer' => [
                    'value' => 'Composer',
                    'edit' => true
                ],
                'genre' => [
                    'value' => 'Stop labelling stuff',
                    'edit' => true
                ],
                'comments' => [
                    'value' => '',
                    'edit' => true
                ],
                'compilationState' => 1,
            ],
        ], $user)
            ->seeStatusCode(200);

        $compilationAlbum = Album::whereArtistIdAndName(Artist::VARIOUS_ID, 'Album')->first();
        $this->assertNotNull($compilationAlbum);

        $artist = Artist::whereName('Band')->first();
        $this->assertNotNull($artist);

        $this->seeInDatabase('songs', [
            'id' => $song->id,
            'album_id' => $album->id,
            'lyrics' => 'Lorem ipsum dolor sic amet.',
            'track' => 1,
            'disc' => 1,
            'composer' => 'Composer',
            'genre' => 'Stop labelling stuff',
            'comments' => '',
            'track' => 1
        ]);

        // Now try changing stuff and make sure things work.
        // Case 1: Keep compilation state and artist the same
        $this->putAsUser('/api/songs', [
            'songs' => [$song->id],
            'data' => [
                'title' => [
                    'value' => 'Foo Bar',
                    'edit' => true
                ],
                'artistName' => [
                    'value' => 'Band',
                    'edit' => true
                ],
                'albumName' => [
                    'value' => 'Another Album',
                    'edit' => true
                ],
                'lyrics' => [
                    'value' => 'Lorem ipsum dolor sic amet.',
                    'edit' => true
                ],
                'track' => [
                    'value' => 1,
                    'edit' => true
                ],
                'disc' => [
                    'value' => 1,
                    'edit' => true
                ],
                'year' => [
                    'value' => 2020,
                    'edit' => true
                ],
                'composer' => [
                    'value' => 'Composer',
                    'edit' => true
                ],
                'genre' => [
                    'value' => 'Stop labelling stuff',
                    'edit' => true
                ],
                'comments' => [
                    'value' => '',
                    'edit' => true
                ],
                'compilationState' => 2,
            ],
        ], $user)
            ->seeStatusCode(200);

        $compilationAlbum = Album::whereArtistIdAndName(Artist::VARIOUS_ID, 'Another Album')->first();
        $this->assertNotNull($compilationAlbum);

        $contributingArtist = Artist::whereName('Band')->first();
        $this->assertNotNull($contributingArtist);

        $this->seeInDatabase('songs', [
            'id' => $song->id,
            'artist_id' => $contributingArtist->id,
            'album_id' => $compilationAlbum->id,
        ]);

        // Case 2: Keep compilation state, but change the artist.
        $this->putAsUser('/api/songs', [
            'songs' => [$song->id],
            'data' => [
                'title' => [
                    'value' => 'Foo Bar',
                    'edit' => true
                ],
                'artistName' => [
                    'value' => 'Another Band',
                    'edit' => true
                ],
                'albumName' => [
                    'value' => 'Another Album',
                    'edit' => true
                ],
                'lyrics' => [
                    'value' => 'Lorem ipsum dolor sic amet.',
                    'edit' => true
                ],
                'track' => [
                    'value' => 1,
                    'edit' => true
                ],
                'disc' => [
                    'value' => 1,
                    'edit' => true
                ],
                'year' => [
                    'value' => 2020,
                    'edit' => true
                ],
                'composer' => [
                    'value' => 'Composer',
                    'edit' => true
                ],
                'genre' => [
                    'value' => 'Stop labelling stuff',
                    'edit' => true
                ],
                'comments' => [
                    'value' => '',
                    'edit' => true
                ],
                'compilationState' => 2,
            ],
        ], $user)
            ->seeStatusCode(200);

        $compilationAlbum = Album::whereArtistIdAndName(Artist::VARIOUS_ID, 'Another Album')->first();
        $this->assertNotNull($compilationAlbum);

        $contributingArtist = Artist::whereName('Another Band')->first();
        $this->assertNotNull($contributingArtist);

        $this->seeInDatabase('songs', [
            'id' => $song->id,
            'artist_id' => $contributingArtist->id,
            'album_id' => $compilationAlbum->id,
        ]);

        // Case 3: Change compilation state only
        $this->putAsUser('/api/songs', [
            'songs' => [$song->id],
            'data' => [
                'title' => [
                    'value' => 'Foo Bar',
                    'edit' => true
                ],
                'artistName' => [
                    'value' => 'Another Band',
                    'edit' => true
                ],
                'albumName' => [
                    'value' => 'Another Album',
                    'edit' => true
                ],
                'lyrics' => [
                    'value' => 'Lorem ipsum dolor sic amet.',
                    'edit' => true
                ],
                'track' => [
                    'value' => 1,
                    'edit' => true
                ],
                'disc' => [
                    'value' => 1,
                    'edit' => true
                ],
                'year' => [
                    'value' => 2020,
                    'edit' => true
                ],
                'composer' => [
                    'value' => 'Composer',
                    'edit' => true
                ],
                'genre' => [
                    'value' => 'Stop labelling stuff',
                    'edit' => true
                ],
                'comments' => [
                    'value' => '',
                    'edit' => true
                ],
                'compilationState' => 0,
            ],
        ], $user)
            ->seeStatusCode(200);

        $artist = Artist::whereName('Another Band')->first();
        $this->assertNotNull($artist);
        $album = Album::whereArtistIdAndName($artist->id, 'Another Album')->first();
        $this->assertNotNull($album);

        $this->seeInDatabase('songs', [
            'id' => $song->id,
            'artist_id' => $artist->id,
            'album_id' => $album->id,
        ]);

        // Case 3: Change compilation state and artist
        // Remember to set the compilation state back to 1
        $this->putAsUser('/api/songs', [
            'songs' => [$song->id],
            'data' => [
                'title' => [
                    'value' => 'Foo Bar',
                    'edit' => true
                ],
                'artistName' => [
                    'value' => 'Another Band',
                    'edit' => true
                ],
                'albumName' => [
                    'value' => 'Another Album',
                    'edit' => true
                ],
                'lyrics' => [
                    'value' => 'Lorem ipsum dolor sic amet.',
                    'edit' => true
                ],
                'track' => [
                    'value' => 1,
                    'edit' => true
                ],
                'disc' => [
                    'value' => 1,
                    'edit' => true
                ],
                'year' => [
                    'value' => 2020,
                    'edit' => true
                ],
                'composer' => [
                    'value' => 'Composer',
                    'edit' => true
                ],
                'genre' => [
                    'value' => 'Stop labelling stuff',
                    'edit' => true
                ],
                'comments' => [
                    'value' => '',
                    'edit' => true
                ],
                'compilationState' => 1,
            ],
        ], $user)
            ->putAsUser('/api/songs', [
                'songs' => [$song->id],
                'data' => [
                    'title' => [
                        'value' => 'Foo Bar',
                        'edit' => true
                    ],
                    'artistName' => [
                        'value' => 'Another Band 2',
                        'edit' => true
                    ],
                    'albumName' => [
                        'value' => 'Another Album 2',
                        'edit' => true
                    ],
                    'lyrics' => [
                        'value' => 'Tired of lorem ipsum.',
                        'edit' => true
                    ],
                    'track' => [
                        'value' => 1,
                        'edit' => true
                    ],
                    'disc' => [
                        'value' => 1,
                        'edit' => true
                    ],
                    'year' => [
                        'value' => 2020,
                        'edit' => true
                    ],
                    'composer' => [
                        'value' => 'Composer',
                        'edit' => true
                    ],
                    'genre' => [
                        'value' => 'Stop labelling stuff',
                        'edit' => true
                    ],
                    'comments' => [
                        'value' => '',
                        'edit' => true
                    ],
                    'compilationState' => 0,
                ],
            ], $user)
            ->seeStatusCode(200);

        $artist = Artist::whereName('Another Band 2')->first();
        $this->assertNotNull($artist);
        $album = Album::whereArtistIdAndName($artist->id, 'Another Album 2')->first();
        $this->assertNotNull($album);
        $this->seeInDatabase('songs', [
            'id' => $song->id,
            'artist_id' => $artist->id,
            'album_id' => $album->id,
            'lyrics' => 'Tired of lorem ipsum.',
        ]);
    }

    /**
     * @throws Exception
     */
    public function testDeletingByChunk(): void
    {
        $this->assertNotEquals(0, Song::count());
        $ids = Song::select('id')->get()->pluck('id')->all();
        Song::deleteByChunk($ids, 'id', 1);
        $this->assertEquals(0, Song::count());
    }
}
