<?php
include_once DOL_DOCUMENT_ROOT . '/core/triggers/Triggers.class.php';

class InterfaceModTaskDiffTrigger
{
    public function __construct($db)
    {
        $this->db = $db;
    }

    public function run_trigger($action, $object, $user, $langs, $conf)
    {
        if ($action === 'TASK_MODIFY' && $object->element === 'task') {
            // Save old snapshot in a custom table or object property
            $object->old_snapshot = clone $object->oldcopy; // oldcopy auto-populated by core
        }
        return 0;
    }
}
