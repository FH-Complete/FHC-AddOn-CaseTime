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
require_once('../../../include/covid/covidhelper.class.php');
require_once('../../../include/vertragsbestandteil.class.php');

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

$showcovidstatus = true; // every supervisor should see covidstatus by default
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
			$all_employee_uid_arr[] = $mitarbeiter->uid;
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

$showOnlyFixEmployees = true;
if ((isset($_GET['onlyfix']) && $_GET['onlyfix'] == 'false') ||
	(!isset($_GET['onlyfix']) && isset($_SESSION['casetime/onlyfix']) && $_SESSION['casetime/onlyfix'] == false))
{
	$showOnlyFixEmployees = false;
}
$_SESSION['casetime/onlyfix'] = $showOnlyFixEmployees;

// Flag MA, die im letzten Monat OE gewechselt oder DV beendet haben
$showLastMonthBeendeteEmployees = false;
if ((isset($_GET['lastMonthBeendete']) && $_GET['lastMonthBeendete'] == 'true') ||
	(!isset($_GET['lastMonthBeendete']) && isset($_SESSION['casetime/lastMonthBeendete']) && $_SESSION['casetime/lastMonthBeendete'] == true))
{
	$showLastMonthBeendeteEmployees = true;
}
$_SESSION['casetime/lastMonthBeendete'] = $showLastMonthBeendeteEmployees;

if ((isset($_GET['submitAllMA']) && $_GET['submitAllMA'] == 'true') ||
	(!isset($_GET['submitAllMA']) && isset($_SESSION['casetime/submitAllMA']) && $_SESSION['casetime/submitAllMA'] == true))
{
	$_SESSION['casetime/submitAllMA'] = true;	// save in session to be saved after changing to timesheet.php
	$showAllMA = true;

	if( $isPersonal && !$rechte->isBerechtigt('extension/fhtw_manual_3g', null, 'suid') )
	{
		$showcovidstatus = false;
	}

	$mitarbeiter->getUntergebene($uid, true, $showOnlyFixEmployees, $showLastMonthBeendeteEmployees);
	$untergebenen_arr = array();
	$untergebenen_arr = $mitarbeiter->untergebene;
}
else
{
	$_SESSION['casetime/submitAllMA'] = false;	// save in session to be saved after changing to timesheet.php
	$mitarbeiter->getUntergebene($uid, false, $showOnlyFixEmployees, $showLastMonthBeendeteEmployees);
	$untergebenen_arr = array();
	$untergebenen_arr = $mitarbeiter->untergebene;
}

if( defined('CIS_SHOW_COVID_STATUS') && !CIS_SHOW_COVID_STATUS )
{
	$showcovidstatus = false;
}

