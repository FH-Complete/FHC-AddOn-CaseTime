<?php
/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
require_once('../../../config/cis.config.inc.php');
require_once('../config.inc.php');
require_once('../include/timesheet.class.php');
require_once('../../../include/basis_db.class.php');
require_once('../../../include/benutzer.class.php');
require_once('../../../include/benutzerberechtigung.class.php');
require_once('../../../include/phrasen.class.php');
require_once('../../../include/sprache.class.php');
require_once('../include/functions.inc.php');

$uid = get_uid();
$sprache = getSprache();	
$p = new phrasen($sprache);

$rechte = new benutzerberechtigung();
$rechte->getBerechtigungen($uid);

// Permission check
// * access for personnel manager only
if (!$rechte->isBerechtigt('mitarbeiter/zeitsperre'))
{
	die($p->t('global/keineBerechtigungFuerDieseSeite'));
}


// Generate & download zip file with krankenstand documents and an overview cvs-list of krankenstand durations
if (isset($_POST['download']) && isset($_POST['from']) && isset($_POST['to']))
{
	if (!empty($_POST['from']) && !empty($_POST['to']))
	{
		$from = $_POST['from'];	// string date from when on downloading krankenstand attests
		$to = $_POST['to'];	// string date until when downloading krankenstand attests
		$zip = new ZipArchive();
		$timesheet = new Timesheet();
		$krankenstand_doc_arr = $timesheet->loadKrankenstaende_inPeriod($from, $to);	// array with dms information
		$uid_arr = array();	// array with uid of users who uploaded a krankenstand document
		$csv = "Vorname;Nachname;Krank von;Krank bis\n";	// string csv-list informing about duration of krankenstaende within the from-to-period
		$cnt = 1;	// counter for unique filename
		
		// Create temp zip file in temp dir
		$tmp_zip_file = tempnam(sys_get_temp_dir(), "FHC_KRANKENSTAENDE_"). '.zip';

		// Create zip archive
		if ($zip->open($tmp_zip_file, ZipArchive::CREATE) === TRUE) 
		{
			// Loop through all krankenstand documents
			foreach ($krankenstand_doc_arr as $krankenstand_doc)
			{		
				$filename = DMS_PATH. $krankenstand_doc->filename;
				$ext = pathinfo($krankenstand_doc->filename, PATHINFO_EXTENSION);
				$cnt = setLeadingZero($cnt);
					
				// Add krankenstand document to zip-file
				if (file_exists($filename))
				{
					$zip->addFile($filename, 'Krankenstand_'. $cnt. '_'. $krankenstand_doc->insertvon. '.'. strtolower($ext));
				}
				
				// Collect uid of users who uploaded a krankenstand document
				$uid_arr []= $krankenstand_doc->insertvon;
				
				$cnt++;
			}
			
			// Make uid array unique
			$uid_arr = array_unique($uid_arr);
			
			// Loop through all users who uploded a krankenstand document
			foreach ($uid_arr as $uid)
			{
				$benutzer = new Benutzer($uid);
				$vorname = $benutzer->vorname;
				$nachname = $benutzer->nachname;
			
				// Get duration of users krankenstand, if the krankenstand was within the from-to-period
				$timesheet = new Timesheet();	
				$krankenstand_tage_arr = $timesheet->getKrankenstaende_byUser_inPeriod($uid, $from, $to);
				
				// Add duration of users krankenstand to csv-list
				foreach ($krankenstand_tage_arr as $krankenstand_tage)
				{
					$csv .= "$vorname;$nachname;$krankenstand_tage->vondatum;$krankenstand_tage->bisdatum\n";
				}
			}
					
			// Add the csv-list to zip-archive
			$zip->addFromString("Krankenstaende_innerhalb_$from-$to.csv", $csv);
	
			// Close zip archive
			$zip->close();			
		
			// Download zip archive
			header('Content-Type: application/zip');
			header('Content-disposition: attachment; filename='. basename($tmp_zip_file));
			header('Content-Length: '. filesize($tmp_zip_file));
			readfile($tmp_zip_file);
			
			// Delete temp zip file
			unlink($tmp_zip_file);
		} 
		else 
		{
		    echo 'Fehler beim Zippen der Krankenstandsbestätigungen.';
		}
	}
	else 
	{
		echo 'Es muss ein Von- und ein Bis-Datum vorhanden sein';
	}
}

?>

<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<link rel="stylesheet" type="text/css" href="../../../vendor/components/jqueryui/themes/base/jquery-ui.min.css">
	<link rel="stylesheet" type="text/css" href="../../../skin/vilesci.css">
	<script type="text/javascript" src="../../../vendor/components/jquery/jquery.min.js"></script>
	<script type="text/javascript" src="../../../vendor/components/jqueryui/jquery-ui.min.js"></script>
	<script type="text/javascript" src="../../../vendor/components/jqueryui/ui/i18n/datepicker-de.js"></script>
	<title>Krankenstaende</title>	
	<script>
	$(document).ready(function(){
		var date = new Date();
		var date_default_from = new Date(date.getFullYear(), date.getMonth() - 1, 20);	// 20th of last month
		var date_default_to = new Date(date.getFullYear(), date.getMonth(), 19);	// 19th of actual month
		var dateFormat = "dd.mm.yy";
		
		from = $("#from")
			.datepicker()	// init datepicker
			.datepicker("setDate", date_default_from)	// set value to from-date
			.on("change", function() {
				to.datepicker("option", "minDate", getDate(this));	// restrict to-date not before from-date
			}),
		to = $("#to")
			.datepicker()
			.datepicker("setDate", date_default_to)
			.on("change", function() {
				from.datepicker("option", "maxDate", getDate(this));
			}); 

		function getDate(element) {
			var date;		
			try {
				date = $.datepicker.parseDate(dateFormat, element.value);
			} catch(error) {
				date = null;
			}
		  return date;
		}
	});

	</script>
</head>
<body class="Background_main">
	<h2>Krankenstände der MitarbeiterInnen</h2>
	
	<h3>ZIP-Download von Krankenstand-Bestätigungen</h3>
	<br>
	
	<form accept-charset="UTF-8" action="<?php $_SERVER['PHP_SELF'] ?>" method="POST">
		<table>
			<tr>
				<td style="width: 50px;">Von</td>
				<td><input id="from" type="text" name="from" value="">
				<td style="width: 30px; padding-left: 20px;">Bis</td>
				<td><input id="to" type="text" name="to" value="">
			</tr>
		</table>
		<br>
		<input type="submit" value="ZIP Download" name="download">
	</form>
</body>