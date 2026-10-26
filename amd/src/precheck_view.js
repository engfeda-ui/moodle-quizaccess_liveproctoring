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
 * AMD module: Pre-exam device verification & attempt button gating.
 *
 * Locks the quiz attempt button on mod/quiz/view.php until the student
 * grants camera and microphone permissions and passes device checks.
 *
 * @module    quizaccess_liveproctoring/precheck_view
 * @copyright 2026 Energy & Water Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    /** @type {object} Plugin configuration passed from PHP */
    let cfg = {};

    /** @type {boolean} Whether device verification has succeeded */
    let isVerified = false;

    /** @type {MediaStream|null} Stream captured during precheck */
    let precheckStream = null;

    /**
     * Query all possible attempt submit buttons on the quiz view page.
     *
     * @return {HTMLElement[]}
     */
    const getAttemptButtons = function() {
        const selector = [
            '.quizstartbuttondiv input[type="submit"]',
            '.quizstartbuttondiv button',
            '#id_submitbutton',
            '.quizattempt input[type="submit"]',
            '.quizattempt button',
            'form[action*="startattempt.php"] input[type="submit"]',
            'form[action*="startattempt.php"] button',
            'form[action*="startattempt.php"] input[type="button"]',
            'button[name="submitbutton"]',
            '.singlebutton form[action*="startattempt.php"] button'
        ].join(', ');

        return Array.from(document.querySelectorAll(selector));
    };

    /**
     * Lock all attempt buttons to prevent student from entering quiz unverified.
     */
    const lockAttemptButtons = function() {
        if (isVerified) {
            return;
        }

        const buttons = getAttemptButtons();
        buttons.forEach(function(btn) {
            btn.disabled = true;
            btn.setAttribute('data-lp-locked', '1');
            btn.style.opacity = '0.35';
            btn.style.filter = 'grayscale(1)';
            btn.style.cursor = 'not-allowed';
            btn.style.pointerEvents = 'none';
            btn.title = cfg.strings.button_locked_hint || 'Authorize camera and mic above first.';
        });
    };

    /**
     * Unlock all attempt buttons with glowing green confirmation.
     */
    const unlockAttemptButtons = function() {
        isVerified = true;

        // Inject glowing animation style once.
        if (!document.getElementById('lp-unlock-pulse-style')) {
            const st = document.createElement('style');
            st.id = 'lp-unlock-pulse-style';
            st.textContent = `
                @keyframes lp-attempt-pulse {
                    0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(46, 204, 113, 0.7); }
                    70% { transform: scale(1.02); box-shadow: 0 0 0 14px rgba(46, 204, 113, 0); }
                    100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(46, 204, 113, 0); }
                }
                .lp-btn-unlocked {
                    animation: lp-attempt-pulse 2s infinite !important;
                    background-color: #27ae60 !important;
                    border-color: #219653 !important;
                    color: #ffffff !important;
                    font-weight: bold !important;
                }
            `;
            document.head.appendChild(st);
        }

        const buttons = getAttemptButtons();
        buttons.forEach(function(btn) {
            btn.disabled = false;
            btn.removeAttribute('data-lp-locked');
            btn.style.opacity = '1';
            btn.style.filter = 'none';
            btn.style.cursor = 'pointer';
            btn.style.pointerEvents = 'auto';
            btn.title = '';
            btn.classList.add('lp-btn-unlocked');
        });
    };

    /**
     * Display an error message inside the precheck card.
     *
     * @param {string} msg
     */
    const showError = function(msg) {
        const errBox = document.getElementById('lp-precheck-error');
        const vbtn = document.getElementById('lp-btn-verify-device');
        if (errBox) {
            errBox.style.display = 'block';
            errBox.textContent = msg;
        }
        if (vbtn) {
            vbtn.disabled = false;
            vbtn.textContent = '🔄 ' + (cfg.strings.btn_verify || 'إعادة محاولة التحقق');
        }
    };

    /**
     * Intercept form submissions to strictly block unverified bypass.
     */
    const interceptForms = function() {
        const forms = document.querySelectorAll('form[action*="startattempt.php"]');
        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                if (!isVerified) {
                    e.preventDefault();
                    e.stopPropagation();
                    alert(cfg.strings.must_verify_first || 'Please authorize camera and mic first!');
                    return false;
                }
                return true;
            }, true);
        });
    };

    /**
     * Run camera, mic, virtual camera and display verification.
     */
    const runVerification = function() {
        const errBox = document.getElementById('lp-precheck-error');
        if (errBox) {
            errBox.style.display = 'none';
        }

        const vbtn = document.getElementById('lp-btn-verify-device');
        if (vbtn) {
            vbtn.disabled = true;
            vbtn.textContent = '⏳ ' + (cfg.strings.btn_verifying || 'جارٍ الاتصال والتحقق...');
        }

        const statusCam = document.getElementById('lp-status-cam');
        const statusMic = document.getElementById('lp-status-mic');
        const statusScreen = document.getElementById('lp-status-screen');

        if (statusCam) {
            statusCam.textContent = cfg.strings.checking || 'جارٍ الفحص...';
        }
        if (statusMic) {
            statusMic.textContent = cfg.strings.checking || 'جارٍ الفحص...';
        }
        if (statusScreen) {
            statusScreen.textContent = cfg.strings.checking || 'جارٍ الفحص...';
        }

        // Multi-screen detection.
        if (cfg.blockmultiscreen) {
            let isExtended = false;
            if (window.screen && typeof window.screen.isExtended === 'boolean') {
                isExtended = window.screen.isExtended;
            } else if (window.screen && (window.screen.availWidth > window.screen.width * 1.5)) {
                isExtended = true;
            }

            if (isExtended) {
                if (statusScreen) {
                    statusScreen.className = 'badge bg-danger text-white';
                    statusScreen.textContent = '❌ شاشات متعددة';
                }
                showError(cfg.strings.multimonitorwarning || 'Multiple monitors detected.');
                return;
            }
        }

        if (statusScreen) {
            statusScreen.className = 'badge bg-success text-white';
            statusScreen.textContent = cfg.strings.screen_ok || '✅ شاشة واحدة معتمدة';
        }

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showError(cfg.strings.camerablocked || 'Camera API not supported in this browser.');
            return;
        }

        const res = cfg.resolution || 640;
        const videoConstraints = {
            width: { ideal: res },
            height: { ideal: Math.round(res * 0.75) },
            frameRate: { ideal: 30 },
            facingMode: 'user'
        };
        const needMic = !!cfg.requiremic;

        navigator.mediaDevices.getUserMedia({
            video: videoConstraints,
            audio: needMic
        }).then(function(mediaStream) {
            const videoTracks = mediaStream.getVideoTracks();
            const audioTracks = mediaStream.getAudioTracks();

            if (!videoTracks.length || (needMic && !audioTracks.length)) {
                mediaStream.getTracks().forEach(function(t) { t.stop(); });
                showError(needMic ? cfg.strings.cameramic_required : cfg.strings.camerablocked);
                return;
            }

            // Virtual Camera Blacklist Inspection.
            if (cfg.blockvirtualcamera) {
                const track = videoTracks[0];
                const trackLabel = (track.label || '').toLowerCase();
                const blacklisted = [
                    'obs', 'virtual', 'manycam', 'v4l2loopback', 'splitcam',
                    'camo', 'droidcam', 'iriun', 'fake', 'software camera', 'e2esoft'
                ];
                const isVirtual = blacklisted.some(function(k) {
                    return trackLabel.indexOf(k) !== -1;
                });

                if (isVirtual) {
                    mediaStream.getTracks().forEach(function(t) { t.stop(); });
                    if (statusCam) {
                        statusCam.className = 'badge bg-danger text-white';
                        statusCam.textContent = '❌ كاميرا وهمية مرفوضة';
                    }
                    const errTemplate = cfg.strings.virtualcamerablocked || 'Virtual camera detected ({$a}).';
                    showError(errTemplate.replace('{$a}', track.label || 'Virtual Device'));
                    return;
                }
            }

            precheckStream = mediaStream;
            isVerified = true;

            // Save authorization into sessionStorage.
            try {
                sessionStorage.setItem('lp_cam_prechecked_' + cfg.quizid, '1');
                sessionStorage.setItem('lp_cam_prechecked_time_' + cfg.quizid, Date.now().toString());
            } catch (e) {}

            // Update Status Badges.
            if (statusCam) {
                statusCam.className = 'badge bg-success text-white';
                statusCam.textContent = cfg.strings.cam_ok || '✅ متصلة ومطابقة';
            }
            if (statusMic) {
                statusMic.className = 'badge bg-success text-white';
                statusMic.textContent = cfg.strings.mic_ok || '✅ متصل ونشط';
            }

            // Mount Video Stream Preview.
            const videoElem = document.getElementById('lp-precheck-video');
            const placeholder = document.getElementById('lp-precheck-placeholder');
            const liveBadge = document.getElementById('lp-precheck-livebadge');

            if (videoElem) {
                videoElem.srcObject = mediaStream;
                videoElem.style.display = 'block';
            }
            if (placeholder) {
                placeholder.style.display = 'none';
            }
            if (liveBadge) {
                liveBadge.style.display = 'block';
            }

            // Update Verify Button State.
            if (vbtn) {
                vbtn.className = 'btn btn-success btn-lg fw-bold shadow-sm';
                vbtn.textContent = '✅ ' + (cfg.strings.btn_verified || 'تم التحقق من الجهاز بنجاح');
                vbtn.disabled = true;
                vbtn.style.pointerEvents = 'none';
            }

            // Display Success Notice.
            const succBox = document.getElementById('lp-precheck-success');
            if (succBox) {
                succBox.style.display = 'block';
            }

            // Unlock attempt buttons!
            unlockAttemptButtons();
        }).catch(function(err) {
            const msg = (err && err.message) ? err.message : (cfg.strings.camerablocked || 'Camera access denied.');
            showError(msg);
        });
    };

    /**
     * Initialise module on quiz view page.
     *
     * @param {object} config
     */
    const init = function(config) {
        cfg = config;

        // Check if already verified in this browser tab session.
        let alreadyVerified = false;
        try {
            alreadyVerified = (sessionStorage.getItem('lp_cam_prechecked_' + cfg.quizid) === '1');
        } catch (e) {}

        if (alreadyVerified) {
            // Already verified, trigger verification automatically.
            isVerified = true;
            runVerification();
        } else {
            // Lock attempt buttons immediately.
            lockAttemptButtons();

            // Repeatedly ensure dynamically rendered buttons stay locked until verified.
            const lockInterval = setInterval(function() {
                if (isVerified) {
                    clearInterval(lockInterval);
                    return;
                }
                lockAttemptButtons();
            }, 300);

            setTimeout(function() {
                clearInterval(lockInterval);
            }, 5000);
        }

        interceptForms();

        // Bind verification trigger button.
        const verifyBtn = document.getElementById('lp-btn-verify-device');
        if (verifyBtn) {
            verifyBtn.addEventListener('click', function(e) {
                e.preventDefault();
                runVerification();
            });
        }
    };

    return {
        init: init
    };
});
