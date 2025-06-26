<?php
class InterfaceTaskToAgenda extends DolibarrTriggers
{
    public $family = 'project';
    public $description = 'Logs task events to the Agenda';
    public $version = '1.0';

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function runTrigger($action, $object, $user, $langs, $conf)
    {
        if (in_array($action, ['TASK_CREATE', 'TASK_MODIFY', 'TASK_DELETE'])) {
            require_once DOL_DOCUMENT_ROOT.'/core/class/actioncomm.class.php';

            $event = new ActionComm($this->db);
            $event->type_code = 'AC_OTH'; // Or 'AC_TEL', 'AC_MEETING'
            $event->label = 'Task ' . str_replace('TASK_', '', $action) . ': ' . $object->ref;
            $event->note = 'Triggered by task event';
            $event->datep = dol_now(); // Current time
            $event->fk_element = $object->id;
            $event->elementtype = 'task';
            $event->fk_user_author = $user->id;
            $event->create($user);
        }

        return 0;
    }
}
