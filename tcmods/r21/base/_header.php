<!--
/*
 *  This Program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3, or (at your option)
 *  any later version.
 *
 *  This Program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with TsunAMP; see the file COPYING.  If not, see
 *  <http://www.gnu.org/licenses/>.
 *
 *	PlayerUI Copyright (C) 2013 Andrea Coiutti & Simone De Gregori
 *	Tsunamp Team
 *	http://www.tsunamp.com
 *
 *	UI-design/JS code by: 	Andrea Coiutti (aka ACX)
 *	PHP/JS code by:			Simone De Gregori (aka Orion)
 * 
 *	file:					_header.php
 * 	version:				1.1
 *
 *  RaspyFi Project (C) 2013 Michelangelo Guarise (OS image build/customization)
 *  RaspyFi Logo design (C) 2013 Daniele Busciantella Ricci
 *
 *	TCMODS Edition 
 *
 *	TC (Tim Curtis) 2014-08-23, r1.0
 *	- change title
 *	- custom css for modal overrides
 *	- changes to menu
 *	- new left side header btns
 *
 *	TC (Tim Curtis) 2014-09-17, r1.1
 *	- new favicons for desktop and mobile
 *
 *	TC (Tim Curtis) 2014-12-23, r1.3 
 *  - remove class php ami from menu items, fixes the "persistent menu item highlight" bug (?)
 *  - add menu link for Audio info modal popup 
 *  - change menu item "Library" to "Sources"
 *	- remove stop button
 *	- add btn for 2nd volume control modal popup for small screens
 *	- toolbar hide/show button
 *	- reduced padding on toolbar and volume header btns
 *	- set vol and toolbar btns to default hidden, hide/show state controlled in code
 *	- menu item for clock radio modal
 *	- shovel & broom
 *
 *	TC (Tim Curtis) 2015-01-27, r1.5
 *	- menu item for tcmods config editor
 *	- shovel & broom
 *
 *	TC (Tim Curtis) 2015-02-25, r1.6
 *	- launch About modal via click handler in scripts-playback.js
 *
 *	TC (Tim Curtis) 2015-03-21, r1.7
 *	- change browser title
 *	- change v1 to v2 favicons
 *	- change Menu/TCMODS to Menu/Customize
 *
 *	TC (Tim Curtis) 2015-04-29, r1.8
 *	- change playback next and prev icons from "icon-step-" to "icon-"
 *	- change 'previous' to 'prev' for previous track button
 *	- remove power off button, not really used much
 *	- move toolbar button to left side for new layout
 *	- comment out Playlist tab since new Playback panel has integrated playlist
 *
 *	TC (Tim Curtis) 2015-05-30, r1.9
 *	- add Refresh pick to menu for reloading web page from server
 *	- add Playback history pick to menu for viewing the Playback history log
 *
 *	TC (Tim Curtis) 2015-06-26, r2.0
 *	- replace individual menu items for config pages with a single link to config-modal
 *
 *	TC (Tim Curtis) 2015-07-31, r2.1
 *	- add playback-page-cycle button to cycle through knobs, albumart when UI is vertical
 *
 */
-->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
	<title>MoOde Player</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0 user-scalable=no">
    
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/flat-ui.css" rel="stylesheet">
    <link href="css/bootstrap-select.css" rel="stylesheet">
	<link href="css/bootstrap-fileupload.css" rel="stylesheet">
    <link href="css/font-awesome.min.css" rel="stylesheet">
	<?php if ($sezione == 'index') { ?>
		<link href="css/jquery.countdown.css" rel="stylesheet">
	<?php } ?>
	<link href="css/jquery.pnotify.default.css" rel="stylesheet">
	<link href="css/panels.css" rel="stylesheet">
    <link href="css/tcmods.css" rel="stylesheet">

	<!-- favicons for desktop and mobile -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black">
	<link rel="apple-touch-icon" sizes="57x57" href="/v2-apple-touch-icon-57x57.png">
	<link rel="apple-touch-icon" sizes="60x60" href="/v2-apple-touch-icon-60x60.png">
	<link rel="apple-touch-icon" sizes="72x72" href="/v2-apple-touch-icon-72x72.png">
	<link rel="apple-touch-icon" sizes="76x76" href="/v2-apple-touch-icon-76x76.png">
	<link rel="apple-touch-icon" sizes="114x114" href="/v2-apple-touch-icon-114x114.png">
	<link rel="apple-touch-icon" sizes="120x120" href="/v2-apple-touch-icon-120x120.png">
	<link rel="apple-touch-icon" sizes="144x144" href="/v2-apple-touch-icon-144x144.png">
	<link rel="apple-touch-icon" sizes="152x152" href="/v2-apple-touch-icon-152x152.png">
	<link rel="apple-touch-icon" sizes="152x152" href="/v2-apple-touch-icon-180x180.png">
	<link rel="icon" type="image/png" href="/v2-favicon-16x16.png" sizes="16x16">
	<link rel="icon" type="image/png" href="/v2-favicon-32x32.png" sizes="32x32">
	<link rel="icon" type="image/png" href="/v2-favicon-96x96.png" sizes="96x96">
	<meta name="msapplication-TileColor" content="#da532c">
	<meta name="msapplication-TileImage" content="/v2-mstile-144x144.png">
