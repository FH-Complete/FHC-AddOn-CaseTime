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
 * http://localhost:8080/sync/import_zeitsperre?sachb=oesi&buchdat=20150112&art=krankenstand
 */
require_once('../config.inc.php');
require_once('../../../config/vilesci.config.inc.php');
require_once('../../../include/functions.inc.php');
require_once('../../../include/benutzerberechtigung.class.php');
require_once('../include/casetime.class.php');
require_once('../include/functions.inc.php');
require_once('../../../include/mail.class.php');

// Wenn das Script nicht ueber Commandline gestartet wird, muss eine
// Authentifizierung stattfinden
if(php_sapi_name() != 'cli')
{
	$uid = get_uid();
	$rechte = new benutzerberechtigung();
	$rechte->getBerechtigungen($uid);

	if(!$rechte->isBerechtigt('admin')
	&& !$rechte->isBerechtigt('addon/casetime_manageTimesheet'))
		die('Sie haben keine Berechtigung fuer diese Seite');
}

$db = new basis_db();

// Zeitraum festlegen der uebertragen weden soll

if(CASETIME_SYNC_START_ABSOLUTE == '')
{
	$datum= new DateTime();
	$datum->sub(new DateInterval('P40D')); // Heute - 40 Tage
	$sync_datum_start = $datum->format('Y-m-d');
}
else
	$sync_datum_start = CASETIME_SYNC_START_ABSOLUTE;

$msglog = '';
$msglog_hr = '';
$datum= new DateTime();
$sync_datum_ende = $datum->format('Y-m-d');

$user_arr = array();

if(isset($_GET['uid']))
	$user_arr[] = $_GET['uid'];
else
{
	$ct = new casetime();
	$user_arr = $ct->getUserToSync();
}

$msglog_hr_include_users_arr = unserialize(CASETIME_MSGLOG_HR_INCLUDE_USERS);

// Urlaub/Krankenstand holen die geloescht wurden
$qry = "
SELECT
	uid, datum::date, 'Urlaub' as typ
FROM
	addon.tbl_casetime_zeitsperre
WHERE
	NOT EXISTS(
		SELECT 1
		FROM
			(SELECT generate_series(vondatum::timestamp, bisdatum::timestamp, '1 day') as datum, mitarbeiter_uid
			FROM campus.tbl_zeitsperre WHERE zeitsperretyp_kurzbz='Urlaub' AND freigabevon is not null) a
		WHERE
			datum=tbl_casetime_zeitsperre.datum
			AND mitarbeiter_uid = tbl_casetime_zeitsperre.uid
		)
	AND typ='Urlaub'
	AND datum>=".$db->db_add_param($sync_datum_start)."
	AND uid in(".$db->db_implode4SQL($user_arr).")
UNION
SELECT
	uid, datum::date, 'ZA' as typ
FROM
	addon.tbl_casetime_zeitsperre
WHERE
	NOT EXISTS(
		SELECT 1
		FROM
			(SELECT generate_series(vondatum::timestamp, bisdatum::timestamp, '1 day') as datum, mitarbeiter_uid
			FROM campus.tbl_zeitsperre WHERE zeitsperretyp_kurzbz='ZA' AND vonstunde is null and bisstunde is null) a
		WHERE
			datum=tbl_casetime_zeitsperre.datum
			AND mitarbeiter_uid = tbl_casetime_zeitsperre.uid
		)
	AND typ='ZA'
	AND datum>=".$db->db_add_param($sync_datum_start)."
	AND uid in(".$db->db_implode4SQL($user_arr).")
UNION
SELECT
	uid, datum::date, 'DienstV' as typ
FROM
	addon.tbl_casetime_zeitsperre
WHERE
	NOT EXISTS(
		SELECT 1
		FROM
			(SELECT generate_series(vondatum::timestamp, bisdatum::timestamp, '1 day') as datum, mitarbeiter_uid
			FROM campus.tbl_zeitsperre WHERE zeitsperretyp_kurzbz='DienstV' AND vonstunde is null and bisstunde is null) a
		WHERE
			datum=tbl_casetime_zeitsperre.datum
			AND mitarbeiter_uid = tbl_casetime_zeitsperre.uid
		)
	AND typ='DienstV'
	AND datum>=".$db->db_add_param($sync_datum_start)."
	AND uid in(".$db->db_implode4SQL($user_arr).")
