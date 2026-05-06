<?php session_start();
if (!isset($_SESSION['user_id'])) {
    require_once("oj-header.php");
    echo "<a href='loginpage.php'>$MSG_Login</a>";
    require_once("oj-footer.php");
    exit(0);
}

require_once("include/check_post_key.php");
require_once("include/db_info.inc.php");
require_once("include/const.inc.php");
require_once("include/my_func.inc.php");
require_once("include/prompt_judge.inc.php");
require_once("include/deepseek_client.php");

function render_submit_error($message) {
    // This function renders a template from function scope.
    // Bring commonly used globals into scope so template/header logic
    // does not early-exit with a blank page.
    global $OJ_TEMPLATE, $view_errors, $mysqli, $OJ_NAME;
    $view_errors = $message;
    require("template/".$OJ_TEMPLATE."/error.php");
    exit(0);
}

function build_source_for_submit($problem_id, $language, $source_raw) {
    global $mysqli, $OJ_DATA, $OJ_APPENDCODE, $language_ext;

    $source_raw = preg_replace("/(\r\n)/", "\n", $source_raw);
    $source = $mysqli->real_escape_string($source_raw);

    $prepend_file = "$OJ_DATA/$problem_id/prepend.$language_ext[$language]";
    if (isset($OJ_APPENDCODE) && $OJ_APPENDCODE && file_exists($prepend_file)) {
        $source = $mysqli->real_escape_string(file_get_contents($prepend_file)."\n").$source;
    }

    $append_file = "$OJ_DATA/$problem_id/append.$language_ext[$language]";
    if (isset($OJ_APPENDCODE) && $OJ_APPENDCODE && file_exists($append_file)) {
        $source .= $mysqli->real_escape_string("\n".file_get_contents($append_file));
    }
    return $source;
}

function check_submit_interval_limit($user_id, $submit_interval_limit = 5) {
    global $mysqli;
    $now = strftime("%Y-%m-%d %X", time() - $submit_interval_limit);
    $sql = "SELECT `in_date` FROM `solution` WHERE `user_id`='$user_id' AND in_date>'$now' ORDER BY `in_date` DESC LIMIT 1";
    $res = $mysqli->query($sql);
    if ($res->num_rows > 0 && !HAS_PRI("enter_admin_page")) {
        $res->free();
        render_submit_error("You should not submit more than twice in $submit_interval_limit seconds<br>");
    }
    $res->free();
}

function check_prompt_submit_cooldown($user_id) {
    global $mysqli;
    if (HAS_PRI("enter_admin_page")) {
        return 0;
    }

    $user_id = $mysqli->real_escape_string($user_id);
    $seconds = intval(PROMPT_SUBMIT_COOLDOWN_SECONDS);
    $since = strftime("%Y-%m-%d %X", time() - $seconds);

    $sql = "SELECT `created_at` FROM `prompt_submission`
            WHERE `user_id`='$user_id'
              AND `created_at`>'$since'
            ORDER BY `created_at` DESC
            LIMIT 1";
    $res = $mysqli->query($sql);
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $last_time = strtotime($row['created_at']);
        $wait_seconds = max(1, $seconds - (time() - $last_time));
        $wait_minutes = intval(ceil($wait_seconds / 60));
        $res->free();
        return $wait_minutes;
    }
    if ($res) {
        $res->free();
    }
    return 0;
}

function acquire_prompt_submit_lock($user_id) {
    global $mysqli;
    $lock_name = 'prompt_submit_' . md5($user_id);
    $lock_name = $mysqli->real_escape_string($lock_name);
    $res = $mysqli->query("SELECT GET_LOCK('$lock_name', 5)");
    if (!$res) {
        return false;
    }
    $row = $res->fetch_array();
    $res->free();
    return isset($row[0]) && intval($row[0]) === 1;
}

function release_prompt_submit_lock($user_id) {
    global $mysqli;
    $lock_name = 'prompt_submit_' . md5($user_id);
    $lock_name = $mysqli->real_escape_string($lock_name);
    $mysqli->query("SELECT RELEASE_LOCK('$lock_name')");
}

