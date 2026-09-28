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


class EzCompta /*extends Commonobject*/
{

	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var string[] Array of Errors code (or messages)
	 */
	public $errors = array();

	/**
	 * @var string Table name for accounting accounts
	 */
	public $table_accountingaccount = 'accounting_account';

	/**
	 * @var int Store maximum depth (Recursive List)
	 */
	public $maximumDepth = 6;



	/**
	 * Constructor
	 *
	 * @param DoliDB $db handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Load an array with all accounting accounts starting with $startNumber for $pcgVersion
	 *
	 * @param 	int 	$startNumber 		   	Accounting accounts must start with
	 * @param 	int 	$pcgVersion 		   	Accounting system PCG version
	 * @param 	int 	$mode 		   			array => return an array, count => count elements
	 * @return 	int|array                      	Return integer <0 if KO, array if empty or found
	 */
	public function getAccountingAccountsByStartNumber(int $startNumber, $pcgVersion, $mode = 'array')
	{
		global $langs;
		require_once DOL_DOCUMENT_ROOT.'/accountancy/class/accountingaccount.class.php';


		switch ($mode) {
			case 'array':
				$sql = "SELECT aa.rowid FROM ".MAIN_DB_PREFIX.$this->table_accountingaccount." AS aa";
				$sql .= " WHERE aa.account_number LIKE '".(int) $startNumber."%'";
				$sql .= " AND aa.fk_pcg_version = '".$this->db->escape($pcgVersion)."'";
				$sql .= $this->db->order('aa.account_number', 'ASC');
				$res = $this->db->query($sql);
				if (!$res) {
					$this->error = $langs->trans('ErrorSQL');
					return -1;
				}

				$listAccountingAccount = array();
				if ($res->num_rows > 0) {
					while ($obj = $this->db->fetch_object($res)) {
						$account = new AccountingAccount($this->db);
						$account->fetch($obj->rowid);
						$listAccountingAccount[$account->id]= $account;
					}
				}
				return $listAccountingAccount;
				break;
			case 'count':
				$sql = "SELECT COUNT(aa.rowid) as count FROM ".MAIN_DB_PREFIX.$this->table_accountingaccount." AS aa";
				$sql .= " WHERE aa.account_number LIKE '".(int) $startNumber."%'";
				$sql .= " AND aa.fk_pcg_version = '".$this->db->escape($pcgVersion)."'";
				$sql .= $this->db->order('aa.account_number', 'ASC');
				$res = $this->db->query($sql);
				if (!$res) {
					$this->error = $langs->trans('ErrorSQL');
					return -1;
				}

				$obj = $this->db->fetch_object($res);
				return $obj->count;
				break;
		}
	}

