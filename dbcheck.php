<?php
/* Copyright (C) 2014 FH Technikum-Wien
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
 */
/**
 * FH-Complete Addon CaseTime Datenbank Check
 *
 * Prueft und aktualisiert die Datenbank
 */
require_once('../../config/system.config.inc.php');
require_once('../../include/basis_db.class.php');
require_once('../../include/functions.inc.php');
require_once('../../include/benutzerberechtigung.class.php');

// Datenbank Verbindung
$db = new basis_db();

echo '<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
	<link rel="stylesheet" href="../../skin/fhcomplete.css" type="text/css">
	<link rel="stylesheet" href="../../skin/vilesci.css" type="text/css">
	<title>Addon Datenbank Check</title>
</head>
<body>
<h1>Addon Datenbank Check</h1>';

$uid = get_uid();
$rechte = new benutzerberechtigung();
$rechte->getBerechtigungen($uid);

if(!$rechte->isBerechtigt('basis/addon', null, 'suid'))
{
	exit('Sie haben keine Berechtigung für die Verwaltung von Addons');
}


// ******** Berechtigungen Prüfen ************/
echo '<h2>Berechtigungen pruefen</h2>';
$neue=false;
$berechtigung_kurzbz=0;
$beschreibung=1;
$berechtigungen = array(
	array('addon/casetimeGenerateXLS','Generieren der Monatsliste im XLS-Format')
);

foreach($berechtigungen as $row)
{
	$qry = "SELECT * FROM system.tbl_berechtigung
			WHERE berechtigung_kurzbz=".$db->db_add_param($row[$berechtigung_kurzbz]);

	if($result = $db->db_query($qry))
	{
		if($db->db_num_rows($result)==0)
		{
			// Nicht vorhanden -> anlegen
			$qry_insert="INSERT INTO system.tbl_berechtigung (berechtigung_kurzbz, beschreibung) VALUES(".
				$db->db_add_param($row[$berechtigung_kurzbz]).','.
				$db->db_add_param($row[$beschreibung]).');';

			if($db->db_query($qry_insert))
			{
				echo '<br>'.$row[$berechtigung_kurzbz].' -> '.$row[$beschreibung].' <b>hinzugefügt</b>';
				$neue=true;
			}
			else
				echo '<br><span class="error">Fehler: '.$row[$berechtigung_kurzbz].' -> '.$row[$beschreibung].' hinzufügen nicht möglich</span>';
		}
	}
}
if($neue==false)
	echo '<br>Keine neuen Berechtigungen';




echo '<h2>Aktualisierung der Datenbank</h2>';

// Code fuer die Datenbankanpassungen

