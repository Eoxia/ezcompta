<?php
/* Copyright (C) 2026		Florian Henry				<florian.henry@scopen.fr>
 * Copyright (C) 2026		Laurent MAGNIN				<laurent.magnin@evarisk.com>
 *
 * This program is free software: you can redistribute it and/or modify
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
 * \file    lib/ezcompta_bankstatement.lib.php
 * \ingroup ezcompta
 * \brief   Library files with common functions for BankStatement
 */

/**
 * Prepare array of tabs for BankStatement / BankRecord
 *
 * @param	BankRecord|BankStatement	$object					BankStatement or BankRecord
 * @return 	array<array{string,string,string}>	Array of tabs
 */
function bankstatementPrepareHead($object)
{
	global $db, $langs, $conf;

	$langs->load("ezcompta@ezcompta");

	$showtabofpagecontact = 0;
	$showtabofpagenote = 1;
	$showtabofpagedocument = 1;
	$showtabofpageagenda = 0;

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/ezcompta/bankstatement_card.php", 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans("BankStatement");
	$head[$h][2] = 'card';
	$h++;

	if ($showtabofpagedocument) {
		require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
		require_once DOL_DOCUMENT_ROOT.'/core/class/link.class.php';

		$upload_dir = '';
		if (!empty($object->fk_bank) && !empty($conf->bank->dir_output)) {
			$upload_dir = $conf->bank->dir_output.'/'.$object->fk_bank.'/statement/'.$object->id;
		}
		if (empty($upload_dir) || !is_dir($upload_dir)) {
			$upload_dir = $conf->ezcompta->dir_output."/bankstatement/".$object->id;
		}

		$nbFiles = is_dir($upload_dir) ? count(dol_dir_list($upload_dir, 'files', 0, '', '(\.meta|_preview.*\.png)$')) : 0;
		$nbLinks = Link::count($db, $object->element, $object->id);
		$head[$h][0] = dol_buildpath("/ezcompta/bankstatement_document.php", 1).'?id='.$object->id;
		$head[$h][1] = $langs->trans('Documents');
		if (($nbFiles + $nbLinks) > 0) {
			$head[$h][1] .= '<span class="badge marginleftonlyshort">'.($nbFiles + $nbLinks).'</span>';
		}
		$head[$h][2] = 'document';
		$h++;
	}

	if ($showtabofpagenote) {
		$nbNotes = (!empty($object->note_private) ? 1 : 0) + (!empty($object->note_public) ? 1 : 0);
		$head[$h][0] = dol_buildpath("/ezcompta/bankstatement_note.php", 1).'?id='.$object->id;
		$head[$h][1] = $langs->trans('Notes');
		if ($nbNotes > 0) {
			$head[$h][1] .= '<span class="badge marginleftonlyshort">'.$nbNotes.'</span>';
		}
		$head[$h][2] = 'note';
		$h++;
	}

	if ($showtabofpageagenda) {
		$head[$h][0] = dol_buildpath("/ezcompta/bankstatement_agenda.php", 1).'?id='.$object->id;
		$head[$h][1] = $langs->trans("Events");
		$head[$h][2] = 'agenda';
		$h++;
	}

	complete_head_from_modules($conf, $langs, $object, $head, $h, 'bankstatement@ezcompta');
	complete_head_from_modules($conf, $langs, $object, $head, $h, 'bankstatement@ezcompta', 'remove');

	return $head;
}
