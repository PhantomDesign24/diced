<?php
/*
* 파일명: cron_game_manager_abc.php
* 위치: /game/cron_game_manager_abc.php
* 기능: A/B/C 게임 회차 스케줄러 (미리 생성된 회차 관리)
* 작성일: 2025-01-07
*/

// 직접 실행 방지
if (php_sapi_name() !== 'cli' && (!isset($_GET['manual']) || $_GET['manual'] !== '1')) {
    http_response_code(403);
    exit('Access denied');
}

include_once(__DIR__ . '/../common.php');

// ===================================
// 로그 시스템
// ===================================
$log_file = __DIR__ . '/logs/cron_game_abc.log';
if (!is_dir(dirname($log_file))) {
    mkdir(dirname($log_file), 0755, true);
}

function cronLog($message, $data = null) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] {$message}";
    
    if ($data !== null) {
        $log_message .= " | " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    
    $log_message .= PHP_EOL;
    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
    
    // 웹 실행시 출력
    if (php_sapi_name() !== 'cli') {
        echo htmlspecialchars($log_message) . "<br>";
    }
    
    echo $log_message;
}

function getConfig($key, $default = '') {
    $sql = "SELECT config_value FROM dice_game_config WHERE config_key = '{$key}'";
    $result = sql_fetch($sql);
    return $result ? $result['config_value'] : $default;
}

// 그누보드 환경에 맞는 affected_rows 함수
function getSafeAffectedRows() {
    if (function_exists('sql_affected_rows')) {
        return sql_affected_rows();
    } else {
        // ROW_COUNT() 사용
        $result = sql_fetch("SELECT ROW_COUNT() as affected");
        return $result ? intval($result['affected']) : 0;
    }
}