UNION
	SELECT
		uid, datum::date, 'DienstF' as typ
	FROM
		addon.tbl_casetime_zeitsperre
	WHERE
		NOT EXISTS(
			SELECT 1
			FROM
				(SELECT generate_series(vondatum::timestamp, bisdatum::timestamp, '1 day') as datum, mitarbeiter_uid
				FROM campus.tbl_zeitsperre WHERE zeitsperretyp_kurzbz='DienstF' AND vonstunde is null and bisstunde is null) a
			WHERE
				datum=tbl_casetime_zeitsperre.datum
				AND mitarbeiter_uid = tbl_casetime_zeitsperre.uid
			)
		AND typ='DienstF'
		AND datum>=".$db->db_add_param($sync_datum_start)."
		AND uid in(".$db->db_implode4SQL($user_arr).")
UNION
SELECT
	uid, datum::date, 'PflegeU' as typ
FROM
	addon.tbl_casetime_zeitsperre
WHERE
	NOT EXISTS(
		SELECT 1
		FROM
			(SELECT generate_series(vondatum::timestamp, bisdatum::timestamp, '1 day') as datum, mitarbeiter_uid
			FROM campus.tbl_zeitsperre WHERE zeitsperretyp_kurzbz='PflegeU' AND vonstunde is null and bisstunde is null) a
		WHERE
			datum=tbl_casetime_zeitsperre.datum
			AND mitarbeiter_uid = tbl_casetime_zeitsperre.uid
		)
	AND typ='PflegeU'
	AND datum>=".$db->db_add_param($sync_datum_start)."
	AND uid in(".$db->db_implode4SQL($user_arr).")
UNION
SELECT
	uid, datum::date, 'Krank' as typ
FROM
	addon.tbl_casetime_zeitsperre
WHERE
	NOT EXISTS(
		SELECT 1
		FROM
			(SELECT generate_series(vondatum::timestamp, bisdatum::timestamp, '1 day') as datum, mitarbeiter_uid
			FROM campus.tbl_zeitsperre WHERE zeitsperretyp_kurzbz='Krank') a
		WHERE
			datum=tbl_casetime_zeitsperre.datum
			AND mitarbeiter_uid = tbl_casetime_zeitsperre.uid
		)
	AND typ='Krank'
	AND datum>=".$db->db_add_param($sync_datum_start)."
	AND uid in(".$db->db_implode4SQL($user_arr).")
UNION
SELECT
	uid, datum::date, 'CovidSB' as typ
FROM
	addon.tbl_casetime_zeitsperre
WHERE
	NOT EXISTS(
		SELECT 1
		FROM
			(SELECT generate_series(vondatum::timestamp, bisdatum::timestamp, '1 day') as datum, mitarbeiter_uid
			FROM campus.tbl_zeitsperre WHERE zeitsperretyp_kurzbz='CovidSB') a
		WHERE
			datum=tbl_casetime_zeitsperre.datum
			AND mitarbeiter_uid = tbl_casetime_zeitsperre.uid
		)
	AND typ='CovidSB'
	AND datum>=".$db->db_add_param($sync_datum_start)."
	AND uid in(".$db->db_implode4SQL($user_arr).")
UNION
SELECT
	uid, datum::date, 'CovidKS' as typ
FROM
	addon.tbl_casetime_zeitsperre
WHERE
	NOT EXISTS(
		SELECT 1
		FROM
			(SELECT generate_series(vondatum::timestamp, bisdatum::timestamp, '1 day') as datum, mitarbeiter_uid
			FROM campus.tbl_zeitsperre WHERE zeitsperretyp_kurzbz='CovidKS') a
		WHERE
			datum=tbl_casetime_zeitsperre.datum
			AND mitarbeiter_uid = tbl_casetime_zeitsperre.uid
		)
	AND typ='CovidKS'
	AND datum>=".$db->db_add_param($sync_datum_start)."
	AND uid in(".$db->db_implode4SQL($user_arr).")
";

if($result = $db->db_query($qry))
{
	while($row = $db->db_fetch_object($result))
	{
		$msglog .= "\nDelete ".$row->uid.' '.$row->datum.' '.$row->typ;
		if ($row->typ == 'DienstF')
		{
			$msglog_hr .= "\nDelete ".$row->uid.' '.$row->datum.' '.$row->typ;
		}
		if (in_array($row->uid, $msglog_hr_include_users_arr))
		{
			$msglog_hr .= "\n * Delete ".$row->uid.' '.$row->datum.' '.$row->typ;
		}

		$retval = SendDataDelete($row->uid, $row->datum, $row->typ);

		if($retval===true)
		{
			// Daten wurden erfolgreich im CaseTime entfernt
			// Eintrag aus Synctabelle loeschen
			$ct = new casetime();
			if(!$ct->deleteZeitsperre($row->uid, $row->datum, $row->typ))
				$msglog .= ' delete Failed:'.$ct->errormsg;
			else
				$msglog .= ' deleted';
		}
		else
		{
			// Beim schreiben in CaseTime ist ein Fehler aufgetreten
			$msglog .= 'Error:'.$retval;
		}
	}
}


