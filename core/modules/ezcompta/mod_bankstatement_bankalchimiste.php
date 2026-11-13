<?php
/* Copyright (C) 2003-2007	Rodolphe Quiedeville		<rodolphe@quiedeville.org>
 * Copyright (C) 2004-2007	Laurent Destailleur			<eldy@users.sourceforge.net>
 * Copyright (C) 2005-2009	Regis Houssin				<regis.houssin@inodbox.com>
 * Copyright (C) 2008		Raphael Bertrand (Resultic) <raphael.bertrand@resultic.fr>
 * Copyright (C) 2019-2024	Frédéric France				<frederic.france@free.fr>
 * Copyright (C) 2024		MDW							<mdeweerd@users.noreply.github.com>
 * Copyright (C) 2026		Florian Henry				<florian.henry@scopen.fr>
 * Copyright (C) 2026		Laurent MAGNIN				<laurent.magnin@evarisk.com>
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
 * or see https://www.gnu.org/
 */

/**
 * \file       htdocs/custom/ezcompta/core/modules/ezcompta/mod_bankstatement_bankalchimiste.php
 * \ingroup    ezcompta
 * \brief      File containing class for Bankalchimiste numbering model of BankStatement
 */

dol_include_once('/ezcompta/core/modules/ezcompta/modules_bankstatement.php');


/**
 *	Class to manage Bankalchimiste (advanced mask) numbering rule for BankStatement
 */
class mod_bankstatement_bankalchimiste extends ModeleNumRefBankStatement
{
	/**
	 * Dolibarr version of the loaded document
	 * @var string
	 */
	public $version = 'dolibarr';

	/**
	 * @var string Error message
	 */
	public $error = '';

	/**
	 * @var string name
	 */
	public $name = 'Bankalchimiste';


	/**
	 *	Returns the description of the numbering model
	 *
	 *	@param	Translate	$langs	Translate Object
	 *	@return	string				Text with description
	 */
	public function info($langs)
	{
		global $db;

		$langs->loadLangs(array("bills", "admin", "ezcompta@ezcompta"));

		$form = new Form($db);

		$defaultmask = 'BK{yy}{mm}-{0000}';
		$currentmask = getDolGlobalString('EZCOMPTA_BANKSTATEMENT_MASK', $defaultmask);

		$text = '<span class="badge badge-info">'.$langs->trans("Advanced").'</span> '.$langs->trans("BankStatementModelBankalchimisteDesc")."<br>\n";
		$text .= '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
		$text .= '<input type="hidden" name="token" value="'.newToken().'">';
		$text .= '<input type="hidden" name="action" value="updateMask">';
		$text .= '<input type="hidden" name="maskconst" value="EZCOMPTA_BANKSTATEMENT_MASK">';
		$text .= '<table class="nobordernopadding centpercent marginbottomonly">';

		$tooltip = $langs->trans("GenericMaskCodes", $langs->transnoentities("BankStatement"), $langs->transnoentities("BankStatement"));
		$tooltip .= $langs->trans("GenericMaskCodes2");
		$tooltip .= $langs->trans("GenericMaskCodes3");
		$tooltip .= $langs->trans("GenericMaskCodes4a", $langs->transnoentities("BankStatement"), $langs->transnoentities("BankStatement"));
		$tooltip .= $langs->trans("GenericMaskCodes5");
		$tooltip .= '<br>'.$langs->trans("GenericMaskCodes5b");

		// prefix configuration
		$text .= '<tr><td>'.$langs->trans("Mask").':</td>';
		$text .= '<td class="right">'.$form->textwithpicto('<input type="text" class="flat minwidth175" name="maskvalue" value="'.dol_escape_htmltag($currentmask).'">', $tooltip, 1, 1).'</td>';
		$text .= '<td class="left" rowspan="2">&nbsp; <input type="submit" class="button button-edit" value="'.$langs->trans("Modify").'" name="Button"></td>';
		$text .= '</tr>';

		$text .= '</table>';
		$text .= '</form>';

		return $text;
	}

	/**
	 *	Return an example of numbering
	 *
	 *	@return	string		Example
	 */
	public function getExample()
	{
		global $db, $langs;

		$langs->loadLangs(array("bills", "admin", "ezcompta@ezcompta"));

		dol_include_once('/ezcompta/class/bankrecord.class.php');
		$object = new BankRecord($db);
		$object->initAsSpecimen();

		$numExample = $this->getNextValue($object);

		if (!$numExample) {
			$numExample = $langs->trans('NotConfigured');
		}
		return $numExample;
	}

	/**
	 * 	Return next free value
	 *
	 *  @param  BankStatement|BankRecord		$object		Object we need next value for
	 *  @return string|int<-1,0>							Next value if OK, <=0 if KO
	 */
	public function getNextValue($object)
	{
		global $db;

		require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

		// We get cursor rule
		$mask = getDolGlobalString('EZCOMPTA_BANKSTATEMENT_MASK', 'BK{yy}{mm}-{0000}');

		if (!$mask) {
			$this->error = 'NotConfigured';
			return 0;
		}

		$date = !empty($object->date_creation) ? $object->date_creation : (!empty($object->datec) ? $object->datec : dol_now());

		$numFinal = get_next_value($db, $mask, 'bank_record', 'ref', '', '', $date);

		return $numFinal;
	}
}
