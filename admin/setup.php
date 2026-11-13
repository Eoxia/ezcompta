<?php
/* Copyright (C) 2004-2017  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 * Copyright (C) ---Replace with your own copyright and developer email---
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    htdocs/modulebuilder/template/admin/setup.php
 * \ingroup ezcompta
 * \brief   Ezcompta setup page.
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"] . "/main.inc.php";
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
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)) . "/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1)) . "/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1))) . "/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/ezcompta.lib.php';
//require_once "../class/myclass.class.php";

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

// Translations
$langs->loadLangs(array("admin", "ezcompta@ezcompta"));

// Initialize a technical object to manage hooks of page. Note that conf->hooks_modules contains an array of hook context
/** @var HookManager $hookmanager */
$hookmanager->initHooks(array('ezcomptasetup', 'globalsetup'));

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$modulepart = GETPOST('modulepart', 'aZ09');    // Used by actions_setmoduleoptions.inc.php

$value = GETPOST('value', 'alpha');
$label = GETPOST('label', 'alpha');
$scandir = GETPOST('scan_dir', 'alpha');
$type = 'myobject';

$error = 0;
$setupnotempty = 0;

// Access control
if (!$user->admin) {
	accessforbidden();
}


// Set this to 1 to use the factory to manage constants. Warning, the generated module will be compatible with version v15+ only
$useFormSetup = 1;

if (!class_exists('FormSetup')) {
	require_once DOL_DOCUMENT_ROOT . '/core/class/html.formsetup.class.php';
}
$formSetup = new FormSetup($db);

// Access control
if (!$user->admin) {
	accessforbidden();
}


// Enter here all parameters in your setup page
$item = $formSetup->newItem('EZCOMPTA_BANKSTATEMENT_ALLOW_MANUAL_LINES');
$item->setAsYesNo();
$item->defaultFieldValue = '0';
//$item->fieldAttr['placeholder'] = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'];
//$item->cssClass = 'minwidth500';
//
//// Setup conf for selection of a simple string input
//$item = $formSetup->newItem('EZCOMPTA_MYPARAM2');
//$item->defaultFieldValue = 'default value';
//$item->fieldAttr['placeholder'] = 'A placeholder here';
//
//// Setup conf for selection of a simple textarea input but we replace the text of field title
//$item = $formSetup->newItem('EZCOMPTA_MYPARAM3');
//$item->nameText = $item->getNameText().' more html text ';
//
//// Setup conf for a selection of a Thirdparty
//$item = $formSetup->newItem('EZCOMPTA_MYPARAM4');
//$item->setAsThirdpartyType();
//
//// Setup conf for a selection of a boolean
//$formSetup->newItem('EZCOMPTA_MYPARAM5')->setAsYesNo();
//
//// Setup conf for a selection of an Email template of type thirdparty
//$formSetup->newItem('EZCOMPTA_MYPARAM6')->setAsEmailTemplate('thirdparty');
//
//// Setup conf for a selection of a secured key
////$formSetup->newItem('EZCOMPTA_MYPARAM7')->setAsSecureKey();
//
//// Setup conf for a selection of a Product
//$formSetup->newItem('EZCOMPTA_MYPARAM8')->setAsProduct();
//
//// Add a title for a new section
//$formSetup->newItem('NewSection')->setAsTitle();
//
//$TField = array(
//	'test01' => $langs->trans('test01'),
//	'test02' => $langs->trans('test02'),
//	'test03' => $langs->trans('test03'),
//	'test04' => $langs->trans('test04'),
//	'test05' => $langs->trans('test05'),
//	'test06' => $langs->trans('test06'),
//);
//
//// Setup conf for a simple combo list
//$formSetup->newItem('EZCOMPTA_MYPARAM9')->setAsSelect($TField);
//
//// Setup conf for a multiselect combo list
//$item = $formSetup->newItem('EZCOMPTA_MYPARAM10');
//$item->setAsMultiSelect($TField);
//$item->helpText = $langs->transnoentities('EZCOMPTA_MYPARAM10');
//
//// Setup conf for a category selection
//$formSetup->newItem('EZCOMPTA_CATEGORY_ID_XXX')->setAsCategory('product');
//
//// Setup conf EZCOMPTA_MYPARAM10
//$item = $formSetup->newItem('EZCOMPTA_MYPARAM10');
//$item->setAsColor();
//$item->defaultFieldValue = '#FF0000';
////$item->fieldValue = '';
////$item->fieldAttr = array() ; // fields attribute only for compatible fields like input text
////$item->fieldOverride = false; // set this var to override field output will override $fieldInputOverride and $fieldOutputOverride too
////$item->fieldInputOverride = false; // set this var to override field input
////$item->fieldOutputOverride = false; // set this var to override field output
//
//$item = $formSetup->newItem('EZCOMPTA_MYPARAM11')->setAsHtml();
//$item->nameText = $item->getNameText().' more html text ';
//$item->fieldInputOverride = '';
//$item->helpText = $langs->transnoentities('HelpMessage');
//$item->cssClass = 'minwidth500';
//
//$item = $formSetup->newItem('EZCOMPTA_MYPARAM12');
//$item->fieldOverride = "Value forced, can't be modified";
//$item->cssClass = 'minwidth500';

