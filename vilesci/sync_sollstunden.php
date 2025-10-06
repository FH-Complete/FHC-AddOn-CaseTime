<?php
/* Copyright (C) 2022 fhcomplete.org
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

require_once(dirname(__FILE__). '/../config.inc.php');
require_once(dirname(__FILE__). '/../../../config/vilesci.config.inc.php');
require_once(dirname(__FILE__). '/../include/functions.inc.php');
require_once(dirname(__FILE__). '/../include/casetime.class.php');
require_once(dirname(__FILE__). '/../../../include/benutzerberechtigung.class.php');
require_once(dirname(__FILE__). '/../../../include/mitarbeiter.class.php');

// Wenn das Script nicht ueber Commandline gestartet wird, muss eine
// Authentifizierung stattfinden
if(php_sapi_name() != 'cli')
{
	$uid = get_uid();
	$rechte = new benutzerberechtigung();
	$rechte->getBerechtigungen($uid);
	if(!$rechte->isBerechtigt('admin'))
		die('Sie haben keine Berechtigung fuer diese Seite');
}

$db = new basis_db();

/**
 * Sendet einen Request an den CaseTime Server um die Daten zu laden
 */

$today = new DateTime();
$lastMonth = $today->modify('-1 month');

$casetimetoday = $lastMonth->format('Ym');
$postgrestimetoday = $lastMonth->format('Y-m-d');

$retval = getCaseTimeSollstunden($casetimetoday);
$anzahl = 0;
if (is_array($retval) && (count($retval) !== 0))
{
	$delete_qry = "DELETE
		FROM addon.tbl_casetime_zeitrohdaten
		WHERE DATE_TRUNC('month', datum) = DATE_TRUNC('month', DATE ". $db->db_add_param($postgrestimetoday) .");";

	if ($db->db_query($delete_qry))
	{
		foreach ($retval as $key => $value)
		{
			$uid = strtolower($value[0]);
			$datum = DateTime::createFromFormat('d.m.Y', $value[1])->format('Y-m-d');
			$sollstunden = str_replace(',', '.', $value[2]);
			$iststunden = str_replace(',', '.', $value[3]);

			$qry = "INSERT INTO addon.tbl_casetime_zeitrohdaten(uid, datum, sollstunden, iststunden)
			VALUES (".
				$db->db_add_param($uid) . ", ".
				$db->db_add_param($datum) . ", ".
				$db->db_add_param($sollstunden) . ", ".
				$db->db_add_param($iststunden) . ");";

			if (!$db->db_query($qry))
			{
				echo "Fehler beim Speichern von $uid an $datum";
			}
			else
				$anzahl++;
		}
	}
}
echo "<br>Sollstunden fuer $anzahl Eintraege im Monat $casetimetoday uebertragen.";


?>
