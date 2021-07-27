<?php
/**
 * FH-Complete
 *
 * @package		FHC-API
 * @author		FHC-Team
 * @copyright	Copyright (c) 2016, fhcomplete.org
 * @license		GPLv3
 * @link		http://fhcomplete.org
 * @since		Version 1.0
 * @filesource
 *
 * Cronjobs to be run for closing timesheets, that were marked as 'vorzeitig_abgeschickt'.
 */
// ------------------------------------------------------------------------

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
require_once('../../../include/mitarbeiter.class.php');
require_once('../../../include/sancho.inc.php');
require_once('../include/functions.inc.php');


$uid = get_uid();
$db = new basis_db();
$sprache_obj = new sprache();
$sprache = getSprache();	// users language
$sprache_index = $sprache_obj->getIndexFromSprache($sprache);	// users language index (for globals.inc.php)

$date_last_month = new DateTime('first day of last month midnight');

$cnt_timesheetsVersendet = 0;
$cnt_timesheetsNichtVersendet = 0;
$cnt_timesheetsError = 0;
$uid_timesheetsError_arr = array();


// Wenn das Script ueber die Kommandozeile aufgerufen wird, erfolgt keine Authentifizierung
if (php_sapi_name() != 'cli')
{
	$rechte = new benutzerberechtigung();
	$rechte->getBerechtigungen($uid);
	
	if(!$rechte->isBerechtigt('admin'))
	{
		exit($rechte->errormsg);
	}
}


// Get all timesheets of last month
// WHERE 'vorzeitig_abgeschickt' = TRUE
// AND abgeschicktamum IS NULL
$timesheet = new Timesheet();
$timesheets_vorzeitigAbgeschickt_arr = $timesheet->loadAllFromLastMonthVorzeitigAbgeschickt();

