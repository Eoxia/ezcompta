<?php
/* Copyright (C) 2024 Anthony Damhet <a.damhet@progiseize.fr>
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

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1'); // Disables token renewal
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}
require_once DOL_DOCUMENT_ROOT.'/accountancy/class/accountingaccount.class.php';
dol_include_once('/ezcompta/class/ezcompta.class.php');


$newLabel = GETPOST('newlabel','alphanohtml');
$accountNumber = GETPOST('accountnumber','alphanohtml');
$pcgVersion = GETPOST('pcg_version','alphanohtml');
$pcgType = GETPOST('pcg_type','alphanohtml');
$parentID = GETPOSTINT('parentid');
$level = GETPOSTINT('level');
$action = '';

$accounting = new AccountingAccount($db);
$accounting->fetch(0, $accountNumber, $pcgVersion);

$ezcompta = new EzCompta($db);

$result = array();

if ($accounting->id > 0) {
	$result['action'] = 'update';
	$accounting->label = $newLabel;
	$res = $accounting->update($user);
} else {
	$result['action'] = 'create';
	$accounting->fk_pcg_version = $pcgVersion;
	$accounting->account_number = $accountNumber;
	$accounting->label = $newLabel;
	$accounting->pcg_type = $pcgType;
	$accounting->account_parent = $parentID;
	$accounting->active = 1;
	$res = $accounting->create($user); // Return create ID
	if ($res) {
		// Get unordered list of accounting accounts
		$list = $ezcompta->getAccountingAccountsByStartNumber($accountNumber[0], $pcgVersion);
		$listorder = $ezcompta->makeListOfAccountingAccountsByAccountNumber($list);
		$result['sublist'] = $ezcompta->recursiveListAccountingAccounts($accounting->account_number, $listorder, $action, $level, $accounting->id, $pcgType);
	}


}

if ($res > 0) {
	$result['success'] = true;
	$result['label'] = $accounting->label;
	$result['account_number'] = $accounting->account_number;
	$result['fk_pcg_version'] = $accounting->fk_pcg_version;
	$result['pcg_type'] = $accounting->pcg_type;
	$result['account_parent'] = $accounting->account_parent;
	$result['active'] = $accounting->active;
	$result['accountid'] = $accounting->id;
} else {
	$result['success'] = false;
	$result['error'] = $accounting->error;
}
print json_encode($result);

// Compte père + groupe de compte