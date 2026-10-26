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

// CRITICAL: Immediately release the PHP session lock so that long-running SSE streaming
// does NOT block other requests (like report.php, quiz navigation, or other browser tabs).
\core\session\manager::write_close();

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

$groupid = optional_param('group', 0, PARAM_INT);

while ((time() - $starttime) < $maxruntime) {
    // In a live exam hall, only show students who are ACTUALLY live/active right now in this exam
    // (active within the last 2 hours or who pushed a camera snapshot recently).
    // Stale/abandoned attempts from days ago are excluded from the live meeting.
    $activesince = time() - (2 * 3600);

    $groupsql = "";
    $params = [
        'quizid'       => $quizid,
        'activesince'  => $activesince,
        'activesince2' => $activesince,
    ];
    if ($groupid > 0) {
        $groupsql = " AND qa.userid IN (SELECT gm.userid FROM {groups_members} gm WHERE gm.groupid = :groupid) ";
        $params['groupid'] = $groupid;
    }

    $sql = "SELECT qa.userid, qa.id AS attemptid, qa.timestart, qa.timemodified,
                   u.firstname, u.lastname, u.username,
                   s.risk_score, s.face_detected, s.face_count,
                   s.suspicious, s.thumbdata, s.timecreated AS snaptime
              FROM {quiz_attempts} qa
              JOIN {user} u ON u.id = qa.userid
         LEFT JOIN {quizaccess_liveproctoring_snaps} s
                ON s.quizid = qa.quiz
               AND s.userid = qa.userid
               AND s.id = (
                       SELECT MAX(s2.id)
                         FROM {quizaccess_liveproctoring_snaps} s2
                        WHERE s2.quizid = qa.quiz
                          AND s2.userid = qa.userid
                   )
             WHERE qa.quiz = :quizid
               AND qa.state = 'inprogress'
               {$groupsql}
               AND (qa.timemodified >= :activesince OR s.timecreated >= :activesince2)
          ORDER BY COALESCE(s.risk_score, 0) DESC, qa.timemodified DESC";

    $rows = $DB->get_records_sql($sql, $params);

    // Aggregate proctoring events by student for this quiz/cmid (within the same active window).
    $eventstats = $DB->get_records_sql(
        "SELECT userid,
                COUNT(*) AS total_events,
                SUM(CASE WHEN severity = 'high' THEN 1 ELSE 0 END) AS high_count,
                SUM(CASE WHEN severity = 'medium' THEN 1 ELSE 0 END) AS med_count,
                SUM(CASE WHEN eventtype = 'tab_switch' OR eventtype LIKE 'breach_tab%' THEN 1 ELSE 0 END) AS tab_count,
                SUM(CASE WHEN eventtype IN ('screenshot_attempt', 'breach_screenshot_attempt') THEN 1 ELSE 0 END) AS screenshot_count,
                SUM(CASE WHEN eventtype IN ('speaking_detected', 'breach_speaking_detected') THEN 1 ELSE 0 END) AS speech_count,
                SUM(CASE WHEN eventtype IN ('virtual_camera_detected', 'breach_virtual_camera_detected') THEN 1 ELSE 0 END) AS virtual_cam_count,
                SUM(CASE WHEN eventtype IN ('camera_blocked', 'camera_denied', 'camera_lost') THEN 1 ELSE 0 END) AS cam_issues,
                MAX(timecreated) AS last_event_time
           FROM {quizaccess_liveproctoring_events}
          WHERE cmid = :cmid
            AND timecreated >= :activesince
          GROUP BY userid",
        ['cmid' => $cmid, 'activesince' => $activesince]
    );

    $students = [];
    foreach ($rows as $row) {
        $uid = (int) $row->userid;
        $ev = $eventstats[$uid] ?? null;

        $hasthumb = !empty($row->thumbdata);
        $airisk = isset($row->risk_score) && $row->risk_score >= 0 ? (int) $row->risk_score : 0;

        // Calculate dynamic behavioral risk.
        $tabcount        = $ev ? (int) $ev->tab_count : 0;
        $camissues       = $ev ? (int) $ev->cam_issues : 0;
        $screenshotcount = $ev ? (int) $ev->screenshot_count : 0;
        $speechcount     = $ev ? (int) $ev->speech_count : 0;
        $virtualcamcount = $ev ? (int) $ev->virtual_cam_count : 0;
        $highcount       = $ev ? (int) $ev->high_count : 0;
        $medcount        = $ev ? (int) $ev->med_count : 0;

        $behaviorrisk = ($tabcount * 35) + ($camissues * 75) + ($screenshotcount * 50) + ($speechcount * 40) + ($virtualcamcount * 80) + ($highcount > 0 ? 20 : 0) + ($medcount * 10);
        $totalrisk = min(100, max($airisk, $behaviorrisk));

        $issuspicious = ($totalrisk >= 70 || !empty($row->suspicious) || $camissues > 0 || $tabcount >= 2 || $screenshotcount > 0 || $virtualcamcount > 0);

        // Status message displayed under risk score.
        if ($virtualcamcount > 0) {
            $statusmsg = 'كاميرا وهمية محظورة 🚫';
        } else if ($camissues > 0) {
            $statusmsg = 'الكاميرا محجوبة / مغلقة ❌';
        } else if ($screenshotcount > 0) {
            $statusmsg = "التقاط شاشة ({$screenshotcount}) 📸";
        } else if ($speechcount > 0) {
            $statusmsg = "تحدث / صوت مرصود 🗣️";
        } else if ($tabcount > 0) {
            $statusmsg = "تبديل نافذة ({$tabcount}) ⚠️";
        } else if (!$hasthumb) {
            $statusmsg = 'متصل (جارٍ تشغيل الكاميرا...)';
        } else if (isset($row->face_detected) && $row->face_detected == 0) {
            $statusmsg = 'الوجه غير ظاهر ⚠️';
        } else if (isset($row->face_count) && $row->face_count > 1) {
            $statusmsg = 'أكثر من وجه بالصورة ⚠️';
        } else {
            $statusmsg = 'نشط وطبيعي ✅';
        }

        $lasteventtime = $ev ? (int) $ev->last_event_time : 0;
        $lastseen = max(!empty($row->snaptime) ? (int) $row->snaptime : 0, (int) $row->timemodified, $lasteventtime);

        $students[] = [
            'userid'       => $uid,
            'attemptid'    => (int) $row->attemptid,
            'name'         => fullname($row) . ' (' . $row->username . ')',
            'risk_score'   => $totalrisk,
            'face_detected'=> isset($row->face_detected) ? (bool) $row->face_detected : true,
            'face_count'   => isset($row->face_count) ? (int) $row->face_count : 1,
            'suspicious'   => $issuspicious,
            'thumb'        => $row->thumbdata ?? '',
            'lastseen'     => $lastseen,
            'status_msg'   => $statusmsg,
        ];
    }

    // Sort students: highest risk / suspicious first.
    usort($students, function($a, $b) {
        if ($a['suspicious'] !== $b['suspicious']) {
            return $b['suspicious'] ? 1 : -1;
        }
        if ($a['risk_score'] !== $b['risk_score']) {
            return $b['risk_score'] <=> $a['risk_score'];
        }
        return $b['lastseen'] <=> $a['lastseen'];
    });

    // Get recent high-risk events (last 30 seconds).
    $since = time() - 30;
    $alerts = $DB->get_records_sql(
        "SELECT e.*, u.firstname, u.lastname
           FROM {quizaccess_liveproctoring_events} e
           JOIN {user} u ON u.id = e.userid
          WHERE e.cmid = :cmid
            AND (e.severity = 'high' OR e.eventtype IN ('screenshot_attempt', 'speaking_detected', 'tab_switch', 'virtual_camera_detected', 'camera_blocked', 'face_disappeared', 'multi_face'))
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

    // Deallocate result sets and trigger garbage collection to ensure flat memory footprint.
    unset($rows, $eventstats, $recentalerts, $students, $alertlist, $payload);
    gc_collect_cycles();

    sleep($interval);

    // Check if client disconnected.
    if (connection_aborted()) {
        break;
    }
}

echo "data: " . json_encode(['disconnect' => true]) . "\n\n";
flush();
