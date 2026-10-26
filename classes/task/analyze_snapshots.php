<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace quizaccess_liveproctoring\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task: asynchronously analyze pending webcam snapshots with AI.
 */
class analyze_snapshots extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('task:analyzesnapshots', 'quizaccess_liveproctoring');
    }

    public function execute() {
        global $DB;

        $analyzer = new \quizaccess_liveproctoring\ai_analyzer();
        $configured = $analyzer->is_configured();

        // Process a bounded batch to keep runtime short.
        $snaps = $DB->get_records('quizaccess_liveproctoring_snaps', ['risk_score' => -1],
            'id ASC', '*', 0, 50);

        $fs = get_file_storage();

        foreach ($snaps as $snap) {
            $existing = json_decode($snap->ai_result, true);
            if (!is_array($existing)) {
                $existing = [];
            }

            if (!$configured) {
                $snap->risk_score    = 0;
                $snap->ai_result     = json_encode(array_merge($existing, [
                    'risk_score' => 0,
                    'error'      => 'AI not configured',
                ]));
                $DB->update_record('quizaccess_liveproctoring_snaps', $snap);
                continue;
            }

            $file = $fs->get_file_by_id($snap->fileid);
            if (!$file || $file->is_directory()) {
                $snap->risk_score = 0;
                $DB->update_record('quizaccess_liveproctoring_snaps', $snap);
                continue;
            }

            $base64 = base64_encode($file->get_content());
            $airesult = $analyzer->analyze($base64, (int) $snap->userid, (int) $snap->attemptid, (int) $snap->quizid);

            // Preserve identity verification info captured at upload time.
            if (isset($existing['identity_match'])) {
                $airesult['identity_match'] = $existing['identity_match'];
            }
            if (isset($existing['identity_reason'])) {
                $airesult['identity_reason'] = $existing['identity_reason'];
            }

            $risk = (int) ($airesult['risk_score'] ?? -1);
            if ($risk < 0) {
                $risk = 0;
            }

            $snap->ai_result     = json_encode($airesult);
            $snap->risk_score    = $risk;
            $snap->face_detected = (int) ($airesult['face_detected'] ?? 0);
            $snap->face_count    = (int) ($airesult['face_count'] ?? 0);
            $snap->suspicious    = (int) ($airesult['suspicious'] ?? 0);
            $DB->update_record('quizaccess_liveproctoring_snaps', $snap);

            if (!empty($airesult['suspicious']) || (!empty($airesult['face_detected']) && $airesult['face_count'] > 1)) {
                $ev = new stdClass();
                $ev->userid       = $snap->userid;
                $ev->cmid         = $snap->cmid;
                $ev->attemptid    = $snap->attemptid;
                $ev->eventtype    = $airesult['face_count'] > 1 ? 'multi_face' : 'ai_alert';
                $ev->severity     = $risk > 70 ? 'high' : 'medium';
                $ev->warningcount = 0;
                $ev->message      = substr((string) ($airesult['reason'] ?? ''), 0, 255);
                $ev->timecreated  = time();
                $DB->insert_record('quizaccess_liveproctoring_events', $ev);
            }
        }
    }
}