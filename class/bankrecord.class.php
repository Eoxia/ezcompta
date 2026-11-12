<?php
/*
 * Copyright (C) 2026 Laurent MAGNIN <laurent.magnin@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file        class/bankrecord.class.php
 * \ingroup     ezcompta
 * \brief       CRUD class file for BankRecord (Official bank statement)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class for BankRecord (Bank statement header)
 * Follows ISO 20022 CAMT.053 & SWIFT MT940 banking standards.
 * Only the official bank statement values (opening_balance, closing_balance, total_debit, total_credit)
 * are stored natively in the table. Calculated balances from transactions are computed on the fly.
 */
class BankRecord extends CommonObject
{
	/**
	 * @var string ID of module.
	 */
	public $module = 'ezcompta';

	/**
	 * @var string ID to identify managed object.
	 */
	public $element = 'bankrecord';

	/**
	 * @var string Name of table without prefix where object is stored.
	 */
	public $table_element = 'bank_record';

	/**
	 * @var string String with name of icon for bankrecord.
	 */
	public $picto = 'fa-file-invoice-dollar';

	const STATUS_DRAFT = 0;
	const STATUS_VALIDATED = 1;
	const STATUS_CANCELED = 9;

	/**
	 * @var int Bank record ID
	 */
	public $rowid;

	/**
	 * @var string Reference / Legal sequence number (e.g. 2026-10 or 010)
	 */
	public $ref;

	/**
	 * @var int Foreign key to bank account (llx_bank_account.rowid)
	 */
	public $fk_bank;

	/**
	 * @var int Start date timestamp
	 */
	public $dt_from;

	/**
	 * @var int End date timestamp
	 */
	public $dt_to;

	/**
	 * @var float Opening balance read on official bank PDF statement (ISO 20022: OPBD / MT940: :60F:)
	 */
	public $opening_balance;

	/**
	 * @var float Closing balance read on official bank PDF statement (ISO 20022: CLBD / MT940: :62F:)
	 */
	public $closing_balance;

	/**
	 * @var float Total debit read on official bank PDF statement (ISO 20022: TtlDbtNtries)
	 */
	public $total_debit;

	/**
	 * @var float Total credit read on official bank PDF statement (ISO 20022: TtlCdtNtries)
	 */
	public $total_credit;

	/**
	 * @var int Total number of operations/entries on the official bank statement (ISO 20022: NbOfNtries)
	 */
	public $nb_operations;

	/**
	 * @var int Status (0=Draft/In progress, 1=Validated, 9=Canceled)
	 */
	public $status;

	/**
	 * @var int User author ID
	 */
	public $fk_user_author;

	/**
	 * @var int User validator ID
	 */
	public $fk_user_valid;

	/**
	 * @var string Note / Comment
	 */
	public $note;

	/**
	 * @var int Creation date
	 */
	public $date_creation;

	/**
	 * @var int Validation date
	 */
	public $date_valid;

