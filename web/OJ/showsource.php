<?php
/**
* This file is modified
* by yybird
* @2016.05.26
**/
$title = "Show Source Code";
$cache_time=90;
$OJ_CACHE_SHARE=false;
require_once('./include/cache_start.php');
require_once('./include/db_info.inc.php');
require_once('./include/setlang.php');
require_once("./include/my_func.inc.php");
require_once("./include/prompt_judge.inc.php");
$view_title= "Source Code"; 
require_once("./include/const.inc.php");
if (!isset($_GET['id'])){
    $view_errors= "No such code!\n";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

/* 获取solution信息 start */
$sid=strval(intval($_GET['id']));
$sql="SELECT * FROM `solution` WHERE `solution_id`='".$sid."'";
$result=$mysqli->query($sql);
$row = null;
if ($result && $result->num_rows > 0) {
    $row=$result->fetch_object();
}
if (!$row) {
    if ($result) {
        $result->free();
    }
    $view_errors = "No such code!";
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}
$slanguage=$row->language;
$sresult=$row->result;
$spass_rate = isset($row->pass_rate) ? floatval($row->pass_rate) : 0.0;
$stime=$row->time;
$smemory=$row->memory;
$view_user_id=$suser_id=$row->user_id;
$pid = $row->problem_id;
$cid = $row->contest_id;
$num = $row->num;
$result->free();
if($cid) {
    $sql = "SELECT COUNT(1) FROM team WHERE contest_id=$cid AND user_id='$suser_id'";
    $is_temp_user = $mysqli->query($sql)->fetch_array()[0];
}
/* 获取solution信息 end */

$ok = canSeeSource($sid);

$view_source="No source code available!";

$sql="SELECT `source` FROM `source_code` WHERE `solution_id`=".$sid;
$result=$mysqli->query($sql);
$row=$result->fetch_object();
if($row) $view_source=$row->source;
if($result) $result->free();

$prompt_submission = prompt_submission_get_by_solution_id($sid);
$show_prompt_submission = false;
$is_prompt_judge_submission = false;
if ($prompt_submission) {
    $is_prompt_judge_submission = true;
    $is_owner = isset($_SESSION['user_id']) && strtolower($_SESSION['user_id']) == strtolower($suser_id);
    if ($is_owner || HAS_PRI("enter_admin_page")) {
        $show_prompt_submission = true;
    }

    if (isset($prompt_submission['id']) && isset($prompt_submission['prompt'])) {
        $problem_sql = "SELECT standard_length FROM problem WHERE problem_id=" . intval($pid) . " LIMIT 1";
        $problem_res = null;
        if (problem_table_has_column('standard_length')) {
            $problem_res = $mysqli->query($problem_sql);
        }
        $problem_row = null;
        if ($problem_res && $problem_res->num_rows > 0) {
            $problem_row = $problem_res->fetch_assoc();
        }
        if ($problem_res) {
            $problem_res->free();
        }

        $standard_length = get_prompt_standard_length($problem_row);
        $now_length = isset($prompt_submission['prompt_length']) ? intval($prompt_submission['prompt_length']) : 0;
        if ($now_length <= 0) {
            $normalized = normalize_prompt_and_get_length($prompt_submission['prompt']);
            $now_length = intval($normalized['normalized_length']);
        }

        $new_score = calculate_prompt_score(
            intval($sresult),
            floatval($spass_rate),
            $now_length,
            $standard_length
        );
        if (intval($prompt_submission['score']) !== intval($new_score)) {
            prompt_submission_update_score($prompt_submission['id'], $new_score);
            $prompt_submission['score'] = $new_score;
        }
        $prompt_submission['problem_standard_length'] = $standard_length;
        $prompt_submission['prompt_length'] = $now_length;
    }
}

/////////////////////////Template
require("template/".$OJ_TEMPLATE."/showsource.php");
/////////////////////////Common foot
if(file_exists('./include/cache_end.php'))
    require_once('./include/cache_end.php');
?>

