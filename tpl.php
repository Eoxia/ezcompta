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
//if (! defined('NOREQUIREMENU'))            define('NOREQUIREMENU', '1');				// If there is no need to load and show top and left menu
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


/*******************************************************************
* VARIABLES
********************************************************************/
$action = GETPOST('action');

$accountancySystem = new AccountancySystem($db);
$accountancySystem->fetch(getDolGlobalInt('CHARTOFACCOUNTS'));

/*******************************************************************
* ACTIONS
********************************************************************/

/***************************************************
* VIEW
****************************************************/
$js_array = array();
$css_array = array('/ezcompta/assets/css/ezcompta.css');
llxHeader('', $langs->trans('EzCompta'), '', '', 0, 0, $js_array, $css_array, '', 'mod-ezcompta page-index');
?>

<div id="ezcompta-main-wrapper">
	<div class="ezcompta-side-wrapper">
		<form action="#" method="POST">
			<div class="searchicon"><span class="fas fa-search"></span></div>
			<input type="text" name="" placeholder="<?php echo $langs->trans('Search'); ?>">
		</form>
		<ul>
			<li><a href=""><span class="fas fa-folder paddingright"></span> 1. Capitaux <span class="badge marginleftonlyshort">92</span></a></li>
			<li><a href="" class="active"><span class="fas fa-folder paddingright"></span> 2. Immobilisation <span class="badge marginleftonlyshort">144</span></a></li>
			<li><a href=""><span class="fas fa-folder paddingright"></span> 3. Stock <span class="badge marginleftonlyshort">38</span></a></li>
			<li><a href=""><span class="fas fa-folder paddingright"></span> 4. Tiers <span class="badge marginleftonlyshort">158</span></a></li>
			<li><a href=""><span class="fas fa-folder paddingright"></span> 5. Financiers <span class="badge marginleftonlyshort">49</span></a></li>
			<li><a href=""><span class="fas fa-folder paddingright"></span> 6. Charges <span class="badge marginleftonlyshort">235</span></a></li>
			<li><a href=""><span class="fas fa-folder paddingright"></span> 7. Produits <span class="badge marginleftonlyshort">118</span></a></li>
			<li><a href=""><span class="fas fa-folder paddingright"></span> 8. Spéciaux <span class="badge marginleftonlyshort">24</span></a></li>
		</ul>
	</div>
	<div class="ezcompta-content-wrapper">

		<h1>2. Immobilisation</h1>
		<p class="opacitymedium">Unde Rufinus ea tempestate praefectus praetorio ad discrimen trusus est ultimum. ire enim ipse compellebatur ad militem, quem exagitabat inopia simul et feritas, et alioqui coalito more in ordinarias dignitates asperum semper et saevum, ut satisfaceret atque monstraret, quam ob causam annonae convectio sit impedita.</p>

		<ul class="ezcompta-listaccount">
			<li>
				<div class="account-cardline-header">
					<div class="cardline-header-title">
						<span class="fas fa-clipboard-list paddingright"></span> <b>20<span class="opacitymedium">XXXX</span></b> - Immobilisation incorporelles et frais d’établissement
						<a href="#"><span class="fas fa-pen" style="margin-left:12px;color:#ddd;"></span></a>
					</div>
					<div class="cardline-header-actions">
						<span style="margin-right: 16px">
							<span class="badge badge-folder"><span class="fas fa-folder paddingright"></span> 5</span>
							<span class="badge badge-items"><span class="fas fa-clipboard-list paddingright"></span> 42</span>
						</span>
						<span class="fas fa-chevron-down paddingright paddingleft"></span>
					</div>
				</div>
			</li>
			<li>
				<div class="account-cardline-header">
					<div class="cardline-header-title">
						<span class="fas fa-clipboard-list paddingright"></span> <b>21<span class="opacitymedium">XXXX</span></b> - Immobilisations corporelles
						<a href="#"><span class="fas fa-pen" style="margin-left:12px;color:#ddd;"></span></a>
					</div>
					<div class="cardline-header-actions">
						<span style="margin-right: 16px">
							<span class="badge badge-folder"><span class="fas fa-folder paddingright"></span> 6</span>
							<span class="badge badge-items"><span class="fas fa-clipboard-list paddingright"></span> 32</span>
						</span>
						<span class="fas fa-chevron-up paddingright paddingleft" style="color:#b0bb29"></span>
					</div>
				</div>
				<ul class="open">

					<?php

					$list = array(
						'211' => 'Terrains',
						'212' => 'Agencements et aménagements de terrains',
						'213' => 'Constructions',
						'214' => 'Construction sur sol d’autrui',
						'215' => 'Installations techniques, matériels et outillages industriels',
						'216' => '',
						'217' => '',
						'218' => 'Autres immobilisations corporelles',
						'219' => '',
					);

					foreach ($list as $accountnumber => $accountlabel) {

						$coloricon = empty($accountlabel) ? '#ddd' : '#b0bb39';
						$labeltoshow = empty($accountlabel) ? '<span class="opacitymedium">Compte inexistant</span>' : $accountlabel;
						print '<li>';
							print '<div class="flexline">';
								print '<div>';
									print '<span class="fas fa-circle paddingright" style="color: '.$coloricon.';font-size: 0.5em;"></span> <b>'.$accountnumber.'<span class="opacitymedium">XXX</span></b> - ';

									if ($action == 'editaccount' && GETPOST('accountnumber') == $accountnumber) {
										print '<input type="text" name="" value="'.$accountlabel.'" class="minwidth400">';
									} else {
										print $labeltoshow;
										print '<a href="'.$_SERVER['PHP_SELF'].'?action=editaccount&accountnumber='.$accountnumber.'"><span class="fas fa-pen" style="margin-left:12px;opacity:0.1"></span></a>';
									}
								print '</div>';
								print '<div>';
								if ($action == 'editaccount' && GETPOST('accountnumber') == $accountnumber) {
									print '<a href="'.$_SERVER['PHP_SELF'].'"><span class="fas fa-times paddingright" style="color:#f00"></span></a> ';
									print '<span class="fas fa-check paddingleft" style="color:#55a580"></span> ';
								} else {
									if (!empty($accountlabel)) {
										print '<span class="fas fa-eye paddingright paddingleft" style="opacity:0.9; color:#ccc"></span>';
									}
								}
								print '</div>';
								//print '<div><span class="fas fa-ellipsis-v paddingright"></span></div>';
							print '</div>';
						print '</li>';
					}
					?>
				</ul>
			</li>
			<li>
				<div class="account-cardline-header">
					<div class="cardline-header-title"><span class="fas fa-clipboard-list paddingright"></span> <b>22<span class="opacitymedium">XXXX</span></b> - Immobilisations mises en concession <a href="#"><span class="fas fa-pen" style="margin-left:12px;color:#ddd;"></span></a></div>
					<div class="cardline-header-actions"><span class="fas fa-chevron-down paddingright"></span></div>
				</div>
			</li>
			<li>
				<div class="account-cardline-header">
					<div class="cardline-header-title"><span class="fas fa-clipboard-list paddingright"></span> <b>23<span class="opacitymedium">XXXX</span></b> - Immobilisations en cours, avances et acomptes <a href="#"><span class="fas fa-pen" style="margin-left:12px;color:#ddd;"></span></a></div>
					<div class="cardline-header-actions"><span class="fas fa-chevron-down paddingright"></span></div>
				</div>
			</li>
			<li>
				<div class="account-cardline-header">
					<div class="cardline-header-title"><span class="fas fa-clipboard-list paddingright" style="color:#ddd;"></span> <b>24<span class="opacitymedium">XXXX</span></b> - <span class="opacitymedium">Compte inexistant</span> <a href="#"><span class="fas fa-pen" style="margin-left:12px;color:#ddd;"></span></a></div>
					<div class="cardline-header-actions"></div>
				</div>
			</li>
			<li>
				<div class="account-cardline-header">
					<div class="cardline-header-title"><span class="fas fa-clipboard-list paddingright" style="color:#ddd;"></span> <b>25<span class="opacitymedium">XXXX</span></b> - <span class="opacitymedium">Compte inexistant</span> <a href="#"><span class="fas fa-pen" style="margin-left:12px;color:#ddd;"></span></a></div>
					<div class="cardline-header-actions"></div>
				</div>
			</li>
			<li>
				<div class="account-cardline-header">
					<div class="cardline-header-title"><span class="fas fa-clipboard-list paddingright"></span> <b>26<span class="opacitymedium">XXXX</span></b> - <span class="opacitymedium">participations et créances rattachées a des participations </span> <a href="#"><span class="fas fa-pen" style="margin-left:12px;color:#ddd;"></span></a></div>
					<div class="cardline-header-actions"><span class="fas fa-chevron-down paddingright"></span></div>
				</div>
				<ul class="open">
					<li>
						<div class="flexline">
							<div>
								<span class="fas fa-circle paddingright" style="color: #b0bb39;font-size: 0.5em;"></span> <b>261<span class="opacitymedium">XXX</span></b> - Titres de participation
							</div>
							<div>
								<span class="fas fa-eye-slash paddingright paddingleft" style="opacity:0.9; color:#b0bb39"></span>
							</div>
						</div>
						<ul class="lvl2 open">
							<li>Account 2611XX <a href="#"><span class="fas fa-pen" style="margin-left:12px;color:#ddd;"></span></a></li>
							<li>Account 2618XX <a href="#"><span class="fas fa-pen" style="margin-left:12px;color:#ddd;"></span></a></li>
						</ul>
					</li>
					<li>
						<div class="flexline">
							<div>
								<span class="fas fa-circle paddingright" style="color: #b0bb39;font-size: 0.5em;"></span> <b>262<span class="opacitymedium">XXX</span></b> - Titres évalués par équivalence 
							</div>
							<div>
								<span class="fas fa-eye paddingright paddingleft" style="opacity:0.9; color:#ccc"></span>
							</div>
						</div>
					</li>
					<li>
						<div class="flexline">
							<div>
								<span class="fas fa-circle paddingright" style="color: #ddd;font-size: 0.5em;"></span> <b>263<span class="opacitymedium">XXX</span></b> - <span class="opacitymedium">Compte inexistant</span>
							</div>
							<div>
								<span class="fas fa-eye paddingright paddingleft" style="opacity:0.9; color:#ccc"></span>
							</div>
						</div>
					</li>
					<li>
						<div class="flexline">
							<div>
								<span class="fas fa-circle paddingright" style="color: #ddd;font-size: 0.5em;"></span> <b>264<span class="opacitymedium">XXX</span></b> - <span class="opacitymedium">Compte inexistant</span>
							</div>
							<div>
								<span class="fas fa-eye paddingright paddingleft" style="opacity:0.9; color:#ccc"></span>
							</div>
						</div>
					</li>
					<li>
						<div class="flexline">
							<div>
								<span class="fas fa-circle paddingright" style="color: #ddd;font-size: 0.5em;"></span> <b>265<span class="opacitymedium">XXX</span></b> - <span class="opacitymedium">Compte inexistant</span>
							</div>
							<div>
								<span class="fas fa-eye paddingright paddingleft" style="opacity:0.9; color:#ccc"></span>
							</div>
						</div>
					</li>
					<li>
						<div class="flexline">
							<div>
								<span class="fas fa-circle paddingright" style="color: #b0bb29;font-size: 0.5em;"></span> <b>266<span class="opacitymedium">XXX</span></b> - Autres formes de participation
							</div>
							<div>
								<span class="fas fa-eye paddingright paddingleft" style="opacity:0.9; color:#ccc"></span>
							</div>
						</div>
					</li>
				</ul>
			</li>
		</ul>
	</div>

</div>

<?php


// End of page
llxFooter();
$db->close(); ?>