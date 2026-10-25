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
 * AMD module: Student proctoring overlay.
 *
 * Handles:
 *  - Camera permission request
 *  - Periodic snapshot capture and push to server
 *  - Tab-switch detection (from ProctorLink concept)
 *  - Warning overlay display
 *  - Watermark injection
 *  - Auto-submit on warning limit
 *
 * @module    quizaccess_liveproctoring/proctoring_student
 * @copyright 2026 Energy & Water Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/notification'], function(Ajax, Notification) {

    /** @type {object} Plugin configuration passed from PHP */
    let cfg = {};

    /** @type {HTMLVideoElement|null} Hidden video element for camera */
    let video = null;

    /** @type {HTMLCanvasElement|null} Off-screen canvas for captures */
    let canvas = null;

    /** @type {MediaStream|null} Active camera stream */
    let stream = null;

    /** @type {number} Warning count */
    let warningCount = 0;

    /** @type {number|null} Snapshot interval timer */
    let snapTimer = null;

    /** @type {boolean} Whether camera is active */
    let cameraActive = false;

    /**
     * Initialise the student proctoring module.
     *
     * @param {object} config Configuration from PHP.
     */
    const init = function(config) {
        cfg = config;

        setupVideoElements();
        injectWatermark();
        setupTabSwitch();
        requestCamera();
    };

    /**
     * Create hidden video and canvas elements.
     */
    const setupVideoElements = function() {
        video = document.createElement('video');
        video.setAttribute('autoplay', '');
        video.setAttribute('playsinline', '');
        video.style.cssText = 'position:fixed;opacity:0;pointer-events:none;width:1px;height:1px;';
        document.body.appendChild(video);

        canvas = document.createElement('canvas');
        canvas.style.display = 'none';
        document.body.appendChild(canvas);
    };

    /**
     * Inject transparent watermark with student name.
     */
    const injectWatermark = function() {
        if (!cfg.showwatermark) {
            return;
        }
        const wm = document.createElement('div');
        wm.id = 'lp-watermark';
        wm.textContent = cfg.username || 'Student';
        wm.style.cssText = [
            'position:fixed',
            'top:50%',
            'left:50%',
            'transform:translate(-50%,-50%) rotate(-30deg)',
            'font-size:48px',
            'color:rgba(180,0,0,0.10)',
            'pointer-events:none',
            'z-index:9999',
            'user-select:none',
            'white-space:nowrap',
            'font-weight:bold',
            'letter-spacing:4px',
        ].join(';');
        document.body.appendChild(wm);
    };

    /**
     * Request camera access from browser.
     */
    const requestCamera = function() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showWarning(cfg.strings.camerablocked);
            return;
        }

        navigator.mediaDevices.getUserMedia({
            video: {
                width: { ideal: 320 },
                height: { ideal: 240 },
                facingMode: 'user',
            },
        }).then(function(mediaStream) {
            stream = mediaStream;
            video.srcObject = mediaStream;
            cameraActive = true;
            startSnapshots();
        }).catch(function() {
            cameraActive = false;
            showWarning(cfg.strings.camerablocked);
            logEvent('camera_blocked', 'high');
        });
    };

    /**
     * Start periodic snapshot capture.
     */
    const startSnapshots = function() {
        const intervalMs = (cfg.snapshotinterval || 30) * 1000;

        // Take first snapshot immediately.
        captureAndPush();

        snapTimer = setInterval(captureAndPush, intervalMs);
    };

    /**
     * Capture a frame from the camera and push to server.
     */
    const captureAndPush = function() {
        if (!cameraActive || !video || !canvas) {
            return;
        }

        const w = video.videoWidth  || 320;
        const h = video.videoHeight || 240;
        canvas.width  = w;
        canvas.height = h;

        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, w, h);
        const imagedata = canvas.toDataURL('image/jpeg', 0.8);

        const attemptid = getAttemptId();
        if (!attemptid) {
            return;
        }

        fetch(cfg.pushurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                cmid:      cfg.cmid,
                quizid:    cfg.quizid,
                attemptid: attemptid,
                image:     imagedata,
                sesskey:   cfg.sesskey,
            }),
        }).then(function(r) {
            return r.json();
        }).then(function(data) {
            if (data && data.suspicious) {
                issueWarning('ai_alert');
            }
        }).catch(function() {
            // Network error — silently retry next interval.
        });
    };

    /**
     * Detect tab visibility changes.
     */
    const setupTabSwitch = function() {
        if (!cfg.blocktabs) {
            return;
        }
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                issueWarning('tab_switch');
            }
        });

        // Blur = user switched window.
        window.addEventListener('blur', function() {
            issueWarning('tab_switch');
        });
    };

    /**
     * Issue a warning to the student.
     *
     * @param {string} eventtype Type of violation.
     */
    const issueWarning = function(eventtype) {
        warningCount++;
        logEvent(eventtype, warningCount > 2 ? 'high' : 'medium');

        const remaining = cfg.warninglimit - warningCount;

        showWarning(
            cfg.strings.warningmessage + '\n' +
            cfg.strings.warningcount + ': ' + warningCount + ' / ' + cfg.warninglimit +
            (remaining > 0 ? ' (' + remaining + ' remaining)' : '')
        );

        if (cfg.autosubmit && warningCount >= cfg.warninglimit) {
            autoSubmitAttempt();
        }
    };

    /**
     * Display a prominent warning overlay.
     *
     * @param {string} message Warning message.
     */
    const showWarning = function(message) {
        // Remove existing overlay.
        const existing = document.getElementById('lp-warning');
        if (existing) {
            existing.remove();
        }

        const overlay = document.createElement('div');
        overlay.id = 'lp-warning';
        overlay.innerHTML = `
            <div class="lp-warning-inner">
                <div class="lp-warning-icon">⚠️</div>
                <h2>${cfg.strings.warningtitle || 'Warning'}</h2>
                <p>${message.replace(/\n/g, '<br>')}</p>
                <button id="lp-dismiss-btn" class="btn btn-warning">
                    OK — I understand
                </button>
            </div>
        `;
        document.body.appendChild(overlay);

        document.getElementById('lp-dismiss-btn').addEventListener('click', function() {
            overlay.remove();
        });

        // Auto-dismiss after 8 seconds.
        setTimeout(function() {
            if (overlay.parentNode) {
                overlay.remove();
            }
        }, 8000);
    };

    /**
     * Log a proctoring event to the server (fire-and-forget).
     *
     * @param {string} eventtype Event type string.
     * @param {string} severity  low|medium|high
     */
    const logEvent = function(eventtype, severity) {
        const attemptid = getAttemptId();
        if (!attemptid) {
            return;
        }

        fetch(cfg.pushurl.replace('push_snapshot.php', 'log_event.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                cmid:         cfg.cmid,
                quizid:       cfg.quizid,
                attemptid:    attemptid,
                eventtype:    eventtype,
                severity:     severity,
                warningcount: warningCount,
                sesskey:      cfg.sesskey,
            }),
        }).catch(function() {
            // Silently ignore.
        });
    };

    /**
     * Auto-submit the quiz attempt.
     */
    const autoSubmitAttempt = function() {
        showWarning(cfg.strings.autosubmit || 'Too many violations. Exam submitted automatically.');

        setTimeout(function() {
            // Find and click the finish attempt button.
            const finishBtn = document.querySelector(
                '.submitbtns [type="submit"], #responseform [name="finishattempt"]'
            );
            if (finishBtn) {
                finishBtn.click();
            } else {
                // Fallback: navigate to finish URL.
                const attemptid = getAttemptId();
                if (attemptid) {
                    window.location.href = M.cfg.wwwroot +
                        '/mod/quiz/summary.php?attempt=' + attemptid + '&cmid=' + cfg.cmid;
                }
            }
        }, 3000);
    };

    /**
     * Extract attempt ID from URL or DOM.
     *
     * @return {string|null}
     */
    const getAttemptId = function() {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('attempt') || null;
    };

    return { init };
});
