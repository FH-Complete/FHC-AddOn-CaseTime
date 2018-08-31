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
 * Synchronisiert die Zeitaufzeichnungen mit CaseTime
 *
 * Es wird jeweils die erste Uhrzeit eines Tages und die letzte Uhrzeit herangezogen
 * und als "kommt" (ko) im CaseTime gebucht.
 * Zusätzlich werden "arzt" (ar), "dienstreise" (dr), "behoehrdengang" (bh) und "pause" (pa) übertragen.
 *
 * Der Status der Synchronisierung wird in der Tabelle addon.tbl_casetime_zeitaufzeichnung erfasst.
 *
 * Bei Änderungen an den Eintraegen wird immer der gesamte Tag aus CaseTime und Synctabelle entfernt
 * und der gesamte Tag neu gesynct.
 *
 * Beispielaufruf fuer den Import:
 * http://localhost:8080/sync/rohdatem_import?sachb=oesi&bwart=ko&datumvon=20141108&zeitvon=071013&datumbis=20141108&zeitbis=173455
 */
require_once('../config.inc.php');
require_once('../../../config/vilesci.config.inc.php');
require_once('../../../include/functions.inc.php');
require_once('../../../include/benutzerberechtigung.class.php');
require_once('../../../include/datum.class.php');
require_once('../include/casetime.class.php');
require_once('../include/functions.inc.php');
require_once('../../../include/mail.class.php');

$datum_obj = new datum();

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

if(CASETIME_SYNC_START_ABSOLUTE == '')
{
	$datum= new DateTime();
	$datum->sub(new DateInterval('P40D')); // Heute - 40 Tage
	$sync_datum_start = $datum->format('Y-m-d');
}
else
	$sync_datum_start = CASETIME_SYNC_START_ABSOLUTE;


$datum= new DateTime();
$sync_datum_ende = $datum->format('Y-m-d');

$msglog = '';
$user_arr = array();

if(isset($_GET['uid']))
	$user_arr[] = $_GET['uid'];
else
{
	$ct = new casetime();
	$user_arr = $ct->getUserToSync();
}

// Loeschen von geaenderten oder geloeschten Eintraegen

// Geloeschte Eintraege markieren
$qry = "UPDATE
	addon.tbl_casetime_zeitaufzeichnung
SET
	delete=true
WHERE
	zeitaufzeichnung_id is not null
AND
	datum>=".$db->db_add_param($sync_datum_start)."
AND
	uid in (".$db->db_implode4SQL($user_arr).")
AND
	typ != 'da'
AND
	not exists (SELECT 1 FROM campus.tbl_zeitaufzeichnung WHERE zeitaufzeichnung_id=tbl_casetime_zeitaufzeichnung.zeitaufzeichnung_id);";
// Geloeschte Tage markieren
$qry .= "
UPDATE
	addon.tbl_casetime_zeitaufzeichnung
SET
	delete=true
WHERE
	datum>=".$db->db_add_param($sync_datum_start)."
AND
	uid in(".$db->db_implode4SQL($user_arr).")
AND
	typ != 'da'
AND
	not exists (select 1 from campus.tbl_zeitaufzeichnung where start::date = tbl_casetime_zeitaufzeichnung.datum and uid=tbl_casetime_zeitaufzeichnung.uid
	and (ende::date = tbl_casetime_zeitaufzeichnung.datum_bis or tbl_casetime_zeitaufzeichnung.datum_bis is null)
);";
// geaenderte Ko/Ge Eintraege markieren
$qry.="
UPDATE
	addon.tbl_casetime_zeitaufzeichnung
SET
	sync=true
WHERE
	zeitaufzeichnung_id is null
	AND
	datum>=".$db->db_add_param($sync_datum_start)."
	AND
		uid in(".$db->db_implode4SQL($user_arr).")
	AND
	(zeit_start<>(SELECT min(start::time) FROM campus.tbl_zeitaufzeichnung
					WHERE ((aktivitaet_kurzbz != 'LehreExtern' and aktivitaet_kurzbz != 'Ersatzruhe' and aktivitaet_kurzbz != 'DienstreiseMT') or  aktivitaet_kurzbz is null) and uid=tbl_casetime_zeitaufzeichnung.uid AND start::date=tbl_casetime_zeitaufzeichnung.datum)
	OR
	zeit_ende<>(SELECT max(ende::time) FROM campus.tbl_zeitaufzeichnung
				WHERE ((aktivitaet_kurzbz != 'LehreExtern' and aktivitaet_kurzbz != 'Ersatzruhe' and aktivitaet_kurzbz != 'DienstreiseMT') or aktivitaet_kurzbz is null) and uid=tbl_casetime_zeitaufzeichnung.uid AND start::date=tbl_casetime_zeitaufzeichnung.datum)
	);";

