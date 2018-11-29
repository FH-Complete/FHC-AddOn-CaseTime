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
require_once('../config.inc.php');
require_once('../../../include/functions.inc.php');
require_once('../include/timesheet.class.php');
require_once('../../../include/basis_db.class.php');
require_once('../../../include/benutzer.class.php');
require_once('../../../include/benutzerberechtigung.class.php');
require_once('../../../include/benutzerfunktion.class.php');
require_once('../../../include/bisverwendung.class.php');
require_once('../../../include/organisationseinheit.class.php');
require_once('../../../include/phrasen.class.php');
require_once('../../../include/sprache.class.php');
require_once('../../../include/globals.inc.php');
require_once('../../../include/mitarbeiter.class.php');
require_once('../include/functions.inc.php');

session_start();	// session to keep filter setting 'Alle meine Mitarbeiter' and show correct employees
	
$uid = get_uid();
$db = new basis_db();
$sprache_obj = new sprache();
$sprache = getSprache();	
$sprache_index = $sprache_obj->getIndexFromSprache($sprache);	// users language index (for globals.inc.php)
$p = new phrasen($sprache);

$date_last_month = new DateTime('first day of this month midnight');
$date_last_month->sub(new DateInterval('P1M'));
$date_last_month->modify('last day of this month');	// date obj of last month

$isPersonal = false;	// true if uid has personnel departments permission 
$isVorgesetzter = false;	// true if uid is supervisor
$isVorgesetzter_indirekt = false;	// true if uid is indirect supervisor on higher oe level
$rechte = new benutzerberechtigung();
$rechte->getBerechtigungen($uid);

// Check if uid has personnel manager permission
$all_employee_uid_arr = array();
if ($isPersonal = check_isPersonal($uid))
{	
	// get ALL active and fix-employed employees
	$mitarbeiter = new Mitarbeiter();
	$mitarbeiter->getPersonal('true', false, false, 'true', false, null);

	foreach ($mitarbeiter->result as $mitarbeiter)
	{
		if ($mitarbeiter->personalnummer > 0)	// filter out dummies
		{
			$all_employee_uid_arr []= $mitarbeiter->uid;
		}	
	}	
}

// Check if uid is timesheet manager
$isTimesheetManager = false;
if ($rechte->isBerechtigt('addon/casetime_manageTimesheet'))
{
	$isTimesheetManager = true;						
}

// Define employees to be shown (all or direct); show all by default
// * differ if requested by THIS site ($_GET) or by returning from timesheet.php ($_SESSION)
$mitarbeiter = new Mitarbeiter();
$showAllMA = false;	// used to toggle between direct employees and all employees

if ((isset($_GET['submitAllMA']) && $_GET['submitAllMA'] == 'true') ||
	(!isset($_GET['submitAllMA']) && isset($_SESSION['casetime/submitAllMA']) && $_SESSION['casetime/submitAllMA'] == true))
{
	$_SESSION['casetime/submitAllMA'] = true;	// save in session to be saved after changing to timesheet.php
	$showAllMA = true;

	$mitarbeiter->getUntergebene($uid, true);
	$untergebenen_arr = array();
	$untergebenen_arr = $mitarbeiter->untergebene;
}
else
{
	$_SESSION['casetime/submitAllMA'] = false;	// save in session to be saved after changing to timesheet.php
	$mitarbeiter->getUntergebene($uid);
	$untergebenen_arr = array();
	$untergebenen_arr = $mitarbeiter->untergebene;
}

// Check if uid is a supervisor
if (!empty($untergebenen_arr))
{
		$isVorgesetzter = true;
		
		// check if Vorgesetzter manages child OEs
		$mitarbeiter->getUntergebene($uid, true);		
		if ($mitarbeiter->result['isIndirectSupervisor'])
		{
			$isVorgesetzter_indirekt = true;
		}	
}

// Permission check
if (!$isPersonal &&
	!$isVorgesetzter)	
{
	die('Sie haben keine Berechtigung für diese Seite');
}
?>

