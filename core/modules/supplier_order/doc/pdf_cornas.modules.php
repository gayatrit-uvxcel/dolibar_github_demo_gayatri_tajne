<?php
/* Copyright (C) 2004-2014 Laurent Destailleur   <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2011 Regis Houssin         <regis.houssin@inodbox.com>
 * Copyright (C) 2007      Franky Van Liedekerke <franky.van.liedekerke@telenet.be>
 * Copyright (C) 2010-2014 Juanjo Menent         <jmenent@2byte.es>
 * Copyright (C) 2015      Marcos García         <marcosgdf@gmail.com>
 * Copyright (C) 2017      Ferran Marcet         <fmarcet@2byte.es>
 * Copyright (C) 2018-2022 Frédéric France       <frederic.france@netlogic.fr>
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
 *    \file       htdocs/core/modules/supplier_order/doc/pdf_cornas.modules.php
 *    \ingroup    fournisseur
 *    \brief      File of class to generate suppliers orders from cornas model
 */

require_once DOL_DOCUMENT_ROOT . '/core/modules/supplier_order/modules_commandefournisseur.php';
require_once DOL_DOCUMENT_ROOT . '/fourn/class/fournisseur.commande.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';

/**
 *    Class to generate the supplier orders with the cornas model
 */
class pdf_cornas extends ModelePDFSuppliersOrders
{
    /**
     * @var DoliDb Database handler
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
     * @var string
     */
    public $version = 'dolibarr';

    /**
     * @var int page_largeur
     */
    public $page_largeur;

    /**
     * @var int page_hauteur
     */
    public $page_hauteur;

    /**
     * @var array format
     */
    public $format;

    /**
     * @var int marge_gauche
     */
    public $marge_gauche;

    /**
     * @var int marge_droite
     */
    public $marge_droite;

    /**
     * @var int marge_haute
     */
    public $marge_haute;

    /**
     * @var int marge_basse
     */
    public $marge_basse;

    /**
     * Issuer
     * @var Societe    object that emits
     */
    public $emetteur;
    public $pagenb;

    /**
     *    Constructor
     *
     *  @param    DoliDB        $db          Database handler
     */
    public function __construct($db)
    {
        global $conf, $langs, $mysoc;

        // Load translation files required by the page
        $langs->loadLangs(array("main", "bills"));

        $this->db = $db;
        $this->name = "cornas";
        $this->description = $langs->trans('SuppliersCommandModel');
        $this->update_main_doc_field = 1; // Save the name of generated file as the main doc when generating a doc with this template

        // Page size for A4 format
        $this->type = 'pdf';
        $formatarray = pdf_getFormat();
        $this->page_largeur = $formatarray['width'];
        // height of a page
        $this->page_hauteur = $formatarray['height'];

        $this->format = array($this->page_largeur, $this->page_hauteur);
        $this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
        $this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
        $this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
        $this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);
        $this->option_logo = 1; // Display logo
        $this->option_tva = 1; // Manage the vat option FACTURE_TVAOPTION
        $this->option_modereg = 1; // Display payment mode
        $this->option_condreg = 1; // Display payment terms
        $this->option_multilang = 1; //Available in several languages
        $this->option_escompte = 0; // Displays if there has been a discount
        $this->option_credit_note = 0; // Support credit notes
        $this->option_freetext = 1; // Support add of a personalised text
        $this->option_draft_watermark = 1; // Support add of a watermark on drafts

        // Get source company
        $this->emetteur = $mysoc;
        if (empty($this->emetteur->country_code)) {
            $this->emetteur->country_code = substr($langs->defaultlang, -2); // By default, if was not defined
        }

        // Define position of columns
        $this->posxdesc = $this->marge_gauche + 1; // For module retrocompatibility support durring PDF transition: TODO remove this at the end

