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
 * \file        class/bankrecordline.class.php
 * \ingroup     ezcompta
 * \brief       Class for BankRecordLine (Bank statement line item)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobjectline.class.php';

/**
 * Class of bank record detail lines
 * Follows ISO 20022 CAMT.053 & SWIFT MT940 banking standards.
 */
class BankRecordLine extends CommonObjectLine
{
	/**
	 * @var string ID of module.
	 */
	public $module = 'ezcompta';

	/**
	 * @var string ID to identify managed object.
	 */
	public $element = 'bankrecordline';

	/**
	 * @var string Name of table without prefix where object is stored.
	 */
	public $table_element = 'bank_record_det';

	const STATUS_UNMATCHED = 0;
	const STATUS_RECONCILED = 1;
	const STATUS_EXCLUDED = 2;

	/**
	 * @var int Bank record line ID
	 */
	public $rowid;

	/**
	 * @var int Foreign key to parent bank record (llx_bank_record.rowid)
	 */
	public $fk_bank_record;

	/**
	 * @var int Booking date timestamp (<BookgDt>)
	 */
	public $bdate;

	/**
	 * @var int Value date timestamp (<ValDt>)
	 */
	public $vdate;

	/**
	 * @var float Signed amount (+ credit, - debit)
	 */
	public $amount = 0.0;

	/**
	 * @var string Currency ISO code (e.g. EUR)
	 */
	public $currency_code = 'EUR';

	/**
	 * @var float Charges / fees amount (<Chrgs><Amt>)
	 */
	public $charges_amount = 0.0;

	/**
	 * @var string|null End to end identification (<EndToEndId>)
	 */
	public $end_to_end_id;

	/**
	 * @var string|null Bank unique transaction reference (<AcctSvcrRef>)
	 */
	public $acct_svcr_ref;

	/**
	 * @var string|null Mandate reference for direct debits (<MndtId>)
	 */
	public $mandate_id;

	/**
	 * @var string|null Check number (<ChqNb>)
	 */
	public $check_number;

	/**
	 * @var string|null Counterparty name (<Dbtr><Nm> or <Cdtr><Nm>)
	 */
	public $counterparty_name;

	/**
	 * @var string|null Counterparty IBAN
	 */
	public $counterparty_iban;

	/**
	 * @var string|null Counterparty BIC
	 */
	public $counterparty_bic;

	/**
	 * @var string Operation label
	 */
	public $label = '';

	/**
	 * @var string|null Full unstructured remittance text (<Ustrd>)
	 */
	public $remittance_unstructured;

	/**
	 * @var string|null Structured remittance reference (ISO 11649 / <Strd>)
	 */
	public $remittance_structured;

	/**
	 * @var string|null ISO Bank Transaction Code (e.g. PMNT-ICDT-ESCT)
	 */
	public $bank_tx_code;

	/**
	 * @var string|null National proprietary code (e.g. CFONB)
	 */
	public $proprietary_code;

	/**
	 * @var int|null Thirdparty ID in llx_societe
	 */
	public $fk_soc;

	/**
	 * @var int|null Linked entry in llx_bank
	 */
	public $fk_bank;

	/**
	 * @var int Status (0=Unmatched, 1=Reconciled, 2=Excluded)
	 */
	public $status = 0;

	/**
	 * @var string|null Raw source XML / JSON payload
	 */
	public $datas;

