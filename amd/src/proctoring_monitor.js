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
 * AMD module: Teacher Live Monitor Dashboard.
 *
 * Opens an SSE connection to live_feed.php and updates the student grid
 * in real time. Shows color-coded risk levels and browser notifications
 * for high-risk events.
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

    /** @type {Set<number>} Already-alerted user IDs (to prevent repeat notifications) */
    const alreadyAlerted = new Set();

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
     * Connect to SSE live feed.
     */
    const connectSSE = function() {
        evtSource = new EventSource(cfg.feedurl);

        evtSource.onmessage = function(e) {
            try {
                const data = JSON.parse(e.data);
                if (data.disconnect) {
                    evtSource.close();
                    setTimeout(connectSSE, 2000); // Reconnect.
                    return;
                }
                updateDashboard(data);
            } catch (err) {
                // Ignore parse errors.
            }
        };

        evtSource.onerror = function() {
            evtSource.close();
            setTimeout(connectSSE, 5000); // Retry after 5s.
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
     * Update the dashboard grid with latest data.
     *
     * @param {object} data JSON payload from SSE.
     */
    const updateDashboard = function(data) {
        updateStats(data);
        updateAlertBanner(data.alerts || []);
        updateStudentGrid(data.students || []);
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
            suspicious.className = data.suspicious > 0 ? 'lp-stat-value lp-risk-high' : 'lp-stat-value';
        }
        if (lastupdate) {
            lastupdate.textContent = new Date(data.servertime * 1000).toLocaleTimeString();
        }
    };

    /**
     * Update the alert banner for new high-risk events.
     *
     * @param {Array} alerts List of recent high-risk events.
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

        // Browser notification for NEW alerts.
        if (!alreadyAlerted.has(latest.time)) {
            alreadyAlerted.add(latest.time);
            sendBrowserNotification(latest.name, latest.eventtype);
        }
    };

    /**
     * Send a browser notification.
     *
     * @param {string} studentName
     * @param {string} eventtype
     */
    const sendBrowserNotification = function(studentName, eventtype) {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('🚨 Live Monitor Alert', {
                body: studentName + ' — ' + eventtype,
                icon: M.cfg.wwwroot + '/mod/quiz/accessrule/liveproctoring/pix/alert.png',
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

        // Always remove the initial connecting indicator.
        const connecting = grid.querySelector('.lp-connecting');
        if (connecting) {
            connecting.remove();
        }

        if (!students || students.length === 0) {
            if (!grid.querySelector('.lp-empty-state')) {
                grid.innerHTML = `
                    <div class="lp-empty-state" style="grid-column:1/-1;text-align:center;padding:48px 16px;color:#8892b0;">
                        <div style="font-size:42px;margin-bottom:12px;">⏳</div>
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

        // Track active user IDs to remove finished/left students.
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
        card.innerHTML = buildCardHTML(student);
        return card;
    };

    /**
     * Update an existing student card.
     *
     * @param {HTMLElement} card
     * @param {object}      student
     */
    const updateStudentCard = function(card, student) {
        card.innerHTML = buildCardHTML(student);
    };

    /**
     * Build HTML for a student card.
     *
     * @param {object} student
     * @return {string}
     */
    const buildCardHTML = function(student) {
        const risk = student.risk_score;
        let riskClass = 'lp-risk-ok';
        let riskLabel = cfg.strings.ok || 'OK';
        let borderColor = '#27ae60';

        if (student.suspicious || risk >= 70) {
            riskClass = 'lp-risk-high';
            riskLabel = cfg.strings.high || 'HIGH RISK';
            borderColor = '#e74c3c';
        } else if (risk >= 40) {
            riskClass = 'lp-risk-medium';
            riskLabel = cfg.strings.medium || 'Warning';
            borderColor = '#f39c12';
        }

        const thumbHtml = (student.thumb && student.thumb.length > 50)
            ? `<img src="${student.thumb}" class="lp-thumb" alt="${student.name}" />`
            : `<div class="lp-thumb-placeholder" style="height:140px;display:flex;flex-direction:column;align-items:center;justify-content:center;background:#16213e;color:#8892b0;border-radius:8px 8px 0 0;">
                 <span style="font-size:32px;">📷</span>
                 <small style="margin-top:6px;font-size:11px;">${student.status_msg || 'Connecting camera...'}</small>
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
                    <div class="lp-card-name">${student.name}</div>
                    <div class="${riskClass} lp-risk-label">${riskLabel}</div>
                    ${risk >= 0 ? `<div class="lp-risk-score">Risk: ${risk}/100</div>` : ''}
                    ${faceInfo}
                    <div class="lp-lastseen">${cfg.strings.lastseen || 'Last seen'}: ${timeAgo}</div>
                </div>
            </div>
        `;
    };

    return { init };
});
