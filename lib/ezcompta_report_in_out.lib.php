<?php
/* Copyright (C) 2026		Nicolas Domenech			<nicolas.domenech@evarisk.com>
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
 *	\file       ezcompta/lib/ezcompta_report_in_out.lib.php
 *	\ingroup    ezcompta
 *	\brief      Aggregation and data provenance helpers for the in/out bank report
 *
 * Where the figures of the in/out report come from:
 *   1. the bank aggregator (Powens) feeds llx_banking4dolibarr_bank_record (module banking4dolibarr)
 *   2. the "transfer" button of ezcompta_index.php copies those records into llx_bank_import
 *   3. this report sums llx_bank_import.amount_credit / amount_debit grouped by month of bdate
 *
 * It reports the *bank statement*, not the Dolibarr accounting entries (llx_bank), which is why
 * both may differ. ezcomptaGetInOutSourceIndicator() measures that gap.
 */

dol_include_once('/ezcompta/lib/ezcompta.lib.php');	// for ezcomptaGetPendingTransferInfo()


/**
 * Return the SQL WHERE clauses shared by every query reading the bank statement table.
 *
 * @param	DoliDB	$db					Database handler
 * @param	int[]	$bankaccounts		Ids of the bank accounts to keep (empty = none)
 * @param	string	$datestart			First day to keep (format YYYY-MM-DD)
 * @param	string	$dateend			Last day to keep (format YYYY-MM-DD)
 * @param	int		$includeexcluded	1 = also keep records flagged as deleted or duplicate
 * @return	string						SQL string starting with " AND ..." (empty accounts give an always false clause)
 */
function ezcomptaInOutSqlFilter($db, $bankaccounts, $datestart, $dateend, $includeexcluded = 0)
{
	if (empty($bankaccounts)) {
		return " AND 1 = 0";
	}

	$sql = " AND ba.entity IN (".getEntity('bank_account').")";
	$sql .= " AND b.id_account IN (".$db->sanitize(implode(',', array_map('intval', $bankaccounts))).")";
	$sql .= " AND b.bdate >= '".$db->escape($datestart)."'";
	$sql .= " AND b.bdate <= '".$db->escape($dateend)."'";
	if (empty($includeexcluded)) {
		$sql .= " AND b.deleted_date IS NULL";
		$sql .= " AND b.fk_duplicate_of IS NULL";
	}

	return $sql;
}

/**
 * Aggregate the imported bank statement by month and by bank account.
 *
 * Debit is returned as a positive amount whatever the sign convention used by the source
 * (llx_bank_import stores it negative when fed by banking4dolibarr), so that no record is
 * silently dropped by a sign test.
 *
 * @param	DoliDB	$db					Database handler
 * @param	int[]	$bankaccounts		Ids of the bank accounts to keep
 * @param	string	$datestart			First day to keep (format YYYY-MM-DD)
 * @param	string	$dateend			Last day to keep (format YYYY-MM-DD)
 * @param	int		$includeexcluded	1 = also keep records flagged as deleted or duplicate
 * @return	array<string,mixed>|int		array('bymonth' => array, 'byaccountmonth' => array, 'accountsused' => array) or -1 on error
 */
