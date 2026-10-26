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
 * English language strings for quizaccess_liveproctoring.
 *
 * @package   quizaccess_liveproctoring
 * @copyright 2026 Energy & Water Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Live Proctoring with AI';
$string['plugindescription'] = 'Monitors students during quiz attempts using webcam snapshots and a custom AI API. Includes a live teacher dashboard.';

// Settings.
$string['setting:enablecamera'] = 'Enable camera proctoring';
$string['setting:enableai'] = 'Enable AI analysis';
$string['setting:snapshotinterval'] = 'Snapshot interval (seconds)';
$string['setting:resolution'] = 'Webcam resolution (width in px)';
$string['setting:referencerequired'] = 'Require identity verification (reference photo)';
$string['task:analyzesnapshots'] = 'Analyze pending proctoring snapshots with AI';
$string['setting:warninglimit'] = 'Maximum warnings before action';
$string['setting:autosubmit'] = 'Auto-submit on warning limit';
$string['setting:showwatermark'] = 'Show student name watermark';
$string['setting:blocktabs'] = 'Warn on tab/window switch';

// Global admin settings.
$string['setting:aiapiurl'] = 'AI API endpoint URL';
$string['setting:aiapiurldesc'] = 'Full URL of your custom AI proctoring API (e.g. https://myai.example.com/analyze).';
$string['setting:aiapikey'] = 'AI API key';
$string['setting:aiapidesc'] = 'Bearer token / API key to authenticate with your AI service.';
$string['setting:aimodel'] = 'AI Model Name';
$string['setting:aimodeldesc'] = 'Model ID for OpenAI / OpenRouter endpoints (e.g. openrouter/free, google/gemma-4-31b-it:free, gpt-4o-mini).';
$string['setting:aitimeout'] = 'AI API timeout (seconds)';
$string['setting:aitimeoutdesc'] = 'How long to wait for AI API response before giving up.';
$string['setting:globalheading'] = 'Global AI API Settings';

// Student notices.
$string['studentnotice'] = '📷 This exam uses Live Proctoring. Your webcam will be active throughout the exam. Ensure good lighting and keep your face visible.';
$string['warningtitle'] = '⚠️ Proctoring Warning';
$string['warningmessage'] = 'A proctoring violation has been detected.';
$string['warningcount'] = 'Warning';
$string['camerablocked'] = 'Camera access was denied. Please allow camera access and reload the page to continue the exam.';
$string['tabswitchwarning'] = 'You switched away from the exam window.';
$string['autosubmitmessage'] = 'You have exceeded the maximum number of violations. Your exam has been submitted automatically.';
$string['cameragate_title'] = 'Webcam Proctoring Required';
$string['cameragate_desc'] = 'This exam requires an active camera. Questions remain completely blocked until camera access is granted.';
$string['cameragate_btn'] = '📷 Enable Camera & Enter Exam';
$string['cameragate_retry'] = '🔄 Retry Camera Access';
$string['cameragate_denied'] = 'Camera access denied! You must allow camera access in browser permissions to take this exam.';
$string['cameragate_lost'] = 'Camera feed was disconnected! Please restore camera or your exam will be terminated.';
$string['ejectedmessage'] = 'Your exam attempt has been terminated by the proctor due to proctoring violations. You can no longer access this quiz.';
$string['warninglimitexceeded'] = 'You have exceeded the maximum allowed proctoring violations. Your exam has been submitted and locked.';
$string['virtualcamerablocked'] = 'Virtual camera detected ({$a}). Virtual cameras and broadcasting software are strictly prohibited. Please select your physical webcam to proceed.';
$string['multimonitorwarning'] = 'Multiple displays detected. Using secondary or extended monitors is prohibited during the exam.';

