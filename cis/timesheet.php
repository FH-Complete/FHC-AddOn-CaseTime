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
 */
require_once('../../../config/cis.config.inc.php');
require_once('../include/timesheet.class.php');
require_once('../../../include/benutzer.class.php');
require_once('../../../include/phrasen.class.php');
require_once('../../../include/sprache.class.php');
require_once('../../../include/globals.inc.php');
require_once('../../../include/dms.class.php');
require_once('../../../include/mitarbeiter.class.php');
require_once('../../../include/mail.class.php');

// Input params
$uid = 'hainberg';
$month = 5;
$year = 2018;

//TEST
//$_GET['timesheet_id'] = 46;
//$_GET['role'] = 'supervisor';

// *********************************	SPECIAL VIEWS for supervisors & personnel department
$isVorgesetzter = false;
$isPersonal = false;
if(isset($_GET['timesheet_id']) && isset($_GET['role']))
{
	if(!is_numeric($_GET['timesheet_id']))
		die ('Es muss eine numerische timesheet_id übergeben werden.');
	else 
		$timesheet_id = $_GET['timesheet_id'];
	
	// *********************************	VIEW:  SUPERVISOR
	if($_GET['role'] == 'supervisor')
	{	
		$mitarbeiter = new Mitarbeiter();
		$mitarbeiter->getUntergebene($uid);
		$untergebenen_arr = $mitarbeiter->untergebene;
		$confirm_vorgesetzten_uid = $uid;
		
		//get the uid of the timesheet_id
		$timesheet = new Timesheet();
		if ($timesheet->getUser($timesheet_id))
			$uid = $timesheet->getUser($timesheet_id);		// from now on uid is uid from employee
		else
			die ($this->errormsg);
		
		//check, if uid is an employee of supervisor
		if (empty($untergebenen_arr))
			die ('Es sind Ihnen keine Mitarbeiter zugeteilt.');
		elseif (!in_array($uid, $untergebenen_arr))
			die ('Die Monatsliste ist nicht von einem Ihrem Mitarbeiter.');		
		else
			// flag supervisor
			$isVorgesetzter = true;		
	}
}

$benutzer = new Benutzer($uid);
$full_name = $benutzer->getFullName();									// string full name of user

$sprache_obj = new sprache();
$sprache = getSprache();												// users language
$sprache_index = $sprache_obj->getIndexFromSprache($sprache);			// users language index (for globals.inc.php)
$p = new phrasen($sprache);

// vars supervisor of user
$mitarbeiter = new Mitarbeiter($uid);
$mitarbeiter->getVorgesetzte($uid);
$vorgesetzten_uid = $mitarbeiter->vorgesetzte[0]; //...hier noch check: falls array mehrere vorgesetzte hat, passts dann auch, wenn der erste genommen wird? 
$benutzer = new Benutzer($vorgesetzten_uid);
$vorgesetzter_full_name = $benutzer->getFullName();						// string full name of supervisor
$vorgesetzter_vorname = $benutzer->vorname;								// string first name of supervisor
$vorgesetzter_nachname = $benutzer->nachname;							// string family name of supervisor

$month_name = DateTime::createFromFormat('!m', $month);					
$month_name = $month_name->format('F');									// string month-name to be used in modify-method
$date_selected = new DateTime();										
$date_selected->modify('last day of ' . $month_name . ' '. $year);		// date obj of month/year selected with last day of month (to operate with diff method)
$date_selected_year = $date_selected->format('Y');						// string year selected
$date_selected_month = $date_selected->format('m');						// string month selected

$monthyear_chosen = $date_selected->format('Y-m');						// string month/year selected

$date_actual = new DateTime();											// date obj of actual date
$monthyear_actual = $date_actual->format('Y-m');						// string month/year of actual date

$isFuture = false;														// bool if chosen monthyear is in the future (after actual monthyear)
$isMissing_doc = false;													// bool if upload documents are missing after check against absences

// *********************************	ALL TIMESHEETS 
// Get all timesheets
$timesheet_arr = new Timesheet();
$timesheet_arr = $timesheet_arr->loadAll($uid);
$isAllowed_createTimesheet = true;

