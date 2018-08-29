<?php
/* Copyright (C) 2018 Technikum-Wien
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
 * Authors:		Cristina Hainberger <cristina.hainberger@technikum-wien.at>
 */

require_once('../../../config/cis.config.inc.php');
require_once('../../../include/functions.inc.php');
require_once('../../../include/person.class.php');
require_once('../../../include/benutzerberechtigung.class.php');
require_once('../include/timesheet.class.php');
require_once('../../../include/dokument.class.php');
require_once('../../../include/dms.class.php');
require_once('../../../include/phrasen.class.php');

$uid = get_uid();
$p = new phrasen();

if (!isset($_GET['timesheet_id']) || empty($_GET['timesheet_id']))
{
	echo 'Es fehlt die Timesheet ID.';
}
else
	$timesheet_id = $_GET['timesheet_id'];


// Permission check
// * flag timesheet owner
$timesheet = new Timesheet();
$uid_of_timesheet_id = $timesheet->getUser($timesheet_id);
$isTimesheetOwner = ($uid == $uid_of_timesheet_id) ? true : false;		// bool for permission check; true if timesheet belongs to uid

// * for other permissions
$rechte = new benutzerberechtigung();
$rechte->getBerechtigungen($uid);

// * permission check
if (!$isTimesheetOwner &&	// timesheets owner
	!$rechte->isBerechtigt('mitarbeiter/zeitsperre', null, 'suid') &&	// personnel department
	!$rechte->isBerechtigt('admin'))	// admin
		die('Keine Berechtigung');

$isSuccess = false;		// flag for alerts, bool true e.g. if document upload succeded
$isError = false;		// flag for alerts, bool true e.g. if document upload failed

// get timesheets month and year
$timesheet = new Timesheet();
$timesheet->load_byID($timesheet_id);
$timesheet_date = new DateTime($timesheet->datum);
$year = $timesheet_date->format('Y');
$month = $timesheet_date->format('m');

if (isset($_POST['submitBestaetigung']))
{
	$error = false;

	// Save document in DMS
	if (isset($_POST['fileupload']))
	{
		$ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
		$filename = uniqid();
		$filename .= ".". $ext;
		$uploadfile = DMS_PATH. $filename;
		$mime = $_FILES['file']['type'];
	
		if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadfile))
		{		
			if ($mime == 'image/jpeg' || $mime == 'application/pdf')
			{
				$kategorie_kurzbz = isset($_POST['kategorie_kurzbz']) ? $_POST['kategorie_kurzbz'] : '';
				$dokument_kurzbz = isset($_POST['dokument_kurzbz']) ? $_POST['dokument_kurzbz'] : '';

				$dms = new dms();

				$dms->setPermission($uploadfile);

				$dms->version = 0;
				$dms->filename = $filename;
				$dms->mimetype = $_FILES['file']['type'];
				$dms->name = $_FILES['file']['name'];
				$dms->kategorie_kurzbz = $kategorie_kurzbz;
				$dms->insertamum = date('Y-m-d H:i:s');
				$dms->insertvon = $uid;
				$dms->dokument_kurzbz = $dokument_kurzbz;

				if ($dms->save(true))
				{
					$dms_id = $dms->dms_id;
				}
				else
				{
					echo 'Fehler beim Speichern der Daten';
					$error = true;
				}
			}
			else
			{
				$isError = true;
				$err_msg = 'Bitte wählen Sie eine Datei im PDF oder JPG Format.';
				$error = true;
			}
		}
		else
		{
			$isError = true;
			$err_msg = 'Fehler beim Hochladen der Datei. Haben Sie eine Datei ausgewählt?';
			$error = true;
		}
	}

	// Save document to certain timesheet, if no errors occured when saving to DMS
	if (isset($_FILES['file']['tmp_name']) && !$error)
	{
		$timesheet = new Timesheet();

		if ($timesheet->saveBestaetigung($timesheet_id, $dms_id, $uid))
		{
			$isSuccess = true;
			$msg = "Hochladen von <b>". $dms->name. "</b> war erfolgreich!";
		}
		else
		{
			$isError = true;
			$err_msg = $timesheet->errormsg;
		}
	}
}

