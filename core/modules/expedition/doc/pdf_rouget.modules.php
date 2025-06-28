<?php
/* Copyright (C) 2005		Rodolphe Quiedeville		<rodolphe@quiedeville.org>
 * Copyright (C) 2005-2012	Laurent Destailleur			<eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012	Regis Houssin				<regis.houssin@inodbox.com>
 * Copyright (C) 2014-2015	Marcos García				<marcosgdf@gmail.com>
 * Copyright (C) 2018-2024	Frédéric France				<frederic.france@free.fr>
 * Copyright (C) 2024		MDW							<mdeweerd@users.noreply.github.com>
 * Copyright (C) 2024		Nick Fragoulis
 * Copyright (C) 2024		Alexandre Spangaro			<alexandre@inovea-conseil.com>
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
 * or see https://www.gnu.org/
 */

/**
 *	\file       htdocs/core/modules/expedition/doc/pdf_rouget.modules.php
 *	\ingroup    expedition
 *	\brief      Class file used to generate the dispatch slips for the Rouget model
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/expedition/modules_expedition.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/product.lib.php';
require_once DOL_DOCUMENT_ROOT.'/mrp/class/mo.class.php'; // Added for MO/BOM features
require_once DOL_DOCUMENT_ROOT.'/bom/class/bom.class.php';   // Added for BOM features (if needed)


/**
 *	Class to build sending documents with model Rouget
 */