foreach ($timesheets_vorzeitigAbgeschickt_arr as $timesheet_vorzeitigAbgeschickt)
{
	// Get name of Mitarbeiter
	$benutzer = new Benutzer($timesheet_vorzeitigAbgeschickt->uid);
	$full_name = $benutzer->getFullName();	// string full name of user
	$first_name = $benutzer->vorname;
	
	// Check for blocking casetime errors
	$hasCaseTimeError = $timesheet->hasCaseTimeError(
		$timesheet_vorzeitigAbgeschickt->uid,
		$date_last_month->format('m'),
		$date_last_month->format('Y'));
	
	// Check for missing Bestaetigungen
	$hasMissingBestaetigung = $timesheet->hasMissingBestaetigung(
		$timesheet_vorzeitigAbgeschickt->uid,
		$timesheet_vorzeitigAbgeschickt->timesheet_id
	);
	
	// Check if Casetime inserts / updated were made today
	$timesheet = new Timesheet();
	$hasCaseTimeChanges_today = $timesheet->hasNewOrChangedTimesToday(
		$timesheet_vorzeitigAbgeschickt->uid,
		$date_last_month
	);
	
	// Check if times were deleted today
	$timesheet = new Timesheet();
	$isSyncedWithCaseTime_today = $timesheet->hasDeletedTimes(
		$timesheet_vorzeitigAbgeschickt->uid,
		$date_last_month
	);
	
	// If no casetime error
	// and no Bestaetigung is missing
	// and no Casetime Inserts or Changes were made today
	// and is synced with Casetime
	if (!$hasCaseTimeError
		&& !$hasMissingBestaetigung
		&& !$hasCaseTimeChanges_today
		&& $isSyncedWithCaseTime_today)
	{
		// Get Vorgesetzte
		$mitarbeiter = new Mitarbeiter();
		$vorgesetzte_uid_arr = array();	// array with uid of one or more supervisors
		
		if ($mitarbeiter->getVorgesetzte($timesheet_vorzeitigAbgeschickt->uid))
		{
			$vorgesetzte_uid_arr = $mitarbeiter->vorgesetzte;
		}
		
		// Send Mail to Vorgesetzte
		foreach ($vorgesetzte_uid_arr as $vorgesetzten_uid)
		{
			$header_img = 'sancho_header_confirm_timesheet.jpg';
			$benutzer = new Benutzer($vorgesetzten_uid);
			$vorgesetzter_vorname = $benutzer->vorname;
			$to = $vorgesetzten_uid. '@'. DOMAIN;
			
			$subject =
				'Monatsliste '. $monatsname[$sprache_index][$date_last_month->format('m') - 1]. ' '.
				$date_last_month->format('Y'). ' von '. $full_name;
			
			// Set mail template fields
			$fields = array(
				'firstName' => $vorgesetzter_vorname,
				'employee' => $first_name,
				'date_monthlist' => $monatsname[$sprache_index][$date_last_month->format('m') - 1]. " ". $date_last_month->format('Y'),
				'link' => APP_ROOT. "addons/casetime/cis/timesheet.php?timesheet_id=". $timesheet_vorzeitigAbgeschickt->timesheet_id
			);
			
			// Send Sancho mail to Vorgesetzte
			if (sendSanchoMail('Sancho_Content_confirmTimesheet', $fields, $to, $subject, $header_img))
			{
				$send_date = new DateTime();
				$timesheet = new Timesheet();
				$timesheet->timesheet_id = $timesheet_vorzeitigAbgeschickt->timesheet_id;
				$timesheet->abgeschicktamum = $send_date->format('Y-m-d H:i:s');
				
				// Save abgeschicktamum
				$timesheet->save(true);
			
				$cnt_timesheetsVersendet++;
			}
			else
			{
				$cnt_timesheetsError++;
				$uid_timesheetsError[]= $timesheet->uid;
			}
		}
	}
	// Elseif casetime error exist or at least one Bestaetigung is missing
	elseif ($hasCaseTimeError || $hasMissingBestaetigung)
	{
		// Reset vorzeitig_abgeschickt to FALSE
		$timesheet = new Timesheet();
		$timesheet->resetVorzeitigAbgeschickt($timesheet_vorzeitigAbgeschickt->timesheet_id);
		
		// Send mail to Mitarbeiter and cc to HR
		$to = $timesheet_vorzeitigAbgeschickt->uid. '@'. DOMAIN;
		$cc = defined('CASETIME_TIMESHEET_HR_EMAIL') && !empty(CASETIME_TIMESHEET_HR_EMAIL)
			? CASETIME_TIMESHEET_HR_EMAIL
			: '';
		
		$subject = 'Monatsliste '. $monatsname[$sprache_index][$date_last_month->format('m') - 1]. ' '.
			$date_last_month->format('Y'). ' konnte nicht abgeschickt werden';
		
		// Set mail template fields
		$fields = array(
			'firstName' => $first_name,
			'date_monthlist' => $monatsname[$sprache_index][$date_last_month->format('m') - 1]. " ". $date_last_month->format('Y'),
			'link_monthlist' => APP_ROOT. "addons/casetime/cis/timesheet.php?timesheet_id=". $timesheet_vorzeitigAbgeschickt->timesheet_id,
			'link_zeitaufzeichnung' => APP_ROOT. "cis/private/tools/zeitaufzeichnung.php"
		);
		
		// Send Sancho mail to Mitarbeiter and cc to HR informing the timesheet needs to be checked
		if (sendSanchoMail('TimesheetNichtAbgeschickt',
			$fields,
			$to,
			$subject,
			DEFAULT_SANCHO_HEADER_IMG, DEFAULT_SANCHO_FOOTER_IMG,
			'',
			$cc)
		)
		{
			$cnt_timesheetsNichtVersendet++;
		}
		else
		{
			$cnt_timesheetsError++;
			$uid_timesheetsError[]= $timesheet->uid;
		}
	}

}

$nl = "\n";
echo $nl. "Fertig.";
echo $nl. "Anzahl Monatslisten an Vorgesetzte abgeschickt: ". $cnt_timesheetsVersendet;
echo $nl. "Anzahl Monatslisten, wegen CasetimeError/fehlende Dokumente, nicht abgeschickt: ". $cnt_timesheetsNichtVersendet;
echo $nl. "Anzahl Monatslisten fehlerhaft: ". $cnt_timesheetsError;

if ($cnt_timesheetsError > 0)
{
	echo $nl. "Anzahl Monatslisten fehlerhaft bei folgenden UIDs: ";
	echo "<br>".'<pre>'. print_r($uid_timesheetsError_arr, -1). '</pre>';
}



