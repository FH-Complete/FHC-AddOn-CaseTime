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

	$params = 'sachb='.$uid.'&datum='.$datum.'&datum_bis=&typ=';

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
 * Sendet einen Request an den CaseTime Server um die Daten eines Mitarbeiters und Tages zu entfernen
 */
function DeleteRecordsDienstreiseMT($uid, $datum, $datum_bis, $typ)
{
	$datum_obj = new datum();

	$ch = curl_init();

	$url = CASETIME_SERVER.'/sync/rohdaten_delete';

	$datum = $datum_obj->formatDatum($datum,'Ymd');
	$datum_bis = $datum_obj->formatDatum($datum_bis,'Ymd');

	$params = 'sachb='.$uid.'&datum='.$datum.'&datum_bis='.$datum_bis.'&typ='.$typ;

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
function SendData($art, $uid, $datum, $beginn, $ende, $datum_bis = null)
{
	$datum_obj = new datum();
	$datum_bis_obj = new datum();

	$ch = curl_init();

	$url = CASETIME_SERVER.'/sync/rohdaten_import';

	$datum = $datum_obj->formatDatum($datum,'Ymd');
	if ($datum_bis)
	{
		$datum_bis = $datum_bis_obj->formatDatum($datum_bis,'Ymd');
	}
	else
	{
		$datum_bis = $datum;
	}
	$beginn = str_replace(':','',$beginn);
	$ende = str_replace(':','',$ende);

	$params = 'sachb='.$uid.'&bwart='.$art.'&datumvon='.$datum.'&zeitvon='.$beginn.'&datumbis='.$datum_bis.'&zeitbis='.$ende;

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

/**
 * Sendet einen Request an den CaseTime Server um die Zeitfehler abzufragen
 */
function getCaseTimeErrors($uid)
{
	$ch = curl_init();

	$url = CASETIME_SERVER.'/sync/get_zeitfehler';

	$params = 'sachb='.$uid;

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

		if(isset($data->STATUS) && $data->STATUS=='OK')
		{
			return $data->RESULT;
		}
		else
			return false;
	}
}

/**
 * Sendet einen Request an den CaseTime Server um den Zeitsaldo abzufragen
 */
function getCaseTimeZeitsaldo($uid)
{
	$ch = curl_init();

	$url = CASETIME_SERVER.'/sync/get_zeitsaldo';

	$params = 'sachb='.$uid;

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

		if(isset($data->STATUS) && $data->STATUS=='OK')
		{
			return $data->RESULT;
		}
		else
			return false;
	}
}

/**
 * Sendet einen Request an den CaseTime Server um den Zeitsaldo aller User abzufragen
 */
function getCaseTimeSalden($uidarr)
{
	$uidarr = array_map('strtoupper', $uidarr);	// uppercase string uids

	$ch = curl_init();

	$url = CASETIME_SERVER.'/sync/get_salden';

	$params = '';
	$fields = array('sachb' => $uidarr);
	$fields_string = http_build_query($fields);
	//$fields_string = '';
	curl_setopt($ch, CURLOPT_URL, $url.'?'.$params ); //Url together with parameters
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Return data instead printing directly in Browser
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT , 7); //Timeout after 7 seconds
	curl_setopt($ch, CURLOPT_USERAGENT , "FH-Complete CaseTime Addon");
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);

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

/**
 * Sendet einen Request an den CaseTime Server um den Urlaubssaldo abzufragen
 */
function getCastTimeUrlaubssaldo($uid)
{
	$ch = curl_init();

	$url = CASETIME_SERVER.'/sync/get_urlaubsaldo';

	$params = 'sachb='.$uid;

	curl_setopt($ch, CURLOPT_URL, $url.'?'.$params); //Url together with parameters
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Return data instead printing directly in Browser
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 7); //Timeout after 7 seconds
	curl_setopt($ch, CURLOPT_USERAGENT, "FH-Complete CaseTime Addon");
	curl_setopt($ch, CURLOPT_HEADER, 0);

	$result = curl_exec($ch);

	if (curl_errno($ch))
	{
		return 'Curl error: '.curl_error($ch);
	}
	else
	{
		curl_close($ch);
		$data = json_decode($result);

		if (isset($data->STATUS) && $data->STATUS == 'OK')
		{
			return $data->RESULT;
		}
		else
			return false;
	}
}

