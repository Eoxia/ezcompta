<?php
/* Copyright (C) 2026 Laurent MAGNIN <laurent.magnin@evarisk.com>
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
 * \file       htdocs/custom/ezcompta/core/modules/ezcompta/mod_bankstatement_bankanarchie.php
 * \ingroup    ezcompta
 * \brief      File containing class for Bankanarchie (free/manual) numbering model of BankStatement
 */

dol_include_once('/ezcompta/core/modules/ezcompta/modules_bankstatement.php');

/**
 * Class to manage Bankanarchie (free/manual) numbering for BankStatement
 */
class mod_bankstatement_bankanarchie extends ModeleNumRefBankStatement
{
	/**
	 * Dolibarr version of the loaded document
	 * @var string
	 */
	public $version = 'dolibarr';

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var string Name
	 */
	public $name = 'Bankanarchie';

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->code_null = 0;
		$this->code_modifiable = 1;
		$this->code_modifiable_invalide = 1;
		$this->code_modifiable_null = 1;
		$this->code_auto = 0;
	}

	/**
	 * Return description of numbering module
	 *
	 * @param  Translate $langs Translate Object
	 * @return string          Text with description
	 */
	public function info($langs)
	{
		global $langs;
		$langs->loadLangs(array("admin", "ezcompta@ezcompta"));
		return '<span class="badge badge-secondary">'.$langs->trans("Free").'</span> '.$langs->trans("BankStatementModelBankanarchieDesc");
	}

	/**
	 * Return an example of numbering
	 *
	 * @return string Example
	 */
	public function getExample()
	{
		global $langs;
		$langs->loadLangs(array("admin", "ezcompta@ezcompta"));
		return $langs->trans("Free");
	}

	/**
	 * Checks if the numbers already in the database do not cause conflicts
	 *
	 * @param  CommonObject $object Object we need next value for
	 * @return bool                 True if ok
	 */
	public function canBeActivated($object)
	{
		return true;
	}

	/**
	 * Return next free value
	 *
	 * @param  BankStatement|BankRecord $object Object we need next value for
	 * @return string                          Next value if OK, <=0 if KO
	 */
	public function getNextValue($object)
	{
		return '';
	}
}
