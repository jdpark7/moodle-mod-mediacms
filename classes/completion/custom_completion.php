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
 * Custom completion class for the mediacms module.
 *
 * @package     mod_mediacms
 * @copyright   2026 JD Park, Pai-Chai University & UNSIA
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace mod_mediacms\completion;

defined('MOODLE_INTERNAL') || die();

use core_completion\activity_custom_completion;

/**
 * Custom completion class for mediacms.
 *
 * @package     mod_mediacms
 * @copyright   2026 Antigravity
 */
class custom_completion extends activity_custom_completion {
    
    /**
     * Get the completion state for a specific rule.
     *
     * @param string $rule The completion rule.
     * @return int The completion state.
     */
    public function get_state(string $rule): int {
        global $DB;
        
        $this->validate_rule($rule);

        if ($rule === 'completionminview') {
            // Get user progress
            $record = $DB->get_record('mediacms_progress', [
                'mediacmsid' => $this->cm->instance,
                'userid' => $this->userid
            ]);
            $currentprogress = $record ? $record->progress : 0;
            
            // Get required percentage
            // Let's try to access via cm properties first, if not fallback to DB.
            $instance = $DB->get_record('mediacms', ['id' => $this->cm->instance], 'completionminview', MUST_EXIST);
            $required = $instance->completionminview;

            if ($currentprogress >= $required) {
                return COMPLETION_COMPLETE;
            }
            return COMPLETION_INCOMPLETE;
        }

        return COMPLETION_INCOMPLETE;
    }

    /**
     * Get the list of defined custom completion rules.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionminview'];
    }

    /**
     * Get descriptions for the custom completion rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        global $DB;
        $instance = $DB->get_record('mediacms', ['id' => $this->cm->instance], 'completionminview', MUST_EXIST);
        
        return [
            'completionminview' => get_string('completiondetail:minview', 'mediacms', $instance->completionminview),
        ];
    }
    
    /**
     * Get the sort order of completion rules.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return ['completionminview'];
    }
}
