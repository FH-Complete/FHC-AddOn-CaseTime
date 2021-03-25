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

$retval = SendData();
echo json_encode($retval);

/**
 * Sendet einen Request an den CaseTime Server um die Daten dort zu holen/speichern
 */
function SendData()
{

	$ch = curl_init();

	$url = CASETIME_SERVER.'/sync/get_all_missing_zeitmodelle';

	//$fields_string = '';
	curl_setopt($ch, CURLOPT_URL, $url ); //Url together with parameters
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Return data instead printing directly in Browser
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT , 7); //Timeout after 7 seconds
	curl_setopt($ch, CURLOPT_USERAGENT , "FH-Complete CaseTime Addon");
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_POST, true);

	$result = curl_exec($ch);

	if(curl_errno($ch))
	{
		//print_r($ch);
		return 'Curl error: ' . curl_error($ch);
		curl_close($ch);
	}
	else
	{
		curl_close($ch);
		$data = json_decode($result);

		if(isset($data->STATUS) && $data->STATUS=='OK')
		{
			//print_r($data);
			return $data->RESULT;
		}
		else
			//print_r($data);
			return $data;
	}
}
?>
