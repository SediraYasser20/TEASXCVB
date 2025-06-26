<?php
/*
 * This file is an adaptation of pdf_bon_garantie.modules.php to work with the Expedition module.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/expedition/modules_expedition.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';

/**
 * Class to generate a Warranty Slip PDF from an Expedition.
 */
class pdf_custom_shipping_slip extends ModelePDFExpedition
{
    public $db;
    public $name;
    public $description;
    public $type;
    public $cols = array();

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct(DoliDB $db)
    {
        global $langs;
        $langs->loadLangs(array("main", "bills", "products", "sendings"));

        parent::__construct($db);

        $this->name = "custom_shipping_slip";
        $this->description = $langs->trans('WarrantySlipFromExpedition');
        $this->type = 'pdf';
    }

    /**
     * Main function to build the PDF.
     */
    public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
    {
        global $conf, $langs, $mysoc;

        // Prevent null emitter
        $this->emetteur = $mysoc;

        if (!is_object($outputlangs)) {
            $outputlangs = $langs;
        }

        $object->fetch_thirdparty();

        $objectref = dol_sanitizeFileName($object->ref);
        $dir = $conf->expedition->dir_output . "/" . $objectref;
        if (!file_exists($dir)) {
            dol_mkdir($dir);
        }
        $file = $dir . "/" . $objectref . "_garantie.pdf";

        $pdf = pdf_getInstance($this->format);
        $pdf->SetAutoPageBreak(true, $this->marge_basse);
        if (class_exists('TCPDF')) {
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
        }
        $pdf->SetFont(pdf_getPDFFont($outputlangs));
        $pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
        $pdf->AddPage();

        // Header
        $this->_pagehead($pdf, $object, 1, $outputlangs);

        // Columns
        $this->defineColumnField($object, $outputlangs);

        $tab_top = $pdf->GetY() + 10;
        $tab_height = $this->page_hauteur - $tab_top - $this->marge_basse - 45;

        // Titles
        $this->pdfTabTitles($pdf, $tab_top, $tab_height, $outputlangs);
        $nexY = $tab_top + $this->tabTitleHeight;

        // Content
        foreach ($object->lines as $line) {
            $curY = $nexY;
            if ($curY > ($this->page_hauteur - $this->marge_basse - 45)) {
                $pdf->AddPage();
                $this->_pagehead($pdf, $object, 0, $outputlangs);
                $this->pdfTabTitles($pdf, $this->marge_haute, $tab_height, $outputlangs);
                $curY = $this->marge_haute + $this->tabTitleHeight;
            }

            $pdf->SetFont('', '', pdf_getPDFFontSize($outputlangs) - 1);
            $pdf->SetTextColor(0, 0, 0);

            // Description
            if ($this->getColumnStatus('desc')) {
                $desc = !empty($line->product_label) ? $line->product_label : $line->desc;
                $this->printStdColumnContent($pdf, $curY, 'desc', $desc);
                $nexY = max($curY, $pdf->GetY());
            }
            // Qty
            if ($this->getColumnStatus('qty')) {
                $this->printStdColumnContent($pdf, $curY, 'qty', $line->qty);
            }
            // Serial
            if ($this->getColumnStatus('serialnumber')) {
                $sn = $this->getLineSerialNumber($object, array_search($line, $object->lines));
                $this->printStdColumnContent($pdf, $curY, 'serialnumber', $sn);
            }
            // Warranty
            if ($this->getColumnStatus('garantie')) {
                $w = $this->getLineGarantie($object, array_search($line, $object->lines));
                $this->printStdColumnContent($pdf, $curY, 'garantie', $w);
            }

            $nexY = $curY + 4;
        }

        $this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $this->marge_basse - 45, 0, $outputlangs);
        $this->_pagefoot($pdf, $object, $outputlangs);

        $pdf->Output($file, 'F');
        if (!empty($this->update_main_doc_field)) {
            $this->record_generated_document($object, $outputlangs, $file);
        }

