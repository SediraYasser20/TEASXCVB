<?php
/* Copyright (C) 2022-2023 EVARISK <technique@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    class/control.class.php
 * \ingroup digiquali
 * \brief   This file is a CRUD class file for Control (Create/Read/Update/Delete).
 */

// Load Dolibarr libraries.
require_once DOL_DOCUMENT_ROOT . '/core/lib/ticket.lib.php';

// Load Saturne libraries.
require_once __DIR__ . '/../../saturne/class/saturneobject.class.php';

/**
 * Class for Control.
 */
class Control extends SaturneObject
{
    /**
     * @var string Module name.
     */
    public $module = 'digiquali';

    /**
     * @var string Element type of object.
     */
    public $element = 'control';

    /**
     * @var string Name of table without prefix where object is stored. This is also the key used for extrafields management.
     */
    public $table_element = 'digiquali_control';

    /**
     * @var int Does this object support multicompany module ?
     * 0 = No test on entity, 1 = Test with field entity, 'field@table' = Test with link by field@table.
     */
    public $ismultientitymanaged = 1;

    /**
     * @var int Does object support extrafields ? 0 = No, 1 = Yes.
     */
    public $isextrafieldmanaged = 1;

    /**
     * @var string Name of icon for control. Must be a 'fa-xxx' fontawesome code (or 'fa-xxx_fa_color_size') or 'control@digiquali' if picto is file 'img/object_control.png'.
     */
    public string $picto = 'fontawesome_fa-tasks_fas_#d35968';

    public const STATUS_DELETED   = -1;
    public const STATUS_DRAFT     = 0;
    public const STATUS_VALIDATED = 1;
    public const STATUS_LOCKED    = 2;
    public const STATUS_ARCHIVED  = 3;

    /**
     * 'type' field format:
     *      'integer', 'integer:ObjectClass:PathToClass[:AddCreateButtonOrNot[:Filter[:Sortfield]]]',
     *      'select' (list of values are in 'options'),
     *      'sellist:TableName:LabelFieldName[:KeyFieldName[:KeyFieldParent[:Filter[:Sortfield]]]]',
     *      'chkbxlst:...',
     *      'varchar(x)',
     *      'text', 'text:none', 'html',
     *      'double(24,8)', 'real', 'price',
     *      'date', 'datetime', 'timestamp', 'duration',
     *      'boolean', 'checkbox', 'radio', 'array',
     *      'mail', 'phone', 'url', 'password', 'ip'
     *      Note: Filter can be a string like "(t.ref:like:'SO-%') or (t.date_creation:<:'20160101') or (t.nature:is:NULL)"
     * 'label' the translation key.
     * 'picto' is code of a picto to show before value in forms
     * 'enabled' is a condition when the field must be managed (Example: 1 or '$conf->global->MY_SETUP_PARAM' or '!empty($conf->multicurrency->enabled)' ...)
     * 'position' is the sort order of field.
     * 'notnull' is set to 1 if not null in database. Set to -1 if we must set data to null if empty '' or 0.
     * 'visible' says if field is visible in list (Examples: 0=Not visible, 1=Visible on list and create/update/view forms, 2=Visible on list only, 3=Visible on create/update/view form only (not list), 4=Visible on list and update/view form only (not create). 5=Visible on list and view only (not create/not update). Using a negative value means field is not shown by default on list but can be selected for viewing)
     * 'noteditable' says if field is not editable (1 or 0)
     * 'default' is a default value for creation (can still be overwroted by the Setup of Default Values if field is editable in creation form). Note: If default is set to '(PROV)' and field is 'ref', the default value will be set to '(PROVid)' where id is rowid when a new record is created.
     * 'index' if we want an index in database.
     * 'foreignkey'=>'tablename.field' if the field is a foreign key (it is recommanded to name the field fk_...).
     * 'searchall' is 1 if we want to search in this field when making a search from the quick search button.
     * 'isameasure' must be set to 1 or 2 if field can be used for measure. Field type must be summable like integer or double(24,8). Use 1 in most cases, or 2 if you don't want to see the column total into list (for example for percentage)
     * 'css' and 'cssview' and 'csslist' is the CSS style to use on field. 'css' is used in creation and update. 'cssview' is used in view mode. 'csslist' is used for columns in lists. For example: 'css'=>'minwidth300 maxwidth500 widthcentpercentminusx', 'cssview'=>'wordbreak', 'csslist'=>'tdoverflowmax200'
     * 'help' is a 'TranslationString' to use to show a tooltip on field. You can also use 'TranslationString:keyfortooltiponlick' for a tooltip on click.
     * 'showoncombobox' if value of the field must be visible into the label of the combobox that list record
     * 'disabled' is 1 if we want to have the field locked by a 'disabled' attribute. In most cases, this is never set into the definition of $fields into class, but is set dynamically by some part of code.
     * 'arrayofkeyval' to set a list of values if type is a list of predefined values. For example: array("0"=>"Draft","1"=>"Active","-1"=>"Cancel"). Note that type can be 'integer' or 'varchar'
     * 'autofocusoncreate' to have field having the focus on a create form. Only 1 field should have this property set to 1.
     * 'comment' is not used. You can store here any text of your choice. It is not used by application.
     * 'validate' is 1 if you need to validate with $this->validateField()
     * 'copytoclipboard' is 1 or 2 to allow to add a picto to copy value into clipboard (1=picto after label, 2=picto after value)
     *
     * Note: To have value dynamic, you can set value to 0 in definition and edit the value on the fly into the constructor.
     */

