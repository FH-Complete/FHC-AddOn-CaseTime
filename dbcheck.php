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

if(!$rechte->isBerechtigt('basis/addon'))
{
	exit('Sie haben keine Berechtigung für die Verwaltung von Addons');
}

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


echo '<br>Aktualisierung abgeschlossen<br><br>';
echo '<h2>Gegenprüfung</h2>';


// Liste der verwendeten Tabellen / Spalten des Addons
$tabellen=array(
	"addon.tbl_casetime_zeitaufzeichnung"  => array("casetime_zeitaufzeichnung_id","uid","datum","zeit_start","zeit_ende","ext_id1","ext_id2","typ","sync","delete","zeitaufzeichung_id"),
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
