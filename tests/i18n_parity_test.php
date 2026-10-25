<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Static verification test: Language pack parity between EN and AR.
 *
 * @package   quizaccess_liveproctoring
 * @copyright 2026 Energy & Water Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('MOODLE_INTERNAL', true);

$enfile = __DIR__ . '/../lang/en/quizaccess_liveproctoring.php';
$arfile = __DIR__ . '/../lang/ar/quizaccess_liveproctoring.php';

if (!file_exists($enfile) || !file_exists($arfile)) {
    echo "FAIL: Language files not found\n";
    exit(1);
}

// Load EN strings.
$string = [];
require($enfile);
$enstrings = $string;

// Load AR strings.
$string = [];
require($arfile);
$arstrings = $string;

$enkeys = array_keys($enstrings);
$arkeys = array_keys($arstrings);

$missinginar = array_diff($enkeys, $arkeys);
$missinginen = array_diff($arkeys, $enkeys);

$errors = 0;

if (!empty($missinginar)) {
    echo "ERROR: Keys missing in Arabic language pack:\n";
    foreach ($missinginar as $k) {
        echo "  - $k\n";
    }
    $errors++;
}

if (!empty($missinginen)) {
    echo "ERROR: Keys missing in English language pack:\n";
    foreach ($missinginen as $k) {
        echo "  - $k\n";
    }
    $errors++;
}

if ($errors === 0) {
    echo "SUCCESS: 100% Language Parity! Total keys verified: " . count($enkeys) . "\n";
    exit(0);
} else {
    exit(1);
}
