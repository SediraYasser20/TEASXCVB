<?php
require_once DOL_DOCUMENT_ROOT.'/core/class/dolibarrModules.class.php';

class CustomBillingModule extends DolibarrModules
{
    function __construct($db)
    {
        parent::__construct($db);
        $this->name = 'CustomBillingModule';
        $this->description = 'Custom module to classify orders as billed when invoice total >= shipment total';
        $this->version = '1.0';
        $this->numero = 100000;
    }

    function init($options = '')
    {
        $this->hooks = array('billvalidate');
        return parent::init($options);
    }

    function billValidate($parameters, &$object, &$action, $hookmanager)
    {
        global $db, $user, $langs;

        $error = 0;

        // Ensure the invoice is linked to an order
        $invoice = $object;
        if ($invoice->fk_commande > 0) {
            $order = new Commande($db);
            $order->fetch($invoice->fk_commande);

            // Calculate total invoiced amount (excluding taxes for consistency with workflow)
            $total_invoiced = 0;
            $sql = 'SELECT SUM(f.total_ht) as total FROM '.MAIN_DB_PREFIX.'facture as f';
            $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'element_element as ee ON f.rowid = ee.fk_target';
            $sql .= " WHERE ee.fk_source = ".((int) $order->id)." AND ee.sourcetype = 'commande' AND ee.targettype = 'facture'";
            $sql .= ' AND f.fk_statut > 0'; // Only validated or paid invoices
            dol_syslog(get_class($this)."::billValidate fetch invoices", LOG_DEBUG);
            $resql = $db->query($sql);
            if ($resql) {
                $obj = $db->fetch_object($resql);
                $total_invoiced = $obj->total ? $obj->total : 0;
                $db->free($resql);
            } else {
                dol_syslog(get_class($this)."::billValidate invoice fetch error: ".$db->lasterror(), LOG_ERR);
                return -1;
            }

            // Calculate total shipped amount (excluding taxes for consistency)
            $total_shipped = 0;
            $sql = 'SELECT SUM(e.total_ht) as total FROM '.MAIN_DB_PREFIX.'expedition as e';
            $sql .= ' LEFT JOIN '.MAIN_DB_PREFIX.'element_element as ee ON e.rowid = ee.fk_target';
            $sql .= " WHERE ee.fk_source = ".((int) $order->id)." AND ee.sourcetype = 'commande' AND ee.targettype = 'shipping'";
            dol_syslog(get_class($this)."::billValidate fetch shipments", LOG_DEBUG);
            $resql = $db->query($sql);
            if ($resql) {
                $obj = $db->fetch_object($resql);
                $total_shipped = $obj->total ? $obj->total : 0;
                $db->free($resql);
            } else {
                dol_syslog(get_class($this)."::billValidate shipment fetch error: ".$db->lasterror(), LOG_ERR);
                return -1;
            }

            // Classify order as billed if total invoiced >= total shipped and shipment exists
            if ($total_invoiced >= $total_shipped && $total_shipped > 0) {
                dol_syslog(get_class($this)."::billValidate classifying order as billed (invoice: $total_invoiced, shipment: $total_shipped)", LOG_DEBUG);
                $ret = $order->classifyBilled($user);
                if ($ret < 0) {
                    dol_syslog(get_class($this)."::billValidate classifyBilled error: ".$order->error, LOG_ERR);
                    return -1;
                }
            } else {
                dol_syslog(get_class($this)."::billValidate not classifying order (invoice: $total_invoiced, shipment: $total_shipped)", LOG_DEBUG);
            }
        }

        return 0;
    }
}