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
 * WebRTC and live stream signaling endpoint for Zoom Exam Hall.
 *
 * @package   quizaccess_liveproctoring
 * @copyright 2026 Energy & Water Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!confirm_sesskey()) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid session']);
    exit;
}

require_login();

$action    = required_param('action',    PARAM_ALPHANUMEXT);
$attemptid = required_param('attemptid', PARAM_INT);

// Verify attempt exists.
$attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', MUST_EXIST);
$quiz    = $DB->get_record('quiz', ['id' => $attempt->quiz], '*', MUST_EXIST);

// Handle teacher-initiated actions.
$teacheractions = ['start_stream', 'poll_stream', 'send_answer', 'send_warning', 'terminate_attempt', 'stop_stream'];
if (in_array($action, $teacheractions)) {
    $cmid = required_param('cmid', PARAM_INT);
    $context = context_module::instance($cmid, MUST_EXIST);
    require_capability('quizaccess/liveproctoring:viewmonitor', $context);

    // Verify cm belongs to the attempt's quiz (cross-module IDOR guard).
    list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'quiz');
    if ((int) $cm->instance !== (int) $attempt->quiz) {
        http_response_code(403);
        echo json_encode(['error' => 'Module/quiz mismatch']);
        exit;
    }

    $record = $DB->get_record('quizaccess_liveproctoring_live', ['attemptid' => $attemptid]);

    if ($action === 'start_stream') {
        if ($record) {
            $record->status             = 'requested';
            $record->teacherid          = $USER->id;
            $record->offer              = null;
            $record->answer             = null;
            $record->student_candidates = '[]';
            $record->teacher_candidates = '[]';
            $record->timemodified       = time();
            $DB->update_record('quizaccess_liveproctoring_live', $record);
        } else {
            $record = (object)[
                'quizid'             => $quiz->id,
                'attemptid'          => $attemptid,
                'userid'             => $attempt->userid,
                'teacherid'          => $USER->id,
                'status'             => 'requested',
                'offer'              => null,
                'answer'             => null,
                'student_candidates' => '[]',
                'teacher_candidates' => '[]',
                'latest_frame'       => null,
                'custom_warning'     => null,
                'terminate_flag'     => 0,
                'timemodified'       => time(),
            ];
            $DB->insert_record('quizaccess_liveproctoring_live', $record);
        }
        echo json_encode(['success' => true, 'status' => 'requested']);
        exit;
    }

    if ($action === 'poll_stream') {
        if (!$record) {
            echo json_encode(['success' => false, 'active' => false]);
            exit;
        }
        // Touch heartbeat.
        $record->timemodified = time();
        $DB->update_record('quizaccess_liveproctoring_live', $record);

        echo json_encode([
            'success'            => true,
            'active'             => in_array($record->status, ['requested', 'streaming']),
            'status'             => $record->status,
            'offer'              => $record->offer,
            'student_candidates' => $record->student_candidates,
            'latest_frame'       => $record->latest_frame,
            'timemodified'       => $record->timemodified,
        ]);
        exit;
    }

    if ($action === 'send_answer') {
        $answer     = required_param('answer',     PARAM_RAW);
        $candidates = optional_param('candidates', '[]', PARAM_RAW);

        if ($record) {
            $record->answer             = $answer;
            $record->teacher_candidates = $candidates;
            $record->status             = 'streaming';
            $record->timemodified       = time();
            $DB->update_record('quizaccess_liveproctoring_live', $record);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'send_warning') {
        $msg = required_param('message', PARAM_TEXT);
        if ($record) {
            $record->custom_warning = $msg;
            $record->timemodified   = time();
            $DB->update_record('quizaccess_liveproctoring_live', $record);
        }
        // Log as proctoring event.
        $ev = (object)[
            'userid'       => $attempt->userid,
            'cmid'         => $cmid,
            'attemptid'    => $attemptid,
            'eventtype'    => 'teacher_warning',
            'severity'     => 'high',
            'warningcount' => 1,
            'message'      => $msg,
            'timecreated'  => time(),
        ];
        $DB->insert_record('quizaccess_liveproctoring_events', $ev);

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'terminate_attempt') {
        if ($record) {
            $record->terminate_flag = 1;
            $record->status         = 'closed';
            $record->timemodified   = time();
            $DB->update_record('quizaccess_liveproctoring_live', $record);
        }

        // Server-enforced immediate finalization in Moodle.
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        try {
            $attemptobj = quiz_attempt::create($attemptid);
            if ($attemptobj->is_inprogress()) {
                $attemptobj->process_finish(time(), false);
            }
        } catch (\Exception $e) {
            // Already finalized or in transitional state.
        }

        // Log termination event.
        $ev = (object)[
            'userid'       => $attempt->userid,
            'cmid'         => $cmid,
            'attemptid'    => $attemptid,
            'eventtype'    => 'force_terminated',
            'severity'     => 'high',
            'warningcount' => 99,
            'message'      => 'Attempt terminated by proctor from live monitor.',
            'timecreated'  => time(),
        ];
        $DB->insert_record('quizaccess_liveproctoring_events', $ev);

        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'stop_stream') {
        if ($record) {
            $record->status       = 'closed';
            $record->timemodified = time();
            $DB->update_record('quizaccess_liveproctoring_live', $record);
        }
        echo json_encode(['success' => true]);
        exit;
    }
}

