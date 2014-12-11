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

class casetime extends basis_db
{
	public $new=true;
	public $result = array();
	
	public $uid;
	public $datum;
	public $zeit_start;
	public $zeit_ende;
	public $ext_id1;
	public $ext_id2;
	public $typ;
	public $sync;
	public $delete;
	public $zeitaufzeichnung_id;
	
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
			$qry = "INSERT INTO addon.tbl_casetime_zeitaufzeichnung(uid, datum, zeit_start, zeit_ende, ext_id1, ext_id2, typ, sync, delete, zeitaufzeichnung_id) VALUES(".
					$this->db_add_param($this->uid).','.
					$this->db_add_param($this->datum).','.
					$this->db_add_param($this->zeit_start).','.
					$this->db_add_param($this->zeit_ende).','.
					$this->db_add_param($this->ext_id1).','.
					$this->db_add_param($this->ext_id2).','.
					$this->db_add_param($this->typ).','.
					$this->db_add_param($this->sync, FHC_BOOLEAN).','.
					$this->db_add_param($this->delete, FHC_BOOLEAN).','.
					$this->db_add_param($this->zeitaufzeichnung_id, FHC_INTEGER).');';
		}
		else
		{
			$qry.="UPDATE addon.tbl_casetime_zeitaufzeichnung SET ".
				'uid='.$this->db_add_param($this->uid).','.
				'datum='.$this->db_add_param($this->datum).','.
				'zeit_start='.$this->db_add_param($this->zeit_start).','.
				'zeit_ende='.$this->db_add_param($this->zeit_ende).','.
				'ext_id1='.$this->db_add_param($this->ext_id1).','.
				'ext_id2='.$this->db_add_param($this->ext_id2).','.
				'typ='.$this->db_add_param($this->typ).','.
				'sync='.$this->db_add_param($this->sync, FHC_BOOLEAN).','.
				'delete='.$this->db_add_param($this->delete, FHC_BOOLEA).','.
				'zeitaufzeichnung_id='.$this->db_add_param($zeitaufzeichnung_id).
				' WHERE casetime_zeitaufzeichnung_id='.$this->db_add_param($this->casetime_zeitaufzeichnung_id, FHC_INTEGER, false);
		}

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

	/**
	 * Speichert die Uebertragenen Urlaube in die Sync-Tabelle
	 * @param $new boolean default NULL
	 * @return boolean true wenn ok, false im Fehlerfall
	 */
	public function saveUrlaub($new=null)
	{
		if(is_null($new))
			$new = $this->new;

		if($new)
		{
			$qry = "INSERT INTO addon.tbl_casetime_urlaub(uid, datum) VALUES(".
					$this->db_add_param($this->uid).','.
					$this->db_add_param($this->datum).');';
		}
		else
		{
			$this->errormsg = 'Not implemented';
			return false;
		}

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

	/**
	 * Loescht die Eintraege eines Mitarbeiters an einem Tag aus der Sync Tabelle
	 * @param $uid UID des Mitarbeiters
	 * @param $datum Datum des Tages der entfernt werden soll
	 * @return true wenn ok
	 * @return false wenn fehler
	 */
	public function deleteDay($uid, $datum)
	{
		$qry = "DELETE FROM addon.tbl_casetime_zeitaufzeichnung WHERE uid=".$this->db_add_param($uid)." AND datum=".$this->db_add_param($datum);

		if($this->db_query($qry))
		{
			return true;
		}
		else
		{
			$this->errormsg = 'Fehler beim Loeschen der Daten';
			return false;
		}	
	}
}
?>
