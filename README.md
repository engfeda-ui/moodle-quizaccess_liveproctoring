# 🎥 Live Proctoring with Custom AI (`quizaccess_liveproctoring`)

[![Moodle Plugin](https://img.shields.io/badge/Moodle-4.4+-orange.svg)](https://moodle.org)
[![Version](https://img.shields.io/badge/version-v1.0.4-blue.svg)](version.php)
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
