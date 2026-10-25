# 🎥 Live Proctoring with Custom AI (`quizaccess_liveproctoring`)

[![Moodle Plugin](https://img.shields.io/badge/Moodle-4.4+-orange.svg)](https://moodle.org)
[![Version](https://img.shields.io/badge/version-v1.0.0-blue.svg)](version.php)
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

### 🌟 v1.0.0 (Build `2026102501`) — 2026-10-25
- Initial production release combining best-of-breed proctoring mechanics:
  - Base access rule with quiz configuration form fields.
  - Periodic client webcam capture via AMD (`proctoring_student.js`).
  - Real-time teacher dashboard via Server-Sent Events (`live_feed.php` + `proctoring_monitor.js`).
  - Pluggable AI HTTP client (`ai_analyzer.php`).
  - Tab switch detection and warning dialog overlay.
  - Post-exam visual report (`report.php`) and secure file serving callback (`quizaccess_liveproctoring_pluginfile`).