if(!$result = @$db->db_query("SELECT 1 FROM addon.tbl_casetime_zeitaufzeichnung"))
{

	$qry = "CREATE TABLE addon.tbl_casetime_zeitaufzeichnung
			(
				casetime_zeitaufzeichnung_id bigint NOT NULL,
				uid varchar(32),
				datum date,
				zeit_start time,
				zeit_ende time,
				ext_id1 bigint,
				ext_id2 bigint,
				typ varchar(16),
				sync boolean,
				delete boolean,
				zeitaufzeichnung_id integer
			);

	COMMENT ON TABLE addon.tbl_casetime_zeitaufzeichnung IS 'CaseTime Addon Synctabelle fuer Zeitaufzeichnungen';

	ALTER TABLE addon.tbl_casetime_zeitaufzeichnung ADD CONSTRAINT pk_casetime_zeitaufzeichnung PRIMARY KEY (casetime_zeitaufzeichnung_id);

	CREATE SEQUENCE addon.tbl_casetime_zeitaufzeichnung_casetime_zeitaufzeichnung_id_seq
	INCREMENT BY 1
	NO MAXVALUE
	NO MINVALUE
	CACHE 1;

	ALTER TABLE addon.tbl_casetime_zeitaufzeichnung ALTER COLUMN casetime_zeitaufzeichnung_id SET DEFAULT nextval('addon.tbl_casetime_zeitaufzeichnung_casetime_zeitaufzeichnung_id_seq');

	ALTER TABLE addon.tbl_casetime_zeitaufzeichnung ADD CONSTRAINT fk_benutzer_casetime_zeitaufzeichnung FOREIGN KEY (uid) REFERENCES public.tbl_benutzer(uid) ON DELETE RESTRICT ON UPDATE CASCADE;
	ALTER TABLE addon.tbl_casetime_zeitaufzeichnung ADD CONSTRAINT fk_zeitaufzeichnung_casetime_zeitaufzeichnung FOREIGN KEY (zeitaufzeichnung_id) REFERENCES campus.tbl_zeitaufzeichnung(zeitaufzeichnung_id) ON DELETE RESTRICT ON UPDATE CASCADE;

	GRANT SELECT, INSERT, UPDATE, DELETE ON addon.tbl_casetime_zeitaufzeichnung TO vilesci;
	GRANT SELECT, UPDATE ON addon.tbl_casetime_zeitaufzeichnung_casetime_zeitaufzeichnung_id_seq TO vilesci;
	";

	if(!$db->db_query($qry))
		echo '<strong>addon.tbl_casetime_zeitaufzeichnung: '.$db->db_last_error().'</strong><br>';
	else
		echo ' addon.tbl_casetime_zeitaufzeichnung: Tabelle addon.tbl_casetime_zeitaufzeichnung hinzugefuegt!<br>';

}

if(!$result = @$db->db_query("SELECT 1 FROM addon.tbl_casetime_zeitsperre"))
{

	$qry = "CREATE TABLE addon.tbl_casetime_zeitsperre
			(
				casetime_zeitsperre_id bigint NOT NULL,
				uid varchar(32),
				datum date,
				typ varchar(8)
			);

	COMMENT ON TABLE addon.tbl_casetime_zeitsperre IS 'CaseTime Addon Synctabelle fuer Urlaub, Krankenstand, etc';

	ALTER TABLE addon.tbl_casetime_zeitsperre ADD CONSTRAINT pk_casetime_zeitsperre PRIMARY KEY (casetime_zeitsperre_id);

	CREATE SEQUENCE addon.tbl_casetime_zeitsperre_casetime_zeitsperre_id_seq
	INCREMENT BY 1
	NO MAXVALUE
	NO MINVALUE
	CACHE 1;

	ALTER TABLE addon.tbl_casetime_zeitsperre ALTER COLUMN casetime_zeitsperre_id SET DEFAULT nextval('addon.tbl_casetime_zeitsperre_casetime_zeitsperre_id_seq');

	ALTER TABLE addon.tbl_casetime_zeitsperre ADD CONSTRAINT fk_benutzer_casetime_zeitsperre FOREIGN KEY (uid) REFERENCES public.tbl_benutzer(uid) ON DELETE RESTRICT ON UPDATE CASCADE;

	GRANT SELECT, INSERT, UPDATE, DELETE ON addon.tbl_casetime_zeitsperre TO vilesci;
	GRANT SELECT, UPDATE ON addon.tbl_casetime_zeitsperre_casetime_zeitsperre_id_seq TO vilesci;
	";

	if(!$db->db_query($qry))
		echo '<strong>addon.tbl_casetime_zeitsperre: '.$db->db_last_error().'</strong><br>';
	else
		echo ' addon.tbl_casetime_zeitsperre: Tabelle addon.tbl_casetime_zeitsperre hinzugefuegt!<br>';

}

