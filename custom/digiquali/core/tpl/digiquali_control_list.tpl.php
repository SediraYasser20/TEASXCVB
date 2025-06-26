<?php
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/stock/class/productlot.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT . '/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/commande/class/commande.class.php';
require_once DOL_DOCUMENT_ROOT . '/contrat/class/contrat.class.php';
require_once DOL_DOCUMENT_ROOT . '/ticket/class/ticket.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formprojet.class.php';

require_once __DIR__.'/../../class/sheet.class.php';

$sheet = new Sheet($db);
// Build and execute select
// --------------------------------------------------------------------
$sql = 'SELECT DISTINCT ';
foreach ($object->fields as $key => $val)
{
    if (!array_key_exists($key, $elementElementFields)) {
        $sql .= 't.' . $key . ', ';
    }
}

// Add fields from extrafields
if (!empty($extrafields->attributes[$object->table_element]['label']) && is_array($extrafields->attributes[$object->table_element]['label'])) {
    foreach ($extrafields->attributes[$object->table_element]['label'] as $key => $val) {
        if (isset($extrafields->attributes[$object->table_element]['type'][$key]) && $extrafields->attributes[$object->table_element]['type'][$key] != 'separate') {
            $sql .= "ef.".$key.' as options_'.$key.', ';
        }
    }
}
// Add fields from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListSelect', $parameters, $object);
$sql .= preg_replace('/^,/', '', $hookmanager->resPrint);
$sql = preg_replace('/,\s*$/', '', $sql);
foreach($elementElementFields as $genericName => $elementElementName) {
    if (GETPOST('search_' . $genericName) > 0 || $fromtype == $elementElementName) {
        $id_tosearch = GETPOST('search' . $genericName) ?: $fromid;
        $sql .= ',' .  $elementElementName . '.fk_source, ';
    }
}
$sql = rtrim($sql, ', ');
if (array_key_exists($sortfield, $elementElementFields) && !preg_match('/' . 'LEFT JOIN ' . MAIN_DB_PREFIX . 'element_element as ' . $elementElementFields[$sortfield] . '/', $sql)) {
    $sql .= ',' .  $elementElementFields[$sortfield] . '.fk_source';
}
$sql .= " FROM ".MAIN_DB_PREFIX.$object->table_element." as t";
if (!empty($conf->categorie->enabled)) {
    $sql .= Categorie::getFilterJoinQuery('control', "t.rowid");
}
if (!empty($extrafields->attributes[$object->table_element]['label']) && is_array($extrafields->attributes[$object->table_element]['label'])) {
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX.$object->table_element."_extrafields as ef on (t.rowid = ef.fk_object)";
}

foreach($elementElementFields as $genericName => $elementElementName) {
    if (GETPOST('search_'.$genericName) > 0 || $fromtype == $elementElementName) {
        $id_to_search = GETPOST('search_'.$genericName) ?: $fromid;
        $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'element_element as '. $elementElementName .' on ('. $elementElementName .'.fk_source = ' . $id_to_search . ' AND '. $elementElementName .'.sourcetype="'. $elementElementName .'" AND '. $elementElementName .'.targettype = "digiquali_control")';
    }
}

if (array_key_exists($sortfield,$elementElementFields) && !preg_match('/' . 'LEFT JOIN ' . MAIN_DB_PREFIX . 'element_element as '. $elementElementFields[$sortfield] .'/', $sql)) {
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'element_element as '. $elementElementFields[$sortfield] .' on ( '. $elementElementFields[$sortfield] .'.sourcetype="'. $elementElementFields[$sortfield] .'" AND '. $elementElementFields[$sortfield] .'.targettype = "digiquali_control" AND '. $elementElementFields[$sortfield] .'.fk_target = t.rowid)';
}

// Add table from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListFrom', $parameters, $object);
$sql .= $hookmanager->resPrint;
if ($object->ismultientitymanaged == 1) $sql .= " WHERE t.entity IN (".getEntity($object->element).")";
else $sql .= " WHERE 1 = 1".$sqlfilter;
$sql .= ' AND status > -1';

foreach($elementElementFields as $genericName => $elementElementName) {
    if (GETPOST('search_'.$genericName) > 0 || $fromtype == $elementElementName) {
        $sql .= ' AND t.rowid = '. $elementElementName .'.fk_target ';
    }
}

