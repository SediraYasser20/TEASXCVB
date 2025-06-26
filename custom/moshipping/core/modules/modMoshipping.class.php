<?php
/* Copyright (C) 2024 Your Company
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Description and activation file for module MOShipping
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modMOShipping extends DolibarrModules
{
    /**
     * Constructor. Define names, constants, directories, boxes, permissions
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;

        // Id for module (must be unique).
        $this->numero = 104050;
        // Key text used to identify module (for permissions, menus, etc...)
        $this->rights_class = 'moshipping';

        // Family can be 'crm','financial','hr','projects','products','ecm','technic','interface','other'
        $this->family = "products";
        // Module position in the family on 2 digits ('01', '10', '20', ...)
        $this->module_position = '90';
        // Gives the possibility to the module, to provide his own family info and position of this family (Overwrite $this->family and $this->module_position. Avoid this)
        $this->familyinfo = array();
        // Module label (no space allowed), used if translation string 'ModuleMOShippingName' not found
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        // Module description, used if translation string 'ModuleMOShippingDesc' not found
        $this->description = "Automatically ship produced products instead of Manufacturing Orders";
        // Used only if file README.md and README-LL.md not found.
        $this->descriptionlong = "This module automatically replaces Manufacturing Orders with their produced products in shipments";

        // Possible values for version are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version string like 'x.y.z'
        $this->version = '1.0.0';
        // Key used in llx_const table to save module status enabled/disabled
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        // Name of image file used for this module.
        $this->picto = 'moshipping@moshipping';

        // Defined all module parts (triggers, login, substitutions, menus, css, etc...)
        $this->module_parts = array(
            'triggers' => 1,
            'hooks' => array('shipmentcard'),
            'substitutions' => 1
        );

        // Define hooks array for the module
        $this->hooks = array('shipmentcard');

        // Data directories to create when module is enabled.
        $this->dirs = array();

        // Config pages. Put here list of php page, stored into moshipping/admin directory, to use to setup module.
        $this->config_page_url = array("setup.php@moshipping");

        // Dependencies
        $this->hidden = false;
        $this->depends = array('modExpedition');
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->langfiles = array("moshipping@moshipping");
        $this->phpmin = array(7, 0);
        $this->need_dolibarr_version = array(11, 0);
        $this->warnings_activation = array();
        $this->warnings_activation_ext = array();

        // Constants
        $this->const = array();

        // Boxes/Widgets
        $this->boxes = array();

        // Cronjobs
        $this->cronjobs = array();

        // Permissions
        $this->rights = array();

        // Main menu entries
        $this->menu = array();
    }

    /**
     * Function called when module is enabled.
     * The init function add constants, boxes, permissions and menus
     *
     * @param string $options Options when enabling module ('', 'noboxes')
     * @return int 1 if OK, 0 if KO
     */
    public function init($options = '')
    {
        $result = $this->_load_tables('/moshipping/sql/');
        if ($result < 0) return -1;

        return $this->_init($this->const, $options);
    }

    /**
     * Function called when module is disabled.
     * Remove from database constants, boxes and permissions from Dolibarr database.
     * Data directories are not deleted
     *
     * @param string $options Options when disabling module ('', 'noboxes')
     * @return int 1 if OK, 0 if KO
     */
    public function remove($options = '')
    {
        return $this->_remove($this->const, $options);
    }
}