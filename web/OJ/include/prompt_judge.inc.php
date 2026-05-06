<?php
/**
 * Prompt Judge helpers
 */

if (!defined('OJ_PROBLEM_TYPE_NORMAL')) {
    define('OJ_PROBLEM_TYPE_NORMAL', 0);
}
if (!defined('OJ_PROBLEM_TYPE_PROMPT')) {
    define('OJ_PROBLEM_TYPE_PROMPT', 1);
}

if (!defined('PROMPT_MAX_LENGTH')) {
    define('PROMPT_MAX_LENGTH', 1000);
}
if (!defined('PROMPT_STANDARD_LENGTH_DEFAULT')) {
    define('PROMPT_STANDARD_LENGTH_DEFAULT', 200);
}
if (!defined('PROMPT_SUBMIT_COOLDOWN_SECONDS')) {
    define('PROMPT_SUBMIT_COOLDOWN_SECONDS', 300);
}

function get_prompt_submit_notice() {
    return "&#x63D0;&#x793A;&#x8BCD;&#x9898;&#x578B;&#x6BCF; 5 &#x5206;&#x949F;&#x4EC5;&#x80FD;&#x63D0;&#x4EA4; 1 &#x6B21;&#x3002;&#x8BF7;&#x5148;&#x7528; AI &#x68C0;&#x67E5;&#x63D0;&#x793A;&#x8BCD;&#x662F;&#x5426;&#x5B8C;&#x6574;&#x3001;&#x53EF;&#x5B9E;&#x73B0;&#xFF0C;&#x518D;&#x63D0;&#x4EA4;&#x5230; OJ&#x3002;";
}

function problem_table_has_column($column_name) {
    global $mysqli;
    static $cache = array();

    $column = preg_replace('/[^A-Za-z0-9_]/', '', (string)$column_name);
    if ($column === '') {
        return false;
    }
    if (isset($cache[$column])) {
        return $cache[$column];
    }

    $sql = "SHOW COLUMNS FROM `problem` LIKE '" . $mysqli->real_escape_string($column) . "'";
    $res = $mysqli->query($sql);
    $exists = ($res && $res->num_rows > 0);
    if ($res) {
        $res->free();
    }

    $cache[$column] = $exists;
    return $exists;
}

function get_ac_result_code() {
    static $ac_code = null;
    if ($ac_code !== null) {
        return $ac_code;
    }

    global $judge_result, $MSG_Accepted;
    if (isset($judge_result) && is_array($judge_result) && isset($MSG_Accepted)) {
        foreach ($judge_result as $code => $text) {
            if ($text === $MSG_Accepted) {
                $ac_code = intval($code);
                return $ac_code;
            }
        }
    }

    $ac_code = 4;
    return $ac_code;
}

function get_python3_language_id() {
    global $language_name;
    if (isset($language_name) && is_array($language_name)) {
        foreach ($language_name as $idx => $name) {
            if (stripos($name, 'Python(3') !== false || stripos($name, 'Python3') !== false) {
                return intval($idx);
            }
        }
    }
    return 18;
}

function get_utf8_length($text) {
    if (function_exists('mb_strlen')) {
        return mb_strlen($text, 'UTF-8');
    }
    return strlen($text);
}

function get_prompt_standard_length($problem_row) {
    if (is_array($problem_row) && isset($problem_row['standard_length'])) {
        $val = intval($problem_row['standard_length']);
        if ($val > 0) {
            return $val;
        }
    }
    return PROMPT_STANDARD_LENGTH_DEFAULT;
}

function normalize_prompt_and_get_length($prompt) {
    $text = trim((string)$prompt);
    if ($text === '') {
        return array('normalized_prompt' => '', 'normalized_length' => 0);
    }

    // Merge all continuous whitespaces as one single space.
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim($text);
    if ($text === '') {
        return array('normalized_prompt' => '', 'normalized_length' => 0);
    }

    $length = 0;
    $tokens = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($tokens as $token) {
        // English words count as one.
        if (preg_match('/^[A-Za-z]+(?:[\'\-][A-Za-z]+)*$/u', $token)) {
            $length += 1;
            continue;
        }

        // Numbers (integer/decimal/scientific) count as one.
        if (preg_match('/^[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?$/u', $token)) {
            $length += 1;
            continue;
        }

        // Mixed token: scan left to right.
        $offset = 0;
        while ($offset < strlen($token)) {
            if (preg_match('/\G[A-Za-z]+(?:[\'\-][A-Za-z]+)*/u', $token, $m, 0, $offset)) {
                $length += 1;
                $offset += strlen($m[0]);
                continue;
            }
            if (preg_match('/\G[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?/u', $token, $m, 0, $offset)) {
                $length += 1;
                $offset += strlen($m[0]);
                continue;
            }

            // Fallback: count one Unicode character (CJK included) as one.
            if (preg_match('/\G./us', $token, $m, 0, $offset)) {
                $length += 1;
                $offset += strlen($m[0]);
                continue;
            }

            break;
        }
    }

    return array('normalized_prompt' => $text, 'normalized_length' => $length);
}

