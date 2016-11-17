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
        $sql = 'TRUNCATE '.$tablename;
        $DB->execute($sql);

        /* Overarching/Domain pages
         * ------------------------ */
        // Array of tables with module or overarching course or domain data.
        $sourcetable1 = 'usr_data_courses';

        // Write from provided domain/school/subject course list into main course creation list.
        $sql = 'INSERT INTO '.$tablename. ' (course_fullname,course_shortname,course_idnumber,category_idnumber)
                 SELECT course_fullname,course_shortname,course_idnumber,category_idnumber FROM '.$sourcetable1;
        $DB->execute($sql);

        // Update course creation list to add in category.id.
        $sql = 'UPDATE ' .$tablename. '
                INNER JOIN mdl_course_categories ON '.$tablename.'.category_idnumber = mdl_course_categories.idnumber
                SET category_id = mdl_course_categories.id';
        $DB->execute($sql);

        /* Sandbox pages
         * ------------- */
        // Add staff sandboxes to course creation list, based on staff usernames/emails.
        $staffsandboxcategoryidnumber = 'staff_SB';
        $sandboxcatid = $DB->get_record('course_categories', array('idnumber'=>'staff_SB'));
        echo $sandboxcatid->id;

        $sourcetable2 = 'mdl_user';
        $sql = 'INSERT INTO '.$tablename. ' (course_fullname,course_shortname,course_idnumber)
                SELECT username,CONCAT("sb_",username),CONCAT("sb_id_",username) FROM '.$sourcetable2. ' WHERE ' .$sourcetable2.
                '.email LIKE "%@glos%"';
        $DB->execute($sql);

        // Add staff sandbox category_idnumber and category_id to table
        $sql = "UPDATE " . $tablename . ", mdl_course_categories
                SET " . $tablename . ".category_idnumber = '" . $staffsandboxcategoryidnumber ."', " . $tablename . ".category_id = " . $sandboxcatid->id . "
                WHERE " . $tablename . ".course_idnumber LIKE '%sb%'";
        $DB->execute($sql);

        /* Update 'course' categories for changes
         * -------------------------------------- */
        // Update any altered categories for 'course' pages that already exist in mdl_course.
        // If they have altered they should exist in the usr_data_courses or other source and therefore in usr_ro_modules.
        $sql = 'UPDATE mdl_course, usr_ro_modules SET mdl_course.category = usr_ro_modules.category_id
                WHERE mdl_course.idnumber = usr_ro_modules.category_idnumber';
        $DB->execute($sql);

    }
}