// Check if uid is a supervisor
if (!empty($untergebenen_arr))
{
		$isVorgesetzter = true;

		// check if Vorgesetzter manages child OEs
		$mitarbeiter->getUntergebene($uid, true, true, $showLastMonthBeendeteEmployees);
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
	<link rel="stylesheet" type="text/css" href="../../../vendor/twbs/bootstrap3/dist/css/bootstrap.min.css">
	<link href="../../../vendor/fortawesome/font-awesome4/css/font-awesome.min.css" rel="stylesheet" type="text/css"/>
	<link href="../../../vendor/mottie/tablesorter/dist/css/theme.default.min.css" rel="stylesheet">
	<link href="../../../vendor/mottie/tablesorter/dist/css/jquery.tablesorter.pager.min.css" rel="stylesheet">
	<link href="../../../public/css/sbadmin2/tablesort_bootstrap.css" rel="stylesheet">
	<script type="text/javascript" src="../../../vendor/components/jquery/jquery.min.js"></script>
	<script type="text/javascript" src="../../../vendor/components/jqueryui/jquery-ui.min.js"></script>
	<script type="text/javascript" src="../../../vendor/twbs/bootstrap3/dist/js/bootstrap.min.js"></script>
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

	// show Only Fix or All Employees
	function fixOrAllEmployees()
	{
		var params = new URLSearchParams(window.location.search);
		var onlyfixemployees = $('#onlyfixemployees').is(':checked');
		params.set('onlyfix', onlyfixemployees);
		window.location.search = params.toString();
	}

	// show also Employees where Dienstverhältnis ended last month
	function lastMonthBeendeteEmployees()
	{
		var params = new URLSearchParams(window.location.search);
		var lastMonthBeendete = $('#lastMonthBeendete').is(':checked');
		params.set('lastMonthBeendete', lastMonthBeendete);
		window.location.search = params.toString();
	}

	// trigger loading effect of progress bar
	var progressbarwidth = 1;
	var employees_count = 1;
	var employees_loaded = 0;
	function triggerProgressbar()
	{
		var elem = document.getElementById("progressbar");
		var width = 1;
		var id = setInterval(frame, 100);

		function frame()
		{
			progressbarwidth = Math.ceil(employees_loaded * 100 / employees_count);
			elem.style.width = progressbarwidth + '%';
			elem.innerHTML = progressbarwidth + '%';
			
			if(progressbarwidth > 99) {
				clearInterval(id);	
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
	<p><br>Supportanfragen unter <a href="mailto:zeiterfassung@technikum-wien.at">zeiterfassung@technikum-wien.at</a></p>
	<br><br>
	<?php if($isVorgesetzter_indirekt && !$isPersonal): ?>
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
	<?php if($isPersonal): ?>
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
$employee_uid_arr = array();	// array with employees uid who will be displayed in the table
if (!empty($untergebenen_arr))
{
	$employee_uid_arr = $untergebenen_arr; // direct or indirect employees
}
// * if supervisor is also personnel manager, untergebenen_arr is overwritten when clicking "all employees"
if (!empty($all_employee_uid_arr))
{
	if ($_SESSION['casetime/submitAllMA'] == false)
	{
		$employee_uid_arr = $untergebenen_arr;
	}
	else
	{
		$employee_uid_arr = $all_employee_uid_arr;
	}
}

/*
 * 2024-02-06 ma0080@technikum-wien.at
 * initialize employee count for progress bar
 * use double empolyee count since 50% is 
 * fetching data from casetime and 50% is
 * fetching data from FHC
 */
$employees_count = count($employee_uid_arr) * 2;
$employees_loaded = 0;
echo <<<EOJS
<script>employees_count = {$employees_count};</script>

EOJS;

// *********************************  data for SUPERVISORS VIEW
// covidstatus
$covidhelper = new CovidHelper();
$covidhelper->fetchCovidStatus($employee_uid_arr);
// vars employees
$employees_data_arr = array();	// array with timesheet data of all employees of supervisor

/*
 * 2024-02-06 ma0080@technikum-wien.at
 * load data from casetime in chunks to be able to 
 * have a more accurate progress bar 
 * output loading count via ob_flush after each chunk
 */
function loadCaseTimeSaldenChunked($employee_uid_arr) 
{
	global $employees_loaded;

	$chunksize = 50;
	$time_holiday_balance_arr = array();
	$chunks = array_chunk($employee_uid_arr, $chunksize);
	foreach($chunks as $chunk)
	{
		$part = array();
		if( false !== ($part = getCaseTimeSalden($chunk)) )
		{
			$time_holiday_balance_arr = array_merge($time_holiday_balance_arr, (array)$part);
		}

		$employees_loaded += count($chunk);
		echo <<<EOJS
	<script>employees_loaded = {$employees_loaded};</script>

EOJS;

		ob_flush();
		flush();
	}
	$time_holiday_balance_arr = (object) $time_holiday_balance_arr;
	return $time_holiday_balance_arr;
}

/* Retrieve time- and holiday balances for direct/all employees
 * from CaseTime server OR
 * from $_SESSION var, if user remains within same browser session AND within same day
 * Retrieving data from $_SESSION variable speeds up the process.
*/
// * Get time- and holiday balances for DIRECT employees from CaseTime Server
if (!isset($_SESSION['casetime/time_holiday_balance_arr_DIRECT']) && $_SESSION['casetime/submitAllMA'] == false ||
	(!empty($_SESSION['casetime/time_holiday_balance_arr_DIRECT'])) &&
    ($_SESSION['casetime/datetime'] < new DateTime('today')))
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
	$time_holiday_balance_arr = loadCaseTimeSaldenChunked($employee_uid_arr);

	// store array in session var
	$_SESSION['casetime/time_holiday_balance_arr_DIRECT'] = $time_holiday_balance_arr;

	// store datetime in session var
	$_SESSION['casetime/datetime'] = new DateTime();

}
// * Get time- and holiday balances for ALL employees from CaseTime Server
elseif($_SESSION['casetime/submitAllMA'] == true &&
	!isset($_SESSION['casetime/time_holiday_balance_arr_ALL']) ||
	(!empty($_SESSION['casetime/time_holiday_balance_arr_ALL'])) &&
    ($_SESSION['casetime/datetime'] < new DateTime('today')))
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
	$time_holiday_balance_arr = loadCaseTimeSaldenChunked($employee_uid_arr);

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
		$employees_loaded = count($employee_uid_arr); // set progress bar var since no casetime request is made
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
		$employees_loaded = count($employee_uid_arr); // set progress bar var since no casetime request is made
	}
}
$isAllIn = false;

//$employees_loaded = 0;
foreach($employee_uid_arr as $employee_uid)
{
	// name of employee
	$benutzer = new Benutzer($employee_uid);
	$empl_vorname = $benutzer->vorname;
	$empl_nachname = $benutzer->nachname;

	// all timesheets of employee
	$timesheet = new Timesheet();
	$timesheet_arr = $timesheet->loadAll($employee_uid);

    // Get last timesheet
    $last_timesheet_id = !empty($timesheet_arr) ? $timesheet_arr[0]->timesheet_id : null;
    $last_timesheet_date = !empty($timesheet_arr) ? new DateTime($timesheet_arr[0]->datum) : null;

    // Get last sent timesheet
	$timesheet = new timesheet();
    $result = $timesheet->getSent($employee_uid, 'DESC', 1);

    $lastSentTimesheet = $result == true && !empty($timesheet->result) ? $timesheet->result[0] : null;
    $lastSentTimesheetDatum = null;
	$lastSentTimesheetDatumMonat = null;
	if(!is_null($lastSentTimesheet) && (new DateTime($lastSentTimesheet->datum) == $date_last_month))
	{
		$lastSentTimesheetDatum = new DateTime($lastSentTimesheet->abgeschicktamum);
		$lastSentTimesheetDatumMonat = new DateTime($lastSentTimesheet->datum);

	}

    // Get last confirmed timesheet
	$timesheet = new timesheet();
    $result = $timesheet->getConfirmed($employee_uid, 'DESC', 1);
    $lastConfirmedTimesheet = $result == true && !empty($timesheet->result) ? $timesheet->result[0] : null;
    $lastConfirmedTimesheetDatum = null;
	$lastConfirmedTimesheetDatumMonat = null;
	if(!is_null($lastConfirmedTimesheet) && (new DateTime($lastConfirmedTimesheet->datum) == $date_last_month))
	{
		$lastConfirmedTimesheetDatum = new DateTime($lastConfirmedTimesheet->genehmigtamum);
		$lastConfirmedTimesheetDatumMonat = new DateTime($lastConfirmedTimesheet->datum);
	}
	
    // Erster VBT Zeitaufzeichnungspflicht
    $vbt = new vertragsbestandteil();
    $result = $vbt->getZaPflichtig($employee_uid, 'ASC', 1);
    $ersteZaPflicht = $result == true ? $vbt->result[0] : null;

    $cnt_isNotSent = 0;	        // counts all timesheets not sent by the employee
    $cnt_isNotConfirmed = 0;	// counts all timesheets not confirmed by supervisor
    $cnt_isNotCreated = 0;	    // counts missing timesheets between last timesheet date and last months date
/*
    // Monatsliste startet ab erster Zeitaufzeichnunsplficht (aber nicht vor GoLive-Datum)
    $monatslisteStartdatum = getMonatslisteStartdatum($ersteZaPflicht);

    $monat = new DateTime();

    while ($monat->format('Y-m') >= $monatslisteStartdatum->format('Y-m'))
    {
        $isZaPflichtig = $vbt->isZaPflichtig($employee_uid, $monat->format('Y-m-t'));

        // Wenn im Monat zeitaufzeichnungspflichtig ist
        if ($isZaPflichtig)
        {
            $ts = new Timesheet($employee_uid, $monat->format('m'), $monat->format('Y'));
*/
            /**
             * Vergangene nicht versendete / nicht erstellte Timesheet ermitteln.
             *
             * Aktuelles Monat nicht beruecksichtigen bei Ermittlung der unversendeten / nicht erstellten Timesheets.
             *
             * Wenn es mindestens ein genehmigtes Timesheet gibt, und davor noch Timesheets fehlen, dann nur die Timesheets
             * NACH der Genehmigung auf 'fehlen' oder 'unversendet' prüfen.
             **/
/*	
            if ((is_null($lastConfirmedTimesheet) || $monat->format('Y-m') > (new DateTime($lastConfirmedTimesheet->datum))->format('Y-m')) &&
                $monat->format('Y-m') != (new DateTime())->format('Y-m'))  // aktuelles Monat nicht beruecksichtigen
            {
                // Wenn Timesheet vorhanden ist
                if (!is_null($ts->timesheet_id))
                {
                    // flag if at least one timesheet is not sent
                    if (is_null($ts->abgeschicktamum))
                    {
                        $cnt_isNotSent++;
                    }

                    // flag if at least one timesheet is not confirmed
                    if (is_null($ts->genehmigtamum))
                    {
                        $cnt_isNotConfirmed++;
                    }
                }
                // Wenn kein Timesheet vorhanden ist
                else
                {
                    $cnt_isNotCreated++;
                }
            }
        }

        // Monat erhöhen
        $monat->sub(new DateInterval('P1M'));
    }
*/	
	$all_timesheets_notCreatedOrConfirmed = 0;
	if( $tstotalnotconfirmedcount = getNotConfirmedTimesheetCount($employee_uid) ) 
	{
		$all_timesheets_notCreatedOrConfirmed = $tstotalnotconfirmedcount;
	}
	
	// Flag if user has obligation to record times
	$isAllIn = false;

    // Flag if employee is karenziert
	$vbt = new vertragsbestandteil();
	$isKarenziert = $vbt->isKarenziert($employee_uid);

	// Get employees active Wochenstunden
	$vbt = new vertragsbestandteil();
	$vertragsstunden = $vbt->getWochenstunden($employee_uid) ? $vbt->result[0]->wochenstunden : 0;

	// Flag if employee has AllIn Contract
	$vbt = new vertragsbestandteil();
	$isAllin = $vbt->isAllin($employee_uid);

    // Flag if employee is AZG-relevant
    $vbt = new vertragsbestandteil();
    $azg = $vbt->isAzgRelevant($employee_uid);

    // Flag if employee is (actually) zeitaufzeichnungspflichtig
    $vbt = new vertragsbestandteil();
	$isZeitaufzeichnungspflichtig = $vbt->isZaPflichtig($employee_uid);

	// Get time- & holiday balances and SaldoAllin
	$time_balance = false;
	$holiday = false;
	$allInSaldo = false;
	
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
											);

				//AllinSaldo
				$allInSaldo = (
					isset($time_holiday_balance_arr->{$uc_employee_uid}->AllInSaldo)
					? $time_holiday_balance_arr->{$uc_employee_uid}->AllInSaldo
					: false
				);
			}
		}

	}
	else
	{
		// balance of time
		$time_balance = getCaseTimeZeitsaldo($employee_uid);	// float time balance OR string error OR bool false

		// holiday information
		$holiday = getCastTimeUrlaubssaldo($employee_uid);	// object with int urlaubsanspruch, float resturlaub, float aktueller stand OR string error OR bool false

		//request for allin
		if($isAllIn)
		{
			$allInSaldo = false;
			if(getCaseTimeSaldoAllIn($employee_uid))
				$allInSaldo = getCaseTimeSaldoAllIn($employee_uid)->salue1sum;
		}
	}

	// set css-class for time-balance field
	if (!$vertragsstunden)
	{
		$zeitsaldoklasse = ' danger';
	}
	elseif (!$isZeitaufzeichnungspflichtig)
	{
		$zeitsaldoklasse = '';
	}
	elseif (isset($vertragsstunden) && isset($time_balance) && $time_balance!==false)
	{
		if ($time_balance > $vertragsstunden * 3 || $time_balance < $vertragsstunden * -1)
			$zeitsaldoklasse = ' danger';
		elseif ($time_balance > $vertragsstunden * 1.5)
			$zeitsaldoklasse = ' warning';
		else
			$zeitsaldoklasse = '';
	}
	else {
			$zeitsaldoklasse = '';
	}
	
	// Get organisational unit of employee
	$benutzer_fkt = new Benutzerfunktion();
	$benutzer_fkt->getBenutzerFunktionByUid($employee_uid, 'oezuordnung', date('Y-m-d'));
	$employee_oe_kurzbz = (!empty($benutzer_fkt->result)) ? $benutzer_fkt->result[0]->oe_kurzbz : '';	// string oe

	// get organisational unit hierarchy
	//:NOTE: 2 different arrays of org units for different display in filter functionality (one is used by hidden val)
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

	if(!isset($allInSaldo))
			$allInSaldo = '';
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
		$obj->last_timesheet_sent = $lastSentTimesheetDatum;
		$obj->lastSentTimesheetDatumMonat = $lastSentTimesheetDatumMonat;
		$obj->last_timesheet_confirmed = $lastConfirmedTimesheetDatum;
		$obj->lastConfirmedTimesheetDatumMonat = $lastConfirmedTimesheetDatumMonat;
		$obj->all_timesheets_notCreated = $cnt_isNotCreated;
		$obj->all_timesheets_notSent = $cnt_isNotSent;
		$obj->all_timesheets_notConfirmed = $cnt_isNotConfirmed;
		$obj->all_timesheets_notCreatedOrConfirmed = $all_timesheets_notCreatedOrConfirmed;
		$obj->time_balance = $time_balance;
		$obj->holiday = $holiday;
		$obj->last_cntrl_timesheet_id = $last_cntrl_timesheet_id;
		$obj->last_cntrl_date = $last_cntrl_date;
		$obj->last_cntrl_uid = $last_cntrl_uid;
		$obj->last_cntrl_remark = $last_cntrl_remark;
		$obj->azg = $azg;	// boolean
		$obj->isAllIn = $isAllIn;
		$obj->isZeitaufzeichnungspflichtig = $isZeitaufzeichnungspflichtig;	// boolean
		$obj->vertragsstunden = $vertragsstunden;
		$obj->zeitsaldoklasse = $zeitsaldoklasse;
		$obj->salue1sum = $allInSaldo;
        $obj->isKarenziert = $isKarenziert;
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
		$obj->lastSentTimesheetDatumMonat = null;
		$obj->last_timesheet_confirmed = null;
		$obj->lastConfirmedTimesheetDatumMonat = null;
		$obj->all_timesheets_notCreated = $cnt_isNotCreated;
		$obj->all_timesheets_notSent = 0;
		$obj->all_timesheets_notConfirmed = 0;
		$obj->all_timesheets_notCreatedOrConfirmed = 0;
		$obj->time_balance = $time_balance;
		$obj->holiday = $holiday;
		$obj->last_cntrl_timesheet_id = $last_cntrl_timesheet_id; //empty
		$obj->last_cntrl_date = $last_cntrl_date;	//empty
		$obj->last_cntrl_uid = $last_cntrl_uid;	//empty
		$obj->last_cntrl_remark = $last_cntrl_remark;	//empty
		$obj->azg = $azg;	// boolean
		$obj->isAllIn = $isAllIn;
		$obj->isZeitaufzeichnungspflichtig = $isZeitaufzeichnungspflichtig; // boolean
		$obj->vertragsstunden = $vertragsstunden;
		$obj->zeitsaldoklasse = $zeitsaldoklasse;
		$obj->salue1sum = $allInSaldo;
		$obj->isKarenziert = $isKarenziert;
	}
	// * push to employees array
	$employees_data_arr []= $obj;
	$employees_loaded++;
	echo <<<EOJS
	<script>employees_loaded = {$employees_loaded};</script>