	/**
	 * @var array Lines of statement
	 */
	public $lines = array();

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
		$this->status = self::STATUS_DRAFT;
	}

	/**
	 * Create bank record into database
	 *
	 * @param  User $user      User that creates
	 * @param  int  $notrigger 0=launch triggers after, 1=disable triggers
	 * @return int             <0 if KO, id of created object if OK
	 */
	public function create(User $user, $notrigger = 0)
	{
		$error = 0;

		$now = dol_now();
		$this->date_creation = $now;
		$this->fk_user_author = $user->id;

		$this->db->begin();

		$sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";
		$sql .= "ref, fk_bank, dt_from, dt_to, opening_balance, closing_balance, total_debit, total_credit, nb_operations, status, fk_user_author, date_creation";
		$sql .= ") VALUES (";
		$sql .= "'".$this->db->escape($this->ref)."', ";
		$sql .= ((int) $this->fk_bank).", ";
		$sql .= "'".$this->db->idate($this->dt_from)."', ";
		$sql .= "'".$this->db->idate($this->dt_to)."', ";
		$sql .= ($this->opening_balance !== null && $this->opening_balance !== '' ? price2num($this->opening_balance) : "NULL").", ";
		$sql .= ($this->closing_balance !== null && $this->closing_balance !== '' ? price2num($this->closing_balance) : "NULL").", ";
		$sql .= ($this->total_debit !== null && $this->total_debit !== '' ? price2num($this->total_debit) : "NULL").", ";
		$sql .= ($this->total_credit !== null && $this->total_credit !== '' ? price2num($this->total_credit) : "NULL").", ";
		$sql .= ($this->nb_operations !== null && $this->nb_operations !== '' ? ((int) $this->nb_operations) : "NULL").", ";
		$sql .= ((int) $this->status).", ";
		$sql .= ((int) $this->fk_user_author).", ";
		$sql .= "'".$this->db->idate($this->date_creation)."'";
		$sql .= ")";

		dol_syslog(__METHOD__, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			$error++;
		}

		if (!$error) {
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
			$this->rowid = $this->id;

			$this->db->commit();
			return $this->id;
		} else {
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 * Fetch bank record by ID or Ref
	 *
	 * @param  int    $id  ID of record
	 * @param  string $ref Ref of record
	 * @return int         <0 if KO, >0 if OK
	 */
	public function fetch($id, $ref = null)
	{
		$sql = "SELECT rowid, ref, fk_bank, dt_from, dt_to, opening_balance, closing_balance, total_debit, total_credit, nb_operations, status, fk_user_author, fk_user_valid, note, date_creation, date_valid, tms";
		$sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element;
		if ($id > 0) {
			$sql .= " WHERE rowid = ".((int) $id);
		} elseif (!empty($ref)) {
			$sql .= " WHERE ref = '".$this->db->escape($ref)."'";
		} else {
			return -1;
		}

		dol_syslog(__METHOD__, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql)) {
				$obj = $this->db->fetch_object($resql);

				$this->id = (int) $obj->rowid;
				$this->rowid = (int) $obj->rowid;
				$this->ref = $obj->ref;
				$this->fk_bank = (int) $obj->fk_bank;
				$this->dt_from = $this->db->jdate($obj->dt_from);
				$this->dt_to = $this->db->jdate($obj->dt_to);
				$this->opening_balance = $obj->opening_balance !== null ? (float) $obj->opening_balance : null;
				$this->closing_balance = $obj->closing_balance !== null ? (float) $obj->closing_balance : null;
				$this->total_debit = $obj->total_debit !== null ? (float) $obj->total_debit : null;
				$this->total_credit = $obj->total_credit !== null ? (float) $obj->total_credit : null;
				$this->nb_operations = $obj->nb_operations !== null ? (int) $obj->nb_operations : null;
				$this->status = (int) $obj->status;
				$this->fk_user_author = (int) $obj->fk_user_author;
				$this->fk_user_valid = (int) $obj->fk_user_valid;
				$this->note = $obj->note;
				$this->date_creation = $this->db->jdate($obj->date_creation);
				$this->date_valid = $this->db->jdate($obj->date_valid);

				$this->db->free($resql);
				return 1;
			}
			$this->db->free($resql);
			return 0;
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Update bank record in database
	 *
	 * @param  User $user      User that updates
	 * @param  int  $notrigger 0=launch triggers, 1=disable
	 * @return int             <0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = 0)
	{
		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET ";
		$sql .= "ref = '".$this->db->escape($this->ref)."', ";
		$sql .= "fk_bank = ".((int) $this->fk_bank).", ";
		$sql .= "dt_from = '".$this->db->idate($this->dt_from)."', ";
		$sql .= "dt_to = '".$this->db->idate($this->dt_to)."', ";
		$sql .= "opening_balance = ".($this->opening_balance !== null && $this->opening_balance !== '' ? price2num($this->opening_balance) : "NULL").", ";
		$sql .= "closing_balance = ".($this->closing_balance !== null && $this->closing_balance !== '' ? price2num($this->closing_balance) : "NULL").", ";
		$sql .= "total_debit = ".($this->total_debit !== null && $this->total_debit !== '' ? price2num($this->total_debit) : "NULL").", ";
		$sql .= "total_credit = ".($this->total_credit !== null && $this->total_credit !== '' ? price2num($this->total_credit) : "NULL").", ";
		$sql .= "nb_operations = ".($this->nb_operations !== null && $this->nb_operations !== '' ? ((int) $this->nb_operations) : "NULL").", ";
		$sql .= "status = ".((int) $this->status).", ";
		$sql .= "note = ".(!empty($this->note) ? "'".$this->db->escape($this->note)."'" : "NULL")." ";
		$sql .= "WHERE rowid = ".((int) $this->id);

		dol_syslog(__METHOD__, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			return 1;
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Delete bank record and its links
	 *
	 * @param  User $user      User that deletes
	 * @param  int  $notrigger 0=launch triggers, 1=disable
	 * @return int             <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 0)
	{
		$this->db->begin();

		// Delete links first
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."bank_record_link WHERE fk_bank_record = ".((int) $this->id);
		$this->db->query($sql);

		$sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE rowid = ".((int) $this->id);
		$resql = $this->db->query($sql);
		if ($resql) {
			$this->db->commit();
			return 1;
		} else {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 * Link an imported line from llx_bank_import to this record
	 *
	 * @param  int $import_id ID of llx_bank_import row
	 * @return int            <0 if KO, >0 if OK
	 */
	public function link_line($import_id)
	{
		if (empty($this->id) || empty($import_id)) {
			return -1;
		}

		$sql = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."bank_record_link (fk_bank_record, fk_bank_import) VALUES (";
		$sql .= ((int) $this->id).", ".((int) $import_id).")";

		$resql = $this->db->query($sql);
		return $resql ? 1 : -1;
	}

	/**
	 * Automatically link imported lines falling within this statement period
	 *
	 * @return int Number of newly linked lines, or -1 on error
	 */
	public function auto_link_lines()
	{
		if (empty($this->id) || empty($this->fk_bank) || empty($this->dt_from) || empty($this->dt_to)) {
			return -1;
		}

		$date_start = $this->db->idate($this->dt_from);
		$date_end = $this->db->idate($this->dt_to);

		$sql = "INSERT IGNORE INTO ".MAIN_DB_PREFIX."bank_record_link (fk_bank_record, fk_bank_import) ";
		$sql .= "SELECT ".((int) $this->id).", bi.rowid ";
		$sql .= "FROM ".MAIN_DB_PREFIX."bank_import as bi ";
		$sql .= "WHERE bi.id_account = ".((int) $this->fk_bank)." ";
		$sql .= "AND bi.bdate >= '".$this->db->escape($date_start)." 00:00:00' ";
		$sql .= "AND bi.bdate <= '".$this->db->escape($date_end)." 23:59:59' ";
		$sql .= "AND bi.deleted_date IS NULL";

		$resql = $this->db->query($sql);
		if ($resql) {
			return $this->db->affected_rows($resql);
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Fetch lines linked to this statement
	 *
	 * @return array Array of lines
	 */
	public function fetch_lines()
	{
		$this->lines = array();

		if (empty($this->id)) {
			return $this->lines;
		}

		$sql = "SELECT bi.* ";
		$sql .= "FROM ".MAIN_DB_PREFIX."bank_import as bi ";
		$sql .= "INNER JOIN ".MAIN_DB_PREFIX."bank_record_link as brl ON brl.fk_bank_import = bi.rowid ";
		$sql .= "WHERE brl.fk_bank_record = ".((int) $this->id)." ";
		$sql .= "ORDER BY bi.bdate ASC, bi.rowid ASC";

		$resql = $this->db->query($sql);
		if ($resql) {
			while ($obj = $this->db->fetch_object($resql)) {
				$this->lines[] = $obj;
			}
			$this->db->free($resql);
		}

		return $this->lines;
	}

	/**
	 * Calculate dynamic stats on-the-fly from linked transactions.
	 * Note: These values are NOT stored in the database table to respect banking & accounting normalization.
	 *
	 * @return array Array with calculated_debit, calculated_credit, nb_operations, calculated_end_balance, gap
	 */
	public function getCalculatedStats()
	{
		$stats = array(
			'nb_operations' => 0,
			'calculated_debit' => 0.0,
			'calculated_credit' => 0.0,
			'calculated_end_balance' => 0.0,
			'gap' => 0.0,
		);

		if (empty($this->id)) {
			return $stats;
		}

		$sql = "SELECT COUNT(bi.rowid) as nb, SUM(ABS(bi.amount_debit)) as debit, SUM(ABS(bi.amount_credit)) as credit ";
		$sql .= "FROM ".MAIN_DB_PREFIX."bank_import as bi ";
		$sql .= "INNER JOIN ".MAIN_DB_PREFIX."bank_record_link as brl ON brl.fk_bank_import = bi.rowid ";
		$sql .= "WHERE brl.fk_bank_record = ".((int) $this->id)." ";
		$sql .= "AND bi.deleted_date IS NULL";

		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql))) {
			$stats['nb_operations'] = (int) $obj->nb;
			$stats['calculated_debit'] = (float) $obj->debit;
			$stats['calculated_credit'] = (float) $obj->credit;
			$stats['total_debit'] = (float) $obj->debit;
			$stats['total_credit'] = (float) $obj->credit;

			if ($this->opening_balance !== null) {
				$stats['calculated_end_balance'] = (float) ($this->opening_balance + $stats['calculated_credit'] - $stats['calculated_debit']);
				if ($this->closing_balance !== null) {
					$stats['gap'] = (float) round($this->closing_balance - $stats['calculated_end_balance'], 2);
				}
			}
			$this->db->free($resql);
		}

		return $stats;
	}

	/**
	 * Return status badge
	 *
	 * @param  int    $mode 0=long label, 1=short label, 2=picto, 3=badge
	 * @return string       Status HTML
	 */
	public function getLibStatut($mode = 3)
	{
		global $langs;

		if ($this->status == self::STATUS_VALIDATED) {
			return '<span class="badge badge-status4 badge-status">'.$langs->trans("Validated").'</span>';
		} elseif ($this->status == self::STATUS_CANCELED) {
			return '<span class="badge badge-status5 badge-status">'.$langs->trans("Canceled").'</span>';
		} else {
			return '<span class="badge badge-status1 badge-status">'.$langs->trans("Draft").'</span>';
		}
	}
}
