<?php

require_once DOL_DOCUMENT_ROOT .'/core/modules/DolibarrModules.class.php';

class modErpsavdz extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;
        $this->numero = 100000; // TODO: Replace with a unique module ID from https://wiki.dolibarr.org/index.php/List_of_modules_id
        $this->rights_class = 'erpsavdz';
        $this->family = "other"; // TODO: Or other family
        $this->module_position = 50;
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = "Description of my new module Erpsavdz"; // TODO: Change description
        $this->editor_name = "Your Name"; // TODO: Change this
        $this->editor_url = "https://example.com"; // TODO: Change this
        $this->version = '1.0.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->special = 0;
        $this->picto = 'erpsavdz.png'; // TODO: Add a 16x16 pictograma for module

        // Module parts (css, js, ...)
        $this->module_parts = array(
            'css' => array(),
            'js' => array(),
            'hooks' => array(),
            'triggers' => array(),
        );

        // Config page
        $this->config_page_url = array("erpsavdz_setup.php@erpsavdz");

        // Dependencies
        $this->depends = array();
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->need_dolibarr_version = array(9,0); // Minimum Dolibarr version
        $this->phpmin = array(5,3); // Minimum PHP version

        // Menus
        $this->menu = array(); // TODO: Add menu entries if needed

        // Permissions
        $this->rights = array(); // TODO: Add permissions if needed
        $r=0;

        // Extrafields
        $this->extrafields = array(
            'commande' => array( // Corresponds to 'order' or sales order object
                'savorders_sav' => array(
                    'label' => 'SAV Active', // Translation key
                    'type' => 'boolean',
                    'element' => 'commande', // Link to sales order
                    'perms' => '$user->rights->commande->lire', // Permissions
                    'position' => 100, // Order of appearance
                    'list' => 1, // Show in list views
                    'search' => 1, // Allow searching
                    'module_source' => 'erpsavdz', // Module providing this field
                    'isextrafield' => 1
                ),
                'savorders_status' => array(
                    'label' => 'SAV Status', // Translation key
                    'type' => 'sellist', // Special type for selectable list
                    'element' => 'commande',
                    'perms' => '$user->rights->commande->lire',
                    'position' => 110,
                    'list' => 1,
                    'search' => 1,
                    'module_source' => 'erpsavdz',
                    'isextrafield' => 1,
                    'param' => array('options' => array( // Define list options
                        'open' => 'Open', // Key => Value (translation key)
                        'in_progress' => 'In Progress',
                        'resolved' => 'Resolved',
                        'closed' => 'Closed'
                    ))
                ),
                'savorders_history' => array(
                    'label' => 'SAV History', // Translation key
                    'type' => 'text', // For long text
                    'element' => 'commande',
                    'perms' => '$user->rights->commande->lire',
                    'position' => 120,
                    'list' => 0, // Usually not shown in lists due to length
                    'search' => 0,
                    'module_source' => 'erpsavdz',
                    'isextrafield' => 1
                )
            )
        );

        // Tabs system
        $this->tabs = array(
            'order:+sav:SAVTabTitle:erpsavdz@erpsavdz:$user->rights->commande->lire && $conf->global->MAIN_MODULE_ORDERS_ENABLED:/erpsavdz/sav_tab.php?id=__ID__'
        );

        // Example permission
        // $this->rights[$r][0] = $this->numero + $r;
        // $this->rights[$r][1] = 'Read Erpsavdz objects';
        // $this->rights[$r][2] = 'r';
        // $this->rights[$r][3] = 1;
        // $this->rights[$r][4] = 'read';
        // $r++;
    }

    public function init($options = '')
    {
        // TODO: Load SQL files if needed
        // $this->_load_tables('/erpsavdz/sql/');

        // DolibarrModules class should handle extrafields activation
        // Ensure $this->extrafields is defined before calling parent constructor or init
        // Or, if there's a specific method to register them, call it here.
        // For example: if (method_exists($this, 'registerExtrafields')) $this->registerExtrafields();

        return $this->_init($options);
    }

    public function remove($options = '')
    {
        return $this->_remove($options);
    }
}
?>
