<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * PHPUnit tests for quizaccess_liveproctoring.
 *
 * @package   quizaccess_liveproctoring
 * @copyright 2026 Energy & Water Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/quiz/accessrule/liveproctoring/rule.php');

/**
 * Basic installation and sanity tests.
 */
class quizaccess_liveproctoring_testcase extends advanced_testcase {

    public function test_tables_exist() {
        global $DB;
        $dbman = $DB->get_manager();
        $this->assertTrue($dbman->table_exists('quizaccess_liveproctoring_snaps'));
        $this->assertTrue($dbman->table_exists('quizaccess_liveproctoring_events'));
        $this->assertTrue($dbman->table_exists('quizaccess_liveproctoring_quiz'));
        $this->assertTrue($dbman->table_exists('quizaccess_liveproctoring_refs'));
        $this->assertTrue($dbman->table_exists('quizaccess_liveproctoring_live'));
    }

    public function test_ai_analyzer_default_result() {
        $analyzer = new \quizaccess_liveproctoring\ai_analyzer();
        $result = $analyzer->analyze('not-base64', 1, 1, 1);
        $this->assertArrayHasKey('risk_score', $result);
        $this->assertIsInt($result['risk_score']);
    }

    public function test_access_rule_class_loads() {
        $this->assertTrue(class_exists('quizaccess_liveproctoring'));
    }
}