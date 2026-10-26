# 🎥 Live Proctoring with Custom AI (`quizaccess_liveproctoring`)

[![Moodle Plugin](https://img.shields.io/badge/Moodle-4.4+-orange.svg)](https://moodle.org)
[![Version](https://img.shields.io/badge/version-v1.0.19-blue.svg)](version.php)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](LICENSE)

An intelligent, real-time proctoring subplugin for Moodle Quiz activities (`mod_quiz`). Combines automatic periodic webcam snapshots, behavioural monitoring (tab-switching detection), anti-cheat watermarking, a real-time **Live Monitor Dashboard** for instructors powered by Server-Sent Events (SSE), and seamless integration with **Custom AI APIs** for automated facial and risk analysis.

---

## 🌟 Key Features

1. **📷 Periodic Webcam Snapshots**:
   - Captures low-latency JPEG frames at configurable intervals (e.g. every 30s) directly from the student's browser.
   - Automatically generates a lightweight base64 thumbnail for instant live rendering.
   - Full-resolution photos stored securely in Moodle File Storage (`snapshots` filearea).

2. **🤖 Custom AI API Integration**:
   - Modular HTTP client connects to any custom AI endpoint (OpenAI Vision, Gemini, local FastAPI/Flask models).
   - Real-time risk evaluation (`risk_score`: 0–100, face count, suspicious posture/gaze).
   - Graceful fallback: If AI API is offline or slow, snapshots continue recording without interrupting the student's exam.

3. **🔴 Live Monitor Dashboard (SSE)**:
   - Dedicated full-screen instructor monitor streaming live student webcam thumbnails and risk scores every 5 seconds.
   - Color-coded risk cards:
     - 🟢 **OK** (`< 40`)
     - 🟡 **Warning** (`40–69`)
     - 🔴 **High Risk** (`≥ 70` with pulsing danger glow)
   - Browser notification alerts when high-risk events occur.

4. **🛡️ Behavioural Deterrence & Anti-Cheat (ProctorLink concepts)**:
   - **Tab & Window Switch Detection**: Automatically warns student upon leaving the active exam window (`visibilitychange` / `blur`).
   - **Dynamic Watermark**: Translucent overlay displaying student name across the screen to deter secondary phone photography.
   - **Automated Submission**: Configurable violation threshold (e.g. 3 warnings) before quiz auto-submits.

5. **📋 Post-Exam Review Report**:
   - Sortable table of all students with snapshot count, max risk, and suspicious event totals.
   - Detailed visual gallery of every snapshot taken during the attempt with exact timestamps and risk levels.

---

## ⚙️ Configuration & Settings

### Global Settings (Site Administration → Plugins → Quiz → Access rules → Live Proctoring):
- **AI API URL**: Endpoint of your AI model (e.g. `https://ai.example.com/api/v1/proctor/analyze`).
- **AI API Key**: Bearer authentication token for your AI service.
- **AI Timeout**: Maximum seconds to wait for AI response (default: `10s`).

### Per-Quiz Settings (Quiz settings → Live Proctoring):
- **Enable Camera Proctoring**: Yes / No
- **Enable AI Analysis**: Yes / No
- **Snapshot Interval**: Time between webcam frames in seconds (default: 30)
- **Warning Limit**: Maximum warnings before auto-submission (default: 3)
- **Auto-Submit on Warning Limit**: Yes / No
- **Show Student Watermark**: Yes / No
- **Warn on Tab Switch**: Yes / No

---

## 🤖 Custom AI API Contract

The plugin posts a JSON payload to `{ai_api_url}`:

```json
{
  "image_base64": "data:image/jpeg;base64,/9j/4AAQSkZJRg...",
  "student_id": 42,
  "attempt_id": 105,
  "metadata": {
    "quiz_id": 12,
    "timestamp": 1729859036
  }
}
```

Expected response format:

```json
{
  "face_detected": true,
  "face_count": 1,
  "gaze_direction": "center",
  "suspicious": false,
  "risk_score": 15,
  "flags": [],
  "reason": "Single user looking forward"
}
```

---

## 📋 Changelog

### 🌟 v1.0.19 (Build `2026102610`) — 2026-10-26
- **WebSocket signaling server**: Deployed Node.js relay on production (port 8080).
- **Plugin integration**: Added `ws_url` setting; student/monitor connect via WebSocket for instant push (terminate, warning, start_stream, offer) with HTTP fallback.
- **Removed minified AMD builds** to ensure src changes take effect.

### 🌟 v1.0.18 (Build `2026102609`) — 2026-10-26
- **Latency improvements**: Reduced signal heartbeat (1s) and spotlight poll (500ms) for more responsive video/signaling (without full WebSocket).

### 🌟 v1.0.17 (Build `2026102608`) — 2026-10-26
- **TURN server (coturn)**: Installed & configured on production (150.230.241.37:3478) for reliable NAT traversal.
- **Plugin settings**: Added TURN URL/username/password (admin settings) wired into WebRTC iceServers (student + monitor).
- **Video quality**: Best available with TURN (relay) + STUN + 640px/30FPS.

### 🌟 v1.0.16 (Build `2026102607`) — 2026-10-26
- **Video quality improvements (Zoom-like):**
  - Default resolution raised to 640px width + 30 FPS frameRate.
  - ICE gathering fix: wait for gathering complete before sending offer (better NAT traversal).
  - Minified build updated accordingly.

### 🌟 v1.0.15 (Build `2026102606`) — 2026-10-26
- **Course-level bulk delete**: report.php now offers a CSRF-protected "Delete all snapshots (course)" button that purges file areas for all quiz modules in the course and deletes DB records.

### 🌟 v1.0.14 (Build `2026102605`) — 2026-10-26
- **Async AI queue**: Moved risk analysis to scheduled task `analyze_snapshots` (runs every 5 min) to avoid blocking student requests and improve scalability.
- **db/tasks.php**: Registered scheduled task.

### 🌟 v1.0.13 (Build `2026102604`) — 2026-10-26
- **Automated tests**: Added `tests/quizaccess_liveproctoring_test.php` (PHPUnit) covering DB tables, AI analyzer default result, and rule class loading.
- **Quality**: aligns with Moodle certified plugin automated testing support.

### 🌟 v1.0.12 (Build `2026102603`) — 2026-10-26
- **Identity Verification (vs Proctoring for Moodle):**
  - Implemented reference photo capture on first snapshot when `referencerequired` enabled.
  - Added `ai_analyzer::compare_faces()` (OpenAI vision) to verify identity on subsequent snapshots.
  - Logs `identity_mismatch` (high) and warns student on mismatch.
  - New quiz setting `liveproctoring_referencerequired` (DB column already present).
- **Enhancements:** v1.0.11 resolution + bulk delete retained.

### 🌟 v1.0.11 (Build `2026102602`) — 2026-10-26
- **Enhancements vs Proctoring for Moodle (BS23):**
  - **Configurable webcam resolution**: new quiz setting `liveproctoring_resolution` (width px) wired through PHP → AMD (src + minified build) into `getUserMedia` constraints.
  - **Bulk delete snapshots**: report.php now offers a CSRF-protected "Delete all snapshots" button (capability `deletesnapshots`) that purges Moodle file area + DB records.
  - **DB upgrade**: added `resolution` column to `quizaccess_liveproctoring_quiz` (upgrade step 2026102602).
- **Production hardening**: maintained v1.0.10 IDOR guards and install.xml repair.

### 🌟 v1.0.10 (Build `2026102601`) — 2026-10-26
- **Production Hardening & Cross-Module IDOR Defense (v1.0.10):**
  - **Fixed malformed `db/install.xml`**: Closed the unclosed `quizaccess_liveproctoring_refs` table definition (missing `</TABLE>` and indexes), which previously broke fresh plugin installation/upgrade on production LMS. All XMLDB tables now validate cleanly.
  - **Cross-Module IDOR Guards**: Added strict `cm->instance === quizid/attempt->quiz` binding checks in `push_snapshot.php`, `log_event.php`, and `live_signal.php` (teacher actions), preventing cross-quiz/cross-module snapshot/event/stream manipulation.
  - **Version Bump**: Production release v1.0.10.

### 🌟 v1.0.9 (Build `2026102510`) — 2026-10-25
- **SANAD Visual Identity & Zoom Virtual Hall Theming (`styles.css` & `amd/src/proctoring_monitor.js`):**
  - **SANAD Brand Palette Integration**: Overhauled the Zoom virtual exam hall with SANAD Academy's visual identity: Deep Executive Navy backdrop (`#183B56`, `#10273B`, `#0B1C2A`), Signature Terracotta branding (`#C4694A` with glow `rgba(196, 105, 74, 0.45)`), Sage Green status tones (`#5C7268`), and Warm Amber warning highlights (`#C89B3C`).
  - **Tajawal Typography**: Integrated Google Fonts Tajawal with full RTL/LTR hierarchy for crystal-clear readability.
  - **Frosted Glass Floating Dock**: Upgraded the Zoom bottom controls dock with SANAD terracotta action glows, glassmorphism blur, and animated badge indicators.
  - **Spotlight Stage & Modal Overhaul**: Re-themed the WebRTC live stage and dialog controls with terracotta accents and sleek navy cards.
- **GitHub Repository & Automated Moodle Plugin CI (`.github/workflows/ci.yml`):**
  - Published official GitHub repository under `engfeda-ui/moodle-quizaccess_liveproctoring`.
  - Configured Moodle Plugin CI workflow testing PHP 8.1, 8.2, and 8.3 across Moodle 4.5 Stable with automated linting, codesniffer, phpdoc, and validation checks.

### 🌟 v1.0.8 (Build `2026102509`) — 2026-10-25
- **OpenCode Multi-Agent Advisory Hardening & Scalability Suite:**
  - **Cross-Quiz IDOR Defense (`report.php`)**: Enforced strict binding validation `(int) $cm->instance === (int) $quiz->id` and target student record validation, preventing cross-quiz unauthorized snapshot disclosure.
  - **Database Index Optimization (`db/install.xml` & `db/upgrade.php`)**: Added composite indexes `idx_snaps_quiz_user (quizid, userid)` on `{quizaccess_liveproctoring_snaps}` and `idx_events_cmid_time (cmid, timecreated)` on `{quizaccess_liveproctoring_events}`, eliminating slow table scans and reducing dashboard query time from O(n²) to O(log n).
  - **Zero-Accumulation Memory Management (`live_feed.php`)**: Added explicit deallocation (`unset`) of per-loop result sets and active `gc_collect_cycles()` invocation, ensuring flat memory consumption during extended 300s SSE streaming sessions.

### 🌟 v1.0.7 (Build `2026102508`) — 2026-10-25
- **PHP Session Lock Release & Live Hall Active Attempt Filtering:**
  - **Zero-Block PHP Session Write-Close (`live_feed.php`)**: Added immediate `\core\session\manager::write_close();` in the long-running Server-Sent Events (SSE) feed loop. Fixes severe website lag and unblocks concurrent requests (e.g. `report.php`, student quiz navigation, and other LMS tabs) that were previously queued waiting for the 300s SSE session lock to release.
  - **2-Hour Active Window Exam Hall Filter (`live_feed.php`)**: Added dynamic time boundary (`qa.timemodified >= :activesince OR s.timecreated >= :activesince2`) where `$activesince = time() - (2 * 3600)`. Filters out stale/abandoned `inprogress` attempts from days/weeks ago, ensuring the Live Monitor strictly displays candidates actively engaged in the exam right now.
  - **Event Aggregation Window Alignment**: Scoped live proctoring events aggregation to the same 2-hour window (`timecreated >= :activesince`), preventing historical warnings on abandoned attempts from polluting current live stats.

### 🌟 v1.0.6 (Build `2026102507`) — 2026-10-25
- **Zoom Exam Hall Interface & On-Demand WebRTC Live Spotlight:**
  - **Zoom-Style Meeting Gallery UI (`templates/monitor_dashboard.mustache` & `styles.css`)**: Re-imagined instructor dashboard as a Zoom Virtual Exam Hall featuring sleek dark gallery tiles, a floating glassmorphism dock with candidates count, filtered views (All vs Flagged only), alert chime toggle, and one-click Fullscreen Hall mode.
  - **On-Demand WebRTC Live Spotlight (`amd/src/proctoring_monitor.js` & `live_signal.php`)**: Instructors can click "🎥 Live Spotlight" on any candidate card to open an instant live theatre stage with direct 30 FPS HD WebRTC audio/video streaming, backed by sub-second Turbo Frame fallback.
  - **Direct Proctor Controls**: Added direct actions inside the Spotlight view allowing instructors to flash instant custom warnings on the student's screen (`send_warning`) or immediately terminate and eject cheating candidates (`terminate_attempt`).
  - **Student Live Streaming Engine (`amd/src/proctoring_student.js`)**: Background signaling listener automatically negotiates WebRTC peer connections and sends rapid preview frames upon proctor request, while listening for direct warnings and termination signals.
  - **Web Audio Alert Chimes**: Added zero-dependency synthetic audio alerts ringing in the monitor when candidates trigger high-risk events (tab switches or camera closures).

### 🌟 v1.0.5 (Build `2026102506`) — 2026-10-25
- **Full-Screen Camera Hard Gate & Dynamic Behavioral Risk Scoring:**
  - **Full-Screen Unclosable Camera Gate (`amd/src/proctoring_student.js`)**: Implemented an impenetrable blocking gate (`#lp-camera-gate`, `z-index: 9999999`) completely covering all quiz questions until the student explicitly clicks "Enable Camera & Enter Exam" and camera access is granted. Questions cannot be viewed or answered without an active camera.
  - **Mid-Exam Camera Loss Reconnection & Ejection**: Added continuous video track liveness monitoring (`track.onended`, `track.onmute`, inactive stream). If the student stops or disables the camera mid-exam, the exam immediately locks with a 15-second countdown banner; failure to re-enable the camera automatically terminates and submits the attempt.
  - **Dynamic Behavioral Risk Calculation (`live_feed.php`)**: Aggregated events from `{quizaccess_liveproctoring_events}` in bulk to dynamically calculate risk:
    - 1 tab switch -> 35% Risk (Yellow Warning)
    - 2 tab switches -> 70% Risk (Red High Risk)
    - Camera blocked / denied -> 75% Risk (Red High Risk)
  - **Direct Status Badges on Student Cards (`proctoring_monitor.js`)**: Real-time display of behavioral status on student cards (e.g. `تبديل نافذة (1) ⚠️`, `الكاميرا محجوبة / مغلقة ❌`, `الوجه غير ظاهر ⚠️`) ensuring instructors immediately see the exact reason behind elevated risk scores.

### 🌟 v1.0.4 (Build `2026102505`) — 2026-10-25
- **Android Screen Overlay & Permission Tapjacking Mitigation:**
  - Deferred HTML overlay and watermark injection until AFTER `getUserMedia()` permission prompt resolution, preventing Android OS tapjacking security block (`يتعذر على الموقع طلب الإذن بسبب التراكمات`).
  - Added clean DOM state during browser permission requests for zero-friction tablet authorizations.

### 🌟 v1.0.3 (Build `2026102504`) — 2026-10-25
- **Production Minified AMD Build & Floating Webcam Preview:**
  - **AMD Asset Compilation (`amd/build/*.min.js`)**: Compiled minified assets `proctoring_student.min.js` and `proctoring_monitor.min.js` with sourcemaps using Terser, fixing Moodle production RequireJS 404 script loading failures.
  - **Floating Student Webcam Preview Box**: Integrated a responsive, non-intrusive floating camera box in the corner of the student's exam interface with a pulsing `● REC` badge and live mirror preview.
  - **Live Monitor In-Progress Attempt Streaming**: Refactored `live_feed.php` to immediately stream all active `inprogress` attempts from `{quiz_attempts}` (with `LEFT JOIN` on snapshots), eliminating empty monitor screens while students initialize.
  - **Clean Empty State & Card Removal**: Updated `proctoring_monitor.js` with a friendly "waiting for students" empty state and automated removal of cards when attempts finish.
  - **Pre-Attempt Styled Notice**: Enhanced student exam entry warning with an official styled notice card on `view.php`.

### 🌟 v1.0.2 (Build `2026102503`) — 2026-10-25
- **Moodle 4.4+ Access Manager SQL & Namespace Compatibility Hotfix:**
  - Resolved `HTTP ERROR 500` on quiz entry (`mod/quiz/view.php`) caused by `dml_exception: أنواع متفاوتة من معاملات استعلام sql!!`.
  - Refactored `get_settings_sql($quizid)` in `rule.php` to return query fragments `[$fields, $joins, $params]` rather than full SQL statements, preventing SQL parameter placeholder collisions with Moodle's `mod_quiz\access_manager`.
  - Added `use mod_quiz\quiz_settings;` and dynamic class aliases for full compatibility with Moodle 4.2+ through 4.5+ class loading architecture.
  - Optimized rule instantiation in `make()` to check pre-joined `$quiz->liveproctoring_enablecamera`, avoiding redundant database queries on non-proctored quizzes.

### 🌟 v1.0.1 (Build `2026102502`) — 2026-10-25
- **OpenCode & OpenRouter Multimodal Vision Integration:**
  - Added dual API mode in `ai_analyzer.php`: supports OpenAI/OpenRouter vision endpoints (`/chat/completions`) and legacy flat JSON endpoints.
  - Added configurable `ai_model` setting with default `openrouter/free` (multimodal auto-router).
  - Configured live API credentials on production LMS server and verified live webcam frame analysis from within `moodle-app`.
- **Harness Engineering & Multi-Agent Quality Gates:**
  - Added `tests/i18n_parity_test.php` verifying 100% string parity between EN and AR (56/56 keys green).
  - Added `tests/security_audit_test.php` scanning IDOR, CSRF tokens, teacher capability boundaries, and parameter types.
  - Added `classes/privacy/provider.php` for GDPR compliance.
  - Updated OpenCode multi-agent advisory matrix in `AGENTS.md` and `SKILL.md` following real-time catalog health audit.

### 🌟 v1.0.0 (Build `2026102501`) — 2026-10-25
- Initial production release combining best-of-breed proctoring mechanics:
  - Base access rule with quiz configuration form fields.
  - Periodic client webcam capture via AMD (`proctoring_student.js`).
  - Real-time teacher dashboard via Server-Sent Events (`live_feed.php` + `proctoring_monitor.js`).
  - Pluggable AI HTTP client (`ai_analyzer.php`).
  - Tab switch detection and warning dialog overlay.
  - Post-exam visual report (`report.php`) and secure file serving callback (`quizaccess_liveproctoring_pluginfile`).