class pdf_rouget extends ModelePdfExpedition
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string model name
	 */
	public $name;

	/**
	 * @var string model description (short text)
	 */
	public $description;

	/**
	 * @var int     Save the name of generated file as the main doc when generating a doc with this template
	 */
	public $update_main_doc_field;

	/**
	 * @var string document type
	 */
	public $type;

	/**
	 * Dolibarr version of the loaded document
	 * @var string Version, possible values are: 'development', 'experimental', 'dolibarr', 'dolibarr_deprecated' or a version string like 'x.y.z'''|'development'|'dolibarr'|'experimental'
	 */
	public $version = 'dolibarr';

	/**
	 * @var float|int
	 */
	public $posxweightvol;
	/**
	 * @var float|int
	 */
	public $posxqtytoship;
	/**
	 * @var float|int
	 */
	public $posxqtyordered;


	/**
	 *	Constructor
	 *
	 *	@param	DoliDB	$db		Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $conf, $langs, $mysoc;

		$this->db = $db;
		$this->name = "rouget";
		$this->description = $langs->trans("DocumentModelStandardPDF").' ('.$langs->trans("OldImplementation").')';
		$this->update_main_doc_field = 1; // Save the name of generated file as the main doc when generating a doc with this template

		$this->type = 'pdf';
		$formatarray = pdf_getFormat();
		$this->page_largeur = $formatarray['width'];
		$this->page_hauteur = $formatarray['height'];
		$this->format = array($this->page_largeur, $this->page_hauteur);
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
		$this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);
		$this->corner_radius = getDolGlobalInt('MAIN_PDF_FRAME_CORNER_RADIUS', 0);
		$this->option_logo = 1; // Display logo
		$this->option_draft_watermark = 1; // Support add of a watermark on drafts
		$this->watermark = '';

		// Define position of columns. New structure: Description | (Picture) | U.P. HT | QTY (Shipped) | Total HT
		$this->posxdesc = $this->marge_gauche + 1;

		// Define target column widths
		$unitprice_ht_width = 20;
		$qty_shipped_width = 15;
		$total_ht_width = 25;
		$picture_width = getDolGlobalInt('MAIN_DOCUMENTS_WITH_PICTURE_WIDTH', 20);

		// Default end of description area (will be adjusted if pictures are shown)
		$end_of_desc_area = $this->page_largeur - $this->marge_droite;

		if (getDolGlobalString('SHIPPING_PDF_DISPLAY_AMOUNT_HT')) {
			// Prices are shown. Columns from right: Total HT, QTY Shipped, U.P. HT
			$this->posxtotalht = $this->page_largeur - $this->marge_droite - $total_ht_width;

			if (getDolGlobalString('SHIPPING_PDF_HIDE_ORDERED')) { // This global setting now hides QTY Shipped
				$this->posxqtyordered = $this->posxtotalht; // QTY Shipped column zero-width
				$this->posxpuht = $this->posxtotalht - $unitprice_ht_width; // U.P. HT is to the left of Total HT
			} else {
				$this->posxqtyordered = $this->posxtotalht - $qty_shipped_width; // QTY Shipped column
				$this->posxpuht = $this->posxqtyordered - $unitprice_ht_width; // U.P. HT column
			}
			$end_of_desc_area = $this->posxpuht;
		} else {
			// Prices are NOT shown. Only QTY Shipped might be shown.
			if (getDolGlobalString('SHIPPING_PDF_HIDE_ORDERED')) { // Hides QTY Shipped
				$this->posxqtyordered = $this->page_largeur - $this->marge_droite; // QTY Shipped column zero-width, desc fills all
			} else {
				$this->posxqtyordered = $this->page_largeur - $this->marge_droite - $qty_shipped_width; // QTY Shipped column
			}
			$end_of_desc_area = $this->posxqtyordered;
			// Ensure price column pos are set to not interfere / effectively hidden
			$this->posxpuht = $this->posxqtyordered;
			$this->posxtotalht = $this->posxqtyordered;
		}

		// Picture column position (if pictures are generated)
		// $this->atleastonephoto is set in write_file, for now we base layout on global setting
		if (getDolGlobalString('MAIN_GENERATE_SHIPMENT_WITH_PICTURE')) {
			$this->posxpicture = $end_of_desc_area - $picture_width;
		} else {
			$this->posxpicture = $end_of_desc_area; // No space for picture, desc extends to $end_of_desc_area
		}

		// $this->posxweightvol traditionally marks the end of the description/picture area and start of data columns
		$this->posxweightvol = $this->posxpicture;

		// $this->posxqtytoship is no longer used (removed column) - set for safety if any old code refers to it
		$this->posxqtytoship = $this->posxqtyordered;


		// Adjustments for narrow page formats (e.g., US Executive < 200mm)
		// This shifts all data columns and picture column further left.
		if ($this->page_largeur < 200) {
			$narrow_page_reduction = 10; // Shift left by 10mm
			if (getDolGlobalString('SHIPPING_PDF_DISPLAY_AMOUNT_HT')) {
				$this->posxtotalht += $narrow_page_reduction;
				$this->posxpuht += $narrow_page_reduction;
			}
			$this->posxqtyordered += $narrow_page_reduction;
			// $this->posxpicture itself is calculated from other posx*, so they carry the reduction.
			// However, if it was set based on page_largeur initially, it might also need direct adjustment.
			// Let's adjust the calculated $this->posxpicture and $this->posxweightvol too.
			$this->posxpicture += $narrow_page_reduction;
			$this->posxweightvol += $narrow_page_reduction;
			$this->posxqtytoship += $narrow_page_reduction;
		}

		if ($mysoc === null) {
			dol_syslog(get_class($this).'::__construct() Global $mysoc should not be null.'. getCallerInfoString(), LOG_ERR);
			return;
		}

		// Get source company
		$this->emetteur = $mysoc;
		if (!$this->emetteur->country_code) {
			$this->emetteur->country_code = substr($langs->defaultlang, -2); // By default if not defined
		}
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *  Function to build pdf onto disk
	 *
	 *	@param		Expedition	$object				Object shipping to generate (or id if old method)
	 *  @param		Translate	$outputlangs		Lang output object
	 *  @param		string		$srctemplatepath	Full path of source filename for generator using a template file
	 *  @param		int<0,1>	$hidedetails		Do not show line details
	 *  @param		int<0,1>	$hidedesc			Do not show desc
	 *  @param		int<0,1>	$hideref			Do not show ref
	 *  @return		int<-1,1>						1 if OK, <=0 if KO
	 */
	public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
	{
		// phpcs:enable
		global $user, $conf, $langs, $hookmanager;

        // Initialize grand total
        $this->calculated_grand_total_ht = 0.0;

		$object->fetch_thirdparty();

		if (!is_object($outputlangs)) {
			$outputlangs = $langs;
		}
		// For backward compatibility with FPDF, force output charset to ISO, because FPDF expect text to be encoded in ISO
		if (getDolGlobalString('MAIN_USE_FPDF')) {
			$outputlangs->charset_output = 'ISO-8859-1';
		}

		// Load traductions files required by page
		$outputlangs->loadLangs(array("main", "bills", "orders", "products", "dict", "companies", "propal", "deliveries", "sendings", "productbatch", "other", "compta"));

		// Show Draft Watermark
		if ($object->statut == $object::STATUS_DRAFT && (getDolGlobalString('SHIPPING_DRAFT_WATERMARK'))) {
			$this->watermark = getDolGlobalString('SHIPPING_DRAFT_WATERMARK');
		}

		global $outputlangsbis;
		$outputlangsbis = null;
		if (getDolGlobalString('PDF_USE_ALSO_LANGUAGE_CODE') && $outputlangs->defaultlang != getDolGlobalString('PDF_USE_ALSO_LANGUAGE_CODE')) {
			$outputlangsbis = new Translate('', $conf);
			$outputlangsbis->setDefaultLang(getDolGlobalString('PDF_USE_ALSO_LANGUAGE_CODE'));
			$outputlangsbis->loadLangs(array("main", "bills", "orders", "products", "dict", "companies", "propal", "deliveries", "sendings", "productbatch", "other", "compta"));
		}

		$nblines = is_array($object->lines) ? count($object->lines) : 0;

		// Loop on each lines to detect if there is at least one image to show
		$realpatharray = array();
		$this->atleastonephoto = false;
		if (getDolGlobalString('MAIN_GENERATE_SHIPMENT_WITH_PICTURE')) {
			$objphoto = new Product($this->db);

			for ($i = 0; $i < $nblines; $i++) {
				if (empty($object->lines[$i]->fk_product)) {
					continue;
				}

				$objphoto = new Product($this->db);
				$objphoto->fetch($object->lines[$i]->fk_product);
				if (getDolGlobalInt('PRODUCT_USE_OLD_PATH_FOR_PHOTO')) {
					$pdir = get_exdir($object->lines[$i]->fk_product, 2, 0, 0, $objphoto, 'product').$object->lines[$i]->fk_product."/photos/";
					$dir = $conf->product->dir_output.'/'.$pdir;
				} else {
					$pdir = get_exdir(0, 2, 0, 0, $objphoto, 'product').dol_sanitizeFileName($objphoto->ref).'/';
					$dir = $conf->product->dir_output.'/'.$pdir;
				}

				$realpath = '';

				foreach ($objphoto->liste_photos($dir, 1) as $key => $obj) {
					if (!getDolGlobalInt('CAT_HIGH_QUALITY_IMAGES')) {
						// If CAT_HIGH_QUALITY_IMAGES not defined, we use thumb if defined and then original photo
						if ($obj['photo_vignette']) {
							$filename = $obj['photo_vignette'];
						} else {
							$filename = $obj['photo'];
						}
					} else {
						$filename = $obj['photo'];
					}

					$realpath = $dir.$filename;
					$this->atleastonephoto = true;
					break;
				}

				if ($realpath) {
					$realpatharray[$i] = $realpath;
				}
			}
		}

		if (count($realpatharray) == 0) {
			$this->posxpicture = $this->posxweightvol;
		}

		if ($conf->expedition->dir_output) {
			// Definition of $dir and $file
			if ($object->specimen) {
				$dir = $conf->expedition->dir_output."/sending";
				$file = $dir."/SPECIMEN.pdf";
			} else {
				$expref = dol_sanitizeFileName($object->ref);
				$dir = $conf->expedition->dir_output."/sending/".$expref;
				$file = $dir."/".$expref.".pdf";
			}

			if (!file_exists($dir)) {
				if (dol_mkdir($dir) < 0) {
					$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
					return 0;
				}
			}

			if (file_exists($dir)) {
				// Add pdfgeneration hook
				if (!is_object($hookmanager)) {
					include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
					$hookmanager = new HookManager($this->db);
				}
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
				global $action;
				$reshook = $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks

				// Set nblines with the new facture lines content after hook
				$nblines = is_array($object->lines) ? count($object->lines) : 0;

				$pdf = pdf_getInstance($this->format);
				$default_font_size = pdf_getPDFFontSize($outputlangs);
				$heightforinfotot = 8; // Height reserved to output the info and total part
				$heightforfreetext = getDolGlobalInt('MAIN_PDF_FREETEXT_HEIGHT', 5); // Height reserved to output the free text on last page
				$heightforfooter = $this->marge_basse + 8; // Height reserved to output the footer (value include bottom margin)
				if (getDolGlobalString('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS')) {
					$heightforfooter += 6;
				}
				$pdf->SetAutoPageBreak(1, 0);

				if (class_exists('TCPDF')) {
					$pdf->setPrintHeader(false);
					$pdf->setPrintFooter(false);
				}
				$pdf->SetFont(pdf_getPDFFont($outputlangs));
				// Set path to the background PDF File
				if (!getDolGlobalString('MAIN_DISABLE_FPDI') && getDolGlobalString('MAIN_ADD_PDF_BACKGROUND')) {
					$pagecount = $pdf->setSourceFile($conf->mycompany->dir_output.'/' . getDolGlobalString('MAIN_ADD_PDF_BACKGROUND'));
					$tplidx = $pdf->importPage(1);
				}

				$pdf->Open();
				$pagenb = 0;
				$pdf->SetDrawColor(128, 128, 128);

				if (method_exists($pdf, 'AliasNbPages')) {
					$pdf->AliasNbPages();  // @phan-suppress-current-line PhanUndeclaredMethod
				}

				$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
				$pdf->SetSubject($outputlangs->transnoentities("Shipment"));
				$pdf->SetCreator("Dolibarr ".DOL_VERSION);
				$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
				$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref)." ".$outputlangs->transnoentities("Shipment"));
				if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
					$pdf->SetCompression(false);
				}

				// @phan-suppress-next-line PhanPluginSuspiciousParamOrder
				$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite); // Left, Top, Right

				// New page
				$pdf->AddPage();
				if (!empty($tplidx)) {
					$pdf->useTemplate($tplidx);
				}
				$pagenb++;
				// $top_shift = $this->_pagehead($pdf, $object, 1, $outputlangs); // Old call
				$pagehead_data = $this->_pagehead($pdf, $object, 1, $outputlangs, $outputlangsbis); // Pass $outputlangsbis
				$top_shift = $pagehead_data['top_shift'];
				// $shipp_shift = $pagehead_data['shipp_shift']; // Store if needed later, not currently used in this write_file after top_shift is used for tab_top_newpage
				$pdf->SetFont('', '', $default_font_size - 1);
				$pdf->MultiCell(0, 3, ''); // Set interline to 3
				$pdf->SetTextColor(0, 0, 0);

				$tab_top = 90;	// position of top tab
				// $tab_top_newpage calculation relies on $top_shift being a number.
				$tab_top_newpage = (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD') ? 42 + $top_shift : 10);

				$tab_height = $this->page_hauteur - $tab_top - $heightforfooter - $heightforfreetext;

				// Incoterm
				$height_incoterms = 0;
				if (isModEnabled('incoterm')) {
					$desc_incoterms = $object->getIncotermsForPDF();
					if ($desc_incoterms) {
						$tab_top -= 2;

						$pdf->SetFont('', '', $default_font_size - 1);
						$pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $tab_top - 1, dol_htmlentitiesbr($desc_incoterms), 0, 1);
						$nexY = $pdf->GetY();
						$height_incoterms = $nexY - $tab_top;

						// Rect takes a length in 3rd parameter
						$pdf->SetDrawColor(192, 192, 192);
						$pdf->RoundedRect($this->marge_gauche, $tab_top - 1, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $height_incoterms + 3, $this->corner_radius, '1234', 'D');

						$tab_top = $nexY + 6;
						$height_incoterms += 4;
					}
				}

				// Public note and Tracking code
				if (!empty($object->note_public) || !empty($object->tracking_number)) {
					$tab_top_alt = $tab_top;

					//$tab_top_alt += 1;

					// Tracking number
					if (!empty($object->tracking_number)) {
						$height_trackingnumber = 4;

						$pdf->SetFont('', 'B', $default_font_size - 2);
						$pdf->writeHTMLCell(60, $height_trackingnumber, $this->posxdesc - 1, $tab_top - 1, $outputlangs->transnoentities("TrackingNumber")." : ".$object->tracking_number, 0, 1, false, true, 'L');
						$tab_top_alt = $pdf->GetY();

						$object->getUrlTrackingStatus($object->tracking_number);
						if (!empty($object->tracking_url)) {
							if ($object->shipping_method_id > 0) {
								// Get code using getLabelFromKey
								$code = $outputlangs->getLabelFromKey($this->db, $object->shipping_method_id, 'c_shipment_mode', 'rowid', 'code');
								$label = '';
								if ($object->tracking_url != $object->tracking_number) {
									$label .= $outputlangs->trans("LinkToTrackYourPackage")."<br>";
								}
								$label .= $outputlangs->trans("SendingMethod").": ".$outputlangs->trans("SendingMethod".strtoupper($code));
								//var_dump($object->tracking_url != $object->tracking_number);exit;
								if ($object->tracking_url != $object->tracking_number) {
									$label .= " : ";
									$label .= $object->tracking_url;
								}

								$height_trackingnumber += 4;
								$pdf->SetFont('', 'B', $default_font_size - 2);
								$pdf->writeHTMLCell(60, $height_trackingnumber, $this->posxdesc - 1, $tab_top_alt, $label, 0, 1, false, true, 'L');
							}
						}
						$tab_top = $pdf->GetY();
					}

					// Notes
					if (!empty($object->note_public)) {
						$pdf->SetFont('', '', $default_font_size - 1); // Dans boucle pour gerer multi-page
						$pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $tab_top, dol_htmlentitiesbr($object->note_public), 0, 1);
					}

					$nexY = $pdf->GetY();
					$height_note = $nexY - $tab_top;

					// Rect takes a length in 3rd parameter
					$pdf->SetDrawColor(192, 192, 192);
					$pdf->RoundedRect($this->marge_gauche, $tab_top - 1, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $height_note + 3, $this->corner_radius, '1234', 'D');

					$tab_height -= $height_note;
					$tab_top = $nexY + 6;
				} else {
					$height_note = 0;
				}

				// Show barcode - REMOVED
				/*
				$height_barcode = 0;
				//$pdf->Rect($this->marge_gauche, $this->marge_haute, $this->page_largeur-$this->marge_gauche-$this->marge_droite, 30);
				if (isModEnabled('barcode') && getDolGlobalString('BARCODE_ON_SHIPPING_PDF')) {
					require_once DOL_DOCUMENT_ROOT.'/core/modules/barcode/doc/tcpdfbarcode.modules.php';

					$encoding = 'QRCODE';
					$module = new modTcpdfbarcode();
					$barcode_path = '';
					$result = 0;
					if ($module->encodingIsSupported($encoding)) {
						$result = $module->writeBarCode($object->ref, $encoding);

						// get path of qrcode image
						$newcode = $object->ref;
						if (!preg_match('/^\w+$/', $newcode) || dol_strlen($newcode) > 32) {
							$newcode = dol_hash($newcode, 'md5');
						}
						$barcode_path = $conf->barcode->dir_temp . '/barcode_' . $newcode . '_' . $encoding . '.png';
					}

					if ($result > 0) {
						$tab_top -= 2;

						$pdf->Image($barcode_path, $this->marge_gauche, $tab_top, 20, 20);

						$nexY = $pdf->GetY();
						$height_barcode = 20;

						$tab_top += 22;
					} else {
						$this->error = 'Failed to generate barcode';
					}
				}
				*/


				$iniY = $tab_top + 7;
				$curY = $tab_top + 7;
				$nexY = $tab_top + 7;

				// Loop on each lines
				for ($i = 0; $i < $nblines; $i++) {
					$curY = $nexY;
					$pdf->SetFont('', '', $default_font_size - 1); // Into loop to work with multipage
					$pdf->SetTextColor(0, 0, 0);

					// --- BEGIN: Product 483 MO/BOM Data Fetching ---
					$product_483_components = array(); // Initialize for each line
					$extracted_mo_ref = null;

					if ($object->lines[$i]->fk_product == 483) {
						$line_description = $object->lines[$i]->desc;
						if (empty($line_description) && !empty($object->lines[$i]->libelle)) { // Fallback to libelle if desc is empty
							$line_description = $object->lines[$i]->libelle;
						}

						if (!empty($line_description) && preg_match('/([A-Za-z0-9-]+-?\d+)(?: \(Fabrication\))?/', $line_description, $matches)) {
							$extracted_mo_ref = $matches[1];
							dol_syslog("pdf_rouget: Product 483 - Extracted MO Ref: " . $extracted_mo_ref . " from line " . $i, LOG_DEBUG);

							$mo = new Mo($this->db);
							if ($mo->fetch(0, $extracted_mo_ref) > 0) {
								// Option 1: Try to get components from MO lines (role 'toconsume')
								if (!empty($mo->lines)) {
									foreach ($mo->lines as $moline) {
										if ($moline->role == 'toconsume' && $moline->fk_product > 0) {
											$component_product = new Product($this->db);
											if ($component_product->fetch($moline->fk_product) > 0) {
												$product_483_components[] = array(
													'ref' => $component_product->ref,
													'label' => $component_product->label,
													'qty' => $moline->qty_needed // Or $moline->qty
												);
											}
										}
									}
								}

								// Option 2: If no components from MO lines, or as a fallback/addition, check fk_bom
								// For this integration, let's prioritize MO lines if they exist, then consider BOM if MO lines are empty.
								if (empty($product_483_components) && $mo->fk_bom > 0) {
									dol_syslog("pdf_rouget: Product 483 - MO Ref: " . $extracted_mo_ref . " - No 'toconsume' lines, trying fk_bom: " . $mo->fk_bom, LOG_DEBUG);
									$sql_bom_lines = "SELECT bl.fk_product, bl.qty";
									$sql_bom_lines .= " FROM ".MAIN_DB_PREFIX."bom_bomline as bl";
									$sql_bom_lines .= " WHERE bl.fk_bom = " . (int)$mo->fk_bom;
									$sql_bom_lines .= " ORDER BY bl.position ASC"; // Or relevant order

									$resql_bom_lines = $this->db->query($sql_bom_lines);
									if ($resql_bom_lines) {
										while ($bom_line_row = $this->db->fetch_object($resql_bom_lines)) {
											if ($bom_line_row->fk_product > 0) {
												$component_product = new Product($this->db);
												if ($component_product->fetch($bom_line_row->fk_product) > 0) {
													$product_483_components[] = array(
														'ref' => $component_product->ref,
														'label' => $component_product->label,
														'qty' => $bom_line_row->qty
													);
												}
											}
										}
										$this->db->free($resql_bom_lines);
									} else {
										dol_syslog("pdf_rouget: Product 483 - DB error fetching BOM lines for BOM ID: " . $mo->fk_bom . " - " . $this->db->lasterror(), LOG_ERR);
									}
								}
								if (empty($product_483_components)){
									dol_syslog("pdf_rouget: Product 483 - MO Ref: " . $extracted_mo_ref . " - No components found from MO lines or BOM.", LOG_WARNING);
								}

							} else {
								dol_syslog("pdf_rouget: Product 483 - Failed to fetch MO with ref: " . $extracted_mo_ref . ". Error: " . $mo->error, LOG_ERR);
							}
						} else {
							dol_syslog("pdf_rouget: Product 483 - Could not extract MO reference from description: '" . $line_description . "' for line " . $i, LOG_WARNING);
						}
					}
					// --- END: Product 483 MO/BOM Data Fetching ---

					// Define size of image if we need it
					$imglinesize = array();
					if (!empty($realpatharray[$i])) {
						$imglinesize = pdf_getSizeForImage($realpatharray[$i]);
					}

					$pdf->setTopMargin($tab_top_newpage);
					$pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext + $heightforinfotot); // The only function to edit the bottom margin of current page to set it.
					$pageposbefore = $pdf->getPage();

					$showpricebeforepagebreak = 1;
					$posYAfterImage = 0;
					$posYAfterDescription = 0;
					$heightforsignature = 0;

					// We start with Photo of product line
					if (isset($imglinesize['width']) && isset($imglinesize['height']) && ($curY + $imglinesize['height']) > ($this->page_hauteur - ($heightforfooter + $heightforfreetext + $heightforinfotot))) {	// If photo too high, we moved completely on new page
						$pdf->AddPage('', '', true);
						if (!empty($tplidx)) {
							$pdf->useTemplate($tplidx);
						}
						if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
							$this->_pagehead($pdf, $object, 0, $outputlangs);
						}
						$pdf->setPage($pageposbefore + 1);

						$curY = $tab_top_newpage;

						// Allows data in the first page if description is long enough to break in multiples pages
						if (getDolGlobalString('MAIN_PDF_DATA_ON_FIRST_PAGE')) {
							$showpricebeforepagebreak = 1;
						} else {
							$showpricebeforepagebreak = 0;
						}
					}

					if (isset($imglinesize['width']) && isset($imglinesize['height'])) {
						$curX = $this->posxpicture - 1;
						$pdf->Image($realpatharray[$i], $curX + (($this->posxweightvol - $this->posxpicture - $imglinesize['width']) / 2), $curY, $imglinesize['width'], $imglinesize['height'], '', '', '', 2, 300); // Use 300 dpi
						// $pdf->Image does not increase value return by getY, so we save it manually
						$posYAfterImage = $curY + $imglinesize['height'];
					}

					// Description of product line
					$curX = $this->posxdesc - 1;

					$pdf->startTransaction();
					pdf_writelinedesc($pdf, $object, $i, $outputlangs, $this->posxpicture - $curX, 3, $curX, $curY, $hideref, $hidedesc);

					$pageposafter = $pdf->getPage();
					if ($pageposafter > $pageposbefore) {	// There is a pagebreak
						$pdf->rollbackTransaction(true);
						$pageposafter = $pageposbefore;
						//print $pageposafter.'-'.$pageposbefore;exit;
						$pdf->setPageOrientation('', 1, $heightforfooter); // The only function to edit the bottom margin of current page to set it.
						pdf_writelinedesc($pdf, $object, $i, $outputlangs, $this->posxpicture - $curX, 3, $curX, $curY, $hideref, $hidedesc);

						$pageposafter = $pdf->getPage();
						$posyafter = $pdf->GetY();
						//var_dump($posyafter); var_dump(($this->page_hauteur - ($heightforfooter+$heightforfreetext+$heightforinfotot))); exit;
						if ($posyafter > ($this->page_hauteur - ($heightforfooter + $heightforfreetext + $heightforinfotot))) {	// There is no space left for total+free text
							if ($i == ($nblines - 1)) {	// No more lines, and no space left to show total, so we create a new page
								$pdf->AddPage('', '', true);
								if (!empty($tplidx)) {
									$pdf->useTemplate($tplidx);
								}
								if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
									$this->_pagehead($pdf, $object, 0, $outputlangs);
								}
								$pdf->setPage($pageposafter + 1);
							}
						} else {
							// We found a page break

							// Allows data in the first page if description is long enough to break in multiples pages
							if (getDolGlobalString('MAIN_PDF_DATA_ON_FIRST_PAGE')) {
								$showpricebeforepagebreak = 1;
							} else {
								$showpricebeforepagebreak = 0;
							}
						}
					} else { // No pagebreak
						$pdf->commitTransaction();
					}
					$posYAfterDescription = $pdf->GetY();

					$nexY = max($pdf->GetY(), $posYAfterImage);
					$pageposafter = $pdf->getPage();

					$pdf->setPage($pageposbefore);
					$pdf->setTopMargin($this->marge_haute);
					$pdf->setPageOrientation('', 1, 0); // The only function to edit the bottom margin of current page to set it.

					// We suppose that a too long description or photo were moved completely on next page
					if ($pageposafter > $pageposbefore && empty($showpricebeforepagebreak)) {
						$pdf->setPage($pageposafter);
						$curY = $tab_top_newpage;
					}

					// We suppose that a too long description is moved completely on next page
					if ($pageposafter > $pageposbefore) {
						$pdf->setPage($pageposafter);
						$curY = $tab_top_newpage;
					}

					$pdf->SetFont('', '', $default_font_size - 1); // We reposition the default font

					// Weight and Volume data printing is removed.
					// QtyToShip column is removed. Old QtyOrdered column name now refers to QTY (Shipped).

					// --- BEGIN: Data for new columns: U.P. HT, QTY (Shipped), Total HT ---
					$unit_price_ht_text = '';
					$line_total_ht_text = '';
					// Use qty_shipped for the QTY (Shipped) column from the expedition line itself
					$shipped_qty_val = isset($object->lines[$i]->qty_shipped) ? $object->lines[$i]->qty_shipped : (isset($object->lines[$i]->qty) ? $object->lines[$i]->qty : 0);

					if (getDolGlobalString('SHIPPING_PDF_DISPLAY_AMOUNT_HT')) {
						if (!class_exists('CommandeDet')) { // Ensure CommandeDet class is available
							require_once DOL_DOCUMENT_ROOT.'/commande/class/commandedet.class.php';
						}
						$order_line = new CommandeDet($this->db);
						// fk_origin_line links expeditiondet to commandedet
						if (!empty($object->lines[$i]->fk_origin_line) && $order_line->fetch($object->lines[$i]->fk_origin_line) > 0) {
							$unitprice = $order_line->subprice;
							// Calculate line total based on SHIPPED quantity and ORDER unit price
							$line_total_ht = $unitprice * $shipped_qty_val;

							$unit_price_ht_text = price($unitprice, 0, $outputlangs);
							$line_total_ht_text = price($line_total_ht, 0, $outputlangs);

							if (is_numeric($line_total_ht)) { // Ensure we add a number
								$this->calculated_grand_total_ht += $line_total_ht;
							}
						} else {
							dol_syslog("pdf_rouget: Could not fetch order line for price data using fk_origin_line: " . ($object->lines[$i]->fk_origin_line ?? 'N/A') . " for expeditiondet rowid " . $object->lines[$i]->rowid, LOG_WARNING);
							// Prices will remain empty or show 0 if fetch fails
							$unit_price_ht_text = price(0, 0, $outputlangs);
							$line_total_ht_text = price(0, 0, $outputlangs);
						}
					}

					// Print columns: U.P. HT, QTY (Shipped), Total HT
					// $this->posxpuht, $this->posxqtyordered, $this->posxtotalht are defined in constructor
					// Note: $this->posxqtyordered now refers to the start of the QTY (Shipped) column.
					if (getDolGlobalString('SHIPPING_PDF_DISPLAY_AMOUNT_HT')) {
						// U.P. HT Column
						$pdf->SetXY($this->posxpuht, $curY);
						// Width: from U.P. HT start to QTY (Shipped) start. Use -1 for minor padding from line.
						$pdf->MultiCell($this->posxqtyordered - $this->posxpuht -1, 3, $unit_price_ht_text, '', 'R');

						// QTY (Shipped) Column - only if not hidden by SHIPPING_PDF_HIDE_ORDERED (which now controls this QTY column)
						if (!getDolGlobalString('SHIPPING_PDF_HIDE_ORDERED')) {
							$pdf->SetXY($this->posxqtyordered, $curY);
							// Width: from QTY (Shipped) start to Total HT start
							$pdf->MultiCell($this->posxtotalht - $this->posxqtyordered -1, 3, $shipped_qty_val, '', 'C');
						}

						// TOTAL HT Column
						$pdf->SetXY($this->posxtotalht, $curY);
						// Width: from Total HT start to page margin (effective end of table)
						$pdf->MultiCell($this->page_largeur - $this->marge_droite - $this->posxtotalht, 3, $line_total_ht_text, '', 'R');
					} else {
						// Prices are NOT shown: only QTY (Shipped) column might be shown
						if (!getDolGlobalString('SHIPPING_PDF_HIDE_ORDERED')) {
							$pdf->SetXY($this->posxqtyordered, $curY);
							// Width: from QTY (Shipped) start to page margin
							$pdf->MultiCell($this->page_largeur - $this->marge_droite - $this->posxqtyordered, 3, $shipped_qty_val, '', 'C');
						}
					}
					// --- END: Data for new columns ---

					$nexY = max($nexY, $pdf->GetY()); // Update nexY after all cells for the line are printed

					// --- BEGIN: Product 483 Component Display ---
					if ($object->lines[$i]->fk_product == 483 && !empty($product_483_components)) {
						$curY_component = $nexY +1; // Start components a bit below the main line's lowest point

						// Check for page break before component title
						// Estimate title height + one line of component ~ 5mm
						if ($curY_component + 5 > ($this->page_hauteur - $heightforfooter - $heightforfreetext - $heightforinfotot)) {
							$pdf->AddPage('', '', true);
							if (!empty($tplidx)) $pdf->useTemplate($tplidx);
							if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) $this->_pagehead($pdf, $object, 0, $outputlangs, $outputlangsbis);
							$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, 1, 1);
							$curY_component = $tab_top_newpage + 5;
						}

						$pdf->SetFont('', 'B', $default_font_size - 2);
						$pdf->SetXY($this->marge_gauche + 3, $curY_component);
						$pdf->MultiCell(0, 3, $outputlangs->transnoentities("BOMComponents") . ":", 0, 'L');
						$curY_component = $pdf->GetY() + 0.5; // Space after title

						$pdf->SetFont('', '', $default_font_size - 2);
						$component_line_height = 3;

						foreach ($product_483_components as $component) {
							if ($curY_component + $component_line_height > ($this->page_hauteur - $heightforfooter - $heightforfreetext - $heightforinfotot - 2 )) {
                                $pdf->AddPage('', '', true);
                                if (!empty($tplidx)) $pdf->useTemplate($tplidx);
                                if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) $this->_pagehead($pdf, $object, 0, $outputlangs, $outputlangsbis);
                                $this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, 1, 1);
                                $curY_component = $tab_top_newpage + 5;
                            }

							$component_text = "- " . $component['ref'] . " - " . dol_trunc($component['label'], 40) . " (x" . $component['qty'] . ")";
							$pdf->SetXY($this->marge_gauche + 5, $curY_component);
                            $component_text_width = ($this->posxweightvol -1) - ($this->marge_gauche + 5);
                            if ($component_text_width < 10) $component_text_width = $this->page_largeur - $this->marge_gauche - $this->marge_droite - 10; // Fallback width
							$pdf->MultiCell($component_text_width, $component_line_height, $outputlangs->convToOutputCharset($component_text), 0, 'L');
							$curY_component = $pdf->GetY();
						}
						$nexY = $curY_component;
					}
					// --- END: Product 483 Component Display ---

					$nexY += 2; // Add small space before potential dashed line / next item
					// Add line
					if (getDolGlobalString('MAIN_PDF_DASH_BETWEEN_LINES') && $i < ($nblines - 1)) {
						$pdf->setPage($pageposafter);
						$pdf->SetLineStyle(array('dash' => '1,1', 'color' => array(80, 80, 80)));
						//$pdf->SetDrawColor(190,190,200);
						$pdf->line($this->marge_gauche, $nexY - 1, $this->page_largeur - $this->marge_droite, $nexY - 1);
						$pdf->SetLineStyle(array('dash' => 0));
					}

					// Detect if some page were added automatically and output _tableau for past pages
					while ($pagenb < $pageposafter) {
						$pdf->setPage($pagenb);
						if ($pagenb == 1) {
							$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, 0, 1);
						} else {
							$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, 1, 1);
						}
						$this->_pagefoot($pdf, $object, $outputlangs, 1);
						$pagenb++;
						$pdf->setPage($pagenb);
						$pdf->setPageOrientation('', 1, 0); // The only function to edit the bottom margin of current page to set it.
						if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
							$this->_pagehead($pdf, $object, 0, $outputlangs);
						}
						if (!empty($tplidx)) {
							$pdf->useTemplate($tplidx);
						}
					}
					if (isset($object->lines[$i + 1]->pagebreak) && $object->lines[$i + 1]->pagebreak) {  // @phan-suppress-current-line PhanUndeclaredProperty
						if ($pagenb == 1) {
							$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, 0, 1);
						} else {
							$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, 1, 1);
						}
						$this->_pagefoot($pdf, $object, $outputlangs, 1);
						// New page
						$pdf->AddPage();
						if (!empty($tplidx)) {
							$pdf->useTemplate($tplidx);
						}
						$pagenb++;
						if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
							$this->_pagehead($pdf, $object, 0, $outputlangs);
						}
					}
				}

				// Show square
				if ($pagenb == 1) {
					$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 0, 0);
					$bottomlasttab = $this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;
				} else {
					$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 1, 0);
					$bottomlasttab = $this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;
				}

				// Display total area
				$posy = $this->_tableau_tot($pdf, $object, 0, $bottomlasttab, $outputlangs);

				// Pagefoot
				$this->_pagefoot($pdf, $object, $outputlangs);
				if (method_exists($pdf, 'AliasNbPages')) {
					$pdf->AliasNbPages();  // @phan-suppress-current-line PhanUndeclaredMethod
				}

				$pdf->Close();

				$pdf->Output($file, 'F');

				// Add pdfgeneration hook
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
				global $action;
				$reshook = $hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action); // Note that $action and $object may have been modified by some hooks
				if ($reshook < 0) {
					$this->error = $hookmanager->error;
					$this->errors = $hookmanager->errors;
				}

				dolChmod($file);

				$this->result = array('fullpath' => $file);

				return 1; // No error
			} else {
				$this->error = $langs->transnoentities("ErrorCanNotCreateDir", $dir);
				return 0;
			}
		} else {
			$this->error = $langs->transnoentities("ErrorConstantNotDefined", "EXP_OUTPUTDIR");
			return 0;
		}
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 *	Show total to pay
	 *
	 *	@param	TCPDF		$pdf            Object PDF
	 *	@param  Expedition	$object         Object expedition
	 *	@param  int			$deja_regle     Amount already paid
	 *	@param	int         $posy           Start Position
	 *	@param	Translate	$outputlangs	Object langs
	 *	@return int							Position for suite
	 */
	protected function _tableau_tot(&$pdf, $object, $deja_regle, $posy, $outputlangs)
	{
		// phpcs:enable
		global $conf, $mysoc;

		$sign = 1;

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$tab2_top = $posy;
		$tab2_hl = 4;
		$pdf->SetFont('', 'B', $default_font_size - 1);

		// Total table
		$col1x = $this->posxweightvol - 50;
		$col2x = $this->posxweightvol;
		/*if ($this->page_largeur < 210) // To work with US executive format
		{
			$col2x-=20;
		}*/
		if (!getDolGlobalString('SHIPPING_PDF_HIDE_ORDERED')) {
			$largcol2 = ($this->posxqtyordered - $this->posxweightvol);
		} else {
			$largcol2 = ($this->posxqtytoship - $this->posxweightvol);
		}

		$useborder = 0;
		$index = 0;

		$totalWeighttoshow = '';
		$totalVolumetoshow = '';

		// Load dim data
		$tmparray = $object->getTotalWeightVolume();
		$totalWeight = $tmparray['weight'];
		$totalVolume = $tmparray['volume'];
		$totalOrdered = $tmparray['ordered'];
		$totalToShip = $tmparray['toship'];

		// Set trueVolume and volume_units not currently stored into database
		if ($object->trueWidth && $object->trueHeight && $object->trueDepth) {
			$object->trueVolume = price(((float) $object->trueWidth * (float) $object->trueHeight * (float) $object->trueDepth), 0, $outputlangs, 0, 0);
			$object->volume_units = (float) $object->size_units * 3;
		}

		if (!empty($totalWeight)) {
			$totalWeighttoshow = showDimensionInBestUnit($totalWeight, 0, "weight", $outputlangs, -1, 'no', 1);
		}
		if (!empty($totalVolume) && !getDolGlobalString('SHIPPING_PDF_HIDE_VOLUME')) {
			$totalVolumetoshow = showDimensionInBestUnit($totalVolume, 0, "volume", $outputlangs, -1, 'no', 1);
		}
		if (!empty($object->trueWeight)) {
			$totalWeighttoshow = showDimensionInBestUnit($object->trueWeight, (int) $object->weight_units, "weight", $outputlangs);
		}
		if (!empty($object->trueVolume) && !getDolGlobalString('SHIPPING_PDF_HIDE_VOLUME')) {
			$totalVolumetoshow = showDimensionInBestUnit($object->trueVolume, $object->volume_units, "volume", $outputlangs);
		}

		// $pdf->SetFillColor(255, 255, 255); // White background is default
		// $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
		// $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("Total"), 0, 'L', 1);

		// Remove old Qty Ordered and Qty Shipped totals display from here
		// if (!getDolGlobalString('SHIPPING_PDF_HIDE_ORDERED')) { ... }
		// if (!getDolGlobalString('SHIPPING_PDF_HIDE_QTYTOSHIP')) { ... }

		// Weight and Volume totals can remain if desired and if columns were not fully removed logically
		// For now, assuming Weight/Vol totals are also not primary focus after column removal.
		// if (!getDolGlobalString('SHIPPING_PDF_HIDE_WEIGHT_AND_VOLUME')) { ... }


		// Add Grand Total HT display
		if (getDolGlobalString('SHIPPING_PDF_DISPLAY_AMOUNT_HT')) {
			$index++; // Ensure it's on a new line
			$pdf->SetFillColor(230, 230, 230);
			$pdf->SetFont('', 'B', $default_font_size); // Bold for grand total

			// Initialize if not done in write_file yet (placeholder for now)
			if (!isset($this->calculated_grand_total_ht)) {
				$this->calculated_grand_total_ht = 0.0;
				// Actual calculation should happen in write_file by summing line->total_ht
				// For display here, if $object->total_ht is available and reliable, it could be a fallback.
				// However, summing manually from fetched lines is more accurate if line prices are from orders.
				// Example: Sum $order_line->total_ht in write_file's loop into $this->calculated_grand_total_ht
                if (isset($object->total_ht)) { // Fallback if direct sum is not yet implemented in write_file
                    // This is a temporary fallback, sum from lines is preferred.
                    // $this->calculated_grand_total_ht = $object->total_ht;
                    // To properly sum from lines, the accumulation must happen in write_file
                    // For now, let's ensure write_file populates this. If it's zero, it means not summed or zero.
                }
			}

			$label_grand_total = $outputlangs->transnoentities("TotalNetHT");
			$value_grand_total = price(isset($this->calculated_grand_total_ht) ? $this->calculated_grand_total_ht : 0, 0, $outputlangs);

			// Position Grand Total label and value.
			// Label from somewhat left, value aligned to the right of the table.
			// Use $this->posxpuht as a reference for where price-related totals might start.
			// This is a simplified positioning. A more robust way would use defined positions for total labels/values.
			$gt_label_posx = $this->marge_gauche + (($this->posxpuht - $this->marge_gauche) / 2) ; // Approx middle of desc area
            if ($this->posxpuht <= $this->marge_gauche) $gt_label_posx = $this->marge_gauche + 70; // Fallback if no price col
			$gt_value_posx = $this->posxtotalht > 0 ? $this->posxtotalht -1 : $this->page_largeur - $this->marge_droite - 50; // Approx start of last value col
            $gt_label_width = $gt_value_posx - $gt_label_posx -1;
            $gt_value_width = $this->page_largeur - $this->marge_droite - $gt_value_posx;


			if ($gt_label_width < 10) { // If columns are very compressed, adjust label position
                $gt_label_width = 50; // Minimum label width
                $gt_label_posx = $gt_value_posx - $gt_label_width -1;
            }
            if ($gt_value_width < 20) { // Ensure value has some space
                $gt_value_width = 20;
                $gt_value_posx = $this->page_largeur - $this->marge_droite - $gt_value_width;
                if ($gt_label_posx + $gt_label_width > $gt_value_posx) { // Prevent overlap
                    $gt_label_width = $gt_value_posx - $gt_label_posx -1;
                }
            }


			$pdf->SetXY($gt_label_posx, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($gt_label_width, $tab2_hl, $label_grand_total, 0, 'R', 1); // No border, align R, fill

			$pdf->SetXY($gt_value_posx, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($gt_value_width, $tab2_hl, $value_grand_total, 0, 'R', 1); // No border, align R, fill

			$index++;
		}

		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', '', $default_font_size -1); // Reset font

		return ($tab2_top + ($tab2_hl * $index));
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *   Show table for lines
	 *
	 *   @param		TCPDF		$pdf     		Object PDF
	 *   @param		float|int	$tab_top		Top position of table
	 *   @param		float|int	$tab_height		Height of table (angle)
	 *   @param		int			$nexY			Y
	 *   @param		Translate	$outputlangs	Langs object
	 *   @param		int			$hidetop		Hide top bar of array
	 *   @param		int			$hidebottom		Hide bottom bar of array
	 *   @return	void
	 */
	protected function _tableau(&$pdf, $tab_top, $tab_height, $nexY, $outputlangs, $hidetop = 0, $hidebottom = 0)
	{
		// phpcs:enable
		global $conf;

		// Force to disable hidetop and hidebottom
		$hidebottom = 0;
		if ($hidetop) {
			$hidetop = -1;
		}

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		// Amount in (at tab_top - 1)
		$pdf->SetTextColor(0, 0, 0);
		$pdf->SetFont('', '', $default_font_size - 2);

		// Output Rect
		$this->printRoundedRect($pdf, $this->marge_gauche, $tab_top, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $tab_height, $this->corner_radius, $hidetop, $hidebottom, 'D'); // Rect takes a length in 3rd parameter and 4th parameter

		$pdf->SetDrawColor(128, 128, 128);
		$pdf->SetFont('', '', $default_font_size - 1);

		// Description
		if (empty($hidetop)) { // Draw headers only if not hidetop
			// Top line of table header
			$pdf->line($this->marge_gauche, $tab_top + 5, $this->page_largeur - $this->marge_droite, $tab_top + 5);

			// Description Header
			$pdf->SetXY($this->posxdesc -1, $tab_top + 1);
			// $this->posxweightvol is now set in constructor to be the start of the first column after description/picture
			// (i.e., it's the X-coordinate of the left edge of either Picture column or first data column like U.P. HT or Qty Shipped)
			$pdf->MultiCell($this->posxweightvol - ($this->posxdesc -1) , 2, $outputlangs->transnoentities("Description"), '', 'L');

			// Vertical line after Description/Picture block (left of the first data column)
			$pdf->line($this->posxweightvol -1, $tab_top, $this->posxweightvol -1, $tab_top + $tab_height);

			// Conditional Headers for U.P. HT, QTY (Shipped), TOTAL HT columns
			if (getDolGlobalString('SHIPPING_PDF_DISPLAY_AMOUNT_HT')) {
				// U.P. HT Header
				$pdf->SetXY($this->posxpuht -1, $tab_top + 1); // posxpuht is start of Unit Price
				$pdf->MultiCell($this->posxqtyordered - $this->posxpuht, 2, $outputlangs->transnoentities("PriceUHT"), '', 'C');
				// Line after U.P. HT (which is left of QTY Shipped column)
				$pdf->line($this->posxqtyordered -1, $tab_top, $this->posxqtyordered -1, $tab_top + $tab_height);

				// QTY (Shipped) Header - only if not hidden by SHIPPING_PDF_HIDE_ORDERED
				if (!getDolGlobalString('SHIPPING_PDF_HIDE_ORDERED')) {
					$pdf->SetXY($this->posxqtyordered -1, $tab_top + 1);
					$pdf->MultiCell($this->posxtotalht - $this->posxqtyordered, 2, $outputlangs->transnoentities("QtyShipped"), '', 'C'); // Header "QtyShipped"
					// Line after QTY (Shipped) (which is left of TOTAL HT)
					$pdf->line($this->posxtotalht -1, $tab_top, $this->posxtotalht -1, $tab_top + $tab_height);
				} else {
					// If QTY (Shipped) is hidden, the line after U.P. HT (at $this->posxqtyordered -1)
					// is effectively the line before TOTAL HT because $this->posxqtyordered would be equal to $this->posxtotalht.
					// So the existing line at $this->posxqtyordered -1 is correct.
				}

				// TOTAL HT Header
				$pdf->SetXY($this->posxtotalht -1, $tab_top + 1);
				$pdf->MultiCell($this->page_largeur - $this->marge_droite - ($this->posxtotalht -1), 2, $outputlangs->transnoentities("TotalHT"), '', 'C');
				// No line after TOTAL HT as it's the last column.
			} else {
				// Prices are NOT shown: only QTY (Shipped) column might be shown after Description/Picture
				if (!getDolGlobalString('SHIPPING_PDF_HIDE_ORDERED')) { // Check if QTY Shipped column is hidden
					$pdf->SetXY($this->posxqtyordered -1, $tab_top + 1); // posxqtyordered is start of QTY Shipped column
					// Width: from start of QTY Shipped column to page margin
					$pdf->MultiCell($this->page_largeur - $this->marge_droite - ($this->posxqtyordered -1), 2, $outputlangs->transnoentities("QtyShipped"), '', 'C');
					// No line after the last column.
				}
			}
		}
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
/**
 * Show top header of page.
 *
 * @param TCPDF       $pdf            Object PDF
 * @param Commande    $object         Object to show
 * @param int         $showaddress    0=no, 1=yes
 * @param Translate   $outputlangs    Object lang for output
 * @param Translate   $outputlangsbis Object lang for output bis
 * @param string      $titlekey       Translation key to show as title of document
 * @return array<string, int|float>   top shift of linked object lines
 */
protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs, $outputlangsbis = null, $titlekey = "PdfOrderTitle")
{
    // phpcs:enable
    global $conf, $langs, $hookmanager, $mysoc;

    $ltrdirection = 'L';
    if ($outputlangs->trans("DIRECTION") == 'rtl') {
        $ltrdirection = 'R';
    }

    // Load translation files required by page
    $outputlangs->loadLangs(array("main", "bills", "propal", "orders", "companies"));

    $default_font_size = pdf_getPDFFontSize($outputlangs);

    pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);

    $pdf->SetTextColor(0, 0, 60);
    $pdf->SetFont('', 'B', $default_font_size + 3);

    $posy = $this->marge_haute;
    $posx = $this->marge_gauche;
    $page_width = $this->page_largeur - $this->marge_gauche - $this->marge_droite;

    // ===== LEFT COLUMN =====

    // Company Logo Section (aligned left)
    if (!getDolGlobalInt('PDF_DISABLE_MYCOMPANY_LOGO')) {
        if ($this->emetteur->logo) {
            $logodir = $conf->mycompany->dir_output;
            if (!empty(getMultidirOutput($mysoc, 'mycompany'))) {
                $logodir = getMultidirOutput($mysoc, 'mycompany');
            }
            $logo = !getDolGlobalInt('MAIN_PDF_USE_LARGE_LOGO') ? $logodir . '/logos/thumbs/' . $this->emetteur->logo_small : $logodir . '/logos/' . $this->emetteur->logo;
            if (is_readable($logo)) {
                $height = pdf_getHeightForLogo($logo);
                $logo_width = 70; // Increased size for bigger logo
                $pdf->Image($logo, $this->marge_gauche, $posy, $logo_width, $height); // Aligned left
                $posy += $height + 2; // Reduced more spacing after logo
            } else {
                $pdf->SetTextColor(200, 0, 0);
                $pdf->SetFont('', 'B', $default_font_size - 2);
                $pdf->SetXY($this->marge_gauche, $posy);
                $pdf->MultiCell($page_width / 2, 3, $outputlangs->transnoentities("ErrorLogoFileNotFound", $logo), 0, 'L');
                $pdf->MultiCell($page_width / 2, 3, $outputlangs->transnoentities("ErrorGoToGlobalSetup"), 0, 'L');
                $posy += 10; // Reduced spacing
            }
        } else {
            $pdf->SetFont('', 'B', $default_font_size + 2);
            $pdf->SetXY($this->marge_gauche, $posy);
            $pdf->MultiCell($page_width / 2, 4, $outputlangs->convToOutputCharset($this->emetteur->name), 0, 'L');
            $posy += 6; // Reduced spacing
        }
    }

    // Company Information
    $pdf->SetFont('', 'B', $default_font_size);
    $pdf->SetXY($this->marge_gauche, $posy);
    $pdf->MultiCell($page_width / 2, 3, $outputlangs->convToOutputCharset($this->emetteur->name), 0, 'L'); // Reduced line height
    $posy += 3; // Reduced spacing

    // Website URL
    $pdf->SetFont('', '', $default_font_size - 1);
    $pdf->SetXY($this->marge_gauche, $posy);
    $pdf->MultiCell($page_width / 2, 3, 'www.informatics-dz.com', 0, 'L'); // Reduced line height
    $posy += 3; // Reduced spacing

    // Add full-width box with the new text
$pdf->SetFillColor(230, 230, 230); // Light gray background
$pdf->Rect($this->marge_gauche, $posy, $page_width, 8, 'F'); // Full width box, height 8mm

// Set font to support Arabic (e.g., aealarabiya or dejavusans)
$pdf->SetFont('aealarabiya', '', $default_font_size + 1); // Slightly reduced font size
$pdf->SetXY($this->marge_gauche, $posy + 1); // Adjusted Y for text in box
$pdf->MultiCell($page_width, 4, $outputlangs->convToOutputCharset('CERTIFICAT DE GARANTIE                     الضمان شهادة'), 0, 'C'); // Reduced line height
$posy += 8; // Space after box (just height of box)

    // ===== RIGHT COLUMN =====

    $w = 100;
    $posx_right = $this->page_largeur - $this->marge_droite - $w; // Renamed for clarity
    $right_posy = $this->marge_haute;

    // Document title and reference
    $pdf->SetFont('', 'B', $default_font_size + 3);
    $pdf->SetXY($posx_right, $right_posy);
    $pdf->SetTextColor(0, 0, 60);
    $title = $outputlangs->transnoentities("SendingSheet");
    if (getDolGlobalInt('PDF_USE_ALSO_LANGUAGE_CODE') && is_object($outputlangsbis)) {
        $title .= ' - ';
        $title .= $outputlangsbis->transnoentities("SendingSheet");
    }
    $title .= ' ' . $outputlangs->convToOutputCharset($object->ref);
    if ($object->statut == $object::STATUS_DRAFT) {
        $pdf->SetTextColor(128, 0, 0);
        $title .= ' - ' . $outputlangs->transnoentities("NotValidated");
    }
    $pdf->MultiCell($w, 3, $title, '', 'R');
    $right_posy = $pdf->getY() + 3; // Reduced spacing

    // Reference info
    $pdf->SetFont('', '', $default_font_size - 1);
    $pdf->SetTextColor(0, 0, 60);

    if ($object->ref_client) {
        $pdf->SetXY($posx_right, $right_posy);
        $pdf->MultiCell($w, 3, $outputlangs->transnoentities("RefCustomer") . " : " .
                       dol_trunc($outputlangs->convToOutputCharset($object->ref_client), 65), '', 'R');
        $right_posy += 3; // Reduced spacing
    }

    if (getDolGlobalInt('PDF_SHOW_PROJECT_TITLE')) {
        $object->fetchProject();
        if (!empty($object->project->ref)) {
            $pdf->SetXY($posx_right, $right_posy);
            $pdf->MultiCell($w, 3, $outputlangs->transnoentities("Project") . " : " .
                           (empty($object->project->title) ? '' : $object->project->title), '', 'R');
            $right_posy += 3; // Reduced spacing
        }
    }

    if (getDolGlobalInt('PDF_SHOW_PROJECT')) {
        $object->fetchProject();
        if (!empty($object->project->ref)) {
            $outputlangs->load("projects");
            $pdf->SetXY($posx_right, $right_posy);
            $pdf->MultiCell($w, 3, $outputlangs->transnoentities("RefProject") . " : " .
                           (empty($object->project->ref) ? '' : $object->project->ref), '', 'R');
            $right_posy += 3; // Reduced spacing
        }
    }

    // Order date (using $object->date_creation or $object->date for expedition context)
    $date_to_display = !empty($object->date_creation) ? $object->date_creation : $object->date;
    $pdf->SetXY($posx_right, $right_posy);
    $title_date = $outputlangs->transnoentities("Date"); // Generic "Date" for expedition context // Renamed to avoid conflict
    if (getDolGlobalInt('PDF_USE_ALSO_LANGUAGE_CODE') && is_object($outputlangsbis)) {
        $title_date .= ' - ' . $outputlangsbis->transnoentities("Date");
    }
    $pdf->MultiCell($w, 3, $title_date . " : " . dol_print_date($date_to_display, "day", false, $outputlangs, true), '', 'R');
    $right_posy += 3; // Reduced spacing


    // Customer codes if enabled
    if (!getDolGlobalString('MAIN_PDF_HIDE_CUSTOMER_CODE') && !empty($object->thirdparty->code_client)) {
        $pdf->SetXY($posx_right, $right_posy);
        $pdf->MultiCell($w, 3, $outputlangs->transnoentities("CustomerCode") . " : " .
                       $outputlangs->transnoentities($object->thirdparty->code_client), '', 'R');
        $right_posy += 3; // Reduced spacing
    }

    if (!getDolGlobalString('MAIN_PDF_HIDE_CUSTOMER_ACCOUNTING_CODE') && !empty($object->thirdparty->code_compta_client)) {
        $pdf->SetXY($posx_right, $right_posy);
        $pdf->MultiCell($w, 3, $outputlangs->transnoentities("CustomerAccountancyCode") . " : " .
                       $outputlangs->transnoentities($object->thirdparty->code_compta_client), '', 'R');
        $right_posy += 3; // Reduced spacing
    }

    // Get contact
    if (getDolGlobalInt('DOC_SHOW_FIRST_SALES_REP')) {
        $arrayidcontact = array();
        if (isset($object->origin_object) && is_object($object->origin_object)) {
            $arrayidcontact = $object->origin_object->getIdContact('internal', 'SALESREPFOLL');
        }
        if (count($arrayidcontact) > 0) {
            $usertmp = new User($this->db);
            $usertmp->fetch($arrayidcontact[0]);
            $pdf->SetXY($posx_right, $right_posy);
            $pdf->MultiCell($w, 3, $outputlangs->transnoentities("SalesRepresentative") . " : " .
                           $usertmp->getFullName($langs), '', 'R');
            $right_posy += 3; // Reduced spacing
        }
    }

    // ===== LINKED OBJECTS =====

    $right_posy += 1; // Reduced spacing
    $current_y = $pdf->getY(); // This should be $right_posy before the call
    // Use $posx_right for X position of linked objects
    $posy_ref = pdf_writeLinkedObjects($pdf, $object, $outputlangs, $posx_right, $right_posy, $w, 3, 'R', $default_font_size);
    // $right_posy should be updated by $pdf->getY() after this call.
    $new_right_posy = $pdf->getY();
    if ($new_right_posy > $right_posy) { // If linked objects added height
        $top_shift = $new_right_posy - $right_posy; // This is how much the right column content shifted due to linked objects
        $right_posy = $new_right_posy;
    } else { // If no linked objects or they didn't increase Y
        $top_shift = 0;
    }


    // ===== ADDRESS FRAMES =====

    if ($showaddress) {
        // Calculate the start position for address frames
        $address_start_y = max($posy, $right_posy) + 3; // Reduced extra space

        // Sender properties
        $carac_emetteur = '';
        // Add internal contact of object if defined
        // For expeditions, the relevant contact might be on the linked order or the expedition itself.
        // We'll try to get it from the origin object if available.
        $arrayidcontact = array();
        if (isset($object->origin_object) && is_object($object->origin_object)) {
             $arrayidcontact = $object->origin_object->getIdContact('internal', 'SALESREPFOLL');
        }

        if (count($arrayidcontact) > 0) {
            $user_contact_tmp = new User($this->db); // Use a temporary variable to avoid conflict if $object->user exists
            $user_contact_tmp->fetch($arrayidcontact[0]);
            $labelbeforecontactname = ($outputlangs->transnoentities("FromContactName") != 'FromContactName' ?
                                      $outputlangs->transnoentities("FromContactName") :
                                      $outputlangs->transnoentities("Name"));
            $carac_emetteur .= ($carac_emetteur ? "\n" : '') . $labelbeforecontactname . " " .
                              $outputlangs->convToOutputCharset($user_contact_tmp->getFullName($outputlangs));
            $carac_emetteur .= (getDolGlobalInt('PDF_SHOW_PHONE_AFTER_USER_CONTACT') ||
                              getDolGlobalInt('PDF_SHOW_EMAIL_AFTER_USER_CONTACT')) ? ' (' : '';
            $carac_emetteur .= (getDolGlobalInt('PDF_SHOW_PHONE_AFTER_USER_CONTACT') &&
                              !empty($user_contact_tmp->office_phone)) ? $user_contact_tmp->office_phone : '';
            $carac_emetteur .= (getDolGlobalInt('PDF_SHOW_PHONE_AFTER_USER_CONTACT') &&
                              getDolGlobalInt('PDF_SHOW_EMAIL_AFTER_USER_CONTACT') && !empty($user_contact_tmp->office_phone) && !empty($user_contact_tmp->email) ) ? ', ' : '';
            $carac_emetteur .= (getDolGlobalInt('PDF_SHOW_EMAIL_AFTER_USER_CONTACT') &&
                              !empty($user_contact_tmp->email)) ? $user_contact_tmp->email : '';
            $carac_emetteur .= (getDolGlobalInt('PDF_SHOW_PHONE_AFTER_USER_CONTACT') ||
                              getDolGlobalInt('PDF_SHOW_EMAIL_AFTER_USER_CONTACT')) ? ')' : '';
            $carac_emetteur .= "\n";
        }


        $carac_emetteur .= pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, '', 0, 'source', $object);

        // Show sender
        $posy_address = $address_start_y; // Use a different variable for Y position of addresses
        $posx_sender = $this->marge_gauche;
        if (getDolGlobalInt('MAIN_INVERT_SENDER_RECIPIENT')) {
            $posx_sender = $this->page_largeur - $this->marge_droite - 80;
        }

        $hautcadre = getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 38 : 40;
        $widthrecbox = getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 92 : 82;

        // Show sender frame
        if (!getDolGlobalString('MAIN_PDF_NO_SENDER_FRAME')) {
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('', '', $default_font_size - 2);
            $pdf->SetXY($posx_sender, $posy_address - 5);
            $pdf->MultiCell($widthrecbox, 5, $outputlangs->transnoentities("BillFrom"), 0, $ltrdirection);
            $pdf->SetXY($posx_sender, $posy_address);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->RoundedRect($posx_sender, $posy_address, $widthrecbox, $hautcadre, $this->corner_radius, '1234', 'F');
            $pdf->SetTextColor(0, 0, 60);
        }

        // Show sender name
        $current_y_sender = $posy_address +3;
        if (!getDolGlobalString('MAIN_PDF_NO_SENDER_NAME')) {
            $pdf->SetXY($posx_sender + 2, $current_y_sender);
            $pdf->SetFont('', 'B', $default_font_size);
            $pdf->MultiCell($widthrecbox - 2, 4, $outputlangs->convToOutputCharset($this->emetteur->name), 0, $ltrdirection);
            $current_y_sender = $pdf->getY();
        }

        // Show sender information
        $pdf->SetXY($posx_sender + 2, $current_y_sender);
        $pdf->SetFont('', '', $default_font_size - 1);
        $pdf->MultiCell($widthrecbox - 2, 4, $carac_emetteur, 0, $ltrdirection);


        // If CUSTOMER contact defined, we use it (for expedition, this might be SHIPPING contact from order)
        $usecontact = false;
        $contact_to_use = null; // Initialize
        if (isset($object->origin_object) && is_object($object->origin_object)) {
            $arrayidcontact_shipping = $object->origin_object->getIdContact('external', 'SHIPPING');
            if (count($arrayidcontact_shipping) > 0) {
                $usecontact = true;
                // $object->fetch_contact might not be the right method here if contact is on origin_object
                // We need to fetch the contact object and store it
                $contact_temp = new Contact($this->db);
                $contact_temp->fetch($arrayidcontact_shipping[0]);
                $contact_to_use = $contact_temp;
            }
        }
        // If no shipping contact, try customer contact from order
        if (!$usecontact && isset($object->origin_object) && is_object($object->origin_object)) {
            $arrayidcontact_customer = $object->origin_object->getIdContact('external', 'CUSTOMER');
             if (count($arrayidcontact_customer) > 0) {
                $usecontact = true;
                $contact_temp = new Contact($this->db);
                $contact_temp->fetch($arrayidcontact_customer[0]);
                $contact_to_use = $contact_temp;
            }
        }


        // Recipient name
        $thirdparty_recipient = $object->thirdparty; // Default to expedition's thirdparty
        if ($usecontact && isset($contact_to_use->socid) && $contact_to_use->socid > 0 && $contact_to_use->socid != $object->thirdparty->id && getDolGlobalInt('MAIN_USE_COMPANY_NAME_OF_CONTACT')) {
            $soc_temp = new Societe($this->db);
            $soc_temp->fetch($contact_to_use->socid);
            $thirdparty_recipient = $soc_temp; // Use contact's company if different and configured
        }


        if (is_object($thirdparty_recipient)) {
            $carac_client_name = pdfBuildThirdpartyName($thirdparty_recipient, $outputlangs);
        } else {
            $carac_client_name = pdfBuildThirdpartyName($object->thirdparty, $outputlangs); // Fallback
        }


        $mode = 'target';
        $carac_client = pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty,
                                         ($usecontact && $contact_to_use ? $contact_to_use : null),
                                         ($usecontact ? 1 : 0), $mode, $object);

        // Show recipient
        $widthrecbox_recipient = getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 92 : 100;
        if ($this->page_largeur < 210) {
            $widthrecbox_recipient = 84; // To work with US executive format
        }
        $posy_recipient = $address_start_y;
        $posx_recipient = $this->page_largeur - $this->marge_droite - $widthrecbox_recipient;
        if (getDolGlobalInt('MAIN_INVERT_SENDER_RECIPIENT')) {
            $posx_recipient = $this->marge_gauche;
        }

        // Store Y position for the top of the recipient frame
        $recipient_frame_y_start = $posy_recipient;

        // Print "BillTo" (or "ShipTo" for expedition) title (above the frame)
        if (!getDolGlobalString('MAIN_PDF_NO_RECIPENT_FRAME')) {
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('', '', $default_font_size - 2);
            $pdf->SetXY($posx_recipient + 2, $recipient_frame_y_start - 5);
            // $pdf->MultiCell($widthrecbox_recipient, 5, $outputlangs->transnoentities("BillTo"), 0, $ltrdirection);
            $pdf->MultiCell($widthrecbox_recipient, 5, $outputlangs->transnoentities("ShipTo"), 0, $ltrdirection); // Changed to ShipTo
        }

        // Y position where actual content inside the box starts
        $y_text_content_start_recipient = $recipient_frame_y_start + 3;
        $pdf->SetXY($posx_recipient + 2, $y_text_content_start_recipient);

        // Show recipient name
        $pdf->SetFont('', 'B', $default_font_size);
        $pdf->MultiCell($widthrecbox_recipient, 2, $carac_client_name, 0, $ltrdirection);
        $current_y_tracker_recipient = $pdf->GetY();

        // Show recipient information (address)
        $pdf->SetFont('', '', $default_font_size - 1);
        $pdf->SetXY($posx_recipient + 2, $current_y_tracker_recipient);
        $pdf->MultiCell($widthrecbox_recipient, 4, $carac_client, 0, $ltrdirection);
        $current_y_tracker_recipient = $pdf->GetY();

        // Add Client Phone
        if (!empty($object->thirdparty->phone)) {
            $current_y_tracker_recipient += 1; // Add a small top margin
            $pdf->SetFont('', '', $default_font_size - 1); // Ensure font
            $pdf->SetXY($posx_recipient + 2, $current_y_tracker_recipient);
            $pdf->MultiCell($widthrecbox_recipient, 4, $outputlangs->transnoentities("PhonePro").": ".$outputlangs->convToOutputCharset($object->thirdparty->phone), 0, $ltrdirection);
            $current_y_tracker_recipient = $pdf->GetY();
        }

        // Add Client Email
        if (!empty($object->thirdparty->email)) {
            $current_y_tracker_recipient += 1; // Add a small top margin
            $pdf->SetFont('', '', $default_font_size - 1); // Ensure font
            $pdf->SetXY($posx_recipient + 2, $current_y_tracker_recipient);
            $pdf->MultiCell($widthrecbox_recipient, 4, $outputlangs->transnoentities("Email").": ".$outputlangs->convToOutputCharset($object->thirdparty->email), 0, $ltrdirection);
            $current_y_tracker_recipient = $pdf->GetY();
        }

        // Draw the recipient box frame using sender's box height ($hautcadre)
        if (!getDolGlobalString('MAIN_PDF_NO_RECIPENT_FRAME')) {
            $pdf->RoundedRect($posx_recipient, $recipient_frame_y_start, $widthrecbox_recipient, $hautcadre, $this->corner_radius, '1234', 'D');
        }

        // Update $posy to be the bottom of this recipient box for subsequent elements.
        // This $posy was for the general layout, we should use a more specific variable if needed below,
        // or ensure this $posy update correctly reflects the bottom of the address blocks area.
        $posy = $recipient_frame_y_start + $hautcadre;


        // Shipping address section for expedition is usually the main recipient.
        // The original _pagehead for pdf_rouget handles the recipient as the shipping address.
        // The copied logic from pdf_bon_garantie already has a recipient block.
        // We might not need a separate shipping block here if the recipient is already the shipping destination.
        // For now, let's assume the recipient block IS the shipping address for pdf_rouget.
        // So, we can comment out or remove the explicit "Show shipping address" part from pdf_bon_garantie
        // if it's redundant.
        // For this merge, we'll keep the structure, but it might need simplification later.

        $shipp_shift = 0; // Initialize shipp_shift

        // The original pdf_rouget doesn't have a separate shipping address block in _pagehead,
        // the recipient is the shipping address.
        // The copied code from bon_garantie has a shipping block.
        // We need to decide if we keep it or adapt.
        // For now, let's assume the main recipient block serves as the shipping address for rouget.
        // If a distinct shipping address is needed for rouget from another source, this would need more specific logic.
        // The $object->getIdContact('external', 'SHIPPING') in bon_garantie might be relevant if $object here
        // also has a similar contact fetching mechanism.
        // Given $object is an Expedition, $object->thirdparty is the recipient.
        // $object->origin_object would be the order, which might have a specific shipping contact.

        // Let's use the shipping address logic from bon_garantie but adapt it for Expedition context.
        // The primary recipient of an expedition IS the shipping address.
        // So the "Recipient" frame above already serves this purpose.
        // We can remove the secondary shipping address block or make it conditional
        // if an expedition can have yet another shipping address different from its main thirdparty.
        // For simplicity, we'll assume the "Recipient" box is the "ShipTo" box.
        // The code above already uses $outputlangs->transnoentities("ShipTo") for the recipient box title.

    }

    $pdf->SetTextColor(0, 0, 0);

    $pagehead = array('top_shift' => $top_shift, 'shipp_shift' => $shipp_shift);

    return $pagehead;
}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
	/**
	 *  Show footer of page. Need this->emetteur object
	 *
	 *  @param	TCPDF		$pdf     			PDF
	 *  @param	Expedition	$object				Object to show
	 *  @param	Translate	$outputlangs		Object lang for output
	 *  @param	int			$hidefreetext		1=Hide free text
	 *  @return	int								Return height of bottom margin including footer text
	 */