foreach ($search as $key => $val) {
    if (!array_key_exists($key, $elementElementFields)) {
        if (array_key_exists($key, $object->fields)) {
            if ($key == 'status' && $val == 'specialCase') {
                $newStatus = [Control::STATUS_DRAFT, Control::STATUS_VALIDATED, Control::STATUS_LOCKED];
                if (!empty($newStatus)) {
                    $sql .= natural_search($key, implode(',', $newStatus), 2);
                }
                continue;
            } elseif ($key == 'status' && $search[$key] == -1) {
                continue;
            }
            if ($key == 'verdict' && $search[$key] == 0) {
                continue;
            }
            $mode_search = (isset($object->fields[$key]['type']) && ($object->isInt($object->fields[$key]) || $object->isFloat($object->fields[$key])) ? 1 : 0);
            if ((isset($object->fields[$key]['type']) && (strpos($object->fields[$key]['type'], 'integer:') === 0 || strpos($object->fields[$key]['type'], 'sellist:') === 0)) || !empty($object->fields[$key]['arrayofkeyval'])) {
                if ($search[$key] == '-1' || ($search[$key] === '0' && (empty($object->fields[$key]['arrayofkeyval']) || !array_key_exists('0', $object->fields[$key]['arrayofkeyval'])))) {
                    $search[$key] = '';
                }
                $mode_search = 2;
            }
            if ($key == 'verdict' && $search[$key] == 3) {
                $sql .= ' AND (verdict IS NULL)';
            }
            else if ($search[$key] != '') {
                $sql .= natural_search($key, $search[$key], (($key == 'status') ? 2 : $mode_search));
            }
        } else {
            if (preg_match('/(_dtstart|_dtend)$/', $key) && $search[$key] != '') {
                $columnName = preg_replace('/(_dtstart|_dtend)$/', '', $key);
                if (isset($object->fields[$columnName]['type']) && preg_match('/^(date|timestamp|datetime)/', $object->fields[$columnName]['type'])) {
                    if (preg_match('/_dtstart$/', $key)) {
                        $sql .= " AND t.".$columnName." >= '".$db->idate($search[$key])."'";
                    }
                    if (preg_match('/_dtend$/', $key)) {
                        $sql .= " AND t." . $columnName . " <= '" . $db->idate($search[$key]) . "'";
                    }
                }
            }
        }
    }
}

if ($searchAll) $sql .= natural_search(array_keys($fieldstosearchall), $searchAll);

if (!empty($conf->categorie->enabled)) {
    $sql .= Categorie::getFilterSelectQuery('control', "t.rowid", $search_category_array);
}

// Add where from extra fields
include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_search_sql.tpl.php';
// Add where from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListWhere', $parameters, $object);
$sql .= $hookmanager->resPrint;

if (array_key_exists($sortfield, $elementElementFields)) {
    $sql .= ' ORDER BY '. $elementElementFields[$sortfield] .'.fk_source ' . $sortorder;
} else {
    if ($sortfield == 'days_remaining_before_next_control') {
        $sql .= $db->order('next_control_date', $sortorder);
    } else {
        $sql .= $db->order($sortfield, $sortorder);
    }
}
// Count total nb of records
$nbtotalofrecords = '';
if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST)) {
    $resql = $db->query($sql);
    $nbtotalofrecords = $db->num_rows($resql);
    if (($page * $limit) > $nbtotalofrecords) {
        $page = 0;
        $offset = 0;
    }
}
// if total of record found is smaller than limit, no need to do paging and to restart another select with limits set.
if (is_numeric($nbtotalofrecords) && ($limit > $nbtotalofrecords || empty($limit))) {
    $num = $nbtotalofrecords;
} else {
    if ($limit) $sql .= $db->plimit($limit + 1, $offset);

    $resql = $db->query($sql);
    if (!$resql) {
        dol_print_error($db);
        exit;
    }

    $num = $db->num_rows($resql);
}

// Direct jump if only one record found
if ($num == 1 && !empty($conf->global->MAIN_SEARCH_DIRECT_OPEN_IF_ONLY_ONE) && $searchAll && !$page)
{
    $obj = $db->fetch_object($resql);
    $id = $obj->rowid;
    header("Location: ".dol_buildpath('/digiquali/view/control/control_card.php', 1).'?id='.$id);
    exit;
}

// Output page
// --------------------------------------------------------------------

$arrayofselected = is_array($toselect) ? $toselect : array();
$extraparams = $fromtype && $fromid ? '?fromtype=' . $fromtype . '&fromid=' . $fromid : '';

