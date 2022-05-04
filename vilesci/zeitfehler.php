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
require_once('../include/functions.inc.php');
require_once('../include/casetime.class.php');
require_once('../../../include/benutzerberechtigung.class.php');
require_once('../../../include/mitarbeiter.class.php');

$uid = get_uid();

$username = $_GET['uid'];

// Wenn es nicht der eigene Eintrag ist, muss man admin sein
// oder Vorgesetzter, der ausschließlich sichtberechtigt auf Files seiner MA ist
if ($username != $uid)
{
	$rechte = new benutzerberechtigung();
	$rechte->getBerechtigungen($uid);
	$mas = new mitarbeiter();
	$mas->getUntergebene($uid, true);
	$untergebenen_arr = array();
	$untergebenen_arr = $mas->untergebene;

	if(!$rechte->isBerechtigt('admin') && !$rechte->isBerechtigt('mitarbeiter/urlaube', null, 'suid') &&
	!(in_array($username, $untergebenen_arr)))
		die('Sie haben keine Berechtigung fuer diese Seite');
}

header('Content-Type: application/json');
/**
 * Sendet einen Request an den CaseTime Server um die Daten dort zu speichern
 */
try
{
	$retval = getCaseTimeErrors($username);
}
catch (exception $ex )
{
	http_response_code(500);
	echo json_encode(array("error" => $ex->getMessage()));
	exit();
}


//echo '[["14.01.2015", "Zeitfehler 1"], ["14.01.2015", "Zeitfehler 2"],["01.01.2014","Zeitfehler ausserhalb der Range"]]';
echo json_encode($retval);

?>