// Neuen Urlaub / Krankenstand / ZA holen
$qry = "
	SELECT
		mitarbeiter_uid, datum::date, 'Urlaub' as typ
	FROM
		(SELECT generate_series(vondatum::timestamp, bisdatum::timestamp, '1 day') as datum, mitarbeiter_uid
		FROM campus.tbl_zeitsperre WHERE zeitsperretyp_kurzbz='Urlaub' AND freigabevon is not null) a
	WHERE
		NOT EXISTS (SELECT 1 FROM addon.tbl_casetime_zeitsperre WHERE uid=a.mitarbeiter_uid AND datum=a.datum AND typ='Urlaub')
		AND datum>=".$db->db_add_param($sync_datum_start)."
		AND mitarbeiter_uid in(".$db->db_implode4SQL($user_arr).")
	UNION
	SELECT
		mitarbeiter_uid, datum::date, 'ZA' as typ
	FROM
		(SELECT generate_series(vondatum::timestamp, bisdatum::timestamp, '1 day') as datum, mitarbeiter_uid
		FROM campus.tbl_zeitsperre WHERE zeitsperretyp_kurzbz='ZA' AND vonstunde is null and bisstunde is null) a
	WHERE
		NOT EXISTS (SELECT 1 FROM addon.tbl_casetime_zeitsperre WHERE uid=a.mitarbeiter_uid AND datum=a.datum AND typ='ZA')
		AND datum>=".$db->db_add_param($sync_datum_start)."
		AND mitarbeiter_uid in(".$db->db_implode4SQL($user_arr).")
	UNION
	SELECT
		mitarbeiter_uid, datum::date, 'DienstV' as typ
	FROM
		(SELECT generate_series(vondatum::timestamp, bisdatum::timestamp, '1 day') as datum, mitarbeiter_uid
		FROM campus.tbl_zeitsperre WHERE zeitsperretyp_kurzbz='DienstV' AND vonstunde is null and bisstunde is null) a
	WHERE
		NOT EXISTS (SELECT 1 FROM addon.tbl_casetime_zeitsperre WHERE uid=a.mitarbeiter_uid AND datum=a.datum AND typ='DienstV')
		AND datum>=".$db->db_add_param($sync_datum_start)."
		AND mitarbeiter_uid in(".$db->db_implode4SQL($user_arr).")
	UNION
	SELECT
		mitarbeiter_uid, datum::date, 'DienstF' as typ
	FROM
		(SELECT generate_series(vondatum::timestamp, bisdatum::timestamp, '1 day') as datum, mitarbeiter_uid
		FROM campus.tbl_zeitsperre WHERE zeitsperretyp_kurzbz='DienstF' AND vonstunde is null and bisstunde is null) a
	WHERE
		NOT EXISTS (SELECT 1 FROM addon.tbl_casetime_zeitsperre WHERE uid=a.mitarbeiter_uid AND datum=a.datum AND typ='DienstF')
		AND datum>=".$db->db_add_param($sync_datum_start)."
		AND mitarbeiter_uid in(".$db->db_implode4SQL($user_arr).")
	UNION
	SELECT
		mitarbeiter_uid, datum::date, 'PflegeU' as typ
	FROM
		(SELECT generate_series(vondatum::timestamp, bisdatum::timestamp, '1 day') as datum, mitarbeiter_uid
		FROM campus.tbl_zeitsperre WHERE zeitsperretyp_kurzbz='PflegeU' AND vonstunde is null and bisstunde is null) a
	WHERE
		NOT EXISTS (SELECT 1 FROM addon.tbl_casetime_zeitsperre WHERE uid=a.mitarbeiter_uid AND datum=a.datum AND typ='PflegeU')
		AND datum>=".$db->db_add_param($sync_datum_start)."
		AND mitarbeiter_uid in(".$db->db_implode4SQL($user_arr).")
	UNION
	SELECT
		mitarbeiter_uid, datum::date, 'Krank' as typ
	FROM
		(SELECT generate_series(vondatum::timestamp, bisdatum::timestamp, '1 day') as datum, mitarbeiter_uid
		FROM campus.tbl_zeitsperre WHERE zeitsperretyp_kurzbz='Krank') a
	WHERE
		NOT EXISTS (SELECT 1 FROM addon.tbl_casetime_zeitsperre WHERE uid=a.mitarbeiter_uid AND datum=a.datum AND typ='Krank')
		AND datum>=".$db->db_add_param($sync_datum_start)."
		AND mitarbeiter_uid in(".$db->db_implode4SQL($user_arr).")
	UNION
	SELECT
		mitarbeiter_uid, datum::date, 'CovidSB' as typ
	FROM
		(SELECT generate_series(vondatum::timestamp, bisdatum::timestamp, '1 day') as datum, mitarbeiter_uid
		FROM campus.tbl_zeitsperre WHERE zeitsperretyp_kurzbz='CovidSB') a
	WHERE
		NOT EXISTS (SELECT 1 FROM addon.tbl_casetime_zeitsperre WHERE uid=a.mitarbeiter_uid AND datum=a.datum AND typ='CovidSB')
		AND datum>=".$db->db_add_param($sync_datum_start)."
		AND mitarbeiter_uid in(".$db->db_implode4SQL($user_arr).")
	UNION
	SELECT
		mitarbeiter_uid, datum::date, 'CovidKS' as typ
	FROM
		(SELECT generate_series(vondatum::timestamp, bisdatum::timestamp, '1 day') as datum, mitarbeiter_uid
		FROM campus.tbl_zeitsperre WHERE zeitsperretyp_kurzbz='CovidKS') a
	WHERE
		NOT EXISTS (SELECT 1 FROM addon.tbl_casetime_zeitsperre WHERE uid=a.mitarbeiter_uid AND datum=a.datum AND typ='CovidKS')
		AND datum>=".$db->db_add_param($sync_datum_start)."
		AND mitarbeiter_uid in(".$db->db_implode4SQL($user_arr).")
		";



