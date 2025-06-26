<?php
// Dolibarr Trigger Class for MO Shipment Handling
// File: htdocs/core/triggers/interface_90_ModuleMyModule_MOShipment.class.php

// Ensure Dolibarr environment is loaded (path might need adjustment based on actual Dolibarr structure)
// Typically, triggers are loaded within Dolibarr's execution context, so direct inclusion of master.inc.php might not be needed here.
// However, if run standalone or if DOL_DOCUMENT_ROOT is not correctly set, it might be.
// For safety, ensure critical paths and classes are available.
if (defined('DOL_DOCUMENT_ROOT')) {
    require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
} else {
    // Fallback or error if environment is not set, though unlikely in trigger context
    // This path assumes the trigger file is in htdocs/core/triggers/
    require_once dirname(__FILE__).'/../../class/triggers/dolibarrtriggers.class.php';
}


/**
 * Class InterfaceMOShipment
 *
 * Trigger implementation for handling specific actions related to Manufacturing Orders (MO)
 * during shipment creation and order validation.
 *
 * The name 'ModuleMyModule' in the class name and filename usually refers to a custom module.
 * If this is part of a generic enhancement, the naming might be different,
 * e.g., interface_90_core_MOShipment.class.php or similar.
 * Using 'ModuleMyModule' as specified in the issue.
 */
class InterfaceMOShipment extends DolibarrTriggers
{
    public $name = 'InterfaceMOShipment';
    public $description = "Handles MO (Manufacturing Order) specific logic during shipment creation and order validation.";
    public $element = 'commande'; // Or 'shipping' or both, depending on primary context. 'commande' for ORDER_VALIDATE.
    public $version = '1.0';
    public $picto = 'generic'; // Optional: icon for the trigger

    /**
     * Constructor
     *
     * @param DolibarrDB    \$db         Database handler
     */
    public function __construct(\$db)
    {
        parent::__construct(\$db);
    }

    /**
     * Function called when a Dolibarr business event occurs.
     * The list of actions is defined in core/actions_*.class.php files.
     *
     * @param string        \$action     Event action code
     * @param CommonObject  \$object     The object that is being processed
     * @param User          \$user       The user who triggered the event
     * @param Translate     \$langs      The language object
     * @param Conf          \$conf       The configuration object
     * @return int                      <0 if KO, 0 if OK
     */
    public function runTrigger(\$action, \$object, User \$user, Translate \$langs, Conf \$conf)
    {
        // Check if custom triggers are disabled via a global configuration
        // Replace 'MAIN_MODULE_MYMODULE_TRIGGERS_DISABLED' with a more generic or appropriate constant if needed.
        if (!empty(\$conf->global->MAIN_DISABLE_CUSTOM_TRIGGERS) || !empty(\$conf->global->MAIN_MODULE_MYMODULE_TRIGGERS_DISABLED)) {
            dol_syslog(get_class(\$this)."::runTrigger Triggers disabled by MAIN_DISABLE_CUSTOM_TRIGGERS or MAIN_MODULE_MYMODULE_TRIGGERS_DISABLED.", LOG_DEBUG);
            return 0;
        }

        dol_syslog(get_class(\$this)."::runTrigger action=" . \$action . " for object id=" . \$object->id, LOG_DEBUG);

        // Trigger when a shipment (expedition) is created from a sales order
        if (\$action == 'SHIPPING_CREATE') {
            // The \$object here is typically the Expedition object.
            // We need to check its origin.
            if (isset(\$object->origin) && \$object->origin == 'commande' && isset(\$object->origin_id) && \$object->origin_id > 0) {
                dol_syslog(get_class(\$this)."::runTrigger SHIPPING_CREATE detected for order ID: " . \$object->origin_id, LOG_INFO);
                \$this->updateMOOrderLines(\$object->origin_id);
            }
        }
        
        // Trigger when a sales order (commande) is validated
        if (\$action == 'ORDER_VALIDATE') {
            // The \$object here is the Commande object.
            // We need to update its lines.
            if (isset(\$object->id) && \$object->id > 0) {
                dol_syslog(get_class(\$this)."::runTrigger ORDER_VALIDATE detected for order ID: " . \$object->id, LOG_INFO);
                \$this->updateMOOrderLines(\$object->id);
            }
        }
        
        return 0; // Must return 0 if OK, <0 if error
    }
    
    /**
     * Update order lines for MO orders to set fk_product = 31.
     * This method is called internally by the trigger.
     *
     * @param int \$commandeid The ID of the sales order (commande)
     * @return int Number of affected rows or -1 on error, though the trigger doesn't directly use the return value.
     */
    private function updateMOOrderLines(\$commandeid)
    {
        global \$db; // Use global \$db if not passed or set as property

        if (empty(\$commandeid) || !is_numeric(\$commandeid) || \$commandeid <= 0) {
            dol_syslog(get_class(\$this)."::updateMOOrderLines Invalid commandeid: " . \$commandeid, LOG_WARNING);
            return -1;
        }

        // Ensure $this->db is available if global $db is not preferred
        // $current_db = (isset($this->db) && is_object($this->db)) ? $this->db : $db;
        // For simplicity with Dolibarr triggers, global $db is often used directly in private methods.

        $sql = "UPDATE " . MAIN_DB_PREFIX . "commandedet";
        $sql .= " SET fk_product = 31";
        $sql .= " WHERE fk_commande = " . ((int) \$commandeid);
        $sql .= " AND (fk_product IS NULL OR fk_product = 0)"; // Only update if not set or set to 0
        // Regex to identify MO lines based on description pattern
        $sql .= " AND description REGEXP '^MO[0-9]+-[0-9]+.*Fabrication'";
        
        dol_syslog(get_class(\$this)."::updateMOOrderLines SQL: " . \$sql, LOG_DEBUG);
        
        \$resql = \$db->query(\$sql); // Using global $db
        
        if (\$resql) {
            $affected_rows = \$db->affected_rows(\$resql);
            dol_syslog(get_class(\$this)."::updateMOOrderLines Updated " . \$affected_rows . " MO lines for order ID " . \$commandeid, LOG_INFO);
            return \$affected_rows;
        } else {
            \$error_msg = \$db->lasterror();
            dol_syslog(get_class(\$this)."::updateMOOrderLines Error updating MO lines for order ID " . \$commandeid . ": " . \$error_msg, LOG_ERR);
            // Storing error in a property might be useful if the trigger class has error handling.
            // $this->error = $error_msg; 
            return -1;
        }
    }
}
?>
