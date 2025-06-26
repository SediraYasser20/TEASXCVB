<?php
/* Copyright (C) 2024 Your Company
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

// Load Dolibarr environment
$res = 0;
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

global $langs, $user, $db, $conf, $hookmanager;

// Libraries
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once '../lib/moshipping.lib.php';

// Translations
$langs->loadLangs(array("admin", "moshipping@moshipping"));

// Access control
if (!$user->admin) accessforbidden();

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');

$arrayofparameters = array(
    'MOSHIPPING_ENABLE_AUTOMATIC_REPLACEMENT' => array('css'=>'minwidth500', 'enabled'=>1),
    'MOSHIPPING_LOG_REPLACEMENTS' => array('css'=>'minwidth500', 'enabled'=>1),
);

/*
 * Actions
 */

if ($action == 'update') {
    $db->begin();
    
    foreach ($arrayofparameters as $key => $val) {
        $result = dolibarr_set_const($db, $key, GETPOST($key, 'alpha'), 'chaine', 0, '', $conf->entity);
        if ($result < 0) {
            $error++;
        }
    }
    
    if (!$error) {
        $db->commit();
        setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
    } else {
        $db->rollback();
        setEventMessages($langs->trans("Error"), null, 'errors');
    }
}

/*
 * View
 */

llxHeader('', $langs->trans("MOShippingSetup"));

$linkback = '<a href="'.($backtopage?$backtopage:DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.$langs->trans("BackToModuleList").'</a>';

print load_fiche_titre($langs->trans("MOShippingSetup"), $linkback, 'title_setup');

print '<form action="'.$_SERVER["PHP_SELF"].'" method="POST">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Parameter").'</td>';
print '<td>'.$langs->trans("Value").'</td>';
print '</tr>';

foreach ($arrayofparameters as $key => $val) {
    print '<tr class="oddeven">';
    print '<td>';
    $tooltiphelp = (($langs->trans($key.'Tooltip') != $key.'Tooltip') ? $langs->trans($key.'Tooltip') : '');
    print $form->textwithpicto($langs->trans($key), $tooltiphelp);
    print '</td>';
    print '<td>';
    
    if ($key == 'MOSHIPPING_ENABLE_AUTOMATIC_REPLACEMENT' || $key == 'MOSHIPPING_LOG_REPLACEMENTS') {
        print ajax_constantonoff($key);
    } else {
        print '<input name="'.$key.'"  class="flat '.(empty($val['css']) ? 'minwidth200' : $val['css']).'" value="'.$conf->global->$key.'">';
    }
    print '</td></tr>';
}
print '</table>';

print '<br><div class="center">';
print '<input class="button button-save" type="submit" value="'.$langs->trans("Save").'">';
print '</div>';

print '</form>';

llxFooter();
$db->close();