function ezcomptaGetInOutStats($db, $bankaccounts, $datestart, $dateend, $includeexcluded = 0)
{
	$result = array('bymonth' => array(), 'byaccountmonth' => array(), 'accountsused' => array());

	if (empty($bankaccounts)) {
		return $result;
	}

	$sql = "SELECT b.id_account, DATE_FORMAT(b.bdate, '%Y-%m') as dm";
	$sql .= ", SUM(ABS(b.amount_credit)) as credit";
	$sql .= ", SUM(ABS(b.amount_debit)) as debit";
	$sql .= ", COUNT(b.rowid) as nb";
	$sql .= " FROM ".$db->prefix()."bank_import as b";
	$sql .= " INNER JOIN ".$db->prefix()."bank_account as ba ON ba.rowid = b.id_account";
	$sql .= " WHERE b.bdate IS NOT NULL";
	$sql .= ezcomptaInOutSqlFilter($db, $bankaccounts, $datestart, $dateend, $includeexcluded);
	$sql .= " GROUP BY b.id_account, dm";

	$resql = $db->query($sql);
	if (!$resql) {
		dol_print_error($db);
		return -1;
	}

	while ($obj = $db->fetch_object($resql)) {
		$idaccount = (int) $obj->id_account;
		$dm = $obj->dm;

		if (!isset($result['bymonth'][$dm])) {
			$result['bymonth'][$dm] = array('credit' => 0, 'debit' => 0, 'nb' => 0);
		}
		$result['bymonth'][$dm]['credit'] += (float) $obj->credit;
		$result['bymonth'][$dm]['debit'] += (float) $obj->debit;
		$result['bymonth'][$dm]['nb'] += (int) $obj->nb;

		$result['byaccountmonth'][$dm][$idaccount] = array(
			'credit' => (float) $obj->credit,
			'debit' => (float) $obj->debit,
			'nb' => (int) $obj->nb
		);
		$result['accountsused'][$idaccount] = $idaccount;
	}
	$db->free($resql);

	return $result;
}

/**
 * Collect everything needed to explain where the figures of the report come from, and to
 * spot the usual reasons why they do not match the bank or the Dolibarr accounting entries.
 *
 * @param	DoliDB	$db					Database handler
 * @param	int[]	$bankaccounts		Ids of the bank accounts to keep
 * @param	string	$datestart			First day to keep (format YYYY-MM-DD)
 * @param	string	$dateend			Last day to keep (format YYYY-MM-DD)
 * @param	int		$includeexcluded	1 = deleted/duplicate records are part of the displayed figures
 * @return	array<string,mixed>			Counters used by ezcomptaPrintInOutSourceIndicator()
 */
