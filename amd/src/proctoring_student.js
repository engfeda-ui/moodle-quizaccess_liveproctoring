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

    /** @type {boolean} Whether camera stream is currently active */
    let cameraActive = false;

    /** @type {number|null} Snapshot interval timer */
    let snapTimer = null;

    /** @type {number|null} Reconnect countdown timer */
    let reconnectTimer = null;
    let reconnectSeconds = 15;

    /** @type {RTCPeerConnection|null} Live WebRTC connection */
    let peerConnection = null;

    /** @type {boolean} Whether live spotlight is currently streaming */
    let isLiveStreaming = false;

    /** @type {boolean} Whether WebRTC answer has been applied */
    let answerApplied = false;

    /** @type {number|null} Rapid frame timer */
    let liveFrameTimer = null;

    /** @type {number|null} Signal heartbeat timer */
    let signalTimer = null;

    /** @type {WebSocket|null} WebSocket signaling connection */
    let ws = null;

    /**
     * Initialise the student proctoring module.
     *
     * @param {object} config Configuration from PHP.
     */
    const init = function(config) {
        cfg = config;

        setupTabSwitch();
        setupMultiMonitorCheck();
        setupScreenshotDetection();

        const attemptid = getAttemptId();
        const hasSession = attemptid ? sessionStorage.getItem('lp_cam_granted_' + attemptid) : null;
        if (hasSession) {
            requestCamera(false);
        } else {
            showCameraGate(false);
        }

        setupSignalHeartbeat();
        connectWS();
    };

    /**
     * Check for multiple displays / extended screens.
     */
    const setupMultiMonitorCheck = function() {
        let hasWarnedScreens = false;
        const checkScreens = function() {
            if (hasWarnedScreens) {
                return;
            }
            let isExtended = false;
            if (window.screen && typeof window.screen.isExtended === 'boolean') {
                isExtended = window.screen.isExtended;
            } else if (window.screen && (window.screen.availWidth > window.screen.width * 1.5)) {
                isExtended = true;
            }

            if (isExtended) {
                hasWarnedScreens = true;
                logEvent('multi_monitor_detected', 'high');
                showWarning(
                    cfg.strings.multimonitorwarning ||
                    '⚠️ Multiple displays detected. Using secondary or extended monitors is prohibited during the exam.'
                );
            }
        };

        checkScreens();
        window.addEventListener('resize', checkScreens);
    };

    /**
     * Connect to WebSocket signaling server (optional, for instant push).
     */
    const connectWS = function() {
        if (!cfg.wsurl) {
            return;
        }
        try {
            ws = new WebSocket(cfg.wsurl + '?cmid=' + cfg.cmid + '&role=student');
            ws.onmessage = function(e) {
                try {
                    const data = JSON.parse(e.data);
                    if (data.type === 'terminate' || data.terminate_flag === 1) {
                        clearInterval(signalTimer);
                        autoSubmitAttempt();
                        return;
                    }
                    if (data.type === 'warning' || data.custom_warning) {
                        showWarning(data.message || data.custom_warning);
                    }
                    if (data.type === 'start_stream' || data.active === true) {
                        const attemptid = getAttemptId();
                        if (!isLiveStreaming && stream && cameraActive && attemptid) {
                            startLiveWebRTC(attemptid);
                        }
                    }
                    if ((data.type === 'send_answer' || data.type === 'answer') && data.answer && isLiveStreaming && !answerApplied && peerConnection) {
                        applyTeacherAnswer(data.answer, data.candidates || data.teacher_candidates);
                    }
                } catch (err) {}
            };
            ws.onclose = function() {
                setTimeout(connectWS, 3000);
            };
        } catch (e) {}
    };

    const sendWS = function(msg) {
        if (ws && ws.readyState === 1) {
            try { ws.send(JSON.stringify(msg)); } catch (e) {}
        }
    };

    /**
     * Display a full-screen unclosable camera gate modal.
     *
     * @param {boolean} isReconnect Whether this is due to mid-exam camera loss.
     */
    const showCameraGate = function(isReconnect) {
        let gate = document.getElementById('lp-camera-gate');
        if (gate) {
            gate.remove();
        }

        gate = document.createElement('div');
        gate.id = 'lp-camera-gate';
        gate.style.cssText = [
            'position:fixed',
            'top:0',
            'left:0',
            'width:100vw',
            'height:100vh',
            'background:#0d1117',
            'z-index:9999999',
            'display:flex',
            'align-items:center',
            'justify-content:center',
            'padding:20px',
            'box-sizing:border-box',
            'font-family:system-ui,-apple-system,sans-serif',
        ].join(';');

        const title = isReconnect
            ? (cfg.strings.cameragate_lost || 'Camera & Microphone feed disconnected!')
            : (cfg.strings.cameragate_title || 'Webcam & Microphone Required');

        const desc = isReconnect
            ? 'تم إيقاف الامتحان مؤقتاً بسبب انقطاع بث الكاميرا أو الميكروفون. يرجى إعادة تفعيلهما فوراً قبل انتهاء الوقت المتبقي وإلا سيتم إخراجك من الامتحان وتسليمه تلقائياً.'
            : (cfg.strings.cameramic_required || 'Webcam and Microphone access are strictly required to enter this exam. Questions remain blocked until camera and mic access are granted.');

        const btnText = isReconnect
            ? '🔄 إعادة تفعيل الكاميرا والمايك ومتابعة الحل'
            : (cfg.strings.cameragate_btn || '📷🎤 السماح بالكاميرا والمايك ودخول الامتحان');

        gate.innerHTML = `
            <div style="max-width:480px;width:100%;background:#161b22;border:1px solid ${isReconnect ? '#da3633' : '#30363d'};border-radius:16px;padding:32px 24px;text-align:center;box-shadow:0 16px 40px rgba(0,0,0,0.6);color:#f0f6fc;">
                <div style="font-size:48px;margin-bottom:12px;">${isReconnect ? '🚨' : '📷🎤'}</div>
                <h2 style="font-size:20px;font-weight:bold;margin:0 0 12px;color:${isReconnect ? '#f85149' : '#f0f6fc'};">${title}</h2>
                <p style="color:#8b949e;font-size:14px;line-height:1.6;margin:0 0 20px;">${desc}</p>
                ${isReconnect ? `
                    <div style="background:rgba(218,54,51,0.15);border:1px solid #da3633;border-radius:8px;padding:10px;margin-bottom:20px;color:#f85149;font-weight:bold;font-size:15px;">
                        ⏰ الوقت المتبقي: <span id="lp-countdown">15</span> ثانية قبل الإخراج التلقائي
                    </div>
                ` : ''}
                <button id="lp-gate-btn" style="background:#238636;color:#fff;border:none;padding:12px 24px;font-size:15px;font-weight:bold;border-radius:8px;cursor:pointer;width:100%;transition:background 0.2s;">
                    ${btnText}
                </button>
                <div id="lp-gate-error" style="display:none;margin-top:16px;color:#f85149;font-size:13px;line-height:1.5;background:rgba(248,81,73,0.1);padding:10px;border-radius:6px;border:1px solid #da3633;"></div>
            </div>
        `;

        document.body.appendChild(gate);

        if (isReconnect) {
            reconnectSeconds = 15;
            if (reconnectTimer) {
                clearInterval(reconnectTimer);
            }
            reconnectTimer = setInterval(function() {
                reconnectSeconds--;
                const cdElem = document.getElementById('lp-countdown');
                if (cdElem) {
                    cdElem.textContent = reconnectSeconds;
                }
                if (reconnectSeconds <= 0) {
                    clearInterval(reconnectTimer);
                    autoSubmitAttempt();
                }
            }, 1000);
        }

        document.getElementById('lp-gate-btn').addEventListener('click', function() {
            const btn = this;
            btn.disabled = true;
            btn.textContent = '⏳ جارٍ الاتصال بالكاميرا...';
            requestCamera(isReconnect);
        });
    };

    /**
     * Show an error inside the gate modal.
     */
    const showGateError = function(msg) {
        const errDiv = document.getElementById('lp-gate-error');
        const btn = document.getElementById('lp-gate-btn');
        if (errDiv) {
            errDiv.style.display = 'block';
            errDiv.textContent = msg;
        }
        if (btn) {
            btn.disabled = false;
            btn.textContent = cfg.strings.cameragate_retry || '🔄 إعادة المحاولة والسماح بالكاميرا';
        }
    };

    /**
     * Handle sudden camera loss during exam.
     */
    const handleCameraLost = function() {
        if (!cameraActive) {
            return;
        }
        cameraActive = false;
        logEvent('camera_blocked', 'high');
        showCameraGate(true);
    };

    /**
     * Create floating video preview widget and hidden canvas.
     */
    const setupVideoElements = function() {
        if (document.getElementById('lp-student-preview')) {
            return;
        }

        // Add pulse animation CSS once.
        if (!document.getElementById('lp-style-pulse')) {
            const style = document.createElement('style');
            style.id = 'lp-style-pulse';
            style.textContent = `
                @keyframes lp-pulse {
                    0% { transform: scale(0.95); opacity: 0.8; }
                    50% { transform: scale(1.2); opacity: 1; }
                    100% { transform: scale(0.95); opacity: 0.8; }
                }
            `;
            document.head.appendChild(style);
        }

        const previewWrap = document.createElement('div');
        previewWrap.id = 'lp-student-preview';
        previewWrap.style.cssText = [
            'position:fixed',
            'bottom:16px',
            'right:16px',
            'width:160px',
            'height:120px',
            'border-radius:12px',
            'overflow:hidden',
            'box-shadow:0 6px 20px rgba(0,0,0,0.35)',
            'z-index:9998',
            'border:2px solid #27ae60',
            'background:#111',
            'transition:border-color 0.3s ease',
        ].join(';');

        const badge = document.createElement('div');
        badge.id = 'lp-preview-badge';
        badge.style.cssText = [
            'position:absolute',
            'top:6px',
            'left:6px',
            'background:rgba(0,0,0,0.7)',
            'color:#2ecc71',
            'font-size:10px',
            'font-weight:bold',
            'padding:2px 6px',
            'border-radius:6px',
            'z-index:2',
            'display:flex',
            'align-items:center',
            'gap:4px',
            'font-family:sans-serif',
        ].join(';');
        badge.innerHTML = '<span style="display:inline-block;width:6px;height:6px;background:#2ecc71;border-radius:50%;animation:lp-pulse 1.5s infinite;"></span> REC';
        previewWrap.appendChild(badge);

        video = document.createElement('video');
        video.setAttribute('autoplay', '');
        video.setAttribute('playsinline', '');
        video.setAttribute('muted', '');
        video.style.cssText = 'width:100%;height:100%;object-fit:cover;transform:scaleX(-1);display:block;';
        previewWrap.appendChild(video);
        document.body.appendChild(previewWrap);

        if (!canvas) {
            canvas = document.createElement('canvas');
            canvas.style.display = 'none';
            document.body.appendChild(canvas);
        }
    };

    /**
     * Inject transparent watermark with student name.
     */
    const injectWatermark = function() {
        if (!cfg.showwatermark || document.getElementById('lp-watermark')) {
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
     *
     * @param {boolean} isReconnect
     */
    const requestCamera = function(isReconnect) {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showGateError(cfg.strings.camerablocked || 'Webcam API not supported in this browser.');
            return;
        }

        const res = cfg.resolution || 640;
        const videoConstraints = {
            width: { ideal: res },
            height: { ideal: Math.round(res * 0.75) },
            frameRate: { ideal: 30 },
            facingMode: 'user',
        };

        navigator.mediaDevices.getUserMedia({
            video: videoConstraints,
            audio: true,
        }).then(function(mediaStream) {
            // Strictly verify BOTH video AND audio tracks are present and active!
            const videoTracks = mediaStream.getVideoTracks();
            const audioTracks = mediaStream.getAudioTracks();
            if (!videoTracks.length || !audioTracks.length) {
                mediaStream.getTracks().forEach(function(t) { t.stop(); });
                cameraActive = false;
                showGateError(cfg.strings.cameramic_required || 'Webcam and Microphone access are strictly required to enter this exam.');
                logEvent('camera_denied', 'high');
                return;
            }

            // Virtual Camera Blacklist Inspection.
            const track = videoTracks[0];
            const trackLabel = (track.label || '').toLowerCase();
            const blacklistedKeywords = [
                'obs', 'virtual', 'manycam', 'v4l2loopback', 'splitcam',
                'camo', 'droidcam', 'iriun', 'fake', 'software camera', 'e2esoft'
            ];
            const isVirtual = blacklistedKeywords.some(function(keyword) {
                return trackLabel.indexOf(keyword) !== -1;
            });

            if (isVirtual) {
                mediaStream.getTracks().forEach(function(t) { t.stop(); });
                stream = null;
                cameraActive = false;
                logEvent('virtual_camera_detected', 'high');
                const errTemplate = cfg.strings.virtualcamerablocked || 'Virtual camera detected ({$a}). Virtual cameras are strictly prohibited.';
                const errMsg = errTemplate.replace('{$a}', track.label || 'Virtual Device');
                showCameraGate(false);
                showGateError(errMsg);
                return;
            }

            stream = mediaStream;
            cameraActive = true;

            const attemptid = getAttemptId();
            if (attemptid) {
                try {
                    sessionStorage.setItem('lp_cam_granted_' + attemptid, '1');
                } catch (e) {}
            }

            // Stop any reconnection countdown.
            if (reconnectTimer) {
                clearInterval(reconnectTimer);
                reconnectTimer = null;
            }

            // Remove full-screen camera barrier!
            const gate = document.getElementById('lp-camera-gate');
            if (gate) {
                gate.remove();
            }

            // Mount UI overlays only AFTER camera permission dialog has finished.
            setupVideoElements();
            injectWatermark();

            if (video) {
                video.srcObject = mediaStream;
            }

            // Monitor video & audio track liveness.
            videoTracks[0].onended = function() {
                handleCameraLost();
            };
            videoTracks[0].onmute = function() {
                handleCameraLost();
            };
            audioTracks[0].onended = function() {
                handleCameraLost();
            };
            audioTracks[0].onmute = function() {
                handleCameraLost();
            };

            // Start audio speech / loud noise monitoring.
            setupAudioSpeechDetection(mediaStream);

            startSnapshots();
        }).catch(function() {
            cameraActive = false;
            showGateError(cfg.strings.cameramic_required || 'Webcam and Microphone access are strictly required to enter this exam.');
            logEvent('camera_denied', 'high');
        });
    };

    /**
     * Start periodic snapshot capture.
     */
    const startSnapshots = function() {
        const intervalMs = (cfg.snapshotinterval || 30) * 1000;

        if (snapTimer) {
            clearInterval(snapTimer);
        }

        // Take first snapshot immediately.
        captureAndPush();

        snapTimer = setInterval(function() {
            // Check camera stream active.
            if (!stream || !stream.active || !stream.getVideoTracks().length || stream.getVideoTracks()[0].readyState !== 'live') {
                handleCameraLost();
                return;
            }
            captureAndPush();
        }, intervalMs);
    };

    /**
     * Capture a frame from the camera and push to server.
     */
    /**
     * Capture a frame from the camera and push to server.
     *
     * @param {string|null} breachType Optional breach identifier to flag evidence snapshot.
     */
    const captureAndPush = function(breachType) {
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

        const params = {
            cmid:      cfg.cmid,
            quizid:    cfg.quizid,
            attemptid: attemptid,
            image:     imagedata,
            sesskey:   cfg.sesskey,
        };
        if (breachType) {
            params.breach = breachType;
        }

        fetch(cfg.pushurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(params),
        }).then(function(r) {
            return r.json();
        }).then(function(data) {
            if (data && data.suspicious) {
                issueWarning('ai_alert');
            }
            if (data && data.identity_match === false) {
                issueWarning('identity_mismatch');
            }
            if (data && data.face_detected === false) {
                issueWarning('no_face');
            }
            if (data && data.face_count > 1) {
                issueWarning('multiple_faces');
            }
        }).catch(function() {
            // Network error — silently retry next interval.
        });
    };

    /**
     * Detect screenshot, print, and screen-clipping attempts.
     */
    const setupScreenshotDetection = function() {
        window.addEventListener('keyup', function(e) {
            if (e.key === 'PrintScreen' || e.keyCode === 44) {
                try {
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText('');
                    }
                } catch (err) {}
                issueWarning('screenshot_attempt');
            }
        });

        window.addEventListener('keydown', function(e) {
            // Ctrl+P / Cmd+P (Print to PDF / printer).
            if ((e.ctrlKey || e.metaKey) && (e.key === 'p' || e.key === 'P')) {
                e.preventDefault();
                issueWarning('screenshot_attempt');
            }
            // Snipping tool shortcuts: Ctrl+Shift+S or Meta+Shift+S.
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'S' || e.key === 's')) {
                e.preventDefault();
                issueWarning('screenshot_attempt');
            }
        });
    };

    /**
     * Monitor audio levels to detect student speech or loud conversations.
     *
     * @param {MediaStream} audioStream
     */
    const setupAudioSpeechDetection = function(audioStream) {
        if (!audioStream || !audioStream.getAudioTracks().length) {
            return;
        }

        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) {
                return;
            }
            const actx = new AudioCtx();
            const src = actx.createMediaStreamSource(audioStream);
            const analyser = actx.createAnalyser();
            analyser.fftSize = 256;
            src.connect(analyser);

            const dataArray = new Uint8Array(analyser.frequencyBinCount);
            let speechDurationMs = 0;
            let lastSpeechWarnTime = 0;

            setInterval(function() {
                if (!cameraActive || !stream) {
                    return;
                }
                analyser.getByteFrequencyData(dataArray);
                let sum = 0;
                for (let i = 0; i < dataArray.length; i++) {
                    sum += dataArray[i];
                }
                const avg = sum / dataArray.length;
                if (avg > 38) {
                    speechDurationMs += 250;
                    if (speechDurationMs >= 1750) {
                        const now = Date.now();
                        if (now - lastSpeechWarnTime > 8000) {
                            lastSpeechWarnTime = now;
                            issueWarning('speaking_detected');
                        }
                        speechDurationMs = 0;
                    }
                } else {
                    speechDurationMs = Math.max(0, speechDurationMs - 120);
                }
            }, 250);
        } catch (e) {}
    };

    /**
     * Detect tab visibility changes with debouncing to prevent double warnings.
     */
    const setupTabSwitch = function() {
        if (!cfg.blocktabs) {
            return;
        }

        let lastTabSwitchTime = 0;
        const handleTabSwitch = function() {
            const now = Date.now();
            if (now - lastTabSwitchTime < 1000) {
                return;
            }
            lastTabSwitchTime = now;
            issueWarning('tab_switch');
        };

        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                handleTabSwitch();
            }
        });

        // Blur = user switched window.
        window.addEventListener('blur', function() {
            handleTabSwitch();
        });
    };

    /**
     * Issue a warning to the student and capture immediate breach proof.
     *
     * @param {string} eventtype Type of violation.
     */
    const issueWarning = function(eventtype) {
        warningCount++;
        logEvent(eventtype, warningCount > 2 ? 'high' : 'medium');

        // Immediately take snapshot of the student at this exact breach moment!
        if (eventtype !== 'ai_alert' && eventtype !== 'identity_mismatch' && eventtype !== 'no_face' && eventtype !== 'multiple_faces') {
            captureAndPush('breach_' + eventtype);
        }

        const remaining = cfg.warninglimit - warningCount;
        let violationText = cfg.strings.warningmessage || 'A proctoring violation has been detected.';
        if (eventtype === 'tab_switch') {
            violationText = cfg.strings.tabswitch || violationText;
        } else if (eventtype === 'screenshot_attempt') {
            violationText = cfg.strings.screenshot_detected || 'Screenshot attempt detected!';
        } else if (eventtype === 'speaking_detected') {
            violationText = cfg.strings.speaking_detected || 'Speech or noise detected!';
        } else if (eventtype === 'no_face') {
            violationText = cfg.strings.face_disappeared || 'Student face not detected in frame!';
        } else if (eventtype === 'multiple_faces') {
            violationText = 'Multiple faces detected in camera frame!';
        }

        showWarning(
            violationText + '\n' +
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
                <p id="lp-warning-text"></p>
                <button id="lp-dismiss-btn" class="btn btn-warning">
                    OK — I understand
                </button>
            </div>
        `;
        document.body.appendChild(overlay);

        const textElem = overlay.querySelector('#lp-warning-text');
        if (textElem && message) {
            String(message).split('\n').forEach(function(line, idx) {
                if (idx > 0) {
                    textElem.appendChild(document.createElement('br'));
                }
                textElem.appendChild(document.createTextNode(line));
            });
        }

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

        const logUrl = cfg.logeventurl || cfg.pushurl.replace('push_snapshot.php', 'log_event.php');
        fetch(logUrl, {
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
     * Auto-submit the quiz attempt with server-enforced lock.
     */
    const autoSubmitAttempt = function() {
        showWarning(cfg.strings.autosubmit || 'Too many violations. Exam submitted automatically.');

        const attemptid = getAttemptId();
        if (attemptid && cfg.signalurl) {
            // Server-enforced immediate finalization in Moodle database
            fetch(cfg.signalurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action:    'student_auto_submit',
                    attemptid: attemptid,
                    reason:    'warning_limit',
                    sesskey:   cfg.sesskey,
                }),
            }).catch(function() {});
        }

        setTimeout(function() {
            // Find and click the finish attempt button.
            const finishBtn = document.querySelector(
                '.submitbtns [type="submit"], #responseform [name="finishattempt"]'
            );
            if (finishBtn) {
                finishBtn.click();
            } else {
                // Fallback: navigate to finish URL.
                if (attemptid) {
                    window.location.href = M.cfg.wwwroot +
                        '/mod/quiz/summary.php?attempt=' + attemptid + '&cmid=' + cfg.cmid;
                }
            }
        }, 2500);
    };

    /**
     * Extract attempt ID from config, URL, or DOM.
     *
     * @return {string|number|null}
     */
    const getAttemptId = function() {
        if (cfg.attemptid) {
            return cfg.attemptid;
        }
        const urlParams = new URLSearchParams(window.location.search);
        const fromUrl = urlParams.get('attempt');
        if (fromUrl) {
            return fromUrl;
        }
        const formInput = document.querySelector('input[name="attempt"]');
        if (formInput && formInput.value) {
            return formInput.value;
        }
        return null;
    };

    /**
     * Setup signaling heartbeat for Zoom Spotlight live streaming and teacher control.
     */
    const setupSignalHeartbeat = function() {
        if (!cfg.signalurl) {
            return;
        }

        signalTimer = setInterval(function() {
            const attemptid = getAttemptId();
            if (!attemptid) {
                return;
            }

            fetch(cfg.signalurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action:    'check_stream',
                    attemptid: attemptid,
                    sesskey:   cfg.sesskey,
                }),
            }).then(function(r) {
                return r.json();
            }).then(function(data) {
                if (!data) {
                    return;
                }

                // Check force-terminate signal from proctor.
                if (data.terminate_flag === 1) {
                    clearInterval(signalTimer);
                    autoSubmitAttempt();
                    return;
                }

                // Check custom proctor warning.
                if (data.custom_warning) {
                    showWarning(data.custom_warning);
                }

                // Check live stream request from teacher.
                if (data.active && !isLiveStreaming && stream && cameraActive) {
                    startLiveWebRTC(attemptid);
                } else if (!data.active && isLiveStreaming) {
                    stopLiveWebRTC();
                } else if (data.active && isLiveStreaming && data.answer && !answerApplied && peerConnection) {
                    applyTeacherAnswer(data.answer, data.teacher_candidates);
                }
            }).catch(function() {});
        }, 1000);
    };

    /**
     * Start WebRTC live stream & rapid frames to teacher.
     */
    const startLiveWebRTC = function(attemptid) {
        isLiveStreaming = true;
        answerApplied = false;

        try {
            const iceServers = [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' },
            ];
            if (cfg.turnurl) {
                iceServers.push({
                    urls: cfg.turnurl,
                    username: cfg.turnusername,
                    credential: cfg.turnpassword,
                });
            }
            peerConnection = new RTCPeerConnection({ iceServers: iceServers });

            if (stream) {
                stream.getTracks().forEach(function(track) {
                    const sender = peerConnection.addTrack(track, stream);
                    // Adaptive Bitrate & Framerate prioritization.
                    if (track.kind === 'video' && sender && sender.getParameters && sender.setParameters) {
                        try {
                            const p = sender.getParameters();
                            if (!p.encodings || p.encodings.length === 0) {
                                p.encodings = [{}];
                            }
                            p.encodings[0].maxBitrate = 600000; // 600 kbps max to prevent network congestion.
                            p.degradationPreference = 'maintain-framerate'; // Prioritize smooth 25-30 fps over resolution.
                            sender.setParameters(p).catch(function() {});
                        } catch (e) {}
                    }
                });
            }

            const iceCandidates = [];
            peerConnection.onicecandidate = function(event) {
                if (event.candidate) {
                    iceCandidates.push(event.candidate);
                }
            };

            let instantFrame = null;
            if (video && canvas) {
                try {
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(video, 0, 0, canvas.width || 320, canvas.height || 240);
                    instantFrame = canvas.toDataURL('image/jpeg', 0.6);
                } catch (e) {}
            }

            const waitForIceGathering = function(pc) {
                return new Promise(function(resolve) {
                    if (pc.iceGatheringState === 'complete') {
                        resolve();
                        return;
                    }
                    const check = function() {
                        if (pc.iceGatheringState === 'complete') {
                            pc.removeEventListener('icegatheringstatechange', check);
                            resolve();
                        }
                    };
                    pc.addEventListener('icegatheringstatechange', check);
                    setTimeout(function() {
                        pc.removeEventListener('icegatheringstatechange', check);
                        resolve();
                    }, 2000);
                });
            };

            peerConnection.createOffer().then(function(offer) {
                return peerConnection.setLocalDescription(offer);
            }).then(function() {
                return waitForIceGathering(peerConnection);
            }).then(function() {
                const params = {
                    action:     'send_offer',
                    attemptid:  attemptid,
                    offer:      JSON.stringify(peerConnection.localDescription),
                    candidates: JSON.stringify(iceCandidates),
                    sesskey:    cfg.sesskey,
                };
                if (instantFrame) {
                    params.frame = instantFrame;
                }
                sendWS({
                    type: 'send_offer',
                    attemptid: attemptid,
                    cmid: cfg.cmid,
                    offer: params.offer,
                    candidates: params.candidates,
                    frame: params.frame,
                });
                return fetch(cfg.signalurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams(params),
                });
            }).catch(function() {});

            if (liveFrameTimer) {
                clearInterval(liveFrameTimer);
            }
            liveFrameTimer = setInterval(function() {
                if (!isLiveStreaming || !video || !canvas) {
                    return;
                }
                try {
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(video, 0, 0, canvas.width || 320, canvas.height || 240);
                    const fastFrame = canvas.toDataURL('image/jpeg', 0.6);
                    fetch(cfg.signalurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action:    'push_live_frame',
                            attemptid: attemptid,
                            frame:     fastFrame,
                            sesskey:   cfg.sesskey,
                        }),
                    }).catch(function() {});
                } catch (e) {}
            }, 1000);

        } catch (err) {
            isLiveStreaming = false;
        }
    };

    /**
     * Apply remote SDP answer from teacher.
     */
    const applyTeacherAnswer = function(answerStr, candidatesStr) {
        try {
            answerApplied = true;
            const answerDesc = new RTCSessionDescription(JSON.parse(answerStr));
            peerConnection.setRemoteDescription(answerDesc).then(function() {
                if (candidatesStr) {
                    try {
                        const candidates = JSON.parse(candidatesStr);
                        candidates.forEach(function(c) {
                            peerConnection.addIceCandidate(new RTCIceCandidate(c)).catch(function() {});
                        });
                    } catch (e) {}
                }
            }).catch(function() {});
        } catch (e) {}
    };

    /**
     * Stop WebRTC live stream.
     */
    const stopLiveWebRTC = function() {
        isLiveStreaming = false;
        answerApplied = false;

        if (liveFrameTimer) {
            clearInterval(liveFrameTimer);
            liveFrameTimer = null;
        }

        if (peerConnection) {
            peerConnection.close();
            peerConnection = null;
        }
    };

    return { init };
});
