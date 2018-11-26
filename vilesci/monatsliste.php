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
require_once('../../../include/mitarbeiter.class.php');
require_once('../include/casetime.class.php');
require_once('../include/functions.inc.php');  // casetime functions.inc

$uid = get_uid();

// generate timesheet and mail
if(!isset($_GET['download']))
	{
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

	$retval = generateTimesheetAndMail($username, $monat, $jahr, $ftype);
	//echo "-18.66";
	echo json_encode($retval);
}

// generate timesheet and download in browser
if(isset($_GET['download']))
{	
	if (isset($_GET['uid']) && !empty($_GET['uid']) &&
		isset($_GET['monat']) && !empty($_GET['monat']) &&
		isset($_GET['jahr']) && !empty($_GET['jahr']))
	{
		$timesheet_uid = $_GET['uid'];
		$month = $_GET['monat'];
		$year = $_GET['jahr'];
		$ftype = 'pdf';
		$isTimesheetOwner = false;
		$isPersonal = false;
		$isVorgesetzter = false;
		$isVorgesetzter_indirekt = false;
		
		// Check if timesheet belongs to uid
		$isTimesheetOwner = ($uid == $timesheet_uid) ? true : false;		// bool for permission check; true if timesheet belongs to uid

		if (!$isTimesheetOwner)
		{
			// Check if uid has personnel manager permission
			$rechte = new benutzerberechtigung();
			$rechte->getBerechtigungen($uid);
			if ($rechte->isBerechtigt('mitarbeiter/zeitsperre'))
			{
				$isPersonal = true;
			}

			// Check if uid is a supervisor
			$mitarbeiter = new Mitarbeiter();
			$mitarbeiter->getUntergebene($uid);
			$untergebenen_arr = $mitarbeiter->untergebene;

				// check, if uid is an employee of supervisor
			if (!empty($untergebenen_arr) &&
				in_array($timesheet_uid, $untergebenen_arr))
			{
				$isVorgesetzter = true;
			}
			
			// Check if uid is a supervisor on higher oe-level
			$isVorgesetzter_indirekt = check_isVorgesetzter_indirekt($uid, $timesheet_uid);
		}
		
		
		// Permission check
		// * limited permission for request param timesheet_id
		if (!$isTimesheetOwner &&
			!$isPersonal &&	
			!$isVorgesetzter &&
			!$isVorgesetzter_indirekt)	
		{
			die('Sie haben keine Berechtigung für diese Seite');
		}
	
		// get casetime server filepath with filename
		$sysFile = generateCaseTimeTimesheet($timesheet_uid, $month, $year, $ftype);

		// connect to casetime server, get timesheet pdf and display in browser
		renderCaseTimeTimesheet($timesheet_uid, $sysFile);
	}
	else
	{
		echo 'UID, Monat oder Jahr nicht vorhanden oder inkorrekt.';
	}
		
}

?>
