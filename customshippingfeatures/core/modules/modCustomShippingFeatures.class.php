<?php

require_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

class modCustomShippingFeatures extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;
        $this->numero = 700000; // TODO: Choose a unique number not used by other modules

        $this->rights_class = 'customshippingfeatures';
        $this->family = "crm"; // Choose a category: crm, logistic, ...
        $this->module_position = 500;
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = "Adds custom features related to shipping, including automatic email notifications.";
        $this->version = '1.0';
        $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
        $this->special = 0;
        $this->picto = 'dolly'; // Default picto

        // Module parts
        $this->module_parts = array(
            'triggers' => 1,
            'login' => 0,
            'substitutions' => 0,
            'menus' => 0,
            'theme' => 0,
            'tpl' => 0,
            'barcode' => 0,
            'models' => 0,
            'css' => 0,
            'js' => 0,
            'workflow' => 0,
            'perms'=>0
        );

        // Config page
        $this->config_page_url = array("setup.php@".$this->name);

        // Dependencies
        $this->depends = array();
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->langfiles = array("customshippingfeatures@customshippingfeatures");

        // Triggers
        $this->triggers_array = array(
            'SHIPPING_VALIDATE'=>array(
                'customshippingfeatures/core/triggers/interface_customshippingfeatures_autoshippingemail.class.php'
            )
        );

        // Constants
        $this->const = array();

        // Permissions
        $this->rights = array();
        $this->rights_class = 'customshippingfeatures'; // Set to 'system' if your module must be able to do everything

        // Menus
        $this->menus = array();
    }

    public function init($options = '')
    {
        // $sql = array();
        // return $this->_init($sql, $options);
        return 1; // No SQL needed for init for now
    }

    public function remove($options = '')
    {
        // $sql = array();
        // return $this->_remove($sql, $options);
        return 1; // No SQL needed for remove for now
    }
}
?>