$param = '';
if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) $param .= '&contextpage='.urlencode($contextpage);
$param .= $fromtype && $fromid ? '&fromtype=' . $fromtype . '&fromid=' . $fromid : '';
if ($limit > 0 && $limit != $conf->liste_limit) $param .= '&limit='.urlencode($limit);
foreach ($search as $key => $val)
{
    if (is_array($search[$key]) && count($search[$key])) foreach ($search[$key] as $skey) $param .= '&search_'.$key.'[]='.urlencode($skey);
    else $param .= '&search_'.$key.'='.urlencode($search[$key]);
}
if ($optioncss != '')     $param .= '&optioncss='.urlencode($optioncss);
if ($source != '') {
    $param .= '&source=' . urlencode($source);
}
// Add $param from extra fields
include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_search_param.tpl.php';
// Add $param from hooks
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListSearchParam', $parameters, $object);
$param .= $hookmanager->resPrint;

// List of mass actions available
$arrayofmassactions = ['prearchive' => '<span class="fas fa-archive paddingrightonly"></span>' . $langs->trans('Archive')];
if ($permissiontodelete) $arrayofmassactions['predelete'] = '<span class="fa fa-trash paddingrightonly"></span>'.$langs->trans("Delete");
if (GETPOST('nomassaction', 'int') || in_array($massaction, array('presend', 'predelete'))) $arrayofmassactions = array();
$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].$extraparams.'">'."\n";
if ($optioncss != '') print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';
print '<input type="hidden" name="source" value="' . $source . '">';
if (GETPOSTISSET('id')) {
    print '<input type="hidden" name="id" value="'.GETPOST('id','int').'">';
}

$fromurl = '';
if (!empty($fromtype)) {
    $fromurl = '&fromtype=' . $fromtype . '&fromid=' . $fromid;
}

$newCardButton = dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', dol_buildpath('/digiquali/view/control/control_card.php', 1) . '?action=create&source=' . $source . $fromurl, '', $permissiontoadd);

print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'object_'.$object->picto, 0, $newCardButton, '', $limit, 0, 0, 1);

if ($massaction == 'prearchive') {
    print $form->formconfirm($_SERVER["PHP_SELF"], $langs->trans('ConfirmMassArchive'), $langs->trans('ConfirmMassArchivingQuestion', count($toselect)), 'archive', null, '', 0, 200, 500, 1);
}

// Add code for pre mass action (confirmation or email presend form)
$topicmail = "SendControlRef";
$modelmail = "control";
$objecttmp = new Control($db);
$trackid = 'xxxx'.$object->id;
include DOL_DOCUMENT_ROOT . '/core/tpl/massactions_pre.tpl.php';

if ($searchAll)
{
    foreach ($fieldstosearchall as $key => $val) $fieldstosearchall[$key] = $langs->trans($val);
    print '<div class="divsearchfieldfilter">'.$langs->trans("FilterOnInto", $searchAll).join(', ', $fieldstosearchall).'</div>';
}

$moreforfilter = '';

// Filter on categories
if (!empty($conf->categorie->enabled) && $user->rights->categorie->lire && $source != 'pwa') {
    $formcategory = new FormCategory($db);
    $moreforfilter .= $formcategory->getFilterBox('control', $search_category_array);
}

$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldPreListTitle', $parameters, $object);
if (empty($reshook)) $moreforfilter .= $hookmanager->resPrint;
else $moreforfilter = $hookmanager->resPrint;

if (!empty($moreforfilter))
{
    print '<div class="liste_titre liste_titre_bydiv centpercent">';
    print $moreforfilter;
    print '</div>';
}

$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;

$signatoriesInDictionary = saturne_fetch_dictionary('c_' . $object->element . '_attendants_role');
if (is_array($signatoriesInDictionary) && !empty($signatoriesInDictionary)) {
    $customFieldsPosition = 111;
    foreach ($signatoriesInDictionary as $signatoryInDictionary) {
        $arrayfields[$signatoryInDictionary->ref] = ['label' => $signatoryInDictionary->ref, 'checked' => $source == 'pwa' ? 0 : 1, 'position' => $customFieldsPosition++, 'css' => 'minwidth300 maxwidth500 widthcentpercentminusxx'];
    }
}

$arrayfields['QuestionAnswered']  = ['label' => 'QuestionAnswered', 'position' => 66, 'css' => 'center minwidth200 maxwidth250 widthcentpercentminusxx'];
$arrayfields['LastStatusDate']    = ['label' => 'LastStatusDate', 'position' => 67, 'css' => 'center minwidth200 maxwidth300 widthcentpercentminusxx'];
$arrayfields['SocietyAttendants'] = ['label' => 'SocietyAttendants', 'checked' => $source == 'pwa' ? 0 : 1, 'position' => 115, 'css' => 'minwidth300 maxwidth500 widthcentpercentminusxx'];

$selectedfields = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage);
$selectedfields .= (count($arrayofmassactions) ? $form->showCheckAddButtons('checkforselect', 1) : '');

