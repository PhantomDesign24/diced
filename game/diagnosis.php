<?php
/*
 * 파일명: diagnosis.php
 * 위치: /game/diagnosis.php
 * 기능: 주사위 게임 + 미리 결과 시스템 진단 도구 (업데이트)
 * 작성일: 2025-06-12
 * 수정일: 2025-06-12 (미리 결과 시스템 진단 기능 추가)
 */

// ===================================
// 그누보드 환경 설정
// ===================================
include_once('./../common.php');

// 관리자만 접근 가능
if (!$is_admin) {
    alert('관리자만 접근할 수 있습니다.', G5_URL);
}

/* 설정값 조회 함수 */
function getGameConfig($key, $default = '') {
    $sql = "SELECT config_value FROM dice_game_config WHERE config_key = '{$key}'";
    $result = sql_fetch($sql);
    return $result ? $result['config_value'] : $default;
}

echo "<h2>🔍 주사위 게임 통합 진단 도구</h2>";
echo "<style>
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
.error { color: red; font-weight: bold; }
.success { color: green; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
.info { color: blue; font-weight: bold; }
.section { background: #f8f9fa; padding: 15px; margin: 20px 0; border-left: 4px solid #007bff; }
.pre-result-match { background: #d4edda; }
.pre-result-mismatch { background: #f8d7da; }
</style>";

// ===================================
// 0. 시스템 상태 요약
// ===================================
echo "<div class='section'>";
echo "<h3>📊 시스템 상태 요약</h3>";

$use_pre_results = getGameConfig('use_pre_results', '0');
$game_status = getGameConfig('game_status', '1');

echo "<table>";
echo "<tr><th>항목</th><th>상태</th><th>설명</th></tr>";
echo "<tr><td>게임 상태</td><td class='" . ($game_status === '1' ? 'success' : 'error') . "'>" . ($game_status === '1' ? '✅ 활성' : '❌ 비활성') . "</td><td>전체 게임 시스템</td></tr>";
echo "<tr><td>미리 결과 사용</td><td class='" . ($use_pre_results === '1' ? 'info' : 'warning') . "'>" . ($use_pre_results === '1' ? '🎯 사용중' : '🎲 랜덤') . "</td><td>결과 생성 방식</td></tr>";
echo "<tr><td>현재 시간</td><td class='info'>" . date('Y-m-d H:i:s') . "</td><td>서버 시간</td></tr>";
echo "</table>";
echo "</div>";

// ===================================
// 1. 현재 회차 상태 확인
// ===================================
echo "<div class='section'>";
echo "<h3>1. 현재 회차 상태</h3>";
$current_round_sql = "SELECT * FROM dice_game_rounds WHERE status IN ('betting', 'waiting', 'completed') ORDER BY round_number DESC LIMIT 5";
$current_rounds = sql_query($current_round_sql);

echo "<table>";
echo "<tr><th>회차</th><th>상태</th><th>주사위결과</th><th>대소/홀짝</th><th>시작시간</th><th>마감시간</th><th>결과시간</th></tr>";
while ($round = sql_fetch_array($current_rounds)) {
    $dice_result = $round['dice1'] ? "{$round['dice1']}-{$round['dice2']}-{$round['dice3']} = {$round['total']}" : "미확정";
    $result_type = $round['dice1'] ? (($round['is_high'] ? '대' : '소') . '/' . ($round['is_odd'] ? '홀' : '짝')) : "-";
    
    echo "<tr>";
    echo "<td><strong>{$round['round_number']}</strong></td>";
    echo "<td class='" . ($round['status'] == 'completed' ? 'success' : 'warning') . "'>{$round['status']}</td>";
    echo "<td>{$dice_result}</td>";
    echo "<td>{$result_type}</td>";
    echo "<td>" . date('H:i:s', strtotime($round['start_time'])) . "</td>";
    echo "<td>" . date('H:i:s', strtotime($round['end_time'])) . "</td>";
    echo "<td>" . ($round['result_time'] ? date('H:i:s', strtotime($round['result_time'])) : '-') . "</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// ===================================
// 2. 미리 결과 시스템 상태 (신규)
// ===================================
echo "<div class='section'>";
echo "<h3>2. 🎯 미리 결과 시스템 상태</h3>";

// 미리 결과 설정 확인
$pre_configs = array();
$pre_config_keys = array('use_pre_results', 'auto_generate_results', 'pre_result_buffer');
foreach ($pre_config_keys as $key) {
    $pre_configs[$key] = getGameConfig($key, '미설정');
}

echo "<h4>2-1. 설정값</h4>";
echo "<table>";
echo "<tr><th>설정</th><th>값</th><th>상태</th></tr>";
echo "<tr><td>미리 결과 사용</td><td>{$pre_configs['use_pre_results']}</td><td class='" . ($pre_configs['use_pre_results'] === '1' ? 'info' : 'warning') . "'>" . ($pre_configs['use_pre_results'] === '1' ? '✅ 활성' : '❌ 비활성') . "</td></tr>";
echo "<tr><td>자동 생성</td><td>{$pre_configs['auto_generate_results']}</td><td class='" . ($pre_configs['auto_generate_results'] === '1' ? 'success' : 'warning') . "'>" . ($pre_configs['auto_generate_results'] === '1' ? '✅ 활성' : '❌ 비활성') . "</td></tr>";
echo "<tr><td>버퍼 개수</td><td>{$pre_configs['pre_result_buffer']}</td><td>-</td></tr>";
echo "</table>";

// 미리 결과 개수 확인
$current_round_info = sql_fetch("SELECT round_number FROM dice_game_rounds WHERE status IN ('betting', 'waiting') ORDER BY round_number DESC LIMIT 1");
$next_round = $current_round_info ? $current_round_info['round_number'] + 1 : 1;

$pre_result_count = sql_fetch("SELECT COUNT(*) as cnt FROM dice_game_pre_results WHERE round_number >= {$next_round} AND is_used = 0")['cnt'] ?? 0;

echo "<h4>2-2. 미리 결과 현황</h4>";
echo "<table>";
echo "<tr><th>항목</th><th>값</th><th>상태</th></tr>";
echo "<tr><td>다음 회차</td><td>{$next_round}</td><td>-</td></tr>";
echo "<tr><td>준비된 결과 수</td><td>{$pre_result_count}개</td><td class='" . ($pre_result_count >= 10 ? 'success' : ($pre_result_count > 0 ? 'warning' : 'error')) . "'>" . ($pre_result_count >= 10 ? '✅ 충분' : ($pre_result_count > 0 ? '⚠️ 부족' : '❌ 없음')) . "</td></tr>";
echo "</table>";

// 최근 회차에서 미리 결과 사용 여부 확인
echo "<h4>2-3. 최근 회차 미리 결과 사용 확인</h4>";
$pre_result_usage_sql = "
    SELECT r.round_number, r.dice1, r.dice2, r.dice3, r.status,
           p.round_number as pre_round, p.dice1 as pre_dice1, p.dice2 as pre_dice2, p.dice3 as pre_dice3,
           p.is_used, p.used_at, p.created_by
    FROM dice_game_rounds r
    LEFT JOIN dice_game_pre_results p ON r.round_number = p.round_number
    WHERE r.status = 'completed'
    ORDER BY r.round_number DESC 
    LIMIT 5
";
$pre_usage_result = sql_query($pre_result_usage_sql);

echo "<table>";
echo "<tr><th>회차</th><th>실제 결과</th><th>미리 결과</th><th>일치</th><th>사용됨</th><th>생성자</th></tr>";
while ($usage = sql_fetch_array($pre_usage_result)) {
    $actual = $usage['dice1'] ? "{$usage['dice1']}-{$usage['dice2']}-{$usage['dice3']}" : "없음";
    $preset = $usage['pre_dice1'] ? "{$usage['pre_dice1']}-{$usage['pre_dice2']}-{$usage['pre_dice3']}" : "없음";
    
    $match = false;
    $match_class = '';
    $match_text = '';
    
    if ($usage['pre_dice1']) {
        $match = ($usage['dice1'] == $usage['pre_dice1'] && $usage['dice2'] == $usage['pre_dice2'] && $usage['dice3'] == $usage['pre_dice3']);
        $match_class = $match ? 'pre-result-match' : 'pre-result-mismatch';
        $match_text = $match ? '✅ 일치' : '❌ 불일치';
    } else {
        $match_text = '-';
    }
    
    $used_text = $usage['is_used'] ? '✅' : ($usage['pre_dice1'] ? '❌' : '-');
    $creator = $usage['created_by'] ?? '-';
    
    echo "<tr class='{$match_class}'>";
    echo "<td><strong>{$usage['round_number']}</strong></td>";
    echo "<td>{$actual}</td>";
    echo "<td>{$preset}</td>";
    echo "<td>{$match_text}</td>";
    echo "<td>{$used_text}</td>";
    echo "<td>{$creator}</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// ===================================
// 3. 베팅 처리 상태 확인
// ===================================
echo "<div class='section'>";
echo "<h3>3. 베팅 처리 상태</h3>";
$recent_bets_sql = "
    SELECT b.*, r.status as round_status, r.dice1, r.dice2, r.dice3
    FROM dice_game_bets b 
    LEFT JOIN dice_game_rounds r ON b.round_id = r.round_id 
    ORDER BY b.created_at DESC 
    LIMIT 10
";
$recent_bets = sql_query($recent_bets_sql);

echo "<table>";
echo "<tr><th>회차</th><th>회원ID</th><th>베팅</th><th>금액</th><th>당첨여부</th><th>당첨금</th><th>처리시간</th><th>회차상태</th></tr>";
while ($bet = sql_fetch_array($recent_bets)) {
    $bet_text = $bet['bet_high_low'] . '/' . $bet['bet_odd_even'];
    $is_win_text = $bet['is_win'] === null ? '미처리' : ($bet['is_win'] ? '당첨' : '미당첨');
    $win_class = $bet['is_win'] === null ? 'warning' : ($bet['is_win'] ? 'success' : 'error');
    
    echo "<tr>";
    echo "<td>{$bet['round_number']}</td>";
    echo "<td>{$bet['mb_id']}</td>";
    echo "<td>{$bet_text}</td>";
    echo "<td>" . number_format($bet['bet_amount']) . "P</td>";
    echo "<td class='{$win_class}'>{$is_win_text}</td>";
    echo "<td>" . number_format($bet['win_amount']) . "P</td>";
    echo "<td>" . ($bet['processed_at'] ? date('H:i:s', strtotime($bet['processed_at'])) : '-') . "</td>";
    echo "<td>{$bet['round_status']}</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// ===================================
// 4. 크론잡 로그 확인 (업데이트)
// ===================================
echo "<div class='section'>";
echo "<h3>4. 크론잡 로그 상태</h3>";

// 기존 로그와 새 로그 모두 확인
$log_files = array(
    'game_cron.log' => '기존 크론잡',
    'game_cron_pre_results.log' => '미리결과 크론잡 (v1)',
    'game_cron_pre_results_fixed.log' => '미리결과 크론잡 (v2-수정)'
);

foreach ($log_files as $filename => $description) {
    $log_file = __DIR__ . '/logs/' . $filename;
    echo "<h4>{$description}</h4>";
    
    if (file_exists($log_file)) {
        $log_content = file_get_contents($log_file);
        $log_lines = array_slice(explode("\n", $log_content), -10); // 최근 10줄
        echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 200px; overflow-y: scroll; font-size: 12px;'>";
        echo htmlspecialchars(implode("\n", array_filter($log_lines)));
        echo "</pre>";
        
        // 마지막 실행 시간 확인
        $last_modified = filemtime($log_file);
        $time_diff = time() - $last_modified;
        $status_class = $time_diff < 120 ? 'success' : ($time_diff < 300 ? 'warning' : 'error');
        echo "<p class='{$status_class}'>마지막 업데이트: " . date('Y-m-d H:i:s', $last_modified) . " ({$time_diff}초 전)</p>";
    } else {
        echo "<p class='error'>로그 파일이 없습니다: {$filename}</p>";
    }
}
echo "</div>";

// ===================================
// 5. 수동 처리 버튼들 (업데이트)
// ===================================
echo "<div class='section'>";
echo "<h3>5. 수동 처리 도구</h3>";

if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'run_cron':
            echo "<div class='info'>기존 크론잡을 수동 실행합니다...</div>";
            include_once(__DIR__ . '/cron_game_manager.php');
            break;
            
        case 'run_new_cron':
            echo "<div class='info'>새 크론잡(미리결과)을 수동 실행합니다...</div>";
            include_once(__DIR__ . '/cron_game_manager_pre_results_fixed.php');
            break;
            
        case 'complete_round':
            $round_id = intval($_GET['round_id']);
            if ($round_id > 0) {
                // 회차 강제 완료 (수동 처리)
                if (manualCompleteRound($round_id)) {
                    echo "<div class='success'>{$round_id}번 회차가 완료되었습니다.</div>";
                } else {
                    echo "<div class='error'>회차 완료 처리에 실패했습니다.</div>";
                }
            }
            break;
            
        case 'reprocess_points':
            // 당첨된 베팅들의 포인트 재지급
            $reprocessed = reprocessWinningBets();
            echo "<div class='success'>{$reprocessed}건의 당첨금을 재지급했습니다.</div>";
            break;
            
        case 'generate_test_results':
            // 테스트용 미리 결과 생성
            $generated = generateTestPreResults();
            echo "<div class='success'>{$generated}개의 테스트 미리 결과를 생성했습니다.</div>";
            break;
            
        case 'enable_pre_results':
            // 미리 결과 시스템 활성화
            sql_query("UPDATE dice_game_config SET config_value = '1' WHERE config_key = 'use_pre_results'");
            sql_query("
                INSERT INTO dice_game_config (config_key, config_value, config_desc, updated_at) VALUES
                ('use_pre_results', '1', '미리 설정된 결과 사용 여부', NOW()),
                ('auto_generate_results', '1', '결과 자동 생성 여부', NOW()),
                ('pre_result_buffer', '10', '미리 준비할 결과 개수', NOW())
                ON DUPLICATE KEY UPDATE 
                    config_value = VALUES(config_value),
                    updated_at = NOW()
            ");
            echo "<div class='success'>미리 결과 시스템이 활성화되었습니다.</div>";
            break;
    }
    echo "<script>setTimeout(function(){ location.href='diagnosis.php'; }, 3000);</script>";
}

echo "<div style='margin: 20px 0;'>";
echo "<h4>기본 도구</h4>";
echo "<a href='?action=run_cron' style='background: #007bff; color: white; padding: 10px; text-decoration: none; margin: 5px; display: inline-block;'>🔄 기존 크론잡 실행</a>";
echo "<a href='?action=run_new_cron' style='background: #28a745; color: white; padding: 10px; text-decoration: none; margin: 5px; display: inline-block;'>🎯 새 크론잡 실행</a>";
echo "<a href='?action=reprocess_points' style='background: #ffc107; color: black; padding: 10px; text-decoration: none; margin: 5px; display: inline-block;'>💰 당첨금 재지급</a>";

echo "<h4>미리 결과 시스템 도구</h4>";
echo "<a href='?action=enable_pre_results' style='background: #6f42c1; color: white; padding: 10px; text-decoration: none; margin: 5px; display: inline-block;'>⚙️ 미리결과 활성화</a>";
echo "<a href='?action=generate_test_results' style='background: #fd7e14; color: white; padding: 10px; text-decoration: none; margin: 5px; display: inline-block;'>🧪 테스트 결과 생성</a>";
echo "<a href='./pre_result_admin.php' style='background: #20c997; color: white; padding: 10px; text-decoration: none; margin: 5px; display: inline-block;'>🎲 미리결과 관리</a>";

// 완료되지 않은 회차가 있으면 강제 완료 버튼 표시
$incomplete_sql = "SELECT round_id, round_number FROM dice_game_rounds WHERE status = 'waiting' OR (status = 'completed' AND dice1 IS NULL)";
$incomplete_rounds = sql_query($incomplete_sql);
$has_incomplete = false;
while ($incomplete = sql_fetch_array($incomplete_rounds)) {
    if (!$has_incomplete) {
        echo "<h4>회차 강제 완료</h4>";
        $has_incomplete = true;
    }
    echo "<a href='?action=complete_round&round_id={$incomplete['round_id']}' style='background: #dc3545; color: white; padding: 10px; text-decoration: none; margin: 5px; display: inline-block;'>⚡ {$incomplete['round_number']}회차 강제완료</a>";
}

echo "</div>";
echo "</div>";

// ===================================
// 6. 시스템 정보 (업데이트)
// ===================================
echo "<div class='section'>";
echo "<h3>6. 시스템 정보</h3>";
echo "<table>";
echo "<tr><th>항목</th><th>값</th></tr>";
echo "<tr><td>현재 시간</td><td>" . date('Y-m-d H:i:s') . "</td></tr>";
echo "<tr><td>PHP 버전</td><td>" . phpversion() . "</td></tr>";
echo "<tr><td>로그 디렉토리 쓰기 권한</td><td>" . (is_writable(__DIR__ . '/logs') ? '✅ 정상' : '❌ 오류') . "</td></tr>";

// 게임 설정 확인
$config_sql = "SELECT * FROM dice_game_config ORDER BY config_key";
$config_result = sql_query($config_sql);
while ($config = sql_fetch_array($config_result)) {
    echo "<tr><td>{$config['config_key']}</td><td>{$config['config_value']}</td></tr>";
}
echo "</table>";
echo "</div>";

// ===================================
// 새로운 헬퍼 함수들
// ===================================

/**
 * 테스트용 미리 결과 생성
 */
function generateTestPreResults() {
    try {
        $current_round_info = sql_fetch("SELECT round_number FROM dice_game_rounds WHERE status IN ('betting', 'waiting') ORDER BY round_number DESC LIMIT 1");
        $start_round = $current_round_info ? $current_round_info['round_number'] + 1 : 1;
        
        $count = 0;
        for ($i = 0; $i < 10; $i++) {
            $round_number = $start_round + $i;
            
            // 기존 결과 확인
            $existing = sql_fetch("SELECT round_number FROM dice_game_pre_results WHERE round_number = {$round_number}");
            if ($existing) continue;
            
            $dice1 = rand(1, 6);
            $dice2 = rand(1, 6);
            $dice3 = rand(1, 6);
            $total = $dice1 + $dice2 + $dice3;
            $is_high = $total >= 11 ? 1 : 0;
            $is_odd = $total % 2 ? 1 : 0;
            $estimated_time = date('Y-m-d H:i:s', time() + ($i * 90));
            
            $sql = "
                INSERT INTO dice_game_pre_results 
                (round_number, dice1, dice2, dice3, total, is_high, is_odd, estimated_time, created_at, created_by) 
                VALUES 
                ({$round_number}, {$dice1}, {$dice2}, {$dice3}, {$total}, {$is_high}, {$is_odd}, '{$estimated_time}', NOW(), 'diagnosis_test')
            ";
            sql_query($sql);
            $count++;
        }
        
        return $count;
    } catch (Exception $e) {
        return 0;
    }
}

// ===================================
// 기존 함수들 (수정 없음)
// ===================================

/**
 * 수동 회차 완료 처리
 * @param int $round_id 회차 ID
 * @return bool 처리 성공 여부
 */
function manualCompleteRound($round_id) {
    try {
        // 회차 정보 조회
        $round_sql = "SELECT * FROM dice_game_rounds WHERE round_id = {$round_id}";
        $round = sql_fetch($round_sql);
        
        if (!$round) {
            return false;
        }
        
        // 이미 주사위 결과가 있으면 재처리만
        if ($round['dice1'] !== null) {
            echo "<p>이미 주사위 결과가 있습니다. 베팅 재처리만 진행합니다.</p>";
            return reprocessBettingResults($round_id, $round['round_number'], $round['is_high'], $round['is_odd']);
        }
        
        // 주사위 굴리기
        $dice1 = rand(1, 6);
        $dice2 = rand(1, 6);
        $dice3 = rand(1, 6);
        $total = $dice1 + $dice2 + $dice3;
        
        $is_high = $total >= 11 ? 1 : 0;
        $is_odd = $total % 2 === 1 ? 1 : 0;
        
        echo "<p>주사위 결과: {$dice1}, {$dice2}, {$dice3} = {$total} (" . ($is_high ? '대' : '소') . " " . ($is_odd ? '홀' : '짝') . ")</p>";
        
        // 회차 결과 업데이트
        $update_round_sql = "
            UPDATE dice_game_rounds 
            SET dice1 = {$dice1}, 
                dice2 = {$dice2}, 
                dice3 = {$dice3}, 
                total = {$total}, 
                is_high = {$is_high}, 
                is_odd = {$is_odd}, 
                status = 'completed'
            WHERE round_id = {$round_id}
        ";
        sql_query($update_round_sql);
        
        // 베팅 처리
        return reprocessBettingResults($round_id, $round['round_number'], $is_high, $is_odd);
        
    } catch (Exception $e) {
        echo "<p class='error'>오류: " . $e->getMessage() . "</p>";
        return false;
    }
}

/**
 * 베팅 결과 재처리
 * @param int $round_id 회차 ID
 * @param int $round_number 회차 번호
 * @param int $is_high 대소 결과
 * @param int $is_odd 홀짝 결과
 * @return bool 처리 성공 여부
 */
function reprocessBettingResults($round_id, $round_number, $is_high, $is_odd) {
    try {
        // 게임 설정 로드
        $config_sql = "SELECT * FROM dice_game_config";
        $config_result = sql_query($config_sql);
        $config = array();
        while ($row = sql_fetch_array($config_result)) {
            $config[$row['config_key']] = $row['config_value'];
        }
        
        $high_low_rate = floatval($config['win_rate_high_low']);
        $odd_even_rate = floatval($config['win_rate_odd_even']);
        
        // 해당 회차의 모든 베팅 조회
        $bets_sql = "SELECT * FROM dice_game_bets WHERE round_id = {$round_id}";
        $bets_result = sql_query($bets_sql);
        
        $processed_count = 0;
        
        while ($bet = sql_fetch_array($bets_result)) {
            $is_win = 0;
            $win_amount = 0;
            
            // 대소 체크
            $high_low_correct = ($bet['bet_high_low'] === 'high' && $is_high) || 
                               ($bet['bet_high_low'] === 'low' && !$is_high);
            
            // 홀짝 체크
            $odd_even_correct = ($bet['bet_odd_even'] === 'odd' && $is_odd) || 
                               ($bet['bet_odd_even'] === 'even' && !$is_odd);
            
            // 둘 다 맞으면 당첨
            if ($high_low_correct && $odd_even_correct) {
                $is_win = 1;
                $win_amount = intval($bet['bet_amount'] * $high_low_rate * $odd_even_rate);
                
                // 당첨금 지급
                $win_content = "주사위게임 당첨 ({$round_number}회차)";
                
                // 기존 지급 이력 확인
                $existing_point_sql = "
                    SELECT COUNT(*) as cnt FROM g5_point 
                    WHERE mb_id = '{$bet['mb_id']}' 
                    AND po_content = '{$win_content}'
                    AND po_point = {$win_amount}
                ";
                $existing_point = sql_fetch($existing_point_sql);
                
                if ($existing_point['cnt'] == 0) {
                    // 아직 지급되지 않았으면 지급
                    insert_point($bet['mb_id'], $win_amount, $win_content, 'dice_game_bets', $bet['bet_id'], '당첨');
                    echo "<p class='success'>당첨금 지급: {$bet['mb_id']} - {$win_amount}P</p>";
                } else {
                    echo "<p class='warning'>이미 지급됨: {$bet['mb_id']} - {$win_amount}P</p>";
                }
            }
            
            // 베팅 결과 업데이트
            $now = date('Y-m-d H:i:s');
            $update_bet_sql = "
                UPDATE dice_game_bets 
                SET is_win = {$is_win}, 
                    win_amount = {$win_amount}, 
                    processed_at = '{$now}'
                WHERE bet_id = {$bet['bet_id']}
            ";
            sql_query($update_bet_sql);
            
            $processed_count++;
        }
        
        echo "<p class='success'>총 {$processed_count}건의 베팅을 처리했습니다.</p>";
        return true;
        
    } catch (Exception $e) {
        echo "<p class='error'>베팅 처리 오류: " . $e->getMessage() . "</p>";
        return false;
    }
}

/**
 * 당첨된 베팅들의 포인트 재지급
 * @return int 재지급 건수
 */
function reprocessWinningBets() {
    try {
        // 당첨 처리되었지만 포인트 지급 이력이 없는 베팅들 조회
        $unprocessed_sql = "
            SELECT b.*, r.round_number 
            FROM dice_game_bets b
            LEFT JOIN dice_game_rounds r ON b.round_id = r.round_id
            WHERE b.is_win = 1 
            AND b.win_amount > 0
            AND NOT EXISTS (
                SELECT 1 FROM g5_point p 
                WHERE p.mb_id = b.mb_id 
                AND p.po_content = CONCAT('주사위게임 당첨 (', r.round_number, '회차)')
                AND p.po_point = b.win_amount
            )
            ORDER BY b.round_number DESC
        ";
        
        $unprocessed_result = sql_query($unprocessed_sql);
        $reprocessed_count = 0;
        
        while ($bet = sql_fetch_array($unprocessed_result)) {
            $win_content = "주사위게임 당첨 ({$bet['round_number']}회차)";
            
            try {
                insert_point($bet['mb_id'], $bet['win_amount'], $win_content, 'dice_game_bets', $bet['bet_id'], '당첨');
                echo "<p class='success'>재지급: {$bet['mb_id']} - {$bet['round_number']}회차 - " . number_format($bet['win_amount']) . "P</p>";
                $reprocessed_count++;
            } catch (Exception $e) {
                echo "<p class='error'>재지급 실패: {$bet['mb_id']} - " . $e->getMessage() . "</p>";
            }
        }
        
        return $reprocessed_count;
        
    } catch (Exception $e) {
        echo "<p class='error'>재지급 처리 오류: " . $e->getMessage() . "</p>";
        return 0;
    }
}

?>