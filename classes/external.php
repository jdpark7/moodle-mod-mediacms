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
 * External API for the mediacms module.
 *
 * @package     mod_mediacms
 * @copyright   2026 JD Park, Pai-Chai University & UNSIA
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_mediacms;

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use context_module;

require_once($CFG->libdir . '/externallib.php');

class external extends external_api {
    public static function submit_progress_parameters() {
        return new external_function_parameters([
             'cmid' => new external_value(PARAM_INT, 'course module id'),
             'progress' => new external_value(PARAM_INT, 'progress percentage 0-100'),
        ]);
    }

    public static function submit_progress($cmid, $progress) {
        global $DB, $USER;

        $params = self::validate_parameters(self::submit_progress_parameters(), ['cmid' => $cmid, 'progress' => $progress]);
        
        $cm = get_coursemodule_from_id('mediacms', $params['cmid'], 0, false, MUST_EXIST);
        $mediacms = $DB->get_record('mediacms', ['id' => $cm->instance], '*', MUST_EXIST);
        
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        
        // Update or insert progress
        $record = $DB->get_record('mediacms_progress', ['mediacmsid' => $mediacms->id, 'userid' => $USER->id]);
        if (!$record) {
            mtrace("MediaCMS External: Inserting progress for user {$USER->id} on mediacms {$mediacms->id}: {$params['progress']}");
            $record = new \stdClass();
            $record->mediacmsid = $mediacms->id;
            $record->userid = $USER->id;
            $record->progress = $params['progress'];
            $record->timemodified = time();
            $DB->insert_record('mediacms_progress', $record);
        } else {
            mtrace("MediaCMS External: Existing progress for user {$USER->id}: {$record->progress}, New: {$params['progress']}");
            // Only update if progress increased
            if ($params['progress'] > $record->progress) {
                $record->progress = $params['progress'];
                $record->timemodified = time();
                $DB->update_record('mediacms_progress', $record);
            }
        }

        // Trigger completion update
        $completion = new \completion_info(get_course($cm->course));
        if ($completion->is_enabled($cm)) {
            // We pass COMPLETION_COMPLETE which triggers the dynamic check via get_state
            $completion->update_state($cm, COMPLETION_COMPLETE, $USER->id);
        }

        return ['status' => true];
    }

    public static function submit_progress_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'operation status')
        ]);
    }
}
