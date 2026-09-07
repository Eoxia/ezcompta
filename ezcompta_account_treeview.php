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

//if (! defined('NOREQUIREDB'))              define('NOREQUIREDB', '1');				// Do not create database handler $db
//if (! defined('NOREQUIREUSER'))            define('NOREQUIREUSER', '1');				// Do not load object $user
//if (! defined('NOREQUIRESOC'))             define('NOREQUIRESOC', '1');				// Do not load object $mysoc
//if (! defined('NOREQUIRETRAN'))            define('NOREQUIRETRAN', '1');				// Do not load object $langs
//if (! defined('NOSCANGETFORINJECTION'))    define('NOSCANGETFORINJECTION', '1');		// Do not check injection attack on GET parameters
//if (! defined('NOSCANPOSTFORINJECTION'))   define('NOSCANPOSTFORINJECTION', '1');		// Do not check injection attack on POST parameters
//if (! defined('NOCSRFCHECK'))              define('NOCSRFCHECK', '1');				// Do not check CSRF attack (test on referer + on token if option MAIN_SECURITY_CSRF_WITH_TOKEN is on).
//if (! defined('NOTOKENRENEWAL'))           define('NOTOKENRENEWAL', '1');				// Do not roll the Anti CSRF token (used if MAIN_SECURITY_CSRF_WITH_TOKEN is on)
//if (! defined('NOSTYLECHECK'))             define('NOSTYLECHECK', '1');				// Do not check style html tag into posted data
if (! defined('NOREQUIREMENU'))            define('NOREQUIREMENU', '1');				// If there is no need to load and show top and left menu
//if (! defined('NOREQUIREHTML'))            define('NOREQUIREHTML', '1');				// If we don't need to load the html.form.class.php
//if (! defined('NOREQUIREAJAX'))            define('NOREQUIREAJAX', '1');       	  	// Do not load ajax.lib.php library
//if (! defined("NOLOGIN"))                  define("NOLOGIN", '1');					// If this page is public (can be called outside logged session). This include the NOIPCHECK too.
//if (! defined('NOIPCHECK'))                define('NOIPCHECK', '1');					// Do not check IP defined into conf $dolibarr_main_restrict_ip
//if (! defined("MAIN_LANG_DEFAULT"))        define('MAIN_LANG_DEFAULT', 'auto');					// Force lang to a particular value
//if (! defined("MAIN_AUTHENTICATION_MODE")) define('MAIN_AUTHENTICATION_MODE', 'aloginmodule');	// Force authentication handler
//if (! defined("NOREDIRECTBYMAINTOLOGIN"))  define('NOREDIRECTBYMAINTOLOGIN', 1);		// The main.inc.php does not make a redirect if not logged, instead show simple error message
//if (! defined("FORCECSP"))                 define('FORCECSP', 'none');				// Disable all Content Security Policies
//if (! defined('CSRFCHECK_WITH_TOKEN'))     define('CSRFCHECK_WITH_TOKEN', '1');		// Force use of CSRF protection with tokens even for GET
//if (! defined('NOBROWSERNOTIF'))     		 define('NOBROWSERNOTIF', '1');				// Disable browser notification

$res=0;
if (! $res && file_exists("../main.inc.php")): $res=@include '../main.inc.php'; endif;
if (! $res && file_exists("../../main.inc.php")): $res=@include '../../main.inc.php'; endif;

// Protection if external user
if ($user->socid > 0): accessforbidden(); endif;

require_once DOL_DOCUMENT_ROOT.'/accountancy/class/accountancysystem.class.php';
require_once DOL_DOCUMENT_ROOT.'/accountancy/class/accountingaccount.class.php';
dol_include_once('/ezcompta/class/ezcompta.class.php');

$langs->load('ezcompta@ezcompta');


/*******************************************************************
* VARIABLES
********************************************************************/
$action = GETPOST('action');
$startwith = GETPOSTINT('startwith') ? : 1;
$id = GETPOSTINT('id');
$accountnumber = GETPOST('accountnumber', 'alpha');

$accountancySystem = new AccountancySystem($db);
$accountancySystem->fetch(getDolGlobalInt('CHARTOFACCOUNTS'));

$ezcompta = new EzCompta($db);
$accounting = new AccountingAccount($db);

// Get unordered list of accounting accounts
$list = $ezcompta->getAccountingAccountsByStartNumber($startwith, $accountancySystem->pcg_version);

// Sort List
$listorder = $ezcompta->makeListOfAccountingAccountsByAccountNumber($list);

// Get First Level Accounting Accounts label
$firstLevel = $ezcompta->getAccountingAccountsFirstLevel($accountancySystem->pcg_version);