    /**
     * @var array Array with all fields and their property. Do not use it as a static var. It may be modified by constructor.
     */
    public $fields = [
        'rowid'              => ['type' => 'integer',      'label' => 'TechnicalID',      'enabled' => 1, 'position' => 10,   'notnull' => 1, 'visible' => 0, 'showinpwa' => 0, 'noteditable' => 1, 'index' => 1, 'comment' => 'Id'],
        'ref'                => ['type' => 'varchar(128)', 'label' => 'Ref',              'enabled' => 1, 'position' => 20,  'notnull' => 1, 'visible' => 4, 'showinpwa' => 1, 'noteditable' => 1, 'default' => '(PROV)', 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'validate' => 1, 'comment' => 'Reference of object'],
        'ref_ext'            => ['type' => 'varchar(128)', 'label' => 'RefExt',           'enabled' => 1, 'position' => 70,  'notnull' => 0, 'visible' => 0, 'showinpwa' => 0],
        'entity'             => ['type' => 'integer',      'label' => 'Entity',           'enabled' => 1, 'position' => 80,  'notnull' => 1, 'visible' => 0, 'showinpwa' => 0, 'index' => 1],
        'date_creation'      => ['type' => 'datetime',     'label' => 'DateCreation',     'enabled' => 1, 'position' => 90,  'notnull' => 1, 'visible' => 2, 'showinpwa' => 1, 'positioncard' => 10],
        'tms'                => ['type' => 'timestamp',    'label' => 'DateModification', 'enabled' => 1, 'position' => 100,  'notnull' => 0, 'visible' => 0, 'showinpwa' => 0],
        'import_key'         => ['type' => 'varchar(14)',  'label' => 'ImportId',         'enabled' => 1, 'position' => 110,  'notnull' => 0, 'visible' => 0, 'showinpwa' => 0, 'index' => 0],
        'control_date'       => ['type' => 'date',         'label' => 'ControlDate',      'enabled' => 1, 'position' => 120,  'notnull' => 0, 'visible' => 2, 'showinpwa' => 0],
        'next_control_date'  => ['type' => 'date',         'label' => 'NextControlDate',  'enabled' => 1, 'position' => 130,  'notnull' => 0, 'visible' => 2, 'showinpwa' => 0],
        'success_rate'       => ['type' => 'real',         'label' => 'SuccessScore',     'enabled' => 1, 'position' => 140,  'notnull' => 0, 'visible' => 2, 'showinpwa' => 0, 'help' => 'PercentageValue'],
        'status'             => ['type' => 'smallint',     'label' => 'Status',           'enabled' => 1, 'position' => 220,  'notnull' => 1, 'visible' => 5, 'showinpwa' => 0, 'index' => 1, 'default' => 0, 'arrayofkeyval' => [0 => 'StatusDraft', 1 => 'Validated', 2 => 'Locked', 3 => 'Archived', 'specialCase' => 'DraftValidatedLocked']],
        'label'              => ['type' => 'varchar(255)', 'label' => 'Label',            'enabled' => 1, 'position' => 30,  'notnull' => 0, 'visible' => 1, 'showinpwa' => 1, 'searchall' => 1, 'css' => 'maxwidth500 widthcentpercentminusxx'],
        'note_public'        => ['type' => 'html',         'label' => 'NotePublic',       'enabled' => 1, 'position' => 150,  'notnull' => 0, 'visible' => 0, 'showinpwa' => 0],
        'note_private'       => ['type' => 'html',         'label' => 'NotePrivate',      'enabled' => 1, 'position' => 160,  'notnull' => 0, 'visible' => 0, 'showinpwa' => 0],
        'verdict'            => ['type' => 'smallint',     'label' => 'Verdict',          'enabled' => 1, 'position' => 170, 'notnull' => 0, 'visible' => 5, 'showinpwa' => 1, 'index' => 1, 'positioncard' => 20, 'arrayofkeyval' => [0 => '', 1 => 'OK', 2 => 'KO', 3 => 'N/A']],
        'photo'              => ['type' => 'text',         'label' => 'Photo',            'enabled' => 1, 'position' => 180, 'notnull' => 0, 'visible' => 0, 'showinpwa' => 0],
        'track_id'           => ['type' => 'text',         'label' => 'TrackID',          'enabled' => 1, 'position' => 190, 'notnull' => 0, 'visible' => 2, 'showinpwa' => 0],
        'fk_user_creat'      => ['type' => 'integer:User:user/class/user.class.php',           'label' => 'UserAuthor',  'picto' => 'user',                            'enabled' => 1, 'position' => 200, 'notnull' => 1, 'visible' => 0, 'showinpwa' => 0, 'foreignkey' => 'user.rowid'],
        'fk_user_modif'      => ['type' => 'integer:User:user/class/user.class.php',           'label' => 'UserModif',   'picto' => 'user',                            'enabled' => 1, 'position' => 210, 'notnull' => 0, 'visible' => 0, 'showinpwa' => 0, 'foreignkey' => 'user.rowid'],
        'fk_sheet'           => ['type' => 'integer:Sheet:digiquali/class/sheet.class.php',    'label' => 'Sheet',       'picto' => 'fontawesome_fa-list_fas_#d35968', 'enabled' => 1, 'position' => 40,  'notnull' => 1, 'visible' => 5, 'showinpwa' => 0, 'index' => 1, 'css' => 'maxwidth500 widthcentpercentminusxx', 'foreignkey' => 'digiquali_sheet.rowid'],
        'fk_user_controller' => ['type' => 'integer:User:user/class/user.class.php:1',         'label' => 'Controller',  'picto' => 'user',                            'enabled' => 1, 'position' => 50,  'notnull' => 1, 'visible' => 1, 'showinpwa' => 0, 'index' => 1, 'css' => 'maxwidth500 widthcentpercentminusxx', 'foreignkey' => 'user.rowid',   'positioncard' => 1],
        'projectid'          => ['type' => 'integer:Project:projet/class/project.class.php:1', 'label' => 'Project',     'picto' => 'project',                         'enabled' => 1, 'position' => 60,  'notnull' => 0, 'visible' => 1, 'showinpwa' => 0, 'index' => 1, 'css' => 'maxwidth500 widthcentpercentminusxx', 'foreignkey' => 'projet.rowid', 'positioncard' => 2]
    ];

    /**
     * @var int ID.
     */
    public int $rowid;

    /**
     * @var string Ref.
     */
    public $ref;

    /**
     * @var string Ref ext.
     */
    public $ref_ext;

    /**
     * @var int Entity.
     */
    public $entity;

    /**
     * @var int|string Creation date.
     */
    public $date_creation;

    /**
     * @var int|string Timestamp.
     */
    public $tms;

    /**
     * @var string Import key.
     */
    public $import_key;

    /**
     * @var int Status.
     */
    public $status;

    /**
     * @var string|null Label.
     */
    public ?string $label;

    /**
     * @var string Public note.
     */
    public $note_public;

    /**
     * @var string Private note.
     */
    public $note_private;

    /**
     * @var int|null Verdict.
     */
    public ?int $verdict = null;

    /**
     * @var string|null Photo path.
     */
    public ?string $photo = '';

    /**
     * @var string|null TrackID.
     */
    public ?string $track_id;

    /**
     * @var int|string NextControlDate.
     */
    public $next_control_date;

    /**
     * @var int|string ControlDate.
     */
    public $control_date;

    /**
     * @var float|string|null Success rate
     */
    public $success_rate;

    /**
     * @var int User ID.
     */
    public $fk_user_creat;

    /**
     * @var int|null User ID.
     */
    public $fk_user_modif;

    /**
     * @var int Sheet ID.
     */
    public int $fk_sheet;

    /**
     * @var int|string|null User ID.
     */
    public $fk_user_controller;

    /**
     * @var int|string|null Project ID.
     */
    public $projectid;

    /**
     * @var string Name of subtable line
     */
    public $table_element_line = 'digiquali_controldet';

    /**
     * @var ControlLine[] Array of subtable lines
     */
    public $lines = [];

    /**
     * Constructor.
     *
     * @param DoliDb $db Database handler.
     */
    public function __construct(DoliDB $db)
    {
        parent::__construct($db, $this->module, $this->element);
    }

    /**
     * Create object into database.
     *
     * @param  User $user      User that creates.
     * @param  bool $notrigger false = launch triggers after, true = disable triggers.
     * @return int             0 < if KO, ID of created object if OK.
     */
    public function create(User $user, bool $notrigger = false): int
    {
        $this->track_id = generate_random_id();
        $result = parent::create($user, $notrigger);

        if ($result > 0) {
            global $conf;

            require_once TCPDF_PATH . 'tcpdf_barcodes_2d.php';

            $url = dol_buildpath('custom/digiquali/public/control/public_control.php?track_id=' . $this->track_id . '&entity=' . $conf->entity, 3);

            $barcode = new TCPDF2DBarcode($url, 'QRCODE,L');

            dol_mkdir($conf->digiquali->multidir_output[$conf->entity] . '/control/' . $this->ref . '/qrcode/');
            $file = $conf->digiquali->multidir_output[$conf->entity] . '/control/' . $this->ref . '/qrcode/' . 'barcode_' . $this->track_id . '.png';

            $imageData = $barcode->getBarcodePngData();
            $imageData = imagecreatefromstring($imageData);
            imagepng($imageData, $file);
        }

        return $result;
    }

