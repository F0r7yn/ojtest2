<?php
  /**
   * This file is created
   * by yybird
   * @2016.03.23
   * last modified
   * by yybird
   * @2016.04.12
  **/
?>

<?php
    $title="Submit";
    if(isset($_GET['id']))
    require_once("header.php");
    else
    require_once("contest_header.php");
    require_once $_SERVER['DOCUMENT_ROOT']."/OJ/include/const.inc.php";
    $is_prompt_problem = isset($problem_type) && intval($problem_type) === 1;
    $show_prompt_submit_notice = $is_prompt_problem && !HAS_PRI("enter_admin_page");
?>

<div class="am-container" style="padding-top: 20px;">
  <?php
  if(isset($_GET['cid'])) {
      $title = "Submit solution: Problem {$PID[$pid]}";
  }
  else {
      $title = "Submit solution: Problem $id";
  }
  echo "<h1>$title</h1>"
  ?>
  <hr/>
  <div class="am-g">
    <form id="submit_form" action="/OJ/submit.php" method="post">
      <?php require_once $_SERVER["DOCUMENT_ROOT"]."/OJ/include/set_post_key.php" ?>
      <div class="am-u-md-10 am-u-md-centered">
        <?php if (!$is_prompt_problem): ?>
        <div class="am-g am-text-center" style="margin-bottom: 20px;">
          <div class="am-u-md-6">
            <label for="language">Language: </label>
            <select id="language" name="language" data-am-selected="{searchBox: 1, maxHeight: 400}">
                <?php
                $lang_count=count($language_ext);

                if(isset($contest_langmask))
                    $langmask=$contest_langmask;
                else
                    $langmask=$OJ_LANGMASK;

                $lang=((int)$langmask)&((1<<($lang_count))-1);

                if(isset($_COOKIE['lastlang'])) $lastlang=$_COOKIE['lastlang'];
                else $lastlang=0;
                for($i=0;$i<$lang_count;$i++){
                    $j = $language_order[$i];
                    if($lang&(1<<$j))
                        echo"<option value=$j ".( $lastlang==$j?"selected":"").">
                                ".$language_name[$j]."
                         </option>";
                }
                ?>
            </select>
          </div>
          <div class="m-u-md-6">
            <label for="language">Theme: </label>
            <select id="theme" name="theme" data-am-selected>
              <option value="xcode">Bright</option>
              <option value="monokai">Dark</option>
            </select>
          </div>
        </div>
        <div id="editor" style="wdith:100%; height: 500px; border: 1px solid #F0F0F0;"><?php if(isset($view_src))echo htmlentities($view_src); ?></div>
        <input type="hidden" id="source" name="source">
        <?php else: ?>
        <?php if ($show_prompt_submit_notice): ?>
        <div class="am-alert am-alert-danger" role="alert" style="font-size: 18px; font-weight: 700; text-align: center; white-space: nowrap; overflow-x: auto; border: 2px solid #dd514c; border-left-width: 8px; padding: 14px 18px; margin: 16px auto;">
          <i class="am-icon-exclamation-triangle"></i>
          <?php echo get_prompt_submit_notice(); ?>
        </div>
        <?php endif; ?>
        <div class="am-alert am-alert-warning">
          &#x672C;&#x9898;&#x63A5;&#x6536;&#x7684;&#x63D0;&#x793A;&#x8BCD;&#x5C06;&#x4F1A;&#x53D1;&#x9001;&#x7ED9; DeepSeek &#x751F;&#x6210;&#x5BF9;&#x5E94;&#x8BED;&#x8A00;&#x7684;&#x4EE3;&#x7801;&#xFF0C;&#x63D0;&#x793A;&#x8BCD;&#x8D8A;&#x7CBE;&#x70BC;&#x8D8A;&#x597D;&#x3002;
        </div>
        <div class="am-form-group">
          <label for="prompt">提示词</label>
          <textarea id="prompt" name="prompt" class="am-form-field" rows="12" maxlength="1000" required></textarea>
          <small>
            长度限制：1 - 1000 个规范化单位。规则：去首尾空白、连续空白合并为一个空格；
            中文按字符计数，英文按单词计数，数字按数值字面量计数。
            标准长度：<?php echo intval($prompt_standard_length); ?>。
          </small>
        </div>
        <?php endif; ?>
          <?php
          if(isset($_GET['cid'])) {
              echo "<input type='hidden' name='cid' value='$cid'>";
              echo "<input type='hidden' name='pid' value='$pid'>";
          }
          else {
              echo "<input type='hidden' name='id' value='$id'>";
          }
          ?>
      </div>
      <div class="am-g am-text-center" style="margin-top: 20px;">
        <button class="am-btn am-btn-success">Submit</button>
      </div>
    </form>
  </div>