        $this->tva = array();
        $this->tva_array = array();
        $this->localtax1 = array();
        $this->localtax2 = array();
        $this->atleastoneratenotnull = 0;
        $this->atleastonediscount = 0;
    }

    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
    /**
     *  Function to build pdf onto disk
     *
     *  @param        CommandeFournisseur    $object                Id of object to generate
     *  @param        Translate            $outputlangs        Lang output object
     *  @param        string                $srctemplatepath    Full path of source filename for generator using a template file
     *  @param        int                    $hidedetails        Do not show line details
     *  @param        int                    $hidedesc            Do not show desc
     *  @param        int                    $hideref            Do not show ref
     *  @return        int                                        1=OK, 0=KO
     */
    public function write_file($object, $outputlangs = '', $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0, $contentHeight)
    {
        print "<script>console.log(`height in write  " . $contentHeight . "`)</script>";
        // phpcs:enable
        global $user, $langs, $conf, $hookmanager, $mysoc, $nblines;

        if (!is_object($outputlangs)) {
            $outputlangs = $langs;
        }
        // For backward compatibility with FPDF, force output charset to ISO, because FPDF expect text to be encoded in ISO
        if (!empty($conf->global->MAIN_USE_FPDF)) {
            $outputlangs->charset_output = 'ISO-8859-1';
        }

        // Load translation files required by the page
        $outputlangs->loadLangs(array("main", "orders", "companies", "bills", "dict", "products"));

        global $outputlangsbis;
        $outputlangsbis = null;
        if (!empty($conf->global->PDF_USE_ALSO_LANGUAGE_CODE) && $outputlangs->defaultlang != $conf->global->PDF_USE_ALSO_LANGUAGE_CODE) {
            $outputlangsbis = new Translate('', $conf);
            $outputlangsbis->setDefaultLang($conf->global->PDF_USE_ALSO_LANGUAGE_CODE);
            $outputlangsbis->loadLangs(array("main", "orders", "companies", "bills", "dict", "products"));
        }

        $nblines = count($object->lines);

        $hidetop = 0;
        if (!empty($conf->global->MAIN_PDF_DISABLE_COL_HEAD_TITLE)) {
            $hidetop = $conf->global->MAIN_PDF_DISABLE_COL_HEAD_TITLE;
        }

        // Loop on each lines to detect if there is at least one image to show
        $realpatharray = array();
        if (!empty($conf->global->MAIN_GENERATE_SUPPLIER_ORDER_WITH_PICTURE)) {
            for ($i = 0; $i < $nblines; $i++) {
                if (empty($object->lines[$i]->fk_product)) {
                    continue;
                }

                $objphoto = new Product($this->db);
                $objphoto->fetch($object->lines[$i]->fk_product);

                if (getDolGlobalInt('PRODUCT_USE_OLD_PATH_FOR_PHOTO')) {
                    $pdir = get_exdir($objphoto->id, 2, 0, 0, $objphoto, 'product') . $object->lines[$i]->fk_product . "/photos/";
                    $dir = $conf->product->dir_output . '/' . $pdir;
                } else {
                    $pdir = get_exdir($objphoto->id, 0, 0, 0, $objphoto, 'product');
                    $dir = $conf->product->dir_output . '/' . $pdir;
                }

                $realpath = '';
                foreach ($objphoto->liste_photos($dir, 1) as $key => $obj) {
                    if (!getDolGlobalInt('CAT_HIGH_QUALITY_IMAGES')) { // If CAT_HIGH_QUALITY_IMAGES not defined, we use thumb if defined and then original photo
                        if ($obj['photo_vignette']) {
                            $filename = $obj['photo_vignette'];
                        } else {
                            $filename = $obj['photo'];
                        }
                    } else {
                        $filename = $obj['photo'];
                    }
                    $realpath = $dir . $filename;
                    break;
                }

                if ($realpath) {
                    $realpatharray[$i] = $realpath;
                }
            }
        }
        if (count($realpatharray) == 0) {
            $this->posxpicture = $this->posxtva;
        }

        if ($conf->fournisseur->commande->dir_output) {
            $object->fetch_thirdparty();

            $deja_regle = 0;
            $amount_credit_notes_included = 0;
            $amount_deposits_included = 0;
            //$amount_credit_notes_included = $object->getSumCreditNotesUsed();
            //$amount_deposits_included = $object->getSumDepositsUsed();

            // Definition of $dir and $file
            if ($object->specimen) {
                $dir = $conf->fournisseur->commande->dir_output;
                $file = $dir . "/SPECIMEN.pdf";
            } else {
                $objectref = dol_sanitizeFileName($object->ref);
                $objectrefsupplier = dol_sanitizeFileName($object->ref_supplier);
                $dir = $conf->fournisseur->commande->dir_output . '/' . $objectref;
                $file = $dir . "/" . $objectref . ".pdf";
                if (!empty($conf->global->SUPPLIER_REF_IN_NAME)) {
                    $file = $dir . "/" . $objectref . ($objectrefsupplier ? "_" . $objectrefsupplier : "") . ".pdf";
                }
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
                    include_once DOL_DOCUMENT_ROOT . '/core/class/hookmanager.class.php';
                    $hookmanager = new HookManager($this->db);
                }
                $hookmanager->initHooks(array('pdfgeneration'));
                $parameters = array('file' => $file, 'object' => $object, 'outputlangs' => $outputlangs);
                global $action;
                $reshook = $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks

                $nblines = count($object->lines);

                $pdf = pdf_getInstance($this->format);
                $default_font_size = pdf_getPDFFontSize($outputlangs); // Must be after pdf_getInstance
                $heightforinfotot = 50; // Height reserved to output the info and total part
                $heightforfreetext = (isset($conf->global->MAIN_PDF_FREETEXT_HEIGHT) ? $conf->global->MAIN_PDF_FREETEXT_HEIGHT : 5); // Height reserved to output the free text on last page
                // height of footer
                $heightforfooter = $this->marge_basse + 8; // Height reserved to output the footer (value include bottom margin)
                if (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS)) {
                    $heightforfooter += 6;
                }
                $pdf->SetAutoPageBreak(1, 0);

                if (class_exists('TCPDF')) {
                    $pdf->setPrintHeader(false);
                    $pdf->setPrintFooter(false);
                }
                $pdf->SetFont(pdf_getPDFFont($outputlangs));
                // Set path to the background PDF File
                if (!empty($conf->global->MAIN_ADD_PDF_BACKGROUND)) {
                    $pagecount = $pdf->setSourceFile($conf->mycompany->dir_output . '/' . $conf->global->MAIN_ADD_PDF_BACKGROUND);
                    $tplidx = $pdf->importPage(1);
                }

                $pdf->Open();
                $pagenb = 0;

                $pdf->SetDrawColor(128, 128, 128);

                $pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
                $pdf->SetSubject($outputlangs->transnoentities("Order"));
                $pdf->SetCreator("Dolibarr " . DOL_VERSION);
                $pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
                $pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref) . " " . $outputlangs->transnoentities("Order") . " " . $outputlangs->convToOutputCharset($object->thirdparty->name));
                if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
                    $pdf->SetCompression(false);
                }

                $pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite); // Left, Top, Right

                // Does we have at least one line with discount $this->atleastonediscount
                foreach ($object->lines as $line) {
                    if ($line->remise_percent) {
                        $this->atleastonediscount = true;
                        break;
                    }
                }

                // New page
                $pdf->AddPage();
                if (!empty($tplidx)) {
                    $pdf->useTemplate($tplidx);
                }
                $pagenb++;
                $top_shift = $this->_pagehead($pdf, $object, 1, $outputlangs, $pagenb);

                $pdf->SetFont('', '', $default_font_size - 1);
                $pdf->MultiCell(0, 3, ''); // Set interline to 3
                $pdf->SetTextColor(0, 0, 0);

                $tab_top = 90 + $top_shift;
                $tab_top_newpage = (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD') ? 42 + $top_shift : 10);

                $tab_height = $this->page_hauteur - $tab_top - $heightforfooter - $heightforfreetext;
                // Incoterm
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
                        $pdf->Rect($this->marge_gauche, $tab_top - 1, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $height_incoterms + 1);

                        $tab_top = $nexY + 6;
                    }
                }

                // Affiche notes
                $notetoshow = empty($object->note_public) ? '' : $object->note_public;

                // Extrafields in note
                $extranote = $this->getExtrafieldsInHtml($object, $outputlangs);
                if (!empty($extranote)) {
                    $notetoshow = dol_concatdesc($notetoshow, $extranote);
                }

                $pagenb = $pdf->getPage();
                if ($notetoshow) {
                    $tab_width = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
                    $pageposbeforenote = $pagenb;

                    $substitutionarray = pdf_getSubstitutionArray($outputlangs, null, $object);
                    complete_substitutions_array($substitutionarray, $outputlangs, $object);
                    $notetoshow = make_substitutions($notetoshow, $substitutionarray, $outputlangs);
                    $notetoshow = convertBackOfficeMediasLinksToPublicLinks($notetoshow);

                    $tab_top -= 2;

                    $pdf->startTransaction();

                    $pdf->SetFont('', '', $default_font_size - 1);
                    $pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $tab_top, dol_htmlentitiesbr($notetoshow), 0, 1);
                    // Description
                    $pageposafternote = $pdf->getPage();
                    $posyafter = $pdf->GetY();

                    if ($pageposafternote > $pageposbeforenote) {
                        $pdf->rollbackTransaction(true);

                        // prepar pages to receive notes
                        while ($pagenb < $pageposafternote) {
                            $pdf->AddPage();
                            $pagenb++;
                            if (!empty($tplidx)) {
                                $pdf->useTemplate($tplidx);
                            }
                            if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
                                $this->_pagehead($pdf, $object, 0, $outputlangs, $pagenb);
                            }
                            // $this->_pagefoot($pdf,$object,$outputlangs,1);
                            $pdf->setTopMargin($tab_top_newpage);
                            // The only function to edit the bottom margin of current page to set it.
                            $pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext);
                        }

                        // back to start
                        $pdf->setPage($pageposbeforenote);
                        $pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext);
                        $pdf->SetFont('', '', $default_font_size - 1);
                        $pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $tab_top, dol_htmlentitiesbr($notetoshow), 0, 1);
                        $pageposafternote = $pdf->getPage();

                        $posyafter = $pdf->GetY();

                        if ($posyafter > ($this->page_hauteur - ($heightforfooter + $heightforfreetext + 20))) { // There is no space left for total+free text
                            $pdf->AddPage('', '', true);
                            $pagenb++;
                            $pageposafternote++;
                            $pdf->setPage($pageposafternote);
                            $pdf->setTopMargin($tab_top_newpage);
                            // The only function to edit the bottom margin of current page to set it.
                            $pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext);
                            //$posyafter = $tab_top_newpage;
                        }

                        // apply note frame to previus pages
                        $i = $pageposbeforenote;
                        while ($i < $pageposafternote) {
                            $pdf->setPage($i);

                            $pdf->SetDrawColor(128, 128, 128);
                            // Draw note frame
                            if ($i > $pageposbeforenote) {
                                $height_note = $this->page_hauteur - ($tab_top_newpage + $heightforfooter);
                                $pdf->Rect($this->marge_gauche, $tab_top_newpage - 1, $tab_width, $height_note + 1);
                            } else {
                                $height_note = $this->page_hauteur - ($tab_top + $heightforfooter);
                                $pdf->Rect($this->marge_gauche, $tab_top - 1, $tab_width, $height_note + 1);
                            }

                            // Add footer
                            $pdf->setPageOrientation('', 1, 0); // The only function to edit the bottom margin of current page to set it.
                            $this->_pagefoot($pdf, $object, $outputlangs, 1);

                            $i++;
                        }

                        // apply note frame to last page
                        $pdf->setPage($pageposafternote);
                        if (!empty($tplidx)) {
                            $pdf->useTemplate($tplidx);
                        }
                        if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
                            $this->_pagehead($pdf, $object, 0, $outputlangs, $pagenb);
                        }
                        $height_note = $posyafter - $tab_top_newpage;
                        $pdf->Rect($this->marge_gauche, $tab_top_newpage - 1, $tab_width, $height_note + 1);
                    } else {
                        // No pagebreak
                        $pdf->commitTransaction();
                        $posyafter = $pdf->GetY();
                        $height_note = $posyafter - $tab_top;
                        $pdf->Rect($this->marge_gauche, $tab_top - 1, $tab_width, $height_note + 1);

                        if ($posyafter > ($this->page_hauteur - ($heightforfooter + $heightforfreetext + 20))) {
                            // not enough space, need to add page
                            $pdf->AddPage('', '', true);
                            $pagenb++;
                            $pageposafternote++;
                            $pdf->setPage($pageposafternote);
                            if (!empty($tplidx)) {
                                $pdf->useTemplate($tplidx);
                            }
                            if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
                                $this->_pagehead($pdf, $object, 0, $outputlangs, $pagenb);
                            }

                            $posyafter = $tab_top_newpage;
                        }
                    }

                    $tab_height = $tab_height - $height_note;
                    $tab_top = $posyafter + 6;
                } else {
                    $height_note = 0;
                }

                $nexY = $tab_top - 5;

                // Use new auto collum system
                $this->prepareArrayColumnField($object, $outputlangs, $hidedetails, $hidedesc, $hideref);

                // Loop on each lines
                $pageposbeforeprintlines = $pdf->getPage();
                $pagenb = $pageposbeforeprintlines;
                for ($i = 0; $i < $nblines; $i++) {
                    $curY = $nexY;
                    $pdf->SetFont('', '', $default_font_size - 1); // Into loop to work with multipage
                    $pdf->SetTextColor(0, 0, 0);

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

                    // We start with Photo of product line
                    if ($this->getColumnStatus('photo')) {
                        // We start with Photo of product line
                        if (isset($imglinesize['width']) && isset($imglinesize['height']) && ($curY + $imglinesize['height']) > ($this->page_hauteur - ($heightforfooter + $heightforfreetext + $heightforinfotot))) { // If photo too high, we moved completely on new page
                            $pdf->AddPage('', '', true);
                            if (!empty($tplidx)) {
                                $pdf->useTemplate($tplidx);
                            }
                            $pdf->setPage($pageposbefore + 1);

                            $curY = $tab_top_newpage;

                            // Allows data in the first page if description is long enough to break in multiples pages
                            if (!empty($conf->global->MAIN_PDF_DATA_ON_FIRST_PAGE)) {
                                $showpricebeforepagebreak = 1;
                            } else {
                                $showpricebeforepagebreak = 0;
                            }
                        }

                        if (!empty($this->cols['photo']) && isset($imglinesize['width']) && isset($imglinesize['height'])) {
                            $pdf->Image($realpatharray[$i], $this->getColumnContentXStart('photo'), $curY + 1, $imglinesize['width'], $imglinesize['height'], '', '', '', 2, 300); // Use 300 dpi
                            // $pdf->Image does not increase value return by getY, so we save it manually
                            $posYAfterImage = $curY + $imglinesize['height'];
                        }
                    }
                    // Description of product line
                    $curX = $this->posxdesc - 1;
                    $showpricebeforepagebreak = 1;

                    if ($this->getColumnStatus('desc')) {
                        $pdf->startTransaction();
                        $this->printColDescContent($pdf, $curY, 'desc', $object, $i, $outputlangs, $hideref, $hidedesc, 1);

                        $pageposafter = $pdf->getPage();
                        if ($pageposafter > $pageposbefore) { // There is a pagebreak
                            $pdf->rollbackTransaction(true);

                            $this->printColDescContent($pdf, $curY, 'desc', $object, $i, $outputlangs, $hideref, $hidedesc, 1);

                            $pageposafter = $pdf->getPage();
                            $posyafter = $pdf->GetY();
                            if ($posyafter > ($this->page_hauteur - ($heightforfooter + $heightforfreetext + $heightforinfotot))) { // There is no space left for total+free text
                                if ($i == ($nblines - 1)) { // No more lines, and no space left to show total, so we create a new page
                                    $pdf->AddPage('', '', true);
                                    if (!empty($tplidx)) {
                                        $pdf->useTemplate($tplidx);
                                    }
                                    //if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) $this->_pagehead($pdf, $object, 0, $outputlangs);
                                    $pdf->setPage($pageposafter + 1);
                                }
                            } else {
                                // We found a page break
                                // Allows data in the first page if description is long enough to break in multiples pages
                                if (!empty($conf->global->MAIN_PDF_DATA_ON_FIRST_PAGE)) {
                                    $showpricebeforepagebreak = 1;
                                } else {
                                    $showpricebeforepagebreak = 0;
                                }
                            }
                        } else // No pagebreak
                        {
                            $pdf->commitTransaction();
                        }
                        $posYAfterDescription = $pdf->GetY();
                    }

                    $nexY = $pdf->GetY();
                    $pageposafter = $pdf->getPage();
                    $pdf->setPage($pageposbefore);
                    $pdf->setTopMargin($this->marge_haute);
                    $pdf->setPageOrientation('', 1, 0); // The only function to edit the bottom margin of current page to set it.

                    // We suppose that a too long description is moved completely on next page
                    if ($pageposafter > $pageposbefore && empty($showpricebeforepagebreak)) {
                        $pdf->setPage($pageposafter);
                        $curY = $tab_top_newpage;
                    }

                    $pdf->SetFont('', '', $default_font_size - 1); // On repositionne la police par defaut

                    // VAT Rate
                    if ($this->getColumnStatus('vat')) {
                        $vat_rate = pdf_getlinevatrate($object, $i, $outputlangs, $hidedetails);
                        $this->printStdColumnContent($pdf, $curY, 'vat', $vat_rate);
                        $nexY = max($pdf->GetY(), $nexY);
                    }

                    // Unit price before discount
                    if ($this->getColumnStatus('subprice')) {
                        $up_excl_tax = pdf_getlineupexcltax($object, $i, $outputlangs, $hidedetails);
                        $this->printStdColumnContent($pdf, $curY, 'subprice', $up_excl_tax);
                        $nexY = max($pdf->GetY(), $nexY);
                    }

                    // Quantity
                    // Enough for 6 chars
                    if ($this->getColumnStatus('qty')) {
                        $qty = pdf_getlineqty($object, $i, $outputlangs, $hidedetails);
                        $this->printStdColumnContent($pdf, $curY, 'qty', $qty);
                        $nexY = max($pdf->GetY(), $nexY);
                    }

                    // Unit
                    if ($this->getColumnStatus('unit')) {
                        $unit = pdf_getlineunit($object, $i, $outputlangs, $hidedetails, $hookmanager);
                        $this->printStdColumnContent($pdf, $curY, 'unit', $unit);
                        $nexY = max($pdf->GetY(), $nexY);
                    }

                    // Discount on line
                    if ($this->getColumnStatus('discount') && $object->lines[$i]->remise_percent) {
                        $remise_percent = pdf_getlineremisepercent($object, $i, $outputlangs, $hidedetails);
                        $this->printStdColumnContent($pdf, $curY, 'discount', $remise_percent);
                        $nexY = max($pdf->GetY(), $nexY);
                    }

                    // Total HT line
                    if ($this->getColumnStatus('totalexcltax')) {
                        $total_excl_tax = pdf_getlinetotalexcltax($object, $i, $outputlangs, $hidedetails);
                        $this->printStdColumnContent($pdf, $curY, 'totalexcltax', $total_excl_tax);
                        $nexY = max($pdf->GetY(), $nexY);
                    }

                    // Extrafields
                    if (!empty($object->lines[$i]->array_options)) {
                        foreach ($object->lines[$i]->array_options as $extrafieldColKey => $extrafieldValue) {
                            if ($this->getColumnStatus($extrafieldColKey)) {
                                $extrafieldValue = $this->getExtrafieldContent($object->lines[$i], $extrafieldColKey, $outputlangs);
                                $this->printStdColumnContent($pdf, $curY, $extrafieldColKey, $extrafieldValue);
                                $nexY = max($pdf->GetY(), $nexY);
                            }
                        }
                    }

                    $parameters = array(
                        'object' => $object,
                        'i' => $i,
                        'pdf' => &$pdf,
                        'curY' => &$curY,
                        'nexY' => &$nexY,
                        'outputlangs' => $outputlangs,
                        'hidedetails' => $hidedetails,
                    );
                    $reshook = $hookmanager->executeHooks('printPDFline', $parameters, $this); // Note that $object may have been modified by hook

                    // Collecte des totaux par valeur de tva dans $this->tva["taux"]=total_tva
                    if (isModEnabled("multicurrency") && $object->multicurrency_tx != 1) {
                        $tvaligne = $object->lines[$i]->multicurrency_total_tva;
                    } else {
                        $tvaligne = $object->lines[$i]->total_tva;
                    }

                    $localtax1ligne = $object->lines[$i]->total_localtax1;
                    $localtax2ligne = $object->lines[$i]->total_localtax2;
                    $localtax1_rate = $object->lines[$i]->localtax1_tx;
                    $localtax2_rate = $object->lines[$i]->localtax2_tx;
                    $localtax1_type = $object->lines[$i]->localtax1_type;
                    $localtax2_type = $object->lines[$i]->localtax2_type;

                    // TODO remise_percent is an obsolete field for object parent
                    /*if (!empty($object->remise_percent)) {
                    $tvaligne -= ($tvaligne * $object->remise_percent) / 100;
                    }
                    if (!empty($object->remise_percent)) {
                    $localtax1ligne -= ($localtax1ligne * $object->remise_percent) / 100;
                    }
                    if (!empty($object->remise_percent)) {
                    $localtax2ligne -= ($localtax2ligne * $object->remise_percent) / 100;
                    }*/

                    $vatrate = (string) $object->lines[$i]->tva_tx;

                    // Retrieve type from database for backward compatibility with old records
                    if ((!isset($localtax1_type) || $localtax1_type == '' || !isset($localtax2_type) || $localtax2_type == '') // if tax type not defined
                        && (!empty($localtax1_rate) || !empty($localtax2_rate))
                    ) { // and there is local tax
                        $localtaxtmp_array = getLocalTaxesFromRate($vatrate, 0, $mysoc, $object->thirdparty);
                        $localtax1_type = isset($localtaxtmp_array[0]) ? $localtaxtmp_array[0] : '';
                        $localtax2_type = isset($localtaxtmp_array[2]) ? $localtaxtmp_array[2] : '';
                    }

                    // retrieve global local tax
                    if ($localtax1_type && $localtax1ligne != 0) {
                        if (empty($this->localtax1[$localtax1_type][$localtax1_rate])) {
                            $this->localtax1[$localtax1_type][$localtax1_rate] = $localtax1ligne;
                        } else {
                            $this->localtax1[$localtax1_type][$localtax1_rate] += $localtax1ligne;
                        }
                    }
                    if ($localtax2_type && $localtax2ligne != 0) {
                        if (empty($this->localtax2[$localtax2_type][$localtax2_rate])) {
                            $this->localtax2[$localtax2_type][$localtax2_rate] = $localtax2ligne;
                        } else {
                            $this->localtax2[$localtax2_type][$localtax2_rate] += $localtax2ligne;
                        }
                    }

                    if (($object->lines[$i]->info_bits & 0x01) == 0x01) {
                        $vatrate .= '*';
                    }

                    // Fill $this->tva and $this->tva_array
                    if (!isset($this->tva[$vatrate])) {
                        $this->tva[$vatrate] = 0;
                    }
                    $this->tva[$vatrate] += $tvaligne;
                    $vatcode = $object->lines[$i]->vat_src_code;
                    if (empty($this->tva_array[$vatrate . ($vatcode ? ' (' . $vatcode . ')' : '')]['amount'])) {
                        $this->tva_array[$vatrate . ($vatcode ? ' (' . $vatcode . ')' : '')]['amount'] = 0;
                    }
                    $this->tva_array[$vatrate . ($vatcode ? ' (' . $vatcode . ')' : '')] = array('vatrate' => $vatrate, 'vatcode' => $vatcode, 'amount' => $this->tva_array[$vatrate . ($vatcode ? ' (' . $vatcode . ')' : '')]['amount'] + $tvaligne);

                    if ($posYAfterImage > $posYAfterDescription) {
                        $nexY = $posYAfterImage;
                    }

                    // Add line
                    if (!empty($conf->global->MAIN_PDF_DASH_BETWEEN_LINES) && $i < ($nblines - 1)) {
                        $pdf->setPage($pageposafter);
                        $pdf->SetLineStyle(array('dash' => '1,1', 'color' => array(80, 80, 80)));
                        //$pdf->SetDrawColor(190,190,200);
                        $pdf->line($this->marge_gauche, $nexY, $this->page_largeur - $this->marge_droite, $nexY);
                        $pdf->SetLineStyle(array('dash' => 0));
                    }

                    // Detect if some page were added automatically and output _tableau for past pages
                    while ($pagenb < $pageposafter) {
                        $pdf->setPage($pagenb);
                        if ($pagenb == $pageposbeforeprintlines) {
                            $this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, $hidetop, 1, $object->multicurrency_code);
                        } else {
                            $this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, 1, 1, $object->multicurrency_code);
                        }
                        $this->_pagefoot($pdf, $object, $outputlangs, 1);
                        $pagenb++;
                        $pdf->setPage($pagenb);
                        $pdf->setPageOrientation('', 1, 0); // The only function to edit the bottom margin of current page to set it.
                        if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
                            $this->_pagehead($pdf, $object, 0, $outputlangs, $pagenb);
                        }
                        if (!empty($tplidx)) {
                            $pdf->useTemplate($tplidx);
                        }
                    }
                    if (isset($object->lines[$i + 1]->pagebreak) && $object->lines[$i + 1]->pagebreak) {
                        if ($pagenb == $pageposafter) {
                            $this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, $hidetop, 1, $object->multicurrency_code);
                        } else {
                            $this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, 1, 1, $object->multicurrency_code);
                        }
                        $this->_pagefoot($pdf, $object, $outputlangs, 1);
                        // New page
                        $pdf->AddPage();
                        if (!empty($tplidx)) {
                            $pdf->useTemplate($tplidx);
                        }
                        $pagenb++;
                        if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
                            $this->_pagehead($pdf, $object, 0, $outputlangs, $pagenb);
                        }
                    }
                }

                // Show square
                if ($pagenb == $pageposbeforeprintlines) {
                    $this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, $hidetop, 0, $object->multicurrency_code);
                    $bottomlasttab = $this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;
                } else {
                    $this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 1, 0, $object->multicurrency_code);
                    $bottomlasttab = $this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;
                }

                // Affiche zone infos
                // $posy = $this->_tableau_info($pdf, $object, $bottomlasttab, $outputlangs);

                // Affiche zone totaux
                $posy = $this->_tableau_tot($pdf, $object, $deja_regle, $bottomlasttab, $outputlangs);

                // Affiche zone versements
                if ($deja_regle || $amount_credit_notes_included || $amount_deposits_included) {
                    $posy = $this->_tableau_versements($pdf, $object, $posy, $outputlangs);
                }

                $termsContentHeight = $this->displayTermsAndCondition($pdf, $object, true);
                $remainingSpaceOnFirstPage = $this->page_hauteur - $posy - $heightforfooter;
                if ($remainingSpaceOnFirstPage > $termsContentHeight) {
                    $posy = $this->displayTermsAndCondition($pdf, $object, false);
                } else {
                    $this->_pagefoot($pdf, $object, $outputlangs, 1);
                    // New page
                    $pdf->AddPage();
                    if (!empty($tplidx)) {
                        $pdf->useTemplate($tplidx);
                    }
                    $pagenb++;
                    if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
                        $this->_pagehead($pdf, $object, 0, $outputlangs, $pagenb);
                    }

                    $posy = $this->displayTermsAndCondition($pdf, $object, false);
                }
                $posy = $this->displayRegards($pdf, $object);
                // Pied de page
                $this->_pagefoot($pdf, $object, $outputlangs);
                if (method_exists($pdf, 'AliasNbPages')) {
                    $pdf->AliasNbPages();
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
                print "<script>console.log(`page no in write " . $pagenb . "`)</script>";
                $this->result = array('fullpath' => $file);

                return 1; // No error
            } else {
                $this->error = $langs->trans("ErrorCanNotCreateDir", $dir);
                return 0;
            }
        } else {
            $this->error = $langs->trans("ErrorConstantNotDefined", "SUPPLIER_OUTPUTDIR");
            return 0;
        }
    }

    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
    /**
     *  Show payments table
     *
     *  @param    TCPDF        $pdf             Object PDF
     *  @param  CommandeFournisseur        $object            Object order
     *    @param    int            $posy            Position y in PDF
     *    @param    Translate    $outputlangs    Object langs for output
     *    @return int                            <0 if KO, >0 if OK
     */
    protected function _tableau_versements(&$pdf, $object, $posy, $outputlangs)
    {
        // phpcs:enable
        return 1;
    }

    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
    /**
     *   Show miscellaneous information (payment mode, payment term, ...)
     *
     *   @param        TCPDF        $pdf             Object PDF
     *   @param        CommandeFournisseur        $object            Object to show
     *   @param        int            $posy            Y
     *   @param        Translate    $outputlangs    Langs object
     *   @return    integer
     */
    // no need to change
    protected function _tableau_info(&$pdf, $object, $posy, $outputlangs)
    {
        // phpcs:enable
        global $conf, $mysoc;
        $default_font_size = pdf_getPDFFontSize($outputlangs);

        $diffsizetitle = (empty($conf->global->PDF_DIFFSIZE_TITLE) ? 3 : $conf->global->PDF_DIFFSIZE_TITLE);

        // If France, show VAT mention if not applicable
        if ($this->emetteur->country_code == 'FR' && empty($mysoc->tva_assuj)) {
            $pdf->SetFont('', 'B', $default_font_size - $diffsizetitle);
            $pdf->SetXY($this->marge_gauche, $posy);
            $pdf->MultiCell(100, 3, $outputlangs->transnoentities("VATIsNotUsedForInvoice"), 0, 'L', 0);

            $posy = $pdf->GetY() + 4;
        }

        $posxval = 52;

        // Show payments conditions
        if (!empty($object->cond_reglement_code) || $object->cond_reglement) {
            $pdf->SetFont('', 'B', $default_font_size - $diffsizetitle);
            $pdf->SetXY($this->marge_gauche, $posy);
            $titre = $outputlangs->transnoentities("PaymentConditions") . ':';
            $pdf->MultiCell(80, 4, $titre, 0, 'L');

            $pdf->SetFont('', '', $default_font_size - $diffsizetitle);
            $pdf->SetXY($posxval, $posy);
            $lib_condition_paiement = $outputlangs->transnoentities("PaymentCondition" . $object->cond_reglement_code) != ('PaymentCondition' . $object->cond_reglement_code) ? $outputlangs->transnoentities("PaymentCondition" . $object->cond_reglement_code) : $outputlangs->convToOutputCharset($object->cond_reglement_doc ? $object->cond_reglement_doc : $object->cond_reglement_label);
            $lib_condition_paiement = str_replace('\n', "\n", $lib_condition_paiement);
            $pdf->MultiCell(80, 4, $lib_condition_paiement, 0, 'L');

            $posy = $pdf->GetY() + 3;
        }

        // Show payment mode
        if (!empty($object->mode_reglement_code)) {
            $pdf->SetFont('', 'B', $default_font_size - $diffsizetitle);
            $pdf->SetXY($this->marge_gauche, $posy);
            $titre = $outputlangs->transnoentities("PaymentMode") . ':';
            $pdf->MultiCell(80, 5, $titre, 0, 'L');

            $pdf->SetFont('', '', $default_font_size - $diffsizetitle);
            $pdf->SetXY($posxval, $posy);
            $lib_mode_reg = $outputlangs->transnoentities("PaymentType" . $object->mode_reglement_code) != ('PaymentType' . $object->mode_reglement_code) ? $outputlangs->transnoentities("PaymentType" . $object->mode_reglement_code) : $outputlangs->convToOutputCharset($object->mode_reglement);
            $pdf->MultiCell(80, 5, $lib_mode_reg, 0, 'L');

            $posy = $pdf->GetY() + 2;
        }

        return $posy;
    }
    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
    /**
     *    Show total to pay
     *
     *    @param    TCPDF        $pdf           Object PDF
     *    @param  Facture        $object         Object invoice
     *    @param  int            $deja_regle     Montant deja regle
     *    @param    int            $posy            Position depart
     *    @param    Translate    $outputlangs    Objet langs
     *    @return int                            Position pour suite
     */
    // to display total count below discription table
    protected function _tableau_tot(&$pdf, $object, $deja_regle, $posy, $outputlangs)
    {
        // phpcs:enable
        global $conf, $mysoc;
        $default_font_size = pdf_getPDFFontSize($outputlangs);
        $tab2_top = $posy - 10;
        $tab2_hl = 4;
        $pdf->SetFont('', '', $default_font_size - 1);

        // Tableau total
        $col1x = 120;
        $col2x = 170;
        if ($this->page_largeur < 210) { // To work with US executive format
            $col2x -= 20;
        }
        $largcol2 = ($this->page_largeur - $this->marge_droite - $col2x);

        $useborder = 0;
        $index = 0;

        // Total HT
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetXY($col1x, $tab2_top);
        $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("TotalHT"), 0, 'L', 1);

        $total_ht = ((isModEnabled("multicurrency") && isset($object->multicurrency_tx) && $object->multicurrency_tx != 1) ? $object->multicurrency_total_ht : $object->total_ht);
        $pdf->SetXY($col2x, $tab2_top);
        $pdf->MultiCell($largcol2, $tab2_hl, price($total_ht + (!empty($object->remise) ? $object->remise : 0)), 0, 'R', 1);

        // Show VAT by rates and total
        $pdf->SetFillColor(248, 248, 248);

        $this->atleastoneratenotnull = 0;
        foreach ($this->tva as $tvakey => $tvaval) {
            if ($tvakey > 0) { // On affiche pas taux 0
                $this->atleastoneratenotnull++;

                $index++;
                $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

                $tvacompl = '';

                if (preg_match('/\*/', $tvakey)) {
                    $tvakey = str_replace('*', '', $tvakey);
                    $tvacompl = " (" . $outputlangs->transnoentities("NonPercuRecuperable") . ")";
                }

                $totalvat = $outputlangs->transcountrynoentities("TotalVAT", $mysoc->country_code) . ' ';
                $totalvat .= vatrate($tvakey, 1) . $tvacompl;
                $pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', 1);

                $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
                $pdf->MultiCell($largcol2, $tab2_hl, price($tvaval), 0, 'R', 1);
            }
        }
        if (!$this->atleastoneratenotnull) { // If no vat at all
            $index++;
            $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
            $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transcountrynoentities("TotalVAT", $mysoc->country_code), 0, 'L', 1);

            $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
            $pdf->MultiCell($largcol2, $tab2_hl, price($object->total_tva), 0, 'R', 1);

            // Total LocalTax1
            if (!empty($conf->global->FACTURE_LOCAL_TAX1_OPTION) && $conf->global->FACTURE_LOCAL_TAX1_OPTION == 'localtax1on' && $object->total_localtax1 > 0) {
                $index++;
                $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
                $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transcountrynoentities("TotalLT1", $mysoc->country_code), 0, 'L', 1);
                $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
                $pdf->MultiCell($largcol2, $tab2_hl, price($object->total_localtax1), $useborder, 'R', 1);
            }

            // Total LocalTax2
            if (!empty($conf->global->FACTURE_LOCAL_TAX2_OPTION) && $conf->global->FACTURE_LOCAL_TAX2_OPTION == 'localtax2on' && $object->total_localtax2 > 0) {
                $index++;
                $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
                $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transcountrynoentities("TotalLT2", $mysoc->country_code), 0, 'L', 1);
                $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
                $pdf->MultiCell($largcol2, $tab2_hl, price($object->total_localtax2), $useborder, 'R', 1);
            }
        } else {
            //if (!empty($conf->global->FACTURE_LOCAL_TAX1_OPTION) && $conf->global->FACTURE_LOCAL_TAX1_OPTION=='localtax1on')
            //{
            //Local tax 1
            foreach ($this->localtax1 as $localtax_type => $localtax_rate) {
                if (in_array((string) $localtax_type, array('2', '4', '6'))) {
                    continue;
                }

                foreach ($localtax_rate as $tvakey => $tvaval) {
                    if ($tvakey != 0) { // On affiche pas taux 0
                        //$this->atleastoneratenotnull++;

                        $index++;
                        $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

                        $tvacompl = '';
                        if (preg_match('/\*/', $tvakey)) {
                            $tvakey = str_replace('*', '', $tvakey);
                            $tvacompl = " (" . $outputlangs->transnoentities("NonPercuRecuperable") . ")";
                        }
                        $totalvat = $outputlangs->transcountrynoentities("TotalLT1", $mysoc->country_code) . ' ';
                        $totalvat .= vatrate(abs($tvakey), 1) . $tvacompl;
                        $pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', 1);

                        $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
                        $pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);
                    }
                }
            }

            //if (!empty($conf->global->FACTURE_LOCAL_TAX2_OPTION) && $conf->global->FACTURE_LOCAL_TAX2_OPTION=='localtax2on')
            //{
            //Local tax 2
            foreach ($this->localtax2 as $localtax_type => $localtax_rate) {
                if (in_array((string) $localtax_type, array('2', '4', '6'))) {
                    continue;
                }

                foreach ($localtax_rate as $tvakey => $tvaval) {
                    if ($tvakey != 0) { // On affiche pas taux 0
                        //$this->atleastoneratenotnull++;

                        $index++;
                        $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

                        $tvacompl = '';
                        if (preg_match('/\*/', $tvakey)) {
                            $tvakey = str_replace('*', '', $tvakey);
                            $tvacompl = " (" . $outputlangs->transnoentities("NonPercuRecuperable") . ")";
                        }
                        $totalvat = $outputlangs->transcountrynoentities("TotalLT2", $mysoc->country_code) . ' ';
                        $totalvat .= vatrate(abs($tvakey), 1) . $tvacompl;
                        $pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', 1);

                        $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
                        $pdf->MultiCell($largcol2, $tab2_hl, price($tvaval), 0, 'R', 1);
                    }
                }
            }
        }

        // Total TTC
        $index++;
        $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
        $pdf->SetTextColor(0, 0, 60);
        $pdf->SetFillColor(224, 224, 224);
        $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("TotalTTC"), $useborder, 'L', 1);

        $total_ttc = (isModEnabled("multicurrency") && $object->multicurrency_tx != 1) ? $object->multicurrency_total_ttc : $object->total_ttc;
        $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
        $pdf->MultiCell($largcol2, $tab2_hl, price($total_ttc), $useborder, 'R', 1);
        $pdf->SetFont('', '', $default_font_size - 1);
        $pdf->SetTextColor(0, 0, 0);

        $creditnoteamount = 0;
        $depositsamount = 0;
        //$creditnoteamount=$object->getSumCreditNotesUsed();
        //$depositsamount=$object->getSumDepositsUsed();
        //print "x".$creditnoteamount."-".$depositsamount;exit;
        $resteapayer = price2num($total_ttc - $deja_regle - $creditnoteamount - $depositsamount, 'MT');
        if (!empty($object->paye)) {
            $resteapayer = 0;
        }

        if ($deja_regle > 0) {
            // Already paid + Deposits
            $index++;

            $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
            $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("AlreadyPaid"), 0, 'L', 0);
            $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
            $pdf->MultiCell($largcol2, $tab2_hl, price($deja_regle), 0, 'R', 0);

            $index++;
            $pdf->SetTextColor(0, 0, 60);
            $pdf->SetFillColor(224, 224, 224);
            $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
            $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("RemainderToPay"), $useborder, 'L', 1);

            $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
            $pdf->MultiCell($largcol2, $tab2_hl, price($resteapayer), $useborder, 'R', 1);

            $pdf->SetFont('', '', $default_font_size - 1);
            $pdf->SetTextColor(0, 0, 0);
        }

        $index++;
        $posy = ($tab2_top + ($tab2_hl * $index));

        return $posy;
    }
    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
    /**
     *   Show table for lines
     *
     *   @param        TCPDF        $pdf             Object PDF
     *   @param        string        $tab_top        Top position of table
     *   @param        string        $tab_height        Height of table (rectangle)
     *   @param        int            $nexY            Y (not used)
     *   @param        Translate    $outputlangs    Langs object
     *   @param        int            $hidetop        Hide top bar of array
     *   @param        int            $hidebottom        Hide bottom bar of array
     *   @param        string        $currency        Currency code
     *   @return    void
     */
    // to draw discription table
    protected function _tableau(&$pdf, $tab_top, $tab_height, $nexY, $outputlangs, $hidetop = 0, $hidebottom = 0, $currency = '')
    {
        global $conf;

        // Force to disable hidetop and hidebottom
        $hidebottom = 0;
        $default_font_size = pdf_getPDFFontSize($outputlangs);

        $pdf->SetDrawColor(128, 128, 128);
        $pdf->SetFont('', '', $default_font_size - 1);

        // Output Rect
        $this->printRect($pdf, $this->marge_gauche, $tab_top - 10, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $tab_height, $hidetop, $hidebottom); // Rect takes a length in 3rd parameter and 4th parameter

        foreach ($this->cols as $colKey => $colDef) {
            if (!$this->getColumnStatus($colKey)) {
                continue;
            }

            // get title label
            $colDef['title']['label'] = !empty($colDef['title']['label']) ? $colDef['title']['label'] : $outputlangs->transnoentities($colDef['title']['textkey']);

            // Add column separator
            if (!empty($colDef['border-left'])) {
                $pdf->line($colDef['xStartPos'], $tab_top - 10, $colDef['xStartPos'], $tab_top - 10 + $tab_height);
            }

            // to display the title
            if (empty($hidetop)) {
                $pdf->SetXY($colDef['xStartPos'] + $colDef['title']['padding'][3], $tab_top - 10 + $colDef['title']['padding'][0]);

                $textWidth = $colDef['width'] - $colDef['title']['padding'][3] - $colDef['title']['padding'][1];
                $pdf->MultiCell($textWidth, 2, $colDef['title']['label'], '', $colDef['title']['align']);
            }
        }

        if (empty($hidetop)) {
            $pdf->line($this->marge_gauche, $tab_top - 5, $this->page_largeur - $this->marge_droite, $tab_top - 5); // line takes a position y in 2nd parameter and 4th parameter
        }
    }

    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
    /**
     *  Show top header of page.
     *
     *  @param    TCPDF        $pdf             Object PDF
     *  @param  CommandeFournisseur        $object         Object to show
     *  @param  Translate    $outputlangs    Object lang for output
     *  @return    float|int
     */
    protected function _pagehead(&$pdf, $object, $outputlangs, $pagenb)
    {
        global $langs, $conf, $mysoc;
        $ltrdirection = 'L';
        print "<script>console.log(`page no in head " . $pagenb . "`)</script>";
        $default_font_size = pdf_getPDFFontSize($outputlangs);

        $pdf->SetTextColor(0, 0, 60);
        $pdf->SetFont('', 'B', $default_font_size + 3);
        $w = 110;
        // right margin
        $posx = $this->marge_gauche;
        // margin top
        $posy = $this->marge_haute;

        $pdf->SetXY($posx, $posy);
        // to display text above logo
        $pdf->SetFont('', '', $default_font_size);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetY($pdf->GetY() - 7);
        $pdf->SetX($pdf->GetX() + 30);
        $pdf->MultiCell($w, 4, "First Sight Automation t/a", 0, 'L');
        // // to display company logo
        // if (!getDolGlobalInt('PDF_DISABLE_MYCOMPANY_LOGO')) {
        //     if ($this->emetteur->logo) {
        //         $logodir = $conf->mycompany->dir_output;
        //         if (!empty($conf->mycompany->multidir_output[$object->entity])) {
        //             $logodir = $conf->mycompany->multidir_output[$object->entity];
        //         }
        //         if (!getDolGlobalInt('MAIN_PDF_USE_LARGE_LOGO')) {
        //             $logo = 'C:/dolibarr/www/dolibarr/htdocs/install/doctemplates/websites/website_template-corporate/medias/image/websitekey/pdfLogo.jpg';
        //         } else {
        //             $logo = $logodir . '/logos/' . $this->emetteur->logo;
        //         }
        //         if (is_readable($logo)) {
        //             $height = pdf_getHeightForLogo($logo);
        //             $logoWidth = 120;

        //             // Calculate the X coordinate to center the logo
        //             $xCoordinate = ($pdf->GetPageWidth() - $logoWidth) / 2;
        //             // $yCoordinate = $posy + 10;
        //             $pdf->Image($logo, $xCoordinate, $posy, $logoWidth);
        //             // $pdf->Image($logo, $this->marge_gauche, $posy, 0, $height); // width=0 (auto)
        //         } else {
        //             $pdf->SetTextColor(200, 0, 0);
        //             $pdf->SetFont('', 'B', $default_font_size - 2);
        //             $pdf->MultiCell($w, 3, $outputlangs->transnoentities("ErrorLogoFileNotFound", $logo), 0, 'L');
        //             $pdf->MultiCell($w, 3, $outputlangs->transnoentities("ErrorGoToGlobalSetup"), 0, 'L');
        //         }
        //     } else {
        //         $text = $this->emetteur->name;
        //         $pdf->MultiCell($w, 4, $outputlangs->convToOutputCharset($text), 0, $ltrdirection);
        //     }
        // }

        $logo = 'C:/dolibarr/www/dolibarr/htdocs/install/doctemplates/websites/website_template-corporate/medias/image/websitekey/pdfLogo.jpg';
        if (is_readable($logo)) {
            $height = pdf_getHeightForLogo($logo);
            $logoWidth = 120;
            // Calculate the X coordinate to center the logo
            $xCoordinate = ($pdf->GetPageWidth() - $logoWidth) / 2;
            $pdf->Image($logo, $xCoordinate, $posy, $logoWidth);
        } else {
            $pdf->SetTextColor(200, 0, 0);
            $pdf->SetFont('', 'B', $default_font_size - 2);
            $pdf->MultiCell($w, 3, $outputlangs->transnoentities("ErrorLogoFileNotFound", $logo), 0, 'L');
            $pdf->MultiCell($w, 3, $outputlangs->transnoentities("ErrorGoToGlobalSetup"), 0, 'L');
        }

        $posy += 3;
        $pdf->SetFont('', '', $default_font_size - 2);

        // to display the text below logo

        $pdf->SetY($pdf->GetY() - 0);
        $tableX = $this->marge_gauche;
        $tableY = $posy + 20;
        $pdf->SetFont('', '', $default_font_size);
        $pdf->SetXY($tableX, $tableY);
        $pdf->Cell(0, 5, 'SA Company Registration: 2010/002503/23', '0', 0, 'L');
        $pdf->Cell(0, 5, 'South Africa: +27 83 268 8819', 0, 1, 'R');
        $pdf->Cell(0, 5, 'Website: www.firstvisionautomation.com', 0, 0, 'L');
        $pdf->Cell(0, 5, 'Email: atulr@firstvisionautomation.com', 0, 1, 'R');

        $po_obj = new stdClass();
        $sql_llx_commande_fournisseur = "SELECT * FROM " . MAIN_DB_PREFIX . "commande_fournisseur WHERE rowid = $object->id";
        $res_llx_commande_fournisseur = $this->db->query($sql_llx_commande_fournisseur);

        if ($res_llx_commande_fournisseur) {
            while ($row = $this->db->fetch_object($res_llx_commande_fournisseur)) {
                $originalDate = $row->datec;
                $dateTime = new DateTime($originalDate);
                $formattedDate = $dateTime->format('d M Y');
                $po_obj->dateValue = $formattedDate;
                $po_obj->company_rowid = $row->fk_soc;
                $po_obj->division = $row->division;
                $po_obj->projectid = $row->fk_projet;
                $po_obj->vendorNO = $row->vendor_no;
                $po_obj->contact = $row->contact_person;
                $po_obj->tellNo = $row->cell;
                $po_obj->email = $row->email;
                $po_obj->vendorVat = $row->vendor_vat;
                $po_obj->poNo = $row->ref;
                $po_obj->quoteNo = $row->quote_no;
                $po_obj->ourVatNo = $row->vat_no;
                // $po_obj->invoiceNo = $row->ref;

            }
        } else {
            // Handle any errors with the llx_facture query
            echo "Error executing llx_facture query: " . $this->db->lasterror();
        }

        $sql_llx_societe = "SELECT nom FROM " . MAIN_DB_PREFIX . "societe WHERE rowid = $po_obj->company_rowid";
        $res_llx_societe = $this->db->query($sql_llx_societe);
        if ($res_llx_societe) {
            $row = $this->db->fetch_object($res_llx_societe);
            if ($row) {
                $po_obj->company = $row->nom;
            }
        }

        // $sql_llx_commande_fournisseur = "SELECT ref FROM " . MAIN_DB_PREFIX . "commande_fournisseur WHERE rowid = $po_obj->company_rowid";
        // $res_llx_commande_fournisseur = $this->db->query($sql_llx_commande_fournisseur);
        // if ($res_llx_commande_fournisseur) {
        //     $row = $this->db->fetch_object($res_llx_commande_fournisseur);
        //     if ($row) {
        //         $po_obj->poNo = $row->ref;
        //     }
        // }

        $sql_llx_projet = "SELECT title FROM " . MAIN_DB_PREFIX . "projet WHERE rowid = $po_obj->projectid";
        $res_llx_projet = $this->db->query($sql_llx_projet);
        if ($res_llx_projet) {
            $row = $this->db->fetch_object($res_llx_projet);
            if ($row) {
                $po_obj->project = $row->title;
            }
        }

        $pdf->SetFont('', 'B', 14); // Set bold font with size 14
        $pdf->Cell(0, 10, 'PURCHASE ORDER', 0, 1, 'C'); // Centered heading
        $tableX = $this->marge_gauche;
        $tableY = $posy + 40;
        $pdf->SetFont('');
        $pdf->SetFont('', '', $default_font_size);
        $pdf->SetXY($tableX, $tableY);
        $pdf->Cell(0, 5, 'Date: ' . $po_obj->dateValue, 'LTR', 0, 'L');
        $pdf->Cell(0, 5, 'Division: ' . $po_obj->division, 'R', 1, 'R');
        $pdf->Cell(0, 5, 'Company: ' . $po_obj->company, 'L', 0, 'L');
        $pdf->Cell(0, 5, 'Vendor No: ' . $po_obj->vendorNO, 'R', 1, 'R');
        $pdf->Cell(0, 5, 'Project: ' . $po_obj->project, 'L', 0, 'L');
        $pdf->Cell(0, 5, 'P.O. No.: ' . $po_obj->poNo, 'R', 1, 'R');
        $pdf->Cell(0, 5, 'Contact: ' . $po_obj->contact, 'L', 0, 'L');
        $pdf->Cell(0, 5, '                 ', 'R', 1, 'R');
        $pdf->Cell(0, 5, 'Tel No: ' . $po_obj->tellNo, 'L', 0, 'L');
        $pdf->Cell(0, 5, 'Quote No.: ' . $po_obj->quoteNo, 'R', 1, 'R');
        $pdf->Cell(0, 5, 'Email: ' . $po_obj->email, 'L', 0, 'L');
        $pdf->Cell(0, 5, '              ', 'R', 1, 'R');
        $pdf->Cell(0, 5, 'Vendor Vat: ' . $po_obj->vendorVat, 'LB', 0, 'L');
        $pdf->Cell(0, 5, 'Our Vat No.: ' . $po_obj->ourVatNo, 'RB', 1, 'R');

        return $posy;
    }

    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
    /**
     *       Show footer of page. Need this->emetteur object
     *
     *       @param    TCPDF        $pdf                 PDF
     *         @param    CommandeFournisseur        $object                Object to show
     *      @param    Translate    $outputlangs        Object lang for output
     *      @param    int            $hidefreetext        1=Hide free text
     *      @return    int                                Return height of bottom margin including footer text
     */
    protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0)
    {
        $showdetails = getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS', 0);

        $pdf->SetXY($this->marge_gauche, $this->page_hauteur - $this->marge_basse);
        $pdf->MultiCell(0, 0, '#23 Green Park, Dickie Fritz Avenue,', 0, 'L');
        $pdf->MultiCell(0, 0, "Edenvale 1609 Johannesburg, South Africa", 0, 'L');
        return pdf_pagefoot($pdf, $outputlangs, 'SUPPLIER_ORDER_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext);
    }

    /**
     *       Define Array Column Field
     *
     *       @param    object            $object            common object
     *       @param    Translate        $outputlangs    langs
     *      @param    int               $hidedetails        Do not show line details
     *      @param    int               $hidedesc        Do not show desc
     *      @param    int               $hideref            Do not show ref
     *      @return    void
     */
    // for table data
    public function defineColumnField($object, $outputlangs, $hidedetails = 0, $hidedesc = 0, $hideref = 0)
    {
        global $conf, $hookmanager;

        // Default field style for content
        $this->defaultContentsFieldsStyle = array(
            'align' => 'R', // R,C,L
            'padding' => array(1, 0.5, 1, 0.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
        );

        // Default field style for content
        $this->defaultTitlesFieldsStyle = array(
            'align' => 'C', // R,C,L
            'padding' => array(0.5, 0, 0.5, 0), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
        );

        /*
         * For exemple
        $this->cols['theColKey'] = array(
        'rank' => $rank, // int : use for ordering columns
        'width' => 20, // the column width in mm
        'title' => array(
        'textkey' => 'yourLangKey', // if there is no label, yourLangKey will be translated to replace label
        'label' => ' ', // the final label : used fore final generated text
        'align' => 'L', // text alignement :  R,C,L
        'padding' => array(0.5,0.5,0.5,0.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
        ),
        'content' => array(
        'align' => 'L', // text alignement :  R,C,L
        'padding' => array(0.5,0.5,0.5,0.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
        ),
        );
         */

        $rank = 0; // do not use negative rank
        $this->cols['desc'] = array(
            'rank' => $rank,
            'width' => false, // only for desc
            'status' => true,
            'title' => array(
                'textkey' => 'Designation', // use lang key is usefull in somme case with module
                'align' => 'L',
                // 'textkey' => 'yourLangKey', // if there is no label, yourLangKey will be translated to replace label
                // 'label' => ' ', // the final label
                'padding' => array(0.5, 1, 0.5, 1.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
            ),
            'content' => array(
                'align' => 'L',
                'padding' => array(1, 0.5, 1, 1.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
            ),
        );

        $rank = $rank + 10;
        $this->cols['photo'] = array(
            'rank' => $rank,
            'width' => (empty($conf->global->MAIN_DOCUMENTS_WITH_PICTURE_WIDTH) ? 20 : $conf->global->MAIN_DOCUMENTS_WITH_PICTURE_WIDTH), // in mm
            'status' => false,
            'title' => array(
                'textkey' => 'Photo',
                'label' => ' ',
            ),
            'content' => array(
                'padding' => array(0, 0, 0, 0), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
            ),
            'border-left' => false, // remove left line separator
        );

        if (!empty($conf->global->MAIN_GENERATE_SUPPLIER_ORDER_WITH_PICTURE)) {
            $this->cols['photo']['status'] = true;
        }

        $rank = $rank + 10;
        $this->cols['vat'] = array(
            'rank' => $rank,
            'status' => false,
            'width' => 16, // in mm
            'title' => array(
                'textkey' => 'VAT',
            ),
            'border-left' => true, // add left line separator
        );

        if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT) && empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_COLUMN)) {
            $this->cols['vat']['status'] = true;
        }

        $rank = $rank + 10;
        $this->cols['subprice'] = array(
            'rank' => $rank,
            'width' => 19, // in mm
            'status' => false,
            'title' => array(
                'textkey' => 'PriceUHT',
            ),
            'border-left' => true, // add left line separator
        );

        if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_PURCHASE_ORDER_WITHOUT_UNIT_PRICE)) {
            $this->cols['subprice']['status'] = true;
        }

        $rank = $rank + 10;
        $this->cols['qty'] = array(
            'rank' => $rank,
            'width' => 16, // in mm
            'status' => true,
            'title' => array(
                'textkey' => 'Qty',
            ),
            'border-left' => true, // add left line separator
        );

        $rank = $rank + 10;
        $this->cols['unit'] = array(
            'rank' => $rank,
            'width' => 11, // in mm
            'status' => false,
            'title' => array(
                'textkey' => 'Unit',
            ),
            'border-left' => true, // add left line separator
        );
        if (getDolGlobalInt('PRODUCT_USE_UNITS')) {
            $this->cols['unit']['status'] = true;
        }

        $rank = $rank + 10;
        $this->cols['discount'] = array(
            'rank' => $rank,
            'width' => 13, // in mm
            'status' => false,
            'title' => array(
                'textkey' => 'ReductionShort',
            ),
            'border-left' => true, // add left line separator
        );
        if ($this->atleastonediscount) {
            $this->cols['discount']['status'] = true;
        }

        $rank = $rank + 1000; // add a big offset to be sure is the last col because default extrafield rank is 100
        $this->cols['totalexcltax'] = array(
            'rank' => $rank,
            'width' => 26, // in mm
            'status' => true,
            'title' => array(
                'textkey' => 'TotalHT',
            ),
            'border-left' => true, // add left line separator
        );

        if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_PURCHASE_ORDER_WITHOUT_TOTAL_COLUMN)) {
            $this->cols['totalexcltax']['status'] = true;
        }

        // Add extrafields cols
        if (!empty($object->lines)) {
            $line = reset($object->lines);
            $this->defineColumnExtrafield($line, $outputlangs, $hidedetails);
        }

        $parameters = array(
            'object' => $object,
            'outputlangs' => $outputlangs,
            'hidedetails' => $hidedetails,
            'hidedesc' => $hidedesc,
            'hideref' => $hideref,
        );

        $reshook = $hookmanager->executeHooks('defineColumnField', $parameters, $this); // Note that $object may have been modified by hook
        if ($reshook < 0) {
            setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
        } elseif (empty($reshook)) {
            $this->cols = array_replace($this->cols, $hookmanager->resArray); // array_replace is used to preserve keys
        } else {
            $this->cols = $hookmanager->resArray;
        }
    }

    public function displayTermsAndCondition(&$pdf, $object, $calculateHeightOnly = false)
    {
        $lineHeight = 5;
        $marginTop = 3;
        $sql_llx_commande_fournisseur = "SELECT * FROM " . MAIN_DB_PREFIX . "commande_fournisseur WHERE rowid = $object->id";
        $res_llx_commande_fournisseur = $this->db->query($sql_llx_commande_fournisseur);

        if ($res_llx_commande_fournisseur) {
            while ($row = $this->db->fetch_object($res_llx_commande_fournisseur)) {
                $terms_and_conditions = json_decode($row->terms_and_conditions, true); 
            }
        }
        
        // Define your terms and conditions content
        if (isset($terms_and_conditions) && is_array($terms_and_conditions)) {
            $termsContent = "Terms and Conditions";

            foreach ($terms_and_conditions as $index => $condition) {
                $termsContent .= "\n" . str_repeat(' ', 9) . ($index + 1) . ". " . $condition .".";
            }
        } else {
            $termsContent = "Default Terms and Conditions: No specific terms found.";
        }

        // Define your terms and conditions content
        // $termsContent = "Terms and Conditions:
		// 1. Weekly Progress sign-off report to be emailed on Mondays.
		// 2. Delivery is 4 weeks from order placement.
		// 3. Payment terms – 40% on order placement, 40% on fabrication complete and release for
		//    powder coating and 20% on panel delivery";

        // Explode the terms content into an array of lines
        $termsLines = explode("\n", $termsContent);

        $termsContentHeight = count($termsLines) * $lineHeight + $marginTop;

        // Iterate through each line of terms and add it to the PDF
        if (!$calculateHeightOnly) {
            // Iterate through each line of terms and add it to the PDF
            foreach ($termsLines as $line) {
                $pdf->MultiCell(0, $lineHeight, $line, 0, 'L', 0);
            }
        }
        return $termsContentHeight;
    }

    public function displayRegards(&$pdf, $object, $contentHeight = false)
    {
        global $contentHeight;
        $initialY = $pdf->GetY();
        $marginTop = 8;
        $pdf->SetY($initialY + $marginTop);
        // to display message
        $pdf->MultiCell(0, 5, "We trust our offer meets your requirements.Should you require any additional information, please do not hesitate to call upon the undersigned.", 0, 'L');
        $pdf->MultiCell(0, 5, "With Kind Regards", 0, 'L');
        $pdf->MultiCell(0, 5, "Atul Rajgure.", 0, 'L');
        $pdf->MultiCell(0, 5, "Cell: +27 83 268 8819.", 0, 'L');

        $finalY = $pdf->GetY();

        $contentHeight = $finalY - $initialY;
    }
}
