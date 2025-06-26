<?php
/**
 * \file    htdocs/custom/mofabintegration/admin/mofabintegration_setup.php
 * \ingroup mofabintegration
 * \brief   Setup page for MOFabIntegration module
 */
require_once '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once '../lib/mofabintegration.lib.php';

global $langs, $user, $db, $conf;

$action = GETPOST('action', 'alpha');
$langs->load("mofabintegration@mofabintegration");

if (!$user->admin) accessforbidden();

if ($action == 'update') {
    $enabled = GETPOST('MOFABINTEGRATION_ENABLED', 'int');
    $ajax = GETPOST('MOFABINTEGRATION_USE_AJAX', 'int');
    dolibarr_set_const($db, 'MOFABINTEGRATION_ENABLED', $enabled, 'chaine', 0, '', $conf->entity);
    dolibarr_set_const($db, 'MOFABINTEGRATION_USE_AJAX', $ajax, 'chaine', 0, '', $conf->entity);
    setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
}

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("MOFabIntegration"), $linkback);

print '<div class="fichecenter">';
print '<div class="info">'.$langs->trans("ModuleDescription").'</div>';

print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("EnableMOIntegration").'</td>';
print '<td>';
print '<input type="checkbox" name="MOFABINTEGRATION_ENABLED" value="1"'.($conf->global->MOFABINTEGRATION_ENABLED ? ' checked' : '').'>';
print '</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>'.$langs->trans("EnableAjaxSearch").'</td>';
print '<td>';
print '<input type="checkbox" name="MOFABINTEGRATION_USE_AJAX" value="1"'.($conf->global->MOFABINTEGRATION_USE_AJAX ? ' checked' : '').'>';
print '</td>';
print '</tr>';

print '</table>';

print '<div class="tabsAction">';
print '<input type="submit" class="button" value="'.$langs->trans("Save").'">';
print '</div>';

print '</form>';
print '</div>';

llxFooter();
$db->close();
?>