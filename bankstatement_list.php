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
 * \brief       Standard Dolibarr list page for Bank Statements (BankRecord)
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
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

$search_ref = GETPOST('search_ref', 'alpha');
$search_account = GETPOSTINT('search_account');
$search_status = GETPOST('search_status', 'alpha');
$search_date_start = GETPOSTDATE('search_date_start', '', 'tzuserrel');
$search_date_end = GETPOSTDATE('search_date_end', '', 'tzuserrel');
$search_remove_btn = GETPOST('button_removefilter', 'alpha');

if (!empty($search_remove_btn)) {
	$search_ref = '';
	$search_account = 0;
	$search_status = '';
	$search_date_start = null;
	$search_date_end = null;
}

$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTINT('page');
if (empty($page) || $page == -1) {
	$page = 0;
}
$offset = $limit * $page;
if (empty($sortfield)) {
	$sortfield = 't.dt_from';
}
if (empty($sortorder)) {
	$sortorder = 'DESC';
}

$can_delete = ($user->hasRight('banque', 'supprimer') || $user->hasRight('banque', 'delete'));
$can_edit = ($user->hasRight('banque', 'modifier') || $user->hasRight('banque', 'write'));


/*
 * Actions
 */

// Action: Delete statement
if ($action == 'delete' && $confirm == 'yes' && $can_delete) {
	$record_id = GETPOSTINT('record_id');
	if ($record_id > 0) {
		$record = new BankRecord($db);
		if ($record->fetch($record_id) > 0) {
			$record->delete($user);
			setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
		}
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?page=".$page);
	exit;
}


/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

$sql_where = " WHERE t.entity IN (".getEntity('bank_record').")";
if (!empty($search_ref)) {
	$sql_where .= natural_search('t.ref', $search_ref);
}
if ($search_account > 0) {
	$sql_where .= " AND t.fk_bank = ".((int) $search_account);
}
if ($search_status != '' && $search_status != '-1') {
	$sql_where .= " AND t.status = ".((int) $search_status);
}
if (!empty($search_date_start)) {
	$sql_where .= " AND t.dt_from >= '".$db->idate($search_date_start)." 00:00:00'";
}
if (!empty($search_date_end)) {
	$sql_where .= " AND t.dt_to <= '".$db->idate($search_date_end)." 23:59:59'";
}

// Count total
$sql_count = "SELECT COUNT(t.rowid) as total FROM ".MAIN_DB_PREFIX."bank_record as t";
$sql_count .= " LEFT JOIN ".MAIN_DB_PREFIX."bank_account as ba ON ba.rowid = t.fk_bank";
$sql_count .= $sql_where;
$res_count = $db->query($sql_count);
$nbtotalofrecords = 0;
if ($res_count && ($obj_cnt = $db->fetch_object($res_count))) {
	$nbtotalofrecords = (int) $obj_cnt->total;
}

// Select query
$sql = "SELECT t.rowid, t.ref, t.entity, t.ref_ext, t.fk_bank, t.dt_from, t.dt_to, t.opening_balance, t.closing_balance, ";
$sql .= "t.total_debit, t.total_credit, t.nb_operations, t.status, t.date_creation, ";
$sql .= "ba.ref as account_ref, ba.label as account_label ";
$sql .= "FROM ".MAIN_DB_PREFIX."bank_record as t ";
$sql .= "LEFT JOIN ".MAIN_DB_PREFIX."bank_account as ba ON ba.rowid = t.fk_bank ";
$sql .= $sql_where;
$sql .= $db->order($sortfield, $sortorder);
$sql .= $db->plimit($limit + 1, $offset);

$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}

$num = $db->num_rows($resql);

$title = $langs->trans("BankStatements");
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-ezcompta page-bankstatement-list');

$param = '';
if (!empty($search_ref)) {
	$param .= '&search_ref='.urlencode($search_ref);
}
if ($search_account > 0) {
	$param .= '&search_account='.urlencode((string) $search_account);
}
if ($search_status != '' && $search_status != '-1') {
	$param .= '&search_status='.urlencode($search_status);
}
if (!empty($search_date_start)) {
	$param .= '&search_date_start='.urlencode(dol_print_date($search_date_start, 'day'));
}
if (!empty($search_date_end)) {
	$param .= '&search_date_end='.urlencode(dol_print_date($search_date_end, 'day'));
}

print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="page" value="'.$page.'">';
print '<input type="hidden" name="limit" value="'.$limit.'">';