    /**
	 * Load list of objects in memory from the database.
	 *
	 * @param  string      $sortorder    Sort Order
	 * @param  string      $sortfield    Sort field
	 * @param  int         $limit        limit
	 * @param  int         $offset       Offset
	 * @param  array       $filter       Filter array. Example array('field'=>'valueforlike', 'customurl'=>...)
	 * @param  string      $filtermode   Filter mode (AND or OR)
	 * @return array|int                 int <0 if KO, array of pages if OK
	 */
	public function fetchAll($sortorder = '', $sortfield = '', $limit = 0, $offset = 0, array $filter = array(), $filtermode = 'AND', $fetchCategories = false)
	{
		dol_syslog(__METHOD__, LOG_DEBUG);

		$records = array();

		$sql                                                                              = 'SELECT ';
		$sql                                                                             .= $this->getFieldList('t');
		$sql                                                                             .= ' FROM ' . MAIN_DB_PREFIX . $this->table_element . ' as t';
        if (isModEnabled('categorie') && $fetchCategories) {
            $sql .= Categorie::getFilterJoinQuery('control', 't.rowid');
        }
		if (isset($this->ismultientitymanaged) && $this->ismultientitymanaged == 1) $sql .= ' WHERE t.entity IN (' . getEntity($this->table_element) . ')';
		else $sql                                                                        .= ' WHERE 1 = 1';
		// Manage filter
		$sqlwhere = array();
		if (count($filter) > 0) {
			foreach ($filter as $key => $value) {
				if ($key == 't.rowid') {
					$sqlwhere[] = $key . '=' . $value;
				} elseif (isset($this->fields[$key]['type']) && in_array($this->fields[$key]['type'], array('date', 'datetime', 'timestamp'))) {
					$sqlwhere[] = $key . ' = \'' . $this->db->idate($value) . '\'';
				} elseif ($key == 'customsql') {
					$sqlwhere[] = $value;
				} elseif (strpos($value, '%') === false) {
					$sqlwhere[] = $key . ' IN (' . $this->db->sanitize($this->db->escape($value)) . ')';
				} else {
					$sqlwhere[] = $key . ' LIKE \'%' . $this->db->escape($value) . '%\'';
				}
			}
		}
		if (count($sqlwhere) > 0) {
			$sql .= ' AND (' . implode(' ' . $filtermode . ' ', $sqlwhere) . ')';
		}

		if ( ! empty($sortfield)) {
			$sql .= $this->db->order($sortfield, $sortorder);
		}
		if ( ! empty($limit)) {
			$sql .= ' ' . $this->db->plimit($limit, $offset);
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i   = 0;
			while ($i < ($limit ? min($limit, $num) : $num)) {
				$obj = $this->db->fetch_object($resql);

				$record = new self($this->db);
				$record->setVarsFromFetchObj($obj);

				$records[$record->id] = $record;

				$i++;
			}
			$this->db->free($resql);

			return $records;
		} else {
			$this->errors[] = 'Error ' . $this->db->lasterror();
			dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

			return -1;
		}
	}


    /**
     * Load list of objects in memory from the database.
     *
     * @param  string      $sortorder    Sort Order
     * @param  string      $sortfield    Sort field
     * @param  int         $limit        limit
     * @param  int         $offset       Offset
     * @param  array       $filter       Filter array. Example array('field'=>'valueforlike', 'customurl'=>...)
     * @param  string      $filtermode   Filter mode (AND or OR)
     * @return array|int                 int <0 if KO, array of pages if OK
     */
    public function fetchAllWithLeftJoin($sortorder = '', $sortfield = '', $limit = 0, $offset = 0, array $filter = array(), $filtermode = 'AND', $fetchCategories = false, $leftJoin = '')
    {
        dol_syslog(__METHOD__, LOG_DEBUG);

        $records = array();

        $sql                                                                              = 'SELECT ';
        $sql                                                                             .= $this->getFieldList('t');
        $sql                                                                             .= ' FROM ' . MAIN_DB_PREFIX . $this->table_element . ' as t';
        if (isModEnabled('categorie') && $fetchCategories) {
            require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';
            $sql .= Categorie::getFilterJoinQuery('control', 't.rowid');
        }
        if (dol_strlen($leftJoin)) {
            $sql .= ' ' . $leftJoin;
        }
        if (isset($this->ismultientitymanaged) && $this->ismultientitymanaged == 1) $sql .= ' WHERE t.entity IN (' . getEntity($this->table_element) . ')';
        else $sql                                                                        .= ' WHERE 1 = 1';

        // Manage filter
        $sqlwhere = array();
        if (count($filter) > 0) {
            foreach ($filter as $key => $value) {
                if ($key == 't.rowid') {
                    $sqlwhere[] = $key . '=' . $value;
                } elseif (in_array($this->fields[$key]['type'], array('date', 'datetime', 'timestamp'))) {
                    $sqlwhere[] = $key . ' = \'' . $this->db->idate($value) . '\'';
                } elseif ($key == 'customsql') {
                    $sqlwhere[] = $value;
                } elseif (strpos($value, '%') === false) {
                    $sqlwhere[] = $key . ' IN (' . $this->db->sanitize($this->db->escape($value)) . ')';
                } else {
                    $sqlwhere[] = $key . ' LIKE \'%' . $this->db->escape($value) . '%\'';
                }
            }
        }
        if (count($sqlwhere) > 0) {
            $sql .= ' AND (' . implode(' ' . $filtermode . ' ', $sqlwhere) . ')';
        }

        if ( ! empty($sortfield)) {
            $sql .= $this->db->order($sortfield, $sortorder);
        }
        if ( ! empty($limit)) {
            $sql .= ' ' . $this->db->plimit($limit, $offset);
        }

        $resql = $this->db->query($sql);
        if ($resql) {
            $num = $this->db->num_rows($resql);
            $i   = 0;
            while ($i < ($limit ? min($limit, $num) : $num)) {
                $obj = $this->db->fetch_object($resql);

                $record = new self($this->db);
                $record->setVarsFromFetchObj($obj);

                $records[$record->id] = $record;

                $i++;
            }
            $this->db->free($resql);

            return $records;
        } else {
            $this->errors[] = 'Error ' . $this->db->lasterror();
            dol_syslog(__METHOD__ . ' ' . join(',', $this->errors), LOG_ERR);

            return -1;
        }
    }

    /**
     * Set draft status.
     *
     * @param  User $user      Object user that modify.
     * @param  int  $notrigger 1 = Does not execute triggers, 0 = Execute triggers.
     * @return int             0 < if KO, > 0 if OK.
     * @throws Exception
     */
    public function setDraft(User $user, int $notrigger = 0): int
    {
        // Protection
        if ($this->status <= self::STATUS_DRAFT) {
            return 0;
        }

        $signatory = new SaturneSignature($this->db);
        $signatory->deleteSignatoriesSignatures($this->id, 'control');

        return $this->setStatusCommon($user, self::STATUS_DRAFT, $notrigger, 'CONTROL_UNVALIDATE');
    }

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Return if a control can be deleted
	 *
	 *  @return    int         <=0 if no, >0 if yes
	 */
	public function isErasable() {
		return $this->isLinkedToOtherObjects();
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Return if a control is linked to another object
	 *
	 *  @return    int         <=0 if no, >0 if yes
	 */
	public function isLinkedToOtherObjects() {

		// Links between objects are stored in table element_element
		$sql = 'SELECT rowid, fk_source, sourcetype, fk_target, targettype';
		$sql .= ' FROM '.MAIN_DB_PREFIX.'element_element';
		$sql .= ' WHERE fk_target = ' . $this->id;
		$sql .= " AND targettype = '" . $this->table_element . "'";

		$resql = $this->db->query($sql);

		if ($resql) {
			$nbObjectsLinked = 0;
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < $num) {
				$nbObjectsLinked++;
				$i++;
			}
			if ($nbObjectsLinked > 0) {
				return -1;
			} else {
				return 1;
			}
		} else {
			dol_print_error($this->db);
			return -1;
		}
	}

