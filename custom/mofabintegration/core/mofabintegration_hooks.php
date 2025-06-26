<?php
/**
 * \file    htdocs/custom/mofabintegration/core/mofabintegration_hooks.php
 * \ingroup mofabintegration
 * \brief   Hook file for MOFabIntegration module
 */
class mofabintegration_hooks
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf;
        dol_syslog("MOFabIntegration: formObjectOptions - context: ".$parameters['currentcontext'].", action: ".$action.", colname: ".(isset($parameters['colname']) ? $parameters['colname'] : 'N/A'), LOG_DEBUG);
        if ($parameters['currentcontext'] == 'ordercard' && $action == 'create' && !empty($conf->global->MOFABINTEGRATION_ENABLED) && (isset($parameters['colname']) && $parameters['colname'] == 'idprod')) {
            $this->addMoOptions($parameters, $hookmanager);
        }
        return 0;
    }

    public function formAddObjectLine($parameters, &$object, &$action, $hookmanager)
    {
        global $conf;
        dol_syslog("MOFabIntegration: formAddObjectLine - context: ".$parameters['currentcontext'].", action: ".$action, LOG_DEBUG);
        if ($parameters['currentcontext'] == 'ordercard' && $action == 'create' && !empty($conf->global->MOFABINTEGRATION_ENABLED)) {
            $this->addMoOptions($parameters, $hookmanager);
        }
        return 0;
    }

    public function printInputField($parameters, &$object, &$action, $hookmanager)
    {
        global $conf;
        dol_syslog("MOFabIntegration: printInputField - context: ".$parameters['currentcontext'].", action: ".$action.", colname: ".(isset($parameters['colname']) ? $parameters['colname'] : 'N/A'), LOG_DEBUG);
        if ($parameters['currentcontext'] == 'ordercard' && $action == 'create' && !empty($conf->global->MOFABINTEGRATION_ENABLED) && (isset($parameters['colname']) && $parameters['colname'] == 'idprod')) {
            $this->addMoOptions($parameters, $hookmanager);
        }
        return 0;
    }

    protected function addMoOptions(&$parameters, $hookmanager)
    {
        global $db;
        $sql = "SELECT rowid, ref, label FROM llx_mrp_mo WHERE status = 1";
        $resql = $db->query($sql);
        if ($resql) {
            $mo_options = array();
            $num = $db->num_rows($resql);
            dol_syslog("MOFabIntegration: Found $num validated MOs", LOG_DEBUG);
            while ($row = $db->fetch_object($resql)) {
                $mo_options['MO:'.$row->rowid] = 'MO: '.$row->ref.' - '.($row->label ? $row->label : 'No Label');
            }
            dol_syslog("MOFabIntegration: Original options: ".print_r($parameters['options'], true), LOG_DEBUG);
            if (!empty($parameters['options'])) {
                $parameters['options'] = array_merge($parameters['options'], $mo_options);
            } else {
                $parameters['options'] = $mo_options;
                dol_syslog("MOFabIntegration: No original options, using only MOs", LOG_WARNING);
            }
            $hookmanager->resArray['options'] = $parameters['options'];
            dol_syslog("MOFabIntegration: Updated options: ".print_r($parameters['options'], true), LOG_DEBUG);
        } else {
            dol_syslog("MOFabIntegration: Error fetching validated MOs: ".$db->lasterror(), LOG_ERR);
            dol_print_error($db);
        }
    }

    public function createLine($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $conf;

        if ($parameters['currentcontext'] == 'ordercard' && $action == 'addline' && !empty($conf->global->MOFABINTEGRATION_ENABLED)) {
            $fk_product = GETPOST('idprod', 'alpha');
            if (strpos($fk_product, 'MO:') === 0) {
                $mo_id = str_replace('MO:', '', $fk_product);
                if (!is_numeric($mo_id)) {
                    dol_syslog("MOFabIntegration: Invalid Manufacturing Order ID: ".$mo_id, LOG_ERR);
                    dol_print_error('', 'Invalid Manufacturing Order ID: '.$mo_id);
                    return -1;
                }
                $sql = "SELECT fk_product FROM llx_mrp_mo WHERE rowid = ".intval($mo_id)." AND status = 1";
                $resql = $this->db->query($sql);
                if ($resql && $row = $this->db->fetch_object($resql)) {
                    if ($row->fk_product) {
                        $_POST['idprod'] = $row->fk_product;
                        if (isset($object->lines[$parameters['lineid']])) {
                            $line = &$object->lines[$parameters['lineid']];
                            $line->array_options['options_mo_ref'] = $mo_id;
                            $line->update();
                        } else {
                            $_POST['mo_ref'] = $mo_id;
                        }
                    } else {
                        dol_syslog("MOFabIntegration: No finished product defined for MO ID: ".$mo_id, LOG_ERR);
                        dol_print_error('', 'No finished product defined for Manufacturing Order ID: '.$mo_id);
                        return -1;
                    }
                } else {
                    dol_syslog("MOFabIntegration: Error fetching validated MO: ".$this->db->lasterror(), LOG_ERR);
                    dol_print_error($this->db, 'Error fetching Manufacturing Order: '.$this->db->lasterror());
                    return -1;
                }
            }
        }
        return 0;
    }
}
?>