//$item = $formSetup->newItem('EZCOMPTA_MYPARAM13')->setAsDate();	// Not yet implemented


// Setup conf for selection of a simple string input
if (isModEnabled("banking4dolibarr")) {
	//$formSetup->newItem('Copy Data')->setAsTitle();
}

// End of definition of parameters


$setupnotempty += count($formSetup->items);


$dirmodels = array_merge(array('/', '/ezcompta/'), (array) (empty($conf->modules_parts['models']) ? array() : $conf->modules_parts['models']));

$moduledir = 'ezcompta';
$myTmpObjects = array();
$myTmpObjects['bankstatement'] = array(
	'label' => 'BankStatements',
	'includerefgeneration' => 1,
	'includedocgeneration' => 0,
	'class' => 'BankRecord'
);

$tmpobjectkey = GETPOST('object', 'aZ09');
if (empty($tmpobjectkey)) {
	$tmpobjectkey = 'bankstatement';
}
if ($tmpobjectkey && !array_key_exists($tmpobjectkey, $myTmpObjects)) {
	accessforbidden('Bad value for object. Hack attempt ?');
}


/*
 * Actions
 */

// For retrocompatibility Dolibarr < 15.0
if (versioncompare(explode('.', DOL_VERSION), array(15)) < 0 && $action == 'update' && !empty($user->admin)) {
	$formSetup->saveConfFromPost();
}

include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

if ($action == 'updateMask') {
	$maskconst = GETPOST('maskconst', 'aZ09');
	$maskvalue = GETPOST('maskvalue', 'alpha');

	if ($maskconst && preg_match('/_MASK$/', $maskconst)) {
		$res = dolibarr_set_const($db, $maskconst, $maskvalue, 'chaine', 0, '', $conf->entity);
		if (!($res > 0)) {
			$error++;
		}
	}

	if (!$error) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
} elseif ($action == 'setmod') {
	if (!empty($tmpobjectkey)) {
		$constforval = 'EZCOMPTA_'.strtoupper($tmpobjectkey)."_ADDON";
		$res = dolibarr_set_const($db, $constforval, $value, 'chaine', 0, '', $conf->entity);
		if (!($res > 0)) {
			$error++;
		}
	}
	if (!$error) {
		setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	} else {
		setEventMessages($langs->trans("Error"), null, 'errors');
	}
}

$action = 'edit';


/*
 * View
 */

$form = new Form($db);

$help_url = '';
$title = "EzcomptaSetup";

llxHeader('', $langs->trans($title), $help_url, '', 0, 0, '', '', '', 'mod-ezcompta page-admin');

// Subheader
$linkback = '<a href="' . ($backtopage ? $backtopage : DOL_URL_ROOT . '/admin/modules.php?restore_lastsearch_values=1') . '">' . $langs->trans("BackToModuleList") . '</a>';

print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

// Configuration header
$head = ezcomptaAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($title), -1, "ezcompta@ezcompta");

// Setup page goes here
if (!empty($formSetup->items)) {
	print $formSetup->generateOutput(true);
	print '<br>';
}

