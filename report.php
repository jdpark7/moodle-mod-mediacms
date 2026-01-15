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
 * Report generation for the mediacms module.
 *
 * @package     mod_mediacms
 * @copyright   2026 JD Park, Pai-Chai University & UNSIA
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require('../../config.php');
require_once('lib.php');

$id = optional_param('id', 0, PARAM_INT);
$n  = optional_param('n', 0, PARAM_INT);

if ($id) {
    echo "ID: $id";
    $cm         = get_coursemodule_from_id('mediacms', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $mediacms   = $DB->get_record('mediacms', array('id' => $cm->instance), '*', MUST_EXIST);
} elseif ($n) {
    echo "N: $n";
    $mediacms   = $DB->get_record('mediacms', array('id' => $n), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $mediacms->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('mediacms', $mediacms->id, $course->id, false, MUST_EXIST);
} else {
    print_error('invalidid', 'mediacms');
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/mediacms:viewreport', $context);

$strname = format_string($mediacms->name);
$PAGE->set_url('/mod/mediacms/report.php', array('id' => $cm->id));
$PAGE->set_title($course->shortname . ': ' . $strname);
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulename', 'mod_mediacms') . ' Report');

$groupmode = groups_get_activity_groupmode($cm);
$currentgroup = groups_get_activity_group($cm, true);
if ($groupmode) {
    groups_print_activity_menu($cm, $PAGE->url);
}

// Get enrolled users
$users = get_enrolled_users($context, 'mod/mediacms:view', $currentgroup, 'u.*', null, 0, 0, true);

// Get stats
$progress = $DB->get_records('mediacms_progress', array('mediacmsid' => $mediacms->id));
$progress_by_user = array();
foreach ($progress as $p) {
    $progress_by_user[$p->userid] = $p;
}

$table = new html_table();
$table->head = array(
    get_string('fullname'), 
    get_string('email'), 
    'Progress (%)', 
    'Last Access'
);

// Show only current user
$users = array($USER);

foreach ($users as $user) {

    $row = array();
    $row[] = fullname($user);
    $row[] = $user->email;
    
    if (isset($progress_by_user[$user->id])) {
        $p = $progress_by_user[$user->id];
        $row[] = $p->progress . '%';
        $row[] = userdate($p->timemodified);
    } else {
        $row[] = '0%';
        $row[] = '-';
    }
    
    $table->data[] = $row;
}
echo html_writer::table($table);

echo $OUTPUT->footer();
