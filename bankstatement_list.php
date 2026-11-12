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
 * \file        bankstatement_list.php
 * \ingroup     ezcompta
 * \brief       Annual 12-month dashboard for official bank statements (BankRecord)
 *              Takes into account the company fiscal year start (SOCIETE_FISCAL_MONTH_START).
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */
global $conf, $db, $hookmanager, $langs, $user;

require_once DOL_DOCUMENT_ROOT.'/core/lib/bank.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
dol_include_once('/ezcompta/class/bankrecord.class.php');
dol_include_once('/ezcompta/lib/ezcompta.lib.php');

// Security check
if (!$user->hasRight('banque', 'lire') && !$user->hasRight('banque', 'read')) {
	accessforbidden();
}

$langs->loadLangs(array("banks", "bills", "compta", "ezcompta@ezcompta", "other"));

$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$account_id = GETPOSTINT('account_id');
$year = GETPOSTINT('year');

$fiscal_month_start = getDolGlobalInt('SOCIETE_FISCAL_MONTH_START', 1);

$now = dol_now();
$now_month = (int) dol_print_date($now, '%m');
$now_year = (int) dol_print_date($now, '%Y');
$current_fiscal_year = ($fiscal_month_start > 1 && $now_month >= $fiscal_month_start) ? ($now_year + 1) : $now_year;
if (empty($year)) {
	$year = $current_fiscal_year;
}

// Find default bank account if not specified
if (empty($account_id)) {
	$sql_ba = "SELECT rowid FROM ".MAIN_DB_PREFIX."bank_account WHERE entity IN (".getEntity('bank_account').") AND clos = 0 ORDER BY rowid ASC LIMIT 1";
	$res_ba = $db->query($sql_ba);
	if ($res_ba && ($obj_ba = $db->fetch_object($res_ba))) {
		$account_id = (int) $obj_ba->rowid;
	}
}

$bank_account = new Account($db);
if ($account_id > 0) {
	$bank_account->fetch($account_id);
}

// Generate the 12 periods of the fiscal year
$fiscal_periods = array();
for ($i = 0; $i < 12; $i++) {
	$cal_month = ($fiscal_month_start + $i - 1) % 12 + 1;
	$cal_year = ($fiscal_month_start > 1 && $cal_month >= $fiscal_month_start) ? ($year - 1) : $year;
	$dt_start = dol_stringtotime(sprintf('%04d-%02d-01', $cal_year, $cal_month));
	$dt_end = dol_stringtotime(date('Y-m-t 23:59:59', $dt_start));
	$key = sprintf('%04d-%02d', $cal_year, $cal_month);
	$month_name = dol_ucfirst($langs->trans("Month".sprintf('%02d', $cal_month))).' '.$cal_year;

	$fiscal_periods[] = array(
		'seq' => $i + 1,
		'key' => $key,
		'cal_month' => $cal_month,
		'cal_year' => $cal_year,
		'dt_start' => $dt_start,
		'dt_end' => $dt_end,
		'month_name' => $month_name,
	);
}

$first_dt = $fiscal_periods[0]['dt_start'];
$last_dt = $fiscal_periods[11]['dt_end'];


/*
 * Actions
 */

