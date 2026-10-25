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
 * Live Monitor Dashboard — Teacher view.
 *
 * @package   quizaccess_liveproctoring
 * @copyright 2026 Energy & Water Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once(__DIR__ . '/rule.php');

$cmid   = required_param('cmid',   PARAM_INT);
$quizid = required_param('quizid', PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'quiz');
require_login($course, true, $cm);

$context = context_module::instance($cmid, MUST_EXIST);
require_capability('quizaccess/liveproctoring:viewmonitor', $context);

$quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);

$PAGE->set_url(new moodle_url('/mod/quiz/accessrule/liveproctoring/live_monitor.php',
    ['cmid' => $cmid, 'quizid' => $quizid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('livemonitor', 'quizaccess_liveproctoring') . ' — ' . format_string($quiz->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('popup');

$feedurl   = (new moodle_url('/mod/quiz/accessrule/liveproctoring/live_feed.php',
    ['cmid' => $cmid, 'quizid' => $quizid]))->out(false);
$signalurl = (new moodle_url('/mod/quiz/accessrule/liveproctoring/live_signal.php'))->out(false);

$PAGE->requires->js_call_amd(
    'quizaccess_liveproctoring/proctoring_monitor',
    'init',
    [[
        'feedurl'   => $feedurl,
        'signalurl' => $signalurl,
        'sesskey'   => sesskey(),
        'cmid'      => $cmid,
        'quizid'    => $quizid,
        'strings'   => [
            'high'                => get_string('riskhigh', 'quizaccess_liveproctoring'),
            'medium'              => get_string('riskmedium', 'quizaccess_liveproctoring'),
            'ok'                  => get_string('riskok', 'quizaccess_liveproctoring'),
            'noface'              => get_string('facenotdetected', 'quizaccess_liveproctoring'),
            'multiface'           => get_string('multiplefaces', 'quizaccess_liveproctoring'),
            'lastseen'            => get_string('lastseen', 'quizaccess_liveproctoring'),
            'spotlight'           => get_string('spotlight', 'quizaccess_liveproctoring'),
            'spotlight_title'     => get_string('spotlight_title', 'quizaccess_liveproctoring'),
            'connecting_live'     => get_string('connecting_live', 'quizaccess_liveproctoring'),
            'live_active'         => get_string('live_active', 'quizaccess_liveproctoring'),
            'send_warning'        => get_string('send_warning', 'quizaccess_liveproctoring'),
            'warning_placeholder' => get_string('warning_placeholder', 'quizaccess_liveproctoring'),
            'terminate_btn'       => get_string('terminate_btn', 'quizaccess_liveproctoring'),
            'terminate_prompt'    => get_string('terminate_prompt', 'quizaccess_liveproctoring'),
            'warning_sent'        => get_string('warning_sent', 'quizaccess_liveproctoring'),
            'filter_all'          => get_string('filter_all', 'quizaccess_liveproctoring'),
            'filter_flagged'      => get_string('filter_flagged', 'quizaccess_liveproctoring'),
        ],
    ]]
);

echo $OUTPUT->header();

$templatedata = [
    'quizname' => format_string($quiz->name),
    'cmid'     => $cmid,
    'quizid'   => $quizid,
    'reporturl' => (new moodle_url('/mod/quiz/accessrule/liveproctoring/report.php',
        ['cmid' => $cmid, 'quizid' => $quizid]))->out(false),
];

echo $OUTPUT->render_from_template('quizaccess_liveproctoring/monitor_dashboard', $templatedata);

echo $OUTPUT->footer();