<!--:NOTE: HTML starts here, because ob_flush (used to display progress bar while php is computing time-consuming operations)
needs the GUI to be displayed.-->
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<link rel="stylesheet" type="text/css" href="../../../vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
	<link href="../../../vendor/components/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css"/>
	<link href="../../../vendor/mottie/tablesorter/dist/css/theme.default.min.css" rel="stylesheet">
	<link href="../../../vendor/mottie/tablesorter/dist/css/jquery.tablesorter.pager.min.css" rel="stylesheet">	
	<link href="../../../public/css/sbadmin2/tablesort_bootstrap.css" rel="stylesheet">	
	<script type="text/javascript" src="../../../vendor/components/jquery/jquery.min.js"></script>
	<script type="text/javascript" src="../../../vendor/components/jqueryui/jquery-ui.min.js"></script>
	<script type="text/javascript" src="../../../vendor/twbs/bootstrap/dist/js/bootstrap.min.js"></script>
	<script type="text/javascript" src="../../../vendor/mottie/tablesorter/dist/js/jquery.tablesorter.min.js"></script>
	<script type="text/javascript" src="../../../vendor/mottie/tablesorter/dist/js/jquery.tablesorter.widgets.min.js"></script>
	<script type="text/javascript" src="../../../vendor/mottie/tablesorter/dist/js/extras/jquery.tablesorter.pager.min.js"></script>
	<title>Timesheet Überblick</title>
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
		.inactive {
			pointer-events: none;
			cursor: default;
			text-decoration: none;
			color: grey;
		}
	</style>
	<script>
	$(document).ready(function() 
	{
		// init tablesorter
		$("#tbl_monthlist_overview").tablesorter(
			{
				theme: "default",
				headerTemplate: '{content} {icon}',
				widgets: ["filter"],
				widgetOptions: 
					{
						filter_saveFilters : true,
						filter_searchFiltered: false
					}			
			}
		);
	});
	
	// **************************** FUNCTIONS
	
	// toggle organisational units (single or hiararchy)
	function toggleParentOE()
	{
		if ($('.oe').is(':visible'))
		{
			$('.oe').css('display', 'none');
			$('.oe_parents').css('display', 'inline');
			$('#btn_toggle_oe').text('Direkte OE anzeigen');
		}
		else
		{
			$('.oe').css('display', 'inline');
			$('.oe_parents').css('display', 'none');
			$('#btn_toggle_oe').text('OE-Hierarchie anzeigen');
		}
	}
	
	// trigger loading effect of progress bar
	function triggerProgressbar()
	{
		var elem = document.getElementById("progressbar");   
		var width = 1;
		var id = setInterval(frame, 100);

		function frame()
		{
			if (width >= 95) 
			{
				clearInterval(id);
			} 
			else
			{
				width++; 
				elem.style.width = width + '%';
				elem.innerHTML = width + '%';
			}
		}
	}
	</script>
</head>

<body class="main" style="font-family: Arial, Helvetica, sans-serif; font-size: 13px;">
	
	<h3>Verwaltung Zeitaufzeichnung - Monatslisten</h3>
	<br><br>
	<h4>Übersicht Monatslisten</h4>
		<!--************************************	TEXTUAL INFORMATION	 -->
	
	<?php if($isVorgesetzter): ?>
		Überblick über die Zeiterfassung des letzten Monats Ihrer MitarbeiterInnen sowie über deren Zeitsaldo und konsumierten Urlaubstage.<br>
		Wenn Sie noch Monatslisten genehmigen müssen, wird dies in der Spalte "Nicht genehmigt" rot angezeigt.<br>
		Klicken Sie auf einen Namen um die Monatslisten der entsprechenden Person einzusehen und zu verwalten.
	<?php elseif ($isPersonal): ?>
		Überblick über die Zeiterfassung des letzten Monats aller fix angestellten und aktiven MitarbeiterInnen.<br>
		In der Spalte "Letzte Kontrolle" sehen Sie wann Sie zuletzt eine Monatsliste als "kontrolliert" gespeichert haben.<br>
		Klicken Sie auf einen Namen um die Monatslisten der entsprechenden Person einzusehen, Genehmigungen aufzuheben oder Kontrollnotizen zu setzen.<br>
	<?php endif; ?>
	<br><br>
	<?php if($isVorgesetzter_indirekt): ?>
		<div class="well">
			<form class="form" method="GET" action="<?php echo $_SERVER['PHP_SELF'] ?>">
				<label>Wählen Sie Ihre Ansicht:</label>
				<div class="btn-group col-xs-offset-1"> 
					<button type="submit" class="btn <?php echo (!$showAllMA) ? 'btn-primary active' : 'btn-default' ?>" 
							name="submitAllMA" value="false">Meine direkten Mitarbeiter
					</button>
					<button type="submit" class="btn <?php echo ($showAllMA) ? 'btn-primary active' : 'btn-default' ?>"
							name="submitAllMA" value="true">Alle meine Mitarbeiter
					</button>
				</div>
			</form>
		</div>
		<br>
	<?php endif; ?>
	<?php if($isPersonal && $isVorgesetzter): ?>
	<div class="well">
		<form class="form" method="GET" action="<?php echo $_SERVER['PHP_SELF'] ?>">
			<label>Wählen Sie Ihre Ansicht:</label>
			<div class="btn-group col-xs-offset-1"> 
				<button type="submit" class="btn <?php echo (!$showAllMA) ? 'btn-primary active' : 'btn-default' ?>" 
						name="submitAllMA" value="false">Meine direkten Mitarbeiter
				</button>
				<button type="submit" class="btn <?php echo ($showAllMA) ? 'btn-primary active' : 'btn-default' ?>"
						name="submitAllMA" value="true">Alle Mitarbeiter
				</button>					
			</div>
		</form>
	</div>
	<br>
	<?php endif; ?>
	
	<!--PROGRESS BAR-->
	<div class="progress hidden" id="progressbar_div">
		<div id="progressbar" class="progress-bar progress-bar-striped active" role="progressbar" style="width: 1%">
	  </div>
	</div>	

