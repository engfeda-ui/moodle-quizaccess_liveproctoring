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
 * Admin settings for quizaccess_liveproctoring.
 *
 * @package   quizaccess_liveproctoring
 * @copyright 2026 Energy & Water Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings->add(new admin_setting_heading(
        'quizaccess_liveproctoring/globalheading',
        get_string('setting:globalheading', 'quizaccess_liveproctoring'),
        ''
    ));

    // AI API URL.
    $settings->add(new admin_setting_configtext(
        'quizaccess_liveproctoring/ai_api_url',
        get_string('setting:aiapiurl', 'quizaccess_liveproctoring'),
        get_string('setting:aiapiurldesc', 'quizaccess_liveproctoring'),
        '',
        PARAM_URL
    ));

    // AI API Key (password masked).
    $settings->add(new admin_setting_configpasswordunmask(
        'quizaccess_liveproctoring/ai_api_key',
        get_string('setting:aiapikey', 'quizaccess_liveproctoring'),
        get_string('setting:aiapidesc', 'quizaccess_liveproctoring'),
        '',
        PARAM_TEXT
    ));

    // AI Model ID (for OpenAI / OpenRouter endpoints).
    $settings->add(new admin_setting_configtext(
        'quizaccess_liveproctoring/ai_model',
        get_string('setting:aimodel', 'quizaccess_liveproctoring'),
        get_string('setting:aimodeldesc', 'quizaccess_liveproctoring'),
        'openrouter/free',
        PARAM_TEXT
    ));

    // AI timeout.
    $settings->add(new admin_setting_configtext(
        'quizaccess_liveproctoring/ai_timeout',
        get_string('setting:aitimeout', 'quizaccess_liveproctoring'),
        get_string('setting:aitimeoutdesc', 'quizaccess_liveproctoring'),
        15,
        PARAM_INT
    ));

    // TURN server URL.
    $settings->add(new admin_setting_configtext(
        'quizaccess_liveproctoring/turn_url',
        'TURN server URL',
        'TURN/STUN server URL (e.g. turn:150.230.241.37:3478).',
        'turn:150.230.241.37:3478',
        PARAM_TEXT
    ));
    // TURN username.
    $settings->add(new admin_setting_configtext(
        'quizaccess_liveproctoring/turn_username',
        'TURN username',
        'Username for TURN authentication.',
        'lpuser',
        PARAM_TEXT
    ));
    // TURN password.
    $settings->add(new admin_setting_configpasswordunmask(
        'quizaccess_liveproctoring/turn_password',
        'TURN password',
        'Password for TURN authentication.',
        'StrongPass123!',
        PARAM_TEXT
    ));
}
