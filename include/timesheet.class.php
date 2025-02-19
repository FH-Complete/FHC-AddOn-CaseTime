<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
require_once(dirname(__FILE__). '/../../../include/basis_db.class.php');
require_once(dirname(__FILE__). '/../../../include/datum.class.php');
require_once(dirname(__FILE__). '/../../../include/dms.class.php');
require_once(dirname(__FILE__). '/../../../include/functions.inc.php');
require_once(dirname(__FILE__). '/../../../include/zeitaufzeichnung.class.php');
require_once(dirname(__FILE__). '/../../../include/vertragsbestandteil.class.php');

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
	public $vorzeitig_abgeschickt; // boolean

	/** Constructor
	 *
	 * @param string $uid
	 * @param string $month	Number as string, like '03' for march.
	 * @param string $year	Number as string, like '2018'.
	 */
	public function __construct($uid = null, $month = null, $year = null)
	{
		parent::__construct();

		if (!is_null($uid) && !is_null($month) && !is_null($year))
			$this->load($uid, $month, $year);
	}

	/** Load single timesheet for one person
	 *
	 * @param string $uid
	 * @param string $month	Number as string, like '03' for march.
	 * @param string $year	Number as string, like '2018'.
	 * @return boolean	True on success. If true, sets timesheets information on instance of Timesheet.
	 */
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
					kontroll_notizen,
					vorzeitig_abgeschickt
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
					$this->vorzeitig_abgeschickt = $row->vorzeitig_abgeschickt;

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

	/** Load single timesheet by timesheet_id
	 *
	 * @param integer $timesheet_id
	 * @return boolean True on success. If true, sets timesheet information on instance of Timesheet.
	 */
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
					kontroll_notizen,
					vorzeitig_abgeschickt
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
					$this->vorzeitig_abgeschickt = $row->vorzeitig_abgeschickt;

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

	/** Load all timesheets for one person
	 *
	 * @param string $uid
	 * @return boolean	True on success. If true, returns object-array with all users timesheets.
	 */
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
					kontroll_notizen,
					vorzeitig_abgeschickt
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
					$obj->vorzeitig_abgeschickt = $row->vorzeitig_abgeschickt;

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

	/** Save single timesheet for one person
	 *
	 * @param boolean $sent	If true, update on sent-columns.
	 * @param boolean $confirmed	If true, update on confirmation-columns.
	 * @param boolean $controlled	If true, update on controlling-columns.
	 * @return boolean True on success. If true and timesheet is new, returns new timesheet_id.
	 */
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

    /**
     * Get confirmed timesheets only.
     *
     * DESC and limit 1 to retrieve last confirmed timesheet only.
     * ASC and limit 1 to retrieve frist confirmed timesheet only.
     *
     * @param $uid
     * @param string $order
     * @param null $limit
     * @return array|bool
     */
    public function getConfirmed($uid, $order = 'DESC', $limit = null)
    {
        if ($order !== 'ASC' && $order !== 'DESC')
        {
            $this->errormsg = 'Fehler beim Laden der Daten';
            return false;
        }

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
					kontroll_notizen,
					vorzeitig_abgeschickt
				FROM
					addon.tbl_casetime_timesheet
				WHERE
					uid ='. $this->db_add_param($uid). '
                AND genehmigtamum IS NOT NULL
				ORDER BY
					datum ' . $order;


            if (!is_null($limit))
            {
                $qry .= ' LIMIT ' . $this->db_add_param($limit, FHC_INTEGER);
            }

            if ($result = $this->db_query($qry))
            {
                $this->result = array();

                if ($this->db_num_rows($result) > 0)
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
                        $obj->vorzeitig_abgeschickt = $row->vorzeitig_abgeschickt;

                        $this->result[] = $obj;
                    }

                    return $this->result;
                }

                return true;
            }
            else
            {
                $this->errormsg = "Fehler in der Abfrage zum Genehmigen der timesheets.";
                return false;
            }
        }
        else
        {
            $this->errormsg = "UID muss vorhanden und nicht leer sein";
            return false;
        }
    }

    /**
     * Get unconfirmed timesheets only.
     *
     * DESC and limit 1 to retrieve last unconfirmed timesheet only.
     * ASC and limit 1 to retrieve frist unconfirmed timesheet only.
     *
     * @param $uid
     * @param string $order
     * @param null $limit
     * @return array|bool
     */
    public function getUnconfirmed($uid, $order = 'DESC', $limit = null)
    {
        if ($order !== 'ASC' && $order !== 'DESC')
        {
            $this->errormsg = 'Fehler beim Laden der Daten';
            return false;
        }

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
					kontroll_notizen,
					vorzeitig_abgeschickt
				FROM
					addon.tbl_casetime_timesheet
				WHERE
					uid ='. $this->db_add_param($uid). '
                AND genehmigtamum IS NULL
				ORDER BY
					datum ' . $order;


            if (!is_null($limit))
            {
                $qry .= ' LIMIT ' . $this->db_add_param($limit, FHC_INTEGER);
            }

            if ($result = $this->db_query($qry))
            {
				$this->result = array();
                if ($this->db_num_rows($result) > 0)
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
                        $obj->vorzeitig_abgeschickt = $row->vorzeitig_abgeschickt;

                        $this->result[] = $obj;
                    }

                    return $this->result;
                }

                return true;
            }
            else
            {
                $this->errormsg = "Fehler in der Abfrage zum Genehmigen der timesheets.";
                return false;
            }
        }
        else
        {
            $this->errormsg = "UID muss vorhanden und nicht leer sein";
            return false;
        }
    }

    /**
     * Get sent timesheets only.
     *
     * DESC and limit 1 to retrieve last sent timesheet only.
     * ASC and limit 1 to retrieve frist sent timesheet only.
     * @param $uid
     * @param string $order
     * @param null $limit
     * @return array|bool
     */
    public function getSent($uid, $order = 'DESC', $limit = null)
    {
        if ($order !== 'ASC' && $order !== 'DESC')
        {
            $this->errormsg = 'Fehler beim Laden der Daten';
            return false;
        }

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
					kontroll_notizen,
					vorzeitig_abgeschickt
				FROM
					addon.tbl_casetime_timesheet
				WHERE
					uid ='. $this->db_add_param($uid). '
                AND abgeschicktamum IS NOT NULL
				ORDER BY
					datum ' . $order;


            if (!is_null($limit))
            {
                $qry .= ' LIMIT ' . $this->db_add_param($limit, FHC_INTEGER);
            }

            if ($result = $this->db_query($qry))
            {
				$this->result = array();
                if ($this->db_num_rows($result) > 0)
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
                        $obj->vorzeitig_abgeschickt = $row->vorzeitig_abgeschickt;

                        $this->result[] = $obj;
                    }

                    return $this->result;
                }

                return true;
            }
            else
            {
                $this->errormsg = "Fehler in der Abfrage zum Genehmigen der timesheets.";
                return false;
            }
        }
        else
        {
            $this->errormsg = "UID muss vorhanden und nicht leer sein";
            return false;
        }
    }

    /**
     * Get unsent timesheets only.
     *
     * DESC and limit 1 to retrieve last unsent timesheet only.
     * ASC and limit 1 to retrieve frist unsent timesheet only.
     * @param $uid
     * @param string $order
     * @param null $limit
     * @return array|bool
     */
    public function getUnsent($uid, $order = 'DESC', $limit = null)
    {
        if ($order !== 'ASC' && $order !== 'DESC')
        {
            $this->errormsg = 'Fehler beim Laden der Daten';
            return false;
        }

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
					kontroll_notizen,
					vorzeitig_abgeschickt
				FROM
					addon.tbl_casetime_timesheet
				WHERE
					uid ='. $this->db_add_param($uid). '
                AND abgeschicktamum IS NULL
				ORDER BY
					datum ' . $order;


            if (!is_null($limit))
            {
                $qry .= ' LIMIT ' . $this->db_add_param($limit, FHC_INTEGER);
            }

            if ($result = $this->db_query($qry))
            {
				$this->result = array();
                if ($this->db_num_rows($result) > 0)
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
                        $obj->vorzeitig_abgeschickt = $row->vorzeitig_abgeschickt;

                        $this->result[] = $obj;
                    }

                    return $this->result;
                }

                return true;
            }
            else
            {
                $this->errormsg = "Fehler in der Abfrage zum Genehmigen der timesheets.";
                return false;
            }
        }
        else
        {
            $this->errormsg = "UID muss vorhanden und nicht leer sein";
            return false;
        }
    }

	/** Get all times and reasons of absence which need to be reported. (of all users timesheets)
	 *
	 * @param string $uid
	 * @return boolean	True on success. If true, returns object-array with absences.
	 */
	public function getAllAbsentTimes($uid, $timesheet_id = '')
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
					zeitsperretyp_kurzbz IN (\'DienstV\', \'Krank\', \'PflegeU\', \'CovidSB\',  \'CovidKS\')
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
					ende::date BETWEEN date_trunc(\'month\', datum::date) AND datum::date
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

				// If timesheet is given, check absences only for that timesheet
				if (is_numeric($timesheet_id))
				{
					foreach ($this->result as $key => $absence)
					{
						if ($absence->timesheet_id != $timesheet_id)
						{
							unset($this->result[$key]);
						}
					}

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

	/** Check if user made any inserts/updates today concerning the month period of given date
	 *
	 * @param string $uid	User ID.
	 * @param DateTime $date_monthlist	Casetime entries will be checked from first to last of the dates' month.
	 * @return boolean	True if at least one Casetime insert/update found.
	 * **/
	public function hasNewOrChangedTimesToday($uid, $date_monthlist)
	{
		if (isset($uid) && !empty($uid))
		{
			if (isset($date_monthlist) && $date_monthlist instanceof DateTime)
			{
				$first_day_monthlist = $date_monthlist->modify('first day of this month');
				$first_day_monthlist = $first_day_monthlist->format('Y-m-d');

				$last_day_monthlist = $date_monthlist->modify('last day of this month');
				$last_day_monthlist = $last_day_monthlist->format('Y-m-d');

				$qry = "
					SELECT EXISTS(
						SELECT
							uid,
							aktivitaet_kurzbz as casetime_eintrag,
							start,
							ende,
							insertamum,
							updateamum
						-- Zeitaufzeichnung entries
						FROM
							campus.tbl_zeitaufzeichnung
						-- ...for the given employee
						WHERE
							uid = ". $this->db_add_param($uid). "
						-- ...inserted or updated today
						AND
						(
							insertamum::date = NOW()::date
						OR
							updateamum::date = NOW()::date
						)
						-- ...for the given monthlist
						AND
							start >= '". $first_day_monthlist. "'
						AND
							ende <= '". $last_day_monthlist. "'
						UNION
						SELECT
							mitarbeiter_uid,
							zeitsperretyp_kurzbz,
							vondatum,
							bisdatum,
							insertamum,
							updateamum
						-- Zeitsperre entries
						FROM
							campus.tbl_zeitsperre
						-- ...for the given employee
						WHERE
							mitarbeiter_uid =  ". $this->db_add_param($uid). "
						-- ...inserted or updated today
						AND
						(
							insertamum::date = NOW()::date
						OR
							updateamum::date = NOW()::date
						)
						-- ...for the given monthlist
						AND
							vondatum >= '". $first_day_monthlist. "'
						AND
							bisdatum <= '". $last_day_monthlist. "');";

				if ($result = $this->db_query($qry))
				{
					return $this->db_parse_bool($this->db_result($result, 0, 0));
				}
				else
				{
					$this->errormsg = "Fehler in der Abfrage zum Einholen von CaseTime eines users.";
					return false;
				}
			}
			else
			{
				$this->errormsg = "Datum muss vorhanden und nicht leer sein";
				return false;
			}
		}
		else
		{
			$this->errormsg = "UID muss vorhanden und nicht leer sein";
			return false;
		}
	}

	/** Save Bestätigung related to a certain timesheet
	 *
	 * @param integer $timesheet_id
	 * @param integer $dms_id
	 * @param string $uid
	 * @return boolean	True on success.
	 */
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

	/** Load all Bestätigungen of a certain timesheet
	 *
	 * @param integer $timesheet_id
	 * @return boolean True on success. If true, returns object-array with all attests related to this timesheet.
	 */
	public function loadAllBestaetigungen_byTimesheet($timesheet_id)
	{
		if (isset($timesheet_id) && !empty($timesheet_id))
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
					tbl_casetime_timesheet_dms.timesheet_id=' . $this->db_add_param($timesheet_id);

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

					$this->result[]= $obj;
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

	/** Load all Bestätigungen of a certain timesheet
	 *
	 * @param string $uid
	 * @return boolean True on success. If true, returns object-array with all attests of this user.
	 */
	public function loadAllBestaetigungen_byUser($uid, $timesheet_id = '')
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

				if (is_numeric($timesheet_id))
				{
					$timesheet = new Timesheet();
					$timesheet->load_byID($timesheet_id);

					foreach ($this->result as $key => $bestaetigung)
					{
						if($bestaetigung->datum != $timesheet->datum)
						{
							unset($this->result[$key]);
						}
					}
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

	/** Delete Bestätigung
	 *
	 * @param integer $dms_id
	 * @return boolean True if attest was deleted successfully.
	 */
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

	/** Get user of timesheet
	 *
	 * @param integer $timesheet_id
	 * @return boolean True on success. If true, returns uid of this timesheet.
	 */
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

	/** Get user by dms id
	 *
	 * @param integer $dms_id
	 * @return boolean True on success. If true, returns uid of this document.
	 */
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

	/** Get the most recent timesheet controlling data
	 *
	 * @param type $uid
	 * @return boolean True on success. If true, sets controlling data on instance of Timesheet.
	 */
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

	/** Load all nursing confirmations within a certain period
	 *
	 * @param string $from
	 * @param string $to
	 * @param string $dokument_kurzbz (Bestaetigungen start with bst_ e.g. bst_arzt)
	 * @return boolean	True on success. If true, returns object-array with all nursing confirmations of the given period.
	 */
	public function loadBestaetigungen_inPeriod($from, $to, $dokument_kurzbz = null)
	{
		if (isset($from) && !empty($from) &&
			isset($to) && !empty($to))
		{
			$from = new DateTime($from);
			$from = $from->format('Y-m-d');
			$to = new DateTime($to);
			$to = $to->format('Y-m-d');

			$qry = '
				SELECT
					dms_id,
					filename,
					mimetype,
					name,
					tbl_dms_version.insertamum,
					tbl_dms_version.insertvon
				FROM
					campus.tbl_dms_version
				JOIN
					addon.tbl_casetime_timesheet_dms
					USING(dms_id)
				JOIN
					campus.tbl_dms
					USING (dms_id)
				WHERE
				(
					tbl_dms_version.insertamum >= ' . $this->db_add_param($from, FHC_STRING) . '
				AND
					tbl_dms_version.insertamum <= ' . $this->db_add_param($to, FHC_STRING) . ')';

			if (is_string($dokument_kurzbz))
			{
				$qry .= '
				AND dokument_kurzbz LIKE ' . $this->db_add_param($dokument_kurzbz, FHC_STRING);
			}

			$qry .= '
				ORDER BY
					insertamum DESC
			';

			if ($this->db_query($qry))
			{
				while ($row = $this->db_fetch_object())
				{
					$obj = new stdClass();

					$obj->dms_id = $row->dms_id;
					$obj->filename = $row->filename;
					$obj->mimetype = $row->mimetype;
					$obj->insertamum = $row->insertamum;
					$obj->insertvon = $row->insertvon;

					$this->result[] = $obj;
				}

				return $this->result;
			}
			else
			{
				$this->errormsg = "Fehler in der Abfrage zum Laden aller Krankenbestätigungen einer Periode.";
				return false;
			}
		}
		else
		{
			$this->errormsg = "Von- und Endedatum muss vorhanden und nicht leer sein";
			return false;
		}
	}

	/** Load sick leave times for one user within a certain period
	 *
	 * @param string $from
	 * @param string $to
	 * @return boolean	True on success. If true, returns object-array with all nursing confirmations of the given period.
	 */
	public function getKrankenstaende_byUser_inPeriod($uid, $from, $to)
	{
		if (isset($uid) && !empty($uid) &&
			isset($from) && !empty($from) &&
			isset($to) && !empty($to))
		{
			$from = new DateTime($from);
			$from = $from->format('Y-m-d');
			$to = new DateTime($to);
			$to = $to->format('Y-m-d');

			// Query only sick leave where duration more than 2 days
			$qry = '
				SELECT
					zeitsperre_id,
					zeitsperretyp_kurzbz,
					mitarbeiter_uid,
					vondatum,
					bisdatum
				FROM
					campus.tbl_zeitsperre
				WHERE
					mitarbeiter_uid = '. $this->db_add_param($uid). '
				AND
					zeitsperretyp_kurzbz IN (\'Krank\', \'CovidSB\', \'CovidKS\')
				AND
					(bisdatum - vondatum) > 2
				AND
				(
					(vondatum >= \''. $from. '\' AND vondatum <= \''. $to. '\')
					OR
					(bisdatum >= \''. $from. '\' AND bisdatum <= \''. $to. '\')
				)
				ORDER BY
					vondatum DESC;
				';

			if ($this->db_query($qry))
			{
				while ($row = $this->db_fetch_object())
				{
					$obj = new stdClass();

					$obj->zeitsperre_id = $row->zeitsperre_id;
					$obj->zeitsperretyp_kurzbz = $row->zeitsperretyp_kurzbz;
					$obj->mitarbeiter_uid = $row->mitarbeiter_uid;
					$obj->vondatum = $row->vondatum;
					$obj->bisdatum = $row->bisdatum;

					$this->result[] = $obj;
				}
				return $this->result;
			}
			else
			{
				$this->errormsg = "Fehler in der Abfrage zum Einholen aller Krankenstände eines users in einer bestimmten Periode.";
				return false;
			}
		}
		else
		{
			$this->errormsg = "UID, Von- und Bis-Datum müssen vorhanden und nicht leer sein";
			return false;
		}
	}

	/** Check if user has changed or deleted today any times within the given timesheet month
	 *
	 * @param string $uid User ID.
	 * @param DateTime $date_monthlist	Casetime entries will be checked from first to last of the dates' month.
	 * @return boolean	True when at least one deleted/changed time was found.
	 * Deletion/Change is detected by comparing the 'user entry tables'
	 * campus.tbl_zeitaufzeichnung / campus.tbl_zeitsperre with the SYNC tables
	 * addon.tbl_casetime_zeitaufzeichnung / addon.tbl_casetime_zeitsperre.
	 */
	public function hasDeletedTimes($uid, $date_monthlist)
	{
		if (isset($uid) && !empty($uid))
		{
			if (isset($date_monthlist) && $date_monthlist instanceof DateTime)
			{
				$first_day_monthlist = $date_monthlist->modify('first day of this month');
				$first_day_monthlist = $first_day_monthlist->format('Y-m-d');

				$last_day_monthlist = $date_monthlist->modify('last day of this month');
				$last_day_monthlist = $last_day_monthlist->format('Y-m-d');

				// Check if user has deleted or changed start/ending times in zeitaufzeichnung
				$qry = "
					SELECT
						1
					FROM
					(
						SELECT
							uid,
							datum,
							zeit_start,
							zeit_ende
						-- Zeitaufzeichnung entries
						FROM
							addon.tbl_casetime_zeitaufzeichnung
						-- ...from given employee
						WHERE
							uid = ". $this->db_add_param($uid). "
						AND
						-- ...with type KOMMEN
							typ = 'ko'
						-- ...for the given monthlist
						AND
							(datum >= '". $first_day_monthlist."' AND datum <= '". $last_day_monthlist."')
						-- ...where working times (earlier start or later end) are not synchronized
						-- ...except externe Lehre, Ersatzruhe and Dienstreise
						AND
						(
							tbl_casetime_zeitaufzeichnung.zeit_start !=
							(
								SELECT
									min(start::time)
								FROM
									campus.tbl_zeitaufzeichnung
								WHERE
									uid = ". $this->db_add_param($uid). "
								AND
									start::date = tbl_casetime_zeitaufzeichnung.datum
								AND
								(
									/* null values in aktivitaet_kurzbz are considered as working times -> leave them */
									aktivitaet_kurzbz IS NULL
									OR
									aktivitaet_kurzbz NOT IN ('LehreExtern', 'Ersatzruhe','DienstreiseMT')
								)
							)

							OR

							date_trunc('minutes',tbl_casetime_zeitaufzeichnung.zeit_ende) !=
							(
								SELECT
									max(ende::time)
								FROM
									campus.tbl_zeitaufzeichnung
								WHERE
									uid = ". $this->db_add_param($uid). "
								AND
									ende::date = tbl_casetime_zeitaufzeichnung.datum
								AND
								(
									/* null values in aktivitaet_kurzbz are considered as working times -> leave them */
									aktivitaet_kurzbz IS NULL
									OR
									aktivitaet_kurzbz NOT IN ('LehreExtern', 'Ersatzruhe','DienstreiseMT')
								)
							)
						)
					) AS check1";

				// Check if user has deleted any other times but start-/ending times in zeitaufzeichnung
				// (which are checked in first select above)
				$qry .= "
					UNION

					SELECT
						1
					FROM
					(
						SELECT
							zeitaufzeichnung_id
						-- Zeitaufzeichnung entries
						FROM
							addon.tbl_casetime_zeitaufzeichnung
						-- ...from given employee
						WHERE
							uid = ". $this->db_add_param($uid). "
						-- ...for the given monthlist
						AND
							(datum >= '". $first_day_monthlist."' AND datum <= '". $last_day_monthlist."')
						-- ...for all types except KOMMEN
						AND
							typ != 'ko'
						-- ...that are not synchronized
						AND NOT EXISTS
						(
							SELECT
							   1
							FROM
								campus.tbl_zeitaufzeichnung
							WHERE
								zeitaufzeichnung_id = tbl_casetime_zeitaufzeichnung.zeitaufzeichnung_id
						)
					) AS check2 ";

				// Check if user has deleted any times in zeitsperre
				$qry .= "
					UNION

					SELECT
						1
					FROM
					(
						SELECT
							datum,
							typ
						-- Zeitaufzeichnung entries
						FROM
							addon.tbl_casetime_zeitsperre
						-- ...from given employee
						WHERE
							uid = ". $this->db_add_param($uid). "
						-- ...for the given monthlist
						AND
							(datum >= '". $first_day_monthlist."' AND datum <= '". $last_day_monthlist."')
						-- ...that are not synchronized
						-- ...check only DienstF, DienstV, Krank, PflegeU, Urlaub, ZA
						AND NOT EXISTS
						(
							SELECT
								datum,
								typ
							FROM
							(
								SELECT
									generate_series(vondatum::date, bisdatum::date, '1 day')::date AS datum,
									zeitsperretyp_kurzbz AS typ
								FROM
									campus.tbl_zeitsperre
								WHERE
									mitarbeiter_uid = ". $this->db_add_param($uid). "
								AND
									/* only complete days are considered in sync table */
									vonstunde IS NULL
								AND
									/* only complete days are considered in sync table */
									bisstunde IS NULL
							) AS zsp
							WHERE
								datum = tbl_casetime_zeitsperre.datum
							AND
								/* only the following types are considered in sync table */
								typ IN ('DienstF', 'DienstV', 'Krank', 'PflegeU', 'Urlaub', 'ZA','CovidSB','CovidKS')
						)
					) AS check3;";

				$isSynced_today = true;	// False if at least one deleted/changed time is found in zeitaufzeichnung or zeitsperre

				// Execute query
				if ($result = $this->db_query($qry))
				{
					if ($this->db_num_rows($result) > 0)
					{
							$isSynced_today = false;
					}
				}
				else
				{
					$this->errormsg = "Fehler in der Abfrage zum Einholen der gelöschten CaseTime-Zeiten eines users.";
					return false;
				}

				// Return false if any times has been deleted/changed today
				return $isSynced_today;
			}
			else
			{
				$this->errormsg = "Datum muss vorhanden und nicht leer sein";
				return false;
			}
		}
		else
		{
			$this->errormsg = "UID muss vorhanden und nicht leer sein";
			return false;
		}
	}

	/** Check if there is an existing timesheet
	 *
	 * @param string $uid UserID.
	 * @return boolean	True when there is a timesheet
	 */
	public function checkIfUserHasTimesheet($uid)
	{
		if (isset($uid) && !empty($uid))
		{
			$qry = "
			SELECT *
			FROM addon.tbl_casetime_timesheet
			WHERE uid = ". $this->db_add_param($uid). "
			";

			if ($this->db_query($qry))
			{
				$num_rows = $this->db_num_rows();
				if ($num_rows > 0)
				{
					return true;
				}
				else
				{
					return false;
				}
			}
			else
				return false;
		}
	}

	/** Insert new timesheet
	 *
	 * @param string $uid UserID.
	 * @param date $datum Date of Timesheet.
	 * @return boolean	True when inserted successfully
	 */
	public function insertTimeSheet($uid, $datum)
	{
		$qry = "
			INSERT INTO addon.tbl_casetime_timesheet (uid, datum, insertvon)
			VALUES (". $this->db_add_param($uid). ",". $this->db_add_param($datum). ",'tsdaemon')
		";

		if ($this->db_query($qry))
		{
				return true;
		}
		else
		{
			$this->errormsg = 'Timesheet konnte nicht eingefügt werden';
			return false;
		}
	}

	/**
	 * Saves users if Monatsliste should be closed before the month has finished.
	 * @param $timesheet_id
	 * @param $vorzeitig_abgeschickt
	 * @return bool
	 */
	public function saveVorzeitigAbgeschickt($timesheet_id, $vorzeitig_abgeschickt)
	{
		$qry = '
				UPDATE
					addon.tbl_casetime_timesheet
				SET
					vorzeitig_abgeschickt = '. $this->db_add_param($this->db_escape($vorzeitig_abgeschickt)). '
				WHERE
					timesheet_id = '. $this->db_add_param($timesheet_id);

		if($this->db_query($qry))
		{
			return true;
		}
		else
		{
			$this->errormsg = 'Fehler beim Update von vorzeitig_abgeschickt';
			return false;
		}
	}

	/**
	 * Reset vorzeitig_abgeschickt to FALSE.
	 * @param $timesheet_id
	 */
	public function resetVorzeitigAbgeschickt($timesheet_id)
	{
		$qry = '
				UPDATE
					addon.tbl_casetime_timesheet
				SET
					vorzeitig_abgeschickt = FALSE
				WHERE
					timesheet_id = '. $this->db_add_param($timesheet_id);

		if($this->db_query($qry))
		{
			return true;
		}
		else
		{
			$this->errormsg = 'Fehler beim reset von vorzeitig_abgeschickt.';
			return false;
		}

	}

	/**
	 * Loads all timesheets of the last month that are flagged as vorzeitig_abgeschickt.
	 */
	public function loadAllFromLastMonthVorzeitigAbgeschickt()
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
				kontroll_notizen,
				vorzeitig_abgeschickt
			FROM
				addon.tbl_casetime_timesheet
			WHERE
				-- vorzeitig abgeschickt ist gesetzt
				vorzeitig_abgeschickt
			AND
				-- noch nicht abgeschickt
				abgeschicktamum IS NULL
			AND
				-- nur timesheets des letzten Monats
				date_trunc(\'month\', datum) = date_trunc(\'month\', now() - interval \'1 month\')
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
				$obj->vorzeitig_abgeschickt = $row->vorzeitig_abgeschickt;

				$this->result[] = $obj;
			}

			return $this->result;
		}
		else
		{
			$this->errormsg = "Fehler beim Laden der timesheets aus dem Vormonat mit flag vorzeitig_abgeschickt.";
			return false;
		}
	}

	/**
	 * Checks if there are casetime server errors that are defined as blocking errors
	 * @param String $uid
	 * @param String $month Timesheet month, e.g. 06
	 * @param String $year  Timesheet year, e.g. 2021
	 * @return bool         True, if casetime blocking errors were found.
	 * @throws Exception
	 */
	public function hasCaseTimeError($uid, $month, $year)
	{
		$casetime_error_arr = getCaseTimeErrors($uid);
		$blocking_error_arr = unserialize(CASETIME_BLOCKING_ERR);

		foreach ($casetime_error_arr as $casetime_error)
		{
			$casetime_error_date = new DateTime($casetime_error[0]);
			$casetime_error_month = $casetime_error_date->format('m');
			$casetime_error_year = $casetime_error_date->format('Y');

			// If casetime error exists in the given timesheet or one month before...
			if ($casetime_error_year == $year
				&& ($casetime_error_month == $month || $casetime_error_month == ($month-1)))
			{
				// ...check if it is a blocking error
				foreach($blocking_error_arr as $blocking_err)
				{
					if (strpos($casetime_error[1], $blocking_err) !== false)
					{
						return true; // Blocking error found
					}
				}
			}
		}
		return false; // No blocking errors found
	}

	/**
	 * Checks if there are blocking Pausen errors
	 * @param string $uid Mitarbeiter_uid.
	 * @param string $month Timesheet month, e.g. 06.
	 * @param string $year  Timesheet year, e.g. 2021.
	 * @return bool false if no blocking pause errors were found, else the day of the error.
	 */
	public function hasBlockingErrorPause($uid, $month, $year)
	{
		$datum = new datum();

		//aktuelles Monat nach Pausenfehler checken
		$start = $year. "-". $month. "-01";
		$stamp = strtotime($start);
		while (date("n", $stamp) == $month)
		{
			$day = date("Y-m-d", $stamp);
			$za = new zeitaufzeichnung();
			if ($za->checkPausenErrors($uid, $day))
			{
                $vbt = new vertragsbestandteil();
                $isAzgrelevant = $vbt->isAzgRelevant($uid, $day);

                if ($isAzgrelevant)
                {
                    return $day; // Blocking error found
                }
			}
			$stamp = strtotime("+1 day", $stamp);
		}
		return false; // no blocking Pausenfehler found
	}

	/**
	 * Checks if there are missing Bestaetigungen for the reported absences.
	 * If only uid is given, all absences of the user  will be checked against all Bestaetigunen.
	 * If timesheet_id is given, only absences and Bestaetigungen for that timesheet period will
	 * be checked.
	 *
	 * @param $uid
	 * @param $timesheet_id
	 * @return bool
	 * @throws Exception
	 */
	public function hasMissingBestaetigung($uid, $timesheet_id = '')
	{
		$timesheet = new Timesheet();
		$timesheet->load_byID($timesheet_id);

		// Array to store missing bestaetigungen
		$missing_bestaetigung_arr = array();
		$missing_bestaetigung_arr['arztbesuch'] = 0;
		$missing_bestaetigung_arr['behoerdenbesuch'] = 0;
		$missing_bestaetigung_arr['krankenstand'] = 0;
		$missing_bestaetigung_arr['plegeurlaub'] = 0;
		$missing_bestaetigung_arr['krankenstandcovid'] = 0;

		// Get all absences of given UID
		// If timesheet is given, check absences only for that timesheet
		$absences = is_numeric($timesheet_id)
			? $timesheet->getAllAbsentTimes($uid, $timesheet_id)
			: $timesheet->getAllAbsentTimes($uid);

		if (!empty($absences))
		{
			// Count absences by type
			$cnt_ab = 0;     // counter arztbesuch
			$cnt_beh = 0;    // counter behörde
			$cnt_kst = 0;    // counter krankenstand
			$cnt_pfl = 0;    // counter pflegeurlaub
			$cnt_covSB = 0;    // counter CovidSB
			$cnt_covKS = 0;    // counter CovidKS
			foreach ($absences as $absence) {
				switch ($absence->abwesenheit_kurzbz) {
					case 'Arztbesuch':
						// each Arztbesuch-absence needs to be attested
						$cnt_ab++;
						continue;
					case 'Behoerde':
						// each Behörden-absence needs to be attested
						$cnt_beh++;
						continue;
					case 'PflegeU':
						$von = new DateTime($absence->von);
						$bis = new DateTime($absence->bis);

						// Pflegeurlaub needs to be attested only from the 3rd day on
						if ($von->diff($bis)->d >= 2)
							$cnt_pfl++;
						continue;
					case 'Krank':
						$von = new DateTime($absence->von);
						$bis = new DateTime($absence->bis);

						// Krankenstand needs to be attested only from the 3rd day on
						if ($von->diff($bis)->d >= 2)
							$cnt_kst++;
						continue;
					case 'CovidSB':
						$von = new DateTime($absence->von);
						$bis = new DateTime($absence->bis);
						// each covidkrankenstand needs to be attested
							$cnt_covSB++;
						continue;
					case 'CovidKS':
						$von = new DateTime($absence->von);
						$bis = new DateTime($absence->bis);
						// each covidkrankenstand needs to be attested
							$cnt_covKS++;
						continue;
				}
			}

			// Get all Bestaetigungen
			// If timesheet is given, only Bestaetigungen of that timesheets period are needed
			$timesheet = new Timesheet();
			$bestaetigungen = is_numeric($timesheet_id)
				? $timesheet->loadAllBestaetigungen_byTimesheet($timesheet_id)
				: $timesheet->loadAllBestaetigungen_byUser($uid);

			// Count Bestaetigungen by type
			$cnt_ab_doc = 0;	// counter arztbesuch bestätigungen
			$cnt_beh_doc = 0;	// counter behörde bestätigungen
			$cnt_kst_doc = 0;	// counter krankenstand bestätigungen
			$cnt_pfl_doc = 0;	// counter pflegeurlaub bestätigungen
			$cnt_cov_doc = 0;	// counter covidkrankenstand bestätigungen
			foreach ($bestaetigungen as $bestaetigung)
			{
				switch ($bestaetigung->dokument_kurzbz)
				{
					case 'bst_arzt':
						$cnt_ab_doc++;
						continue;
					case 'bst_bhrd':
						$cnt_beh_doc++;
						continue;
					case 'bst_pfur':
						$cnt_pfl_doc++;
						continue;
					case 'bst_krnk':
						$cnt_kst_doc++;
						continue;
					case 'bst_cov':
						$cnt_cov_doc++;
						continue;
				}
			}

			// Store missing bestaetigungen by type
			if ($cnt_ab > $cnt_ab_doc)
			{
				$missing_bestaetigung_arr['arztbesuch'] = $cnt_ab - $cnt_ab_doc;
			}
			if ($cnt_beh > $cnt_beh_doc)
			{
				$missing_bestaetigung_arr['behoerdenbesuch'] = $cnt_beh - $cnt_beh_doc;
			}
			// if one or more krankenstände there need to be at least one krankenstandsbestätigung
			if ($cnt_kst >= 1 && $cnt_kst_doc == 0)
			{
				$missing_bestaetigung_arr['krankenstand'] = 1; // at least 1, no matter if more
			}
			if ($cnt_pfl >= 1 && $cnt_pfl_doc == 0)
			{
				$missing_bestaetigung_arr['plegeurlaub'] = 1; // at least 1, no matter if more
			}
			if ($cnt_covSB >= 1 && $cnt_cov_doc == 0)
			{
				$missing_bestaetigung_arr['krankenstandcovid'] = 1; // at least 1, no matter if more
			}
			if ($cnt_covKS >= 1 && $cnt_cov_doc == 0)
			{
				$missing_bestaetigung_arr['krankenstandcovid'] = 1; // at least 1, no matter if more
			}

			// ...and save them as result
			$this->result = $missing_bestaetigung_arr;

			// Check if absences have their according Bestaetigung.
			// Return true if Bestaetigung is missing.
			return $cnt_ab > $cnt_ab_doc
				|| $cnt_beh > $cnt_beh_doc
				|| ($cnt_pfl >= 1 && $cnt_pfl_doc == 0)  // if one or more Pflegeurlaub, at least one Bestaetigung
				|| ($cnt_kst >= 1 && $cnt_kst_doc == 0); // if one or more Krankenstand, at least one Bestaetigung
		}

		// If not even one single absence found
		$this->result = $missing_bestaetigung_arr;
		return false;
	}

	/**
	 * Resets the field abgeschicktamum for newer timesheets to reset sperrdate and enables user to correct date entries
	 *
	 * @param integer $timesheet_id
	 * @return bool
	 * @throws Exception
	 */
	public function resetSentData($timesheet_id)
	{
		$qry = '
				UPDATE
					addon.tbl_casetime_timesheet
				SET
					abgeschicktamum = NULL,
					vorzeitig_abgeschickt = false
				WHERE
					timesheet_id = '. $this->db_add_param($timesheet_id);

		if($this->db_query($qry))
		{
			return true;
		}
		else
		{
			$this->errormsg = 'Fehler bei reset von insertamum.';
			return false;
		}
	}

	/**
	 * returns an array of newer timesheets which have been already  sent to superior
	 *
	 * @param integer $timesheet_id
	 * @param String $uid
	 * @return bool
	 * @throws Exception
	 */
	function getNewerTimesheets($timesheet_id, $uid)
	{
		$qry = '
				SELECT
					timesheet_id
				FROM
					addon.tbl_casetime_timesheet
				WHERE
				    uid = '. $this->db_add_param($uid). '
				AND
				    abgeschicktamum is not null
				AND
					datum >
				(
					SELECT
						datum
					FROM
						addon.tbl_casetime_timesheet
					WHERE
						timesheet_id = '. $this->db_add_param($timesheet_id). '
				)';

		if($this->db_query($qry))
		{
			$ids = array();
			while ($row = $this->db_fetch_object())
			{
				$ids[] = $row->timesheet_id;
			}

			return $ids;
		}
		else
		{
			$this->errormsg = 'Fehler bei der Abfrage getNewerTimesheets()';
			return false;
		}
	}

	/**
	 * Checks if user has unsent timesheet(s). The check considers all months before the last month.
	 *
	 * @param $uid
	 * @return bool
	 */
	public function hasFormerUnsentTimesheetsBeforeLastMonth($uid)
	{
		$qry = '
			SELECT 1
			FROM addon.tbl_casetime_timesheet
			WHERE
				uid = '. $this->db_add_param($this->db_escape($uid)). '
				 -- Filter to only consider months before the last month
				AND datum < DATE_TRUNC(\'month\', CURRENT_DATE - INTERVAL \'1 month\')
				-- Filter only not sent ones
				AND abgeschicktamum IS NULL;
		';

		if ($result = $this->db_query($qry))
		{
			return $this->db_num_rows($result) > 0;  // True if former unsent timesheets was found
		}

	}

	/**
	 * Checks if user has any missing timesheet(s). The check considers all months before the last month.
	 *
	 * @param $uid
	 * @return bool
	 */
	public function hasFormerMissingTimesheetsBeforeLastMonth($uid)
	{
		$qry = '
			SELECT 1
			FROM (
			     -- Subquery to generate series of all months between the minimum and maximum months of given uid
				SELECT
					generate_series(
						DATE_TRUNC(\'month\', MIN(datum))::date,
						DATE_TRUNC(\'month\', MAX(datum))::date,
						\'1 month\'::interval
					) AS month
				FROM
					addon.tbl_casetime_timesheet
				WHERE uid = '. $this->db_add_param($this->db_escape($uid)). '
			) AS all_months
			LEFT JOIN (
			 	-- Subquery to get distinct months present of given uid
				SELECT DISTINCT
					DATE_TRUNC(\'month\', datum)::date AS month
				FROM
					addon.tbl_casetime_timesheet
			WHERE uid = '. $this->db_add_param($this->db_escape($uid)). '
			) AS existing_months ON all_months.month = existing_months.month
			WHERE
				-- Filter to find months that are missing
				existing_months.month IS NULL
				-- Filter to only consider months before the last month
				AND all_months.month < DATE_TRUNC(\'month\', CURRENT_DATE - INTERVAL \'1 month\')::date
				AND EXISTS(
				SELECT
				1
				FROM
					hr.tbl_dienstverhaeltnis
					JOIN hr.tbl_vertragsbestandteil USING(dienstverhaeltnis_id)
					JOIN hr.tbl_vertragsbestandteil_zeitaufzeichnung USING(vertragsbestandteil_id)
				WHERE
					mitarbeiter_uid='. $this->db_add_param($this->db_escape($uid)). '
					AND tbl_vertragsbestandteil_zeitaufzeichnung.zeitaufzeichnung=true
					AND all_months.month between tbl_vertragsbestandteil.von AND COALESCE(tbl_vertragsbestandteil.bis,\'2999-12-31\')
				)
		';

		if ($result = $this->db_query($qry))
		{
			return $this->db_num_rows($result) > 0; // True if former missing timesheets was found
		}
	}
}
