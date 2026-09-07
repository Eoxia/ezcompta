<?php
/* Copyright (C) 2005       Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2004-2017  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012  Regis Houssin           <regis.houssin@inodbox.com>
 * Copyright (C) 2013-2023  Charlene BENKE          <charlene@patas-monkey.com>
 * Copyright (C) 2024		MDW							<mdeweerd@users.noreply.github.com>
 * Copyright (C) 2024		Frédéric France			<frederic.france@free.fr>
 * Copyright (C) 2025		Florian HENRY			<florian.henry@scopen.fr>
 * Copyright (C) 2026		Nicolas Domenech		<nicolas.domenech@evarisk.com>
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
 *		\file	     ezcompta/report_in_out.php
 *		\ingroup     ezcompta
 *		\brief       Page to report input-output of a bank account
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/bank.lib.php';
require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/dolgraph.class.php';
dol_include_once('/ezcompta/class/html.formezcompta.class.php');
dol_include_once('/ezcompta/lib/ezcompta_report_in_out.lib.php');

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

//inspired from htdocs/compta/bank/annuel.php

// Load translation files required by the page
$langs->loadLangs(array('banks', 'categories', 'ezcompta@ezcompta'));

$bankaccounts = GETPOST('bankaccounts', 'array') ? GETPOST('bankaccounts', 'array') : array();
$bankaccounts = array_map('intval', $bankaccounts);
$optioncss = GETPOST('optioncss', 'alpha');
$includeexcluded = GETPOSTINT('includeexcluded');
// Initialize a technical object to manage hooks of page. Note that conf->hooks_modules contains an array of hook context
$hookmanager->initHooks(array('ezcomptakannualreport', 'globalcard'));

if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All tests are required to be compatible with all browsers
	$bankaccounts = array();
	$includeexcluded = 0;
}


// Security check
$fieldvalue = (!empty($id) ? $id : (!empty($ref) ? $ref : ''));
$fieldtype = (!empty($ref) ? 'ref' : 'rowid');
if ($user->socid) {
	$socid = $user->socid;
}
if (!$user->hasRight('banque', 'lire')) {
	accessforbidden();
}

$year_start = GETPOSTINT('year_start');
$year_current = (int) dol_print_date(time(), "%Y");
if (!$year_start) {
	$year_start = $year_current - 2;
}
$year_end = $year_start + 2;


/*
 * View
 */
$error = 0;

$form = new Form($db);
$fromezcompta = new FormEzCompta($db);

$allbankaccounts = $fromezcompta->getBankAccounts();
if (empty($bankaccounts)) {
	$bankaccounts = array_keys($allbankaccounts);
}

$annee = '';
$totentrees = array();
$totsorties = array();
$nb_mois_decalage = getDolGlobalInt('SOCIETE_FISCAL_MONTH_START') ? (getDolGlobalInt('SOCIETE_FISCAL_MONTH_START') - 1) : 0;
$year_end_for_table = ($year_end - ($nb_mois_decalage > 0 ? 1 : 0));

// Boundaries of the displayed period, fiscal year offset included. They bound the SQL so that
// the figures of the panel and the figures of the table always describe the same set of records.
$datestart = dol_print_date(dol_mktime(0, 0, 0, 1 + $nb_mois_decalage, 1, $year_start), '%Y-%m-%d');
$lastmonth = 12 + $nb_mois_decalage;
$lastyear = $year_end_for_table;
if ($lastmonth > 12) {
	$lastmonth -= 12;
	$lastyear++;
}
$dateend = dol_print_date(dol_get_last_day($lastyear, $lastmonth), '%Y-%m-%d');

$stats = ezcomptaGetInOutStats($db, $bankaccounts, $datestart, $dateend, $includeexcluded);
if (!is_array($stats)) {
	$stats = array('bymonth' => array(), 'byaccountmonth' => array(), 'accountsused' => array());
}
$encaiss = array();
$decaiss = array();
foreach ($stats['bymonth'] as $dm => $amounts) {
	$encaiss[$dm] = $amounts['credit'];
	$decaiss[$dm] = $amounts['debit'];
}

$indicator = ezcomptaGetInOutSourceIndicator($db, $bankaccounts, $datestart, $dateend, $includeexcluded);

