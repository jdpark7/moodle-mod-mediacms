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
 * Redirects the user to Video Watch instance.
 *
 * @package     mod_mediacms
 * @copyright   2026 JD Park, Pai-Chai University & UNSIA
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require('../../config.php');
require_once('lib.php');
require_once($CFG->libdir . '/resourcelib.php');

$id = optional_param('id', 0, PARAM_INT); // Course Module ID
$n  = optional_param('n', 0, PARAM_INT);  // MediaCMS Instance ID

if ($id) {
    $cm         = get_coursemodule_from_id('mediacms', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $mediacms   = $DB->get_record('mediacms', array('id' => $cm->instance), '*', MUST_EXIST);
} elseif ($n) {
    $mediacms   = $DB->get_record('mediacms', array('id' => $n), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $mediacms->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('mediacms', $mediacms->id, $course->id, false, MUST_EXIST);
} else {
    print_error('You must specify a course_module ID or an instance ID');
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/mediacms:view', $context);

$strname = format_string($mediacms->name);
$PAGE->set_url('/mod/mediacms/view.php', array('id' => $cm->id));
$PAGE->set_title($course->shortname . ': ' . $strname);
$PAGE->set_heading($course->fullname);
$PAGE->add_body_class('limitedwidth');

echo $OUTPUT->header();
// Echo heading removed as per user request (duplicate name)
// echo $OUTPUT->heading($strname);

// Render the activity information (Completion details)
if (class_exists('\\core_completion\\activity_custom_completion') && !class_exists('core\\output\\activity_header')) {
    $cminfo = cm_info::create($cm);
    $completiondetails = \core_completion\cm_completion_details::get_instance($cminfo, $USER->id);
    $activitydates = \core\activity_dates::get_dates_for_module($cminfo, $USER->id);
    echo $OUTPUT->activity_information($cminfo, $completiondetails, $activitydates);
}

// Render using mustache template
$uniqid = 'mediacms-player-' . $cm->id;

// Attempt to guess mimetype
// Try to get direct source via API
$info = mediacms_get_media_info($mediacms->mediacmsurl);

// Check if we got valid info
if ($info) {
    // Derive base URL from user input to prepend to relative paths
    $parsed_input = parse_url($mediacms->mediacmsurl);
    $base_url = (isset($parsed_input['scheme']) ? $parsed_input['scheme'] : 'http') . '://' .
                (isset($parsed_input['host']) ? $parsed_input['host'] : '') .
                (isset($parsed_input['port']) ? ':' . $parsed_input['port'] : '');

    // Check for HLS first (preferred for streaming)
    if (!empty($info['hls_info']) && !empty($info['hls_info']['master_file'])) {
         $mediacmsurl = $info['hls_info']['master_file'];
         if (strpos($mediacmsurl, 'http') !== 0) {
             $mediacmsurl = $base_url . $mediacmsurl;
         }
         $mimetype = 'application/x-mpegURL';
    } 
    // Fallback to encodings
    elseif (!empty($info['encodings_info'])) {
        // Encodings are keys like "1080", "720". We want the highest.
        // Sort keys numerically descending
        $resolutions = array_keys($info['encodings_info']);
        rsort($resolutions, SORT_NUMERIC);
        
        foreach ($resolutions as $res) {
            if (!empty($info['encodings_info'][$res]['h264']['url'])) {
                $mediacmsurl = $info['encodings_info'][$res]['h264']['url'];
                if (strpos($mediacmsurl, 'http') !== 0) {
                    $mediacmsurl = $base_url . $mediacmsurl;
                }
                $mimetype = 'video/mp4'; // Assuming h264/mp4
                break;
            }
        }
    }
} else {
    // Fallback to user provided URL (likely to fail if it's a page)
    $mediacmsurl = $mediacms->mediacmsurl;
    // Attempt standard guessing
    $mimetype = resourcelib_guess_url_mimetype($mediacmsurl);
    $extension = pathinfo($mediacmsurl, PATHINFO_EXTENSION);
    if ($extension === 'm3u8' || strpos($mediacmsurl, '.m3u8') !== false) {
        $mimetype = 'application/x-mpegURL';
    }
}

// Fetch user progress
$progress = 0;
if ($record = $DB->get_record('mediacms_progress', array('mediacmsid' => $mediacms->id, 'userid' => $USER->id))) {
    $progress = $record->progress;
}

// Check completion state
$iscompleted = false;
if (isset($mediacms->completionminview) && $mediacms->completionminview > 0 && $progress >= $mediacms->completionminview) {
    $iscompleted = true;
}

$templatecontext = (object) [
    'name' => $strname,
    'intro' => format_module_intro('mediacms', $mediacms, $cm->id),
    'mediacmsurl' => $mediacmsurl,
    'uniqid' => $uniqid,
    'mimetype' => $mimetype,
    'cmid' => $cm->id,
    'progress' => $progress,
    'completionminview' => isset($mediacms->completionminview) ? $mediacms->completionminview : 0,
    'iscompleted' => $iscompleted
];

echo $OUTPUT->render_from_template('mod_mediacms/view', $templatecontext);

echo $OUTPUT->footer();