// Tabelle zur Steuerung welche Teilnehmer gesynct werden und welche nicht
if(!$result = @$db->db_query("SELECT 1 FROM addon.tbl_casetime_gruppen"))
{

	$qry = "CREATE TABLE addon.tbl_casetime_gruppen
			(
				casetime_gruppen_id bigint NOT NULL,
				oe_kurzbz varchar(32),
				uid varchar(32),
				sync boolean NOT NULL default true
			);

	COMMENT ON TABLE addon.tbl_casetime_gruppen IS 'CaseTime Addon Organisationseinheiten die uebertragen werden sollen';

	ALTER TABLE addon.tbl_casetime_gruppen ADD CONSTRAINT pk_casetime_gruppen PRIMARY KEY (casetime_gruppen_id);

	CREATE SEQUENCE addon.tbl_casetime_gruppen_casetime_gruppen_id_seq
	INCREMENT BY 1
	NO MAXVALUE
	NO MINVALUE
	CACHE 1;

	ALTER TABLE addon.tbl_casetime_gruppen ALTER COLUMN casetime_gruppen_id SET DEFAULT nextval('addon.tbl_casetime_gruppen_casetime_gruppen_id_seq');

	ALTER TABLE addon.tbl_casetime_gruppen ADD CONSTRAINT fk_organisationseinheit_addon_casetime_gruppen FOREIGN KEY (oe_kurzbz) REFERENCES public.tbl_organisationseinheit(oe_kurzbz) ON DELETE RESTRICT ON UPDATE CASCADE;
	ALTER TABLE addon.tbl_casetime_gruppen ADD CONSTRAINT fk_benutzer_addon_casetime_gruppen FOREIGN KEY (uid) REFERENCES public.tbl_benutzer(uid) ON DELETE RESTRICT ON UPDATE CASCADE;

	GRANT SELECT, INSERT, UPDATE, DELETE ON addon.tbl_casetime_gruppen TO vilesci;
	GRANT SELECT, UPDATE ON addon.tbl_casetime_gruppen_casetime_gruppen_id_seq TO vilesci;
	";

	if(!$db->db_query($qry))
		echo '<strong>addon.tbl_casetime_gruppen: '.$db->db_last_error().'</strong><br>';
	else
		echo ' addon.tbl_casetime_gruppen: Tabelle addon.tbl_casetime_gruppen hinzugefuegt!<br>';

}

//Spalte datum_bis in addon.tbl_casetime_zeitaufzeichnung
if(!$result = @$db->db_query("SELECT datum_bis FROM addon.tbl_casetime_zeitaufzeichnung LIMIT 1"))
{
	$qry = "ALTER TABLE addon.tbl_casetime_zeitaufzeichnung ADD COLUMN datum_bis date;";

	if(!$db->db_query($qry))
		echo '<strong>addon.tbl_casetime_zeitaufzeichnung: '.$db->db_last_error().'</strong><br>';
	else
		echo '<br>addon.tbl_casetime_zeitaufzeichnung: Spalte datum_bis hinzugefuegt';
}

// Tabelle für die monatlich zu genehmigenden Timesheets
if(!$result = @$db->db_query("SELECT 1 FROM addon.tbl_casetime_timesheet"))
{

	$qry = "CREATE TABLE addon.tbl_casetime_timesheet
			(
				timesheet_id bigint NOT NULL,
				uid varchar(32) NOT NULL,
				datum date NOT NULL,
				insertamum timestamp DEFAULT now(),
				insertvon varchar(32),
				abgeschicktamum timestamp,
				genehmigtamum timestamp,
				genehmigtvon varchar(32)
			);

	COMMENT ON TABLE addon.tbl_casetime_timesheet IS 'CaseTime Addon Monatslisten, die genehmigt werden sollen';

	ALTER TABLE addon.tbl_casetime_timesheet ADD CONSTRAINT pk_casetime_timesheet PRIMARY KEY (timesheet_id);

	CREATE SEQUENCE addon.tbl_casetime_timesheet_timesheet_id_seq
	INCREMENT BY 1
	NO MAXVALUE
	NO MINVALUE
	CACHE 1;

	ALTER TABLE addon.tbl_casetime_timesheet ALTER COLUMN timesheet_id SET DEFAULT nextval('addon.tbl_casetime_timesheet_timesheet_id_seq');

	ALTER TABLE addon.tbl_casetime_timesheet ADD CONSTRAINT fk_benutzer_casetime_timesheet_uid FOREIGN KEY (uid) REFERENCES public.tbl_benutzer(uid) ON DELETE RESTRICT ON UPDATE CASCADE;
	ALTER TABLE addon.tbl_casetime_timesheet ADD CONSTRAINT fk_benutzer_casetime_timesheet_genehmigtvon FOREIGN KEY (genehmigtvon) REFERENCES public.tbl_benutzer(uid) ON DELETE RESTRICT ON UPDATE CASCADE;

	GRANT SELECT, INSERT, UPDATE, DELETE ON addon.tbl_casetime_timesheet TO vilesci;
	GRANT SELECT, UPDATE ON addon.tbl_casetime_timesheet_timesheet_id_seq TO vilesci;
	GRANT SELECT, INSERT, UPDATE, DELETE ON addon.tbl_casetime_timesheet TO web;
	GRANT SELECT, UPDATE ON addon.tbl_casetime_timesheet_timesheet_id_seq TO web;
	";

	if(!$db->db_query($qry))
		echo '<strong>addon.tbl_casetime_timesheet: '.$db->db_last_error().'</strong><br>';
	else
		echo ' addon.tbl_casetime_timesheet: Tabelle addon.tbl_casetime_timesheet hinzugefuegt!<br>';
}


