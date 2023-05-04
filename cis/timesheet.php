<?php
/* Copyright (C) 2018 FH Technikum-Wien
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
 * Authors:	Cristina Hainberger		<hainberg@technikum-wien.at>
 *			Manuela Thamer			<manuela.thamer@technikum-wien.at>
 */
require_once('../../../config/cis.config.inc.php');
require_once('../config.inc.php');
require_once('../include/timesheet.class.php');
require_once('../../../include/basis_db.class.php');
require_once('../../../include/benutzer.class.php');
require_once('../../../include/benutzerberechtigung.class.php');
require_once('../../../include/benutzerfunktion.class.php');
require_once('../../../include/functions.inc.php');
require_once('../../../include/phrasen.class.php');
require_once('../../../include/sprache.class.php');
require_once('../../../include/globals.inc.php');
require_once('../../../include/dms.class.php');
require_once('../../../include/mitarbeiter.class.php');
require_once('../../../include/bisverwendung.class.php');
require_once('../../../include/zeitaufzeichnung.class.php');
require_once('../../../include/sancho.inc.php');
require_once('../include/functions.inc.php');
require_once('../../../include/datum.class.php');
require_once('../../../include/vertragsbestandteil.class.php');

session_start();	// session to keep filter setting 'Alle meine Mitarbeiter' and show correct employees in timesheet_overview.php

$uid = get_uid();
$db = new basis_db();
$sprache_obj = new sprache();
$sprache = getSprache();	// users language
$sprache_index = $sprache_obj->getIndexFromSprache($sprache);	// users language index (for globals.inc.php)
$p = new phrasen($sprache);

// :NOTE: for correct month-operations with DateTime(), set them to first of month
$date_actual = new DateTime('first day of this month midnight');	// date obj of actual date
$date_golive = new DateTime('first day of '. CASETIME_TIMESHEET_GOLIVE);	// first possible date to create monthlists (timesheet go live)

// month & year of actual date
$month = $date_actual->format('m');	// string month of actual timesheet
$year = $date_actual->format('Y');	// string year of actual timesheet

$isTimesheetManager = false;	//true if uid has special right to manage timesheets
$arrayZP = array();

// month & year is overwritten by navigating through monthlist
if (isset($_GET['month']) && isset($_GET['year']))
{
	if (is_numeric($_GET['month']) && is_numeric($_GET['year']))
	{
		$month = $_GET['month'];
		$year = $_GET['year'];

		// request by timesheet manager to create timesheet
		if(isset($_GET['create']) && isset($_GET['employee_uid']) && !empty($_GET['employee_uid']))
		{
			$employee_uid = $_GET['employee_uid'];
			if ($isTimesheetManager = check_isTimesheetManager($uid, $employee_uid))
			{
				$timesheet_manager_uid = $uid;	// keep timesheet managers uid
				$uid = $employee_uid;	// from now on uid is employees uid to run check and logic as like timesheet was created by an employee
			}
			else
			{
				die('Keine Berechtigung für diese Seite.');
			}
		}
	}
	else
	{
		echo 'Monat und Jahr müssen numerisch sein.';
	}
}

// *********************************	DATA for supervisors & personnel departments view
$isPersonal = false;	// true if uid has personnel departments permission
$isVorgesetzter = false;	// true if uid is supervisor
$isVorgesetzter_indirekt = false;	//true if uid supervisor on higher oe level (not direct supervisor of employee)
$isVorgesetzterMitVertretungsfunktion = false; //true if supervisor is direct supervisor of supervisor

// If GET-REQUEST: Check if uid is supervisor, indirect supervisor, personnel- or timesheet manager
if (isset($_GET['timesheet_id']))
{
	if (!is_numeric($_GET['timesheet_id']))
	{
		die('Es muss eine numerische timesheet_id übergeben werden.');
	}
	else
	{
		$timesheet_id = $_GET['timesheet_id'];
	}

	// set month and year to timesheets month and year to enter correct timesheet
	$timesheet = new Timesheet();
	$timesheet->load_byID($timesheet_id);

	// exit if no existing timesheet for this timesheet_id
	if (is_null($timesheet->timesheet_id))
	{
		die($timesheet->errormsg);
	}

	// overwrite selected year and month with chosen timesheets date
	$timesheet_date = new DateTime($timesheet->datum);
	$year = $timesheet_date->format('Y');
	$month = $timesheet_date->format('m');

	// Get the uid of the timesheet_id
	if ($timesheet->getUser($timesheet_id))
	{
		$employee_uid = $timesheet->getUser($timesheet_id);

		$mitarbeiter = new Mitarbeiter();
		$mitarbeiter->getVorgesetzte($employee_uid);
		$chef_arr = array();
		$chef_arr = $mitarbeiter->vorgesetzte;
		foreach ($chef_arr as $k => $v)
		{
			$dirVor = $v;
		}
		$isVorgesetzterMitVertretungsfunktion = true;
		$ben = new benutzer();
		$ben->load($dirVor);
	}
	else
	{
		die($this->errormsg);
	}

	// Check if uid is personnel manager
	if ($isPersonal = check_isPersonal($uid))
	{
		$personnel_manager_uid = $uid;	// keep personnel managers uid
	}

	// Check if uid is a supervisor
	$mitarbeiter = new Mitarbeiter();
	if ($isVorgesetzter = $mitarbeiter->check_isVorgesetzter($uid, $employee_uid))
	{
		$confirm_vorgesetzten_uid = $uid;	// keep supervisors uid
	}

	// Check if uid is a supervisor on higher oe level
	$mitarbeiter = new Mitarbeiter();
	$isVorgesetzter_indirekt = $mitarbeiter->check_isVorgesetzter_indirekt($uid, $employee_uid);

	// Check, if uid is timesheet manager
	$isTimesheetManager = check_isTimesheetManager($uid, $employee_uid);

	// Permission check
	// * limited permission for request param timesheet_id
	if (!$isPersonal &&
		!$isVorgesetzter &&
		!$isTimesheetManager &&
		!$isVorgesetzter_indirekt)
	{
		die('Sie haben keine Berechtigung für diese Seite');
	}

	$uid = $employee_uid; // from now on $uid is employees uid
}

// vars user of monthlist
$benutzer = new Benutzer($uid);
$full_name = $benutzer->getFullName();	// string full name of user
$first_name = $benutzer->vorname;

// vars supervisor of user
$mitarbeiter = new Mitarbeiter($uid);
$vorgesetzte_uid_arr = array();	// array with uid of one or more supervisors
$vorgesetzte_full_name_arr = array();	// array of supervisor(s) full name
$hasVorgesetzten = false;

$dateTimesheet = new DateTime('last day of'.$year.'-'.$month.'.');

if ($mitarbeiter->getVorgesetzteMonatTimesheet($uid,$dateTimesheet->format('Y-m-d')))
{
	$vorgesetzte_uid_arr = $mitarbeiter->vorgesetzte;

	if (!empty($vorgesetzte_uid_arr))
	{
		foreach ($vorgesetzte_uid_arr as $vorgesetzten_uid)
		{
			$benutzer = new Benutzer($vorgesetzten_uid);
			$vorgesetzte_full_name_arr []= $benutzer->getFullName();	// string full name of supervisor

		}
		$hasVorgesetzten = true;
	}
	else
	{
		$hasVorgesetzten = false;
	}
}
else
{
	echo $mitarbeiter->errormsg;
}

// :NOTE: init $date_selected MUST be after request
$date_selected = new DateTime($year. '-'. $month);	// date obj of date selected; day and time is automatically set to first and zero
$now = new DateTime('today');

// Get all timesheets
$ts = new Timesheet();
$ts->loadAll($uid);
$timesheet_arr = !empty($ts->result) ? $ts->result : array();

$isBeforeGolive = false;	// true if date is before golive date
$isFirstEntry = empty($timesheet_arr) ? true : false;	// true if user enters first time monthlist site
$isCurrentMonth = false;
$isFuture = false;	// true if date selected is in the future

$date_last_timesheet = !empty($timesheet_arr) ? new DateTime($timesheet_arr[0]->datum) : null;	// date of last existing timesheet
$firstMissingTimesheetDatum = null;
$firstNotSentTimesheetDatum = null;
$firstNotConfirmedTimesheetDatum = null;

// Erster VBT Zeitaufzeichnungspflicht
$vbt = new vertragsbestandteil();
$result = $vbt->getZaPflichtig($uid, 'ASC', 1);
$ersteZaPflicht = $result == true ? $vbt->result[0] : null;

// Mindestens eine Zeitaufzeichnungspflicht vorhanden
$isZeitaufzeichnungspflichtig = empty($result) ? false : true;  // bei mindestens 1 ZA Pflicht

// Letztes genehmigtes Timesheet
$ts = new Timesheet();
$ts->getConfirmed($uid, 'DESC', 1);
$lastConfirmedTimesheet = !empty($ts->result) ? $ts->result[0] : null;

// Monatsliste startet ab erster Zeitaufzeichnunsplficht (aber nicht vor GoLive-Datum)
$monatslisteStartdatum = getMonatslisteStartdatum($ersteZaPflicht);

// Loop durch alle Monate der Monatsliste
$monat = new DateTime();
while ($monat->format('Y-m') >= $monatslisteStartdatum->format('Y-m'))
{
    $isZaPflichtig = $vbt->isZaPflichtig($uid, $monat->format('Y-m-t'));

    // Wenn im Monat zeitaufzeichnungspflichtig ist
    if ($isZaPflichtig)
    {
        $ts = new Timesheet($uid, $monat->format('m'), $monat->format('Y'));

        /**
         * Vergangene nicht versendete / nicht erstellte Timesheet ermitteln.
         *
         * Aktuelles Monat nicht beruecksichtigen bei Ermittlung der unversendeten / nicht erstellten Timesheets.
         *
         * Wenn es mindestens ein genehmigtes Timesheet gibt, und davor noch Timesheets fehlen, dann nur die Timesheets
         * NACH der Genehmigung auf 'fehlen' oder 'unversendet' prüfen.
         **/
        if ((is_null($lastConfirmedTimesheet) || $monat->format('Y-m') > (new DateTime($lastConfirmedTimesheet->datum))->format('Y-m')) &&
            $monat->format('Y-m') != $now->format('Y-m'))  // aktuelles Monat nicht beruecksichtigen
        {
            // Wenn Timesheet vorhanden ist
            if (!is_null($ts->timesheet_id))
            {
                // flag if at least one timesheet is not sent
                if (is_null($ts->abgeschicktamum))
                {
                    $firstNotSentTimesheetDatum = new DateTime($ts->datum);
                }

                // flag if at least one timesheet is not confirmed
                if (is_null($ts->genehmigtamum))
                {
                    $firstNotConfirmedTimesheetDatum = clone $monat;

                }
            }
            // Wenn kein Timesheet vorhanden ist
            else
            {
                $firstMissingTimesheetDatum = clone $monat;
            }
        }
    }

    // Monat erhöhen
    $monat->sub(new DateInterval('P1M'));
}

if (($date_selected == $date_actual))
{
	$isCurrentMonth = true;
}

// Flag if selected date is in the future
if (($date_selected > $date_actual))
{
	$isFuture = true;
}

// Flag if date selected is before golive
if ($date_selected < $date_golive)
{
	$isBeforeGolive = true;
}

$vbt = new vertragsbestandteil();
$isZaPflichtigOnSelDate = $vbt->isZaPflichtig($uid, $date_selected->format('Y-m-t'));

// Check if is allowed to create Timesheet
$isAllowed_createTimesheet = $isZaPflichtigOnSelDate;	// true, if is zapflichtig on selected date
if (isset($_GET['create']))
{
	if ($_GET['create'] === 'false')
	{
		$isAllowed_createTimesheet = false;
	}
}