if($result = $db->db_query($qry))
{
	while($row = $db->db_fetch_object($result))
	{
		$msglog .= "\n ADD ".$row->mitarbeiter_uid.' '.$row->datum.' '.$row->typ;
		if (($row->typ == 'DienstF') || ($row->typ == 'CovidKS')|| ($row->typ == 'CovidSB'))
		{
			$msglog_hr .= "\n ADD ".$row->mitarbeiter_uid.' '.$row->datum.' '.$row->typ;
		}
		if (in_array($row->mitarbeiter_uid, $msglog_hr_include_users_arr))
		{
			$msglog_hr .= "\n * ADD ".$row->mitarbeiter_uid.' '.$row->datum.' '.$row->typ;
		}
		$retval = SendDataImport($row->mitarbeiter_uid, $row->datum, $row->typ);

		if($retval===true)
		{
			// Daten wurden erfolgreich ins CaseTime uebertragen
			// Eintrag in Synctabelle schreiben
			$ct = new casetime();
			$ct->uid = $row->mitarbeiter_uid;
			$ct->datum = $row->datum;
			$ct->typ = $row->typ;

			if(!$ct->saveZeitsperre(true))
				$msglog .= ' Save Failed:'.$ct->errormsg;
			else
				$msglog .= ' Saved';
		}
		else
		{
			// Beim schreiben in CaseTime ist ein Fehler aufgetreten
			$msglog .= 'Error:'.$retval;
		}
	}
}

if ($msglog == '')
	$msglog = "\nNothing to Sync.\n";

echo nl2br($msglog);

// send mail to CaseTime-Admin
if (CASETIME_SYNC_ADMIN_EMAIL != '')
{
	$mail = new mail(CASETIME_SYNC_ADMIN_EMAIL, 'vilesci@'.DOMAIN,'CaseTime Sync Zeitsperren', $msglog);
	if($mail->send())
		echo "<br>Mail gesendet";
	else
		echo "<br>Mail konnte nicht verschickt werden";
}
else
	echo "<br>Mailversand deaktiviert";

if ($msglog_hr != '')
{
	if (CASETIME_SYNC_HR_EMAIL != '')
	{
		$msglog_hr = "Geänderte Dienstfreistellungen und Einträge definierter User (*):\n\n".$msglog_hr;
		$mail = new mail(CASETIME_SYNC_HR_EMAIL, 'vilesci@'.DOMAIN,'CaseTime Sync HR', $msglog_hr);
		if($mail->send())
			echo "<br>Mail gesendet";
		else
			echo "<br>Mail konnte nicht verschickt werden";
	}
	else
		echo "<br>Mailversand deaktiviert";
}


?>