/**
 * Show footer of page. Need this->emetteur object
 *
 * @param TCPDF       $pdf            PDF
 * @param Commande    $object         Object to show
 * @param Translate   $outputlangs    Object lang for output
 * @param int         $hidefreetext   1=Hide free text
 * @return int                        Return height of bottom margin including footer text
 */
protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
{
    global $conf;

    $showdetails = getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS', 0);
    $default_font_size = pdf_getPDFFontSize($outputlangs);
    $width = $this->page_largeur - $this->marge_gauche - $this->marge_droite;

    // Standard footer content
    // Note: The free text key for expedition is 'SHIPPING_FREE_TEXT'
    $height = pdf_pagefoot($pdf, $outputlangs, 'SHIPPING_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext, $this->page_largeur, $this->watermark);

    // Add warranty conditions only on the last page
// In _pagefoot method, replace the warranty conditions block
if ($pdf->getPage() == $pdf->getNumPages()) {
    $posy = $this->page_hauteur - $this->marge_basse - 40; // Reserve space for warranty conditions
    // Adjust Y position if standard footer already took some space.
    // $height from pdf_pagefoot is the height of the standard footer.
    // We need to place warranty conditions above this, or integrate carefully.
    // For now, let's assume $this->marge_basse is the primary bottom margin, and warranty text goes above it.
    // If $height (from pdf_pagefoot) is significant, this $posy might overlap.
    // A safer $posy might be $this->page_hauteur - $this->marge_basse - $height_of_warranty_text - $height_of_standard_footer_text
    // Let's try to position it fixed from bottom, ensuring it doesn't overlap with pdf_pagefoot content.
    // The pdf_pagefoot function draws from $this->page_hauteur - $this->marge_basse upwards.
    // So, we need to draw warranty text at $this->page_hauteur - $this->marge_basse - $height_of_warranty_text (e.g. 40)
    // and then ensure pdf_pagefoot's content is drawn above that or this fixed block is considered by pdf_pagefoot.

    // Recalculate posy to be above the standard footer elements drawn by pdf_pagefoot
    // $height already includes some margin from bottom.
    // Let's position the warranty text block starting from a calculated Y
    // that is above where pdf_pagefoot would draw its own text.
    // pdf_pagefoot uses $this->page_hauteur - $this->marge_basse as its effective bottom line.
    // The warranty text needs to be above this.
    $warranty_block_height = 40; // Estimated height for warranty text
    $posy = $this->page_hauteur - $this->marge_basse - $warranty_block_height;
    if ($showdetails) { // If company details are shown by pdf_pagefoot, they take ~6mm
        $posy -= 6;
    }
    if (!$hidefreetext && !empty($conf->global->SHIPPING_FREE_TEXT)) { // If free text is shown
        // Estimate height of free text. This is tricky.
        // Let's assume a fixed reduction for now if free text exists.
        // A more robust way would be to measure freetext height.
        $posy -= (getDolGlobalInt('MAIN_PDF_FREETEXT_HEIGHT', 5) > 0 ? getDolGlobalInt('MAIN_PDF_FREETEXT_HEIGHT', 5) + 2 : 0);
    }


    $warranty_conditions = "شروط الضمان:\n";
    $warranty_conditions .= "1- تضمن الشركة للزبون العتاد المباع، ضد كل عيوب التصنيع والعمالة ضمن المدة المحددة ابتداء من تاريخ الشراء.\n";
    $warranty_conditions .= "2- نظام التشغيل والبرامج + نضائد الكمبيوتر المحمول ولوحات المفاتيح وكذا مقود اللعب، الفارة، مكبرات الصوت الفلاشديسك والمستهلكات مضمونة فقط عند أول تشغيل.\n";
    $warranty_conditions .= "5- تثبيت البرمجيات غير مضمون.\n";
    $warranty_conditions .= "7- لا تضمن الشركة أن هذا العتاد سيشتغل بصفة غير منقطعة أو دون خطأ في هذا العتاد.\n";
    $warranty_conditions .= "8- الضمان لا يشمل إرجاع المنتوج أو استبداله، تمنح الشركة مدة 3 أيام من تاريخ استلام المنتوج كأقصى حد لإرجاعه يتم فيها مراجعة المنتوج وتطبيق مستحقات قدرها 5% من سعر المنتوج -(لا تشمل مستحقات التوصيل)-.\n";
    $warranty_conditions .= "9- على الزبون الحفاظ على التغليف خلال مدة ضمان.\n";
    $warranty_conditions .= "10- الضمان لا يشمل: القيام بكسر السرعة OVER CLOCK / الصيانة سيئة / تغيير أو استعمال غير مرخصين / استعمال بطاقة امتداد غير معتمدة / حالات نقل سيئة. وفي حالة خلل في الجهاز يجب على الزبون إرجاعه للشركة خلال فترة الضمان في تغليفه الأصلي.\n";
    $warranty_conditions .= "11- الضمان على الطابعة يشمل إشتغالها فقط، ولا يشمل أخطاء الطباعة أو سوء ملء الخزان الخاص بها.";

    $pdf->SetFont('aealarabiya', '', $default_font_size - 1);
    $pdf->SetTextColor(0, 0, 0);
    // $pdf->SetXY($this->marge_gauche, $posy); // SetXY might be problematic if MultiCell pushes Y too far
    $pdf->SetFillColor(255, 255, 255); // Changed to white

    // Draw the rounded rectangle first
    $pdf->RoundedRect($this->marge_gauche, $posy, $width, $warranty_block_height, $this->corner_radius, '1234', 'F');

    // Set position for MultiCell carefully within the rectangle
    $pdf->SetXY($this->marge_gauche + 2, $posy + 2);
    $pdf->MultiCell($width - 4, 4, $outputlangs->convToOutputCharset($warranty_conditions), 0, 'R', true, 1, '', '', true, 0, false, true, $warranty_block_height - 4, 'T');

    // $height from pdf_pagefoot is the height of the standard footer.
    // We are adding warranty conditions, so the total footer area might increase.
    // However, pdf_pagefoot returns the height it consumed.
    // The question is whether this custom block should be part of that height or extend it.
    // If we want the page numbering (part of pdf_pagefoot) to be at the very bottom,
    // this warranty text must be placed above where pdf_pagefoot starts its drawing.
    // The current $height is what pdf_pagefoot calculated. We're adding text above it.
    // The effective total footer height used would be $warranty_block_height + $height_of_standard_text_below_warranty.
    // For now, let's return the original $height from pdf_pagefoot, as that function handles page numbering at the absolute bottom.
    // This assumes the warranty text fits nicely above the standard footer text.
}

    return $height; // Return the height calculated by the standard pdf_pagefoot
}
}