// Tabelle für die DMS Dokumenten IDs, die zu den monatlich zu genehmigenden Timesheets angehängt werden müssen (Krankenstände etc)
if(!$result = @$db->db_query("SELECT 1 FROM addon.tbl_casetime_timesheet_dms"))
{

	$qry = "CREATE TABLE addon.tbl_casetime_timesheet_dms
			(
				timesheet_dms_id bigint NOT NULL,
				timesheet_id bigint NOT NULL,
				dms_id bigint NOT NULL,
				insertamum timestamp DEFAULT now(),
				insertvon varchar(32)
			);

	COMMENT ON TABLE addon.tbl_casetime_timesheet_dms IS 'CaseTime Addon Dokumenten IDs, die zu Monatslisten angehängt werden sollen';

	ALTER TABLE addon.tbl_casetime_timesheet_dms ADD CONSTRAINT pk_casetime_timesheet_dms PRIMARY KEY (timesheet_dms_id);

	CREATE SEQUENCE addon.tbl_casetime_timesheet_dms_timesheet_dms_id_seq
	INCREMENT BY 1
	NO MAXVALUE
	NO MINVALUE
	CACHE 1;

	ALTER TABLE addon.tbl_casetime_timesheet_dms ALTER COLUMN timesheet_dms_id SET DEFAULT nextval('addon.tbl_casetime_timesheet_dms_timesheet_dms_id_seq');

	ALTER TABLE addon.tbl_casetime_timesheet_dms ADD CONSTRAINT fk_casetime_timesheet_casetime_timesheet_dms FOREIGN KEY (timesheet_id) REFERENCES addon.tbl_casetime_timesheet(timesheet_id) ON DELETE RESTRICT ON UPDATE CASCADE;
	ALTER TABLE addon.tbl_casetime_timesheet_dms ADD CONSTRAINT fk_dms_casetime_timesheet_dms FOREIGN KEY (dms_id) REFERENCES campus.tbl_dms(dms_id) ON DELETE RESTRICT ON UPDATE CASCADE;

	GRANT SELECT, INSERT, UPDATE, DELETE ON addon.tbl_casetime_timesheet_dms TO vilesci;
	GRANT SELECT, UPDATE ON addon.tbl_casetime_timesheet_dms_timesheet_dms_id_seq TO vilesci;
	GRANT SELECT, INSERT, UPDATE, DELETE ON addon.tbl_casetime_timesheet_dms TO web;
	GRANT SELECT, UPDATE ON addon.tbl_casetime_timesheet_dms_timesheet_dms_id_seq TO web;
	";

	if(!$db->db_query($qry))
		echo '<strong>addon.tbl_casetime_timesheet_dms: '.$db->db_last_error().'</strong><br>';
	else
		echo ' addon.tbl_casetime_timesheet_dms: Tabelle addon.tbl_casetime_timesheet_dms hinzugefuegt!<br>';
}

