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
require_once('../include/timesheet.class.php');
require_once('../../../include/basis_db.class.php');
require_once('../../../include/benutzer.class.php');
require_once('../../../include/phrasen.class.php');
require_once('../../../include/sprache.class.php');
require_once('../../../include/globals.inc.php');
require_once('../../../include/mitarbeiter.class.php');

$uid = get_uid();
$db = new basis_db();
$sprache_obj = new sprache();
$sprache = getSprache();	// users language
$sprache_index = $sprache_obj->getIndexFromSprache($sprache);	// users language index (for globals.inc.php)
$p = new phrasen($sprache);

$date_last_month = new DateTime();	
$date_last_month->sub(new DateInterval('P1M'));
$date_last_month->modify('last day of this month');	// date obj of last month

// *********************************	CHECK if SUPERVISOR
$mitarbeiter = new Mitarbeiter();
$mitarbeiter->getUntergebene($uid);
$untergebenen_arr = $mitarbeiter->untergebene;

// check, if uid is a supervisor
if (empty($untergebenen_arr))
	die('Es sind Ihnen keine Mitarbeiter zugeteilt.');

// vars supervisor 
$benutzer = new Benutzer($uid);
$full_name = $benutzer->getFullName();	// string full name of supervisor

// vars employees
$employees_data_arr = array();	// array with timesheet data of all employees of supervisor
foreach($untergebenen_arr as $untergebener)
{
	// name of employee
	$benutzer = new Benutzer($untergebener);
	$empl_vorname = $benutzer->vorname;
	$empl_nachname = $benutzer->nachname;

	// all timesheets of employee
	$timesheet = new Timesheet();
	$timesheet_arr = $timesheet->loadAll($untergebener);

	// data of MOST RECENT timesheet BEFORE the actual month
	if (!empty($timesheet_arr))
	{
		$index = 0;
		$cnt_isNotSent = 0;	// counts all timesheets not sent by the employee
		$cnt_isNotConfirmed = 0;	// counts all timesheets of the employee not confirmed by supervisor
		$cnt_isNotCreated = 0;	// counts missing timesheets between last timesheet date and last months date
		
		// last timesheet date
		$last_timesheet_date = DateTime::createFromFormat('Y-m-d', $timesheet_arr[0]->datum);
		
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
				$last_timesheet_date = DateTime::createFromFormat('Y-m-d', $timesheet_arr[$index]->datum);	
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
	
	// collect all employees data to push to overall employees array
	$obj = new stdClass();
	// * full data of employee who has timesheets
	if (!empty($timesheet_arr))
	{
		$obj->vorname = $empl_vorname;
		$obj->nachname = $empl_nachname;
		$obj->last_timesheet_id = $last_timesheet_id;
		$obj->last_timesheet_date = $last_timesheet_date;
		$obj->last_timesheet_sent = $last_timesheet_sent;
		$obj->last_timesheet_confirmed = $last_timesheet_confirmed;
		$obj->all_timesheets_notCreated = $cnt_isNotCreated;
		$obj->all_timesheets_notSent = $cnt_isNotSent;
		$obj->all_timesheets_notConfirmed = $cnt_isNotConfirmed;
	}
	// * basic data of employee who has NO timesheets
	else
	{
		$obj->vorname = $empl_vorname;
		$obj->nachname = $empl_nachname;
		$obj->last_timesheet_id = null;
		$obj->last_timesheet_date = null;
		$obj->last_timesheet_sent = null;
		$obj->last_timesheet_confirmed = null;
		$obj->all_timesheets_notCreated = 0;
		$obj->all_timesheets_notSent = 0;
		$obj->all_timesheets_notConfirmed = 0;
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

<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<link rel="stylesheet" type="text/css" href="../../../vendor/twbs/bootstrap/dist/css/bootstrap.min.css">
	<link href="../../../vendor/components/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css"/>
	<!-- mottie tablesorter css -->
	<link href="../../../vendor/mottie/tablesorter/dist/css/theme.default.min.css" rel="stylesheet">
	<link href="../../../vendor/mottie/tablesorter/dist/css/jquery.tablesorter.pager.min.css" rel="stylesheet">	
	
	<script type="text/javascript" src="../../../vendor/components/jquery/jquery.min.js"></script>
	<script type="text/javascript" src="../../../vendor/twbs/bootstrap/dist/js/bootstrap.min.js"></script>
	<!-- mottie tablesorter js -->
	<script type="text/javascript" src="../../../vendor/mottie/tablesorter/dist/js/jquery.tablesorter.min.js"></script>
	<script type="text/javascript" src="../../../vendor/mottie/tablesorter/dist/js/jquery.tablesorter.widgets.min.js"></script>
	<script type="text/javascript" src="../../../vendor/mottie/tablesorter/dist/js/extras/jquery.tablesorter.pager.min.js"></script>
	<title>Timesheet Überblick</title>
	<style>
		.main {
			width: 85%;
		}
		.row {
			margin-left: 0px;
			margin-right: 0px;
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
/*		.table tbody{
			font-size: 11px;
		}*/
	</style>
	<script>
	$(document).ready(function() 
	{
		// init tablesorter
		$("#table_monthlists").tablesorter();

	});
	</script>
</head>

<body class="main" style="font-family: Arial, Helvetica, sans-serif; font-size: 13px;">
	
	<h3>Verwaltung Zeitaufzeichnung - Monatslisten</h3>
	<h4>Team von <?php echo $full_name ?></h4><br>
	
	<!--************************************	TEXTUAL INFORMATION	 -->

	<h4>Übersicht Monatslisten</h4>
	Überblick über die Zeiterfassung Ihrer MitarbeiterInnen des letzten Monats.<br>
	Klicken Sie auf einen Namen, um die Monatslisten der entsprechenden Person einzusehen und zu verwalten.
	<br><br><br>
	
	<!--************************************	TABLE with EMPLOYEES MONTHLIST INFORMATION	 -->
				
	<table class="table table-bordered tablesorter" id="table_monthlists">
		
		<!--************************************	TABLE HEAD	 -->
		<thead class="text-center">
			<tr class="table tablesorter-ignoreRow">
				<td></td>
				<td colspan="3" class="text-uppercase"><b><?php echo $monatsname[$sprache_index][$date_last_month->format('m') - 1]. ' '. $date_last_month->format('Y')?></b></td>
				<td colspan="1" class="text-uppercase"><b>bis <?php echo $monatsname[$sprache_index][$date_last_month->format('m') - 1]. ' '. $date_last_month->format('Y')?></b></td>
				<td colspan="4" class="text-uppercase"><b>Insgesamt</b></td>
			</tr>
			<tr>
				<th>Mitarbeiter</th>
				<th>Status</th>
				<th>Abgeschickt am</th>
				<th>Genehmigt am</th>
				<!--<th>Nicht angelegt/abgeschickt<br>(insgesamt)</th>-->
				<th>Nicht genehmigt</th>
				<th>Stundenübertrag</th>
				<th>Überstunden</th>
				<th>Konsumierte Urlaubstage</th>
			</tr>
		</thead>
		
		<!--************************************	TABLE BODY	 -->
		<tbody> 
			<?php foreach ($employees_data_arr as $employee): ?>
				
				<!--if employee has at least one timesheet-->
				<?php if (isset($employee->last_timesheet_id)): ?>
				<tr>
					<!--employee name & link to most last timesheet-->
					<td><a href="<?php echo APP_ROOT. 'addons/casetime/cis/timesheet.php?timesheet_id='. $employee->last_timesheet_id ?>"><?php echo $employee->nachname. ' '. $employee->vorname ?></a></td>
					
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
					
<!--					amount of all timesheets not created AND not sent
					<?php $all_timesheets_notCreatedOrSent = $employee->all_timesheets_notCreated + $employee->all_timesheets_notSent; ?>
					<td class='text-center'>
						<?php echo (!empty($all_timesheets_notCreatedOrSent)) ? $all_timesheets_notCreatedOrSent : '-' ?>
					</td>	-->
					
					<!--amount of all timesheets not confirmed-->
					<?php $all_timesheets_notCreatedOrConfirmed = $employee->all_timesheets_notCreated + $employee->all_timesheets_notConfirmed; ?>
					<td class='text-center <?php echo (!empty($all_timesheets_notCreatedOrConfirmed)) ? 'danger' : '' ?>'>
						<?php echo (!empty($all_timesheets_notCreatedOrConfirmed)) ? $all_timesheets_notCreatedOrConfirmed : '-' ?>
					</td>
					
					<!--balance of working hours on next account-->
					<td class='text-center'>20 h</td>
					
					<!--overtime hours-->
					<td class='text-center'>5,0 h</td>
					
					<!--holidays cosumed-->
					<td class='text-center'>17 / 25</td>						
				
				</tr>
				
				<!--if employee has NO timesheet yet-->
				<?php else: ?>
				<tr>
					<td><?php echo $employee->nachname. ' '. $employee->vorname ?></td>
					<td class='text-center'>nicht angelegt</td>
					<td class='text-center'>-</td>
					<td class='text-center'>-</td>
					<!--<td class='text-center'>-</td>-->	
					<td class='text-center'>-</td>
					<td class='text-center'>-</td>	
					<td class='text-center'>-</td>	
					<td class='text-center'>-</td>
				</tr>
				<?php endif; ?>
			<?php endforeach; ?>
		</tbody>
	</table>		
</body>
</html>