EOJS;
	
	ob_flush();
	flush();
}

// sort employees array by employees family name
usort($employees_data_arr, "sortEmployeesName");

// *********************************	FUNCTIONS
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

function sortEmployeesName($employee1, $employee2)
{
    return strcmp($employee1->nachname, $employee2->nachname);
}
?>

<script>

	// hide progress bar when site is done
	$("#progressbar_div").toggleClass("hidden");

</script>
	<!--************************************	TABLE with EMPLOYEES MONTHLIST INFORMATION	 -->
    <input type="checkbox" id="onlyfixemployees" name="onlyfixemployees"
		<?php echo ($showOnlyFixEmployees) ? ' checked="checked"' : ''; ?>
           onchange="fixOrAllEmployees()"/>
    <label for="onlyfixemployess">&nbsp;nur fix Angestellte</label><br>
    <input type="checkbox" id="lastMonthBeendete" name="lastMonthBeendete"
		<?php echo ($showLastMonthBeendeteEmployees) ? ' checked="checked"' : ''; ?>
           onchange="lastMonthBeendeteEmployees()"/>
    <label for="lastMonthBeendete">&nbsp;auch letzten Monat ausgeschiedene Angestellte (Dienstverhältnis-Ende / OE-Wechsel) </label>
	<table class="table table-condensed table-bordered tablesorter tablesort-active" id="tbl_monthlist_overview" role="grid">

		<!--************************************	TABLE HEAD	 -->
		<thead class="text-center">
			<tr class="table tablesorter-ignoreRow">
				<td><button type="button" id="btn_toggle_oe" class="btn btn-default btn-xs" onclick="toggleParentOE()">OE-Hierarchie anzeigen</button></td>
				<td></td>
                <td colspan="2" class="text-uppercase"><b>Zeitaufzeichnung <br><?php echo $monatsname[$sprache_index][(new DateTime())->format('m') - 1]. ' '. (new DateTime())->format('Y')?></b></td>
				<td colspan="2" class="text-uppercase"><b>Monatslisten <br><?php echo $monatsname[$sprache_index][$date_last_month->format('m') - 1]. ' '. $date_last_month->format('Y')?></b></td>
				<td colspan="1" class="text-uppercase"><b>Monatslisten <br>bis <?php echo $monatsname[$sprache_index][$date_last_month->format('m') - 1]. ' '. $date_last_month->format('Y')?></b></td>
				<td colspan="2" class="text-uppercase"><b>Insgesamt</b></td>
				<?php echo ($isPersonal) ? '<td class="text-uppercase">Letzte Kontrolle</td>' : '' ?>
			</tr>
			<tr>
				<th style="width: 10%">Organisationseinheit</th>
				<th>Mitarbeiter</th>

				<?php echo (!$isAllIn) ? '<th>AZG anwendbar</th>' : '' ?>
				<?php echo ($isAllIn) ? '<th>All In</th>' : '' ?>
				<th>Zeitaufzeichnungspflichtig</th>
				<!--<th>Status</th>-->
				<th>Abgeschickt am</th>
				<th>Genehmigt am</th>
				<th>Nicht genehmigt&nbsp;
					<i class="fa fa-question-circle-o" aria-hidden="true" style="white-space: pre-line;"
						data-toggle="tooltip" title="Anzahl nicht genehmigter Monatslisten bis inklusive <?php echo $monatsname[$sprache_index][$date_last_month->format('m') - 1]. ' '. $date_last_month->format('Y')?>.&#013;&#010;(auch solche, die nicht erstellt/abgeschickt und daher nicht genehmigt wurden)">
					</i>
				</th>
				<th>Zeitsaldo
					<i class="fa fa-question-circle-o" aria-hidden="true" style="white-space: pre-line;"
						data-toggle="tooltip" title="Aktueller Stand (Vortag) / Wochenarbeitszeit <?php echo ($isAllIn) ? '/ Allin Summe Studienjahr' : '' ?>&#010;Gelb: 1,5-fache Wochenarbeitszeit überschritten&#010;Rot: 3-fache Wochenarbeitszeit überschritten&#010;oder 1-fache Wochenarbeitszeit im Minus">
				</th>
				<th data-toggle="tooltip" title="Verfügbare Urlaubstage / Urlaubsanspruch">Urlaubstage
					<i class="fa fa-question-circle-o" aria-hidden="true" style="white-space: pre-line;"
						data-toggle="tooltip" title="Verfügbare Urlaubstage / Urlaubsanspruch&#010;Diese Anzeige berücksichtigt alle freigegebenen Urlaubstage">
					</i>
				</th>
				<?php echo ($isPersonal) ? '<th>Kontrolliert am</th>' : '' ?>
			</tr>
		</thead>

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
						<?php echo ($showcovidstatus) ? $covidhelper->getIconHtml($employee->uid) : ''; ?><a href="<?php echo APP_ROOT. 'addons/casetime/cis/timesheet.php?timesheet_id='. $employee->last_timesheet_id ?>"><?php echo $employee->nachname. ' '. $employee->vorname ?></a>
					</td>

					<!--obligated to record times (zeitaufzeichnungspflichtig)-->
					<?php if (!$isAllIn): ?>
						<?php if ($employee->azg): ?>
							<td class='text-center'>ja</td>
						<?php else: ?>
							<td class='text-center'>nein</td>
						<?php endif; ?>
					<?php endif; ?>

					<?php if ($isAllIn): ?>
						<?php if ($employee->isAllIn): ?>
							<td class='text-center'>ja</td>
						<?php else: ?>
							<td class='text-center'>nein
							</td>
						<?php endif; ?>
					<?php endif; ?>

					<?php if ($employee->isZeitaufzeichnungspflichtig): ?>
						<td class='text-center'>ja</td>
					<?php else: ?>
						<td class='text-center'>nein</td>
					<?php endif; ?>

					<!--sending date-->
					<!-- * only consider if timesheet date is date of last month -->
					<?php if ($date_last_month == $employee->lastSentTimesheetDatumMonat):?>

						<td class='text-center'>
							<?php echo (!is_null($employee->last_timesheet_sent)) ? $employee->last_timesheet_sent->format('d.m.Y') : '-' ?>
						</td>
					<?php else: ?>
						<td class='text-center'>-</td>
					<?php endif; ?>

					<!--confirmation date-->
					<!-- * only consider if timesheet date is date of last month -->
					<?php if ($date_last_month == $employee->lastConfirmedTimesheetDatumMonat): ?>
						<td class='text-center'>
							<?php echo (!is_null($employee->last_timesheet_confirmed)) ? $employee->last_timesheet_confirmed->format('d.m.Y') : '-' ?>
						</td>
					<?php else: ?>
						<td class='text-center'>-</td>
					<?php endif; ?>

					<!--amount of all timesheets not created AND not confirmed (includes not sent ones)-->
					<?php 
						//$all_timesheets_notCreatedOrConfirmed = $employee->all_timesheets_notCreated + $employee->all_timesheets_notConfirmed; 
						$all_timesheets_notCreatedOrConfirmed = $employee->all_timesheets_notCreatedOrConfirmed; 
					?>
					<td class='text-center <?php echo (!empty($all_timesheets_notCreatedOrConfirmed)) ? 'danger' : '' ?>'>
						<?php echo $all_timesheets_notCreatedOrConfirmed ?>
					</td>

					<!--balance of working hours on next account-->
					<td class='text-right<?php echo $employee->zeitsaldoklasse ?>'>
                    <?php echo ($employee->isKarenziert) ? '<span class="badge bg-secondary">Karenziert&nbsp;</span>' : ''; ?>
                    <?php
					echo (is_float($employee->time_balance)) ? $employee->time_balance : '-';
					echo (isset($employee->vertragsstunden)) ? ' / '.$employee->vertragsstunden : ' / -'; ?>
					<?php if ($isAllIn): ?>
						<?php if ($employee->isAllIn): ?>
							<?php echo (isset($employee->salue1sum) && (is_float($employee->salue1sum))) ? ' / '.$employee->salue1sum : ' / -';?>
						<?php endif; ?>
					<?php endif; ?>
						</td>

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
					<?php if($isTimesheetManager && $employee->isZeitaufzeichnungspflichtig): ?>
						<td>
							<span class="label pull-right text-uppercase" style="background-color: lightgrey; margin-left: 5px;"
								  data-toggle="tooltip" title="Noch keine Monatsliste vorhanden. Als Timesheetmanager können sie die erste anlegen.">erstanlage</span>
							<?php echo ($showcovidstatus) ? $covidhelper->getIconHtml($employee->uid) : ''; ?><a href="<?php echo APP_ROOT. 'addons/casetime/cis/timesheet.php' ?>?year=<?php echo $date_last_month->format('Y') ?>&month=<?php echo $date_last_month->format('m') ?>&employee_uid=<?php echo $employee->uid ?>&create=false"><?php echo $employee->nachname. ' '. $employee->vorname ?>
						</td>
					<?php else: ?>
						<td><?php echo ($showcovidstatus) ? $covidhelper->getIconHtml($employee->uid) : ''; ?><?php echo $employee->nachname. ' '. $employee->vorname ?></td>
					<?php endif; ?>

					<!--obligated to record times (zeitaufzeichnungspflichtig)-->
					<?php if (!$isAllIn): ?>
						<?php if ($employee->azg): ?>
							<td class='text-center'>ja</td>
						<?php else: ?>
							<td class='text-center'>nein</td>
						<?php endif; ?>
					<?php endif; ?>

					<?php if ($isAllIn): ?>
						<?php if ($employee->isAllIn): ?>
							<td class='text-center'>ja</td>
						<?php else: ?>
							<td class='text-center'>nein
							</td>
						<?php endif; ?>
					<?php endif; ?>

					<!--obligated to record times (zeitaufzeichnungspflichtig)-->
					<?php if ($employee->isZeitaufzeichnungspflichtig): ?>
						<td class='text-center'>ja</td>
					<?php else: ?>
						<td class='text-center'>nein</td>
					<?php endif; ?>


					<!--status-->
					<!--<td class='text-center'>nicht angelegt</td>-->

					<!--sending date-->
					<td class='text-center'>-</td>

					<!--confirmation date-->
					<td class='text-center'>-</td>

					<!--amount of all timesheets not sent-->
					<!--<td class='text-center'>-</td>-->

					<!--amount of all timesheets not confirmed-->
                    <td class='text-center <?php echo ($employee->all_timesheets_notCreated != 0) ? 'danger' : '' ?>'>
						<?php echo $employee->all_timesheets_notCreated ?>
                    </td>

						<td class='text-right<?php echo $zeitsaldoklasse ?>'>
                        <?php echo ($employee->isKarenziert) ? '<span class="badge bg-secondary">Karenziert&nbsp;</span>' : ''; ?>
                        <?php
                            echo (is_float($employee->time_balance)) ? $employee->time_balance : '-';
                            echo (isset($employee->vertragsstunden)) ? ' / '.$employee->vertragsstunden : ' / -';?>
						<?php if ($isAllIn): ?>
							<?php if ($employee->isAllIn): ?>
								<?php echo (isset($employee->salue1sum) && (is_float($employee->salue1sum))) ? ' / '.$employee->salue1sum : ' / -';?>
							<?php endif; ?>
						<?php endif; ?>
						</td>
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