// Add DMS category "casetime" (for timesheet upload-documents)
if ($result = @$db->db_query("SELECT 1 FROM campus.tbl_dms_kategorie WHERE kategorie_kurzbz = 'casetime';"))
{
	if ($db->db_num_rows($result) == 0)
	{
		$qry = "INSERT INTO campus.tbl_dms_kategorie (
					kategorie_kurzbz,
					bezeichnung,
					beschreibung,
					parent_kategorie_kurzbz,
					oe_kurzbz,
					berechtigung_kurzbz
			   ) VALUES(
					'casetime',
					'Casetime',
					'Dokumente aus der Zeiterfassung',
					'fas',
					'etw',
					NULL
			   );";
		if (!$db->db_query($qry))
			echo '<strong>campus.tbl_dms_kategorie '.$db->db_last_error().'</strong><br>';
		else
			echo ' campus.tbl_dms_kategorie: Added category "casetime"!<br>';
	}
}


// Add DMS category permissiongroup for DMS category "casetime"
if ($result = @$db->db_query("SELECT 1 FROM campus.tbl_dms_kategorie_gruppe WHERE kategorie_kurzbz = 'casetime';"))
{
	if ($db->db_num_rows($result) == 0)
	{
		$qry = "INSERT INTO campus.tbl_dms_kategorie_gruppe (
					kategorie_kurzbz,
					gruppe_kurzbz,
					insertamum,
					insertvon
			   ) VALUES(
					'casetime',
					'CMS_LOCK',
					NOW(),
					'dbcheck'
			   );";
		if (!$db->db_query($qry))
			echo '<strong>campus.tbl_dms_kategorie_gruppe '.$db->db_last_error().'</strong><br>';
		else
			echo ' campus.tbl_dms_kategorie_gruppe: Added category group "CMS_LOCK" to category "casetime"!<br>';
	}
}

// Add document types
if ($result = @$db->db_query("SELECT 1 FROM public.tbl_dokument WHERE dokument_kurzbz = 'bst_arzt';"))
{
	if ($db->db_num_rows($result) == 0)
	{
		$qry = "INSERT INTO public.tbl_dokument (
					dokument_kurzbz,
					bezeichnung,
					ext_id,
					bezeichnung_mehrsprachig,
					dokumentbeschreibung_mehrsprachig
			   ) VALUES(
					'bst_arzt',
					'Bestätigung Arztbesuch',
					NULL,
					'{\"Bestätigung Arztbesuch\",\"Bestätigung Arztbesuch\"}',
					NULL
			   );";
		if (!$db->db_query($qry))
			echo '<strong>public.tbl_dokument '.$db->db_last_error().'</strong><br>';
		else
			echo ' public.tbl_dokument: Added document type bst_arzt<br>';

		$qry = "INSERT INTO public.tbl_dokument (
					dokument_kurzbz,
					bezeichnung,
					ext_id,
					bezeichnung_mehrsprachig,
					dokumentbeschreibung_mehrsprachig
			   ) VALUES(
					'bst_bhrd',
					'Bestätigung Behörde',
					NULL,
					'{\"Bestätigung Behörde\",\"Bestätigung Behörde\"}',
					NULL
			   );";
		if (!$db->db_query($qry))
			echo '<strong>public.tbl_dokument '.$db->db_last_error().'</strong><br>';
		else
			echo ' public.tbl_dokument: Added document type bst_bhrd<br>';

		$qry = "INSERT INTO public.tbl_dokument (
					dokument_kurzbz,
					bezeichnung,
					ext_id,
					bezeichnung_mehrsprachig,
					dokumentbeschreibung_mehrsprachig
			   ) VALUES(
					'bst_dv',
					'Bestätigung Dienstverhinderung',
					NULL,
					'{\"Bestätigung Dienstverhinderung\",\"Bestätigung Dienstverhinderung\"}',
					NULL
			   );";
		if (!$db->db_query($qry))
			echo '<strong>public.tbl_dokument '.$db->db_last_error().'</strong><br>';
		else
			echo ' public.tbl_dokument: Added document type bst_dv<br>';

		$qry = "INSERT INTO public.tbl_dokument (
					dokument_kurzbz,
					bezeichnung,
					ext_id,
					bezeichnung_mehrsprachig,
					dokumentbeschreibung_mehrsprachig
			   ) VALUES(
					'bst_krnk',
					'Bestätigung Krankenstand',
					NULL,
					'{\"Bestätigung Krankenstand\",\"Bestätigung Krankenstand\"}',
					NULL
			   );";
		if (!$db->db_query($qry))
			echo '<strong>public.tbl_dokument '.$db->db_last_error().'</strong><br>';
		else
			echo ' public.tbl_dokument: Added document type bst_krnk<br>';


		$qry = "INSERT INTO public.tbl_dokument (
					dokument_kurzbz,
					bezeichnung,
					ext_id,
					bezeichnung_mehrsprachig,
					dokumentbeschreibung_mehrsprachig
			   ) VALUES(
					'bst_pfur',
					'Bestätigung Pflegeurlaub',
					NULL,
					'{\"Bestätigung Pflegeurlaub\",\"Bestätigung Pflegeurlaub\"}',
					NULL
			   );";
		if (!$db->db_query($qry))
			echo '<strong>public.tbl_dokument '.$db->db_last_error().'</strong><br>';
		else
			echo ' public.tbl_dokument: Added document type bst_pfur<br>';

	}
}

