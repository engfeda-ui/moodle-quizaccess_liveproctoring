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

        // Preferred webcam resolution (width in px).
        $mform->addElement('text', 'liveproctoring_resolution',
            get_string('setting:resolution', 'quizaccess_liveproctoring'));
        $mform->setType('liveproctoring_resolution', PARAM_INT);
        $mform->setDefault('liveproctoring_resolution', 640);
        $mform->disabledIf('liveproctoring_resolution', 'liveproctoring_enablecamera', 'eq', '0');

        // Warning limit before auto-submit.
        $mform->addElement('text', 'liveproctoring_warninglimit',
            get_string('setting:warninglimit', 'quizaccess_liveproctoring'));
        $mform->setType('liveproctoring_warninglimit', PARAM_INT);
        $mform->setDefault('liveproctoring_warninglimit', 3);

        // Auto-submit on warning limit.
        $mform->addElement('selectyesno', 'liveproctoring_autosubmit',
            get_string('setting:autosubmit', 'quizaccess_liveproctoring'));
        $mform->setDefault('liveproctoring_autosubmit', 0);

        // Require reference photo / identity verification.
        $mform->addElement('selectyesno', 'liveproctoring_referencerequired',
            get_string('setting:referencerequired', 'quizaccess_liveproctoring'));
        $mform->setDefault('liveproctoring_referencerequired', 0);
        $mform->disabledIf('liveproctoring_referencerequired', 'liveproctoring_enablecamera', 'eq', '0');

        // Show watermark on student screen.
        $mform->addElement('selectyesno', 'liveproctoring_showwatermark',
            get_string('setting:showwatermark', 'quizaccess_liveproctoring'));
        $mform->setDefault('liveproctoring_showwatermark', 1);

        // Block tab switching.
        $mform->addElement('selectyesno', 'liveproctoring_blocktabs',
            get_string('setting:blocktabs', 'quizaccess_liveproctoring'));
        $mform->setDefault('liveproctoring_blocktabs', 1);

        // Require microphone along with webcam.
        $mform->addElement('selectyesno', 'liveproctoring_requiremic',
            get_string('setting:requiremic', 'quizaccess_liveproctoring'));
        $mform->setDefault('liveproctoring_requiremic', 1);
        $mform->disabledIf('liveproctoring_requiremic', 'liveproctoring_enablecamera', 'eq', '0');

        // Detect speech and loud conversations.
        $mform->addElement('selectyesno', 'liveproctoring_detectspeech',
            get_string('setting:detectspeech', 'quizaccess_liveproctoring'));
        $mform->setDefault('liveproctoring_detectspeech', 1);
        $mform->disabledIf('liveproctoring_detectspeech', 'liveproctoring_enablecamera', 'eq', '0');

        // Detect & block screenshot attempts.
        $mform->addElement('selectyesno', 'liveproctoring_preventscreenshot',
            get_string('setting:preventscreenshot', 'quizaccess_liveproctoring'));
        $mform->setDefault('liveproctoring_preventscreenshot', 1);
        $mform->disabledIf('liveproctoring_preventscreenshot', 'liveproctoring_enablecamera', 'eq', '0');

        // Block OBS and virtual camera drivers.
        $mform->addElement('selectyesno', 'liveproctoring_blockvirtualcamera',
            get_string('setting:blockvirtualcamera', 'quizaccess_liveproctoring'));
        $mform->setDefault('liveproctoring_blockvirtualcamera', 1);
        $mform->disabledIf('liveproctoring_blockvirtualcamera', 'liveproctoring_enablecamera', 'eq', '0');

        // Block extended or secondary monitors.
        $mform->addElement('selectyesno', 'liveproctoring_blockmultiscreen',
            get_string('setting:blockmultiscreen', 'quizaccess_liveproctoring'));
        $mform->setDefault('liveproctoring_blockmultiscreen', 1);
        $mform->disabledIf('liveproctoring_blockmultiscreen', 'liveproctoring_enablecamera', 'eq', '0');

        // Reconnect grace period in seconds.
        $mform->addElement('text', 'liveproctoring_reconnectgrace',
            get_string('setting:reconnectgrace', 'quizaccess_liveproctoring'));
        $mform->setType('liveproctoring_reconnectgrace', PARAM_INT);
        $mform->setDefault('liveproctoring_reconnectgrace', 15);
        $mform->disabledIf('liveproctoring_reconnectgrace', 'liveproctoring_enablecamera', 'eq', '0');
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
        $data->referencerequired  = !empty($quiz->liveproctoring_referencerequired) ? 1 : 0;
        $data->resolution         = !empty($quiz->liveproctoring_resolution)
            ? (int) $quiz->liveproctoring_resolution : 640;
        $data->requiremic         = !empty($quiz->liveproctoring_requiremic) ? 1 : 0;
        $data->detectspeech       = !empty($quiz->liveproctoring_detectspeech) ? 1 : 0;
        $data->preventscreenshot  = !empty($quiz->liveproctoring_preventscreenshot) ? 1 : 0;
        $data->blockvirtualcamera = !empty($quiz->liveproctoring_blockvirtualcamera) ? 1 : 0;
        $data->blockmultiscreen   = !empty($quiz->liveproctoring_blockmultiscreen) ? 1 : 0;
        $data->reconnectgrace     = !empty($quiz->liveproctoring_reconnectgrace)
            ? (int) $quiz->liveproctoring_reconnectgrace : 15;

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
        $DB->delete_records('quizaccess_liveproctoring_live', ['quizid' => $quiz->id]);
        $DB->delete_records('quizaccess_liveproctoring_events', ['cmid' => $quiz->cmid ?? 0]);

        if (!empty($quiz->course)) {
            $fs = get_file_storage();
            $context = context_course::instance($quiz->course, IGNORE_MISSING);
            if ($context) {
                $fs->delete_area_files($context->id, 'quizaccess_liveproctoring', 'snapshots');
            }
        }
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
             liveproctoring.blocktabs AS liveproctoring_blocktabs,
             liveproctoring.resolution AS liveproctoring_resolution,
             liveproctoring.referencerequired AS liveproctoring_referencerequired,
             liveproctoring.requiremic AS liveproctoring_requiremic,
             liveproctoring.detectspeech AS liveproctoring_detectspeech,
             liveproctoring.preventscreenshot AS liveproctoring_preventscreenshot,
             liveproctoring.blockvirtualcamera AS liveproctoring_blockvirtualcamera,
             liveproctoring.blockmultiscreen AS liveproctoring_blockmultiscreen,
             liveproctoring.reconnectgrace AS liveproctoring_reconnectgrace',
            'LEFT JOIN {quizaccess_liveproctoring_quiz} liveproctoring ON liveproctoring.quizid = quiz.id',
            [],
        ];
    }

    /**
     * Prevent access to the quiz if proctoring violation limit reached or force-terminated.
     *
     * @return string|null an error message, or null if no block.
     */
    public function prevent_access() {
        global $DB, $USER;

        $quizid = $this->quizobj->get_quizid();
        $settings = self::get_quiz_proctoring_settings($quizid);
        if (empty($settings) || empty($settings->enablecamera)) {
            return null;
        }

        // Check if student has an attempt with termination flag.
        $sql = "SELECT l.terminate_flag, qa.id AS attemptid
                  FROM {quizaccess_liveproctoring_live} l
                  JOIN {quiz_attempts} qa ON qa.id = l.attemptid
                 WHERE qa.quiz = :quizid AND qa.userid = :userid AND l.terminate_flag = 1";
        $terminated = $DB->get_record_sql($sql, ['quizid' => $quizid, 'userid' => $USER->id]);
        if ($terminated) {
            return get_string('ejectedmessage', 'quizaccess_liveproctoring');
        }

        // Check warning limit if auto-submit is enabled.
        if (!empty($settings->autosubmit) && !empty($settings->warninglimit)) {
            $warnings = $DB->count_records_sql(
                "SELECT COUNT(e.id)
                   FROM {quizaccess_liveproctoring_events} e
                   JOIN {quiz_attempts} qa ON qa.id = e.attemptid
                  WHERE qa.quiz = :quizid
                    AND qa.userid = :userid
                    AND e.severity IN ('high', 'medium')",
                ['quizid' => $quizid, 'userid' => $USER->id]
            );
            if ($warnings >= (int) $settings->warninglimit) {
                return get_string('warninglimitexceeded', 'quizaccess_liveproctoring');
            }
        }

        return null;
    }

    /**
     * Information shown to students at the start of the quiz.
     *
     * @return string HTML formatted information.
     */
    public function description() {
        global $PAGE;

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

        $quizid   = $this->quizobj->get_quizid();
        $settings = self::get_quiz_proctoring_settings($quizid);
        if (empty($settings) || empty($settings->enablecamera)) {
            return '';
        }

        // Call AMD module to lock Attempt button and handle device verification on view.php.
        $config = [
            'quizid'             => (int) $quizid,
            'cmid'               => (int) $this->quizobj->get_cmid(),
            'resolution'         => (int) ($settings->resolution ?? 640),
            'requiremic'         => !isset($settings->requiremic) || !empty($settings->requiremic),
            'blockvirtualcamera' => !isset($settings->blockvirtualcamera) || !empty($settings->blockvirtualcamera),
            'blockmultiscreen'   => !isset($settings->blockmultiscreen) || !empty($settings->blockmultiscreen),
            'strings'            => [
                'waiting'              => get_string('precheck_waiting', 'quizaccess_liveproctoring'),
                'checking'             => get_string('precheck_checking', 'quizaccess_liveproctoring'),
                'cam_ok'               => get_string('precheck_cam_ok', 'quizaccess_liveproctoring'),
                'mic_ok'               => get_string('precheck_mic_ok', 'quizaccess_liveproctoring'),
                'screen_ok'            => get_string('precheck_screen_ok', 'quizaccess_liveproctoring'),
                'btn_verify'           => get_string('precheck_btn_verify', 'quizaccess_liveproctoring'),
                'btn_verifying'        => get_string('precheck_btn_verifying', 'quizaccess_liveproctoring'),
                'btn_verified'         => get_string('precheck_btn_verified', 'quizaccess_liveproctoring'),
                'button_locked_hint'   => get_string('button_locked_hint', 'quizaccess_liveproctoring'),
                'must_verify_first'    => get_string('must_verify_first', 'quizaccess_liveproctoring'),
                'virtualcamerablocked' => get_string('virtualcamerablocked', 'quizaccess_liveproctoring', ''),
                'multimonitorwarning'  => get_string('multimonitorwarning', 'quizaccess_liveproctoring'),
                'camerablocked'        => get_string('camerablocked', 'quizaccess_liveproctoring'),
                'cameramic_required'   => get_string('cameramic_required', 'quizaccess_liveproctoring'),
            ],
        ];

        $PAGE->requires->js_call_amd('quizaccess_liveproctoring/precheck_view', 'init', [$config]);

        // Build elegant Pre-Exam Verification Card.
        $html  = '<div id="lp-precheck-card" class="card shadow-sm my-4" style="border: 2px solid #0f3460; border-radius: 14px; overflow: hidden; background: #ffffff;">';
        $html .= '  <div class="card-header d-flex align-items-center justify-content-between p-3" style="background: linear-gradient(135deg, #0f3460 0%, #1a1a2e 100%); color: #ffffff;">';
        $html .= '    <div class="d-flex align-items-center gap-2">';
        $html .= '      <span style="font-size: 24px;">📷🎙️</span>';
        $html .= '      <h5 class="mb-0 fw-bold" style="color: #ffffff; font-size: 17px;">' . get_string('precheck_title', 'quizaccess_liveproctoring') . '</h5>';
        $html .= '    </div>';
        $html .= '    <span class="badge rounded-pill bg-danger px-3 py-2" style="font-size: 12px; letter-spacing: 0.5px;">' . get_string('precheck_badge', 'quizaccess_liveproctoring') . '</span>';
        $html .= '  </div>';

        $html .= '  <div class="card-body p-4">';
        $html .= '    <p class="text-muted mb-3" style="font-size: 14px; line-height: 1.6;">' . get_string('precheck_desc', 'quizaccess_liveproctoring') . '</p>';

        $html .= '    <div class="row align-items-center g-3">';
        // Video preview box
        $html .= '      <div class="col-md-5 col-12 d-flex justify-content-center">';
        $html .= '        <div id="lp-precheck-videowrap" style="width: 240px; height: 180px; border-radius: 12px; background: #161b22; position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center; text-align: center; border: 2px solid #30363d; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">';
        $html .= '          <div id="lp-precheck-placeholder" style="color: #8b949e; font-size: 13px; padding: 12px;">';
        $html .= '            <div style="font-size: 34px; margin-bottom: 6px;">📷</div>';
        $html .= '            <span>' . get_string('precheck_placeholder', 'quizaccess_liveproctoring') . '</span>';
        $html .= '          </div>';
        $html .= '          <video id="lp-precheck-video" autoplay playsinline muted style="width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1); display: none;"></video>';
        $html .= '          <div id="lp-precheck-livebadge" style="display: none; position: absolute; top: 6px; left: 6px; background: rgba(0,0,0,0.75); color: #2ecc71; font-size: 10px; font-weight: bold; padding: 2px 8px; border-radius: 6px;">● LIVE</div>';
        $html .= '        </div>';
        $html .= '      </div>';

        // Status items column
        $html .= '      <div class="col-md-7 col-12">';
        $html .= '        <div class="p-3 rounded" style="background: #f8f9fa; border: 1px solid #e9ecef;">';
        $html .= '          <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">';
        $html .= '            <span class="fw-semibold" style="font-size: 14px;">📹 ' . get_string('precheck_cam_status', 'quizaccess_liveproctoring') . '</span>';
        $html .= '            <span id="lp-status-cam" class="badge bg-secondary text-white" style="font-size: 12px;">' . get_string('precheck_waiting', 'quizaccess_liveproctoring') . '</span>';
        $html .= '          </div>';

        if (!isset($settings->requiremic) || !empty($settings->requiremic)) {
            $html .= '          <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom">';
            $html .= '            <span class="fw-semibold" style="font-size: 14px;">🎙️ ' . get_string('precheck_mic_status', 'quizaccess_liveproctoring') . '</span>';
            $html .= '            <span id="lp-status-mic" class="badge bg-secondary text-white" style="font-size: 12px;">' . get_string('precheck_waiting', 'quizaccess_liveproctoring') . '</span>';
            $html .= '          </div>';
        }

        if (!isset($settings->blockmultiscreen) || !empty($settings->blockmultiscreen)) {
            $html .= '          <div class="d-flex align-items-center justify-content-between mb-1">';
            $html .= '            <span class="fw-semibold" style="font-size: 14px;">🖥️ ' . get_string('precheck_screen_status', 'quizaccess_liveproctoring') . '</span>';
            $html .= '            <span id="lp-status-screen" class="badge bg-secondary text-white" style="font-size: 12px;">' . get_string('precheck_waiting', 'quizaccess_liveproctoring') . '</span>';
            $html .= '          </div>';
        }
        $html .= '        </div>';

        // Action button
        $html .= '        <div class="mt-3">';
        $html .= '          <button type="button" id="lp-btn-verify-device" class="btn btn-warning btn-lg w-100 fw-bold shadow-sm" style="border-radius: 10px; padding: 12px 20px; font-size: 15px;">';
        $html .= '            📷🎙️ ' . get_string('precheck_btn_verify', 'quizaccess_liveproctoring');
        $html .= '          </button>';
        $html .= '        </div>';

        $html .= '      </div>';
        $html .= '    </div>';

        // Error and Success alerts
        $html .= '    <div id="lp-precheck-error" class="alert alert-danger mt-3 mb-0" style="display: none; border-radius: 8px; font-size: 14px;"></div>';
        $html .= '    <div id="lp-precheck-success" class="alert alert-success mt-3 mb-0" style="display: none; border-radius: 8px; font-weight: bold; font-size: 14px;">';
        $html .= '      ✅ ' . get_string('precheck_success', 'quizaccess_liveproctoring');
        $html .= '    </div>';

        $html .= '  </div>';
        $html .= '</div>';

        return $html;
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

        $attemptid = optional_param('attempt', 0, PARAM_INT);

        // Pass config to AMD module.
        $config = [
            'cmid'             => $this->quizobj->get_cmid(),
            'quizid'           => $quiz->id,
            'userid'           => $USER->id,
            'attemptid'        => $attemptid,
            'snapshotinterval' => (int) ($settings->snapshotinterval ?? 30),
            'resolution'       => (int) ($settings->resolution ?? 640),
            'referencerequired'=> (bool) ($settings->referencerequired ?? false),
            'turnurl'          => (string) (get_config('quizaccess_liveproctoring', 'turn_url') ?: 'turn:150.230.241.37:3478'),
            'turnusername'     => (string) (get_config('quizaccess_liveproctoring', 'turn_username') ?: 'lpuser'),
            'turnpassword'     => (string) (get_config('quizaccess_liveproctoring', 'turn_password') ?: 'StrongPass123!'),
            'wsurl'            => (string) (get_config('quizaccess_liveproctoring', 'ws_url') ?: ''),
            'warninglimit'     => (int) ($settings->warninglimit ?? 3),
            'autosubmit'       => (bool) ($settings->autosubmit ?? false),
            'showwatermark'    => (bool) ($settings->showwatermark ?? true),
            'blocktabs'        => (bool) ($settings->blocktabs ?? true),
            'enableai'         => (bool) ($settings->enableai ?? false),
            'requiremic'       => !isset($settings->requiremic) || !empty($settings->requiremic),
            'detectspeech'     => !isset($settings->detectspeech) || !empty($settings->detectspeech),
            'preventscreenshot'=> !isset($settings->preventscreenshot) || !empty($settings->preventscreenshot),
            'blockvirtualcamera'=> !isset($settings->blockvirtualcamera) || !empty($settings->blockvirtualcamera),
            'blockmultiscreen' => !isset($settings->blockmultiscreen) || !empty($settings->blockmultiscreen),
            'reconnectgrace'   => (int) ($settings->reconnectgrace ?? 15),
            'pushurl'          => (new moodle_url(
                '/mod/quiz/accessrule/liveproctoring/push_snapshot.php'
            ))->out(false),
            'signalurl'        => (new moodle_url(
                '/mod/quiz/accessrule/liveproctoring/live_signal.php'
            ))->out(false),
            'logeventurl'      => (new moodle_url(
                '/mod/quiz/accessrule/liveproctoring/log_event.php'
            ))->out(false),
            'sesskey'          => sesskey(),
            'username'         => fullname($USER),
            'strings'          => [
                'warningtitle'         => get_string('warningtitle', 'quizaccess_liveproctoring'),
                'warningmessage'       => get_string('warningmessage', 'quizaccess_liveproctoring'),
                'tabswitch'            => get_string('tabswitchwarning', 'quizaccess_liveproctoring'),
                'camerablocked'        => get_string('camerablocked', 'quizaccess_liveproctoring'),
                'autosubmit'           => get_string('autosubmitmessage', 'quizaccess_liveproctoring'),
                'warningcount'         => get_string('warningcount', 'quizaccess_liveproctoring'),
                'cameragate_title'     => get_string('cameragate_title', 'quizaccess_liveproctoring'),
                'cameragate_desc'      => get_string('cameragate_desc', 'quizaccess_liveproctoring'),
                'cameragate_btn'       => get_string('cameragate_btn', 'quizaccess_liveproctoring'),
                'cameragate_retry'     => get_string('cameragate_retry', 'quizaccess_liveproctoring'),
                'cameragate_denied'    => get_string('cameragate_denied', 'quizaccess_liveproctoring'),
                'cameragate_lost'      => get_string('cameragate_lost', 'quizaccess_liveproctoring'),
                'virtualcamerablocked' => get_string('virtualcamerablocked', 'quizaccess_liveproctoring', ''),
                'multimonitorwarning'  => get_string('multimonitorwarning', 'quizaccess_liveproctoring'),
                'ejectedmessage'       => get_string('ejectedmessage', 'quizaccess_liveproctoring'),
                'cameramic_required'   => get_string('cameramic_required', 'quizaccess_liveproctoring'),
                'screenshot_detected'  => get_string('screenshot_detected', 'quizaccess_liveproctoring'),
                'speaking_detected'    => get_string('speaking_detected', 'quizaccess_liveproctoring'),
                'face_disappeared'     => get_string('face_disappeared', 'quizaccess_liveproctoring'),
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
