<?php

class ActionsAutoshipmentdoc
{
    /**
     * Constructor
     *
     * @param   DoliDB      $db      Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
        $this->output = '';
        $this->errors = array();
    }

    // Add action methods here if needed in the future
    // For example, methods for hooks would go here.

    /**
     *  Example of a method that could be called by a hook
     *
     * @param   array        $parameters    Hook metadatas (context, etc...)
     * @param   CommonObject &$object       The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string       &$action       Current action (if set). Generally create or edit or null
     * @param   HookManager  $hookmanager   Hook manager propagated to allow calling another hook
     * @return  int                         <0 if error, 0 if success, >0 if success and replace standard code
     */
    /*
    public function exampleHookMethod($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $conf, $user;

        $context = $parameters['context']; // Example: 'invoicecard', 'thirdpartycard', etc.
        $hookname = $parameters['hookname']; // Example: 'doActions', 'formObjectOptions', etc.

        // Your code here
        // if (in_array('yourcontext', explode(':', $context))) {
        //    // Do something specific for 'yourcontext'
        // }

        // $this->output .= '<div>My custom output from hook</div>';
        // $this->error[] = 'An error occurred';
        // return 0; // 0 = hook action is OK, >0 = hook action is OK and replace standard code, <0 = hook action is KO
    }
    */
}
?>