function ezcomptaGetInOutSourceIndicator($db, $bankaccounts, $datestart, $dateend, $includeexcluded = 0)
{
	$indicator = array(
		'includeexcluded' => (int) $includeexcluded,
		'datestart' => dol_stringtotime($datestart),
		'dateend' => dol_stringtotime($dateend),
		'nbaccounts' => count($bankaccounts),
		'nbtotal' => 0,
		'nbdeleted' => 0,
		'nbduplicate' => 0,
		'nbnodate' => 0,
		'nbwrongsign' => 0,
		'nbbothsides' => 0,
		'nbnoamount' => 0,
		'firstdate' => '',
		'lastdate' => '',
		'lasttransfer' => '',
		'statuses' => array(),
		'bankcredit' => 0,
		'bankdebit' => 0,
		'reportcredit' => 0,
		'reportdebit' => 0,
		'nblinked' => 0,
		'b4denabled' => isModEnabled('banking4dolibarr'),
		'b4dpending' => 0,
		'b4dlastdate' => ''
	);

	if (empty($bankaccounts)) {
		return $indicator;
	}

	// Volume and quality of the source records over the displayed period
	$sql = "SELECT COUNT(b.rowid) as nbtotal";
	$sql .= ", SUM(CASE WHEN b.deleted_date IS NOT NULL THEN 1 ELSE 0 END) as nbdeleted";
	$sql .= ", SUM(CASE WHEN b.fk_duplicate_of IS NOT NULL THEN 1 ELSE 0 END) as nbduplicate";
	$sql .= ", SUM(CASE WHEN b.amount_credit < 0 OR b.amount_debit > 0 THEN 1 ELSE 0 END) as nbwrongsign";
	$sql .= ", SUM(CASE WHEN b.amount_credit <> 0 AND b.amount_debit <> 0 THEN 1 ELSE 0 END) as nbbothsides";
	$sql .= ", SUM(CASE WHEN b.amount_credit = 0 AND b.amount_debit = 0 THEN 1 ELSE 0 END) as nbnoamount";
	$sql .= ", MIN(b.bdate) as firstdate, MAX(b.bdate) as lastdate, MAX(b.datec) as lasttransfer";
	$sql .= " FROM ".$db->prefix()."bank_import as b";
	$sql .= " INNER JOIN ".$db->prefix()."bank_account as ba ON ba.rowid = b.id_account";
	$sql .= " WHERE b.bdate IS NOT NULL";
	// Quality counters are computed on the whole period, whatever the deleted/duplicate choice
	$sql .= ezcomptaInOutSqlFilter($db, $bankaccounts, $datestart, $dateend, 1);

	$resql = $db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		if ($obj) {
			$indicator['nbtotal'] = (int) $obj->nbtotal;
			$indicator['nbdeleted'] = (int) $obj->nbdeleted;
			$indicator['nbduplicate'] = (int) $obj->nbduplicate;
			$indicator['nbwrongsign'] = (int) $obj->nbwrongsign;
			$indicator['nbbothsides'] = (int) $obj->nbbothsides;
			$indicator['nbnoamount'] = (int) $obj->nbnoamount;
			$indicator['firstdate'] = $db->jdate($obj->firstdate);
			$indicator['lastdate'] = $db->jdate($obj->lastdate);
			$indicator['lasttransfer'] = $db->jdate($obj->lasttransfer);
		}
		$db->free($resql);
	}

	// Records without an operation date are invisible in the report whatever the period
	$sql = "SELECT COUNT(b.rowid) as nb";
	$sql .= " FROM ".$db->prefix()."bank_import as b";
	$sql .= " INNER JOIN ".$db->prefix()."bank_account as ba ON ba.rowid = b.id_account";
	$sql .= " WHERE b.bdate IS NULL";
	$sql .= " AND ba.entity IN (".getEntity('bank_account').")";
	$sql .= " AND b.id_account IN (".$db->sanitize(implode(',', array_map('intval', $bankaccounts))).")";
	$resql = $db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		$indicator['nbnodate'] = $obj ? (int) $obj->nb : 0;
		$db->free($resql);
	}

	// Statuses found in the source, so that an unexpected one does not go unnoticed
	$sql = "SELECT b.status, COUNT(b.rowid) as nb";
	$sql .= " FROM ".$db->prefix()."bank_import as b";
	$sql .= " INNER JOIN ".$db->prefix()."bank_account as ba ON ba.rowid = b.id_account";
	$sql .= " WHERE b.bdate IS NOT NULL";
	$sql .= ezcomptaInOutSqlFilter($db, $bankaccounts, $datestart, $dateend, $includeexcluded);
	$sql .= " GROUP BY b.status ORDER BY b.status";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$indicator['statuses'][(int) $obj->status] = (int) $obj->nb;
		}
		$db->free($resql);
	}

	// Amounts really displayed by the report, to be compared with the Dolibarr entries
	$sql = "SELECT SUM(ABS(b.amount_credit)) as credit, SUM(ABS(b.amount_debit)) as debit";
	$sql .= " FROM ".$db->prefix()."bank_import as b";
	$sql .= " INNER JOIN ".$db->prefix()."bank_account as ba ON ba.rowid = b.id_account";
	$sql .= " WHERE b.bdate IS NOT NULL";
	$sql .= ezcomptaInOutSqlFilter($db, $bankaccounts, $datestart, $dateend, $includeexcluded);
	$resql = $db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		if ($obj) {
			$indicator['reportcredit'] = (float) $obj->credit;
			$indicator['reportdebit'] = (float) $obj->debit;
		}
		$db->free($resql);
	}

	// Same period seen from the Dolibarr bank entries (llx_bank), which this report does NOT use
	$sql = "SELECT SUM(CASE WHEN b.amount > 0 THEN b.amount ELSE 0 END) as credit";
	$sql .= ", SUM(CASE WHEN b.amount < 0 THEN -b.amount ELSE 0 END) as debit";
	$sql .= " FROM ".$db->prefix()."bank as b";
	$sql .= " INNER JOIN ".$db->prefix()."bank_account as ba ON ba.rowid = b.fk_account";
	$sql .= " WHERE ba.entity IN (".getEntity('bank_account').")";
	$sql .= " AND b.fk_account IN (".$db->sanitize(implode(',', array_map('intval', $bankaccounts))).")";
	$sql .= " AND b.dateo >= '".$db->escape($datestart)."'";
	$sql .= " AND b.dateo <= '".$db->escape($dateend)."'";
	$resql = $db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		if ($obj) {
			$indicator['bankcredit'] = (float) $obj->credit;
			$indicator['bankdebit'] = (float) $obj->debit;
		}
		$db->free($resql);
	}

	// How many statement lines are already reconciled with a Dolibarr entry
	$sql = "SELECT COUNT(DISTINCT l.fk_bank_import) as nb";
	$sql .= " FROM ".$db->prefix()."bank_record_link as l";
	$sql .= " INNER JOIN ".$db->prefix()."bank_import as b ON b.rowid = l.fk_bank_import";
	$sql .= " INNER JOIN ".$db->prefix()."bank_account as ba ON ba.rowid = b.id_account";
	$sql .= " WHERE b.bdate IS NOT NULL";
	$sql .= ezcomptaInOutSqlFilter($db, $bankaccounts, $datestart, $dateend, $includeexcluded);
	$resql = $db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		$indicator['nblinked'] = $obj ? (int) $obj->nb : 0;
		$db->free($resql);
	}

	// Records downloaded by banking4dolibarr but not transferred yet: the report is late by that much
	if ($indicator['b4denabled']) {
		$pending = ezcomptaGetPendingTransferInfo($db, $bankaccounts);
		$indicator['b4dpending'] = $pending['nb'];
		$indicator['b4dlastdate'] = $pending['lastdate'];
	}

	return $indicator;
}