// The chart of accounts does not always start at 1: fall back on its first group instead of
// dereferencing a missing key on every line of the page
if (!empty($firstLevel) && !isset($firstLevel[$startwith])) {
	$startwith = (int) array_key_first($firstLevel);
	$list = $ezcompta->getAccountingAccountsByStartNumber($startwith, $accountancySystem->pcg_version);
	$listorder = $ezcompta->makeListOfAccountingAccountsByAccountNumber($list);
}

// A chart of accounts without any one digit account (a custom plan for instance) leaves no group
// to display: keep the page readable instead of dereferencing a missing entry on every line
$currentGroup = isset($firstLevel[$startwith]) ? $firstLevel[$startwith] : array('id' => 0, 'label' => '', 'labelshort' => '', 'pcg_type' => '', 'nbitems' => 0);

// Get maximum length for this accounting system
$maxNumberAccountingAccounts = $ezcompta->getMaximumLengthForAccountingAccounts($accountancySystem->pcg_version);

//
$permissiontoadd = $user->hasRight('accounting', 'chartofaccount');
$permissiontodelete = $user->hasRight('accounting', 'chartofaccount');

/*******************************************************************
* ACTIONS
********************************************************************/

if ($action == 'editaccountingaccountlabel') {
	var_dump($_POST);
}

// Disable accounting account
if ($action == 'disable' && $permissiontoadd) {
	if ($accounting->fetch($id)) {
		$mode = GETPOSTINT('mode');
		$result = $accounting->accountDeactivate($id, $mode);
		if ($result < 0) {
			setEventMessages($accounting->error, $accounting->errors, 'errors');
		}
		header('Location:'.$_SERVER['PHP_SELF'].'?startwith='.$startwith);
		exit();
	}

	$action = 'update';
} elseif ($action == 'enable' && $permissiontoadd) {
	if ($accounting->fetch($id)) {
		$mode = GETPOSTINT('mode');
		$result = $accounting->accountActivate($id, $mode);
		if ($result < 0) {
			setEventMessages($accounting->error, $accounting->errors, 'errors');
		}
		header('Location:'.$_SERVER['PHP_SELF'].'?startwith='.$startwith);
		exit();
	}
}

/*if (!empty($accountnumber)) {
	$expl = strlen($accountnumber);
	var_dump($expl);
}*/

/***************************************************
* VIEW
****************************************************/
$js_array = array();
$css_array = array('/ezcompta/assets/css/ezcompta.css');
llxHeader('', $langs->trans('EzCompta'), '', '', 0, 0, $js_array, $css_array, '', 'mod-ezcompta page-index');