<?php
		
// vars supervisor / personnel manager
$benutzer = new Benutzer($uid);
$full_name = $benutzer->getFullName();	// string full name

// set employees uid arr depending if uid is supervisor or personnel manager
$employee_uid_arr = array();	// array with employees uid
if (!empty($untergebenen_arr))	
{
	$employee_uid_arr = $untergebenen_arr;
}
// * if supervisor is also personnel manager, untergebenen_arr is overwritten
if (!empty ($all_employee_uid_arr))	
{
	if ($isPersonal && $isVorgesetzter && $_SESSION['casetime/submitAllMA'] == false)
	{
		$employee_uid_arr = $untergebenen_arr;
	}
	else
	{
		$employee_uid_arr = $all_employee_uid_arr;
	}	
}

// *********************************  data for SUPERVISORS VIEW
// vars employees
$employees_data_arr = array();	// array with timesheet data of all employees of supervisor

/* Retrieve time- and holiday balances for direct/all employees
 * from CaseTime server OR
 * from $_SESSION var, if user remains within same browser session AND within same day
 * Retrieving data from $_SESSION variable speeds up the process.
*/
// * Get time- and holiday balances for DIRECT employees from CaseTime Server
if ($_SESSION['casetime/submitAllMA'] == false &&
	!isset($_SESSION['casetime/time_holiday_balance_arr_DIRECT']) || 
	(!empty($_SESSION['casetime/time_holiday_balance_arr_DIRECT'])) && ($_SESSION['casetime/datetime'] < new DateTime('today')))
{
	// display progress bar
	echo '
		<script>
			$("#progressbar_div").toggleClass("hidden");
			triggerProgressbar();
		</script>
		';

	// force displaying part of GUI + progress bar while server is still processing php script
	ob_flush();
	flush();
	
	// get time and holiday balance times
	$time_holiday_balance_arr = getCaseTimeSalden($employee_uid_arr);
	
	// store array in session var
	$_SESSION['casetime/time_holiday_balance_arr_DIRECT'] = $time_holiday_balance_arr;
	
	// store datetime in session var 
	$_SESSION['casetime/datetime'] = new DateTime();
}
// * Get time- and holiday balances for ALL employees from CaseTime Server
elseif($_SESSION['casetime/submitAllMA'] == true &&
	!isset($_SESSION['casetime/time_holiday_balance_arr_ALL']) || 
	(!empty($_SESSION['casetime/time_holiday_balance_arr_ALL'])) && ($_SESSION['casetime/datetime'] < new DateTime('today')))
{
	// display progress bar
	echo '
		<script>
			$("#progressbar_div").toggleClass("hidden");
			triggerProgressbar();
		</script>
		';
	
	// force displaying part of GUI + progress bar while server is still processing php script
	ob_flush();
	flush();
	
	// get time and holiday balance times
	$time_holiday_balance_arr = getCaseTimeSalden($employee_uid_arr);
	
	// store array in session var
	$_SESSION['casetime/time_holiday_balance_arr_ALL'] = $time_holiday_balance_arr;
	
	// store datetime in session var 
	$_SESSION['casetime/datetime'] = new DateTime();

}
else
{
	// * Set time- and holiday balances for DIRECT employees from $_SESSION variable
	if ($_SESSION['casetime/submitAllMA'] == false)
	{
		// hide progress bar as further php processing time is short enough
		echo '
		<script>
			$("#progressbar_div").hide();
		</script>
		';
		
		// set time and holiday balance times
		$time_holiday_balance_arr = $_SESSION['casetime/time_holiday_balance_arr_DIRECT'];
	}
	// * Set time- and holiday balances for ALL employees from $_SESSION variable
	else
	{
		// display progress bar
		echo '
		<script>
			$("#progressbar_div").toggleClass("hidden");
			triggerProgressbar();
		</script>
		';
		
		// force displaying part of GUI + progress bar while server is still processing php script
		ob_flush();
		flush();
		
		// set time and holiday balance times
		$time_holiday_balance_arr = $_SESSION['casetime/time_holiday_balance_arr_ALL'];
	}
}