function getMonatslisteStartdatum($ersteZaPflicht)
{

    // Wenn Zeitaufzeichnungspflicht hat
    if (!is_null($ersteZaPflicht))
    {
        // Wenn erste Zeitaufzeichnungspflichtmonat >= GoLive-Monat liegt...
        if ((new DateTime($ersteZaPflicht->von))->format('Y-m') >= (new DateTime(CASETIME_TIMESHEET_GOLIVE))->format('Y-m'))
        {
            return new DateTime($ersteZaPflicht->von);  // ...vondatum der ersten Zeitaufzeichnungspflicht setzen
        }
        else
        {
            return new DateTime(CASETIME_TIMESHEET_GOLIVE);  // ...ansonsten GoLive Datum setzen
        }
    }

    // Wenn erste Zeitaufzeichnungspflichtmonat in der Zukunft liegt oder es gar keine Zeitaufzeichnungspflicht gibt
    return new DateTime();  // aktuelles Datum setzen
}

// *********************************	ACTUAL TIMESHEET (of month/year selected)
$timesheet = new Timesheet($uid, $month, $year);
$timesheet_id = $timesheet->timesheet_id;
$timesheet->new = (is_null($timesheet_id) ? true : false);
$timesheet_vorzeitig_abgeschickt = $timesheet->vorzeitig_abgeschickt;
$timesheet_cntrl_date = (!is_null($timesheet->kontrolliertamum)) ? new DateTime($timesheet->kontrolliertamum) : '';
$timesheet_cntrl_uid = (!is_null($timesheet->kontrolliertvon)) ? $timesheet->kontrolliertvon : '';
$timesheet_cntrl_remark = (!is_null($timesheet->kontroll_notizen)) ? $timesheet->kontroll_notizen : '';
$controller_name = '';

$isSent = (is_null($timesheet->abgeschicktamum) ? false : true);	// true if timesheet was already sent
$isConfirmed = (is_null($timesheet->genehmigtamum) ? false : true);	// true if timesheet was already confirmed
$isControlled = (is_null($timesheet->kontrolliertamum) ? false : true);	// true if timesheet was already controlled by personnel department

if (!empty($timesheet_cntrl_uid))
{
	$benutzer = new Benutzer($timesheet_cntrl_uid);
	$controller_name = $benutzer->vorname. ' '. $benutzer->nachname;
}

// Check if has at least former unsent timesheet
$hasFormerUnsentTimesheet = false;
if (!is_null($firstNotSentTimesheetDatum) && $firstNotSentTimesheetDatum->format('Y-m') < $date_selected->format('Y-m'))
{
    $hasFormerUnsentTimesheet = true;
}

// Check if has at least former missing timesheet
$hasFormerMissingTimesheet = false;	// true if there are former missing timesheets
if (!is_null($firstMissingTimesheetDatum) && $firstMissingTimesheetDatum->format('Y-m') < $date_selected->format('Y-m'))
{
    $hasFormerMissingTimesheet = true;
}

// Check if has no missing and unsent timesheet
$isAllowed_sendTimesheet = !$hasFormerMissingTimesheet && !$hasFormerUnsentTimesheet;

// Check if has not confirmed timesheet
$isAllowed_confirmTimesheet = true;
if (!is_null($firstNotConfirmedTimesheetDatum) && $firstNotConfirmedTimesheetDatum->format('Y-m') < $date_selected->format('Y-m'))
{
    $isAllowed_confirmTimesheet = false;
}

// Save timesheet, if timesheet is new and user is timely allowed to create first timesheet
if ($timesheet->new && $isZaPflichtigOnSelDate)
{
	// only if the selected date <= actual date
	if ($date_selected <= $date_actual)
	{
		if (!$hasFormerMissingTimesheet)
		{
			$timesheet->uid = $uid;
			$timesheet->datum = $date_selected->format('Y-m-d'); 	//saved with last day (=t)of month
			$timesheet->insertvon = (isset($_GET['create'])) ? get_uid() : $uid;

			// if saving succeeded, last inserted timesheet_id is returned
			if ($timesheet_id = $timesheet->save())
			{
				// if timesheet was created by timesheet manager, refresh site with timesheet_id
				if (isset($_GET['create']))
				{
					header('Location: '.$_SERVER['PHP_SELF']. '?timesheet_id='. $timesheet_id. '&saved=true');
				}
				// otherwise refresh site with year & month
				else
				{
					header('Location: '.$_SERVER['PHP_SELF']. '?year='. $year. '&month='. $month. '&saved=true');
				}
			}
			else
			{
				 echo 'Timesheet konnte nicht gespeichert werden';
			}
		}
	}
}


// *********************************	FEHLZEITEN & deren BESTAETIGUNGEN

// Get all absences
$total_absences_arr = array();
$timesheet = new Timesheet();
$timesheet->getAllAbsentTimes($uid);
$total_absences_arr = $timesheet->result;

// Get absences of timesheet selected
$timesheet_absences_arr = array();
$timesheet = new Timesheet();
$timesheet->getAllAbsentTimes($uid, $timesheet_id);
$timesheet_absences_arr = $timesheet->result;

// Get all Bestaetigungen
$total_bestaetigungen_arr = array();
$timesheet = new Timesheet();
$timesheet->loadAllBestaetigungen_byUser($uid);
$total_bestaetigungen_arr = $timesheet->result;

// Get Bestaetigungen of timesheet selected
$timesheet_bestaetigungen_arr = array();
$timesheet = new Timesheet();
$timesheet->loadAllBestaetigungen_byTimesheet($timesheet_id);
$timesheet_bestaetigungen_arr = $timesheet->result;


// *********************************	CASETIME CHECKS
// Check if user made any changes in Zeitaufzeichnung today concerning the month period of selected date
$hasCaseTimeChanges_today = false;	// true if has inserted/updated Zeitaufzeichnung today
$isSyncedWithCaseTime_today = true;	// false if has deleted Zeitaufzeichnung/Zeitsperre today

// * no check if selected month is actual month as sending monthsheet is not allowed anyway
if ($date_selected != $date_actual)
{
	$timesheet = new Timesheet();
	$hasCaseTimeChanges_today = $timesheet->hasNewOrChangedTimesToday($uid, $date_selected);

	$timesheet = new Timesheet();
	$isSyncedWithCaseTime_today = $timesheet->hasDeletedTimes($uid, $date_selected);
}

// *********************************	AJAX REQUESTS
// Delete single Bestätigung (on ajax call)
if (isset($_POST['action']) && isset($_POST['method']))
{
	if ($_POST['action'] == 'ajax' && $_POST['method'] == 'deleteDmsId')
	{
		if (isset($_POST['dms_id']) && is_numeric($_POST['dms_id']))
		{
			$result = false;
			$timesheet = new Timesheet();
			$dms_uid = $timesheet->getUserByDMSId($_POST['dms_id']);

			// delete only if document belongs to user
			if ($dms_uid == $uid)
			{
				if ($timesheet->deleteBestaetigung($_POST['dms_id']))
				{
					$result = true;
				}
			}

			// ajax return true if deleting succeeded, false if failed
			echo json_encode($result);
			exit;
		}
	}
}

if (isset($_POST['action']) && isset($_POST['method']))
{
	if ($_POST['action'] == 'ajax' && $_POST['method'] == 'saveVorzeitigAbgeschickt')
	{
		if (isset($_POST['timesheet_id']) && is_numeric($_POST['timesheet_id']))
		{
			if (isset($_POST['vorzeitig_abgeschickt']) && is_string($_POST['vorzeitig_abgeschickt']))
			{
				$result = false;
				$timesheet = new Timesheet();

				if ($timesheet->saveVorzeitigAbgeschickt($_POST['timesheet_id'], $_POST['vorzeitig_abgeschickt']))
				{
					$result = true;
				}

				// return true if update was done successfully
				echo json_encode($result);
				exit;
			}
		}
	}
}

// *********************************	EMAIL SENDING (and document check)
$hasCaseTimeError = false;
$hasMissingBestaetigung = false;
$hasBlockingPauseError = false;
$missing_bestaetigungen = '';
if (isset($_POST['submitTimesheet']))
{
	$timesheet = new Timesheet();

	// Check for blocking casetime errors
  $hasCaseTimeError = $timesheet->hasCaseTimeError($uid, $month, $year);

	// Check for missing Bestaetigungen
	$hasMissingBestaetigung = $timesheet->hasMissingBestaetigung($uid, $timesheet_id);

	// Retrieve amount of missing Bestaetigungen by type
	if ($hasMissingBestaetigung)
	{
		$missing_bestaetigungen = $timesheet->result;
	}

	// Check for blocking Pause Errors
	$hasBlockingPauseError = $timesheet->hasBlockingErrorPause($uid, $month, $year);
//echo "<pre>"; print_r('$hasMissingBestaetigung'); echo "</pre>";
//echo "<pre>"; print_r($hasMissingBestaetigung); echo "</pre>";
//echo "<pre>"; print_r('$hasCaseTimeError'); echo "</pre>";
//echo "<pre>"; print_r($hasCaseTimeError); echo "</pre>";
//echo "<pre>"; print_r('$hasBlockingPauseError'); echo "</pre>";
//echo "<pre>"; print_r($hasBlockingPauseError); echo "</pre>";
	// if document $ casetime server error check ok, prepare for email sending
	if (!$hasMissingBestaetigung && !$hasCaseTimeError && !$hasBlockingPauseError)
	{
		foreach ($vorgesetzte_uid_arr as $vorgesetzten_uid)
		{
			$benutzer = new Benutzer($vorgesetzten_uid);
			$vorgesetzter_vorname = $benutzer->vorname;	// string first name of supervisor

			$to = $vorgesetzten_uid. '@'. DOMAIN;	// email of supervisor
			$subject =
				'Monatsliste '. $monatsname[$sprache_index][$month - 1]. ' '.
				$year. ' von '. $full_name;

			// Set vars to be used in mail content template
			$template_data = array(
				'firstName' => $vorgesetzter_vorname,
				'employee' => $first_name,
				'date_monthlist' => $monatsname[$sprache_index][$month - 1]. " ". $year,
				'link' => APP_ROOT. "addons/casetime/cis/timesheet.php?timesheet_id=". $timesheet_id
			);

			// Sancho header image
			$header_img = 'sancho_header_confirm_timesheet.jpg';

			// Send email in Sancho design to supervisor
			if (sendSanchoMail('Sancho_Content_confirmTimesheet', $template_data, $to, $subject, $header_img))
			{
				$send_date = new DateTime();
				$timesheet = new Timesheet();
				$timesheet->timesheet_id = $timesheet_id;
				$timesheet->abgeschicktamum = $send_date->format('Y-m-d H:i:s');

				// save sending date
				if ($timesheet->save(true))
				{
					// reload page to refresh actual and all monthlist display vars
					header('Location: '.$_SERVER['PHP_SELF']. '?year='. $year. '&month='. $month);
				}
				else
				{
					echo $timesheet->errormsg;
				}
			}
			else
			{
				echo 'Mail konnte nicht versendet werden.';
			}
		}
	}
}

