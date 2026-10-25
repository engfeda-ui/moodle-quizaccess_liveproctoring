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
 * AI Analyzer class for quizaccess_liveproctoring.
 *
 * Sends captured snapshot to a configurable Custom AI API and returns
 * structured analysis results.
 *
 * @package   quizaccess_liveproctoring
 * @copyright 2026 Energy & Water Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_liveproctoring;

defined('MOODLE_INTERNAL') || die();

/**
 * Sends images to a custom AI API for proctoring analysis.
 */
class ai_analyzer {

    /** @var string AI API base URL */
    private string $apiurl;

    /** @var string API key / bearer token */
    private string $apikey;

    /** @var int Request timeout in seconds */
    private int $timeout;

    /**
     * Constructor — reads from plugin config.
     */
    public function __construct() {
        $this->apiurl  = (string) get_config('quizaccess_liveproctoring', 'ai_api_url');
        $this->apikey  = (string) get_config('quizaccess_liveproctoring', 'ai_api_key');
        $this->timeout = (int) (get_config('quizaccess_liveproctoring', 'ai_timeout') ?: 10);
    }

    /**
     * Check whether AI analysis is globally configured.
     *
     * @return bool
     */
    public function is_configured(): bool {
        return !empty($this->apiurl) && !empty($this->apikey);
    }

    /**
     * Analyze a base64-encoded image via the custom AI API.
     *
     * @param string $imagedata Base64 image (with or without data URI prefix).
     * @param int    $userid    Moodle user ID.
     * @param int    $attemptid Quiz attempt ID.
     * @param int    $quizid    Quiz ID.
     * @return array Parsed result array or default on failure.
     */
    public function analyze(string $imagedata, int $userid, int $attemptid, int $quizid): array {
        if (!$this->is_configured()) {
            return $this->default_result('AI API not configured');
        }

        // Strip data-URI prefix if present.
        if (strpos($imagedata, 'data:') === 0) {
            $imagedata = preg_replace('/^data:[^;]+;base64,/', '', $imagedata);
        }

        $payload = json_encode([
            'image_base64' => 'data:image/jpeg;base64,' . $imagedata,
            'student_id'   => $userid,
            'attempt_id'   => $attemptid,
            'metadata'     => [
                'quiz_id'   => $quizid,
                'timestamp' => time(),
            ],
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $this->apiurl,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apikey,
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlerr  = curl_error($curl);
        curl_close($curl);

        if ($curlerr || $httpcode !== 200 || empty($response)) {
            return $this->default_result('API error: ' . ($curlerr ?: "HTTP $httpcode"));
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            return $this->default_result('Invalid JSON response');
        }

        return $this->normalize($data);
    }

    /**
     * Normalize API response into a consistent structure.
     *
     * @param array $raw Raw API response.
     * @return array Normalized result.
     */
    private function normalize(array $raw): array {
        return [
            'face_detected'   => (bool) ($raw['face_detected'] ?? false),
            'face_count'      => (int)  ($raw['face_count']    ?? 0),
            'gaze_direction'  => (string) ($raw['gaze_direction'] ?? 'unknown'),
            'suspicious'      => (bool) ($raw['suspicious']    ?? false),
            'risk_score'      => min(100, max(0, (int) ($raw['risk_score'] ?? 0))),
            'flags'           => (array) ($raw['flags']         ?? []),
            'reason'          => (string) ($raw['reason']       ?? ''),
            'error'           => null,
        ];
    }

    /**
     * Return a default (failed/unanalyzed) result.
     *
     * @param string $reason Human-readable error reason.
     * @return array
     */
    private function default_result(string $reason = ''): array {
        return [
            'face_detected'  => false,
            'face_count'     => 0,
            'gaze_direction' => 'unknown',
            'suspicious'     => false,
            'risk_score'     => -1,
            'flags'          => [],
            'reason'         => '',
            'error'          => $reason,
        ];
    }
}
