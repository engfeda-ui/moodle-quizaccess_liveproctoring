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
$quizid = optional_param('quizid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'quiz');
require_login($course, true, $cm);

if (empty($quizid)) {
    $quizid = (int) $cm->instance;
}

$context = context_module::instance($cmid, MUST_EXIST);
require_capability('quizaccess/liveproctoring:viewsnapshots', $context);

$quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
if ((int) $cm->instance !== (int) $quiz->id) {
    throw new moodle_exception('invalidcoursemodule');
}

// Handle bulk delete of all snapshots for this quiz.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && optional_param('deleteall', 0, PARAM_INT)) {
    confirm_sesskey();
    require_capability('quizaccess/liveproctoring:deletesnapshots', $context);
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'quizaccess_liveproctoring', 'snapshots');
    $DB->delete_records('quizaccess_liveproctoring_snaps', ['quizid' => $quizid]);
    redirect(
        new moodle_url('/mod/quiz/accessrule/liveproctoring/report.php', ['cmid' => $cmid, 'quizid' => $quizid]),
        'All snapshots for this quiz have been deleted.',
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Handle course-level bulk delete of all snapshots.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && optional_param('deletecourse', 0, PARAM_INT)) {
    confirm_sesskey();
    require_capability('quizaccess/liveproctoring:deletesnapshots', $context);
    $fs = get_file_storage();
    $cms = get_coursemodules_in_course('quiz', $course->id);
    foreach ($cms as $cmobj) {
        $ctx = context_module::instance($cmobj->id);
        $fs->delete_area_files($ctx->id, 'quizaccess_liveproctoring', 'snapshots');
    }
    $DB->delete_records_select(
        'quizaccess_liveproctoring_snaps',
        'quizid IN (SELECT id FROM {quiz} WHERE course = :courseid)',
        ['courseid' => $course->id]
    );
    redirect(
        new moodle_url('/mod/quiz/accessrule/liveproctoring/report.php', ['cmid' => $cmid, 'quizid' => $quizid]),
        'All snapshots for this course have been deleted.',
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
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

// Bulk delete control (teachers with deletesnapshots capability).
if (has_capability('quizaccess/liveproctoring:deletesnapshots', $context)) {
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => (new moodle_url('/mod/quiz/accessrule/liveproctoring/report.php',
            ['cmid' => $cmid, 'quizid' => $quizid]))->out(false),
        'class'  => 'mt-3',
        'onsubmit' => "return confirm('Delete ALL snapshots for this quiz? This cannot be undone.');",
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'deleteall', 'value' => '1']);
    echo html_writer::tag('button', '🗑️ Delete all snapshots', ['type' => 'submit', 'class' => 'btn btn-danger btn-sm']);
    echo html_writer::end_tag('form');

    // Course-level bulk delete.
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => (new moodle_url('/mod/quiz/accessrule/liveproctoring/report.php',
            ['cmid' => $cmid, 'quizid' => $quizid]))->out(false),
        'class'  => 'mt-2',
        'onsubmit' => "return confirm('Delete ALL snapshots for the entire course? This cannot be undone.');",
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'deletecourse', 'value' => '1']);
    echo html_writer::tag('button', '🗑️ Delete all snapshots (course)', ['type' => 'submit', 'class' => 'btn btn-outline-danger btn-sm']);
    echo html_writer::end_tag('form');
}

