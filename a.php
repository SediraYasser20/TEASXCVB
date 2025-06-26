<?php
define('NOCSRFCHECK', 1);
define('NOLOGIN', 1);

require __DIR__ . '/htdocs/master.inc.php';

global $db;

$sql = "
    INSERT INTO ".MAIN_DB_PREFIX."const
        (name, value, type, section, note, visible, entity)
    VALUES
        ('MAIN_MODULE_SAVTAB', '0', 'chaine', 'modules', 'Disabled via script', 0, 1)
    ON DUPLICATE KEY UPDATE value = '0'
";
$res = $db->query($sql);
if (!$res) {
    die("Error disabling module flag: " . $db->lasterror() . "\n");
}

if (!class_exists('modSavTab')) {
    require_once DOL_DOCUMENT_ROOT . '/custom/savtab/core/modules/modSavTab.class.php';
}

if (class_exists('modSavTab')) {
    $mod = new modSavTab($db);
    $mod->remove('');
}

// Clear Smarty cache
$cachedir = __DIR__ . '/htdocs/admin/smarty/templates_c';
if (is_dir($cachedir)) {
    foreach (glob($cachedir . '/*.tpl.php') as $file) {
        @unlink($file);
    }
}

echo "SAVTAB module disabled successfully.\n";
