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
 * Library callbacks for quizaccess_liveproctoring.
 *
 * @package   quizaccess_liveproctoring
 * @copyright 2026 Energy & Water Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Serve snapshot files from the quizaccess_liveproctoring file area.
 *
 * @param stdClass $course The course object.
 * @param stdClass $cm The course module object.
 * @param context $context The context.
 * @param string $filearea The file area ('snapshots').
 * @param array $args Extra arguments (attemptid, filename).
 * @param bool $forcedownload Whether to force download.
 * @param array $options Additional options.
 * @return bool False if file not found or access denied.
 */
function quizaccess_liveproctoring_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, false, $cm);

    if ($filearea !== 'snapshots' && $filearea !== 'evidence_videos') {
        return false;
    }

    $attemptid = (int) array_shift($args);
    $filename  = array_pop($args);

    if (!$attemptid || empty($filename)) {
        return false;
    }

    if ($filearea === 'snapshots') {
        // Only allow .jpg files.
        if (!preg_match('/^snap_\d+_\d+\.jpg$/', $filename)) {
            return false;
        }
    } else if ($filearea === 'evidence_videos') {
        // Only allow .webm or .mp4 files.
        if (!preg_match('/^evidence_\d+_\d+\.(webm|mp4)$/', $filename)) {
            return false;
        }
    }

    // Capability check: teachers with viewsnapshots capability.
    require_capability('quizaccess/liveproctoring:viewsnapshots', $context);

    // Verify attempt belongs to this quiz.
    $attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid]);
    if (!$attempt || (int) $attempt->quiz !== (int) $cm->instance) {
        return false;
    }

    $fs   = get_file_storage();
    $file = $fs->get_file($context->id, 'quizaccess_liveproctoring', $filearea, $attemptid, '/', $filename);

    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, null, 0, $forcedownload, $options);
    return true;
}

/**
 * Extend quiz navigation to add Live Monitor and Report links.
 *
 * @param navigation_node $quiznode The quiz navigation node.
 * @param stdClass $course The course object.
 * @param stdClass $module The module object.
 * @param context_module $context The module context.
 */
function quizaccess_liveproctoring_extend_navigation_quiz(navigation_node $quiznode, stdClass $course, stdClass $module, context_module $context) {
    global $DB;

    if (!has_capability('quizaccess/liveproctoring:viewmonitor', $context)) {
        return;
    }

    $settings = $DB->get_record('quizaccess_liveproctoring_quiz', ['quizid' => $module->instance]);
    if (empty($settings) || !$settings->enablecamera) {
        return;
    }

    $cmid   = $module->id;
    $quizid = $module->instance;

    $monitorurl = new moodle_url('/mod/quiz/accessrule/liveproctoring/live_monitor.php',
        ['cmid' => $cmid, 'quizid' => $quizid]);
    $reporturl  = new moodle_url('/mod/quiz/accessrule/liveproctoring/report.php',
        ['cmid' => $cmid, 'quizid' => $quizid]);

    $quiznode->add(
        get_string('livemonitor', 'quizaccess_liveproctoring'),
        $monitorurl,
        navigation_node::TYPE_SETTING,
        null,
        'liveproctoring_monitor',
        new pix_icon('i/report', '')
    );

    $quiznode->add(
        get_string('viewreport', 'quizaccess_liveproctoring'),
        $reporturl,
        navigation_node::TYPE_SETTING,
        null,
        'liveproctoring_report',
        new pix_icon('i/report', '')
    );
}
