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

require_once('../../../config/vilesci.config.inc.php');		//...VILESCI ok? oder muss im CIS config sein?
require_once('../../../include/functions.inc.php');
require_once('../../../include/person.class.php');
require_once('../../../include/benutzerberechtigung.class.php');
require_once('../include/timesheet.class.php');
require_once('../../../include/dokument.class.php');
require_once('../../../include/dms.class.php');
require_once('../../../include/phrasen.class.php');

$uid= get_uid();
$p = new phrasen();
$rechte = new benutzerberechtigung();
$rechte->getBerechtigungen($uid);
if(!$rechte->isBerechtigt('admin') && !$rechte->isBerechtigt('assistenz') && !$rechte->isBerechtigt('mitarbeiter'))
	die('Keine Berechtigung');


if (!isset($_GET['timesheet_id']) || empty($_GET['timesheet_id']))
{
	echo 'Es fehlt die Timesheet ID.';
}
else
	$timesheet_id = $_GET['timesheet_id'];

$isSuccess = false;		// flag for alerts, bool true e.g. if document upload succeded
$isError = false;		// flag for alerts, bool true e.g. if document upload failed

if(isset($_POST['submitBestaetigung']))
{
	$error = false;

	// Save document in DMS
	if(isset($_POST['fileupload']))
	{
		$ext = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
		$filename = uniqid();
		$filename.= ".". $ext;
		$uploadfile = DMS_PATH. $filename;

		if(move_uploaded_file($_FILES['file']['tmp_name'], $uploadfile))
		{
			if(!chgrp($uploadfile,'dms'))
				echo 'CHGRP failed';
			if(!chmod($uploadfile, 0774))
				echo 'CHMOD failed';
			
			$kategorie_kurzbz = isset($_POST['kategorie_kurzbz']) ? $_POST['kategorie_kurzbz'] : '';
			$dokument_kurzbz = isset($_POST['dokument_kurzbz']) ? $_POST['dokument_kurzbz'] : '';

			$dms = new dms();
			
			$dms->version = 0;
			$dms->filename = $filename;
			$dms->mimetype = $_FILES['file']['type'];
			$dms->name = $_FILES['file']['name'];
			$dms->kategorie_kurzbz = $kategorie_kurzbz;
			$dms->insertamum = date('Y-m-d H:i:s');
			$dms->insertvon = $uid;
			$dms->dokument_kurzbz = $dokument_kurzbz;
			
			if($dms->save(true))
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
			$err_msg = 'Fehler beim Hochladen der Datei. Haben Sie eine Datei ausgewählt?';
			$error = true;
		}
	}

	// Save document to certain timesheet, if no errors occured when saving to DMS
	if(isset($_FILES['file']['tmp_name']) && !$error)
	{		
		$timesheet = new Timesheet();

		if($timesheet->saveBestaetigung($timesheet_id, $dms_id, $uid))
		{
			$isSuccess = true;
			$msg = "Hochladen von <b>" . $dms->name . "</b> war erfolgreich!";
		}
		else
		{
			$isError = true;
			$err_msg = $timesheet->errormsg;
		}
	}
}

// Create array of all Bestätigungs-Typen (start with bst_) -> will be shown in dropdown
if(!empty($timesheet_id))
{
	$dokument = new dokument();
	$dokument->getAllDokumente();

	// loop through all documents to get only confirmations types for the typ-dropdown 
	foreach ($dokument->result as $key => $value)
	{
		// confirmation documents MUST start with 'bst_' (bestätigung)
		if(substr($value->dokument_kurzbz, 0, 4) != 'bst_')
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
		function onUploadRefresh()
		{
			window.opener.location = 'timesheet.php?uploadRefresh=true';
		}
	</script>
</head>

<body class="container">
	
	<h3>Dokumente hochladen</h3><br><br>	
	Bitte wählen Sie für jede Ihrer Abwesenheiten den entsprechenden Bestätigungstyp im Dropdown aus und laden die jeweils zugehörige Bestätigung hoch.<br><br>
	<div class="panel panel-default">
		<div class="panel-heading">
			<strong>Info</strong>
		</div>
		<div class="panel-body">
			Die Unterscheidung zwischen Arztbesuch und Krankenstand ist wichtig.<br>
			Arztbesuch: stundenweise, max. 1 Tag<br>
			Krankenstand: mehr als 3 Tage
		</div>
	</div><br>
		
	<form class="form-horizontal" method="POST" enctype="multipart/form-data" action="<?php echo $_SERVER['PHP_SELF']?>?timesheet_id=<?php echo $timesheet_id ?>">
		<input type='hidden' name='kategorie_kurzbz' id='kategorie_kurzbz' value='casetime'> 
		<input type='hidden' name='fileupload' id='fileupload'>

		<div class="form-group">
			<label for="typ" class="col-xs-2 control-label">Typ</label>
			<select id="typ" name='dokument_kurzbz' class="form-control" style='width:300px'>

			<?php foreach ($dokument->result as $dok): ?>
				<?php $onclick = "document.getElementById('titel').value='". $dok->dokument_kurzbz."';"; ?>
				<option value="<?php echo $dok->dokument_kurzbz ?>" onclick="<?php echo $onclick ?>" 
						<?php echo (isset($_GET['dokument_kurzbz']) && $_GET['dokument_kurzbz'] == $dok->dokument_kurzbz) ? 'selected' : ''; ?>>
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
} // END if(!empty($timesheet_id))