// If timesheet is created the first time: only allow for actual month or one month before acutal month
if (empty($timesheet_arr))
{
	$monthyear_actual_minus_one = $date_actual;
	$monthyear_actual_minus_one->modify('last day of last month');	
	$monthyear_actual_minus_one = $monthyear_actual_minus_one->format('Y-m');
	
	// flag if chosen monthyear is before one month before actual monthyear OR after actual monthyear
	if ($monthyear_chosen < $monthyear_actual_minus_one || $monthyear_chosen > $monthyear_actual)
		$isAllowed_createTimesheet = false;
}

$timesheet_years = array();												// list of unique timesheet years to set title in "Alle Monatslisten" - panel
$isDisabled_by_formerUnsentTimesheet = false;							// bool if there are existing former unsent timesheets (up to the chosen date)
$isDisabled_by_missingTimesheet = false;								// bool if there are missing timesheets (up to the chosen date)
$month_diff = 0;
$cnt = 0;

foreach ($timesheet_arr as $ts)
{	
	$ts_date = new DateTime($ts->datum);
	$ts_year = $ts_date->format('Y');
	$ts_month = $ts_date->format('m');
	$ts_monthyear = $ts_date->format('Y-m');
	$ts_isSent = (is_null($ts->abgeschicktamum)) ? false : true;
	
	// get years (unique), where timesheet exist
	if ($ts_year != end($timesheet_years))
		$timesheet_years[] = $ts_year;
	
	// flag if at least one timesheet is NOT sent AND BEFORE the actual monthyear
	if (!$ts_isSent)
	{
		if ($ts_monthyear < $monthyear_chosen)
		{
			$isDisabled_by_formerUnsentTimesheet = true;	
		}
	}	
	
	// flag if timesheets are missing 
	if ($cnt == 0)
	{		
		// get month amount between last timesheet and chosen date
		$month_diff = (($date_selected_year - $ts_year) * 12) + ($date_selected_month - $ts_month);
		// diff of 1 month is okay, as timesheet is mostly created for the month before
		if ($month_diff > 1)
			$isDisabled_by_missingTimesheet = true;
	}
	$cnt++;
}


// *********************************	ACTUAL TIMESHEET (of month/year selected)
$timesheet = new Timesheet($uid, $month, $year);
$timesheet_id = $timesheet->timesheet_id;
$timesheet->new = (is_null($timesheet_id) ? true : false);
$timesheet_datum = $timesheet->datum;									// date string of timesheet
$isSent = (is_null($timesheet->abgeschicktamum) ? false : true);		// boolean if timesheet was already sent
$isConfirmed = (is_null($timesheet->genehmigtamum) ? false : true);		// boolean if timesheet was already confirmed

// Save timesheet, if timesheet is new and user is timely allowed to create first timesheet
if ($timesheet->new && $isAllowed_createTimesheet)
{
	// only if the chosen monthyear <= actual monthyear
	if ($monthyear_chosen <= $monthyear_actual)
	{
		if (!$isDisabled_by_missingTimesheet)
		{
			$timesheet->uid = $uid;
			$timesheet->datum = $date_selected->format('Y-m-t'); 	//saved with last day of month
			$timesheet->insertvon = $uid;

			// if saving succeeded, last inserted timesheet_id is returned
			if (!$timesheet_id = $timesheet->save())
			{
				echo 'Timesheet konnte nicht gespeichert werden';
			}
		}
	}
	// do not save timesheet and set flag if chosen monthyear is in the future
	else 
	{
		$isFuture = true;
	}
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
		$actual_absent_times_arr []= $absence;
	}
}

