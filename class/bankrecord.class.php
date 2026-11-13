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
require_once __DIR__.'/bankrecordline.class.php';

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
	 * @var string Name of table without prefix where lines are stored.
	 */
	public $table_element_line = 'bank_record_det';

	/**
	 * @var string Class name of line object.
	 */
	public $class_element_line = 'BankRecordLine';

	/**
	 * @var string Foreign key in lines table pointing to parent object.
	 */
	public $fk_element = 'fk_bank_record';

	/**
	 * @var string String with name of icon for bankrecord.
	 */
	public $picto = 'fa-file-invoice-dollar';

	/**
	 * @var int 1 = entity is managed
	 */
	public $ismultientitymanaged = 1;

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
	 * @var int Multi-company entity ID
	 */
	public $entity = 1;

	/**
	 * @var string|null External system reference / Open Banking ID
	 */
	public $ref_ext;

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
	 * @var float|null Opening balance read on official bank PDF statement (ISO 20022: OPBD / MT940: :60F:)
	 */
	public $opening_balance;

	/**
	 * @var float|null Closing balance read on official bank PDF statement (ISO 20022: CLBD / MT940: :62F:)
	 */
	public $closing_balance;

	/**
	 * @var float|null Total debit read on official bank PDF statement (ISO 20022: TtlDbtNtries)
	 */
	public $total_debit;

	/**
	 * @var float|null Total credit read on official bank PDF statement (ISO 20022: TtlCdtNtries)
	 */
	public $total_credit;

	/**
	 * @var int|null Total number of operations/entries on the official bank statement (ISO 20022: NbOfNtries)
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
	 * @var int|null User last modifier ID
	 */
	public $fk_user_modif;

	/**
	 * @var int|null User validator ID
	 */
	public $fk_user_valid;

	/**
	 * @var string|null Legacy note field
	 */
	public $note;

	/**
	 * @var string|null Private accounting notes
	 */
	public $note_private;

	/**
	 * @var string|null Public notes (for PDF / exports)
	 */
	public $note_public;

	/**
	 * @var string|null Document generation template model
	 */
	public $model_pdf;

	/**
	 * @var string Relative path to last generated main document / uploaded PDF
	 */
	public $last_main_doc;

	/**
	 * @var string|null Mass import batch key
	 */
	public $import_key;

	/**
	 * @var int Creation date
	 */
	public $date_creation;

	/**
	 * @var int|null Validation date
	 */
	public $date_valid;

	/**
	 * @var array<BankRecordLine|CommonObject|stdClass> Lines of statement
	 */
	public $lines = array();

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $conf;

		$this->db = $db;
		$this->status = self::STATUS_DRAFT;
		$this->entity = (!empty($conf->entity) ? (int) $conf->entity : 1);
	}

	/**
	 * Initialize specimen object for numbering preview
	 *
	 * @return int 1
	 */
	public function initAsSpecimen()
	{
		$this->id = 0;
		$this->ref = 'SPECIMEN';
		$this->specimen = 1;
		$this->entity = 1;
		$this->fk_bank = 1;
		$this->dt_from = dol_now() - 30 * 86400;
		$this->dt_to = dol_now();
		$this->date_creation = dol_now();
		$this->datec = dol_now();
		$this->opening_balance = 1000.0;
		$this->closing_balance = 1500.0;
		$this->total_debit = 500.0;
		$this->total_credit = 1000.0;
		$this->nb_operations = 5;
		$this->status = self::STATUS_DRAFT;
		return 1;
	}

	/**
	 * Return next reference for bank record
	 *
	 * @return string|int Next reference string, or -1 on error
	 */
	public function getNextNumRef()
	{
		global $conf, $langs;
		$langs->load("ezcompta@ezcompta");

		$addon = getDolGlobalString('EZCOMPTA_BANKSTATEMENT_ADDON', 'mod_bankstatement_bankomatik');
		if ($addon == 'mod_bankstatement_standard') {
			$addon = 'mod_bankstatement_bankomatik';
		} elseif ($addon == 'mod_bankstatement_free') {
			$addon = 'mod_bankstatement_bankanarchie';
		} elseif ($addon == 'mod_bankstatement_advanced') {
			$addon = 'mod_bankstatement_bankalchimiste';
		}

		$file = $addon . '.php';
		$classname = $addon;

		$res = dol_include_once('/ezcompta/core/modules/ezcompta/' . $file);
		if ($res <= 0) {
			$res = dol_include_once('/core/modules/ezcompta/' . $file);
		}

		if (class_exists($classname)) {
			$obj = new $classname($this->db);
			$numref = $obj->getNextValue($this);
			if ($numref !== -1 && $numref !== false) {
				return $numref;
			} else {
				$this->error = $obj->error;
				return -1;
			}
		} else {
			$this->error = "Numbering module '".$addon."' not found";
			return -1;
		}
	}

	/**
	 * Create bank record into database
	 *
	 * @param  User $user      User that creates
	 * @param  int  $notrigger 0=launch triggers after, 1=disable triggers
	 * @return int             Return integer <0 if KO, id of created object if OK
	 */
	public function create(User $user, $notrigger = 0)
	{
		global $conf;

		$error = 0;

		$now = dol_now();
		$this->date_creation = $now;
		$this->fk_user_author = $user->id;
		if (empty($this->entity)) {
			$this->entity = (!empty($conf->entity) ? (int) $conf->entity : 1);
		}

		if (empty($this->ref)) {
			$addon = getDolGlobalString('EZCOMPTA_BANKSTATEMENT_ADDON', 'mod_bankstatement_bankomatik');
			if ($addon != 'mod_bankstatement_bankanarchie' && $addon != 'mod_bankstatement_free') {
				$numref = $this->getNextNumRef();
				if ($numref !== -1 && !empty($numref)) {
					$this->ref = $numref;
				}
			}
		}

		if (!empty($this->note) && empty($this->note_private)) {
			$this->note_private = $this->note;
		}

		$this->db->begin();

		$sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";
		$sql .= "ref, entity, ref_ext, fk_bank, dt_from, dt_to, opening_balance, closing_balance, total_debit, total_credit, nb_operations, status, fk_user_author, note, note_private, note_public, model_pdf, last_main_doc, import_key, date_creation";
		$sql .= ") VALUES (";
		$sql .= "'".$this->db->escape($this->ref)."', ";
		$sql .= ((int) $this->entity).", ";
		$sql .= (!empty($this->ref_ext) ? "'".$this->db->escape($this->ref_ext)."'" : "NULL").", ";
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
		$sql .= (!empty($this->note_private) ? "'".$this->db->escape($this->note_private)."'" : "NULL").", ";
		$sql .= (!empty($this->note_private) ? "'".$this->db->escape($this->note_private)."'" : "NULL").", ";
		$sql .= (!empty($this->note_public) ? "'".$this->db->escape($this->note_public)."'" : "NULL").", ";
		$sql .= (!empty($this->model_pdf) ? "'".$this->db->escape($this->model_pdf)."'" : "NULL").", ";
		$sql .= (!empty($this->last_main_doc) ? "'".$this->db->escape($this->last_main_doc)."'" : "NULL").", ";
		$sql .= (!empty($this->import_key) ? "'".$this->db->escape($this->import_key)."'" : "NULL").", ";
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
	 * @return int         Return integer <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id, $ref = null)
	{
		$sql = "SELECT rowid, ref, entity, ref_ext, fk_bank, dt_from, dt_to, opening_balance, closing_balance, total_debit, total_credit, nb_operations, status, fk_user_author, fk_user_modif, fk_user_valid, note, note_private, note_public, model_pdf, last_main_doc, import_key, date_creation, date_valid, tms";
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
				if ($obj) {
					$this->id = (int) $obj->rowid;
					$this->rowid = (int) $obj->rowid;
					$this->ref = $obj->ref;
					$this->entity = isset($obj->entity) ? (int) $obj->entity : 1;
					$this->ref_ext = isset($obj->ref_ext) ? $obj->ref_ext : null;
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
					$this->fk_user_modif = !empty($obj->fk_user_modif) ? (int) $obj->fk_user_modif : null;
					$this->fk_user_valid = !empty($obj->fk_user_valid) ? (int) $obj->fk_user_valid : null;
					$this->note_private = isset($obj->note_private) ? $obj->note_private : null;
					$this->note_public = isset($obj->note_public) ? $obj->note_public : null;
					$this->note = !empty($this->note_private) ? $this->note_private : $obj->note;
					$this->model_pdf = isset($obj->model_pdf) ? $obj->model_pdf : null;
					$this->last_main_doc = isset($obj->last_main_doc) ? $obj->last_main_doc : null;
					$this->import_key = isset($obj->import_key) ? $obj->import_key : null;
					$this->date_creation = $this->db->jdate($obj->date_creation);
					$this->date_valid = !empty($obj->date_valid) ? $this->db->jdate($obj->date_valid) : null;

					$this->db->free($resql);
					return 1;
				}
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
	 * @return int             Return integer <0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = 0)
	{
		$this->fk_user_modif = $user->id;

		if (!empty($this->note) && empty($this->note_private)) {
			$this->note_private = $this->note;
		}

		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET ";
		$sql .= "ref = '".$this->db->escape($this->ref)."', ";
		$sql .= "ref_ext = ".(!empty($this->ref_ext) ? "'".$this->db->escape($this->ref_ext)."'" : "NULL").", ";
		$sql .= "fk_bank = ".((int) $this->fk_bank).", ";
		$sql .= "dt_from = '".$this->db->idate($this->dt_from)."', ";
		$sql .= "dt_to = '".$this->db->idate($this->dt_to)."', ";
		$sql .= "opening_balance = ".($this->opening_balance !== null && $this->opening_balance !== '' ? price2num($this->opening_balance) : "NULL").", ";
		$sql .= "closing_balance = ".($this->closing_balance !== null && $this->closing_balance !== '' ? price2num($this->closing_balance) : "NULL").", ";
		$sql .= "total_debit = ".($this->total_debit !== null && $this->total_debit !== '' ? price2num($this->total_debit) : "NULL").", ";
		$sql .= "total_credit = ".($this->total_credit !== null && $this->total_credit !== '' ? price2num($this->total_credit) : "NULL").", ";
		$sql .= "nb_operations = ".($this->nb_operations !== null && $this->nb_operations !== '' ? ((int) $this->nb_operations) : "NULL").", ";
		$sql .= "status = ".((int) $this->status).", ";
		$sql .= "fk_user_modif = ".((int) $this->fk_user_modif).", ";
		$sql .= "note = ".(!empty($this->note_private) ? "'".$this->db->escape($this->note_private)."'" : "NULL").", ";
		$sql .= "note_private = ".(!empty($this->note_private) ? "'".$this->db->escape($this->note_private)."'" : "NULL").", ";
		$sql .= "note_public = ".(!empty($this->note_public) ? "'".$this->db->escape($this->note_public)."'" : "NULL").", ";
		$sql .= "model_pdf = ".(!empty($this->model_pdf) ? "'".$this->db->escape($this->model_pdf)."'" : "NULL").", ";
		$sql .= "last_main_doc = ".(!empty($this->last_main_doc) ? "'".$this->db->escape($this->last_main_doc)."'" : "NULL").", ";
		$sql .= "import_key = ".(!empty($this->import_key) ? "'".$this->db->escape($this->import_key)."'" : "NULL")." ";
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
	 * Delete bank record and its lines
	 *
	 * @param  User $user      User that deletes
	 * @param  int  $notrigger 0=launch triggers, 1=disable
	 * @return int             Return integer <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 0)
	{
		$this->db->begin();

		// Delete native lines first
		$sql = "DELETE FROM ".MAIN_DB_PREFIX."bank_record_det WHERE fk_bank_record = ".((int) $this->id);
		$this->db->query($sql);

		// Delete legacy links
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
	 * Link an imported line from llx_bank_import to this record (legacy compatibility)
	 *
	 * @param  int $import_id ID of llx_bank_import row
	 * @return int            Return integer <0 if KO, >0 if OK
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
	 * Fetch lines linked to this statement (from llx_bank_record_det or legacy llx_bank_import)
	 *
	 * @return array<BankRecordLine|CommonObject|stdClass> Array of lines
	 */
	public function fetch_lines()
	{
		$this->lines = array();

		if (empty($this->id)) {
			return $this->lines;
		}

		// 1. Try native llx_bank_record_det first
		$sql = "SELECT d.* ";
		$sql .= "FROM ".MAIN_DB_PREFIX."bank_record_det as d ";
		$sql .= "WHERE d.fk_bank_record = ".((int) $this->id)." ";
		$sql .= "ORDER BY d.bdate ASC, d.rowid ASC";

		$resql = $this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql) > 0) {
				while ($obj = $this->db->fetch_object($resql)) {
					$line = new BankRecordLine($this->db);
					$line->id = (int) $obj->rowid;
					$line->rowid = (int) $obj->rowid;
					$line->fk_bank_record = (int) $obj->fk_bank_record;
					$line->bdate = $this->db->jdate($obj->bdate);
					$line->vdate = $this->db->jdate($obj->vdate);
					$line->amount = (float) $obj->amount;
					$line->currency_code = $obj->currency_code;
					$line->charges_amount = (float) $obj->charges_amount;
					$line->end_to_end_id = $obj->end_to_end_id;
					$line->acct_svcr_ref = $obj->acct_svcr_ref;
					$line->mandate_id = $obj->mandate_id;
					$line->check_number = $obj->check_number;
					$line->counterparty_name = $obj->counterparty_name;
					$line->counterparty_iban = $obj->counterparty_iban;
					$line->counterparty_bic = $obj->counterparty_bic;
					$line->label = $obj->label;
					$line->remittance_unstructured = $obj->remittance_unstructured;
					$line->remittance_structured = $obj->remittance_structured;
					$line->bank_tx_code = $obj->bank_tx_code;
					$line->proprietary_code = $obj->proprietary_code;
					$line->fk_soc = !empty($obj->fk_soc) ? (int) $obj->fk_soc : null;
					$line->fk_bank = !empty($obj->fk_bank) ? (int) $obj->fk_bank : null;
					$line->status = (int) $obj->status;
					$line->datas = $obj->datas;
					$line->import_key = $obj->import_key;
					$this->lines[] = $line;
				}
				$this->db->free($resql);
				return $this->lines;
			}
			$this->db->free($resql);
		}

		// 2. Fallback to legacy llx_bank_import via link table
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
	 * Alias of fetch_lines()
	 *
	 * @return array<BankRecordLine|CommonObject|stdClass> Array of lines
	 */
	public function fetchLines()
	{
		return $this->fetch_lines();
	}

	/**
	 * Alias of fetch_lines()
	 *
	 * @return array<BankRecordLine|CommonObject|stdClass> Array of lines
	 */
	public function fetchAllLines()
	{
		return $this->fetch_lines();
	}

	/**
	 * Calculate dynamic stats on-the-fly from statement transactions.
	 * Checks llx_bank_record_det first, then falls back to llx_bank_import.
	 *
	 * @return array<string, float|int> Array with calculated_debit, calculated_credit, nb_operations, calculated_end_balance, gap
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

		// 1. Try native llx_bank_record_det first
		$sql = "SELECT COUNT(rowid) as nb, ";
		$sql .= "SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as debit, ";
		$sql .= "SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as credit ";
		$sql .= "FROM ".MAIN_DB_PREFIX."bank_record_det ";
		$sql .= "WHERE fk_bank_record = ".((int) $this->id);

		$resql = $this->db->query($sql);
		if ($resql && ($obj = $this->db->fetch_object($resql)) && !empty($obj->nb)) {
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
			return $stats;
		}
		if ($resql) {
			$this->db->free($resql);
		}

		// 2. Fallback to legacy llx_bank_import
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

	/**
	 * Validate bank record
	 *
	 * @param  User $user User that validates
	 * @return int        Return integer <0 if KO, >0 if OK
	 */
	public function validate(User $user)
	{
		global $langs;

		$this->fetch_lines();
		$nb_lines = count($this->lines);

		if ($this->nb_operations !== null && $this->nb_operations > 0 && $nb_lines != $this->nb_operations) {
			$this->error = $langs->trans("ErrorLinesCountMismatchWithNbOperations", $nb_lines, $this->nb_operations);
			return -1;
		}

		$this->status = self::STATUS_VALIDATED;
		$this->date_valid = dol_now();
		$this->fk_user_valid = $user->id;
		return $this->update($user);
	}

	/**
	 * Re-open bank record to draft status
	 *
	 * @param  User $user User that re-opens
	 * @return int        Return integer <0 if KO, >0 if OK
	 */
	public function reopen(User $user)
	{
		$this->status = self::STATUS_DRAFT;
		$this->date_valid = null;
		$this->fk_user_valid = null;
		return $this->update($user);
	}

	/**
	 * Return link to bank record with picto
	 *
	 * @param  int    $withpicto 0=No picto, 1=Include picto, 2=Picto only
	 * @param  string $option    Options
	 * @param  int    $notooltip 0=Tooltip, 1=No tooltip
	 * @param  int    $save_lastsearch_value Save search
	 * @param  string $morecss   More css
	 * @return string            HTML link
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $save_lastsearch_value = -1, $morecss = '')
	{
		global $langs;

		$result = '';
		$url = dol_buildpath('/ezcompta/bankstatement_card.php', 1).'?id='.$this->id;

		$label = '<u>'.$langs->trans("BankStatement").'</u>';
		$label .= '<br><b>'.$langs->trans("Ref").':</b> '.$this->ref;
		if (!empty($this->dt_from) && !empty($this->dt_to)) {
			$label .= '<br><b>'.$langs->trans("Period").':</b> '.dol_print_date($this->dt_from, 'day').' - '.dol_print_date($this->dt_to, 'day');
		}
		if ($this->closing_balance !== null) {
			$label .= '<br><b>'.$langs->trans("EndingBalance").':</b> '.price($this->closing_balance);
		}

		$linkclose = '';
		if (empty($notooltip)) {
			$linkclose .= ' title="'.dol_escape_htmltag($label, 1).'" class="classfortooltip'.($morecss ? ' '.$morecss : '').'"';
		} else {
			$linkclose .= ($morecss ? ' class="'.$morecss.'"' : '');
		}

		$linkstart = '<a href="'.$url.'"'.$linkclose.'>';
		$linkend = '</a>';

		$picto = 'fa-file-invoice-dollar';

		if ($withpicto) {
			$result .= ($linkstart.img_picto('', $picto, 'class="paddingright"').$linkend);
		}
		if ($withpicto != 2) {
			$result .= $linkstart.$this->ref.$linkend;
		}

		return $result;
	}

	/**
	 * Add a statement line into database
	 *
	 * @param  int         $bdate                    Booking date
	 * @param  int         $vdate                    Value date
	 * @param  float       $amount                   Signed amount (+ credit, - debit)
	 * @param  string      $label                    Operation label
	 * @param  string      $currency_code            Currency code
	 * @param  float       $charges_amount           Charges amount
	 * @param  string|null $end_to_end_id            End to end ID
	 * @param  string|null $acct_svcr_ref            Account servicer reference
	 * @param  string|null $mandate_id               Mandate reference
	 * @param  string|null $check_number             Check number
	 * @param  string|null $counterparty_name        Counterparty name
	 * @param  string|null $counterparty_iban        Counterparty IBAN
	 * @param  string|null $counterparty_bic         Counterparty BIC
	 * @param  string|null $remittance_unstructured  Unstructured remittance
	 * @param  string|null $remittance_structured    Structured remittance
	 * @param  string|null $bank_tx_code             Bank transaction code
	 * @param  string|null $proprietary_code         Proprietary code
	 * @param  int|null    $fk_soc                   Thirdparty ID
	 * @param  int|null    $fk_bank                  Linked llx_bank entry ID
	 * @param  int         $status                   Status
	 * @param  string|null $datas                    Datas
	 * @param  string|null $import_key               Import key
	 * @return int                                   >0 if OK, <0 if KO
	 */
	public function addline(
		$bdate,
		$vdate,
		$amount,
		$label,
		$currency_code = 'EUR',
		$charges_amount = 0.0,
		$end_to_end_id = null,
		$acct_svcr_ref = null,
		$mandate_id = null,
		$check_number = null,
		$counterparty_name = null,
		$counterparty_iban = null,
		$counterparty_bic = null,
		$remittance_unstructured = null,
		$remittance_structured = null,
		$bank_tx_code = null,
		$proprietary_code = null,
		$fk_soc = null,
		$fk_bank = null,
		$status = 0,
		$datas = null,
		$import_key = null
	) {
		global $user;

		if (empty($this->id)) {
			return -1;
		}

		$line = new BankRecordLine($this->db);
		$line->fk_bank_record = $this->id;
		$line->bdate = $bdate;
		$line->vdate = !empty($vdate) ? $vdate : $bdate;
		$line->amount = $amount;
		$line->label = $label;
		$line->currency_code = !empty($currency_code) ? $currency_code : 'EUR';
		$line->charges_amount = $charges_amount;
		$line->end_to_end_id = $end_to_end_id;
		$line->acct_svcr_ref = $acct_svcr_ref;
		$line->mandate_id = $mandate_id;
		$line->check_number = $check_number;
		$line->counterparty_name = $counterparty_name;
		$line->counterparty_iban = $counterparty_iban;
		$line->counterparty_bic = $counterparty_bic;
		$line->remittance_unstructured = $remittance_unstructured;
		$line->remittance_structured = $remittance_structured;
		$line->bank_tx_code = $bank_tx_code;
		$line->proprietary_code = $proprietary_code;
		$line->fk_soc = $fk_soc;
		$line->fk_bank = $fk_bank;
		$line->status = $status;
		$line->datas = $datas;
		$line->import_key = $import_key;

		$res = $line->create($user);
		if ($res > 0) {
			$this->fetch_lines();
			return $res;
		} else {
			$this->error = $line->error;
			$this->errors = $line->errors;
			return -1;
		}
	}

	/**
	 * Update a statement line
	 *
	 * @param  int         $rowid                    Line ID
	 * @param  int         $bdate                    Booking date
	 * @param  int         $vdate                    Value date
	 * @param  float       $amount                   Signed amount (+ credit, - debit)
	 * @param  string      $label                    Operation label
	 * @param  string      $currency_code            Currency code
	 * @param  float       $charges_amount           Charges amount
	 * @param  string|null $end_to_end_id            End to end ID
	 * @param  string|null $acct_svcr_ref            Account servicer reference
	 * @param  string|null $mandate_id               Mandate reference
	 * @param  string|null $check_number             Check number
	 * @param  string|null $counterparty_name        Counterparty name
	 * @param  string|null $counterparty_iban        Counterparty IBAN
	 * @param  string|null $counterparty_bic         Counterparty BIC
	 * @param  string|null $remittance_unstructured  Unstructured remittance
	 * @param  string|null $remittance_structured    Structured remittance
	 * @param  string|null $bank_tx_code             Bank transaction code
	 * @param  string|null $proprietary_code         Proprietary code
	 * @param  int|null    $fk_soc                   Thirdparty ID
	 * @param  int|null    $fk_bank                  Linked llx_bank entry ID
	 * @param  int         $status                   Status
	 * @return int                                   >0 if OK, <0 if KO
	 */
	public function updateline(
		$rowid,
		$bdate,
		$vdate,
		$amount,
		$label,
		$currency_code = 'EUR',
		$charges_amount = 0.0,
		$end_to_end_id = null,
		$acct_svcr_ref = null,
		$mandate_id = null,
		$check_number = null,
		$counterparty_name = null,
		$counterparty_iban = null,
		$counterparty_bic = null,
		$remittance_unstructured = null,
		$remittance_structured = null,
		$bank_tx_code = null,
		$proprietary_code = null,
		$fk_soc = null,
		$fk_bank = null,
		$status = 0
	) {
		global $user;

		$line = new BankRecordLine($this->db);
		if ($line->fetch($rowid) <= 0) {
			return -1;
		}

		$line->bdate = $bdate;
		$line->vdate = !empty($vdate) ? $vdate : $bdate;
		$line->amount = $amount;
		$line->label = $label;
		$line->currency_code = !empty($currency_code) ? $currency_code : 'EUR';
		$line->charges_amount = $charges_amount;
		$line->end_to_end_id = $end_to_end_id;
		$line->acct_svcr_ref = $acct_svcr_ref;
		$line->mandate_id = $mandate_id;
		$line->check_number = $check_number;
		$line->counterparty_name = $counterparty_name;
		$line->counterparty_iban = $counterparty_iban;
		$line->counterparty_bic = $counterparty_bic;
		$line->remittance_unstructured = $remittance_unstructured;
		$line->remittance_structured = $remittance_structured;
		$line->bank_tx_code = $bank_tx_code;
		$line->proprietary_code = $proprietary_code;
		$line->fk_soc = $fk_soc;
		$line->fk_bank = $fk_bank;
		$line->status = $status;

		$res = $line->update($user);
		if ($res > 0) {
			$this->fetch_lines();
			return $res;
		} else {
			$this->error = $line->error;
			$this->errors = $line->errors;
			return -1;
		}
	}

	/**
	 * Delete a statement line
	 *
	 * @param  User|null $user    User that deletes
	 * @param  int       $line_id ID of line to delete
	 * @return int             >0 if OK, <0 if KO
	 */
	public function deleteline($user, $line_id)
	{
		$line = new BankRecordLine($this->db);
		if ($line->fetch($line_id) > 0) {
			if ($line->fk_bank_record == $this->id) {
				$res = $line->delete($user);
				if ($res > 0) {
					$this->fetch_lines();
					return 1;
				}
				$this->error = $line->error;
				$this->errors = $line->errors;
			}
		}
		return -1;
	}
}