// *********************************	CONFIRMATION by direct/indirect supervisor, timesheet- or personal manager
$isApproved_overtime = true;	// boolean to flag as approved when ALL overtimes were checked
$checkbox_overtime_arr = array();	// string array of checkbox checked-status
if (isset($_POST['submitTimesheetConfirmation']))
{
	$checkbox_overtime_str_arr = (isset($_POST['checkbox_overtime_arr'])) ? $_POST['checkbox_overtime_arr'] : die('Überstunden konnten nicht geprüft werden');
	$checkbox_overtime_arr = explode(',', $checkbox_overtime_str_arr);
	$checked_cnt = 0;										// counter for true checkbox checked-status

	// count checked overtime-checkboxes
	foreach($checkbox_overtime_arr as $checkbox_overtime)
	{
		if ($checkbox_overtime == 'true')
		{
			$checked_cnt++;
		}
	}

	$confirm_date = new DateTime();
	$timesheet = new Timesheet();
	$timesheet->timesheet_id = $timesheet_id;
	$timesheet->genehmigtamum = $confirm_date->format('Y-m-d H:i:s');
	$timesheet->genehmigtvon = get_uid();	// NOTE: not $uid, as this was set to employees uid before

	// check if all overtimes are checked
//	if($checked_cnt != 3) //:TODO: integer to be changed with real overtimes
//	{
//		$isApproved_overtime = false;
//	}

	// save confirmation
	if ($isApproved_overtime)
	{
		// If timesheet was not sent, but should be anyway confirmed by timesheet manager
		if ($isTimesheetManager && !$isSent)
		{
			// * define confirmation date as sending date
			$timesheet->abgeschicktamum = $confirm_date->format('Y-m-d H:i:s');

			if ($timesheet->save(true, true))
			{
				// reload page to refresh actual and all monthlist display vars
				header('Location: '.$_SERVER['PHP_SELF']. '?timesheet_id='. $timesheet_id);
			}
			else
			{
				echo $timesheet->errormsg;
			}
		}
		// Confirmation within the normal process
		else
		{
			if ($timesheet->save(false, true))
			{
				// reload page to refresh actual and all monthlist display vars
				header('Location: '.$_SERVER['PHP_SELF']. '?timesheet_id='. $timesheet_id);
			}
			else
			{
				echo $timesheet->errormsg;
			}
		}

	}
}

// *********************************	SENDING BACK timesheet by supervisor
if (isset($_POST['submitTimesheetSendBack']))
{
	$timesheet = new Timesheet();
	$timesheet->timesheet_id = $timesheet_id;
	$timesheet->abgeschicktamum = null;

	// save confirmation
	if ($timesheet->save(true))
	{
		// reload page to refresh actual and all monthlist display vars
		header('Location: '. $_SERVER['PHP_SELF']. '?timesheet_id='. $timesheet_id);
	}
	else
	{
		echo $timesheet->errormsg;
	}
}

// *********************************	SAVE CONTROLLING DATA by personnel department
if (isset($_POST['submitTimesheetControlled']))
{
	$controlling_remark = (isset($_POST['ta_controllingRemark'])) ? $_POST['ta_controllingRemark'] : '';
	$controlling_date = new DateTime();
	$timesheet = new Timesheet();
	$timesheet->timesheet_id = $timesheet_id;
	$timesheet->kontrolliertamum = $controlling_date->format('Y-m-d H:i:s');
	$timesheet->kontrolliertvon = $personnel_manager_uid;
	$timesheet->kontroll_notizen = $controlling_remark;

	// save controlling data
	if ($timesheet->save(false, false, true))
	{
		// reload page to refresh actual and all monthlist display vars
		header('Location: '. $_SERVER['PHP_SELF']. '?timesheet_id='. $timesheet_id);
	}
	else
	{
		echo $timesheet->errormsg;
	}
}

// *********************************	CANCEL CONFIRMATIONS by personnel department
if (isset($_POST['submitTimesheetCancelConfirmation']))
{
	// get timesheet_ids of selected month AND all months after
	$cancel_timesheet_id_arr = array();
    $timesheet_arr = new Timesheet();
    $timesheet_arr = $timesheet_arr->loadAll($uid);

	foreach ($timesheet_arr as $ts)
	{
		$ts_date = new DateTime($ts->datum);
		if ($ts_date->format('Y-m') >= $date_selected->format('Y-m'))
		{
			$cancel_timesheet_id_arr []= $ts->timesheet_id;
		}
	}

	// cancel sent- and confirmation data
	foreach ($cancel_timesheet_id_arr as $ts_id)
	{
		$timesheet = new Timesheet();
		$timesheet->timesheet_id = $ts_id;
		$timesheet->abgeschicktamum = null;
		$timesheet->genehmigtamum = null;
		$timesheet->genehmigtvon = null;

		// save cancellation data
		if (!$timesheet->save(true, true))
		{
			// if not saved break foreach
			echo $timesheet->errormsg;
			break;
		}
	}

	// reload page to refresh actual and all monthlist display vars
	header('Location: '. $_SERVER['PHP_SELF']. '?timesheet_id='. $timesheet_id);
}

?>

<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<link rel="stylesheet" type="text/css" href="../../../vendor/components/jqueryui/themes/base/jquery-ui.min.css">
	<link rel="stylesheet" type="text/css" href="../../../vendor/twbs/bootstrap3/dist/css/bootstrap.min.css">
	<link rel="stylesheet" type="text/css" href="../../../vendor/fortawesome/font-awesome4/css/font-awesome.min.css">
	<link rel="stylesheet" type="text/css" href="../../../public/css/DialogLib.css">
    <link href="../../../vendor/mottie/tablesorter/dist/css/theme.default.min.css" rel="stylesheet">
    <link href="../../../vendor/mottie/tablesorter/dist/css/jquery.tablesorter.pager.min.css" rel="stylesheet">
    <link href="../../../public/css/sbadmin2/tablesort_bootstrap.css" rel="stylesheet">
	<script type="text/javascript" src="../../../vendor/components/jquery/jquery.min.js"></script>
	<script type="text/javascript" src="../../../vendor/components/jqueryui/jquery-ui.min.js"></script>
	<script type="text/javascript" src="../../../vendor/twbs/bootstrap3/dist/js/bootstrap.min.js"></script>
	<script type="text/javascript" src="../../../public/js/DialogLib.js"></script>
    <script type="text/javascript" src="../../../vendor/mottie/tablesorter/dist/js/jquery.tablesorter.min.js"></script>
    <script type="text/javascript" src="../../../vendor/mottie/tablesorter/dist/js/jquery.tablesorter.widgets.min.js"></script>
    <script type="text/javascript" src="../../../vendor/mottie/tablesorter/dist/js/extras/jquery.tablesorter.pager.min.js"></script>

	<title>Timesheet</title>
	<style>
		.row {
			margin-left: 0px;
			margin-right: 0px;
		}
		.table>tbody>tr>td {
			border-top: none;
		}
		.btn {
			width: 185px;
		}
		.panel-body-alleMonatslisten {
			padding: 0px;
		}
		.panel-top-cstm{
			border-top: solid 1px lightgrey;
		}
		.inactive {
			pointer-events: none;
			cursor: default;
			text-decoration: none;
			color: grey;
		}
		#table-overtime tbody td:nth-child(4) {
			border-left: solid 2px #31708f;
			border-right: solid 2px #31708f;
		}
		<?php if($isVorgesetzter): ?>
		#table-overtime tbody tr:last-child td:last-child{
			border-bottom: solid 2px #31708f;
		}
		<?php endif; ?>
	</style>
	<script>
	// Open popup window for uploading documents & refresh site when window closed (to display new uploaded documents)
    function FensterOeffnen (adresse)
	{
		// only open download window if button is not disabled
		// NOTE: this extra check is important, otherwise popup will be opened even if button is disabled
		if (!$('#uploadButton').attr('disabled'))
		{
			MeinFenster = window.open(adresse, '', "width=820px, innerHeight=500px, left=100, top=100, scrollbars, toolbar=0, resizable");
			MeinFenster.focus();
		}
	}

	//Homeofficetage Ein- und Ausblenden
	function showHomeofficeTage()
	{
		if (!$("#homeofficeTage").is(":visible"))
			$("#homeofficeTage").show();
		else {
			$("#homeofficeTage").hide();
		}
	}

	// Delete documents and refresh view
	function deleteBestaetigung(dms_id)
	{
		$.ajax({
			type: 'POST',
			dataType: 'json',
			cache: false,
			data: {
				action: 'ajax',
				method: 'deleteDmsId',
				dms_id : dms_id
			},
			success: function(data){
				if(data)
				{
					$('#tbl_all_actualMonth_bestaetigungen').load(window.location.href + ' #tbl_all_actualMonth_bestaetigungen');
					$('#panel_all_user_bestaetigungen').load(window.location.href + ' #panel_all_user_bestaetigungen');
				}
			}
		});
	}

	// Refresh views when documents were uploaded
	function onUploadRefresh()
	{
		// if table is hidden, display first to be able to be refreshed afterwards
		if ($('#tbl_all_actualMonth_bestaetigungen').is(':hidden'))
		{
			$('#tbl_all_actualMonth_bestaetigungen').css('display', '');
		}

		// refresh
		$('#tbl_all_actualMonth_bestaetigungen').load(window.location.href + ' #tbl_all_actualMonth_bestaetigungen');
		$('#panel_all_user_bestaetigungen').load(window.location.href + ' #panel_all_user_bestaetigungen');
	}

	// Show Info for 3,5 sec when timesheet was saved successfully
	function showSaveSuccessAlert()
	{
		$("#timesheetSaveSuccess").show();
		setTimeout(function()
		{
			$("#timesheetSaveSuccess").hide();
		}, 3500);
	}

	// Get overtime checkbox values from one form and pass to hidden field of the submitting form
	// NOTE: workaround to use values of 2 forms by submitting only one
	$(document).ready(function(){

    // Monatslisten Tablesorter initialisieren
    $("#monatslistenTable").tablesorter(
        {
            theme: "default",
            widgets: ["filter"],
            widgetOptions:
            {
                filter_saveFilters : true,
                filter_searchFiltered: false,
                filter_columnFilters : true,
                filter_liveSearch : true
            }
        });


		// Save 'Vor Monatsende abschließen' - Checkbox value
        $("#vorzeitigAbgeschickt").change(function() {
            let timesheet_id = $(this).closest('form').find('input[name=timesheet_id]').val();
            let submitButton = $('#submitTimesheet');
            let vorzeitig_abgeschickt = this.checked;
            let alert_vorzeitigAbgeschickt = $('#alert-vorzeitigAbgeschickt');
			let isSent = <?php echo json_encode($isSent) ?>;
			let isAllowed_sendTimesheet = <?php echo json_encode($isAllowed_sendTimesheet) ?>;
			let isPersonal = <?php echo json_encode($isPersonal) ?>;
			let isVorgesetzter_indirekt = <?php echo json_encode($isVorgesetzter_indirekt) ?>;
			let isVorgesetzter = <?php echo json_encode($isVorgesetzter) ?>;
			let hasVorgesetzten = <?php echo json_encode($hasVorgesetzten) ?>;
			let hasCaseTimeChanges_today = <?php echo json_encode($hasCaseTimeChanges_today) ?>;
			let isSyncedWithCaseTime_today = <?php echo json_encode($isSyncedWithCaseTime_today) ?>;


            // If passed all checks, disable submit button if vorzeitig_abgeschickt is true
            if (vorzeitig_abgeschickt)
            {
                submitButton.prop('disabled', true);
                alert_vorzeitigAbgeschickt.removeClass('hidden');
            }
            // Otherwise enable submit button
            else
            {
                submitButton.prop('disabled', false);
                alert_vorzeitigAbgeschickt.addClass('hidden');
            }
            // But: Submit button must be disabled anyway if some of these checks are met
            if (isSent ||
                !isAllowed_sendTimesheet ||
                isPersonal ||
                isVorgesetzter_indirekt ||
                isVorgesetzter ||
                !hasVorgesetzten ||
                hasCaseTimeChanges_today ||
                !isSyncedWithCaseTime_today
            )
            {
                submitButton.prop('disabled', true);
            }

           // Change vorzeitig_abgeschickt in database
            $.ajax({
                type: 'POST',
                dataType: 'json',
                cache: false,
                data: {
                    action: 'ajax',
                    method: 'saveVorzeitigAbgeschickt',
                    timesheet_id: timesheet_id,
					vorzeitig_abgeschickt: vorzeitig_abgeschickt
                },
                success: function (result) {
                    if (result) {
                        FHC_DialogLib.alertSuccess('Gespeichert');
                    }
                    else
					{
					    FHC_DialogLib.alertError('Fehler beim Speichern')
					}
                }
            })
        })
	});
	</script>
