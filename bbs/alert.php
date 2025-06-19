<?php
global $lo_location;
global $lo_url;
include_once('./_common.php');
if ($error) {
    $g5['title'] = "오류안내 페이지";
} else {
    $g5['title'] = "결과안내 페이지";
}
include_once(G5_PATH . '/head.sub.php');
$msg = isset($msg) ? strip_tags($msg) : '올바른 방법으로 이용해 주십시오.';
$msg = htmlspecialchars($msg, ENT_QUOTES);
$msg = str_replace("\n", "<br>", $msg); // 줄바꿈 문자를 HTML <br>로 변경
$url = isset($url) ? clean_xss_tags($url, 1) : '';
if (!$url) $url = isset($_SERVER['HTTP_REFERER']) ? clean_xss_tags($_SERVER['HTTP_REFERER'], 1) : '';
$url = preg_replace("/[\<\>\'\"\\\'\\\"\(\)]/", "", $url);
$url = preg_replace('/\r\n|\r|\n|[^\x20-\x7e]/', '', $url);
// url 체크
check_url_host($url, $msg);
if ($error) {
    $icon = "error";
    $title = "오류 안내";
} else {
    $icon = "success";
    $title = "알림";
}
// SweetAlert2 라이브러리 추가
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Swal.fire({
        icon: '<?php echo $icon; ?>',
        title: '<?php echo $title; ?>',
        html: '<?php echo $msg; ?>',
        confirmButtonText: '확인'
    }).then(function() {
        <?php if ($url) { ?>
        location.href = '<?php echo str_replace('&amp;', '&', $url); ?>';
        <?php } else { ?>
        history.back();
        <?php } ?>
    });
});
</script>
<noscript>
<div id="validation_check">
    <h1><?php echo ($error ? "다음 항목에 오류가 있습니다." : "다음 내용을 확인해 주세요."); ?></h1>
    <p class="cbg">
        <?php echo nl2br(htmlspecialchars($msg, ENT_QUOTES)); ?>
    </p>
    <?php if ($post) { ?>
    <form method="post" action="<?php echo $url ?>">
    <?php
    foreach ($_POST as $key => $value) {
        $key = clean_xss_tags($key);
        $value = clean_xss_tags($value);
        if (strlen($value) < 1)
            continue;
        if (preg_match("/pass|pwd|capt|url/", $key))
            continue;
    ?>
    <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
    <?php
    }
    ?>
    <input type="submit" value="돌아가기">
    </form>
    <?php } else { ?>
    <div class="btn_confirm">
        <a href="<?php echo $url ?>">돌아가기</a>
    </div>
    <?php } ?>
</div>
</noscript>
<?php
include_once(G5_PATH . '/tail.sub.php');
?>