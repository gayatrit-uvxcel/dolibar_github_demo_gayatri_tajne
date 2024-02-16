<?php
/* Copyright (C) 2004-2014 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012 Regis Houssin        <regis.houssin@inodbox.com>
 * Copyright (C) 2008      Raphael Bertrand     <raphael.bertrand@resultic.fr>
 * Copyright (C) 2010-2015 Juanjo Menent        <jmenent@2byte.es>
 * Copyright (C) 2012      Christophe Battarel   <christophe.battarel@altairis.fr>
 * Copyright (C) 2012      Cedric Salvador      <csalvador@gpcsolutions.fr>
 * Copyright (C) 2015      Marcos García        <marcosgdf@gmail.com>
 * Copyright (C) 2017      Ferran Marcet        <fmarcet@2byte.es>
 * Copyright (C) 2018      Frédéric France      <frederic.france@netlogic.fr>
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
 *    \file       htdocs/core/modules/propale/doc/pdf_cyan.modules.php
 *    \ingroup    propale
 *    \brief      File of Class to generate PDF proposal with Cyan template
 */
require_once DOL_DOCUMENT_ROOT . '/core/modules/propale/modules_propale.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';

/**
 *    Class to generate PDF proposal Cyan
 */
class pdf_cyan extends ModelePDFPropales
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
     * @var int    Save the name of generated file as the main doc when generating a doc with this template
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
     * @var Societe Object that emits
     */
    public $emetteur;

    /**
     * @var array of document table columns
     */
    public $cols;

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
        $this->name = "cyan";
        $this->description = $langs->trans('DocModelCyanDescription');
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

        $this->option_logo = 1; // Display logo
        $this->option_tva = 1; // Manage the vat option FACTURE_TVAOPTION
        $this->option_modereg = 1; // Display payment mode
        $this->option_condreg = 1; // Display payment terms
        $this->option_multilang = 1; // Available in several languages
        $this->option_escompte = 0; // Displays if there has been a discount
        $this->option_credit_note = 0; // Support credit notes
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
    }

    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
    /**
     *  Function to build pdf onto disk
     *
     *  @param        Propal        $object                Object to generate
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

        // Load translation files required by page
        $outputlangs->loadLangs(array("main", "dict", "companies", "bills", "products", "propal"));

        //  Show Draft Watermark
        if ($object->statut == $object::STATUS_DRAFT && getDolGlobalString('PROPALE_DRAFT_WATERMARK')) {
            $this->watermark = getDolGlobalString('PROPALE_DRAFT_WATERMARK');
        }

        global $outputlangsbis;
        $outputlangsbis = null;
        if (!empty($conf->global->PDF_USE_ALSO_LANGUAGE_CODE) && $outputlangs->defaultlang != $conf->global->PDF_USE_ALSO_LANGUAGE_CODE) {
            $outputlangsbis = new Translate('', $conf);
            $outputlangsbis->setDefaultLang($conf->global->PDF_USE_ALSO_LANGUAGE_CODE);
            $outputlangsbis->loadLangs(array("main", "dict", "companies", "bills", "products", "propal"));
        }

        $nblines = count($object->lines);

        for ($i = 0; $i < $nblines; $i++) {
            $productId = $object->lines[$i]->rowid;
            $sql_llx_propaldet = "SELECT * FROM " . MAIN_DB_PREFIX . "propaldet WHERE rowid = $productId";
            $res_llx_propaldet = $this->db->query($sql_llx_propaldet);
            if ($res_llx_propaldet) {
                while ($row = $this->db->fetch_object($res_llx_propaldet)) {
                    $object->lines[$i]->category = explode(" - ", $row->category)[0];
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
        if (!empty($conf->global->MAIN_GENERATE_PROPOSALS_WITH_PICTURE)) {
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

        if (count($realpatharray) == 0) {
            $this->posxpicture = $this->posxtva;
        }

        if ($conf->propal->multidir_output[$conf->entity]) {
            $object->fetch_thirdparty();

            $deja_regle = 0;

            // Definition of $dir and $file
            if ($object->specimen) {
                $dir = $conf->propal->multidir_output[$conf->entity];
                $file = $dir . "/SPECIMEN.pdf";
            } else {
                $objectref = dol_sanitizeFileName($object->ref);
                $dir = $conf->propal->multidir_output[$object->entity] . "/" . $objectref;
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

                // Set nblines with the new content of lines after hook
                $nblines = count($object->lines);
                //$nbpayments = count($object->getListOfPayments());

                // Create pdf instance
                $pdf = pdf_getInstance($this->format);
                $default_font_size = pdf_getPDFFontSize($outputlangs); // Must be after pdf_getInstance
                $pdf->SetAutoPageBreak(1, 0);

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
                $pdf->SetSubject($outputlangs->transnoentities("PdfCommercialProposalTitle"));
                $pdf->SetCreator("Dolibarr " . DOL_VERSION);
                $pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
                $pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref) . " " . $outputlangs->transnoentities("PdfCommercialProposalTitle") . " " . $outputlangs->convToOutputCharset($object->thirdparty->name));
                if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
                    $pdf->SetCompression(false);
                }

                $pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite); // Left, Top, Right

                // Set $this->atleastonediscount if you have at least one discount
                for ($i = 0; $i < $nblines; $i++) {
                    if ($object->lines[$i]->remise_percent) {
                        $this->atleastonediscount++;
                    }
                }

                // New page
                $pdf->AddPage();
                if (!empty($tplidx)) {
                    $pdf->useTemplate($tplidx);
                }
                $pagenb++;
                $heightforinfotot = 40; // Height reserved to output the info and total part
                $heightforsignature = empty($conf->global->PROPAL_DISABLE_SIGNATURE) ? (pdfGetHeightForHtmlContent($pdf, $outputlangs->transnoentities("ProposalCustomerSignature")) + 10) : 0;
                $heightforfreetext = (isset($conf->global->MAIN_PDF_FREETEXT_HEIGHT) ? $conf->global->MAIN_PDF_FREETEXT_HEIGHT : 5); // Height reserved to output the free text on last page
                $heightforfooter = $this->marge_basse + (empty($conf->global->MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS) ? 12 : 22); // Height reserved to output the footer (value include bottom margin)
                //print $heightforinfotot + $heightforsignature + $heightforfreetext + $heightforfooter;exit;

                $top_shift = $this->_pagehead($pdf, $object, 1, $outputlangs, $outputlangsbis, $pagenb);
                $pdf->SetFont('', '', $default_font_size - 1);
                $pdf->MultiCell(0, 3, ''); // Set interline to 3
                $pdf->SetTextColor(0, 0, 0);

                $tab_top = 90 + $top_shift;
                $tab_top_newpage = (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD') ? 48 + $top_shift : 10);

                $nexY = $tab_top;

                // Incoterm
                $height_incoterms = 0;
                if (isModEnabled('incoterm')) {
                    $desc_incoterms = $object->getIncotermsForPDF();
                    if ($desc_incoterms) {
                        $tab_top -= 2;

                        $pdf->SetFont('', '', $default_font_size - 1);
                        $pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $tab_top - 1, dol_htmlentitiesbr($desc_incoterms), 0, 1);
                        $nexY = max($pdf->GetY(), $nexY);
                        $height_incoterms = $nexY - $tab_top;

                        // Rect takes a length in 3rd parameter
                        $pdf->SetDrawColor(192, 192, 192);
                        $pdf->Rect($this->marge_gauche, $tab_top - 1, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $height_incoterms + 1);

                        $tab_top = $nexY + 6;
                        $height_incoterms += 4;
                    }
                }

                // Displays notes
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

                if (!empty($conf->global->MAIN_ADD_CREATOR_IN_NOTE) && $object->user_author_id > 0) {
                    $tmpuser = new User($this->db);
                    $tmpuser->fetch($object->user_author_id);

                    $creator_info = $langs->trans("CaseFollowedBy") . ' ' . $tmpuser->getFullName($langs);
                    if ($tmpuser->email) {
                        $creator_info .= ',  ' . $langs->trans("EMail") . ': ' . $tmpuser->email;
                    }

                    if ($tmpuser->office_phone) {
                        $creator_info .= ', ' . $langs->trans("Phone") . ': ' . $tmpuser->office_phone;
                    }

                    $notetoshow = dol_concatdesc($notetoshow, $creator_info);
                }

                $tab_height = $this->page_hauteur - $tab_top_newpage - $heightforinfotot - $heightforfreetext - $heightforsignature - $heightforfooter;

                $pagenb = $pdf->getPage();
                // if ($notetoshow) {
                //     $tab_top -= 2;

                //     $tab_width = $this->page_largeur - $this->marge_gauche - $this->marge_droite;
                //     $pageposbeforenote = $pagenb;

                //     $substitutionarray = pdf_getSubstitutionArray($outputlangs, null, $object);
                //     complete_substitutions_array($substitutionarray, $outputlangs, $object);
                //     $notetoshow = make_substitutions($notetoshow, $substitutionarray, $outputlangs);
                //     $notetoshow = convertBackOfficeMediasLinksToPublicLinks($notetoshow);

                //     $pdf->startTransaction();

                //     $pdf->SetFont('', '', $default_font_size - 1);
                //     $pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $tab_top, dol_htmlentitiesbr($notetoshow), 0, 1);
                //     // Description
                //     $pageposafternote = $pdf->getPage();
                //     $posyafter = $pdf->GetY();

                //     if ($pageposafternote > $pageposbeforenote) {
                //         $pdf->rollbackTransaction(true);

                //         // prepare pages to receive notes
                //         while ($pagenb < $pageposafternote) {
                //             $pdf->AddPage();
                //             $pagenb++;
                //             if (!empty($tplidx)) {
                //                 $pdf->useTemplate($tplidx);
                //             }
                //             if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
                //                 $this->_pagehead($pdf, $object, 0, $outputlangs, $pagenb, $outputlangsbis);

                //             }
                //             // $this->_pagefoot($pdf,$object,$outputlangs,1);
                //             $pdf->setTopMargin($tab_top_newpage);
                //             // The only function to edit the bottom margin of current page to set it.
                //             $pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext);
                //         }

                //         // back to start
                //         $pdf->setPage($pageposbeforenote);
                //         $pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext);
                //         $pdf->SetFont('', '', $default_font_size - 1);
                //         $pdf->writeHTMLCell(190, 3, $this->posxdesc - 1, $tab_top, dol_htmlentitiesbr($notetoshow), 0, 1);
                //         $pageposafternote = $pdf->getPage();

                //         $posyafter = $pdf->GetY();

                //         if ($posyafter > ($this->page_hauteur - ($heightforfooter + $heightforfreetext + 20))) { // There is no space left for total+free text
                //             $pdf->AddPage('', '', true);
                //             $pagenb++;
                //             $pageposafternote++;
                //             $pdf->setPage($pageposafternote);
                //             $pdf->setTopMargin($tab_top_newpage);
                //             // The only function to edit the bottom margin of current page to set it.
                //             $pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext);
                //             //$posyafter = $tab_top_newpage;
                //         }

                //         // apply note frame to previous pages
                //         $i = $pageposbeforenote;
                //         while ($i < $pageposafternote) {
                //             $pdf->setPage($i);

                //             $pdf->SetDrawColor(128, 128, 128);
                //             // Draw note frame
                //             if ($i > $pageposbeforenote) {
                //                 $height_note = $this->page_hauteur - ($tab_top_newpage + $heightforfooter);
                //                 $pdf->Rect($this->marge_gauche, $tab_top_newpage - 1, $tab_width, $height_note + 1);
                //             } else {
                //                 $height_note = $this->page_hauteur - ($tab_top + $heightforfooter);
                //                 $pdf->Rect($this->marge_gauche, $tab_top - 1, $tab_width, $height_note + 1);
                //             }

                //             // Add footer
                //             $pdf->setPageOrientation('', 1, 0); // The only function to edit the bottom margin of current page to set it.
                //             $this->_pagefoot($pdf, $object, $outputlangs, 1);

                //             $i++;
                //         }

                //         // apply note frame to last page
                //         $pdf->setPage($pageposafternote);
                //         if (!empty($tplidx)) {
                //             $pdf->useTemplate($tplidx);
                //         }
                //         if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
                //             $this->_pagehead($pdf, $object, 0, $outputlangs, $pagenb, $outputlangsbis);
                //         }
                //         $height_note = $posyafter - $tab_top_newpage;
                //         $pdf->Rect($this->marge_gauche, $tab_top_newpage - 1, $tab_width, $height_note + 1);
                //     } else {
                //         // No pagebreak
                //         $pdf->commitTransaction();
                //         $posyafter = $pdf->GetY();
                //         $height_note = $posyafter - $tab_top;
                //         $pdf->Rect($this->marge_gauche, $tab_top - 1, $tab_width, $height_note + 1);

                //         if ($posyafter > ($this->page_hauteur - ($heightforfooter + $heightforfreetext + 20))) {
                //             // not enough space, need to add page
                //             $pdf->AddPage('', '', true);
                //             $pagenb++;
                //             $pageposafternote++;
                //             $pdf->setPage($pageposafternote);
                //             if (!empty($tplidx)) {
                //                 $pdf->useTemplate($tplidx);
                //             }
                //             if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
                //                 $this->_pagehead($pdf, $object, 0, $outputlangs, $pagenb, $outputlangsbis);
                //             }

                //             $posyafter = $tab_top_newpage;
                //         }
                //     }
                //     $tab_height = $tab_height - $height_note;
                //     $tab_top = $posyafter + 6;
                // } else {
                //     $height_note = 0;
                // }

                // Use new auto column system
                $this->prepareArrayColumnField($object, $outputlangs, $hidedetails, $hidedesc, $hideref);

                // Table simulation to know the height of the title line
                $pdf->startTransaction();
                $this->pdfTabTitles($pdf, $tab_top, $tab_height, $outputlangs, $hidetop);
                $pdf->rollbackTransaction(true);

                $nexY = $tab_top + $this->tabTitleHeight;

                usort($object->lines, function ($a, $b) {
                    return strcmp($a->category, $b->category);
                });

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

                    $pdf->setTopMargin($tab_top_newpage);
                    $pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext + $heightforsignature + $heightforinfotot); // The only function to edit the bottom margin of current page to set it.
                    $pageposbefore = $pdf->getPage();

                    $showpricebeforepagebreak = 1;
                    $posYAfterImage = 0;
                    $posYAfterDescription = 0;

                    if ($this->getColumnStatus('photo')) {
                        // We start with Photo of product line
                        if (isset($imglinesize['width']) && isset($imglinesize['height']) && ($curY + $imglinesize['height']) > ($this->page_hauteur - ($heightforfooter + $heightforfreetext + $heightforsignature + $heightforinfotot))) { // If photo too high, we moved completely on new page
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
                    if ($this->getColumnStatus('desc')) {
                        $pdf->startTransaction();

                        // $this->printColDescContent($pdf, $curY, 'desc', $object, $i, $outputlangs, $hideref, $hidedesc);
                        if (!isset($line->desc)) {
                            $this->printStdColumnContent($pdf, $curY, 'desc', $line['desc']);
                        } else {
                            $this->printColDescContent($pdf, $curY, 'desc', $object, $i, $outputlangs, $hideref, $hidedesc);
                        }
                        $pageposafter = $pdf->getPage();
                        if ($pageposafter > $pageposbefore) { // There is a pagebreak
                            $pdf->rollbackTransaction(true);

                            $pdf->setPageOrientation('', 1, $heightforfooter); // The only function to edit the bottom margin of current page to set it.

                            // $this->printColDescContent($pdf, $curY, 'desc', $object, $i, $outputlangs, $hideref, $hidedesc);

                            if (!isset($line->desc)) {
                                $this->printStdColumnContent($pdf, $curY, 'desc', $line['desc']);
                            } else {
                                $this->printColDescContent($pdf, $curY, 'desc', $object, $i, $outputlangs, $hideref, $hidedesc);
                            }

                            $pageposafter = $pdf->getPage();
                            $posyafter = $pdf->GetY();
                            //var_dump($posyafter); var_dump(($this->page_hauteur - ($heightforfooter+$heightforfreetext+$heightforinfotot))); exit;
                            if ($posyafter > ($this->page_hauteur - ($heightforfooter + $heightforfreetext + $heightforsignature + $heightforinfotot))) { // There is no space left for total+free text
                                if ($i == ($nblines - 1)) { // No more lines, and no space left to show total, so we create a new page
                                    $object->isLinesAvailable = 1;
                                    $pdf->AddPage('', '', true);
                                    if (!empty($tplidx)) {
                                        $pdf->useTemplate($tplidx);
                                    }
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

                    // $displayValue = '';
                    // $values = array($line->product_ref, $line->product_label, $line->desc);
                    // $filteredValues = array_filter($values, 'strlen'); // Remove empty values
                    // $displayValue = implode('<br>', $filteredValues);

                    // if (isset($line->desc)) {
                    //     $this->printStdColumnContent($pdf, $curY, 'desc', $displayValue);
                    // } else {
                    //     $this->printStdColumnContent($pdf, $curY, 'desc', $line['desc']);
                    // }
                    // $nexY = max($pdf->GetY(), $nexY);

                    $nexY = $pdf->GetY();
                    $pageposafter = $pdf->getPage();

                    $pdf->setPage($pageposbefore);
                    $pdf->setTopMargin($this->marge_haute);
                    $pdf->setPageOrientation('', 1, 0); // The only function to edit the bottom margin of current page to set it.

                    // We suppose that a too long description or photo were moved completely on next page
                    if ($pageposafter > $pageposbefore && empty($showpricebeforepagebreak)) {
                        $pdf->setPage($pageposafter);
                        $curY = $tab_top_newpage;
                    }

                    $pdf->SetFont('', '', $default_font_size - 1); // We reposition the default font

                    // VAT Rate
                    if ($this->getColumnStatus('vat')) {
                        $vat_rate = pdf_getlinevatrate($object, $i, $outputlangs, $hidedetails);
                        $this->printStdColumnContent($pdf, $curY, 'vat', $vat_rate);
                        $nexY = max($pdf->GetY(), $nexY);
                    }

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

                    // Collection of totals by value of vat in $this->tva["rate"] = total_tva
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
                    $this->tva[$vatrate] += $tvaligne;
                    $vatcode = $object->lines[$i]->vat_src_code;
                    if (empty($this->tva_array[$vatrate . ($vatcode ? ' (' . $vatcode . ')' : '')]['amount'])) {
                        $this->tva_array[$vatrate . ($vatcode ? ' (' . $vatcode . ')' : '')]['amount'] = 0;
                    }
                    $this->tva_array[$vatrate . ($vatcode ? ' (' . $vatcode . ')' : '')] = array('vatrate' => $vatrate, 'vatcode' => $vatcode, 'amount' => $this->tva_array[$vatrate . ($vatcode ? ' (' . $vatcode . ')' : '')]['amount'] + $tvaligne);

                    if ($posYAfterImage > $posYAfterDescription) {
                        $nexY = max($nexY, $posYAfterImage);
                    }

                    // Add line
                    if (!empty($conf->global->MAIN_PDF_DASH_BETWEEN_LINES) && $i < ($nblines - 1)) {
                        $pdf->setPage($pageposafter);
                        $pdf->SetLineStyle(array('dash' => '1,1', 'color' => array(80, 80, 80)));
                        //$pdf->SetDrawColor(190,190,200);
                        $pdf->line($this->marge_gauche, $nexY + 1, $this->page_largeur - $this->marge_droite, $nexY + 1);
                        $pdf->SetLineStyle(array('dash' => 0));
                    }

                    $nexY += 2; // Add space between lines

                    // Detect if some page were added automatically and output _tableau for past pages
                    while ($pagenb < $pageposafter) {
                        $pdf->setPage($pagenb);
                        if ($pagenb == $pageposbeforeprintlines) {
                            $this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, $hidetop, 1, $object->multicurrency_code, $outputlangsbis);
                        } else {
                            $this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, 1, 1, $object->multicurrency_code, $outputlangsbis);
                        }
                        $this->_pagefoot($pdf, $object, $outputlangs, 1);
                        $pagenb++;
                        $pdf->setPage($pagenb);
                        $pdf->setPageOrientation('', 1, 0); // The only function to edit the bottom margin of current page to set it.
                        if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
                            $this->_pagehead($pdf, $object, 0, $outputlangs, $pagenb, $outputlangsbis);
                        }
                        if (!empty($tplidx)) {
                            $pdf->useTemplate($tplidx);
                        }
                    }

                    if (isset($object->lines[$i + 1]->pagebreak) && $object->lines[$i + 1]->pagebreak) {
                        if ($pagenb == $pageposafter) {
                            $this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, $hidetop, 1, $object->multicurrency_code, $outputlangsbis);
                        } else {
                            $this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, 1, 1, $object->multicurrency_code, $outputlangsbis);
                        }
                        $this->_pagefoot($pdf, $object, $outputlangs, 1);
                        // New page
                        $pdf->AddPage();
                        if (!empty($tplidx)) {
                            $pdf->useTemplate($tplidx);
                        }
                        $pagenb++;
                        if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
                            $this->_pagehead($pdf, $object, 0, $outputlangs, $pagenb, $outputlangsbis);
                        }
                    }
                }

                if ($notetoshow) {
                    $pdf->setPage($pagenb);

                    // Adjust the top margin
                    $topMargin = 90;
                    $pdf->setTopMargin($this->marge_haute + $topMargin);

                    $pdf->setPageOrientation('', 1, 0); // The only function to edit the bottom margin of the current page to set it.

                    //  to move the note to the right side
                    $rightMargin = 153;
                    $xPosition = 190 - $rightMargin;
                    // Adjust the Y position to set the top margin
                    $topPosition = $pdf->GetY() + $topMargin;
                    $pdf->SetY($topPosition);

                    $pdf->SetFont('', '', $default_font_size - 1);
                    $pdf->writeHTMLCell(190, 3, $xPosition, $pdf->GetY(), dol_htmlentitiesbr($notetoshow), 0, 1);

                    // Update $nexY after displaying the note
                    $nexY = $pdf->GetY() + 6;
                }
                // Show square
                if ($pagenb == $pageposbeforeprintlines) {
                    $this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforinfotot - $heightforfreetext - $heightforsignature - $heightforfooter, 0, $outputlangs, $hidetop, 0, $object->multicurrency_code, $outputlangsbis);
                    $bottomlasttab = $this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforsignature - $heightforfooter + 1;
                } else {
                    if ($object->isLinesAvailable !== 1) {
                        $this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforinfotot - $heightforfreetext - $heightforsignature - $heightforfooter, 0, $outputlangs, 1, 0, $object->multicurrency_code, $outputlangsbis);
                        $bottomlasttab = $this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforsignature - $heightforfooter + 1;
                    } else {
                        $bottomlasttab = 50;
                    }
                }

                // Display infos area
                $posy = $this->drawInfoTable($pdf, $object, $bottomlasttab, $outputlangs);

                // Display total zone
                $posy = $this->drawTotalTable($pdf, $object, 0, $bottomlasttab, $outputlangs);

                // display terms and condition
                // $posy = $this->displayNotesAndTerms($pdf, $object, $posy, $outputlangs);
                $remainingSpaceforNotes = $this->page_hauteur - $pdf->GetY() - $heightforfooter;
                $notesContentHeight = $this->displayNotes($pdf, $posy, $object, $outputlangs, true);
                if ($remainingSpaceforNotes > $notesContentHeight) {
                    $posy = $this->displayNotes($pdf, $posy, $object, $outputlangs, false);
                } else {
                    $this->_pagefoot($pdf, $object, $outputlangs, 1);
                    // New page
                    $pdf->AddPage();
                    if (!empty($tplidx)) {
                        $pdf->useTemplate($tplidx);
                    }
                    $pagenb++;
                    if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
                        $this->_pagehead($pdf, $object, 1, $outputlangs, $outputlangsbis, $pagenb);
                    }

                    $this->displayNotes($pdf, $posy, $object, $outputlangs, false);
                }

                $termsContentHeight = $this->displayTermsAndCondition($pdf, $object, true);
                $remainingSpaceforTerms = $this->page_hauteur - $pdf->GetY() - $heightforfooter;
                if ($remainingSpaceforTerms > $termsContentHeight) {
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
                        $this->_pagehead($pdf, $object, 1, $outputlangs, $outputlangsbis, $pagenb);
                    }

                    $posy = $this->displayTermsAndCondition($pdf, $object, false);
                }
                $remainingforRegards = $this->page_hauteur - $pdf->GetY() - $heightforfooter;
                $heightOfRegards = $this->displayRegards($pdf, $object, true);
                if ($remainingforRegards > $heightOfRegards) {
                    $posy = $this->displayRegards($pdf, $object, false);
                } else {
                    $this->_pagefoot($pdf, $object, $outputlangs, 1);
                    // New page
                    $pdf->AddPage();
                    if (!empty($tplidx)) {
                        $pdf->useTemplate($tplidx);
                    }
                    $pagenb++;
                    if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) {
                        $this->_pagehead($pdf, $object, 1, $outputlangs, $outputlangsbis, $pagenb);
                    }

                    $posy = $this->displayRegards($pdf, $object, false);
                }

                $this->_pagefoot($pdf, $object, $outputlangs);
                if (method_exists($pdf, 'AliasNbPages')) {
                    $pdf->AliasNbPages();
                }

                // $remainingSpaceRegards = $pdf->getPageHeight() - $posy;
                // $heightForRegards = 4 * 4 + 5 * 3;

                // if ($remainingSpaceRegards < $heightForRegards) {
                //     $pdf->AddPage();
                //     $pagenb++;
                //     $posy = 15;
                // }
                // $this->_pagehead($pdf, $object, 1, $outputlangs, $outputlangsbis, $pagenb);

                // Display the Regards content
                // $posy = $this->displayRegards($pdf, $object, $posy, $outputlangs);

                // Pagefoot
                // $this->_pagefoot($pdf, $object, $outputlangs);
                // if (method_exists($pdf, 'AliasNbPages')) {
                //     $pdf->AliasNbPages();
                // }

                //If propal merge product PDF is active
                if (!empty($conf->global->PRODUIT_PDF_MERGE_PROPAL)) {
                    require_once DOL_DOCUMENT_ROOT . '/product/class/propalmergepdfproduct.class.php';

                    $already_merged = array();
                    foreach ($object->lines as $line) {
                        if (!empty($line->fk_product) && !(in_array($line->fk_product, $already_merged))) {
                            // Find the desire PDF
                            $filetomerge = new Propalmergepdfproduct($this->db);

                            if (getDolGlobalInt('MAIN_MULTILANGS')) {
                                $filetomerge->fetch_by_product($line->fk_product, $outputlangs->defaultlang);
                            } else {
                                $filetomerge->fetch_by_product($line->fk_product);
                            }

                            $already_merged[] = $line->fk_product;

                            $product = new Product($this->db);
                            $product->fetch($line->fk_product);

                            if ($product->entity != $conf->entity) {
                                $entity_product_file = $product->entity;
                            } else {
                                $entity_product_file = $conf->entity;
                            }

                            // If PDF is selected and file is not empty
                            if (count($filetomerge->lines) > 0) {
                                foreach ($filetomerge->lines as $linefile) {
                                    if (!empty($linefile->id) && !empty($linefile->file_name)) {
                                        if (getDolGlobalInt('PRODUCT_USE_OLD_PATH_FOR_PHOTO')) {
                                            if (isModEnabled("product")) {
                                                $filetomerge_dir = $conf->product->multidir_output[$entity_product_file] . '/' . get_exdir($product->id, 2, 0, 0, $product, 'product') . $product->id . "/photos";
                                            } elseif (isModEnabled("service")) {
                                                $filetomerge_dir = $conf->service->multidir_output[$entity_product_file] . '/' . get_exdir($product->id, 2, 0, 0, $product, 'product') . $product->id . "/photos";
                                            }
                                        } else {
                                            if (isModEnabled("product")) {
                                                $filetomerge_dir = $conf->product->multidir_output[$entity_product_file] . '/' . get_exdir(0, 0, 0, 0, $product, 'product');
                                            } elseif (isModEnabled("service")) {
                                                $filetomerge_dir = $conf->service->multidir_output[$entity_product_file] . '/' . get_exdir(0, 0, 0, 0, $product, 'product');
                                            }
                                        }

                                        dol_syslog(get_class($this) . ':: upload_dir=' . $filetomerge_dir, LOG_DEBUG);

                                        $infile = $filetomerge_dir . '/' . $linefile->file_name;
                                        if (file_exists($infile) && is_readable($infile)) {
                                            $pagecount = $pdf->setSourceFile($infile);
                                            for ($i = 1; $i <= $pagecount; $i++) {
                                                $tplIdx = $pdf->importPage($i);
                                                if ($tplIdx !== false) {
                                                    $s = $pdf->getTemplatesize($tplIdx);
                                                    $pdf->AddPage($s['h'] > $s['w'] ? 'P' : 'L');
                                                    $pdf->useTemplate($tplIdx);
                                                } else {
                                                    setEventMessages(null, array($infile . ' cannot be added, probably protected PDF'), 'warnings');
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                $pdf->Close();

                $pdf->Output($file, 'F');

                //Add pdfgeneration hook
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
                $this->error = $langs->trans("ErrorCanNotCreateDir", $dir);
                return 0;
            }
        } else {
            $this->error = $langs->trans("ErrorConstantNotDefined", "PROP_OUTPUTDIR");
            return 0;
        }
    }

    /**
     *  Show payments table
     *
     *  @param    TCPDF        $pdf            Object PDF
     *  @param  Propal        $object         Object proposal
     *  @param  int            $posy           Position y in PDF
     *  @param  Translate    $outputlangs    Object langs for output
     *  @return int                         <0 if KO, >0 if OK
     */
    protected function drawPaymentsTable(&$pdf, $object, $posy, $outputlangs)
    {
    }

    /**
     *   Show miscellaneous information (payment mode, payment term, ...)
     *
     *   @param        TCPDF        $pdf             Object PDF
     *   @param        Propal        $object            Object to show
     *   @param        int            $posy            Y
     *   @param        Translate    $outputlangs    Langs object
     *   @return    int                            Pos y
     */
    public function drawInfoTable(&$pdf, $object, $posy, $outputlangs)
    {
        global $conf, $mysoc;
        $default_font_size = pdf_getPDFFontSize($outputlangs);

        $pdf->SetFont('', '', $default_font_size - 1);

        $diffsizetitle = (empty($conf->global->PDF_DIFFSIZE_TITLE) ? 3 : $conf->global->PDF_DIFFSIZE_TITLE);

        // If France, show VAT mention if not applicable
        if ($this->emetteur->country_code == 'FR' && empty($mysoc->tva_assuj)) {
            $pdf->SetFont('', 'B', $default_font_size - $diffsizetitle);
            $pdf->SetXY($this->marge_gauche, $posy);
            $pdf->MultiCell(100, 3, $outputlangs->transnoentities("VATIsNotUsedForInvoice"), 0, 'L', 0);

            $posy = $pdf->GetY() + 4;
        }

        $posxval = 52;
        if (!empty($conf->global->MAIN_PDF_DATE_TEXT)) {
            $displaydate = "daytext";
        } else {
            $displaydate = "day";
        }

        // Show shipping date
        if (!empty($object->delivery_date)) {
            $outputlangs->load("sendings");
            $pdf->SetFont('', 'B', $default_font_size - $diffsizetitle);
            $pdf->SetXY($this->marge_gauche, $posy);
            $titre = $outputlangs->transnoentities("DateDeliveryPlanned") . ':';
            $pdf->MultiCell(80, 4, $titre, 0, 'L');
            $pdf->SetFont('', '', $default_font_size - $diffsizetitle);
            $pdf->SetXY($posxval, $posy);
            $dlp = dol_print_date($object->delivery_date, $displaydate, false, $outputlangs, true);
            $pdf->MultiCell(80, 4, $dlp, 0, 'L');

            $posy = $pdf->GetY() + 1;
        } elseif ($object->availability_code || $object->availability) { // Show availability conditions
            $pdf->SetFont('', 'B', $default_font_size - $diffsizetitle);
            $pdf->SetXY($this->marge_gauche, $posy);
            $titre = $outputlangs->transnoentities("AvailabilityPeriod") . ':';
            $pdf->MultiCell(80, 4, $titre, 0, 'L');
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('', '', $default_font_size - $diffsizetitle);
            $pdf->SetXY($posxval, $posy);
            $lib_availability = $outputlangs->transnoentities("AvailabilityType" . $object->availability_code) != ('AvailabilityType' . $object->availability_code) ? $outputlangs->transnoentities("AvailabilityType" . $object->availability_code) : $outputlangs->convToOutputCharset($object->availability);
            $lib_availability = str_replace('\n', "\n", $lib_availability);
            $pdf->MultiCell(80, 4, $lib_availability, 0, 'L');

            $posy = $pdf->GetY() + 1;
        }

        // Show delivery mode
        if (empty($conf->global->PROPOSAL_PDF_HIDE_DELIVERYMODE) && $object->shipping_method_id > 0) {
            $outputlangs->load("sendings");

            $shipping_method_id = $object->shipping_method_id;
            if (!empty($conf->global->SOCIETE_ASK_FOR_SHIPPING_METHOD) && !empty($this->emetteur->shipping_method_id)) {
                $shipping_method_id = $this->emetteur->shipping_method_id;
            }
            $shipping_method_code = dol_getIdFromCode($this->db, $shipping_method_id, 'c_shipment_mode', 'rowid', 'code');
            $shipping_method_label = dol_getIdFromCode($this->db, $shipping_method_id, 'c_shipment_mode', 'rowid', 'libelle');

            $pdf->SetFont('', 'B', $default_font_size - $diffsizetitle);
            $pdf->SetXY($this->marge_gauche, $posy);
            $titre = $outputlangs->transnoentities("SendingMethod") . ':';
            $pdf->MultiCell(43, 4, $titre, 0, 'L');

            $pdf->SetFont('', '', $default_font_size - $diffsizetitle);
            $pdf->SetXY($posxval, $posy);
            $lib_condition_paiement = ($outputlangs->transnoentities("SendingMethod" . strtoupper($shipping_method_code)) != "SendingMethod" . strtoupper($shipping_method_code)) ? $outputlangs->trans("SendingMethod" . strtoupper($shipping_method_code)) : $shipping_method_label;
            $lib_condition_paiement = str_replace('\n', "\n", $lib_condition_paiement);
            $pdf->MultiCell(67, 4, $lib_condition_paiement, 0, 'L');

            $posy = $pdf->GetY() + 1;
        }

        // Show payments conditions
        if (empty($conf->global->PROPOSAL_PDF_HIDE_PAYMENTTERM) && $object->cond_reglement_code) {
            $pdf->SetFont('', 'B', $default_font_size - $diffsizetitle);
            $pdf->SetXY($this->marge_gauche, $posy);
            $titre = $outputlangs->transnoentities("PaymentConditions") . ':';
            $pdf->MultiCell(43, 4, $titre, 0, 'L');

            $pdf->SetFont('', '', $default_font_size - $diffsizetitle);
            $pdf->SetXY($posxval, $posy);
            $lib_condition_paiement = $outputlangs->transnoentities("PaymentCondition" . $object->cond_reglement_code) != ('PaymentCondition' . $object->cond_reglement_code) ? $outputlangs->transnoentities("PaymentCondition" . $object->cond_reglement_code) : $outputlangs->convToOutputCharset($object->cond_reglement_doc ? $object->cond_reglement_doc : $object->cond_reglement_label);
            $lib_condition_paiement = str_replace('\n', "\n", $lib_condition_paiement);
            if ($object->deposit_percent > 0) {
                $lib_condition_paiement = str_replace('__DEPOSIT_PERCENT__', $object->deposit_percent, $lib_condition_paiement);
            }
            $pdf->MultiCell(67, 4, $lib_condition_paiement, 0, 'L');

            $posy = $pdf->GetY() + 3;
        }

        if (empty($conf->global->PROPOSAL_PDF_HIDE_PAYMENTMODE)) {
            // Show payment mode
            if (
                $object->mode_reglement_code
                && $object->mode_reglement_code != 'CHQ'
                && $object->mode_reglement_code != 'VIR'
            ) {
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

            // Show payment mode CHQ
            if (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'CHQ') {
                // Si mode reglement non force ou si force a CHQ
                if (getDolGlobalInt('FACTURE_CHQ_NUMBER')) {
                    if ($conf->global->FACTURE_CHQ_NUMBER > 0) {
                        $account = new Account($this->db);
                        $account->fetch(getDolGlobalInt('FACTURE_CHQ_NUMBER'));

                        $pdf->SetXY($this->marge_gauche, $posy);
                        $pdf->SetFont('', 'B', $default_font_size - $diffsizetitle);
                        $pdf->MultiCell(100, 3, $outputlangs->transnoentities('PaymentByChequeOrderedTo', $account->proprio), 0, 'L', 0);
                        $posy = $pdf->GetY() + 1;

                        if (empty($conf->global->MAIN_PDF_HIDE_CHQ_ADDRESS)) {
                            $pdf->SetXY($this->marge_gauche, $posy);
                            $pdf->SetFont('', '', $default_font_size - $diffsizetitle);
                            $pdf->MultiCell(100, 3, $outputlangs->convToOutputCharset($account->owner_address), 0, 'L', 0);
                            $posy = $pdf->GetY() + 2;
                        }
                    }
                    if (getDolGlobalInt('FACTURE_CHQ_NUMBER') == -1) {
                        $pdf->SetXY($this->marge_gauche, $posy);
                        $pdf->SetFont('', 'B', $default_font_size - $diffsizetitle);
                        $pdf->MultiCell(100, 3, $outputlangs->transnoentities('PaymentByChequeOrderedTo', $this->emetteur->name), 0, 'L', 0);
                        $posy = $pdf->GetY() + 1;

                        if (empty($conf->global->MAIN_PDF_HIDE_CHQ_ADDRESS)) {
                            $pdf->SetXY($this->marge_gauche, $posy);
                            $pdf->SetFont('', '', $default_font_size - $diffsizetitle);
                            $pdf->MultiCell(100, 3, $outputlangs->convToOutputCharset($this->emetteur->getFullAddress()), 0, 'L', 0);
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
     *    Show total to pay
     *
     *    @param    TCPDF        $pdf            Object PDF
     *    @param  Propal        $object         Object proposal
     *    @param  int            $deja_regle     Amount already paid (in the currency of invoice)
     *    @param    int            $posy            Position depart
     *    @param    Translate    $outputlangs    Objet langs
     *  @param  Translate    $outputlangsbis    Object lang for output bis
     *    @return int                            Position pour suite
     */
    protected function drawTotalTable(&$pdf, $object, $deja_regle, $posy, $outputlangs, $outputlangsbis = null)
    {
        global $conf, $mysoc, $hookmanager;

        $default_font_size = pdf_getPDFFontSize($outputlangs);

        if (!empty($conf->global->PDF_USE_ALSO_LANGUAGE_CODE) && $outputlangs->defaultlang != $conf->global->PDF_USE_ALSO_LANGUAGE_CODE) {
            $outputlangsbis = new Translate('', $conf);
            $outputlangsbis->setDefaultLang($conf->global->PDF_USE_ALSO_LANGUAGE_CODE);
            $outputlangsbis->loadLangs(array("main", "dict", "companies", "bills", "products", "propal"));
            $default_font_size--;
        }

        $tab2_top = $posy;
        $tab2_hl = 4;
        $pdf->SetFont('', '', $default_font_size - 1);

        // Total table
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
        $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("TotalHT") . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transnoentities("TotalHT") : ''), 0, 'L', 1);

        $total_ht = ((isModEnabled("multicurrency") && isset($object->multicurrency_tx) && $object->multicurrency_tx != 1) ? $object->multicurrency_total_ht : $object->total_ht);
        $pdf->SetXY($col2x, $tab2_top);
        $pdf->MultiCell($largcol2, $tab2_hl, price($total_ht + (!empty($object->remise) ? $object->remise : 0), 0, $outputlangs), 0, 'R', 1);

        // Show VAT by rates and total
        $pdf->SetFillColor(248, 248, 248);

        $total_ttc = (isModEnabled("multicurrency") && $object->multicurrency_tx != 1) ? $object->multicurrency_total_ttc : $object->total_ttc;

        $this->atleastoneratenotnull = 0;
        if (empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT)) {
            $tvaisnull = ((!empty($this->tva) && count($this->tva) == 1 && isset($this->tva['0.000']) && is_float($this->tva['0.000'])) ? true : false);
            if (!empty($conf->global->MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_IFNULL) && $tvaisnull) {
                // Nothing to do
            } else {
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

                // VAT
                foreach ($this->tva as $tvakey => $tvaval) {
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
                        $totalvat .= vatrate($tvakey, 1) . $tvacompl;
                        $pdf->MultiCell($col2x - $col1x, $tab2_hl, $totalvat, 0, 'L', 1);

                        $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
                        $pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);
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
                //}

                // Total TTC
                $index++;
                $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
                $pdf->SetTextColor(0, 0, 60);
                $pdf->SetFillColor(224, 224, 224);
                $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("TotalTTC") . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transnoentities("TotalTTC") : ''), $useborder, 'L', 1);

                $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
                $pdf->MultiCell($largcol2, $tab2_hl, price($total_ttc, 0, $outputlangs), $useborder, 'R', 1);
            }
        }

        $pdf->SetTextColor(0, 0, 0);

        $resteapayer = 0;
        /*
        $resteapayer = $object->total_ttc - $deja_regle;
        if (!empty($object->paye)) $resteapayer=0;
         */

        if ($deja_regle > 0) {
            // Already paid + Deposits
            $index++;

            $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
            $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("AlreadyPaid") . (is_object($outputlangsbis) ? ' / ' . $outputlangsbis->transnoentities("AlreadyPaid") : ''), 0, 'L', 0);

            $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
            $pdf->MultiCell($largcol2, $tab2_hl, price($deja_regle, 0, $outputlangs), 0, 'R', 0);

            /*
            if ($object->close_code == 'discount_vat')
            {
            $index++;
            $pdf->SetFillColor(255,255,255);

            $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
            $pdf->MultiCell($col2x - $col1x, $tab2_hl, $outputlangs->transnoentities("EscompteOfferedShort"), $useborder, 'L', 1);

            $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
            $pdf->MultiCell($largcol2, $tab2_hl, price($object->total_ttc - $deja_regle, 0, $outputlangs), $useborder, 'R', 1);

            $resteapayer=0;
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
        return ($tab2_top + ($tab2_hl * $index));
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

        // Amount in (at tab_top - 1)
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('', '', $default_font_size - 2);

        if (empty($hidetop)) {
            // $conf->global->MAIN_PDF_TITLE_BACKGROUND_COLOR='230,230,230';
            if (!empty($conf->global->MAIN_PDF_TITLE_BACKGROUND_COLOR)) {
                $pdf->Rect($this->marge_gauche, $tab_top, $this->page_largeur - $this->marge_droite - $this->marge_gauche, $this->tabTitleHeight, 'F', null, explode(',', $conf->global->MAIN_PDF_TITLE_BACKGROUND_COLOR));
            }
        }

        $pdf->SetDrawColor(128, 128, 128);
        $pdf->SetFont('', '', $default_font_size - 1);

        // Output Rect
        $this->printRect($pdf, $this->marge_gauche, $tab_top, $this->page_largeur - $this->marge_gauche - $this->marge_droite, $tab_height, $hidetop, $hidebottom); // Rect takes a length in 3rd parameter and 4th parameter

        $this->pdfTabTitles($pdf, $tab_top, $tab_height, $outputlangs, $hidetop);

        if (empty($hidetop)) {
            $pdf->line($this->marge_gauche, $tab_top + $this->tabTitleHeight, $this->page_largeur - $this->marge_droite, $tab_top + $this->tabTitleHeight); // line takes a position y in 2nd parameter and 4th parameter
        }
    }

    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
    /**
     *  Show top header of page.
     *
     *  @param    TCPDF        $pdf             Object PDF
     *  @param  Propal        $object         Object to show
     *  @param  int            $showaddress    0=no, 1=yes
     *  @param  Translate    $outputlangs    Object lang for output
     *  @param  Translate    $outputlangsbis    Object lang for output bis
     *  @return    float|int
     */
    protected function _pagehead(&$pdf, $object, $showaddress, $outputlangs, $outputlangsbis = null, $pagenb)
    {
        global $conf, $langs;

        $ltrdirection = 'L';
        if ($outputlangs->trans("DIRECTION") == 'rtl') {
            $ltrdirection = 'R';
        }

        // Load traductions files required by page
        $outputlangs->loadLangs(array("main", "propal", "companies", "bills"));

        $default_font_size = pdf_getPDFFontSize($outputlangs);

        pdf_pagehead($pdf, $outputlangs, $this->page_hauteur);

        $pdf->SetTextColor(0, 0, 60);
        $pdf->SetFont('', 'B', $default_font_size + 3);

        $w = 100;

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

        // $posy += 3;
        // $pdf->SetFont('', '', $default_font_size - 2);

        // if ($object->ref_client) {
        //     $posy += 4;
        //     $pdf->SetXY($posx, $posy);
        //     $pdf->SetTextColor(0, 0, 60);
        //     $pdf->MultiCell($w, 3, $outputlangs->transnoentities("RefCustomer") . " : " . dol_trunc($outputlangs->convToOutputCharset($object->ref_client), 65), '', 'R');
        // }

        // if (!empty($conf->global->PDF_SHOW_PROJECT_TITLE)) {
        //     $object->fetch_projet();
        //     if (!empty($object->project->ref)) {
        //         $posy += 3;
        //         $pdf->SetXY($posx, $posy);
        //         $pdf->SetTextColor(0, 0, 60);
        //         $pdf->MultiCell($w, 3, $outputlangs->transnoentities("Project") . " : " . (empty($object->project->title) ? '' : $object->project->title), '', 'R');
        //     }
        // }

        // if (!empty($conf->global->PDF_SHOW_PROJECT)) {
        //     $object->fetch_projet();
        //     if (!empty($object->project->ref)) {
        //         $outputlangs->load("projects");
        //         $posy += 3;
        //         $pdf->SetXY($posx, $posy);
        //         $pdf->SetTextColor(0, 0, 60);
        //         $pdf->MultiCell($w, 3, $outputlangs->transnoentities("RefProject") . " : " . (empty($object->project->ref) ? '' : $object->project->ref), '', 'R');
        //     }
        // }

        // if (!empty($conf->global->MAIN_PDF_DATE_TEXT)) {
        //     $displaydate = "daytext";
        // } else {
        //     $displaydate = "day";
        // }

        //$posy += 4;
        // $posy = $pdf->getY();
        // $pdf->SetXY($posx, $posy);
        // $pdf->SetTextColor(0, 0, 60);
        // $pdf->MultiCell($w, 3, $outputlangs->transnoentities("Date") . " : " . dol_print_date($object->date, $displaydate, false, $outputlangs, true), '', 'R');

        // $posy += 4;
        // $pdf->SetXY($posx, $posy);
        // $pdf->SetTextColor(0, 0, 60);

        // $title = $outputlangs->transnoentities("DateEndPropal");
        // if (!empty($conf->global->PDF_USE_ALSO_LANGUAGE_CODE) && is_object($outputlangsbis)) {
        //     $title .= ' - ' . $outputlangsbis->transnoentities("DateEndPropal");
        // }
        // $pdf->MultiCell($w, 3, $title . " : " . dol_print_date($object->fin_validite, $displaydate, false, $outputlangs, true), '', 'R');

        // if (empty($conf->global->MAIN_PDF_HIDE_CUSTOMER_CODE) && $object->thirdparty->code_client) {
        //     $posy += 4;
        //     $pdf->SetXY($posx, $posy);
        //     $pdf->SetTextColor(0, 0, 60);
        //     $pdf->MultiCell($w, 3, $outputlangs->transnoentities("CustomerCode") . " : " . $outputlangs->transnoentities($object->thirdparty->code_client), '', 'R');
        // }

        // // Get contact
        // if (!empty($conf->global->DOC_SHOW_FIRST_SALES_REP)) {
        //     $arrayidcontact = $object->getIdContact('internal', 'SALESREPFOLL');
        //     if (count($arrayidcontact) > 0) {
        //         $usertmp = new User($this->db);
        //         $usertmp->fetch($arrayidcontact[0]);
        //         $posy += 4;
        //         $pdf->SetXY($posx, $posy);
        //         $pdf->SetTextColor(0, 0, 60);
        //         $pdf->MultiCell($w, 3, $langs->transnoentities("SalesRepresentative") . " : " . $usertmp->getFullName($langs), '', 'R');
        //     }
        // }

        $posy += 2;

        $top_shift = 0;
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
        $pdf->SetLineWidth(0.2);
        $separatorY = $pdf->GetY() + 2;
        $pdf->Line($this->marge_gauche, $separatorY, $this->page_largeur - $this->marge_droite, $separatorY);
        $pdf->SetLineWidth(0.2);
        $invoice_obj = new stdClass();
        $sql_llx_facture = "SELECT * FROM " . MAIN_DB_PREFIX . "propal WHERE rowid = $object->id";
        $res_llx_facture = $this->db->query($sql_llx_facture);

        if ($res_llx_facture) {
            while ($row = $this->db->fetch_object($res_llx_facture)) {
                $originalDate = $row->datep;
                $dateTime = new DateTime($originalDate);
                $formattedDate = $dateTime->format('d M Y');
                $invoice_obj->dateValue = $formattedDate;
                $invoice_obj->company_rowid = $row->fk_soc;
                $invoice_obj->division = $row->division;
                $invoice_obj->projectid = $row->fk_projet;
                $invoice_obj->vendorNO = $row->vendor_no;
                $invoice_obj->contact = $row->contact_person;
                $invoice_obj->tellNo = $row->cell;
                $invoice_obj->email = $row->email;
                $invoice_obj->clientVat = $row->client_vat;
                $invoice_obj->poNo = $row->po_no;
                $invoice_obj->quoteNo = $row->ref;
                $invoice_obj->ourVatNo = $row->vat_no;
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

        // Add TAX INVOICE heading
        if ($pagenb === 1) {
            $pdf->SetFont('', 'B', 12); // Set bold font with size 14
            $pdf->Cell(0, 10, 'QUOTATION', 0, 1, 'C'); // Centered heading
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
            $pdf->Cell(0, 5, 'Contact: ' . $invoice_obj->contact, 'L', 0, 'L');
            $pdf->Cell(0, 5, '                 ', 'R', 1, 'R');
            $pdf->Cell(0, 5, 'Tel No: ' . $invoice_obj->tellNo, 'L', 0, 'L');
            $pdf->Cell(0, 5, 'Quote No.: ' . $invoice_obj->quoteNo, 'R', 1, 'R');
            $pdf->Cell(0, 5, 'Email: ' . $invoice_obj->email, 'L', 0, 'L');
            $pdf->Cell(0, 5, '                  ', 'R', 1, 'R');
            $pdf->Cell(0, 5, 'Client Vat: ' . $invoice_obj->clientVat, 'LB', 0, 'L');
            $pdf->Cell(0, 5, 'Our Vat No.: ' . $invoice_obj->ourVatNo, 'RB', 1, 'R');
        }
        $pdf->SetTextColor(0, 0, 0);
        return $top_shift;
    }

    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.PublicUnderscore
    /**
     *       Show footer of page. Need this->emetteur object
     *
     *       @param    TCPDF        $pdf                 PDF
     *         @param    Propal        $object                Object to show
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
        return pdf_pagefoot($pdf, $outputlangs, 'PROPOSAL_FREE_TEXT', $this->emetteur, $this->marge_basse, $this->marge_gauche, $this->page_hauteur, $object, $showdetails, $hidefreetext, $this->page_largeur, $this->watermark);
    }

    /**
     *    Show area for the customer to sign
     *
     *    @param    TCPDF        $pdf            Object PDF
     *    @param  Propal        $object         Object proposal
     *    @param    int            $posy            Position depart
     *    @param    Translate    $outputlangs    Objet langs
     *    @return int                            Position pour suite
     */
    protected function drawSignatureArea(&$pdf, $object, $posy, $outputlangs)
    {
        global $conf;
        $default_font_size = pdf_getPDFFontSize($outputlangs);
        $tab_top = $posy + 4;
        $tab_hl = 4;

        $posx = 120;
        $largcol = ($this->page_largeur - $this->marge_droite - $posx);
        $useborder = 0;
        $index = 0;
        // Total HT
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetXY($posx, $tab_top);
        $pdf->SetFont('', '', $default_font_size - 2);
        $pdf->MultiCell($largcol, $tab_hl, $outputlangs->transnoentities("ProposalCustomerSignature"), 0, 'L', 1);

        $pdf->SetXY($posx, $tab_top + $tab_hl);
        $pdf->MultiCell($largcol, $tab_hl * 3, '', 1, 'R');
        if (!empty($conf->global->MAIN_PDF_PROPAL_USE_ELECTRONIC_SIGNING)) {
            $pdf->addEmptySignatureAppearance($posx, $tab_top + $tab_hl, $largcol, $tab_hl * 3);
        }

        return ($tab_hl * 7);
    }

    // to display note
    public function displayNotes(&$pdf, $posy, $object, $outputlangs, $calculateHeightOnly = false)
    {
        $default_font_size = pdf_getPDFFontSize($outputlangs);
        $lineHeight = 5;
        $marginTop = 5;

        $sql_llx_facture = "SELECT * FROM " . MAIN_DB_PREFIX . "propal WHERE rowid = $object->id";
        $res_llx_facture = $this->db->query($sql_llx_facture);

        if ($res_llx_facture) {
            while ($row = $this->db->fetch_object($res_llx_facture)) {
                $notes = json_decode($row->notes, true);
            }
        }

        // Define your terms and conditions content
        if (isset($notes) && is_array($notes)) {

            $notesContent = "Notes :";
            foreach ($notes as $index => $condition) {
                $notesContent .= "\n" . str_repeat(' ', 6) . ($index + 1) . ". " . $condition . ".";
            }
        }

        $notesLines = explode("\n", $notesContent);

        $pdf->SetFont('', '', $default_font_size - 1);

        // Calculate the height of the notes content
        $notesContentHeight = count($notesLines) * $lineHeight + $marginTop;

        // Iterate through each line of notes and add it to the PDF
        if (!$calculateHeightOnly) {
            $pdf->SetY($pdf->GetY() + $marginTop);
            foreach ($notesLines as $line) {
                $pdf->MultiCell(0, $lineHeight, $line, 0, 'L', 0);
            }
        }
        return $notesContentHeight;
    }

    // to display terms ad conditions
    public function displayTermsAndCondition(&$pdf, $object, $calculateHeightOnly = false)
    {
        $lineHeight = 5;
        $marginTop = 5;
        $sql_llx_propal = "SELECT * FROM " . MAIN_DB_PREFIX . "propal WHERE rowid = $object->id";
        $res_llx_propal = $this->db->query($sql_llx_propal);

        if ($res_llx_propal) {
            while ($row = $this->db->fetch_object($res_llx_propal)) {
                $terms_and_conditions = json_decode($row->terms_and_conditions, true);
            }
        }

        // Define your terms and conditions content
        if (isset($terms_and_conditions) && is_array($terms_and_conditions)) {
            $termsContent = "Terms and Conditions";

            foreach ($terms_and_conditions as $index => $condition) {
                $termsContent .= "\n" . str_repeat(' ', 9) . ($index + 1) . ". " . $condition . ".";
            }
        }

        // Explode the terms content into an array of lines
        $termsLines = explode("\n", $termsContent);

        $termsContentHeight = count($termsLines) * $lineHeight + $marginTop;

        // Iterate through each line of terms and add it to the PDF
        if (!$calculateHeightOnly) {
            // Iterate through each line of terms and add it to the PDF
            $pdf->SetY($pdf->GetY() + $marginTop);
            foreach ($termsLines as $line) {
                $pdf->MultiCell(0, $lineHeight, $line, 0, 'L', 0);
            }
        }
        return $termsContentHeight;
    }

    // to display regards
    public function displayRegards(&$pdf, $object, $calculateHeightOnly = false)
    {
        // $contentHeight;
        $initialY = $pdf->GetY();
        $marginTop = 3;
        $lineHeight = 8;
        $pdf->SetY($initialY + $marginTop);
        // to display message
        if (!$calculateHeightOnly) {
            $pdf->MultiCell(0, $lineHeight, "We trust our offer meets your requirements.Should you require any additional information, please do not hesitate to call upon the undersigned.", 0, 'L');
            $pdf->MultiCell(0, $lineHeight, "With Kind Regards", 0, 'L');
            $pdf->MultiCell(0, $lineHeight, "Atul Rajgure.", 0, 'L');
            $pdf->MultiCell(0, $lineHeight, "Cell: +27 83 268 8819.", 0, 'L');
        }
        $finalY = $pdf->GetY();
        $contentHeight = $finalY - $initialY + $lineHeight * 4;
        return $contentHeight;
    }

    /**
     *       Define Array Column Field
     *
     *       @param    Propal            $object            object proposal
     *       @param    Translate        $outputlangs    langs
     *      @param    int                $hidedetails    Do not show line details
     *      @param    int                $hidedesc        Do not show desc
     *      @param    int                $hideref        Do not show ref
     *      @return    void
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
            'width' => 11, // in mm
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

        if (!empty($conf->global->MAIN_GENERATE_PROPOSALS_WITH_PICTURE) && !empty($this->atleastonephoto)) {
            $this->cols['photo']['status'] = true;
            $this->cols['photo']['border-left'] = true;
        }

        // $rank = $rank + 10;
        // $this->cols['vat'] = array(
        //     'rank' => $rank,
        //     'status' => false,
        //     'width' => 16, // in mm
        //     'title' => array(
        //         'textkey' => 'VAT'
        //     ),
        //     'border-left' => true, // add left line separator
        // );

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
}
