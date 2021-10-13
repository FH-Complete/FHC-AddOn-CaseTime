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
 			Manuela Thamer			<manuela.thamer@technikum-wien.at>
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
require_once('../../../include/sancho.inc.php');
require_once('../include/functions.inc.php');
require_once('../../../include/datum.class.php');

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
$hasVorgesetzten = true;

if ($mitarbeiter->getVorgesetzte($uid))
{
	$vorgesetzte_uid_arr = $mitarbeiter->vorgesetzte;

	if (!empty($vorgesetzte_uid_arr))
	{
		foreach ($vorgesetzte_uid_arr as $vorgesetzten_uid)
		{
			$benutzer = new Benutzer($vorgesetzten_uid);
			$vorgesetzte_full_name_arr []= $benutzer->getFullName();	// string full name of supervisor
		}
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

$isFuture = false;	// true if date selected is in the future
$isMissing_doc = false;	// true if upload documents are missing after check against absences

// Check if user has obligation to record times
$date_begin_zeitaufzeichnungspflicht = clone $date_golive;	// earliest date of mandatory time recording; default date of golive
$isZeitaufzeichnungspflichtig = false;
//var_dump($date_begin_zeitaufzeichnungspflicht);

// * only get active employee contracts to be checked for 'zeitaufzeichnungspflichtig'
$bisverwendung = new bisverwendung();
$now = new DateTime('today');
$bisverwendung->getVerwendung($uid);
$verwendung_arr = $bisverwendung->result;
$date_first_begin_verwendung = null;

//check if timesheet vorhanden und zeitaufzeichnungsplichtig
$timesheet = new Timesheet();
$timesheetVorhanden = $timesheet->checkIfUserHasTimesheet($uid);
$bisverwendung->getLastVerwendung($uid);

//neues Timesheet einfügen mit Beginndatum letzter zeitaufzeichnungspflichtiger Bisverwendung
if (!$timesheetVorhanden && $bisverwendung->zeitaufzeichnungspflichtig)
{
	$date_last_begin_verwendung = $timesheet->getLastVerwendungZapflicht($uid);
	$timesheet->insertTimeSheet($uid, $date_last_begin_verwendung);
}

foreach($verwendung_arr as $verwendung)
{
	if($verwendung->zeitaufzeichnungspflichtig)
	{
		$isZeitaufzeichnungspflichtig = true;
		// * if employee contract has begin date
		if (!is_null($verwendung->beginn))
		{
			$date_begin_verwendung = new DateTime('first day of '. $verwendung->beginn. ' midnight');

			// * init var for comparison
			if (is_null($date_first_begin_verwendung))
			{
				$date_first_begin_verwendung = clone $date_begin_verwendung;
			}

			// * compare each contract for the earliest begin date
			if ($date_first_begin_verwendung > $date_begin_verwendung)
			{
				$date_first_begin_verwendung = $date_begin_verwendung;

			}

			// * reset begin date of time recording if earlier begin date found (but never before golive)
			if ($date_begin_zeitaufzeichnungspflicht < $date_first_begin_verwendung)
			{
				$date_begin_zeitaufzeichnungspflicht = $date_first_begin_verwendung;
			}
		}
		// * if only one contract has no begin date, reset begin zeitaufzeichnungspflicht
		else
		{
			$date_begin_zeitaufzeichnungspflicht = clone $date_golive;
			break;
		}
	}
}

// *********************************	ALL TIMESHEETS
// Get all timesheets
$timesheet_arr = new Timesheet();
$timesheet_arr = $timesheet_arr->loadAll($uid);

$isAllowed_createTimesheet = true;	// false if not allowed to create new timesheet
$isBeforeGolive = false;	// true if date is before golive date
$isDisabled_by_missingTimesheet = false;	// true if there are missing timesheets (up to the date selected)
$isDisabled_by_formerUnsentTimesheet = false;	// true if there are former unsent timesheets (up to the date selected)
$notConfirmed_arr = array();	// date string array of all timesheets not confirmed
$isAllowed_confirmTimesheet = true;	// false if former timesheets are not confirmed by the supervisor
$isFirstEntry = false;	// true if user enters first time monthlist site
$date_last_timesheet = null;	// date of last existing timesheet
$date_first_dummy_ts = null;	// date of first missing timesheet

// If no timesheets existing (very first entry)
if (empty($timesheet_arr))
{
	$isFirstEntry = true;

	// start date of time recording for user obligated to record times
	if ($isZeitaufzeichnungspflichtig)
	{
		$date_first_dummy_ts = clone $date_begin_zeitaufzeichnungspflicht;
	}
	// start date of time recording for user recording times voluntary
	else
	{
		$date_first_dummy_ts = clone $date_actual;
		$date_first_dummy_ts->sub(new DateInterval('P1M'));	//allow create first timesheet for last month
	}
}
// If timesheets existing
else
{
	$date_last_timesheet = new DateTime('first day of '. $timesheet_arr[0]->datum. ' midnight');
	if ($date_last_timesheet < $date_actual)
	{
		$date_first_dummy_ts = clone $date_last_timesheet;
		if ($date_last_timesheet < $date_actual)
		{
			$date_first_dummy_ts->add(new DateInterval('P1M')); // +1 as last timesheet already exists
		}
	}
}

// Get data of existing timesheets
foreach ($timesheet_arr as $ts)
{
	$ts_date = new DateTime('first day of '. $ts->datum. ' midnight');
	$ts_isSent = (is_null($ts->abgeschicktamum)) ? false : true;

	// flag if at least one timesheet is NOT sent AND BEFORE the selected date
	if (!$ts_isSent)
	{
		if ($ts_date < $date_selected)
		{
			if(CheckisZeitaufzeichnungspflichtig($verwendung_arr, $ts->datum))
				$isDisabled_by_formerUnsentTimesheet = true;
		}
	}

	// collect all dates of timesheets that are not confirmed
	if (is_null($ts->genehmigtamum))
	{
		$notConfirmed_arr[] = array(
			'timesheet_id' => $ts->timesheet_id,
			'datum' => $ts_date
			);

		// flag if at least one former timesheet is not confirmed
		if ($date_selected > $ts_date)
		{
			$isAllowed_confirmTimesheet = false;
		}
	}
}

// Find the first timesheet not confirmed
if (!empty($notConfirmed_arr))
{
	foreach ($notConfirmed_arr as $key => $val)
	{
		if ($val['datum'] == min(array_column($notConfirmed_arr, 'datum')))
		{
		   $first_notConfirmed_key = $key;
		}
	}
	$notConfirmed_timesheet = $notConfirmed_arr[$first_notConfirmed_key];
}

// Create dummy timesheets if timesheets are missing
$missing_timesheet_arr = array();
if(!is_null($date_first_dummy_ts))
{
	$date_first_dummy_ts_cln = clone $date_first_dummy_ts;

	while ($date_first_dummy_ts_cln <= $date_actual)
	{
		$obj = new stdClass();
		$obj->timesheet_id = null;
		$obj->uid = $uid;
		$obj->datum = $date_first_dummy_ts_cln->format('Y-m-d');
		$obj->abgeschicktamum = null;
		$obj->genehmigtamum = null;
		$obj->genehmigtvon = null;

		$missing_timesheet_arr[] = $obj;

		$date_first_dummy_ts_cln->add(new DateInterval('P1M'));
	}
}

// Reverse missing dummy timesheets to merge after in correct order
$missing_timesheet_arr = array_reverse($missing_timesheet_arr);

// Merge missing dummy timesheets with timesheet array
$merged_timesheet_arr = array_merge($missing_timesheet_arr, $timesheet_arr);

function CheckisZeitaufzeichnungspflichtig($verwendung_arr, $datum)
{
	$ts_date = new DateTime('first day of '. $datum. ' midnight');
	$startdatum = $ts_date->format('Y-m-d');
	foreach ($verwendung_arr as $verwendung)
	{
		if ($datum < $verwendung->beginn)
		{
			$startdatum = $verwendung->beginn;
		}
	}

	$zp = false;
	foreach ($verwendung_arr as $bv)
	{
		if ($bv->inZeitaufzeichnungspflichtigPeriod($startdatum, $datum))
		{
			$zp = true;
		}
	}
	return $zp;
}

// Get data of merged timesheet array (missing and existing timesheets)
$timesheet_year_arr = array();	// unique timesheet years to set title in "Alle Monatslisten" - panel
$date_allow_new_ts = clone $date_actual;	// date of timesheet to be created
$zp = false;
foreach ($merged_timesheet_arr as $ts)
{
	$ts_date = new DateTime($ts->datum);
	$ts_year = $ts_date->format('Y');

	// get years (unique) for existing AND/OR missing timesheets
	if ($ts_year != end($timesheet_year_arr))
	{
		$timesheet_year_arr[] = $ts_year;
	}

	if (is_null($ts->timesheet_id))
	{
		$zp = CheckisZeitaufzeichnungspflichtig($verwendung_arr, $ts->datum);
		if($zp)
		{
			$date_allow_new_ts = clone $ts_date;
		}
	}
}

// Get the most earliest monthlist date of merged timesheet array
// This could be date of an existing or a dummy timesheet
if(count($merged_timesheet_arr)>0)
	$date_earliest_ts = new DateTime('first day of '. end($merged_timesheet_arr)->datum);
else
	$date_earliest_ts = new DateTime('2999-01-01'); // Currently not allowed to create Timesheet

// Flag if timesheet may not be created
if ($date_allow_new_ts < $date_selected ||
	$date_selected > $date_actual||
	$date_selected < $date_golive ||
	$date_selected < $date_earliest_ts)
{
	$isAllowed_createTimesheet = false;
}

if($isAllowed_createTimesheet)
{
	if(!CheckisZeitaufzeichnungspflichtig($verwendung_arr,$date_selected->format('Y-m-d')))
	{
		$isAllowed_createTimesheet = false;
	}
}

// Flag if timesheets are missing up to selected date
if ($date_allow_new_ts < $date_selected)
{
	$isDisabled_by_missingTimesheet = true;
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

if (isset($_GET['create']))
{
	if ($_GET['create'] === 'false')
	{
		$isAllowed_createTimesheet = false;
	}
}
// *********************************	ACTUAL TIMESHEET (of month/year selected)
$timesheet = new Timesheet($uid, $month, $year);
$timesheet_id = $timesheet->timesheet_id;
$timesheet->new = (is_null($timesheet_id) ? true : false);
$timesheet_cntrl_date = (!is_null($timesheet->kontrolliertamum)) ? new DateTime($timesheet->kontrolliertamum) : '';
$timesheet_cntrl_uid = (!is_null($timesheet->kontrolliertvon)) ? $timesheet->kontrolliertvon : '';
$timesheet_cntrl_remark = (!is_null($timesheet->kontroll_notizen)) ? $timesheet->kontroll_notizen : '';
$controller_name = '';

$isSent = (is_null($timesheet->abgeschicktamum) ? false : true);	// true if timesheet was already sent
$isConfirmed = (is_null($timesheet->genehmigtamum) ? false : true);	// true if timesheet was already confirmed
$isControlled = (is_null($timesheet->kontrolliertamum) ? false : true);	// true if timesheet was already controlled by personnel department
$isAllowed_sendTimesheet = true;	// boolean if timesheet is allowed to be sent

if (!empty($timesheet_cntrl_uid))
{
	$benutzer = new Benutzer($timesheet_cntrl_uid);
	$controller_name = $benutzer->vorname. ' '. $benutzer->nachname;
}

// Save timesheet, if timesheet is new and user is timely allowed to create first timesheet
if ($timesheet->new && $isAllowed_createTimesheet)
{
	// only if the selected date <= actual date
	if ($date_selected <= $date_actual)
	{
		if (!$isDisabled_by_missingTimesheet)
		{
			$timesheet->uid = $uid;
			$timesheet->datum = $date_selected->format('Y-m-t'); 	//saved with last day (=t)of month
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

// Flag if sending timesheet is allowed
if ($date_actual <= $date_selected)
{
	$isAllowed_sendTimesheet = false;
}

// *********************************	ALL ABSENT TIMES
$timesheet = new Timesheet();
$timesheet->getAllAbsentTimes($uid);
$absent_times_arr = $timesheet->result;


// *********************************	ACTUAL ABSENT TIMES (of month/year selected)
$actual_absent_times_arr = array();
foreach ($absent_times_arr as $absence)
{
	if ($absence->timesheet_id == $timesheet_id)
	{
		$actual_absent_times_arr[] = $absence;
	}
}

// Get absences that need to be checked against document upload
$cnt_ab = 0;	// counter arztbesuch
$cnt_beh = 0;	// counter behöre
$cnt_kst = 0;	// counter krankenstand
$cnt_pfl = 0;	// counter pflegeurlaub
foreach ($actual_absent_times_arr as $actual_absence)
{
	switch ($actual_absence->abwesenheit_kurzbz)
	{
		case 'Arztbesuch':
			// each Arztbesuch-absence needs to be attested
			$cnt_ab++;
			continue;
		case 'Behoerde':
			// each Behörden-absence needs to be attested
			$cnt_beh++;
			continue;
		case 'PflegeU':
			$von = new DateTime($actual_absence->von);
			$bis = new DateTime($actual_absence->bis);

			// Pflegeurlaub needs to be attested only from the 3rd day on
			if($von->diff($bis)->d >= 2)
				$cnt_pfl++;
			continue;
		case 'Krank':
			$von = new DateTime($actual_absence->von);
			$bis = new DateTime($actual_absence->bis);

			// Krankenstand needs to be attested only from the 3rd day on
			if($von->diff($bis)->d >= 2)
				$cnt_kst++;
			continue;
	}
}

// *********************************	ALL DOCUMENTS
// Load all Bestätigungen of user
$timesheet = new Timesheet();
$timesheet->loadAllBestaetigungen_byUser($uid);
$all_user_bestaetigungen = $timesheet->result;

// collect all Bestätigungen of actual monthlist in an extra array
$all_actualMonth_bestaetigungen = array();
foreach ($all_user_bestaetigungen as $bestaetigung)
{
	$date_bestaetigung = new DateTime($bestaetigung->datum);
	$date_bestaetigung->modify('first day of this month midnight');

	if($date_selected == $date_bestaetigung)
		$all_actualMonth_bestaetigungen[] = $bestaetigung;
}

// Get document amount that need to be checked against absences
$cnt_ab_doc = 0;	// counter arztbesuch bestätigungen
$cnt_beh_doc = 0;	// counter behörde bestätigungen
$cnt_kst_doc = 0;	// counter krankenstand bestätigungen
$cnt_pfl_doc = 0;	// counter pflegeurlaub bestätigungen
foreach ($all_actualMonth_bestaetigungen as $actual_bestaetigung)
{
	switch ($actual_bestaetigung->dokument_kurzbz)
	{
		case 'bst_arzt':
			$cnt_ab_doc++;
			continue;
		case 'bst_bhrd':
			$cnt_beh_doc++;
			continue;
		case 'bst_pfur':
			$cnt_pfl_doc++;
			continue;
		case 'bst_krnk':
			$cnt_kst_doc++;
			continue;
	}
}

// *********************************	CASETIME CHECKS
// Check if user made any changes in Zeitaufzeichnung today concerning the month period of selected date
$hasCaseTimeChanges_today = false;	// true if has inserted/updated Zeitaufzeichnung today
$isSyncedWithCaseTime_today = true;	// false if has deleted Zeitaufzeichnung/Zeitsperre today

// * no check if selected month is actual month as sending monthsheet is not allowed anyway
if ($date_selected != $date_actual)
{
	$timesheet = new Timesheet();
	$hasCaseTimeChanges_today = $timesheet->hasAbsentTimes($uid, $date_selected);

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

// *********************************	EMAIL SENDING (and document check)
$isCaseTimeError = false;	// boolean to flag casetime server errors which should be eliminated before timesheet sending
if (isset($_POST['submitTimesheet']))
{
	// Check if there are casetime server errors that are defined as blocking errors
	$isCaseTimeError = checkCaseTimeErrors($uid, $month, $year);

	// Check if documents according have been uploaded to absences
	$missing_docs = "<ul>";
	if ($cnt_ab > $cnt_ab_doc)
	{
		$isMissing_doc = true;
		$missing_docs .= "<li>". ($cnt_ab - $cnt_ab_doc). " Arztbesuch(e)</li>";
	}
	if ($cnt_beh > $cnt_beh_doc)
	{
		$isMissing_doc = true;
		$missing_docs .= "<li>". ($cnt_beh - $cnt_beh_doc). " Behördenbesuch(e)</li>";
	}
	// if one or more krankenstände there need to be at least one krankenstandsbestätigung
	if ($cnt_kst >= 1 && $cnt_kst_doc == 0)
	{
		$isMissing_doc = true;
		$missing_docs .= "<li>mindestens einen Krankenstand</li>";
	}
	if ($cnt_pfl >= 1 && $cnt_pfl_doc == 0)
	{
		$isMissing_doc = true;
		$missing_docs .= "<li>mindestens einen Pflegeurlaub</li>";
	}

	$missing_docs .= "</ul>";

	// if document $ casetime server error check ok, prepare for email sending
	if (!$isMissing_doc && !$isCaseTimeError)
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
	foreach ($merged_timesheet_arr as $ts)
	{
		$ts_date = new DateTime($ts->datum);
		if ($ts_date >= $date_selected)
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

// *********************************	CASETIME SERVER ERROR HANDLING
// checks if there are casetime server errors that are defined as blocking errors
function checkCaseTimeErrors($uid, $month, $year)
{
	$isCaseTimeError = false;
	$casetime_error_arr = getCaseTimeErrors($uid);
	$blocking_error_arr = unserialize(CASETIME_BLOCKING_ERR);

	foreach ($casetime_error_arr as $casetime_error)
	{
		$casetime_error_date = new DateTime($casetime_error[0]);
		$casetime_error_month = $casetime_error_date->format('m');
		$casetime_error_year = $casetime_error_date->format('Y');

		// if casetime error date matches the selected timesheet date OR one month before
		if ($casetime_error_year == $year &&
			(
			$casetime_error_month == $month ||
			$casetime_error_month == ($month-1))
			)
		{
			// check if casetime error is a blocking error
			foreach($blocking_error_arr as $blocking_err)
			{
				if (strpos($casetime_error[1], $blocking_err) !== false)
				{
					$isCaseTimeError = true;
				}
			}
		}
	}
	return $isCaseTimeError;
}

?>

<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<link rel="stylesheet" type="text/css" href="../../../vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
	<link href="../../../vendor/components/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css"/>
	<script type="text/javascript" src="../../../vendor/components/jquery/jquery.min.js"></script>
	<script type="text/javascript" src="../../../vendor/twbs/bootstrap/dist/js/bootstrap.min.js"></script>
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
		$("#formTimesheetConfirmation").submit(function(e)
		{
			var checked = [];
			$("input[name='checkbox_overtime[]']").each(function()
			{
				checked.push(this.checked);
			});

			$("input[name='checkbox_overtime_arr']").val(checked);
		});
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
	<?php if (!$isFuture && ($isVorgesetzter_indirekt  && !$isVorgesetzter)): ?>
	<div class="panel panel-default">
		<div class="panel-body text-danger">
			<i class="fa fa-info-circle fa-lg" aria-hidden="true"></i>
			<b>Sie sind INDIREKT VORGESETZT. </b>Sie können Monatlisten einsehen, aber nicht genehmigen oder retournieren.
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

		<!--panel: UPLOAD documents-->
		<div class="row panel-top-cstm" style="<?php echo ($isConfirmed || $isFuture || $isDisabled_by_missingTimesheet || !$isAllowed_createTimesheet) ? 'display: none;' : '' ?>">
			<div class="panel-body col-xs-8">
				<b>Upload von Dokumenten</b><br><br>

				<!--counter for displaying absence text only once-->
				<?php $counter = 0; ?>

				<!--loop through absent times-->
				<?php foreach ($actual_absent_times_arr as $absence): ?>

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
				<a role="button" <?php echo ($isSent || ($isVorgesetzter && !$isTimesheetManager) || ($isPersonal && !$isTimesheetManager) || ($isVorgesetzter_indirekt && !$isTimesheetManager)) ? 'disabled' : ''; ?>
				   <?php echo ($isSent && !$isVorgesetzter && !$isPersonal && !$isVorgesetzter_indirekt && !$isTimesheetManager) ? 'data-toggle="tooltip" title="Information zur Sperre weiter unten in der Messagebox."' : ''; ?>
				   <?php echo ($isSent && $isTimesheetManager) ? 'data-toggle="tooltip" title="Kein Upload mehr möglich da die Monatsliste bereits abgeschickt worden ist."' : ''; ?>
				   class="btn btn-default pull-right" id="uploadButton"
				   href="<?php echo APP_ROOT. 'addons/casetime/cis/timesheet_dmsupload.php?timesheet_id='. $timesheet_id ?>"
				   onclick="FensterOeffnen(this.href); return false;">Dokumente hochladen</a><br><br><br>

				<!--if there are existing bestaetigungen in actual month -> display table and all bestaetigungen-->
				<table class="table table-condensed pull-right" <?php echo (empty($all_actualMonth_bestaetigungen)) ? 'style="display: none;"' : '' ?> id="tbl_all_actualMonth_bestaetigungen">
				<?php foreach($all_actualMonth_bestaetigungen as $bestaetigung): ?>
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
					<?php foreach ($actual_absent_times_arr as $overtime): ?>

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
		<div class="row panel-top-cstm" style="<?php echo ($isConfirmed || $isFuture || $isDisabled_by_missingTimesheet || !$isAllowed_createTimesheet) ? 'display: none;' : '' ?>">
			<div class="panel-body col-xs-8">
				<b>Monatsliste abschließen</b><br><br>
				Wenn  Sie alle erforderlichen Dokumente hochgeladen haben, verschicken Sie bis zum 03. des Folgemonats Ihre Monatsliste zur Freigabe an Ihre Vorgesetzte / Ihren Vorgesetzten.<br>
				Nach dem Verschicken kann diese <b>nicht</b> mehr bearbeitet werden.
			</div>
			<form method="POST" action="">
				<div class="panel-body col-xs-4"><br>
					<button type="submit" <?php echo ($isSent || $isDisabled_by_formerUnsentTimesheet || !$isAllowed_sendTimesheet || $isVorgesetzter || $isPersonal || !$hasVorgesetzten || $hasCaseTimeChanges_today || !$isSyncedWithCaseTime_today || $isVorgesetzter_indirekt) ? 'disabled data-toggle="tooltip" ' : '';
						echo (($isSent || $isDisabled_by_formerUnsentTimesheet || !$isAllowed_sendTimesheet || !$isSyncedWithCaseTime_today) && !$isVorgesetzter && !$isPersonal && !$isVorgesetzter_indirekt) ? 'title="Information zur Sperre weiter unten in der Messagebox."' : '' ?>
						name="submitTimesheet" class="btn btn-default pull-right"
						onclick="return confirm('Wollen Sie die Monatsliste für <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year ?>\njetzt an <?php echo implode(' und ', $vorgesetzte_full_name_arr) ?> verschicken?');">Monatsliste verschicken</button>
				</div>
			</form>
		</div>
	</div><!--/.panel-->
	<br><br>

	<!--************************************		VIEW for supervisors, personnel department and timesheet manager-->

	<?php if ($isVorgesetzter || $isPersonal || $isTimesheetManager): ?>
	<div class="panel panel-default" style="padding-bottom: 20px;">
		<div class="panel-heading">
			<span class="panel-title h2">Vorgesetztensicht</span>
		</div>

		<!--panel: CONFIRM timesheet-->
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
					<button type="submit" <?php echo ((!$isSent && !$isTimesheetManager) || $isConfirmed || !$isAllowed_confirmTimesheet || ($isVorgesetzter_indirekt && !$isVorgesetzter)) ? 'disabled data-toggle="tooltip" title="Information zur Sperre weiter unten in der Messagebox."' : '' ?>
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
						<?php if ((!$isSent || $isConfirmed || !$isAllowed_confirmTimesheet) && ($isVorgesetzter || $isPersonal || $isTimesheetManager)): ?>
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
		<!-- IF first entry AND obliged to record times AND timesheets are missing before actual date -->
		<?php if ($isFirstEntry && $isZeitaufzeichnungspflichtig && !$isTimesheetManager): ?>
			<div class="alert alert-danger alert-dismissible text-center" role="alert">
				<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<b>Sie sind ab <?php echo $monatsname[$sprache_index][($date_begin_zeitaufzeichnungspflicht->format('n')) - 1]. ' '. $date_begin_zeitaufzeichnungspflicht->format('Y'); ?> zeitaufzeichnungspflichtig.</b><br><br>
				Monatslisten müssen chronologisch erstellt und an Vorgesetzte gesendet werden.<br>
				<a href="<?php echo $_SERVER['PHP_SELF']?>?year=<?php echo $date_begin_zeitaufzeichnungspflicht->format('Y') ?>&month=<?php echo $date_begin_zeitaufzeichnungspflicht->format('m')?>"
				   class="text-danger"><b>Monatsliste <?php echo $monatsname[$sprache_index][$date_begin_zeitaufzeichnungspflicht->format('n') - 1]. ' '. $date_begin_zeitaufzeichnungspflicht->format('Y') ?> jetzt erstellen</b></a>
		   </div>
		<?php endif; ?>

		<!-- Info WHEN new timesheet was created and is NOT disabled by missing timesheets -->
		<?php if (!$isDisabled_by_missingTimesheet): ?>
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
		<?php if (!$isAllowed_sendTimesheet && !$isDisabled_by_missingTimesheet && $isAllowed_createTimesheet && !$isFuture && $date_last_timesheet == $date_actual): ?>
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
		<?php if ($isDisabled_by_missingTimesheet && !$isConfirmed && !$isFuture && !is_null($date_last_timesheet)): ?>
		<div class="alert alert-danger alert-dismissible text-center" role="alert">
			<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<b>Für <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year ?> kann noch keine Monatsliste angelegt werden!</b><br><br>
			Monatslisten müssen chronologisch erstellt und an Vorgesetzte gesendet werden.<br>
			Ihre letzte Monatsliste haben sie für <?php echo $monatsname[$sprache_index][($date_last_timesheet->format('n')) - 1]. ' '. $date_last_timesheet->format('Y'); ?> erstellt.<br><br>
			<a href="<?php echo $_SERVER['PHP_SELF']?>?year=<?php echo $date_last_timesheet->format('Y') ?>&month=<?php echo ($date_last_timesheet->format('m') + 1)?>"
			   class="text-danger"><b>Monatsliste <?php echo $monatsname[$sprache_index][$date_last_timesheet->format('n')]. ' '. ($date_last_timesheet->format('Y')) ?> jetzt erstellen</b></a>
		</div>
		<?php endif; ?>

		<!-- IF former timesheets were not sent -->
		<?php if ($isDisabled_by_formerUnsentTimesheet): ?>
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

		<!-- IF not allowed to create timesheet (only checked when trying to create FIRST timesheet 2 or more months before actual monthyear) -->
		<?php if (!$isAllowed_createTimesheet && !$isZeitaufzeichnungspflichtig): ?>
		<div class="alert alert-warning alert-dismissible text-center" role="alert">
			<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<?php if ($isBeforeGolive): ?>
			<b>Monatslisten dürfen NICHT vor dem <?php echo $monatsname[$sprache_index][$date_golive->format('n') - 1]. ' '. $date_golive->format('Y') ?> liegen!</b>
			<?php else: ?>
			<b>Für <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year ?> kann noch keine Monatsliste angelegt werden!</b><br><br>
			Monatslisten können nur für abgeschlossene Monate an den Vorgesetzten gesendet werden.<br>
			<a href="<?php echo $_SERVER['PHP_SELF']?>?year=<?php echo $year ?>&month=<?php echo ($month - 1)?>"
			   class="text-warning"><b>Erste Monatsliste für <?php echo $monatsname[$sprache_index][$month - 2]. ' '. $year ?> jetzt erstellen</b></a>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<!-- IF there are casetime server errors that are defined as blocking errors -->
		<?php if ($isCaseTimeError && !$isDisabled_by_formerUnsentTimesheet && $isSyncedWithCaseTime_today && !$hasCaseTimeChanges_today): ?>
		<div class="alert alert-danger alert-dismissible text-center" role="alert">
			<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<b>Die Monatsliste für <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year ?> konnte nicht versendet werden!</b><br><br>
			Die Zeiterfassung für <?php echo $monatsname[$sprache_index][$month - 1]. ' '. $year ?> oder den Monat davor ist nicht vollständig oder inkorrekt.<br>
			Bitte überarbeiten Sie erst Ihre Zeiterfassung für diesen Zeitraum und versenden Sie danach erneut Ihre Monatsliste.<br><br>
			<a href="<?php echo APP_ROOT. 'cis/private/tools/zeitaufzeichnung.php' ?>" class="text-danger"><b>Zeitaufzeichnung jetzt bearbeiten</b></a>
		</div>
		<?php endif; ?>

		<!-- IF document uploads are missing (after check against absences) -->
		<?php if ($isMissing_doc): ?>
		<div class="alert alert-danger alert-dismissible" role="alert">
			<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<div class="col-xs-offset-4">
				<b>Die Monatsliste konnte noch nicht versendet werden!</b>&nbsp;&nbsp;
				<a role="button" data-toggle="modal" data-target="#modalSendMonatsliste"><i class="alert-danger fa fa-question-circle-o fa-lg" aria-hidden="true"></i></a><br>
				Es fehlen noch Bestätigungen für:
				<?php echo $missing_docs ?>
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
		<?php if (!$isDisabled_by_missingTimesheet): ?>
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
			Die weitest zurückliegende Monatsliste, die noch nicht genehmigt wurde, ist vom <?php echo $monatsname[$sprache_index][$notConfirmed_timesheet['datum']->format('n') - 1]. ' '. $notConfirmed_timesheet['datum']->format('Y') ?>.<br><br>
			<a role="button" href="<?php echo $_SERVER['PHP_SELF']?>?timesheet_id=<?php echo $notConfirmed_timesheet['timesheet_id'] ?>" class="text-danger"><b>Monatsliste <?php echo $monatsname[$sprache_index][$notConfirmed_timesheet['datum']->format('n') - 1]. ' '. $notConfirmed_timesheet['datum']->format('Y') ?> jetzt genehmigen</b></a>
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
			<b><?php echo $full_name. ' ist ab '. $monatsname[$sprache_index][($date_begin_zeitaufzeichnungspflicht->format('n')) - 1]. ' '. $date_begin_zeitaufzeichnungspflicht->format('Y'); ?> zeitaufzeichnungspflichtig.</b><br><br>
			Monatslisten müssen chronologisch erstellt und an Vorgesetzte gesendet werden.<br>
			<a href="<?php echo $_SERVER['PHP_SELF']?>?year=<?php echo $date_begin_zeitaufzeichnungspflicht->format('Y') ?>&month=<?php echo $date_begin_zeitaufzeichnungspflicht->format('m')?>&employee_uid=<?php echo $uid ?>&create=true""
			   class="text-danger"><b>Monatsliste <?php echo $monatsname[$sprache_index][$date_begin_zeitaufzeichnungspflicht->format('n') - 1]. ' '. $date_begin_zeitaufzeichnungspflicht->format('Y') ?> jetzt erstellen</b></a>
		</div>
		<?php endif; ?>

	<?php endif; ?><!-- /.end alert conditions for timesheet managers -->



	<!--************************************	ALL TIMESHEETS - TABLE -->

	<h4>Alle Monatslisten</h4><br>

	<!--if timesheets are present, show panel with all timesheets-->
	<?php if (!empty($merged_timesheet_arr) || !empty($missing_timesheet_arr)): ?>
		<div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">

		<!--year counter: timesheets of the first (=actual) year will be fully displayed; other years are collapsed-->
		<?php $year_cnt = 1; ?>

		<!--loop through years-->
		<div id="panel_all_user_bestaetigungen">
		<?php foreach ($timesheet_year_arr as $year): ?>
			<div class="text-center" role="tab" id="heading<?php echo $year_cnt ?>">
				<h4 class="panel-title">
					<a role="button" data-toggle="collapse" data-parent="#accordion" href="#collapse<?php echo $year_cnt ?>" aria-expanded="true" aria-controls="collapse<?php echo $year_cnt ?>">

						<!--display year as title in the panel-->
						<h3><?php echo $year ?></h3>
					</a>
				</h4>
			</div>
			<div id="collapse<?php echo $year_cnt ?>" class="panel-collapse collapse <?php echo ($year_cnt == 1 || $year_cnt == 2) ? 'in' : '' ?>" role="tabpanel" aria-labelledby="heading<?php echo $year_cnt ?>">
				<div class="panel-body panel-body-alleMonatslisten">
					<table class="table table-bordered table-condensed">
						<tr>
							<th>Monatsliste</th>
							<th>Abwesenheit</th>
							<th>Dokumente</th>
							<th>Abgeschickt am</th>
							<th>Genehmigt</th>
						</tr>
						<!--loop through all timesheets-->
						<?php foreach ($merged_timesheet_arr as $ts): ?>
							<?php $ts_date = new DateTime('first day of '. $ts->datum. ' midnight'); ?>
                            <?php
								$zp = CheckisZeitaufzeichnungspflichtig($verwendung_arr, $ts->datum);
                            ?>
							<?php
							//if timesheet is in the looped year, then show timesheet information in this table
							if ($ts_date->format('Y') == $year):
							?>
							<tr>
								<!--Monatsliste: link to monthlist-->
								<!--URL to existing timesheets-->
								<?php if (($ts_date < $date_allow_new_ts || $ts_date == $date_last_timesheet) && $zp): ?>
									<td>
										<!--for supervisors, personnel department & timesheet manager-->
										<?php if ($isVorgesetzter || $isPersonal || $isVorgesetzter_indirekt || $isTimesheetManager): ?>
											<a href="<?php echo $_SERVER['PHP_SELF']?>?timesheet_id=<?php echo !is_null($ts->timesheet_id) ? $ts->timesheet_id : '' ?>">
												<?php echo $monatsname[$sprache_index][$ts_date->format('n') - 1]. ' '. $ts_date->format('Y') ?>
											</a>

										<!--for employees-->
										<?php else: ?>
											<a href="<?php echo $_SERVER['PHP_SELF']?>?year=<?php echo $ts_date->format('Y') ?>&month=<?php echo $ts_date->format('m')?>">
												<?php echo $monatsname[$sprache_index][$ts_date->format('n') - 1]. ' '. $ts_date->format('Y') ?>
											</a>
										<?php endif; ?>
										<?php if (isset($ts->kontroll_notizen) && !is_null($ts->kontroll_notizen)): ?>
											<span class="label label-warning pull-right text-uppercase" style="margin-left: 5px;">Notiz</span>
										<?php endif; ?>
									</td>

								<!--URL for missing timesheet to be created-->
								<?php elseif($ts_date == $date_allow_new_ts && is_null($ts->timesheet_id) && $zp): ?>
									<td>
										<!--supervisors & personnel department: text only-->
										<?php if (!$isTimesheetManager && ($isVorgesetzter || $isPersonal || $isVorgesetzter_indirekt)): ?>
											<?php echo $monatsname[$sprache_index][$ts_date->format('n') - 1]. ' '. $ts_date->format('Y') ?>
											<span class="label pull-right text-uppercase" style="background-color: lightgrey;">fehlt</span>

										<!--for timesheet managers (allowed to create timesheet, but needs to be executed differently as for employees to retrieve all timesheet manager data again)-->
										<?php elseif($isTimesheetManager): ?>
											<span class="label pull-right text-uppercase" style="background-color: lightgrey; margin-left: 5px;">fehlt</span>
											<a href="<?php echo $_SERVER['PHP_SELF']?>?year=<?php echo $ts_date->format('Y') ?>&month=<?php echo $ts_date->format('m') ?>&employee_uid=<?php echo $uid ?>&create=true">
											<?php echo $monatsname[$sprache_index][$ts_date->format('n') - 1]. ' '. $ts_date->format('Y') ?>
												&nbsp;<i class="fa fa-plus-square-o" aria-hidden="true"></i>
											</a>

										<!--for employees-->
										<?php else: ?>
											<span class="label pull-right text-uppercase" style="background-color: lightgrey; margin-left: 5px;">fehlt</span>
											<a href="<?php echo $_SERVER['PHP_SELF']?>?year=<?php echo $ts_date->format('Y') ?>&month=<?php echo $ts_date->format('m')?>">
											<?php echo $monatsname[$sprache_index][$ts_date->format('n') - 1]. ' '. $ts_date->format('Y') ?>
												&nbsp;<i class="fa fa-plus-square-o" aria-hidden="true"></i>
											</a>
										<?php endif; ?>
										<?php if (isset($ts->kontroll_notizen) && !is_null($ts->kontroll_notizen)): ?>
											<span class="label label-warning pull-right text-uppercase" style="margin-left: 5px;">Notiz</span>
										<?php endif; ?>
									</td>

								<!--No URL, only text for all other missing timesheets-->
								<?php elseif (!$zp): ?>
									<td><?php echo $monatsname[$sprache_index][$ts_date->format('n') - 1]. ' '. $ts_date->format('Y') ?>
										<span class="label pull-right text-uppercase" style="background-color: lightgrey;">nicht zeitaufzeichnungspflichtig</span>
									</td>


                                <?php elseif ($ts_date > $date_allow_new_ts): ?>
                                    <td><?php echo $monatsname[$sprache_index][$ts_date->format('n') - 1]. ' '. $ts_date->format('Y') ?>
                                        <span class="label pull-right text-uppercase" style="background-color: lightgrey;">fehlt</span>
                                        <?php if (isset($ts->kontroll_notizen) && !is_null($ts->kontroll_notizen)): ?>
                                            <span class="label label-warning pull-right text-uppercase" style="margin-left: 5px;">Notiz</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>

								<!--Abwesenheit: absence reasons & times-->
								<td>
								<?php foreach ($absent_times_arr as $absence): ?>
									<?php if ($ts->timesheet_id == $absence->timesheet_id): ?>
										<?php echo date_format(date_create($absence->von), 'd.m.Y'). ' - '. date_format(date_create($absence->bis), 'd.m.Y'). ': '. $absence->abwesenheitsgrund. "<br>" ?>
									<?php endif; ?>
								<?php endforeach; ?>
								</td>

								<!--Dokumente: link to documents-->
								<td>
								<?php foreach ($all_user_bestaetigungen as $bestaetigung): ?>
									<?php $date_bestaetigung = new DateTime($bestaetigung->datum); ?>
									<?php if($ts_date->format('m-Y') == $date_bestaetigung->format('m-Y')): ?>
										<a href="<?php echo APP_ROOT. 'addons/casetime/cis/timesheet_dmsdownload.php?dms_id='. $bestaetigung->dms_id ?>"
										   target="_blank"><?php echo $db->convert_html_chars($bestaetigung->name) ?></a><br>
									<?php endif; ?>
								<?php endforeach; ?>
								</td>

								<!--Abgeschickt am: sending date-->
								<?php if (!is_null($ts->abgeschicktamum)): ?>
									<?php $ts_date = new DateTime($ts->abgeschicktamum); ?>
									<td><?php echo $ts_date->format('d.m.Y') ?></td>
								<?php elseif($zp): ?>
									<td>Nicht abgeschickt</td>
								<?php else: ?>
                                    <td>Nicht Zeitaufzeichnungspflichtig</td>
								<?php endif; ?>

								<!--Genehmigt: confirmation status-->
								<?php if (is_null($ts->genehmigtamum) & $zp): ?>
									<td class='text-center' data-toggle="tooltip" title="Muss noch von Ihrem Vorgesetzten genehmigt worden."><img src="../../../skin/images/ampel_gelb.png" ></td>
								<?php elseif (!$zp): ?>
                                    <td></td>
                                <?php else: ?>
									<?php
									$ts_date_genehmigt = new DateTime($ts->genehmigtamum);
									$genehmigtvon = new Benutzer($ts->genehmigtvon);
									$genehmigtvon = $genehmigtvon->getFullName();
									?>
									<td class='text-center' data-toggle="tooltip" title="Genehmigt am <?php echo $ts_date_genehmigt->format('d.m.Y') ?> von <?php echo $genehmigtvon ?>"><img src="../../../skin/images/ampel_gruen.png" ></td>
								<?php endif; ?>
							</tr>
							<?php endif; ?>
						<?php endforeach; ?>
					</table>
			  </div>
			</div>
		<?php $year_cnt++; ?>
		<?php endforeach; ?>
		</div>
		</div>

	<!--if no timesheets existing yet, show panel with info, that soon timesheets will be pinned up-->
	<?php else: ?>
		<div class="panel panel-default">
			<div class="panel-body text-center"><br>
				Sobald Sie Ihre erste Monatsliste erstellt haben, wird diese und alle weiteren hier aufgelistet werden.<br><br>
			</div>
		</div>
	<?php endif; ?>
</div><!--/.col-xs-8-->

<!--************************************	RIGHT CONTAINER -->
<div class="col-xs-4">
	<!--IF timesheet is sent AND confirmed-->
	<?php if ($isSent && $isConfirmed): ?>
		<div class="panel panel-success">
			<div class="panel-heading text-center text-success">Status: <b>GENEHMIGT</b></div>
		</div>
	<?php elseif (!$zp): ?>
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
