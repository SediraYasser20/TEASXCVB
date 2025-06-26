<?php
/**
 * \file    htdocs/custom/mofabintegration/lib/mofabintegration.lib.php
 * \ingroup mofabintegration
 * \brief   Library file for MOFabIntegration module
 */
function mofabintegrationAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("mofabintegration@mofabintegration");

    $h = 0;
    $head = array();

    $head[$h][0] = DOL_URL_ROOT.'/custom/mofabintegration/admin/mofabintegration_setup.php';
    $head[$h][1] = $langs->trans("Settings");
    $head[$h][2] = 'settings';
    $h++;

    complete_head_from_modules($conf, $langs, null, $head, $h, 'mofabintegration@mofabintegration');

    return $head;
}
?>