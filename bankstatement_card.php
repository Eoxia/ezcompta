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
 * \file        bankstatement_card.php
 * \ingroup     ezcompta
 * \brief       Bank statement card and operations line management (BankRecord & BankRecordLine)
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
dol_include_once('/ezcompta/class/bankrecordline.class.php');
dol_include_once('/ezcompta/lib/ezcompta_bankstatement.lib.php');

// Security check
if (!$user->hasRight('banque', 'lire') && !$user->hasRight('banque', 'read')) {
	accessforbidden();
}

$langs->loadLangs(array("banks", "bills", "compta", "ezcompta@ezcompta", "other"));

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$lineid = GETPOSTINT('lineid');

$can_edit = ($user->hasRight('banque', 'modifier') || $user->hasRight('banque', 'write'));
$can_delete = ($user->hasRight('banque', 'modifier') || $user->hasRight('banque', 'write'));
$allow_manual_lines = getDolGlobalInt('EZCOMPTA_BANKSTATEMENT_ALLOW_MANUAL_LINES', 0);

$object = new BankRecord($db);
if ($action != 'create' && $action != 'add') {
	if ($id > 0 || !empty($ref)) {
		$res = $object->fetch($id, $ref);
		if ($res <= 0) {
			accessforbidden($langs->trans("RecordNotFound"));
		}
	} else {
		accessforbidden();
	}
}


/*
 * Actions
 */

// Action: Add statement (Creation)
if ($action == 'add' && $can_edit) {
	$object->ref = trim(GETPOST('ref', 'alpha'));
	$object->fk_bank = GETPOSTINT('fk_bank');
	$object->dt_from = GETPOSTDATE('dt_from', '', 'tzuserrel');
	$object->dt_to = GETPOSTDATE('dt_to', '', 'tzuserrel');
	$op_bal = GETPOST('opening_balance', 'alpha');
	$cl_bal = GETPOST('closing_balance', 'alpha');
	$tot_deb = GETPOST('total_debit', 'alpha');
	$tot_crd = GETPOST('total_credit', 'alpha');
	$nb_op = GETPOST('nb_operations', 'alpha');

	$object->opening_balance = ($op_bal !== '' ? price2num($op_bal) : null);
	$object->closing_balance = ($cl_bal !== '' ? price2num($cl_bal) : null);
	$object->total_debit = ($tot_deb !== '' ? price2num($tot_deb) : null);
	$object->total_credit = ($tot_crd !== '' ? price2num($tot_crd) : null);
	$object->nb_operations = ($nb_op !== '' ? GETPOSTINT('nb_operations') : null);
	$object->note_public = GETPOST('note_public', 'restricthtml');
	$object->note_private = GETPOST('note_private', 'restricthtml');

	$addon = getDolGlobalString('EZCOMPTA_BANKSTATEMENT_ADDON', 'mod_bankstatement_bankomatik');
	if (empty($object->ref) || $object->ref == '(PROV)') {
		if ($addon == 'mod_bankstatement_bankanarchie' || $addon == 'mod_bankstatement_free') {
			setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentities("Ref")), null, 'errors');
			$action = 'create';
		} else {
			$newref = $object->getNextNumRef();
			if ($newref !== -1 && !empty($newref)) {
				$object->ref = $newref;
			}
		}
	}

	if (empty($object->ref) && $action != 'create') {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentities("Ref")), null, 'errors');
		$action = 'create';
	} elseif (empty($object->fk_bank) && $action != 'create') {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentities("BankAccount")), null, 'errors');
		$action = 'create';
	} else {
		$res = $object->create($user);
		if ($res > 0) {
			if (!empty($object->nb_operations) && $object->nb_operations > 0) {
				for ($i = 1; $i <= $object->nb_operations; $i++) {
					$default_date = !empty($object->dt_from) ? $object->dt_from : dol_now();
					$object->addline(
						$default_date,
						$default_date,
						0.0,
						$langs->trans("Operation").' '.$i
					);
				}
			}
			setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
			header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
			$action = 'create';
		}
	}
}