    /**
     * Clone an object into another one.
     *
     * @param  User      $user    User that creates
     * @param  int       $fromID  ID of object to clone.
     * @param  array     $options Options array.
     * @return int                New object created, <0 if KO.
     * @throws Exception
     */
    public function createFromClone(User $user, int $fromID, array $options): int
    {
        global $conf, $langs;

        dol_syslog(__METHOD__, LOG_DEBUG);

        $error = 0;

        $object = new self($this->db);
        $this->db->begin();

        // Load source object.
        $result = $object->fetchCommon($fromID);
        if ($result > 0 && ! empty($object->table_element_line)) {
            $object->fetchLines();
        }

        $objectRef = $object->ref;

        // Reset some properties.
        unset($object->fk_user_creat);
        unset($object->import_key);

        // Clear fields.
        if (property_exists($object, 'ref')) {
            $object->ref = '';
        }
        if (property_exists($object, 'date_creation')) {
            $object->date_creation = dol_now();
        }
        if (property_exists($object, 'status')) {
            $object->status = 0;
        }
        if (property_exists($object, 'verdict')) {
            $object->verdict = 0;
        }
        if (!empty($options['label'])) {
            if (property_exists($object, 'label')) {
                $object->label = $options['label'];
            }
        }
        if (empty($options['photos'])) {
            $object->photo = '';
        }
        if (property_exists($object, 'control_date')) {
            $object->control_date = '';
        }
        if (property_exists($object, 'next_control_date')) {
            $object->next_control_date = '';
        }

        $object->context = 'createfromclone';

        $object->fetchObjectLinked('','', $object->id, 'digiquali_' . $object->element,  'OR', 1, 'sourcetype', 0);

        $controlID = $object->create($user);

        if ($controlID > 0) {
            $objectFromClone = new self($this->db);
            $objectFromClone->fetch($controlID);

            // Categories.
            $cat = new Categorie($this->db);
            $categories = $cat->containing($fromID, 'control');
            if (is_array($categories) && !empty($categories)) {
                foreach($categories as $cat) {
                    $categoryIds[] = $cat->id;
                }
                $object->setCategories($categoryIds);
            }

            // Add objects linked.
			$linkableElements = get_sheet_linkable_objects();

			if (!empty($linkableElements)) {
				foreach($linkableElements as $linkableElement) {
                    if ($linkableElement['conf'] > 0 && (!empty($object->linkedObjectsIds[$linkableElement['link_name']]))) {
						foreach($object->linkedObjectsIds[$linkableElement['link_name']] as $linkedElementId) {
							$objectFromClone->add_object_linked($linkableElement['link_name'], $linkedElementId);
						}
					}
				}
			}

            // Add Attendants.
            $signatory = new SaturneSignature($this->db);
            if (!empty($options['attendants'])) {
                // Load signatory from source object.
                $signatories = $signatory->fetchSignatory('', $fromID, $this->element);
                if (is_array($signatories) && !empty($signatories)) {
                    foreach ($signatories as $arrayRole) {
                        foreach ($arrayRole as $signatoryRole) {
                            $signatory->createFromClone($user, $signatoryRole->id, $controlID);
                        }
                    }
                }
            } else {
                $signatory->setSignatory($objectFromClone->id, $this->element, 'user', [$objectFromClone->fk_user_controller], 'Controller', 1);
            }

            // Add Photos.
            if (!empty($options['photos'])) {
                $dir  = $conf->digiquali->multidir_output[$conf->entity] . '/control';
                $path = $dir . '/' . $objectRef . '/photos';
                dol_mkdir($dir . '/' . $objectFromClone->ref . '/photos');
                dolCopyDir($path,$dir . '/' . $objectFromClone->ref . '/photos', 0, 1);
            }
        } else {
            $error++;
            $this->error  = $object->error;
            $this->errors = $object->errors;
        }

        // End.
        if (!$error) {
            $this->db->commit();
            return $controlID;
        } else {
            $this->db->rollback();
            return -1;
        }
    }

    /**
     * Return the label of the verdict.
     *
     * @param  int     $mode 0 = long label, 1 = short label, 2 = Picto + short label, 3 = Picto, 4 = Picto + long label, 5 = Short label + Picto, 6 = Long label + Picto.
     * @return string        Label of verdict.
     */
    public function getLibVerdict(int $mode = 0): string
    {
        return $this->libVerdict($this->verdict, $mode);
    }

    /**
     * Return the verdict.
     *
     * @param  int|null $verdict ID verdict.
     * @param  int      $mode    0 = long label, 1 = short label, 2 = Picto + short label, 3 = Picto, 4 = Picto + long label, 5 = Short label + Picto, 6 = Long label + Picto.
     * @return string            Label of verdict.
     */
    public function libVerdict(?int $verdict, int $mode = 0): string
    {
        global $langs;

        if (empty($verdict)) {
            $verdict = 0;
        }

        $this->labelStatus[0] = $langs->trans('NA');
        $this->labelStatus[1] = $langs->trans('OK');
        $this->labelStatus[2] = $langs->trans('KO');

        $verdictType = 'status' . $verdict;
        if ($verdict == 0) {
            $verdictType = 'status6';
        }
        if ($verdict == 1) {
            $verdictType = 'status4';
        }
        if ($verdict == 2) {
            $verdictType = 'status8';
        }

        return dolGetStatus($this->labelStatus[$verdict], $this->labelStatusShort[$verdict], '', $verdictType, $mode);
    }

    /**
     * Return the status.
     *
     * @param  int    $status ID status.
     * @param  int    $mode   0 = long label, 1 = short label, 2 = Picto + short label, 3 = Picto, 4 = Picto + long label, 5 = Short label + Picto, 6 = Long label + Picto.
     * @return string         Label of status.
     */
    public function LibStatut(int $status, int $mode = 0): string
    {
        if (empty($this->labelStatus) || empty($this->labelStatusShort)) {
            global $langs;

            $this->labelStatus[self::STATUS_DRAFT]          = $langs->transnoentitiesnoconv('StatusDraft');
            $this->labelStatus[self::STATUS_VALIDATED]      = $langs->transnoentitiesnoconv('Validated');
            $this->labelStatus[self::STATUS_LOCKED]         = $langs->transnoentitiesnoconv('Locked');
            $this->labelStatus[self::STATUS_ARCHIVED]       = $langs->transnoentitiesnoconv('Archived');
            $this->labelStatus[self::STATUS_DELETED]        = $langs->transnoentitiesnoconv('Deleted');

            $this->labelStatusShort[self::STATUS_DRAFT]     = $langs->transnoentitiesnoconv('StatusDraft');
            $this->labelStatusShort[self::STATUS_VALIDATED] = $langs->transnoentitiesnoconv('Validated');
            $this->labelStatusShort[self::STATUS_LOCKED]    = $langs->transnoentitiesnoconv('Locked');
            $this->labelStatusShort[self::STATUS_ARCHIVED]  = $langs->transnoentitiesnoconv('Archived');
            $this->labelStatusShort[self::STATUS_DELETED]   = $langs->transnoentitiesnoconv('Deleted');
        }

        $statusType = 'status' . $status;
        if ($status == self::STATUS_VALIDATED) {
            $statusType = 'status4';
        }
        if ($status == self::STATUS_LOCKED) {
            $statusType = 'status6';
        }
        if ($status == self::STATUS_ARCHIVED) {
            $statusType = 'status8';
        }
        if ($status == self::STATUS_DELETED) {
            $statusType = 'status9';
        }

        return dolGetStatus($this->labelStatus[$status], $this->labelStatusShort[$status], '', $statusType, $mode);
    }

    /**
     * Initialise object with example values.
     * ID must be 0 if object instance is a specimen.
     *
     * @return void
     */
    public function initAsSpecimen()
    {
        $this->initAsSpecimenCommon();
    }

