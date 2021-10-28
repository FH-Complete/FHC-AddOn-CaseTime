<?php
require_once(dirname(__FILE__). '/../../../include/basis_db.class.php');
/**
 * Description of covidhelper
 *
 * @author bambi
 */
class CovidHelper extends basis_db
{
	const STATUS_OK			= 1;
	const STATUS_NOTOK		= 0;
	const STATUS_UNKNOWN	= -1;
	const STATUS_NOTSET		= -2;
	
	protected $uids;
	protected $covidstatus;

	public function __construct($uids)
	{
		parent::__construct();
		$this->uids = $uids;
		$this->covidstatus = array();
		$this->fetchCovidValidStatus();
	}

	public function getIconHtml($uid) {
		$html = '';
		$status = isset($this->covidstatus[$uid]) ? $this->covidstatus[$uid] : self::STATUS_NOTSET;
		switch ($status)
		{
			case self::STATUS_OK:
				$html = '<i title="Covid Status gültig" class="fa fa-check-circle" aria-hidden="true" style="color: green; margin-right: .5em;"></i>';
				break;
			case self::STATUS_NOTOK:
				$html = '<i title="Covid Status ungültig" class="fa fa-times-circle" aria-hidden="true" style="color: red; margin-right: .5em;"></i>';
				break;
			case self::STATUS_UNKNOWN:
				$html = '<i title="Covid Status unbekannt" class="fa fa-question-circle" aria-hidden="true" style="color: grey; margin-right: .5em;"></i>';
				break;
			default:
				$html = '';
				break;
		}
		return $html;
	}	
	
	public function getCovidStatus() {
		return $this->covidstatus;
	}
	
	protected function fetchCovidValidStatus() {
		if( !($this->checkIfUdfValuesAreDefined() && is_array($this->uids)) ) {
			return;
		}
		$mergeduids = "'" . implode("','", $this->uids) . "'";
		$sql = <<<EOSQL
SELECT b.uid, CASE 
		WHEN (p."udf_values" -> 'udf_3gvalid')::text::date >= NOW() THEN 1 
		WHEN (p."udf_values" -> 'udf_3gvalid')::text::date < NOW() THEN 0 
		ELSE -1 
	END AS covidvalid 
	FROM tbl_person p 
	JOIN tbl_benutzer b ON b.person_id = p.person_id AND b.uid IN ({$mergeduids})
EOSQL;
	
		$this->covidstatus = array();
		if( $this->db_query($sql) ) {
			while( false !== ($row = $this->db_fetch_object()) )
			{
				$this->covidstatus[$row->uid] = $row->covidvalid; 
			}
		} else {
			$this->errormsg = "Fehler in der Abfrage des Covidstatus.";
		}	
	}
		
	protected function checkIfUdfValuesAreDefined()
	{
		$sql = 'SELECT count(*) AS udfdefined FROM "system"."tbl_udf" WHERE "schema" = \'public\' AND "table" = \'tbl_person\'';
		if ( $this->db_query($sql) )
		{
			if ($row = $this->db_fetch_object())
			{
				return ($row->udfdefined > 0) ? true : false;
			}
			else 
			{
				$this->errormsg = "Fehler in der Abfrage beim Pruefen der UDFs. Kein Datensatz gefunden.";
				return false;
			}
		}
		else 
		{
			$this->errormsg = "Fehler in der Abfrage beim Pruefen der UDFs.";
			return false;
		}
	}
}
