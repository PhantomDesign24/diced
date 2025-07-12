<?php
/*
* 파일명: round_manager.php
* 위치: /game/includes/round_manager.php
* 기능: 회차 관리 헬퍼 함수들
* 작성일: 2025-06-12
*/

// ===================================
// 회차 생성 및 관리 함수
// ===================================

/**
 * 새로운 회차 생성
 * @return array 생성된 회차 정보
 */
function createNewRound() {
    // 게임 설정 로드
    $config = loadGameConfig();
    
    // 마지막 회차 번호 조회
    $last_round_sql = "SELECT MAX(round_number) as last_round FROM dice_game_rounds";
    $last_round_result = sql_fetch($last_round_sql);
    $next_round_number = ($last_round_result['last_round'] ?? 0) + 1;
    
    // 시간 계산
    $now = new DateTime();
    $start_time = $now->format('Y-m-d H:i:s');
    
    $betting_time = intval($config['betting_time']); // 베팅 시간 (초)
    $result_time = intval($config['result_time']); // 결과 시간 (초)
    
    $end_time = clone $now;
    $end_time->add(new DateInterval('PT' . $betting_time . 'S'));
    
    $result_announce_time = clone $end_time;
    $result_announce_time->add(new DateInterval('PT' . $result_time . 'S'));
    
    // 회차 생성
    $insert_sql = "
        INSERT INTO dice_game_rounds 
        (round_number, start_time, end_time, result_time, status, created_at) 
        VALUES 
        ({$next_round_number}, '{$start_time}', '{$end_time->format('Y-m-d H:i:s')}', '{$result_announce_time->format('Y-m-d H:i:s')}', 'betting', '{$start_time}')
    ";
    
    sql_query($insert_sql);
    $round_id = sql_insert_id();
    
    // 생성된 회차 정보 반환
    $new_round_sql = "SELECT * FROM dice_game_rounds WHERE round_id = {$round_id}";
    return sql_fetch($new_round_sql);
}

/**
 * 회차 완료 처리
 * @param int $round_id 회차 ID
 * @return bool 처리 성공 여부
 */
function completeRound($round_id) {
    try {
        sql_query("START TRANSACTION");
        
        // 주사위 굴리기
        $dice1 = rand(1, 6);
        $dice2 = rand(1, 6);
        $dice3 = rand(1, 6);
        $total = $dice1 + $dice2 + $dice3;
        
        $is_high = $total >= 11 ? 1 : 0;
        $is_odd = $total % 2 === 1 ? 1 : 0;
        
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
        
        // 베팅 결과 처리
        processBettingResults($round_id, $is_high, $is_odd);
        
        // 히스토리 생성
        createRoundHistory($round_id);
        
        sql_query("COMMIT");
        return true;
        
    } catch (Exception $e) {
        sql_query("ROLLBACK");
        return false;
    }
}

/**
 * 베팅 결과 처리 및 당첨금 지급
 * @param int $round_id 회차 ID
 * @param int $is_high 대소 결과 (1:대, 0:소)
 * @param int $is_odd 홀짝 결과 (1:홀, 0:짝)
 */
function processBettingResults($round_id, $is_high, $is_odd) {
    $config = loadGameConfig();
    $high_low_rate = floatval($config['win_rate_high_low']);
    $odd_even_rate = floatval($config['win_rate_odd_even']);
    
    // 모든 베팅 조회
    $bets_sql = "SELECT * FROM dice_game_bets WHERE round_id = {$round_id} AND is_win IS NULL";
    $bets_result = sql_query($bets_sql);
    
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
            $win_content = "주사위게임 당첨 ({$bet['round_number']}회차)";
            insert_point($bet['mb_id'], $win_amount, $win_content, '@dice_game', $bet['mb_id'], '주사위게임');
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
    }
}

/**
 * 회차 히스토리 생성
 * @param int $round_id 회차 ID
 */