$title = $langs->trans("IOMonthlyReporting");
$helpurl = "";
llxHeader('', $title, $helpurl);

ezcomptaPrintInOutStyle();

// Where the figures come from
ezcomptaPrintInOutSourceIndicator($indicator, $allbankaccounts);

// Tabs tab / graph
print dol_get_fiche_head([], 'annual', $langs->trans("FinancialAccount"), 0, 'account');

$parambk = '';
if (!empty($bankaccounts)) {
	$parambk .= implode('&bankaccounts[]=', $bankaccounts);
	$parambk = '&bankaccounts[]='.$parambk;
}
if ($includeexcluded) {
	$parambk .= '&includeexcluded=1';
}
$link = ($year_start ? '<a href="'.$_SERVER["PHP_SELF"].'?'.$parambk.'&year_start='.($year_start - 1).'">'.img_previous('', 'class="valignbottom"')."</a> ".$langs->trans("Year").' <a href="'.$_SERVER["PHP_SELF"].'?'.$parambk.'&year_start='.($year_start + 1).'">'.img_next('', 'class="valignbottom"').'</a>' : '');

$linkback = '';
$morehtmlref = '';

print $langs->trans("FinancialAccount");
print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">'."\n";
if ($optioncss != '') {
	print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
}
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="year_start" value="'.$year_start.'">';

$fromezcompta->selectBankAccounts($bankaccounts);

print ' <label class="paddingleft"><input type="checkbox" name="includeexcluded" value="1"'.($includeexcluded ? ' checked' : '').'> '.$langs->trans('EzComptaIncludeExcluded').'</label>';

print $form->showFilterButtons();

print '</form>';

print dol_get_fiche_end();


// Affiche tableau
print load_fiche_titre('', $link, '');

print '<div class="div-table-responsive">'; // You can use div-table-responsive-no-min if you don't need reserved height for your table
print '<table class="noborder centpercent" id="ezc-report-table">';

print '<tr class="liste_titre"><td class="liste_titre">'.$langs->trans("Month").'</td>';
for ($annee = $year_start; $annee <= $year_end_for_table; $annee++) {
	print '<td colspan="3" class="liste_titre borderrightlight center">';
	print $annee;
	if ($nb_mois_decalage > 0) {
		print '-'.($annee + 1);
	}
	print '</td>';
}
print '</tr>';

print '<tr class="liste_titre">';
print '<td class="liste_titre">&nbsp;</td>';
for ($annee = $year_start; $annee <= $year_end_for_table; $annee++) {
	print '<td class="liste_titre center">'.$langs->trans("Debit").'</td>';
	print '<td class="liste_titre center">'.$langs->trans("Credit").'</td>';
	print '<td class="liste_titre center borderrightlight">'.$langs->trans("Balance").'</td>';
}
print '</tr>';

for ($annee = $year_start; $annee <= $year_end_for_table; $annee++) {
	$totsorties[$annee] = 0;
	$totentrees[$annee] = 0;
}

