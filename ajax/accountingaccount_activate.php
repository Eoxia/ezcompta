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

$accountID = GETPOSTINT('accountid');
$toggleaction = GETPOST('toggleaction', 'alphanohtml');

$result = array();
$result['accountid'] = $accountID;
$result['toggleaction'] = $toggleaction;

$accounting = new AccountingAccount($db);

// Distinct de $res, qui porte le resultat du chargement de main.inc.php
$toggleResult = 0;

if ($toggleaction == 'disable' && $user->hasRight('accounting', 'chartofaccount')) {
	if ($accounting->fetch($accountID)) {
		$toggleResult = $accounting->accountDeactivate($accountID, 0);
	}
} elseif ($toggleaction == 'enable' && $user->hasRight('accounting', 'chartofaccount')) {
	if ($accounting->fetch($accountID)) {
		$toggleResult = $accounting->accountActivate($accountID, 0);
	}
}

if ($toggleResult > 0) {
	$result['success'] = true;
} else {
	$result['success'] = false;
}

print json_encode($result);