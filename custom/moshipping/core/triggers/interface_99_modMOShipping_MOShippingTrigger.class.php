<?php
/* Copyright (C) 2024 Your Company
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * Class of triggers for MOShipping module
 */
class InterfaceMOShippingTrigger extends DolibarrTriggers
{
    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "demo";
        $this->description = "MOShipping triggers.";
        $this->version = '1.0.0';
        $this->picto = 'moshipping@moshipping';
    }

    /**
     * Trigger name
     *
     * @return string Name of trigger file
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Trigger description
     *
     * @return string Description of trigger file
     */
    public function getDesc()
    {
        return $this->description;
    }

    /**
     * Function called when a Dolibarr business event occurs.
     * All functions "runTrigger" are triggered if file
     * is inside directory core/triggers
     *
     * @param string $action Event action code
     * @param CommonObject $object Object
     * @param User $user Object user
     * @param Translate $langs Object langs
     * @param Conf $conf Object conf
     * @return int <0 if KO, 0 if no triggered ran, >0 if OK
     */
    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        if (empty($conf->moshipping->enabled)) return 0;

        // Check if automatic replacement is enabled
        if (empty($conf->global->MOSHIPPING_ENABLE_AUTOMATIC_REPLACEMENT)) return 0;

        switch ($action) {
            case 'LINEORDER_INSERT':
            case 'LINEORDER_UPDATE':
                // Handle order line creation/update
                if ($object->element == 'orderline') {
                    $this->handleOrderLineChange($object, $user, $conf);
                }
                break;

            case 'SHIPPING_CREATE':
            case 'SHIPPING_MODIFY':
                // Handle shipment creation/modification
                if ($object->element == 'shipping') {
                    $this->handleShipmentChange($object, $user, $conf);
                }
                break;
        }

        return 0;
    }

    /**
     * Handle order line changes to detect MO products
     */
    private function handleOrderLineChange($orderline, $user, $conf)
    {
        // This will be called when order lines are added/modified
        // We'll use this to track which products are MOs
        return 1;
    }

    /**
     * Handle shipment changes to replace MO with produced products
     */
    private function handleShipmentChange($shipment, $user, $conf)
    {
        global $db;

        if (empty($shipment->lines)) return 1;

        $modified = false;

        foreach ($shipment->lines as $line) {
            if (empty($line->fk_product)) continue;

            // Check if this product is actually a Manufacturing Order
            $sql = "SELECT mo.rowid, mo.ref, mo.fk_product as produced_product_id, mo.qty";
            $sql .= " FROM ".MAIN_DB_PREFIX."mrp_mo as mo";
            $sql .= " INNER JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = mo.fk_product";
            $sql .= " WHERE mo.ref = (SELECT ref FROM ".MAIN_DB_PREFIX."product WHERE rowid = ".(int)$line->fk_product.")";
            $sql .= " AND mo.status >= 2"; // Only validated MOs

            $resql = $db->query($sql);
            if ($resql && $db->num_rows($resql) > 0) {
                $obj = $db->fetch_object($resql);
                
                if ($obj->produced_product_id && $obj->produced_product_id != $line->fk_product) {
                    // Replace the MO product with the produced product
                    $line->fk_product = $obj->produced_product_id;
                    $line->product_ref = null; // Will be refetched
                    $line->product_label = null; // Will be refetched
                    
                    // Update the line in database
                    $sql_update = "UPDATE ".MAIN_DB_PREFIX."expeditiondet";
                    $sql_update .= " SET fk_product = ".(int)$obj->produced_product_id;
                    $sql_update .= " WHERE rowid = ".(int)$line->rowid;
                    
                    $db->query($sql_update);
                    $modified = true;

                    // Log the replacement if enabled
                    if (!empty($conf->global->MOSHIPPING_LOG_REPLACEMENTS)) {
                        dol_syslog("MOShipping: Replaced MO product ".$line->fk_product." with produced product ".$obj->produced_product_id." in shipment ".$shipment->ref);
                    }
                }
            }
        }

        if ($modified) {
            // Refresh shipment object to reflect changes
            $shipment->fetch($shipment->id);
        }

        return 1;
    }
}