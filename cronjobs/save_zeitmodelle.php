<?php
/* Copyright (C) 2026 fhcomplete.org
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
 * Skript zum Speichern von Zeitmodellen von casetime
 */
require_once('../config.inc.php');
require_once('../../../config/vilesci.config.inc.php');
require_once('../../../include/functions.inc.php');
require_once('../../../include/benutzerberechtigung.class.php');
require_once('../include/casetime.class.php');
require_once('../../../include/mitarbeiter.class.php');
require_once('../../../include/vertragsbestandteil_zeitaufzeichnung.class.php');
require_once('../include/zeitmodell.class.php');


// Wenn das Script ueber die Kommandozeile aufgerufen wird, erfolgt keine Authentifizierung
if (php_sapi_name() != 'cli')
{
	$uid = get_uid();

	$rechte = new benutzerberechtigung();
	$rechte->getBerechtigungen($uid);

	if(!$rechte->isBerechtigt('admin'))
	{
		exit($rechte->errormsg);
	}
}

saveData();

/**
 * Sendet einen Request an den CaseTime Server um die Daten dort zu holen/speichern
 */
function saveData()
{
	// aktive Mitarbeiter holen
	$mitarbeiter = new mitarbeiter();
	if ($mitarbeiter->getZeitaufzeichnungspflichtig(true))
	{
		// alle Zeitmodelle holen
		$url = '/sync/get_zeitmodelle';
		$zeitmodelle = getWithCurl($url);

		if(isset($zeitmodelle->STATUS) && $zeitmodelle->STATUS=='OK' && isset($zeitmodelle->RESULT))
		{
			$url = '/sync/get_zeitmodelle_sachb';

			$mitarbeiter = $mitarbeiter->maData;

			$savedZeitmodelle = [];
			foreach ($mitarbeiter as $ma)
			{
				// Zeitmodelle für Mitarbeiter holen
				echo "Holen der Zeitmodelle für ".$ma->mitarbeiter_uid."...\n";
				$zeitmodelle_sachb = getWithCurl($url.'?sachb='.urlencode(strtoupper($ma->mitarbeiter_uid)));

				if (isset($zeitmodelle_sachb->STATUS) && $zeitmodelle_sachb->STATUS=='OK' && isset($zeitmodelle_sachb->RESULT))
				{
					$lastZeitmodell = end($zeitmodelle_sachb->RESULT);

					if ($lastZeitmodell && isset($lastZeitmodell[2]))
					{
						$zeitmodell_kurzbz = $lastZeitmodell[2];

						// geliefertes Zeitmodell finden
						$zm = getCaseTimeZeitmodellByBezeichnung($zeitmodelle->RESULT, $zeitmodell_kurzbz);
						$zeitmodell = createFHCZeitmodellObj($zm);

						if (!$zeitmodell)
						{
							echo "Fehler beim Erstellen des Zeitmodell Objects";
							continue;
						}

						if (in_array($zeitmodell_kurzbz, $savedZeitmodelle))
						{
							// Zeitmodell bereits hinzugefügt
							$zeitmodell_id = $zeitmodell->zeitmodell_id;
						}
						else
						{
							// Zeitmodell ergänzen
							if ($zeitmodell_id = $zeitmodell->save()) $savedZeitmodelle[] = $zeitmodell_kurzbz;
						}

						// Zeitaufzeichnung Vertragsbestandteile zu Zeitmodellen holen
						$vertragsbestandteil_zeitaufzeichnung = new vertragsbestandteil_zeitaufzeichnung();
						if ($vertragsbestandteil_zeitaufzeichnung->getFromStartdate(
							$ma->mitarbeiter_uid, date_format(date_create($lastZeitmodell[1]), 'Y-m-d'), 'ASC')
						) {
							foreach($vertragsbestandteil_zeitaufzeichnung->result AS $vtb)
							{
								if ($vtb->zeitmodell_id == $zeitmodell_id) continue;

								// Zeitmodell Mitarbeitern zuweisen
								$vtb->new = false;
								$vtb->zeitmodell_id = $zeitmodell_id;
								if ($vtb->save())
									echo "Zeitmodell ".$zeitmodell_kurzbz." der uid ".$ma->mitarbeiter_uid
										.", vertragsbestandteil ".$vtb->vertragsbestandteil_id.", zugewiesen\n";
							}
						}
						else
						{
							echo "Kein Zeitaufzeichnung Vertragsbestandteil für ".$ma->mitarbeiter_uid." gefunden, Datum ".$lastZeitmodell[1]."\n";
						}
					}
				}
				else
				{
					echo "Fehler beim Holen des Zeitmodells von ".$ma->mitarbeiter_uid.": "
					.(isset($zeitmodelle_sachb->RESULT) && is_string($zeitmodelle_sachb->RESULT) ? $zeitmodelle_sachb->RESULT : "")."\n";
				}
			}
		}
		else
		{
			echo "Fehler beim Holen aller Zeitmodelle";
		}
	}
}

function getWithCurl($url)
{
	$ch = curl_init();

	$url = CASETIME_SERVER.$url;

	curl_setopt($ch, CURLOPT_URL, $url ); //Url together with parameters
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //Return data instead printing directly in Browser
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT , 7); //Timeout after 7 seconds
	curl_setopt($ch, CURLOPT_USERAGENT , "FH-Complete CaseTime Addon");
	curl_setopt($ch, CURLOPT_HEADER, 0);

	$result = curl_exec($ch);
    
	if(curl_errno($ch))
	{
		echo 'Curl error: ' . curl_error($ch);
		curl_close($ch);
		return false;
	}
	else
	{
		curl_close($ch);
		return json_decode($result);
	}
}

function getCaseTimeZeitmodellByBezeichnung($zeitmodelle, $zeitmodell_kurzbz)
{
	foreach ($zeitmodelle as $zm)
	{
		if (isset($zm[0]) && $zm[0] === $zeitmodell_kurzbz) return $zm;
	}
	return null;
}

function createFHCZeitmodellObj($casetimeZm)
{
	// casetime liefert pro Zeitmodell ein Array, eigenschaften haben jede einen numerischen index
	$propertyIndexes = array(
		'zeitmodell_kurzbz' => 0,
		'beschreibung' => 1,
		'stundenanzahl' => 9,
	);

	$zm = new zeitmodell();

	foreach ($propertyIndexes as $property => $index)
	{
		if (!isset($casetimeZm[$index])) return null;
		if ($property == "stundenanzahl")
		{
			$casetimeZm[$index] = str_replace(',','.',$casetimeZm[$index]);
			if (!is_numeric($casetimeZm[$index])) return null;
		}
		$zm->{$property} = $casetimeZm[$index];
	}

	$insertUpdateVon = 'casetimesync';

	// prüfen, ob Zeitmodell schon vorhanden
	if ($zm->loadByZeitmodellKurzbz($casetimeZm[$propertyIndexes['zeitmodell_kurzbz']]))
	{
		$zm->new = false;
		$zm->updatevon = $insertUpdateVon;
		$zm->updateamum = date('Y-m-d H:i:s');
	}
	else
	{
		$zm->new = true;
		$zm->insertvon = $insertUpdateVon;
		$zm->insertamum = date('Y-m-d H:i:s');
	}

	$zm->aktiv = true;

	return $zm;
}
?>