// Get absences that need to checked against document upload
$cnt_ab = 0;		// counter arztbesuch
$cnt_beh = 0;		// counter behöre
$cnt_kst = 0;		// counter krankenstand
$cnt_pfl = 0;		// counter pflegeurlaub
foreach ($actual_absent_times_arr as $actual_absence)
{
	switch ($actual_absence->abwesenheit_kurzbz)
	{
		case 'Arztbesuch':
		{
			// each Arztbesuch-absence needs to be attested
			$cnt_ab++;
			continue;	
		}
		case 'Behoerde':
		{
			// each Behörden-absence needs to be attested
			$cnt_beh++;
			continue;	
		}
		case 'PflegeU':
		{
			$von = new DateTime($actual_absence->von);
			$bis = new DateTime($actual_absence->bis);
			
			// Pflegeurlaub needs to be attested only from the 3rd day on
			if($von->diff($bis)->d >= 2)
				$cnt_pfl++;
			
			continue;
		}
		case 'Krank':
		{
			$von = new DateTime($actual_absence->von);
			$bis = new DateTime($actual_absence->bis);
			
			// Krankenstand needs to be attested only from the 3rd day on
			if($von->diff($bis)->d >= 2)
				$cnt_kst++;
			
			continue;
		}
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
	$monthyear_bestaetigung = $date_bestaetigung->format('Y-m');

	if($monthyear_chosen == $monthyear_bestaetigung)
		$all_actualMonth_bestaetigungen []= $bestaetigung;
}

// Get document amount that need to be checked against absences
$cnt_ab_doc = 0;		// counter arztbesuch bestätigungen
$cnt_beh_doc = 0;		// counter behöre bestätigungen
$cnt_kst_doc = 0;		// counter krankenstand bestätigungen
$cnt_pfl_doc = 0;		// counter pflegeurlaub bestätigungen
foreach ($all_actualMonth_bestaetigungen as $actual_bestaetigung)
{
	switch ($actual_bestaetigung->dokument_kurzbz)
	{
		case 'bst_arzt':
		{
			$cnt_ab_doc++;
			continue;	
		}
		case 'bst_bhrd':
		{
			$cnt_beh_doc++;
			continue;	
		}
		case 'bst_pfur':
		{
			$cnt_pfl_doc++;
			continue;
		}
		case 'bst_krnk':
		{
			$cnt_kst_doc++;
			continue;
		}
	}
}

// *********************************	AJAX REQUESTS
// Delete single Bestätigung (on ajax call)
if( isset($_POST['action']) && isset($_POST['method']) ){
	if($_POST['action'] == 'ajax' && $_POST['method'] == 'deleteDmsId')
	{
		if(isset($_POST['dms_id']) && is_numeric($_POST['dms_id']))
		{
			$result = false;	
			$timesheet = new Timesheet();
			if($timesheet->deleteBestaetigung($_POST['dms_id']))
			{	
				$result = true;
			}
			
			// ajax return true if deleting succeeded, false if failed
			echo json_encode($result);	
			exit;
		}		
	}
}

// *********************************	EMAIL SENDING (and document check)
// Send email to supervisor
if (isset($_POST['submitTimesheet']))
{
	// First check if documents according have been uploaded to absences 
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
	
	// if document check ok, prepare for email sending
	if(!$isMissing_doc)
	{	
		$to = $vorgesetzten_uid. '@'. DOMAIN;	// email of supervisor
		$from = 'noreply@'. DOMAIN;				
		$subject = 'Monatsliste '. $monatsname[$sprache_index][$date_selected_month-1]. ' '. $date_selected_year. ' von '. $full_name;
		$text = "
			Guten Tag!<br><br>
			Sie haben die Monatsliste ". $monatsname[$sprache_index][$date_selected_month-1]. " ". $date_selected_year. " von ". $full_name. " erhalten.<br>
			Um diese zu genehmigen, folgen Sie diesem Link:<br><br>
			<a href=". APP_ROOT. "addons/casetime/cis/timesheet.php?timesheet_id=". $timesheet_id. ">Monatsliste jetzt genehmigen</a><br><br>";
		
		$text = wordwrap($text, 70); // wrap code, otherwise display errors in mail
		$mail = new Mail($to, $from, $subject, $text);
		$mail->setHTMLContent($text);
		
		// send email
		if($mail->send())
		{
			$send_date = new DateTime();
			$timesheet = new Timesheet();	
			$timesheet->timesheet_id = $timesheet_id;
			$timesheet->abgeschicktamum = $send_date->format('Y-m-d H:i:s');

			// save sending date
			if($timesheet->save(true))
			{
				// reload page to refresh actual and all monthlist display vars
				header('Location: '.$_SERVER['PHP_SELF']);  
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


// *********************************	CONFIRMATION by supervisor 
if (isset($_POST['submitTimesheetConfirmation']))
{
	$confirm_date = new DateTime();
	$timesheet = new Timesheet();	
	$timesheet->timesheet_id = $timesheet_id;
	$timesheet->genehmigtamum = $confirm_date->format('Y-m-d H:i:s');
	$timesheet->genehmigtvon = $confirm_vorgesetzten_uid;
	
	// save confirmation
	if($timesheet->save(false, true))
	{
		// reload page to refresh actual and all monthlist display vars
		header('Location: '.$_SERVER['PHP_SELF']); 
	}
	else
	{
		echo $timesheet->errormsg;
	}	
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
		.main {
			width: 65%;
		}
		.custom-panel {
			border: solid 1px lightgrey; border-radius: 0.3em; padding: 1%;
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
		.inactive {
			pointer-events: none;
			cursor: default;
			text-decoration: none;
			color: grey;
		}
	</style>
	<script>			
	// Open popup window for uploading documents & refresh site when window closed (to display new uploaded documents)
    function FensterOeffnen (adresse)
	{
		MeinFenster = window.open(adresse, '', 'width = 820px; height = 600px;');
		MeinFenster.focus();
	}
	
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
	
	function onUploadRefresh()
	{
		// display table to be able to be refreshed afterwards
		if ($('#tbl_all_actualMonth_bestaetigungen').is(':hidden'))
		{
			$('#tbl_all_actualMonth_bestaetigungen').css('display', '');
		};
		
		$('#tbl_all_actualMonth_bestaetigungen').load(window.location.href + ' #tbl_all_actualMonth_bestaetigungen');
		$('#panel_all_user_bestaetigungen').load(window.location.href + ' #panel_all_user_bestaetigungen');	
	}
	</script>
</head>

<body class="main" style="font-family: Arial, Helvetica, sans-serif; font-size: 13px;">

	<h3>Zeitaufzeichnung - Monatslisten von <?php echo $full_name ?></h3><br>
	
	<!--************************************	PANEL ACTUAL TIMESHEET	 -->

	<h4>Aktuelle Monatsliste: <?php echo $monatsname[$sprache_index][$date_selected_month-1]. ' '. $date_selected_year ?></h4><br>
	
	<div class="row custom-panel" <?php echo ($isFuture || !$isAllowed_createTimesheet) ? 'style="display: none;"' : '' ?>>
		<div class="col-xs-8">
			<b>Monatsliste für <?php echo $monatsname[$sprache_index][$date_selected_month-1]. ' '. $date_selected_year?> herunterladen (xls):</b><br><br>
			Diese Liste ist nur für Ihren Bedarf und Ihre Eigenkontrolle.<br>
			Sie wird in diesem Schritt nicht an Ihren Vorgesetzten versendet.
		</div>
		<div class="col-xs-4"><br>
			<button type="button" class="btn btn-default pull-right">Monatsliste herunterladen</button>
		</div>
	</div>
	<div class="row custom-panel" style="border-top: none; <?php echo ($isConfirmed || $isFuture || $isDisabled_by_missingTimesheet || !$isAllowed_createTimesheet) ? 'display: none;' : '' ?>">
		<div class="col-xs-8">
			<b>Upload von Dokumenten</b><br><br>
			
			<!--counter for displaying absence text only once-->
			<?php $counter = 0; ?>
			
			<!--loop through absent times-->
			<?php foreach ($absent_times_arr as $absence): ?>

				<!--set absence text-->
				<?php if ($counter == 0): ?>
					Bitte laden Sie Bestätigungen für folgende Fehlzeiten hoch:<br><br>
					<?php $counter++ ?>
				<?php endif; ?>		
					
				<!--find absences and times only for the actual timesheet-->
				<ul>
				<?php if ($absence->timesheet_id == $timesheet_id): ?>	
					<li><?php echo $absence->abwesenheitsgrund. ' von '. date_format(date_create($absence->von), 'd.m.Y') . ' - '. date_format(date_create($absence->bis), 'd.m.Y') ?></li>					
				</ul>
				<?php endif; ?>
							
			<?php endforeach; ?>	
				
			<!--if no absent times in actual timesheet found, display other text-->		
			<?php if($counter = 0): ?>	
				Sie haben im <?php echo $monatsname[$sprache_index][$date_selected_month-1]. ' '. $date_selected_year?> keine Fehlzeiten.
			<?php endif; ?>			
		</div>
		<div class="col-xs-4"><br>
			<a role="button" <?php echo ($isSent) ? 'disabled data-toggle="tooltip" title="Information zur Sperre weiter unten in der Messagebox."' : '' ?> class="btn btn-default pull-right" 
			   href="<?php echo APP_ROOT. 'addons/casetime/cis/timesheet_dmsupload.php?timesheet_id='. $timesheet_id ?>" 
			   onclick="FensterOeffnen(this.href); return false;">Dokumente hochladen</a><br><br><br>
					
			<!--if there are existing bestaetigungen in actual month -> display table and all bestaetigungen-->
			<table class="table table-condensed pull-right" <?php echo (empty($all_actualMonth_bestaetigungen)) ? 'style="display: none;"' : '' ?> id="tbl_all_actualMonth_bestaetigungen">
			<?php foreach($all_actualMonth_bestaetigungen as $bestaetigung): ?>
				<tr>
					<td><?php echo $bestaetigung->dokument_bezeichnung ?>: </td>
					<td><a href="<?php echo APP_ROOT. 'addons/casetime/cis/timesheet_dmsdownload.php?dms_id='. $bestaetigung->dms_id ?>"><?php echo $bestaetigung->name ?></a></td>
					<td><a role="button" <?php echo ($isSent) ? 'class="inactive"' : '' ?> value="<?php echo $bestaetigung->dms_id?>" name="trash_dms_id" id="trash_dms_id" 
						   onclick="deleteBestaetigung(<?php echo $bestaetigung->dms_id ?>)"><i class="fa fa-trash-o" aria-hidden="true"></i></a></td>
				</tr>
			<?php endforeach; ?>
			</table>		
		</div>
	</div>
	
	<div class="row custom-panel" style="border-top: none; <?php echo ($isConfirmed || $isFuture || $isDisabled_by_missingTimesheet || !$isAllowed_createTimesheet) ? 'display: none;' : '' ?>">
		<div class="col-xs-8">
			<b>Monatsliste abschließen</b><br><br>
			Wenn  Sie alle erforderlichen Dokumente hochgeladen haben, verschicken Sie Ihrem Vorgesetzten bis zum 05. des Folgemonats Ihre Monatsliste.<br>
			Nach dem Verschicken kann diese <b>nicht</b> mehr bearbeitet werden.
		</div>
		<form method="POST" action="">
			<div class="col-xs-4"><br>
				<button type="submit" <?php echo ($isSent || $isDisabled_by_formerUnsentTimesheet) ? 'disabled data-toggle="tooltip" title="Information zur Sperre weiter unten in der Messagebox."' : '' ?> 
						name="submitTimesheet" class="btn btn-default pull-right"
						onclick="return confirm('Wollen Sie die Monatsliste für <?php echo $monatsname[$sprache_index][$date_selected_month-1]. ' '. $date_selected_year ?>\n\
								jetzt an <?php echo $vorgesetzter_vorname. ' ', $vorgesetzter_nachname ?> verschicken?');">Monatsliste verschicken</button>
			</div>
		</form>
	</div>
	<div class="row custom-panel" style="border: solid 1px #31708f; padding-top: 20px; padding-bottom: 20px;<?php echo ($isVorgesetzter) ? '' : 'display: none;' ?>">
		<div class="col-xs-8">
			<span class="text-uppercase text-info"><b>Monatsliste genehmigen</b></span><br><br>
			Prüfen Sie die Zeiterfassung Ihres Mitarbeiters, indem Sie die Monatsliste herunterladen.<br>
			Prüfen Sie die Abwesenheitsbestätigungen, indem Sie auf die einzelnen Dokumentenlinks klicken.<br><br>
			Die Genehmigung ist danach nicht mehr widerrufbar.<br>
			Sollte dies notwendig sein, wenden Sie sich an die Personalabteilung
		</div>
		<form method="POST" action="">
			<div class="col-xs-4"><br>
				<button type="submit" <?php echo ($isConfirmed) ? 'disabled data-toggle="tooltip" title="Information zur Sperre weiter unten in der Messagebox."' : '' ?> 
						name="submitTimesheetConfirmation" class="btn btn-primary pull-right"
						onclick="return confirm('Wollen Sie die Monatsliste für <?php echo $monatsname[$sprache_index][$date_selected_month-1]. ' '. $date_selected_year ?>\n\
						für <?php echo $full_name ?> sicher genehmigen?');">Monatsliste genehmigen</button>
			</div>
		</form>
	</div>
	<br><br>



	<!--************************************	ALERTS	 -->
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
							<li>Bestätigung Arzbesuch: für Arztbesuche, Dauer max. 1 Tag.<br>Abwesenheit wurde im CIS in der täglichen Zeiterfassung eingetragen.</li>
							<li>Bestätigung Behörde: für Behördenwege, Dauer max. 1 Tag.<br>Abwesenheit wurde im CIS in der täglichen Zeiterfassung eingetragen.</li>
							<li>Bestätigung Dienstverhinderung: für Dienstverhinderungen, Dauer max. 1 Tag.<br>Abwesenheit wurde im CIS in der täglichen Zeiterfassung eingetragen.</li>
							<li>Bestätigung Krankenstand: für Krankenstände/Spitalsaufenthalte, ab 3 Tage Dauer.<br>Abwesenheit wurde im CIS über die Zeitsperre eingetragen.</li>
							<li>Bestätigung Pflegeurlaub: für Pflegeurlaube, ab 3 Tage Dauer.<br>Abwesenheit wurde im CIS über die Zeitsperre eingetragen.</li>
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
	
	
	<!-- IF chosen date is in the future -->
	<?php if ($isFuture): ?>
	<div class="alert alert-danger alert-dismissible text-center" role="alert">
		<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		<b>Für <?php echo $monatsname[$sprache_index][$date_selected_month-1] . ' ' .$date_selected_year ?> kann noch keine Monatsliste angelegt werden!</b><br><br>
		Es können nur Monatslisten für den aktuellen oder vergangene Monate erstellt werden.
	</div>
	<?php endif; ?>
	
	<!-- IF timesheets are missing before actual monthyear -->	
	<?php if ($isDisabled_by_missingTimesheet && !$isFuture && !$isConfirmed): ?>
	<div class="alert alert-danger alert-dismissible text-center" role="alert">
		<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		<b>Für <?php echo $monatsname[$sprache_index][$date_selected_month-1] . ' ' .$date_selected_year ?> kann noch keine Monatsliste angelegt werden!</b><br><br>
		Monatslisten müssen chronologisch erstellt und an Vorgesetzte gesendet werden.<br>
		Bitte erstellen Sie erst die Monatslisten vergangener Monate.<br>
		
		<!--list months of missing timesheets and provide link to timesheet-->
		<?php 
		if ($month_diff > 2)
		{
			for ($i = $month_diff - 1; $i > 0; $i--)
			{
				echo '<br>Monatsliste ' . $monatsname[$sprache_index][$date_selected_month-1-$i]. ' '. $date_selected_year. ' fehlt';
			}
			echo '<br>';
		}
	
		if ($isDisabled_by_missingTimesheet)
		{
			echo '<br><a role="button" href="#" class="text-danger"><b>Monatsliste ' . $monatsname[$sprache_index][$date_selected_month - $month_diff]. ' '. $date_selected_year. ' jetzt erstellen</b></a><br>';
		}
		?>
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
	
	<!-- if timesheet is sent AND NOT confirmed -->
	<?php if ($isSent && !$isConfirmed && !$isVorgesetzter): ?>
	<div class="alert alert-success alert-dismissible text-center" role="alert">
		<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		<b>Ihre Monatsliste für <?php echo $monatsname[$sprache_index][$date_selected_month-1]. ' '. $date_selected_year ?> ist erfolgreich an <?php echo $vorgesetzter_vorname. ' '. $vorgesetzter_nachname ?> versendet worden!</b><br><br>
		Nach dem Versenden können keine Änderungen mehr vorgenommen werden.<br>
		Sollte dies dennoch nötig sein, wenden Sie sich an Ihren Vorgesetzten für eine erneute Freigabe.<br><br>
		Sobald Ihre Monatsliste genehmigt wurde, wird sie in der unteren Tabelle "Alle Monatslisten" mit einer grünen Ampel versehen.
	</div>
	<?php endif; ?>
	
	<!-- IF timesheet is sent AND confirmed -->
	<?php if ($isSent && $isConfirmed): ?>
	<div class="alert alert-info alert-dismissible text-center" role="alert">
		<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		<b>Die Monatsliste für <?php echo $monatsname[$sprache_index][$date_selected_month-1]. ' '. $date_selected_year?> ist bereits genehmigt worden.</b><br><br>
		Sie können diese aber weiterhin für Ihren persönlichen Bedarf als Excel Datei herunterladen.
	</div>
	<?php endif; ?>
		
	<!-- IF not allowed to create timesheet (only checked when trying to create FIRST timesheet 2 or more months before actual monthyear) -->
	<?php if (!$isAllowed_createTimesheet): ?>
	<div class="alert alert-danger alert-dismissible text-center" role="alert">
		<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
		<b>Für <?php echo $monatsname[$sprache_index][$date_selected_month-1]. ' '. $date_selected_year ?> kann keine Monatsliste angelegt werden!</b><br><br>
		Die erste Monatsliste kann nur für den aktuellen oder den vergangenen Monat erstellt werden.
	</div>
	<?php endif; ?>
	<br><br>
	

	
	
	
	<!--************************************	ALL TIMESHEETS - TABLE -->
	
	<h4>Alle Monatslisten</h4><br>
	
	<!--if there are present timesheets, show panel with all timesheets-->
	<?php if (!empty($timesheet_arr)): ?>
		
		
		<div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
			
		<!--year counter: timesheets of the first (=actual) year will be fully displayed; other years are collapsed-->
		<?php $year_cnt = 1; ?>	
		
		<!--loop through years-->	
		<div id="panel_all_user_bestaetigungen">  <!-- class="panel" -->
		<?php foreach ($timesheet_years as $year): ?>
			<div class="text-center" role="tab" id="heading<?php echo $year_cnt ?>">
				<h4 class="panel-title">
					<a role="button" data-toggle="collapse" data-parent="#accordion" href="#collapse<?php echo $year_cnt ?>" aria-expanded="true" aria-controls="collapse<?php echo $year_cnt ?>">

						<!--display year as title in the panel-->
						<h3><?php echo $year ?></h3>
					</a>
				</h4>
			</div>
			<div id="collapse<?php echo $year_cnt ?>" class="panel-collapse collapse <?php echo ($year_cnt == 1) ? 'in' : '' ?>" role="tabpanel" aria-labelledby="heading<?php echo $year_cnt ?>">
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
						<?php foreach ($timesheet_arr as $ts): ?>
							<?php $ts_date = new DateTime($ts->datum); ?>

							<!--if timesheet is in the looped year, then show timesheet information in this table-->
							<?php if ($ts_date->format('Y') == $year): ?>
							<tr>
								<!--Monatsliste: link to monthlist-->
								<td><a href="#"><?php echo $monatsname[$sprache_index][$ts_date->format('n')-1] . ' ' . $ts_date->format('Y') ?></a></td>

								<!--Abwesenheit: absence reasons & times-->
								<td>
								<?php foreach ($absent_times_arr as $absence): ?>
									<?php if ($ts->timesheet_id == $absence->timesheet_id): ?>
										<?php echo date_format(date_create($absence->von), 'd.m.Y') . ' - '. date_format(date_create($absence->bis), 'd.m.Y'). ': '. $absence->abwesenheitsgrund. "<br>" ?>
									<?php endif; ?>
								<?php endforeach; ?>
								</td>	

								<!--Dokumente: link to documents-->
								<td>
								<?php foreach ($all_user_bestaetigungen as $bestaetigung): ?>
									<?php $date_bestaetigung = new DateTime($bestaetigung->datum); ?>
									<?php if($ts_date->format('m-Y') == $date_bestaetigung->format('m-Y')): ?>
										<a href="<?php echo APP_ROOT. 'addons/casetime/cis/timesheet_dmsdownload.php?dms_id='. $bestaetigung->dms_id ?>"><?php echo $bestaetigung->name ?></a><br>
									<?php endif; ?>
								<?php endforeach; ?>
								</td>

								<!--Abgeschickt am: sending date-->
								<?php if (!is_null($ts->abgeschicktamum)): ?>
									<?php $ts_date = new DateTime($ts->abgeschicktamum); ?>
									<td><?php echo $ts_date->format('d.m.Y') ?></td>
								<?php else: ?>
									<td>Nicht abgeschickt</td>
								<?php endif; ?>

								<!--Genehmigt: confirmation status-->	
								<?php if (is_null($ts->genehmigtamum)): ?>	
									<td class='text-center'><img src="../../../skin/images/ampel_gelb.png" ></td>
								<?php else: ?>
									<td class='text-center'><img src="../../../skin/images/ampel_gruen.png" ></td>
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
		
	<!--if no timesheets existing yet show panel with info, that soon timesheets will be pinned up-->
	<?php else: ?>	
		<div class="panel panel-default">
			<div class="panel-body text-center"><br>
				Sobald Sie Ihre erste Monatsliste versendet haben, wird diese und alle weiteren hier aufgelistet werden.<br><br>
			</div>
		</div>
	<?php endif; ?>
	
</body>
</html>

<?php
// NOTE: Code at the END of script to recognize JS-methods
// Refresh data when document was uploaded (GET request from timesheet_dmsupload.php)
if( isset($_GET['uploadRefresh']) && $_GET['uploadRefresh'] == true ){
	echo 
		'<script type="text/javascript">',
		'onUploadRefresh();',               
		'</script>';
}
?>