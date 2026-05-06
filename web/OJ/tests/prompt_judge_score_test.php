<?php
require_once __DIR__ . '/../include/prompt_judge.inc.php';

function assert_equals($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

// normalize prompt length tests (rule compliance smoke tests)
$norm = normalize_prompt_and_get_length("  hello   world  ");
assert_equals(2, $norm['normalized_length'], 'English words count by word after trim/merge whitespace');

$norm = normalize_prompt_and_get_length("  12.5   7e-2  ");
assert_equals(2, $norm['normalized_length'], 'Numbers count by numeric literal');

$norm = normalize_prompt_and_get_length("  你好   世界 ");
assert_equals(4, $norm['normalized_length'], 'Chinese counts by character');

// scoring tests
$ac = get_ac_result_code();

// 1) non-AC, score = 60 * passed / total (pass_rate=0.5 => 30)
$score = calculate_prompt_score($ac + 1, 0.5, 200, 200);
assert_equals(30, $score, 'Non-AC score uses pass ratio * 60');

// 2) AC and now_length < standard_length => 100
$score = calculate_prompt_score($ac, 1.0, 100, 200);
assert_equals(100, $score, 'AC with shorter prompt should get 100');

// 3) AC and now_length == standard_length => 100
$score = calculate_prompt_score($ac, 1.0, 200, 200);
assert_equals(100, $score, 'AC with equal prompt length should get 100');

// 4) AC and now_length > standard_length => reduced by length reward
// 200/333 ~=0.6006 => round(...,1)=0.6 => reward=24 => total=84
$score = calculate_prompt_score($ac, 1.0, 333, 200);
assert_equals(84, $score, 'AC with longer prompt gets reduced score by length reward formula');

// 5) now_length == 0 / empty prompt abnormal
$score = calculate_prompt_score($ac, 1.0, 0, 200);
assert_equals(0, $score, 'Zero normalized length should score 0');

$norm = normalize_prompt_and_get_length(" \n\t  ");
assert_equals(0, $norm['normalized_length'], 'Empty prompt after normalization has length 0');

// 6) standard_length not configured uses default 200
$score = calculate_prompt_score($ac, 1.0, 800, 0);
// 200/800=0.25 => round(...,1)=0.3 => reward=12 => total=72
assert_equals(72, $score, 'Default standard_length=200 when not configured');

echo "All prompt judge score tests passed.\n";