function get_prompt_passed_test_count($result, $pass_rate) {
    $ac_code = get_ac_result_code();
    if (intval($result) === intval($ac_code)) {
        return 1;
    }
    $ratio = floatval($pass_rate);
    if ($ratio <= 0) {
        return 0;
    }
    if ($ratio >= 1) {
        return 1;
    }
    return $ratio;
}

function calculate_prompt_score($result, $pass_rate, $now_length, $standard_length = PROMPT_STANDARD_LENGTH_DEFAULT) {
    $now_length = intval($now_length);
    $standard_length = intval($standard_length);
    if ($standard_length <= 0) {
        $standard_length = PROMPT_STANDARD_LENGTH_DEFAULT;
    }
    if ($now_length <= 0) {
        return 0;
    }

    $ac_code = get_ac_result_code();
    $is_ac = intval($result) === intval($ac_code);
    if (!$is_ac) {
        $passed_ratio = get_prompt_passed_test_count($result, $pass_rate);
        $score = 60.0 * $passed_ratio;
        $score = round($score, 6);
    } else {
        $ratio = $standard_length / $now_length;
        $ratio_rounded = round($ratio, 1);
        $length_reward = 40.0 * min(1.0, $ratio_rounded);
        $score = 60.0 + $length_reward;
    }

    if ($score < 0) {
        $score = 0;
    }
    if ($score > 100) {
        $score = 100;
    }
    return intval(round($score));
}

function extract_generated_code($content) {
    $text = trim((string)$content);
    if ($text === '') {
        return array('ok' => false, 'code' => '', 'error' => 'Generated content is empty');
    }

    if (preg_match('/```(?:python|py)?\s*([\s\S]*?)```/i', $text, $matches)) {
        $text = trim($matches[1]);
    } else if (preg_match('/```[\s\S]*?\n([\s\S]*?)```/i', $text, $matches)) {
        $text = trim($matches[1]);
    }

    $text = preg_replace('/^\s*```(?:python|py)?\s*/i', '', $text);
    $text = preg_replace('/\s*```\s*$/i', '', $text);
    $text = trim($text);

    $lines = preg_split("/\r\n|\r|\n/", $text);
    while (count($lines) > 0) {
        $line = trim($lines[0]);
        if ($line === '') {
            array_shift($lines);
            continue;
        }
        if (preg_match('/^(here|sure|below|code:|answer:)/i', $line)) {
            array_shift($lines);
            continue;
        }
        break;
    }
    while (count($lines) > 0) {
        $line = trim($lines[count($lines) - 1]);
        if ($line === '') {
            array_pop($lines);
            continue;
        }
        if (preg_match('/^(hope|explanation|note:|thanks)/i', $line)) {
            array_pop($lines);
            continue;
        }
        break;
    }
    $text = trim(implode("\n", $lines));

    if ($text === '') {
        return array('ok' => false, 'code' => '', 'error' => 'No valid Python code extracted');
    }
    return array('ok' => true, 'code' => $text, 'error' => '');
}

function fetch_problem_for_prompt($problem_id) {
    global $mysqli;
    $problem_id = intval($problem_id);
    $sql = "SELECT * FROM problem WHERE problem_id=$problem_id LIMIT 1";
    $res = $mysqli->query($sql);
    if (!$res || $res->num_rows === 0) {
        if ($res) {
            $res->free();
        }
        return null;
    }
    $row = $res->fetch_assoc();
    $res->free();

    $sample_sql = "SELECT input, output FROM problem_samples WHERE problem_id=$problem_id ORDER BY sample_id ASC LIMIT 1";
    $sample_res = $mysqli->query($sample_sql);
    if ($sample_res && $sample_res->num_rows > 0) {
        $sample = $sample_res->fetch_assoc();
        if (!isset($row['sample_input']) || $row['sample_input'] === null || $row['sample_input'] === '') {
            $row['sample_input'] = $sample['input'];
        }
        if (!isset($row['sample_output']) || $row['sample_output'] === null || $row['sample_output'] === '') {
            $row['sample_output'] = $sample['output'];
        }
    }
    if ($sample_res) {
        $sample_res->free();
    }
    if (!isset($row['sample_input'])) {
        $row['sample_input'] = '';
    }
    if (!isset($row['sample_output'])) {
        $row['sample_output'] = '';
    }
    return $row;
}