// Student-initiated actions.
if ($attempt->userid != $USER->id) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied: attempt ownership mismatch']);
    exit;
}

$record = $DB->get_record('quizaccess_liveproctoring_live', ['attemptid' => $attemptid]);

if ($action === 'student_auto_submit') {
    $reason = optional_param('reason', 'warning_limit', PARAM_ALPHAEXT);
    require_once($CFG->dirroot . '/mod/quiz/locallib.php');
    try {
        $attemptobj = quiz_attempt::create($attemptid);
        if ($attemptobj->is_inprogress()) {
            $attemptobj->process_finish(time(), false);
        }
    } catch (\Exception $e) {}

    if ($record) {
        $record->terminate_flag = 1;
        $record->status         = 'closed';
        $record->timemodified   = time();
        $DB->update_record('quizaccess_liveproctoring_live', $record);
    }

    $ev = (object)[
        'userid'       => $attempt->userid,
        'cmid'         => $cmid,
        'attemptid'    => $attemptid,
        'eventtype'    => 'auto_submitted',
        'severity'     => 'high',
        'warningcount' => 99,
        'message'      => 'Attempt automatically finalized on server: ' . $reason,
        'timecreated'  => time(),
    ];
    $DB->insert_record('quizaccess_liveproctoring_events', $ev);

    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'check_stream') {
    if (!$record) {
        echo json_encode(['active' => false]);
        exit;
    }

    // Check if teacher heartbeat is fresh (within 35s).
    $isactive = in_array($record->status, ['requested', 'streaming']) && ($record->timemodified >= (time() - 35));

    $customwarning = $record->custom_warning;
    if ($customwarning) {
        // Clear warning after delivering it once.
        $record->custom_warning = null;
        $DB->update_record('quizaccess_liveproctoring_live', $record);
    }

    echo json_encode([
        'active'             => $isactive,
        'status'             => $record->status,
        'answer'             => $record->answer,
        'teacher_candidates' => $record->teacher_candidates,
        'custom_warning'     => $customwarning,
        'terminate_flag'     => (int) $record->terminate_flag,
    ]);
    exit;
}

if ($action === 'send_offer') {
    $offer      = required_param('offer',      PARAM_RAW);
    $candidates = optional_param('candidates', '[]', PARAM_RAW);
    $frame      = optional_param('frame',      null, PARAM_RAW);

    if ($record) {
        $record->offer              = $offer;
        $record->student_candidates = $candidates;
        $record->status             = 'streaming';
        if ($frame) {
            $record->latest_frame = $frame;
        }
        $record->timemodified       = time();
        $DB->update_record('quizaccess_liveproctoring_live', $record);
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'push_live_frame') {
    $frame = required_param('frame', PARAM_RAW);
    if ($record && in_array($record->status, ['requested', 'streaming'])) {
        $record->latest_frame = $frame;
        $record->timemodified = time();
        $DB->update_record('quizaccess_liveproctoring_live', $record);
    }
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Unknown action']);