// Top Right Buttons
$newcardbutton = '';
$newcardbutton .= '<a class="btnTitle" href="'.dol_buildpath('/ezcompta/bankstatement_dashboard.php', 1).'" title="'.$langs->trans("Dashboard").'">';
$newcardbutton .= '<span class="fas fa-th-large paddingright"></span>'.$langs->trans("Dashboard");
$newcardbutton .= '</a> ';
if ($can_edit) {
	$newcardbutton .= '<a class="btnTitle" href="'.dol_buildpath('/ezcompta/bankstatement_card.php', 1).'?action=create" title="'.$langs->trans("NewBankStatement").'">';
	$newcardbutton .= '<span class="fas fa-plus-circle paddingright"></span>'.$langs->trans("NewBankStatement");
	$newcardbutton .= '</a>';
}

print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', $num, $nbtotalofrecords, 'fa-file-invoice-dollar', 0, $newcardbutton, '', $limit, 0, 0, 1);

print '<div class="div-table-responsive">';
print '<table class="tagtable liste">'."\n";

// Table Header
print '<tr class="liste_titre">';
print_liste_field_titre($langs->trans("Ref"), $_SERVER["PHP_SELF"], 't.ref', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans("BankAccount"), $_SERVER["PHP_SELF"], 'ba.label', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans("DateStart"), $_SERVER["PHP_SELF"], 't.dt_from', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans("DateEnd"), $_SERVER["PHP_SELF"], 't.dt_to', '', $param, '', $sortfield, $sortorder);
print_liste_field_titre($langs->trans("InitialBalance"), $_SERVER["PHP_SELF"], 't.opening_balance', '', $param, 'class="right"', $sortfield, $sortorder);
print_liste_field_titre($langs->trans("EndingBalance"), $_SERVER["PHP_SELF"], 't.closing_balance', '', $param, 'class="right"', $sortfield, $sortorder);
print_liste_field_titre($langs->trans("Debit"), $_SERVER["PHP_SELF"], 't.total_debit', '', $param, 'class="right"', $sortfield, $sortorder);
print_liste_field_titre($langs->trans("Credit"), $_SERVER["PHP_SELF"], 't.total_credit', '', $param, 'class="right"', $sortfield, $sortorder);
print_liste_field_titre($langs->trans("NbOfOperations"), $_SERVER["PHP_SELF"], 't.nb_operations', '', $param, 'class="center"', $sortfield, $sortorder);
print_liste_field_titre($langs->trans("BalanceGap"), $_SERVER["PHP_SELF"], '', '', $param, 'class="center"', $sortfield, $sortorder);
print_liste_field_titre($langs->trans("CertifiedPDF"), $_SERVER["PHP_SELF"], '', '', $param, 'class="center"', $sortfield, $sortorder);
print_liste_field_titre($langs->trans("Status"), $_SERVER["PHP_SELF"], 't.status', '', $param, 'class="center"', $sortfield, $sortorder);
print_liste_field_titre('', $_SERVER["PHP_SELF"], '', '', $param, 'class="center width80"', $sortfield, $sortorder, 'maxwidthsearch');
print '</tr>'."\n";

// Filter Row
print '<tr class="liste_titre_filter">';
// Ref
print '<td class="liste_titre"><input type="text" class="flat maxwidth100" name="search_ref" value="'.dol_escape_htmltag($search_ref).'"></td>';
// Account
print '<td class="liste_titre">';
print '<select name="search_account" class="flat maxwidth150">';
print '<option value="0">&nbsp;</option>';
$sql_accounts = "SELECT rowid, ref, label FROM ".MAIN_DB_PREFIX."bank_account WHERE entity IN (".getEntity('bank_account').") AND clos = 0 ORDER BY label ASC";
$res_accounts = $db->query($sql_accounts);
if ($res_accounts) {
	while ($acc = $db->fetch_object($res_accounts)) {
		print '<option value="'.$acc->rowid.'"'.($acc->rowid == $search_account ? ' selected' : '').'>'.dol_escape_htmltag($acc->label.' ('.$acc->ref.')').'</option>';
	}
}
print '</select>';
print '</td>';
// Date start
print '<td class="liste_titre">'.$form->selectDate($search_date_start, 'search_date_start', 0, 0, 1, '', 1, 0).'</td>';
// Date end
print '<td class="liste_titre">'.$form->selectDate($search_date_end, 'search_date_end', 0, 0, 1, '', 1, 0).'</td>';
// Initial balance
print '<td class="liste_titre"></td>';
// Ending balance
print '<td class="liste_titre"></td>';
// Debit
print '<td class="liste_titre"></td>';
// Credit
print '<td class="liste_titre"></td>';
// Nb operations
print '<td class="liste_titre"></td>';
// Gap
print '<td class="liste_titre"></td>';
// PDF
print '<td class="liste_titre"></td>';
// Status
print '<td class="liste_titre center">';
print '<select name="search_status" class="flat maxwidth100">';
print '<option value="-1">&nbsp;</option>';
print '<option value="0"'.($search_status === '0' ? ' selected' : '').'>'.$langs->trans("Draft").'</option>';
print '<option value="1"'.($search_status === '1' ? ' selected' : '').'>'.$langs->trans("Validated").'</option>';
print '<option value="9"'.($search_status === '9' ? ' selected' : '').'>'.$langs->trans("Canceled").'</option>';
print '</select>';
print '</td>';
// Search action icons
print '<td class="liste_titre center nowrap">';
$searchpicto = $form->showFilterButtons();
print $searchpicto;
print '</td>';
print '</tr>'."\n";