/**
 * Sendet einen Request an den CaseTime Server um eine Monatsliste zu generieren und am Casetime Server abzulegen
 */
function generateCaseTimeTimesheet($uid, $month, $year, $ftype)
{
	$ch = curl_init();

	$url = CASETIME_SERVER.'/sync/generate_monatsliste.py';

	$params = 'ftype='.$ftype.'&ps_sachb='.$uid.'&ps_monat='.$month.'.'.$year;
	$authstr = base64_encode(CASETIME_ZOPE_USER.':'.CASETIME_ZOPE_PASS);
	$headers = array();
	$headers[] = "Authorization: Basic ".$authstr;
	curl_setopt($ch, CURLOPT_URL, $url.'?'.$params ); //Url together with parameters
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Return data instead printing directly in Browser
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT , 7); //Timeout after 7 seconds
	curl_setopt($ch, CURLOPT_USERAGENT , "FH-Complete CaseTime Addon");
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

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

		if(isset($data->sysfile))
		{
			return $data->sysfile;
		}
		else
			return false;
	}
}

/**
 * Sendet einen Request an den CaseTime Server um eine generierte Monatsliste abzurufen und am Browser anzuzeigen
 */
function renderCaseTimeTimesheet($uid, $sysFile)
{
	// create temp filename
	$tmp_file = tempnam(sys_get_temp_dir(), "FHC_TIMESHEET_");

	// create filename (e.g. uid_zrep_monatsliste_09_2019.pdf)
	$filename = $uid. '_'. basename($sysFile);

	// connect to CaseTimeServer and get timesheet pdf via sysFile-path

	require_once("../../../vendor/autoload.php");

	$sftp = new \phpseclib\Net\SFTP(CASETIME_SERVER_IP);
	if (!$sftp->login(CASETIME_ZOPE_SYSUSER, CASETIME_ZOPE_SYSPASS))
	{
		exit('Login Failed');
	}

	$sftp->get($sysFile, $tmp_file);

	// close connection
	$conn = null;

	// display pdf in browser
	if ($handle = fopen($tmp_file, "r"))
	{
		header('Content-type: application/pdf');
		header('Content-Disposition: inline; filename="'. $filename.'"');
		header('Content-Length: '. filesize($tmp_file));

		while (!feof($handle))
		{
			echo fread($handle, 8192);
		}
		fclose($handle);
	}
	else
	{
		echo 'Fehler: Datei konnte nicht geoeffnet werden';
	}

	// deletes temp file
	unlink($tmp_file);
}

/**
 * Sendet einen Request an den CaseTime Server um die Daten dort zu speichern
 */
function generateTimesheetAndMail($uid, $monat, $jahr, $ftype)
{
	$datum_obj = new datum();

	$ch = curl_init();

	$url = CASETIME_SERVER.'/sync/generate_monatsliste.py';

	$params = 'ftype='.$ftype.'&ps_sachb='.$uid.'&ps_monat='.$monat.'.'.$jahr.'&ps_email='.$uid.'@'.DOMAIN;
	$authstr = base64_encode(CASETIME_ZOPE_USER.':'.CASETIME_ZOPE_PASS);
	$headers = array();
	$headers[] = "Authorization: Basic ".$authstr;
	curl_setopt($ch, CURLOPT_URL, $url.'?'.$params ); //Url together with parameters
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Return data instead printing directly in Browser
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT , 7); //Timeout after 7 seconds
	curl_setopt($ch, CURLOPT_USERAGENT , "FH-Complete CaseTime Addon");
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

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

		if(isset($data->message))
		{
			return $data->message;
		}
		else
			return false;
	}
}
?>
