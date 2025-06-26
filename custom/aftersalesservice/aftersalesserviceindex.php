<?php
/*
 * After Sales Service Module - Index Page - View Only
 */
$res = 0;
if (!empty($_SERVER['CONTEXT_DOCUMENT_ROOT'])) {
    $res = @include $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/main.inc.php';
}
if (!$res && file_exists(__DIR__.'/../../main.inc.php')) {
    $res = @include __DIR__.'/../../main.inc.php';
}
if (!$res) die('Include of main fails');

global $db, $langs, $user, $conf;
$langs->loadLangs(['aftersalesservice@aftersalesservice', 'companies', 'bills']);
if (empty($conf->aftersalesservice->enabled) || (!$user->admin && empty($user->rights->aftersalesservice->read))) {
    accessforbidden();
}

require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
dol_include_once('/fourn/class/fournisseur.facture.class.php');
$extrafields = new ExtraFields($db);
$extrafields->fetch_name_optionals_label('facture_fourn', true);
$reglementOptions = $extrafields->attributes['facture_fourn']['param']['reglement_sav']['options'] ?? array();

$form = new Form($db);

// Filters
$date_from     = GETPOST('date_from', 'alpha');
$date_to       = GETPOST('date_to', 'alpha');
$vendor_name   = GETPOST('vendor_name', 'alpha');
$status_filter = GETPOST('status', 'alpha');

llxHeader('', $langs->trans('AfterSalesService'));
print load_fiche_titre($langs->trans('AfterSalesService'), '', 'supplierinvoice');

print '<form method="GET" action="' . $_SERVER['PHP_SELF'] . '">';
print '<div class="tabsAction">';
print $langs->trans('From') . ': <input type="date" name="date_from" value="' . dol_escape_htmltag($date_from) . '"> ';
print $langs->trans('To') . ': <input type="date" name="date_to" value="' . dol_escape_htmltag($date_to) . '"> ';
print $langs->trans('Vendor') . ': <input type="text" name="vendor_name" value="' . dol_escape_htmltag($vendor_name) . '"> ';
print $langs->trans('Status') . ': <select name="status">';
print '<option value="">' . $langs->trans('All') . '</option>';
print '<option value="1"' . ($status_filter === '1' ? ' selected' : '') . '>' . $langs->trans('Paid') . '</option>';
print '<option value="0"' . ($status_filter === '0' ? ' selected' : '') . '>' . $langs->trans('Unpaid') . '</option>';
print '</select> ';
print '<input type="submit" class="button" value="' . $langs->trans('Filter') . '">';
print '</div>';
print '</form><br>';

$sql  = "SELECT f.rowid, f.ref, f.datef AS invoice_date, f.paye, f.fk_soc,";
$sql .= " co.rowid AS order_id, co.date_commande AS order_date, co.date_livraison AS delivery_date,";
$sql .= " s.rowid AS vendor_id, s.nom AS vendor_name,";
$sql .= " ef.exp, ef.reffrencesav, ef.reglement_sav,";
$sql .= " ef.date_of_exp, ef.date_of_reglement,";
$sql .= " cf.credit_ttc";
$sql .= " FROM " . MAIN_DB_PREFIX . "facture_fourn AS f";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "facture_fourn_extrafields AS ef ON ef.fk_object = f.rowid";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe AS s ON f.fk_soc = s.rowid";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "element_element AS ee ON ee.fk_source = f.rowid";
$sql .= "   AND ee.sourcetype='invoice_supplier' AND ee.targettype='order_supplier'";
$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "commande_fournisseur AS co ON co.rowid = ee.fk_target";
$sql .= " INNER JOIN (";
$sql .= "   SELECT fk_facture_source, SUM(total_ttc) AS credit_ttc";
$sql .= "   FROM " . MAIN_DB_PREFIX . "facture_fourn";
$sql .= "   WHERE type = 2";
$sql .= "   GROUP BY fk_facture_source";
$sql .= " ) AS cf ON cf.fk_facture_source = f.rowid";
$sql .= " WHERE f.entity IN (" . getEntity('facture_fourn') . ")";
if (!empty($date_from))    $sql .= " AND f.datef >= '" . $db->idate($date_from) . "'";
if (!empty($date_to))      $sql .= " AND f.datef <= '" . $db->idate($date_to) . "'";
if (!empty($vendor_name))  $sql .= " AND s.nom LIKE '%" . $db->escape($vendor_name) . "%'";
if ($status_filter !== '') $sql .= " AND f.paye = '" . $db->escape($status_filter) . "'";
$sql .= " ORDER BY co.date_commande DESC, f.datef DESC";

