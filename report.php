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

defined('MOODLE_INTERNAL') || die();

global $CFG,$PAGE, $USER;
require_login();
$context = get_context_instance(CONTEXT_SYSTEM);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/proctoru/report.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_course($SITE);
$PAGE->set_title('Reg title');
$PAGE->set_heading('Reg Header');
$PAGE->navbar->add('Nav');

if(is_siteadmin($USER) or has_capability('local/proctoru:viewstats', get_context_instance(CONTEXT_SYSTEM))){

    $output = $PAGE->get_renderer('local_proctoru');
    $reportData = new registration_report();
    echo $output->header();
    echo $output->render($reportData);
    echo $output->footer();

    mtrace('end table out');
}else{
    //@TODO use lang strings
    notice("You are not authorized to view this resource",new moodle_url('/my'));
}

?>
