<?php
/**
 * \file    htdocs/custom/mofabintegration/core/modules/modMOFabIntegration.class.php
 * \ingroup mofabintegration
 * \brief   Description and activation class for module MOFabIntegration
 */
dol_include_once('/mofabintegration/lib/mofabintegration.lib.php');

class modMOFabIntegration extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;
        $this->numero = 500000; // Unique module number
        $this->rights_class = 'mofabintegration';
        $this->family = "other";
        $this->module_position = '90';
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = "Integrates validated Manufacturing Orders into Sales Order product/service dropdown in Dolibarr v21.0.1";
        $this->version = '1.0.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto = 'mrp@mofabintegration';
        $this->module_parts = array(
            'hooks' => array('ordercard', 'formcard', 'printInputField')
        );
        $this->dirs = array("/mofabintegration/temp");
        $this->config_page_url = array("mofabintegration_setup.php@mofabintegration");
        $this->depends = array("modCommande", "modMRP");
        $this->requiredby = array();
        $this->const = array(
            array('MOFABINTEGRATION_ENABLED', 'chaine', '1', 'Enable MO integration in sales order dropdown', 1),
            array('MOFABINTEGRATION_USE_AJAX', 'chaine', '0', 'Enable AJAX search for MO dropdown', 1)
        );
        $this->langfiles = array("mofabintegration@mofabintegration");
    }

    public function init($options = '')
    {
        $sql = array();
        return $this->_init($sql, $options);
    }

    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}
?>