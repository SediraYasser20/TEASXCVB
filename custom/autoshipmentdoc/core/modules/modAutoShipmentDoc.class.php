<?php

require_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

class modAutoShipmentDoc extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;
        $this->numero = 1000000; // TODO: Replace with a unique module ID from https://wiki.dolibarr.org/index.php/List_of_modules_id
        $this->rights_class = 'autoshipmentdoc';
        $this->family = "crm";
        $this->module_position = 500;
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = $langs->trans("AutoShipmentDocDescription");
        $this->version = '1.0';
        $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
        $this->special = 0;
        $this->picto = 'generic.png';

        // Module parts (triggers, login, substitutions, menus, etc.)
        $this->module_parts = array(
            'triggers' => 1,
            'hooks' => array(
                // 'hookcontext1', // Uncomment and add hook contexts if needed
                // 'hookcontext2'
            )
        );

        // Data for menus
        $this->menus = array(); // TODO: Add menu entries if needed

        // Config page
        $this->config_page_url = array(); // TODO: Add config page URL if needed

        // Permissions
        $this->rights = array(); // TODO: Add permissions if needed
        $this->rights_class = 'autoshipmentdoc';

        // Constants
        $this->const = array(); // TODO: Add constants if needed

        // Boxes
        $this->boxes = array(); // TODO: Add boxes if needed

        // Cron jobs
        $this->cronjobs = array(); // TODO: Add cron jobs if needed
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