function insert_solution_with_source($problem_id, $user_id, $language, $ip, $code_length, $source, $contest_id = null, $num = null) {
    global $mysqli;
    if ($contest_id === null || $num === null) {
        $sql = "INSERT INTO solution(problem_id,user_id,in_date,language,ip,code_length)
        VALUES('$problem_id','$user_id',NOW(),'$language','$ip','$code_length')";
    } else {
        $sql = "INSERT INTO solution(problem_id,user_id,in_date,language,ip,code_length,contest_id,num)
        VALUES('$problem_id','$user_id',NOW(),'$language','$ip','$code_length','$contest_id','$num')";
    }
    $mysqli->query($sql);
    $insert_id = $mysqli->insert_id;
    if (!$insert_id) {
        return 0;
    }
    $sql = "INSERT INTO `source_code`(`solution_id`,`source`)VALUES('$insert_id','$source')";
    $mysqli->query($sql);
    return intval($insert_id);
}

function update_prompt_submission_or_fail($prompt_submission_id, $data) {
    if (!prompt_submission_update($prompt_submission_id, $data)) {
        render_submit_error("Prompt submission update failed, please contact administrator.");
    }
}

function clear_status_cache($cid = null) {
    global $OJ_MEMCACHE, $OJ_SAE, $OJ_MEMSERVER, $OJ_MEMPORT;

    $statusURI = strstr($_SERVER['REQUEST_URI'], "submit", true)."status.php";
    if (isset($cid)) {
        $statusURI .= "?cid=$cid";
    }

    $sid = "";
    if (isset($_SESSION['user_id'])) {
        $sid .= session_id().$_SERVER['REMOTE_ADDR'];
    }
    if (isset($_SERVER["REQUEST_URI"])) {
        $sid .= $statusURI;
    }
    $sid = md5($sid);
    $file = "cache/cache_$sid.html";

    if ($OJ_MEMCACHE) {
        $mem = new Memcache;
        if ($OJ_SAE) {
            $mem = memcache_init();
        } else {
            $mem->connect($OJ_MEMSERVER, $OJ_MEMPORT);
        }
        $mem->delete($file, 0);
    } else if (file_exists($file)) {
        unlink($file);
    }
}

function redirect_after_submit($cid = null) {
    $statusURI = "status.php?user_id=".$_SESSION['user_id'];
    if (isset($cid)) {
        $statusURI .= "&cid=$cid";
    }
    $target = "/OJ/" . $statusURI;

    // Some legacy includes output whitespace before this point, which can
    // make header() redirection fail silently. Fall back to client redirect.
    if (!headers_sent()) {
        header("Location: $target");
        exit(0);
    }

    echo "<script>window.location.href='" . htmlspecialchars($target, ENT_QUOTES, "UTF-8") . "'</script>";
    echo "<noscript><meta http-equiv='refresh' content='0;url=" . htmlspecialchars($target, ENT_QUOTES, "UTF-8") . "'></noscript>";
    exit(0);
}

$now = strftime("%Y-%m-%d %H:%M",time());
$user_id = $_SESSION['user_id'];
$cid = null;
$pid = null;

if (isset($_POST['cid'])) {
    $pid = intval($_POST['pid']);
    $cid = intval($_POST['cid']);
    if ($cid <= 0 || $pid < 0) {
        render_submit_error("Problem Not Available!");
    }
    $sql="SELECT `problem_id` FROM `contest_problem`
    WHERE `num`='$pid' AND contest_id = $cid";
} else {
    $id = intval($_POST['id']);
    if ($id <= 0) {
        render_submit_error("Problem Not Available!");
    }
    if (HAS_PRI("see_hidden_".get_problemset($id)."_problem")) {
        $sql = "SELECT `problem_id` FROM `problem` WHERE `problem_id`='$id'";
    } else {
        $sql = <<<SQL
SELECT problem_id FROM `problem`
WHERE
`problem_id` = $id
AND `defunct`='N'
AND `problem_id` NOT IN (
    SELECT `problem_id` FROM `contest_problem`
    WHERE
    `contest_id` IN(
        SELECT `contest_id` FROM `contest`
        WHERE
        `end_time`> NOW()
        AND start_time < NOW()
        AND practice = 0
        )
    )
SQL;
    }
}

$res = $mysqli->query($sql) or die($mysqli->error);
if ($res->num_rows != 1){
    $res->free();
    render_submit_error("Problem Not Available!");
}
$res->free();