// Teacher dashboard.
$string['livemonitor'] = 'Live Monitor';
$string['viewreport'] = 'Post-Exam Report';
$string['activestudents'] = 'Active Students';
$string['suspicious'] = 'Suspicious';
$string['lastupdate'] = 'Last Update';
$string['connecting'] = 'Connecting to live feed…';
$string['riskhigh'] = 'High Risk';
$string['riskmedium'] = 'Warning';
$string['riskok'] = 'OK';
$string['facenotdetected'] = 'No face';
$string['multiplefaces'] = 'Multiple faces';
$string['lastseen'] = 'Last seen';
$string['nostudents'] = 'Waiting for students to start...';
$string['nostudentsdesc'] = 'Active student webcam feeds will appear here automatically once attempts begin.';

// Zoom Exam Hall strings.
$string['examhall'] = 'Live Exam Hall';
$string['spotlight'] = 'Live Video & Spotlight';
$string['spotlight_title'] = 'Live Direct Video Stream';
$string['connecting_live'] = 'Connecting to candidate camera...';
$string['live_active'] = 'Connected Live (30 FPS)';
$string['send_warning'] = 'Send Instant Warning';
$string['warning_placeholder'] = 'Type direct warning to flash on student screen...';
$string['terminate_btn'] = 'Force Terminate Exam';
$string['terminate_prompt'] = 'Are you sure you want to terminate this student\'s exam attempt immediately?';
$string['filter_all'] = 'All Candidates';
$string['filter_flagged'] = 'Flagged Only';
$string['sound_toggle'] = 'Alert Sound';
$string['fullscreen'] = 'Fullscreen Hall';
$string['warning_sent'] = 'Warning sent successfully!';
$string['studentaudio'] = 'Student Audio';
$string['audiomuted'] = 'Audio: Muted';
$string['audiounmuted'] = 'Audio: Active';
$string['classroom'] = 'Classroom / Group';

// Capabilities.
$string['liveproctoring:viewmonitor'] = 'View live proctoring monitor';
$string['liveproctoring:viewsnapshots'] = 'View exam snapshots';
$string['liveproctoring:deletesnapshots'] = 'Delete exam snapshots';
$string['liveproctoring:manage'] = 'Manage live proctoring settings';

// Privacy strings.
$string['privacy:metadata:quizaccess_liveproctoring_snaps'] = 'Stores webcam snapshot records and AI risk scores taken during quiz attempts.';
$string['privacy:metadata:quizaccess_liveproctoring_snaps:userid'] = 'The ID of the student being monitored.';
$string['privacy:metadata:quizaccess_liveproctoring_snaps:quizid'] = 'The ID of the quiz being proctored.';
$string['privacy:metadata:quizaccess_liveproctoring_snaps:attemptid'] = 'The ID of the quiz attempt.';
$string['privacy:metadata:quizaccess_liveproctoring_snaps:risk_score'] = 'The risk score calculated by AI analysis.';
$string['privacy:metadata:quizaccess_liveproctoring_snaps:face_detected'] = 'Whether a face was detected in the frame.';
$string['privacy:metadata:quizaccess_liveproctoring_snaps:suspicious'] = 'Whether suspicious behavior was flagged.';
$string['privacy:metadata:quizaccess_liveproctoring_snaps:timecreated'] = 'Timestamp when the snapshot was recorded.';
$string['privacy:metadata:quizaccess_liveproctoring_events'] = 'Stores proctoring violations and events during quiz attempts.';
$string['privacy:metadata:quizaccess_liveproctoring_events:userid'] = 'The ID of the user who triggered the event.';
$string['privacy:metadata:quizaccess_liveproctoring_events:attemptid'] = 'The ID of the attempt in which the event occurred.';
$string['privacy:metadata:quizaccess_liveproctoring_events:eventtype'] = 'The type of proctoring event.';
$string['privacy:metadata:quizaccess_liveproctoring_events:severity'] = 'The severity level of the event.';
$string['privacy:metadata:quizaccess_liveproctoring_events:timecreated'] = 'Timestamp when the event occurred.';
$string['privacy:metadata:core_files'] = 'Webcam snapshot image files stored by the live proctoring system.';