print '<div id="ezcompta-main-wrapper">';
	print '<div class="ezcompta-side-wrapper">';
		print '<form action="#" method="POST">';
			print '<div class="searchicon"><span class="fas fa-search"></span></div>';
			print '<input type="text" name="" placeholder="'.$langs->trans('Search').'">';
		print '</form>';
		print '<ul>';
		for ($i=1; $i < 10; $i++) {
			$existFolder = false;
			if (isset($firstLevel[$i])) {
				$existFolder = true;
				$group = $firstLevel[$i];
				$groupclass = ($startwith == $i) ? 'active' : '';
				$grouplabel = (!empty($group['labelshort'])) ? $group['labelshort'] : $group['label'];

				print '<li>';
					print '<a href="'.$_SERVER['PHP_SELF'].'?startwith='.$i.'" class="'.$groupclass.'"><span class="fas fa-folder paddingright"></span> '.$i.'. '.$grouplabel.' <span class="badge marginleftonlyshort">'.$group['nbitems'].'</span></a>';
				print '</li>';
			}		}
		print '</ul>';
	print '</div>';
	print '<div class="ezcompta-content-wrapper">';
		print '<h1>'.$startwith.'. '.$currentGroup['label'].'</h1>';
		if (empty($firstLevel)) {
			print '<div class="warning">'.$langs->trans('EzComptaNoFirstLevelAccount', $accountancySystem->pcg_version).'</div>';
		}
		print '<p class="opacitymedium">Description de cette partie comptable, unde Rufinus ea tempestate praefectus praetorio ad discrimen trusus est ultimum. ire enim ipse compellebatur ad militem, quem exagitabat inopia simul et feritas, et alioqui coalito more in ordinarias dignitates asperum semper et saevum, ut satisfaceret atque monstraret, quam ob causam annonae convectio sit impedita.</p>';

		$level = 2;
		print '<ul class="ezcompta-listaccount">';
		for ($i=0; $i < 10; $i++) {

			$current = $startwith.$i;
			$accountExist = false;
			$dataLabel = '';
			//$dataParent = ;
			if (isset($listorder[$current])) {
				$accountExist = true;
				$currentAccount = $listorder[$current];
				$dataLabel = $currentAccount->label;
			}

			$editLine = ($action == 'editaccount' && $accountnumber == $current) ? true : false;
			$nbX = $ezcompta->maximumDepth - strlen($current);

			print '<li>';
				print '<form class="ezcompta-editline-form account-cardline-header" method="POST" action="'.$_SERVER['PHP_SELF'].'?startwith='.$startwith.'" id="line-'.$current.'" data-label="'.$dataLabel.'" data-level="'.$level.'" data-parent="'.$currentGroup['id'].'" data-accountexist="'.($accountExist ? 1 : 0).'" data-pcgtype="'.$currentGroup['pcg_type'].'">';
				if ($editLine) {
					print '<div class="cardline-header-title">';
						print '<span class="fas fa-clipboard-list paddingright"></span> <b>'.$current.'<span class="opacitymedium">'.$ezcompta->showX($nbX).'</span></b> - ';
						print '<span class="account-label"><input type="text" name="accountlabel" value="'.$currentAccount->label.'" class="minwidth500 input-nobottom"></span>';
					print '</div>';
					print '<div class="cardline-header-actions">';
						print '<input type="hidden" name="action" value="editaccountingaccountlabel">';
						print '<input type="hidden" name="token" value="'.newToken().'">';
						print '<input type="hidden" name="accountnumber" value="'.$current.'">';
						print '<a class="button-editline cancel" href="'.$_SERVER['PHP_SELF'].'?startwith='.$startwith.'" data-target="'.$current.'"><span class="fas fa-times paddingright"></span></a> ';
						print '<button class="button-editline" type="submit"  data-target="'.$current.'"><span class="fas fa-check paddingleft"></span></button>';
					print '</div>';
				} else {
					print '<div class="cardline-header-title">';
						print '<span class="toggle-icon">';
							if ($accountExist && $currentAccount->active) {
								print '<a class="reposition toggle-account" href="'.$_SERVER['PHP_SELF'].'?id='.$currentAccount->id.'&action=disable&mode=0&token='.newToken().'" data-toggle="disable" data-target="'.$current.'" data-accountid="'.$currentAccount->id.'">';
									print '<span class="fas fa-toggle-on paddingright" style="color: #681bb5;font-size: 1em;"></span>';
								print '</a>';
							} else if ($accountExist && !$currentAccount->active) {
								print '<a class="reposition toggle-account" href="'.$_SERVER['PHP_SELF'].'?id='.$currentAccount->id.'&action=enable&mode=0&token='.newToken().'" data-toggle="enable" data-target="'.$current.'" data-accountid="'.$currentAccount->id.'">';
									print '<span class="fas fa-toggle-off paddingright" style="color: #ccc;font-size: 1em;"></span>';
								print '</a>';
							} else {
								print '<span class="fas fa-toggle-off paddingright" style="color: #ccc;font-size: 1em;"></span>';
							}
							//print '<span class="fas fa-clipboard-list paddingright"></span> ';
						print '</span>';
						print '<b>'.$current.'<span class="opacitymedium">'.$ezcompta->showX($nbX).'</span></b> - ';
						print '<span class="account-label">';
							if($accountExist) {
								print $currentAccount->label;
							} else {
								print '<span class="opacitymedium">'.$langs->trans('AccountingAccountNotExist').'</span>';
							}
						print '</span>';
						print '<a href="'.$_SERVER['PHP_SELF'].'?action=editaccount&accountnumber='.$current.'&token='.newToken().'"><span class="fas fa-pen edit-account-label" data-target="'.$current.'" style="margin-left:12px;color:#ddd;"></span></a>';
					print '</div>';
					print '<div class="cardline-header-actions">';
						if ($accountExist) {
							print '<span class="fas fa-ellipsis-v paddingright paddingleft"></span>';
							print '<span class="fas fa-chevron-down paddingleft toggleview" data-target="'.$current.'" style="margin-left:12px;"></span>';
						}
					print '</div>';
				}
				print '</form>';
				print '<ul class="firstlevel" id="subaccountlist-'.$current.'">';
					$dataParent = $accountExist ? $currentAccount->id : 0;
					if ($accountExist) {
						print $ezcompta->recursiveListAccountingAccounts($current, $listorder, $action, $level, $dataParent, $currentGroup['pcg_type']);
					}
				print '</ul>';
			//print '</form>';
			print '</li>';
		}
		print '</ul>';
	print '</div>';
	/*print '<div style="position:fixed;top:0;right:0;bottom:0;background:#25f;width:520px;box-sizing:border-box;padding:24px 42px;">';
		print '<h2>XXXXXX - Nom du compte</h2>';
		print '<div>Label: LABEL</div>';
		print '<div>LabelShort: LABELSHORT</div>';
		print '<div>CompteParent: COMPTEPARENT</div>';
		print '<div>GroupeComptable: GroupeComptable</div>';
		print '<div>GroupePerso: GroupePerso</div>';
	print '</div>';*/