foreach($employee_uid_arr as $employee_uid)
{
	// name of employee
	$benutzer = new Benutzer($employee_uid);
	$empl_vorname = $benutzer->vorname;
	$empl_nachname = $benutzer->nachname;

	// all timesheets of employee
	$timesheet = new Timesheet();
	$timesheet_arr = $timesheet->loadAll($employee_uid);

	// data of MOST RECENT timesheet BEFORE the actual month
	if (!empty($timesheet_arr))
	{
		$index = 0;
		$cnt_isNotSent = 0;	// counts all timesheets not sent by the employee
		$cnt_isNotConfirmed = 0;	// counts all timesheets not confirmed by supervisor
		$cnt_isNotCreated = 0;	// counts missing timesheets between last timesheet date and last months date
		
		// last timesheet date
		$last_timesheet_date = DateTime::createFromFormat('Y-m-d|', $timesheet_arr[0]->datum); //set time to zero
	
		// count missing timesheets (until last month)
		if ($last_timesheet_date < $date_last_month)
		{
			$cnt_isNotCreated = $date_last_month->diff($last_timesheet_date)->m;
		}
		
		// if employee has already created monthlist for actual month, go back to the one monthlist before
		if ($last_timesheet_date > $date_last_month)
		{
			// start counting with -1 as actual month should not be considered
			$cnt_isNotSent = -1;	
			$cnt_isNotConfirmed = -1;
			
			// go back one monthlist only if it exists
			if (count($timesheet_arr) > 1)
			{
				$index = 1;
				$last_timesheet_date = DateTime::createFromFormat('Y-m-d|', $timesheet_arr[$index]->datum);	
			}
		}
		$last_timesheet_id = $timesheet_arr[$index]->timesheet_id;
		$last_timesheet_sent = (!is_null($timesheet_arr[$index]->abgeschicktamum)) ? new DateTime($timesheet_arr[$index]->abgeschicktamum) : null;
		$last_timesheet_confirmed = (!is_null($timesheet_arr[$index]->genehmigtamum)) ? new DateTime ($timesheet_arr[$index]->genehmigtamum) : null;
	}
		
	// data of ALL timesheets
	foreach ($timesheet_arr as $timesheet)
	{
		if (is_null($timesheet->abgeschicktamum))
		{
			$cnt_isNotSent++;
		}
		
		if (is_null($timesheet->genehmigtamum))
		{
			$cnt_isNotConfirmed++;
		}
	}
	
	// Flag if user has obligation to record times 
	$isZeitaufzeichnungspflichtig = false;

	// * only get active employee contracts to be checked for 'zeitaufzeichnungspflichtig'
	$bisverwendung = new bisverwendung();
	$now = new DateTime('today');
	$bisverwendung->getVerwendungDatum($employee_uid, $now->format('Y-m-d'));
	$verwendung_arr = $bisverwendung->result;

	foreach($verwendung_arr as $verwendung)
	{
		if($verwendung->zeitaufzeichnungspflichtig)
		{
			$isZeitaufzeichnungspflichtig = true;
			break;
		}
	}
		
	// Get time- & holiday balances
	$time_balance = false;
	$holiday = false;
	
	// * if uid is personnel manager or superleader, check the object-array with all time-
	// and holiday balances and match with the actual employee
	if ($isPersonal || $isVorgesetzter || $isVorgesetzter_indirekt)
	{
		if (is_object($time_holiday_balance_arr))
		{
			// uppercase employee uid as object property uid is uppercased
			$uc_employee_uid = strtoupper($employee_uid);
			
			// match the actual employee with the one in the casetime-salden-array
			if (property_exists($time_holiday_balance_arr, strtoupper($uc_employee_uid)))
			{
				// balance of time
				$time_balance = (
									isset($time_holiday_balance_arr->{$uc_employee_uid}->Zeitsaldo) 
									? $time_holiday_balance_arr->{$uc_employee_uid}->Zeitsaldo
									: false
								);
				
				// holiday information
				$holiday = new stdClass();
				$holiday->AktuellerStand = (
												isset($time_holiday_balance_arr->{$uc_employee_uid}->UrlaubAktuell) 
												? $time_holiday_balance_arr->{$uc_employee_uid}->UrlaubAktuell
												: '-'
											);
				$holiday->Urlaubsanspruch = (
												isset($time_holiday_balance_arr->{$uc_employee_uid}->UrlaubAnspruch) 
												? $time_holiday_balance_arr->{$uc_employee_uid}->UrlaubAnspruch
												: '-'
											);;
			}
		}	
	}
	else
	{
		// balance of time
		$time_balance = getCaseTimeZeitsaldo($employee_uid);	// float time balance OR string error OR bool false

		// holiday information	
		$holiday = getCastTimeUrlaubssaldo($employee_uid);	// object with int urlaubsanspruch, float resturlaub, float aktueller stand OR string error OR bool false
	}
	
	// Get organisational unit of employee
	$benutzer_fkt = new Benutzerfunktion();
	$benutzer_fkt->getBenutzerFunktionByUid($employee_uid, 'oezuordnung', date('Y-m-d'));
	$employee_oe_kurzbz = (!empty($benutzer_fkt->result)) ? $benutzer_fkt->result[0]->oe_kurzbz : '';	// string oe

	// get organisational unit hierarchy
	//:NOTE: 2 different arrays of organisational units for different display in filter functionality (one is used by hidden values) 
	$employee_oe_parent_arr = array();	// array of string org units 
	$employee_oe_parent_withType_arr = array(); // array of string org units plus type of org unit 
	
	$oe = new Organisationseinheit();
	if ($oe->getParents_withOEType($employee_oe_kurzbz))
	{
		foreach ($oe->result as &$oe_parent)
		{
			$employee_oe_parent_arr[] = $oe_parent->oe_bezeichnung;
			$employee_oe_parent_withType_arr[] = "<b>". $oe_parent->oe_typ_bezeichnung. "</b> ". $oe_parent->oe_bezeichnung;
		}			
	}
	
	// Extra data for personnel department
	$last_cntrl_timesheet_id = '';	// timesheet_id of last controlled timesheet
	$last_cntrl_date = '';	// date of last controlled timesheet
	$last_cntrl_uid = '';	// controller uid of last controlled timesheet
	$last_cntrl_remark = '';	// remark of last controlled timesheet	
	if ($isPersonal)
	{
		// get latest controlling data
		$timesheet = new Timesheet();
		$wasControlled = $timesheet->getLatestControllingData($employee_uid); 
		
		if ($wasControlled)
		{
			$last_cntrl_timesheet_id = $timesheet->timesheet_id;
			$last_cntrl_date = new DateTime($timesheet->kontrolliertamum);
			$last_cntrl_uid = $timesheet->kontrolliertvon;
			$last_cntrl_remark = (!is_null($timesheet->kontroll_notizen)) ? $timesheet->kontroll_notizen : '';
		}

	}
	
	// Collect all employees data to push to overall employees array
	$obj = new stdClass();
	// * full data of employee who has timesheets
	if (!empty($timesheet_arr))
	{
		$obj->oe_parent_arr = $employee_oe_parent_arr;
		$obj->oe_parent_withType_arr = $employee_oe_parent_withType_arr;
		$obj->uid = $employee_uid;
		$obj->vorname = $empl_vorname;
		$obj->nachname = $empl_nachname;
		$obj->last_timesheet_id = $last_timesheet_id;
		$obj->last_timesheet_date = $last_timesheet_date;
		$obj->last_timesheet_sent = $last_timesheet_sent;
		$obj->last_timesheet_confirmed = $last_timesheet_confirmed;
		$obj->all_timesheets_notCreated = $cnt_isNotCreated;
		$obj->all_timesheets_notSent = $cnt_isNotSent;
		$obj->all_timesheets_notConfirmed = $cnt_isNotConfirmed;
		$obj->time_balance = $time_balance;
		$obj->holiday = $holiday;
		$obj->last_cntrl_timesheet_id = $last_cntrl_timesheet_id;
		$obj->last_cntrl_date = $last_cntrl_date;
		$obj->last_cntrl_uid = $last_cntrl_uid;
		$obj->last_cntrl_remark = $last_cntrl_remark;
		$obj->isZeitaufzeichnungspflichtig = $isZeitaufzeichnungspflichtig;	// boolean
	}
	// * basic data of employee who has NO timesheets
	else
	{
		$obj->oe_parent_arr = $employee_oe_parent_arr;
		$obj->oe_parent_withType_arr = $employee_oe_parent_withType_arr;
		$obj->uid = $employee_uid;
		$obj->vorname = $empl_vorname;
		$obj->nachname = $empl_nachname;
		$obj->last_timesheet_id = null;
		$obj->last_timesheet_date = null;
		$obj->last_timesheet_sent = null;
		$obj->last_timesheet_confirmed = null;
		$obj->all_timesheets_notCreated = 0;
		$obj->all_timesheets_notSent = 0;
		$obj->all_timesheets_notConfirmed = 0;
		$obj->time_balance = $time_balance;
		$obj->holiday = $holiday;
		$obj->last_cntrl_timesheet_id = $last_cntrl_timesheet_id; //empty
		$obj->last_cntrl_date = $last_cntrl_date;	//empty
		$obj->last_cntrl_uid = $last_cntrl_uid;	//empty
		$obj->last_cntrl_remark = $last_cntrl_remark;	//empty
		$obj->isZeitaufzeichnungspflichtig = $isZeitaufzeichnungspflichtig; // boolean
	}
	// * push to employees array
	$employees_data_arr []= $obj;
}

