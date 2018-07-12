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
			width: 170px;
		}
		.panel-body-alleMonatslisten {
			padding: 0px;
		}
	</style>
</head>

<body class="main" style="font-family: Arial, Helvetica, sans-serif; font-size: 13px;">

	<h3>Zeitaufzeichnung - Monatslisten von Cristina Hainberger</h3><br>

	<h4>Aktuelle Monatsliste: September 2018</h4><br>
	
	<div class="row custom-panel">
		<div class="col-xs-8">
			<b>Monatsliste für September 2018 erstellen (xls):</b><br><br>
			Diese Liste ist nur für Ihren Bedarf und Ihre Eigenkontrolle und wird nicht weitergesendet.
		</div>
		<div class="col-xs-4"><br>
			<button type="button" class="btn btn-default pull-right">Monatsliste erstellen</button>
		</div>
	</div>
	<div class="row custom-panel" style="border-top: none;">
		<div class="col-xs-8">
			<b>Upload von Dokumenten</b><br><br>
			<!--IF-->
			Sie haben im September 2018 keine Fehlzeiten.
			<!--ELSE-->
			Bitte laden Sie Bestätigungen für folgende Fehlzeiten hoch:<br><br>
			<ul>
				<li>Krankenstand von 03.09. - 09.09.2018</li>
				<li>Dienstreise von 13.09. - 13.09.2018</li>
			</ul>
		</div>
		<div class="col-xs-4"><br>
			<button type="button" class="btn btn-default pull-right">Dokumente hochladen</button><br><br><br>
			<table class="table table-condensed pull-right">
				<tr>
					<td><a href="#">Krankenstandsbestaetigung_Hainberger.jpg</a></td>
					<td><a role="button" ><i class="fa fa-trash-o" aria-hidden="true"></i></a></td>
				</tr>
				<tr>
					<td><a href="#">Sammlung_Hainberger.jpg</a></td>
					<td><a role="button" ><i class="fa fa-trash-o" aria-hidden="true"></i></a></i></td>
				</tr>
				<tr>
					<td><a href="#">Sammlung2_Hainberger.jpg</a></td>
					<td><a role="button" ><i class="fa fa-trash-o" aria-hidden="true"></i></a></td>
				</tr>
			</table>
		</div>
	</div>
	<div class="row custom-panel" style="border-top: none;">
		<div class="col-xs-8">
			<b>Monatsliste abschließen</b><br><br>
			Wenn  Sie alle erforderlichen Dokumente hochgeladen haben, verschicken Sie Ihrem Vorgesetzten bis zum 05. des Folgemonats Ihre Monatsliste.<br>
			Nach dem Verschicken kann diese nicht mehr bearbeitet werden.
		</div>
		<div class="col-xs-4"><br>
			<button type="button" class="btn btn-default pull-right">Monatsliste verschicken</button>
		</div>
	</div><br><br>
	
	<!--IF frühere Monate nicht genehmigt-->
	<div class="alert alert-danger text-center" role="alert">
		<b>Sie müssen noch frühere Monatslisten genehmigen lassen!</b><br><br>
		Monatslisten müssen chronologisch an Vorgesetzte gesendet werden.<br>
		Bitte entnehmen Sie der unten stehenden Tabelle "Alle Monatslisten", welche Monate noch keine genehmigten Monatslisten haben.
	</div>
	
	<!--IF Monatsliste abgeschickt-->
	<div class="alert alert-success text-center" role="alert">
		<b>Ihre Monatsliste ist erfolgreich an Andreas Österreicher versendet worden!</b><br><br>
		Sobald Ihre Monatsliste genehmigt wurde, wird sie in der unteren Tabelle "Alle Monatslisten" mit einer grünen Ampel versehen.
	</div>
	
	<!--IF Monatsliste abgeschickt UND genehmigt-->
	<div class="alert alert-info text-center" role="alert">
		<b>Sie haben die Monatsliste für September 2018 bereits erstellt.</b><br><br>
		Sie können diese aber weiterhin für Ihren persönlichen Bedarf als Excel Datei herunterladen.
	</div>
	<br><br>
	
	<h4>Alle Monatslisten</h4><br>	
	
	<div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
  <div class="panel">
    <div class="text-center" role="tab" id="headingOne">
      <h4 class="panel-title">
        <a role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
          <h3>2018</h3>
        </a>
      </h4>
    </div>
    <div id="collapseOne" class="panel-collapse collapse in" role="tabpanel" aria-labelledby="headingOne">
		<div class="panel-body panel-body-alleMonatslisten">
			<table class="table table-bordered table-condensed">
				<tr>
					<th>Monat</th>
					<th>Dokumente</th>
					<th>Abgeschickt am</th>
					<th>Genehmigt</th>
				</tr>
				<tr>
					<td>September</td>
					<td>
						<a href="#">Krankenstandsbestaetigung_Hainberger.jpg</a><br>
						<a href="#">Sammlung_Hainberger.jpg</a><br>
						<a href="#">Sammlung_Hainberger2.jpg</a>
					</td>
					<td>03.10.2018</td>
					<td>img</td>
				</tr>
				<tr>
					<td>August</td>
					<td>Keine Dokumente vorhanden</td>
					<td>03.09.2018</td>
					<td>img</td>
				</tr>
				<tr>
					<td>Juli</td>
					<td>Keine Dokumente vorhanden</td>
					<td>03.08.2018</td>
					<td>img</td>
				</tr>
			</table>
      </div>
    </div>
  </div>
  <div class="panel">
    <div class="text-center" role="tab" id="headingTwo">
      <h4 class="panel-title">
        <a class="collapsed" role="button" data-toggle="collapse" data-parent="#accordion" href="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
          <h3>2017</h3>
        </a>
      </h4>
    </div>
    <div id="collapseTwo" class="panel-collapse collapse" role="tabpanel" aria-labelledby="headingTwo">
		<div class="panel-body panel-body-alleMonatslisten">
			Jahrestabelle 2
		</div>
    </div>
  </div>
</div>
	
</body>
</html>