// Create array of all Bestätigungs-Typen (start with bst_) -> will be shown in dropdown
if (!empty($timesheet_id))
{
	$dokument = new dokument();
	$dokument->getAllDokumente();

	// loop through all documents to get only confirmations types for the typ-dropdown
	foreach ($dokument->result as $key => $value)
	{
		// confirmation documents MUST start with 'bst_' (bestätigung)
		if (substr($value->dokument_kurzbz, 0, 4) != 'bst_')
		{
			unset($dokument->result[$key]);
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
	<title>Upload von Abwesenheitsdokumenten</title>
	<style>
		.col-xs-10
		{
			padding-left: 0;
		}
	</style>
	<script>
		// refreshes display of uploaded files in opener window (timesheet.php)
		function onUploadRefresh()
		{
			window.opener.location = 'timesheet.php?year=<?php echo $year ?>&month=<?php echo $month ?>&uploadRefresh=true';
		}
	</script>
</head>

<body class="container">
	
	<h3>Dokumente hochladen</h3><br><br>	
	Bitte wählen Sie für jede Ihrer Abwesenheiten den entsprechenden Bestätigungstyp im Dropdown aus und laden die jeweils zugehörige Bestätigung (im PDF oder JPG Format) hoch.
	<a role="button" data-toggle="modal" data-target="#modalUploadDocuments"><i class="fa fa-question-circle-o fa-lg" aria-hidden="true"></i></a><br><br><br>
	
	<!--POPUP WINDOW with document upload help information-->
		<div class="modal fade text-muted" tabindex="-1" role="dialog" id="modalUploadDocuments">
			<div class="modal-dialog" role="document">
				<div class="modal-content">
					<div class="modal-header">
						<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
						<h4 class="modal-title">Bestätigungen hochladen</h4>
					</div>
					<div class="modal-body">
						<p>
							1. Laden Sie für alle Ihre Abwesenheitszeiten jeweils SEPARAT die entsprechenden Bestätigungen hoch.<br>
							<small><em>Das heißt: KEINEN Sammelscan, bei dem in EINER Datei mehrere Bestätigungen eingescant sind.</em></small>
						</p>
						<p>
							2. Wählen Sie den für jede Bestätigung den entsprechenden Typ aus:<br>
						<ul>
							<li>Bestätigung Arztbesuch:<br><small><em>für Arztbesuche, Dauer max. 1 Tag.<br>Abwesenheit wurde im CIS in der täglichen Zeiterfassung eingetragen.</em></small></li>
							<li>Bestätigung Behörde:<br><small><em>für Behördenwege, Dauer max. 1 Tag.<br>Abwesenheit wurde im CIS in der täglichen Zeiterfassung eingetragen.</em></small></li>
							<li>Bestätigung Dienstverhinderung:<br><small><em>für Dienstverhinderungen, Dauer max. 1 Tag.<br>Abwesenheit wurde im CIS in der täglichen Zeiterfassung eingetragen.</em></small></li>
							<li>Bestätigung Krankenstand:<br><small><em>für Krankenstände/Spitalsaufenthalte, ab 3 Tage Dauer.<br>Abwesenheit wurde im CIS über die Zeitsperre eingetragen.</em></small></li>
							<li>Bestätigung Pflegeurlaub:<br><small><em>für Pflegeurlaube, ab 3 Tage Dauer.<br>Abwesenheit wurde im CIS über die Zeitsperre eingetragen.</em></small></li>
						</ul>
					</div>
					<div class="modal-footer">
						<button type="button" class="btn btn-default" data-dismiss="modal">Schließen</button>
					</div>
				</div><!-- /.modal-content -->
			</div><!-- /.modal-dialog -->
		</div><!-- /.modal -->
		
	<form class="form-horizontal" method="POST" enctype="multipart/form-data" action="<?php echo $_SERVER['PHP_SELF']?>?timesheet_id=<?php echo $timesheet_id ?>">
		<input type='hidden' name='kategorie_kurzbz' id='kategorie_kurzbz' value='casetime'> 
		<input type='hidden' name='fileupload' id='fileupload'>
		<div class="form-group">
			<label for="typ" class="col-xs-2 control-label">Typ</label>
			<select id="typ" name='dokument_kurzbz' class="form-control" style='width:300px'>

			<?php foreach ($dokument->result as $dok): ?>	
				<option value="<?php echo $dok->dokument_kurzbz ?>" 
						<?php echo (isset($_POST['dokument_kurzbz']) && $_POST['dokument_kurzbz'] == $dok->dokument_kurzbz) ? 'selected' : ''; ?>>
						<?php echo $dok->bezeichnung ?>
				</option>
			<?php endforeach; ?>

			</select>				
		</div>		
		<div class="form-group">
			<label for="file" class="col-xs-2 control-label">Bestätigung</label>
			<input type="file" id="file" name="file" class="col-xs-10">
		</div><br>
		<div class="form-group">
			<div class="col-xs-offset-2 col-xs-10">
				<button type="submit" class="btn btn-default" name="submitBestaetigung" value="upload" onclick="onUploadRefresh()">Hochladen</button>
			</div>
		</div>
	</form>
	
	<!-- success alert (e.g. on upload success) -->
	<?php if ($isSuccess): ?>
	<div class="alert alert-success text-center" role="alert">
		<?php echo $msg ?>
	</div>
	<?php endif; ?>
	
	<!-- error alert -->
	<?php if ($isError): ?>
	<div class="alert alert-danger text-center" role="alert">
		<b><?php echo $err_msg ?></b>
	</div>
	<?php endif; ?>
</body>

</html>

<?php
} // END if

