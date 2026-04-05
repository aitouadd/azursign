<?php
/* Copyright (C) 2026 ITCAMELION SARL AU
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

$res = 0;
if (!$res && !empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
	$res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] === $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1)).'/main.inc.php')) {
	$res = @include substr($tmp, 0, ($i + 1)).'/main.inc.php';
}
if (!$res && file_exists('../../main.inc.php')) {
	$res = @include '../../main.inc.php';
}
if (!$res && file_exists('../../../main.inc.php')) {
	$res = @include '../../../main.inc.php';
}
if (!$res) {
	die('Include of main fails');
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

$langs->loadLangs(array('admin', 'azursign@azursign'));

if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

if ($action === 'save' && GETPOST('token', 'alpha') === currentToken()) {
	$requireLegal = GETPOSTINT('AZURSIGN_REQUIRE_LEGAL_ACK') ? 1 : 0;
	$logIp = GETPOSTINT('AZURSIGN_LOG_IP') ? 1 : 0;
	$setSigned = GETPOSTINT('AZURSIGN_SET_PROPOSAL_SIGNED') ? 1 : 0;
	$legalText = trim(GETPOST('AZURSIGN_LEGAL_TEXT', 'restricthtml'));

	$res1 = dolibarr_set_const($db, 'AZURSIGN_REQUIRE_LEGAL_ACK', $requireLegal, 'yesno', 0, '', $conf->entity);
	$res2 = dolibarr_set_const($db, 'AZURSIGN_LOG_IP', $logIp, 'yesno', 0, '', $conf->entity);
	$res3 = dolibarr_set_const($db, 'AZURSIGN_SET_PROPOSAL_SIGNED', $setSigned, 'yesno', 0, '', $conf->entity);
	$res4 = dolibarr_set_const($db, 'AZURSIGN_LEGAL_TEXT', $legalText, 'chaine', 0, '', $conf->entity);

	if ($res1 > 0 && $res2 > 0 && $res3 > 0 && $res4 > 0) {
		setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	} else {
		setEventMessages($langs->trans('Error'), null, 'errors');
	}
}

$title = $langs->trans('AzurSignSetupTitle');
llxHeader('', $title);

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($title, $linkback, 'title_setup');

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans('Parameter').'</td>';
print '<td>'.$langs->trans('Value').'</td>';
print '<td>'.$langs->trans('Description').'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans('AzurSignRequireLegalAck').'</td>';
print '<td><input type="checkbox" name="AZURSIGN_REQUIRE_LEGAL_ACK" value="1"'.(getDolGlobalInt('AZURSIGN_REQUIRE_LEGAL_ACK', 1) ? ' checked' : '').'></td>';
print '<td>'.$langs->trans('AzurSignRequireLegalAckDesc').'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans('AzurSignLegalText').'</td>';
print '<td><textarea name="AZURSIGN_LEGAL_TEXT" rows="4" class="quatrevingtpercent" style="width:100%;">'.dol_escape_htmltag(getDolGlobalString('AZURSIGN_LEGAL_TEXT', '')).'</textarea></td>';
print '<td>'.$langs->trans('AzurSignLegalTextDesc').'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans('AzurSignLogIp').'</td>';
print '<td><input type="checkbox" name="AZURSIGN_LOG_IP" value="1"'.(getDolGlobalInt('AZURSIGN_LOG_IP', 1) ? ' checked' : '').'></td>';
print '<td>'.$langs->trans('AzurSignLogIpDesc').'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans('AzurSignSetProposalSigned').'</td>';
print '<td><input type="checkbox" name="AZURSIGN_SET_PROPOSAL_SIGNED" value="1"'.(getDolGlobalInt('AZURSIGN_SET_PROPOSAL_SIGNED', 1) ? ' checked' : '').'></td>';
print '<td>'.$langs->trans('AzurSignSetProposalSignedDesc').'</td>';
print '</tr>';

print '</table>';

print '<div class="center">';
print '<input type="submit" class="button" value="'.$langs->trans('Save').'">';
print '</div>';
print '</form>';

llxFooter();
$db->close();
