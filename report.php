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
 * Display simple tabular data
 *
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../config.php');
require_once 'lib.php';

defined('MOODLE_INTERNAL') || die();

global $SITE,$PAGE, $USER;
require_login();
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/proctoru/report.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_course($SITE);
$PAGE->set_title(ProctorU::_s('report_page_title'));
$PAGE->set_heading('Reg Header');

$lngName = ProctorU::_s('pluginname');
$shtName = ProctorU::_s('franken_name');
$settingsLink = new moodle_url('/admin/settings.php', array('section'=>$shtName));
$PAGE->navbar->add($lngName, $settingsLink);

if(is_siteadmin($USER) or has_capability('local/proctoru:viewstats', context_system::instance())){

    $output = $PAGE->get_renderer('local_proctoru');
    $reportData = new registration_report();
    echo $output->header();
    echo $output->render($reportData);
    echo $output->footer();

}else{
    notice(ProctorU::_s('report_not_auth',new moodle_url('/my')));
}

?>
