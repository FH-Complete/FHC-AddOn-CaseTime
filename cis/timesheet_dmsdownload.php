<?php
/* Copyright (C) 2011 FH Technikum Wien
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
 * Authors: Cristina Hainberger	<hainberg@technikum-wien.at>
 */
require_once('../../../config/cis.config.inc.php');
require_once('../../../include/benutzerberechtigung.class.php');
require_once('../../../include/phrasen.class.php');
require_once('../../../include/dms.class.php');
require_once('../../../include/mitarbeiter.class.php');
require_once('../include/timesheet.class.php');

session_cache_limiter('none'); //muss gesetzt werden sonst funktioniert der Download mit IE8 nicht
session_start();

$uid = get_uid();
$p = new phrasen();

if(isset($_GET['dms_id']) && is_numeric($_GET['dms_id']))
	$dms_id = $_GET['dms_id'];
else
	echo 'dms ID muss gesetzt sein.';

// Permission check
// * flag documents owner
$timesheet = new Timesheet();
$uid_of_dms_id = $timesheet->getUserByDMSId($dms_id);
$isDocumentOwner = ($uid == $uid_of_dms_id) ? true : false;		// bool for permission check; true if timesheet belongs to uid

// * flag supervisor of documents owner
$isDocumentOwner_supervisor = false;
$mitarbeiter = new Mitarbeiter();
$mitarbeiter->getUntergebene($uid);
$untergebenen_arr = $mitarbeiter->untergebene;
if (!empty($untergebenen_arr) && in_array($uid_of_dms_id, $untergebenen_arr))
{
	$isDocumentOwner_supervisor = true;
}

// * for other permissions
$rechte = new benutzerberechtigung();
$rechte->getBerechtigungen($uid);

// * permission check
if (!$isDocumentOwner &&	// documents owner
	!$isDocumentOwner_supervisor &&	// supervisor
	!$rechte->isBerechtigt('mitarbeiter/zeitsperre', null, 'suid') &&	// personnel department
	!$rechte->isBerechtigt('admin'))	// admin
		die('Keine Berechtigung');
	
$doc = new dms();

// Check, if document still exists
if(!$doc->load($dms_id))
	die('Dieses Dokument existiert nicht mehr');

$filename = DMS_PATH. $doc->filename;
if (file_exists($filename))
{
	if ($handle = fopen($filename, "r"))
	{
		if ($doc->mimetype == '')
			$doc->mimetype = 'application/octetstream';
		
		header('Content-type: '.$doc->mimetype);
		header('Content-Disposition: inline; filename="'. $doc->name. '"');
		header('Content-Length: '. filesize($filename));
		
		while (!feof($handle))
		{
			echo fread($handle, 8192);
		}
		fclose($handle);
	}
	else
		echo 'Fehler: Datei konnte nicht geoeffnet werden';
}
else
	echo 'Die Datei existiert nicht';
