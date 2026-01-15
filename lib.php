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
 * Library of functions for the mediacms module.
 *
 * @package     mod_mediacms
 * @copyright   2026 JD Park, Pai-Chai University & UNSIA
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Supported features
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed
 */
function mediacms_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_ARCHETYPE: return MOD_ARCHETYPE_RESOURCE;
        case FEATURE_GROUPS: return false;
        case FEATURE_NO_VIEW_LINK: return false;
        case FEATURE_IDNUMBER: return true;
        case FEATURE_SHOW_DESCRIPTION: return true;
        case FEATURE_COMPLETION_HAS_RULES: return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return false; // We use custom progress, not standard view tracking? Or maybe true is okay? 
        // Actually usually false if we have specific rules.
        default: return null;
    }
}

/**
 * Adds a new instance of mediacms activity
 *
 * @param stdClass $mediacms
 * @param mod_mediacms_mod_form $mform
 * @return int intance id
 */
function mediacms_add_instance($mediacms, $mform = null) {
    global $DB;

    $mediacms->timecreated = time();
    $mediacms->timemodified = time();

    return $DB->insert_record('mediacms', $mediacms);
}

/**
 * Updates an instance of the mediacms activity
 *
 * @param stdClass $mediacms
 * @param mod_mediacms_mod_form $mform
 * @return bool true
 */
function mediacms_update_instance($mediacms, $mform = null) {
    global $DB;

    $mediacms->timemodified = time();
    $mediacms->id = $mediacms->instance;

    return $DB->update_record('mediacms', $mediacms);
}

/**
 * Deletes an instance of the mediacms activity
 *
 * @param int $id
 * @return bool true
 */
function mediacms_delete_instance($id) {
    global $DB;

    if (!$mediacms = $DB->get_record('mediacms', array('id' => $id))) {
        return false;
    }

    $DB->delete_records('mediacms', array('id' => $mediacms->id));

    return true;
}

/**
 * Extract info from MediaCMS URL and fetch API details
 *
 * @param string $url The full MediaCMS URL (watch page or API)
 * @return array|null The media info array or null if failed
 */
function mediacms_get_media_info($url) {
    global $CFG;
    require_once($CFG->libdir . '/filelib.php');

    $token = '';
    $base_url = '';

    // Try to parse token from common MediaCMS URL patterns
    // Pattern 1: /watch?v=TOKEN or /view?m=TOKEN
    $parsed = parse_url($url);
    if (isset($parsed['query'])) {
        parse_str($parsed['query'], $query);
        if (isset($query['v'])) {
            $token = $query['v'];
        } elseif (isset($query['m'])) {
            $token = $query['m'];
        }
    }

    // Pattern 2: /w/TOKEN or /v/TOKEN or /media/TOKEN
    if (!$token && isset($parsed['path'])) {
        if (preg_match('~/(?:w|v|media)/([a-zA-Z0-9\-_]+)~', $parsed['path'], $matches)) {
            $token = $matches[1];
        }
    }

    if (!$token) {
        return null;
    }

    // innovative guess of base URL
    $scheme = isset($parsed['scheme']) ? $parsed['scheme'] : 'http';
    $host = isset($parsed['host']) ? $parsed['host'] : '';
    $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
    $path = isset($parsed['path']) ? $parsed['path'] : '';
    
    // MediaCMS usually installed at root, but could be subpath.
    // We assume the URL provided was something like http://site/watch?v=...
    // So base is scheme://host:port
    // If installed in subdir, this guess might fail, but it's a good start.
    // Ideally user provides base URL in settings, but we don't have that yet.
    
    // Check if we need to include path prefix (if installed in subdir)
    // If path starts with /mediacms/ or similar, we might need it. 
    // For now simple base construction:
    $base_url = $scheme . '://' . $host . $port;

    $api_url = $base_url . '/api/v1/media/' . $token;

    // Moodle blocks localhost/loopback requests by default. We need to bypass this for local dev.
    $curl = new curl(['ignoresecurity' => true]);
    $response = $curl->get($api_url);

    if ($curl->errno) {
        return null; // Request failed
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null; // Invalid JSON
    }

    return $data;
}

/**
 * Extends the settings navigation with the report link.
 *
 * @param settings_navigation $settings navigation object
 * @param navigation_node $navnode module navigation node
 */
function mediacms_extend_settings_navigation(settings_navigation $settings, navigation_node $navnode) {
    global $PAGE;

    if (has_capability('mod/mediacms:view', $PAGE->cm->context)) {
        $url = new moodle_url('/mod/mediacms/report.php', array('id' => $PAGE->cm->id));
        $navnode->add('Report', $url, navigation_node::TYPE_SETTING,
            null, 'mediacmsreport', new pix_icon('i/report', ''));
    }
}

/**
 * Obtains the automatic completion state for this mediacms based on any conditions
 * in mediacms settings.
 *
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not, $type if conditions not set.
 */
function mediacms_get_completion_state($course, $cm, $userid, $type) {
    global $DB;

    $mediacms = $DB->get_record('mediacms', array('id' => $cm->instance), '*', MUST_EXIST);

    $result = $type; // Default return value

    if ($cm->completion == COMPLETION_TRACKING_NONE) {
        return $result;
    }

    // Check for our custom rule: completionminview
    if (isset($mediacms->completionminview) && $mediacms->completionminview > 0) {
        $record = $DB->get_record('mediacms_progress', array('mediacmsid' => $mediacms->id, 'userid' => $userid));
        $progress = $record ? intval($record->progress) : 0;

        if ($progress >= $mediacms->completionminview) {
            $result = true;
        } else {
            $result = false;
        }
    }

    return $result;
}
