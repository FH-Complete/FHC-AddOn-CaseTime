<?php
/* Copyright (C) 2014 fhcomplete.org
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
 * Synchronisiert den Urlaub mit CaseTime
 * 
 * Es werden nur bereits freigegebene Urlaube ins CaseTime uebertragen
 *
 * Bereits uebertragene Urlaube werden in der Tabelle addon.tbl_casetime_urlaub erfasst.
 *
 * Beispielaufruf fuer den Import:
 * http://localhost:8080/sync/import_urlaub?sachb=oesi&datum=20141108
 */
require_once('../config.inc.php');
require_once('../../../config/vilesci.config.inc.php');
require_once('../../../include/functions.inc.php');
require_once('../../../include/benutzerberechtigung.class.php');
require_once('../include/casetime.class.php');

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

// Zeitraum festlegen der uebertragen weden soll
$datum= new DateTime();
$datum->sub(new DateInterval('P40D')); // Heute - 40 Tage
$sync_datum_start = $datum->format('Y-m-d');

$datum= new DateTime();
$sync_datum_ende = $datum->format('Y-m-d');

$user='oesi';

$qry = "
	SELECT 
		mitarbeiter_uid, datum::date
	FROM
		(SELECT generate_series(vondatum::timestamp, bisdatum::timestamp, '1 day') as datum, mitarbeiter_uid 
		FROM campus.tbl_zeitsperre WHERE zeitsperretyp_kurzbz='Urlaub' AND freigabevon is not null) a
	WHERE
		NOT EXISTS (SELECT 1 FROM addon.tbl_casetime_urlaub WHERE uid=a.mitarbeiter_uid AND datum=a.datum)
	";
if($user!='')
	$qry.="AND mitarbeiter_uid=".$db->db_add_param($user);

if($result = $db->db_query($qry))
{
	while($row = $db->db_fetch_object($result))
	{
		echo "\n<br>".$row->mitarbeiter_uid.' '.$row->datum;

		$retval = SendData($row->mitarbeiter_uid, $row->datum);

		if($retval===true)
		{
			// Daten wurden erfolgreich ins CaseTime uebertragen
			// Eintrag in Synctabelle schreiben
			$ct = new casetime();
			$ct->uid = $row->mitarbeiter_uid;
			$ct->datum = $row->datum;

			if(!$ct->saveUrlaub(true))
				echo ' Save Failed:'.$ct->errormsg;
			else
				echo ' Saved';
		}
		else
		{
			// Beim schreiben in CaseTime ist ein Fehler aufgetreten
			echo 'Error:'.$retval;
		}
	}
}

/**
 * Sendet einen Request an den CaseTime Server um die Daten dort zu speichern
 */
function SendData($uid, $datum)
{
	$datum_obj = new datum();

	$ch = curl_init();

	$url = CASETIME_SERVER.'/sync/import_urlaub';

	$datum = $datum_obj->formatDatum($datum,'Ymd');

	$params = 'sachb='.$uid.'&buchdat='.$datum;

	curl_setopt($ch, CURLOPT_URL, $url.'?'.$params ); //Url together with parameters
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Return data instead printing directly in Browser
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT , 7); //Timeout after 7 seconds
	curl_setopt($ch, CURLOPT_USERAGENT , "FH-Complete CaseTime Addon");
	curl_setopt($ch, CURLOPT_HEADER, 0);

	$result = curl_exec($ch);
    
	if(curl_errno($ch))
	{
		return 'Curl error: ' . curl_error($ch);
		curl_close($ch);
	}
	else
	{
		curl_close($ch);
		$data = json_decode($result);
		
		/*
		Der Import liefert einen JSON String mit Status als Returnwert. 
		Wenn ein Fehler aufgetreten ist, wird die Fehlermeldung als result geliefert.

		Beispiel fuer Fehlerfall:
		{"STATUS": "ERR", "RESULT": "Das ist die Errormessage"}
		
		Beispiel fuer Erfolgsmeldung:
		{"STATUS": "OK", "RESULT": "Urlaubseintrag erfolgreich"}
		*/

		if(isset($data->STATUS) && $data->STATUS=='OK')
		{
			return true;
		}
		elseif(isset($data->STATUS) && $data->STATUS=='ERR')
		{
			// Error, Fehlermeldung wird zurueckgeliefert
			return $data->RESULT;
		}
		else
		{
			return 'Invalid return from CaseTime:'.$result;
		}
	}
	
}
?>