function createRoundHistory($round_id) {
    // 회차 정보 조회
    $round_sql = "SELECT * FROM dice_game_rounds WHERE round_id = {$round_id}";
    $round = sql_fetch($round_sql);
    
    if (!$round) return;
    
    // 베팅 통계 계산
    $stats_sql = "
        SELECT 
            COUNT(*) as total_bets,
            SUM(bet_amount) as total_amount,
            SUM(win_amount) as total_win_amount
        FROM dice_game_bets 
        WHERE round_id = {$round_id}
    ";
    $stats = sql_fetch($stats_sql);
    
    $house_profit = $stats['total_amount'] - $stats['total_win_amount'];
    $result_high_low = $round['is_high'] ? 'high' : 'low';
    $result_odd_even = $round['is_odd'] ? 'odd' : 'even';
    $completed_at = date('Y-m-d H:i:s');
    
    // 히스토리 저장
    $history_sql = "
        INSERT INTO dice_game_history 
        (round_number, total_bets, total_amount, total_win_amount, house_profit, 
         dice1, dice2, dice3, total, result_high_low, result_odd_even, completed_at)
        VALUES 
        ({$round['round_number']}, {$stats['total_bets']}, {$stats['total_amount']}, 
         {$stats['total_win_amount']}, {$house_profit}, {$round['dice1']}, 
         {$round['dice2']}, {$round['dice3']}, {$round['total']}, 
         '{$result_high_low}', '{$result_odd_even}', '{$completed_at}')
    ";
    sql_query($history_sql);
}

/**
 * 게임 설정 로드
 * @return array 게임 설정 배열
 */
function loadGameConfig() {
    static $config = null;
    
    if ($config === null) {
        $sql = "SELECT * FROM dice_game_config";
        $result = sql_query($sql);
        $config = array();
        while ($row = sql_fetch_array($result)) {
            $config[$row['config_key']] = $row['config_value'];
        }
    }
    
    return $config;
}

/**
 * 자동 회차 관리 (크론잡용)
 */
function autoRoundManagement() {
    $now = date('Y-m-d H:i:s');
    
    // 완료되어야 할 회차들 처리
    $complete_sql = "
        SELECT round_id FROM dice_game_rounds 
        WHERE status = 'waiting' AND result_time <= '{$now}'
    ";
    $complete_result = sql_query($complete_sql);
    
    while ($row = sql_fetch_array($complete_result)) {
        completeRound($row['round_id']);
    }
    
    // 새 회차 생성이 필요한지 체크
    $active_sql = "
        SELECT COUNT(*) as cnt FROM dice_game_rounds 
        WHERE status IN ('betting', 'waiting')
    ";
    $active_result = sql_fetch($active_sql);
    
    if ($active_result['cnt'] == 0) {
        createNewRound();
    }
}

// ===================================
// 베팅 상태 변경 함수
// ===================================

/**
 * 베팅 시간 종료 처리
 * @param int $round_id 회차 ID
 */
function endBettingTime($round_id) {
    $update_sql = "UPDATE dice_game_rounds SET status = 'waiting' WHERE round_id = {$round_id}";
    sql_query($update_sql);
}

/**
 * 현재 진행중인 회차 상태 체크 및 업데이트
 */
function checkAndUpdateRoundStatus() {
    $now = date('Y-m-d H:i:s');
    
    // 베팅 시간이 끝난 회차들을 대기 상태로 변경
    $update_betting_sql = "
        UPDATE dice_game_rounds 
        SET status = 'waiting' 
        WHERE status = 'betting' AND end_time <= '{$now}'
    ";
    sql_query($update_betting_sql);
    
    // 결과 발표 시간이 된 회차들 완료 처리
    $result_ready_sql = "
        SELECT round_id FROM dice_game_rounds 
        WHERE status = 'waiting' AND result_time <= '{$now}'
    ";
    $result_ready = sql_query($result_ready_sql);
    
    while ($row = sql_fetch_array($result_ready)) {
        completeRound($row['round_id']);
    }
}

?>