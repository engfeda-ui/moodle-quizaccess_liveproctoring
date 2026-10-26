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
 * AMD module: Teacher Zoom Exam Hall Monitor & Live Spotlight.
 *
 * Provides:
 *  - Real-time Zoom-style gallery grid of active students.
 *  - Filtering (All / Flagged only).
 *  - Instant Spotlight WebRTC live video & audio stream (On-Demand).
 *  - Instant direct warning dispatcher.
 *  - One-click exam termination / ejection.
 *  - Web Audio alert chimes for new violations.
 *
 * @module    quizaccess_liveproctoring/proctoring_monitor
 * @copyright 2026 Energy & Water Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    /** @type {object} Config from PHP */
    let cfg = {};

    /** @type {EventSource|null} SSE connection */
    let evtSource = null;

    /** @type {Set<number>} Already-alerted alert timestamps */
    const alreadyAlerted = new Set();

    /** @type {string} Current filter: 'all' or 'flagged' */
    let currentFilter = 'all';

    /** @type {boolean} Whether alert audio is enabled */
    let audioEnabled = true;

    /** @type {object|null} Active spotlight session */
    let activeSpotlight = null;

    /** @type {WebSocket|null} WebSocket signaling connection */
    let ws = null;

    /** @type {AudioContext|null} Web Audio API context for VU meter */
    let vuAudioCtx = null;

    /** @type {AnalyserNode|null} Analyser node for VU meter */
    let vuAnalyser = null;

    /** @type {number|null} requestAnimationFrame ID for VU meter */
    let vuAnimFrame = null;

    /**
     * Initialise the monitor.
     *
     * @param {object} config Passed from PHP.
     */
    const init = function(config) {
        cfg = config;

        requestNotificationPermission();
        connectSSE();
        setupReconnect();
        setupDockControls();
        setupSpotlightHandlers();
        connectWS();
    };

    const connectWS = function() {
        if (!cfg.wsurl) {
            return;
        }
        try {
            ws = new WebSocket(cfg.wsurl + '?cmid=' + cfg.cmid + '&role=teacher');
            ws.onmessage = function(e) {
                try {
                    const data = JSON.parse(e.data);
                    if (data.type === 'send_offer' && data.offer) {
                        if (activeSpotlight && !activeSpotlight.offerProcessed && data.attemptid === activeSpotlight.attemptid) {
                            activeSpotlight.offerProcessed = true;
                            handleIncomingOffer(data.offer, data.student_candidates);
                        }
                    }
                    if (data.type === 'push_live_frame' && data.frame) {
                        const frameElem = document.getElementById('lp-spotlight-frame');
                        const videoElem = document.getElementById('lp-spotlight-video');
                        if (frameElem && (!videoElem || videoElem.style.display === 'none')) {
                            frameElem.src = data.frame;
                            frameElem.style.display = 'block';
                        }
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
     * Request browser notification permission.
     */
    const requestNotificationPermission = function() {
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    };

    /**
     * Play a clean notification chime using Web Audio API.
     */
    const playChime = function() {
        if (!audioEnabled) {
            return;
        }
        try {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (!AudioCtx) {
                return;
            }
            const ctx = new AudioCtx();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();

            osc.type = 'sine';
            osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
            osc.frequency.setValueAtTime(880, ctx.currentTime + 0.08); // A5

            gain.gain.setValueAtTime(0.25, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);

            osc.connect(gain);
            gain.connect(ctx.destination);

            osc.start();
            osc.stop(ctx.currentTime + 0.35);
        } catch (e) {
            // Audio context policy.
        }
    };

    /**
     * Connect to SSE live feed.
     */
    const connectSSE = function() {
        evtSource = new EventSource(cfg.feedurl);

        evtSource.onmessage = function(e) {
            try {
                const data = JSON.parse(e.data);
                if (data.disconnect) {
                    evtSource.close();
                    setTimeout(connectSSE, 2000);
                    return;
                }
                updateDashboard(data);
            } catch (err) {
                // Ignore parse errors.
            }
        };

        evtSource.onerror = function() {
            evtSource.close();
            setTimeout(connectSSE, 5000);
        };
    };

    /**
     * Auto-reconnect if tab regains focus.
     */
    const setupReconnect = function() {
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden && (!evtSource || evtSource.readyState === EventSource.CLOSED)) {
                connectSSE();
            }
        });
    };

    /**
     * Setup bottom Zoom dock buttons.
     */
    const setupDockControls = function() {
        // Filter: All.
        const btnAll = document.getElementById('lp-filter-all');
        const btnFlagged = document.getElementById('lp-filter-flagged');

        if (btnAll && btnFlagged) {
            btnAll.addEventListener('click', function() {
                currentFilter = 'all';
                btnAll.classList.add('active');
                btnFlagged.classList.remove('active');
                applyFilter();
            });

            btnFlagged.addEventListener('click', function() {
                currentFilter = 'flagged';
                btnFlagged.classList.add('active');
                btnAll.classList.remove('active');
                applyFilter();
            });
        }

        // Audio toggle.
        const btnAudio = document.getElementById('lp-btn-audio');
        const audioStatus = document.getElementById('lp-audio-status');
        if (btnAudio && audioStatus) {
            btnAudio.addEventListener('click', function() {
                audioEnabled = !audioEnabled;
                audioStatus.textContent = audioEnabled ? 'الصوت: مفعّل' : 'الصوت: مكتوم';
                btnAudio.style.opacity = audioEnabled ? '1' : '0.6';
            });
        }

        // Fullscreen toggle.
        const btnFs = document.getElementById('lp-btn-fullscreen');
        if (btnFs) {
            btnFs.addEventListener('click', function() {
                if (!document.fullscreenElement) {
                    document.documentElement.requestFullscreen().catch(function() {});
                } else {
                    document.exitFullscreen().catch(function() {});
                }
            });
        }
    };

    /**
     * Apply the current filter to card visibility.
     */
    const applyFilter = function() {
        const cards = document.querySelectorAll('.lp-student-card');
        cards.forEach(function(card) {
            const isSuspicious = card.dataset.suspicious === '1';
            const risk = parseInt(card.dataset.risk || '0', 10);

            if (currentFilter === 'flagged') {
                if (isSuspicious || risk >= 40) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            } else {
                card.style.display = '';
            }
        });
    };

    /**
     * Update the dashboard grid with latest data.
     *
     * @param {object} data JSON payload from SSE.
     */
    const updateDashboard = function(data) {
        updateStats(data);
        updateAlertBanner(data.alerts || []);
        updateStudentGrid(data.students || []);
        applyFilter();
    };

    /**
     * Update the top stats bar.
     *
     * @param {object} data
     */
    const updateStats = function(data) {
        const total = document.getElementById('lp-stat-total');
        const suspicious = document.getElementById('lp-stat-suspicious');
        const lastupdate = document.getElementById('lp-stat-lastupdate');

        if (total) {
            total.textContent = data.total || 0;
        }
        if (suspicious) {
            suspicious.textContent = data.suspicious || 0;
            suspicious.className = data.suspicious > 0 ? 'lp-dock-value lp-risk-high' : 'lp-dock-value';
        }
        if (lastupdate) {
            lastupdate.textContent = new Date(data.servertime * 1000).toLocaleTimeString();
        }
    };

    /**
     * Update the alert banner for new high-risk events.
     *
     * @param {Array} alerts
     */
    const updateAlertBanner = function(alerts) {
        const banner = document.getElementById('lp-alert-banner');
        if (!banner) {
            return;
        }

        if (!alerts || alerts.length === 0) {
            banner.style.display = 'none';
            return;
        }

        banner.style.display = 'block';
        const latest = alerts[0];
        banner.textContent = '⚠️ ' + latest.name + ' — ' + latest.eventtype;

        // Browser notification & chime for NEW alerts.
        if (!alreadyAlerted.has(latest.time)) {
            alreadyAlerted.add(latest.time);
            playChime();
            sendBrowserNotification(latest.name, latest.eventtype);
        }
    };

    /**
     * Send a browser notification.
     */
    const sendBrowserNotification = function(studentName, eventtype) {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('🚨 Zoom Exam Hall Alert', {
                body: studentName + ' — ' + eventtype,
                tag: 'lp-alert-' + Date.now(),
            });
        }
    };

    /**
     * Update each student card in the grid.
     *
     * @param {Array} students
     */
    const updateStudentGrid = function(students) {
        const grid = document.getElementById('lp-student-grid');
        if (!grid) {
            return;
        }

        const connecting = grid.querySelector('.lp-connecting');
        if (connecting) {
            connecting.remove();
        }

        if (!students || students.length === 0) {
            if (!grid.querySelector('.lp-empty-state')) {
                grid.innerHTML = `
                    <div class="lp-empty-state" style="grid-column:1/-1;text-align:center;padding:48px 16px;color:#8892b0;">
                        <div style="font-size:48px;margin-bottom:12px;">⏳</div>
                        <h4 style="color:#ccd6f6;margin-bottom:8px;">${cfg.strings.nostudents || 'Waiting for students...'}</h4>
                        <p style="font-size:14px;color:#8892b0;">${cfg.strings.nostudentsdesc || 'Active student cameras will appear here automatically.'}</p>
                    </div>
                `;
            }
            return;
        }

        const emptyState = grid.querySelector('.lp-empty-state');
        if (emptyState) {
            emptyState.remove();
        }

        const activeIds = new Set(students.map(s => String(s.userid)));
        grid.querySelectorAll('.lp-student-card').forEach(function(card) {
            const uid = card.id.replace('lp-card-', '');
            if (!activeIds.has(uid)) {
                card.remove();
            }
        });

        students.forEach(function(student) {
            let card = document.getElementById('lp-card-' + student.userid);

            if (!card) {
                card = createStudentCard(student);
                grid.appendChild(card);
            } else {
                updateStudentCard(card, student);
            }
        });
    };

    /**
     * Create a new student card element.
     *
     * @param {object} student
     * @return {HTMLElement}
     */
    const createStudentCard = function(student) {
        const card = document.createElement('div');
        card.id = 'lp-card-' + student.userid;
        card.className = 'lp-student-card';
        card.dataset.suspicious = student.suspicious ? '1' : '0';
        card.dataset.risk = student.risk_score;
        card.innerHTML = buildCardHTML(student);
        attachCardEvents(card, student);
        return card;
    };

    /**
     * Update an existing student card.
     */
    const updateStudentCard = function(card, student) {
        card.dataset.suspicious = student.suspicious ? '1' : '0';
        card.dataset.risk = student.risk_score;
        card.innerHTML = buildCardHTML(student);
        attachCardEvents(card, student);
    };

    /**
     * Attach click handler to spotlight trigger.
     */
    const attachCardEvents = function(card, student) {
        const btn = card.querySelector('.lp-spotlight-trigger');
        if (btn) {
            btn.onclick = function(e) {
                e.stopPropagation();
                openSpotlight(student);
            };
        }
    };

    /**
     * Escape HTML special characters.
     *
     * @param {string} str
     * @return {string}
     */
    const escapeHtml = function(str) {
        if (!str) {
            return '';
        }
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    /**
     * Build HTML for a student card.
     */
    const buildCardHTML = function(student) {
        const risk = student.risk_score;
        let riskClass = 'lp-risk-ok';
        let riskLabel = cfg.strings.ok || 'OK';
        let borderColor = '#5C7268'; // SANAD Sage Green

        if (student.suspicious || risk >= 70) {
            riskClass = 'lp-risk-high';
            riskLabel = cfg.strings.high || 'HIGH RISK';
            borderColor = '#C4694A'; // SANAD Terracotta
        } else if (risk >= 40) {
            riskClass = 'lp-risk-medium';
            riskLabel = cfg.strings.medium || 'Warning';
            borderColor = '#C89B3C'; // SANAD Gold
        }

        const thumbHtml = (student.thumb && student.thumb.length > 50)
            ? `<img src="${student.thumb}" class="lp-thumb" alt="${escapeHtml(student.name)}" />`
            : `<div class="lp-thumb-placeholder" style="height:140px;display:flex;flex-direction:column;align-items:center;justify-content:center;background:#16213e;color:#8892b0;border-radius:8px 8px 0 0;">
                 <span style="font-size:32px;">📷</span>
                 <small style="margin-top:6px;font-size:11px;">${escapeHtml(student.status_msg || 'Connecting camera...')}</small>
               </div>`;

        const faceInfo = student.face_detected
            ? (student.face_count > 1
                ? `<span class="lp-badge lp-badge-danger">${cfg.strings.multiface || 'Multiple faces'}</span>`
                : '')
            : `<span class="lp-badge lp-badge-warning">${cfg.strings.noface || 'No face'}</span>`;

        const timeAgo = student.lastseen
            ? Math.round((Date.now() / 1000) - student.lastseen) + 's ago'
            : '—';

        return `
            <div class="lp-card-inner" style="border-color:${borderColor}">
                ${thumbHtml}
                <div class="lp-card-info">
                    <div class="lp-card-name">${escapeHtml(student.name)}</div>
                    <div class="${riskClass} lp-risk-label">${riskLabel}</div>
                    ${risk >= 0 ? `<div class="lp-risk-score">Risk: ${risk}/100</div>` : ''}
                    ${student.status_msg ? `<div class="lp-status-msg" style="font-size:11px;font-weight:600;margin:3px 0;color:${borderColor};">${escapeHtml(student.status_msg)}</div>` : ''}
                    ${faceInfo}
                    <div class="lp-lastseen">${cfg.strings.lastseen || 'Last seen'}: ${timeAgo}</div>
                    <button class="lp-spotlight-trigger" data-userid="${student.userid}">
                        🎥 ${cfg.strings.spotlight || 'Live Spotlight'}
                    </button>
                </div>
            </div>
        `;
    };

    /**
     * ============================================================
     * Spotlight Live Video Engine (WebRTC + Turbo Fallback)
     * ============================================================
     */

    /**
     * Setup handlers for Spotlight modal buttons.
     */
    const setupSpotlightHandlers = function() {
        const closeBtn = document.getElementById('lp-spotlight-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', closeSpotlight);
        }

        const sendWarnBtn = document.getElementById('lp-btn-send-warning');
        if (sendWarnBtn) {
            sendWarnBtn.addEventListener('click', function() {
                const input = document.getElementById('lp-direct-warning-input');
                if (!input || !input.value.trim() || !activeSpotlight) {
                    return;
                }
                const msg = input.value.trim();
                sendDirectWarning(activeSpotlight.attemptid, msg);
                input.value = '';
            });
        }

        const termBtn = document.getElementById('lp-btn-terminate');
        if (termBtn) {
            termBtn.addEventListener('click', function() {
                if (!activeSpotlight) {
                    return;
                }
                const promptMsg = cfg.strings.terminate_prompt || 'Are you sure you want to terminate this student exam attempt?';
                if (confirm(promptMsg)) {
                    terminateStudentAttempt(activeSpotlight.attemptid);
                }
            });
        }

        const audioToggleBtn = document.getElementById('lp-spotlight-audio-toggle');
        if (audioToggleBtn) {
            audioToggleBtn.addEventListener('click', function() {
                const videoElem = document.getElementById('lp-spotlight-video');
                if (!videoElem) {
                    return;
                }
                videoElem.muted = !videoElem.muted;
                const label = document.getElementById('lp-audio-btn-label');
                if (videoElem.muted) {
                    audioToggleBtn.className = 'btn btn-sm btn-outline-secondary';
                    if (label) {
                        label.textContent = cfg.strings.audiomuted || 'Audio: Muted';
                    }
                    if (audioToggleBtn.childNodes[0]) {
                        audioToggleBtn.childNodes[0].nodeValue = '🔇 ';
                    }
                } else {
                    audioToggleBtn.className = 'btn btn-sm btn-outline-success';
                    if (label) {
                        label.textContent = cfg.strings.audiounmuted || 'Audio: Active';
                    }
                    if (audioToggleBtn.childNodes[0]) {
                        audioToggleBtn.childNodes[0].nodeValue = '🔊 ';
                    }
                    if (vuAudioCtx && vuAudioCtx.state === 'suspended') {
                        vuAudioCtx.resume().catch(function() {});
                    }
                }
            });
        }
    };

    /**
     * Open Spotlight live modal for a candidate.
     *
     * @param {object} student
     */
    const openSpotlight = function(student) {
        if (activeSpotlight) {
            closeSpotlight();
        }

        const modal = document.getElementById('lp-spotlight-modal');
        const titleName = document.getElementById('lp-spotlight-name');
        const statusBadge = document.getElementById('lp-spotlight-status');
        const videoElem = document.getElementById('lp-spotlight-video');
        const frameElem = document.getElementById('lp-spotlight-frame');

        if (!modal || !titleName || !statusBadge) {
            return;
        }

        titleName.textContent = student.name;
        statusBadge.textContent = '📡 ' + (cfg.strings.connecting_live || 'Connecting to camera...');
        modal.style.display = 'flex';

        if (videoElem) {
            videoElem.style.display = 'none';
            videoElem.srcObject = null;
            videoElem.muted = false;
        }

        const audioToggleBtn = document.getElementById('lp-spotlight-audio-toggle');
        const audioBtnLabel = document.getElementById('lp-audio-btn-label');
        if (audioToggleBtn && audioBtnLabel) {
            audioToggleBtn.className = 'btn btn-sm btn-outline-success';
            audioBtnLabel.textContent = cfg.strings.audiounmuted || 'Audio: Active';
            if (audioToggleBtn.childNodes[0]) {
                audioToggleBtn.childNodes[0].nodeValue = '🔊 ';
            }
        }

        const vuBar = document.getElementById('lp-audio-vu-bar');
        if (vuBar) {
            vuBar.style.width = '0%';
        }

        if (frameElem) {
            frameElem.style.display = 'none';
            if (student.thumb && student.thumb.length > 50) {
                frameElem.src = student.thumb;
                frameElem.style.display = 'block';
            }
        }

        activeSpotlight = {
            student: student,
            attemptid: student.attemptid,
            pc: null,
            pollTimer: null,
            offerProcessed: false,
        };

        // Notify server that teacher started live request.
        fetch(cfg.signalurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action:    'start_stream',
                attemptid: student.attemptid,
                cmid:      cfg.cmid,
                sesskey:   cfg.sesskey,
            }),
        }).catch(function() {});

        sendWS({ type: 'start_stream', attemptid: student.attemptid, cmid: cfg.cmid });

        // Start polling stream signal every 1s.
        activeSpotlight.pollTimer = setInterval(pollSpotlightStream, 500);
    };

    /**
     * Poll live stream signal endpoint.
     */
    const pollSpotlightStream = function() {
        if (!activeSpotlight) {
            return;
        }

        fetch(cfg.signalurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action:    'poll_stream',
                attemptid: activeSpotlight.attemptid,
                cmid:      cfg.cmid,
                sesskey:   cfg.sesskey,
            }),
        }).then(function(r) {
            return r.json();
        }).then(function(data) {
            if (!activeSpotlight || !data.success) {
                return;
            }

            const frameElem = document.getElementById('lp-spotlight-frame');
            const videoElem = document.getElementById('lp-spotlight-video');
            const statusBadge = document.getElementById('lp-spotlight-status');

            // Render Turbo frame if video not yet live.
            if (data.latest_frame && (!videoElem || videoElem.style.display === 'none')) {
                if (frameElem) {
                    frameElem.src = data.latest_frame;
                    frameElem.style.display = 'block';
                }
                if (statusBadge) {
                    statusBadge.textContent = '⚡ ' + (cfg.strings.live_active || 'Connected Live');
                }
            }

            // If student created WebRTC offer and we haven't answered yet.
            if (data.offer && !activeSpotlight.offerProcessed) {
                activeSpotlight.offerProcessed = true;
                handleIncomingOffer(data.offer, data.student_candidates);
            }
        }).catch(function() {});
    };

    /**
     * Connect remote stream to Web Audio API Analyser to drive the VU meter.
     *
     * @param {MediaStream} mediaStream
     */
    const setupAudioVUMeter = function(mediaStream) {
        if (!mediaStream) {
            return;
        }

        try {
            if (vuAudioCtx) {
                vuAudioCtx.close().catch(function() {});
            }
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (!AudioContextClass) {
                return;
            }
            vuAudioCtx = new AudioContextClass();
            vuAnalyser = vuAudioCtx.createAnalyser();
            vuAnalyser.fftSize = 64;
            const source = vuAudioCtx.createMediaStreamSource(mediaStream);
            source.connect(vuAnalyser);

            const dataArray = new Uint8Array(vuAnalyser.frequencyBinCount);
            const vuBar = document.getElementById('lp-audio-vu-bar');

            const updateVU = function() {
                if (!activeSpotlight || !vuAnalyser) {
                    return;
                }
                vuAnalyser.getByteFrequencyData(dataArray);
                let sum = 0;
                for (let i = 0; i < dataArray.length; i++) {
                    sum += dataArray[i];
                }
                const avg = sum / dataArray.length;
                const percent = Math.min(100, Math.round((avg / 128) * 100));

                if (vuBar) {
                    vuBar.style.width = percent + '%';
                    if (percent > 65) {
                        vuBar.style.background = '#ef4444';
                    } else if (percent > 30) {
                        vuBar.style.background = '#f59e0b';
                    } else {
                        vuBar.style.background = '#10b981';
                    }
                }
                vuAnimFrame = requestAnimationFrame(updateVU);
            };
            updateVU();
        } catch (e) {}
    };

    /**
     * Handle incoming WebRTC offer from student.
     *
     * @param {string} offerStr
     * @param {string} candidatesStr
     */
    const handleIncomingOffer = function(offerStr, candidatesStr) {
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
            const pc = new RTCPeerConnection({ iceServers: iceServers });

            activeSpotlight.pc = pc;

            pc.ontrack = function(event) {
                const videoElem = document.getElementById('lp-spotlight-video');
                const frameElem = document.getElementById('lp-spotlight-frame');
                const statusBadge = document.getElementById('lp-spotlight-status');

                if (videoElem && event.streams && event.streams[0]) {
                    videoElem.srcObject = event.streams[0];
                    videoElem.style.display = 'block';
                    if (frameElem) {
                        frameElem.style.display = 'none';
                    }
                    if (statusBadge) {
                        statusBadge.textContent = '🟢 ' + (cfg.strings.live_active || 'Connected Live (30 FPS)');
                    }
                    setupAudioVUMeter(event.streams[0]);
                }
            };

            const offerDesc = new RTCSessionDescription(JSON.parse(offerStr));
            pc.setRemoteDescription(offerDesc).then(function() {
                // Add student candidates if any.
                if (candidatesStr) {
                    try {
                        const candidates = JSON.parse(candidatesStr);
                        candidates.forEach(function(c) {
                            pc.addIceCandidate(new RTCIceCandidate(c)).catch(function() {});
                        });
                    } catch (e) {}
                }
                return pc.createAnswer();
            }).then(function(answer) {
                return pc.setLocalDescription(answer);
            }).then(function() {
                // Send answer back to student via WS if connected and HTTP fallback.
                sendWS({
                    type:      'send_answer',
                    attemptid: activeSpotlight.attemptid,
                    answer:    JSON.stringify(pc.localDescription),
                    cmid:      cfg.cmid,
                });
                fetch(cfg.signalurl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action:    'send_answer',
                        attemptid: activeSpotlight.attemptid,
                        cmid:      cfg.cmid,
                        answer:    JSON.stringify(pc.localDescription),
                        sesskey:   cfg.sesskey,
                    }),
                });
            }).catch(function() {});
        } catch (err) {
            // WebRTC error — Turbo frame fallback remains active.
        }
    };

    /**
     * Send direct warning message to student.
     */
    const sendDirectWarning = function(attemptid, message) {
        fetch(cfg.signalurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action:    'send_warning',
                attemptid: attemptid,
                cmid:      cfg.cmid,
                message:   message,
                sesskey:   cfg.sesskey,
            }),
        }).then(function() {
            alert(cfg.strings.warning_sent || 'Warning sent successfully!');
        }).catch(function() {});

        sendWS({ type: 'send_warning', attemptid: attemptid, cmid: cfg.cmid, message: message });
    };

    /**
     * Force terminate student attempt.
     */
    const terminateStudentAttempt = function(attemptid) {
        fetch(cfg.signalurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action:    'terminate_attempt',
                attemptid: attemptid,
                cmid:      cfg.cmid,
                sesskey:   cfg.sesskey,
            }),
        }).then(function() {
            closeSpotlight();
        }).catch(function() {});

        sendWS({ type: 'terminate_attempt', attemptid: attemptid, cmid: cfg.cmid });
    };

    /**
     * Close the active spotlight modal.
     */
    const closeSpotlight = function() {
        if (!activeSpotlight) {
            return;
        }

        const modal = document.getElementById('lp-spotlight-modal');
        if (modal) {
            modal.style.display = 'none';
        }

        const videoElem = document.getElementById('lp-spotlight-video');
        if (videoElem) {
            videoElem.srcObject = null;
        }

        if (vuAnimFrame) {
            cancelAnimationFrame(vuAnimFrame);
            vuAnimFrame = null;
        }
        if (vuAudioCtx) {
            vuAudioCtx.close().catch(function() {});
            vuAudioCtx = null;
            vuAnalyser = null;
        }
        const vuBar = document.getElementById('lp-audio-vu-bar');
        if (vuBar) {
            vuBar.style.width = '0%';
        }

        if (activeSpotlight.pollTimer) {
            clearInterval(activeSpotlight.pollTimer);
        }

        if (activeSpotlight.pc) {
            activeSpotlight.pc.close();
        }

        // Notify server that teacher stopped live session.
        fetch(cfg.signalurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action:    'stop_stream',
                attemptid: activeSpotlight.attemptid,
                cmid:      cfg.cmid,
                sesskey:   cfg.sesskey,
            }),
        }).catch(function() {});

        activeSpotlight = null;
    };

    return { init };
});
