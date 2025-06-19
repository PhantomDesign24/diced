<?php
/*
 * 파일명: debug_preset_result.php
 * 위치: /game/debug_preset_result.php
 * 기능: 미리 결과 적용 문제 디버깅 도구
 * 작성일: 2025-06-12
 */

include_once(__DIR__ . '/../common.php');

// 관리자 권한 확인
if (!$is_admin) {
    alert('관리자만 접근 가능합니다.');
    goto_url('./index.php');
}

echo "<h2>🔍 미리 결과 적용 디버깅</h2>";
echo "<style>
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
.match { background-color: #d4edda; }
.mismatch { background-color: #f8d7da; }
.warning { background-color: #fff3cd; }
</style>";

// ===================================
// 1. 현재 상황 확인
// ===================================
echo "<h3>1. 현재 상황 확인</h3>";

$current_round = sql_fetch("SELECT * FROM dice_game_rounds WHERE status IN ('betting', 'waiting') ORDER BY round_number DESC LIMIT 1");
$next_round = $current_round ? $current_round['round_number'] + 1 : 1;

echo "<p><strong>현재 진행중 회차:</strong> " . ($current_round ? $current_round['round_number'] . "회차 (" . $current_round['status'] . ")" : "없음") . "</p>";
echo "<p><strong>다음 예정 회차:</strong> {$next_round}회차</p>";

// ===================================
// 2. 최근 5회차 비교
// ===================================
echo "<h3>2. 최근 5회차 실제 vs 미리 결과 비교</h3>";

$comparison_sql = "
    SELECT 
        r.round_number,
        r.dice1 as actual_dice1, r.dice2 as actual_dice2, r.dice3 as actual_dice3,
        r.total as actual_total, r.is_high as actual_high, r.is_odd as actual_odd,
        r.status, r.result_time,
        p.dice1 as preset_dice1, p.dice2 as preset_dice2, p.dice3 as preset_dice3,
        p.total as preset_total, p.is_high as preset_high, p.is_odd as preset_odd,
        p.is_used, p.used_at, p.created_by
    FROM dice_game_rounds r
    LEFT JOIN dice_game_pre_results p ON r.round_number = p.round_number
    WHERE r.status = 'completed'
    ORDER BY r.round_number DESC 
    LIMIT 5
";

$comparison_result = sql_query($comparison_sql);

echo "<table>";
echo "<tr><th>회차</th><th>실제 결과</th><th>미리 결과</th><th>일치</th><th>사용됨</th><th>사용시간</th><th>생성자</th></tr>";

while ($row = sql_fetch_array($comparison_result)) {
    $actual = $row['actual_dice1'] ? "{$row['actual_dice1']}-{$row['actual_dice2']}-{$row['actual_dice3']}" : "없음";
    $preset = $row['preset_dice1'] ? "{$row['preset_dice1']}-{$row['preset_dice2']}-{$row['preset_dice3']}" : "없음";
    
    $match = false;
    $class = '';
    $match_text = '';
    
    if ($row['preset_dice1'] && $row['actual_dice1']) {
        $match = ($row['actual_dice1'] == $row['preset_dice1'] && 
                 $row['actual_dice2'] == $row['preset_dice2'] && 
                 $row['actual_dice3'] == $row['preset_dice3']);
        $class = $match ? 'match' : 'mismatch';
        $match_text = $match ? '✅ 일치' : '❌ 불일치';
    } else if ($row['preset_dice1']) {
        $class = 'warning';
        $match_text = '⚠️ 실제결과없음';
    } else {
        $match_text = '📝 미리결과없음';
    }
    
    $used_text = $row['is_used'] ? '✅' : ($row['preset_dice1'] ? '❌' : '-');
    $used_at = $row['used_at'] ? date('H:i:s', strtotime($row['used_at'])) : '-';
    $creator = $row['created_by'] ?? '-';
    
    echo "<tr class='{$class}'>";
    echo "<td><strong>{$row['round_number']}</strong></td>";
    echo "<td>{$actual}</td>";
    echo "<td>{$preset}</td>";
    echo "<td>{$match_text}</td>";
    echo "<td>{$used_text}</td>";
    echo "<td>{$used_at}</td>";
    echo "<td>{$creator}</td>";
    echo "</tr>";
}

echo "</table>";

// ===================================
// 3. 미리 결과 조회 함수 테스트
// ===================================
echo "<h3>3. 미리 결과 조회 함수 테스트</h3>";

function testGetPresetResult($round_number) {
    echo "<h4>테스트: {$round_number}회차 미리 결과 조회</h4>";
    
    $sql = "SELECT * FROM dice_game_pre_results WHERE round_number = {$round_number} AND is_used = 0";
    echo "<p><strong>실행 쿼리:</strong> {$sql}</p>";
    
    $result = sql_fetch($sql);
    
    if ($result) {
        echo "<p class='match'>✅ 미리 결과 발견:</p>";
        echo "<ul>";
        echo "<li>주사위: {$result['dice1']}-{$result['dice2']}-{$result['dice3']}</li>";
        echo "<li>합계: {$result['total']}</li>";
        echo "<li>대소/홀짝: " . ($result['is_high'] ? '대' : '소') . "/" . ($result['is_odd'] ? '홀' : '짝') . "</li>";
        echo "<li>생성시간: {$result['created_at']}</li>";
        echo "<li>생성자: {$result['created_by']}</li>";
        echo "</ul>";
        return $result;
    } else {
        echo "<p class='mismatch'>❌ 미리 결과 없음</p>";
        
        // 혹시 사용된 결과가 있는지 확인
        $used_sql = "SELECT * FROM dice_game_pre_results WHERE round_number = {$round_number} AND is_used = 1";
        $used_result = sql_fetch($used_sql);
        
        if ($used_result) {
            echo "<p class='warning'>⚠️ 이미 사용된 미리 결과 발견:</p>";
            echo "<ul>";
            echo "<li>주사위: {$used_result['dice1']}-{$used_result['dice2']}-{$used_result['dice3']}</li>";
            echo "<li>사용시간: {$used_result['used_at']}</li>";
            echo "</ul>";
        } else {
            echo "<p>해당 회차의 미리 결과가 아예 없습니다.</p>";
        }
        
        return null;
    }
}

// 다음 회차 테스트
$test_result = testGetPresetResult($next_round);

// ===================================
// 4. 크론잡 코드에서 사용되는 방식 테스트
// ===================================
echo "<h3>4. 크론잡 방식 시뮬레이션</h3>";

function simulateCronProcess($round_number) {
    echo "<h4>시뮬레이션: {$round_number}회차 크론잡 처리</h4>";
    
    // 설정값 확인
    $use_pre_results = sql_fetch("SELECT config_value FROM dice_game_config WHERE config_key = 'use_pre_results'");
    $use_pre_value = $use_pre_results ? $use_pre_results['config_value'] : '0';
    
    echo "<p><strong>use_pre_results 설정:</strong> {$use_pre_value}</p>";
    
    if ($use_pre_value === '1') {
        echo "<p>✅ 미리 결과 사용 설정 활성화됨</p>";
        
        // 미리 결과 조회
        $preset_result = sql_fetch("SELECT * FROM dice_game_pre_results WHERE round_number = {$round_number} AND is_used = 0");
        
        if ($preset_result) {
            echo "<p class='match'>✅ 미리 결과 발견! 적용할 값:</p>";
            echo "<ul>";
            echo "<li>dice1 = {$preset_result['dice1']}</li>";
            echo "<li>dice2 = {$preset_result['dice2']}</li>";
            echo "<li>dice3 = {$preset_result['dice3']}</li>";
            echo "<li>total = {$preset_result['total']}</li>";
            echo "<li>is_high = {$preset_result['is_high']}</li>";
            echo "<li>is_odd = {$preset_result['is_odd']}</li>";
            echo "</ul>";
            
            return [
                'source' => '미리 설정된 결과',
                'dice1' => $preset_result['dice1'],
                'dice2' => $preset_result['dice2'],
                'dice3' => $preset_result['dice3'],
                'total' => $preset_result['total'],
                'is_high' => $preset_result['is_high'],
                'is_odd' => $preset_result['is_odd']
            ];
        } else {
            echo "<p class='mismatch'>❌ 미리 결과 없음 - 랜덤 생성됨</p>";
            
            $dice1 = rand(1, 6);
            $dice2 = rand(1, 6);
            $dice3 = rand(1, 6);
            $total = $dice1 + $dice2 + $dice3;
            $is_high = $total >= 11 ? 1 : 0;
            $is_odd = $total % 2 ? 1 : 0;
            
            echo "<p>랜덤 생성된 값: {$dice1}-{$dice2}-{$dice3}</p>";
            
            return [
                'source' => '랜덤 생성',
                'dice1' => $dice1,
                'dice2' => $dice2,
                'dice3' => $dice3,
                'total' => $total,
                'is_high' => $is_high,
                'is_odd' => $is_odd
            ];
        }
    } else {
        echo "<p class='warning'>⚠️ 미리 결과 사용 설정 비활성화됨</p>";
        
        $dice1 = rand(1, 6);
        $dice2 = rand(1, 6);
        $dice3 = rand(1, 6);
        $total = $dice1 + $dice2 + $dice3;
        $is_high = $total >= 11 ? 1 : 0;
        $is_odd = $total % 2 ? 1 : 0;
        
        echo "<p>랜덤 생성된 값: {$dice1}-{$dice2}-{$dice3}</p>";
        
        return [
            'source' => '랜덤 생성 (설정 비활성화)',
            'dice1' => $dice1,
            'dice2' => $dice2,
            'dice3' => $dice3,
            'total' => $total,
            'is_high' => $is_high,
            'is_odd' => $is_odd
        ];
    }
}

$simulation_result = simulateCronProcess($next_round);

// ===================================
// 5. 문제 진단 및 해결책
// ===================================
echo "<h3>5. 문제 진단 및 해결책</h3>";

$issues = [];

// 설정 확인
$use_pre_config = sql_fetch("SELECT config_value FROM dice_game_config WHERE config_key = 'use_pre_results'");
if (!$use_pre_config || $use_pre_config['config_value'] !== '1') {
    $issues[] = "use_pre_results 설정이 비활성화되어 있습니다.";
}

// 미리 결과 존재 확인
$next_preset = sql_fetch("SELECT * FROM dice_game_pre_results WHERE round_number = {$next_round} AND is_used = 0");
if (!$next_preset) {
    $issues[] = "다음 회차({$next_round})의 미리 결과가 없습니다.";
}

// 최근 회차에서 불일치 확인
$recent_mismatch = sql_fetch("
    SELECT COUNT(*) as mismatch_count
    FROM dice_game_rounds r
    LEFT JOIN dice_game_pre_results p ON r.round_number = p.round_number
    WHERE r.status = 'completed' 
    AND r.dice1 IS NOT NULL 
    AND p.dice1 IS NOT NULL
    AND (r.dice1 != p.dice1 OR r.dice2 != p.dice2 OR r.dice3 != p.dice3)
    AND r.round_number > (SELECT MAX(round_number) - 5 FROM dice_game_rounds)
");

if ($recent_mismatch && $recent_mismatch['mismatch_count'] > 0) {
    $issues[] = "최근 {$recent_mismatch['mismatch_count']}회차에서 미리 결과와 실제 결과가 불일치합니다.";
}

if (empty($issues)) {
    echo "<p class='match'>✅ 특별한 문제가 발견되지 않았습니다.</p>";
    echo "<p>다음 회차({$next_round})에서 미리 결과가 정상적으로 적용될 것으로 예상됩니다.</p>";
} else {
    echo "<p class='mismatch'>❌ 발견된 문제점들:</p>";
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li>{$issue}</li>";
    }
    echo "</ul>";
}

// ===================================
// 6. 즉시 해결 도구
// ===================================
echo "<h3>6. 즉시 해결 도구</h3>";

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action === 'enable_preset') {
        sql_query("UPDATE dice_game_config SET config_value = '1' WHERE config_key = 'use_pre_results'");
        echo "<p class='match'>✅ 미리 결과 사용이 활성화되었습니다.</p>";
    }
    
    if ($action === 'create_test_preset') {
        // 특별한 테스트 결과 생성 (6-6-6)
        sql_query("DELETE FROM dice_game_pre_results WHERE round_number = {$next_round}");
        sql_query("
            INSERT INTO dice_game_pre_results 
            (round_number, dice1, dice2, dice3, total, is_high, is_odd, estimated_time, created_at, created_by) 
            VALUES 
            ({$next_round}, 6, 6, 6, 18, 1, 0, NOW(), NOW(), 'debug_test')
        ");
        echo "<p class='match'>✅ {$next_round}회차에 테스트 결과(6-6-6) 생성완료!</p>";
    }
    
    if ($action === 'test_cron') {
        echo "<p class='warning'>⚠️ 크론잡 수동 실행 중...</p>";
        echo "<iframe src='./cron_game_manager_final.php?manual=1' width='100%' height='300' style='border:1px solid #ccc;'></iframe>";
    }
    
    echo "<script>setTimeout(function(){ location.href='debug_preset_result.php'; }, 3000);</script>";
}

echo "<p>";
echo "<a href='?action=enable_preset' style='background: green; color: white; padding: 10px; text-decoration: none; margin: 5px;'>✅ 미리결과 활성화</a> ";
echo "<a href='?action=create_test_preset' style='background: blue; color: white; padding: 10px; text-decoration: none; margin: 5px;'>🎯 테스트결과 생성</a> ";
echo "<a href='?action=test_cron' style='background: orange; color: white; padding: 10px; text-decoration: none; margin: 5px;'>🚀 크론잡 실행</a>";
echo "</p>";

echo "<h4>📝 다음 단계:</h4>";
echo "<ol>";
echo "<li><strong>미리결과 활성화</strong> 버튼 클릭</li>";
echo "<li><strong>테스트결과 생성</strong> 버튼 클릭 (6-6-6 결과 생성)</li>";
echo "<li><strong>크론잡 실행</strong> 버튼 클릭</li>";
echo "<li>다음 회차에서 <strong>6-6-6</strong> 결과가 나오는지 확인</li>";
echo "<li>만약 다른 결과가 나오면 크론잡 코드에 버그가 있는 것</li>";
echo "</ol>";

echo "<hr>";
echo "<p><a href='./diagnosis.php'>← 통합 진단으로 돌아가기</a></p>";
?>