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
 * Quiz access rule: Live Proctoring with AI.
 *
 * @package   quizaccess_liveproctoring
 * @copyright 2026 Energy & Water Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use mod_quiz\quiz_settings;

// Maintain compatibility with Moodle 4.2+ class loader and older versions.
if (class_exists('\mod_quiz\local\access_rule_base')) {
    if (!class_exists('quiz_access_rule_base', false)) {
        \class_alias('\mod_quiz\local\access_rule_base', 'quiz_access_rule_base');
    }
} else {
    require_once($CFG->dirroot . '/mod/quiz/accessrule/accessrulebase.php');
}

require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Live proctoring quiz access rule.
 */
class quizaccess_liveproctoring extends quiz_access_rule_base {

    /**
     * Return an appropriately configured instance of this rule, if it is applicable
     * to the given quiz, otherwise return null.
     *
     * @param quiz_settings $quizobj information about the quiz in question.
     * @param int $timenow the time that should be considered as 'now'.
     * @param bool $canignoretimelimits whether the current user is exempt from time limits.
     * @return self|null the rule, if applicable, else null.
     */
    public static function make(quiz_settings $quizobj, $timenow, $canignoretimelimits) {
        $quiz = $quizobj->get_quiz();
        if (isset($quiz->liveproctoring_enablecamera)) {
            if (empty($quiz->liveproctoring_enablecamera)) {
                return null;
            }
        } else {
            $settings = self::get_quiz_proctoring_settings($quizobj->get_quizid());
            if (empty($settings) || !$settings->enablecamera) {
                return null;
            }
        }

        return new self($quizobj, $timenow);
    }

    /**
     * Add any fields that this rule requires to the quiz settings form.
     *
     * @param mod_quiz_mod_form $quizform the quiz settings form that is being built.
     * @param MoodleQuickForm $mform the wrapped MoodleQuickForm.
     */
    public static function add_settings_form_fields(mod_quiz_mod_form $quizform, MoodleQuickForm $mform) {
        $mform->addElement('header', 'liveproctoring_header',
            get_string('pluginname', 'quizaccess_liveproctoring'));

        // Enable camera proctoring.
        $mform->addElement('selectyesno', 'liveproctoring_enablecamera',
            get_string('setting:enablecamera', 'quizaccess_liveproctoring'));
        $mform->setDefault('liveproctoring_enablecamera', 0);

        // Enable AI analysis.
        $mform->addElement('selectyesno', 'liveproctoring_enableai',
            get_string('setting:enableai', 'quizaccess_liveproctoring'));
        $mform->setDefault('liveproctoring_enableai', 0);
        $mform->disabledIf('liveproctoring_enableai', 'liveproctoring_enablecamera', 'eq', '0');

        // Snapshot interval (seconds).
        $mform->addElement('text', 'liveproctoring_snapshotinterval',
            get_string('setting:snapshotinterval', 'quizaccess_liveproctoring'));
        $mform->setType('liveproctoring_snapshotinterval', PARAM_INT);
        $mform->setDefault('liveproctoring_snapshotinterval', 30);
        $mform->disabledIf('liveproctoring_snapshotinterval', 'liveproctoring_enablecamera', 'eq', '0');

        // Warning limit before auto-submit.
        $mform->addElement('text', 'liveproctoring_warninglimit',
            get_string('setting:warninglimit', 'quizaccess_liveproctoring'));
        $mform->setType('liveproctoring_warninglimit', PARAM_INT);
        $mform->setDefault('liveproctoring_warninglimit', 3);

        // Auto-submit on warning limit.
        $mform->addElement('selectyesno', 'liveproctoring_autosubmit',
            get_string('setting:autosubmit', 'quizaccess_liveproctoring'));
        $mform->setDefault('liveproctoring_autosubmit', 0);

        // Show watermark on student screen.
        $mform->addElement('selectyesno', 'liveproctoring_showwatermark',
            get_string('setting:showwatermark', 'quizaccess_liveproctoring'));
        $mform->setDefault('liveproctoring_showwatermark', 1);

        // Block tab switching.
        $mform->addElement('selectyesno', 'liveproctoring_blocktabs',
            get_string('setting:blocktabs', 'quizaccess_liveproctoring'));
        $mform->setDefault('liveproctoring_blocktabs', 1);
    }

