<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
require_once(dirname(__FILE__). '/../../../include/basis_db.class.php');
require_once(dirname(__FILE__). '/../../../include/datum.class.php');
require_once(dirname(__FILE__). '/../../../include/dms.class.php');

/**
 * Description of casetime_timesheet
 *
 * @author Cristina
 */
class Timesheet extends basis_db 
{
	public $new = false;			// boolean
	public $result = array();	
	
	//table columns
	public $timesheet_id;		// integer
	public $uid;				// varchar(32)
	public $datum;				// date
	public $insertamum;			// timestamp
	public $insertvon;			// varchar(32)
	public $abgeschicktamum;	// timestamp
	public $genehmigtamum;		// timestamp
	public $genehmigtvon;		// varchar(32)
	public $kontrolliertamum;	// timestamp
	public $kontrolliertvon;	// varchar(32)
	public $kontroll_notizen;	// text
	
	// Constructor
	public function __construct($uid = null, $month = null, $year = null)
	{
		parent::__construct();

		if (!is_null($uid) && !is_null($month) && !is_null($year))
			$this->load($uid, $month, $year);
	}
	
	// Load single timesheet for one person
	public function load($uid, $month, $year)
	{
		if (isset($uid) && !empty($uid))
		{
			$qry = '
				SELECT
					timesheet_id,
					uid,
					datum,
					insertamum,
					insertvon,
					abgeschicktamum,
					genehmigtamum,
					genehmigtvon,
					kontrolliertamum,
					kontrolliertvon,
					kontroll_notizen
				FROM
					addon.tbl_casetime_timesheet
				WHERE
					uid ='. $this->db_add_param($uid). '
				AND
					date_part(\'month\', datum) ='. $this->db_add_param($month, FHC_INTEGER). '
				AND 
                    date_part(\'year\', datum) ='. $this->db_add_param($year, FHC_INTEGER);
				
			if ($this->db_query($qry))
			{
				if ($row = $this->db_fetch_object())
				{
					$this->timesheet_id = $row->timesheet_id;
					$this->uid = $row->uid;
					$this->datum = $row->datum;
					$this->insertamum = $row->insertamum;
					$this->insertvon = $row->insertvon;
					$this->abgeschicktamum = $row->abgeschicktamum;
					$this->genehmigtamum = $row->genehmigtamum;
					$this->genehmigtvon = $row->genehmigtvon;
					$this->kontrolliertamum = $row->kontrolliertamum;
					$this->kontrolliertvon = $row->kontrolliertvon;
					$this->kontroll_notizen = $row->kontroll_notizen;
					
					$this->new = false;
					return true;
				}
				else
				{
					$this->errormsg = "Kein timesheet zu dieser UID oder zu diesem Monat vorhanden.";
					return false;
				}
			}
			else
			{
				$this->errormsg = "Fehler in der Abfrage zum Laden des timesheets.";
				return false;
			}	
		}
		else
		{
			$this->errormsg = "UID muss vorhanden und nicht leer sein";
			return false;
		}
	}
	
	// Load single timesheet by timesheet_id
	public function load_byID($timesheet_id)
	{
		if (isset($timesheet_id) && is_numeric($timesheet_id))
		{
			$qry = '
				SELECT
					timesheet_id,
					uid,
					datum,
					insertamum,
					insertvon,
					abgeschicktamum,
					genehmigtamum,
					genehmigtvon,
					kontrolliertamum,
					kontrolliertvon,
					kontroll_notizen
				FROM
					addon.tbl_casetime_timesheet
				WHERE
					timesheet_id ='. $this->db_add_param($timesheet_id, FHC_INTEGER);
				
			if ($this->db_query($qry))
			{
				if ($row = $this->db_fetch_object())
				{
					$this->timesheet_id = $row->timesheet_id;
					$this->uid = $row->uid;
					$this->datum = $row->datum;
					$this->insertamum = $row->insertamum;
					$this->insertvon = $row->insertvon;
					$this->abgeschicktamum = $row->abgeschicktamum;
					$this->genehmigtamum = $row->genehmigtamum;
					$this->genehmigtvon = $row->genehmigtvon;
					$this->kontrolliertamum = $row->kontrolliertamum;
					$this->kontrolliertvon = $row->kontrolliertvon;
					$this->kontroll_notizen = $row->kontroll_notizen;
					
					return true;
				}
				else
				{
					$this->errormsg = "Kein timesheet zu dieser timesheet_id vorhanden.";
					return false;
				}
			}
			else
			{
				$this->errormsg = "Fehler in der Abfrage zum Laden des timesheets.";
				return false;
			}	
		}
		else
		{
			$this->errormsg = "Timesheet_ID muss vorhanden und numerisch sein";
			return false;
		}
	}
	
	// Load all timesheets for one person
	public function loadAll($uid)
	{
		if (isset($uid) && !empty($uid))
		{
			$qry = '
				SELECT
					timesheet_id,
					uid,
					datum,
					insertamum,
					insertvon,
					abgeschicktamum,
					genehmigtamum,
					genehmigtvon,
					kontrolliertamum,
					kontrolliertvon,
					kontroll_notizen
				FROM
					addon.tbl_casetime_timesheet
				WHERE
					uid ='. $this->db_add_param($uid). '
				ORDER BY
					datum DESC';
				
			if ($this->db_query($qry))
			{
				while ($row = $this->db_fetch_object())
				{
					$obj = new stdClass();
					
					$obj->timesheet_id = $row->timesheet_id;
					$obj->uid = $row->uid;
					$obj->datum = $row->datum;
					$obj->insertamum = $row->insertamum;
					$obj->insertvon = $row->insertvon;
					$obj->abgeschicktamum = $row->abgeschicktamum;
					$obj->genehmigtamum = $row->genehmigtamum;
					$obj->genehmigtvon = $row->genehmigtvon;
					$obj->kontrolliertamum = $row->kontrolliertamum;
					$obj->kontrolliertvon = $row->kontrolliertvon;
					$obj->kontroll_notizen = $row->kontroll_notizen;
					
					$this->result[] = $obj;					
				}
				
				return $this->result;
			}
			else
			{
				$this->errormsg = "Fehler in der Abfrage zum Laden des timesheets.";
				return false;
			}	
		}
		else
		{
			$this->errormsg = "UID muss vorhanden und nicht leer sein";
			return false;
		}
	}
	
	// Save single timesheet for one person
	public function save($sent = false, $confirmed = false, $controlled = false)
	{	
		// Insert new timesheet
		if ($this->new)
		{
			$qry = ' 
				INSERT INTO
					addon.tbl_casetime_timesheet(
						uid,
						datum,
						insertvon
					)
				VALUES ('.
					$this->db_add_param($this->uid). ', '.
					$this->db_add_param($this->datum). ', '.
					$this->db_add_param($this->insertvon). '
				)
				RETURNING
					timesheet_id
			';
		}
		elseif ($sent || $confirmed || $controlled)
		{
			$qry = '
				UPDATE
					addon.tbl_casetime_timesheet
				SET '; 
			
			// Update when timesheet is sent
			if ($sent)
			{
				$qry.= 	
					'abgeschicktamum = '. $this->db_add_param($this->abgeschicktamum). ',';
			}
			
			// Update when timesheet is confirmed
			if ($confirmed)
			{
				$qry.= 
					'genehmigtamum='. $this->db_add_param($this->genehmigtamum). ','.
					'genehmigtvon='. $this->db_add_param($this->genehmigtvon). ',';
			}
			
			// Update when timesheet is controlled
			if ($controlled)
			{
				$qry.= 
					'kontrolliertamum='. $this->db_add_param($this->kontrolliertamum). ','.
					'kontrolliertvon='. $this->db_add_param($this->kontrolliertvon). ',';
				
				if (isset($this->kontroll_notizen))
				{
					$qry.= 
					'kontroll_notizen='. $this->db_add_param($this->kontroll_notizen). ',';
				}
			}
			
			$qry = rtrim($qry, ',');	// trim last comma
			$qry.= '
				WHERE 
					timesheet_id='. $this->db_add_param($this->timesheet_id). ';';
		}

		if ($this->new)
		{
			if($result = $this->db_query($qry))
			{
				$this->new = false;
				list($last_insert_timesheet_id) = $this->db_fetch_row($result);
				return $last_insert_timesheet_id;
			}
			else
			{
				$this->errormsg = 'Fehler beim Speichern der Daten';
				return false;
			}
		}
		else
		{
			if($this->db_query($qry))
			{
				return true;
			}
			else
			{
				$this->errormsg = 'Fehler beim Speichern der Daten';
				return false;
			}
		}
		
	}	
	
	// Get all times and reasons of absence which need to be reported. (of all users timesheets) 
	public function getAllAbsentTimes($uid)
	{
		// get absence for:
		// DIENSTVERHINDERUNG, KRANK and PFLEGEURLAUB (from tbl_zeitsperre) 
		// ARZTBESUCH, BEHÖRDE, DIENSTREISE and DIENSTREISEMT (from tbl_zeitaufzeichnung)
		if (isset($uid) && !empty($uid))
		{
			$qry = '
				SELECT
					timesheet_id,
					datum,
					zeitsperre_id,
					null AS zeitaufzeichnung_id,
					tbl_zeitsperretyp.beschreibung AS abwesenheitsgrund, 
					tbl_zeitsperretyp.zeitsperretyp_kurzbz AS abwesenheit_kurzbz, 
					vondatum AS von,
					bisdatum AS bis
				FROM
					addon.tbl_casetime_timesheet
				JOIN 
					campus.tbl_zeitsperre 
				ON 
					tbl_casetime_timesheet.uid = tbl_zeitsperre.mitarbeiter_uid
				JOIN
					campus.tbl_zeitsperretyp USING (zeitsperretyp_kurzbz)
				WHERE
					uid = '. $this->db_add_param($uid). '
				AND 
					zeitsperretyp_kurzbz IN (\'DienstV\', \'Krank\', \'PflegeU\')
				AND 
					bisdatum BETWEEN date_trunc(\'month\', datum::date) AND datum::date
					
				UNION
				SELECT
					timesheet_id,
					datum,
					null,
					zeitaufzeichnung_id,
					tbl_aktivitaet.beschreibung,
					aktivitaet_kurzbz,
					start,
					ende
				FROM
					addon.tbl_casetime_timesheet
				JOIN 
					campus.tbl_zeitaufzeichnung USING (uid)
				JOIN
					fue.tbl_aktivitaet USING (aktivitaet_kurzbz)
				WHERE
					uid = '. $this->db_add_param($uid). '
				AND 
					aktivitaet_kurzbz IN (\'Arztbesuch\', \'Behoerde\', \'Dienstreise\', \'DienstreiseMT\')
				AND 
					ende BETWEEN date_trunc(\'month\', datum::date) AND datum::date
				ORDER BY
					datum DESC
				';
			
			if ($this->db_query($qry))
			{
				while ($row = $this->db_fetch_object())
				{
					$obj = new stdClass();
					
					$obj->datum = $row->datum;
					$obj->abwesenheit_kurzbz = $row->abwesenheit_kurzbz;
					$obj->abwesenheitsgrund = $row->abwesenheitsgrund;
					$obj->von = $row->von;
					$obj->bis = $row->bis;
					$obj->timesheet_id = $row->timesheet_id;
					$obj->zeitsperre_id = $row->zeitsperre_id;
					$obj->zeitaufzeichnung_id = $row->zeitaufzeichnung_id;
					
					$this->result[] = $obj;					
				}			
				return $this->result;
			}
			else
			{
				$this->errormsg = "Fehler in der Abfrage zum Einholen aller Fehlzeiten eines users.";
				return false;
			}	
		}
		else
		{
			$this->errormsg = "UID muss vorhanden und nicht leer sein";
			return false;
		}	
	}
	
	// Save Bestätigung realted to a certain timesheet
	public function saveBestaetigung($timesheet_id, $dms_id, $uid)
	{
		if (isset($timesheet_id) && !empty($timesheet_id) &&
			isset($dms_id) && !empty($dms_id))
		{
			$qry = ' 
				INSERT INTO
					addon.tbl_casetime_timesheet_dms(
						timesheet_id,
						dms_id,
						insertvon
					)
				VALUES ('.
					$this->db_add_param($timesheet_id, FHC_INTEGER). ', '.
					$this->db_add_param($dms_id, FHC_INTEGER). ', '.
					$this->db_add_param($uid). '
				)
			';
			
			if($this->db_query($qry))
			{
				return true;
			}
			else
			{
				$this->errormsg = 'Fehler beim Speichern der Bestätigung zu einem Timesheet.';
				return false;
			}
		}
		else
		{
			$this->errormsg = "timesheet_id UND dms_id muss vorhanden und nicht leer sein";
			return false;
		}
	}
	
	// Load all Bestätigungen of a certain timesheet
	public function loadAllBestaetigungen_byTimesheet($timesheet_id)
	{
		if (isset($timesheet_id) && !empty($timesheet_id))
		{
			$qry = '
				SELECT
					dms_id,
					name,
					beschreibung,
					dokument_kurzbz
				FROM
					campus.tbl_dms_version
				JOIN
					addon.tbl_casetime_timesheet_dms
				USING (dms_id)
				JOIN
					campus.tbl_dms
				USING (dms_id)
				WHERE
					tbl_casetime_timesheet_dms.timesheet_id=' . $this->db_add_param($timesheet_id, FHC_INTEGER);
			
			if ($this->db_query($qry))
			{
				while ($row = $this->db_fetch_object())
				{
					$obj = new stdClass();
					
					$obj->dms_id = $row->dms_id;
					$obj->name = $row->name;
					$obj->beschreibung = $row->beschreibung;
					$obj->dokument_kurzbz = $row->dokument_kurzbz;
					
					$this->result[] = $obj;					
				}
				
				return $this->result;
			}
			else
			{
				$this->errormsg = "Fehler in der Abfrage zum Laden aller Bestätigungen des timesheets.";
				return false;
			}	
		}
		else
		{
			$this->errormsg = "Timesheet_ID muss vorhanden und nicht leer sein";
			return false;
		}
	}
	
	// Load all Bestätigungen of a certain timesheet
	public function loadAllBestaetigungen_byUser($uid)
	{
		if (isset($uid) && !empty($uid))
		{
			$qry = '
				SELECT
					timesheet_id,
					datum,
					dms_id,
					name,
					beschreibung,
					dokument_kurzbz,
					tbl_dokument.bezeichnung as dokument_bezeichnung
				FROM
					campus.tbl_dms_version
				JOIN
					addon.tbl_casetime_timesheet_dms
				USING (dms_id)
				JOIN
					campus.tbl_dms
				USING (dms_id)
				JOIN
					addon.tbl_casetime_timesheet
				USING (timesheet_id)
				JOIN
					public.tbl_dokument
				USING (dokument_kurzbz)
				WHERE
					tbl_casetime_timesheet.uid=' . $this->db_add_param($uid, FHC_STRING) . '
				ORDER BY
					datum DESC';
			
			if ($this->db_query($qry))
			{
				while ($row = $this->db_fetch_object())
				{
					$obj = new stdClass();
					
					$obj->timesheet_id = $row->timesheet_id;
					$obj->datum = $row->datum;
					$obj->dms_id = $row->dms_id;
					$obj->name = $row->name;
					$obj->beschreibung = $row->beschreibung;
					$obj->dokument_kurzbz = $row->dokument_kurzbz;
					$obj->dokument_bezeichnung = $row->dokument_bezeichnung;
					
					$this->result[] = $obj;					
				}
				
				return $this->result;
			}
			else
			{
				$this->errormsg = "Fehler in der Abfrage zum Laden aller Bestätigungen des users.";
				return false;
			}	
		}
		else
		{
			$this->errormsg = "UID muss vorhanden und nicht leer sein";
			return false;
		}
	}
	
	// Delete Bestätigung
	public function deleteBestaetigung($dms_id)
	{
		if (isset($dms_id) && is_numeric($dms_id))
		{
			// delete from tbl_casetime_timesheet_dms
			$qry = '
				BEGIN;
				DELETE FROM
					addon.tbl_casetime_timesheet_dms
				WHERE
					dms_id = ' .$this->db_add_param($dms_id, FHC_INTEGER) .';';
			
			if($this->db_query($qry))
			{
				$this->db_query('COMMIT;');
				$dms = new dms();
				
				// delete from campus.tbl_dms, campus.tbl_dms_version and fue.tbl_projekt_dokument
				if($dms->deleteDms($dms_id))
				{
					return true;
				}
				else
				{
					$this->db_query('ROLLBACK;');
					$this->errormsg = "Fehler beim Löschen des Dokuments aufgetreten.";
					return false;	
				}					
			}
		}
		else
		{
			$this->db_query('ROLLBACK;');
			$this->errormsg = "Fehler beim Löschen des Dokuments aufgetreten";
			return false;
		}
	}
	
	// Get user of timesheet
	public function getUser($timesheet_id)
	{
		if (isset($timesheet_id) && is_numeric($timesheet_id))
		{
			$qry = '
				SELECT
					uid
				FROM
					addon.tbl_casetime_timesheet
				WHERE
					timesheet_id ='. $this->db_add_param($timesheet_id, FHC_INTEGER);
		
		if ($this->db_query($qry))
			{
			if ($row = $this->db_fetch_object())
				{			
					return $this->result = $row->uid;
				}
			}
			else
			{
				$this->errormsg = "Fehler in der Abfrage des users zur timesheet.";
				return false;
			}	
		}
		else
		{
			$this->errormsg = "Timesheet_ID muss vorhanden und numerisch sein";
			return false;
		}
			
	}
	
	// Get user by dms id
	public function getUserByDMSId($dms_id)
	{
		if (isset($dms_id) && is_numeric($dms_id))
		{
			$qry = '
				SELECT
					uid
				FROM
					addon.tbl_casetime_timesheet
				JOIN
					addon.tbl_casetime_timesheet_dms
				USING
					(timesheet_id)
				JOIN 
					campus.tbl_dms
				USING
					(dms_id)
				WHERE
					dms_id ='. $this->db_add_param($dms_id, FHC_INTEGER);
		
		if ($this->db_query($qry))
			{
			if ($row = $this->db_fetch_object())
				{			
					return $this->result = $row->uid;
				}
			}
			else
			{
				$this->errormsg = "Fehler in der Abfrage des users zur dms_id.";
				return false;
			}	
		}
		else
		{
			$this->errormsg = "DMS_ID muss vorhanden und numerisch sein";
			return false;
		}
			
	}
	
	// Get the most recent timesheet controlling data
	public function getLatestControllingData($uid)
	{
		if (isset($uid) && !empty($uid))
		{
			$qry = '
				SELECT 
					* 
				FROM 
					addon.tbl_casetime_timesheet 
				WHERE 
					uid=' . $this->db_add_param($uid, FHC_STRING) . '
				AND 
					kontrolliertamum IS NOT NULL 
				ORDER BY 
					kontrolliertamum DESC LIMIT 1';
			
			if ($this->db_query($qry))
			{
		
				if ($row = $this->db_fetch_object())
				{
					$this->timesheet_id = $row->timesheet_id;
					$this->kontrolliertamum = $row->kontrolliertamum;
					$this->kontrolliertvon = $row->kontrolliertvon;
					$this->kontroll_notizen = $row->kontroll_notizen;

					return true;
				}
				else
				{
					// timesheet has NOT yet been controlled 
					return false;
				}
			}
			else
			{
				$this->errormsg = "Fehler in der Abfrage zu den letzten Timesheet-Kontrolldaten.";
				return false;
			}	
		}
		else
		{
			$this->errormsg = "UID muss vorhanden und nicht leer sein";
			return false;
		}
	}
}
