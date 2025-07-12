<?php
/*
 * 파일명: cron_time_debug.php
 * 위치: /game/cron_time_debug.php
 * 기능: 크론잡 시간 체크 로직 디버깅
 * 작성일: 2025-06-12
 */

include_once(__DIR__ . '/../common.php');

// 관리자 권한 확인
if (!$is_admin) {
    alert('관리자만 접근 가능합니다.');
    goto_url('./index.php');
}

echo "<h2>⏰ 크론잡 시간 체크 로직 디버깅</h2>";
echo "<style>
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
.problem { background-color: #f8d7da; }
.ok { background-color: #d4edda; }
.warning { background-color: #fff3cd; }
</style>";

// ===================================
// 1. 현재 진행중인 회차 상세 분석
// ===================================
echo "<h3>1. 현재 진행중인 회차 상세 분석</h3>";

$current_round = sql_fetch("
    SELECT * FROM dice_game_rounds 
    WHERE status IN ('betting', 'waiting') 
    ORDER BY round_number DESC 
    LIMIT 1
");

if ($current_round) {
    echo "<table>";
    echo "<tr><th>항목</th><th>값</th><th>분석</th></tr>";
    
    $now = time();
    $end_time = strtotime($current_round['end_time']);
    $result_time = $current_round['result_time'] ? strtotime($current_round['result_time']) : null;
    
    echo "<tr><td>회차번호</td><td>{$current_round['round_number']}</td><td>-</td></tr>";
    echo "<tr><td>상태</td><td>{$current_round['status']}</td><td>-</td></tr>";
    echo "<tr><td>시작시간</td><td>{$current_round['start_time']}</td><td>-</td></tr>";
    echo "<tr><td>종료시간</td><td>{$current_round['end_time']}</td><td>" . date('Y-m-d H:i:s', $end_time) . "</td></tr>";
    echo "<tr><td>결과시간</td><td>" . ($current_round['result_time'] ?? 'NULL') . "</td><td>" . ($result_time ? date('Y-m-d H:i:s', $result_time) : 'NULL') . "</td></tr>";
    echo "<tr><td>현재시간</td><td>" . date('Y-m-d H:i:s', $now) . "</td><td>Unix: {$now}</td></tr>";
    
    // 시간 비교
    $betting_ended = $now >= $end_time;
    $result_ready = $result_time && $now >= $result_time;
    
    echo "<tr class='" . ($betting_ended ? 'ok' : 'warning') . "'><td>베팅 종료됨</td><td>" . ($betting_ended ? 'YES' : 'NO') . "</td><td>현재시간 >= 종료시간</td></tr>";
    echo "<tr class='" . ($result_ready ? 'ok' : 'warning') . "'><td>결과 처리 가능</td><td>" . ($result_ready ? 'YES' : 'NO') . "</td><td>현재시간 >= 결과시간</td></tr>";
    
    if ($current_round['status'] === 'waiting' && !$result_ready) {
        $wait_seconds = $result_time - $now;
        echo "<tr class='warning'><td>결과 대기시간</td><td>{$wait_seconds}초</td><td>결과 처리까지 남은 시간</td></tr>";
    }
    
    echo "</table>";
} else {
    echo "<p class='problem'>❌ 진행중인 회차가 없습니다.</p>";
}

// ===================================
// 2. 최근 waiting 상태 회차들 분석
// ===================================
echo "<h3>2. 최근 waiting 상태에서 누락된 회차들</h3>";

$waiting_rounds_sql = "
    SELECT * FROM dice_game_rounds 
    WHERE status = 'waiting' 
    AND result_time IS NOT NULL 
    AND result_time < NOW()
    AND dice1 IS NULL
    ORDER BY round_number DESC 
    LIMIT 5
";

$waiting_rounds = sql_query($waiting_rounds_sql);

if (sql_num_rows($waiting_rounds) > 0) {
    echo "<p class='problem'>❌ 다음 회차들이 waiting 상태에서 처리되지 않았습니다:</p>";
    echo "<table>";
    echo "<tr><th>회차</th><th>상태</th><th>결과시간</th><th>지연시간</th><th>조치</th></tr>";
    
    while ($round = sql_fetch_array($waiting_rounds)) {
        $delay = time() - strtotime($round['result_time']);
        echo "<tr class='problem'>";
        echo "<td>{$round['round_number']}</td>";
        echo "<td>{$round['status']}</td>";
        echo "<td>{$round['result_time']}</td>";
        echo "<td>{$delay}초 지연</td>";
        echo "<td><a href='?fix_round={$round['round_id']}' style='background: red; color: white; padding: 5px; text-decoration: none;'>강제 처리</a></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='ok'>✅ 모든 waiting 회차가 정상 처리되었습니다.</p>";
}

// ===================================
// 3. 크론잡 시간 로직 시뮬레이션
// ===================================
echo "<h3>3. 크론잡 시간 로직 시뮬레이션</h3>";

if ($current_round) {
    $round_id = $current_round['round_id'];
    $round_number = $current_round['round_number'];
    $status = $current_round['status'];
    $end_time = strtotime($current_round['end_time']);
    $current_time = time();
    
    echo "<div style='background: #f0f0f0; padding: 15px; margin: 10px 0; font-family: monospace;'>";
    echo "<h4>시뮬레이션 결과:</h4>";
    
    echo "<p><strong>현재 상황:</strong></p>";
    echo "<ul>";
    echo "<li>회차: {$round_number}</li>";
    echo "<li>상태: {$status}</li>";
    echo "<li>종료시간: " . date('Y-m-d H:i:s', $end_time) . " (Unix: {$end_time})</li>";
    echo "<li>현재시간: " . date('Y-m-d H:i:s', $current_time) . " (Unix: {$current_time})</li>";
    echo "</ul>";
    
    if ($status === 'betting' && $current_time >= $end_time) {
        echo "<p class='ok'>✅ 베팅 종료 조건 만족 → waiting 상태로 변경</p>";
        
        $result_time = 30; // 기본값
        $result_end_time = $current_time + $result_time;
        echo "<p>결과 시간 설정: " . date('Y-m-d H:i:s', $result_end_time) . "</p>";
        
    } elseif ($status === 'waiting') {
        $result_time = strtotime($current_round['result_time']);
        echo "<p><strong>결과 처리 체크:</strong></p>";
        echo "<ul>";
        echo "<li>결과시간: " . date('Y-m-d H:i:s', $result_time) . " (Unix: {$result_time})</li>";
        echo "<li>현재시간: " . date('Y-m-d H:i:s', $current_time) . " (Unix: {$current_time})</li>";
        echo "<li>비교결과: {$current_time} >= {$result_time} = " . ($current_time >= $result_time ? 'TRUE' : 'FALSE') . "</li>";
        echo "</ul>";
        
        if ($current_time >= $result_time) {
            echo "<p class='ok'>✅ 결과 처리 조건 만족 → 결과 처리 실행</p>";
            
            // 미리 결과 체크
            $preset_check = sql_fetch("SELECT * FROM dice_game_pre_results WHERE round_number = {$round_number} AND is_used = 0");
            if ($preset_check) {
                echo "<p class='ok'>✅ 미리 결과 있음: {$preset_check['dice1']}-{$preset_check['dice2']}-{$preset_check['dice3']}</p>";
            } else {
                echo "<p class='warning'>⚠️ 미리 결과 없음 → 랜덤 생성</p>";
            }
        } else {
            $wait_seconds = $result_time - $current_time;
            echo "<p class='warning'>⚠️ 결과 처리까지 {$wait_seconds}초 대기</p>";
        }
    }
    
    echo "</div>";
}

// ===================================
// 4. 강제 처리
// ===================================
if (isset($_GET['fix_round'])) {
    $fix_round_id = intval($_GET['fix_round']);
    
    echo "<h3>4. 회차 강제 처리</h3>";
    
    $fix_round = sql_fetch("SELECT * FROM dice_game_rounds WHERE round_id = {$fix_round_id}");
    
    if ($fix_round) {
        echo "<p>회차 {$fix_round['round_number']} 강제 처리 중...</p>";
        
        // 미리 결과 확인
        $preset_result = sql_fetch("SELECT * FROM dice_game_pre_results WHERE round_number = {$fix_round['round_number']} AND is_used = 0");
        
        if ($preset_result) {
            $dice1 = $preset_result['dice1'];
            $dice2 = $preset_result['dice2'];
            $dice3 = $preset_result['dice3'];
            $total = $preset_result['total'];
            $is_high = $preset_result['is_high'];
            $is_odd = $preset_result['is_odd'];
            
            echo "<p class='ok'>✅ 미리 설정된 결과 사용: {$dice1}-{$dice2}-{$dice3}</p>";
            
            // 미리 결과 사용됨 표시
            $now = date('Y-m-d H:i:s');
            sql_query("UPDATE dice_game_pre_results SET is_used = 1, used_at = '{$now}' WHERE round_number = {$fix_round['round_number']}");
        } else {
            $dice1 = rand(1, 6);
            $dice2 = rand(1, 6);
            $dice3 = rand(1, 6);
            $total = $dice1 + $dice2 + $dice3;
            $is_high = $total >= 11 ? 1 : 0;
            $is_odd = $total % 2 ? 1 : 0;
            
            echo "<p class='warning'>⚠️ 미리 결과 없음 - 랜덤 생성: {$dice1}-{$dice2}-{$dice3}</p>";
        }
        
        // 회차 완료 처리
        $now = date('Y-m-d H:i:s');
        $update_sql = "
            UPDATE dice_game_rounds SET 
                dice1 = {$dice1}, dice2 = {$dice2}, dice3 = {$dice3},
                total = {$total}, is_high = {$is_high}, is_odd = {$is_odd},
                status = 'completed', result_time = '{$now}'
            WHERE round_id = {$fix_round_id}
        ";
        
        if (sql_query($update_sql)) {
            echo "<p class='ok'>✅ 회차 {$fix_round['round_number']} 처리 완료!</p>";
        } else {
            echo "<p class='problem'>❌ 회차 처리 실패</p>";
        }
    }
    
    echo "<script>setTimeout(function(){ location.href='cron_time_debug.php'; }, 2000);</script>";
}

// ===================================
// 5. 실시간 모니터링
// ===================================
echo "<h3>5. 실시간 모니터링</h3>";

echo "<div id='real-time-monitor' style='background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6;'>";
echo "<h4>실시간 상태 (3초마다 업데이트)</h4>";
echo "<div id='monitor-content'>로딩 중...</div>";
echo "</div>";

echo "<script>
function updateMonitor() {
    fetch('./status_check.php')
        .then(response => response.json())
        .then(data => {
            const now = new Date();
            const endTime = new Date(data.end_time);
            const timeDiff = Math.max(0, Math.floor((endTime - now) / 1000));
            
            document.getElementById('monitor-content').innerHTML = `
                <p><strong>현재시간:</strong> \${now.toLocaleString()}</p>
                <p><strong>현재 회차:</strong> \${data.round_number} (\${data.status})</p>
                <p><strong>게임 단계:</strong> \${data.phase}</p>
                <p><strong>남은 시간:</strong> \${timeDiff}초</p>
                \${data.result ? `<p><strong>결과:</strong> \${data.result.dice1}-\${data.result.dice2}-\${data.result.dice3}</p>` : ''}
            `;
        })
        .catch(error => {
            console.error('Error:', error);
        });
}

// 즉시 실행 후 3초마다 반복
updateMonitor();
setInterval(updateMonitor, 3000);
</script>";

echo "<p><a href='./debug_preset_result.php'>← 미리 결과 디버깅으로 돌아가기</a></p>";
?>