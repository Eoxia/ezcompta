<?php
/* Copyright (C) 2007-2017  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2026       Laurent MAGNIN          <laurent.magnin@evarisk.com>
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
 *  \file       bankstatement_note.php
 *  \ingroup    ezcompta
 *  \brief      Tab for notes on BankStatement
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

dol_include_once('/ezcompta/class/bankrecord.class.php');
dol_include_once('/ezcompta/lib/ezcompta_bankstatement.lib.php');

$langs->loadLangs(array("ezcompta@ezcompta", "companies", "bills", "other"));

$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');

$object = new BankRecord($db);

$hookmanager->initHooks(array('bankstatementnote', 'globalcard'));

include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';

$can_edit = ($user->admin || $user->hasRight('ezcompta', 'write') || $user->hasRight('banque', 'modifier'));
$permissionnote = $can_edit;

/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}
if (empty($reshook)) {
	include DOL_DOCUMENT_ROOT.'/core/actions_setnotes.inc.php';
}

/*
 * View
 */

$form = new Form($db);

$title = $langs->trans("BankStatement")." - ".$langs->trans("Notes");
$help_url = '';

llxHeader('', $title, $help_url, '', 0, 0, '', '', '', 'mod-ezcompta page-card_notes');

if ($id > 0 || !empty($ref)) {
	$head = bankstatementPrepareHead($object);

	print dol_get_fiche_head($head, 'note', $langs->trans("BankStatement"), -1, 'fa-file-invoice-dollar');

	$linkback = '<a href="'.dol_buildpath('/ezcompta/bankstatement_list.php', 1).'">'.$langs->trans("BackToList").'</a>';

	$morehtmlref = '<div class="refidno">';
	$morehtmlref .= '</div>';

	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';

	$cssclass = "titlefield";
	include DOL_DOCUMENT_ROOT.'/core/tpl/notes.tpl.php';

	print '</div>';

	print dol_get_fiche_end();
}

llxFooter();
$db->close();