// Fix: Ensure fk_product field is properly configured
$object->fields['fk_product'] = isset($object->fields['fk_product']) ? $object->fields['fk_product'] : [
    'type' => 'integer:Product:product/class/product.class.php:1',
    'label' => 'Product',
    'enabled' => 1,
    'visible' => 1,
    'position' => 50,
];

$object->fields['days_remaining_before_next_control'] = isset($arrayfields['t.days_remaining_before_next_control']) ? $arrayfields['t.days_remaining_before_next_control'] : [
    'label' => 'DaysRemainingBeforeNextControl',
    'checked' => 1,
    'position' => 100,
    'css' => 'center',
];

$object->fields = dol_sort_array($object->fields, 'position');

$signatoriesInDictionary = saturne_fetch_dictionary('c_' . $object->element . '_attendants_role');
if (is_array($signatoriesInDictionary) && !empty($signatoriesInDictionary)) {
    foreach ($signatoriesInDictionary as $signatoryInDictionary) {
        $object->fields['Custom'][$signatoryInDictionary->ref] = $arrayfields[$signatoryInDictionary->ref];
    }
}

$object->fields['Custom']['QuestionAnswered']  = $arrayfields['QuestionAnswered'];
$object->fields['Custom']['LastStatusDate']    = $arrayfields['LastStatusDate'];
$object->fields['Custom']['SocietyAttendants'] = $arrayfields['SocietyAttendants'];

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal liste'.($moreforfilter ? " listwithfilterbefore" : "").'">'."\n";

// Fields title search
// --------------------------------------------------------------------
print '<tr class="liste_titre">';

foreach ($object->fields as $key => $val)
{
    $cssforfield = (empty($val['css']) ? '' : $val['css']);
    if ($key == 'status') $cssforfield .= ($cssforfield ? ' ' : '').'center';
    elseif (isset($val['type']) && in_array($val['type'], array('date', 'datetime', 'timestamp'))) $cssforfield .= ($cssforfield ? ' ' : '').'center';
    elseif (isset($val['type']) && in_array($val['type'], array('timestamp'))) $cssforfield .= ($cssforfield ? ' ' : '').'nowrap';
    elseif (isset($val['type']) && in_array($val['type'], array('double(24,8)', 'double(6,3)', 'integer', 'real', 'price')) && (isset($val['label']) && $val['label'] != 'TechnicalID')) $cssforfield .= ($cssforfield ? ' ' : '').'right';
    if (array_key_exists('t.'.$key, $arrayfields) && !empty($arrayfields['t.'.$key]['checked']))
    {
        print '<td class="liste_titre'.($cssforfield ? ' '.$cssforfield : '').'">';
        if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
            print $form->selectarray('search_' . $key, $val['arrayofkeyval'], $search[$key], $val['notnull'], 0, 0, '', 1, 0, 0, '', 'minwidth200', 1);
        }
        elseif ($key == 'fk_sheet') {
            print $sheet->selectSheetList(GETPOST('fromtype') == 'fk_sheet' ? GETPOST('fromid') : ($search['fk_sheet'] ?: 0), 'search_fk_sheet', 's.type = ' . '"' . $object->element . '"');
        }
        elseif (isset($val['type']) && (strpos($val['type'], 'integer:') === 0)) {
            print $object->showInputField($val, $key, $search[$key], '', '', 'search_', 'minwidth100 maxwidth125 widthcentpercentminusxx', 1);
        } elseif (!isset($val['type']) || !preg_match('/^(date|timestamp)/', $val['type'])) {
            print '<input type="text" class="flat maxwidth75" name="search_'.$key.'" value="'.dol_escape_htmltag($search[$key]).'">';
        }
        print '</td>';
    } elseif ($key == 'Custom') {
        foreach ($val as $resource) {
            if (isset($resource['checked']) && $resource['checked']) {
                print '<td class="liste_titre ' . (isset($resource['css']) ? $resource['css'] : '') . '"></td>';
            }
        }
    }
}
// Extra fields
include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_search_input.tpl.php';

// Fields from hook
$parameters = array('arrayfields'=>$arrayfields);
$reshook = $hookmanager->executeHooks('printFieldListOption', $parameters, $object);
print $hookmanager->resPrint;
// Action column
print '<td class="liste_titre maxwidthsearch">';
$searchpicto = $form->showFilterButtons();
print $searchpicto;
print '</td>';
print '</tr>'."\n";

// Fields title label
// --------------------------------------------------------------------
print '<tr class="liste_titre">';
$invertedElementElementFields = array_flip($elementElementFields);

