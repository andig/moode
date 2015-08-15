<?php 
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
 *	file:					index.php
 * 	version:				1.0
 *
 *	TCMODS Edition 
 *
 *  TC (Tim Curtis) 2014-11-30, r1.3 beta1
 *  - remove trailing ! in 1st content line causing code to be grayed out in editor 
 *
 */
 
// common include
include('inc/connection.php');
playerSession('open',$db,'',''); 
playerSession('unlock',$db,'','');

// set template
$tpl = "indextpl.html";
?>

<?php
$sezione = basename(__FILE__, '.php');
$_section = $sezione;
include('_header.php'); 
?>

<!-- 
TC (Tim Curtis) 2014-11-30
- remove trailing ! in 1st content line causing code to be grayed out in editor 
-->
<!-- content -->
<?php
eval("echoTemplate(\"".getTemplate("templates/$tpl")."\");");
?>
<!-- content -->

<?php 
// debug($_POST);
?>

<?php include('_footer.php'); ?>