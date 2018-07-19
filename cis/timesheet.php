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

// Input params
$uid = 'hainberg';
$month = 6;
$year = 2018;

$benutzer = new Benutzer($uid);
$full_name = $benutzer->getFullName();									// full name of user
$sprache_obj = new sprache();
$sprache = getSprache();												// users language
$sprache_index = $sprache_obj->getIndexFromSprache($sprache);			// users language index (for globals.inc.php)
$p = new phrasen($sprache);

$date_chosen = DateTime::createFromFormat('Y-m', $year. '-'. $month);	// date obj of input month/year 
$date_chosen->modify('last day of this month');							// date obj of input month/year with last day of month (to operate with diff method)
$date_chosen_year = $date_chosen->format('Y');
$date_chosen_month = $date_chosen->format('m');		

$monthyear_chosen = $date_chosen->format('Y-m');						// date string of input month/year

$date_actual = new DateTime();											// date obj of actual date
$monthyear_actual = $date_actual->format('Y-m');						// date string of actual date

$isFuture = false;														// boolean if chosen monthyear is in the future (after actual monthyear)

// *********************************	ALL TIMESHEETS 
// Get all timesheets
$timesheet_arr = new Timesheet();
$timesheet_arr = $timesheet_arr->loadAll($uid);
$isAllowed_createTimesheet = true;

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
$isDisabled_by_formerUnsentTimesheet = false;							// boolean if there are existing former unsent timesheets (up to the chosen date)
$isDisabled_by_missingTimesheet = false;								// boolean if there are missing timesheets (up to the chosen date)
$month_diff = 0;
$cnt = 0;

foreach ($timesheet_arr as $ts)
{	
	$ts_date = new DateTime($ts->datum);
	$ts_year = $ts_date->format('Y');
	$ts_month = $ts_date->format('m');
	$ts_monthyear = $ts_date->format('Y-m');
	$ts_isSent = (is_null($ts->abgeschicktamum)) ? false : true;
	
	// collect years (unique), where timesheet exist
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
		$month_diff = (($date_chosen_year - $ts_year) * 12) + ($date_chosen_month - $ts_month);
		// diff of 1 month is okay, as timesheet is mostly created for the month before
		if ($month_diff > 1)
			$isDisabled_by_missingTimesheet = true;
	}
	$cnt++;
}

$timesheet = new Timesheet();
$timesheet->getAllAbsentTimes($uid);
$absent_times_arr = $timesheet->result;
//var_dump($absent_times_arr);

// *********************************	ACTUAL TIMESHEET (by chosen month/year)
$timesheet = new Timesheet($uid, $month, $year);
$timesheet_id = $timesheet->timesheet_id;
$timesheet_datum = $timesheet->datum;									// date string of timesheet
$isSent = (is_null($timesheet->abgeschicktamum) ? false : true);		// boolean if timesheet was already sent
$isConfirmed = (is_null($timesheet->genehmigtamum) ? false : true);		// boolean if timesheet was already confirmed

