<?php

require_once DOL_DOCUMENT_ROOT . '/core/triggers/interface_90_actions_afterevent.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/gpaofactory/class/gpaofactory.class.php'; // Or the class for your MOs, e.g., mrp/class/mrp_mo.class.php

class ActionsTechniquemodule implements InterfaceActionsAfterEvent
{
    public $results = array();
    public $errors = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        // You can load langs here if needed for error messages
        // global $langs;
        // $langs->load("techniquemodule@techniquemodule");
    }

    /**
     * This is the main trigger entry point.
     * It dispatches to more specific methods based on the action.
     */
    public function run_trigger($action, $object, $user, $langs, $conf)
    {
        global $db; // Ensure $db is available

        // GPAOFACTORY_VALIDATE is for the 'gpaofactory' module.
        // If you use a different MO module (e.g., the core 'mrp' module), the trigger name will be different.
        // Common patterns: MODULENAME_VALIDATE, OBJECTNAME_VALIDATE
        // e.g., MANUFACTURING_ORDER_VALIDATE, MRO_VALIDATE, MO_VALIDATE
        // To find the correct trigger, you can search the MO module's code for `run_triggers(`
        // For example, in `htdocs/gpaofactory/class/gpaofactory.class.php` in the `setValid()` method.
        // Dolibarr 21.0.1 `gpaofactory` module uses `GPAOFACTORY_VALIDATE`

        // Let's log the action and object type to help debug if the trigger isn't firing as expected
        dol_syslog("ActionsTechniquemodule::run_trigger - Action: ".$action." Object type: ".(is_object($object)?get_class($object):gettype($object)), LOG_DEBUG);

        if ($action === 'GPAOFACTORY_VALIDATE') { // This is specific to the Gpaofactory external module
            return $this->afterMOValidate($object, $user, $langs, $conf);
        }
        // Add other potential MO validation triggers if you're unsure which module is active
        // For instance, if you were using the core MRP module:
        // elseif ($action === 'MO_VALIDATE' || $action === 'MANUFACTURINGORDER_VALIDATE') {
        //    return $this->afterMOValidate($object, $user, $langs, $conf);
        // }


        return 0; // 0 for success, no action taken for other triggers
    }

    /**
     * Executed after a Manufacturing Order is validated.
     *
     * @param  CommonObject $object      The Manufacturing Order object (e.g., Gpaofactory instance)
     * @param  User         $user        The user performing the action
     * @param  Translate    $langs       Language object
     * @param  Conf         $conf        Configuration object
     * @return int                       0 if OK, >0 if error, <0 if warning
     */
    protected function afterMOValidate($object, $user, $langs, $conf)
    {
        global $db;

        // Ensure it's indeed a manufacturing order object
        // and it has the necessary properties (ref, statut, fk_product)
        // Gpaofactory class is 'Gpaofactory'
        if (!is_object($object) || get_class($object) !== 'Gpaofactory' || !isset($object->ref) || !isset($object->statut)) {
             dol_syslog("ActionsTechniquemodule::afterMOValidate - Object is not a Gpaofactory MO or missing essential properties. Object class: ".get_class($object), LOG_WARNING);
             return 0; // Not the object we're looking for or not ready
        }

        // Check if MO is validated
        // Gpaofactory::STATUS_VALIDATED is 1.
        // Check the MO class for the correct status constant if this doesn't work.
        if ($object->statut == Gpaofactory::STATUS_VALIDATED) {
            $prod = new Product($db);

            // Define the ref for the new "sellable MO" product/service
            $new_product_ref = 'MOSALE-' . $object->ref;

            // Check if a product with this ref already exists to prevent duplicates
            if ($prod->fetch(null, $new_product_ref, null, null, 0, 0, 0, 0) > 0) { // Added more params for fetch for compatibility
                dol_syslog("ActionsTechniquemodule: Product/Service " . $new_product_ref . " already exists for MO " . $object->ref . ". Skipping creation.", LOG_INFO);
                $this->results[] = "Product/Service " . $new_product_ref . " already exists.";
                return 0; // Already exists, do nothing
            }

            // Populate product/service details
            $prod->ref = $new_product_ref;
            $prod->label = 'Sellable Output of MO: ' . $object->ref;

            // Try to get more details from the MO's product_to_produce
            // In Gpaofactory, the product to produce is referenced by fk_product
            $product_to_produce = new Product($db);
            if (isset($object->fk_product) && $object->fk_product > 0) {
                if ($product_to_produce->fetch($object->fk_product) > 0) {
                    $prod->label .= ' (Produces: ' . $product_to_produce->label . ')';
                    // Set price from the product being manufactured. You might want a different logic.
                    // E.g., a markup on its cost price, or a fixed price for "custom MO sales".
                    $prod->price_base_type = $product_to_produce->price_base_type; // 'HT' or 'TTC'
                    $prod->price = $product_to_produce->price;
                    $prod->price_ttc = $product_to_produce->price_ttc;
                    $prod->price_min = $product_to_produce->price_min;
                    $prod->price_min_ttc = $product_to_produce->price_min_ttc;
                    $prod->tva_tx = $product_to_produce->tva_tx;
                    $prod->localtax1_tx = $product_to_produce->localtax1_tx; // If using local taxes
                    $prod->localtax2_tx = $product_to_produce->localtax2_tx; // If using local taxes
                    $prod->description = "Represents the sellable output of Manufacturing Order: " . $object->ref . "\n"
                                       . "Based on product: " . $product_to_produce->label . " (" . $product_to_produce->ref . ")";
                } else {
                     $prod->description = "Represents the sellable output of Manufacturing Order: " . $object->ref;
                }
            } else {
                 $prod->description = "Represents the sellable output of Manufacturing Order: " . $object->ref;
            }

            // Set as a Service to avoid stock management issues for this representative item
            $prod->type = Product::TYPE_SERVICE; // 1 for Service
            $prod->status = 1; // For sale
            $prod->status_buy = 0; // Not for buy (we are selling the output, not buying the MO itself)
            $prod->finished = 1; // For services, this usually means it's a defined service

            // Set the custom extrafield
            // IMPORTANT: Replace 'options_linked_mo_ref' with the actual code of your extrafield
            // This was defined in Step 4.
            $prod->array_options['options_linked_mo_ref'] = $object->ref;

            // Default price if not set (adjust as needed)
            if (empty($prod->price)) $prod->price = 0; // Set a sensible default or leave it for manual entry
            if (empty($prod->tva_tx) && isset($conf->global->MAIN_DEFAULT_VAT_RATE)) { // Get default VAT if not set
                 $prod->tva_tx = vatrate($conf->global->MAIN_DEFAULT_VAT_RATE);
            }
             if (empty($prod->price_base_type)) $prod->price_base_type = 'HT'; // Default to HT

            // Before creating, re-calculate TTC from HT or vice-versa if necessary
            $prod->calculate_price_inc_tax(!empty($prod->tva_tx) ? $prod->tva_tx : 0, !empty($prod->localtax1_tx) ? $prod->localtax1_tx : 0, !empty($prod->localtax2_tx) ? $prod->localtax2_tx : 0);


            $result = $prod->create($user);

            if ($result > 0) {
                $this->results[] = "Representative service created: " . $prod->ref . " (ID: " . $prod->id . ") for MO " . $object->ref;
                dol_syslog("ActionsTechniquemodule: Service " . $prod->ref . " created for MO " . $object->ref, LOG_INFO);
                // Optional: Add a link from the MO to this new service for traceability
                // $object->add_object_linked('product', $prod->id); // Check MO class for correct link method if needed
                return 0; // Success
            } else {
                $this->errors[] = "Failed to create representative service for MO " . $object->ref . ". Error: " . ($prod->error ? $prod->error : 'Unknown error during product creation');
                dol_syslog("ActionsTechniquemodule: Failed to create service for MO " . $object->ref . " - " . ($prod->error ? $prod->error : 'Unknown error'), LOG_ERR);
                if (!empty($prod->errors)) { // product class might store an array of errors
                    foreach ($prod->errors as $err) {
                        dol_syslog("ActionsTechniquemodule: Product creation sub-error: " . $err, LOG_ERR);
                    }
                }
                return 1; // Error
            }
        }
        return 0; // MO not validated, or other status
    }

    // Implement other required methods from InterfaceActionsAfterEvent
    // This ensures your class fully implements the interface.
    // Most can be left empty if not used by this specific module.
    public function afterRemoveDoc($action, $object, $user, $langs, $conf, $result) { return 0; }
    public function beforeUserCreate($action, $object, $user, $langs, $conf) { return 0; }
    public function afterUserCreate($action, $object, $user, $langs, $conf, $result) { return 0; }
    public function beforeUserUpdate($action, $object, $user, $langs, $conf) { return 0; }
    public function afterUserUpdate($action, $object, $user, $langs, $conf, $result) { return 0; }
    public function beforeUserDelete($action, $object, $user, $langs, $conf) { return 0; }
    public function afterUserDelete($action, $object, $user, $langs, $conf, $result) { return 0; }
    public function beforeLogin($action, $object, $user, $langs, $conf) { return 0; }
    public function afterLogin($action, $object, $user, $langs, $conf, $result) { return 0; }
    public function beforeLogout($action, $object, $user, $langs, $conf) { return 0; }
    public function afterLogout($action, $object, $user, $langs, $conf, $result) { return 0; }
    public function beforeSetPassword($action, $object, $user, $langs, $conf) { return 0; }
    public function afterSetPassword($action, $object, $user, $langs, $conf, $result) { return 0; }
    public function beforePDFCreation($action, $object, $user, $langs, $conf, &$outputlangs) { return 0; }
    public function afterPDFCreation($action, $object, $user, $langs, $conf, $outputlangs) { return 0; }
    public function beforeMAILEnvoi($action, &$object, $user, $langs, $conf, &$sendto, &$replyto, &$subject, &$message, &$attachedfiles, &$cc) { return 0; }
    public function afterMAILEnvoi($action, $object, $user, $langs, $conf, $sendto, $replyto, $subject, $message, $attachedfiles, $cc, $result) { return 0; }
    public function beforeAddObjectLine($action, $object, $user, $langs, $conf, &$line, $object_parent, $project) { return 0; }
    public function afterAddObjectLine($action, $object, $user, $langs, $conf, $line, $object_parent, $project, $result) { return 0; }
    public function beforeUpdateObjectLine($action, $object, $user, $langs, $conf, &$line, $object_parent, $project) { return 0; }
    public function afterUpdateObjectLine($action, $object, $user, $langs, $conf, $line, $object_parent, $project, $result) { return 0; }
    public function beforeDeleteObjectLine($action, $object, $user, $langs, $conf, $lineid, $object_parent, $project) { return 0; }
    public function afterDeleteObjectLine($action, $object, $user, $langs, $conf, $lineid, $object_parent, $project, $result) { return 0; }
    public function beforeDocumentCreation($action, $object, $user, $langs, $conf, &$hidedetails, &$hidedesc, &$hidetotal) { return 0; }
    public function afterDocumentCreation($action, $object, $user, $langs, $conf, $docname, $result) { return 0; }
    public function beforeValidate($action, $object, $user, $langs, $conf) { return 0; }
    public function afterValidate($action, $object, $user, $langs, $conf, $result) { return 0; }
    public function beforeDelete($action, $object, $user, $langs, $conf) { return 0; }
    public function afterDelete($action, $object, $user, $langs, $conf, $result) { return 0; }
    public function beforeCreate($action, $object, $user, $langs, $conf) { return 0; }
    public function afterCreate($action, $object, $user, $langs, $conf, $result) { return 0; }
    public function beforeUpdate($action, $object, $user, $langs, $conf) { return 0; }
    public function afterUpdate($action, $object, $user, $langs, $conf, $result) { return 0; }
    public function beforeClose($action, $object, $user, $langs, $conf) { return 0; }
    public function afterClose($action, $object, $user, $langs, $conf, $result) { return 0; }
    public function beforeReopen($action, $object, $user, $langs, $conf) { return 0; }
    public function afterReopen($action, $object, $user, $langs, $conf, $result) { return 0; }
    public function beforeClone($action, $object, $user, $langs, $conf, &$object_clone) { return 0; }
    public function afterClone($action, $object, $user, $langs, $conf, $object_clone, $result) { return 0; }
    public function beforeBuildDoc($action, $object, $user, $langs, $conf, $module, &$param1) { return 0; }
    public function afterBuildDoc($action, $object, $user, $langs, $conf, $module, &$param1, &$output) { return 0; }
    public function beforeRemoveDoc($action, $object, $user, $langs, $conf, $forcedelete) { return 0; }

}