// sort employees array by employees family name
usort($employees_data_arr, "sortEmployeesName");

// *********************************	FUNCTIONS
function sortEmployeesName($employee1, $employee2)
{
    return strcmp($employee1->nachname, $employee2->nachname);
}
?>

<!--<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<link rel="stylesheet" type="text/css" href="../../../vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
	<link href="../../../vendor/components/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css"/>
	<link href="../../../vendor/mottie/tablesorter/dist/css/theme.default.min.css" rel="stylesheet">
	<link href="../../../vendor/mottie/tablesorter/dist/css/jquery.tablesorter.pager.min.css" rel="stylesheet">	
	<link href="../../../public/css/sbadmin2/tablesort_bootstrap.css" rel="stylesheet">	
	<script type="text/javascript" src="../../../vendor/components/jquery/jquery.min.js"></script>
	<script type="text/javascript" src="../../../vendor/components/jqueryui/jquery-ui.min.js"></script>
	<script type="text/javascript" src="../../../vendor/twbs/bootstrap/dist/js/bootstrap.min.js"></script>
	<script type="text/javascript" src="../../../vendor/mottie/tablesorter/dist/js/jquery.tablesorter.min.js"></script>
	<script type="text/javascript" src="../../../vendor/mottie/tablesorter/dist/js/jquery.tablesorter.widgets.min.js"></script>
	<script type="text/javascript" src="../../../vendor/mottie/tablesorter/dist/js/extras/jquery.tablesorter.pager.min.js"></script>
	<title>Timesheet Überblick</title>
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
		.inactive {
			pointer-events: none;
			cursor: default;
			text-decoration: none;
			color: grey;
		}
	</style>
	<script>
	$(document).ready(function() 
	{
		// init tablesorter
		$("#tbl_monthlist_overview").tablesorter(
			{
				theme: "default",
				headerTemplate: '{content} {icon}',
				widgets: ["filter"],
				widgetOptions: 
					{
						filter_saveFilters : true,
						filter_searchFiltered: false
					}			
			}
		);
	});
	
	// **************************** FUNCTIONS
	
	// toggle organisational units (single or hiararchy)
	function toggleParentOE()
	{
		if ($('.oe').is(':visible'))
		{
			$('.oe').css('display', 'none');
			$('.oe_parents').css('display', 'inline');
			$('#btn_toggle_oe').text('Direkte OE anzeigen');
		}
		else
		{
			$('.oe').css('display', 'inline');
			$('.oe_parents').css('display', 'none');
			$('#btn_toggle_oe').text('OE-Hierarchie anzeigen');
		}
	}
	</script>