print '</div>';
?>

<script type="text/javascript" nonce="<?php echo getNonce(); ?>">
	$(document).ready(function(){

		var startwith = '<?php echo $startwith; ?>';
		var token = '<?php echo newToken(); ?>';
		var link = '<?php echo dol_buildpath('ezcompta/ezcompta_account_treeview.php?startwith='.$startwith, 1); ?>';
		var emptylabel = '<span class="opacitymedium"><?php echo $langs->transnoentities('AccountingAccountNotExist'); ?></span>';

		// ENABLE - DISABLE
		$(document).on('click', '.toggle-account', function(e){
			e.preventDefault();

			var toggleaction = $(this).data('toggle');
			var accountid = $(this).data('accountid');
			var target = $(this).data('target');

			var toggle_on = '';
			toggle_on += '<a class="reposition toggle-account" href="'+link+'&id='+accountid+'&action=disable&mode=0&token='+token+'" data-toggle="disable" data-target="'+target+'" data-accountid="'+accountid+'">';
			toggle_on += '<span class="fas fa-toggle-on paddingright" style="color: #681bb5;font-size: 1em;"></span>';
			toggle_on += '</a>';

			var toggle_off = '';
			toggle_off += '<a class="reposition toggle-account" href="'+link+'&id='+accountid+'&action=enable&mode=0&token='+token+'" data-toggle="enable" data-target="'+target+'" data-accountid="'+accountid+'">';
			toggle_off += '<span class="fas fa-toggle-off paddingright" style="color: #ccc;font-size: 1em;"></span>';
			toggle_off += '</a>';

			var urlUpdate = "<?php echo dol_buildpath('ezcompta/ajax/accountingaccount_activate.php',1); ?>";
			$.ajax(
				urlUpdate,
				{
					async: true,
					method:'POST',
					dataType: "json",
					data: {
						token : '<?php echo newToken(); ?>',
						toggleaction : toggleaction,
						accountid : accountid,

					}
				}
			).done(function(data){
				if (data.success) {
					if (toggleaction === 'enable') {
						$('#line-' + target).find('.toggle-icon').html(toggle_on);
					} else if (toggleaction === 'disable') {
						$('#line-' + target).find('.toggle-icon').html(toggle_off);
					}
				}
			});
		});

		// EDIT
		$(document).on('click', '.edit-account-label', function(e){
			e.preventDefault();

			var target = $(this).data('target');
			var label = $('#line-'+target).data('label');
			var level = $('#line-'+target).data('level');
			var buttons = '<a class="button-editline cancel" data-target="'+target+'" href="'+link+'"><span class="fas fa-times paddingright"></span></a>';
			buttons += ' <button class="button-editline" type="submit" data-target="'+target+'"><span class="fas fa-check paddingleft"></span></button>';
			//
			$(this).remove();
			$('#line-'+target).find('.account-label').html('<input type="text" name="accountlabel" value="' + label + '" class="minwidth500 input-nobottom">');
			if (level == 2) {
				$('#line-'+target).find('.cardline-header-actions').html(buttons);
			} else {
				$('#line-'+target).find('.flexline-actions').html(buttons);
			}
		});

		// CANCEL EDIT
		$(document).on('click', '.button-editline.cancel', function(e) {
			e.preventDefault();
			var target = $(this).data('target');
			var label = $('#line-'+target).data('label');
			var level = $('#line-'+target).data('level');
			var accountexist = $('#line-'+target).data('accountexist');

			var buttons = '';
			if (accountexist) {
				buttons += '<span class="fas fa-ellipsis-v paddingright paddingleft moreactions"></span>';
				buttons += '<span class="fas fa-chevron-down paddingleft toggleview" data-target="'+ target +'" style="margin-left:12px;"></span>';
			}

			if (label == '') {
				label = emptylabel;
			}

			var after = '<a class="edit-account-label" href="'+link+'&action=editaccount&accountnumber='+target+'&token='+token+'" data-target="'+target+'">';
				after += '<span class="fas fa-pen" style="margin-left:12px;opacity:0.1"></span>';
			after += '</a>';

			$('#line-' + target).find('.account-label').html(label);
			$('#line-' + target).find('.account-label').after(after);
			if (level == 2) {
				$('#line-'+target).find('.cardline-header-actions').html(buttons);
			} else {
				$('#line-'+target).find('.flexline-actions').html(buttons);
			}
		});

		// SUBMIT EDIT
		$(document).on('click', 'button[type=submit].button-editline', function(e) {
			e.preventDefault();
			//alert(' AJAX CALL to register newValue and after show it + animate it');

			var target = $(this).data('target');
			var newLabel = $('#line-'+target).find('input[name="accountlabel"]').val();
			var parentID = $('#line-'+target).data('parent');
			var pcgtype = $('#line-'+target).data('pcgtype');
			var level = $('#line-'+target).data('level');

			console.log(level);

			var buttons = '';
			buttons += '<span class="fas fa-ellipsis-v paddingright paddingleft moreactions"></span>';
			buttons += '<span class="fas fa-chevron-down paddingleft toggleview" data-target="'+ target +'" style="margin-left:12px;"></span>';

			var urlUpdate = "<?php echo dol_buildpath('ezcompta/ajax/accountingaccount_editlabel.php',1); ?>";
			$.ajax(
				urlUpdate,
				{
					async: true,
					method:'POST',
					dataType: "json",
					data: {
						token : '<?php echo newToken(); ?>',
						pcg_version : '<?php echo $accountancySystem->pcg_version; ?>',
						newlabel : newLabel,
						accountnumber : target,
						parentid : parentID,
						pcg_type : pcgtype,
						level : level,
					}
				}
			).done(function(data){
				if (data.success) {

					var toggleicon = '';
					if (data.active) {
						toggleicon += '<a class="reposition toggle-account" href="'+link+'&id='+data.accountid+'&action=disable&mode=0&token='+token+'" data-toggle="disable" data-target="'+target+'" data-accountid="'+data.accountid+'">';
						toggleicon += '<span class="fas fa-toggle-on paddingright" style="color: #681bb5;font-size: 1em;"></span>';
						toggleicon += '</a>';
					} else {
						toggleicon += '<a class="reposition toggle-account" href="'+link+'&id='+data.accountid+'&action=enable&mode=0&token='+token+'" data-toggle="disable" data-target="'+target+'" data-accountid="'+data.accountid+'">';
						toggleicon += '<span class="fas fa-toggle-off paddingright" style="color: #ccc;font-size: 1em;"></span>';
						toggleicon += '</a>';
					}

					var after = '<a class="edit-account-label" href="'+link+'&action=editaccount&accountnumber='+data.account_number+'&token='+token+'" data-target="'+data.account_number+'">';
					after += '<span class="fas fa-pen" style="margin-left:12px;opacity:0.1"></span>';
					after += '</a>';

					$('#line-' + target).data('label', data.label);
					$('#line-' + target).find('.toggle-icon').html(toggleicon);
					$('#line-' + target).find('.account-label').html(data.label).after(after);
					if (level == 2) {
						$('#line-' + target).find('.cardline-header-actions').html(buttons);
					} else {
						$('#line-' + target).find('.flexline-actions').html(buttons);
					}

					if (data.action === 'update' && $('#subaccountlist-' + target).hasClass('open')) {
						if (level == 2) {
							$('#line-' + target).find('.cardline-header-actions .fas.fa-chevron-down').removeClass('fa-chevron-down').addClass('fa-chevron-up');
						} else {
							$('#line-' + target).find('.flexline-actions .fas.fa-chevron-down').removeClass('fa-chevron-down').addClass('fa-chevron-up');
						}
					}
					// todo: si create, je dois créér la liste en dessous
					if (data.action === 'create' && data.sublist) {

						if (level > 2) {
							var a = '<ul class="subaccountlist level-'+level+'" id="subaccountlist-'+data.account_number+'">';
							a += data.sublist;
							a += '</ul>';
						} else {
							var a = '<ul class="firstlevel" id="subaccountlist-'+data.account_number+'">';
							a += data.sublist;
							a += '</ul>';
						}
						$('#line-' + target).after(a);
					}
				} else {
					console.warn(data.error);
				}
			});
		});

		//
		$(document).on('click', '.toggleview', function(e){
			var target = '#subaccountlist-' + $(this).data('target');
			$(this).toggleClass('fa-chevron-down fa-chevron-up');
			$(target).toggleClass('open');
		});
	});
</script>

<?php
// End of page
llxFooter();
$db->close(); ?>