	/**
	 * @var string|null Import batch key
	 */
	public $import_key;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
		$this->status = self::STATUS_UNMATCHED;
	}

	/**
	 * Create bank record line into database
	 *
	 * @param  User|null $user      User that creates
	 * @param  int       $notrigger 0=launch triggers after, 1=disable triggers
	 * @return int                  Return integer <0 if KO, id of created line if OK
	 */
	public function create($user = null, $notrigger = 0)
	{
		if (empty($this->fk_bank_record) || empty($this->bdate)) {
			$this->error = 'ErrorMandatoryParametersNotProvided';
			return -1;
		}

		$this->db->begin();

		$sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";
		$sql .= "fk_bank_record, bdate, vdate, amount, currency_code, charges_amount, ";
		$sql .= "end_to_end_id, acct_svcr_ref, mandate_id, check_number, ";
		$sql .= "counterparty_name, counterparty_iban, counterparty_bic, ";
		$sql .= "label, remittance_unstructured, remittance_structured, ";
		$sql .= "bank_tx_code, proprietary_code, fk_soc, fk_bank, status, datas, import_key";
		$sql .= ") VALUES (";
		$sql .= ((int) $this->fk_bank_record).", ";
		$sql .= "'".$this->db->idate($this->bdate)."', ";
		$sql .= "'".$this->db->idate(!empty($this->vdate) ? $this->vdate : $this->bdate)."', ";
		$sql .= price2num($this->amount).", ";
		$sql .= "'".$this->db->escape(!empty($this->currency_code) ? $this->currency_code : 'EUR')."', ";
		$sql .= price2num($this->charges_amount).", ";
		$sql .= (!empty($this->end_to_end_id) ? "'".$this->db->escape($this->end_to_end_id)."'" : "NULL").", ";
		$sql .= (!empty($this->acct_svcr_ref) ? "'".$this->db->escape($this->acct_svcr_ref)."'" : "NULL").", ";
		$sql .= (!empty($this->mandate_id) ? "'".$this->db->escape($this->mandate_id)."'" : "NULL").", ";
		$sql .= (!empty($this->check_number) ? "'".$this->db->escape($this->check_number)."'" : "NULL").", ";
		$sql .= (!empty($this->counterparty_name) ? "'".$this->db->escape($this->counterparty_name)."'" : "NULL").", ";
		$sql .= (!empty($this->counterparty_iban) ? "'".$this->db->escape($this->counterparty_iban)."'" : "NULL").", ";
		$sql .= (!empty($this->counterparty_bic) ? "'".$this->db->escape($this->counterparty_bic)."'" : "NULL").", ";
		$sql .= "'".$this->db->escape($this->label)."', ";
		$sql .= (!empty($this->remittance_unstructured) ? "'".$this->db->escape($this->remittance_unstructured)."'" : "NULL").", ";
		$sql .= (!empty($this->remittance_structured) ? "'".$this->db->escape($this->remittance_structured)."'" : "NULL").", ";
		$sql .= (!empty($this->bank_tx_code) ? "'".$this->db->escape($this->bank_tx_code)."'" : "NULL").", ";
		$sql .= (!empty($this->proprietary_code) ? "'".$this->db->escape($this->proprietary_code)."'" : "NULL").", ";
		$sql .= (!empty($this->fk_soc) && $this->fk_soc > 0 ? ((int) $this->fk_soc) : "NULL").", ";
		$sql .= (!empty($this->fk_bank) && $this->fk_bank > 0 ? ((int) $this->fk_bank) : "NULL").", ";
		$sql .= ((int) $this->status).", ";
		$sql .= (!empty($this->datas) ? "'".$this->db->escape($this->datas)."'" : "NULL").", ";
		$sql .= (!empty($this->import_key) ? "'".$this->db->escape($this->import_key)."'" : "NULL");
		$sql .= ")";

		dol_syslog(__METHOD__, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			$this->db->rollback();
			return -1;
		}

		$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
		$this->rowid = $this->id;

		$this->db->commit();
		return $this->id;
	}

	/**
	 * Fetch bank record line by ID
	 *
	 * @param  int $id ID of line
	 * @return int    Return integer <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id)
	{
		$sql = "SELECT rowid, fk_bank_record, bdate, vdate, amount, currency_code, charges_amount, ";
		$sql .= "end_to_end_id, acct_svcr_ref, mandate_id, check_number, ";
		$sql .= "counterparty_name, counterparty_iban, counterparty_bic, ";
		$sql .= "label, remittance_unstructured, remittance_structured, ";
		$sql .= "bank_tx_code, proprietary_code, fk_soc, fk_bank, status, datas, import_key, tms ";
		$sql .= "FROM ".MAIN_DB_PREFIX.$this->table_element." ";
		$sql .= "WHERE rowid = ".((int) $id);

		dol_syslog(__METHOD__, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			if ($this->db->num_rows($resql)) {
				$obj = $this->db->fetch_object($resql);
				if ($obj) {
					$this->id = (int) $obj->rowid;
					$this->rowid = (int) $obj->rowid;
					$this->fk_bank_record = (int) $obj->fk_bank_record;
					$this->bdate = $this->db->jdate($obj->bdate);
					$this->vdate = $this->db->jdate($obj->vdate);
					$this->amount = (float) $obj->amount;
					$this->currency_code = $obj->currency_code;
					$this->charges_amount = (float) $obj->charges_amount;
					$this->end_to_end_id = $obj->end_to_end_id;
					$this->acct_svcr_ref = $obj->acct_svcr_ref;
					$this->mandate_id = $obj->mandate_id;
					$this->check_number = $obj->check_number;
					$this->counterparty_name = $obj->counterparty_name;
					$this->counterparty_iban = $obj->counterparty_iban;
					$this->counterparty_bic = $obj->counterparty_bic;
					$this->label = $obj->label;
					$this->remittance_unstructured = $obj->remittance_unstructured;
					$this->remittance_structured = $obj->remittance_structured;
					$this->bank_tx_code = $obj->bank_tx_code;
					$this->proprietary_code = $obj->proprietary_code;
					$this->fk_soc = !empty($obj->fk_soc) ? (int) $obj->fk_soc : null;
					$this->fk_bank = !empty($obj->fk_bank) ? (int) $obj->fk_bank : null;
					$this->status = (int) $obj->status;
					$this->datas = $obj->datas;
					$this->import_key = $obj->import_key;

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
	 * Update bank record line in database
	 *
	 * @param  User|null $user      User that updates
	 * @param  int       $notrigger 0=launch triggers, 1=disable
	 * @return int                  Return integer <0 if KO, >0 if OK
	 */
	public function update($user = null, $notrigger = 0)
	{
		$sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET ";
		$sql .= "bdate = '".$this->db->idate($this->bdate)."', ";
		$sql .= "vdate = '".$this->db->idate(!empty($this->vdate) ? $this->vdate : $this->bdate)."', ";
		$sql .= "amount = ".price2num($this->amount).", ";
		$sql .= "currency_code = '".$this->db->escape(!empty($this->currency_code) ? $this->currency_code : 'EUR')."', ";
		$sql .= "charges_amount = ".price2num($this->charges_amount).", ";
		$sql .= "end_to_end_id = ".(!empty($this->end_to_end_id) ? "'".$this->db->escape($this->end_to_end_id)."'" : "NULL").", ";
		$sql .= "acct_svcr_ref = ".(!empty($this->acct_svcr_ref) ? "'".$this->db->escape($this->acct_svcr_ref)."'" : "NULL").", ";
		$sql .= "mandate_id = ".(!empty($this->mandate_id) ? "'".$this->db->escape($this->mandate_id)."'" : "NULL").", ";
		$sql .= "check_number = ".(!empty($this->check_number) ? "'".$this->db->escape($this->check_number)."'" : "NULL").", ";
		$sql .= "counterparty_name = ".(!empty($this->counterparty_name) ? "'".$this->db->escape($this->counterparty_name)."'" : "NULL").", ";
		$sql .= "counterparty_iban = ".(!empty($this->counterparty_iban) ? "'".$this->db->escape($this->counterparty_iban)."'" : "NULL").", ";
		$sql .= "counterparty_bic = ".(!empty($this->counterparty_bic) ? "'".$this->db->escape($this->counterparty_bic)."'" : "NULL").", ";
		$sql .= "label = '".$this->db->escape($this->label)."', ";
		$sql .= "remittance_unstructured = ".(!empty($this->remittance_unstructured) ? "'".$this->db->escape($this->remittance_unstructured)."'" : "NULL").", ";
		$sql .= "remittance_structured = ".(!empty($this->remittance_structured) ? "'".$this->db->escape($this->remittance_structured)."'" : "NULL").", ";
		$sql .= "bank_tx_code = ".(!empty($this->bank_tx_code) ? "'".$this->db->escape($this->bank_tx_code)."'" : "NULL").", ";
		$sql .= "proprietary_code = ".(!empty($this->proprietary_code) ? "'".$this->db->escape($this->proprietary_code)."'" : "NULL").", ";
		$sql .= "fk_soc = ".(!empty($this->fk_soc) && $this->fk_soc > 0 ? ((int) $this->fk_soc) : "NULL").", ";
		$sql .= "fk_bank = ".(!empty($this->fk_bank) && $this->fk_bank > 0 ? ((int) $this->fk_bank) : "NULL").", ";
		$sql .= "status = ".((int) $this->status)." ";
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
	 * Delete bank record line
	 *
	 * @param  User|null $user      User that deletes
	 * @param  int       $notrigger 0=launch triggers, 1=disable
	 * @return int                  Return integer <0 if KO, >0 if OK
	 */
	public function delete($user = null, $notrigger = 0)
	{
		$sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element." WHERE rowid = ".((int) $this->id);
		$resql = $this->db->query($sql);
		if ($resql) {
			return 1;
		} else {
			$this->error = $this->db->lasterror();
			return -1;
		}
	}

	/**
	 * Return the status label or badge
	 *
	 * @param  int $mode 0=long label, 1=short label, 2=picto, 3=badge
	 * @return string    Label or HTML badge
	 */
	public function getLibStatut($mode = 3)
	{
		global $langs;

		if ($this->status == self::STATUS_RECONCILED) {
			return '<span class="badge badge-status4 badge-status">'.$langs->trans("BankLineReconciled").'</span>';
		} elseif ($this->status == self::STATUS_EXCLUDED) {
			return '<span class="badge badge-status5 badge-status">'.$langs->trans("Excluded").'</span>';
		} else {
			return '<span class="badge badge-status0 badge-status">'.$langs->trans("BankLineNotReconciled").'</span>';
		}
	}
}
