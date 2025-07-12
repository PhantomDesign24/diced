<?php
/*
 * 파일명: admin_debug.php
 * 위치: /game/admin_debug.php
 * 기능: admin.php 500 에러 디버깅
 * 작성일: 2025-06-12
 */

// 오류 표시 활성화
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Admin.php 디버깅</h2>";

echo "<h3>1. 그누보드 연결 테스트</h3>";
try {
    include_once(__DIR__ . '/../common.php');
    echo "✅ common.php 로드 성공<br>";
    echo "회원 정보: " . ($is_member ? "로그인됨 ({$member['mb_id']})" : "로그인 안됨") . "<br>";
    echo "관리자 권한: " . ($is_admin ? "관리자" : "일반회원") . "<br>";
} catch (Exception $e) {
    echo "❌ common.php 로드 실패: " . $e->getMessage() . "<br>";
    exit;
}

echo "<h3>2. 테이블 존재 확인</h3>";
$required_tables = [
    'dice_game_config',
    'payment_requests', 
    'payment_admin_accounts',
    'dice_game_rounds',
    'dice_game_bets'
];

foreach ($required_tables as $table) {
    $sql = "SHOW TABLES LIKE '{$table}'";
    $result = sql_query($sql);
    if (sql_num_rows($result) > 0) {
        echo "✅ {$table} 테이블 존재<br>";
    } else {
        echo "❌ {$table} 테이블 없음<br>";
    }
}

echo "<h3>3. 기본 함수 테스트</h3>";
try {
    function testGetConfigValue($key, $default = '') {
        $sql = "SELECT config_value FROM dice_game_config WHERE config_key = '{$key}'";
        $result = sql_fetch($sql);
        return $result ? $result['config_value'] : $default;
    }
    
    $test_config = testGetConfigValue('game_status', '1');
    echo "✅ getConfigValue 함수 작동: game_status = {$test_config}<br>";
} catch (Exception $e) {
    echo "❌ getConfigValue 함수 오류: " . $e->getMessage() . "<br>";
}

echo "<h3>4. 데이터베이스 조회 테스트</h3>";
try {
    $total_members = sql_fetch("SELECT COUNT(*) as cnt FROM {$g5['member_table']} WHERE mb_leave_date = ''")['cnt'] ?? 0;
    echo "✅ 회원 수 조회 성공: {$total_members}명<br>";
} catch (Exception $e) {
    echo "❌ 회원 수 조회 실패: " . $e->getMessage() . "<br>";
}

try {
    $admin_account = sql_fetch("SELECT * FROM payment_admin_accounts WHERE is_active = 1 ORDER BY display_order ASC LIMIT 1");
    if ($admin_account) {
        echo "✅ 관리자 계좌 조회 성공: {$admin_account['bank_name']}<br>";
    } else {
        echo "⚠️ 관리자 계좌 없음<br>";
    }
} catch (Exception $e) {
    echo "❌ 관리자 계좌 조회 실패: " . $e->getMessage() . "<br>";
}

echo "<h3>5. POST 데이터 테스트</h3>";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "POST 데이터:<br>";
    foreach ($_POST as $key => $value) {
        echo "{$key}: " . htmlspecialchars($value) . "<br>";
    }
} else {
    echo "GET 요청<br>";
}

echo "<h3>6. 메모리 및 시간 제한</h3>";
echo "메모리 제한: " . ini_get('memory_limit') . "<br>";
echo "실행 시간 제한: " . ini_get('max_execution_time') . "초<br>";
echo "현재 메모리 사용량: " . round(memory_get_usage() / 1024 / 1024, 2) . "MB<br>";

echo "<h3>7. 간단한 admin.php 로드 테스트</h3>";
echo '<form method="post">';
echo '<input type="hidden" name="test" value="1">';
echo '<button type="submit">POST 테스트</button>';
echo '</form>';

if (isset($_POST['test'])) {
    echo "✅ POST 처리 성공<br>";
}

echo "<br><strong>결과:</strong> 위 모든 항목이 ✅ 이면 admin.php가 정상 작동해야 합니다.<br>";
echo "❌ 항목이 있다면 해당 부분을 먼저 해결해야 합니다.<br>";
?>

<h3>8. 오류 로그 확인</h3>
<p>서버 오류 로그를 확인하여 정확한 500 에러 원인을 파악하세요:</p>
<ul>
    <li>Apache: /var/log/apache2/error.log</li>
    <li>Nginx: /var/log/nginx/error.log</li>
    <li>PHP: /var/log/php_errors.log</li>
</ul>

<h3>9. 다음 단계</h3>
<p>이 디버깅 결과를 알려주시면 정확한 해결방법을 제시하겠습니다.</p>