</head>

<body style="font-family: Arial, Helvetica, sans-serif; font-size: 13px;">

<!--************************************	row: HEADER-->
<div class="row">
	<div class="col-xs-8">
		<!--link to monthlist overview-->
		<?php if($isVorgesetzter || $isPersonal || $isVorgesetzter_indirekt): ?>
			<h5 class="pull-right">
				<br><br>
					<a href="<?php echo APP_ROOT. 'addons/casetime/cis/timesheet_overview.php' ?>">
					<i class="fa fa-angle-left fa-lg" aria-hidden="true"></i>&nbsp;&nbsp;Monatslisten-Übersicht
				</a>
			</h5>
		<?php endif; ?>

		<!--title-->
		<h3>Zeitaufzeichnung - Monatslisten von <?php echo $full_name.' ('.$mitarbeiter->uid.')' ?></h3>
		<br>

		<!--************************************	PANEL ACTUAL TIMESHEET	 -->
		<span class="h4">Aktuelle Monatsliste: <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year ?></span>
		<br><br><br>

	</div><!--/.end col-xs-8-->

	<div class="col-xs-4">
	</div><!--/.end col-xs-4-->

</div><!--/.end row-->

<!--************************************	row: BODY-->
<div class="row">
<div class="col-xs-8">
	<!--information panel IF uid is INDIRECT SUPERVISOR-->
	<?php if (!$isFuture && ($isVorgesetzter_indirekt  && !$isVorgesetzter && !$isVorgesetzterMitVertretungsfunktion)): ?>
	<div class="panel panel-default">
		<div class="panel-body text-danger">
			<i class="fa fa-info-circle fa-lg" aria-hidden="true"></i>
			<b>Sie sind INDIREKT VORGESETZT. </b>Sie können Monatlisten einsehen, aber nicht genehmigen oder retournieren.
		</div>
	</div>
	<?php endif; ?>


	<!--information panel IF uid is INDIRECT SUPERVISOR and DIRECT SUPERVISOR has ZEITSPERRE-->
	<?php if (!$isFuture && ($isVorgesetzter_indirekt && !$isVorgesetzter && $isVorgesetzterMitVertretungsfunktion)): ?>
	<div class="panel panel-default">
		<div class="panel-body text-danger">
			<i class="fa fa-info-circle fa-lg" aria-hidden="true"></i>
			<b>Sie sind INDIREKT VORGESETZT.
			<br>Sie können in Vertretung für <?php echo $db->convert_html_chars($ben->vorname).' '.$db->convert_html_chars($ben->nachname); ?> Monatlisten einsehen, genehmigen und retournieren.
		</div>
	</div>
	<?php endif; ?>

	<!--information panel IF uid is TIMESHEET MANAGER-->
	<?php if (!$isFuture && $isTimesheetManager): ?>
	<div class="panel panel-default">
		<div class="panel-body text-info">
			<i class="fa fa-info-circle fa-lg" aria-hidden="true"></i>
			<b>Sie sind TIMESHEET MANAGER für diese Monatsliste.</b><br>
			Sie können Monatslisten neu erstellen, genehmigen bzw. retournieren.<br>
			Weiters können Sie Dokumente hochladen und löschen, solange die Monatsliste vom Mitarbeiter nicht versendet worden ist.
		</div>
	</div>

	<?php endif; ?>
	<!--************************************		ACTUAL MONTHLIST for employees-->
	<div class="panel panel-default" <?php echo ($isFuture) ? 'style="display: none;"' : '' ?>>

		<!--panel: DOWNLOAD timesheet-->
		<div class="row">
			<div class="panel-body col-xs-8">
				<b>Monatsliste für <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year?> herunterladen</b><br><br>
				Diese Liste ist nur für Ihren Bedarf und Ihre Eigenkontrolle.<br>
				Sie wird in diesem Schritt nicht an Ihren Vorgesetzten versendet.
			</div>
			<div class="panel-body col-xs-4"><br>
				<a role="button" class="btn btn-default pull-right"
					href="<?php echo APP_ROOT. 'addons/casetime/vilesci/monatsliste.php?download=true&uid='. $uid. '&monat='. $month. '&jahr='. $year ?>"
					target="_blank">Monatsliste herunterladen
				</a>
			</div>
		</div>

		<!--panel: Anzeige Homeoffice Tage-->
		<div class="row panel-top-cstm">
			<div class="panel-body col-xs-8">
				<b>Homeoffice </b><br><br>

					<?php
					$datumVon = $year. "-". $month. "-01";
					$datumBis =  date("Y-m-d", strtotime("last day of this month", strtotime($datumVon)));
					$homeofficetage = getHomeofficeTage($uid, $datumVon, $datumBis);
					$countTage = 0;

					$tagesliste = '';
					if ($homeofficetage)
					{
						$tagesliste = '<div id = "homeofficeTage" style="display:none; margin-top: 1em" >';
						$tage = array("Sonntag", "Montag", "Dienstag", "Mittwoch", "Donnerstag", "Freitag", "Samstag");
						foreach ($homeofficetage as $k)
						{
							$k = new DateTime($k);
							$tag = $k->format("w");
							$tagesliste .= $tage[$tag] . ", ". $k->format('d.m.Y');
							$tagesliste .= '<br>';
							$countTage++;
						}
						$tagesliste .= '</div>';
					}
				echo "Anzahl Tage im Homeoffice:  <b>". $countTage. "</b>";
				echo $tagesliste;
					?>
			</div>
			<div class="panel-body col-xs-4 text-right">
				<br><br>
				<tr>
					<a role="button"
					   class="btn btn-default pull-right"
					   onclick="showHomeofficeTage();">Homeofficetage anzeigen</a><br><br><br>
				</tr>
			</div>
		</div>

		<!--panel: UPLOAD documents-->
		<div class="row panel-top-cstm" style="<?php echo ($isConfirmed || $isFuture || $hasFormerMissingTimesheet || !$isAllowed_createTimesheet) ? 'display: none;' : '' ?>">
			<div class="panel-body col-xs-8">
				<b>Upload von Dokumenten</b><br><br>

				<!--counter for displaying absence text only once-->
				<?php $counter = 0; ?>

				<!--loop through absent times-->
				<?php foreach ($timesheet_absences_arr as $absence): ?>

					<!--set absence text-->
					<?php if ($counter == 0): ?>
						Bitte laden Sie Bestätigungen für folgende Fehlzeiten (außer Dienstreisen) hoch:<br><br>
						<?php $counter++ ?>
					<?php endif; ?>

					<!--find absences and times only for the actual timesheet-->
					<ul>
					<?php if ($absence->timesheet_id == $timesheet_id): ?>
						<li><?php echo $absence->abwesenheitsgrund. ' von '. date_format(date_create($absence->von), 'd.m.Y'). ' - '. date_format(date_create($absence->bis), 'd.m.Y') ?></li>
					</ul>
					<?php endif; ?>

				<?php endforeach; ?>

				<!--if no absent times in actual timesheet found, display other text-->
				<?php if($counter == 0): ?>
					Sie haben im <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year?> keine Fehlzeiten.
				<?php endif; ?>
			</div>
			<div class="panel-body col-xs-4"><br>
				<!--allow document uploading only for user himself AND timesheet manager-->
				<a role="button" <?php echo ($isSent || !$isZaPflichtigOnSelDate || ($isVorgesetzter && !$isTimesheetManager) || ($isPersonal && !$isTimesheetManager) || ($isVorgesetzter_indirekt && !$isTimesheetManager)) ? 'disabled' : ''; ?>
				   <?php echo ($isSent && !$isVorgesetzter && !$isPersonal && !$isVorgesetzter_indirekt && !$isTimesheetManager) ? 'data-toggle="tooltip" title="Information zur Sperre weiter unten in der Messagebox."' : ''; ?>
				   <?php echo ($isSent && $isTimesheetManager) ? 'data-toggle="tooltip" title="Kein Upload mehr möglich da die Monatsliste bereits abgeschickt worden ist."' : ''; ?>
				   class="btn btn-default pull-right" id="uploadButton"
				   href="<?php echo APP_ROOT. 'addons/casetime/cis/timesheet_dmsupload.php?timesheet_id='. $timesheet_id ?>"
				   onclick="FensterOeffnen(this.href); return false;">Dokumente hochladen</a><br><br><br>

				<!--if there are existing bestaetigungen in actual month -> display table and all bestaetigungen-->
				<table class="table table-condensed pull-right" <?php echo (empty($timesheet_bestaetigungen_arr)) ? 'style="display: none;"' : '' ?> id="tbl_all_actualMonth_bestaetigungen">
				<?php foreach($timesheet_bestaetigungen_arr as $bestaetigung): ?>
					<tr>
						<td><?php echo $bestaetigung->dokument_bezeichnung ?>: </td>
						<td><a href="<?php echo APP_ROOT. 'addons/casetime/cis/timesheet_dmsdownload.php?dms_id='. $bestaetigung->dms_id ?>" target="_blank"><?php echo $db->convert_html_chars($bestaetigung->name) ?></a></td>
						<td><a role="button" <?php echo ($isSent || !$isTimesheetManager && ($isVorgesetzter || $isVorgesetzter_indirekt)) ? 'class="inactive"' : '' ?> value="<?php echo $bestaetigung->dms_id?>" name="trash_dms_id" id="trash_dms_id"
							   onclick="deleteBestaetigung(<?php echo $bestaetigung->dms_id ?>)"><i class="fa fa-trash-o" aria-hidden="true"></i></a></td>
					</tr>
				<?php endforeach; ?>
				</table>
			</div>
		</div>

		<!--panel: OVERTIME-->
		<!--	<div class="row panel-top-cstm" style="<?php echo ($isFuture || !$isAllowed_createTimesheet) ? '"display: none;"' : '' ?>">
				<div class="panel-body col-xs-12">
					<b>Überstunden</b><br><br>
				counter for displaying overtime text only once
					<?php $counter = 0; ?>

					loop through overtimes
					<?php foreach ($timesheet_absences_arr as $overtime): ?>

						set absence text
						<?php if ($counter == 0): ?>
							Sie haben diesen Monat <b>10:30</b> Überstunden.<br><br>
							<?php $counter++ ?>
						<?php endif; ?>

						find absences and times only for the actual timesheet
						<ul>
						<?php if ($overtime->timesheet_id == $timesheet_id): ?>
							<li><?php echo $overtime->abwesenheitsgrund. ' von '. date_format(date_create($overtime->von), 'd.m.Y'). ' - '. date_format(date_create($overtime->bis), 'd.m.Y') ?></li>
						</ul>
						<?php endif; ?>

					<?php endforeach; ?>

					if no overtimes in actual timesheet found, display other text
					<?php if($counter == 0): ?>
						Sie haben im <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year?> keine Überstunden.
					<?php endif; ?>

					<table id="table-overtime" class="table table-condensed table-bordered">
						<thead>
						<th>Tag</th>
						<th>Arbeitszeit</th>
						<th>Überstunden</th>
						<?php if($isVorgesetzter): ?>
							<th style="border: solid 2px #31708f;" <?php echo (!$isApproved_overtime) ? 'class="text-danger"' : '' ?>>Genehmigen</th>
							<th style="border: solid 2px #31708f;">Genehmigen</th>
						<?php endif; ?>
						</thead>
						<tbody>
							<tr>
								<td>03.09.2018</td>
								<td>08:30 - 23:20</td>
								<td>3:30</td>
								<?php if($isVorgesetzter): ?>
									highlight cell if overtime was not confirmed
									<td <?php echo (isset($checkbox_overtime_arr[0]) && $checkbox_overtime_arr[0] == 'false') ? 'class="danger"' : '' ?>>
										remember checked after reload
										disabled if timesheet has not been sent OR in the future OR former monthlists need to be confirmed
										checked AND disabled if overtime was already confirmed
										<input class="checkbox-inline" name="checkbox_overtime[]" type="checkbox" value="0"
										<?php echo (isset($checkbox_overtime_arr[0]) && $checkbox_overtime_arr[0] == 'true') ? "checked" : "" ?>
										<?php echo (!$isSent || $isFuture || !$isAllowed_confirmTimesheet) ? "disabled" : "" ?>
										<?php echo ($isConfirmed) ? "checked disabled" : "" ?>>
									</td>
								<?php endif; ?>
							</tr>
							<tr>
								<td>03.09.2018</td>
								<td>08:30 - 23:20</td>
								<td>3:30</td>
								<?php if($isVorgesetzter): ?>
									<td <?php echo (isset($checkbox_overtime_arr[1]) && $checkbox_overtime_arr[1] == 'false') ? 'class="danger"' : '' ?>>
										<input class="checkbox-inline" name="checkbox_overtime[]" type="checkbox" value="1"
										<?php echo (isset($checkbox_overtime_arr[1]) && $checkbox_overtime_arr[1] == 'true') ? "checked" : "" ?>
										<?php echo (!$isSent || $isFuture || !$isAllowed_confirmTimesheet) ? "disabled" : "" ?>
										<?php echo ($isConfirmed) ? "checked disabled" : "" ?>>
									</td>
								<?php endif; ?>
							</tr>
							<tr>
								<td>03.09.2018</td>
								<td>08:30 - 23:20</td>
								<td>3:30</td>
								<?php if($isVorgesetzter): ?>
									<td <?php echo (isset($checkbox_overtime_arr[2]) && $checkbox_overtime_arr[2] == 'false') ? 'class="danger"' : '' ?>>
										<input class="checkbox-inline" name="checkbox_overtime[]" type="checkbox" value="2"
										<?php echo (isset($checkbox_overtime_arr[2]) && $checkbox_overtime_arr[2] == 'true') ? "checked" : "" ?>
										<?php echo (!$isSent || $isFuture || !$isAllowed_confirmTimesheet) ? "disabled" : "" ?>
										<?php echo ($isConfirmed) ? "checked disabled" : "" ?>>
									</td>
								<?php endif; ?>
							</tr>
						</tbody>
					</table>
				</div>
			</div>-->

		<!--panel: SEND timesheet-->
		<div class="row panel-top-cstm" style="<?php echo (!$isZaPflichtigOnSelDate || $hasFormerMissingTimesheet || $isConfirmed || $isFuture ||  !$isAllowed_createTimesheet) ? 'display: none;' : '' ?>">
			<div class="panel-body col-xs-8">
				<b>Monatsliste abschließen</b><br><br>
				Wenn  Sie alle erforderlichen Dokumente hochgeladen haben, verschicken Sie bis zum 03. des Folgemonats Ihre Monatsliste zur Freigabe an Ihre Vorgesetzte / Ihren Vorgesetzten.<br>
				Nach dem Verschicken kann diese <b>nicht</b> mehr bearbeitet werden.
			</div>
			<form method="POST" action="">
				<div class="panel-body col-xs-4"><br>
					<button type="submit" <?php echo ($isSent || $timesheet_vorzeitig_abgeschickt == 't' || !$isAllowed_sendTimesheet || $isVorgesetzter || $isPersonal || !$hasVorgesetzten || $hasCaseTimeChanges_today || !$isSyncedWithCaseTime_today || $isVorgesetzter_indirekt) ? 'disabled data-toggle="tooltip"' : '';
						echo (($isSent || $timesheet_vorzeitig_abgeschickt == 't' || !$isAllowed_sendTimesheet || !$isSyncedWithCaseTime_today) && !$isVorgesetzter && !$isPersonal && !$isVorgesetzter_indirekt) ? 'title="Information zur Sperre weiter unten in der Messagebox."' : '' ?>
						name="submitTimesheet" id="submitTimesheet" class="btn btn-default pull-right"
						onclick="return confirm('Wollen Sie die Monatsliste für <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year ?>\njetzt an <?php echo implode(' und ', $vorgesetzte_full_name_arr) ?> verschicken?');">Monatsliste verschicken</button>
				</div>
			</form>
		</div>

		<!-- panel: Monatsliste vorzeitig abschließen -->
		<div class="row panel-top-cstm" style="<?php echo ($isConfirmed || $isFuture || $hasFormerMissingTimesheet || !$isAllowed_createTimesheet) ? 'display: none;' : '' ?>">
			<div class="panel-body col-xs-8">
				<b>Monatsliste vorzeitig abschließen</b><br><br>
				Wenn Sie vor Monatsende Ihre Zeitaufzeichnung beenden wollen (zB aufgrund Urlaub/Feiertage zu Monatsende), markieren Sie die Checkbox.<br>
				Die Monatsliste wird dann zu Beginn des Folgemonats automatisch an Ihre Vorgesetzte / Ihren Vorgesetzten versendet werden und kann NICHT mehr direkt über den Button verschickt werden.
			</div>

			<div class="panel-body col-xs-4"><br>
				<form id="form-vorzeitigAbgeschickt" class="pull-right">
					<input type="hidden" id="timesheet_id" name="timesheet_id" value="<?php echo $timesheet_id; ?>">
					<div class="form-check pull-right">
						<input type="checkbox" class="form-check-input" id="vorzeitigAbgeschickt" name="vorzeitig_abgeschickt" <?php echo ($timesheet_vorzeitig_abgeschickt == 't') ? ' checked ' : ''; ?>
							<?php echo ($isSent || !$isZaPflichtigOnSelDate || $isVorgesetzter || $isPersonal || !$hasVorgesetzten || $isVorgesetzter_indirekt || !$isCurrentMonth)
								? ' disabled data-toggle="tooltip" title="Information zur Sperre weiter unten in der Messagebox."'
								: '' ?>
						<span class="form-check-label" for="vorzeitigAbgeschickt"> Vor Monatsende abschließen</span>
					</div>
				</form>
			</div>

		</div>
	</div><!--/.panel-->
	<br><br>

	<!--************************************		VIEW for supervisors, personnel department and timesheet manager-->
	<?php if ($isVorgesetzter || $isPersonal || $isTimesheetManager || $isVorgesetzterMitVertretungsfunktion): ?>
	<div class="panel panel-default" style="padding-bottom: 20px;">
		<div class="panel-heading">
			<span class="panel-title h2">Vorgesetztensicht</span>
		</div>

		<!--panel: CONFIRM timesheet-->
		<!-- <div class="row">
			<div class="panel-body col-xs-8">
				<span class="text-uppercase text-info"><b>Monatsliste genehmigen</b></span><br><br>
				Prüfen Sie die Zeiterfassung Ihres Mitarbeiters, indem Sie die Monatsliste herunterladen.<br>
				Prüfen Sie die Abwesenheitsbestätigungen, indem Sie auf die einzelnen Dokumentenlinks klicken.<br>
				Sobald Sie die Monatsliste genehmigt haben, wird der Status in der unteren Tabelle "Alle Monatslisten" auf grün gesetzt.<br><br>
			</div>
			<form id="formTimesheetConfirmation" method="POST" action="">
				<input type="hidden" name="checkbox_overtime_arr" value="" />
				<div class="panel-body col-xs-4"><br>
					<button type="submit" <?php echo ((!$isSent && !$isTimesheetManager) || $isConfirmed || !$isAllowed_confirmTimesheet || ($isVorgesetzter_indirekt && !$isVorgesetzter) ) ? 'disabled data-toggle="tooltip" title="Information zur Sperre weiter unten in der Messagebox."' : '' ?>
							name="submitTimesheetConfirmation" class="btn btn-primary pull-right"
							onclick="return confirm('Wollen Sie die Monatsliste für <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year ?>\nfür <php echo $full_name ?> sicher genehmigen?');">Monatsliste genehmigen</button>
				</div>
			</form>
		</div> -->

		<div class="row">
			<div class="panel-body col-xs-8">
				<span class="text-uppercase text-info"><b>Monatsliste genehmigen</b></span><br><br>
				Prüfen Sie die Zeiterfassung Ihres Mitarbeiters, indem Sie die Monatsliste herunterladen.<br>
				Prüfen Sie die Abwesenheitsbestätigungen, indem Sie auf die einzelnen Dokumentenlinks klicken.<br>
				Sobald Sie die Monatsliste genehmigt haben, wird der Status in der unteren Tabelle "Alle Monatslisten" auf grün gesetzt.<br><br>
			</div>
			<form id="formTimesheetConfirmation" method="POST" action="">
				<input type="hidden" name="checkbox_overtime_arr" value="" />
				<div class="panel-body col-xs-4"><br>
					<button type="submit"
					<?php if ((!$isSent && !$isTimesheetManager) || $isConfirmed || !$isAllowed_confirmTimesheet || ($isVorgesetzter_indirekt && !$isVorgesetzterMitVertretungsfunktion)): ?>
							disabled data-toggle="tooltip" title="Information zur Sperre weiter unten in der Messagebox."
						<?php if ($isVorgesetzterMitVertretungsfunktion): ?>
							data-toggle="tooltip" title="Als Vertretung können Sie diese direkt genehmigen."
						<?php else: ?>
							data-toggle='tooltip' title=""
						<?php endif; ?>
					<?php endif; ?>
							name="submitTimesheetConfirmation" class="btn btn-primary pull-right"
							onclick="return confirm('Wollen Sie die Monatsliste für <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year ?>\nfür <?php echo $full_name ?> sicher genehmigen?');">Monatsliste genehmigen</button>
				</div>
			</form>
		</div>

		<!--panel: SEND BACK timesheet-->
		<div class="row panel-top-cstm">
			<div class="panel-body col-xs-8">
				<span class="text-info text-uppercase"><b>Monatsliste retournieren</b></span><br><br>
				Retournieren Sie die Monatsliste, falls diese nochmals überarbeitet werden muss.<br>
				Ihr Mitarbeiter kann diese dann wieder bearbeiten und erneut an Sie versenden.<br>
				Die Monatsliste wird hierbei <b>nicht</b> genehmigt.
			</div>
			<form method="POST" action="<?php echo $_SERVER['PHP_SELF']. '?timesheet_id='. $timesheet_id ?>">
				<div class="panel-body col-xs-4"><br>
					<button type="submit"
						<?php if ((!$isSent || $isConfirmed || !$isAllowed_confirmTimesheet) && ($isVorgesetzter || $isPersonal || $isTimesheetManager || $isVorgesetzterMitVertretungsfunktion)): ?>
							disabled
							<?php if ($isTimesheetManager && !$isConfirmed): ?>
								data-toggle="tooltip" title="Monatsliste wurde nicht versendet. Als Timesheet Manager können Sie diese direkt genehmigen."
							<?php else: ?>
								data-toggle="tooltip" title="Information zur Sperre weiter unten in der Messagebox."
							<?php endif; ?>
						<?php endif; ?>
						name="submitTimesheetSendBack" class="btn btn-default pull-right" style="border-color: #31708f; color: #31708f;"
						onclick="return confirm('Wollen Sie die Monatsliste für <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year ?>\nfür <?php echo $full_name ?> sicher retournieren?');">Monatsliste retournieren</button>
				</div>
			</form>
		</div>
	</div><!--/.panel-->
	<br><br>
	<?php endif; ?>


	<!--************************************		VIEW for personnel department-->

	<?php if ($isPersonal): ?>
	<div class="panel panel-default" style="padding-bottom: 20px;">
		<div class="panel-heading">
			<h2 class="panel-title">Personalverwaltung</h2>
		</div>

		<!--panel: CANCEL CONFIRMATION-->
		<div class="row">
			<div class="panel-body col-xs-8">
				<span class="text-info text-uppercase"><b>Genehmigung aufheben</b></span><br><br>
				<p>
					Genehmigungen, die von Vorgesetzten erteilt wurden, können hier aufgehoben werden.<br>
					Dabei wird auch das "Abgeschickt am"-Datum wieder zurückgesetzt, damit die Monatsliste wieder bearbeitet und an Vorgesetzte versendet werden kann.<br>
					Um mögliche Zeitabhängigkeiten zu berücksichtigen, werden auch alle Genehmigungen der zeitlich darauffolgenden Monatslisten wieder aufgehoben.

				</p>
			</div>
			<div class="panel-body col-xs-4"><br>
				<form class="form" method="POST" action="<?php echo $_SERVER['PHP_SELF']. '?timesheet_id='. $timesheet_id ?>">
				<button type="submit" <?php echo (!$isSent || !$isConfirmed || !$isAllowed_confirmTimesheet) ? 'disabled data-toggle="tooltip" title="Die Monatsliste ist bisher noch nicht genehmigt worden."' : '' ?>
					name="submitTimesheetCancelConfirmation" class="btn btn-primary pull-right"
					onclick="return confirm('Wollen Sie die Genehmigung der Monatsliste für <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year ?>\n für <?php echo $full_name ?> sicher aufheben?\nDabei werden zur Überarbeitung auch\ndie Genehmigungen ALLER MONATE DANACH wieder aufgehoben!');">Genehmigung aufheben</button>
				</form>
			</div>
		</div>

		<!--panel: ADD CONTROLLING REMARK-->
		<div class="row panel-top-cstm">
			<form class="form" method="POST" action="<?php echo $_SERVER['PHP_SELF']. '?timesheet_id='. $timesheet_id ?>">
				<div class="panel-body col-xs-8">
					<span class="text-info text-uppercase"><b>Kontrollnotizen</b></span><br><br>
					<div class="form-group">
						<textarea class="form-control" rows="7" name="ta_controllingRemark"><?php echo $timesheet_cntrl_remark ?></textarea>
					</div>
				</div>
				<div class="panel-body col-xs-4"><br><br>
					<div class="well pull-right text-center" style="width: 185px;">
						<b>Zuletzt kontrolliert am: <?php echo (!empty($timesheet_cntrl_date)) ? $timesheet_cntrl_date->format('d.m.Y') : '-' ?></b>
						<?php echo (!empty($controller_name)) ? '<br>von: '. $controller_name : '' ?>
					</div>
					<button type="submit" name="submitTimesheetControlled" class="btn btn-primary pull-right">Kontrolldaten speichern</button>
				</div>
			</form>
		</div>
	</div><!--/.panel-->
	<br><br>
	<?php endif; ?>


	<!--************************************		ALERTS	 -->

	<!-- IF uid is EMPLOYEE -->
	<?php if (!$isVorgesetzter && !$isPersonal && !$isVorgesetzter_indirekt): ?>

        <!-- IF month of the timesheet is not over, timesheet should not be sent -->
        <?php if (!$isZeitaufzeichnungspflichtig): ?>
            <div class="alert alert-info alert-dismissible text-center" role="alert">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <b>Sie sind in diesem Monat nicht zeitaufzeichnungspflichtig.</b>
            </div>
        <?php endif; ?>

		<!-- IF first entry AND obliged to record times AND timesheets are missing before actual date -->
		<?php if ($isFirstEntry && $isZeitaufzeichnungspflichtig && !$isTimesheetManager): ?>
			<div class="alert alert-danger alert-dismissible text-center" role="alert">
				<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<b>Sie sind ab <?php echo $monatsname[$sprache_index][((new DateTime($ersteZaPflicht->von))->format('n')) - 1]. ' '. (new DateTime($ersteZaPflicht->von))->format('Y'); ?> zeitaufzeichnungspflichtig.</b><br><br>
				Monatslisten müssen chronologisch erstellt und an Vorgesetzte gesendet werden.<br>
				<a href="<?php echo $_SERVER['PHP_SELF']?>?year=<?php echo (new DateTime($ersteZaPflicht->von))->format('Y') ?>&month=<?php echo (new DateTime($ersteZaPflicht->von))->format('m')?>"
				   class="text-danger">
				   <b>Monatsliste <?php echo $monatsname[$sprache_index][(new DateTime($ersteZaPflicht->von))->format('n') - 1]. ' '. (new DateTime($ersteZaPflicht->von))->format('Y') ?> jetzt erstellen</b></a>
		   </div>
		<?php endif; ?>

		<!-- Info WHEN new timesheet was created and is NOT disabled by missing timesheets -->
		<?php if ($isZaPflichtigOnSelDate && !$hasFormerMissingTimesheet): ?>
		<div id="timesheetSaveSuccess" class="alert alert-success alert-dismissible text-center" role="alert" style="display: none;">
			<b>Die Monatsliste <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year ?> wurde erfolgreich erstellt.</b>
		</div>
		<?php endif; ?>

		<!-- IF chosen date is in the future -->
		<?php if ($isFuture): ?>
		<div class="alert alert-danger alert-dismissible text-center" role="alert">
			<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<b>Für <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year ?> kann noch keine Monatsliste angelegt werden!</b><br><br>
			Es können nur Monatslisten für den aktuellen oder vergangene Monate erstellt werden.
		</div>
		<?php endif; ?>

		<!-- IF month of the timesheet is not over, timesheet should not be sent -->
		<?php if (!$isAllowed_sendTimesheet && !$hasFormerMissingTimesheet && $isAllowed_createTimesheet && !$isFuture && $date_selected->format('Y-m') == $date_actual->format('Y-m')): ?>
		<?php $date_next_month = new DateTime('first day of next month midnight'); ?>
		<div class="alert alert-info alert-dismissible text-center" role="alert">
			<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			Ab dem <?php echo $date_next_month->format('d.m.Y') ?> können Sie die Monatsliste für <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year ?> an Ihren Vorgesetzten schicken.<br>
			Sie können jedoch laufend Ihre Bestätigungen zu Ihren Abwesenheitszeiten hochladen.
		</div>
	<?php endif; ?>

		<!-- IF today inserted/updated/deleted times concerning the selected month -->
		<?php if (!$isSyncedWithCaseTime_today || $hasCaseTimeChanges_today): ?>
		<div class="alert alert-warning alert-dismissible text-center" role="alert">
			<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<b>Ab dem morgigen Tag können Sie Ihre Monatsliste für <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year ?> versenden!</b><br><br>
			Sie haben heute in Ihrer Zeiterfassung, in Ihren Zeitsperren oder im Urlaubstool für <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year ?> Einträge gelöscht bzw. Änderungen vorgenommen.<br>
			Das System berechnet täglich über Nacht die Monatslisten neu.<br>
			Ab morgen steht Ihnen die korrekte Monatsliste wieder zur Verfügung.
		</div>
		<?php endif; ?>

		<!-- IF timesheets are missing before selected date -->
		<?php if ($hasFormerMissingTimesheet && !$isConfirmed && !$isFuture && !is_null($date_last_timesheet)): ?>
		<div class="alert alert-danger alert-dismissible text-center" role="alert">
			<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<b>Für <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year ?> kann noch keine Monatsliste angelegt werden!</b><br><br>
			Monatslisten müssen chronologisch erstellt und an Vorgesetzte gesendet werden.<br>
			Ihre letzte Monatsliste haben sie für <?php echo $monatsname[$sprache_index][($date_last_timesheet->format('n')) - 1]. ' '. $date_last_timesheet->format('Y'); ?> erstellt.<br><br>
			<a href="<?php echo $_SERVER['PHP_SELF']?>?year=<?php echo $firstMissingTimesheetDatum->format('Y') ?>&month=<?php echo $firstMissingTimesheetDatum->format('m') ?>"
            class="text-danger"><b>Monatsliste <?php echo $monatsname[$sprache_index][($firstMissingTimesheetDatum->format('n')) - 1]. ' '. $firstMissingTimesheetDatum->format('Y') ?> jetzt erstellen</b></a>

        </div>
		<?php endif; ?>

		<!-- IF former timesheets were not sent -->
		<?php if (!$isAllowed_sendTimesheet): ?>
		<div class="alert alert-danger alert-dismissible text-center" role="alert">
			<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<b>Sie müssen noch frühere Monatslisten versenden!</b><br><br>
			Monatslisten müssen chronologisch an Vorgesetzte gesendet werden.<br>
			Bitte entnehmen Sie der unten stehenden Tabelle "Alle Monatslisten", welche Monatslisten noch nicht versendet wurden.
		</div>
		<?php endif; ?>

		<!-- IF timesheet is sent AND NOT confirmed -->
		<?php if ($isSent && !$isConfirmed): ?>
		<div class="alert alert-success alert-dismissible text-center" role="alert">
			<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<b>Ihre Monatsliste für <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year ?> ist erfolgreich an <?php echo implode(' und ', $vorgesetzte_full_name_arr) ?> versendet worden!</b><br><br>
			Nach dem Versenden können keine Änderungen mehr vorgenommen werden.<br>
			Sollte dies dennoch nötig sein, wenden Sie sich an Ihren Vorgesetzten für eine erneute Freigabe.<br><br>
			Sobald Ihre Monatsliste genehmigt wurde, wird sie in der unteren Tabelle "Alle Monatslisten" mit einer grünen Ampel versehen.
		</div>
		<?php endif; ?>

		 <!--IF timesheet is sent AND confirmed-->
		<?php if ($isSent && $isConfirmed): ?>
		<div class="alert alert-info alert-dismissible text-center" role="alert">
			<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<b>Die Monatsliste für <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year?> ist genehmigt.</b><br><br>
			Sie können diese weiterhin für Ihren persönlichen Bedarf herunterladen.
		</div>
		<?php endif; ?>

