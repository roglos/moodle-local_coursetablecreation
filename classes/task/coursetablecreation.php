<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * A scheduled task for scripted database integrations.
 *
 * @package    local_coursetablecreation
 * @copyright  2016 ROelmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursetablecreation\task;
use stdClass;

defined('MOODLE_INTERNAL') || die();

/**
 * A scheduled task for scripted database integrations.
 *
 * @copyright  2016 ROelmann
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class coursetablecreation extends \core\task\scheduled_task {

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('coursetablecreation', 'local_coursetablecreation');
    }

    /**
     * Run sync.
     */
    public function execute() {
        global $DB; // Ensures use of Moodle data-manipulation api.

        /* Clear main course/module creation table.
         * ---------------------------------------- */
        $tablename = 'usr_ro_modules';
        /***************************************
         * usr_ro_modules                      *
         *     id                              *
         *     course_fullname                 *
         *     course_shortname                *
         *     course_idnumber                 *
         *     category_idnumber               * To be removed
         *     category_id                     *
         ***************************************/
        $sql = 'TRUNCATE '.$tablename;
        $DB->execute($sql);

        /* Overarching/Domain pages.
         * ------------------------- */
        // Array of overarching category data.
        $sourcetable1 = 'usr_data_categories';
        /***************************************
         * usr_data_categories                 *
         *     id                              *
         *     category_name                   *
         *     category_idnumber               *
         *     parent_cat_idnumber             *
         *     deleted                         *
         ***************************************/
        $sql = 'SELECT * FROM ' . $sourcetable1;
        $pages = array();
        $pages = $DB->get_records_sql($sql);

        // Loop through categories array to create course site details for each category.
        foreach ($pages as $page) {
            $fullnameold = $page->category_name;
            // Remove ' from fullname if present (prevents issues with sql line).
            $fullname = str_replace("'", "", $fullnameold);
            // Category sites do not have both shortname and idnumber so use category idnumber for both.
            // Prefix with CRS for ease of identifying in front end UI and Db searches.
            $shortname = 'CRS-' . $page->category_idnumber;
            $idnumber = 'CRS-' . $page->category_idnumber;
            $categoryidnumber = $page->category_idnumber;
            // Get category id for the relevant category idnumber - this is what is needed in the table.
            $category = $DB->get_record('course_categories', array('idnumber' => $categoryidnumber));
            $categoryid = $category->id;
            // Set new coursesite in table by inserting the data created above.
            $sql = "INSERT INTO " . $tablename . " (course_fullname,course_shortname,course_idnumber,category_id)
                VALUES ('" . $fullname . "','" . $shortname . "','" . $idnumber . "','" .$categoryid . "')";
            $DB->execute($sql);
        }

        /* Taught Module Pages
         * ------------------- */
        // Array of taught module/MAV pages for creation.
        $sourcetable3 = 'usr_data_courses';
        /***************************************
         * usr_data_courses                    *
         *     id                              *
         *     course_fullname                 *
         *     course_shortname                *
         *     course_idnumber                 *
         *     course_startdate                *
         *     category_idnumber               *
         ***************************************/
        $sql = 'SELECT * FROM ' . $sourcetable3;
        $modpages = array();
        $modpages = $DB->get_records_sql($sql);

        // Loop through MAV list array to create site details for each one.
        foreach ($modpages as $modpage) {
            $modfullnameold = $modpage->course_fullname;
            // Remove ' from fullname if present (prevents issues with sql line).
            $modfullnametwo = str_replace("'", "", $modfullnameold);
            $modfullname = str_replace("?", "", $modfullnametwo);

            // Find the category id using the category idnumber. id needed for table.
            $modcategoryidnumber = $modpage->category_idnumber;
            if (!$DB->get_record('course_categories', array('idnumber' => $modcategoryidnumber))) {
                $modcategory = $DB->get_record('course_categories', array('name' => 'Miscellaneous'));
            } else {
                $modcategory = $DB->get_record('course_categories', array('idnumber' => $modcategoryidnumber));
            }
            // Set new module site in table by inserting the data retrieved above.
            $sql = "INSERT INTO " . $tablename . " (course_fullname,course_shortname,course_idnumber,category_id)
                VALUES ('" . $modfullname . "','" . $modpage->course_shortname . "','" .
                $modpage->course_idnumber . "','" . $modcategory->id . "')";
            echo $sql;
            $DB->execute($sql);
        }

        /* Staff Sandbox Pages.
         * -------------------- */
        // Find id of the staff_SB category. If there isn't one then bypass whole section.
        if ($DB->get_record('course_categories', array('idnumber' => 'staff_SB'))) {
            $sbcategory = $DB->get_record('course_categories',
                            array('idnumber' => 'staff_SB'));

            // Array of staff user data.
            $sourcetable2 = 'user'; // No data definition as this is a standard mdl table.
            $sandoxes = array();
            $select = "email LIKE '%@glos.ac.uk'"; // Pattern match for staff email accounts.
            $sandboxes = $DB->get_records_select($sourcetable2, $select);

            // Loop through all staff accounts.
            foreach ($sandboxes as $sandbox) {
                // Create fullname, shortname and idnumber for use in table. SB- for easy identifying in UI/Db.
                $sbfull = 'Sandbox Page: ' . $sandbox->username . ' - ' . $sandbox->firstname . ' ' . $sandbox->lastname;
                $sbshort = 'SB-' . $sandbox->username;
                $sbidnumber = 'SB-' . $sandbox->username;
                // Set new staff sandbox in table by inserting the data created above.
                $sql4 = "INSERT INTO " . $tablename . " (course_fullname,course_shortname,course_idnumber,category_id)
                    VALUES ('" . $sbfull . "','" . $sbshort . "','" . $sbidnumber . "','" .$sbcategory->id . "')";
                $DB->execute($sql4);
            }
        }

    }
}