    /**
     * Save the settings for this rule when the form is submitted.
     *
     * @param object $quiz the data from the quiz settings form, as returned by the form.
     */
    public static function save_settings($quiz) {
        global $DB;

        $record = $DB->get_record('quizaccess_liveproctoring_quiz', ['quizid' => $quiz->id]);

        $data = new stdClass();
        $data->quizid             = $quiz->id;
        $data->enablecamera       = !empty($quiz->liveproctoring_enablecamera) ? 1 : 0;
        $data->enableai           = !empty($quiz->liveproctoring_enableai) ? 1 : 0;
        $data->snapshotinterval   = !empty($quiz->liveproctoring_snapshotinterval)
            ? (int) $quiz->liveproctoring_snapshotinterval : 30;
        $data->warninglimit       = !empty($quiz->liveproctoring_warninglimit)
            ? (int) $quiz->liveproctoring_warninglimit : 3;
        $data->autosubmit         = !empty($quiz->liveproctoring_autosubmit) ? 1 : 0;
        $data->showwatermark      = !empty($quiz->liveproctoring_showwatermark) ? 1 : 0;
        $data->blocktabs          = !empty($quiz->liveproctoring_blocktabs) ? 1 : 0;
        $data->referencerequired  = 0;

        if ($record) {
            $data->id = $record->id;
            $DB->update_record('quizaccess_liveproctoring_quiz', $data);
        } else {
            $DB->insert_record('quizaccess_liveproctoring_quiz', $data);
        }
    }

    /**
     * Delete the settings for this rule when the quiz is deleted.
     *
     * @param object $quiz the data from the database, as returned by the quizzes table query.
     */
    public static function delete_settings($quiz) {
        global $DB;
        $DB->delete_records('quizaccess_liveproctoring_quiz', ['quizid' => $quiz->id]);
        $DB->delete_records('quizaccess_liveproctoring_snaps', ['quizid' => $quiz->id]);
        $DB->delete_records('quizaccess_liveproctoring_events', ['cmid' => $quiz->cmid ?? 0]);
    }

    /**
     * Get the quiz settings defaults from the form.
     *
     * @param MoodleQuickForm $mform the wrapped MoodleQuickForm.
     * @param stdClass $quiz the quiz data from the form.
     * @param stdClass $context the quiz context.
     * @return array element names to values.
     */
    public static function get_settings_sql($quizid) {
        return [
            'liveproctoring.enablecamera AS liveproctoring_enablecamera,
             liveproctoring.enableai AS liveproctoring_enableai,
             liveproctoring.snapshotinterval AS liveproctoring_snapshotinterval,
             liveproctoring.warninglimit AS liveproctoring_warninglimit,
             liveproctoring.autosubmit AS liveproctoring_autosubmit,
             liveproctoring.showwatermark AS liveproctoring_showwatermark,
             liveproctoring.blocktabs AS liveproctoring_blocktabs',
            'LEFT JOIN {quizaccess_liveproctoring_quiz} liveproctoring ON liveproctoring.quizid = quiz.id',
            [],
        ];
    }

    /**
     * Prevent access to the quiz if camera not available (client-side check via JS).
     *
     * @return string|null an error message, or null if no block.
     */
    public function prevent_access() {
        // Camera permission check is done client-side (AMD module).
        return null;
    }