    /**
     * Get next control date color
     *
     * @return string $nextControlDateColor Next control date color
     */
    function getNextControlDateColor(): string
    {
        $nextControl                = floor(($this->next_control_date - dol_now('tzuser'))/(3600 * 24));
        $nextControlDateColor       = '#47E58E';
        $nextControlDateFrequencies = [0 => '#E05353', 30 => '#FF6900', 60 => '#E9AD4F', 90 => '#47E58E'];
        foreach ($nextControlDateFrequencies as $nextControlDateFrequency => $nextControlDateFrequencyDefaultColor) {
            if ($nextControl <= $nextControlDateFrequency) {
                $nextControlDateColor = getDolGlobalString('DIGIQUALI_NEXT_CONTROL_DATE_COLOR_' . $nextControlDateFrequency, $nextControlDateFrequencyDefaultColor);
                break;
            }
        }

        return $nextControlDateColor;
    }

    /**
     * Load dashboard info.
     *
     * @return array
     * @throws Exception
     */
public function load_dashboard(): array
{
    global $user, $langs;

    $confName = dol_strtoupper($this->module) . '_DASHBOARD_CONFIG';
    // Initialize default dashboard config if not set
    $dashboardConfig = property_exists($user->conf, $confName) ? json_decode($user->conf->$confName) : (object) [
        'graphs' => (object) [
            'ControlsTagsRepartition' => (object) ['hide' => false],
            'ControlsRepartition' => (object) ['hide' => false],
            'ControlsByFiscalYear' => (object) ['hide' => false],
            'ControlListsByNextControl' => (object) ['hide' => false]
        ]
    ];
    $array = ['graphs' => [], 'lists' => [], 'disabledGraphs' => []];

    if (empty($dashboardConfig->graphs->ControlsTagsRepartition->hide)) {
        $array['graphs'][] = $this->getNbControlsTagsByVerdict();
    } else {
        $array['disabledGraphs']['ControlsTagsRepartition'] = $langs->transnoentities('ControlsTagsRepartition');
    }
    if (empty($dashboardConfig->graphs->ControlsRepartition->hide)) {
        $array['graphs'][] = $this->getNbControlsByVerdict();
    } else {
        $array['disabledGraphs']['ControlsRepartition'] = $langs->transnoentities('ControlsRepartition');
    }
    if (empty($dashboardConfig->graphs->ControlsByFiscalYear->hide)) {
        $array['graphs'][] = $this->getNbControlsByMonth();
    } else {
        $array['disabledGraphs']['ControlsByFiscalYear'] = $langs->transnoentities('ControlsByFiscalYear');
    }
    if (empty($dashboardConfig->graphs->ControlListsByNextControl->hide)) {
        $array['lists'][] = $this->getControlListsByNextControl();
    } else {
        $array['disabledGraphs']['ControlListsByNextControl'] = $langs->transnoentities('ControlListsByNextControl');
    }

    return $array;
}

    /**
     * Get controls by verdict.
     *
     * @return array     Graph datas (label/color/type/title/data etc..).
     * @throws Exception
     */
    public function getNbControlsByVerdict(): array
    {
        global $langs;

        // Graph Title parameters.
        $array['title'] = $langs->transnoentities('ControlsRepartition');
        $array['name']  = 'ControlsRepartition';
        $array['picto'] = $this->picto;

        // Graph parameters.
        $array['width']   = '100%';
        $array['height']  = 400;
        $array['type']    = 'pie';
        $array['dataset'] = 1;

        $array['labels'] = [
            0 => [
                'label' => 'N/A',
                'color' => '#999999'
            ],
            1 => [
                'label' => $langs->transnoentities('OK'),
                'color' => '#47e58e'
            ],
            2 => [
                'label' => $langs->transnoentities('KO'),
                'color' => '#e05353'
            ],
        ];

        $arrayNbControlByVerdict = [0 => 0, 1 => 0, 2 => 0];
        $controls = $this->fetchAll('', '', 0, 0, ['customsql' => 't.status >= 0']);
        if (is_array($controls) && !empty($controls)) {
            foreach ($controls as $control) {
                if (empty($control->verdict)) {
                    $arrayNbControlByVerdict[0]++;
                } else {
                    $arrayNbControlByVerdict[$control->verdict]++;
                }
            }
            ksort($arrayNbControlByVerdict);
        }

        $array['data'] = $arrayNbControlByVerdict;

        return $array;
    }

    /**
     * Get controls with tags by verdict.
     *
     * @return array     Graph datas (label/color/type/title/data etc..).
     * @throws Exception
     */
    public function getNbControlsTagsByVerdict(): array
    {
        global $db, $langs;

        require_once DOL_DOCUMENT_ROOT . '/categories/class/categorie.class.php';

        $category = new Categorie($db);

        // Graph Title parameters.
        $array['title'] = $langs->transnoentities('ControlsTagsRepartition');
        $array['name']  = 'ControlsTagsRepartition';
        $array['picto'] = $this->picto;

        // Graph parameters.
        $array['width']   = '100%';
        $array['height']  = 400;
        $array['type']    = 'bar';
        $array['dataset'] = 3;

        $array['labels'] = [
            0 => [
                'label' => 'N/A',
                'color' => '#999999'
            ],
            1 => [
                'label' => $langs->transnoentities('OK'),
                'color' => '#47e58e'
            ],
            2 => [
                'label' => $langs->transnoentities('KO'),
                'color' => '#e05353'
            ]
        ];

        $categories = $category->get_all_categories('control');
        if (is_array($categories) && !empty($categories)) {
            foreach ($categories as $category) {
                $arrayNbControlByVerdict = [];
                $controls = $this->fetchAll('', '', 0, 0, ['customsql' => 'cp.fk_categorie = ' . $category->id . ' AND t.status >= 0'], 'AND', true);
                if (is_array($controls) && !empty($controls)) {
                    foreach ($controls as $control) {
                        if (empty($control->verdict)) {
                            $arrayNbControlByVerdict[0]++;
                        } else {
                            if (!isset($arrayNbControlByVerdict[$control->verdict])) {
                                $arrayNbControlByVerdict[$control->verdict] = 0;
                            }
                            $arrayNbControlByVerdict[$control->verdict]++;
                        }
                    }
                    $array['data'][] = [$category->label, $arrayNbControlByVerdict[0],  $arrayNbControlByVerdict[1], $arrayNbControlByVerdict[2]];
                }
            }
        }

        return $array;
    }

