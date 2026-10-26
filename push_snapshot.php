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
 * Endpoint: Student pushes a snapshot during exam.
 *
 * Accepts a base64 JPEG image via POST, stores it in Moodle Files,
 * optionally calls the Custom AI API, and returns a JSON response.
 *
 * @package   quizaccess_liveproctoring
 * @copyright 2026 Energy & Water Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once(__DIR__ . '/rule.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');

use quizaccess_liveproctoring\ai_analyzer;

// Only POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['error' => 'Method not allowed']));
}

// Validate sesskey.
if (!confirm_sesskey()) {
    http_response_code(403);
    die(json_encode(['error' => 'Invalid session key']));
}

require_login();

$cmid      = required_param('cmid',      PARAM_INT);
$quizid    = required_param('quizid',    PARAM_INT);
$attemptid = required_param('attemptid', PARAM_INT);
$imagedata = required_param('image',     PARAM_RAW); // base64 data URI

// Context and capability check.
$context = context_module::instance($cmid, MUST_EXIST);
require_capability('mod/quiz:attempt', $context);

// Verify cm belongs to the supplied quiz (cross-module IDOR guard).
list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'quiz');
if ((int) $cm->instance !== (int) $quizid) {
    http_response_code(403);
    die(json_encode(['error' => 'Module/quiz mismatch']));
}

// Verify the attempt belongs to the current user.
$attempt = $DB->get_record('quiz_attempts', ['id' => $attemptid, 'quiz' => $quizid], '*', MUST_EXIST);
if ($attempt->userid != $USER->id) {
    http_response_code(403);
    die(json_encode(['error' => 'Access denied']));
}

// Strip data URI prefix.
$base64 = preg_replace('/^data:[^;]+;base64,/', '', $imagedata);
if (empty($base64) || !base64_decode($base64, true)) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid image data']));
}

$imageraw = base64_decode($base64);

// --- Save image to Moodle Files ---
$fs = get_file_storage();
$now = time();
$filename = 'snap_' . $USER->id . '_' . $now . '.jpg';

$fileinfo = [
    'contextid' => $context->id,
    'component' => 'quizaccess_liveproctoring',
    'filearea'  => 'snapshots',
    'itemid'    => $attemptid,
    'filepath'  => '/',
    'filename'  => $filename,
];

// Generate a small thumbnail (max 120px wide) for live dashboard.
$thumbdata = '';
if (function_exists('imagecreatefromstring')) {
    $src = @imagecreatefromstring($imageraw);
    if ($src !== false) {
        $origw = imagesx($src);
        $origh = imagesy($src);
        $neww  = min(120, $origw);
        $newh  = (int) round($origh * ($neww / $origw));
        $thumb = imagecreatetruecolor($neww, $newh);
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $neww, $newh, $origw, $origh);
        ob_start();
        imagejpeg($thumb, null, 60);
        $thumbraw  = ob_get_clean();
        $thumbdata = 'data:image/jpeg;base64,' . base64_encode($thumbraw);
        imagedestroy($src);
        imagedestroy($thumb);
    }
}

// Store full image.
$storedfile = $fs->create_file_from_string($fileinfo, $imageraw);

// --- Identity Verification (reference photo) ---
$settings  = quizaccess_liveproctoring::get_quiz_proctoring_settings($quizid);
$identityMatch = true;
$identityReason = '';

if (!empty($settings) && !empty($settings->referencerequired)) {
    $refrecord = $DB->get_record('quizaccess_liveproctoring_refs', ['userid' => $USER->id]);
    if (!$refrecord) {
        // Store first snapshot as reference photo.
        $refinfo = [
            'contextid' => $context->id,
            'component' => 'quizaccess_liveproctoring',
            'filearea'  => 'references',
            'itemid'    => $USER->id,
            'filepath'  => '/',
            'filename'  => 'ref_' . $USER->id . '_' . $now . '.jpg',
        ];
        $refstored = $fs->create_file_from_string($refinfo, $imageraw);
        if ($refstored) {
            $ref = new stdClass();
            $ref->userid       = $USER->id;
            $ref->fileid       = $refstored->get_id();
            $ref->timecreated  = $now;
            $ref->timemodified = $now;
            $DB->insert_record('quizaccess_liveproctoring_refs', $ref);
        }
        $identityMatch = true;
        $identityReason = 'Reference photo captured.';
    } else {
        $reffile = $fs->get_file_by_id($refrecord->fileid);
        if ($reffile && !$reffile->is_directory()) {
            $refBase64 = base64_encode($reffile->get_content());
            $analyzer = new ai_analyzer();
            $cmp = $analyzer->compare_faces($refBase64, $base64);
            $identityMatch = (bool) $cmp['match'];
            $identityReason = $cmp['reason'] ?? '';
            if (!$identityMatch) {
                $ev = new stdClass();
                $ev->userid      = $USER->id;
                $ev->cmid        = $cmid;
                $ev->attemptid   = $attemptid;
                $ev->eventtype   = 'identity_mismatch';
                $ev->severity    = 'high';
                $ev->warningcount = 0;
                $ev->message     = substr($identityReason, 0, 255);
                $ev->timecreated = $now;
                $DB->insert_record('quizaccess_liveproctoring_events', $ev);
            }
        }
    }
}

// --- AI Analysis (async via scheduled task) ---
// Risk analysis is deferred to the analyze_snapshots scheduled task to avoid
// blocking the student request. Only identity verification (if enabled) ran above.
$airesult  = ['risk_score' => -1, 'face_detected' => false, 'face_count' => 0,
              'suspicious' => false, 'error' => 'Pending AI analysis (async)',
              'identity_match'  => $identityMatch,
              'identity_reason' => $identityReason];

// --- Store snapshot record ---
$snaprecord = new stdClass();
$snaprecord->userid        = $USER->id;
$snaprecord->cmid          = $cmid;
$snaprecord->quizid        = $quizid;
$snaprecord->attemptid     = $attemptid;
$snaprecord->fileid        = $storedfile->get_id();
$snaprecord->thumbdata     = $thumbdata;
$snaprecord->ai_result     = json_encode($airesult);
$snaprecord->risk_score    = (int) ($airesult['risk_score'] ?? -1);
$snaprecord->face_detected = (int) ($airesult['face_detected'] ?? 0);
$snaprecord->face_count    = (int) ($airesult['face_count'] ?? 0);
$snaprecord->suspicious    = (int) ($airesult['suspicious'] ?? 0);
$snaprecord->timecreated   = $now;

$DB->insert_record('quizaccess_liveproctoring_snaps', $snaprecord);

// --- Log suspicious events ---
if (!empty($airesult['suspicious']) || (!empty($airesult['face_detected']) && $airesult['face_count'] > 1)) {
    $event = new stdClass();
    $event->userid      = $USER->id;
    $event->cmid        = $cmid;
    $event->attemptid   = $attemptid;
    $event->eventtype   = $airesult['face_count'] > 1 ? 'multi_face' : 'ai_alert';
    $event->severity    = $airesult['risk_score'] > 70 ? 'high' : 'medium';
    $event->message     = $airesult['reason'] ?? '';
    $event->timecreated = $now;
    $DB->insert_record('quizaccess_liveproctoring_events', $event);
}

// Return lightweight response.
header('Content-Type: application/json');
echo json_encode([
    'success'      => true,
    'risk_score'   => $snaprecord->risk_score,
    'suspicious'   => (bool) $snaprecord->suspicious,
    'face_detected'=> (bool) $snaprecord->face_detected,
]);
