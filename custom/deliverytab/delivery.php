<?php
// Standard Dolibarr bootstrap (adjust relative path if needed)
$res = @include '../../main.inc.php';          // in custom/deliverytab/
if (! $res) {
    $res = @include '../../../main.inc.php';   // fallback
}
if (! $res) exit('Include of main.inc.php failed');

// First, let's check if the file exists in the expected location or its alternatives
$commande_lib_paths = array(
    DOL_DOCUMENT_ROOT . '/commande/lib/commande.lib.php',
    DOL_DOCUMENT_ROOT . '/comm/commande/lib/commande.lib.php',  // Older versions path
    DOL_DOCUMENT_ROOT . '/core/lib/order.lib.php'  // Another possible location
);

$lib_found = false;
foreach ($commande_lib_paths as $lib_path) {
    if (file_exists($lib_path)) {
        require_once $lib_path;
        $lib_found = true;
        break;
    }
}

// If we can't find the library, define a basic tab function as fallback
if (!$lib_found || !function_exists('commande_prepare_head')) {
    function commande_prepare_head($object) {
        global $langs, $conf, $user;
        $h = 0;
        $head = array();
        
        $head[$h][0] = DOL_URL_ROOT.'/commande/card.php?id='.$object->id;
        $head[$h][1] = $langs->trans("OrderCard");
        $head[$h][2] = 'order';
        $h++;
        
        $head[$h][0] = $_SERVER['PHP_SELF'].'?id='.$object->id;
        $head[$h][1] = $langs->trans("Delivery");
        $head[$h][2] = 'deliverytab';
        $h++;
        
        return $head;
    }
}

// Load core classes
require_once DOL_DOCUMENT_ROOT . '/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';

// Load translations
$langs->load('orders');
$langs->load('deliverytab@deliverytab');

// Create objects
$form = new Form($db);
$extrafields = new ExtraFields($db);

// Get the Sales Order
$id = GETPOST('id', 'int');
if (!$id) {
    accessforbidden('', 0, 0, 'Error: Missing order ID');
}

$order = new Commande($db);
if ($order->fetch($id) <= 0) {
    setEventMessage($langs->trans('OrderNotFound'), 'errors');
    exit;
}

// Load extrafields for "order"
$extrafields->fetch_name_optionals_label($order->table_element);
$order->fetch_optionals();

// Define delivery status options
$delivery_status_options = array(
    '1' => 'Waiting receive',
    '2' => 'RECEIVED',
    '3' => 'SORTI EN LIVRAISON',
    '4' => 'Livree',
    '5' => 'Annuler'
);

// Process form submission to update delivery status
$action = GETPOST('action', 'alpha');
if ($action == 'update_delivery_status' && $user->rights->commande->creer) {
    $delivery_status = GETPOST('delivery_status', 'alpha');
    
    // Update the extrafield
    $order->array_options['options_delivery_status'] = $delivery_status;
    
    $result = $order->insertExtraFields();
    if ($result < 0) {
        setEventMessages($order->error, $order->errors, 'errors');
    } else {
        setEventMessages($langs->trans("RecordSaved"), null);
    }
    
    // Reload the order with updated values
    $order->fetch($id);
    $order->fetch_optionals();
}

// Render the page
llxHeader('', $langs->trans('Delivery'));

// Tabs
$head = commande_prepare_head($order);
dol_fiche_head($head, 'deliverytab', $langs->trans("CustomerOrder"), -1, 'order');

// Form to edit delivery status
print '<form method="POST" action="' . $_SERVER["PHP_SELF"] . '?id=' . $id . '">';
print '<input type="hidden" name="token" value="' . newToken() . '">';
print '<input type="hidden" name="action" value="update_delivery_status">';

// Table showing and editing Delivery Status
print '<div class="fichecenter"><table class="border centpercent tableforfield">';
print '<tr>';
print '  <td width="25%" class="titlefield"><strong>' . $langs->trans('DeliveryStatus') . '</strong></td>';
print '  <td>';

// Get current value
$current_status = $order->array_options['options_delivery_status'] ?? '';

// Display select dropdown to edit the value
print $form->selectarray(
    'delivery_status',
    $delivery_status_options,
    $current_status,
    1,  // Add empty line
    0,  // No translate
    0,  // No max length
    0   // No HTML format
);

print '  </td>';
print '</tr>';
print '</table></div>';

// Submit button
print '<div class="center" style="padding: 15px;">';
print '<input type="submit" class="button button-save" value="' . $langs->trans("Save") . '">';
print '</div>';
print '</form>';

dol_fiche_end();
llxFooter();
$db->close();