    /**
     * Get controls by month.
     *
     * @return array     Graph datas (label/color/type/title/data etc..).
     * @throws Exception
     */
public function getNbControlsByMonth(): array
{
    global $conf, $langs;

    $startMonth  = $conf->global->SOCIETE_FISCAL_MONTH_START;
    $currentYear = date('Y', dol_now());
    $years       = [0 => $currentYear - 2, 1 => $currentYear - 1, 2 => $currentYear];

    // Graph Title parameters.
    $array['title'] = $langs->transnoentities('ControlsByFiscalYear');
    $array['name']  = 'ControlsByFiscalYear';
    $array['picto'] = $this->picto;

    // Graph parameters.
    $array['width']      = '100%';
    $array['height']     = 400;
    $array['type']       = 'bars';
    $array['showlegend'] = 1;
    $array['dataset']    = 3;

    $array['labels'] = [
        0 => [
            'label' => $years[0],
            'color' => '#9567AA'
        ],
        1 => [
            'label' => $years[1],
            'color' => '#4F9EBE'
        ],
        2 => [
            'label' => $years[2],
            'color' => '#FAC461'
        ]
    ];

    // Initialize arrayNbControls to avoid undefined key errors
    $arrayNbControls = [
        0 => array_fill(1, 12, 0), // Year - 2
        1 => array_fill(1, 12, 0), // Year - 1
        2 => array_fill(1, 12, 0)  // Current Year
    ];

    for ($i = 1; $i < 13; $i++) {
        foreach ($years as $key => $year) {
            $controls = $this->fetchAll('', '', 0, 0, ['customsql' => 'MONTH(t.date_creation) = ' . $i . ' AND YEAR(t.date_creation) = ' . $year . ' AND t.status >= 0']);
            if (is_array($controls) && !empty($controls)) {
                $arrayNbControls[$key][$i] = count($controls);
            }
        }

        $month    = $langs->transnoentitiesnoconv('MonthShort'.sprintf('%02d', $i));
        $arrayKey = $i - $startMonth;
        $arrayKey = $arrayKey >= 0 ? $arrayKey : $arrayKey + 12;
        $array['data'][$arrayKey] = [
            $month,
            $arrayNbControls[0][$i] ?? 0,
            $arrayNbControls[1][$i] ?? 0,
            $arrayNbControls[2][$i] ?? 0
        ];
    }
    ksort($array['data']);

    return $array;
}

    /**
     * Get controls list by next control.
     *
     * @return array     Graph datas (label/color/type/title/data etc..).
     * @throws Exception
     */
    public function getControlListsByNextControl(): array
    {
        global $langs;

        // Graph Title parameters.
        $array['title'] = $langs->transnoentities('ControlListsByNextControl');
        $array['name']  = 'ControlListsByNextControl';
        $array['picto'] = $this->picto;

        // Graph parameters.
        $array['type']   = 'list';
        $array['labels'] = ['Ref', 'LinkedObject', 'Controller', 'Project', 'Sheet', 'ControlDate', 'NextControl', 'Verdict'];

        $arrayControlListsByNextControl = [];

        $elementArray = get_sheet_linkable_objects();
        $controls     = $this->fetchAll('ASC', 'next_control_date', 10, 0, ['customsql' => 't.status = ' . self::STATUS_LOCKED . ' AND t.next_control_date IS NOT NULL']);
        if (is_array($controls) && !empty($controls)) {
            foreach ($controls as $control) {
                $control->fetchObjectLinked('', '', $control->id, 'digiquali_control', 'OR', 1, 'sourcetype', 0);
                $linkedObjectsInfos = $control->getLinkedObjectsWithQcFrequency($elementArray);
                $linkedObjects      = $linkedObjectsInfos['linkedObjects'];
                $qcFrequencyArray   = $linkedObjectsInfos['qcFrequencyArray'];
                foreach ($elementArray as $linkableObjectType => $linkableObject) {
                    if (is_object($linkedObjects[$linkableObjectType])) {
                        if ($linkableObject['conf'] > 0 && (!empty($control->linkedObjectsIds[$linkableObject['link_name']]))) {
                            $currentObject = $linkedObjects[$linkableObjectType];
                            if ($qcFrequencyArray[$linkableObjectType] > 0) {
                                require_once __DIR__ . '/sheet.class.php';

                                $userTmp = new User($this->db);
                                $project = new Project($this->db);
                                $sheet   = new Sheet($this->db);

                                $userTmp->fetch($control->fk_user_controller);
                                $project->fetch($control->projectid);
                                $sheet->fetch($control->fk_sheet);

                                if (!empty($control->next_control_date)) {
                                    $nextControl          = floor(($control->next_control_date - dol_now('tzuser'))/(3600 * 24));
                                    $nextControlDateColor = $control->getNextControlDateColor();
                                    $verdictColor         = $control->verdict == 1 ? 'green' : ($control->verdict == 2 ? 'red' : 'grey');

                                    $arrayControlListsByNextControl[$control->id]['Ref']['value']            = $control->getNomUrl(1);
                                    $arrayControlListsByNextControl[$control->id]['LinkedObject']['value']   = $currentObject->getNomUrl(1);
                                    $arrayControlListsByNextControl[$control->id]['UserController']['value'] = $userTmp->getNomUrl(1);
                                    $arrayControlListsByNextControl[$control->id]['Project']['value']        = $project->id > 0 ? $project->getNomUrl(1) : '';
                                    $arrayControlListsByNextControl[$control->id]['Sheet']['value']          = $sheet->getNomUrl(1);
                                    $arrayControlListsByNextControl[$control->id]['ControlDate']['value']    = dol_print_date($control->date_creation, 'day');
                                    $arrayControlListsByNextControl[$control->id]['NextControl']['value']    = '<div class="wpeo-button" style="background-color: ' . $nextControlDateColor .'; border-color: ' . $nextControlDateColor . ' ">' . $nextControl . '<br>' . $langs->trans('Days') . '</div>';
                                    $arrayControlListsByNextControl[$control->id]['NextControl']['morecss']  = 'dashboard-control';
                                    $arrayControlListsByNextControl[$control->id]['Verdict']['value']        = '<div class="wpeo-button button-'. $verdictColor .'">' . $control->fields['verdict']['arrayofkeyval'][(!empty($control->verdict)) ?: 3] . '</div>';
                                    $arrayControlListsByNextControl[$control->id]['Verdict']['morecss']      = 'dashboard-control';
                                }
                            }
                        }
                    }
                }
            }
        }
        $array['data'] = $arrayControlListsByNextControl;

        return $array;
    }

	/**
	 * Get control linked objects with qc frequencies.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function getLinkedObjectsWithQcFrequency($linkableObjects): array
	{
		global $db;

		$qcFrequencyArray = [];
		$linkedObjects    = [];

		foreach($linkableObjects as $linkableElementType => $linkableElement) {
			if ($linkableElement['conf'] > 0 && (!empty($this->linkedObjectsIds[$linkableElement['link_name']]))) {
				$className = $linkableElement['className'];
				$linkedObject = new $className($db);

				$linkedObjectKey = array_key_first($this->linkedObjectsIds[$linkableElement['link_name']]);
				$linkedObjectId  = $this->linkedObjectsIds[$linkableElement['link_name']][$linkedObjectKey];

				$result = $linkedObject->fetch($linkedObjectId);
				if ($result > 0) {
					$linkedObjects[$linkableElementType] = $linkedObject;
					if (array_key_exists('options_qc_frequency', $linkedObject->array_options)) {
						if ($linkedObject->array_options['options_qc_frequency'] > 0) {
							$qcFrequencyArray[$linkableElementType] = $linkedObject->array_options['options_qc_frequency'];
						}
					}
				}
			}
		}
		return [
			'qcFrequencyArray' => $qcFrequencyArray,
			'linkedObjects'    => $linkedObjects
			];
	}

	/**
	 * Write information of trigger description
	 *
	 * @param  Object $object Object calling the trigger
	 * @return string         Description to display in actioncomm->note_private
	 */
	public function getTriggerDescription(SaturneObject $object): string
	{
		global $db, $langs;

        // Load DigiQuali libraries
        require_once __DIR__ . '/../class/sheet.class.php';

		$sheet = new Sheet($db);
		$sheet->fetch($object->fk_sheet);

		$ret  = parent::getTriggerDescription($object);
		$ret .= $langs->transnoentities('Sheet') . ' : ' . $sheet->ref . ' - ' . $sheet->label . '</br>';
		if ($object->fk_user_controller > 0) {
			$user = new User($db);
			$user->fetch($object->fk_user_controller);
			$ret .= $langs->transnoentities('Controller') . ' : ' . ucfirst($user->firstname) . ' ' . dol_strtoupper($user->lastname) . '</br>';
		}
		if ($object->projectid > 0) {
			require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
			$project = new Project($db);
			$project->fetch($object->projectid);
			$ret .= $langs->transnoentities('Project') . ' : ' . $project->ref . ' ' . $project->title . '</br>';
		}
		$ret  .= (!empty($object->note_public) ? $langs->transnoentities('NotePublic') . ' : ' . $object->note_public . '</br>' : '');
		$ret  .= (!empty($object->note_private) ? $langs->transnoentities('NotePrivate') . ' : ' . $object->note_private . '</br>' : '');
		$ret  .= (!empty($object->verdict) ? $langs->transnoentities('Verdict') . ' : ' . $object->verdict . '</br>' : '');
		$ret  .= (!empty($object->photo) ? $langs->transnoentities('Photo') . ' : ' . $object->photo . '</br>' : '');

		return $ret;
	}
}