	/**
	 * Load an array with first level of accounting for $pcgVersion
	 *
	 * @param 	int 	$pcgVersion 		   Accounting system PCG version
	 * @return 	int|array                      Return integer <0 if KO, array if empty or found
	 */
	public function getAccountingAccountsFirstLevel($pcgVersion)
	{
		global $langs;
		require_once DOL_DOCUMENT_ROOT.'/accountancy/class/accountingaccount.class.php';

		$sql = "SELECT aa.rowid FROM ".MAIN_DB_PREFIX.$this->table_accountingaccount." AS aa";
		$sql .= " WHERE aa.account_number IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')";
		$sql .= " AND aa.fk_pcg_version = '".$this->db->escape($pcgVersion)."'";
		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = $langs->trans('ErrorSQL');
			return -1;
		}
		$listAccountingAccount = array();
		if ($res->num_rows > 0) {
			while ($obj = $this->db->fetch_object($res)) {
				$account = new AccountingAccount($this->db);
				$account->fetch($obj->rowid);
				$listAccountingAccount[$account->account_number] = array(
					'id' => $account->id,
					'label' => $account->label,
					'labelshort' => $account->labelshort,
					'pcg_type' => $account->pcg_type,
					'nbitems' => $this->getAccountingAccountsByStartNumber($account->account_number, $pcgVersion, 'count'),
				);
			}
		}
		return $listAccountingAccount;
	}

	//
	public function getMaximumLengthForAccountingAccounts($pcgVersion)
	{
		$sql = "SELECT MAX(LENGTH(account_number)) as max FROM ".MAIN_DB_PREFIX.$this->table_accountingaccount;
		$sql .= " WHERE fk_pcg_version = '".$this->db->escape($pcgVersion)."'";
		$res = $this->db->query($sql);
		if (!$res) {
			$this->error = $langs->trans('ErrorSQL');
			return -1;
		}

		$obj = $this->db->fetch_object($res);
		if (($obj->max + 1) > $this->maximumDepth) {
			$this->maximumDepth = $obj->max + 1;
		}
		return $obj->max;
	}

	/**
	 * Return an array with account number in array key
	 *
	 * @param 	array 		$listAccountingAccounts 	Array to filter
	 * @return 	array       Return integer <0 if KO, array if empty or found
	 */
	public function makeListOfAccountingAccountsByAccountNumber(array $listAccountingAccounts) {

		$sortListOfAccountingAccounts = array();

		if (!empty($listAccountingAccounts)) {
			foreach ($listAccountingAccounts as $accountID => $account ) {
				$sortListOfAccountingAccounts[$account->account_number] = $account;
			}
		}

		return $sortListOfAccountingAccounts;
	}

	/**
	 * Recursive function to show accounting accounts list
	 *
	 * @param 	array 		$listAccountingAccounts 	Array to filter
	 * @return 	int
	 */
	public function recursiveListAccountingAccounts($parentAccountNumber, $accountingAccountsList, $action, $level, $parentID, $pcgType)
	{
		global $langs;

		$level++;
		$startwith = (int) $parentAccountNumber[0];

		$html = '';

		for ($i=0; $i < 10; $i++) {

			$current = $parentAccountNumber.$i;
			$nbX = $this->maximumDepth - strlen($current);
			$showX = $this->showX($nbX);

			$accountExist = false;
			if (isset($accountingAccountsList[$current])) {
				$currentAccount = $accountingAccountsList[$current];
				$accountExist = true;
			}

			$editLine = false;
			if ($action == 'editaccount' && GETPOST('accountnumber', 'alpha') == $current) {
				$editLine = true;
			}

			//
			$colorExistOrNot = $accountExist ? '#681bb5' : '#ccc';
			$dataLabel = $accountExist ? $currentAccount->label : '';

			$html .= '<li>';
				//$html .= $editLine ? '<form class="ezcompta-editline-form" method="POST" action="'.$_SERVER['PHP_SELF'].'?startwith='.$startwith.'">' : '';
				$html .= '<form class="ezcompta-editline-form flexline" method="POST" action="'.$_SERVER['PHP_SELF'].'?startwith='.$startwith.'" id="line-'.$current.'" data-label="'.$dataLabel.'" data-level="'.$level.'" data-parent="'.$parentID.'" data-accountexist="'.$accountExist.'" data-pcgtype="'.$pcgType.'">';
					$html .= '<input type="hidden" name="action" value="editaccountingaccountlabel">';
					$html .= '<input type="hidden" name="token" value="'.newToken().'">';
					$html .= '<input type="hidden" name="accountnumber" value="'.$current.'">';
					if ($editLine) {
						$html .= '<div class="flexline-infos">';
							$html .= '<span class="toggle-icon">';
							if ($accountExist && $currentAccount->active) {
								$html .= '<a class="reposition toggle-account" href="'.$_SERVER['PHP_SELF'].'?id='.$currentAccount->id.'&action=disable&mode=0&token='.newToken().'" data-toggle="disable" data-target="'.$current.'" data-accountid="'.$currentAccount->id.'">';
									$html .= '<span class="fas fa-toggle-on paddingright" style="color: #681bb5;font-size: 1em;"></span>';
								$html .= '</a>';
							} else if ($accountExist && !$currentAccount->active) {
								$html .= '<a class="reposition toggle-account" href="'.$_SERVER['PHP_SELF'].'?id='.$currentAccount->id.'&action=enable&mode=0&token='.newToken().'" data-toggle="enable" data-target="'.$current.'" data-accountid="'.$currentAccount->id.'">';
									$html .= '<span class="fas fa-toggle-off paddingright" style="color: #ccc;font-size: 1em;"></span>';
								$html .= '</a>';
							} else {
								$html .= '<span class="fas fa-toggle-off paddingright" style="color: #ccc;font-size: 1em;"></span>';
							}
							$html .= '</span>';
							$html .= ' <b>'.$current.'<span class="opacitymedium">'.$showX.'</span></b> - ';
							$html .= '<span class="account-label"><input type="text" name="accountlabel" value="'.$currentAccount->label.'" class="minwidth500 input-nobottom"></span>';
						$html .= '</div>';
						$html .= '<div class="flexline-actions">';
							$html .= '<a class="button-editline cancel" href="'.$_SERVER['PHP_SELF'].'" data-target="'.$current.'"><span class="fas fa-times paddingright"></span></a> ';
							$html .= '<button class="button-editline" type="submit" data-target="'.$current.'"><span class="fas fa-check paddingleft"></span></button>';
						$html .= '</div>';
					} else {
						$html .= '<div class="flexline-infos">';
							$html .= '<span class="toggle-icon">';
							if ($accountExist && $currentAccount->active) {
								$html .= '<a class="reposition toggle-account" href="'.$_SERVER['PHP_SELF'].'?startwith='.$startwith.'&id='.$currentAccount->id.'&action=disable&mode=0&token='.newToken().'" data-toggle="disable" data-target="'.$current.'" data-accountid="'.$currentAccount->id.'">';
									$html .= '<span class="fas fa-toggle-on paddingright" style="color: #681bb5;font-size: 1em;"></span>';
								$html .= '</a>';
							} else if ($accountExist && !$currentAccount->active) {
								$html .= '<a class="reposition toggle-account" href="'.$_SERVER['PHP_SELF'].'?startwith='.$startwith.'&id='.$currentAccount->id.'&action=enable&mode=0&token='.newToken().'" data-toggle="enable" data-target="'.$current.'" data-accountid="'.$currentAccount->id.'">';
									$html .= '<span class="fas fa-toggle-off paddingright" style="color: #ccc;font-size: 1em;"></span>';
								$html .= '</a>';
							} else if (!$accountExist) {
								$html .= '<span class="fas fa-toggle-off paddingright" style="color: #ccc;font-size: 1em;"></span>';
							}
							$html .= '</span>';
							$html .= ' <b>'.$current.'<span class="opacitymedium">'.$showX.'</span></b> - ';
							$accountLabel = $accountExist ? $currentAccount->label : '<span class="opacitymedium">'.$langs->trans('AccountingAccountNotExist').'</span>';
							$html .= '<span class="account-label">'.$accountLabel.'</span>';
							$html .= '<a class="edit-account-label" href="'.$_SERVER['PHP_SELF'].'?action=editaccount&accountnumber='.$current.'&token='.newToken().'" data-target="'.$current.'"><span class="fas fa-pen" style="margin-left:12px;opacity:0.1"></span></a>';
						$html .= '</div>';
						$html .= '<div class="flexline-actions">';
							if ($accountExist) {
								$html .= '<span class="fas fa-ellipsis-v paddingright paddingleft moreactions"></span>';
								$html .= '<span class="fas fa-chevron-down paddingleft toggleview" data-target="'.$current.'" style="margin-left:12px;"></span>';
							}
						$html .= '</div>';
					}
				$html .= '</form>';
				//$html .= $editLine ? '</form>' : '';
				if ($level <= $this->maximumDepth && $accountExist) {
					$html .= '<ul class="subaccountlist level-'.$level.'" id="subaccountlist-'.$current.'">';
						$dataParent = $currentAccount->id;
						$html .= $this->recursiveListAccountingAccounts($currentAccount->account_number, $accountingAccountsList, $action, $level, $dataParent, $pcgType);
					$html .= '</ul>';
				}
			$html .= '</li>';
		}
		return $html;
	}

	public function showX($nb){
		$x = '';
		for ($i=0; $i < $nb; $i++) {
			$x .= 'X';
		}
		return $x;
	}
}