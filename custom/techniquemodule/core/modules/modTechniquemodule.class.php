<?php

require_once DOL_DOCUMENT_ROOT .'/core/modules/DolibarrModules.class.php';

class modTechniquemodule extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;
        $this->numero = 700000; // Choose a unique number for your module (e.g., between 500000 and 1000000)
        $this->rights_class = 'techniquemodule'; // For permissions, if you add any later
        $this->family = "technic"; // "crm", "products", "projects", "technic", "other"
        $this->module_position = 500;

        // Module properties
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = "Allows Manufacturing Orders to be represented as sellable items in Sales Orders via automated Product/Service creation.";
        $this->version = '1.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->special = 0;
        $this->picto = 'techniquemodule.png@techniquemodule'; // Will look for techniquemodule.png in module's img folder

        // Dependencies
        $this->depends = array("modProduct", "modSociete", "modCommande", "modGpaofactory"); // Or "modMrp" if you use that for MOs
        $this->requiredby = array();
        $this->conflictwith = array();

        // Config pages
        $this->config_page_url = array();

        // Data for lists
        $this->dirs = array();

        // Triggers
        $this->module_parts = array('triggers' => 1); // Crucial for enabling triggers

        // Permissions
        $this->rights = array(); // No specific permissions for now

        // Constants
        $this->const = array(); // No new constants defined by this module itself yet

        // Boxes
        $this->boxes = array();

        // Menus
        $this->menus = array(); // No new menu items
    }

    /**
     *  Function called when module is enabled.
     *  The init function is called automatically at the end of the constructor.
     *  @param  string  $options    Options when enabling module ('', 'noboxes')
     *  @return int             1 if OK, 0 if KO
     */
    public function init($options = '')
    {
        // $sql = array();
        // return $this->_init($sql, $options);
        return 1; // SUCCESS
    }

    /**
     *  Function called when module is disabled.
     *  @param  string  $options    Options when disabling module
     *  @return int             1 if OK, 0 if KO
     */
    public function remove($options = '')
    {
        // $sql = array();
        // return $this->_remove($sql, $options);
        return 1; // SUCCESS
    }
}