// Rows
$i = 0;
$total_opening = 0.0;
$total_closing = 0.0;
$total_debit = 0.0;
$total_credit = 0.0;
$total_ops = 0;

$record_static = new BankRecord($db);
$account_static = new Account($db);

while ($i < min($num, $limit)) {
	$obj = $db->fetch_object($resql);
	if (!$obj) {
		break;
	}

	$record_static->id = (int) $obj->rowid;
	$record_static->ref = $obj->ref;
	$record_static->status = (int) $obj->status;
	$record_static->dt_from = $db->jdate($obj->dt_from);
	$record_static->dt_to = $db->jdate($obj->dt_to);
	$record_static->opening_balance = ($obj->opening_balance !== null ? (float) $obj->opening_balance : null);
	$record_static->closing_balance = ($obj->closing_balance !== null ? (float) $obj->closing_balance : null);
	$record_static->total_debit = ($obj->total_debit !== null ? (float) $obj->total_debit : null);
	$record_static->total_credit = ($obj->total_credit !== null ? (float) $obj->total_credit : null);
	$record_static->nb_operations = ($obj->nb_operations !== null ? (int) $obj->nb_operations : null);

	$stats = $record_static->getCalculatedStats();

	// Locate PDF
	$pdf_file_name = null;
	$pdf_file_path = null;
	if (!empty($obj->last_main_doc) && file_exists($conf->bank->dir_output.'/'.$obj->last_main_doc)) {
		$pdf_file_path = $conf->bank->dir_output.'/'.$obj->last_main_doc;
		$pdf_file_name = basename($pdf_file_path);
	} else {
		$dir_statement = $conf->bank->dir_output.'/'.$obj->fk_bank.'/statement/'.$obj->rowid;
		if (is_dir($dir_statement)) {
			$files = glob($dir_statement.'/*.pdf');
			if (!empty($files)) {
				$pdf_file_name = basename($files[0]);
				$pdf_file_path = $files[0];
			}
		}
	}

	print '<tr class="oddeven">';

	// Ref
	print '<td class="nowrap">';
	print $record_static->getNomUrl(1);
	print '</td>';

	// Account
	print '<td class="tdoverflowmax150" title="'.dol_escape_htmltag($obj->account_label.' ('.$obj->account_ref.')').'">';
	if ($obj->fk_bank > 0) {
		$account_static->id = (int) $obj->fk_bank;
		$account_static->ref = $obj->account_ref;
		$account_static->label = $obj->account_label;
		print $account_static->getNomUrl(1);
	} else {
		print '<span class="opacitymedium">&mdash;</span>';
	}
	print '</td>';

	// Date start
	print '<td class="nowrap">'.dol_print_date($record_static->dt_from, 'day').'</td>';

	// Date end
	print '<td class="nowrap">'.dol_print_date($record_static->dt_to, 'day').'</td>';

	// Initial balance
	print '<td class="right nowrap">';
	if ($record_static->opening_balance !== null) {
		print price($record_static->opening_balance);
		$total_opening += $record_static->opening_balance;
	} else {
		print '<span class="opacitymedium">&mdash;</span>';
	}
	print '</td>';

	// Ending balance
	print '<td class="right nowrap bold">';
	if ($record_static->closing_balance !== null) {
		print price($record_static->closing_balance);
		$total_closing += $record_static->closing_balance;
	} else {
		print '<span class="opacitymedium">&mdash;</span>';
	}
	print '</td>';

	// Debit
	$disp_deb = ($record_static->total_debit !== null) ? $record_static->total_debit : $stats['calculated_debit'];
	print '<td class="right nowrap color-danger">';
	if ($disp_deb > 0) {
		print '- '.price($disp_deb);
		$total_debit += $disp_deb;
	} else {
		print '<span class="opacitymedium">0,00</span>';
	}
	print '</td>';

	// Credit
	$disp_crd = ($record_static->total_credit !== null) ? $record_static->total_credit : $stats['calculated_credit'];
	print '<td class="right nowrap color-success">';
	if ($disp_crd > 0) {
		print '+ '.price($disp_crd);
		$total_credit += $disp_crd;
	} else {
		print '<span class="opacitymedium">0,00</span>';
	}
	print '</td>';

	// Nb operations
	$disp_ops = ($record_static->nb_operations !== null) ? $record_static->nb_operations : $stats['nb_operations'];
	print '<td class="center nowrap">'.$disp_ops.'</td>';
	$total_ops += $disp_ops;

	// Gap
	print '<td class="center nowrap">';
	$gap = isset($stats['gap']) ? $stats['gap'] : 0.0;
	if (abs($gap) < 0.001) {
		print '<span class="badge badge-status4 font-small"><span class="fas fa-check paddingright"></span>0,00 €</span>';
	} else {
		print '<span class="badge badge-status8 font-small bold" title="'.$langs->trans("BalanceGap").'"><span class="fas fa-exclamation-triangle paddingright"></span>'.price($gap).'</span>';
	}
	print '</td>';

	// PDF
	print '<td class="center nowrap">';
	if ($pdf_file_name) {
		$relativepath = !empty($obj->last_main_doc) ? $obj->last_main_doc : $obj->fk_bank.'/statement/'.$obj->rowid.'/'.$pdf_file_name;
		$download_url = DOL_URL_ROOT.'/document.php?modulepart=bank&file='.urlencode($relativepath);
		print '<a href="'.$download_url.'" target="_blank" class="badge badge-info" title="'.dol_escape_htmltag($pdf_file_name).'">';
		print '<span class="fas fa-file-pdf paddingright"></span> '.dol_trunc($pdf_file_name, 18);
		print '</a> ';
		$fileinfo = array('name' => $pdf_file_name, 'fullname' => (string) $pdf_file_path);
		$preview_html = $formfile->showPreview($fileinfo, 'bank', $relativepath, 0, 'preview=1');
		if ($preview_html) {
			print $preview_html;
		}
	} else {
		print '<span class="opacitymedium">&mdash;</span>';
	}
	print '</td>';

	// Status
	print '<td class="center nowrap">'.$record_static->getLibStatut(3).'</td>';

	// Actions
	print '<td class="center nowrap">';
	print '<a href="'.dol_buildpath('/ezcompta/bankstatement_card.php', 1).'?id='.$obj->rowid.'" class="editfielda paddingright" title="'.$langs->trans("Card").'">';
	print '<span class="fas fa-eye"></span>';
	print '</a>';
	if ($can_delete) {
		print '<a href="'.$_SERVER["PHP_SELF"].'?action=delete&confirm=yes&record_id='.$obj->rowid.'&token='.newToken().'" class="deletefilelink" title="'.$langs->trans("Delete").'" onclick="return confirm(\'Voulez-vous supprimer ce relevé ?\');">';
		print img_picto($langs->trans("Delete"), 'delete');
		print '</a>';
	}
	print '</td>';

	print '</tr>'."\n";
	$i++;
}

if ($num == 0) {
	print '<tr><td colspan="13" class="opacitymedium center paddingtop paddingbottom">'.$langs->trans("NoRecordFound").'</td></tr>';
}

// Table Footer (Totals)
if ($num > 0) {
	print '<tr class="liste_total">';
	print '<td colspan="4" class="liste_total bold">'.$langs->trans("Total").'</td>';
	print '<td class="liste_total right bold nowrap">'.price($total_opening).'</td>';
	print '<td class="liste_total right bold nowrap">'.price($total_closing).'</td>';
	print '<td class="liste_total right bold color-danger nowrap">- '.price($total_debit).'</td>';
	print '<td class="liste_total right bold color-success nowrap">+ '.price($total_credit).'</td>';
	print '<td class="liste_total center bold nowrap">'.$total_ops.'</td>';
	print '<td colspan="4" class="liste_total"></td>';
	print '</tr>'."\n";
}

print '</table>';
print '</div>';

print '</form>';

llxFooter();
$db->close();
