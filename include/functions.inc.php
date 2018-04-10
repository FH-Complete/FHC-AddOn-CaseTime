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
 * Authors: Andreas Oesterreicher <andreas.oesterreicher@technikum-wien.at>
 * 			Gerald Raab <gerald.raab@technikum-wien.at>
 */



/**
 * Sendet einen Request an den CaseTime Server um die Daten eines Mitarbeiters und Tages zu entfernen
 */
function DeleteRecords($uid, $datum)
{
	$datum_obj = new datum();

	$ch = curl_init();

	$url = CASETIME_SERVER.'/sync/rohdaten_delete';

	$datum = $datum_obj->formatDatum($datum,'Ymd');

	$params = 'sachb='.$uid.'&datum='.$datum;

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
		{"STATUS": "ERR", "RESULT": "Fehlermeldung"}

		Beispiel fuer Erfolgsmeldung:
		{"STATUS": "OK", "RESULT": "Erfolgreich geloescht"}
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

/**
 * Sendet einen Request an den CaseTime Server um die Daten dort zu speichern
 */
function SendData($art, $uid, $datum, $beginn, $ende)
{
	$datum_obj = new datum();

	$ch = curl_init();

	$url = CASETIME_SERVER.'/sync/rohdaten_import';

	$datum = $datum_obj->formatDatum($datum,'Ymd');
	$beginn = str_replace(':','',$beginn);
	$ende = str_replace(':','',$ende);

	$params = 'sachb='.$uid.'&bwart='.$art.'&datumvon='.$datum.'&zeitvon='.$beginn.'&datumbis='.$datum.'&zeitbis='.$ende;

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
		Falls dieser OK ist werden die 2 IDs mit "_" getrennt zurueckgeliefert
		Wenn ein Fehler aufgetreten ist, wird die Fehlermeldung als result geliefert.

		Beispiel fuer Fehlerfall:
		{"STATUS": "ERR", "RESULT": "SACHB TEST hat bereits am 20141115 zur Zeit 110000 einen Eintrag!"}

		Beispiel fuer Erfolgsmeldung:
		{"STATUS": "OK", "RESULT": "51895_51896"}
		*/

		if(isset($data->STATUS) && $data->STATUS=='OK')
		{
			// OK, Ids werden zurueckgeliefert
			return explode('_',$data->RESULT);
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

/**
 * Sendet einen Request an den CaseTime Server um die Daten dort zu speichern
 */
function SendDataImport($uid, $datum, $typ, $zeit=0)
{
	if ($typ == "EL")
		$delval = SendDataDelete($uid, $datum, 'EL');
	elseif ($typ == 'ER')
		$delval = SendDataDelete($uid, $datum, 'ER');
	else
		$delval = DeleteRecords($uid, $datum);


	$datum_obj = new datum();

	$ch = curl_init();

	$url = CASETIME_SERVER.'/sync/import_zeitsperre';

	$datum = $datum_obj->formatDatum($datum,'d.m.Y');

	switch($typ)
	{
		case 'Urlaub': $art='urlaub'; break;
		case 'Krank': $art='krankenstand'; break;
		case 'ZA': $art='zeitausgleich'; break;
		case 'PflegeU': $art='pflegeurlaub'; break;
		case 'DienstV': $art='dienstverhinderung'; break;
		case 'DienstF': $art='dienstverhinderung'; break;
		case 'EL': $art='externelehre'; break;
		case 'ER': $art='ersatzruhe'; break;
		default: $art=''; break;
	}
	$params = 'sachb='.$uid.'&buchdat='.$datum.'&art='.$art.'&zeit='.$zeit;

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

/**
 * Sendet einen Request an den CaseTime Server um die Daten dort zu speichern
 */
function SendDataDelete($uid, $datum, $typ)
{
	$datum_obj = new datum();

	$ch = curl_init();

	$url = CASETIME_SERVER.'/sync/delete_zeitsperre';

	$datum = $datum_obj->formatDatum($datum,'Ymd');

	switch($typ)
	{
		case 'Urlaub': $art='urlaub'; break;
		case 'Krank': $art='krankenstand'; break;
		case 'ZA': $art='zeitausgleich'; break;
		case 'PflegeU': $art='pflegeurlaub'; break;
		case 'DienstV': $art='dienstverhinderung'; break;
		case 'DienstF': $art='dienstverhinderung'; break;
		case 'EL': $art='externelehre'; break;
		case 'ER': $art='ersatzruhe'; break;
		default: $art=''; break;
	}
	$params = 'sachb='.$uid.'&buchdat='.$datum.'&art='.$art;

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