</head>

<body class="<?php echo $sezione ?>">

<div id="menu-top" class="ui-header ui-bar-f ui-header-fixed slidedown" data-position="fixed" data-role="header" role="banner">
	<div class="dropdown">
		<a class="dropdown-toggle btn" id="menu-settings" role="button" data-toggle="dropdown" data-target="#" href="#notarget" title="System menu"><i class="icon-reorder"></i></a>
		<ul class="dropdown-menu" role="menu" aria-labelledby="menu-settings">
			<li class="context-menu menu-separator"><a href="#notarget" data-cmd="setforclockradio-m"><i id="clockradio-icon-m" class="icon-time sx clockradio-off-m"></i> Clock radio</a></li>
			<li><a href="#configure-modal" data-toggle="modal"><i class="icon-cogs sx"></i> Configure</a></li>
			<li class="context-menu menu-separator"><a href="#notarget" data-cmd="tcmodsconfedit"><i class="icon-edit sx"></i> Customize</a></li>
			<li><a href="javascript:$('#audioinfo-modal .modal-body').load('tcmods.php',function(e){$('#audioinfo-modal').modal('show');}); void 0"><i class="icon-cog sx"></i> Audio info</a></li>
			<li class="context-menu"><a href="#notarget" data-cmd="viewplayhistory"><i class="icon-book sx"></i> Play history</a></li>
			<li class="context-menu menu-separator"><a href="#notarget" data-cmd="abouttcmods"><i class="icon-info sx"></i> About</a></li>
			<li><a href="javascript:location.reload(true); void 0"><i class="icon-repeat sx"></i> Refresh</a></li>
			<li><a href="#poweroff-modal" data-toggle="modal"><i class="icon-power-off sx"></i> Turn off</a></li>
		</ul>
	</div>
	
	<div class="dropdown">
		<button id="volume-ctl" class="btn hidden btn-volume-control" style="padding-right: 2px;" title="Volume control"><i class="icon-volume-up"></i></button>
	</div>

	<div class="home playback-controls playback-controls-sm hidden">
		<button id="prev" class="btn btn-cmd" title="Previous"><i class="icon-backward"></i></button>
		<button id="play" class="btn btn-cmd" title="Play/Pause"><i class="icon-play"></i></button>
		<button id="next" class="btn btn-cmd" title="Next"><i class="icon-forward"></i></button>
		<!-- TC (Tim Curtis) 2015-07-31: cycle through knobs, albumart when UI is vertical -->
		<button id="playback-page-cycle" class="btn btn-cmd" title="Playback page cycle"><i class="icon-circle-blank"></i></button>
	</div>
	
	<div class="menu-top home">
		<button id="toolbar-btn" class="btn hidden" title="Hide/show toolbar"><i class="icon-chevron-down"></i></button>
		<span id="clockradio-icon" class="clockradio-off" title="Clock radio on/off indicator"><i class="icon-time"></i></span>
	</div>
</div>

<div id="menu-bottom" class="ui-footer ui-bar-f ui-footer-fixed slidedown" data-position="fixed" data-role="footer" role="banner">
	<ul>
		<?php if ($sezione == 'index') { ?>
			<li id="open-panel-sx"><a href="#panel-sx" class="open-panel-sx" data-toggle="tab">Browse</a></li>
			<li id="open-panel-lib"><a href="#panel-lib" class="open-panel-lib" data-toggle="tab">Library</a></li>
			<li id="open-playback" class="active"><a href="#playback" class="close-panels" data-toggle="tab">Playback</a></li>
			<!--li id="open-panel-dx"><a href="#panel-dx" class="open-panel-dx" data-toggle="tab">Playlist</a></li-->
		<?php } else { ?>
			<li id="open-panel-sx"><a href="index.php#panel-sx" class="open-panel-sx">Browse</a></li>
			<li id="open-panel-lib"><a href="index.php#panel-lib" class="open-panel-lib">Library</a></li>
			<li id="open-playback"><a href="index.php#playback" class="close-panels">Playback</a></li>
			<!--li id="open-panel-dx"><a href="index.php#panel-dx" class="open-panel-dx">Playlist</a></li-->
		<?php } ?>
	</ul>
</div>
