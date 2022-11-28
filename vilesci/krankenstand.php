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
require_once('../../../include/mitarbeiter.class.php');
require_once('../../../include/dokument.class.php');
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


// Generate & download zip file with bestaetigung documents
// and - if bestaetigungstyp is krankenstand - an overview cvs-list of krankenstand durations
if (isset($_POST['download']) && isset($_POST['from']) && isset($_POST['to']) && isset($_POST['dokument_kurzbz']))
{
	if (!empty($_POST['from']) && !empty($_POST['to'] && !empty($_POST['dokument_kurzbz'])))
	{
		$from = $_POST['from'];	// string date from when on downloading bestaetigung
		$to = $_POST['to'];	// string date until when downloading bestaetigung
        $dokument_kurzbz = $_POST['dokument_kurzbz'];
        $dokument = new Dokument();
		$dokument->loadDokumenttyp($dokument_kurzbz);
		$dokument_bezeichnung = ltrim($dokument->bezeichnung, 'Bestätigung '); // trim 'Bestätigung' from bezeichnung

		$zip = new ZipArchive();
		$timesheet = new Timesheet();
		$bestaetigung_doc_arr = $timesheet->loadBestaetigungen_inPeriod($from, $to, $dokument_kurzbz);	// array with dms information
		$cnt = 1;	// counter for unique filename
		
		// Create temp zip file in temp dir
		$tmp_zip_file = tempnam(sys_get_temp_dir(), "FHC_BESTAETIGUNGEN_". $dokument_bezeichnung. "_");
		rename($tmp_zip_file, $tmp_zip_file .= '.zip');
		// Create zip archive
		// ---------------------------------------------------------------------------------------------------------
		if ($zip->open($tmp_zip_file, ZipArchive::CREATE) === TRUE) 
		{
			// Loop through all bestaetigungen documents
			foreach ($bestaetigung_doc_arr as $bestaetigung_doc)
			{		
				$filename = DMS_PATH. $bestaetigung_doc->filename;
				$ext = pathinfo($bestaetigung_doc->filename, PATHINFO_EXTENSION);
				$cnt = setLeadingZero($cnt);
					
				// Add krankenstand document to zip-file
				if (file_exists($filename))
				{
					$zip->addFile($filename, $dokument_bezeichnung. '_'. $cnt. '_'. $bestaetigung_doc->insertvon. '.'. strtolower($ext));
				}
				
				$cnt++;
			}

			// Add CSV file (only for krankenstaende)
			// ---------------------------------------------------------------------------------------------------------
			$tmp_csv_name = null;
            if ($dokument_kurzbz == 'bst_krnk')
            {
				$tmp_csv_name = tempnam(sys_get_temp_dir(), "Kontrolle_Krankenstaende_$from-$to"); // string csv-list informing about duration of krankenstaende within the from-to-period
				rename($tmp_csv_name, $tmp_csv_name .= '.csv');
				$csv_file = fopen($tmp_csv_name,'w');
				fputcsv($csv_file, array('Vorname', 'Nachname', 'Krank von', 'Krank bis', 'Abteilung', 'Unternehmen' ), ';');

                // Get all active and fix-employed employees
                $all_employee_uid_arr = array();
                $mitarbeiter = new Mitarbeiter();
                $mitarbeiter->getPersonal('true', false, false, 'true', false, null);
    
                foreach ($mitarbeiter->result as $mitarbeiter)
                {
                    if ($mitarbeiter->personalnummer > 0)	// filter out dummies
                    {
                        $all_employee_uid_arr []= $mitarbeiter->uid;
                    }
                }
                
                // Create entries in csv-list
                foreach ($all_employee_uid_arr as $uid)
                {
                    $benutzer = new Benutzer($uid);
                    $vorname = $benutzer->vorname;
                    $nachname = $benutzer->nachname;
                
                    // Get duration of employees krankenstand, if the krankenstand was within the from-to-period
                    $timesheet = new Timesheet();
                    $krankenstand_tage_arr = $timesheet->getKrankenstaende_byUser_inPeriod($uid, $from, $to);
                    
                    // Add duration of employees krankenstand to csv-list
                    foreach ($krankenstand_tage_arr as $krankenstand_tage)
                    {
						$mitarbeiter = new Mitarbeiter($uid);
						$mitarbeiter->getMitarbeiterKostenstelle($krankenstand_tage->vondatum, $krankenstand_tage->bisdatum);
						$kostenstellen_arr = $mitarbeiter->result;
						$unternehmen = [];
						$kostenstellen = [];
						foreach ($kostenstellen_arr as $kostenstelle)
						{
							$org = new Organisationseinheit();
							$org->getOERoot($kostenstelle->oekurzbz);
							
							$kostenstellen[] = $kostenstelle->bezeichnung;
							$unternehmen[] = $org->oe_kurzbz;
						}
						
						$csv_line = array($vorname, $nachname, $krankenstand_tage->vondatum, $krankenstand_tage->bisdatum, implode(', ', $kostenstellen), implode(', ', $unternehmen));
						$csv_line = array_map('utf8_decode', $csv_line);
						fputcsv($csv_file, $csv_line, ';');
					}
                }

				// Add the csv-list to zip-archive
				$zip->addFile($tmp_csv_name, "Kontrolle_Krankenstaende_$from-$to.csv");
				fclose($csv_file);
			}
            
			// Close zip archive
			// ---------------------------------------------------------------------------------------------------------
			$zip->close();

			// Download zip archive
			header('Content-Type: application/zip');
			header('Content-disposition: attachment; filename='. basename($tmp_zip_file));
			header('Content-Length: '. filesize($tmp_zip_file));
			readfile($tmp_zip_file);
			
			// Delete temp zip file
			unlink($tmp_zip_file);

			if (!is_null($tmp_csv_name) && file_exists($tmp_csv_name))
			{
				unlink($tmp_csv_name);
			}
		}
		else 
		{
		    echo 'Fehler beim Zippen der Krankenstandsbestätigungen.';
		}
	}
	else 
	{
		echo 'Es muss ein Bestaetigungstyp,ein Von- und ein Bis-Datum vorhanden sein';
	}
}

// Get all Bestaetigung-types for the Dropdownlist
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
	<h2>Bestätigungen der MitarbeiterInnen</h2>
	
	<h3>ZIP-Download von Bestätigungen</h3>
	<br>
	
	<form accept-charset="UTF-8" action="<?php $_SERVER['PHP_SELF'] ?>" method="POST">
		<table>
            <tr>
                <td style="width: 50px;">Typ</td>
                <td colspan="3">
                    <select id="typ" name='dokument_kurzbz' class="form-control" style='width:300px'>
		
		                <?php foreach ($dokument->result as $dok): ?>
                            <option value="<?php echo $dok->dokument_kurzbz ?>"
				                <?php echo
				                (!isset($_POST['dokument_kurzbz']) && $dok->dokument_kurzbz == 'bst_krnk') || // default Krankenstand Bestaetigung
				                ((isset($_POST['dokument_kurzbz']) && $_POST['dokument_kurzbz'] == $dok->dokument_kurzbz)) ? 'selected' : ''; ?>>
				                <?php echo $dok->bezeichnung ?>
                            </option>
		                <?php endforeach; ?>

                    </select>
                </td>
            </tr>
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