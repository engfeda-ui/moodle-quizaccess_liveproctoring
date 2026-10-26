<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Log event endpoint — called by student AMD when a behavioural event occurs.
 *
 * @package   quizaccess_liveproctoring
 * @copyright 2026 Energy & Water Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

if (!confirm_sesskey()) {
    http_response_code(403);
    die(json_encode(['error' => 'Invalid session']));
}

require_login();

$cmid         = required_param('cmid',         PARAM_INT);
$attemptid    = required_param('attemptid',    PARAM_INT);
$eventtype    = required_param('eventtype',    PARAM_ALPHANUMEXT);
$severity     = optional_param('severity',     'medium', PARAM_ALPHA);
$warningcount = optional_param('warningcount', 0,        PARAM_INT);
$message      = optional_param('message',      '',       PARAM_TEXT);

// Verify attempt belongs to current user.
$attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid], '*', MUST_EXIST);
if ($attempt->userid != $USER->id) {
    http_response_code(403);
    die(json_encode(['error' => 'Access denied']));
}

// Verify cmid belongs to the attempt's quiz (cross-module IDOR guard).
list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'quiz');
if ((int) $cm->instance !== (int) $attempt->quiz) {
    http_response_code(403);
    die(json_encode(['error' => 'Module/quiz mismatch']));
}

// Sanitize severity.
$allowed = ['low', 'medium', 'high'];
if (!in_array($severity, $allowed)) {
    $severity = 'medium';
}

$event = new stdClass();
$event->userid       = $USER->id;
$event->cmid         = $cmid;
$event->attemptid    = $attemptid;
$event->eventtype    = substr($eventtype, 0, 50);
$event->severity     = $severity;
$event->warningcount = $warningcount;
$event->message      = substr($message, 0, 255);
$event->timecreated  = time();

$DB->insert_record('quizaccess_liveproctoring_events', $event);

header('Content-Type: application/json');
echo json_encode(['success' => true]);