<!--    NOTE: irrelevant mit neuer Vertragsstruktur -->
<!--		 IF not allowed to create timesheet (only checked when trying to create FIRST timesheet 2 or more months before actual monthyear)-->
<!--		--><?php //if (!$isAllowed_createTimesheet && !$isZeitaufzeichnungspflichtig): ?>
<!--		<div class="alert alert-warning alert-dismissible text-center" role="alert">-->
<!--			<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>-->
<!--			--><?php //if ($isBeforeGolive): ?>
<!--			<b>Monatslisten dürfen NICHT vor dem --><?php //echo $monatsname[$sprache_index][$date_golive->format('n') - 1]. ' '. $date_golive->format('Y') ?><!-- liegen!</b>-->
<!--			--><?php //else: ?>
<!--			<b>Für --><?php //echo $monatsname[$sprache_index][$month - 1]. ' '. $year ?><!-- kann noch keine Monatsliste angelegt werden!</b><br><br>-->
<!--			Monatslisten können nur für abgeschlossene Monate an den Vorgesetzten gesendet werden.<br>-->
<!--			<a href="--><?php //echo $_SERVER['PHP_SELF']?><!--?year=--><?php //echo $year ?><!--&month=--><?php //echo ($month - 1)?><!--"-->
<!--			   class="text-warning"><b>Erste Monatsliste für --><?php //echo $monatsname[$sprache_index][$month - 2]. ' '. $year ?><!-- jetzt erstellen</b></a>-->
<!--			--><?php //endif; ?>
<!--		</div>-->
<!--		--><?php //endif; ?>

		<!-- IF there are casetime server errors that are defined as blocking errors -->
		<?php if ($hasCaseTimeError && $isAllowed_sendTimesheet && $isSyncedWithCaseTime_today && !$hasCaseTimeChanges_today): ?>
		<div class="alert alert-danger alert-dismissible text-center" role="alert">
			<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<b>Die Monatsliste für <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year ?> konnte nicht versendet werden!</b><br><br>
			Die Zeiterfassung für <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year ?> oder den Monat davor ist nicht vollständig oder inkorrekt.<br>
			Bitte überarbeiten Sie erst Ihre Zeiterfassung für diesen Zeitraum und versenden Sie danach erneut Ihre Monatsliste.<br><br>
			<a href="<?php echo APP_ROOT. 'cis/private/tools/zeitaufzeichnung.php' ?>" class="text-danger"><b>Zeitaufzeichnung jetzt bearbeiten</b></a>
		</div>
		<?php endif; ?>

		<!-- IF there are blocking Pause errors -->
		<?php if ($hasBlockingPauseError && $isAllowed_sendTimesheet && $isSyncedWithCaseTime_today && !$hasCaseTimeChanges_today):  ?>
			<div class="alert alert-danger alert-dismissible text-center" role="alert">
				<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<b>
					Pausenfehler!
				</b>
				<br><br>Bitte korrigieren Sie den Pausenfehler vom
					<?php
					$day = new DateTime($hasBlockingPauseError);
					echo $day->format('d.m.Y');
					?>!<br><br>
				<a href="<?php echo APP_ROOT. 'cis/private/tools/zeitaufzeichnung.php' ?>" class="text-danger"><b>Zeitaufzeichnung jetzt bearbeiten</b></a>
			</div>
		<?php endif; ?>

		<!-- IF Checkbox 'Vor Monatsende abschließen' is checked, the button 'Monatsliste abschicken' is blocked -->
		<div class="alert alert-info alert-dismissible text-center <?php echo $timesheet_vorzeitig_abgeschickt == 't' && !$isSent ? '' : 'hidden' ?>" role="alert" id="alert-vorzeitigAbgeschickt">
			<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			Monatslisten können nicht mehr direkt verschickt werden, wenn Sie die Checkbox 'Vor Monatsende abschließen' markiert haben.<br>
			<b>Die Monatsliste für <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year ?> wird im kommenden Monat automatisch versendet werden</b>.
		</div>

		<!-- IF document uploads are missing (after check against absences) -->
		<?php if ($hasMissingBestaetigung): ?>
		<div class="alert alert-danger alert-dismissible" role="alert">
			<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<div class="col-xs-offset-4">
				<b>Die Monatsliste konnte noch nicht versendet werden!</b>&nbsp;&nbsp;
				<a role="button" data-toggle="modal" data-target="#modalSendMonatsliste"><i class="alert-danger fa fa-question-circle-o fa-lg" aria-hidden="true"></i></a><br>
				Es fehlen noch Bestätigungen für:
				<ul>
				<?php foreach ($missing_bestaetigungen as $missing_bestaetigung => $value): ?>
					<?php if ($missing_bestaetigung == 'arztbesuch' && $value != 0): ?>
						<li><?php echo $value; ?> Arztbesuch(e)</li>
					<?php elseif ($missing_bestaetigung == 'behoerdenbesuch' && $value != 0):?>
						<li><?php echo $value; ?> Behördenbesuch(e)</li>
					<?php elseif ($missing_bestaetigung == 'krankenstand' && $value != 0):?>
						<li>mindestens einen Krankenstand</li>
					<?php elseif ($missing_bestaetigung == 'pflegeurlaub' && $value != 0):?>
						<li>mindestens einen Pflegeurlaub</li>
					<?php endif; ?>
				<?php endforeach; ?>
				</ul>
			</div>

			<!--POPUP WINDOW with document upload help information-->
			<div class="modal fade text-muted" tabindex="-1" role="dialog" id="modalSendMonatsliste">
				<div class="modal-dialog" role="document">
					<div class="modal-content">
						<div class="modal-header">
							<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
							<h4 class="modal-title">Monatsliste versenden</h4>
						</div>
						<div class="modal-body">
							<p>
								1. Vergewissern Sie sich, dass Sie für alle Ihre Abwesenheitszeiten jeweils SEPARAT eine Bestätigung hochgeladen haben.<br>
							</p>
							<p>
								2. Prüfen Sie in in der Auflistung Ihrer Dokumentenuploads, ob der Bestätigungstyp richtig ist und zur Bestätigung passt:<br>
							<ul>
								<li>Bestätigung Arztbesuch:<br><small><em>für Arztbesuche, Dauer max. 1 Tag.<br>Abwesenheit wurde im CIS in der täglichen Zeiterfassung eingetragen.</em></small></li>
								<li>Bestätigung Behörde:<br><small><em>für Behördenwege, Dauer max. 1 Tag.<br>Abwesenheit wurde im CIS in der täglichen Zeiterfassung eingetragen.</em></small></li>
								<li>Bestätigung Dienstverhinderung:<br><small><em>für Dienstverhinderungen, Dauer max. 1 Tag.<br>Abwesenheit wurde im CIS in der täglichen Zeiterfassung eingetragen.</em></small></li>
								<li>Bestätigung Krankenstand:<br><small><em>für Krankenstände/Spitalsaufenthalte, ab 3 Tage Dauer.<br>Abwesenheit wurde im CIS über die Zeitsperre eingetragen.</em></small></li>
								<li>Bestätigung Pflegeurlaub:<br><small><em>für Pflegeurlaube, ab 3 Tage Dauer.<br>Abwesenheit wurde im CIS über die Zeitsperre eingetragen.</em></small></li>
							</ul>
							Sollte der Bestätigunstyp nicht passen, so löschen Sie das Dokument. Laden Sie es dann erneut mit dem richtigen Typ wieder hoch.
							</p>
							<p>
								3. Sammelscans (EINE Datei, in der mehrere Bestätigungen eingescant sind), können nicht den entsprechenden Bestätigunstypen zugeordnet werden und werden daher nicht akzeptiert.
							</p>
						</div>
						<div class="modal-footer">
							<button type="button" class="btn btn-default" data-dismiss="modal">Schließen</button>
						</div>
					</div><!-- /.modal-content -->
				</div><!-- /.modal-dialog -->
			</div><!-- /.modal -->
		</div><!-- /.alert -->
		<?php endif; ?>

		<!-- IF no supervisor existing -->
		<?php if (!$hasVorgesetzten): ?>
		<div class="alert alert-danger alert-dismissible text-center" role="alert">
			<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<b>Sie sind im System keiner/m Vorgesetzten unterstellt.</b><br><br>
			Monatslisten werden an Ihre/n Vorgesetzte/n versendet.<br>
			Bitte wenden Sie sich für die Verwaltung Ihrer Monatslisten an die Personalabteilung.
		</div>
		<?php endif; ?>

	<?php endif; ?><!-- /.end overall alert conditions -->

	<!--************************************		ALERTS FOR SUPERVISOR or PERSONNEL DEPARTMENT-->

	<!-- IF uid is SUPERVISOR, INDIRECT SUPERVISOR or PERSONNEL MANAGER-->
	<?php if ($isVorgesetzter || $isPersonal || $isVorgesetzter_indirekt): ?>
		<!-- Info WHEN new timesheet was created and is NOT disabled by missing timesheets -->
		<?php if (!$hasFormerMissingTimesheet): ?>
		<div id="timesheetSaveSuccess" class="alert alert-success alert-dismissible text-center" role="alert" style="display: none;">
			<b>Die Monatsliste <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year ?> wurde erfolgreich erstellt.</b>
		</div>
		<?php endif; ?>

		<!--IF timesheet was not yet sent by the employee-->
		<?php if(!$isSent && ($isVorgesetzter_indirekt && $isVorgesetzter) && !$isTimesheetManager): ?>
			<div class="alert alert-danger alert-dismissible text-center" role="alert">
				<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<b>Die Monatsliste für <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year?> muss von Ihrem Mitarbeiter noch versendet werden!</b><br><br>
				Ihr Mitarbeiter muss die Monatsliste erst bzw. erneut versenden, bevor Sie diese genehmigen oder retournieren können.
			</div>
		<?php endif; ?>

		<!--IF former timesheets are not yet confirmed (only shown for supervisors and timesheet managers)-->
		<?php if(!$isAllowed_confirmTimesheet && (($isVorgesetzter && $isVorgesetzter_indirekt) || $isTimesheetManager)): ?>
		<div class="alert alert-danger alert-dismissible text-center" role="alert">
			<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<b>Sie müssen noch frühere Monatslisten genehmigen!</b><br><br>
			Monatslisten müssen chronologisch genehmigt werden.<br>