// geaenderte Ar/Pa/... Eintraege markieren NICHT DienstreiseMT!
$qry.="
UPDATE
	addon.tbl_casetime_zeitaufzeichnung
SET
	sync=true
WHERE
	zeitaufzeichnung_id is not null
	AND
	typ != 'da'
	AND
	datum>=".$db->db_add_param($sync_datum_start)."
	AND
		uid in(".$db->db_implode4SQL($user_arr).")
	AND
	(zeit_start<>(SELECT start::time FROM campus.tbl_zeitaufzeichnung
					WHERE zeitaufzeichnung_id=tbl_casetime_zeitaufzeichnung.zeitaufzeichnung_id)
	OR
	zeit_ende<>(SELECT ende::time FROM campus.tbl_zeitaufzeichnung
					WHERE zeitaufzeichnung_id=tbl_casetime_zeitaufzeichnung.zeitaufzeichnung_id)
	);
";


if(!$db->db_query($qry))
	$msglog .= 'Fehler beim Markieren der Aktualisierungen!';

// Eintraege holen die sich geaendert haben und aus CaseTime und Synctabelle entfernen
$qry = "SELECT distinct datum, uid FROM addon.tbl_casetime_zeitaufzeichnung WHERE sync=true OR delete=true";

if($result = $db->db_query($qry))
{
	while($row = $db->db_fetch_object($result))
	{
		$msglog .= "\nLoesche Tageseintragungen ".$row->uid." am ".$row->datum;

		// Eintraege aus CaseTime entfernen
		$retval = DeleteRecords($row->uid, $row->datum);

		if($retval===true)
		{
			// Eintraege aus Sync Tabelle entfernen
			$ct = new casetime();
			if(!$ct->deleteDay($row->uid, $row->datum))
				$msglog .= 'Fehler beim Loeschen aus Sync Tabelle:'.$row->uid.' '.$row->datum;
		}
		else
		{
			$msglog .= 'Fehler beim Loeschen aus CaseTime:'.$retval;
		}
	}
}


// Löschen von DienstreiseMT

// Geloeschte Eintraege markieren
$qry = "select * from
	addon.tbl_casetime_zeitaufzeichnung
WHERE
	zeitaufzeichnung_id is not null
AND
	datum>=".$db->db_add_param($sync_datum_start)."
AND
	uid in (".$db->db_implode4SQL($user_arr).")
AND
	typ = 'da'
AND
	not exists (SELECT 1 FROM campus.tbl_zeitaufzeichnung WHERE zeitaufzeichnung_id=tbl_casetime_zeitaufzeichnung.zeitaufzeichnung_id);";

if($result = $db->db_query($qry))
{
	while($row = $db->db_fetch_object($result))
	{
		$msglog .= "\nLoesche Dienstreise MT-Tageseintragungen ".$row->uid." am ".$row->datum." / ".$row->datum_bis;

		// Eintraege aus CaseTime entfernen
		$retval = DeleteRecordsDienstreiseMT($row->uid, $row->datum, $row->datum_bis, 'da');

		if($retval===true)
		{
			// Eintraege aus Sync Tabelle entfernen
			$ct = new casetime();
			if(!$ct->deleteDayDienstreiseMT($row->uid, $row->datum))
				$msglog .= 'Fehler beim Loeschen aus Sync Tabelle:'.$row->uid.' '.$row->datum;
		}
		else
		{
			$msglog .= 'Fehler beim Loeschen aus CaseTime:'.$retval;
		}
	}
}

// Anlegen von noch nicht uebertragenen Eintraegen
// Kommt / Geht Eintraege
$qry = "
	SELECT * FROM (
		SELECT
			start::date as datum, min(start::time) as startzeit, max(ende::time) as endzeit, uid
		FROM
			campus.tbl_zeitaufzeichnung
		WHERE
			start::date>=".$db->db_add_param($sync_datum_start)."
			AND ((aktivitaet_kurzbz != 'LehreExtern' and aktivitaet_kurzbz != 'Ersatzruhe' and aktivitaet_kurzbz != 'DienstreiseMT') or aktivitaet_kurzbz is null)
			AND start::date<=".$db->db_add_param($sync_datum_ende);

$qry.="AND uid in(".$db->db_implode4SQL($user_arr).")";

$qry.="	GROUP BY uid, start::date
	) za
	WHERE NOT EXISTS (SELECT 1 FROM addon.tbl_casetime_zeitaufzeichnung WHERE datum=za.datum AND uid=za.uid AND typ='ko')";