// Action: Validate statement
if ($action == 'confirm_validate' && $confirm == 'yes' && $can_edit) {
	$object->fetch_lines();
	$nb_lines = count($object->lines);
	if ($object->nb_operations !== null && $object->nb_operations > 0 && $nb_lines != $object->nb_operations) {
		setEventMessages($langs->trans("ErrorLinesCountMismatchWithNbOperations", $nb_lines, $object->nb_operations), null, 'errors');
		header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
		exit;
	}

	$res = $object->validate($user);
	if ($res > 0) {
		setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// Action: Reopen statement
if ($action == 'confirm_reopen' && $confirm == 'yes' && $can_edit) {
	$res = $object->reopen($user);
	if ($res > 0) {
		setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// Action: Delete statement
if ($action == 'confirm_delete' && $confirm == 'yes' && $can_delete) {
	$res = $object->delete($user);
	if ($res > 0) {
		setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
		header("Location: ".dol_buildpath('/ezcompta/bankstatement_list.php', 1));
		exit;
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

// Action: Update header
if ($action == 'update_header' && $can_edit && $object->status != BankRecord::STATUS_VALIDATED) {
	$object->ref = trim(GETPOST('ref', 'alpha'));
	$object->dt_from = GETPOSTDATE('dt_from', '', 'tzuserrel');
	$object->dt_to = GETPOSTDATE('dt_to', '', 'tzuserrel');
	$op_bal = GETPOST('opening_balance', 'alpha');
	$cl_bal = GETPOST('closing_balance', 'alpha');
	$tot_deb = GETPOST('total_debit', 'alpha');
	$tot_crd = GETPOST('total_credit', 'alpha');
	$nb_op = GETPOST('nb_operations', 'alpha');

	$object->opening_balance = ($op_bal !== '' ? price2num($op_bal) : null);
	$object->closing_balance = ($cl_bal !== '' ? price2num($cl_bal) : null);
	$object->total_debit = ($tot_deb !== '' ? price2num($tot_deb) : null);
	$object->total_credit = ($tot_crd !== '' ? price2num($tot_crd) : null);
	$object->nb_operations = ($nb_op !== '' ? GETPOSTINT('nb_operations') : null);
	$object->note_public = GETPOST('note_public', 'restricthtml');
	$object->note_private = GETPOST('note_private', 'restricthtml');

	$res = $object->update($user);
	if ($res > 0) {
		setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}

// Action: Add line
if ($action == 'addline' && $can_edit && $object->status != BankRecord::STATUS_VALIDATED && !empty($allow_manual_lines)) {
	$bdate = GETPOSTDATE('bdate', '', 'tzuserrel');
	$vdate = GETPOSTDATE('vdate', '', 'tzuserrel');
	if (empty($bdate)) {
		$bdate = dol_now();
	}
	if (empty($vdate)) {
		$vdate = $bdate;
	}
	$label = trim(GETPOST('label', 'restricthtml'));
	$deb_str = GETPOST('amount_debit', 'alpha');
	$crd_str = GETPOST('amount_credit', 'alpha');
	$amount_debit = price2num($deb_str);
	$amount_credit = price2num($crd_str);
	$amount = 0.0;
	if (!empty($amount_credit) && $amount_credit > 0) {
		$amount = (float) $amount_credit;
	} elseif (!empty($amount_debit) && $amount_debit > 0) {
		$amount = -(float) $amount_debit;
	}

	$end_to_end_id = trim(GETPOST('end_to_end_id', 'alpha'));
	$counterparty_name = trim(GETPOST('counterparty_name', 'restricthtml'));
	$fk_soc = GETPOSTINT('fk_soc');
	$line_status = GETPOSTINT('status');

	if (empty($label)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentities("Label")), null, 'errors');
	} else {
		$res = $object->addline(
			$bdate,
			$vdate,
			$amount,
			$label,
			'EUR',
			0.0,
			$end_to_end_id ? $end_to_end_id : null,
			null,
			null,
			null,
			$counterparty_name ? $counterparty_name : null,
			null,
			null,
			null,
			null,
			null,
			null,
			$fk_soc > 0 ? $fk_soc : null,
			null,
			$line_status
		);
		if ($res > 0) {
			setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
			header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}
}

// Action: Update line
if ($action == 'updateline' && $can_edit && $object->status != BankRecord::STATUS_VALIDATED) {
	$bdate = GETPOSTDATE('bdate', '', 'tzuserrel');
	$vdate = GETPOSTDATE('vdate', '', 'tzuserrel');
	$label = trim(GETPOST('label', 'restricthtml'));
	$deb_str = GETPOST('amount_debit', 'alpha');
	$crd_str = GETPOST('amount_credit', 'alpha');
	$amount_debit = price2num($deb_str);
	$amount_credit = price2num($crd_str);
	$amount = 0.0;
	if (!empty($amount_credit) && $amount_credit > 0) {
		$amount = (float) $amount_credit;
	} elseif (!empty($amount_debit) && $amount_debit > 0) {
		$amount = -(float) $amount_debit;
	}

	$end_to_end_id = trim(GETPOST('end_to_end_id', 'alpha'));
	$counterparty_name = trim(GETPOST('counterparty_name', 'restricthtml'));
	$fk_soc = GETPOSTINT('fk_soc');
	$line_status = GETPOSTINT('status');

	if (empty($label)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentities("Label")), null, 'errors');
	} elseif (empty($amount)) {
		setEventMessages($langs->trans("ErrorFieldRequired", $langs->transnoentities("Amount")), null, 'errors');
	} else {
		$res = $object->updateline(
			$lineid,
			$bdate,
			$vdate,
			$amount,
			$label,
			'EUR',
			0.0,
			$end_to_end_id ? $end_to_end_id : null,
			null,
			null,
			null,
			$counterparty_name ? $counterparty_name : null,
			null,
			null,
			null,
			null,
			null,
			null,
			$fk_soc > 0 ? $fk_soc : null,
			null,
			$line_status
		);
		if ($res > 0) {
			setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
			header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}
}

// Action: Delete line
if ($action == 'confirm_deleteline' && $confirm == 'yes' && $can_delete && $lineid > 0 && $object->status != BankRecord::STATUS_VALIDATED && (!empty($allow_manual_lines) || count($object->lines) > (int) $object->nb_operations)) {
	$res = $object->deleteline($user, $lineid);
	if ($res > 0) {
		setEventMessages($langs->trans("RecordDeleted"), null, 'mesgs');
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}


// Action: Upload PDF document
if ($action == 'upload_pdf' && $can_edit && !empty($_FILES['statement_pdf']['name'])) {
	$target_dir = $conf->bank->dir_output.'/'.$object->fk_bank.'/statement/'.$object->id;
	if (!is_dir($target_dir)) {
		dol_mkdir($target_dir);
	}
	$clean_name = dol_sanitizeFileName($_FILES['statement_pdf']['name']);
	$tmp_file = $_FILES['statement_pdf']['tmp_name'];
	if (dol_move_uploaded_file($tmp_file, $target_dir.'/'.$clean_name, 1)) {
		$object->last_main_doc = $object->fk_bank.'/statement/'.$object->id.'/'.$clean_name;
		$object->update($user);
		setEventMessages($langs->trans("FileTransferComplete"), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("ErrorFailedToSaveFile"), null, 'errors');
	}
	header("Location: ".$_SERVER["PHP_SELF"]."?id=".$object->id);
	exit;
}


/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

if ($action == 'create') {
	$title = $langs->trans("NewBankStatement");
	llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-ezcompta page-bankstatement-card');

	print load_fiche_titre($title, '', 'fa-file-invoice-dollar');

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';

	print dol_get_fiche_head(array(), '');

	print '<table class="border centpercent tableforfield">';

	// Reference
	$addon = getDolGlobalString('EZCOMPTA_BANKSTATEMENT_ADDON', 'mod_bankstatement_bankomatik');
	if ($addon == 'mod_bankstatement_bankanarchie' || $addon == 'mod_bankstatement_free') {
		print '<tr><td class="titlefieldfield required">'.$langs->trans("Ref").'</td><td><input type="text" name="ref" class="minwidth200" placeholder="ex: 2026-10 ou 010" required></td></tr>';
	} else {
		$nextref = $object->getNextNumRef();
		if ($nextref == -1 || empty($nextref)) {
			$nextref = '(PROV)';
		}
		print '<tr><td class="titlefieldfield required">'.$langs->trans("Ref").'</td><td><input type="text" name="ref" class="minwidth200" value="'.dol_escape_htmltag($nextref).'"></td></tr>';
	}

	// Bank account
	print '<tr><td class="required">'.$langs->trans("BankAccount").'</td><td>';
	print '<select name="fk_bank" class="flat" required>';
	$sql_accounts = "SELECT rowid, ref, label FROM ".MAIN_DB_PREFIX."bank_account WHERE entity IN (".getEntity('bank_account').") AND clos = 0 ORDER BY label ASC";
	$res_accounts = $db->query($sql_accounts);
	if ($res_accounts) {
		while ($acc = $db->fetch_object($res_accounts)) {
			print '<option value="'.$acc->rowid.'">'.dol_escape_htmltag($acc->label.' ('.$acc->ref.')').'</option>';
		}
	}
	print '</select>';
	print '</td></tr>';

	// Period
	print '<tr><td>'.$langs->trans("Period").'</td><td>';
	print $form->selectDate(dol_now(), 'dt_from').' '.$langs->trans("To").' '.$form->selectDate(dol_now(), 'dt_to');
	print '</td></tr>';

	// Opening Balance
	print '<tr><td>'.$langs->trans("InitialBalance").'</td><td><input type="text" name="opening_balance" class="width100 right" placeholder="0.00"> €</td></tr>';

	// Ending Balance
	print '<tr><td>'.$langs->trans("EndingBalance").'</td><td><input type="text" name="closing_balance" class="width100 right" placeholder="0.00"> €</td></tr>';

	// Total Debit
	print '<tr><td>'.$langs->trans("TotalDebit").'</td><td><input type="text" name="total_debit" class="width100 right" placeholder="0.00"> €</td></tr>';

	// Total Credit
	print '<tr><td>'.$langs->trans("TotalCredit").'</td><td><input type="text" name="total_credit" class="width100 right" placeholder="0.00"> €</td></tr>';

	// Nb operations
	print '<tr><td>'.$langs->trans("NbOfOperations").'</td><td><input type="number" name="nb_operations" class="width100" placeholder="0"></td></tr>';

	// Public Note
	print '<tr><td>'.$langs->trans("NotePublic").'</td><td>';
	print '<textarea name="note_public" class="centpercent" rows="3"></textarea>';
	print '</td></tr>';

	// Private Note
	print '<tr><td>'.$langs->trans("NotePrivate").'</td><td>';
	print '<textarea name="note_private" class="centpercent" rows="3"></textarea>';
	print '</td></tr>';

	print '</table>';

	print dol_get_fiche_end();

	print '<div class="center">';
	print '<input type="submit" class="button button-save" value="'.$langs->trans("Create").'"> ';
	print '<a href="'.dol_buildpath('/ezcompta/bankstatement_list.php', 1).'" class="button button-cancel">'.$langs->trans("Cancel").'</a>';
	print '</div>';

	print '</form>';

	llxFooter();
	$db->close();
	exit;
}

$object->fetch_lines();

// If nb_operations is defined: generate initial template lines or complete up to nb_operations if manual lines disabled
if ($can_edit && $object->status == BankRecord::STATUS_DRAFT && !empty($object->nb_operations) && $object->nb_operations > 0) {
	$nb_existing = count($object->lines);
	if ($nb_existing == 0 || (empty($allow_manual_lines) && $nb_existing < (int) $object->nb_operations)) {
		for ($i = $nb_existing + 1; $i <= (int) $object->nb_operations; $i++) {
			$default_date = !empty($object->dt_from) ? $object->dt_from : dol_now();
			$object->addline(
				$default_date,
				$default_date,
				0.0,
				$langs->trans("Operation").' '.$i
			);
		}
		$object->fetch_lines();
	}
}

$stats = $object->getCalculatedStats();

$num_lines = count($object->lines);
$lines_count_matches = ($object->nb_operations === null || $object->nb_operations === '' || (int) $object->nb_operations <= 0 || $num_lines == (int) $object->nb_operations);
$warn_msg = $langs->trans("WarningLinesCountMustMatchNbOperations", $num_lines, (int) $object->nb_operations);

// Locate PDF document if any
$pdf_file_path = null;
$pdf_file_name = null;
$dir_statement = $conf->bank->dir_output.'/'.$object->fk_bank.'/statement/'.$object->id;
if (!empty($object->last_main_doc) && file_exists($conf->bank->dir_output.'/'.$object->last_main_doc)) {
	$pdf_file_path = $conf->bank->dir_output.'/'.$object->last_main_doc;
	$pdf_file_name = basename($pdf_file_path);
} elseif (is_dir($dir_statement)) {
	$files = glob($dir_statement.'/*.pdf');
	if (!empty($files)) {
		$pdf_file_path = end($files);
		$pdf_file_name = basename($pdf_file_path);
	}
}

$title = $langs->trans("BankStatement")." - ".dol_escape_htmltag($object->ref);
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-ezcompta page-bankstatement-card');

// Confirmation modals
if ($action == 'validate') {
	if (!$lines_count_matches) {
		setEventMessages($warn_msg, null, 'warnings');
	} else {
		print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('Validate'), $langs->trans('ConfirmValidateBankStatement'), 'confirm_validate', '', 0, 1);
	}
} elseif ($action == 'reopen') {
	print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('ReOpen'), $langs->trans('ConfirmReopenBankStatement'), 'confirm_reopen', '', 0, 1);
} elseif ($action == 'delete') {
	print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('DeleteBankStatement'), $langs->trans('ConfirmDeleteObject'), 'confirm_delete', '', 0, 1);
} elseif ($action == 'ask_deleteline') {
	print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id.'&lineid='.$lineid, $langs->trans('DeleteLine'), $langs->trans('ConfirmDeleteLine'), 'confirm_deleteline', '', 0, 1);
}

$head = bankstatementPrepareHead($object);
print dol_get_fiche_head($head, 'card', $langs->trans("BankStatement"), -1, 'fa-file-invoice-dollar');

$linkback = '<a href="'.dol_buildpath('/ezcompta/bankstatement_list.php', 1).'">'.$langs->trans("BackToList").'</a>';
$morehtmlref = '<div class="refidno">';
$account = new Account($db);
if ($object->fk_bank > 0 && $account->fetch($object->fk_bank) > 0) {
	$morehtmlref .= $langs->trans("BankAccount").' : '.$account->getNomUrl(1);
}
if (!empty($object->dt_from) && !empty($object->dt_to)) {
	$morehtmlref .= ' &mdash; <span class="opacitymedium">'.dol_print_date($object->dt_from, 'day').' '.$langs->trans("To").' '.dol_print_date($object->dt_to, 'day').'</span>';
}
$morehtmlref .= '</div>';

dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

// Statement balances summary
print '<div class="underbanner clearboth"></div>';

if ($action == 'edit_header' && $can_edit && $object->status != BankRecord::STATUS_VALIDATED) {
	// Edit header form
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update_header">';

	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefieldfield">'.$langs->trans("Ref").'</td><td><input type="text" name="ref" value="'.dol_escape_htmltag($object->ref).'" required></td></tr>';
	print '<tr><td>'.$langs->trans("Period").'</td><td>';
	print $form->selectDate($object->dt_from, 'dt_from').' '.$langs->trans("To").' '.$form->selectDate($object->dt_to, 'dt_to');
	print '</td></tr>';
	print '<tr><td>'.$langs->trans("InitialBalance").'</td><td><input type="text" name="opening_balance" value="'.price2num($object->opening_balance).'" class="width100 right"> €</td></tr>';
	print '<tr><td>'.$langs->trans("EndingBalance").'</td><td><input type="text" name="closing_balance" value="'.price2num($object->closing_balance).'" class="width100 right"> €</td></tr>';
	print '<tr><td>'.$langs->trans("TotalDebit").'</td><td><input type="text" name="total_debit" value="'.price2num($object->total_debit).'" class="width100 right"> €</td></tr>';
	print '<tr><td>'.$langs->trans("TotalCredit").'</td><td><input type="text" name="total_credit" value="'.price2num($object->total_credit).'" class="width100 right"> €</td></tr>';
	print '<tr><td>'.$langs->trans("NbOfOperations").'</td><td><input type="number" name="nb_operations" value="'.($object->nb_operations !== null ? (int) $object->nb_operations : '').'" class="width100"></td></tr>';
	print '<tr><td>'.$langs->trans("NotePublic").'</td><td><textarea name="note_public" class="centpercent" rows="3">'.dol_escape_htmltag($object->note_public).'</textarea></td></tr>';
	print '<tr><td>'.$langs->trans("NotePrivate").'</td><td><textarea name="note_private" class="centpercent" rows="3">'.dol_escape_htmltag($object->note_private).'</textarea></td></tr>';
	print '</table>';

	print '<div class="center marginbottomonly margintoponly">';
	print '<input type="submit" class="button button-save" value="'.$langs->trans("Save").'"> ';
	print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" class="button button-cancel">'.$langs->trans("Cancel").'</a>';
	print '</div>';
	print '</form>';
} else {
	// Display header
	print '<div class="fichecenter">';
	print '<div class="fichehalfleft">';
	print '<div class="underrefbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">';

	// Opening Balance
	print '<tr><td class="titlefieldfield">'.$langs->trans("InitialBalance").'</td><td class="right nowrap">';
	if ($object->opening_balance !== null) {
		print '<span class="bold">'.price($object->opening_balance).'</span>';
	} else {
		print '<span class="opacitymedium">&mdash;</span>';
	}
	print '</td></tr>';

	// Closing Balance
	print '<tr><td>'.$langs->trans("EndingBalance").'</td><td class="right nowrap">';
	if ($object->closing_balance !== null) {
		print '<span class="bold">'.price($object->closing_balance).'</span>';
		if (!empty($stats['calculated_end_balance'])) {
			print '<div class="opacitymedium font-small">('.$langs->trans("CalculatedFromLines").' : '.price($stats['calculated_end_balance']).')</div>';
		}
	} else {
		print '<span class="opacitymedium">&mdash;</span>';
	}
	print '</td></tr>';

	// Gap
	print '<tr><td>'.$langs->trans("BalanceGap").'</td><td class="right nowrap">';
	$gap = isset($stats['gap']) ? $stats['gap'] : 0.0;
	if (abs($gap) < 0.001) {
		print '<span class="badge badge-status4 bold"><span class="fas fa-check paddingright"></span>0,00 €</span>';
	} else {
		print '<span class="badge badge-status8 bold" title="'.$langs->trans("BalanceGap").'"><span class="fas fa-exclamation-triangle paddingright"></span>'.price($gap).'</span>';
	}
	print '</td></tr>';

	print '</table>';
	print '</div>';

	print '<div class="fichehalfright">';
	print '<div class="underrefbanner clearboth"></div>';
	print '<table class="border centpercent tableforfield">';

	// Operations count
	print '<tr><td class="titlefieldfield">'.$langs->trans("NbOfOperations").'</td><td class="right nowrap">';
	$pdf_ops = $object->nb_operations;
	$calc_ops = !empty($stats['nb_operations']) ? (int) $stats['nb_operations'] : 0;
	if ($pdf_ops !== null) {
		print '<b>'.$pdf_ops.' op.</b> ('.$langs->trans("OfficialStatement").')';
		if ($calc_ops != $pdf_ops) {
			print '<br><span class="badge badge-warning font-small">'.$calc_ops.' intégrées</span>';
		}
	} else {
		print '<b>'.$calc_ops.' op.</b>';
	}
	print '</td></tr>';

	// Movements (Debit / Credit)
	print '<tr><td>'.$langs->trans("Movements").'</td><td class="right nowrap">';
	$calc_crd = isset($stats['calculated_credit']) ? $stats['calculated_credit'] : 0.0;
	$calc_deb = isset($stats['calculated_debit']) ? $stats['calculated_debit'] : 0.0;
	print '<span class="color-success bold">+ '.price($calc_crd).'</span> | <span class="color-danger bold">- '.price($calc_deb).'</span>';
	print '</td></tr>';

	// Attached PDF
	print '<tr><td>'.$langs->trans("CertifiedPDF").'</td><td class="right nowrap">';
	if ($pdf_file_name) {
		$relativepath = !empty($object->last_main_doc) ? $object->last_main_doc : $object->fk_bank.'/statement/'.$object->id.'/'.$pdf_file_name;
		$download_url = DOL_URL_ROOT.'/document.php?modulepart=bank&file='.urlencode($relativepath);
		print '<a href="'.$download_url.'" target="_blank" class="badge badge-info" title="'.dol_escape_htmltag($pdf_file_name).'">';
		print '<span class="fas fa-file-pdf paddingright"></span> '.dol_trunc($pdf_file_name, 25);
		print '</a> ';
		$fileinfo = array('name' => $pdf_file_name, 'fullname' => (string) $pdf_file_path);
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
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" enctype="multipart/form-data" class="inline-block">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="upload_pdf">';
		print '<label class="button button-small" style="cursor:pointer;" title="'.$langs->trans("Upload").'">';
		print '<span class="fas fa-file-upload paddingright"></span> '.$langs->trans("Upload").' PDF';
		print '<input type="file" name="statement_pdf" accept=".pdf" style="display:none;" onchange="this.form.submit();">';
		print '</label>';
		print '</form>';
	}
	print '</td></tr>';

	print '</table>';
	print '</div>';
	print '</div>';
	print '<div class="clearboth"></div>';

	// Notes display
	if (!empty($object->note_public) || !empty($object->note_private)) {
		print '<div class="underbanner clearboth"></div>';
		print '<div class="fichecenter">';
		if (!empty($object->note_public)) {
			print '<div class="fichehalfleft">';
			print '<table class="border centpercent tableforfield">';
			print '<tr><td class="titlefieldfield">'.$langs->trans("NotePublic").'</td><td>'.dol_htmlentitiesbr($object->note_public).'</td></tr>';
			print '</table>';
			print '</div>';
		}
		if (!empty($object->note_private) && $can_edit) {
			print '<div class="fichehalfright">';
			print '<table class="border centpercent tableforfield">';
			print '<tr><td class="titlefieldfield">'.$langs->trans("NotePrivate").'</td><td>'.dol_htmlentitiesbr($object->note_private).'</td></tr>';
			print '</table>';
			print '</div>';
		}
		print '</div>';
		print '<div class="clearboth"></div>';
	}
}

print dol_get_fiche_end();


/*
 * Action buttons
 */

print '<div class="tabsAction">';

if ($can_edit && $object->status != BankRecord::STATUS_VALIDATED && $action != 'edit_header') {
	print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit_header">'.$langs->trans("Modify").'</a>';
}

if ($can_edit && $object->status == BankRecord::STATUS_DRAFT) {
	if ($lines_count_matches) {
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=validate">'.$langs->trans("Validate").'</a>';
	} else {
		print '<a class="butActionRefused classfortooltip" href="#" title="'.dol_escape_htmltag($warn_msg).'">'.$langs->trans("Validate").'</a>';
	}
} elseif ($can_edit && $object->status == BankRecord::STATUS_VALIDATED) {
	print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=reopen">'.$langs->trans("ReOpen").'</a>';
}

if ($can_delete) {
	print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete">'.$langs->trans("Delete").'</a>';
}

print '</div>';


/*
 * Statement Lines (Operations)
 */

$warning_title = '';
if ($object->status == BankRecord::STATUS_DRAFT && !$lines_count_matches) {
	$warning_title = ' <span class="color-danger bold font-normal marginleftonly"><span class="fas fa-exclamation-triangle"></span> '.dol_escape_htmltag($warn_msg).'</span>';
}
print load_fiche_titre($langs->trans("BankStatementLines").' ('.$num_lines.')'.$warning_title, '', 'fa-list-ol');

if ($object->status == BankRecord::STATUS_DRAFT && !$lines_count_matches) {
	print '<div class="warning marginbottom"><span class="fas fa-exclamation-triangle paddingright"></span> '.$warn_msg.'</div>';
}

$can_add_or_edit = ($can_edit && $object->status != BankRecord::STATUS_VALIDATED);
$can_add_lines = ($can_add_or_edit && !empty($allow_manual_lines));
$can_delete_lines = ($can_delete && $object->status != BankRecord::STATUS_VALIDATED && (!empty($allow_manual_lines) || $num_lines > (int) $object->nb_operations));

print '<div class="div-table-responsive-no-min">';

if ($can_add_or_edit && ($can_add_lines || $action == 'editline')) {
	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	if ($action == 'editline') {
		print '<input type="hidden" name="action" value="updateline">';
		print '<input type="hidden" name="lineid" value="'.$lineid.'">';
	} else {
		print '<input type="hidden" name="action" value="addline">';
	}
}

print '<table class="noborder centpercent">';

// Table Header
print '<tr class="liste_titre">';
print '<th class="liste_titre width50 center">'.$langs->trans("LineId").'</th>';
print '<th class="liste_titre width100">'.$langs->trans("Date").'</th>';
print '<th class="liste_titre width100">'.$langs->trans("ValueDate").'</th>';
print '<th class="liste_titre">'.$langs->trans("Label").'</th>';
print '<th class="liste_titre">'.$langs->trans("IsoRef").'</th>';
print '<th class="liste_titre">'.$langs->trans("Counterparty").'</th>';
print '<th class="liste_titre right width100">'.$langs->trans("Debit").'</th>';
print '<th class="liste_titre right width100">'.$langs->trans("Credit").'</th>';
print '<th class="liste_titre center width100">'.$langs->trans("Status").'</th>';
print '<th class="liste_titre center width100">'.$langs->trans("Action").'</th>';
print '</tr>';

// Inline Add Line Row (when drafting, not in editline mode, and manual lines allowed)
if ($can_add_lines && $action != 'editline') {
	$default_bdate = !empty($object->dt_from) ? $object->dt_from : dol_now();
	print '<tr class="oddeven liste_titre_create">';
	print '<td class="center opacitymedium">&mdash;</td>';
	print '<td class="nowrap">'.$form->selectDate($default_bdate, 'bdate', 0, 0, 0, '', 1, 0).'</td>';
	print '<td class="nowrap">'.$form->selectDate($default_bdate, 'vdate', 0, 0, 0, '', 1, 0).'</td>';
	print '<td><input type="text" name="label" class="centpercent" placeholder="'.$langs->trans("Label").'" required></td>';
	print '<td><input type="text" name="end_to_end_id" class="width125" placeholder="EndToEndId / R&eacute;f"></td>';
	print '<td><input type="text" name="counterparty_name" class="centpercent" placeholder="'.$langs->trans("Counterparty").'"></td>';
	print '<td class="right"><input type="text" name="amount_debit" class="width75 right" placeholder="0.00"></td>';
	print '<td class="right"><input type="text" name="amount_credit" class="width75 right" placeholder="0.00"></td>';
	print '<td class="center">';
	print '<select name="status" class="flat">';
	print '<option value="0" selected>'.$langs->trans("BankLineNotReconciled").'</option>';
	print '<option value="1">'.$langs->trans("BankLineReconciled").'</option>';
	print '<option value="2">'.$langs->trans("Excluded").'</option>';
	print '</select>';
	print '</td>';
	print '<td class="center nowrap">';
	print '<input type="submit" class="button button-add" value="'.$langs->trans("Add").'">';
	print '</td>';
	print '</tr>';
}

// Display existing lines
if ($num_lines > 0) {
	foreach ($object->lines as $line) {
		/** @var BankRecordLine $line */
		if ($action == 'editline' && $lineid == $line->id && $can_add_or_edit) {
			// Inline Edit Line Form
			print '<tr class="oddeven highlight">';
			print '<td class="center font-small bold">'.$line->id.'</td>';
			print '<td class="nowrap">'.$form->selectDate($line->bdate, 'bdate', 0, 0, 0, '', 1, 0).'</td>';
			print '<td class="nowrap">'.$form->selectDate($line->vdate, 'vdate', 0, 0, 0, '', 1, 0).'</td>';
			print '<td><input type="text" name="label" class="centpercent" value="'.dol_escape_htmltag($line->label).'" required></td>';
			print '<td><input type="text" name="end_to_end_id" class="width125" value="'.dol_escape_htmltag((string) $line->end_to_end_id).'"></td>';
			print '<td><input type="text" name="counterparty_name" class="centpercent" value="'.dol_escape_htmltag((string) $line->counterparty_name).'"></td>';
			$deb_val = ($line->amount < 0) ? price2num(abs($line->amount)) : '';
			$crd_val = ($line->amount > 0) ? price2num($line->amount) : '';
			print '<td class="right"><input type="text" name="amount_debit" class="width75 right" value="'.$deb_val.'"></td>';
			print '<td class="right"><input type="text" name="amount_credit" class="width75 right" value="'.$crd_val.'"></td>';
			print '<td class="center">';
			print '<select name="status" class="flat">';
			print '<option value="0"'.($line->status == 0 ? ' selected' : '').'>'.$langs->trans("BankLineNotReconciled").'</option>';
			print '<option value="1"'.($line->status == 1 ? ' selected' : '').'>'.$langs->trans("BankLineReconciled").'</option>';
			print '<option value="2"'.($line->status == 2 ? ' selected' : '').'>'.$langs->trans("Excluded").'</option>';
			print '</select>';
			print '</td>';
			print '<td class="center nowrap">';
			print '<input type="submit" class="button button-save button-small" value="'.$langs->trans("Save").'"> ';
			print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'" class="button button-cancel button-small">'.$langs->trans("Cancel").'</a>';
			print '</td>';
			print '</tr>';
		} else {
			// Normal row
			print '<tr class="oddeven">';
			print '<td class="center font-small opacitymedium">'.$line->id.'</td>';
			// Booking date
			print '<td class="nowrap">'.dol_print_date($line->bdate, 'day').'</td>';
			// Value date
			print '<td class="nowrap opacitymedium">'.dol_print_date($line->vdate, 'day').'</td>';
			// Label & Remittance
			print '<td>';
			print '<b>'.dolPrintText($line->label).'</b>';
			if (!empty($line->remittance_unstructured) && $line->remittance_unstructured != $line->label) {
				print '<div class="opacitymedium font-small">'.dolPrintText($line->remittance_unstructured).'</div>';
			}
			print '</td>';
			// ISO Ref
			print '<td class="nowrap">';
			if (!empty($line->end_to_end_id)) {
				print '<span class="badge badge-secondary font-small" title="EndToEndId"><span class="fas fa-fingerprint"></span> '.dol_escape_htmltag($line->end_to_end_id).'</span>';
			} elseif (!empty($line->check_number)) {
				print '<span class="badge badge-secondary font-small" title="Ch&egrave;que"><span class="fas fa-money-check"></span> '.dol_escape_htmltag($line->check_number).'</span>';
			} else {
				print '<span class="opacitymedium">&mdash;</span>';
			}
			print '</td>';
			// Counterparty / Thirdparty
			print '<td>';
			if (!empty($line->counterparty_name)) {
				print dol_escape_htmltag($line->counterparty_name);
				if (!empty($line->counterparty_iban)) {
					print '<div class="opacitymedium font-small">'.dol_escape_htmltag($line->counterparty_iban).'</div>';
				}
			} else {
				print '<span class="opacitymedium">&mdash;</span>';
			}
			print '</td>';
			// Debit
			print '<td class="right nowrap">';
			if ($line->amount < 0) {
				print '<span class="color-danger bold">'.price(abs($line->amount)).'</span>';
			}
			print '</td>';
			// Credit
			print '<td class="right nowrap">';
			if ($line->amount > 0) {
				print '<span class="color-success bold">'.price($line->amount).'</span>';
			}
			print '</td>';
			// Status
			print '<td class="center nowrap">'.$line->getLibStatut(3).'</td>';
			// Actions
			print '<td class="center nowrap">';
			if ($can_add_or_edit) {
				print '<a href="'.$_SERVER["PHP_SELF"]."?id=".$object->id."&action=editline&lineid=".$line->id.'" class="editfielda paddingright" title="'.$langs->trans("Edit").'">';
				print img_picto($langs->trans("Edit"), 'edit');
				print '</a>';
				if ($can_delete_lines) {
					print '<a href="'.$_SERVER["PHP_SELF"]."?id=".$object->id."&action=ask_deleteline&lineid=".$line->id.'" class="paddingleft" title="'.$langs->trans("Delete").'">';
					print img_picto($langs->trans("Delete"), 'delete');
					print '</a>';
				}
			}
			print '</td>';
			print '</tr>';
		}
	}
} else {
	print '<tr><td colspan="10" class="opacitymedium center paddingtop paddingbottom">'.$langs->trans("NoRecordFound").'</td></tr>';
}

// Table Footer (Totals)
$total_debit_calc = isset($stats['calculated_debit']) ? $stats['calculated_debit'] : 0.0;
$total_credit_calc = isset($stats['calculated_credit']) ? $stats['calculated_credit'] : 0.0;
$net_balance_calc = $total_credit_calc - $total_debit_calc;

print '<tr class="liste_total">';
print '<td colspan="6" class="liste_total bold">'.$langs->trans("Total").' ('.$num_lines.' op.)</td>';
print '<td class="liste_total right bold color-danger nowrap">- '.price($total_debit_calc).'</td>';
print '<td class="liste_total right bold color-success nowrap">+ '.price($total_credit_calc).'</td>';
print '<td colspan="2" class="liste_total center bold nowrap">'.($net_balance_calc >= 0 ? '<span class="color-success">+ '.price($net_balance_calc).'</span>' : '<span class="color-danger">- '.price(abs($net_balance_calc)).'</span>').'</td>';
print '</tr>';

print '</table>';

if ($can_add_or_edit && ($can_add_lines || $action == 'editline')) {
	print '</form>';
}

print '</div>';

llxFooter();
$db->close();