        return 1;
    }

    protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs)
    {
        global $conf, $mysoc;
        pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);
        if (!empty($this->emetteur->logo)) {
            $lp = DOL_DOCUMENT_ROOT . '/' . $this->emetteur->logo;
            if (file_exists($lp)) $pdf->Image($lp, $this->marge_gauche, $this->marge_haute, 70);
        }
        $df = pdf_getPDFFontSize($outputlangs);
        $wpage = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
        $posy = $this->marge_haute + 30;
        $pdf->SetFont('', 'B', $df);
        $pdf->SetXY($this->marge_gauche, $posy);
        $pdf->MultiCell($wpage/2, 4, $outputlangs->convToOutputCharset($this->emetteur->name), 0, 'L');
        $pdf->SetFont('', '', $df-1);
        $pdf->SetXY($this->marge_gauche, $posy+5);
        $pdf->MultiCell($wpage/2, 4, 'www.informatics-dz.com', 0, 'L');
        // Title band
        $posy += 15;
        $pdf->SetFillColor(230,230,230);
        $pdf->Rect($this->marge_gauche, $posy, $wpage, 10, 'F');
        $pdf->SetFont('aealarabiya','', $df+2);
        $pdf->SetXY($this->marge_gauche, $posy+2);
        $pdf->MultiCell($wpage,5,$outputlangs->convToOutputCharset('CERTIFICAT DE GARANTIE                     الضمان شهادة'),0,'C');
        // Info box
        $infoW=80;
        $infoX=$this->page_largeur-$this->marge_droite-$infoW;
        $pdf->SetTextColor(0,0,60);
        $pdf->SetFont('', 'B', $df+3);
        $pdf->SetXY($infoX, $this->marge_haute);
        $pdf->MultiCell($infoW,3,$outputlangs->transnoentities("WarrantySlip").' / '. $object->ref,'','R');
        $pdf->SetFont('', '', $df-1);
        $pdf->SetXY($infoX, $this->marge_haute+5);
        $pdf->MultiCell($infoW,3,$outputlangs->transnoentities("Date")." : ".dol_print_date($object->date_delivery,"day",false,$outputlangs,true),'','R');
        if ($showaddress) {
            $client=pdf_build_address($outputlangs,$this->emetteur,$object->thirdparty);
            $bx=100;
            $pdf->SetFont('','',$df-2);
            $pdf->SetXY($infoX, $posy);
            $pdf->MultiCell($bx,5,$outputlangs->transnoentities("BillTo"),0,'L');
            $pdf->SetFont('','',$df-1);
            $pdf->SetXY($infoX, $posy+5);
            $pdf->MultiCell($bx,4,$client,0,'L');
        }
        $pdf->SetY($posy+40);
    }

    protected function _pagefoot(&$pdf, $object, $outputlangs)
    {
        $df = pdf_getPDFFontSize($outputlangs);
        $w = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
        if ($pdf->getPage()==$pdf->getNumPages()) {
            $y=$this->page_hauteur-$this->marge_basse-40;
            $cond = "شروط الضمان:\n".
                    "1- تضمن الشركة للزبون...";
            $pdf->SetFont('aealarabiya','',$df-1);
            $pdf->SetXY($this->marge_gauche,$y);
            $pdf->MultiCell($w,3,$outputlangs->convToOutputCharset($cond),0,'R');
        }
        pdf_pagefoot($pdf,$outputlangs,'',$this->emetteur,$this->marge_basse,$this->marge_gauche,$this->page_hauteur,$object);
    }

    public function defineColumnField($object, $outputlangs, $hidedetails=0, $hidedesc=0, $hideref=0)
    {
        $this->cols = array(
            'desc' => array('rank'=>10,'width'=>80,'status'=>true,'title'=>array('textkey'=>'Description'),'content'=>array('align'=>'L')),
            'qty'  => array('rank'=>20,'width'=>20,'status'=>true,'title'=>array('textkey'=>'Qty'),'content'=>array('align'=>'C')),
            'serialnumber'=>array('rank'=>30,'width'=>45,'status'=>true,'title'=>array('textkey'=>'LotSerial'),'content'=>array('align'=>'L')),
            'garantie'=>array('rank'=>40,'width'=>30,'status'=>true,'title'=>array('textkey'=>'Warranty'),'content'=>array('align'=>'L'))
        );
        // Removed call to buildColumnField() as it does not exist
        // If you need custom column sorting, implement buildColumnField() here
    }

    protected function getLineSerialNumber($object,$i)
    {
        $sn=[];
        if (empty($object->lines[$i]->id)) return '';
        $sql="SELECT batch FROM ".MAIN_DB_PREFIX."expeditiondet_batch WHERE fk_expeditiondet=".(int)$object->lines[$i]->id;
        $res=$this->db->query($sql);
        if ($res) while ($o=$this->db->fetch_object($res)) $sn[]=$o->batch;
        return join(', ',$sn);
    }

    protected function getLineGarantie($object,$i)
    {
        $map=['1'=>'0 MOIS','2'=>'1 MOIS','3'=>'3 MOIS','4'=>'6 MOIS','5'=>'12 MOIS'];
        if (!empty($object->lines[$i]->fk_product)) {
            $sql="SELECT garantie FROM ".MAIN_DB_PREFIX."product_extrafields WHERE fk_object=".(int)$object->lines[$i]->fk_product;
            $res=$this->db->query($sql);
            if ($res && ($g=$this->db->fetch_object($res)) && !empty($g->garantie)) return $map[$g->garantie]??'';
        }
        return '';
    }
}