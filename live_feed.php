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
 * Server-Sent Events (SSE) feed for the Live Monitor Dashboard.
 *
 * Teachers keep this endpoint open; it streams JSON updates every 5 seconds
 * containing the latest snapshot thumbnails and risk scores for all active
 * students in the given quiz.
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

// SSE headers — disable output buffering.
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // For Nginx.

if (ob_get_level()) {
    ob_end_clean();
}

$interval   = 5;   // Seconds between pushes.
$maxruntime = 300; // Max 5 minutes per connection (client reconnects).
$starttime  = time();

while ((time() - $starttime) < $maxruntime) {
    // Load the most recent snapshot per active student in this quiz.
    $sql = "SELECT s.userid, s.risk_score, s.face_detected, s.face_count,
                   s.suspicious, s.thumbdata, s.timecreated,
                   u.firstname, u.lastname, u.username
              FROM {quizaccess_liveproctoring_snaps} s
              JOIN {user} u ON u.id = s.userid
             WHERE s.quizid = :quizid
               AND s.id IN (
                       SELECT MAX(s2.id)
                         FROM {quizaccess_liveproctoring_snaps} s2
                        WHERE s2.quizid = :quizid2
                        GROUP BY s2.userid
                   )
          ORDER BY s.risk_score DESC";

    $rows = $DB->get_records_sql($sql, ['quizid' => $quizid, 'quizid2' => $quizid]);

    $students = [];
    foreach ($rows as $row) {
        $students[] = [
            'userid'       => (int) $row->userid,
            'name'         => fullname($row),
            'risk_score'   => (int) $row->risk_score,
            'face_detected'=> (bool) $row->face_detected,
            'face_count'   => (int) $row->face_count,
            'suspicious'   => (bool) $row->suspicious,
            'thumb'        => $row->thumbdata ?? '',
            'lastseen'     => (int) $row->timecreated,
        ];
    }

    // Get recent high-risk events (last 30 seconds).
    $since = time() - 30;
    $alerts = $DB->get_records_sql(
        "SELECT e.*, u.firstname, u.lastname
           FROM {quizaccess_liveproctoring_events} e
           JOIN {user} u ON u.id = e.userid
          WHERE e.cmid = :cmid
            AND e.severity = 'high'
            AND e.timecreated >= :since
          ORDER BY e.timecreated DESC
          LIMIT 10",
        ['cmid' => $cmid, 'since' => $since]
    );

    $alertlist = [];
    foreach ($alerts as $alert) {
        $alertlist[] = [
            'userid'    => (int) $alert->userid,
            'name'      => fullname($alert),
            'eventtype' => $alert->eventtype,
            'message'   => $alert->message,
            'time'      => (int) $alert->timecreated,
        ];
    }

    $payload = json_encode([
        'students'   => array_values($students),
        'alerts'     => $alertlist,
        'servertime' => time(),
        'total'      => count($students),
        'suspicious' => count(array_filter($students, fn($s) => $s['suspicious'])),
    ], JSON_HEX_TAG | JSON_HEX_AMP);

    echo "data: {$payload}\n\n";
    flush();

    sleep($interval);

    // Check if client disconnected.
    if (connection_aborted()) {
        break;
    }
}

echo "data: " . json_encode(['disconnect' => true]) . "\n\n";
flush();
