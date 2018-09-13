<?php
/* Copyright (C) 2015 fhcomplete.org
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as
 * published by the Free Software Foundation; either version 2 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307, USA.
 *
 */
/**
 * Durchlaufscript fuer Fehlerabfrage
 */
require_once('../config.inc.php');
require_once('../../../config/vilesci.config.inc.php');
require_once('../../../include/functions.inc.php');
require_once('../../../include/benutzerberechtigung.class.php');
require_once('../include/casetime.class.php');
require_once('../include/functions.inc.php');  // casetime functions.inc

$uid = get_uid();

if(isset($_GET['uid']))
	$username = $_GET['uid'];

if (isset($_GET['monat']))
	$monat = $_GET['monat'];
else
	$monat = 8;
if (isset($_GET['jahr']))
	$jahr = $_GET['jahr'];
else
	$jahr = 2015;
if (isset($_GET['ftype']))
	$ftype = $_GET['ftype'];
else
	$ftype = 'pdf';

$rechte = new benutzerberechtigung();
$rechte->getBerechtigungen($uid);

// Wenn es nicht der eigene Eintrag ist, muss man admin sein
if(isset($_GET['uid']) && $username!=$uid)
{

	if(!$rechte->isBerechtigt('admin'))
		die('Sie haben keine Berechtigung fuer diese Seite');

}

if (!$rechte->isBerechtigt('addon/casetimeGenerateXLS'))
	$ftype = 'pdf';

if (!isset($_GET['uid']))
	$username = $uid;

if(!isset($_GET['download']))
{
	$retval = generateTimesheetAndMail($username, $monat, $jahr, $ftype);
	//echo "-18.66";
	echo json_encode($retval);
}

if(isset($_GET['download']))
{
	// get casetime server filepath with filename
	$sysFile = generateCaseTimeTimesheet($uid, $monat, $jahr, $ftype);
	
	// connect to casetime server, get timesheet pdf and display in browser
	renderCaseTimeTimesheet($uid, $sysFile);
}

?>