<!--			Die weitest zurückliegende Monatsliste, die noch nicht genehmigt wurde, ist vom --><?php //echo $monatsname[$sprache_index][$notConfirmed_timesheet['datum']->format('n') - 1]. ' '. $notConfirmed_timesheet['datum']->format('Y') ?><!--.<br><br>-->
<!--			<a role="button" href="--><?php //echo $_SERVER['PHP_SELF']?><!--?timesheet_id=--><?php //echo $notConfirmed_timesheet['timesheet_id'] ?><!--" class="text-danger"><b>Monatsliste --><?php //echo $monatsname[$sprache_index][$notConfirmed_timesheet['datum']->format('n') - 1]. ' '. $notConfirmed_timesheet['datum']->format('Y') ?><!-- jetzt genehmigen</b></a>-->
		</div>
		<?php endif; ?>

		<!--IF overtime was not approved-->
		<!--		<?php if(!$isApproved_overtime): ?>
				<div class="alert alert-danger alert-dismissible text-center" role="alert">
					<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					<b>Bitte prüfen und genehmigen Sie erst alle Überstunden!</b><br><br>
					Wenn Sie nicht mit allen Überstunden einverstanden sind, retournieren Sie die Monatliste.<br>
					Informieren Sie Ihren Mitarbeiter, damit dieser seine Zeiterfassung überarbeitet.
				</div>

		<?php endif; ?>
		-->

		 <!--IF timesheet is sent AND confirmed-->
		<?php if ($isSent && $isConfirmed): ?>
		<div class="alert alert-info alert-dismissible text-center" role="alert">
			<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<b>Die Monatsliste für <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year?> ist genehmigt.</b><br><br>
			Sie können diese weiterhin für Ihren persönlichen Bedarf herunterladen.
		</div>
		<?php endif; ?>

	<?php endif; ?><!-- /.end alert conditions for supervisors & hr-->
	<br><br>


	<!--************************************		ALERTS FOR TIMESHEET MANAGER-->

	<?php if ($isTimesheetManager): ?>
		<?php if ($isFirstEntry && $isZeitaufzeichnungspflichtig): ?>
		<div class="alert alert-danger alert-dismissible text-center" role="alert">
			<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<b><?php echo $full_name. ' ist ab '. $monatsname[$sprache_index][((new DateTime($ersteZaPflicht->datum))->format('n')) - 1]. ' '. (new DateTime($ersteZaPflicht->datum))->format('Y'); ?> zeitaufzeichnungspflichtig.</b><br><br>
			Monatslisten müssen chronologisch erstellt und an Vorgesetzte gesendet werden.<br>
			<a href="<?php echo $_SERVER['PHP_SELF']?>?year=<?php echo (new DateTime($ersteZaPflicht->datum))->format('Y') ?>&month=<?php echo (new DateTime($ersteZaPflicht->datum))->format('m')?>&employee_uid=<?php echo $uid ?>&create=true""
			   class="text-danger"><b>Monatsliste <?php echo $monatsname[$sprache_index][(new DateTime($ersteZaPflicht->datum))->format('n') - 1]. ' '. (new DateTime($ersteZaPflicht->datum))->format('Y') ?> jetzt erstellen</b></a>
		</div>
		<?php endif; ?>

	<?php endif; ?><!-- /.end alert conditions for timesheet managers -->



	<!--************************************	ALL TIMESHEETS - TABLE -->

	<h4>Alle Monatslisten</h4><br>
    <table id="monatslistenTable" class="table table-bordered tablesorter tablesort-active" role="grid">
        <thead>
            <tr>
                <th>Monatsliste</th>
                <th>Zeitaufzeichungspflicht</th>
                <th>Abwesenheit</th>
                <th>Dokumente</th>
                <th>Abgeschickt am</th>
                <th>Genehmigt</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $monat = new DateTime();
        while ($monat->format('Y-m') >= $monatslisteStartdatum->format('Y-m')): ?>
            <tr>
            <?php $isZaPflichtig = $vbt->isZaPflichtig($uid, $monat->format('Y-m-t')); ?>

            <!--Zeitaufzeichnungspflichtig-->
            <?php if ($isZaPflichtig) :?>
                <?php $ts = new Timesheet($uid, $monat->format('m'), $monat->format('Y')); ?>

                <!--Timesheet vorhanden-->
                <?php if (!is_null($ts->timesheet_id)) :?>
                    <!--for supervisors, personnel department & timesheet manager-->
                    <?php if ($isVorgesetzter || $isPersonal || $isVorgesetzter_indirekt || $isTimesheetManager): ?>
                    <td><a href="<?php echo $_SERVER['PHP_SELF']?>?timesheet_id=<?php echo $ts->timesheet_id ?>">
                        <?php echo $monatsname[$sprache_index][$monat->format('n') - 1]. ' '. $monat->format('Y') ?>
                    </a></td>
                    <!--for employees-->
                    <?php else: ?>
                    <td>
                    <a href="<?php echo $_SERVER['PHP_SELF']?>?year=<?php echo $monat->format('Y') ?>&month=<?php echo $monat->format('m')?>">
                        <?php echo $monatsname[$sprache_index][$monat->format('n') - 1]. ' '. $monat->format('Y') ?>
                    </a>
                    <?php if (isset($ts->kontroll_notizen) && !is_null($ts->kontroll_notizen)): ?>
                        <span class="label label-warning pull-right text-uppercase" style="margin-left: 5px;">Notiz</span>
                    <?php endif; ?>
                    </td>
                    <?php endif; ?>

                 <!--Timesheet fehlt-->
                 <?php else: ?>
                    <td>
                        <!--supervisors & personnel department: text only-->
                        <?php if (!$isTimesheetManager && ($isVorgesetzter || $isPersonal || $isVorgesetzter_indirekt)): ?>
                            <?php if (!is_null($lastConfirmedTimesheet) && ($monat->format('Y-m') < (new DateTime($lastConfirmedTimesheet->datum))->format('Y-m'))): ?>
                                <?php echo $monatsname[$sprache_index][$monat->format('n') - 1]. ' '. $monat->format('Y') ?>
                                <span class="label pull-right text-uppercase" style="background-color: indianred; margin-left: 5px;">Nachtrag nicht möglich</span>
                            <?php else: ?>
                                <?php echo $monatsname[$sprache_index][$monat->format('n') - 1]. ' '. $monat->format('Y') ?>
                                <span class="label pull-right text-uppercase" style="background-color: lightgrey;">fehlt</span>
                            <?php endif; ?>

                        <!--for timesheet managers (allowed to create timesheet, but needs to be executed differently as for employees to retrieve all timesheet manager data again)-->
                        <?php elseif($isTimesheetManager): ?>
                            <?php if (!is_null($lastConfirmedTimesheet) && ($monat->format('Y-m') < (new DateTime($lastConfirmedTimesheet->datum))->format('Y-m'))): ?>
                                <?php echo $monatsname[$sprache_index][$monat->format('n') - 1]. ' '. $monat->format('Y') ?>
                                <span class="label pull-right text-uppercase" style="background-color: indianred; margin-left: 5px;">Nachtrag nicht möglich</span>
                            <?php elseif (is_null($firstMissingTimesheetDatum) || !is_null($firstMissingTimesheetDatum) && $monat->format('Y-m') == $firstMissingTimesheetDatum->format('Y-m')) : ?>
                                <span class="label pull-right text-uppercase" style="background-color: lightgrey; margin-left: 5px;">fehlt</span>
                                <a href="<?php echo $_SERVER['PHP_SELF']?>?year=<?php echo $monat->format('Y') ?>&month=<?php echo $monat->format('m') ?>&employee_uid=<?php echo $uid ?>&create=true">
                                    <?php echo $monatsname[$sprache_index][$monat->format('n') - 1]. ' '. $monat->format('Y') ?>
                                    &nbsp;<i class="fa fa-plus-square-o" aria-hidden="true"></i>
                                </a>
                            <?php else: ?>
                                <?php echo $monatsname[$sprache_index][$monat->format('n') - 1]. ' '. $monat->format('Y') ?>
                                <span class="label pull-right text-uppercase" style="background-color: lightgrey; margin-left: 5px;">fehlt</span>
                            <?php endif; ?>

                        <!--for employees-->
                        <?php else: ?>
                            <?php if (!is_null($lastConfirmedTimesheet) && ($monat->format('Y-m') < (new DateTime($lastConfirmedTimesheet->datum))->format('Y-m'))): ?>
                                <?php echo $monatsname[$sprache_index][$monat->format('n') - 1]. ' '. $monat->format('Y') ?>
                                <span class="label pull-right text-uppercase" style="background-color: indianred; margin-left: 5px;">Nachtrag nicht möglich</span>

                            <?php elseif (is_null($firstMissingTimesheetDatum) || (!is_null($firstMissingTimesheetDatum) && $monat->format('Y-m') == $firstMissingTimesheetDatum->format('Y-m'))) : ?>
                                <span class="label pull-right text-uppercase" style="background-color: lightgrey; margin-left: 5px;">fehlt</span>
                                <a href="<?php echo $_SERVER['PHP_SELF']?>?year=<?php echo $monat->format('Y') ?>&month=<?php echo $monat->format('m')?>">
                                    <?php echo $monatsname[$sprache_index][$monat->format('n') - 1]. ' '. $monat->format('Y') ?>
                                    &nbsp;<i class="fa fa-plus-square-o" aria-hidden="true"></i>
                                </a>

                            <?php else: ?>
                                <?php echo $monatsname[$sprache_index][$monat->format('n') - 1]. ' '. $monat->format('Y') ?>
                                <span class="label pull-right text-uppercase" style="background-color: lightgrey; margin-left: 5px;">fehlt</span>
                            <?php endif; ?>
                            <!-- Flag wenn Kontrollnotizen -->
                            <?php if (isset($ts->kontroll_notizen) && !is_null($ts->kontroll_notizen)): ?>
                                <span class="label label-warning pull-right text-uppercase" style="margin-left: 5px;">Notiz</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>

                <?php endif; ?>

            <!--Nicht zeitaufzeichnungspflichtig-->
            <?php else: ?>
               <td>
                   <?php echo $monatsname[$sprache_index][$monat->format('n') - 1]. ' '. $monat->format('Y') ?>
               </td>

            <?php endif; ?>

            <!--Zeitaufzeichnungspflichtig-->
            <td><?php echo ($isZaPflichtig)? 'ja' : 'nein' ?></td>

            <!--Abwesenheit: absence reasons & times-->
            <td>
                <?php foreach ($total_absences_arr as $absence): ?>
                    <?php if ($ts->timesheet_id == $absence->timesheet_id): ?>
                        <?php echo (new DateTime($absence->von))->format( 'd.m.Y'). ' - '. (new DateTime($absence->bis))->format( 'd.m.Y'). ': '. $absence->abwesenheitsgrund. "<br>" ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </td>

            <!--Dokumente: link to documents-->
            <td>
                <?php foreach ($total_bestaetigungen_arr as $bestaetigung): ?>
                    <?php $date_bestaetigung = new DateTime($bestaetigung->datum); ?>
                    <?php if($monat->format('m-Y') == $date_bestaetigung->format('m-Y')): ?>
                        <a href="<?php echo APP_ROOT. 'addons/casetime/cis/timesheet_dmsdownload.php?dms_id='. $bestaetigung->dms_id ?>"
                           target="_blank"><?php echo $db->convert_html_chars($bestaetigung->name) ?></a><br>
                    <?php endif; ?>
                <?php endforeach; ?>
            </td>

            <!--Abgeschickt am: sending date-->
            <?php if (!is_null($ts->abgeschicktamum)): ?>
                <td><?php echo (new DateTime($ts->abgeschicktamum))->format('d.m.Y') ?></td>
            <?php elseif($isZaPflichtig): ?>
                <td>Nicht abgeschickt</td>
            <?php else: ?>
                <td>Nicht Zeitaufzeichnungspflichtig</td>
            <?php endif; ?>
                <!--                    <td>--><?php //echo (is_null($ts->abgeschicktamum)) ? 'Nicht abgeschickt' : (new DateTime($ts->abgeschicktamum))->format('d.m.Y') ?><!--</td>-->

            <!--Genehmigt: confirmation status-->
            <?php if (is_null($ts->genehmigtamum) & $isZaPflichtig): ?>
                <td class='text-center' data-toggle="tooltip" title="Muss noch von Ihrem Vorgesetzten genehmigt worden."><img src="../../../skin/images/ampel_gelb.png" ></td>
            <?php elseif (!$isZaPflichtig): ?>
                <td></td>
            <?php else: ?>
                <td class='text-center' data-toggle="tooltip" title="Genehmigt am <?php echo (new DateTime($ts->genehmigtamum))->format('d.m.Y') ?> von <?php echo (new Benutzer($ts->genehmigtvon))->getFullName() ?>"><img src="../../../skin/images/tick.png" ></td>
            <?php endif; ?>
                <!--                    <td>--><?php //echo (is_null($ts->genehmigtamum)) ? 'Nicht genehmigt' : 'Genehmigt' ?><!--</td>-->

            <!--Monat erhöhen-->
            <?php $monat->sub(new DateInterval('P1M')); ?>

            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</div><!--/.col-xs-8-->