foreach ($object->fields as $key => $val)
{
    $disableSortField = dol_strlen($fromtype) > 0 ? preg_match('/'. $invertedElementElementFields[$fromtype] .'/',$key) : 0;

    $cssforfield = (empty($val['csslist']) ? (empty($val['css']) ? 'maxwidthsearch' : $val['css']) : $val['csslist']);
    if (in_array($key, ['days_remaining_before_next_control', 'status', 'verdict'])) $cssforfield .= ($cssforfield ? ' ' : '').'center';
    elseif (isset($val['type']) && in_array($val['type'], array('date', 'datetime', 'timestamp'))) $cssforfield .= ($cssforfield ? ' ' : '').'center';
    elseif (isset($val['type']) && in_array($val['type'], array('timestamp'))) $cssforfield .= ($cssforfield ? ' ' : '').'nowrap';
    elseif (isset($val['type']) && in_array($val['type'], array('double(24,8)', 'double(6,3)', 'integer', 'real', 'price')) && (isset($val['label']) && $val['label'] != 'TechnicalID')) $cssforfield .= ($cssforfield ? ' ' : '').'right';
    if (array_key_exists('t.'.$key, $arrayfields) && !empty($arrayfields['t.'.$key]['checked']))
    {
        $fieldLabel = (array_key_exists('t.'.$key, $arrayfields) && isset($arrayfields['t.'.$key]['label'])) ? $arrayfields['t.'.$key]['label'] : $key;
        print getTitleFieldOfList($fieldLabel, 0, $_SERVER['PHP_SELF'], $key, '', $param, ($cssforfield ? 'class="'.$cssforfield.'"' : ''), $sortfield, $sortorder, ($cssforfield ? $cssforfield.' ' : ''), $disableSortField)."\n";
    } elseif ($key == 'Custom') {
        foreach ($val as $resource) {
            if (isset($resource['checked']) && $resource['checked']) {
                print '<th class="wrapcolumntitle ' . (isset($resource['css']) ? $resource['css'] : '') . ' liste_titre">';
                print $langs->trans(isset($resource['label']) ? $resource['label'] : 'Unknown');
                print '</th>';
            }
        }
    }
}
// Extra fields
include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_search_title.tpl.php';
// Hook fields
$parameters = array('arrayfields'=>$arrayfields, 'param'=>$param, 'sortfield'=>$sortfield, 'sortorder'=>$sortorder);
$reshook = $hookmanager->executeHooks('printFieldListTitle', $parameters, $object);
print $hookmanager->resPrint;
// Action column
print getTitleFieldOfList($selectedfields, 0, $_SERVER["PHP_SELF"], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ')."\n";
print '</tr>'."\n";

// Detect if we need a fetch on each output line
$needToFetchEachLine = 0;
if (isset($extrafields->attributes[$object->table_element]['computed']) && is_array($extrafields->attributes[$object->table_element]['computed']) && count($extrafields->attributes[$object->table_element]['computed']) > 0) {
    foreach ($extrafields->attributes[$object->table_element]['computed'] as $key => $val)
    {
        if (preg_match('/\$object/', $val)) $needToFetchEachLine++;
    }
}

// Loop on record
// --------------------------------------------------------------------
$i = 0;
$totalarray = array();

$revertedElementFields = array_flip($elementElementFields);

$linkedObjects = $object->fetchAllLinksForObjectType();

while ($i < ($limit ? min($num, $limit) : $num))
{
    $obj = $db->fetch_object($resql);
    if (empty($obj)) break;

    // Store properties in $object
    $object->setVarsFromFetchObj($obj);

    $filter = ['customsql' => 'fk_object=' . $object->id . ' AND status > 0 AND object_type="' . $object->element . '"'];
    $signatories = $signatory->fetchAll('', 'role', 0, 0, $filter);

    // Show here line of result
    print '<tr class="oddeven">';
    foreach ($object->fields as $key => $val)
    {
        $cssforfield = (empty($val['css']) ? '' : $val['css']);
        if (isset($val['type']) && in_array($val['type'], array('date', 'datetime', 'timestamp'))) $cssforfield .= ($cssforfield ? ' ' : '').'center';
        elseif (in_array($key, ['days_remaining_before_next_control', 'status', 'verdict'])) $cssforfield .= ($cssforfield ? ' ' : '').'center';
        if (isset($val['type']) && in_array($val['type'], array('timestamp'))) $cssforfield .= ($cssforfield ? ' ' : '').'nowrap';
        elseif ($key == 'ref') $cssforfield .= ($cssforfield ? ' ' : '').'nowrap';
        if (isset($val['type']) && in_array($val['type'], array('double(24,8)', 'double(6,3)', 'integer', 'real', 'price')) && !in_array($key, array('rowid', 'status'))) $cssforfield .= ($cssforfield ? ' ' : '').'right';
        if (array_key_exists('t.'.$key, $arrayfields) && !empty($arrayfields['t.'.$key]['checked']))
        {
            print '<td'.($cssforfield ? ' class="'.$cssforfield.'"' : '').'>';
            if ($key == 'status') {
                print $object->getLibStatut(5);
            }
            elseif ($key == 'ref') {
                print (method_exists($object, 'getNomUrl') && property_exists($object, 'ref') && !empty($object->ref)) ? $object->getNomUrl(1) : 'N/A';
            }
            elseif ($key == 'fk_sheet') {
                if (property_exists($object, 'fk_sheet') && !empty($object->fk_sheet) && $object->fk_sheet > 0) {
                    $sheet->fetch($object->fk_sheet);
                    print $sheet->getNomUrl(1, '', 0, 'maxwidth200onsmartphone maxwidth300', -1, 1);
                } else {
                    print 'N/A';
                }
            }
            elseif ($key == 'verdict') {
                if (property_exists($object, 'verdict') && isset($object->verdict) && $object->verdict !== null) {
                    $verdictColor = $object->verdict == 1 ? 'green' : ($object->verdict == 2 ? 'red' : 'grey');
                    print '<div class="wpeo-button button-' . $verdictColor . '">' .
                          (isset($object->fields['verdict']['arrayofkeyval'][$object->verdict]) ?
                           $object->fields['verdict']['arrayofkeyval'][$object->verdict] : 'N/A') . '</div>';
                } else {
                    print 'N/A';
                }
            }
            elseif ($key == 'days_remaining_before_next_control') {
                if (property_exists($object, 'next_control_date') && !empty($object->next_control_date) && $object->next_control_date > 0) {
                    $nextControl = floor(($object->next_control_date - dol_now('tzuser')) / (3600 * 24));
                    $nextControlDateColor = method_exists($object, 'getNextControlDateColor') ? $object->getNextControlDateColor() : '#000000';
                    print '<div class="wpeo-button" style="background-color: ' . $nextControlDateColor . '; border-color: ' . $nextControlDateColor . '">' . $nextControl . '</div>';
                } else {
                    print 'N/A';
                }
            }
            elseif ($key == 'fk_user_controller') {
                if (property_exists($object, 'fk_user_controller') && !empty($object->fk_user_controller) && $object->fk_user_controller > 0) {
                    $userTmp = new User($db);
                    $userTmp->fetch($object->fk_user_controller);
                    print $userTmp->getNomUrl(1);
                } else {
                    print 'N/A';
                }
            }
            elseif ($key == 'projectid') {
                if (property_exists($object, 'projectid') && !empty($object->projectid) && $object->projectid > 0) {
                    $project = new Project($db);
                    $project->fetch($object->projectid);
                    print $project->getNomUrl(1);
                } else {
                    print 'N/A';
                }
            }
            elseif ($key == 'success_rate') {
                if (property_exists($object, 'success_rate') && isset($object->success_rate)) {
                    print $object->success_rate . '%';
                } else {
                    print 'N/A';
                }
            }
            elseif ($key == 'track_id') {
                if (property_exists($object, 'track_id') && !empty($object->track_id)) {
                    print dol_escape_htmltag($object->track_id);
                } else {
                    print 'N/A';
                }
            }
            elseif (isset($revertedElementFields) && in_array($key, $revertedElementFields)) {
                if (!isset($linkNameElementCorrespondence[$elementElementFields[$key]])) {
                    print 'N/A';
                } else {
                    $linkedElement = $linkNameElementCorrespondence[$elementElementFields[$key]];

                    if (isset($obj->rowid) && isset($linkedObjects[$obj->rowid]) &&
                        is_array($linkedObjects[$obj->rowid]) && !empty($linkedElement['conf']) &&
                        !empty($linkedObjects[$obj->rowid][$linkedElement['link_name']])) {

                        $className = $linkedElement['className'];
                        $linkedObject = new $className($db);

                        $linkedObjectType = $linkedElement['link_name'];
                        $linkedObjectId = $linkedObjects[$obj->rowid][$linkedElement['link_name']];

                        if (!isset($alreadyFetchedObjects[$linkedObjectType][$linkedObjectId]) ||
                            !is_object($alreadyFetchedObjects[$linkedObjectType][$linkedObjectId])) {
                            $result = $linkedObject->fetch($linkedObjectId);
                        } else {
                            $linkedObject = $alreadyFetchedObjects[$linkedObjectType][$linkedObjectId];
                            $result = $linkedObjects[$obj->rowid][$linkedElement['link_name']];
                        }
                        if ($result > 0) {
                            $alreadyFetchedObjects[$linkedObjectType][$linkedObjectId] = $linkedObject;
                            print $linkedObject->getNomUrl(1);
                        } else {
                            print 'N/A';
                        }
                    } else {
                        print 'N/A';
                    }
                }
            }
            else {
                if (!isset($val['label'])) {
                    $val['label'] = $key;
                }
                if (method_exists($object, 'showOutputField')) {
                    print $object->showOutputField($val, $key, (property_exists($object, $key) ? $object->$key : ''), '');
                } else {
                    print property_exists($object, $key) ? $object->$key : 'N/A';
                }
            }
            print '</td>';
            if (!$i) $totalarray['nbfield']++;
            if (!empty($val['isameasure']))
            {
                if (!$i) $totalarray['pos'][$totalarray['nbfield']] = 't.'.$key;
                $totalarray['val']['t.'.$key] += property_exists($object, $key) ? $object->$key : 0;
            }
        } elseif ($key == 'Custom') {
            foreach ($val as $resource) {
                if (isset($resource['checked']) && $resource['checked']) {
                    if ($resource['label'] == 'SocietyAttendants') {
                        print '<td class="' . (isset($resource['css']) ? $resource['css'] : '') . '">';
                        if (isset($signatories) && is_array($signatories) && !empty($signatories)) {
                            $alreadyAddedThirdParties = [];
                            foreach ($signatories as $objectSignatory) {
                                if ($objectSignatory->element_type == 'socpeople') {
                                    $contact->fetch($objectSignatory->element_id);
                                    $thirdparty->fetch($contact->fk_soc);
                                    if (!in_array($thirdparty->id, $alreadyAddedThirdParties)) {
                                        print $thirdparty->getNomUrl(1);
                                        print '<br>';
                                    }
                                } else {
                                    $userTmp->fetch($objectSignatory->element_id);
                                    if ($userTmp->contact_id > 0) {
                                        $contact->fetch($userTmp->contact_id);
                                        $thirdparty->fetch($contact->fk_soc);
                                        if (!in_array($thirdparty->id, $alreadyAddedThirdParties)) {
                                            print $thirdparty->getNomUrl(1);
                                            print '<br>';
                                        }
                                    }
                                }
                                $alreadyAddedThirdParties[] = $thirdparty->id;
                            }
                        }
                        print '</td>';
                    } else if ($resource['label'] == 'QuestionAnswered') {
                        if (method_exists($object, 'fetchLines')) {
                            $object->fetchLines();
                        }

                        $questionCounter = 0;
                        if (property_exists($object, 'fk_sheet') && !empty($object->fk_sheet) && $object->fk_sheet > 0) {
                            $sheet->fetch($object->fk_sheet);
                            if (method_exists($sheet, 'fetchObjectLinked')) {
                                $sheet->fetchObjectLinked($object->fk_sheet, 'digiquali_' . $sheet->element, null, '', 'OR', 1, 'position');
                                if (isset($sheet->linkedObjectsIds) && is_array($sheet->linkedObjectsIds) && isset($sheet->linkedObjectsIds['digiquali_question'])) {
                                    $questionIds = $sheet->linkedObjectsIds['digiquali_question'];
                                    if (!empty($questionIds)) {
                                        $questionCounter = count($questionIds);
                                    }
                                }
                            }
                        }

                        $answerCounter = 0;
                        if (property_exists($object, 'lines') && is_array($object->lines) && !empty($object->lines)) {
                            foreach($object->lines as $objectLine) {
                                if (property_exists($objectLine, 'answer') && dol_strlen($objectLine->answer) > 0) {
                                    $answerCounter++;
                                }
                            }
                        }
                        print '<td class="' . (isset($resource['css']) ? $resource['css'] : '') . '">';
                        print ' ' . $answerCounter . '/' . $questionCounter;
                        print ($questionCounter == $answerCounter &&
                               property_exists($object, 'status') &&
                               $object->status == Control::STATUS_DRAFT
                               ? img_picto($langs->transnoentities('ObjectReadyToValidate', dol_strtolower($langs->transnoentities('Control'))), 'warning')
                               : '');
                        print '</td>';
                    } else if ($resource['label'] == 'LastStatusDate') {
                        require_once DOL_DOCUMENT_ROOT . '/comm/action/class/actioncomm.class.php';

                        $actioncomm = new ActionComm($db);

                        if (method_exists($actioncomm, 'getActions')) {
                            $lastValidateAction = $actioncomm->getActions(0, $object->id, 'control@digiquali', ' AND a.code = "AC_CONTROL_VALIDATE"', 'a.datep', 'DESC', 1);
                            $lastReOpenAction = $actioncomm->getActions(0, $object->id, 'control@digiquali', ' AND a.code = "AC_CONTROL_UNVALIDATE"', 'a.datep', 'DESC', 1);

                            $lastValidateDate = (is_array($lastValidateAction) && !empty($lastValidateAction) ? $lastValidateAction[0]->datec : 0);
                            $lastReOpenDate = (is_array($lastReOpenAction) && !empty($lastReOpenAction) ? $lastReOpenAction[0]->datec : '');

                            print '<td class="' . (isset($resource['css']) ? $resource['css'] : '') . '">';
                            print $lastValidateDate > 0 ? $langs->trans('ValidationDate') . ': <br>' . dol_print_date($lastValidateDate, 'dayhour') . '<br>' : '';
                            print $lastReOpenDate > 0 ? $langs->trans('ReOpenDate') . ': <br>' . dol_print_date($lastReOpenDate, 'dayhour') . '<br>' : '';
                            print '</td>';
                        } else {
                            print '<td class="' . (isset($resource['css']) ? $resource['css'] : '') . '">N/A</td>';
                        }
                    } else {
                        print '<td class="' . (isset($resource['css']) ? $resource['css'] : '') . '">';
                        if (isset($signatories) && is_array($signatories) && !empty($signatories)) {
                            foreach ($signatories as $objectSignatory) {
                                switch ($objectSignatory->attendance) {
                                    case 1:
                                        $cssButton = '#0d8aff';
                                        $userIcon = 'fa-user-clock';
                                        break;
                                    case 2:
                                        $cssButton = '#e05353';
                                        $userIcon = 'fa-user-slash';
                                        break;
                                    default:
                                        $cssButton = '#47e58e';
                                        $userIcon = 'fa-user';
                                        break;
                                }
                                if ($objectSignatory->element_type == 'user' && $objectSignatory->role == $resource['label']) {
                                    $userTmp = $user;
                                    $userTmp->fetch($objectSignatory->element_id);
                                    print $userTmp->getNomUrl(1, '', 0, 0, 24, 1) . ' - ' .
                                          (method_exists($objectSignatory, 'getLibStatut') ? $objectSignatory->getLibStatut(3) : 'N/A');
                                    print ' - <i class="fas ' . $userIcon . '" style="color: ' . $cssButton . '"></i>';
                                    print '<br>';
                                } elseif ($objectSignatory->element_type == 'socpeople' && $objectSignatory->role == $resource['label']) {
                                    $contact->fetch($objectSignatory->element_id);
                                    print $contact->getNomUrl(1) . ' - ' .
                                          (method_exists($objectSignatory, 'getLibStatut') ? $objectSignatory->getLibStatut(3) : 'N/A');
                                    print ' - <i class="fas ' . $userIcon . '" style="color: ' . $cssButton . '"></i>';
                                    print '<br>';
                                }
                            }
                        }
                        print '</td>';
                    }
                }
            }
        }
    }
    // Extra fields
    include DOL_DOCUMENT_ROOT . '/core/tpl/extrafields_list_print_fields.tpl.php';
    // Fields from hook
    $parameters = array('arrayfields'=>$arrayfields, 'object'=>$object, 'obj'=>$obj, 'i'=>$i, 'totalarray'=>&$totalarray);
    $reshook = $hookmanager->executeHooks('printFieldListValue', $parameters, $object);
    print $hookmanager->resPrint;
    // Action column
    print '<td class="nowrap center">';
    if ($massactionbutton || $massaction)
    {
        $selected = 0;
        if (in_array($object->id, $arrayofselected)) $selected = 1;
        print '<input id="cb'.$object->id.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$object->id.'"'.($selected ? ' checked="checked"' : '').'>';
    }
    print '</td>';
    if (!$i) $totalarray['nbfield']++;

    print '</tr>'."\n";

    $i++;
}

// If no record found
if ($num == 0)
{
    $colspan = 1;
    foreach ($arrayfields as $key => $val) { if (!empty($val['checked'])) $colspan++; }
    print '<tr><td colspan="'.$colspan.'" class="opacitymedium">'.$langs->trans("NoRecordFound").'</td></tr>';
}

$db->free($resql);

$parameters = array('arrayfields'=>$arrayfields, 'sql'=>$sql);
$reshook = $hookmanager->executeHooks('printFieldListFooter', $parameters, $object);
print $hookmanager->resPrint;

print '</table>'."\n";
print '</div>'."\n";

print '</form>'."\n";
?>