// Action: Import all PDFs from documents/banque-temporaire
if ($action == 'import_temp_folder' && $account_id > 0 && ($user->hasRight('banque', 'modifier') || $user->hasRight('banque', 'write'))) {
	$temp_dir = DOL_DATA_ROOT.'/banque-temporaire';
	if (!is_dir($temp_dir)) {
		$temp_dir = DOL_DOCUMENT_ROOT.'/../documents/banque-temporaire';
	}

	$script_path = DOL_DOCUMENT_ROOT.'/custom/ezcompta/scripts/parse_pdf.py';
	$count_imported = 0;
	$count_updated = 0;
	$errors = array();

	if (is_dir($temp_dir) && file_exists($script_path)) {
		$files = glob($temp_dir.'/*.pdf');
		if (!empty($files)) {
			foreach ($files as $file_path) {
				$cmd = 'python '.escapeshellarg($script_path).' '.escapeshellarg($file_path);
				$output = shell_exec($cmd);
				$data = json_decode($output, true);

				if (!empty($data) && empty($data['error'])) {
					$ref = !empty($data['ref']) ? $data['ref'] : 'IMP-'.date('YmdHis');
					$dt_start_str = !empty($data['dt_start']) ? $data['dt_start'] : '';
					$dt_end_str = !empty($data['dt_end']) ? $data['dt_end'] : '';

					if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dt_start_str, $m_d)) {
						$dt_start = dol_stringtotime($m_d[3].'-'.$m_d[2].'-'.$m_d[1]);
					} else {
						$dt_start = dol_stringtotime($dt_start_str);
					}
					if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dt_end_str, $m_d)) {
						$dt_end = dol_stringtotime($m_d[3].'-'.$m_d[2].'-'.$m_d[1]);
					} else {
						$dt_end = dol_stringtotime($dt_end_str);
					}

					if (!empty($dt_end)) {
						if (empty($dt_start)) {
							$dt_start = $dt_end - (30 * 86400);
						}

						// Check if statement already exists for this bank and ref or exact end date
						$check_sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."bank_record WHERE fk_bank = ".((int) $account_id);
						$check_sql .= " AND dt_to = '".$db->idate($dt_end)."'";
						$res_check = $db->query($check_sql);

						$record = new BankRecord($db);
						if ($res_check && $db->num_rows($res_check) > 0) {
							$obj_ck = $db->fetch_object($res_check);
							$record->fetch($obj_ck->rowid);
							$record->ref = $ref;
							$record->dt_from = $dt_start;
							$record->dt_to = $dt_end;
							$record->opening_balance = isset($data['opening_balance']) ? (float) $data['opening_balance'] : null;
							$record->closing_balance = isset($data['closing_balance']) ? (float) $data['closing_balance'] : null;
							$record->total_debit = isset($data['total_debit']) ? (float) $data['total_debit'] : null;
							$record->total_credit = isset($data['total_credit']) ? (float) $data['total_credit'] : null;
							$record->nb_operations = isset($data['nb_operations']) ? (int) $data['nb_operations'] : null;
							$record->update($user);
							$count_updated++;
						} else {
							$record->ref = $ref;
							$record->fk_bank = $account_id;
							$record->dt_from = $dt_start;
							$record->dt_to = $dt_end;
							$record->opening_balance = isset($data['opening_balance']) ? (float) $data['opening_balance'] : null;
							$record->closing_balance = isset($data['closing_balance']) ? (float) $data['closing_balance'] : null;
							$record->total_debit = isset($data['total_debit']) ? (float) $data['total_debit'] : null;
							$record->total_credit = isset($data['total_credit']) ? (float) $data['total_credit'] : null;
							$record->nb_operations = isset($data['nb_operations']) ? (int) $data['nb_operations'] : null;
							$record->status = BankRecord::STATUS_DRAFT;
							$record_id = $record->create($user);
							if ($record_id > 0) {
								$count_imported++;
							}
						}

						if ($record->id > 0) {
							$record->auto_link_lines();

							$target_dir = $conf->bank->dir_output.'/'.$account_id.'/statement/'.dol_sanitizeFileName($record->ref);
							if (!is_dir($target_dir)) {
								dol_mkdir($target_dir);
							}
							$target_file = $target_dir.'/'.dol_basename($file_path);
							if (!file_exists($target_file)) {
								dol_copy($file_path, $target_file);
							}
						}
					}
				}
			}
		}
	}

	setEventMessages($langs->trans("ImportFinished")." : ".$count_imported." relevés créés, ".$count_updated." mis à jour.", null, 'mesgs');
	header("Location: ".$_SERVER["PHP_SELF"]."?account_id=".$account_id."&year=".$year);
	exit;
}

