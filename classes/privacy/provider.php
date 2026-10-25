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
 * Privacy Subsystem implementation for quizaccess_liveproctoring.
 *
 * @package   quizaccess_liveproctoring
 * @copyright 2026 Energy & Water Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_liveproctoring\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\provider as metadata_provider;

/**
 * Privacy Subsystem for quizaccess_liveproctoring.
 */
class provider implements metadata_provider {

    /**
     * Describe the types of data stored by this plugin.
     *
     * @param collection $items The collection of items to add to.
     * @return collection The collection with items added.
     */
    public static function get_metadata(collection $items): collection {
        $items->add_database_table(
            'quizaccess_liveproctoring_snaps',
            [
                'userid'        => 'privacy:metadata:quizaccess_liveproctoring_snaps:userid',
                'quizid'        => 'privacy:metadata:quizaccess_liveproctoring_snaps:quizid',
                'attemptid'     => 'privacy:metadata:quizaccess_liveproctoring_snaps:attemptid',
                'risk_score'    => 'privacy:metadata:quizaccess_liveproctoring_snaps:risk_score',
                'face_detected' => 'privacy:metadata:quizaccess_liveproctoring_snaps:face_detected',
                'suspicious'    => 'privacy:metadata:quizaccess_liveproctoring_snaps:suspicious',
                'timecreated'   => 'privacy:metadata:quizaccess_liveproctoring_snaps:timecreated',
            ],
            'privacy:metadata:quizaccess_liveproctoring_snaps'
        );

        $items->add_database_table(
            'quizaccess_liveproctoring_events',
            [
                'userid'       => 'privacy:metadata:quizaccess_liveproctoring_events:userid',
                'attemptid'    => 'privacy:metadata:quizaccess_liveproctoring_events:attemptid',
                'eventtype'    => 'privacy:metadata:quizaccess_liveproctoring_events:eventtype',
                'severity'     => 'privacy:metadata:quizaccess_liveproctoring_events:severity',
                'timecreated'  => 'privacy:metadata:quizaccess_liveproctoring_events:timecreated',
            ],
            'privacy:metadata:quizaccess_liveproctoring_events'
        );

        $items->add_subsystem_link('core_files', [], 'privacy:metadata:core_files');

        return $items;
    }
}