</head>

<body class="main" style="font-family: Arial, Helvetica, sans-serif; font-size: 13px;">
	
	<h3>Verwaltung Zeitaufzeichnung - Monatslisten</h3>
	<br><br>
	<h4>Übersicht Monatslisten</h4>-->
	
	<!--************************************	TEXTUAL INFORMATION	 -->
<!--	
	<?php if($isVorgesetzter): ?>
		Überblick über die Zeiterfassung des letzten Monats Ihrer MitarbeiterInnen sowie über deren Zeitsaldo und konsumierten Urlaubstage.<br>
		Wenn Sie noch Monatslisten genehmigen müssen, wird dies in der Spalte "Nicht genehmigt" rot angezeigt.<br>
		Klicken Sie auf einen Namen um die Monatslisten der entsprechenden Person einzusehen und zu verwalten.
	<?php elseif ($isPersonal): ?>
		Überblick über die Zeiterfassung des letzten Monats aller fix angestellten und aktiven MitarbeiterInnen.<br>
		In der Spalte "Letzte Kontrolle" sehen Sie wann Sie zuletzt eine Monatsliste als "kontrolliert" gespeichert haben.<br>
		Klicken Sie auf einen Namen um die Monatslisten der entsprechenden Person einzusehen, Genehmigungen aufzuheben oder Kontrollnotizen zu setzen.<br>
	<?php endif; ?>
	<br><br>
	<?php if($isVorgesetzter_indirekt): ?>
		<div class="well">
			<form class="form" method="GET" action="<?php echo $_SERVER['PHP_SELF'] ?>">
				<label>Wählen Sie Ihre Ansicht:</label>
				<div class="btn-group col-xs-offset-1"> 
					<button type="submit" class="btn <?php echo (!$showAllMA) ? 'btn-primary active' : 'btn-default' ?>" 
							name="submitAllMA" value="false">Meine direkten Mitarbeiter
					</button>
					<button type="submit" class="btn <?php echo ($showAllMA) ? 'btn-primary active' : 'btn-default' ?>"
							name="submitAllMA" value="true">Alle meine Mitarbeiter
					</button>
				</div>
			</form>
		</div>
		<br>
	<?php endif; ?>
		<?php if($isPersonal && $isVorgesetzter): ?>
		<div class="well">
			<form class="form" method="GET" action="<?php echo $_SERVER['PHP_SELF'] ?>">
				<label>Wählen Sie Ihre Ansicht:</label>
				<div class="btn-group col-xs-offset-1"> 
					<button type="submit" class="btn <?php echo (!$showAllMA) ? 'btn-primary active' : 'btn-default' ?>" 
							name="submitAllMA" value="false">Meine direkten Mitarbeiter
					</button>
					<button type="submit" class="btn <?php echo ($showAllMA) ? 'btn-primary active' : 'btn-default' ?>"
							name="submitAllMA" value="true">Alle Mitarbeiter
					</button>					
				</div>
			</form>
		</div>
		<br>
	<?php endif; ?>
	-->
	<script>
	
	// hide progress bar when site is done
	$("#progressbar_div").toggleClass("hidden");
	