// Action: Manual creation or edition of a statement
if ($action == 'save_statement' && ($user->hasRight('banque', 'modifier') || $user->hasRight('banque', 'write'))) {
	$record_id = GETPOSTINT('record_id');
	$ref = GETPOST('ref', 'alphanohtml');
	$dt_from = GETPOSTDATE('dt_from');
	$dt_to = GETPOSTDATE('dt_to');
	$opening_balance = price2num(GETPOST('opening_balance', 'alpha'));
	$closing_balance = price2num(GETPOST('closing_balance', 'alpha'));
	$total_debit = price2num(GETPOST('total_debit', 'alpha'));
	$total_credit = price2num(GETPOST('total_credit', 'alpha'));
	$nb_operations = GETPOSTINT('nb_operations');

	$record = new BankRecord($db);
	if ($record_id > 0) {
		$record->fetch($record_id);
	} else {
		$record->fk_bank = $account_id;
	}

	$record->ref = $ref;
	$record->dt_from = $dt_from;
	$record->dt_to = $dt_to;
	$record->opening_balance = $opening_balance !== '' ? (float) $opening_balance : null;
	$record->closing_balance = $closing_balance !== '' ? (float) $closing_balance : null;
	$record->total_debit = $total_debit !== '' ? (float) $total_debit : null;
	$record->total_credit = $total_credit !== '' ? (float) $total_credit : null;
	$record->nb_operations = $nb_operations > 0 ? (int) $nb_operations : null;

	if ($record_id > 0) {
		$res = $record->update($user);
	} else {
		$res = $record->create($user);
	}

	if ($res > 0) {
		$record->auto_link_lines();

		if (!empty($_FILES['statement_pdf']['name']) && !empty($_FILES['statement_pdf']['tmp_name'])) {
			$target_dir = $conf->bank->dir_output.'/'.$account_id.'/statement/'.dol_sanitizeFileName($record->ref);
			if (!is_dir($target_dir)) {
				dol_mkdir($target_dir);
			}
			$clean_name = dol_sanitizeFileName($_FILES['statement_pdf']['name']);
			dol_move_uploaded_file($_FILES['statement_pdf']['tmp_name'], $target_dir.'/'.$clean_name, 1);
		}

		setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
	} else {
		setEventMessages($record->error, $record->errors, 'errors');
	}

	header("Location: ".$_SERVER["PHP_SELF"]."?account_id=".$account_id."&year=".$year);
	exit;
}

// Action: Quick PDF upload on a specific month row
if ($action == 'upload_pdf_month' && ($user->hasRight('banque', 'modifier') || $user->hasRight('banque', 'write'))) {
	if (!empty($_FILES['statement_pdf']['tmp_name'])) {
		$tmp_file = $_FILES['statement_pdf']['tmp_name'];
		$script_path = DOL_DOCUMENT_ROOT.'/custom/ezcompta/scripts/parse_pdf.py';
		$cmd = 'python '.escapeshellarg($script_path).' '.escapeshellarg($tmp_file);
		$output = shell_exec($cmd);
		$data = json_decode($output, true);

		$record = new BankRecord($db);
		$record_id = GETPOSTINT('record_id');
		if ($record_id > 0) {
			$record->fetch($record_id);
		} else {
			$record->fk_bank = $account_id;
		}

		if (!empty($data) && empty($data['error'])) {
			if (!empty($data['ref'])) {
				$record->ref = $data['ref'];
			}
			if (!empty($data['dt_start'])) {
				if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $data['dt_start'], $m_d)) {
					$record->dt_from = dol_stringtotime($m_d[3].'-'.$m_d[2].'-'.$m_d[1]);
				}
			}
			if (!empty($data['dt_end'])) {
				if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $data['dt_end'], $m_d)) {
					$record->dt_to = dol_stringtotime($m_d[3].'-'.$m_d[2].'-'.$m_d[1]);
				}
			}
			if (isset($data['opening_balance'])) {
				$record->opening_balance = (float) $data['opening_balance'];
			}
			if (isset($data['closing_balance'])) {
				$record->closing_balance = (float) $data['closing_balance'];
			}
			if (isset($data['total_debit'])) {
				$record->total_debit = (float) $data['total_debit'];
			}
			if (isset($data['total_credit'])) {
				$record->total_credit = (float) $data['total_credit'];
			}
			if (isset($data['nb_operations'])) {
				$record->nb_operations = (int) $data['nb_operations'];
			}
		}

		if (empty($record->ref)) {
			$cal_m = GETPOSTINT('cal_month');
			$cal_y = GETPOSTINT('cal_year');
			$record->ref = sprintf('%04d-%02d', $cal_y, $cal_m);
		}
		if (empty($record->dt_from)) {
			$cal_m = GETPOSTINT('cal_month');
			$cal_y = GETPOSTINT('cal_year');
			$record->dt_from = dol_stringtotime(sprintf('%04d-%02d-01', $cal_y, $cal_m));
			$record->dt_to = dol_stringtotime(date('Y-m-t', $record->dt_from));
		}

		if ($record->id > 0) {
			$res = $record->update($user);
		} else {
			$res = $record->create($user);
		}

		if ($res > 0) {
			$record->auto_link_lines();

			$target_dir = $conf->bank->dir_output.'/'.$account_id.'/statement/'.dol_sanitizeFileName($record->ref);
			if (!is_dir($target_dir)) {
				dol_mkdir($target_dir);
			}
			$clean_name = dol_sanitizeFileName($_FILES['statement_pdf']['name']);
			dol_move_uploaded_file($tmp_file, $target_dir.'/'.$clean_name, 1);

			setEventMessages($langs->trans("RecordSaved")." (Relevé ".$record->ref.")", null, 'mesgs');
		} else {
			setEventMessages($record->error, $record->errors, 'errors');
		}
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?account_id=".$account_id."&year=".$year);
	exit;
}

