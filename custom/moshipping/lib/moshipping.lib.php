<?php
/* Copyright (C) 2024 Your Company
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Prepare admin pages header
 *
 * @return array
 */
function moshippingAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("moshipping@moshipping");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/moshipping/admin/setup.php", 1);
    $head[$h][1] = $langs->trans("Settings");
    $head[$h][2] = 'settings';
    $h++;

    complete_head_from_modules($conf, $langs, null, $head, $h, 'moshipping');

    complete_head_from_modules($conf, $langs, null, $head, $h, 'moshipping', 'remove');

    return $head;
}