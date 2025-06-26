<?php
/* Copyright (C) 2024 Your Company
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * Class ActionsMyModule
 */
class ActionsMyModule
{
    /**
     * @var DoliDB Database handler.
     */
    public $db;

    /**
     * @var string Error code (or message)
     */
    public $error = '';

    /**
     * @var array Errors
     */
    public $errors = array();

    /**
     * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
     */
    public $results = array();

    /**
     * @var string String displayed by executeHook() immediately after return
     */
    public $resprints;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Execute action
     *
     * @param array $parameters Array of parameters
     * @param CommonObject $object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param string $action 'add', 'update', 'view'
     * @return int <0 if KO,
     *                =0 if OK but we want to process standard actions too,
     *                >0 if OK and we want to replace standard actions.
     */
    public function getNomUrl($parameters, &$object, &$action)
    {
        global $db, $langs, $conf, $user;
        $this->resprints = '';
        return 0;
    }

    /**
     * Overloading the doActions function : replacing the parent's function with the one below
     *
     * @param array $parameters Hook metadatas (context, etc...)
     * @param CommonObject $object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param string $action Current action (if set). Generally create or edit or null
     * @param HookManager $hookmanager Hook manager propagated to allow calling another hook
     * @return int < 0 on error, 0 on success, 1 to replace standard code
     */
    public function doActions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs, $db;

        $error = 0; // Error counter

        /* print_r($parameters); print_r($object); echo "action: " . $action; */
        if (in_array($parameters['currentcontext'], array('shipmentcard'))) {
            // Handle MO to product replacement during shipment creation
            if ($action == 'add_line' && !empty($conf->global->MOSHIPPING_ENABLE_AUTOMATIC_REPLACEMENT)) {
                $this->handleMOReplacementInShipment($object);
            }
        }

        if (!$error) {
            $this->results = array('myreturn' => 999);
            $this->resprints = 'A text to show';
            return 0; // or return 1 to replace standard code
        } else {
            $this->errors[] = 'Error message';
            return -1;
        }
    }

    /**
     * Handle MO replacement in shipment
     */
    private function handleMOReplacementInShipment($shipment)
    {
        global $db;

        // This method will be called when adding lines to shipment
        // Additional logic can be added here if needed for real-time replacement
    }

    /**
     * Overloading the doMassActions function : replacing the parent's function with the one below
     *
     * @param array $parameters Hook metadatas (context, etc...)
     * @param CommonObject $object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param string $action Current action (if set). Generally create or edit or null
     * @param HookManager $hookmanager Hook manager propagated to allow calling another hook
     * @return int < 0 on error, 0 on success, 1 to replace standard code
     */
    public function doMassActions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs, $db;

        $error = 0; // Error counter

        /* print_r($parameters); print_r($object); echo "action: " . $action; */
        if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {        // do something only for the context 'somecontext1' or 'somecontext2'
            // Do what you want here...
            // You can for example call global vars like $fieldstosearchall to overwrite them, or update database depending on $action and $_POST values.
        }

        if (!$error) {
            $this->results = array('myreturn' => 999);
            $this->resprints = 'A text to show';
            return 0; // or return 1 to replace standard code
        } else {
            $this->errors[] = 'Error message';
            return -1;
        }
    }

    /**
     * Overloading the addMoreMassActions function : replacing the parent's function with the one below
     *
     * @param array $parameters Hook metadatas (context, etc...)
     * @param CommonObject $object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param string $action Current action (if set). Generally create or edit or null
     * @param HookManager $hookmanager Hook manager propagated to allow calling another hook
     * @return int < 0 on error, 0 on success, 1 to replace standard code
     */
    public function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs, $db;

        $error = 0; // Error counter
        $disabled = 1;

        /* print_r($parameters); print_r($object); echo "action: " . $action; */
        if (in_array($parameters['currentcontext'], array('somecontext1', 'somecontext2'))) {		// do something only for the context 'somecontext1' or 'somecontext2'
            $this->resprints = '<option value="0"'.($disabled ? ' disabled="disabled"' : '').'>'.$langs->trans("MOShippingMassAction").'</option>';
        }

        if (!$error) {
            return 0; // or return 1 to replace standard code
        } else {
            $this->errors[] = 'Error message';
            return -1;
        }
    }

    /**
     * Execute action completeTabsHead
     *
     * @param array $parameters Array of parameters
     * @param CommonObject $object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param string $action 'add', 'update', 'view'
     * @param Hookmanager $hookmanager hookmanager
     * @return int <0 if KO,
     *                =0 if OK but we want to process standard actions too,
     *                >0 if OK and we want to replace standard actions.
     */
    public function completeTabsHead(&$parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $conf, $user;

        if (!isset($parameters['object']->element)) {
            return 0;
        }
        if ($parameters['object']->element == 'myobject') {
            $langs->load("moshipping@moshipping");
            // Implement here what you want to do
        }

        return 0;
    }
}