// Action: Delete statement
if ($action == 'delete_statement' && ($user->hasRight('banque', 'supprimer') || $user->hasRight('banque', 'delete'))) {
	$record_id = GETPOSTINT('record_id');
	if ($record_id > 0) {
		$record = new BankRecord($db);
		if ($record->fetch($record_id) > 0) {
			$record->delete($user);
			setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
		}
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?account_id=".$account_id."&year=".$year);
	exit;
}


/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

$fiscal_title = ($fiscal_month_start > 1) ? "Exercice fiscal ".$year." (".dol_print_date($first_dt, 'day')." au ".dol_print_date($last_dt, 'day').")" : "Année ".$year;
$title = $langs->trans("BankStatements")." - ".$fiscal_title;
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-ezcompta page-bankstatement');

print load_fiche_titre($langs->trans("BankStatements").' &mdash; '.$fiscal_title, '', 'fa-file-invoice-dollar');

// Navigation & Filters Form
print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'" class="marginbottomonly">';
print '<div class="inline-block paddingright">';
print '<span class="opacitymedium">'.$langs->trans("BankAccount").' : </span>';

// Bank account selector
$sql_accounts = "SELECT rowid, ref, label FROM ".MAIN_DB_PREFIX."bank_account WHERE entity IN (".getEntity('bank_account').") AND clos = 0 ORDER BY label ASC";
$res_accounts = $db->query($sql_accounts);
print '<select name="account_id" class="flat" onchange="this.form.submit();">';
if ($res_accounts) {
	while ($acc = $db->fetch_object($res_accounts)) {
		print '<option value="'.$acc->rowid.'"'.($acc->rowid == $account_id ? ' selected' : '').'>'.dol_escape_htmltag($acc->label.' ('.$acc->ref.')').'</option>';
	}
}
print '</select>';
print '</div>';

// Fiscal Year selector
print '<div class="inline-block paddingright">';
print '<span class="opacitymedium">'.$langs->trans("FiscalYear").' : </span>';
print '<select name="year" class="flat" onchange="this.form.submit();">';
for ($y = $current_fiscal_year + 1; $y >= 2022; $y--) {
	if ($fiscal_month_start > 1) {
		$f_start = dol_stringtotime(sprintf('%04d-%02d-01', $y - 1, $fiscal_month_start));
		$end_month = ($fiscal_month_start == 1) ? 12 : ($fiscal_month_start - 1);
		$f_end = dol_stringtotime(date('Y-m-t', dol_stringtotime(sprintf('%04d-%02d-01', $y, $end_month))));
		$label_y = 'Exercice '.$y.' ('.dol_print_date($f_start, 'day').' au '.dol_print_date($f_end, 'day').')';
	} else {
		$label_y = 'Année '.$y;
	}
	print '<option value="'.$y.'"'.($y == $year ? ' selected' : '').'>'.$label_y.'</option>';
}
print '</select>';
print '</div>';

print '<input type="submit" class="button button-small" value="'.$langs->trans("Search").'">';

// Action buttons on the right
print '<div class="floatright inline-block">';
if ($user->hasRight('banque', 'modifier') || $user->hasRight('banque', 'write')) {
	print '<a href="'.$_SERVER["PHP_SELF"]."?action=import_temp_folder&account_id=".$account_id."&year=".$year."&token=".newToken().'" class="button button-small" onclick="return confirm(\'Voulez-vous importer et analyser automatiquement tous les PDF du dossier documents/banque-temporaire ?\');">';
	print '<span class="fas fa-magic paddingright"></span> Importer les PDF temporaires';
	print '</a> ';

	print '<button type="button" class="button button-small" onclick="document.getElementById(\'modal_add_statement\').style.display=\'block\';">';
	print '<span class="fas fa-plus-circle paddingright"></span> '.$langs->trans("NewObject", $langs->transnoentitiesnoconv("BankStatement"));
	print '</button>';
}
print '</div>';
print '<div class="clearboth"></div>';
print '</form>';


// Load all BankRecords for this account and fiscal year
$records_by_period = array();
$sql_rec = "SELECT rowid FROM ".MAIN_DB_PREFIX."bank_record ";
$sql_rec .= "WHERE fk_bank = ".((int) $account_id)." ";
$sql_rec .= "AND dt_to >= '".$db->idate($first_dt)."' AND dt_to <= '".$db->idate($last_dt)."' ";
$sql_rec .= "ORDER BY dt_to ASC";
$res_rec = $db->query($sql_rec);
if ($res_rec) {
	while ($obj_r = $db->fetch_object($res_rec)) {
		$r = new BankRecord($db);
		if ($r->fetch($obj_r->rowid) > 0) {
			$k = dol_print_date($r->dt_to, '%Y-%m');
			$records_by_period[$k] = $r;
		}
	}
}

// Check transactions from llx_bank for justification pieces
$justified_by_period = array();
$sql_just = "SELECT DATE_FORMAT(b.datev, '%Y-%m') as ym, COUNT(DISTINCT b.rowid) as total_lines, ";
$sql_just .= "COUNT(DISTINCT CASE WHEN bu.rowid IS NOT NULL THEN b.rowid END) as justified_lines ";
$sql_just .= "FROM ".MAIN_DB_PREFIX."bank as b ";
$sql_just .= "LEFT JOIN ".MAIN_DB_PREFIX."bank_url as bu ON bu.fk_bank = b.rowid ";
$sql_just .= "WHERE b.fk_account = ".((int) $account_id)." ";
$sql_just .= "AND b.datev >= '".$db->idate($first_dt)."' AND b.datev <= '".$db->idate($last_dt)."' ";
$sql_just .= "GROUP BY ym";
$res_just = $db->query($sql_just);
if ($res_just) {
	while ($obj_j = $db->fetch_object($res_just)) {
		$justified_by_period[$obj_j->ym] = array(
			'total' => (int) $obj_j->total_lines,
			'justified' => (int) $obj_j->justified_lines,
		);
	}
}


// The 12-Month Table ordered by fiscal year
print '<div class="div-table-responsive-no-min marginbottomonly">';
print '<table class="tagtable liste centpercent">';
print '<tr class="liste_titre">';
print '<th class="liste_titre width100">N° Relevé</th>';
print '<th class="liste_titre">Période & Document PDF</th>';
print '<th class="liste_titre right" style="min-width:140px;">Solde Initial</th>';
print '<th class="liste_titre right" style="min-width:140px;">Solde Final</th>';
print '<th class="liste_titre center width80">Écart</th>';
print '<th class="liste_titre center width120">Opérations</th>';
print '<th class="liste_titre center" style="min-width:160px;">Justification Pièces</th>';
print '<th class="liste_titre center width90">Statut</th>';
print '<th class="liste_titre center width100">Actions</th>';
print '</tr>';

$running_balance = null;
if ($account_id > 0) {
	$sql_prev = "SELECT closing_balance FROM ".MAIN_DB_PREFIX."bank_record WHERE fk_bank = ".((int) $account_id)." AND dt_to < '".$db->idate($first_dt)."' ORDER BY dt_to DESC LIMIT 1";
	$res_prev = $db->query($sql_prev);
	if ($res_prev && ($obj_prev = $db->fetch_object($res_prev))) {
		$running_balance = (float) $obj_prev->closing_balance;
	}
}

foreach ($fiscal_periods as $period) {
	$k = $period['key'];
	$has_record = isset($records_by_period[$k]);
	$rec = $has_record ? $records_by_period[$k] : null;

	$month_name = $period['month_name'];

	$stats = $has_record ? $rec->getCalculatedStats() : array('nb_operations' => 0, 'calculated_debit' => 0, 'calculated_credit' => 0, 'calculated_end_balance' => 0, 'gap' => 0);

	// Check if PDF file exists
	$pdf_file_path = null;
	$pdf_file_name = null;
	if ($has_record) {
		$dir_statement = $conf->bank->dir_output.'/'.$account_id.'/statement/'.dol_sanitizeFileName($rec->ref);
		if (is_dir($dir_statement)) {
			$date_str = dol_print_date($rec->dt_to, '%Y-%m');
			$files = glob($dir_statement.'/*'.$date_str.'*.pdf');
			if (empty($files)) {
				$date_y = dol_print_date($rec->dt_to, '%Y');
				$files = glob($dir_statement.'/*'.$date_y.'*.pdf');
			}
			if (empty($files)) {
				$files = glob($dir_statement.'/*.pdf');
			}
			if (!empty($files)) {
				$pdf_file_path = $files[0];
				$pdf_file_name = basename($files[0]);
			}
		}
	}

	print '<tr class="oddeven" id="row_period_'.$k.'">';

	// Column 1: Month / Statement Ref
	print '<td>';
	print '<b>'.$month_name.'</b><br>';
	if ($has_record) {
		print '<span class="badge badge-status4"><span class="fas fa-hashtag"></span> '.dol_escape_htmltag($rec->ref).'</span>';
	} else {
		print '<span class="opacitymedium">Non créé</span>';
	}
	print '</td>';

	// Column 2: Period & PDF Document
	print '<td>';
	if ($has_record) {
		print '<span class="fas fa-calendar-alt opacitymedium paddingright"></span>';
		print dol_print_date($rec->dt_from, 'day').' au '.dol_print_date($rec->dt_to, 'day').'<br>';
		if ($pdf_file_name) {
			$relativepath = $account_id.'/statement/'.dol_sanitizeFileName($rec->ref).'/'.$pdf_file_name;
			$download_url = DOL_URL_ROOT.'/document.php?modulepart=bank&file='.urlencode($relativepath);
			print '<a href="'.$download_url.'" target="_blank" class="badge badge-info" title="'.dol_escape_htmltag($pdf_file_name).'">';
			print '<span class="fas fa-file-pdf paddingright"></span> '.dol_trunc($pdf_file_name, 25);
			print '</a> ';
			$fileinfo = array(
				'name' => $pdf_file_name,
				'fullname' => (string) $pdf_file_path,
			);
			$preview_html = $formfile->showPreview($fileinfo, 'bank', $relativepath, 0, 'preview=1');
			if ($preview_html) {
				print $preview_html;
			} else {
				$preview_url = DOL_URL_ROOT.'/document.php?modulepart=bank&attachment=0&file='.urlencode($relativepath);
				print '<a class="pictopreview documentpreview" href="'.$preview_url.'" mime="application/pdf" target="_blank" title="'.$langs->trans("Preview").'">';
				print img_picto($langs->trans("Preview"), 'search-plus', 'class="pictofixedwidth valignmiddle"');
				print '</a>';
			}
		} else {
			print '<span class="badge badge-warning opacitymedium">PDF manquant</span>';
		}
	} else {
		print '<span class="opacitymedium">'.dol_print_date($period['dt_start'], 'day').' au '.dol_print_date($period['dt_end'], 'day').'</span>';
	}
	print '</td>';

	// Column 3: Opening Balance (Ligne 1: Réel PDF / Ligne 2: Calculé)
	print '<td class="right nowrap">';
	if ($has_record && $rec->opening_balance !== null) {
		print '<div class="bold">'.price($rec->opening_balance).'</div>';
		if ($running_balance !== null) {
			$diff_start = round($rec->opening_balance - $running_balance, 2);
			if ($diff_start != 0) {
				print '<div class="badge badge-status8 font-small" title="Rupture avec le relevé précédent">Précédent : '.price($running_balance).'</div>';
			} else {
				print '<div class="opacitymedium font-small">(attendu: '.price($running_balance).')</div>';
			}
		}
	} else {
		print '<span class="opacitymedium">&mdash;</span>';
	}
	print '</td>';

	// Column 4: Closing Balance (Ligne 1: Réel PDF / Ligne 2: Calculé)
	print '<td class="right nowrap">';
	if ($has_record && $rec->closing_balance !== null) {
		print '<div class="bold">'.price($rec->closing_balance).'</div>';
		print '<div class="opacitymedium font-small">(calculé: '.price($stats['calculated_end_balance']).')</div>';
		$running_balance = $rec->closing_balance;
	} else {
		print '<span class="opacitymedium">&mdash;</span>';
	}
	print '</td>';

	// Column 5: Gap (Écart)
	print '<td class="center nowrap">';
	if ($has_record && $rec->closing_balance !== null) {
		$gap = isset($stats['gap']) ? $stats['gap'] : 0;
		if (abs($gap) < 0.001) {
			print '<span class="badge badge-status4 font-small"><span class="fas fa-check paddingright"></span>0,00 €</span>';
		} else {
			print '<span class="badge badge-status8 font-small bold" title="Écart entre solde réel et opérations"><span class="fas fa-exclamation-triangle paddingright"></span>'.price($gap).'</span>';
		}
	} else {
		print '<span class="opacitymedium">&mdash;</span>';
	}
	print '</td>';

	// Column 6: Operations Count & totals
	print '<td class="center">';
	if ($has_record) {
		$pdf_ops = $rec->nb_operations;
		$calc_ops = !empty($stats['nb_operations']) ? $stats['nb_operations'] : 0;
		$calc_credit = isset($stats['calculated_credit']) ? $stats['calculated_credit'] : (isset($stats['total_credit']) ? $stats['total_credit'] : 0);
		$calc_debit = isset($stats['calculated_debit']) ? $stats['calculated_debit'] : (isset($stats['total_debit']) ? $stats['total_debit'] : 0);

		if ($pdf_ops !== null) {
			print '<b title="Nombre d\'opérations certifié par le relevé bancaire">'.$pdf_ops.' op.</b>';
			if ($calc_ops != $pdf_ops) {
				print ' <span class="badge badge-warning font-small" title="Opérations intégrées : '.$calc_ops.'">('.$calc_ops.' intégrées)</span>';
			}
			print '<br>';
		} else {
			print '<b>'.$calc_ops.'</b> op.<br>';
		}
		print '<span class="font-small opacitymedium">+ '.price($calc_credit).' | - '.price($calc_debit).'</span>';
	} else {
		print '<span class="opacitymedium">0 op.</span>';
	}
	print '</td>';

	// Column 7: Justification status (Pièces comptables)
	print '<td class="center">';
	$j_info = isset($justified_by_period[$k]) ? $justified_by_period[$k] : array('total' => 0, 'justified' => 0);
	if ($j_info['total'] > 0) {
		$pct = round(($j_info['justified'] / $j_info['total']) * 100);
		$color_class = $pct == 100 ? 'badge-status4' : ($pct >= 70 ? 'badge-warning' : 'badge-status8');
		print '<span class="badge '.$color_class.' font-small bold">'.$j_info['justified'].' / '.$j_info['total'].' ('.$pct.'%)</span>';
		if ($j_info['justified'] < $j_info['total']) {
			$unjust = $j_info['total'] - $j_info['justified'];
			print '<br><span class="font-small color-danger">⚠️ '.$unjust.' sans pièce</span>';
		}
	} else {
		print '<span class="opacitymedium">&mdash;</span>';
	}
	print '</td>';

	// Column 8: Status
	print '<td class="center">';
	if ($has_record) {
		print $rec->getLibStatut(3);
	} else {
		print '<span class="badge badge-status0">À venir</span>';
	}
	print '</td>';

	// Column 9: Actions
	print '<td class="center nowrap">';
	if ($has_record) {
		// Edit button
		$nb_ops_val = $rec->nb_operations !== null ? $rec->nb_operations : (isset($stats['nb_operations']) ? $stats['nb_operations'] : '');
		$tot_debit_val = $rec->total_debit !== null ? price2num($rec->total_debit) : '';
		$tot_credit_val = $rec->total_credit !== null ? price2num($rec->total_credit) : '';
		print '<button type="button" class="button button-small" title="'.$langs->trans("Edit").'" onclick="openEditModal('.$rec->id.', \''.dol_escape_js($rec->ref).'\', \''.dol_print_date($rec->dt_from, 'day').'\', \''.dol_print_date($rec->dt_to, 'day').'\', \''.price2num($rec->opening_balance).'\', \''.price2num($rec->closing_balance).'\', \''.$nb_ops_val.'\', \''.$tot_debit_val.'\', \''.$tot_credit_val.'\');">';
		print '<span class="fas fa-edit"></span>';
		print '</button> ';

		// Delete button
		print '<a href="'.$_SERVER["PHP_SELF"].'?action=delete_statement&record_id='.$rec->id.'&account_id='.$account_id.'&year='.$year.'&token='.newToken().'" class="button button-small" title="'.$langs->trans("Delete").'" onclick="return confirm(\'Voulez-vous supprimer ce relevé ?\');">';
		print '<span class="fas fa-trash"></span>';
		print '</a>';
	} else {
		// Quick upload PDF / Create for this period
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data" class="inline-block">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="upload_pdf_month">';
		print '<input type="hidden" name="account_id" value="'.$account_id.'">';
		print '<input type="hidden" name="year" value="'.$year.'">';
		print '<input type="hidden" name="cal_month" value="'.$period['cal_month'].'">';
		print '<input type="hidden" name="cal_year" value="'.$period['cal_year'].'">';
		print '<label class="button button-small" style="cursor:pointer;" title="Déposer le PDF du mois">';
		print '<span class="fas fa-file-upload paddingright"></span> PDF';
		print '<input type="file" name="statement_pdf" accept=".pdf" style="display:none;" onchange="this.form.submit();">';
		print '</label>';
		print '</form>';
	}
	print '</td>';

	print '</tr>';
}

print '</table>';
print '</div>';


// Modal: Add / Edit Statement
print '
<div id="modal_add_statement" style="display:none; position:fixed; z-index:9999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.5);">
	<div style="background:#fff; width:550px; margin:80px auto; padding:25px; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,0.3);">
		<h3 id="modal_title" class="marginbottom"><span class="fas fa-file-invoice-dollar paddingright"></span> Nouveau Relevé Bancaire</h3>
		<form method="POST" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data">
			<input type="hidden" name="token" value="'.newToken().'">
			<input type="hidden" name="action" value="save_statement">
			<input type="hidden" name="account_id" value="'.$account_id.'">
			<input type="hidden" name="year" value="'.$year.'">
			<input type="hidden" name="record_id" id="form_record_id" value="0">

			<div class="marginbottom">
				<label class="bold">N° / Référence Relevé (PDF) :</label><br>
				<input type="text" name="ref" id="form_ref" class="flat centpercent" placeholder="Ex: 010 ou 2026-10" required>
			</div>

			<div class="marginbottom">
				<label class="bold">Période du Relevé :</label><br>
				<div class="inline-block" style="width:48%;">
					Du : <input type="date" name="dt_from" id="form_dt_from" class="flat centpercent" required>
				</div>
				<div class="inline-block floatright" style="width:48%;">
					Au : <input type="date" name="dt_to" id="form_dt_to" class="flat centpercent" required>
				</div>
				<div class="clearboth"></div>
			</div>

			<div class="marginbottom">
				<label class="bold">Soldes officiels certifiés par le PDF :</label><br>
				<div class="inline-block" style="width:48%;">
					Solde Initial (€) :
					<input type="text" name="opening_balance" id="form_opening_balance" class="flat centpercent" placeholder="Ex: 20856.16">
				</div>
				<div class="inline-block floatright" style="width:48%;">
					Solde Final (€) :
					<input type="text" name="closing_balance" id="form_closing_balance" class="flat centpercent" placeholder="Ex: 7263.43">
				</div>
				<div class="clearboth"></div>
			</div>

			<div class="marginbottom">
				<div class="inline-block" style="width:31%;">
					<label class="bold">Nb d\'opérations :</label><br>
					<input type="number" name="nb_operations" id="form_nb_operations" class="flat centpercent" placeholder="Ex: 20">
				</div>
				<div class="inline-block" style="width:31%; margin-left:3%;">
					<label class="bold">Total Débits (€) :</label><br>
					<input type="text" name="total_debit" id="form_total_debit" class="flat centpercent" placeholder="Ex: 15082.73">
				</div>
				<div class="inline-block floatright" style="width:31%;">
					<label class="bold">Total Crédits (€) :</label><br>
					<input type="text" name="total_credit" id="form_total_credit" class="flat centpercent" placeholder="Ex: 1490.00">
				</div>
				<div class="clearboth"></div>
			</div>

			<div class="marginbottom">
				<label class="bold">Fichier PDF du Relevé réel :</label><br>
				<input type="file" name="statement_pdf" class="flat centpercent" accept=".pdf">
			</div>

			<div class="right margintop">
				<button type="button" class="button button-cancel" onclick="document.getElementById(\'modal_add_statement\').style.display=\'none\';">Annuler</button>
				<button type="submit" class="button button-primary"><span class="fas fa-save paddingright"></span> Enregistrer</button>
			</div>
		</form>
	</div>
</div>

<script type="text/javascript">
function openEditModal(id, ref, dtFrom, dtTo, openingBal, closingBal, nbOps, totDebit, totCredit) {
	document.getElementById("modal_title").innerHTML = "<span class=\"fas fa-edit paddingright\"></span> Modifier le Relevé " + ref;
	document.getElementById("form_record_id").value = id;
	document.getElementById("form_ref").value = ref;
	document.getElementById("form_opening_balance").value = openingBal || "";
	document.getElementById("form_closing_balance").value = closingBal || "";
	document.getElementById("form_nb_operations").value = nbOps || "";
	document.getElementById("form_total_debit").value = totDebit || "";
	document.getElementById("form_total_credit").value = totCredit || "";

	if (dtFrom) {
		var parts = dtFrom.split("/");
		if (parts.length == 3) document.getElementById("form_dt_from").value = parts[2] + "-" + parts[1] + "-" + parts[0];
	}
	if (dtTo) {
		var parts = dtTo.split("/");
		if (parts.length == 3) document.getElementById("form_dt_to").value = parts[2] + "-" + parts[1] + "-" + parts[0];
	}

	document.getElementById("modal_add_statement").style.display = "block";
}
</script>
';

// End of page
llxFooter();
$db->close();
