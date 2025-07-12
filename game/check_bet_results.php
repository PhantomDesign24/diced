<?php
/*
* 파일명: debug_check.php
* 위치: /game/debug_check.php
* 기능: 포인트 지급 문제 진단
* 작성일: 2025-06-12
*/

include_once('../common.php');

echo "<h2>🔍 포인트 지급 문제 진단</h2>";

// ===================================
// 1. 현재 회차 상태 확인
// ===================================
echo "<h3>1. 현재 회차 상태</h3>";

$current_rounds = sql_query("
    SELECT round_id, round_number, start_time, end_time, result_time, status, dice1, dice2, dice3, total, is_high, is_odd
    FROM dice_game_rounds 
    WHERE round_number >= (SELECT MAX(round_number) - 5 FROM dice_game_rounds)
    ORDER BY round_number DESC
");

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>회차</th><th>상태</th><th>시작시간</th><th>마감시간</th><th>결과시간</th><th>주사위</th><th>합계</th><th>대소/홀짝</th></tr>";

while ($round = sql_fetch_array($current_rounds)) {
    $dice_display = $round['dice1'] ? "{$round['dice1']}-{$round['dice2']}-{$round['dice3']}" : "미정";
    $result_display = $round['total'] ? ($round['is_high'] ? '대' : '소') . '/' . ($round['is_odd'] ? '홀' : '짝') : "미정";
    
    echo "<tr>";
    echo "<td>{$round['round_number']}</td>";
    echo "<td>{$round['status']}</td>";
    echo "<td>{$round['start_time']}</td>";
    echo "<td>{$round['end_time']}</td>";
    echo "<td>{$round['result_time']}</td>";
    echo "<td>{$dice_display}</td>";
    echo "<td>{$round['total']}</td>";
    echo "<td>{$result_display}</td>";
    echo "</tr>";
}
echo "</table>";

// ===================================
// 2. 베팅 데이터 확인
// ===================================
echo "<h3>2. 최근 베팅 데이터</h3>";

$recent_bets = sql_query("
    SELECT b.*, r.status as round_status, r.dice1, r.dice2, r.dice3, r.total, r.is_high, r.is_odd
    FROM dice_game_bets b
    LEFT JOIN dice_game_rounds r ON b.round_id = r.round_id
    WHERE b.round_number >= (SELECT MAX(round_number) - 3 FROM dice_game_rounds)
    ORDER BY b.bet_id DESC
    LIMIT 10
");

echo "<table border='1' style='border-collapse: collapse;'>";
echo "<tr><th>베팅ID</th><th>회차</th><th>회원</th><th>베팅</th><th>금액</th><th>당첨여부</th><th>당첨금</th><th>정산시간</th><th>회차상태</th></tr>";

while ($bet = sql_fetch_array($recent_bets)) {
    $bet_display = ($bet['bet_high_low'] === 'high' ? '대' : '소') . '/' . ($bet['bet_odd_even'] === 'odd' ? '홀' : '짝');
    $win_display = $bet['is_win'] === null ? '미정' : ($bet['is_win'] ? '당첨' : '실패');
    
    echo "<tr>";
    echo "<td>{$bet['bet_id']}</td>";
    echo "<td>{$bet['round_number']}</td>";
    echo "<td>{$bet['mb_id']}</td>";
    echo "<td>{$bet_display}</td>";
    echo "<td>" . number_format($bet['bet_amount']) . "</td>";
    echo "<td>{$win_display}</td>";
    echo "<td>" . number_format($bet['win_amount']) . "</td>";
    echo "<td>{$bet['processed_at']}</td>";
    echo "<td>{$bet['round_status']}</td>";
    echo "</tr>";
}
echo "</table>";

// ===================================
// 3. waiting -> completed 전환 대상 확인
// ===================================
echo "<h3>3. 정산 대상 회차 확인</h3>";

$now = date('Y-m-d H:i:s');
echo "<p>현재 시간: {$now}</p>";

$waiting_rounds = sql_query("
    SELECT * FROM dice_game_rounds 
    WHERE status = 'waiting' 
    AND result_time <= '{$now}'
    ORDER BY round_number ASC
");

echo "<h4>정산 대상 회차:</h4>";
if (sql_num_rows($waiting_rounds) == 0) {
    echo "<p style='color: red;'>⚠️ 정산 대상 회차가 없습니다!</p>";
    
    // waiting 상태 회차 확인
    $all_waiting = sql_query("SELECT * FROM dice_game_rounds WHERE status = 'waiting' ORDER BY round_number");
    echo "<h4>현재 waiting 상태 회차:</h4>";
    while ($w = sql_fetch_array($all_waiting)) {
        $time_diff = strtotime($w['result_time']) - time();
        echo "<p>회차 {$w['round_number']}: 결과시간 {$w['result_time']} (남은시간: {$time_diff}초)</p>";
    }
} else {
    while ($round = sql_fetch_array($waiting_rounds)) {
        echo "<p>회차 {$round['round_number']}: 결과시간 {$round['result_time']}</p>";
    }
}

// ===================================
// 4. 포인트 지급 함수 테스트
// ===================================
echo "<h3>4. 포인트 함수 테스트</h3>";

echo "<p>insert_point 함수 존재 여부: " . (function_exists('insert_point') ? '✅ 존재' : '❌ 없음') . "</p>";
echo "<p>sql_affected_rows 함수 존재 여부: " . (function_exists('sql_affected_rows') ? '✅ 존재' : '❌ 없음') . "</p>";

// ROW_COUNT() 테스트
$row_count_test = sql_fetch("SELECT ROW_COUNT() as affected");
echo "<p>ROW_COUNT() 테스트: " . ($row_count_test ? '✅ 동작' : '❌ 실패') . "</p>";

// ===================================
// 5. 수동 정산 테스트 (관리자만)
// ===================================
if ($is_admin && isset($_GET['manual_settle']) && $_GET['manual_settle'] == '1') {
    echo "<h3>5. 수동 정산 실행</h3>";
    
    $target_round = intval($_GET['round_number']);
    if ($target_round > 0) {
        echo "<p>회차 {$target_round} 수동 정산 시작...</p>";
        
        // 회차 정보 조회
        $round_info = sql_fetch("SELECT * FROM dice_game_rounds WHERE round_number = {$target_round}");
        if (!$round_info) {
            echo "<p style='color: red;'>❌ 해당 회차를 찾을 수 없습니다.</p>";
        } else {
            // 주사위 결과가 없으면 임시로 생성
            if (!$round_info['dice1']) {
                $dice1 = rand(1, 6);
                $dice2 = rand(1, 6);
                $dice3 = rand(1, 6);
                $total = $dice1 + $dice2 + $dice3;
                $is_high = $total >= 11 ? 1 : 0;
                $is_odd = $total % 2;
                
                $update_sql = "
                    UPDATE dice_game_rounds 
                    SET dice1 = {$dice1}, dice2 = {$dice2}, dice3 = {$dice3}, 
                        total = {$total}, is_high = {$is_high}, is_odd = {$is_odd}
                    WHERE round_number = {$target_round}
                ";
                sql_query($update_sql);
                
                echo "<p>✅ 주사위 결과 생성: {$dice1}-{$dice2}-{$dice3} = {$total} (" . 
                     ($is_high ? '대' : '소') . "/" . ($is_odd ? '홀' : '짝') . ")</p>";
                
                // 회차 정보 다시 조회
                $round_info = sql_fetch("SELECT * FROM dice_game_rounds WHERE round_number = {$target_round}");
            }
            
            // 베팅 정산
            $bets = sql_query("SELECT * FROM dice_game_bets WHERE round_number = {$target_round} AND is_win IS NULL");
            $processed = 0;
            $winners = 0;
            
            while ($bet = sql_fetch_array($bets)) {
                $processed++;
                
                // 당첨 여부 판정
                $high_correct = ($bet['bet_high_low'] === 'high' && $round_info['is_high']) || 
                               ($bet['bet_high_low'] === 'low' && !$round_info['is_high']);
                $odd_correct = ($bet['bet_odd_even'] === 'odd' && $round_info['is_odd']) || 
                              ($bet['bet_odd_even'] === 'even' && !$round_info['is_odd']);
                
                $win = $high_correct && $odd_correct ? 1 : 0;
                $win_amount = 0;
                
                if ($win) {
                    $winners++;
                    $high_rate = 1.95;
                    $odd_rate = 1.95;
                    $win_amount = floor($bet['bet_amount'] * $high_rate * $odd_rate);
                    
                    echo "<p>당첨자: {$bet['mb_id']} - {$win_amount}P</p>";
                    
                    // 포인트 지급
                    $content = "{$target_round}회차 당첨 (수동정산)";
                    $point_sql = "
                        INSERT INTO g5_point 
                        (mb_id, po_datetime, po_content, po_point, po_use_point, po_expired, po_expire_date, po_mb_point, po_rel_table, po_rel_id, po_rel_action)
                        VALUES 
                        ('{$bet['mb_id']}', NOW(), '{$content}', {$win_amount}, 0, 0, '9999-12-31', 0, 'dice_game_bets', '{$bet['bet_id']}', '당첨')
                    ";
                    
                    if (sql_query($point_sql)) {
                        echo "<p>✅ 포인트 지급 성공</p>";
                    } else {
                        echo "<p>❌ 포인트 지급 실패: " . sql_error() . "</p>";
                    }
                }
                
                // 베팅 결과 업데이트
                $bet_update_sql = "
                    UPDATE dice_game_bets SET 
                        is_win = {$win}, 
                        win_amount = {$win_amount}, 
                        processed_at = NOW()
                    WHERE bet_id = {$bet['bet_id']}
                ";
                sql_query($bet_update_sql);
            }
            
            // 회차 상태를 completed로 변경
            sql_query("UPDATE dice_game_rounds SET status = 'completed' WHERE round_number = {$target_round}");
            
            echo "<p>🏁 정산 완료: 총 {$processed}명 중 {$winners}명 당첨</p>";
        }
    }
}

// ===================================
// 6. 진단 결과 및 해결책
// ===================================
echo "<h3>6. 진단 결과</h3>";

// 정산되지 않은 베팅 확인
$unprocessed_bets = sql_fetch("
    SELECT COUNT(*) as count, SUM(bet_amount) as total_amount
    FROM dice_game_bets b
    JOIN dice_game_rounds r ON b.round_id = r.round_id
    WHERE b.is_win IS NULL 
    AND r.status = 'completed'
    AND r.dice1 IS NOT NULL
");

if ($unprocessed_bets && $unprocessed_bets['count'] > 0) {
    echo "<p style='color: red;'>⚠️ 정산되지 않은 베팅이 {$unprocessed_bets['count']}건 있습니다! (총 " . number_format($unprocessed_bets['total_amount']) . "P)</p>";
    echo "<p><a href='?manual_settle=1&round_number=24' style='background: red; color: white; padding: 10px;'>24회차 수동 정산 실행</a></p>";
    echo "<p><a href='?manual_settle=1&round_number=25' style='background: red; color: white; padding: 10px;'>25회차 수동 정산 실행</a></p>";
} else {
    echo "<p style='color: green;'>✅ 모든 베팅이 정상 정산되었습니다.</p>";
}

// 문제점 분석
echo "<h4>🔧 가능한 문제점:</h4>";
echo "<ul>";
echo "<li><strong>주사위 결과 없음:</strong> waiting 상태 회차에 주사위 결과(dice1, dice2, dice3)가 없어서 정산이 안 됨</li>";
echo "<li><strong>시간 동기화:</strong> result_time이 아직 도달하지 않아서 크론잡에서 처리 안 됨</li>";
echo "<li><strong>상태 전환 실패:</strong> waiting -> completed 전환이 안 되어서 정산 로직 실행 안 됨</li>";
echo "<li><strong>포인트 함수 오류:</strong> insert_point 함수나 수동 포인트 지급에서 오류 발생</li>";
echo "</ul>";

echo "<h4>🛠️ 해결 방법:</h4>";
echo "<ul>";
echo "<li>1. 회차에 주사위 결과를 수동으로 생성하기</li>";
echo "<li>2. 크론잡 실행 시간을 조정하기</li>";
echo "<li>3. 수동으로 회차 상태를 completed로 변경하기</li>";
echo "<li>4. 포인트 지급 로직을 수정하기</li>";
echo "</ul>";

echo "<hr>";
echo "<p><a href='./simple_cron.php?manual=1'>🔄 크론잡 수동 실행</a></p>";
echo "<p><a href='./round_pre_admin.php'>🔧 회차 관리</a></p>";
echo "<p><a href='./index.php'>🎮 게임으로 돌아가기</a></p>";
?>