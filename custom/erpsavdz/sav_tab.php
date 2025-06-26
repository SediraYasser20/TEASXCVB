<?php

// Load Dolibarr environment (this path needs to be adjusted based on actual Dolibarr structure)
// This block is a common way to find main.inc.php
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) { $i--; $j--; }
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) $res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) $res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) $res = @include "../main.inc.php";
if (!$res && file_exists("../../main.inc.php")) $res = @include "../../main.inc.php";
if (!$res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (!$res) die("Include of main fails");

require_once DOL_DOCUMENT_ROOT.'/core/lib/order.lib.php';
require_once DOL_DOCUMENT_ROOT.'/commande/class/commande.class.php';
global $extrafields; // Ensure $extrafields is available

// Load translation files for the module
$langs->load("erpsavdz@erpsavdz");

// Get ID of the order
$id = GETPOST('id', 'int');
$ref = GETPOST('ref', 'alpha'); // If ref is used

// Security check
if (!$user->rights->commande->lire) {
    accessforbidden();
}

$object = new Commande($db);

// Fetch the order object
if ($id > 0 || !empty($ref)) {
    $result = $object->fetch($id, $ref);
    if ($result > 0) {
        // Fetch extrafields
        $object->fetch_optionals(); // This loads extrafields into $object->array_options
    } else {
        dol_print_error($db, $object->error);
        exit;
    }
} else {
    dol_print_error($db, 'Missing ID or Ref for sales order.');
    exit;
}

// Page title
$title = $langs->trans("SAVTabTitle") . ' - ' . $object->ref;
llxHeader("", $title);

print load_fiche_titre($title, '', 'object_order.png@commande');

dol_fiche_head(commande_prepare_head($object, $user), 'sav', $langs->trans("Order"), -1, 'commande');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>'; // Required for proper layout

print '<table class="border tableforcontact" width="100%">';

// SAV Active (Boolean)
print '<tr><td width="30%">';
print $langs->trans('SAVActive'); // Translation key from extrafield label or define new one
print '</td><td>';
print yn($object->array_options['options_savorders_sav']);
print '</td></tr>';

// SAV Status (List)
print '<tr><td>';
print $langs->trans('SAVStatus'); // Translation key
print '</td><td>';
// The extrafield definition for 'sellist' provides options.
// We need to display the selected value's label, not its key.
$status_key = $object->array_options['options_savorders_status'];
$status_options = array();
if (isset($extrafields->attributes['commande']['fields']['savorders_status']['param']['options'])) {
    $status_options = $extrafields->attributes['commande']['fields']['savorders_status']['param']['options'];
} elseif (isset(Commande::$extrafieldsstatic['savorders_status']['param']['options'])) { // Fallback if available statically
    $status_options = Commande::$extrafieldsstatic['savorders_status']['param']['options'];
}
// If status_options is still empty, it implies the extrafield definition isn't loaded as expected.
// For robustness, one might hardcode/re-fetch or show the key.
print $langs->trans($status_options[$status_key] ?? $status_key); // Display translated status or key if no translation/option found
print '</td></tr>';

// SAV History (Long Text)
print '<tr><td>';
print $langs->trans('SAVHistory'); // Translation key
print '</td><td>';
print dol_htmlentitiesbr($object->array_options['options_savorders_history']); // nl2br or similar for text
print '</td></tr>';

print '</table>';

print '</div>'; // End of fichecenter

dol_fiche_end();

llxFooter();

$db->close();

?>
