<?php
/* Copyright (C) 2005-2008 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2009 Regis Houssin        <regis.houssin@inodbox.com>
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
 *        \file       htdocs/core/modules/supplier_order/mod_commande_fournisseur_muguet.php
 *        \ingroup    commande
 *        \brief      Fichier contenant la classe du modele de numerotation de reference de commande fournisseur Muguet
 */

require_once DOL_DOCUMENT_ROOT . '/core/modules/supplier_order/modules_commandefournisseur.php';

/**
 *    Classe du modele de numerotation de reference de commande fournisseur Muguet
 */
class mod_commande_fournisseur_muguet extends ModeleNumRefSuppliersOrders
{
    /**
     * Dolibarr version of the loaded document
     * @var string
     */
    public $version = 'dolibarr'; // 'development', 'experimental', 'dolibarr'

    /**
     * @var string Error code (or message)
     */
    public $error = '';

    /**
     * @var string Nom du modele
     * @deprecated
     * @see $name
     */
    public $nom = 'Muguet';

    /**
     * @var string model name
     */
    public $name = 'Muguet';

    public $prefix = 'CF';

    /**
     * Constructor
     */
    public function __construct()
    {
        global $conf;

        if ((float) $conf->global->MAIN_VERSION_LAST_INSTALL >= 5.0) {
            $this->prefix = 'FVAO'; // We use correct standard code "PO = Purchase Order"
        }
    }

    /**
     *     Return description of numbering module
     *
     *  @return     string      Text with description
     */
    public function info()
    {
        global $langs;
        return $langs->trans("SimpleNumRefModelDesc", $this->prefix);
    }

    /**
     *     Return an example of numbering
     *
     *  @return     string      Example
     */
    public function getExample()
    {
        return $this->prefix . "0501-0001";
    }

    /**
     *  Checks if the numbers already in the database do not
     *  cause conflicts that would prevent this numbering working.
     *
     *  @return     boolean     false if conflict, true if ok
     */
    public function canBeActivated()
    {
        global $conf, $langs, $db;

        $coyymm = '';
        $max = '';

        $posindice = strlen($this->prefix) + 6;
        $sql = "SELECT MAX(CAST(SUBSTRING(ref FROM " . $posindice . ") AS SIGNED)) as max";
        $sql .= " FROM " . MAIN_DB_PREFIX . "commande_fournisseur";
        $sql .= " WHERE ref LIKE '" . $db->escape($this->prefix) . "____-%'";
        $sql .= " AND entity = " . $conf->entity;
        $resql = $db->query($sql);
        if ($resql) {
            $row = $db->fetch_row($resql);
            if ($row) {
                $coyymm = substr($row[0], 0, 6);
                $max = $row[0];
            }
        }
        if (!$coyymm || preg_match('/' . $this->prefix . '[0-9][0-9][0-9][0-9]/i', $coyymm)) {
            return true;
        } else {
            $langs->load("errors");
            $this->error = $langs->trans('ErrorNumRefModel', $max);
            return false;
        }
    }

    /**
     *     Return next value
     *
     *  @param    Societe        $objsoc     Object third party
     *  @param  Object        $object        Object
     *  @return string                  Value if OK, 0 if KO
     */
    public function getNextValue($objsoc = 0, $object = '')
    {
        global $db, $conf;

        print '<script>console.log("object: ' . $object->fk_project . '")</script>';
        // Get current date in the required format
        $date_old = $object->date; // This is po date (not creation date)

        // to get data from project table
        $sql_project_date = "SELECT * FROM " . MAIN_DB_PREFIX . "projet";
        $sql_project_date .= " WHERE rowid = " . $object->fk_project;
        $res_project_date = $db->query($sql_project_date);
        if ($res_project_date) {
            while ($row = $db->fetch_object($res_project_date)) {
                $date = $row->datec;
                $project_date = strtotime($date);
            }
        }
        $formatted_date = strftime("%Y%m%d", $project_date); // Change to get year, month, and day in the desired format
        // Check if there are existing PO for the current date
        $sql_count = "SELECT COUNT(*) as count_quotes FROM " . MAIN_DB_PREFIX . "commande_fournisseur";
        $sql_count .= " WHERE ref LIKE '" . $db->escape($this->prefix) . "-" . $formatted_date . "%'";
        $sql_count .= " AND entity IN (" . getEntity('ponumber', 1, $object) . ")";

        $res_count = $db->query($sql_count);
        if ($res_count) {
            $count_obj = $db->fetch_object($res_count);
            if ($count_obj && $count_obj->count_quotes > 0) {
                // If PO exist for the current date, generate a number in format FVAO-20231214-01, FVAO-20231214-02, etc.
                $num = $count_obj->count_quotes + 1 - 1;
                $formatted_num = sprintf("%02s", $num);
                $po_number = $this->prefix . "-" . $formatted_date . "-" . $formatted_num;
            } else {
                // If no PO exist for the current date, generate a number in format FVAO-20231214
                $po_number = $this->prefix . "-" . $formatted_date;
            }
        } else {
            dol_syslog(get_class($this) . "::getNextValue", LOG_DEBUG);
            return -1;
        }

        dol_syslog(get_class($this) . "::getNextValue return " . $po_number);
        return $po_number;
    }

    // phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
    /**
     *     Renvoie la reference de commande suivante non utilisee
     *
     *  @param    Societe        $objsoc     Object third party
     *  @param  Object        $object        Object
     *  @return string                  Descriptive text
     */
    public function commande_get_num($objsoc = 0, $object = '')
    {
        // phpcs:enable
        return $this->getNextValue($objsoc, $object);
    }
}