if (isset($_POST['id'])) {
    // out of contest
} else if (isset($_POST['pid']) && isset($_POST['cid'])) {
    $sql = "SELECT `private` FROM `contest` WHERE `contest_id`='$cid' AND `start_time`<='$now' AND `end_time`>'$now'";
    $result = $mysqli->query($sql) or die($mysqli->error);
    $rows_cnt = $result->num_rows;
    if ($rows_cnt != 1) {
        $result->free();
        render_submit_error("You Can't Submit Now Because Your are not invited by the contest or the contest is not running!");
    } else {
        $row = $result->fetch_array();
        $isprivate = intval($row[0]);
        $result->free();
        if ($isprivate == 1 && !isset($_SESSION['c'.$cid])) {
            $sql="SELECT count(*) FROM `privilege` WHERE `user_id`='$user_id' AND `rightstr`='c$cid'";
            $result=$mysqli->query($sql) or die ($mysqli->error);
            $row=$result->fetch_array();
            $ccnt=intval($row[0]);
            $result->free();
            if ($ccnt == 0 && !HAS_PRI("edit_contest")){
                render_submit_error("You are not invited!");
            }
        }
    }
    $sql = "SELECT `problem_id` FROM `contest_problem` WHERE `contest_id`='$cid' AND `num`='$pid'";
    $result = $mysqli->query($sql);
    $rows_cnt = $result->num_rows;
    if ($rows_cnt != 1){
        $result->free();
        render_submit_error("No Such Problem!");
    } else {
        $row = $result->fetch_object();
        $id = intval($row->problem_id);
        $result->free();
    }
} else {
    render_submit_error("No Such Problem!");
}

$problem_row = fetch_problem_for_prompt($id);
if (!$problem_row) {
    render_submit_error("Problem Not Available!");
}
$problem_type = isset($problem_row['problem_type']) ? intval($problem_row['problem_type']) : OJ_PROBLEM_TYPE_NORMAL;

$ip = $_SERVER['REMOTE_ADDR'];

