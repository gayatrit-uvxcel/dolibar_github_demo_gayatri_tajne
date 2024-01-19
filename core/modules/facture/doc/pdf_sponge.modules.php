<?php
/* Copyright (C) 2004-2014  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012  Regis Houssin           <regis.houssin@inodbox.com>
 * Copyright (C) 2008       Raphael Bertrand        <raphael.bertrand@resultic.fr>
 * Copyright (C) 2010-2014  Juanjo Menent           <jmenent@2byte.es>
 * Copyright (C) 2012       Christophe Battarel     <christophe.battarel@altairis.fr>
 * Copyright (C) 2012       Cédric Salvador         <csalvador@gpcsolutions.fr>
 * Copyright (C) 2012-2014  Raphaël Doursenaud      <rdoursenaud@gpcsolutions.fr>
 * Copyright (C) 2015       Marcos García           <marcosgdf@gmail.com>
 * Copyright (C) 2017       Ferran Marcet           <fmarcet@2byte.es>
 * Copyright (C) 2018       Frédéric France         <frederic.france@netlogic.fr>
 * Copyright (C) 2022        Anthony Berton                <anthony.berton@bb2a.fr>
 * Copyright (C) 2022       Alexandre Spangaro      <aspangaro@open-dsi.fr>
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
 *  \file       htdocs/core/modules/facture/doc/pdf_sponge.modules.php
 *  \ingroup    facture
 *  \brief      File of class to generate customers invoices from sponge model
 */

require_once DOL_DOCUMENT_ROOT . '/core/modules/facture/modules_facture.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';

/**
 *    Class to manage PDF invoice template sponge
 */
