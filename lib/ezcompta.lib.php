<?php
/*
 * Copyright (C) 2025 Anthony Damhet <a.damhet@progiseize.fr>
 *
 * This program and files/directory inner it is free software: you can
 * redistribute it and/or modify it under the terms of the
 * GNU Affero General Public License (AGPL) as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AGPL for more details.
 *
 * You should have received a copy of the GNU AGPL
 * along with this program.  If not, see <https://www.gnu.org/licenses/agpl-3.0.html>.
 */

function ezcomptaAdminPrepareHead()
{
	global $langs, $conf;

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/ezcompta/admin/setup.php", 1);
    $head[$h][1] = $langs->trans('Parameters');
    $head[$h][2] = 'settings';
    $h++;

	$head[$h][0] = dol_buildpath("/ezcompta/admin/bankstatement_extrafields.php", 1);
	$head[$h][1] = $langs->trans("Extrafields");
	$head[$h][2] = 'bankstatement_extrafields';
	$h++;

	$head[$h][0] = dol_buildpath("/ezcompta/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'ezcompta@ezcompta');

	complete_head_from_modules($conf, $langs, null, $head, $h, 'ezcompta@ezcompta', 'remove');
    return $head;
}

/**
 * Count the records already downloaded by banking4dolibarr but not transferred yet into the
 * bank statement table read by EzCompta. Those records are missing from every EzCompta report
 * until the transfer is run again.
 *
 * @param	DoliDB	$db				Database handler
 * @param	int[]	$bankaccounts	Restrict to those Dolibarr bank account ids (empty = all accounts of the entity)
 * @return	array{nb:int,lastdate:int|string,firstdate:int|string}	Counters, nb = 0 when nothing is pending
 */
function ezcomptaGetPendingTransferInfo($db, $bankaccounts = array())
{
	$info = array('nb' => 0, 'lastdate' => '', 'firstdate' => '');

	if (!isModEnabled('banking4dolibarr')) {
		return $info;
	}

	$sql = "SELECT COUNT(bkr.rowid) as nb, MIN(bkr.bdate) as firstdate, MAX(bkr.bdate) as lastdate";
	$sql .= " FROM ".$db->prefix()."banking4dolibarr_bank_record as bkr";
	$sql .= " INNER JOIN ".$db->prefix()."c_banking4dolibarr_bank_account as b4a ON b4a.rowid = bkr.id_account";
	$sql .= " INNER JOIN ".$db->prefix()."bank_account as ba ON ba.rowid = b4a.fk_bank_account";
	$sql .= " WHERE ba.entity IN (".getEntity('bank_account').")";
	if (!empty($bankaccounts)) {
		$sql .= " AND b4a.fk_bank_account IN (".$db->sanitize(implode(',', array_map('intval', $bankaccounts))).")";
	}
	$sql .= " AND bkr.id_record NOT IN (SELECT import_key FROM ".$db->prefix()."bank_import WHERE import_key IS NOT NULL)";

	$resql = $db->query($sql);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		if ($obj) {
			$info['nb'] = (int) $obj->nb;
			$info['firstdate'] = $db->jdate($obj->firstdate);
			$info['lastdate'] = $db->jdate($obj->lastdate);
		}
		$db->free($resql);
	}

	return $info;
}
