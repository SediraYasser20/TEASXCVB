<?php
include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

/**
 * Class modAftersalesservice
 * Module descriptor for AfterSalesService, managing supplier invoices with custom extra fields
 */
class modAftersalesservice extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs;
        $this->db = $db;

        // Module identification
        $this->numero = 104000;
        $this->rights_class = 'aftersalesservice';

        // Module metadata
        $this->family = 'products';
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = 'Aftersales Service - Vendor Invoices Management';
        $this->version = '1.0';
        $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);
        $this->picto = 'generic';

        // Module dependencies
        $this->depends = [];
        $this->conflictwith = [];
        $this->langfiles = ['aftersalesservice@aftersalesservice'];

        // Data directories
        $this->dirs = ['/aftersalesservice/temp'];

        // Requirements
        $this->phpmin = [7, 0];
        $this->need_dolibarr_version = [17, 0];

        // Rights definitions
        $this->rights = [];
        $r = 0;
        // Read permission
        $this->rights[$r][0] = 104001;
        $this->rights[$r][1] = 'Read aftersales data';
        $this->rights[$r][2] = 'r';
        $this->rights[$r][3] = 1; // Enabled by default for new users
        $this->rights[$r][4] = 'read';
        $this->rights[$r][5] = '';
        $r++;
        // Write permission
        $this->rights[$r][0] = 104002;
        $this->rights[$r][1] = 'Write aftersales data';
        $this->rights[$r][2] = 'w';
        $this->rights[$r][3] = 0; // Not enabled by default
        $this->rights[$r][4] = 'write';
        $this->rights[$r][5] = '';
        $r++;
    }

    /**
     * Initialize the module, including creating extra fields
     * @param string $options Options for initialization
     * @return int 0 if OK, < 0 if error
     */
    public function init($options = '')
    {
        // Create necessary extrafields for supplier invoices
        $extrafields = new ExtraFields($this->db);
        
        // Check if extrafields already exist to avoid duplicates
        $existing = $extrafields->fetch_name_optionals_label('facture_fourn', true);
        
        if (!isset($existing['exp'])) {
            $extrafields->addExtraField('exp', 'EXP', 'boolean', 1, '', 'facture_fourn', 0, 0, '', '', 1, 'Indicates if expedition is required', 1);
        }
        if (!isset($existing['reffrencesav'])) {
            $extrafields->addExtraField('reffrencesav', 'Ref SAV', 'varchar', 1, '255', 'facture_fourn', 0, 0, '', '', 1, 'Reference for after-sales service', 1);
        }
        if (!isset($existing['reglement_sav'])) {
            // Note: Define options via Dolibarr's extrafield setup in UI (Setup > Dictionaries > Extra Fields > Supplier Invoices)
            // Example: array('cash' => 'Cash', 'credit' => 'Credit Card')
            // If hardcoded options are needed, uncomment and customize below
            // $reglementOptions = array('cash' => 'Cash', 'credit' => 'Credit Card');
            // $extrafields->addExtraField('reglement_sav', 'Reglement SAV', 'select', 1, '', 'facture_fourn', 0, 0, '', array('options' => $reglementOptions), 1, 'Payment method for after-sales', 1);
            $extrafields->addExtraField('reglement_sav', 'Reglement SAV', 'select', 1, '', 'facture_fourn', 0, 0, '', array(), 1, 'Payment method for after-sales', 1);
        }
        if (!isset($existing['date_of_exp'])) {
            $extrafields->addExtraField('date_of_exp', 'Date of Exp', 'datetime', 1, '', 'facture_fourn', 0, 0, '', '', 1, 'Date of expedition', 1);
        }
        if (!isset($existing['date_of_reglement'])) {
            $extrafields->addExtraField('date_of_reglement', 'Date of Reglement', 'datetime', 1, '', 'facture_fourn', 0, 0, '', '', 1, 'Date of payment', 1);
        }
        
        // Load any required tables
        $result = $this->_load_tables('/aftersalesservice/sql/');
        return $result < 0 ? -1 : $result;
    }

    /**
     * Remove module data, including tables
     * @param string $options Options for removal
     * @return int 0 if OK, < 0 if error
     */
    public function remove($options = '')
    {
        // Remove tables on deactivation
        // Note: Extrafields are not deleted to preserve data
        $result = $this->_delete_tables();
        return $result < 0 ? -1 : $result;
    }
}