for ($mois = 1 + $nb_mois_decalage; $mois <= 12 + $nb_mois_decalage; $mois++) {
	$mois_modulo = $mois;
	if ($mois > 12) {
		$mois_modulo = $mois - 12;
	}

	// Months keys of that row, one per displayed year, used both by the row and by its detail
	$cases = array();
	for ($annee = $year_start; $annee <= $year_end_for_table; $annee++) {
		$annee_decalage = ($mois > 12) ? $annee + 1 : $annee;
		$cases[$annee] = dol_print_date(dol_mktime(12, 0, 0, $mois_modulo, 1, $annee_decalage), "%Y-%m");
	}

	// Bank accounts having at least one record on that month, whatever the year
	$accountsofrow = array();
	foreach ($cases as $case) {
		if (!empty($stats['byaccountmonth'][$case])) {
			foreach (array_keys($stats['byaccountmonth'][$case]) as $idaccount) {
				$accountsofrow[$idaccount] = $idaccount;
			}
		}
	}

	print '<tr class="oddeven ezc-month" data-ezc-row="'.$mois.'">';
	print '<td><span class="ezc-toggle">'.(!empty($accountsofrow) ? '&#9654;' : '&nbsp;').'</span>';
	print dol_print_date(dol_mktime(1, 1, 1, $mois_modulo, 1, 2000), "%B")."</td>";

	foreach ($cases as $annee => $case) {
		$debit = isset($decaiss[$case]) ? $decaiss[$case] : 0;
		$credit = isset($encaiss[$case]) ? $encaiss[$case] : 0;
		$totsorties[$annee] += $debit;
		$totentrees[$annee] += $credit;

		print '<td class="right nowraponall">'.($debit ? price($debit) : '&nbsp;').'</td>';
		print '<td class="right nowraponall">'.($credit ? price($credit) : '&nbsp;').'</td>';
		print '<td class="right nowraponall borderrightlight">';
		if ($debit || $credit) {
			$solde = $credit - $debit;
			print '<span class="'.($solde < 0 ? 'ezc-negative' : '').'">'.price($solde).'</span>';
		} else {
			print '&nbsp;';
		}
		print '</td>';
	}
	print '</tr>';

	// Detail of the row, one line per bank account, hidden until the month row is clicked
	foreach ($accountsofrow as $idaccount) {
		print '<tr class="ezc-detail" data-ezc-parent="'.$mois.'" style="display:none;">';
		print '<td class="ezc-detail-label">'.dol_escape_htmltag(isset($allbankaccounts[$idaccount]) ? $allbankaccounts[$idaccount] : '#'.$idaccount).'</td>';
		foreach ($cases as $annee => $case) {
			$row = isset($stats['byaccountmonth'][$case][$idaccount]) ? $stats['byaccountmonth'][$case][$idaccount] : array('credit' => 0, 'debit' => 0, 'nb' => 0);
			$listurl = dol_buildpath('/ezcompta/bankstatement_list.php', 1);
			$listurl .= '?search_id_account='.$idaccount;
			$listurl .= '&search_bdate_dtstartday=1&search_bdate_dtstartmonth='.(int) substr($case, 5, 2).'&search_bdate_dtstartyear='.(int) substr($case, 0, 4);
			$lastday = dol_print_date(dol_get_last_day((int) substr($case, 0, 4), (int) substr($case, 5, 2)), '%d');
			$listurl .= '&search_bdate_dtendday='.(int) $lastday.'&search_bdate_dtendmonth='.(int) substr($case, 5, 2).'&search_bdate_dtendyear='.(int) substr($case, 0, 4);

			print '<td class="right nowraponall">'.($row['debit'] ? '<a href="'.$listurl.'" title="'.dol_escape_htmltag($langs->trans('EzComptaSeeRecords', $row['nb'])).'">'.price($row['debit']).'</a>' : '&nbsp;').'</td>';
			print '<td class="right nowraponall">'.($row['credit'] ? '<a href="'.$listurl.'" title="'.dol_escape_htmltag($langs->trans('EzComptaSeeRecords', $row['nb'])).'">'.price($row['credit']).'</a>' : '&nbsp;').'</td>';
			print '<td class="right nowraponall borderrightlight">';
			if ($row['debit'] || $row['credit']) {
				$solde = $row['credit'] - $row['debit'];
				print '<span class="'.($solde < 0 ? 'ezc-negative' : '').'">'.price($solde).'</span>';
			} else {
				print '&nbsp;';
			}
			print '</td>';
		}
		print '</tr>';
	}
}

// Total debit-credit
print '<tr class="liste_total"><td><b>'.$langs->trans("Total")."</b></td>";
for ($annee = $year_start; $annee <= $year_end_for_table; $annee++) {
	$solde = $totentrees[$annee] - $totsorties[$annee];
	print '<td class="right nowraponall"><b>'.price($totsorties[$annee]).'</b></td>';
	print '<td class="right nowraponall"><b>'.price($totentrees[$annee]).'</b></td>';
	print '<td class="right nowraponall borderrightlight"><b><span class="'.($solde < 0 ? 'ezc-negative' : '').'">'.price($solde).'</span></b></td>';
}
print "</tr>\n";

print "</table>";

print "</div>";