foreach ($myTmpObjects as $myTmpObjectKey => $myTmpObjectArray) {
	if ($myTmpObjectArray['includerefgeneration']) {
		/*
		 * Numbering model
		 */
		print load_fiche_titre($langs->trans("BankStatementNumberingModules"), '', '');

		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<td>'.$langs->trans("Name").'</td>';
		print '<td>'.$langs->trans("Description").'</td>';
		print '<td class="nowrap">'.$langs->trans("Example").'</td>';
		print '<td class="center" width="60">'.$langs->trans("Status").'</td>';
		print '<td class="center" width="16">'.$langs->trans("ShortInfo").'</td>';
		print '</tr>'."\n";

		clearstatcache();

		dol_include_once('/ezcompta/class/bankrecord.class.php');

		$scanned_models = array();

		foreach ($dirmodels as $reldir) {
			$dir = dol_buildpath($reldir."core/modules/".$moduledir);

			if (is_dir($dir)) {
				$handle = opendir($dir);
				if (is_resource($handle)) {
					while (($file = readdir($handle)) !== false) {
						if (strpos($file, 'mod_'.strtolower($myTmpObjectKey).'_') === 0 && substr($file, dol_strlen($file) - 4, 4) == '.php') {
							$file = substr($file, 0, dol_strlen($file) - 4);

							if (in_array($file, $scanned_models)) {
								continue;
							}
							$scanned_models[] = $file;

							require_once $dir.'/'.$file.'.php';

							$module = new $file($db);
							'@phan-var-force ModeleNumRefBankStatement $module';

							// Show modules according to feature level
							if ($module->version == 'development' && getDolGlobalInt('MAIN_FEATURES_LEVEL') < 2) {
								continue;
							}
							if ($module->version == 'experimental' && getDolGlobalInt('MAIN_FEATURES_LEVEL') < 1) {
								continue;
							}

							if ($module->isEnabled()) {
								print '<tr class="oddeven"><td>'.$module->name."</td><td>\n";
								print $module->info($langs);
								print '</td>';

								// Show example of the numbering model
								print '<td class="nowrap">';
								$tmp = $module->getExample();
								if (preg_match('/^Error/', $tmp)) {
									$langs->load("errors");
									print '<div class="error">'.$langs->trans($tmp).'</div>';
								} elseif ($tmp == 'NotConfigured') {
									print $langs->trans($tmp);
								} else {
									print $tmp;
								}
								print '</td>'."\n";

								print '<td class="center">';
								$constforvar = 'EZCOMPTA_'.strtoupper($myTmpObjectKey).'_ADDON';
								$defaultifnotset = 'mod_bankstatement_bankomatik';
								$activenumberingmodel = getDolGlobalString($constforvar, $defaultifnotset);
								if ($activenumberingmodel == 'mod_bankstatement_standard') {
									$activenumberingmodel = 'mod_bankstatement_bankomatik';
									dolibarr_set_const($db, $constforvar, $activenumberingmodel, 'chaine', 0, '', $conf->entity);
								} elseif ($activenumberingmodel == 'mod_bankstatement_free') {
									$activenumberingmodel = 'mod_bankstatement_bankanarchie';
									dolibarr_set_const($db, $constforvar, $activenumberingmodel, 'chaine', 0, '', $conf->entity);
								} elseif ($activenumberingmodel == 'mod_bankstatement_advanced') {
									$activenumberingmodel = 'mod_bankstatement_bankalchimiste';
									dolibarr_set_const($db, $constforvar, $activenumberingmodel, 'chaine', 0, '', $conf->entity);
								}
								if ($activenumberingmodel == $file) {
									print img_picto($langs->trans("Activated"), 'switch_on');
								} else {
									print '<a href="'.$_SERVER["PHP_SELF"].'?action=setmod&token='.newToken().'&object='.strtolower($myTmpObjectKey).'&value='.urlencode($file).'">';
									print img_picto($langs->trans("Disabled"), 'switch_off');
									print '</a>';
								}
								print '</td>';

								$className = $myTmpObjectArray['class'];
								$mytmpinstance = new $className($db);
								$mytmpinstance->initAsSpecimen();

								// Info
								$htmltooltip = '';
								$htmltooltip .= ''.$langs->trans("Version").': <b>'.$module->getVersion().'</b><br>';

								$nextval = $module->getNextValue($mytmpinstance);
								if ("$nextval" != $langs->trans("NotAvailable")) {  // Keep " on nextval
									$htmltooltip .= ''.$langs->trans("NextValue").': ';
									if ($nextval) {
										if (preg_match('/^Error/', $nextval) || $nextval == 'NotConfigured') {
											$nextval = $langs->trans($nextval);
										}
										$htmltooltip .= $nextval.'<br>';
									} else {
										$htmltooltip .= $langs->trans($module->error ? $module->error : 'None').'<br>';
									}
								}

								print '<td class="center">';
								print $form->textwithpicto('', $htmltooltip, 1, 'info');
								print '</td>';

								print "</tr>\n";
							}
						}
					}
					closedir($handle);
				}
			}
		}
		print "</table><br>\n";
	}
}

if (empty($setupnotempty) && empty($myTmpObjects)) {
	print '<br>' . $langs->trans("NothingToSetup");
}

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
