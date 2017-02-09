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

// Wenn es nicht der eigene Eintrag ist, muss man admin sein
if(isset($_GET['uid']) && $username!=$uid)
{
	$rechte = new benutzerberechtigung();
	$rechte->getBerechtigungen($uid);

	if(!$rechte->isBerechtigt('admin'))
		die('Sie haben keine Berechtigung fuer diese Seite');	
		
}

if (!isset($_GET['uid']))
	$username = $uid;
$retval = SendData($username, $monat, $jahr);
//echo "-18.66";
echo json_encode($retval);

/**
 * Sendet einen Request an den CaseTime Server um die Daten dort zu speichern
 */
function SendData($uid, $monat, $jahr)
{
	$datum_obj = new datum();

	$ch = curl_init();

	$url = CASETIME_SERVER.'/sync/generate_monatsliste.py';

	$params = 'ps_sachb='.$uid.'&ps_monat='.$monat.'.'.$jahr.'&ps_email='.$uid.'@'.DOMAIN;
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
