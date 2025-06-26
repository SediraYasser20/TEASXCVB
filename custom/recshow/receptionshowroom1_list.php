```php
<?php
/* ------------------------------------------------------------------------
 * Copyright (C) 2017-2025 Your Name / Your Company
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 * ------------------------------------------------------------------------
 */

/**
 *  \file       receptionshowroom1_list.php
 *  \ingroup    recshow
 *  \brief      List page for Receptionshowroom1 objects without permission checks
 */

require_once __DIR__ . '/../../main.inc.php';

require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formadmin.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

require_once __DIR__ . '/class/receptionshowroom1.class.php';

$langs->load('recshow@recshow');

$action = GETPOST('action', 'aZ09');
$sortfield = GETPOST('sortfield', 'aZ09');
$sortorder = GETPOST('sortorder', 'aZ09');
$page = max(GETPOST('page', 'int'), 0); // Dolibarr uses 0-based pagination
$limit = (int) getDolGlobalInt('MAIN_SIZE_LISTE_LIMIT', 20);

$search_ref = GETPOST('search_ref', 'alpha');
$search_label = GETPOST('search_label', 'alpha');
$search_date = GETPOST('search_date', 'alpha');

// Handle clear action
if ($action === 'clear') {
    $search_ref = '';
    $search_label = '';
    $search_date = '';
    $page = 0;
}

// Initialize objects
$object = new Receptionshowroom1($db);
$form = new Form($db);
$extrafields = new ExtraFields($db);

// Build main SQL query
$sql = "SELECT r.rowid, r.ref, r.label, r.date_creation, r.fk_user_author, r.etatproduit AS etat, r.categoryproduit AS category, r.serialnumber, r.fk_soc, r.status, u.login AS user_author_name, s.nom AS thirdparty_name";
$sql .= " FROM " . MAIN_DB_PREFIX . $object->table_element . " AS r";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "user AS u ON r.fk_user_author = u.rowid";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe AS s ON r.fk_soc = s.rowid";

// Apply filters
$where = [];
if ($search_ref) {
    $where[] = "r.ref LIKE '%" . $db->escape($search_ref) . "%'";
}
if ($search_label) {
    $where[] = "r.label LIKE '%" . $db->escape($search_label) . "%'";
}
if ($search_date) {
    $where[] = "DATE(r.date_creation) = '" . $db->escape($search_date) . "'"; // Compare date only
}
if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}

// Sorting
if ($sortfield && in_array($sortfield, ['r.rowid', 'r.ref', 'r.label', 'r.date_creation', 'u.login', 's.nom', 'r.status', 'r.etatproduit', 'r.categoryproduit', 'r.serialnumber'])) {
    $sql .= " ORDER BY " . $db->escape($sortfield) . " " . ($sortorder === 'DESC' ? 'DESC' : 'ASC');
} else {
    $sql .= " ORDER BY r.rowid DESC";
}

// Pagination
$sql .= " " . $db->plimit($limit + 1, $page * $limit); // +1 to check for more records

// Execute main query
$resql = $db->query($sql);
if (!$resql) {
    dol_print_error($db);
    exit;
}

// Build count query
$sqlcount = "SELECT COUNT(*) AS nb FROM " . MAIN_DB_PREFIX . $object->table_element . " AS r";
if (!empty($where)) {
    $sqlcount .= " WHERE " . implode(' AND ', $where);
}
$rescount = $db->query($sqlcount);
$total = 0;
if ($rescount) {
    $objcount = $db->fetch_object($rescount);
    $total = (int) $objcount->nb;
    $db->free($rescount);
}

// Output header
llxHeader('', $langs->trans('Receptionshowroom1List'));

print load_fiche_titre($langs->trans('Receptionshowroom1List'), '', 'receptionshowroom1@recshow');

// Action buttons
print '<div class="tabsAction">';
print '<a class="butAction" href="receptionshowroom1_card.php?action=create">' . $langs->trans('NewReceptionshowroom1') . '</a>';
print '</div>';

// Search form
print '<form method="GET" action="' . dol_escape_htmltag($_SERVER['PHP_SELF']) . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans('Ref') . '</td>';
print '<td>' . $langs->trans('Label') . '</td>';
print '<td>' . $langs->trans('DateCreation') . '</td>';
print '<td></td>';
print '</tr>';
print '<tr class="liste_titre">';
print '<td><input class="flat maxwidth100" type="text" name="search_ref" value="' . dol_escape_htmltag($search_ref) . '"></td>';
print '<td><input class="flat maxwidth100" type="text" name="search_label" value="' . dol_escape_htmltag($search_label) . '"></td>';
print '<td><input class="flat maxwidth100" type="date" name="search_date" value="' . dol_escape_htmltag($search_date) . '"></td>';
print '<td>';
print '<input type="submit" class="button" value="' . $langs->trans('Search') . '">';
print ' <input type="submit" class="button" name="action" value="clear" title="' . $langs->trans('Clear') . '">';
print '</td>';
print '</tr>';
print '</table>';
print '</form>';

// Result table
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print list_titre('Ref', 'r.ref', 'receptionshowroom1@recshow', 'ref', $sortfield, $sortorder);
print list_titre('Label', 'r.label', 'receptionshowroom1@recshow', 'label', $sortfield, $sortorder);
print list_titre('Date creation', 'r.date_creation', 'receptionshowroom1@recshow', 'date_creation', $sortfield, $sortorder);
print list_titre('Author', 'u.login', 'receptionshowroom1@recshow', 'user_author_name', $sortfield, $sortorder);
print list_titre('Type', 'r.etatproduit', 'receptionshowroom1@recshow', 'etatproduit', $sortfield, $sortorder);
print list_titre('Category', 'r.categoryproduit', 'receptionshowroom1@recshow', 'categoryproduit', $sortfield, $sortorder);
print list_titre('Serial', 'r.serialnumber', 'receptionshowroom1@recshow', 'serialnumber', $sortfield, $sortorder);
print list_titre('ThirdParty', 's.nom', 'receptionshowroom1@recshow', 'thirdparty_name', $sortfield, $sortorder);
print list_titre('Status', 'r.status', 'receptionshowroom1@recshow', 'status', $sortfield, $sortorder);
print '<th class="right">' . $langs->trans('Action') . '</th>';
print '</tr>';

$num = $db->num_rows($resql);
if ($num > 0) {
    $i = 0;
    while ($i < min($num, $limit) && $obj = $db->fetch_object($resql)) {
        print '<tr class="oddeven">';
        print '<td><a href="receptionshowroom1_card.php?id=' . ((int) $obj->rowid) . '">' . dol_escape_htmltag($obj->ref) . '</a></td>';
        print '<td>' . dol_escape_htmltag($obj->label) . '</td>';
        print '<td>' . dol_print_date($db->jdate($obj->date_creation), 'dayhour') . '</td>';
        print '<td>' . dol_escape_htmltag($obj->user_author_name) . '</td>';
        print '<td>' . dol_escape_htmltag($object->fields['etatproduit']['arrayofkeyval'][$obj->etat]) . '</td>';
        print '<td>' . dol_escape_htmltag($object->fields['categoryproduit']['arrayofkeyval'][$obj->category]) . '</td>';
        print '<td>' . dol_escape_htmltag($obj->serialnumber) . '</td>';
        print '<td>' . dol_escape_htmltag($obj->thirdparty_name) . '</td>';
        print '<td>' . $object->getLibStatut($obj->status, 5) . '</td>';
        print '<td class="right nowrap">';
        print '<a href="receptionshowroom1_card.php?id=' . ((int) $obj->rowid) . '&action=edit">' . img_edit() . '</a> ';
        print '<a href="receptionshowroom1_card.php?id=' . ((int) $obj->rowid) . '&action=delete&token=' . newToken() . '" class="deletefield">' . img_delete() . '</a>';
        print '</td>';
        print '</tr>';
        $i++;
    }
    $db->free($resql);
} else {
    print '<tr><td colspan="10" class="center">' . $langs->trans('NoRecordFound') . '</td></tr>';
}

print '</table>';

// Pagination
$param = '';
if ($search_ref) $param .= '&search_ref=' . urlencode($search_ref);
if ($search_label) $param .= '&search_label=' . urlencode($search_label);
if ($search_date) $param .= '&search_date=' . urlencode($search_date);
if ($sortfield) $param .= '&sortfield=' . urlencode($sortfield);
if ($sortorder) $param .= '&sortorder=' . urlencode($sortorder);

print '<div class="center">';
print_barre_liste('', $page, $_SERVER['PHP_SELF'], $param, $sortfield, $sortorder, '', $total, $total, '', 0, '', '', $limit);
print '</div>';

llxFooter();
$db->close();
?>