</div>
<?php require_once("footer.php") ?>

<?php if (!$is_prompt_problem): ?>
<script src="/OJ/plugins/ace/ace.js" type="text/javascript" charset="utf-8"></script>
<script src="/OJ/plugins/ace/theme-xcode.js" type="text/javascript" charset="utf-8"></script>
<script src="/OJ/plugins/ace/theme-monokai.js" type="text/javascript" charset="utf-8"></script>
<script src="/OJ/plugins/ace/mode-c_cpp.js" type="text/javascript" charset="utf-8"></script>
<script src="/OJ/plugins/ace/mode-pascal.js" type="text/javascript" charset="utf-8"></script>
<script src="/OJ/plugins/ace/mode-java.js" type="text/javascript" charset="utf-8"></script>
<script src="/OJ/plugins/ace/mode-ruby.js" type="text/javascript" charset="utf-8"></script>
<script src="/OJ/plugins/ace/mode-batchfile.js" type="text/javascript" charset="utf-8"></script>
<script src="/OJ/plugins/ace/mode-python.js" type="text/javascript" charset="utf-8"></script>
<script src="/OJ/plugins/ace/mode-php.js" type="text/javascript" charset="utf-8"></script>
<script src="/OJ/plugins/ace/mode-perl.js" type="text/javascript" charset="utf-8"></script>
<script src="/OJ/plugins/ace/mode-csharp.js" type="text/javascript" charset="utf-8"></script>
<script src="/OJ/plugins/ace/mode-objectivec.js" type="text/javascript" charset="utf-8"></script>
<script src="/OJ/plugins/ace/mode-scheme.js" type="text/javascript" charset="utf-8"></script>
<script src="/OJ/plugins/ace/mode-lua.js" type="text/javascript" charset="utf-8"></script>
<script src="/OJ/plugins/ace/mode-javascript.js" type="text/javascript" charset="utf-8"></script>
<script src="/OJ/plugins/ace/mode-golang.js" type="text/javascript" charset="utf-8"></script>
<script src="/OJ/plugins/js-base64/base64.js" type="text/javascript" charset="utf-8"></script>

<script>
    language_mod = ["c_cpp","c_cpp","pascal","java","ruby","batchfile","python","php","perl","csharp","objectivec","plain_text","scheme","c_cpp","c_cpp","lua","javascript","golang","python", "c_cpp", "c_cpp", "c_cpp"];
    var editor = ace.edit("editor");
    var $obj_select_lang = $("#language");
    var lang = $obj_select_lang.val();
    editor.getSession().setMode("ace/mode/"+language_mod[lang]);
    editor.setTheme("ace/theme/xcode");
    $("#submit_form").submit(function () {
        var code = Base64.encode(editor.getValue());
        code = code + "HZNU";
        $("#source").val(code);
        return true;
    });

    $obj_select_lang.change(function () {
        lang = $(this).val();
        editor.getSession().setMode("ace/mode/"+language_mod[lang]);
    });

    $("#theme").change(function () {
        editor.setTheme("ace/theme/"+$(this).val());
    });
</script>
<?php endif; ?>
