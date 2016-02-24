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

$uid = get_uid();



$rechte = new benutzerberechtigung();
$rechte->getBerechtigungen($uid);

if(!$rechte->isBerechtigt('mitarbeiter'))
	die('Sie haben keine Berechtigung fuer diese Seite');	


$retval = SendData();
$counter = 0;
echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">';
echo '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>';
echo '<table cellpadding="3">';
echo '<tr><th>Wochensumme</th><th>Zeitmodell</th><th>Mo</th><th>Di</th><th>Mi</th><th>Do</th><th>Fr</th><th>Sa</th><th>So</th><th>Beschreibung</th></tr>';
if ($retval)
{
	foreach ($retval as $zm)
	{
			if ($counter % 2 == 1)
				echo '<tr bgcolor="#eeeeee">';
			else 
				echo '<tr>';
			echo '<td>'.$zm[9].'</td><td>'.$zm[0].'</td><td>'.$zm[2].'</td><td>'.$zm[3].'</td><td>'.$zm[4].'</td><td>'.$zm[5].'</td><td>'.$zm[6].'</td><td>'.$zm[7].'</td><td>'.$zm[8].'</td><td>'.$zm[1].'</td></tr>';
			$counter++;
	}
}
echo '<tr><td colspan="10"><b>Gesamtanzahl Zeitmodelle: '.$counter.'</b></td></tr>';
echo '</table>';
echo '</body></html>';

/**
 * Sendet einen Request an den CaseTime Server um die Daten dort zu holen/speichern
 */
function SendData()
{
	$datum_obj = new datum();
	$heute = date('Ymd');
	$angezeigte_tage = 50;
	
	$ch = curl_init();

	$url = CASETIME_SERVER.'/sync/get_zeitmodelle';



	curl_setopt($ch, CURLOPT_URL, $url ); //Url together with parameters
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

		if(isset($data->STATUS) && $data->STATUS=='OK')
		{
			return $data->RESULT;
		}
		else
			return false;
	}	
}
?>