if($result = $db->db_query($qry))
{
	while($row = $db->db_fetch_object($result))
	{
		$msglog .= "\n".$row->uid.' '.$row->datum.' '.$row->startzeit.' '.$row->endzeit;
		// Zuerst alle Zeitrohdaten des Tages löschen
		$retdel = DeleteRecords($row->uid, $row->datum);

		$retval = SendData('ko', $row->uid, $row->datum, $row->startzeit, $row->endzeit);

		if(is_array($retval))
		{
			// Daten wurden erfolgreich ins CaseTime uebertragen
			// Eintrag in Synctabelle schreiben
			$ct = new casetime();
			$ct->uid = $row->uid;
			$ct->datum = $row->datum;
			$ct->zeit_start = $row->startzeit;
			$ct->zeit_ende = $row->endzeit;
			$ct->ext_id1 = $retval[0];
			$ct->ext_id2 = $retval[1];
			$ct->typ = 'ko';
			$ct->sync=false;
			$ct->delete=false;
			$ct->datum_bis = $row->datum;

			if(!$ct->save(true))
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

$msglog .= "\n-----\n";
// Alle Pausen, Arzt, Dienstreisen, etc holen
$qry = "
	SELECT * FROM (
		SELECT
			start as start_full, ende as ende_full, start::date as datum, start::time as startzeit, ende::time as endzeit, ende::date as datum_bis, uid, aktivitaet_kurzbz, zeitaufzeichnung_id
		FROM
			campus.tbl_zeitaufzeichnung
		WHERE
			aktivitaet_kurzbz in('Pause','Arztbesuch','Dienstreise','Behoerde','LehreExtern','Ersatzruhe', 'DienstreiseMT')
			AND start::date>=".$db->db_add_param($sync_datum_start)."
			AND start::date<=".$db->db_add_param($sync_datum_ende);

$qry.="AND uid IN(".$db->db_implode4SQL($user_arr).")";
$qry.="
	) za
	WHERE NOT EXISTS (SELECT 1 FROM addon.tbl_casetime_zeitaufzeichnung WHERE zeitaufzeichnung_id=za.zeitaufzeichnung_id)";

$elsumme = '00:00';
$ersumme = '00:00';
if($result = $db->db_query($qry))
{
	$el_arr = array();
	$er_arr = array();
	while($row = $db->db_fetch_object($result))
	{
		switch($row->aktivitaet_kurzbz)
		{
			case 'Arztbesuch': $typ='ar'; break;
			case 'Pause': $typ = 'pa'; break;
			case 'Dienstreise': $typ = 'dr'; break;
			case 'Behoerde': $typ='bh'; break;
			case 'LehreExtern': $typ = 'pa'; break;
			case 'Ersatzruhe': $typ = 'pa'; break;
			case 'DienstreiseMT': $typ = 'da'; break;
		}

		if ($row->aktivitaet_kurzbz != 'Pause' && $row->aktivitaet_kurzbz != 'LehreExtern' && $row->aktivitaet_kurzbz != 'Ersatzruhe' && $row->aktivitaet_kurzbz != 'DienstreiseMT')
		{
			$start_for_casetime = date('H:i:s', strtotime('+1 minutes', strtotime($row->start_full)));
			$end_for_casetime = date('H:i:s', strtotime('-1 minutes', strtotime($row->ende_full)));

		}
		/*
		else if ($row->aktivitaet_kurzbz != 'Pause')
		{
			$start_for_casetime = date('H:i:s', strtotime('+1 seconds', strtotime($row->start_full)));
			$end_for_casetime = date('H:i:s', strtotime('+1 seconds', strtotime($row->ende_full)));
		}
		*/
		else {
			$start_for_casetime = $row->startzeit;
			$end_for_casetime = $row->endzeit;
		}

		$msglog .= "\n".$row->uid.' '.$row->datum.' '.$row->startzeit.' '.$row->endzeit.' '.$row->aktivitaet_kurzbz.' '.$row->zeitaufzeichnung_id.' '.$typ;
		if ($row->aktivitaet_kurzbz == 'LehreExtern')
		{
			$elstart = new DateTime($row->startzeit);
			$elende = new DateTime($row->endzeit);
			$eldiff = $elstart->diff($elende);
			$eladd = $eldiff->h.':'.$eldiff->i;
			if (array_key_exists($row->uid, $el_arr))
			{

				if (array_key_exists($row->datum, $el_arr[$row->uid]))
				{
					$el_arr[$row->uid][$row->datum] = $datum_obj->sumZeit($el_arr[$row->uid][$row->datum], $eladd);
				}
				else
				{

					$el_arr[$row->uid][$row->datum] = $eladd;
				}
			}
			else
			{
				$el_arr[$row->uid] = array();
				$el_arr[$row->uid][$row->datum] = $eladd;
			}
			$qry_tag = "select count(*) from addon.tbl_casetime_zeitaufzeichnung where uid = '".$row->uid."' and datum = '".$row->datum."' and zeit_start < '".$row->startzeit."' and zeit_ende > '".$row->endzeit."' and typ = 'ko'";
			$result_tag = $db->db_query($qry_tag);
			$inkernzeit = 	$db->db_fetch_row($result_tag);
			$msglog .= $inkernzeit[0];
			if ($inkernzeit[0] == 0)
			{
				$retval = array(-1,-1);
			}
			else
				$retval = SendData($typ, $row->uid, $row->datum, $start_for_casetime, $end_for_casetime);

			// write el with fehler_ok for calculation of ersatzruhe
			$retval_el = SendData('EL', $row->uid, $row->datum, $start_for_casetime, $end_for_casetime);
		}
		elseif ($row->aktivitaet_kurzbz == 'Ersatzruhe')
		{
			$erstart = new DateTime($row->startzeit);
			$erende = new DateTime($row->endzeit);
			$erdiff = $erstart->diff($erende);
			$eradd = $erdiff->h.':'.$erdiff->i;
			if (array_key_exists($row->uid, $er_arr))
			{

				if (array_key_exists($row->datum, $er_arr[$row->uid]))
				{
					$er_arr[$row->uid][$row->datum] = $datum_obj->sumZeit($er_arr[$row->uid][$row->datum], $eradd);
				}
				else
				{

					$er_arr[$row->uid][$row->datum] = $eradd;
				}
			}
			else
			{
				$er_arr[$row->uid] = array();
				$er_arr[$row->uid][$row->datum] = $eradd;
			}
			$qry_tag = "select count(*) from addon.tbl_casetime_zeitaufzeichnung where uid = '".$row->uid."' and datum = '".$row->datum."' and zeit_start < '".$row->startzeit."' and zeit_ende > '".$row->endzeit."' and typ = 'ko'";
			$result_tag = $db->db_query($qry_tag);
			$inkernzeit = 	$db->db_fetch_row($result_tag);
			$msglog .= $inkernzeit[0];
			if ($inkernzeit[0] == 0)
			{
				$retval = array(-1,-1);
			}
			else
				$retval = SendData($typ, $row->uid, $row->datum, $start_for_casetime, $end_for_casetime);
		}
		else
			$retval = SendData($typ, $row->uid, $row->datum, $start_for_casetime, $end_for_casetime, $row->datum_bis);
		//$retval = SendData($typ, $row->uid, $row->datum, $row->startzeit, $row->endzeit);

		if(is_array($retval))
		{
			// Daten wurden erfolgreich ins CaseTime uebertragen
			// Eintrag in Synctabelle schreiben
			$ct = new casetime();
			$ct->uid = $row->uid;
			$ct->datum = $row->datum;
			$ct->zeit_start = $row->startzeit;
			$ct->zeit_ende = $row->endzeit;
			$ct->ext_id1 = $retval[0];
			$ct->ext_id2 = $retval[1];
			$ct->typ = $typ;
			$ct->sync=false;
			$ct->delete=false;
			$ct->zeitaufzeichnung_id = $row->zeitaufzeichnung_id;
			$ct->datum_bis = $row->datum_bis;

			if(!$ct->save(true))
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

	//$msglog .= var_dump($el_arr);
	// eintrag für EL schreiben
	foreach ($el_arr as $key => $val)
	{
		foreach ($el_arr[$key] as $datum_key => $datum_val)
		{
			list($h2, $m2) = explode(':', $datum_val);
			$elzeit = $h2+$m2/60;
			$elzeit = number_format($elzeit, 2, '.', '');
			SendDataImport($key, $datum_key, 'EL', $elzeit);
			$msglog .= "\n EL-Buchung: ".$key."-".$datum_key."-".$elzeit;
		}
	}

	// eintrag für ER schreiben
	foreach ($er_arr as $key => $val)
	{
		foreach ($er_arr[$key] as $datum_key => $datum_val)
		{
			list($h2, $m2) = explode(':', $datum_val);
			$erzeit = $h2+$m2/60;
			$erzeit = number_format($erzeit, 2, '.', '');
			SendDataImport($key, $datum_key, 'ER', $erzeit);
			$msglog .= "\n ER-Buchung: ".$key."-".$datum_key."-".$erzeit;
		}
	}
}

if ($msglog == "\n-----\n")
	$msglog = "\nNothing to Sync.\n";
echo nl2br($msglog);

// send mail to CaseTime-Admin
if (CASETIME_SYNC_ADMIN_EMAIL != '')
{
	$mail = new mail(CASETIME_SYNC_ADMIN_EMAIL, 'vilesci@'.DOMAIN,'CaseTime Sync Zeitaufzeichnung', $msglog);
	if($mail->send())
		echo "<br>Mail gesendet";
	else
		echo "<br>Mail konnte nicht verschickt werden";
}
else
	echo "<br>Mailversand deaktiviert";


?>
