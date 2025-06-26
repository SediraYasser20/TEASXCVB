<?php
/**
 * Tab for complementary attributes in Sales Orders
 */

// Load Dolibarr environment
require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

// Security check
if (!$user->rights->commande->lire) accessforbidden();

$langs->loadLangs(array('orders', 'companies'));

$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');

// Initialize objects
$object = new Commande($db);
$extrafields = new ExtraFields($db);

// Fetch the order
if ($id > 0 || !empty($ref)) {
    $result = $object->fetch($id, $ref);
    if ($result < 0) {
        dol_print_error($db, $object->error);
        exit;
    }
    $result = $object->fetch_thirdparty();
}

// Security check
$socid = 0;
if ($user->socid > 0) $socid = $user->socid;
$result = restrictedArea($user, 'commande', $object->id);

// Get extrafields
$extrafields->fetch_name_optionals_label($object->table_element);

/*
 * Actions
 */
if ($action == 'update_extras') {
    $object->oldcopy = dol_clone($object);

    // Fill array 'array_options' with data from update form
    $ret = $extrafields->setOptionalsFromPost(null, $object, GETPOST('attribute', 'none'));
    if ($ret < 0) $error++;

    if (!$error) {
        $result = $object->insertExtraFields();
        if ($result < 0) {
            setEventMessages($object->error, $object->errors, 'errors');
            $error++;
        }
    }

    if ($error) {
        $action = 'edit_extras';
    } else {
        // Success message
        setEventMessages($langs->trans("RecordSaved"), null, 'mesgs');
        header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
        exit;
    }
}

/*
 * View
 */
$title = $langs->trans('Order')." - ".$langs->trans('ComplementaryAttributes');
llxHeader('', $title);

$head = commande_prepare_head($object);
print dol_get_fiche_head($head, 'complementary_attributes', $langs->trans("CustomerOrder"), -1, 'order');

// Object card
// ------------------------------------------------------------
$linkback = '<a href="'.DOL_URL_ROOT.'/commande/list.php?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';

$morehtmlref = '<div class="refidno">';
// Ref customer
$morehtmlref .= $form->editfieldkey("RefCustomer", 'ref_client', $object->ref_client, $object, 0, 'string', '', 0, 1);
$morehtmlref .= $form->editfieldval("RefCustomer", 'ref_client', $object->ref_client, $object, 0, 'string', '', null, null, '', 1);
// Thirdparty
$morehtmlref .= '<br>'.$langs->trans('ThirdParty').' : '.$object->thirdparty->getNomUrl(1);
$morehtmlref .= '</div>';

dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';

// Display complementary attributes
print '<table class="border centpercent tableforfield">';

// Display delivery_status
$key = 'delivery_status';
$label = $extrafields->attributes[$object->table_element]['label'][$key];
$value = $object->array_options['options_'.$key];

if ($action == 'edit_extras') {
    print '<tr><td class="titlefield fieldrequired">';
    print $langs->trans($label);
    print '</td><td>';
    print $extrafields->showInputField($key, $value, '', '', '', 0, $object->id, $object->table_element);
    print '</td></tr>';
} else {
    print '<tr><td class="titlefield">';
    print $langs->trans($label);
    print '</td><td>';
    print $extrafields->showOutputField($key, $value, '', $object->table_element);
    print '</td></tr>';
}

// Display destination
$key = 'destination';
$label = $extrafields->attributes[$object->table_element]['label'][$key];
$value = $object->array_options['options_'.$key];

if ($action == 'edit_extras') {
    print '<tr><td class="titlefield fieldrequired">';
    print $langs->trans($label);
    print '</td><td>';
    print $extrafields->showInputField($key, $value, '', '', '', 0, $object->id, $object->table_element);
    print '</td></tr>';
} else {
    print '<tr><td class="titlefield">';
    print $langs->trans($label);
    print '</td><td>';
    print $extrafields->showOutputField($key, $value, '', $object->table_element);
    print '</td></tr>';
}

// Display kilometrage
$key = 'kilometrage';
$label = $extrafields->attributes[$object->table_element]['label'][$key];
$value = $object->array_options['options_'.$key];

if ($action == 'edit_extras') {
    print '<tr><td class="titlefield fieldrequired">';
    print $langs->trans($label);
    print '</td><td>';
    print $extrafields->showInputField($key, $value, '', '', '', 0, $object->id, $object->table_element);
    print '</td></tr>';
} else {
    print '<tr><td class="titlefield">';
    print $langs->trans($label);
    print '</td><td>';
    print $extrafields->showOutputField($key, $value, '', $object->table_element);
    print '</td></tr>';
}

print '</table>';

if ($action == 'edit_extras') {
    print '<div class="center">';
    print '<input type="submit" class="button button-save" value="'.$langs->trans("Save").'">';
    print '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
    print '<input type="submit" class="button button-cancel" name="cancel" value="'.$langs->trans("Cancel").'">';
    print '</div>';
}

print '</div>';

print dol_get_fiche_end();

if ($action == 'edit_extras') {
    print '<form action="'.$_SERVER["PHP_SELF"].'" method="post" name="formextra">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    print '<input type="hidden" name="action" value="update_extras">';
    print '<input type="hidden" name="id" value="'.$object->id.'">';
}

if ($action != 'edit_extras' && $user->rights->commande->creer) {
    print '<div class="tabsAction">';
    print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit_extras">'.$langs->trans('Modify').'</a>';
    print '</div>';
}

if ($action == 'edit_extras') {
    print '</form>';
}

// End of page
llxFooter();
$db->close();