// Detail view for a specific student.
if ($userid > 0) {
    $targetuser = $DB->get_record('user', ['id' => $userid]);
    if ($targetuser) {
        echo html_writer::tag('hr', '', ['class' => 'my-4']);
        echo $OUTPUT->heading(fullname($targetuser) . ' (' . $targetuser->username . ')', 2);

        // 1. Evidence Video Clips.
        $fs = get_file_storage();
        $userattempts = $DB->get_records('quiz_attempts', ['quiz' => $quizid, 'userid' => $userid], 'attempt ASC');
        $evidencefiles = [];
        foreach ($userattempts as $uatt) {
            $files = $fs->get_area_files($context->id, 'quizaccess_liveproctoring', 'evidence_videos', $uatt->id, 'timecreated DESC', false);
            foreach ($files as $f) {
                $evidencefiles[] = ['file' => $f, 'attempt' => $uatt];
            }
        }

        echo $OUTPUT->heading('🎬 ' . get_string('evidence_videos', 'quizaccess_liveproctoring'), 3);
        if (!empty($evidencefiles)) {
            echo html_writer::start_tag('div', ['class' => 'row g-3 my-2']);
            foreach ($evidencefiles as $item) {
                $f = $item['file'];
                $att = $item['attempt'];
                $videourl = moodle_url::make_pluginfile_url(
                    $context->id,
                    'quizaccess_liveproctoring',
                    'evidence_videos',
                    $att->id,
                    '/',
                    $f->get_filename()
                );
                $filesize = round($f->get_filesize() / 1048576, 2);

                echo html_writer::start_tag('div', ['class' => 'col-md-6 col-lg-4']);
                echo html_writer::start_tag('div', ['class' => 'card h-100 shadow-sm border-danger']);
                echo html_writer::start_tag('div', ['class' => 'card-body p-3']);
                echo html_writer::tag('div',
                    html_writer::tag('strong', '📹 مقطع إثبات رسمي (المحاولة ' . $att->attempt . ')') .
                    html_writer::tag('span', 'دليل خرق', ['class' => 'badge bg-danger float-end']),
                    ['class' => 'mb-2']
                );
                echo html_writer::tag('video', '', [
                    'src'      => $videourl->out(false),
                    'controls' => 'controls',
                    'style'    => 'width:100%;max-height:240px;background:#000;border-radius:6px;',
                ]);
                echo html_writer::tag('div',
                    html_writer::tag('small', '📅 ' . userdate($f->get_timecreated()) . ' | 💾 ' . $filesize . ' MB', ['class' => 'text-muted']),
                    ['class' => 'mt-2 text-center']
                );
                echo html_writer::end_tag('div');
                echo html_writer::end_tag('div');
                echo html_writer::end_tag('div');
            }
            echo html_writer::end_tag('div');
        } else {
            echo html_writer::tag('div',
                get_string('no_evidence_videos', 'quizaccess_liveproctoring'),
                ['class' => 'alert alert-light border text-muted my-2']
            );
        }

        // 2. Breach & Security Events Log.
        $events = $DB->get_records_sql(
            "SELECT e.*
               FROM {quizaccess_liveproctoring_events} e
              WHERE e.cmid = :cmid AND e.userid = :userid
           ORDER BY e.timecreated DESC",
            ['cmid' => $cmid, 'userid' => $userid]
        );

        echo $OUTPUT->heading('🚨 ' . get_string('breach_events', 'quizaccess_liveproctoring'), 3);
        if (!empty($events)) {
            $eventlabels = [
                'screenshot_attempt'        => '📸 التقاط صورة للشاشة (Screenshot)',
                'speaking_detected'         => '🗣️ تحدث / أصوات مرتفعة',
                'tab_switch'                => '⚠️ تبديل نافذة / مغادرة صفحة الامتحان',
                'camera_blocked'            => '❌ انقطاع أو حجب الكاميرا',
                'camera_denied'             => '❌ رفض إذن الكاميرا أو المايك',
                'camera_lost'               => '⚠️ فقدان إشارة الكاميرا',
                'virtual_camera_detected'   => '🚫 استخدام كاميرا وهمية (Virtual Camera)',
                'multi_monitor_detected'    => '🖥️ استخدام شاشات متعددة (Multi-Monitor)',
                'force_terminated'          => '🚪 إنهاء وطرد إجباري من المراقب',
                'proctor_recording'         => '🎬 تسجيل مقطع إثبات فيديو بواسطة المراقب',
                'teacher_warning'           => '📢 إنذار مباشر من المراقب',
                'auto_submitted'            => '🔒 تسليم تلقائي لتجاوز حد المخالفات',
                'identity_mismatch'         => '👤 عدم تطابق هوية الطالب',
                'multi_face'                => '👥 ظهور أكثر من شخص أمام الكاميرا',
                'ai_alert'                  => '🤖 تنبيه سلوك مشبوه من الذكاء الاصطناعي',
            ];

            echo html_writer::start_tag('table', ['class' => 'table table-hover table-bordered my-2']);
            echo html_writer::start_tag('thead', ['class' => 'table-dark']);
            echo html_writer::tag('tr',
                html_writer::tag('th', 'نوع الحدث / المخالفة') .
                html_writer::tag('th', 'مستوى الخطورة') .
                html_writer::tag('th', 'رقم الإنذار') .
                html_writer::tag('th', 'التفاصيل / الملاحظة') .
                html_writer::tag('th', 'الوقت')
            );
            echo html_writer::end_tag('thead');
            echo html_writer::start_tag('tbody');
            foreach ($events as $ev) {
                $evlabel = $eventlabels[$ev->eventtype] ?? $ev->eventtype;
                $sevbadge = $ev->severity === 'high' ? 'bg-danger' : ($ev->severity === 'medium' ? 'bg-warning text-dark' : 'bg-secondary');

                echo html_writer::tag('tr',
                    html_writer::tag('td', '<strong>' . s($evlabel) . '</strong>') .
                    html_writer::tag('td', html_writer::tag('span', strtoupper($ev->severity), ['class' => 'badge ' . $sevbadge])) .
                    html_writer::tag('td', (int) $ev->warningcount) .
                    html_writer::tag('td', s($ev->message)) .
                    html_writer::tag('td', userdate($ev->timecreated, '%Y-%m-%d %H:%M:%S'))
                );
            }
            echo html_writer::end_tag('tbody');
            echo html_writer::end_tag('table');
        } else {
            echo html_writer::tag('div', 'لا توجد مخالفات أمنية مسجلة لهذا الطالب.', ['class' => 'alert alert-success my-2']);
        }

        // 3. Snapshots Gallery.
        $snaps = $DB->get_records('quizaccess_liveproctoring_snaps',
            ['quizid' => $quizid, 'userid' => $userid],
            'timecreated ASC'
        );

        echo $OUTPUT->heading('📷 لقطات الكاميرا الدورية والخرقية (' . count($snaps) . ')', 3);
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
                    (!empty($snap->suspicious) ? html_writer::tag('span', '🚨 لقطة خرق', ['class' => 'badge bg-danger d-block mb-1']) : '') .
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
