<?php
/* Copyright (C) 2001-2005  Rodolphe Quiedeville    <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012  Regis Houssin           <regis.houssin@inodbox.com>
 * Copyright (C) 2015       Jean-François Ferry     <jfefe@aternatik.fr>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 * Copyright (C) ---Replace with your own copyright and developer email---
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
 *	\file       ezcompta/ezcompta_index.php
 *	\ingroup    ezcompta
 *	\brief      Home page of ezcompta top menu
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
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
 * The main.inc.php has been included so the following variable are now defined:
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */
include_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
dol_include_once('/ezcompta/lib/ezcompta.lib.php');

// Load translation files required by the page
$langs->loadLangs(array("ezcompta@ezcompta", "compta"));

$action = GETPOST('action', 'aZ09');

$now = dol_now();
$max = getDolGlobalInt('MAIN_SIZE_SHORTLIST_LIMIT', 5);

// Security check - Protection if external user
$socid = GETPOSTINT('socid');
if (!empty($user->socid) && $user->socid > 0) {
	$action = '';
	$socid = $user->socid;
}

/*
 * Actions
 */

if ($action == "update_data"
	&& isModEnabled("banking4dolibarr")) {
	$sql = "INSERT INTO ".$db->prefix()."bank_import(id_account, record_type, label, record_type_origin, label_origin, comment, note, bdate,
                            vdate, date_scraped, original_amount, original_currency, amount_credit, amount_debit,
                            deleted_date, fk_duplicate_of, status, datec, tms, fk_user_author, fk_user_modif,
                            import_key, datas)
SELECT b4a.fk_bank_account,
       bkr.record_type,
       bkr.label,
       bkr.sub_record_type,
       bkr.label,
       bkr.comment,
       bkr.note,
       bkr.bdate,
       bkr.vdate,
       bkr.date_scraped,
       bkr.original_amount,
       bkr.original_currency,
       bkr.amount,
       0,
       bkr.deleted_date,
       bkr.fk_duplicate_of,
       bkr.status,
       bkr.datec,
       bkr.tms,
       bkr.fk_user_author,
       bkr.fk_user_modif,
       bkr.id_record,
       bkr.datas
FROM ".$db->prefix()."banking4dolibarr_bank_record as bkr
INNER JOIN ".$db->prefix()."c_banking4dolibarr_bank_account as b4a ON b4a.rowid = bkr.id_account
WHERE bkr.amount >= 0
  AND bkr.id_record NOT IN (SELECT import_key FROM ".$db->prefix()."bank_import WHERE import_key IS NOT NULL)";

	$resql = $db->query($sql);
	if (!$resql) {
		setEventMessages($db->error(), null, 'errors');
	} else {
		setEventMessages($langs->trans('EzComptaRecordsInserted', $db->affected_rows($resql), $langs->transnoentities('AccountingCredit')), null);
	}

	$sql = "INSERT INTO ".$db->prefix()."bank_import(id_account, record_type, label, record_type_origin, label_origin, comment, note, bdate,
                            vdate, date_scraped, original_amount, original_currency, amount_credit, amount_debit,
                            deleted_date, fk_duplicate_of, status, datec, tms, fk_user_author, fk_user_modif,
                            import_key, datas)
SELECT b4a.fk_bank_account,
       bkr.record_type,
       bkr.label,
       bkr.sub_record_type,
       bkr.label,
       bkr.comment,
       bkr.note,
       bkr.bdate,
       bkr.vdate,
       bkr.date_scraped,
       bkr.original_amount,
       bkr.original_currency,
       0,
       bkr.amount,
       bkr.deleted_date,
       bkr.fk_duplicate_of,
       bkr.status,
       bkr.datec,
       bkr.tms,
       bkr.fk_user_author,
       bkr.fk_user_modif,
       bkr.id_record,
       bkr.datas
FROM ".$db->prefix()."banking4dolibarr_bank_record as bkr
INNER JOIN ".$db->prefix()."c_banking4dolibarr_bank_account as b4a ON b4a.rowid = bkr.id_account
WHERE bkr.amount < 0
  AND bkr.id_record NOT IN (SELECT import_key FROM ".$db->prefix()."bank_import WHERE import_key IS NOT NULL)";

	$resql = $db->query($sql);
	if (!$resql) {
		setEventMessages($db->error(), null, 'errors');
	} else {
		setEventMessages($langs->trans('EzComptaRecordsInserted', $db->affected_rows($resql), $langs->transnoentities('AccountingDebit')), null);
	}
}


/*
 * View
 */

$form = new Form($db);
$formfile = new FormFile($db);

llxHeader("", $langs->trans("EzCompta"), '', '', 0, 0, '', '', '', 'mod-ezcompta page-index');

print load_fiche_titre($langs->trans("EzCompta"), '', 'ezcompta.png@ezcompta');

print '<div class="fichecenter">';

if (isModEnabled("banking4dolibarr")) {
	// Say what is waiting before asking the user to click, so the state of the data is never a guess
	$pending = ezcomptaGetPendingTransferInfo($db);
	if ($pending['nb'] > 0) {
		$message = $langs->trans('EzComptaPendingTransferCount', $pending['nb']);
		if (!empty($pending['firstdate'])) {
			$message .= ' '.$langs->trans('EzComptaPendingTransferPeriod', dol_print_date($pending['firstdate'], 'day'), dol_print_date($pending['lastdate'], 'day'));
		}
		print '<div class="warning">'.$message.'</div>';
	} else {
		print '<div class="info">'.$langs->trans('EzComptaPendingTransferNone').'</div>';
	}

	print dolGetButtonAction('', $langs->trans('EzComptaTransfertDataToNewBankTable'), 'default', $_SERVER["PHP_SELF"].'?action=update_data&token='.newToken(), '', $user->hasRight("banque", "read"));
}

print '</div>';

// End of page
llxFooter();
$db->close();