function build_prompt_judge_messages($problem_row, $student_prompt) {
    $system_prompt = <<<PROMPT
You are a competitive programming code generator.
You must output Python 3 code only.
Do not output explanations.
Do not output Markdown.
Do not use fenced code blocks.
The code must read from standard input and write to standard output.
Do not access the network.
Do not read or write files except standard input/output.
Do not use os.system, subprocess, eval, or exec.
You must follow the student's prompt as the only task specification.
Do not assume any hidden problem statement.
If the student's prompt is ambiguous, write your best-guess complete Python 3 program based only on the prompt text.
PROMPT;

    $user_prompt = <<<PROMPT
Student prompt:
{$student_prompt}

Generate a complete Python 3 program that follows the student prompt.
Output code only. No explanation.
PROMPT;

    return array(
        array(
            'role' => 'system',
            'content' => $system_prompt,
        ),
        array(
            'role' => 'user',
            'content' => $user_prompt,
        ),
    );
}

function prompt_submission_insert($data) {
    global $mysqli;
    $solution_id = isset($data['solution_id']) && $data['solution_id'] !== null ? intval($data['solution_id']) : null;
    $problem_id = intval($data['problem_id']);
    $user_id = $mysqli->real_escape_string(strval($data['user_id']));
    $contest_id = isset($data['contest_id']) && $data['contest_id'] !== null ? intval($data['contest_id']) : null;
    $prompt = $mysqli->real_escape_string(strval($data['prompt']));
    $prompt_length = intval($data['prompt_length']);
    $deepseek_status = $mysqli->real_escape_string(strval($data['deepseek_status']));
    $score = intval($data['score']);

    $generated_code_sql = "NULL";
    if (isset($data['generated_code']) && $data['generated_code'] !== null) {
        $generated_code_sql = "'" . $mysqli->real_escape_string(strval($data['generated_code'])) . "'";
    }
    $deepseek_error_sql = "NULL";
    if (isset($data['deepseek_error']) && $data['deepseek_error'] !== null) {
        $deepseek_error_sql = "'" . $mysqli->real_escape_string(strval($data['deepseek_error'])) . "'";
    }
    $model_name_sql = "NULL";
    if (isset($data['model_name']) && $data['model_name'] !== null) {
        $model_name_sql = "'" . $mysqli->real_escape_string(strval($data['model_name'])) . "'";
    }

    $solution_id_sql = $solution_id === null ? "NULL" : strval($solution_id);
    $contest_id_sql = $contest_id === null ? "NULL" : strval($contest_id);

    $sql = <<<SQL
INSERT INTO prompt_submission (
    solution_id,
    problem_id,
    user_id,
    contest_id,
    prompt,
    prompt_length,
    generated_code,
    deepseek_status,
    deepseek_error,
    model_name,
    score
) VALUES (
    $solution_id_sql,
    $problem_id,
    '$user_id',
    $contest_id_sql,
    '$prompt',
    $prompt_length,
    $generated_code_sql,
    '$deepseek_status',
    $deepseek_error_sql,
    $model_name_sql,
    $score
)
SQL;
    if (!$mysqli->query($sql)) {
        return 0;
    }
    return intval($mysqli->insert_id);
}

function prompt_submission_update($id, $data) {
    global $mysqli;
    $id = intval($id);
    if ($id <= 0) {
        return false;
    }

    $allowed_fields = array(
        'solution_id' => 'int_or_null',
        'generated_code' => 'string_or_null',
        'deepseek_status' => 'string',
        'deepseek_error' => 'string_or_null',
        'model_name' => 'string_or_null',
        'score' => 'int',
    );

    $sets = array();
    foreach ($allowed_fields as $field => $type) {
        if (!array_key_exists($field, $data)) {
            continue;
        }

        $value = $data[$field];
        if ($type === 'int') {
            $sets[] = "`$field`=" . intval($value);
        } else if ($type === 'int_or_null') {
            $sets[] = "`$field`=" . ($value === null ? "NULL" : intval($value));
        } else if ($type === 'string_or_null') {
            $sets[] = "`$field`=" . ($value === null ? "NULL" : "'" . $mysqli->real_escape_string(strval($value)) . "'");
        } else {
            $sets[] = "`$field`='" . $mysqli->real_escape_string(strval($value)) . "'";
        }
    }

    if (empty($sets)) {
        return true;
    }

    $sql = "UPDATE prompt_submission SET " . implode(", ", $sets) . " WHERE id=$id LIMIT 1";
    return $mysqli->query($sql);
}

function prompt_submission_get_by_solution_id($solution_id) {
    global $mysqli;
    $solution_id = intval($solution_id);
    $sql = "SELECT * FROM prompt_submission WHERE solution_id=$solution_id ORDER BY id DESC LIMIT 1";
    $res = $mysqli->query($sql);
    if (!$res || $res->num_rows === 0) {
        if ($res) {
            $res->free();
        }
        return null;
    }
    $row = $res->fetch_assoc();
    $res->free();
    return $row;
}

function prompt_submission_update_score($id, $score) {
    global $mysqli;
    $id = intval($id);
    $score = intval($score);
    $sql = "UPDATE prompt_submission SET score=$score WHERE id=$id LIMIT 1";
    return $mysqli->query($sql);
}
