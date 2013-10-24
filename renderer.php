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
 * Renderer for local proctoru
 *
 * @package    local_proctoru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;
require_once $CFG->libdir.'/tablelib.php';
require_once 'lib.php';
/**
 * proctoru verification status local rendrer
 *
 * @package    local_proctoru
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_proctoru_renderer extends plugin_renderer_base {

    public function render_registration_report(registration_report $report) {
        $this->page->requires->yui_module(
            'moodle-local_proctoru-regreport', 
            'M.local_proctoru.regreport.init',
            array(array_values($report->data))
            ); 
        
        $out  = $this->output->heading("ProctorU Registration Status");
        $out .= html_writer::tag('div', '', array("id"=>"report"));
        return $this->output->container($out);
    }
}

class registration_report implements renderable {

    public $data;

    public function __construct(){
        global $DB;
        $sql = "
            SELECT u.id, u.firstname, u.lastname, u.username, u.idnumber, 
                (SELECT value FROM {enrol_ues_usermeta} usm WHERE usm.userid = u.id AND usm.name = 'user_major') AS major, 
                (SELECT value FROM {enrol_ues_usermeta} usm WHERE usm.userid = u.id AND usm.name = 'user_college') AS college, 
                (SELECT r.shortname FROM {role} r WHERE r.id = (SELECT min(roleid) FROM {role_assignments} WHERE userid = u.id)) AS role,
                uid.data AS status
                        FROM {user_info_data} uid 
                        INNER JOIN {user} u ON u.id = uid.userid 
                        WHERE fieldid   = :fieldid
                        AND u.suspended = 0
                        AND u.deleted   = 0";
        
        $this->data = $DB->get_records_sql($sql, array('fieldid'=>ProctorU::intCustomFieldID()));
        $this->fixStatusCodes();
    }
    
    private function fixStatusCodes(){
        //@TODO refactor this so that we don't create a get_string for each data point!!!
        foreach($this->data as $d){
            $d->status = ProctorU::strMapStatusToLangString($d->status);
        }
    }
}

?>