/**
 * Print the provenance panel of the in/out report: the chain the figures went through, the
 * volume kept, what was left out and how far the statement is from the Dolibarr entries.
 *
 * @param	array<string,mixed>	$indicator	Result of ezcomptaGetInOutSourceIndicator()
 * @param	array<int,string>	$accounts	Bank accounts labels, keyed by id
 * @return	void
 */
function ezcomptaPrintInOutSourceIndicator($indicator, $accounts = array())
{
	global $langs;

	$nbkept = $indicator['nbtotal'];
	if (empty($indicator['includeexcluded'])) {
		$nbkept = $indicator['nbtotal'] - $indicator['nbdeleted'] - $indicator['nbduplicate'];
	}
	$gapcredit = $indicator['reportcredit'] - $indicator['bankcredit'];
	$gapdebit = $indicator['reportdebit'] - $indicator['bankdebit'];

	print '<div class="ezc-source div-table-responsive-no-min">';

	print '<div class="ezc-source-title">'.img_picto('', 'info', 'class="pictofixedwidth"').$langs->trans('EzComptaDataSourceTitle').'</div>';

	// The chain the figures went through
	print '<div class="ezc-chain">';
	$steps = array(
		array('label' => $langs->trans('EzComptaChainBank'), 'detail' => $langs->trans('EzComptaChainBankDetail')),
		array('label' => $langs->trans('EzComptaChainDownload'), 'detail' => 'llx_banking4dolibarr_bank_record'),
		array('label' => $langs->trans('EzComptaChainTransfer'), 'detail' => $langs->trans('EzComptaChainTransferDetail')),
		array('label' => $langs->trans('EzComptaChainStatement'), 'detail' => 'llx_bank_import'),
		array('label' => $langs->trans('EzComptaChainReport'), 'detail' => $langs->trans('EzComptaChainReportDetail'))
	);
	$laststep = count($steps) - 1;
	foreach ($steps as $i => $step) {
		print '<span class="ezc-chain-step'.($i == $laststep ? ' ezc-chain-current' : '').'">';
		print '<span class="ezc-chain-label">'.dol_escape_htmltag($step['label']).'</span>';
		print '<span class="ezc-chain-detail">'.dol_escape_htmltag($step['detail']).'</span>';
		print '</span>';
		if ($i < $laststep) {
			print '<span class="ezc-chain-arrow">&rarr;</span>';
		}
	}
	print '</div>';

	// Volume kept and volume left out
	print '<table class="ezc-source-table centpercent">';

	print '<tr><td class="ezc-source-key">'.$langs->trans('EzComptaSourceTable').'</td>';
	print '<td><b>llx_bank_import</b> &mdash; '.$langs->trans('EzComptaSourceTableDetail').'</td></tr>';

	print '<tr><td class="ezc-source-key">'.$langs->trans('EzComptaSourcePeriod').'</td><td>';
	print $langs->trans('EzComptaSourcePeriodDetail', dol_print_date($indicator['datestart'], 'day'), dol_print_date($indicator['dateend'], 'day'));
	if (!empty($indicator['firstdate'])) {
		print ' &mdash; '.$langs->trans('EzComptaSourceCovered', dol_print_date($indicator['firstdate'], 'day'), dol_print_date($indicator['lastdate'], 'day'));
	}
	print '</td></tr>';

	print '<tr><td class="ezc-source-key">'.$langs->trans('EzComptaSourceRecords').'</td><td>';
	print '<span class="badge badge-status4 badge-status">'.$langs->trans('EzComptaSourceRecordsKept', $nbkept).'</span>';
	print ' '.$langs->trans('EzComptaSourceRecordsOn', $indicator['nbtotal'], $indicator['nbaccounts']);
	print '</td></tr>';

	// Records the report leaves out on purpose
	$excluded = array();
	if ($indicator['nbdeleted'] > 0) {
		$excluded[] = $langs->trans('EzComptaExcludedDeleted', $indicator['nbdeleted']);
	}
	if ($indicator['nbduplicate'] > 0) {
		$excluded[] = $langs->trans('EzComptaExcludedDuplicate', $indicator['nbduplicate']);
	}
	if (!empty($excluded)) {
		print '<tr><td class="ezc-source-key">'.$langs->trans('EzComptaSourceExcluded').'</td><td>';
		print implode(', ', $excluded);
		print ' &mdash; <span class="opacitymedium">'.($indicator['includeexcluded'] ? $langs->trans('EzComptaExcludedCounted') : $langs->trans('EzComptaExcludedNotCounted')).'</span>';
		print '</td></tr>';
	}

	// Statuses, so an unexpected value coming from the source is visible
	if (!empty($indicator['statuses'])) {
		print '<tr><td class="ezc-source-key">'.$langs->trans('EzComptaSourceStatus').'</td><td>';
		$known = array(0 => $langs->trans('Draft'), 1 => $langs->trans('Enabled'), 9 => $langs->trans('Disabled'));
		$labels = array();
		foreach ($indicator['statuses'] as $status => $nb) {
			$label = isset($known[$status]) ? $known[$status] : $langs->trans('EzComptaStatusUnknown');
			$labels[] = $label.' ('.$status.') : '.$nb;
		}
		print implode(' &nbsp;|&nbsp; ', $labels);
		print '</td></tr>';
	}

	// Comparison with the Dolibarr bank entries
	print '<tr><td class="ezc-source-key">'.$langs->trans('EzComptaSourceVsDolibarr').'</td><td>';
	print $langs->trans('EzComptaSourceVsDolibarrDetail');
	print '<br>';
	print $langs->trans('EzComptaStatementTotals', price($indicator['reportcredit'], 0, '', 1, -1, -1, 'auto'), price($indicator['reportdebit'], 0, '', 1, -1, -1, 'auto'));
	print ' &nbsp;|&nbsp; ';
	print $langs->trans('EzComptaDolibarrTotals', price($indicator['bankcredit'], 0, '', 1, -1, -1, 'auto'), price($indicator['bankdebit'], 0, '', 1, -1, -1, 'auto'));
	print ' &nbsp;|&nbsp; <b>';
	print $langs->trans('EzComptaGapTotals', price($gapcredit, 0, '', 1, -1, -1, 'auto'), price($gapdebit, 0, '', 1, -1, -1, 'auto'));
	print '</b>';
	print '<br><span class="opacitymedium">'.$langs->trans('EzComptaReconciledCount', $indicator['nblinked'], $nbkept).'</span>';
	print '</td></tr>';

	print '</table>';

	// Warnings: the few situations that silently make the report wrong
	$warnings = array();
	if ($indicator['b4denabled'] && $indicator['b4dpending'] > 0) {
		$warning = $langs->trans('EzComptaWarningPendingTransfer', $indicator['b4dpending']);
		if (!empty($indicator['b4dlastdate'])) {
			$warning .= ' '.$langs->trans('EzComptaWarningPendingTransferUntil', dol_print_date($indicator['b4dlastdate'], 'day'));
		}
		$warning .= ' <a href="'.dol_buildpath('/ezcompta/ezcompta_index.php', 1).'">'.$langs->trans('EzComptaWarningPendingTransferAction').'</a>';
		$warnings[] = $warning;
	}
	if ($indicator['nbnodate'] > 0) {
		$warnings[] = $langs->trans('EzComptaWarningNoDate', $indicator['nbnodate']);
	}
	if ($indicator['nbwrongsign'] > 0) {
		$warnings[] = $langs->trans('EzComptaWarningWrongSign', $indicator['nbwrongsign']);
	}
	if ($indicator['nbbothsides'] > 0) {
		$warnings[] = $langs->trans('EzComptaWarningBothSides', $indicator['nbbothsides']);
	}
	if ($indicator['nbnoamount'] > 0) {
		$warnings[] = $langs->trans('EzComptaWarningNoAmount', $indicator['nbnoamount']);
	}
	if (!empty($warnings)) {
		print '<div class="ezc-source-warnings">';
		foreach ($warnings as $warning) {
			print '<div>'.img_warning('', '', 'pictofixedwidth').$warning.'</div>';
		}
		print '</div>';
	}

	print '</div>';
}

