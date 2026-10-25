<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Post-exam report: Snapshots and AI analysis results per student.
 *
 * @package   quizaccess_liveproctoring
 * @copyright 2026 Energy & Water Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once(__DIR__ . '/rule.php');

$cmid   = required_param('cmid',   PARAM_INT);
$quizid = required_param('quizid', PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'quiz');
require_login($course, true, $cm);

$context = context_module::instance($cmid, MUST_EXIST);
require_capability('quizaccess/liveproctoring:viewsnapshots', $context);

$quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
if ((int) $cm->instance !== (int) $quiz->id) {
    throw new moodle_exception('invalidcoursemodule');
}

$PAGE->set_url(new moodle_url('/mod/quiz/accessrule/liveproctoring/report.php',
    ['cmid' => $cmid, 'quizid' => $quizid]));
$PAGE->set_context($context);
$PAGE->set_title(format_string($quiz->name) . ' — ' . get_string('viewreport', 'quizaccess_liveproctoring'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($quiz->name) . ' — ' . get_string('viewreport', 'quizaccess_liveproctoring'));

// Fetch snapshot summary per student.
$sql = "SELECT s.userid, u.firstname, u.lastname, u.username,
               COUNT(s.id) AS snapcount,
               MAX(s.risk_score) AS maxrisk,
               AVG(CASE WHEN s.risk_score >= 0 THEN s.risk_score END) AS avgrisk,
               SUM(s.suspicious) AS suspiciouscount
          FROM {quizaccess_liveproctoring_snaps} s
          JOIN {user} u ON u.id = s.userid
         WHERE s.quizid = :quizid
         GROUP BY s.userid, u.firstname, u.lastname, u.username
         ORDER BY maxrisk DESC, suspiciouscount DESC";

$students = $DB->get_records_sql($sql, ['quizid' => $quizid]);

if (empty($students)) {
    echo $OUTPUT->notification(
        get_string('activestudents', 'quizaccess_liveproctoring') . ': 0',
        'info'
    );
    echo $OUTPUT->footer();
    exit;
}

// Summary table.
echo html_writer::start_tag('table', ['class' => 'generaltable fullwidth']);
echo html_writer::start_tag('thead');
echo html_writer::tag('tr',
    html_writer::tag('th', get_string('student', 'moodle')) .
    html_writer::tag('th', 'Snapshots') .
    html_writer::tag('th', 'Max Risk') .
    html_writer::tag('th', 'Avg Risk') .
    html_writer::tag('th', get_string('suspicious', 'quizaccess_liveproctoring')) .
    html_writer::tag('th', '')
);
echo html_writer::end_tag('thead');
echo html_writer::start_tag('tbody');

foreach ($students as $st) {
    $riskclass = '';
    if ($st->maxrisk >= 70) {
        $riskclass = 'table-danger';
    } else if ($st->maxrisk >= 40) {
        $riskclass = 'table-warning';
    }

    $detailurl = new moodle_url('/mod/quiz/accessrule/liveproctoring/report.php',
        ['cmid' => $cmid, 'quizid' => $quizid, 'userid' => $st->userid]);

    echo html_writer::tag('tr',
        html_writer::tag('td', fullname($st)) .
        html_writer::tag('td', (int) $st->snapcount) .
        html_writer::tag('td', $st->maxrisk >= 0 ? (int) $st->maxrisk : '—') .
        html_writer::tag('td', $st->avgrisk !== null ? round($st->avgrisk, 1) : '—') .
        html_writer::tag('td', (int) $st->suspiciouscount) .
        html_writer::tag('td', html_writer::link($detailurl, 'View Snapshots')),
        ['class' => $riskclass]
    );
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');

// Detail view for a specific student.
if ($userid > 0) {
    $targetuser = $DB->get_record('user', ['id' => $userid]);
    if ($targetuser) {
        $snaps = $DB->get_records('quizaccess_liveproctoring_snaps',
            ['quizid' => $quizid, 'userid' => $userid],
            'timecreated ASC'
        );

        echo $OUTPUT->heading(fullname($targetuser) . ' — Snapshots', 3);

    echo html_writer::start_tag('div', ['class' => 'd-flex flex-wrap gap-3 mt-3']);
    foreach ($snaps as $snap) {
        if (empty($snap->thumbdata)) {
            continue;
        }
        $risk = (int) $snap->risk_score;
        $borderclass = $risk >= 70 ? 'border-danger' : ($risk >= 40 ? 'border-warning' : 'border-success');

        echo html_writer::tag('div',
            html_writer::tag('img', '', [
                'src'   => $snap->thumbdata,
                'style' => 'width:140px;border-radius:6px;',
                'class' => 'border border-3 ' . $borderclass,
                'title' => 'Risk: ' . $risk . ' | ' . userdate($snap->timecreated),
            ]) .
            html_writer::tag('div',
                html_writer::tag('small', 'Risk: ' . ($risk >= 0 ? $risk : '—') . '/100', ['class' => 'text-muted']) .
                html_writer::tag('br', '') .
                html_writer::tag('small', userdate($snap->timecreated, '%H:%M:%S'), ['class' => 'text-muted']),
                ['class' => 'text-center mt-1']
            ),
            ['class' => 'text-center']
        );
    }
        echo html_writer::end_tag('div');
    }
}

echo $OUTPUT->footer();
