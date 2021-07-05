<?php
require_once('../../../config/vilesci.config.inc.php');
require_once('../config.inc.php');
require_once('../../../include/mitarbeiter.class.php');
require_once('../../../include/benutzerberechtigung.class.php');

$uid = get_uid();

$rechte = new benutzerberechtigung();
$rechte->getBerechtigungen($uid);

// Permission check
if (!$rechte->isBerechtigt('addon/casetime_manageTimesheet'))
{
	die($p->t('global/keineBerechtigungFuerDieseSeite'));
}

if(isset($_GET['work']))
{
	$work = $_REQUEST['work'];
	if(isset($_REQUEST['term']))
		$q = $_REQUEST['term'];
	else
		$q = $_REQUEST['q'];

	switch($work)
	{
		case 'getuser':
				$mitarbeiter = new mitarbeiter();

				if(!$mitarbeiter->search($q))
					die('Fehler beim Laden der Benutzer: '.$benutzer->errormsg);

				foreach($mitarbeiter->result as $row)
				{
					$item['uid']=$row->uid;
					$item['bezeichnung']=$row->nachname.' '.$row->vorname;
					$result[]=$item;
				}
				echo json_encode($result);
				break;
		default:
				die('Invalid Work Parameter');
	}
	exit;
}
?><!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<link rel="stylesheet"  href="../../../skin/vilesci.css" type="text/css">
	<link rel="stylesheet" href="../../../vendor/components/jqueryui/themes/base/jquery-ui.min.css" type="text/css"/>
	<script type="text/javascript" src="../../../vendor/components/jquery/jquery.min.js"></script>
	<script type="text/javascript" src="../../../vendor/components/jqueryui/jquery-ui.min.js"></script>
	<script type="text/javascript">
		$(document).ready(function()
		{
			var user = $("#input_user").val();

			if (user == '')
				$('#SyncDiv').hide();

			// Autocomplete Feld fuer initialisieren
			$("#input_user").autocomplete({
				source: "manual_sync.php?work=getuser",
				minLength:2,
				response: function(event, ui)
				{
					//Value und Label fuer die Anzeige setzen
					for(i in ui.content)
					{
						ui.content[i].value=ui.content[i].uid;
						ui.content[i].label=ui.content[i].bezeichnung+" "+ui.content[i].uid;
					}
				},
				select: function()
				{
					$('#SyncDiv').show();
				}
			});
		});
		function syncZeitsperren()
		{
			var user  = $('#input_user').value;
			window.open("sync_zeitsperren.php?uid="+user)
		}

		function syncZeitaufzeichnung()
		{
			var user  = $('#input_user').value;
			window.open("sync_zeitaufzeichnung.php?uid="+user)
		}
		</script>
		<title>CaseTime</title>

</head>
<body class="Background_main">
	<h2>CaseTime</h2>
	<?php
	$user = '';
	if (isset($_POST['resetDatum'])
	 && isset($_POST['input_user'])
	 && $_POST['input_user'] != ''
	)
	{
		// Update/Insert Timestamp in Zeitsperre und Zeitaufzeichnung korrigieren
		// damit ein vorzeitiges Abschicken möglich ist
		$user = $_POST['input_user'];
		$db = new basis_db();
		$qry = "UPDATE
					campus.tbl_zeitaufzeichnung
				SET
					insertamum = insertamum-'1 day'::interval,
					updatevon = ".$db->db_add_param($uid)."
				WHERE
					uid=".$db->db_add_param($user)."
					AND insertamum>=now()::date;
				UPDATE
					campus.tbl_zeitaufzeichnung
				SET
					updateamum = updateamum-'1 day'::interval,
					updatevon = ".$db->db_add_param($uid)."
				WHERE
					uid=".$db->db_add_param($user)."
					AND updateamum>=now()::date;
				UPDATE
					campus.tbl_zeitsperre
				SET
					insertamum = insertamum-'1 day'::interval,
					updatevon = ".$db->db_add_param($uid)."
				WHERE
					mitarbeiter_uid=".$db->db_add_param($user)."
					AND insertamum>=now()::date;
				UPDATE
					campus.tbl_zeitsperre
				SET
					updateamum = updateamum-'1 day'::interval,
					updatevon = ".$db->db_add_param($uid)."
				WHERE
					mitarbeiter_uid=".$db->db_add_param($user)."
					AND updateamum>=now()::date;";

		if ($db->db_query($qry))
			echo "<b>Zeiten wurden aktualisiert</b><br><br>";
	}
	?>
	Suche nach einer Person um die Daten ins CaseTime zu übertragen:
	<form method="POST">
	<table>
		<tr>
			<td style="width: 50px;">User</td>
			<td colspan="3">
				<input id="input_user" name="input_user" type="text" size="10" value="<?php echo $user;?>"/>
			</td>
		</tr>
	</table>
	<br /> <br />
	<div id="SyncDiv">
		Klicke um die Daten vom CIS ins CaseTime zu übertragen:<br />
		<input type="button" value="Zeitaufzeichnung übertragen" style="width:300px"  onclick="syncZeitaufzeichnung()"><br /> <br />
		<input type="button" value="Zeitsperren übertragen" style="width:300px" onclick="syncZeitsperren()">

		<h3>Datum zurücksetzen</h3>
		Nach der Übertragung müssen die Daten im CaseTime neu berechnet werden damit die Stunden korrekt angezeigt werden.<br>
		Damit die Monatsliste ab noch selben Tag abgeschickt werden kann, muss das Datum der neuen Einträge zurückgesetzt werden.
		<br /><br />
		Das darf nur durchgeführt werden, wenn zuvor die Daten übertragen und neuberechnet wurden!
		<br />
		<br />
		<input type="submit" value="Datum zurücksetzen" style="width:300px" name="resetDatum">
	</div>
	</form>
</body>
</html>