/**
 * Class for ControlLine
 */
class ControlLine extends SaturneObject
{
    /**
     * @var string Module name
     */
    public $module = 'digiquali';

    /**
     * @var string Element type of object
     */
    public $element = 'controldet';

    /**
     * @var string Name of table without prefix where object is stored. This is also the key used for extrafields management
     */
    public $table_element = 'digiquali_controldet';

    /**
     * @var int Does this object support multicompany module ?
     * 0 = No test on entity, 1 = Test with field entity, 'field@table' = Test with link by field@table
     */
    public $ismultientitymanaged = 1;

    /**
     * @var int Does object support extrafields ? 0 = No, 1 = Yes
     */
    public $isextrafieldmanaged = 1;

    /**
     * 'type' field format:
     *      'integer', 'integer:ObjectClass:PathToClass[:AddCreateButtonOrNot[:Filter[:Sortfield]]]',
     *      'select' (list of values are in 'options'),
     *      'sellist:TableName:LabelFieldName[:KeyFieldName[:KeyFieldParent[:Filter[:Sortfield]]]]',
     *      'chkbxlst:...',
     *      'varchar(x)',
     *      'text', 'text:none', 'html',
     *      'double(24,8)', 'real', 'price',
     *      'date', 'datetime', 'timestamp', 'duration',
     *      'boolean', 'checkbox', 'radio', 'array',
     *      'mail', 'phone', 'url', 'password', 'ip'
     *      Note: Filter can be a string like "(t.ref:like:'SO-%') or (t.date_creation:<:'20160101') or (t.nature:is:NULL)"
     * 'label' the translation key.
     * 'picto' is code of a picto to show before value in forms
     * 'enabled' is a condition when the field must be managed (Example: 1 or '$conf->global->MY_SETUP_PARAM' or '!empty($conf->multicurrency->enabled)' ...)
     * 'position' is the sort order of field.
     * 'notnull' is set to 1 if not null in database. Set to -1 if we must set data to null if empty '' or 0.
     * 'visible' says if field is visible in list (Examples: 0=Not visible, 1=Visible on list and create/update/view forms, 2=Visible on list only, 3=Visible on create/update/view form only (not list), 4=Visible on list and update/view form only (not create). 5=Visible on list and view only (not create/not update). Using a negative value means field is not shown by default on list but can be selected for viewing)
     * 'noteditable' says if field is not editable (1 or 0)
     * 'default' is a default value for creation (can still be overwroted by the Setup of Default Values if field is editable in creation form). Note: If default is set to '(PROV)' and field is 'ref', the default value will be set to '(PROVid)' where id is rowid when a new record is created.
     * 'index' if we want an index in database.
     * 'foreignkey'=>'tablename.field' if the field is a foreign key (it is recommanded to name the field fk_...).
     * 'searchall' is 1 if we want to search in this field when making a search from the quick search button.
     * 'isameasure' must be set to 1 or 2 if field can be used for measure. Field type must be summable like integer or double(24,8). Use 1 in most cases, or 2 if you don't want to see the column total into list (for example for percentage)
     * 'css' and 'cssview' and 'csslist' is the CSS style to use on field. 'css' is used in creation and update. 'cssview' is used in view mode. 'csslist' is used for columns in lists. For example: 'css'=>'minwidth300 maxwidth500 widthcentpercentminusx', 'cssview'=>'wordbreak', 'csslist'=>'tdoverflowmax200'
     * 'help' is a 'TranslationString' to use to show a tooltip on field. You can also use 'TranslationString:keyfortooltiponlick' for a tooltip on click.
     * 'showoncombobox' if value of the field must be visible into the label of the combobox that list record
     * 'disabled' is 1 if we want to have the field locked by a 'disabled' attribute. In most cases, this is never set into the definition of $fields into class, but is set dynamically by some part of code.
     * 'arrayofkeyval' to set a list of values if type is a list of predefined values. For example: array("0"=>"Draft","1"=>"Active","-1"=>"Cancel"). Note that type can be 'integer' or 'varchar'
     * 'autofocusoncreate' to have field having the focus on a create form. Only 1 field should have this property set to 1.
     * 'comment' is not used. You can store here any text of your choice. It is not used by application.
     * 'validate' is 1 if you need to validate with $this->validateField()
     * 'copytoclipboard' is 1 or 2 to allow to add a picto to copy value into clipboard (1=picto after label, 2=picto after value)
     *
     * Note: To have value dynamic, you can set value to 0 in definition and edit the value on the fly into the constructor
     */