class pdf_sponge extends ModelePDFFactures
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
     * @var int heightforinfotot
     */
    public $heightforinfotot;

    /**
     * @var int heightforfreetext
     */
    public $heightforfreetext;

    /**
     * @var int heightforfooter
     */
    public $heightforfooter;

    /**
     * @var int tab_top
     */
    public $tab_top;

    /**
     * @var int tab_top_newpage
     */
    public $tab_top_newpage;

    /**
     * Issuer
     * @var Societe Object that emits
     */
    public $emetteur;

    /**
     * @var bool Situation invoice type
     */
    public $situationinvoice;

    /**
     * @var array of document table columns
     */
    public $cols;

    /**
     * @var int Category of operation
     */
    public $categoryOfOperation = -1; // unknown by default

    /**
     *    Constructor
     *
     *  @param        DoliDB        $db      Database handler
     */
    public function __construct($db)
    {
        global $conf, $langs, $mysoc;

        // Translations
        $langs->loadLangs(array("main", "bills"));

        $this->db = $db;
        $this->name = "sponge";
        $this->description = $langs->trans('PDFSpongeDescription');
        $this->update_main_doc_field = 1; // Save the name of generated file as the main doc when generating a doc with this template

        // Dimension page
        $this->type = 'pdf';
        $formatarray = pdf_getFormat();
        $this->page_largeur = $formatarray['width'];
        $this->page_hauteur = $formatarray['height'];
        $this->format = array($this->page_largeur, $this->page_hauteur);
        $this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
        $this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
        $this->marge_haute = getDolGlobalInt('MAIN_PDF_MARGIN_TOP', 10);
        $this->marge_basse = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM', 10);

        // $this->option_logo = 1; // Display logo
        $this->option_tva = 1; // Manage the vat option FACTURE_TVAOPTION
        $this->option_modereg = 1; // Display payment mode
        $this->option_condreg = 1; // Display payment terms
        $this->option_multilang = 1; // Available in several languages
        $this->option_escompte = 1; // Displays if there has been a discount
        $this->option_credit_note = 1; // Support credit notes
        $this->option_freetext = 1; // Support add of a personalised text
        $this->option_draft_watermark = 1; // Support add of a watermark on drafts
        $this->watermark = '';

        // Get source company
        $this->emetteur = $mysoc;
        if (empty($this->emetteur->country_code)) {
            $this->emetteur->country_code = substr($langs->defaultlang, -2); // By default, if was not defined
        }

        // Define position of columns
        $this->posxdesc = $this->marge_gauche + 1; // used for notes ans other stuff

        $this->tabTitleHeight = 5; // default height

        //  Use new system for position of columns, view  $this->defineColumnField()

        $this->tva = array();
        $this->tva_array = array();
        $this->localtax1 = array();
        $this->localtax2 = array();
        $this->atleastoneratenotnull = 0;
        $this->atleastonediscount = 0;
        $this->situationinvoice = false;
    }

    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
    /**
     *  Function to build pdf onto disk
     *
     *  @param        Facture        $object                Object to generate
     *  @param        Translate    $outputlangs        Lang output object
     *  @param        string        $srctemplatepath    Full path of source filename for generator using a template file
     *  @param        int            $hidedetails        Do not show line details
     *  @param        int            $hidedesc            Do not show desc
     *  @param        int            $hideref            Do not show ref
     *  @return     int                             1=OK, 0=KO
     */
    public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0)
    {
        // phpcs:enable
        global $user, $langs, $conf, $mysoc, $db, $hookmanager, $nblines;

        dol_syslog("write_file outputlangs->defaultlang=" . (is_object($outputlangs) ? $outputlangs->defaultlang : 'null'));

        if (!is_object($outputlangs)) {
            $outputlangs = $langs;
        }
        // For backward compatibility with FPDF, force output charset to ISO, because FPDF expect text to be encoded in ISO
        if (!empty($conf->global->MAIN_USE_FPDF)) {
            $outputlangs->charset_output = 'ISO-8859-1';
        }

        // Load translation files required by the page
        $outputlangs->loadLangs(array("main", "bills", "products", "dict", "companies"));

        global $outputlangsbis;
        $outputlangsbis = null;
        if (!empty($conf->global->PDF_USE_ALSO_LANGUAGE_CODE) && $outputlangs->defaultlang != $conf->global->PDF_USE_ALSO_LANGUAGE_CODE) {
            $outputlangsbis = new Translate('', $conf);
            $outputlangsbis->setDefaultLang($conf->global->PDF_USE_ALSO_LANGUAGE_CODE);
            $outputlangsbis->loadLangs(array("main", "bills", "products", "dict", "companies"));
        }

        // Show Draft Watermark
        if ($object->statut == $object::STATUS_DRAFT && (!empty($conf->global->FACTURE_DRAFT_WATERMARK))) {
            $this->watermark = $conf->global->FACTURE_DRAFT_WATERMARK;
        }

        $nblines = count($object->lines);

        for ($i = 0; $i < $nblines; $i++) {
            $productId = $object->lines[$i]->rowid;
            $sql_llx_facturedet = "SELECT * FROM " . MAIN_DB_PREFIX . "facturedet WHERE rowid = $productId";
            $res_llx_facturedet = $this->db->query($sql_llx_facturedet);
            if ($res_llx_facturedet) {
                while ($row = $this->db->fetch_object($res_llx_facturedet)) {
                    $object->lines[$i]->category = $row->category;
                }
            }
        }

        $hidetop = 0;
        if (!empty($conf->global->MAIN_PDF_DISABLE_COL_HEAD_TITLE)) {
            $hidetop = $conf->global->MAIN_PDF_DISABLE_COL_HEAD_TITLE;
        }

        // Loop on each lines to detect if there is at least one image to show
        $realpatharray = array();
        $this->atleastonephoto = false;
        if (!empty($conf->global->MAIN_GENERATE_INVOICES_WITH_PICTURE)) {
            $objphoto = new Product($this->db);

            for ($i = 0; $i < $nblines; $i++) {
                if (empty($object->lines[$i]->fk_product)) {
                    continue;
                }

                $objphoto->fetch($object->lines[$i]->fk_product);
                //var_dump($objphoto->ref);exit;
                if (getDolGlobalInt('PRODUCT_USE_OLD_PATH_FOR_PHOTO')) {
                    $pdir[0] = get_exdir($objphoto->id, 2, 0, 0, $objphoto, 'product') . $objphoto->id . "/photos/";
                    $pdir[1] = get_exdir(0, 0, 0, 0, $objphoto, 'product') . dol_sanitizeFileName($objphoto->ref) . '/';
                } else {
                    $pdir[0] = get_exdir(0, 0, 0, 0, $objphoto, 'product'); // default
                    $pdir[1] = get_exdir($objphoto->id, 2, 0, 0, $objphoto, 'product') . $objphoto->id . "/photos/"; // alternative
                }

                $arephoto = false;
                foreach ($pdir as $midir) {
                    if (!$arephoto) {
                        if ($conf->entity != $objphoto->entity) {
                            $dir = $conf->product->multidir_output[$objphoto->entity] . '/' . $midir; //Check repertories of current entities
                        } else {
                            $dir = $conf->product->dir_output . '/' . $midir; //Check repertory of the current product
                        }

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
                            $arephoto = true;
                            $this->atleastonephoto = true;
                        }
                    }
                }

                if ($realpath && $arephoto) {
                    $realpatharray[$i] = $realpath;
                }
            }
        }

        //if (count($realpatharray) == 0) $this->posxpicture=$this->posxtva;

        if ($conf->facture->multidir_output[$conf->entity]) {
            $object->fetch_thirdparty();

            $deja_regle = $object->getSommePaiement((isModEnabled("multicurrency") && $object->multicurrency_tx != 1) ? 1 : 0);
            $amount_credit_notes_included = $object->getSumCreditNotesUsed((isModEnabled("multicurrency") && $object->multicurrency_tx != 1) ? 1 : 0);
            $amount_deposits_included = $object->getSumDepositsUsed((isModEnabled("multicurrency") && $object->multicurrency_tx != 1) ? 1 : 0);

            // Definition of $dir and $file
            if ($object->specimen) {
                $dir = $conf->facture->multidir_output[$conf->entity];
                $file = $dir . "/SPECIMEN.pdf";
            } else {
                $objectref = dol_sanitizeFileName($object->ref);
                $dir = $conf->facture->multidir_output[$object->entity] . "/" . $objectref;
                $file = $dir . "/" . $objectref . ".pdf";
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

                // Set nblines with the new facture lines content after hook
                $nblines = count($object->lines);
                $nbpayments = count($object->getListOfPayments());

                // Create pdf instance
                $pdf = pdf_getInstance($this->format);
                $default_font_size = pdf_getPDFFontSize($outputlangs); // Must be after pdf_getInstance
                $pdf->SetAutoPageBreak(1, 0);

                $this->heightforinfotot = 50 + (4 * $nbpayments); // Height reserved to output the info and total part and payment part
                $this->heightforfreetext = (isset($conf->global->MAIN_PDF_FREETEXT_HEIGHT) ? $conf->global->MAIN_PDF_FREETEXT_HEIGHT : 5); // Height reserved to output the free text on last page
                $this->heightforfooter = $this->marge_basse + (empty($conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS) ? 12 : 22); // Height reserved to output the footer (value include bottom margin)

                $heightforqrinvoice = $heightforqrinvoice_firstpage = 0;
                if (getDolGlobalString('INVOICE_ADD_SWISS_QR_CODE') == 'bottom') {
                    if ($this->getHeightForQRInvoice(1, $object, $langs) > 0) {
                        // Shrink infotot to a base 30
                        $this->heightforinfotot = 30 + (4 * $nbpayments); // Height reserved to output the info and total part and payment part
                    }
                }

                if (class_exists('TCPDF')) {
                    $pdf->setPrintHeader(false);
                    $pdf->setPrintFooter(false);
                }
                $pdf->SetFont(pdf_getPDFFont($outputlangs));

                // Set path to the background PDF File
                if (!empty($conf->global->MAIN_ADD_PDF_BACKGROUND)) {
                    $logodir = $conf->mycompany->dir_output;
                    if (!empty($conf->mycompany->multidir_output[$object->entity])) {
                        $logodir = $conf->mycompany->multidir_output[$object->entity];
                    }
                    $pagecount = $pdf->setSourceFile($logodir . '/' . $conf->global->MAIN_ADD_PDF_BACKGROUND);
                    $tplidx = $pdf->importPage(1);
                }

                $pdf->Open();
                $pagenb = 0;
                $pdf->SetDrawColor(128, 128, 128);

                $pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
                $pdf->SetSubject($outputlangs->transnoentities("PdfInvoiceTitle"));
                $pdf->SetCreator("Dolibarr " . DOL_VERSION);
                $pdf->SetAuthor($mysoc->name . ($user->id > 0 ? ' - ' . $outputlangs->convToOutputCharset($user->getFullName($outputlangs)) : ''));
                $pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref) . " " . $outputlangs->transnoentities("PdfInvoiceTitle") . " " . $outputlangs->convToOutputCharset($object->thirdparty->name));
                if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
                    $pdf->SetCompression(false);
                }

                // Set certificate
                $cert = empty($user->conf->CERTIFICATE_CRT) ? '' : $user->conf->CERTIFICATE_CRT;
                $certprivate = empty($user->conf->CERTIFICATE_CRT_PRIVATE) ? '' : $user->conf->CERTIFICATE_CRT_PRIVATE;
                // If user has no certificate, we try to take the company one
                if (!$cert) {
                    $cert = empty($conf->global->CERTIFICATE_CRT) ? '' : $conf->global->CERTIFICATE_CRT;
                }
                if (!$certprivate) {
                    $certprivate = empty($conf->global->CERTIFICATE_CRT_PRIVATE) ? '' : $conf->global->CERTIFICATE_CRT_PRIVATE;
                }
                // If a certificate is found
                if ($cert) {
                    $info = array(
                        'Name' => $this->emetteur->name,
                        'Location' => getCountry($this->emetteur->country_code, 0),
                        'Reason' => 'INVOICE',
                        'ContactInfo' => $this->emetteur->email,
                    );
                    $pdf->setSignature($cert, $certprivate, $this->emetteur->name, '', 2, $info);
                }

                $pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite); // Left, Top, Right

                // Set $this->atleastonediscount if you have at least one discount
                // and determine category of operation
                $categoryOfOperation = 0;
                $nbProduct = 0;
                $nbService = 0;
                for ($i = 0; $i < $nblines; $i++) {
                    if ($object->lines[$i]->remise_percent) {
                        $this->atleastonediscount++;
                    }

                    // determine category of operation
                    if ($categoryOfOperation < 2) {
                        $lineProductType = $object->lines[$i]->product_type;
                        if ($lineProductType == Product::TYPE_PRODUCT) {
                            $nbProduct++;
                        } elseif ($lineProductType == Product::TYPE_SERVICE) {
                            $nbService++;
                        }
                        if ($nbProduct > 0 && $nbService > 0) {
                            // mixed products and services
                            $categoryOfOperation = 2;
                        }
                    }
                }
                // determine category of operation
                if ($categoryOfOperation <= 0) {
                    // only services
                    if ($nbProduct == 0 && $nbService > 0) {
                        $categoryOfOperation = 1;
                    }
                }
                $this->categoryOfOperation = $categoryOfOperation;

                // Situation invoice handling
                if ($object->situation_cycle_ref) {
                    $this->situationinvoice = true;
                }

                // New page
                $pdf->AddPage();
                if (!empty($tplidx)) {
                    $pdf->useTemplate($tplidx);
                }
                $pagenb++;

                // Output header (logo, ref and address blocks). This is first call for first page.
                $pagehead = $this->_pagehead($pdf, $object, 1, $outputlangs, $outputlangsbis, $pagenb);
                $top_shift = $pagehead['top_shift'];
                $shipp_shift = $pagehead['shipp_shift'];
                $pdf->SetFont('', '', $default_font_size - 1);
                $pdf->MultiCell(0, 3, ''); // Set interline to 3
                $pdf->SetTextColor(0, 0, 0);

                // $pdf->GetY() here can't be used. It is bottom of the second addresse box but first one may be higher

                // $this->tab_top is y where we must continue content (90 = 42 + 48: 42 is height of logo and ref, 48 is address blocks)
                $this->tab_top = 90 + $top_shift + $shipp_shift; // top_shift is an addition for linked objects or addons (0 in most cases)
                $this->tab_top_newpage = (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD') ? 42 + $top_shift : 10);

                // You can add more thing under header here, if you increase $extra_under_address_shift too.
                $extra_under_address_shift = 0;
                $qrcodestring = '';
                if (!empty($conf->global->INVOICE_ADD_ZATCA_QR_CODE)) {
                    $qrcodestring = $object->buildZATCAQRString();
                } elseif (getDolGlobalString('INVOICE_ADD_SWISS_QR_CODE') == '1') {
                    $qrcodestring = $object->buildSwitzerlandQRString();
                }
                if ($qrcodestring) {
                    $qrcodecolor = array('25', '25', '25');
                    // set style for QR-code
                    $styleQr = array(
                        'border' => false,
                        'padding' => 0,
                        'fgcolor' => $qrcodecolor,
                        'bgcolor' => false, //array(255,255,255)
                        'module_width' => 1, // width of a single module in points
                        'module_height' => 1, // height of a single module in points
                    );
                    $pdf->write2DBarcode($qrcodestring, 'QRCODE,M', $this->marge_gauche, $this->tab_top - 5, 25, 25, $styleQr, 'N');
                    $extra_under_address_shift += 25;
                }

                // Call hook printUnderHeaderPDFline
                $parameters = array(
                    'object' => $object,
                    'i' => $i,
                    'pdf' => &$pdf,
                    'outputlangs' => $outputlangs,
                    'hidedetails' => $hidedetails,
                );
                $reshook = $hookmanager->executeHooks('printUnderHeaderPDFline', $parameters, $this); // Note that $object may have been modified by hook
                if (!empty($hookmanager->resArray['extra_under_address_shift'])) {
                    $extra_under_address_shift += $hookmanager->resArray['extra_under_header_shift'];
                }

                $this->tab_top += $extra_under_address_shift;
                $this->tab_top_newpage += 0;

                // Define heigth of table for lines (for first page)
                $tab_height = $this->page_hauteur - $this->tab_top - $this->heightforfooter - $this->heightforfreetext - $this->getHeightForQRInvoice(1, $object, $langs);

                $nexY = $this->tab_top - 1;

                // Incoterm
                $height_incoterms = 0;
                if (isModEnabled('incoterm')) {
                    $desc_incoterms = $object->getIncotermsForPDF();
                    if ($desc_incoterms) {
                        $this->tab_top -= 2;

                        $pdf->SetFont('', '', $default_font_size - 1);
                        $pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $this->tab_top - 1, dol_htmlentitiesbr($desc_incoterms), 0, 1);
                        $nexY = max($pdf->GetY(), $nexY);
                        $height_incoterms = $nexY - $this->tab_top;

                        // Rect takes a length in 3rd parameter
                        $pdf->SetDrawColor(192, 192, 192);
                        $pdf->Rect($this->marge_gauche, $this->tab_top - 1, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $height_incoterms + 1);

                        $this->tab_top = $nexY + 6;
                        $height_incoterms += 4;
                    }
                }

                // Displays notes. Here we are still on code eecuted only for the first page.
                $notetoshow = empty($object->note_public) ? '' : $object->note_public;
                if (!empty($conf->global->MAIN_ADD_SALE_REP_SIGNATURE_IN_NOTE)) {
                    // Get first sale rep
                    if (is_object($object->thirdparty)) {
                        $salereparray = $object->thirdparty->getSalesRepresentatives($user);
                        $salerepobj = new User($this->db);
                        $salerepobj->fetch($salereparray[0]['id']);
                        if (!empty($salerepobj->signature)) {
                            $notetoshow = dol_concatdesc($notetoshow, $salerepobj->signature);
                        }
                    }
                }

                // Extrafields in note
                $extranote = $this->getExtrafieldsInHtml($object, $outputlangs);
                if (!empty($extranote)) {
                    $notetoshow = dol_concatdesc($notetoshow, $extranote);
                }

                $pagenb = $pdf->getPage();
                if ($notetoshow) {
                    $this->tab_top -= 2;

                    $tab_width = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
                    $pageposbeforenote = $pagenb;

                    $substitutionarray = pdf_getSubstitutionArray($outputlangs, null, $object);
                    complete_substitutions_array($substitutionarray, $outputlangs, $object);
                    $notetoshow = make_substitutions($notetoshow, $substitutionarray, $outputlangs);
                    $notetoshow = convertBackOfficeMediasLinksToPublicLinks($notetoshow);

                    $pdf->startTransaction();

                    $pdf->SetFont('', '', $default_font_size - 1);
                    $pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $this->tab_top, dol_htmlentitiesbr($notetoshow), 0, 1);
                    // Description
                    $pageposafternote = $pdf->getPage();
                    $posyafter = $pdf->GetY();

                    if ($pageposafternote > $pageposbeforenote) {
                        $pdf->rollbackTransaction(true);

                        // prepare pages to receive notes
                        while ($pagenb < $pageposafternote) {
                            $pdf->AddPage();
                            $pagenb++;
                            if (!empty($tplidx)) {
                                $pdf->useTemplate($tplidx);
                            }
                            if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
                                $this->_pagehead($pdf, $object, 0, $outputlangs, $outputlangsbis, $pagenb);
                            }
                            $pdf->setTopMargin($this->tab_top_newpage);
                            // The only function to edit the bottom margin of current page to set it.
                            $pdf->setPageOrientation('', 1, $this->heightforfooter + $this->heightforfreetext);
                        }

                        // back to start
                        $pdf->setPage($pageposbeforenote);
                        $pdf->setPageOrientation('', 1, $this->heightforfooter + $this->heightforfreetext);
                        $pdf->SetFont('', '', $default_font_size - 1);
                        $pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $this->tab_top, dol_htmlentitiesbr($notetoshow), 0, 1);
                        $pageposafternote = $pdf->getPage();

                        $posyafter = $pdf->GetY();

                        if ($posyafter > ($this->page_hauteur - ($this->heightforfooter + $this->heightforfreetext + 20))) { // There is no space left for total+free text
                            $pdf->AddPage('', '', true);
                            $pagenb++;
                            $pageposafternote++;
                            $pdf->setPage($pageposafternote);
                            $pdf->setTopMargin($this->tab_top_newpage);
                            // The only function to edit the bottom margin of current page to set it.
                            $pdf->setPageOrientation('', 1, $this->heightforfooter + $this->heightforfreetext);
                            //$posyafter = $this->tab_top_newpage;
                        }

                        // apply note frame to previous pages
                        $i = $pageposbeforenote;
                        while ($i < $pageposafternote) {
                            $pdf->setPage($i);

                            $pdf->SetDrawColor(128, 128, 128);
                            // Draw note frame
                            if ($i > $pageposbeforenote) {
                                $height_note = $this->page_hauteur - ($this->tab_top_newpage + $this->heightforfooter);
                                $pdf->Rect($this->marge_gauche, $this->tab_top_newpage - 1, $tab_width, $height_note + 1);
                            } else {
                                $height_note = $this->page_hauteur - ($this->tab_top + $this->heightforfooter);
                                $pdf->Rect($this->marge_gauche, $this->tab_top - 1, $tab_width, $height_note + 1);
                            }

                            // Add footer
                            $pdf->setPageOrientation('', 1, 0); // The only function to edit the bottom margin of current page to set it.
                            $this->_pagefoot($pdf, $object, $outputlangs, 1, $this->getHeightForQRInvoice($i, $object, $outputlangs));

                            $i++;
                        }

                        // apply note frame to last page
                        $pdf->setPage($pageposafternote);
                        if (!empty($tplidx)) {
                            $pdf->useTemplate($tplidx);
                        }
                        if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
                            $this->_pagehead($pdf, $object, 0, $outputlangs, $outputlangsbis, $pagenb);
                        }
                        $height_note = $posyafter - $this->tab_top_newpage;
                        $pdf->Rect($this->marge_gauche, $this->tab_top_newpage - 1, $tab_width, $height_note + 1);
                    } else {
                        // No pagebreak
                        $pdf->commitTransaction();
                        $posyafter = $pdf->GetY();
                        $height_note = $posyafter - $this->tab_top;
                        $pdf->Rect($this->marge_gauche, $this->tab_top - 1, $tab_width, $height_note + 1);

                        if ($posyafter > ($this->page_hauteur - ($this->heightforfooter + $this->heightforfreetext + 20))) {
                            // not enough space, need to add page
                            $pdf->AddPage('', '', true);
                            $pagenb++;
                            $pageposafternote++;
                            $pdf->setPage($pageposafternote);
                            if (!empty($tplidx)) {
                                $pdf->useTemplate($tplidx);
                            }
                            if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
                                $this->_pagehead($pdf, $object, 0, $outputlangs, $outputlangsbis, $pagenb);
                            }

                            $posyafter = $this->tab_top_newpage;
                        }
                    }

                    $tab_height = $tab_height - $height_note;
                    $this->tab_top = $posyafter + 6;
                } else {
                    $height_note = 0;
                }

                // Use new auto column system
                $this->prepareArrayColumnField($object, $outputlangs, $hidedetails, $hidedesc, $hideref);

                // Table simulation to know the height of the title line (this set this->tableTitleHeight)
                $pdf->startTransaction();
                $this->pdfTabTitles($pdf, $this->tab_top, $tab_height, $outputlangs, $hidetop);
                $pdf->rollbackTransaction(true);

                $nexY = $this->tab_top + $this->tabTitleHeight;

                usort($object->lines, function ($a, $b) {
                    return strcmp($a->category, $b->category);
                });

                $testObj = $object->lines;
                $modifiedArray = array();
                $currentCategory = null;

                foreach ($object->lines as $item) {
                    if ($item->category !== $currentCategory) {
                        $modifiedArray[] = ["desc" => '<b>' . $item->category . '</b>', "subprice" => null, "unit" => null, "qty" => null];
                    }
                    $modifiedArray[] = $item;
                    $currentCategory = $item->category;
                }

                $object->lines = $modifiedArray;
                $nblines = count($modifiedArray);

                // Loop on each lines
                $pageposbeforeprintlines = $pdf->getPage();
                $pagenb = $pageposbeforeprintlines;
                for ($i = 0; $i < $nblines; $i++) {
                    $line = $object->lines[$i];
                    $curY = $nexY;
                    $pdf->SetFont('', '', $default_font_size - 1); // Into loop to work with multipage
                    $pdf->SetTextColor(0, 0, 0);
                    // Define size of image if we need it
                    $imglinesize = array();
                    if (!empty($realpatharray[$i])) {
                        $imglinesize = pdf_getSizeForImage($realpatharray[$i]);
                    }

                    $pdf->setTopMargin($this->tab_top_newpage);
                    $page_bottom_margin = $this->heightforfooter + $this->heightforfreetext + $this->heightforinfotot + $this->getHeightForQRInvoice($pdf->getPage(), $object, $langs);
                    $pdf->setPageOrientation('', 1, $page_bottom_margin);
                    $pageposbefore = $pdf->getPage();

                    $showpricebeforepagebreak = 1;
                    $posYAfterImage = 0;
                    $posYAfterDescription = 0;

                    if ($this->getColumnStatus('photo')) {
                        // We start with Photo of product line
                        if (isset($imglinesize['width']) && isset($imglinesize['height']) && ($curY + $imglinesize['height']) > ($this->page_hauteur - $page_bottom_margin)) { // If photo too high, we moved completely on new page
                            $pdf->AddPage('', '', true);
                            if (!empty($tplidx)) {
                                $pdf->useTemplate($tplidx);
                            }
                            $pdf->setPage($pageposbefore + 1);

                            $curY = $this->tab_top_newpage;

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
                    // if ($this->getColumnStatus('desc')) {
                    //     $pdf->startTransaction();

                    //     $this->printColDescContent($pdf, $curY, 'desc', $object, $i, $outputlangs, $hideref, $hidedesc);
                    //     $pageposafter = $pdf->getPage();

                    //     if ($pageposafter > $pageposbefore) { // There is a pagebreak
                    //         $pdf->rollbackTransaction(true);
                    //         $pageposafter = $pageposbefore;
                    //         $pdf->setPageOrientation('', 1, $this->heightforfooter); // The only function to edit the bottom margin of current page to set it.

                    //         $this->printColDescContent($pdf, $curY, 'desc', $object, $i, $outputlangs, $hideref, $hidedesc);

                    //         $pageposafter = $pdf->getPage();
                    //         $posyafter = $pdf->GetY();
                    //         //var_dump($posyafter); var_dump(($this->page_hauteur - ($this->heightforfooter+$this->heightforfreetext+$this->heightforinfotot))); exit;
                    //         if ($posyafter > ($this->page_hauteur - $page_bottom_margin)) { // There is no space left for total+free text
                    //             if ($i == ($nblines - 1)) { // No more lines, and no space left to show total, so we create a new page
                    //                 $object->isLinesAvailable = 1;
                    //                 $pdf->AddPage('', '', true);
                    //                 if (!empty($tplidx)) {
                    //                     $pdf->useTemplate($tplidx);
                    //                 }
                    //                 $pdf->setPage($pageposafter + 1);
                    //             }
                    //         } else {
                    //             // We found a page break
                    //             // Allows data in the first page if description is long enough to break in multiples pages
                    //             if (!empty($conf->global->MAIN_PDF_DATA_ON_FIRST_PAGE)) {
                    //                 $showpricebeforepagebreak = 1;
                    //             } else {
                    //                 $showpricebeforepagebreak = 0;
                    //             }
                    //         }
                    //     } else // No pagebreak
                    //     {
                    //         $pdf->commitTransaction();
                    //     }
                    //     $posYAfterDescription = $pdf->GetY();
                    // }

                    if (isset($line->desc)) {
                        $this->printStdColumnContent($pdf, $curY, 'desc', $line->desc);
                    } else {
                        $this->printStdColumnContent($pdf, $curY, 'desc', $line['desc']);
                    }
                    $nexY = max($pdf->GetY(), $nexY);

                    $nexY = max($pdf->GetY(), $posYAfterImage, $posYAfterDescription);

                    $pageposafter = $pdf->getPage();
                    $pdf->setPage($pageposbefore);
                    $pdf->setTopMargin($this->marge_haute);
                    $pdf->setPageOrientation('', 1, 0); // The only function to edit the bottom margin of current page to set it.

                    // We suppose that a too long description or photo were moved completely on next page
                    if ($pageposafter > $pageposbefore && empty($showpricebeforepagebreak)) {
                        $pdf->setPage($pageposafter);
                        $curY = $this->tab_top_newpage;
                    }

                    $pdf->SetFont('', '', $default_font_size - 1); // We reposition the default font

                    // VAT Rate value
                    // if ($this->getColumnStatus('vat')) {
                    //     $vat_rate = pdf_getlinevatrate($object, $i, $outputlangs, $hidedetails);
                    //     $this->printStdColumnContent($pdf, $curY, 'vat', $vat_rate);
                    //     $nexY = max($pdf->GetY(), $nexY);
                    // }

                    // Unit price before discount
                    // if ($this->getColumnStatus('subprice')) {
                    //     $up_excl_tax = pdf_getlineupexcltax($object, $i, $outputlangs, $hidedetails);
                    //     $this->printStdColumnContent($pdf, $curY, 'subprice', $up_excl_tax);
                    //     $nexY = max($pdf->GetY(), $nexY);
                    // }

                    if (isset($line->subprice)) {
                        $this->printStdColumnContent($pdf, $curY, 'subprice', number_format($line->subprice, 2));
                    }

                    $nexY = max($pdf->GetY(), $nexY);

                    // Quantity
                    // Enough for 6 chars
                    // if ($this->getColumnStatus('qty')) {
                    //     $qty = pdf_getlineqty($object, $i, $outputlangs, $hidedetails);
                    //     $this->printStdColumnContent($pdf, $curY, 'qty', $qty);
                    //     $nexY = max($pdf->GetY(), $nexY);
                    // }

                    if (isset($line->qty)) {
                        $this->printStdColumnContent($pdf, $curY, 'qty', $line->qty);
                    } else {
                        $this->printStdColumnContent($pdf, $curY, 'qty', $line['qty']);
                    }
                    $nexY = max($pdf->GetY(), $nexY);

                    // Situation progress
                    if ($this->getColumnStatus('progress')) {
                        $progress = pdf_getlineprogress($object, $i, $outputlangs, $hidedetails);
                        $this->printStdColumnContent($pdf, $curY, 'progress', $progress);
                        $nexY = max($pdf->GetY(), $nexY);
                    }
                    // Unit
                    // if ($this->getColumnStatus('unit')) {
                    //     $unit = pdf_getlineunit($object, $i, $outputlangs, $hidedetails, $hookmanager);
                    //     $this->printStdColumnContent($pdf, $curY, 'unit', $unit);
                    //     $nexY = max($pdf->GetY(), $nexY);
                    // }

                    if (isset($line->unit)) {
                        $this->printStdColumnContent($pdf, $curY, 'unit', $line->unit);
                    }
                    $nexY = max($pdf->GetY(), $nexY);

                    // Discount on line
                    if ($this->getColumnStatus('discount') && $object->lines[$i]->remise_percent) {
                        $remise_percent = pdf_getlineremisepercent($object, $i, $outputlangs, $hidedetails);
                        $this->printStdColumnContent($pdf, $curY, 'discount', $remise_percent);
                        $nexY = max($pdf->GetY(), $nexY);
                    }

                    // Total excl tax line (HT)
                    if ($this->getColumnStatus('totalexcltax')) {
                        $total_excl_tax = pdf_getlinetotalexcltax($object, $i, $outputlangs, $hidedetails);
                        if ($total_excl_tax !== "0.00") {
                            $this->printStdColumnContent($pdf, $curY, 'totalexcltax', $total_excl_tax);
                        }
                        $nexY = max($pdf->GetY(), $nexY);
                    }

                    // Total with tax line (TTC)
                    if ($this->getColumnStatus('totalincltax')) {
                        $total_incl_tax = pdf_getlinetotalwithtax($object, $i, $outputlangs, $hidedetails);
                        $this->printStdColumnContent($pdf, $curY, 'totalincltax', $total_incl_tax);
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
                    $sign = 1;
                    if (isset($object->type) && $object->type == 2 && !empty($conf->global->INVOICE_POSITIVE_CREDIT_NOTE)) {
                        $sign = -1;
                    }
                    // Collecte des totaux par valeur de tva dans $this->tva["taux"]=total_tva
                    // $prev_progress = $testObj[$i]->get_prev_progress($object->id);
                    // if ($prev_progress > 0 && !empty($object->lines[$i]->situation_percent)) { // Compute progress from previous situation
                    //     if (isModEnabled("multicurrency") && $object->multicurrency_tx != 1) {
                    //         $tvaligne = $sign * $object->lines[$i]->multicurrency_total_tva * ($object->lines[$i]->situation_percent - $prev_progress) / $object->lines[$i]->situation_percent;
                    //     } else {
                    //         $tvaligne = $sign * $object->lines[$i]->total_tva * ($object->lines[$i]->situation_percent - $prev_progress) / $object->lines[$i]->situation_percent;
                    //     }
                    // } else {
                    //     if (isModEnabled("multicurrency") && $object->multicurrency_tx != 1) {
                    //         $tvaligne = $sign * $object->lines[$i]->multicurrency_total_tva;
                    //     } else {
                    //         $tvaligne = $sign * $object->lines[$i]->total_tva;
                    //     }
                    // }

                    if (isModEnabled("multicurrency") && $object->multicurrency_tx != 1) {
                        $tvaligne = $sign * $object->lines[$i]->multicurrency_total_tva;
                    } else {
                        $tvaligne = $sign * $object->lines[$i]->total_tva;
                    }

                    $localtax1ligne = $object->lines[$i]->total_localtax1;
                    $localtax2ligne = $object->lines[$i]->total_localtax2;
                    $localtax1_rate = $object->lines[$i]->localtax1_tx;
                    $localtax2_rate = $object->lines[$i]->localtax2_tx;
                    $localtax1_type = $object->lines[$i]->localtax1_type;
                    $localtax2_type = $object->lines[$i]->localtax2_type;

                    // TODO remise_percent is an obsolete field for object parent
                    /*if ($object->remise_percent) {
                    $tvaligne -= ($tvaligne * $object->remise_percent) / 100;
                    }
                    if ($object->remise_percent) {
                    $localtax1ligne -= ($localtax1ligne * $object->remise_percent) / 100;
                    }
                    if ($object->remise_percent) {
                    $localtax2ligne -= ($localtax2ligne * $object->remise_percent) / 100;
                    }*/

                    $vatrate = (string) $object->lines[$i]->tva_tx;

                    // Retrieve type from database for backward compatibility with old records
                    if ((!isset($localtax1_type) || $localtax1_type == '' || !isset($localtax2_type) || $localtax2_type == '') // if tax type not defined
                        && (!empty($localtax1_rate) || !empty($localtax2_rate))
                    ) { // and there is local tax
                        $localtaxtmp_array = getLocalTaxesFromRate($vatrate, 0, $object->thirdparty, $mysoc);
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
                    $this->tva[$vatrate] += $tvaligne; // ->tva is abandonned, we use now ->tva_array that is more complete
                    $vatcode = $object->lines[$i]->vat_src_code;
                    if (empty($this->tva_array[$vatrate . ($vatcode ? ' (' . $vatcode . ')' : '')]['amount'])) {
                        $this->tva_array[$vatrate . ($vatcode ? ' (' . $vatcode . ')' : '')]['amount'] = 0;
                    }
                    $this->tva_array[$vatrate . ($vatcode ? ' (' . $vatcode . ')' : '')] = array('vatrate' => $vatrate, 'vatcode' => $vatcode, 'amount' => $this->tva_array[$vatrate . ($vatcode ? ' (' . $vatcode . ')' : '')]['amount'] + $tvaligne);

                    $nexY = max($nexY, $posYAfterImage);

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
                        $heightforqrinvoice = $this->getHeightForQRInvoice($pagenb, $object, $langs);
                        if ($pagenb == $pageposbeforeprintlines) {
                            $this->_tableau($pdf, $this->tab_top, $this->page_hauteur - $this->tab_top - $this->heightforfooter - $heightforqrinvoice, 0, $outputlangs, $hidetop, 1, $object->multicurrency_code, $outputlangsbis);
                        } else {
                            $this->_tableau($pdf, $this->tab_top_newpage, $this->page_hauteur - $this->tab_top_newpage - $this->heightforfooter - $heightforqrinvoice, 0, $outputlangs, 1, 1, $object->multicurrency_code, $outputlangsbis);
                        }
                        $this->_pagefoot($pdf, $object, $outputlangs, 1, $this->getHeightForQRInvoice($pdf->getPage(), $object, $outputlangs));
                        $pagenb++;
                        $pdf->setPage($pagenb);
                        $pdf->setPageOrientation('', 1, 0); // The only function to edit the bottom margin of current page to set it.
                        if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
                            $this->_pagehead($pdf, $object, 0, $outputlangs, $outputlangsbis, $pagenb);
                        }
                        if (!empty($tplidx)) {
                            $pdf->useTemplate($tplidx);
                        }
                    }

                    if (isset($object->lines[$i + 1]->pagebreak) && $object->lines[$i + 1]->pagebreak) {
                        $heightforqrinvoice = $this->getHeightForQRInvoice($pagenb, $object, $langs);
                        if ($pagenb == $pageposafter) {
                            $this->_tableau($pdf, $this->tab_top, $this->page_hauteur - $this->tab_top - $this->heightforfooter - $heightforqrinvoice, 0, $outputlangs, $hidetop, 1, $object->multicurrency_code, $outputlangsbis);
                        } else {
                            $this->_tableau($pdf, $this->tab_top_newpage, $this->page_hauteur - $this->tab_top_newpage - $this->heightforfooter - $heightforqrinvoice, 0, $outputlangs, 1, 1, $object->multicurrency_code, $outputlangsbis);
                        }
                        $this->_pagefoot($pdf, $object, $outputlangs, 1, $this->getHeightForQRInvoice($pdf->getPage(), $object, $outputlangs));
                        // New page
                        $pdf->AddPage();
                        if (!empty($tplidx)) {
                            $pdf->useTemplate($tplidx);
                        }
                        $pagenb++;
                        if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
                            $this->_pagehead($pdf, $object, 0, $outputlangs, $outputlangsbis, $pagenb);
                        }
                    }
                }

                // Show square
                $heightforqrinvoice = $this->getHeightForQRInvoice($pagenb, $object, $langs);
                if ($pagenb == $pageposbeforeprintlines) {
                    $this->_tableau($pdf, $this->tab_top, $this->page_hauteur - $this->tab_top - $this->heightforinfotot - $this->heightforfreetext - $this->heightforfooter - $heightforqrinvoice, 0, $outputlangs, $hidetop, 0, $object->multicurrency_code, $outputlangsbis);
                    $bottomlasttab = $this->page_hauteur - $this->heightforinfotot - $this->heightforfreetext - $this->heightforfooter - $heightforqrinvoice + 1;
                } else {
                    if ($object->isLinesAvailable !== 1) {
                        $this->_tableau($pdf, $this->tab_top_newpage, $this->page_hauteur - $this->tab_top_newpage - $this->heightforinfotot - $this->heightforfreetext - $this->heightforfooter - $heightforqrinvoice, 0, $outputlangs, 1, 0, $object->multicurrency_code, $outputlangsbis);
                        $bottomlasttab = $this->page_hauteur - $this->heightforinfotot - $this->heightforfreetext - $this->heightforfooter - $heightforqrinvoice + 1;
                    } else {
                        $bottomlasttab = 50;
                    }
                }

                // Display infos area
                $posy = $this->drawInfoTable($pdf, $object, $bottomlasttab, $outputlangs, $outputlangsbis);

                // Display total zone
                $posy = $this->drawTotalTable($pdf, $object, $deja_regle, $bottomlasttab, $outputlangs, $outputlangsbis);

                // to display bank details
                $posy = $this->bankDetails($pdf, $posy, $outputlangs, $object, $outputlangsbis);

                // Display payment area
                if (($deja_regle || $amount_credit_notes_included || $amount_deposits_included) && empty($conf->global->INVOICE_NO_PAYMENT_DETAILS)) {
                    $posy = $this->drawPaymentsTable($pdf, $object, $posy, $outputlangs);
                }

                // Pagefoot
                $this->_pagefoot($pdf, $object, $outputlangs, 0, $this->getHeightForQRInvoice($pdf->getPage(), $object, $langs));
                if (method_exists($pdf, 'AliasNbPages')) {
                    $pdf->AliasNbPages();
                }

                if (getDolGlobalString('INVOICE_ADD_SWISS_QR_CODE') == 'bottom') {
                    $this->addBottomQRInvoice($pdf, $object, $outputlangs);
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
            $this->error = $langs->transnoentities("ErrorConstantNotDefined", "FAC_OUTPUTDIR");
            return 0;
        }
    }

    /**
     *  Show payments table
     *
     *  @param    TCPDF        $pdf            Object PDF
     *  @param  Facture        $object         Object invoice
     *  @param  int            $posy           Position y in PDF
     *  @param  Translate    $outputlangs    Object langs for output
     *  @return int                         <0 if KO, >0 if OK
     */
    public function drawPaymentsTable(&$pdf, $object, $posy, $outputlangs)
    {
        global $conf;

        $sign = 1;
        if ($object->type == 2 && !empty($conf->global->INVOICE_POSITIVE_CREDIT_NOTE)) {
            $sign = -1;
        }

        $tab3_posx = 120;
        $tab3_top = $posy + 8;
        $tab3_width = 80;
        $tab3_height = 4;
        if ($this->page_largeur < 210) { // To work with US executive format
            $tab3_posx -= 15;
        }

        $default_font_size = pdf_getPDFFontSize($outputlangs);

        $title = $outputlangs->transnoentities("PaymentsAlreadyDone");
        if ($object->type == 2) {
            $title = $outputlangs->transnoentities("PaymentsBackAlreadyDone");
        }

        $pdf->SetFont('', '', $default_font_size - 3);
        $pdf->SetXY($tab3_posx, $tab3_top - 4);
        $pdf->MultiCell(60, 3, $title, 0, 'L', 0);

        $pdf->line($tab3_posx, $tab3_top, $tab3_posx + $tab3_width, $tab3_top);

        $pdf->SetFont('', '', $default_font_size - 4);
        $pdf->SetXY($tab3_posx, $tab3_top);
        $pdf->MultiCell(20, 3, $outputlangs->transnoentities("Payment"), 0, 'L', 0);
        $pdf->SetXY($tab3_posx + 21, $tab3_top);
        $pdf->MultiCell(20, 3, $outputlangs->transnoentities("Amount"), 0, 'L', 0);
        $pdf->SetXY($tab3_posx + 40, $tab3_top);
        $pdf->MultiCell(20, 3, $outputlangs->transnoentities("Type"), 0, 'L', 0);
        $pdf->SetXY($tab3_posx + 58, $tab3_top);
        $pdf->MultiCell(20, 3, $outputlangs->transnoentities("Num"), 0, 'L', 0);

        $pdf->line($tab3_posx, $tab3_top - 1 + $tab3_height, $tab3_posx + $tab3_width, $tab3_top - 1 + $tab3_height);

        $y = 0;

        $pdf->SetFont('', '', $default_font_size - 4);

        // Loop on each discount available (deposits and credit notes and excess of payment included)
        $sql = "SELECT re.rowid, re.amount_ht, re.multicurrency_amount_ht, re.amount_tva, re.multicurrency_amount_tva,  re.amount_ttc, re.multicurrency_amount_ttc,";
        $sql .= " re.description, re.fk_facture_source,";
        $sql .= " f.type, f.datef";
        $sql .= " FROM " . MAIN_DB_PREFIX . "societe_remise_except as re, " . MAIN_DB_PREFIX . "facture as f";
        $sql .= " WHERE re.fk_facture_source = f.rowid AND re.fk_facture = " . ((int) $object->id);
        $resql = $this->db->query($sql);
        if ($resql) {
            $num = $this->db->num_rows($resql);
            $i = 0;
            $invoice = new Facture($this->db);
            while ($i < $num) {
                $y += 3;
                $obj = $this->db->fetch_object($resql);

                if ($obj->type == 2) {
                    $text = $outputlangs->transnoentities("CreditNote");
                } elseif ($obj->type == 3) {
                    $text = $outputlangs->transnoentities("Deposit");
                } elseif ($obj->type == 0) {
                    $text = $outputlangs->transnoentities("ExcessReceived");
                } else {
                    $text = $outputlangs->transnoentities("UnknownType");
                }

                $invoice->fetch($obj->fk_facture_source);

                $pdf->SetXY($tab3_posx, $tab3_top + $y);
                $pdf->MultiCell(20, 3, dol_print_date($this->db->jdate($obj->datef), 'day', false, $outputlangs, true), 0, 'L', 0);
                $pdf->SetXY($tab3_posx + 21, $tab3_top + $y);
                $pdf->MultiCell(20, 3, price((isModEnabled("multicurrency") && $object->multicurrency_tx != 1) ? $obj->multicurrency_amount_ttc : $obj->amount_ttc, 0, $outputlangs), 0, 'L', 0);
                $pdf->SetXY($tab3_posx + 40, $tab3_top + $y);
                $pdf->MultiCell(20, 3, $text, 0, 'L', 0);
                $pdf->SetXY($tab3_posx + 58, $tab3_top + $y);
                $pdf->MultiCell(20, 3, $invoice->ref, 0, 'L', 0);

                $pdf->line($tab3_posx, $tab3_top + $y + 3, $tab3_posx + $tab3_width, $tab3_top + $y + 3);

                $i++;
            }
        } else {
            $this->error = $this->db->lasterror();
            return -1;
        }

        // Loop on each payment
        // TODO Call getListOfPaymentsgetListOfPayments instead of hard coded sql
        $sql = "SELECT p.datep as date, p.fk_paiement, p.num_paiement as num, pf.amount as amount, pf.multicurrency_amount,";
        $sql .= " cp.code";
        $sql .= " FROM " . MAIN_DB_PREFIX . "paiement_facture as pf, " . MAIN_DB_PREFIX . "paiement as p";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "c_paiement as cp ON p.fk_paiement = cp.id";
        $sql .= " WHERE pf.fk_paiement = p.rowid AND pf.fk_facture = " . ((int) $object->id);
        //$sql.= " WHERE pf.fk_paiement = p.rowid AND pf.fk_facture = 1";
        $sql .= " ORDER BY p.datep";

        $resql = $this->db->query($sql);
        if ($resql) {
            $num = $this->db->num_rows($resql);
            $i = 0;
            while ($i < $num) {
                $y += 3;
                $row = $this->db->fetch_object($resql);

                $pdf->SetXY($tab3_posx, $tab3_top + $y);
                $pdf->MultiCell(20, 3, dol_print_date($this->db->jdate($row->date), 'day', false, $outputlangs, true), 0, 'L', 0);
                $pdf->SetXY($tab3_posx + 21, $tab3_top + $y);
                $pdf->MultiCell(20, 3, price($sign * ((isModEnabled("multicurrency") && $object->multicurrency_tx != 1) ? $row->multicurrency_amount : $row->amount), 0, $outputlangs), 0, 'L', 0);
                $pdf->SetXY($tab3_posx + 40, $tab3_top + $y);
                $oper = $outputlangs->transnoentitiesnoconv("PaymentTypeShort" . $row->code);

                $pdf->MultiCell(20, 3, $oper, 0, 'L', 0);
                $pdf->SetXY($tab3_posx + 58, $tab3_top + $y);
                $pdf->MultiCell(30, 3, $row->num, 0, 'L', 0);

                $pdf->line($tab3_posx, $tab3_top + $y + 3, $tab3_posx + $tab3_width, $tab3_top + $y + 3);

                $i++;
            }

            return $tab3_top + $y + 3;
        } else {
            $this->error = $this->db->lasterror();
            return -1;
        }
    }

    /**
     *   Show miscellaneous information (payment mode, payment term, ...)
     *
     *   @param        TCPDF        $pdf             Object PDF
     *   @param        Facture        $object            Object to show
     *   @param        int            $posy            Y
     *   @param        Translate    $outputlangs    Langs object
     *   @param      Translate    $outputlangsbis    Object lang for output bis
     *   @return    int                            Pos y
     */
    protected function drawInfoTable(&$pdf, $object, $posy, $outputlangs, $outputlangsbis)
    {
        global $conf, $mysoc;

        $default_font_size = pdf_getPDFFontSize($outputlangs);

        $pdf->SetFont('', '', $default_font_size - 1);

        // If France, show VAT mention if not applicable
        if ($this->emetteur->country_code == 'FR' && empty($mysoc->tva_assuj)) {
            $pdf->SetFont('', 'B', $default_font_size - 2);
            $pdf->SetXY($this->marge_gauche, $posy);
            if ($mysoc->forme_juridique_code == 92) {
                $pdf->MultiCell(100, 3, $outputlangs->transnoentities("VATIsNotUsedForInvoiceAsso"), 0, 'L', 0);
            } else {
                $pdf->MultiCell(100, 3, $outputlangs->transnoentities("VATIsNotUsedForInvoice"), 0, 'L', 0);
            }

            $posy = $pdf->GetY() + 4;
        }

        $posxval = 52; // Position of values of properties shown on left side
        $posxend = 110; // End of x for text on left side
        if ($this->page_largeur < 210) { // To work with US executive format
            $posxend -= 10;
        }

        // Show payments conditions
        if ($object->type != 2 && ($object->cond_reglement_code || $object->cond_reglement)) {
            $pdf->SetFont('', 'B', $default_font_size - 2);
            $pdf->SetXY($this->marge_gauche, $posy);
            $titre = $outputlangs->transnoentities("PaymentConditions") . ':';
            $pdf->MultiCell($posxval - $this->marge_gauche, 4, $titre, 0, 'L');

            $pdf->SetFont('', '', $default_font_size - 2);
            $pdf->SetXY($posxval, $posy);
            $lib_condition_paiement = $outputlangs->transnoentities("PaymentCondition" . $object->cond_reglement_code) != ('PaymentCondition' . $object->cond_reglement_code) ? $outputlangs->transnoentities("PaymentCondition" . $object->cond_reglement_code) : $outputlangs->convToOutputCharset($object->cond_reglement_doc ? $object->cond_reglement_doc : $object->cond_reglement_label);
            $lib_condition_paiement = str_replace('\n', "\n", $lib_condition_paiement);
            $pdf->MultiCell($posxend - $posxval, 4, $lib_condition_paiement, 0, 'L');

            $posy = $pdf->GetY() + 3; // We need spaces for 2 lines payment conditions
        }

        // Show category of operations
        if (getDolGlobalInt('INVOICE_CATEGORY_OF_OPERATION') == 2 && $this->categoryOfOperation >= 0) {
            $pdf->SetFont('', 'B', $default_font_size - 2);
            $pdf->SetXY($this->marge_gauche, $posy);
            $categoryOfOperationTitle = $outputlangs->transnoentities("MentionCategoryOfOperations") . ' : ';
            $pdf->MultiCell($posxval - $this->marge_gauche, 4, $categoryOfOperationTitle, 0, 'L');

            $pdf->SetFont('', '', $default_font_size - 2);
            $pdf->SetXY($posxval, $posy);
            $categoryOfOperationLabel = $outputlangs->transnoentities("MentionCategoryOfOperations" . $this->categoryOfOperation);
            $pdf->MultiCell($posxend - $posxval, 4, $categoryOfOperationLabel, 0, 'L');

            $posy = $pdf->GetY() + 3; // for 2 lines
        }

        if ($object->type != 2) {
            // Check a payment mode is defined
            if (
                empty($object->mode_reglement_code)
                && !getDolGlobalInt('FACTURE_CHQ_NUMBER')
                && !getDolGlobalInt('FACTURE_RIB_NUMBER')
            ) {
                $this->error = $outputlangs->transnoentities("ErrorNoPaiementModeConfigured");
            } elseif (($object->mode_reglement_code == 'CHQ' && !getDolGlobalInt('FACTURE_CHQ_NUMBER') && empty($object->fk_account) && empty($object->fk_bank))
                || ($object->mode_reglement_code == 'VIR' && !getDolGlobalInt('FACTURE_RIB_NUMBER') && empty($object->fk_account) && empty($object->fk_bank))
            ) {
                // Avoid having any valid PDF with setup that is not complete
                $outputlangs->load("errors");

                $pdf->SetXY($this->marge_gauche, $posy);
                $pdf->SetTextColor(200, 0, 0);
                $pdf->SetFont('', 'B', $default_font_size - 2);
                $this->error = $outputlangs->transnoentities("ErrorPaymentModeDefinedToWithoutSetup", $object->mode_reglement_code);
                $pdf->MultiCell($posxend - $this->marge_gauche, 3, $this->error, 0, 'L', 0);
                $pdf->SetTextColor(0, 0, 0);

                $posy = $pdf->GetY() + 1;
            }

            // Show payment mode
            if (
                !empty($object->mode_reglement_code)
                && $object->mode_reglement_code != 'CHQ'
                && $object->mode_reglement_code != 'VIR'
            ) {
                $pdf->SetFont('', 'B', $default_font_size - 2);
                $pdf->SetXY($this->marge_gauche, $posy);
                $titre = $outputlangs->transnoentities("PaymentMode") . ':';
                $pdf->MultiCell($posxend - $this->marge_gauche, 5, $titre, 0, 'L');

                $pdf->SetFont('', '', $default_font_size - 2);
                $pdf->SetXY($posxval, $posy);
                $lib_mode_reg = $outputlangs->transnoentities("PaymentType" . $object->mode_reglement_code) != ('PaymentType' . $object->mode_reglement_code) ? $outputlangs->transnoentities("PaymentType" . $object->mode_reglement_code) : $outputlangs->convToOutputCharset($object->mode_reglement);

                //#21654: add account number used for the debit
                if ($object->mode_reglement_code == "PRE") {
                    require_once DOL_DOCUMENT_ROOT . '/societe/class/companybankaccount.class.php';
                    $bac = new CompanyBankAccount($this->db);
                    $bac->fetch(0, $object->thirdparty->id);
                    $iban = $bac->iban . (($bac->iban && $bac->bic) ? ' / ' : '') . $bac->bic;
                    $lib_mode_reg .= ' ' . $outputlangs->trans("PaymentTypePREdetails", dol_trunc($iban, 6, 'right', 'UTF-8', 1));
                }

                $pdf->MultiCell($posxend - $posxval, 5, $lib_mode_reg, 0, 'L');

                $posy = $pdf->GetY();
            }

            // Show if Option VAT debit option is on also if transmitter is french
            // Decret n°2099-1299 2022-10-07
            // French mention : "Option pour le paiement de la taxe d'après les débits"
            if ($this->emetteur->country_code == 'FR') {
                if (isset($conf->global->TAX_MODE) && $conf->global->TAX_MODE == 1) {
                    $pdf->SetXY($this->marge_gauche, $posy);
                    $pdf->writeHTMLCell(80, 5, '', '', $outputlangs->transnoentities("MentionVATDebitOptionIsOn"), 0, 1);

                    $posy = $pdf->GetY() + 1;
                }
            }

            // Show online payment link
            if (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'CB' || $object->mode_reglement_code == 'VAD') {
                $useonlinepayment = 0;
                if (!empty($conf->global->PDF_SHOW_LINK_TO_ONLINE_PAYMENT)) {
                    if (isModEnabled('paypal')) {
                        $useonlinepayment++;
                    }
                    if (isModEnabled('stripe')) {
                        $useonlinepayment++;
                    }
                    if (isModEnabled('paybox')) {
                        $useonlinepayment++;
                    }
                }

                if ($object->statut != Facture::STATUS_DRAFT && $useonlinepayment) {
                    require_once DOL_DOCUMENT_ROOT . '/core/lib/payments.lib.php';
                    global $langs;

                    $langs->loadLangs(array('payment', 'paybox', 'stripe'));
                    $servicename = $langs->transnoentities('Online');
                    $paiement_url = getOnlinePaymentUrl('', 'invoice', $object->ref, '', '', '');
                    $linktopay = $langs->trans("ToOfferALinkForOnlinePayment", $servicename) . ' <a href="' . $paiement_url . '">' . $outputlangs->transnoentities("ClickHere") . '</a>';

                    $pdf->SetXY($this->marge_gauche, $posy);
                    $pdf->writeHTMLCell($posxend - $this->marge_gauche, 5, '', '', dol_htmlentitiesbr($linktopay), 0, 1);

                    $posy = $pdf->GetY() + 1;
                }
            }

            // Show payment mode CHQ
            if (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'CHQ') {
                // If payment mode unregulated or payment mode forced to CHQ
                if (getDolGlobalInt('FACTURE_CHQ_NUMBER')) {
                    $diffsizetitle = (empty($conf->global->PDF_DIFFSIZE_TITLE) ? 3 : $conf->global->PDF_DIFFSIZE_TITLE);

                    if ($conf->global->FACTURE_CHQ_NUMBER > 0) {
                        $account = new Account($this->db);
                        $account->fetch(getDolGlobalInt('FACTURE_CHQ_NUMBER'));

                        $pdf->SetXY($this->marge_gauche, $posy);
                        $pdf->SetFont('', 'B', $default_font_size - $diffsizetitle);
                        $pdf->MultiCell($posxend - $this->marge_gauche, 3, $outputlangs->transnoentities('PaymentByChequeOrderedTo', $account->proprio), 0, 'L', 0);
                        $posy = $pdf->GetY() + 1;

                        if (empty($conf->global->MAIN_PDF_HIDE_CHQ_ADDRESS)) {
                            $pdf->SetXY($this->marge_gauche, $posy);
                            $pdf->SetFont('', '', $default_font_size - $diffsizetitle);
                            $pdf->MultiCell($posxend - $this->marge_gauche, 3, $outputlangs->convToOutputCharset($account->owner_address), 0, 'L', 0);
                            $posy = $pdf->GetY() + 2;
                        }
                    }
                    if ($conf->global->FACTURE_CHQ_NUMBER == -1) {
                        $pdf->SetXY($this->marge_gauche, $posy);
                        $pdf->SetFont('', 'B', $default_font_size - $diffsizetitle);
                        $pdf->MultiCell($posxend - $this->marge_gauche, 3, $outputlangs->transnoentities('PaymentByChequeOrderedTo', $this->emetteur->name), 0, 'L', 0);
                        $posy = $pdf->GetY() + 1;

                        if (empty($conf->global->MAIN_PDF_HIDE_CHQ_ADDRESS)) {
                            $pdf->SetXY($this->marge_gauche, $posy);
                            $pdf->SetFont('', '', $default_font_size - $diffsizetitle);
                            $pdf->MultiCell($posxend - $this->marge_gauche, 3, $outputlangs->convToOutputCharset($this->emetteur->getFullAddress()), 0, 'L', 0);
                            $posy = $pdf->GetY() + 2;
                        }
                    }
                }
            }

            // If payment mode not forced or forced to VIR, show payment with BAN
            if (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'VIR') {
                if ($object->fk_account > 0 || $object->fk_bank > 0 || getDolGlobalInt('FACTURE_RIB_NUMBER')) {
                    $bankid = ($object->fk_account <= 0 ? $conf->global->FACTURE_RIB_NUMBER : $object->fk_account);
                    if ($object->fk_bank > 0) {
                        $bankid = $object->fk_bank; // For backward compatibility when object->fk_account is forced with object->fk_bank
                    }
                    $account = new Account($this->db);
                    $account->fetch($bankid);

                    $curx = $this->marge_gauche;
                    $cury = $posy;

                    $posy = pdf_bank($pdf, $outputlangs, $curx, $cury, $account, 0, $default_font_size);

                    $posy += 2;
                }
            }
        }

        return $posy;
    }

    /**
     *  Show total to pay
     *
     *  @param    TCPDF        $pdf            Object PDF
     *    @param  Facture        $object         Object invoice
     *    @param  int            $deja_regle     Amount already paid (in the currency of invoice)
     *    @param    int            $posy            Position depart
     *    @param    Translate    $outputlangs    Objet langs
     *  @param  Translate    $outputlangsbis    Object lang for output bis
     *    @return int                            Position pour suite
     */
    // to show the total below component table
    protected function drawTotalTable(&$pdf, $object, $deja_regle, $posy, $outputlangs, $outputlangsbis)
    {
        global $conf, $mysoc, $hookmanager;

        $sign = 1;
        if ($object->type == 2 && !empty($conf->global->INVOICE_POSITIVE_CREDIT_NOTE)) {
            $sign = -1;
        }

        $default_font_size = pdf_getPDFFontSize($outputlangs);

        $tab2_top = $posy;
        $tab2_hl = 4;
        if (is_object($outputlangsbis)) { // When we show 2 languages we need more room for text, so we use a smaller font.
            $pdf->SetFont('', '', $default_font_size - 2);
        } else {
            $pdf->SetFont('', '', $default_font_size - 1);
        }

        // Total table
        $col1x = 120;
        $col2x = 170;
        if ($this->page_largeur < 210) { // To work with US executive format
            $col1x -= 15;
            $col2x -= 10;
        }
        $largcol2 = ($this->page_largeur - $this->marge_droite - $col2x);

        $useborder = 0;
        $index = 0;

        // Add trigger to allow to edit $object
        $parameters = array(
            'object' => &$object,
            'outputlangs' => $outputlangs,
        );
        $hookmanager->executeHooks('beforePercentCalculation', $parameters, $this); // Note that $object may have been modified by hook

        // overall percentage of advancement
        $percent = 0;
        $i = 0;
        foreach ($object->lines as $line) {
            $percent += $line->situation_percent;
            $i++;
        }

        if (!empty($i)) {
            $avancementGlobal = $percent / $i;
        } else {
            $avancementGlobal = 0;
        }

        $object->fetchPreviousNextSituationInvoice();
        $TPreviousIncoice = $object->tab_previous_situation_invoice;

        $total_a_payer = 0;
        $total_a_payer_ttc = 0;
        foreach ($TPreviousIncoice as &$fac) {
            $total_a_payer += $fac->total_ht;
            $total_a_payer_ttc += $fac->total_ttc;
        }
        $total_a_payer += $object->total_ht;
        $total_a_payer_ttc += $object->total_ttc;

        if (!empty($avancementGlobal)) {
            $total_a_payer = $total_a_payer * 100 / $avancementGlobal;
            $total_a_payer_ttc = $total_a_payer_ttc * 100 / $avancementGlobal;
        } else {
            $total_a_payer = 0;
            $total_a_payer_ttc = 0;
        }

        $i = 1;
        if (!empty($TPreviousIncoice)) {
            $pdf->setY($tab2_top);
            $posy = $pdf->GetY();

            foreach ($TPreviousIncoice as &$fac) {
                if ($posy > $this->page_hauteur - 4 - $this->heightforfooter) {
                    $this->_pagefoot($pdf, $object, $outputlangs, 1, $this->getHeightForQRInvoice($pdf->getPage(), $object, $outputlangs));
                    $pdf->addPage();
                    if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
                        $this->_pagehead($pdf, $object, 0, $outputlangs, $outputlangsbis, $pagenb);
                        $pdf->setY($this->tab_top_newpage);
                    } else {
                        $pdf->setY($this->marge_haute);
                    }
                    $posy = $pdf->GetY();
                }

                // Cumulate preceding VAT
                $index++;
                $pdf->SetFillColor(255, 255, 255);
                $pdf->SetXY($col1x, $posy);
                $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("PDFSituationTitle", $fac->situation_counter) . ' ' . $outputlangs->transnoentities("TotalHT"), 0, 'L', 1);

                $pdf->SetXY($col2x, $posy);

                $facSign = '';
                if ($i > 1) {
                    $facSign = $fac->total_ht >= 0 ? '+' : '';
                }

                $displayAmount = ' ' . $facSign . ' ' . price($fac->total_ht, 0, $outputlangs);

                $pdf->MultiCell($largcol2, $tab2_hl, $displayAmount, 0, 'R', 1);

                $i++;
                $posy += $tab2_hl;

                $pdf->setY($posy);
            }

            // Display current total
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetXY($col1x, $posy);
            $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("PDFSituationTitle", $object->situation_counter) . ' ' . $outputlangs->transnoentities("TotalHT"), 0, 'L', 1);

            $pdf->SetXY($col2x, $posy);
            $facSign = '';
            if ($i > 1) {
                $facSign = $object->total_ht >= 0 ? '+' : ''; // management of a particular customer case
            }

            if ($fac->type === Facture::TYPE_CREDIT_NOTE) {
                $facSign = '-'; // les avoirs
            }

            $displayAmount = ' ' . $facSign . ' ' . price($object->total_ht, 0, $outputlangs);
            $pdf->MultiCell($largcol2, $tab2_hl, $displayAmount, 0, 'R', 1);

            $posy += $tab2_hl;

            // Display all total
            $pdf->SetFont('', '', $default_font_size - 1);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetXY($col1x, $posy);
            $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("SituationTotalProgress", $avancementGlobal), 0, 'L', 1);

            $pdf->SetXY($col2x, $posy);
            $pdf->MultiCell($largcol2, $tab2_hl, price($total_a_payer * $avancementGlobal / 100, 0, $outputlangs), 0, 'R', 1);
            $pdf->SetFont('', '', $default_font_size - 2);

            $posy += $tab2_hl;

            if ($posy > $this->page_hauteur - 4 - $this->heightforfooter) {
                $pdf->addPage();
                if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
                    $this->_pagehead($pdf, $object, 0, $outputlangs, $outputlangsbis, );
                    $pdf->setY($this->tab_top_newpage);
                } else {
                    $pdf->setY($this->marge_haute);
                }

                $posy = $pdf->GetY();
            }

            $tab2_top = $posy;
            $index = 0;

            $tab2_top += 3;
        }

        // Get Total HT
        $total_ht = (isModEnabled("multicurrency") && $object->multicurrency_tx != 1 ? $object->multicurrency_total_ht : $object->total_ht);

        // Total remise
        $total_line_remise = 0;
        foreach ($object->lines as $i => $line) {
            $total_line_remise += pdfGetLineTotalDiscountAmount($object, $i, $outputlangs, 2); // TODO: add this method to core/lib/pdf.lib
            // Gestion remise sous forme de ligne négative
            if ($line->total_ht < 0) {
                $total_line_remise += -$line->total_ht;
            }
        }
        if ($total_line_remise > 0) {
            if (!empty($conf->global->MAIN_SHOW_AMOUNT_DISCOUNT)) {
                $pdf->SetFillColor(255, 255, 255);
                $pdf->SetXY($col1x, $tab2_top + $tab2_hl);
                $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("TotalDiscount") . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transnoentities("TotalDiscount") : ''), 0, 'L', 1);
                $pdf->SetXY($col2x, $tab2_top + $tab2_hl);
                $pdf->MultiCell($largcol2, $tab2_hl, price($total_line_remise, 0, $outputlangs), 0, 'R', 1);

                $index++;
            }
            // Show total NET before discount
            if (!empty($conf->global->MAIN_SHOW_AMOUNT_BEFORE_DISCOUNT)) {
                $pdf->SetFillColor(255, 255, 255);
                $pdf->SetXY($col1x, $tab2_top);
                $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("TotalHTBeforeDiscount") . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transnoentities("TotalHTBeforeDiscount") : ''), 0, 'L', 1);
                $pdf->SetXY($col2x, $tab2_top);
                $pdf->MultiCell($largcol2, $tab2_hl, price($total_line_remise + $total_ht, 0, $outputlangs), 0, 'R', 1);

                $index++;
            }
        }

        // Total HT
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
        $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities(empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT) ? "TotalHT" : "Total") . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transnoentities(empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT) ? "TotalHT" : "Total") : ''), 0, 'L', 1);

        $total_ht = ((isModEnabled("multicurrency") && isset($object->multicurrency_tx) && $object->multicurrency_tx != 1) ? $object->multicurrency_total_ht : $object->total_ht);
        $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
        $pdf->MultiCell($largcol2, $tab2_hl, price($sign * ($total_ht + (!empty($object->remise) ? $object->remise : 0)), 0, $outputlangs), 0, 'R', 1);

        // Show VAT by rates and total
        $pdf->SetFillColor(248, 248, 248);

        $total_ttc = (isModEnabled("multicurrency") && $object->multicurrency_tx != 1) ? $object->multicurrency_total_ttc : $object->total_ttc;

        $this->atleastoneratenotnull = 0;
        if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT)) {
            $tvaisnull = ((!empty($this->tva) && count($this->tva) == 1 && isset($this->tva['0.000']) && is_float($this->tva['0.000'])) ? true : false);
            if (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_IFNULL) && $tvaisnull) {
                // Nothing to do
            } else {
                // FIXME amount of vat not supported with multicurrency

                //Local tax 1 before VAT
                //if (!empty($conf->global->FACTURE_LOCAL_TAX1_OPTION) && $conf->global->FACTURE_LOCAL_TAX1_OPTION=='localtax1on')
                //{
                foreach ($this->localtax1 as $localtax_type => $localtax_rate) {
                    if (in_array((string) $localtax_type, array('1', '3', '5'))) {
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

                            $totalvat = $outputlangs->transcountrynoentities("TotalLT1", $mysoc->country_code) . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transcountrynoentities("TotalLT1", $mysoc->country_code) : '');
                            $totalvat .= ' ';
                            $totalvat .= vatrate(abs($tvakey), 1) . $tvacompl;
                            $pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', 1);

                            $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
                            $pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);
                        }
                    }
                }
                //}
                //Local tax 2 before VAT
                //if (!empty($conf->global->FACTURE_LOCAL_TAX2_OPTION) && $conf->global->FACTURE_LOCAL_TAX2_OPTION=='localtax2on')
                //{
                foreach ($this->localtax2 as $localtax_type => $localtax_rate) {
                    if (in_array((string) $localtax_type, array('1', '3', '5'))) {
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
                            $totalvat = $outputlangs->transcountrynoentities("TotalLT2", $mysoc->country_code) . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transcountrynoentities("TotalLT2", $mysoc->country_code) : '');
                            $totalvat .= ' ';
                            $totalvat .= vatrate(abs($tvakey), 1) . $tvacompl;
                            $pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', 1);

                            $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
                            $pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);
                        }
                    }
                }
                //}

                // Situations totals migth be wrong on huge amounts with old mode 1
                if (getDolGlobalInt('INVOICE_USE_SITUATION') == 1 && $object->situation_cycle_ref && $object->situation_counter > 1) {
                    $sum_pdf_tva = 0;
                    foreach ($this->tva as $tvakey => $tvaval) {
                        $sum_pdf_tva += $tvaval; // sum VAT amounts to compare to object
                    }

                    if ($sum_pdf_tva != $object->total_tva) { // apply coef to recover the VAT object amount (the good one)
                        if (!empty($sum_pdf_tva)) {
                            $coef_fix_tva = $object->total_tva / $sum_pdf_tva;
                        } else {
                            $coef_fix_tva = 1;
                        }

                        foreach ($this->tva as $tvakey => $tvaval) {
                            $this->tva[$tvakey] = $tvaval * $coef_fix_tva;
                        }
                        foreach ($this->tva_array as $tvakey => $tvaval) {
                            $this->tva_array[$tvakey]['amount'] = $tvaval['amount'] * $coef_fix_tva;
                        }
                    }
                }

                // VAT
                foreach ($this->tva_array as $tvakey => $tvaval) {
                    if ($tvakey != 0) { // On affiche pas taux 0
                        $this->atleastoneratenotnull++;

                        $index++;
                        $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

                        $tvacompl = '';
                        if (preg_match('/\*/', $tvakey)) {
                            $tvakey = str_replace('*', '', $tvakey);
                            $tvacompl = " (" . $outputlangs->transnoentities("NonPercuRecuperable") . ")";
                        }
                        $totalvat = $outputlangs->transcountrynoentities("TotalVAT", $mysoc->country_code) . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transcountrynoentities("TotalVAT", $mysoc->country_code) : '');
                        $totalvat .= ' ';
                        if (getDolGlobalString('PDF_VAT_LABEL_IS_CODE_OR_RATE') == 'rateonly') {
                            $totalvat .= vatrate($tvaval['vatrate'], 1) . $tvacompl;
                        } elseif (getDolGlobalString('PDF_VAT_LABEL_IS_CODE_OR_RATE') == 'codeonly') {
                            $totalvat .= ($tvaval['vatcode'] ? $tvaval['vatcode'] : vatrate($tvaval['vatrate'], 1)) . $tvacompl;
                        } else {
                            $totalvat .= vatrate($tvaval['vatrate'], 1) . ($tvaval['vatcode'] ? ' (' . $tvaval['vatcode'] . ')' : '') . $tvacompl;
                        }
                        $pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', 1);

                        $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
                        $pdf->MultiCell($largcol2, $tab2_hl, price(price2num($tvaval['amount'], 'MT'), 0, $outputlangs), 0, 'R', 1);
                    }
                }

                //Local tax 1 after VAT
                //if (!empty($conf->global->FACTURE_LOCAL_TAX1_OPTION) && $conf->global->FACTURE_LOCAL_TAX1_OPTION=='localtax1on')
                //{
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
                            $totalvat = $outputlangs->transcountrynoentities("TotalLT1", $mysoc->country_code) . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transcountrynoentities("TotalLT1", $mysoc->country_code) : '');
                            $totalvat .= ' ';
                            $totalvat .= vatrate(abs($tvakey), 1) . $tvacompl;

                            $pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', 1);
                            $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
                            $pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);
                        }
                    }
                }
                //}
                //Local tax 2 after VAT
                //if (!empty($conf->global->FACTURE_LOCAL_TAX2_OPTION) && $conf->global->FACTURE_LOCAL_TAX2_OPTION=='localtax2on')
                //{
                foreach ($this->localtax2 as $localtax_type => $localtax_rate) {
                    if (in_array((string) $localtax_type, array('2', '4', '6'))) {
                        continue;
                    }

                    foreach ($localtax_rate as $tvakey => $tvaval) {
                        // retrieve global local tax
                        if ($tvakey != 0) { // On affiche pas taux 0
                            //$this->atleastoneratenotnull++;

                            $index++;
                            $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

                            $tvacompl = '';
                            if (preg_match('/\*/', $tvakey)) {
                                $tvakey = str_replace('*', '', $tvakey);
                                $tvacompl = " (" . $outputlangs->transnoentities("NonPercuRecuperable") . ")";
                            }
                            $totalvat = $outputlangs->transcountrynoentities("TotalLT2", $mysoc->country_code) . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transcountrynoentities("TotalLT2", $mysoc->country_code) : '');
                            $totalvat .= ' ';

                            $totalvat .= vatrate(abs($tvakey), 1) . $tvacompl;
                            $pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', 1);

                            $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
                            $pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);
                        }
                    }
                }

                // Revenue stamp
                if (price2num($object->revenuestamp) != 0) {
                    $index++;
                    $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
                    $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("RevenueStamp") . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transnoentities("RevenueStamp", $mysoc->country_code) : ''), $useborder, 'L', 1);

                    $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
                    $pdf->MultiCell($largcol2, $tab2_hl, price($sign * $object->revenuestamp), $useborder, 'R', 1);
                }

                // Total TTC
                $index++;
                $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
                $pdf->SetTextColor(0, 0, 60);
                $pdf->SetFillColor(224, 224, 224);
                $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("TotalTTC") . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transnoentities("TotalTTC") : ''), $useborder, 'L', 1);

                $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
                $pdf->MultiCell($largcol2, $tab2_hl, price($sign * $total_ttc, 0, $outputlangs), $useborder, 'R', 1);

                // Retained warranty
                if ($object->displayRetainedWarranty()) {
                    $pdf->SetTextColor(40, 40, 40);
                    $pdf->SetFillColor(255, 255, 255);

                    $retainedWarranty = $object->getRetainedWarrantyAmount();
                    $billedWithRetainedWarranty = $object->total_ttc - $retainedWarranty;

                    // Billed - retained warranty
                    $index++;
                    $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
                    $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("ToPayOn", dol_print_date($object->date_lim_reglement, 'day')), $useborder, 'L', 1);

                    $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
                    $pdf->MultiCell($largcol2, $tab2_hl, price($billedWithRetainedWarranty), $useborder, 'R', 1);

                    // retained warranty
                    $index++;
                    $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

                    $retainedWarrantyToPayOn = $outputlangs->transnoentities("RetainedWarranty") . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transnoentities("RetainedWarranty") : '') . ' (' . $object->retained_warranty . '%)';
                    $retainedWarrantyToPayOn .= !empty($object->retained_warranty_date_limit) ? ' ' . $outputlangs->transnoentities("toPayOn", dol_print_date($object->retained_warranty_date_limit, 'day')) : '';

                    $pdf->MultiCell($col2x - $col1x, $tab2_hl, $retainedWarrantyToPayOn, $useborder, 'L', 1);
                    $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
                    $pdf->MultiCell($largcol2, $tab2_hl, price($retainedWarranty), $useborder, 'R', 1);
                }
            }
        }

        $pdf->SetTextColor(0, 0, 0);

        $creditnoteamount = $object->getSumCreditNotesUsed((isModEnabled("multicurrency") && $object->multicurrency_tx != 1) ? 1 : 0); // Warning, this also include excess received
        $depositsamount = $object->getSumDepositsUsed((isModEnabled("multicurrency") && $object->multicurrency_tx != 1) ? 1 : 0);

        $resteapayer = price2num($total_ttc - $deja_regle - $creditnoteamount - $depositsamount, 'MT');
        if (!empty($object->paye)) {
            $resteapayer = 0;
        }

        if (($deja_regle > 0 || $creditnoteamount > 0 || $depositsamount > 0) && empty($conf->global->INVOICE_NO_PAYMENT_DETAILS)) {
            // Already paid + Deposits
            $index++;
            $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
            $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("Paid") . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transnoentities("Paid") : ''), 0, 'L', 0);
            $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
            $pdf->MultiCell($largcol2, $tab2_hl, price($deja_regle + $depositsamount, 0, $outputlangs), 0, 'R', 0);

            // Credit note
            if ($creditnoteamount) {
                $labeltouse = ($outputlangs->transnoentities("CreditNotesOrExcessReceived") != "CreditNotesOrExcessReceived") ? $outputlangs->transnoentities("CreditNotesOrExcessReceived") : $outputlangs->transnoentities("CreditNotes");
                $labeltouse .= (is_object($outputlangsbis) ? (' / ' . ($outputlangsbis->transnoentities("CreditNotesOrExcessReceived") != "CreditNotesOrExcessReceived") ? $outputlangsbis->transnoentities("CreditNotesOrExcessReceived") : $outputlangsbis->transnoentities("CreditNotes")) : '');
                $index++;
                $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
                $pdf->MultiCell($col2x - $col1x, $tab2_hl, $labeltouse, 0, 'L', 0);
                $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
                $pdf->MultiCell($largcol2, $tab2_hl, price($creditnoteamount, 0, $outputlangs), 0, 'R', 0);
            }

            /*
            if ($object->close_code == Facture::CLOSECODE_DISCOUNTVAT)
            {
            $index++;
            $pdf->SetFillColor(255, 255, 255);

            $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
            $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("EscompteOfferedShort").(is_object($outputlangsbis) ? ' / '.$outputlangsbis->transnoentities("EscompteOfferedShort") : ''), $useborder, 'L', 1);
            $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
            $pdf->MultiCell($largcol2, $tab2_hl, price($object->total_ttc - $deja_regle - $creditnoteamount - $depositsamount, 0, $outputlangs), $useborder, 'R', 1);

            $resteapayer = 0;
            }
             */

            $index++;
            $pdf->SetTextColor(0, 0, 60);
            $pdf->SetFillColor(224, 224, 224);
            $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
            $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("RemainderToPay") . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transnoentities("RemainderToPay") : ''), $useborder, 'L', 1);
            $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
            $pdf->MultiCell($largcol2, $tab2_hl, price($resteapayer, 0, $outputlangs), $useborder, 'R', 1);

            $pdf->SetFont('', '', $default_font_size - 1);
            $pdf->SetTextColor(0, 0, 0);
        }

        $index++;

        // $pdf->MultiCell(0, 8, '#23 Green Park, Dickie Fritz Avenue,', 0, 'L');
        // $pdf->MultiCell(0, 8, "Edenvale 1609 Johannesburg", 0, 'L');
        // $pdf->MultiCell(0, 8, "South Africa", 0, 'L');

        // $pdf->Ln(15);
        // $this->_pagefoot($pdf, $object, $outputlangs, 1, $this->getHeightForQRInvoice($pdf->getPage(), $object, $outputlangs));

        // $pdf->AddPage();
        return ($tab2_top + ($tab2_hl * $index));
    }

    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
    /**
     *  Return list of active generation modules
     *
     *  @param    DoliDB    $db                 Database handler
     *  @param  integer    $maxfilenamelength  Max length of value to show
     *  @return    array                        List of templates
     */
    public static function liste_modeles($db, $maxfilenamelength = 0)
    {
        // phpcs:enable
        return parent::liste_modeles($db, $maxfilenamelength); // TODO: Change the autogenerated stub
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
     *   @param        int            $hidetop        1=Hide top bar of array and title, 0=Hide nothing, -1=Hide only title
     *   @param        int            $hidebottom        Hide bottom bar of array
     *   @param        string        $currency        Currency code
     *   @param        Translate    $outputlangsbis    Langs object bis
     *   @return    void
     */
    // to draw reactangle
    protected function _tableau(&$pdf, $tab_top, $tab_height, $nexY, $outputlangs, $hidetop = 0, $hidebottom = 0, $currency = '', $outputlangsbis = null)
    {
        global $conf;

        // Force to disable hidetop and hidebottom
        $hidebottom = 0;
        if ($hidetop) {
            $hidetop = -1;
        }

        $currency = !empty($currency) ? $currency : $conf->currency;
        $default_font_size = pdf_getPDFFontSize($outputlangs);

        // Amount in (at tab_top - 1) to hide Amount in South Africa Rand currency
        // $pdf->SetTextColor(0, 0, 0);
        // $pdf->SetFont('', '', $default_font_size - 2);

        // if (empty($hidetop)) {
        //     // Show category of operations
        //     if (getDolGlobalInt('INVOICE_CATEGORY_OF_OPERATION') == 1 && $this->categoryOfOperation >= 0) {
        //         $categoryOfOperations = $outputlangs->transnoentities("MentionCategoryOfOperations") . ' : ' . $outputlangs->transnoentities("MentionCategoryOfOperations" . $this->categoryOfOperation);
        //         $pdf->SetXY($this->marge_gauche, $tab_top - 4);
        //         $pdf->MultiCell(($pdf->GetStringWidth($categoryOfOperations)) + 4, 2, $categoryOfOperations);
        //     }

        //     $titre = $outputlangs->transnoentities("AmountInCurrency", $outputlangs->transnoentitiesnoconv("Currency" . $currency));
        //     if (!empty($conf->global->PDF_USE_ALSO_LANGUAGE_CODE) && is_object($outputlangsbis)) {
        //         $titre .= ' - ' . $outputlangsbis->transnoentities("AmountInCurrency", $outputlangsbis->transnoentitiesnoconv("Currency" . $currency));
        //     }

        //     $pdf->SetXY($this->page_largeur - $this->marge_droite - ($pdf->GetStringWidth($titre) + 3), $tab_top - 4);
        //     $pdf->MultiCell(($pdf->GetStringWidth($titre) + 3), 2, $titre);

        //     //$conf->global->MAIN_PDF_TITLE_BACKGROUND_COLOR='230,230,230';
        //     if (!empty($conf->global->MAIN_PDF_TITLE_BACKGROUND_COLOR)) {
        //         $pdf->Rect($this->marge_gauche, $tab_top, $this->page_largeur - $this->marge_droite - $this->marge_gauche, $this->tabTitleHeight, 'F', null, explode(',', $conf->global->MAIN_PDF_TITLE_BACKGROUND_COLOR));
        //     }
        // }

        $pdf->SetDrawColor(128, 128, 128);
        $pdf->SetFont('', '', $default_font_size - 1);

        // Output Rect
        $this->printRect($pdf, $this->marge_gauche, $tab_top, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $tab_height, $hidetop, $hidebottom); // Rect takes a length in 3rd parameter and 4th parameter

        // to display table title
        $this->pdfTabTitles($pdf, $tab_top, $tab_height, $outputlangs, $hidetop);

        if (empty($hidetop)) {
            $pdf->line($this->marge_gauche, $tab_top + $this->tabTitleHeight, $this->page_largeur - $this->marge_droite, $tab_top + $this->tabTitleHeight); // line takes a position y in 2nd parameter and 4th parameter
        }
    }

    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
    /**
     *  Show top header of page. This include the logo, ref and address blocs
     *
     *  @param    TCPDF        $pdf             Object PDF
     *  @param  Facture        $object         Object to show
     *  @param  int            $showaddress    0=no, 1=yes (usually set to 1 for first page, and 0 for next pages)
     *  @param  Translate    $outputlangs    Object lang for output
     *  @param  Translate    $outputlangsbis    Object lang for output bis
     *  @return    array                            top shift of linked object lines
     */
    protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs, $outputlangsbis = null, $pagenb)
    {
        global $conf, $langs;

        $ltrdirection = 'L';
        if ($outputlangs->trans("DIRECTION") == 'rtl') {
            $ltrdirection = 'R';
        }

        // Load traductions files required by page
        $outputlangs->loadLangs(array("main", "bills", "propal", "companies"));

        $default_font_size = pdf_getPDFFontSize($outputlangs);

        pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);

        $pdf->SetTextColor(0, 0, 60);
        $pdf->SetFont('', 'B', $default_font_size + 3);

        $w = 110;

        $posy = $this->marge_haute;
        $posx = $this->page_largeur - $this->marge_droite - $w;

        $pdf->SetXY($this->marge_gauche, $posy);

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

        if (!empty($conf->global->DOC_SHOW_FIRST_SALES_REP)) {
            $arrayidcontact = $object->getIdContact('internal', 'SALESREPFOLL');
            if (count($arrayidcontact) > 0) {
                $usertmp = new User($this->db);
                $usertmp->fetch($arrayidcontact[0]);
                $posy += 4;
                $pdf->SetXY($posx, $posy);
                $pdf->SetTextColor(0, 0, 60);
                $pdf->MultiCell($w, 3, $langs->transnoentities("SalesRepresentative") . " : " . $usertmp->getFullName($langs), '', 'R');
            }
        }

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

        $invoice_obj = new stdClass();
        $sql_llx_facture = "SELECT * FROM " . MAIN_DB_PREFIX . "facture WHERE rowid = $object->id";
        $res_llx_facture = $this->db->query($sql_llx_facture);

        if ($res_llx_facture) {
            while ($row = $this->db->fetch_object($res_llx_facture)) {
                $originalDate = $row->datec;
                $dateTime = new DateTime($originalDate);
                $formattedDate = $dateTime->format('d M Y');
                $invoice_obj->dateValue = $formattedDate;
                $invoice_obj->company_rowid = $row->fk_soc;
                $invoice_obj->division = $row->division;
                $invoice_obj->projectid = $row->fk_projet;
                $invoice_obj->vendorNO = $row->vendor_no;
                $invoice_obj->contact_person = $row->contact_person;
                $invoice_obj->tellNo = $row->cell;
                $invoice_obj->email = $row->email;
                $invoice_obj->clientVat = $row->client_vat;
                $invoice_obj->poNo = $row->po_no;
                $invoice_obj->quoteNo = $row->quote_no;
                $invoice_obj->ourVatNo = $row->vat_no;
                $invoice_obj->invoiceNo = $row->ref;
            }
        } else {
            // Handle any errors with the llx_facture query
            echo "Error executing llx_facture query: " . $this->db->lasterror();
        }

        $sql_llx_societe = "SELECT nom FROM " . MAIN_DB_PREFIX . "societe WHERE rowid = $invoice_obj->company_rowid";
        $res_llx_societe = $this->db->query($sql_llx_societe);
        if ($res_llx_societe) {
            $row = $this->db->fetch_object($res_llx_societe);
            if ($row) {
                $invoice_obj->company = $row->nom;
            }
        }

        $sql_llx_projet = "SELECT title FROM " . MAIN_DB_PREFIX . "projet WHERE rowid = $invoice_obj->projectid";
        $res_llx_projet = $this->db->query($sql_llx_projet);
        if ($res_llx_projet) {
            $row = $this->db->fetch_object($res_llx_projet);
            if ($row) {
                $invoice_obj->project = $row->title;
            }
        }

        // print '<script>console.log(`'.json_encode($invoice_obj).'`)</script>';

        // Add TAX INVOICE heading
        if ($pagenb === 1) {
            $pdf->SetFont('', 'B', 14); // Set bold font with size 14
            $pdf->Cell(0, 10, 'TAX INVOICE', 0, 1, 'C'); // Centered heading
            $tableX = $this->marge_gauche;
            $tableY = $posy + 40;
            $pdf->SetFont('');
            $pdf->SetFont('', '', $default_font_size);
            $pdf->SetXY($tableX, $tableY);
            $pdf->Cell(0, 5, 'Date: ' . $invoice_obj->dateValue, 'LTR', 0, 'L');
            $pdf->Cell(0, 5, 'Division: ' . $invoice_obj->division, 'R', 1, 'R');
            $pdf->Cell(0, 5, 'Company: ' . $invoice_obj->company, 'L', 0, 'L');
            $pdf->Cell(0, 5, 'Vendor No: ' . $invoice_obj->vendorNO, 'R', 1, 'R');
            $pdf->Cell(0, 5, 'Project: ' . $invoice_obj->project, 'L', 0, 'L');
            $pdf->Cell(0, 5, 'P.O. No.: ' . $invoice_obj->poNo, 'R', 1, 'R');
            $pdf->Cell(0, 5, 'Contact: ' . $invoice_obj->contact_person, 'L', 0, 'L');
            $pdf->Cell(0, 5, '                 ', 'R', 1, 'R');
            $pdf->Cell(0, 5, 'Tel No: ' . $invoice_obj->tellNo, 'L', 0, 'L');
            $pdf->Cell(0, 5, 'Quote No.: ' . $invoice_obj->quoteNo, 'R', 1, 'R');
            $pdf->Cell(0, 5, 'Email: ' . $invoice_obj->email, 'L', 0, 'L');
            $pdf->Cell(0, 5, 'Invoice No.: ' . $invoice_obj->invoiceNo, 'R', 1, 'R');
            $pdf->Cell(0, 5, 'Client Vat: ' . $invoice_obj->clientVat, 'LB', 0, 'L');
            $pdf->Cell(0, 5, 'Our Vat No.: ' . $invoice_obj->ourVatNo, 'RB', 1, 'R');
        }

        $pdf->SetTextColor(0, 0, 0);

        $pagehead = array('top_shift' => $top_shift, 'shipp_shift' => $shipp_shift);
        return $pagehead;
    }

    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
    /**
     *       Show footer of page. Need this->emetteur object
     *
     *       @param    TCPDF        $pdf                 PDF
     *         @param    Facture        $object                Object to show
     *      @param    Translate    $outputlangs        Object lang for output
     *      @param    int            $hidefreetext        1=Hide free text
     *      @param    int            $heightforqrinvoice    Height for QR invoices
     *      @return    int                                Return height of bottom margin including footer text
     */
    protected function _pagefoot(&$pdf, $object, $outputlangs, $hidefreetext = 0, $heightforqrinvoice = 0)
    {
        $showdetails = getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS', 0);

        $pdf->SetXY($this->marge_gauche, $this->page_hauteur - $this->marge_basse);
        $pdf->MultiCell(0, 0, '#23 Green Park, Dickie Fritz Avenue,', 0, 'L');
        $pdf->MultiCell(0, 0, "Edenvale 1609 Johannesburg, South Africa", 0, 'L');
        return pdf_pagefoot($pdf, $outputlangs, 'INVOICE_FREE_TEXT', $this->emetteur, $heightforqrinvoice + $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext, $this->page_largeur, $this->watermark);
    }

    /**
     *  Define Array Column Field
     *
     *  @param    Facture           $object            common object
     *  @param    Translate       $outputlangs     langs
     *  @param    int               $hidedetails        Do not show line details
     *  @param    int               $hidedesc        Do not show desc
     *  @param    int               $hideref            Do not show ref
     *  @return    void
     */
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
        $this->cols['qty'] = array(
            'rank' => $rank,
            'width' => 16, // in mm
            'status' => true,
            'title' => array(
                'textkey' => 'Qty',
            ),
            'content' => array(
                'align' => 'C',
                'padding' => array(1, 0.5, 1, 1.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
            ),
            // 'border-left' => true, // add left line separator
        );

        $rank = $rank + 10;
        $this->cols['unit'] = array(
            'rank' => $rank,
            'width' => 16, // in mm
            'status' => true,
            'title' => array(
                'textkey' => 'Unit',
            ),
            'border-left' => true, // add left line separator
        );

        $rank = $rank + 10;
        $this->cols['desc'] = array(
            'rank' => $rank,
            'width' => false, // only for desc
            'status' => true,
            'title' => array(
                'textkey' => 'Designation', // use lang key is usefull in somme case with module
                'align' => 'C',
                // 'textkey' => 'yourLangKey', // if there is no label, yourLangKey will be translated to replace label
                // 'label' => ' ', // the final label
                'padding' => array(0.5, 0.5, 0.5, 0.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
            ),
            'content' => array(
                'align' => 'L',
                'padding' => array(1, 0.5, 1, 1.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
            ),
            'border-left' => true,
        );

        // Image of product
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

        if (!empty($conf->global->MAIN_GENERATE_INVOICES_WITH_PICTURE) && !empty($this->atleastonephoto)) {
            $this->cols['photo']['status'] = true;
        }

        // if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT) && empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_COLUMN)) {
        //     $this->cols['vat']['status'] = true;
        // }

        $rank = $rank + 10;
        $this->cols['subprice'] = array(
            'rank' => $rank,
            'width' => 19, // in mm
            'status' => true,
            'title' => array(
                'textkey' => 'Per',
            ),
            'border-left' => true, // add left line separator
        );

        // Adapt dynamically the width of subprice, if text is too long.
        $tmpwidth = 0;
        $nblines = count($object->lines);
        for ($i = 0; $i < $nblines; $i++) {
            $tmpwidth2 = dol_strlen(dol_string_nohtmltag(pdf_getlineupexcltax($object, $i, $outputlangs, $hidedetails)));
            $tmpwidth = max($tmpwidth, $tmpwidth2);
        }
        if ($tmpwidth > 10) {
            $this->cols['subprice']['width'] += (2 * ($tmpwidth - 10));
        }

        $rank = $rank + 10;
        $this->cols['progress'] = array(
            'rank' => $rank,
            'width' => 19, // in mm
            'status' => false,
            'title' => array(
                'textkey' => 'ProgressShort',
            ),
            'border-left' => true, // add left line separator
        );

        if ($this->situationinvoice) {
            $this->cols['progress']['status'] = true;
        }

        // $rank = $rank + 10;
        // $this->cols['unit'] = array(
        //     'rank' => $rank,
        //     'width' => 11, // in mm
        //     'status' => false,
        //     'title' => array(
        //         'textkey' => 'Unit'
        //     ),
        //     'border-left' => true, // add left line separator
        // );
        // if (getDolGlobalInt('PRODUCT_USE_UNITS')) {
        //     $this->cols['unit']['status'] = true;
        // }

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
            'status' => empty($conf->global->PDF_PROPAL_HIDE_PRICE_EXCL_TAX) ? true : false,
            'title' => array(
                'textkey' => 'Price',
            ),
            'border-left' => true, // add left line separator
        );

        $rank = $rank + 1010; // add a big offset to be sure is the last col because default extrafield rank is 100
        $this->cols['totalincltax'] = array(
            'rank' => $rank,
            'width' => 26, // in mm
            'status' => empty($conf->global->PDF_PROPAL_SHOW_PRICE_INCL_TAX) ? false : true,
            'title' => array(
                'textkey' => 'TotalTTCShort',
            ),
            'border-left' => true, // add left line separator
        );

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

    public function bankDetails(&$pdf, $posy, $outputlangs, $object, $outputlangsbis)
    {
        $default_font_size = pdf_getPDFFontSize($outputlangs);
        $bankDetailsHeight = 50;
        // Check if there is enough space on the current page
        if ($posy > $this->page_hauteur - 10 - $this->heightforfooter - $bankDetailsHeight) {
            // If not, add a new page and handle page headers/footers
            $this->_pagefoot($pdf, $object, $outputlangs, 1, $this->getHeightForQRInvoice($pdf->getPage(), $object, $outputlangs));
            $pdf->addPage();
            if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
                $this->_pagehead($pdf, $object, 0, $outputlangs, $outputlangsbis, $pagenb);
                $pdf->setY($this->tab_top_newpage);
            } else {
                $pdf->setY($this->marge_haute);
            }

            // Update $posy to start from the top of the new page
            $posy = $pdf->GetY();
        }

        // to display bank details
        $tableX = $this->marge_gauche;
        $tableY = $posy;
        $pdf->SetFont('');
        $pdf->SetXY($tableX, $tableY);
        $pdf->SetFont('', 'B', $default_font_size);
        $pdf->Cell(0, 10, 'Banking Details:', 0, 1, 'L');
        $pdf->SetFont('', '', $default_font_size);
        // first row
        $pdf->Cell(40, 5, 'Bank', 1, 0, 'L');
        $pdf->Cell(40, 5, 'Standard Bank', 1, 0, 'C');
        $pdf->Cell(40, 5, 'Branch Code', 1, 0, 'C');
        $pdf->Cell(0, 5, '016 342, Greenstone', 1, 1, 'R');

        // second row
        $pdf->Cell(40, 5, 'Account Name', 1, 0, 'L');
        $pdf->Cell(40, 5, 'First Vision Automation', 1, 0, 'C');
        $pdf->Cell(40, 5, 'Account type', 1, 0, 'C');
        $pdf->Cell(0, 5, 'Current', 1, 1, 'R');

        // third row
        $pdf->Cell(40, 5, 'Account No.', 1, 0, 'L');
        $pdf->Cell(40, 5, '22 01 34 545', 1, 0, 'C');
        $pdf->Cell(40, 5, 'VAT No.', 1, 0, 'C');
        $pdf->Cell(0, 5, '4580291799', 1, 1, 'R');

        // to display message
        $pdf->Ln();
        $pdf->MultiCell(0, 4, "We trust our offer meets your requirements.Should you require any additional information, please do not hesitate to call upon the undersigned.", 0, 'L');
        $pdf->Ln();
        $pdf->MultiCell(0, 4, "With Kind Regards", 0, 'L');
        $pdf->Ln();
        $pdf->MultiCell(0, 4, "Atul Rajgure.", 0, 'L');
        $pdf->Ln();
        $pdf->MultiCell(0, 4, "Cell: +27 83 268 8819.", 0, 'L');
        return $posy;
    }
}