<!--************************************	RIGHT CONTAINER -->
<div class="col-xs-4">
	<!--IF timesheet is sent AND confirmed-->
	<?php if ($isSent && $isConfirmed): ?>
		<div class="panel panel-success">
			<div class="panel-heading text-center text-success">Status: <b>GENEHMIGT</b></div>
		</div>
	<?php elseif (!$isZaPflichtig): ?>
        <div class="panel panel-warning">
            <div class="panel-heading text-center text-warning">Status: <b>ABGESCHICKT</b></div>
        </div>
	<?php elseif ($isSent && !$isConfirmed): ?>
		<div class="panel panel-warning">
			<div class="panel-heading text-center text-warning">Status: <b>ABGESCHICKT</b></div>
		</div>
	<?php elseif (!$isSent && !$isConfirmed): ?>
		<div class="panel panel-default">
			<div class="panel-heading text-center">Status: <b>NICHT ABGESCHICKT</b></div>
		</div>
	<?php endif; ?>

	<div class="panel panel-default">
		<div class="panel-heading text-center"><p>Supportanfragen unter<br><a href="mailto:zeiterfassung@technikum-wien.at">zeiterfassung@technikum-wien.at</a></p></div>
	</div>

	<?php if (!empty($timesheet_cntrl_remark)): ?>
	<div class="panel panel-warning">
		<div class="panel-heading">Kontrollnotiz der Personalabteilung
			<span class="pull-right">Kontrolldatum: <?php echo $timesheet_cntrl_date->format('d.m.Y') ?></span>
		</div>

		<!--panel: DISPLAY controlling remark-->
		<div class="panel-body">
			<p><?php echo $timesheet_cntrl_remark ?></p>
		</div>
	</div>
	<br><br>
	<?php endif; ?>
</div><!--/.col-xs-4-->
</div><!--/.row-->


<?php
// NOTE: Code at the END of script to recognize JS-methods
// Refresh data when document was uploaded (GET request from timesheet_dmsupload.php)
if (isset($_GET['uploadRefresh']) && $_GET['uploadRefresh'] == true)
{
	echo
		'<script type="text/javascript">',
		'onUploadRefresh();',
		'</script>';
}

// Show Info for 3,5 sec when timesheet was saved successfully
if (isset($_GET['saved']) && $_GET['saved'] == true)
{
	echo
		'<script type="text/javascript">',
		'showSaveSuccessAlert();',
		'</script>';
}
?>

</body>
</html>