</script>	
	<!--************************************	TABLE with EMPLOYEES MONTHLIST INFORMATION	 -->
				
	<table class="table table-condensed table-bordered tablesorter tablesort-active" id="tbl_monthlist_overview" role="grid">
		
		<!--************************************	TABLE HEAD	 -->
		<thead class="text-center">
			<tr class="table tablesorter-ignoreRow">
				<td><button type="button" id="btn_toggle_oe" class="btn btn-default btn-xs" onclick="toggleParentOE()">OE-Hierarchie anzeigen</button></td>
				<td></td>
				<td></td>
				<td colspan="3" class="text-uppercase"><b><?php echo $monatsname[$sprache_index][$date_last_month->format('m') - 1]. ' '. $date_last_month->format('Y')?></b></td>
				<td colspan="1" class="text-uppercase"><b>bis <?php echo $monatsname[$sprache_index][$date_last_month->format('m') - 1]. ' '. $date_last_month->format('Y')?></b></td>
				<td colspan="2" class="text-uppercase"><b>Insgesamt</b></td>
				<?php echo ($isPersonal) ? '<td class="text-uppercase">Letzte Kontrolle</td>' : '' ?>
			</tr>
			<tr>
				<th style="width: 10%">Organisationseinheit</th>
				<th>Mitarbeiter</th>	
				<th data-value="ja">Zeitaufzeichnungspflicht</th>
				<th>Status</th>
				<th>Abgeschickt am</th>
				<th>Genehmigt am</th>
				<th>Nicht genehmigt&nbsp;				
					<i class="fa fa-question-circle-o" aria-hidden="true" style="white-space: pre-line;"
						data-toggle="tooltip" title="Anzahl nicht genehmigter Monatslisten bis inklusive <?php echo $monatsname[$sprache_index][$date_last_month->format('m') - 1]. ' '. $date_last_month->format('Y')?>.&#013;&#010;(auch solche, die nicht erstellt/abgeschickt und daher nicht genehmigt wurden)">							 
					</i>
				</th>
				<th>Zeitsaldo</th>
				<th data-toggle="tooltip" title="Aktueller Stand / Urlaubsanspruch">Urlaubstage
					<i class="fa fa-question-circle-o" aria-hidden="true" style="white-space: pre-line;"
						data-toggle="tooltip" title="Aktueller Stand / Urlaubsanspruch">							 
					</i>
				</th>
				<?php echo ($isPersonal) ? '<th>Kontrolliert am</th>' : '' ?>
			</tr>			
		</thead>
		
		<!--************************************	TABLE BODY	 -->
		<tbody> 
			<?php foreach ($employees_data_arr as $employee): ?>
				
				<!--IF employee has AT LEAST ONE TIMESHEET-->
				<?php if (isset($employee->last_timesheet_id)): ?>
				
				<!--IF employee must not record times, color row grey-->
				<tr <?php echo (!$employee->isZeitaufzeichnungspflichtig) ? 'class="active"' : '' ?>>
					
					<!--organisational unit-->
					<td>
						<!--visible string of closest organisational unit-->
						<span class="oe" style='display: inline;'><?php echo (!empty($employee->oe_parent_arr)) ? $employee->oe_parent_arr[0] : '-' ?></span>
						<!--hidden string with org unit and parent org units to allow filtering of higher units-->
						<span class="oe_parents" style='display: none;'><small><?php echo (!empty($employee->oe_parent_withType_arr)) ? implode(' > ', array_reverse($employee->oe_parent_withType_arr)) : '-' ?></small></span>
					</td>

					<!--employee name & link to latest timesheet-->
					<td>
						<a href="<?php echo APP_ROOT. 'addons/casetime/cis/timesheet.php?timesheet_id='. $employee->last_timesheet_id ?>"><?php echo $employee->nachname. ' '. $employee->vorname ?></a>
					</td>
					
					<!--obligated to record times (zeitaufzeichnungspflichtig)-->
					<?php if ($employee->isZeitaufzeichnungspflichtig): ?>
						<td class='text-center'>ja</td>
					<?php else: ?>
						<td class='text-center'>nein</td>
					<?php endif; ?>
						
					<!--status-->
					<!-- * only consider if timesheet date is date of last month -->
					<?php if ($date_last_month == $employee->last_timesheet_date): ?>
					
						<!-- * status text if confirmed-->
						<?php if (!is_null($employee->last_timesheet_confirmed)): ?>
							<td class='text-center'>genehmigt</td>
							
						<!-- * status text if sent-->
						<?php elseif (!is_null($employee->last_timesheet_sent)): ?>
							<td class='text-center'>abgeschickt</td>
							
						<!-- * status text if created-->
						<?php else: ?>	
							<td class='text-center'>angelegt</td>
						<?php endif; ?>
							
					<!-- * status text if NO created timesheet for the last month-->
					<?php else: ?>	
						<td class='text-center'>nicht angelegt</td>
					<?php endif; ?>
					
					<!--sending date-->
					<!-- * only consider if timesheet date is date of last month -->
					<?php if ($date_last_month == $employee->last_timesheet_date): ?>	
						<td class='text-center'>
							<?php echo (!is_null($employee->last_timesheet_sent)) ? $employee->last_timesheet_sent->format('d.m.Y') : '-' ?>
						</td>
					<?php else: ?>	
						<td class='text-center'>-</td>
					<?php endif; ?>
						
					<!--confirmation date-->
					<!-- * only consider if timesheet date is date of last month -->
					<?php if ($date_last_month == $employee->last_timesheet_date): ?>
						<td class='text-center'>
							<?php echo (!is_null($employee->last_timesheet_confirmed)) ? $employee->last_timesheet_confirmed->format('d.m.Y') : '-' ?>
						</td>
					<?php else: ?>	
						<td class='text-center'>-</td>
					<?php endif; ?>
											
					<!--amount of all timesheets not created AND not confirmed (includes not sent ones)-->
					<?php $all_timesheets_notCreatedOrConfirmed = $employee->all_timesheets_notCreated + $employee->all_timesheets_notConfirmed; ?>
					<td class='text-center <?php echo (!empty($all_timesheets_notCreatedOrConfirmed)) ? 'danger' : '' ?>'>
						<?php echo (!empty($all_timesheets_notCreatedOrConfirmed)) ? $all_timesheets_notCreatedOrConfirmed : '-' ?>
					</td>
					
					<!--balance of working hours on next account-->
					<td class='text-center'><?php echo (is_float($employee->time_balance)) ? $employee->time_balance : '-' ?></td>

					<!--overtime hours-->
					<!--<td class='text-center'>5,0 h</td>-->

					<!--holidays cosumed-->
					<td class='text-center'>
						<?php echo (is_object($employee->holiday)) ? $employee->holiday->AktuellerStand. ' / '. $employee->holiday->Urlaubsanspruch : '-' ?>
					</td>	
						
					<!--controlling date (displayed ONLY for personal department)-->
					<?php if ($isPersonal): ?>
						<td class='text-center'>
							<?php echo (!empty($employee->last_cntrl_date)) ? $employee->last_cntrl_date->format('d.m.Y') : '-' ?>
						</td>
					<?php endif; ?>				
				</tr>
								
				<!--IF employee has NO TIMESHEET yet-->
				<?php else: ?>
				<!--IF employee must not record times, color row grey-->
				<tr <?php echo (!$employee->isZeitaufzeichnungspflichtig) ? 'class="active"' : '' ?>>	
					
					<!--organisational unit-->
					<td>					
						<!--visible string of closest organisational unit-->
						<span class="oe" style='display: inline;'><?php echo (!empty($employee->oe_parent_arr)) ? $employee->oe_parent_arr[0] : '-' ?></span>
						<!--hidden string with org unit and parent org units to allow filtering of higher units-->
						<span class="oe_parents" style='display: none;'><small><?php echo (!empty($employee->oe_parent_arr)) ? implode(' > ', array_reverse($employee->oe_parent_arr)) : '-' ?></small></span>
					</td>
						
					<!--employee name: text only (if uid is timesheet manager, provide url to create first timesheet)-->				
					<?php if($isTimesheetManager): ?>
						<td>
							<span class="label pull-right text-uppercase" style="background-color: lightgrey; margin-left: 5px;"
								  data-toggle="tooltip" title="Noch keine Monatsliste vorhanden. Als Timesheetmanager können sie die erste anlegen.">erstanlage</span>
							<a href="<?php echo APP_ROOT. 'addons/casetime/cis/timesheet.php' ?>?year=<?php echo $date_last_month->format('Y') ?>&month=<?php echo $date_last_month->format('m') ?>&employee_uid=<?php echo $employee->uid ?>&create=true"><?php echo $employee->nachname. ' '. $employee->vorname ?>
						</td>
					<?php else: ?>
						<td><?php echo $employee->nachname. ' '. $employee->vorname ?></td>
					<?php endif; ?>		
							
					<!--obligated to record times (zeitaufzeichnungspflichtig)-->
					<?php if ($employee->isZeitaufzeichnungspflichtig): ?>
						<td class='text-center'>ja</td>
					<?php else: ?>
						<td class='text-center'>nein</td>
					<?php endif; ?>
					
					<!--status-->
					<td class='text-center'>nicht angelegt</td>
					
					<!--sending date-->
					<td class='text-center'>-</td>
					
					<!--confirmation date-->
					<td class='text-center'>-</td>
					
					<!--amount of all timesheets not sent-->
					<!--<td class='text-center'>-</td>-->	
					
					<!--amount of all timesheets not confirmed-->
					<td class='text-center'>-</td>
						<!--balance of working hours on next account-->
						<td class='text-center'><?php echo (is_float($employee->time_balance)) ? $employee->time_balance : '-' ?></td>
						<!--overtime hours-->
						<!--<td class='text-center'>-</td>-->	
						<!--holidays cosumed-->
						<td class='text-center'>
							<?php echo (is_object($employee->holiday)) ? $employee->holiday->AktuellerStand. ' / '. $employee->holiday->Urlaubsanspruch : '-' ?>
						</td>						
					<!--controlling date (displayed ONLY for personal department)-->
					<?php if ($isPersonal): ?>
						<td class='text-center'>-</td>
					<?php endif; ?>
				</tr>
				<?php endif; ?>
				
			<?php endforeach; ?>
		</tbody>
	</table>
</body>
</html>
