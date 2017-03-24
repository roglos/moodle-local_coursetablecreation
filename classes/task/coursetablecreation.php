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
        global $DB;
        /* Get course data and UPDATE to include category id.
         * ================================================== */

        // Clear main course/module creation table.
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

        /* Overarching/Domain pages
         * ------------------------ */
        // Array of overarching course or domain data.
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

        foreach($pages as $page) {
            $fullname_old = $page->category_name;
            $fullname = str_replace("'", "", $fullname_old); // Remove ' from fullname if present.
            $shortname = 'CRS-' . $page->category_idnumber;
            $idnumber = 'CRS-' . $page->category_idnumber;
            $categoryidnumber = $page->category_idnumber;
            $category = $DB->get_record('course_categories', array('idnumber'=>$categoryidnumber));
            $categoryid = $category->id;
            // Set new coursesite in table by inserting the data created above.
            $sql = "INSERT INTO " . $tablename . " (course_fullname,course_shortname,course_idnumber,category_id)
                VALUES ('" . $fullname . "','" . $shortname . "','" . $idnumber . "','" .$categoryid . "')";
            $DB->execute($sql);
        }

        /* Taught Module Pages
         * ------------------- */
        // Array of overarching course or domain data.
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

        foreach($modpages as $modpage) {
            $modfullname_old = $modpage->course_fullname;
            $modfullname = str_replace("'", "", $modfullname_old); // Remove ' from fullname if present.
            $modshortname = $modpage->course_shortname;
            $modidnumber = $modpage->course_idnumber;
            $modcategoryidnumber = $modpage->category_idnumber;
            $modcategory = $DB->get_record('course_categories', array('idnumber'=>$modcategoryidnumber));
            $modcategoryid = $modcategory->id;
            // Set new coursesite in table by inserting the data created above.
            $sql = "INSERT INTO " . $tablename . " (course_fullname,course_shortname,course_idnumber,category_id)
                VALUES ('" . $modfullname . "','" . $modshortname . "','" . $modidnumber . "','" .$modcategoryid . "')";
            $DB->execute($sql);
        }

        /* Staff Sandbox Pages
         * ------------------- */
        // Array of staff user data.
        $sourcetable2 = 'user';
        $select = "email LIKE '%@glos.ac.uk'";
        $sandoxes = array();
        $sandboxes = $DB->get_records_select($sourcetable2,$select);
        $sbcategory = $DB->get_record('course_categories',
                            array('idnumber' => 'staff_SB')); // Check idnumber of Sandbox category on live system
        foreach($sandboxes as $sandbox) {
            $newsandboxdata = array();
            $sbfull = 'Sandbox Page: ' . $sandbox->idnumber . ' - ' . $sandbox->firstname . ' ' . $sandbox->lastname;
            $sbshort = 'SB_' . $sandbox->idnumber;
            $sbidnumber = 'SB_' . $sandbox->idnumber;
            // Set new staff sandbox in table by inserting the data created above.
            $sql4 = "INSERT INTO " . $tablename . " (course_fullname,course_shortname,course_idnumber,category_id)
                VALUES ('" . $sbfull . "','" . $sbshort . "','" . $sbidnumber . "','" .$sbcategory->id . "')";
            $DB->execute($sql4);
        }


    }
}
