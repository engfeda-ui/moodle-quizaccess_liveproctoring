<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Handle proctor live incident evidence video clip upload.
 *
 * @package   quizaccess_liveproctoring
 * @copyright 2026 Energy & Water Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once(__DIR__ . '/rule.php');

$cmid      = required_param('cmid',      PARAM_INT);
$quizid    = required_param('quizid',    PARAM_INT);
$attemptid = required_param('attemptid', PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'quiz');
require_login($course, false, $cm);

$context = context_module::instance($cmid, MUST_EXIST);
require_capability('quizaccess/liveproctoring:viewmonitor', $context);
require_sesskey();

header('Content-Type: application/json; charset=utf-8');

$attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid]);
if (!$attempt || (int) $attempt->quiz !== (int) $quizid || (int) $cm->instance !== (int) $quizid) {
    echo json_encode(['success' => false, 'error' => 'invalid_attempt']);
    exit;
}

if (empty($_FILES['video']) || !is_uploaded_file($_FILES['video']['tmp_name'])) {
    echo json_encode(['success' => false, 'error' => 'no_video_uploaded']);
    exit;
}

$fs = get_file_storage();
$timestamp = time();
$filename  = 'evidence_' . $attemptid . '_' . $timestamp . '.webm';

$filerecord = [
    'contextid'   => $context->id,
    'component'   => 'quizaccess_liveproctoring',
    'filearea'    => 'evidence_videos',
    'itemid'      => $attemptid,
    'filepath'    => '/',
    'filename'    => $filename,
    'userid'      => $attempt->userid,
    'timecreated' => $timestamp,
];

try {
    $file = $fs->create_file_from_pathname($filerecord, $_FILES['video']['tmp_name']);

    // Log the evidence recording event in DB.
    $ev = new \stdClass();
    $ev->userid       = $attempt->userid;
    $ev->cmid         = $cmid;
    $ev->attemptid    = $attemptid;
    $ev->eventtype    = 'proctor_recording';
    $ev->severity     = 'high';
    $ev->warningcount = 0;
    $ev->message      = 'Recorded Evidence Clip (' . $filename . ')';
    $ev->timecreated  = $timestamp;
    $DB->insert_record('quizaccess_liveproctoring_events', $ev);

    echo json_encode(['success' => true, 'filename' => $filename]);
} catch (\Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