/**
 * Print the CSS used by the provenance panel and by the per account drill down of the report.
 *
 * @return	void
 */
function ezcomptaPrintInOutStyle()
{
	print '<style>
.ezc-source { border: 1px solid var(--colortextbackhmenu, #ddd); border-left: 4px solid #681bb5; border-radius: 4px; padding: 10px 12px; margin-bottom: 12px; }
.ezc-source-title { font-weight: bold; margin-bottom: 8px; }
.ezc-chain { display: flex; flex-wrap: wrap; align-items: stretch; gap: 4px; margin-bottom: 10px; }
.ezc-chain-step { display: flex; flex-direction: column; justify-content: center; border: 1px solid #ccc; border-radius: 3px; padding: 4px 8px; min-width: 110px; }
.ezc-chain-current { border-color: #681bb5; box-shadow: inset 0 0 0 1px #681bb5; }
.ezc-chain-label { font-weight: bold; font-size: 0.95em; }
.ezc-chain-detail { font-size: 0.8em; opacity: 0.7; }
.ezc-chain-arrow { align-self: center; opacity: 0.5; }
.ezc-source-table td { padding: 2px 6px; vertical-align: top; border: none; }
.ezc-source-key { white-space: nowrap; width: 190px; opacity: 0.7; }
.ezc-source-warnings { margin-top: 8px; padding-top: 8px; border-top: 1px dashed #ccc; }
.ezc-source-warnings div { margin: 2px 0; }
tr.ezc-month { cursor: pointer; }
tr.ezc-month:hover td { background-color: rgba(104, 27, 181, 0.06); }
tr.ezc-detail > td { font-size: 0.9em; }
tr.ezc-detail td.ezc-detail-label { padding-left: 26px; opacity: 0.85; }
.ezc-toggle { display: inline-block; width: 14px; opacity: 0.5; }
.ezc-negative { color: #c0392b; }
</style>';
}