try {
    cronLog("=== A/B/C 게임 회차 스케줄러 시작 ===");
    
    // ===================================
    // 게임 상태 확인
    // ===================================
    if (getConfig('game_status', '1') !== '1') {
        cronLog("게임 비활성화 상태 - 종료");
        exit;
    }
    
    $now = date('Y-m-d H:i:s');
    $now_timestamp = time();
    
    cronLog("현재 시간", ['datetime' => $now, 'timestamp' => $now_timestamp]);
    
    // ===================================
    // 1단계: scheduled -> betting 전환
    // ===================================
    cronLog("1단계: 새로운 베팅 회차 시작 확인");
    
    $now_mysql = date('Y-m-d H:i:s');
    $scheduled_to_betting = sql_query("
        UPDATE dice_game_rounds 
        SET status = 'betting' 
        WHERE status = 'scheduled' 
        AND start_time <= '{$now_mysql}'
        AND start_time > DATE_SUB('{$now_mysql}', INTERVAL 10 SECOND)
    ");
    
    $started_rounds = getSafeAffectedRows();
    if ($started_rounds > 0) {
        cronLog("✅ 새로운 베팅 회차 시작", ['count' => $started_rounds]);
    }
    
    // ===================================
    // 2단계: betting -> waiting 전환
    // ===================================
    cronLog("2단계: 베팅 마감 확인");
    
    $betting_to_waiting = sql_query("
        UPDATE dice_game_rounds 
        SET status = 'waiting' 
        WHERE status = 'betting' 
        AND end_time <= '{$now_mysql}'
    ");
    
    $waiting_rounds = getSafeAffectedRows();
    if ($waiting_rounds > 0) {
        cronLog("⏰ 베팅 마감된 회차", ['count' => $waiting_rounds]);
    }
    
    // ===================================
    // 3단계: 미정산 회차 일괄 정리 (A/B/C 게임용)
    // ===================================
    cronLog("3단계: 미정산 회차 일괄 정리");

    // 현재 진행중이거나 최근 완료된 회차 번호 조회
    $current_round_info = sql_fetch("
        SELECT MAX(round_number) as current_round 
        FROM dice_game_rounds 
        WHERE status IN ('betting', 'waiting', 'completed')
        AND start_time <= '{$now_mysql}'
    ");

    $current_round_number = $current_round_info ? $current_round_info['current_round'] : 0;

    // 체크 범위 설정 (현재 회차 기준으로 이전 20회차까지)
    $check_from_round = max(1, $current_round_number - 20);
    $check_to_round = $current_round_number;

    cronLog("미정산 체크 범위", [
        'current_round' => $current_round_number,
        'from_round' => $check_from_round, 
        'to_round' => $check_to_round
    ]);

    // 미정산 베팅이 있는 회차들 조회
    $unprocessed_rounds = sql_query("
        SELECT DISTINCT r.round_id, r.round_number, r.status, 
               r.game_a_result, r.game_b_result, r.game_c_result,
               r.result_time, r.start_time
        FROM dice_game_rounds r
        INNER JOIN dice_game_bets b ON r.round_id = b.round_id
        WHERE r.round_number >= {$check_from_round}
        AND r.round_number <= {$check_to_round}
        AND b.status = 'pending'
        ORDER BY r.round_number ASC
    ");

    $auto_fixed_count = 0;

    while ($round = sql_fetch_array($unprocessed_rounds)) {
        $round_id = $round['round_id'];
        $round_number = $round['round_number'];
        
        // 시간 체크 - 아직 시작하지 않은 회차는 건너뛰기
        if (strtotime($round['start_time']) > time()) {
            cronLog("⏳ 아직 시작하지 않은 회차 건너뛰기", [
                'round' => $round_number,
                'start_time' => $round['start_time']
            ]);
            continue;
        }
        
        cronLog("🔧 미정산 회차 자동 정리 시작", [
            'round' => $round_number, 
            'status' => $round['status'],
            'result_time' => $round['result_time']
        ]);
        
        // A/B/C 게임 결과가 없으면 자동 생성
        if (!$round['game_a_result'] || !$round['game_b_result'] || !$round['game_c_result']) {
            $game_a_result = (string)rand(1, 2);
            $game_b_result = (string)rand(1, 2);
            $game_c_result = (string)rand(1, 2);
            
            $update_result_sql = "
                UPDATE dice_game_rounds 
                SET game_a_result = '{$game_a_result}', 
                    game_b_result = '{$game_b_result}', 
                    game_c_result = '{$game_c_result}',
                    status = 'completed'
                WHERE round_id = {$round_id}
            ";
            
            if (sql_query($update_result_sql)) {
                cronLog("🎲 A/B/C 게임 결과 자동 생성", [
                    'round' => $round_number,
                    'results' => "A{$game_a_result}, B{$game_b_result}, C{$game_c_result}"
                ]);
                
                // 업데이트된 정보로 다시 설정
                $round['game_a_result'] = $game_a_result;
                $round['game_b_result'] = $game_b_result;
                $round['game_c_result'] = $game_c_result;
            } else {
                cronLog("❌ 게임 결과 생성 실패", ['round' => $round_number, 'error' => sql_error()]);
                continue;
            }
        }
        
        // 베팅 정산 처리
        cronLog("💰 미정산 베팅 정산 시작", ['round' => $round_number]);
        
        $unprocessed_bets = sql_query("
            SELECT * FROM dice_game_bets 
            WHERE round_id = {$round_id} AND status = 'pending'
        ");
        
        $processed = 0;
        $winners = 0;
        $total_bet_amount = 0;
        $total_win_amount = 0;
        
        while ($bet = sql_fetch_array($unprocessed_bets)) {
            $processed++;
            $total_bet_amount += $bet['bet_amount'];
            
            // 당첨 여부 판정
            $game_result = '';
            switch ($bet['game_type']) {
                case 'A':
                    $game_result = $round['game_a_result'];
                    break;
                case 'B':
                    $game_result = $round['game_b_result'];
                    break;
                case 'C':
                    $game_result = $round['game_c_result'];
                    break;
            }
            
            $is_win = ($game_result == $bet['bet_option']);
            $win_amount = 0;
            
            if ($is_win) {
                $winners++;
                // 배율 조회
                $rate_key = 'game_' . strtolower($bet['game_type']) . $bet['bet_option'] . '_rate';
                $rate = (float)getConfig($rate_key, '2.0');
                $win_amount = floor($bet['bet_amount'] * $rate);
                $total_win_amount += $win_amount;
                
                // 포인트 지급
                $content = "{$round_number}회차 {$bet['game_type']}{$bet['bet_option']} 당첨 (자동정산)";
                
                // 그누보드5 포인트 지급
                $po_point = $win_amount;
                $po_content = sql_real_escape_string($content);
                $mb_id = sql_real_escape_string($bet['mb_id']);
                
                // 현재 회원 포인트 조회
                $mb = sql_fetch("SELECT mb_point FROM {$g5['member_table']} WHERE mb_id = '{$mb_id}'");
                if ($mb) {
                    $po_mb_point = $mb['mb_point'] + $po_point;
                    
                    // 포인트 내역 추가
                    $point_sql = "
                        INSERT INTO {$g5['point_table']} SET
                            mb_id = '{$mb_id}',
                            po_datetime = '{$now}',
                            po_content = '{$po_content}',
                            po_point = {$po_point},
                            po_use_point = 0,
                            po_expired = 0,
                            po_expire_date = '9999-12-31',
                            po_mb_point = {$po_mb_point},
                            po_rel_table = 'dice_game_bets',
                            po_rel_id = '{$bet['bet_id']}',
                            po_rel_action = '당첨'
                    ";
                    
                    if (sql_query($point_sql)) {
                        // 회원 포인트 업데이트
                        sql_query("UPDATE {$g5['member_table']} SET mb_point = {$po_mb_point} WHERE mb_id = '{$mb_id}'");
                        
                        cronLog("✅ 당첨 포인트 지급", [
                            'member' => $bet['mb_id'],
                            'game' => $bet['game_type'] . $bet['bet_option'],
                            'amount' => $win_amount,
                            'bet_id' => $bet['bet_id'],
                            'new_point' => $po_mb_point
                        ]);
                    } else {
                        cronLog("❌ 포인트 지급 실패", [
                            'member' => $bet['mb_id'],
                            'amount' => $win_amount,
                            'error' => sql_error()
                        ]);
                    }
                } else {
                    cronLog("❌ 회원 정보 없음", ['member' => $bet['mb_id']]);
                }
            } else {
                // 미당첨도 로그
                cronLog("📝 미당첨 처리", [
                    'member' => $bet['mb_id'],
                    'bet_id' => $bet['bet_id'],
                    'bet' => $bet['game_type'] . $bet['bet_option'],
                    'result' => $bet['game_type'] . $game_result
                ]);
            }
            
            // 베팅 결과 업데이트
            $bet_status = $is_win ? 'win' : 'lose';
            $bet_update_sql = "
                UPDATE dice_game_bets SET 
                    status = '{$bet_status}', 
                    win_amount = {$win_amount}, 
                    processed_at = '{$now}'
                WHERE bet_id = {$bet['bet_id']}
            ";
            
            if (sql_query($bet_update_sql)) {
                cronLog("📝 베팅 결과 업데이트 완료", [
                    'bet_id' => $bet['bet_id'],
                    'status' => $bet_status,
                    'win_amount' => $win_amount
                ]);
            }
        }
        
        // 회차 상태를 completed로 변경
        sql_query("UPDATE dice_game_rounds SET status = 'completed' WHERE round_id = {$round_id} AND status != 'completed'");
        
        // 회차 통계 업데이트
        $stats_update_sql = "
            UPDATE dice_game_rounds SET 
                total_players = {$processed},
                total_bet_amount = {$total_bet_amount}
            WHERE round_id = {$round_id}
        ";
        sql_query($stats_update_sql);
        
        cronLog("🏁 미정산 회차 정리 완료", [
            'round' => $round_number,
            'participants' => $processed,
            'winners' => $winners,
            'total_bet' => number_format($total_bet_amount),
            'total_win' => number_format($total_win_amount),
            'results' => "A{$round['game_a_result']}, B{$round['game_b_result']}, C{$round['game_c_result']}"
        ]);
        
        $auto_fixed_count++;
    }

    if ($auto_fixed_count > 0) {
        cronLog("✅ 총 {$auto_fixed_count}개 회차 자동 정리 완료");
    }
    
    // ===================================
    // 4단계: waiting -> completed 전환 및 정산 (정규 프로세스)
    // ===================================
    cronLog("4단계: 정규 결과 발표 및 정산 확인");
    
    $rounds_to_complete = sql_query("
        SELECT * FROM dice_game_rounds 
        WHERE status = 'waiting' 
        AND result_time <= '{$now_mysql}'
        ORDER BY round_number ASC
    ");
    
    $completed_count = 0;
    
    while ($round = sql_fetch_array($rounds_to_complete)) {
        // 위의 3단계에서 이미 처리되므로 여기서는 상태만 변경
        sql_query("UPDATE dice_game_rounds SET status = 'completed' WHERE round_id = {$round['round_id']}");
        $completed_count++;
    }
    
    if ($completed_count > 0) {
        cronLog("정규 완료 처리", ['count' => $completed_count]);
    }
    
    // ===================================
    // 5단계: 자동 회차 생성 (옵션)
    // ===================================
    $auto_generate = getConfig('auto_generate_rounds', '0');
    if ($auto_generate === '1') {
        cronLog("5단계: 자동 회차 생성 확인");
        
        // 앞으로 1시간 내 예정된 회차 수 확인
        $future_count = sql_fetch("
            SELECT COUNT(*) as count 
            FROM dice_game_rounds 
            WHERE status = 'scheduled' 
            AND start_time BETWEEN '{$now}' AND DATE_ADD('{$now}', INTERVAL 1 HOUR)
        ");
        
        $remaining_rounds = $future_count ? $future_count['count'] : 0;
        
        if ($remaining_rounds < 10) {
            cronLog("⚠️ 예정된 회차 부족", ['remaining' => $remaining_rounds]);
            cronLog("자동 생성은 관리자 페이지에서 수동으로 진행해주세요");
        } else {
            cronLog("✅ 충분한 예정 회차 확보", ['remaining' => $remaining_rounds]);
        }
    }
    
    cronLog("=== A/B/C 게임 회차 스케줄러 완료 ===");
    
} catch (Exception $e) {
    cronLog("❌ 크론잡 오류", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}

// 웹 실행시 완료 메시지
if (php_sapi_name() !== 'cli') {
    echo "<hr>";
    echo "<h3>✅ A/B/C 게임 회차 스케줄러 완료</h3>";
    echo "<p><a href='./round_pre_admin.php'>🔧 회차 관리로 이동</a></p>";
    echo "<p><a href='./index.php'>🎮 게임으로 이동</a></p>";
    echo "<script>setTimeout(() => location.reload(), 10000);</script>"; // 10초 후 자동 새로고침
}
?>