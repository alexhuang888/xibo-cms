<?php
//
// make thing simple:
// 1. DisplayGroup is equal to a company profile: which can contains sub-DG and display.
//      it also has AI Tags. AI Tags will be inherited by sub-DG and display.
//      When a company is created, the wizard will create sub-DG and display as well.
//      Users will select how many displays to create and what layout to associate with these displays.

//      With these layout, playlist will be created, too. and these playlist will inherit ai tags from the display
//      they are assigned to. 
//
//      Users can also alter AI-tags of a Playlist, so they are customizable. 
//
//      Note: In XIBO, a playlist can be assigned to many regions, and these AI tags will go with them.

//      When a media is inserted to library, it will check every playlist in these way:
//      1. filter by all the DG this playlist belong to,
//      2. filter by this playlist.
//
//      In Xibo: if a playlist is assigned to a region, make sure the users know it is a AI-tag playlist
//                  and that is shared by all users.
//
// 
// display group has root tags, which can be inherited by DP or Display
define("ITID_DISPLAYGROUP", 0);
define("ITID_DISPLAY", 1);

// when a layout is created, it is assigned to a campaign, which represent this layout
// a compaign can also container a group of layouts.
// campaign can have its own tags, and be inheritted by its sub-layout
// a layout can have its own tags, which can be passed to its region.
define("ITID_CAMPAIGN", 10);
define("ITID_LAYOUT", 11);
// note: region has its own tag, and it can be from playlist
// case1: when a region is created the first time, its tags may be from layout, and the first playlist
//          of this region has same tags as this region. If a layout is not assigned to 
//          a display, it may have empty tags.
// case2: when a playlist is assinged to this region, the system will ask if tags of playlist will be applied to 
//          this region or not.
define("ITID_REGION", 12);

// a playlist is created when a region is created
// a playlist has many widget, each widget contains a media
// playlist will get tags from region, or from all its media

// note: playlist is actually defined by RegionID and PlaylistID
// a playlist has its ai tags. media can be assigned to this playlist automatically
// by matching media tags and playlist tags.
define("ITID_PLAYLIST", 13);

// widget is along with playlist id
define("ITID_WIDGET", 14);
// media is along with widget
define("ITID_MEDIA", 15);

define("ITID_TAG", 16);


?>