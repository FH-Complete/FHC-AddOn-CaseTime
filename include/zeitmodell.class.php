<?php
/* Copyright (C) 2014 fhcomplete.org
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
 * Authors: Andreas Oesterreicher <andreas.oesterreicher@technikum-wien.at>
 */
require_once(dirname(__FILE__).'/../../../include/basis_db.class.php');

class zeitmodell extends basis_db
{
	public $new=true;
	public $result = array();

	public $zeitmodell_id;
	public $zeitmodell_kurzbz;
	public $beschreibung;
	public $aktiv;
	public $stundenanzahl;
	public $ext_id;
	public $sort;
	public $insertamum;
	public $insertvon;
	public $updateamum;
	public $updatevon;

	/**
	 * Konstruktor
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Speichert die Daten in die Sync-Tabelle
	 * @param $new boolean default NULL
	 * @return boolean true wenn ok, false im Fehlerfall
	 */
	public function save($new=null)
	{
		if(is_null($new))
			$new = $this->new;

		if($new)
		{
			$qry = "INSERT INTO hr.tbl_zeitmodell(zeitmodell_kurzbz, beschreibung, aktiv, stundenanzahl, ext_id, sort, insertamum, insertvon) VALUES(".
					$this->db_add_param($this->zeitmodell_kurzbz).','.
					$this->db_add_param($this->beschreibung).','.
					$this->db_add_param($this->aktiv, FHC_BOOLEAN).','.
					$this->db_add_param($this->stundenanzahl, FHC_INTEGER).','.
					$this->db_add_param($this->ext_id).','.
					$this->db_add_param($this->sort).','.
					$this->db_add_param($this->insertamum).','.
					$this->db_add_param($this->insertvon).');';
		}
		else
		{
			$qry = "UPDATE hr.tbl_zeitmodell SET ".
				'zeitmodell_kurzbz='.$this->db_add_param($this->zeitmodell_kurzbz).','.
				'beschreibung='.$this->db_add_param($this->beschreibung).','.
				'aktiv='.$this->db_add_param($this->aktiv).','.
				'stundenanzahl='.$this->db_add_param($this->stundenanzahl).','.
				'ext_id='.$this->db_add_param($this->ext_id).','.
				'sort='.$this->db_add_param($this->sort).','.
				'updatevon='.$this->db_add_param($this->updatevon).','.
				'updateamum='.$this->db_add_param($this->updateamum).
				' WHERE zeitmodell_id='.$this->db_add_param($this->zeitmodell_id);
		}

		if($this->db_query($qry))
		{
			if($new)
			{
				$qry = "SELECT currval('hr.tbl_zeitmodell_zeitmodell_id_seq') as id;";
				if($this->db_query($qry))
				{
					if($row = $this->db_fetch_object())
					{
						$this->zeitmodell_id = $row->id;
					}
					else
					{
						$this->errormsg = 'Fehler beim Lesen der Sequence';
						return false;
					}
				}
				else
				{
					$this->errormsg = 'Fehler beim Lesen der Sequence';
					return false;
				}
			}
			return $this->zeitmodell_id;
		}
		else
		{
			$this->errormsg = 'Fehler beim Speichern der Daten';
			return false;
		}
	}

	public function loadByZeitmodellKurzbz($zeitmodell_kurzbz)
	{
		if (isset($zeitmodell_kurzbz) && !empty($zeitmodell_kurzbz))
		{
			$qry = '
				SELECT
					zeitmodell_id,
					zeitmodell_kurzbz,
					beschreibung,
					aktiv,
					stundenanzahl,
					ext_id,
					sort,
					insertamum,
					insertvon,
					updateamum,
					updatevon
				FROM
					hr.tbl_zeitmodell
				WHERE
					zeitmodell_kurzbz ='. $this->db_add_param($zeitmodell_kurzbz);

			if ($this->db_query($qry))
			{
				if ($row = $this->db_fetch_object())
				{
					$this->zeitmodell_id = $row->zeitmodell_id;
					$this->zeitmodell_kurzbz = $row->zeitmodell_kurzbz;
					$this->beschreibung = $row->beschreibung;
					$this->aktiv = $row->aktiv;
					$this->stundenanzahl = $row->stundenanzahl;
					$this->ext_id = $row->ext_id;
					$this->sort = $row->sort;
					$this->insertamum = $row->insertamum;
					$this->insertvon = $row->insertvon;
					$this->updateamum = $row->updateamum;
					$this->updatevon = $row->updatevon;

					return true;
				}
				else
				{
					$this->errormsg = "Kein Zeitmodell zu dieser Bezeichnung vorhanden.";
					return false;
				}
			}
			else
			{
				$this->errormsg = "Fehler in der Abfrage zum Laden des Zeitmodells.";
				return false;
			}
		}
		else
		{
			$this->errormsg = "Bezeichnung muss vorhanden und nicht leer sein";
			return false;
		}
	}
}

?>