    /**
     * @var array Array with all fields and their property. Do not use it as a static var. It may be modified by constructor
     */
    public $fields = [
        'rowid'         => ['type' => 'integer',      'label' => 'TechnicalID',      'enabled' => 1, 'position' => 1,   'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1, 'comment' => 'Id'],
        'ref'           => ['type' => 'varchar(128)', 'label' => 'Ref',              'enabled' => 1, 'position' => 10,  'notnull' => 1, 'visible' => 1, 'noteditable' => 1, 'default' => '(PROV)', 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'validate' => 1, 'comment' => 'Reference of object'],
        'ref_ext'       => ['type' => 'varchar(128)', 'label' => 'RefExt',           'enabled' => 1, 'position' => 20,  'notnull' => 0, 'visible' => 0],
        'entity'        => ['type' => 'integer',      'label' => 'Entity',           'enabled' => 1, 'position' => 30,  'notnull' => 1, 'visible' => 0, 'index' => 1],
        'date_creation' => ['type' => 'datetime',     'label' => 'DateCreation',     'enabled' => 1, 'position' => 40,  'notnull' => 1, 'visible' => 0],
        'tms'           => ['type' => 'timestamp',    'label' => 'DateModification', 'enabled' => 1, 'position' => 50,  'notnull' => 0, 'visible' => 0],
        'import_key'    => ['type' => 'varchar(14)',  'label' => 'ImportId',         'enabled' => 1, 'position' => 60,  'notnull' => 0, 'visible' => 0, 'index' => 0],
        'status'        => ['type' => 'smallint',     'label' => 'Status',           'enabled' => 1, 'position' => 70,  'notnull' => 1, 'visible' => 0, 'index' => 1, 'default' => 1],
        'type'          => ['type' => 'varchar(128)', 'label' => 'Type',             'enabled' => 0, 'position' => 80,  'notnull' => 0, 'visible' => 0],
        'answer'        => ['type' => 'text',         'label' => 'Answer',           'enabled' => 1, 'position' => 90,  'notnull' => 0, 'visible' => 0],
        'answer_photo'  => ['type' => 'text',         'label' => 'AnswerPhoto',      'enabled' => 0, 'position' => 100, 'notnull' => 0, 'visible' => 0],
        'comment'       => ['type' => 'text',         'label' => 'Comment',          'enabled' => 1, 'position' => 110, 'notnull' => 0, 'visible' => 0],
        'fk_user_creat' => ['type' => 'integer:User:user/class/user.class.php',              'label' => 'UserAuthor', 'picto' => 'user',                                'enabled' => 1, 'position' => 120, 'notnull' => 1, 'visible' => 0, 'foreignkey' => 'user.rowid'],
        'fk_user_modif' => ['type' => 'integer:User:user/class/user.class.php',              'label' => 'UserModif',  'picto' => 'user',                                'enabled' => 1, 'position' => 130, 'notnull' => 0, 'visible' => 0, 'foreignkey' => 'user.rowid'],
        'fk_control'    => ['type' => 'integer:Control:digiquali/class/survey.class.php',    'label' => 'Control',    'picto' => 'fontawesome_fa-tasks_fas_#d35968',    'enabled' => 1, 'position' => 140,  'notnull' => 1, 'visible' => 0, 'index' => 1, 'css' => 'maxwidth500 widthcentpercentminusxx', 'foreignkey' => 'digiquali_survey.rowid'],
        'fk_question'   => ['type' => 'integer:Question:digiquali/class/question.class.php', 'label' => 'Question',   'picto' => 'fontawesome_fa-question_fas_#d35968', 'enabled' => 1, 'position' => 150,  'notnull' => 1, 'visible' => 0, 'index' => 1, 'css' => 'maxwidth500 widthcentpercentminusxx', 'foreignkey' => 'digiquali_question.rowid'],
    ];

    /**
     * @var int ID
     */
    public int $rowid;

    /**
     * @var string Ref
     */
    public $ref;

    /**
     * @var string Ref ext
     */
    public $ref_ext;

    /**
     * @var int Entity
     */
    public $entity;

    /**
     * @var int|string Creation date
     */
    public $date_creation;

    /**
     * @var int|string Timestamp
     */
    public $tms;

    /**
     * @var string Import key
     */
    public $import_key;

    /**
     * @var int Status
     */
    public $status;

    /**
     * @var string|null Type
     */
    public ?string $type;

    /**
     * @var string|null Answer
     */
    public ?string $answer = '';

    /**
     * @var string|null Answer photo
     */
    public ?string $answer_photo;

    /**
     * @var string|null Comment
     */
    public ?string $comment = '';

    /**
     * @var int User ID
     */
    public $fk_user_creat;

    /**
     * @var int|null User ID
     */
    public $fk_user_modif;

    /**
     * @var int Control ID
     */
    public int $fk_control;

    /**
     * @var ?int|null Question ID
     */
    public int $fk_question;

    /**
     * Constructor
     *
     * @param DoliDb $db Database handler
     */
    public function __construct(DoliDB $db)
    {
        parent::__construct($db, $this->module, $this->element);
    }

    /**
     * Load control line from database form parent with question
     *
     * @param  int        $controlID  Control id
     * @param  int        $questionID Question id
     * @return array|int              Int <0 if KO, array of pages if OK
     * @throws Exception
     */
    public function fetchFromParentWithQuestion(int $controlID, int $questionID)
    {
        return $this->fetchAll('', '', 1, 0, ['customsql' => 't.fk_control = ' . $controlID . ' AND t.fk_question = ' . $questionID . ' AND t.status > 0']);
    }
}

class ControlEquipment extends SaturneObject
{
	/**
	 * @var string Module name.
	 */
	public $module = 'digiquali';

	/**
	 * @var string element to identify managed object
	 */
	public $element = 'control_equipment';

	/**
	 * @var string Name of table without prefix where object is stored
	 */
	public $table_element = 'digiquali_control_equipment';

    /**
     * @var string Name of icon for control_equipment. Must be a 'fa-xxx' fontawesome code (or 'fa-xxx_fa_color_size') or 'control_equipment@digiquali' if picto is file 'img/object_control_equipment.png'.
     */
    public string $picto = 'fontawesome_fa-toolbox_fas_#d35968';

	public const STATUS_DELETED = -1;
	public const STATUS_ENABLED = 1;

	/**
	 * @var array  Array with all fields and their property. Do not use it as a static var. It may be modified by constructor.
	 */
	public $fields = [
		'rowid'         => ['type' => 'integer', 'label' => 'TechnicalID', 'enabled' => '1', 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => '1', 'index' => 1, 'comment' => 'Id'],
		'ref'           => ['type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => '1', 'position' => 10, 'notnull' => 1, 'visible' => 1, 'noteditable' => '1', 'index' => 1, 'searchall' => 1, 'showoncombobox' => '1', 'comment' => 'Reference of object'],
		'ref_ext'       => ['type' => 'varchar(128)', 'label' => 'RefExt', 'enabled' => '1', 'position' => 20, 'notnull' => 0, 'visible' => 0],
		'entity'        => ['type' => 'integer', 'label' => 'Entity', 'enabled' => '1', 'position' => 20, 'notnull' => 1, 'visible' => 0],
		'date_creation' => ['type' => 'datetime', 'label' => 'DateCreation', 'enabled' => '1', 'position' => 30, 'notnull' => 1, 'visible' => 0],
		'tms'           => ['type' => 'timestamp', 'label' => 'DateModification', 'enabled' => '1', 'position' => 40, 'notnull' => 0, 'visible' => 0],
		'status'        => ['type' => 'status', 'label' => 'Status', 'enabled' => '1', 'position' => 50, 'notnull' => 1, 'visible' => 0],
		'json'          => ['type' => 'text', 'label' => 'JSON', 'enabled' => '1', 'position' => 60, 'notnull' => 1, 'visible' => 0],
        'fk_product'    => ['type' => 'integer', 'label' => 'FkProduct', 'enabled' => '1', 'position' => 70, 'notnull' => 1, 'visible' => 0],
        'fk_lot'        => ['type' => 'integer', 'label' => 'FkLot', 'enabled' => '1', 'position' => 75, 'notnull' => 1, 'visible' => 0],
		'fk_control'    => ['type' => 'integer', 'label' => 'FkControl', 'enabled' => '1', 'position' => 80, 'notnull' => 0, 'visible' => 0],
	];

    /**
     * @var int ID.
     */
    public int $rowid;

    /**
     * @var string Ref.
     */
    public $ref;

    /**
     * @var string Ref ext.
     */
    public $ref_ext;

    /**
     * @var int Entity.
     */
    public $entity;

    /**
     * @var int|string Creation date.
     */
    public $date_creation;

    /**
     * @var int|string Timestamp.
     */
    public $tms;

    /**
     * @var string Import key.
     */
    public $import_key;

    /**
     * @var int Status.
     */
    public $status;

    /**
     * @var string Json.
     */
    public $json;

    /**
     * @var int Fk_product.
     */
	public $fk_product;

    /**
     * @var int Fk_lot.
     */
    public $fk_lot;


    /**
     * @var int Fk_control.
     */
	public $fk_control;

	/**
	 * Constructor
	 *
	 * @param DoliDb $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		parent::__construct($db, $this->module, $this->element);
	}

	/**
	 * Create object into database.
	 *
	 * @param  User $user      User that creates.
	 * @param  bool $notrigger false = launch triggers after, true = disable triggers.
	 * @return int             0 < if KO, ID of created object if OK.
	 */
	public function create(User $user, bool $notrigger = false): int
	{
		$this->status = 1;

		return parent::create($user, $notrigger);
	}

    /**
     *    Load control line from database and from parent
     *
     * @param  int       $control_id id of parent control equipment to fetch
     * @param  int       $limit      limit of object to fetch
     * @return array|int             <0 if KO, >0 if OK
     */
    public function fetchFromParent($control_id, $limit = 0)
    {
        return $this->fetchAll('', '', $limit, 0, ['customsql' => 'fk_control = ' . $control_id . ' AND status > 0']);
    }

}