//add columns kontrolliertamum, kontrolliertvon, kontroll_notizen to tbl_casetime_timesheet
if(!$result = @$db->db_query("SELECT kontrolliertamum FROM addon.tbl_casetime_timesheet LIMIT 1"))
{
	$qry = "ALTER TABLE addon.tbl_casetime_timesheet ADD COLUMN kontrolliertamum timestamp;
			ALTER TABLE addon.tbl_casetime_timesheet ADD COLUMN kontrolliertvon varchar(32);
			ALTER TABLE addon.tbl_casetime_timesheet ADD COLUMN kontroll_notizen text;

			ALTER TABLE addon.tbl_casetime_timesheet ADD CONSTRAINT fk_benutzer_casetime_timesheet_kontrolliertvon FOREIGN KEY (kontrolliertvon) REFERENCES public.tbl_benutzer(uid) ON DELETE RESTRICT ON UPDATE CASCADE;";

	if(!$db->db_query($qry))
		echo '<strong>addon.tbl_casetime_timesheet: '.$db->db_last_error().'</strong><br>';
	else
		echo '<br>addon.tbl_casetime_timesheet: Spalten kontrolliertamum, kontrolliertvon, kontroll_notizen hinzugefuegt';
}

// INSERT, UPDATE und DELETE permissions for web User for addon.tbl_casetime_zeitaufzeichnung und SEQUENCE addon.tbl_casetime_zeitaufzeichnung_casetime_zeitaufzeichnung_id_seq
if($result = @$db->db_query("SELECT * FROM information_schema.role_table_grants WHERE table_name='tbl_casetime_zeitaufzeichnung' AND table_schema='public' AND grantee='web' AND privilege_type='INSERT'"))
{
	if($db->db_num_rows($result)==0)
	{
		$qry = "GRANT SELECT ON addon.tbl_casetime_zeitaufzeichnung TO web;";

		if(!$db->db_query($qry))
			echo '<strong>addon.tbl_casetime_zeitaufzeichnung Berechtigungen: '.$db->db_last_error().'</strong><br>';
		else
			echo '<br>SELECT Rechte fuer Tabelle addon.tbl_casetime_zeitaufzeichnung fuer web user gesetzt ';
	}
}