print '<div class="opacitymedium small paddingtop paddingbottom">'.img_picto('', 'info', 'class="pictofixedwidth"').$langs->trans('EzComptaClickRowHint').'</div>';


/*
 * Monthly figures per bank account
 */

print load_fiche_titre($langs->trans('EzComptaMonthlyPerAccount'), '', '');

print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';

print '<tr class="liste_titre">';
print '<td class="liste_titre">'.$langs->trans('BankAccount').'</td>';
print '<td class="liste_titre center">'.$langs->trans('Year').'</td>';
for ($mois = 1 + $nb_mois_decalage; $mois <= 12 + $nb_mois_decalage; $mois++) {
	$mois_modulo = ($mois > 12) ? $mois - 12 : $mois;
	print '<td class="liste_titre center">'.dol_print_date(dol_mktime(1, 1, 1, $mois_modulo, 1, 2000), "%b").'</td>';
}
print '<td class="liste_titre right borderrightlight">'.$langs->trans('Total').'</td>';
print '</tr>';

$accountstoshow = !empty($stats['accountsused']) ? array_keys($stats['accountsused']) : array();
if (empty($accountstoshow)) {
	print '<tr class="oddeven"><td colspan="'.(15 + $nb_mois_decalage).'" class="opacitymedium">'.$langs->trans('NoRecordFound').'</td></tr>';
}
foreach ($accountstoshow as $idaccount) {
	for ($annee = $year_start; $annee <= $year_end_for_table; $annee++) {
		$totalyear = 0;
		$cells = '';
		$hasdata = 0;
		for ($mois = 1 + $nb_mois_decalage; $mois <= 12 + $nb_mois_decalage; $mois++) {
			$mois_modulo = ($mois > 12) ? $mois - 12 : $mois;
			$annee_decalage = ($mois > 12) ? $annee + 1 : $annee;
			$case = dol_print_date(dol_mktime(12, 0, 0, $mois_modulo, 1, $annee_decalage), "%Y-%m");

			$row = isset($stats['byaccountmonth'][$case][$idaccount]) ? $stats['byaccountmonth'][$case][$idaccount] : null;
			$cells .= '<td class="right nowraponall">';
			if ($row) {
				$solde = $row['credit'] - $row['debit'];
				$totalyear += $solde;
				$hasdata = 1;
				$cells .= '<span class="'.($solde < 0 ? 'ezc-negative' : '').'" title="'.dol_escape_htmltag($langs->trans('Credit').' '.price($row['credit']).' / '.$langs->trans('Debit').' '.price($row['debit']).' / '.$langs->trans('EzComptaSeeRecords', $row['nb'])).'">'.price($solde).'</span>';
			} else {
				$cells .= '&nbsp;';
			}
			$cells .= '</td>';
		}
		if (!$hasdata) {
			continue;
		}

		print '<tr class="oddeven">';
		print '<td class="nowraponall">'.dol_escape_htmltag(isset($allbankaccounts[$idaccount]) ? $allbankaccounts[$idaccount] : '#'.$idaccount).'</td>';
		print '<td class="center">'.$annee.($nb_mois_decalage > 0 ? '-'.($annee + 1) : '').'</td>';
		print $cells;
		print '<td class="right nowraponall borderrightlight"><b><span class="'.($totalyear < 0 ? 'ezc-negative' : '').'">'.price($totalyear).'</span></b></td>';
		print '</tr>';
	}
}

print '</table>';
print '</div>';

print '<div class="opacitymedium small paddingtop paddingbottom">'.img_picto('', 'info', 'class="pictofixedwidth"').$langs->trans('EzComptaMonthlyPerAccountHint').'</div>';

print '<script>
jQuery(document).ready(function() {
	jQuery("#ezc-report-table tr.ezc-month").on("click", function() {
		var row = jQuery(this).attr("data-ezc-row");
		var details = jQuery("#ezc-report-table tr.ezc-detail[data-ezc-parent=\'" + row + "\']");
		if (details.length === 0) {
			return;
		}
		var opened = details.first().is(":visible");
		details.toggle(!opened);
		jQuery(this).find(".ezc-toggle").html(opened ? "&#9654;" : "&#9660;");
	});
});
</script>';

// End of page
llxFooter();
$db->close();
