<?php
/* Copyright (C) 2008-2012	Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2008-2012	Regis Houssin		<regis.houssin@inodbox.com>
 * Copyright (C) 2014		Juanjo Menent		<jmenent@2byte.es>
 * Copyright (C) 2017		Rui Strecht			<rui.strecht@aliartalentos.com>
 * Copyright (C) 2020       Open-Dsi         	<support@open-dsi.fr>
 * Copyright (C) 2024       Frédéric France             <frederic.france@free.fr>
 * Copyright (C) 2024		MDW							<mdeweerd@users.noreply.github.com>
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
 *    \file       ezcompta/class/html.ezcompta.class.php
 *  \ingroup    ezcompta
 *    \brief      File of class to build HTML component
 */


/**
 *    Class to build HTML component for third parties management
 *    Only common components are here.
 */

require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';


/**
 * Class of forms component to manage companies
 */
class FormEzCompta extends Form
{
	public function selectBankAccounts($selected = [], $htmlname = 'bankaccounts')
	{

		global $langs;
		dol_syslog(get_class($this) . __METHOD__, LOG_DEBUG);

		$options = $this->getBankAccounts();
		if (empty($selected)) {
			$selected = array_keys($options);
		}
		print Form::multiselectarray($htmlname, $options, $selected, 0, 0, 'minwidth300');
	}

	public function getBankAccounts()
	{
		dol_syslog(get_class($this) . __METHOD__, LOG_DEBUG);

		$options = array();
		$sql = "SELECT b.rowid, b.label";
		$sql .= " FROM " . $this->db->prefix() . "bank_account as b";
		$sql .= " WHERE b.clos = 0";
		$sql .= " AND b.entity IN (" . getEntity('bank_account') . ")";
		$sql .= " ORDER BY b.label";
		$resql = $this->db->query($sql);
		if ($resql) {

			while ($obj = $this->db->fetch_object($resql)) {
				$options[$obj->rowid] = $obj->label;
			}

		} else {
			dol_print_error($this->db);
		}

		return $options;
	}
}