// INSERT, UPDATE und DELETE permissions for web User for addon.tbl_casetime_zeitsperre und SEQUENCE addon.tbl_casetime_zeitsperre_casetime_zeitsperre_id_seq
if($result = @$db->db_query("SELECT * FROM information_schema.role_table_grants WHERE table_name='tbl_casetime_zeitsperre' AND table_schema='public' AND grantee='web' AND privilege_type='INSERT'"))
{
	if($db->db_num_rows($result)==0)
	{
		$qry = "GRANT SELECT ON addon.tbl_casetime_zeitsperre TO web;";

		if(!$db->db_query($qry))
			echo '<strong>addon.tbl_casetime_zeitsperre Berechtigungen: '.$db->db_last_error().'</strong><br>';
		else
			echo '<br>SELECT Rechte fuer Tabelle addon.tbl_casetime_zeitsperre fuer web user gesetzt ';
	}
}

// Add permission for managing Casetime timesheets
if ($result = @$db->db_query("SELECT 1 FROM system.tbl_berechtigung WHERE berechtigung_kurzbz = 'addon/casetime_manageTimesheet';"))
{
	if ($db->db_num_rows($result) == 0)
	{
		$qry = "INSERT INTO system.tbl_berechtigung (berechtigung_kurzbz, beschreibung) VALUES('addon/casetime_manageTimesheet', 'Managen von Casetime timesheets');";
		if (!$db->db_query($qry))
			echo '<strong>system.tbl_berechtigung '.$db->db_last_error().'</strong><br>';
		else
			echo ' system.tbl_berechtigung: Added permission to manage Casetime timesheets.<br>';
	}
}

// Add index to addon.tbl_casetime_zeitaufzeichnung.uid
if ($result = $db->db_query("SELECT * FROM pg_class WHERE relname='idx_tbl_casetime_zeitaufzeichnung_uid'"))
{
	if ($db->db_num_rows($result) == 0)
	{
		$qry = "CREATE INDEX idx_tbl_casetime_zeitaufzeichnung_uid ON addon.tbl_casetime_zeitaufzeichnung USING btree (uid)";

		if (! $db->db_query($qry))
			echo '<strong>Indizes: ' . $db->db_last_error() . '</strong><br>';
		else
			echo '<br>Index fuer campus.tbl_casetime_zeitaufzeichnung.uid hinzugefuegt<br>';
	}
}

echo '<br>Aktualisierung abgeschlossen<br><br>';
echo '<h2>Gegenprüfung</h2>';


// Liste der verwendeten Tabellen / Spalten des Addons
$tabellen=array(
	"addon.tbl_casetime_gruppen"  => array("casetime_gruppen_id","oe_kurzbz","uid","sync"),
	"addon.tbl_casetime_zeitsperre"  => array("casetime_zeitsperre_id","uid","datum","typ"),
	"addon.tbl_casetime_zeitaufzeichnung"  => array("casetime_zeitaufzeichnung_id","uid","datum","zeit_start","zeit_ende","ext_id1","ext_id2","typ","sync","delete","zeitaufzeichnung_id", "datum_bis"),
	"addon.tbl_casetime_timesheet"  => array("timesheet_id","uid","datum","insertamum","insertvon","abgeschicktamum","genehmigtamum", "genehmigtvon", "kontrolliertamum", "kontrolliertvon", "kontroll_notizen"),
	"addon.tbl_casetime_timesheet_dms"  => array("timesheet_dms_id","timesheet_id","dms_id","insertamum", "insertvon"),
);


$tabs=array_keys($tabellen);
$i=0;
foreach ($tabellen AS $attribute)
{
	$sql_attr='';
	foreach($attribute AS $attr)
		$sql_attr.=$attr.',';
	$sql_attr=substr($sql_attr, 0, -1);

	if (!@$db->db_query('SELECT '.$sql_attr.' FROM '.$tabs[$i].' LIMIT 1;'))
		echo '<BR><strong>'.$tabs[$i].': '.$db->db_last_error().' </strong><BR>';
	else
		echo $tabs[$i].': OK - ';
	flush();
	$i++;
}
?>