// If timesheet is new and user is timely allowed to create first timesheet -> Save timesheet
if ($timesheet->new && $isAllowed_createTimesheet)
{
	// only if the chosen monthyear <= actual monthyear
	if ($monthyear_chosen <= $monthyear_actual)
	{
		if (!$isDisabled_by_missingTimesheet)
		{
			$timesheet->uid = $uid;
			$timesheet->datum = $date_chosen->format('Y-m-t'); 	//save with last day of month
			$timesheet->insertvon = $uid;

			if (!$timesheet->save())
			{
				echo 'Timesheet konnte nicht gespeichert werden';
			}
		}
	}
	// if chosen monthyear is in the future -> timesheet NOT saved
	else 
	{
		$isFuture = true;	// GUI fields will be disabled, if $isFuture is true
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
</head>

<body class="main" style="font-family: Arial, Helvetica, sans-serif; font-size: 13px;">

	<h3>Zeitaufzeichnung - Monatslisten von <?php echo $full_name ?></h3><br>
	
	<!--************************************	PANEL ACTUAL TIMESHEET	 -->

	<h4>Aktuelle Monatsliste: <?php echo $monatsname[$sprache_index][$date_chosen_month-1]. ' '. $date_chosen_year ?></h4><br>
	
	<div class="row custom-panel" <?php echo ($isFuture || !$isAllowed_createTimesheet) ? 'style="display: none;"' : '' ?>>
		<div class="col-xs-8">
			<b>Monatsliste für <?php echo $monatsname[$sprache_index][$date_chosen_month-1]. ' '. $date_chosen_year?> herunterladen (xls):</b><br><br>
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
			
			<!--loop throup absent times-->
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
				<?php endif; ?>
				</ul>			
			<?php endforeach; ?>	
				
			<!--if no absent times in actual timesheet found, display other text-->		
			<?php if($counter = 0): ?>	
				Sie haben im <?php echo $monatsname[$sprache_index][$date_chosen_month-1]. ' '. $date_chosen_year?> keine Fehlzeiten.
			<?php endif; ?>			
		</div>
		<div class="col-xs-4"><br>
			<button type="button" <?php echo ($isSent) ? 'disabled' : '' ?> class="btn btn-default pull-right">Dokumente hochladen</button><br><br><br>
			<table class="table table-condensed pull-right">
				<tr>
					<td><a href="#" <?php echo ($isSent) ? 'class="inactive"' : '' ?>>Krankenstandsbestaetigung_Hainberger.jpg</a></td>
					<td><a role="button" <?php echo ($isSent) ? 'class="inactive"' : '' ?>><i class="fa fa-trash-o" aria-hidden="true"></i></a></td>
				</tr>
				<tr>
					<td><a href="#" <?php echo ($isSent) ? 'class="inactive"' : '' ?>>Sammlung_Hainberger.jpg</a></td>
					<td><a role="button" <?php echo ($isSent) ? 'class="inactive"' : '' ?>><i class="fa fa-trash-o" aria-hidden="true"></i></a></i></td>
				</tr>
				<tr>
					<td><a href="#" <?php echo ($isSent) ? 'class="inactive"' : '' ?>>Sammlung2_Hainberger.jpg</a></td>
					<td><a role="button" <?php echo ($isSent) ? 'class="inactive"' : '' ?>><i class="fa fa-trash-o" aria-hidden="true"></i></a></td>
				</tr>
			</table>
		</div>
	</div>
	
	<div class="row custom-panel" style="border-top: none; <?php echo ($isConfirmed || $isFuture || $isDisabled_by_missingTimesheet || !$isAllowed_createTimesheet) ? 'display: none;' : '' ?>">
		<div class="col-xs-8">
			<b>Monatsliste abschließen</b><br><br>
			Wenn  Sie alle erforderlichen Dokumente hochgeladen haben, verschicken Sie Ihrem Vorgesetzten bis zum 05. des Folgemonats Ihre Monatsliste.<br>
			Nach dem Verschicken kann diese nicht mehr bearbeitet werden.
		</div>
		<div class="col-xs-4"><br>
			<button type="button" <?php echo ($isSent || $isDisabled_by_formerUnsentTimesheet) ? 'disabled' : '' ?> class="btn btn-default pull-right">Monatsliste verschicken</button>
		</div>
	</div><br><br>
	
	
	
	<!--************************************	ALERTS	 -->
	<!-- IF chosen date is in the future -->
	<?php if ($isFuture): ?>
	<div class="alert alert-danger text-center" role="alert">
		<b>Für <?php echo $monatsname[$sprache_index][$date_chosen_month-1] . ' ' .$date_chosen_year ?> kann noch keine Monatsliste angelegt werden!</b><br><br>
		Es können nur Monatslisten für den aktuellen oder vergangene Monate erstellt werden.
	</div>
	<?php endif; ?>
	
	<!-- IF timesheets are missing before actual monthyear -->	
	<?php if ($isDisabled_by_missingTimesheet && !$isFuture && !$isConfirmed): ?>
	<div class="alert alert-danger text-center" role="alert">
		<b>Für <?php echo $monatsname[$sprache_index][$date_chosen_month-1] . ' ' .$date_chosen_year ?> kann noch keine Monatsliste angelegt werden!</b><br><br>
		Monatslisten müssen chronologisch erstellt und an Vorgesetzte gesendet werden.<br>
		Bitte erstellen Sie erst die Monatslisten vergangener Monate.<br>
		
		<!--list months of missing timesheets and provide link to timesheet-->
		<?php 
		for ($i = $month_diff - 1; $i > 0; $i--)
		{
			echo '<br><a role="button" href="#" class="text-danger"><b>Monatsliste ' . $monatsname[$sprache_index][$date_chosen_month-1-$i]. ' '. $date_chosen_year. '</b> erstellen</a>';
		}
		?>
	</div> 
	<?php endif; ?> 
		
	<!-- IF former timesheets were not sent -->
	<?php if ($isDisabled_by_formerUnsentTimesheet): ?>
	<div class="alert alert-danger text-center" role="alert">
		<b>Sie müssen noch frühere Monatslisten versenden!</b><br><br>
		Monatslisten müssen chronologisch an Vorgesetzte gesendet werden.<br>
		Bitte entnehmen Sie der unten stehenden Tabelle "Alle Monatslisten", welche Monatslisten noch nicht versendet wurden.
	</div>
	<?php endif; ?>
	
	<!-- if timesheet is sent AND NOT confirmed -->
	<?php if ($isSent && !$isConfirmed): ?>
	<div class="alert alert-success text-center" role="alert">
		<b>Ihre Monatsliste für <?php echo $monatsname[$sprache_index][$date_chosen_month-1]. ' '. $date_chosen_year ?> ist erfolgreich an Andreas Österreicher versendet worden!</b><br><br>
		Sobald Ihre Monatsliste genehmigt wurde, wird sie in der unteren Tabelle "Alle Monatslisten" mit einer grünen Ampel versehen.
	</div>
	<?php endif; ?>
	
	<!-- IF timesheet is sent AND confirmed -->
	<?php if ($isSent && $isConfirmed): ?>
	<div class="alert alert-info text-center" role="alert">
		<b>Die Monatsliste für <?php echo $monatsname[$sprache_index][$date_chosen_month-1]. ' '. $date_chosen_year?> ist bereits genehmigt worden.</b><br><br>
		Sie können diese aber weiterhin für Ihren persönlichen Bedarf als Excel Datei herunterladen.
	</div>
	<?php endif; ?>
		
	<!-- IF not allowed to create timesheet (only checked when trying to create FIRST timesheet 2 or more months before actual monthyear) -->
	<?php if (!$isAllowed_createTimesheet): ?>
	<div class="alert alert-danger text-center" role="alert">
		<b>Für <?php echo $monatsname[$sprache_index][$date_chosen_month-1]. ' '. $date_chosen_year ?> kann keine Monatsliste angelegt werden!</b><br><br>
		Die erste Monatsliste kann nur für den aktuellen oder den vergangenen Monat erstellt werden.
	</div>
	<?php endif; ?>
	<br><br>
	

	
	
	
	<!--************************************	ALL TIMESHEETS - TABLE -->
	
<!--	<h4>Alle Monatslisten</h4><br>	-->
	
	<!--if there are present timesheets, show panel with all timesheets-->
	<?php if (!empty($timesheet_arr)): ?>
		<h4>Alle Monatslisten</h4><br>
		
		<div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
			
		<!--year counter: timesheets of the first (=actual) year will be fully displayed; other years are collapsed-->
		<?php $year_cnt = 1; ?>	
		
		<!--loop through years-->	
		<?php foreach ($timesheet_years as $year): ?>
			<div class="panel">
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
									<!--link to monthlist-->
									<td><a href="#"><?php echo $monatsname[$sprache_index][$ts_date->format('n')-1] . ' ' . $ts_date->format('Y') ?></a></td>
									
									<!--absence reasons & times-->
									<td>
									<?php foreach ($absent_times_arr as $absence): ?>
										<?php if ($ts->timesheet_id == $absence->timesheet_id): ?>
											<?php echo date_format(date_create($absence->von), 'd.m.Y') . ' - '. date_format(date_create($absence->bis), 'd.m.Y'). ': '. $absence->abwesenheitsgrund. "<br>" ?>
										<?php endif; ?>
									<?php endforeach; ?>
									</td>	
									
									<!--link to documents-->
									<td><a href="#">Krankenstandsbestaetigung_Hainberger.jpg</a><br></td>
									
									<!--sending date-->
									<?php if (!is_null($ts->abgeschicktamum)): ?>
										<?php $ts_date = new DateTime($ts->abgeschicktamum); ?>
										<td><?php echo $ts_date->format('d.m.Y') ?></td>
									<?php else: ?>
										<td>Nicht abgeschickt</td>
									<?php endif; ?>
										
									<!--confirmation status-->	
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
			</div>
		<?php $year_cnt++; ?>
		<?php endforeach; ?>	
		</div>
		
		
	<?php else: ?>
<!--	
		<div class="panel panel-default">
			 <div class="panel-body">
				Sobald Sie Ihre erste Monatsliste versendet haben, wird diese und alle weiteren hier aufgelistet werden.
			 </div>
		</div>-->
	<?php endif; ?>
	
</body>
</html>