if ($problem_type === OJ_PROBLEM_TYPE_PROMPT) {
    $prompt = isset($_POST['prompt']) ? trim($_POST['prompt']) : '';
    if (get_magic_quotes_gpc()) {
        $prompt = stripslashes($prompt);
    }
    if ($prompt === '') {
        render_submit_error("Prompt can not be empty!");
    }

    $normalized = normalize_prompt_and_get_length($prompt);
    $normalized_prompt = $normalized['normalized_prompt'];
    $prompt_length = intval($normalized['normalized_length']);
    if ($prompt_length <= 0) {
        render_submit_error("Prompt is invalid.");
    }
    if ($prompt_length > PROMPT_MAX_LENGTH) {
        render_submit_error("Prompt is too long! Maximum ".PROMPT_MAX_LENGTH." normalized units.");
    }

    if (!acquire_prompt_submit_lock($user_id)) {
        render_submit_error("Prompt submission is busy, please retry later.");
    }
    $wait_minutes = check_prompt_submit_cooldown($user_id);
    if ($wait_minutes > 0) {
        release_prompt_submit_lock($user_id);
        render_submit_error("&#x63D0;&#x793A;&#x8BCD;&#x9898;&#x578B;&#x6BCF; 5 &#x5206;&#x949F;&#x53EA;&#x80FD;&#x63D0;&#x4EA4; 1 &#x6B21;&#x3002;&#x8BF7;&#x7B49;&#x5F85; {$wait_minutes} &#x5206;&#x949F;&#x540E;&#x518D;&#x63D0;&#x4EA4;&#x3002;&#x63D0;&#x4EA4;&#x524D;&#x8BF7;&#x5148;&#x7528; AI &#x68C0;&#x67E5;&#x63D0;&#x793A;&#x8BCD;&#x662F;&#x5426;&#x5B8C;&#x6574;&#x3001;&#x53EF;&#x5B9E;&#x73B0;&#x3002;");
    }
    $prompt_submission_id = prompt_submission_insert(array(
        'solution_id' => null,
        'problem_id' => $id,
        'user_id' => $user_id,
        'contest_id' => $cid,
        'prompt' => $normalized_prompt,
        'prompt_length' => $prompt_length,
        'generated_code' => null,
        'deepseek_status' => 'PENDING',
        'deepseek_error' => null,
        'model_name' => null,
        'score' => 0,
    ));
    if (!$prompt_submission_id) {
        release_prompt_submit_lock($user_id);
        render_submit_error("Prompt submission failed, please retry.");
    }
    release_prompt_submit_lock($user_id);

    $messages = build_prompt_judge_messages($problem_row, $normalized_prompt);
    $deepseek_resp = deepseek_chat_completion($messages);
    if (!$deepseek_resp['ok']) {
        update_prompt_submission_or_fail($prompt_submission_id, array(
            'generated_code' => null,
            'deepseek_status' => 'FAILED',
            'deepseek_error' => $deepseek_resp['error'],
            'model_name' => $deepseek_resp['model'],
            'score' => 0,
        ));

        $msg = "DeepSeek code generation failed. Please try again later.";
        if (HAS_PRI("enter_admin_page")) {
            $msg .= "<br>Error: " . htmlentities($deepseek_resp['error'], ENT_QUOTES, "UTF-8");
        }
        render_submit_error($msg);
    }

    $extract = extract_generated_code($deepseek_resp['content']);
    if (!$extract['ok']) {
        update_prompt_submission_or_fail($prompt_submission_id, array(
            'generated_code' => null,
            'deepseek_status' => 'FAILED',
            'deepseek_error' => $extract['error'],
            'model_name' => $deepseek_resp['model'],
            'score' => 0,
        ));

        $msg = "DeepSeek code generation failed. Please try again later.";
        if (HAS_PRI("enter_admin_page")) {
            $msg .= "<br>Error: " . htmlentities($extract['error'], ENT_QUOTES, "UTF-8");
        }
        render_submit_error($msg);
    }

    $language = get_python3_language_id();
    if (!(($OJ_LANGMASK) & (1 << $language))) {
        update_prompt_submission_or_fail($prompt_submission_id, array(
            'generated_code' => null,
            'deepseek_status' => 'FAILED',
            'deepseek_error' => 'Python 3 is disabled by OJ_LANGMASK',
            'model_name' => $deepseek_resp['model'],
            'score' => 0,
        ));
        render_submit_error("Python 3 language is disabled by server config.");
    }

    $generated_code = $extract['code'];
    $source = build_source_for_submit($id, $language, $generated_code);
    $len = strlen($source);
    if ($len < 1) {
        update_prompt_submission_or_fail($prompt_submission_id, array(
            'generated_code' => $generated_code,
            'deepseek_status' => 'FAILED',
            'deepseek_error' => 'Generated code too short',
            'model_name' => $deepseek_resp['model'],
            'score' => 0,
        ));
        render_submit_error("Generated code too short!");
    }
    if ($len > 131072) {
        update_prompt_submission_or_fail($prompt_submission_id, array(
            'generated_code' => $generated_code,
            'deepseek_status' => 'FAILED',
            'deepseek_error' => 'Generated code too long',
            'model_name' => $deepseek_resp['model'],
            'score' => 0,
        ));
        render_submit_error("Generated code too long!");
    }
    setcookie('lastlang', $language, time()+360000);

    $insert_id = insert_solution_with_source($id, $user_id, $language, $ip, $len, $source, $cid, $pid);
    if (!$insert_id) {
        update_prompt_submission_or_fail($prompt_submission_id, array(
            'generated_code' => $generated_code,
            'deepseek_status' => 'FAILED',
            'deepseek_error' => 'Submit insert failed',
            'model_name' => $deepseek_resp['model'],
            'score' => 0,
        ));
        render_submit_error("Submit failed, please retry.");
    }

    update_prompt_submission_or_fail($prompt_submission_id, array(
        'solution_id' => $insert_id,
        'generated_code' => $generated_code,
        'deepseek_status' => 'SUCCESS',
        'deepseek_error' => null,
        'model_name' => $deepseek_resp['model'],
        'score' => 0,
    ));

    clear_status_cache($cid);
    redirect_after_submit($cid);
}

$language = intval($_POST['language']);
if ($language > count($language_name) || $language < 0) {
    $language = 0;
}
$language = strval($language);

$source = $_POST['source'];
$source = substr($source, 0, strlen($source) - 4);
$source = base64_decode($source);
$input_text = $_POST['input_text'];
if (get_magic_quotes_gpc()) {
    $source = stripslashes($source);
    $input_text = stripslashes($input_text);
}

$input_text = preg_replace("/(\r\n)/", "\n", $input_text);
$input_text = $mysqli->real_escape_string($input_text);
$source = build_source_for_submit($id, $language, $source);
$len = strlen($source);

setcookie('lastlang', $language, time()+360000);

if ($len < 1) {
    render_submit_error("Code too short!");
}
if ($len > 131072) {
    render_submit_error("Code too long!");
}

check_submit_interval_limit($user_id, 5);

if (($OJ_LANGMASK) & (1 << $language)) {
    $insert_id = insert_solution_with_source($id, $user_id, $language, $ip, $len, $source, $cid, $pid);
}

clear_status_cache($cid);
redirect_after_submit($cid);
?>
