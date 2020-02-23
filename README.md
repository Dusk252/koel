## Intro

This is a personal fork of [koel](https://github.com/phanan/koel) by [phanan](https://github.com/phanan/koel), a self-hosted audio streaming service. Please check out and support that awesome project, it's good stuff.

The changes in this fork were made mostly for reasons of personal usage, meaning there's no real reason to visual changes other than "I like it better looking this way". The actual features added are also out of personal convenience but if they fit your purposes feel free to use it. You can follow the instructions found in the original project.

Notes: Most of the features listed below were initially implemented on top of v3.8.0, and then adapted to v4.2.2.

## Added features

* __Two-way sync.__ Updates to fields in koel aren't saved only in the database but also to the physical files.
* Added year, composer, genre, bitrate, format, and frequency to the fields that are synced from the files. Comments are synced to but not from the files.
* __Song info tab__ in the extra panel, displays album cover and general information on the song (the fields listed in the previous point). Also added an edit button to easily access the edit form for the currently playing song.
* Right click context menu with edit option to albums and artists in addition to the songs.
* Ability to __set album cover from the edit form__.
* Ability to select what fields get updated via a checkbox. Allows the user to clear fields.
* __Lyrics scrapping__ from three services (jlyric, utaten, musixmatch), accessed from the lyrics panel.
* Allow more fields in search.
* Logic to keep track of all individual play interactions (rather than simply their count) and the time they happened, with the purpose of eventually implementing a stats page on the frontend.
* __Playlist ordering.__ Ability for the user to order songs through drag-and-drop in a playlist after creating it. New order is persisted.
* Add year to album cards. Year is decided from the year tag in the album song if all match.

## Fixes and miscellaneous

* Infinite scroll mixin for albums and artist lists - was checking whether items filled the container before the DOM was rendered, resulting in a huge increase in the amount of rendered items that slowed down the app. Now the check is only done when switching to the respective views.
* Styles for the play button in mobile.
* Prevent deletion of the "Various Artists" artist if all songs using it are deleted.
* Fix issue where artist images don't show up if they're being gotten from the album covers.
* Have song-list parents pass a default keys parameter.
* Update the meta info according to the displayed items when searching.