$resql = $db->query($sql);

$totalCredit = 0;
if ($resql && $db->num_rows($resql) > 0) {
    print '<table class="noborder centpercent">';
    print '<tr class="liste_titre">';
    print '<th>' . $langs->trans('VendorInvoiceRef') . '</th>';
    print '<th>' . $langs->trans('Vendor') . '</th>';
    print '<th>' . $langs->trans('OrderDate') . '</th>';
    print '<th>' . $langs->trans('DeliveryDate') . '</th>';
    print '<th>' . $langs->trans('CreditTTC') . '</th>';
    print '<th>' . $langs->trans('Billed') . '</th>';
    print '<th>' . $langs->trans('EXP') . '</th>';
    print '<th>' . $langs->trans('RefSAV') . '</th>';
    print '<th>' . $langs->trans('ReglementSAV') . '</th>';
    print '<th>' . $langs->trans('DateOfExp') . '</th>';
    print '<th>' . $langs->trans('DateOfReglement') . '</th>';
    print '</tr>';
    while ($obj = $db->fetch_object($resql)) {
        $statusLabel = $obj->paye ? $langs->trans('Paid') : $langs->trans('Unpaid');
        $creditAmount = (float)$obj->credit_ttc;
        $totalCredit += $creditAmount;

        $dateExp = $obj->date_of_exp ? dol_print_date($db->jdate($obj->date_of_exp), 'dayhour') : '-';
        $dateReg = $obj->date_of_reglement ? dol_print_date($db->jdate($obj->date_of_reglement), 'dayhour') : '-';

        $reglementLabel = isset($reglementOptions[$obj->reglement_sav]) ? $reglementOptions[$obj->reglement_sav] : '';

        print '<tr class="oddeven">';
        print '<td><a href="' . DOL_URL_ROOT . '/fourn/facture/card.php?id=' . $obj->rowid . '">'
              . dol_escape_htmltag($obj->ref) . '</a></td>';
        print '<td><a href="' . DOL_URL_ROOT . '/societe/card.php?socid=' . $obj->vendor_id . '">'
              . dol_escape_htmltag($obj->vendor_name) . '</a></td>';
        print '<td>';
        if ($obj->order_id) {
            print '<a href="' . DOL_URL_ROOT . '/fourn/commande/card.php?id=' . $obj->order_id . '">'
                  . dol_print_date($db->jdate($obj->order_date), 'day') . '</a>';
        } else {
            print '-';
        }
        print '</td>';
        print '<td>';
        if ($obj->delivery_date) {
            print dol_print_date($db->jdate($obj->delivery_date), 'day');
        } else {
            print '-';
        }
        print '</td>';
        print '<td>' . price($creditAmount) . '</td>';
        print '<td><strong>' . $statusLabel . '</strong></td>';
        print '<td>' . (int)$obj->exp . '</td>';
        print '<td>' . dol_escape_htmltag($obj->reffrencesav) . '</td>';
        print '<td>' . dol_escape_htmltag($reglementLabel) . '</td>';
        print '<td>' . $dateExp . '</td>';
        print '<td>' . $dateReg . '</td>';
        print '</tr>';
    }
    print '<tr class="liste_total">';
    print '<td colspan="4" style="text-align: right; font-weight: bold;">' . $langs->trans('TotalCredit') . ':</td>';
    print '<td style="font-weight: bold;">' . price($totalCredit) . '</td>';
    print '<td colspan="6"></td>';
    print '</tr>';
    print '</table>';
} else {
    print '<div class="opacitymedium">' . $langs->trans('NoInvoiceFound') . '</div>';
}
$db->free($resql);
llxFooter();
$db->close();
?>