    /**
     * Information shown to students at the start of the quiz.
     *
     * @return string HTML formatted information.
     */
    public function description() {
        $context = $this->quizobj->get_context();
        if (has_capability('quizaccess/liveproctoring:viewmonitor', $context)) {
            $cmid   = $this->quizobj->get_cmid();
            $quizid = $this->quizobj->get_quizid();
            $monitorurl = new moodle_url('/mod/quiz/accessrule/liveproctoring/live_monitor.php',
                ['cmid' => $cmid, 'quizid' => $quizid]);
            $reporturl  = new moodle_url('/mod/quiz/accessrule/liveproctoring/report.php',
                ['cmid' => $cmid, 'quizid' => $quizid]);

            $html  = '<div class="alert alert-info d-flex align-items-center justify-content-between p-3 my-2" style="border-left: 5px solid #0f3460;">';
            $html .= '<div><strong>🎥 ' . get_string('pluginname', 'quizaccess_liveproctoring') . '</strong><br>';
            $html .= '<small class="text-muted">' . get_string('plugindescription', 'quizaccess_liveproctoring') . '</small></div>';
            $html .= '<div class="btn-group ms-3">';
            $html .= '<a href="' . $monitorurl->out(false) . '" class="btn btn-primary btn-sm" target="_blank" onclick="window.open(this.href,\'lp_monitor\',\'width=1200,height=800,scrollbars=yes\'); return false;">';
            $html .= '🔴 ' . get_string('livemonitor', 'quizaccess_liveproctoring') . '</a>';
            $html .= '<a href="' . $reporturl->out(false) . '" class="btn btn-outline-secondary btn-sm" target="_blank">';
            $html .= '📋 ' . get_string('viewreport', 'quizaccess_liveproctoring') . '</a>';
            $html .= '</div></div>';
            return $html;
        }

        return get_string('studentnotice', 'quizaccess_liveproctoring');
    }

    /**
     * Set up the attempt (start) page for this quiz attempt.
     *
     * @param quiz_attempt $attemptobj the current attempt.
     */
    public function setup_attempt_page($page) {
        global $PAGE, $USER, $CFG;

        $quiz    = $this->quizobj->get_quiz();
        $context = $this->quizobj->get_context();

        // Do not activate proctoring for teachers previewing the quiz.
        if (has_capability('quizaccess/liveproctoring:viewmonitor', $context)) {
            return;
        }

        $settings = self::get_quiz_proctoring_settings($quiz->id);

        if (empty($settings) || !$settings->enablecamera) {
            return;
        }

        // Pass config to AMD module.
        $config = [
            'cmid'             => $this->quizobj->get_cmid(),
            'quizid'           => $quiz->id,
            'userid'           => $USER->id,
            'snapshotinterval' => (int) ($settings->snapshotinterval ?? 30),
            'warninglimit'     => (int) ($settings->warninglimit ?? 3),
            'autosubmit'       => (bool) ($settings->autosubmit ?? false),
            'showwatermark'    => (bool) ($settings->showwatermark ?? true),
            'blocktabs'        => (bool) ($settings->blocktabs ?? true),
            'enableai'         => (bool) ($settings->enableai ?? false),
            'pushurl'          => (new moodle_url(
                '/mod/quiz/accessrule/liveproctoring/push_snapshot.php'
            ))->out(false),
            'sesskey'          => sesskey(),
            'username'         => fullname($USER),
            'strings'          => [
                'warningtitle'    => get_string('warningtitle', 'quizaccess_liveproctoring'),
                'warningmessage'  => get_string('warningmessage', 'quizaccess_liveproctoring'),
                'tabswitch'       => get_string('tabswitchwarning', 'quizaccess_liveproctoring'),
                'camerablocked'   => get_string('camerablocked', 'quizaccess_liveproctoring'),
                'autosubmit'      => get_string('autosubmitmessage', 'quizaccess_liveproctoring'),
                'warningcount'    => get_string('warningcount', 'quizaccess_liveproctoring'),
            ],
        ];

        $PAGE->requires->js_call_amd(
            'quizaccess_liveproctoring/proctoring_student',
            'init',
            [$config]
        );
    }

    /**
     * Get per-quiz proctoring settings from DB.
     *
     * @param int $quizid
     * @return stdClass|false
     */
    public static function get_quiz_proctoring_settings(int $quizid) {
        global $DB;
        return $DB->get_record('quizaccess_liveproctoring_quiz', ['quizid' => $quizid]);
    }
}
