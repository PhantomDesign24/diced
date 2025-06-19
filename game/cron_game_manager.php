<?php
/*
* 파일명: cron_game_manager.php
* 위치: /game/cron_game_manager.php
* 기능: 서버 중심 회차 자동 관리 (안전한 버전)
* 작성일: 2025-06-12
* 수정일: 2025-06-12
*/

// ===================================
// 그누보드 환경 설정 (경로 자동 탐지)
// ===================================

// 다양한 경로에서 common.php 찾기
$possible_paths = [
    __DIR__ . '/../common.php',
    __DIR__ . '/../../common.php',
    dirname(__DIR__) . '/common.php',
    realpath(__DIR__ . '/..') . '/common.php'
];

$g5_loaded = false;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        include_once($path);
        if (function_exists('sql_query')) {
            $g5_loaded = true;
            break;
        }
    }
}

if (!$g5_loaded) {
    die('그누보드5 환경을 찾을 수 없습니다. common.php 경로를 확인해주세요.');
}

// CLI에서만 실행되도록 제한
if (php_sapi_name() !== 'cli' && !isset($_GET['manual'])) {
    die('이 스크립트는 명령줄에서만 실행할 수 있습니다.');
}

// ===================================
// 로그 함수
// ===================================

/**
 * 로그 기록
 * @param string $message 로그 메시지
 */
function writeLog($message) {
    $log_file = __DIR__ . '/logs/game_cron.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] {$message}" . PHP_EOL;
    
    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
    
    if (php_sapi_name() === 'cli' || isset($_GET['manual'])) {
        echo $log_message;
        if (isset($_GET['manual'])) {
            echo "<br>";
        }
    }
}

// ===================================
// 게임 설정 로드
// ===================================
function loadGameConfig() {
    try {
        $sql = "SELECT * FROM dice_game_config";
        $result = sql_query($sql);
        
        if (!$result) {
            writeLog("ERROR: 게임 설정 조회 실패");
            return array();
        }
        
        $config = array();
        while ($row = sql_fetch_array($result)) {
            $config[$row['config_key']] = $row['config_value'];
        }
        
        writeLog("게임 설정 로드 완료: " . count($config) . "개 설정");
        return $config;
        
    } catch (Exception $e) {
        writeLog("게임 설정 로드 오류: " . $e->getMessage());
        return array();
    }
}

// ===================================
// 메인 게임 관리 로직
// ===================================

writeLog("=== 게임 관리 크론잡 시작 ===");
writeLog("PHP 버전: " . phpversion());
writeLog("현재 경로: " . __DIR__);

try {
    // 함수 존재 여부 재확인
    if (!function_exists('sql_query')) {
        throw new Exception('sql_query 함수가 정의되지 않았습니다.');
    }
    
    if (!function_exists('insert_point')) {
        throw new Exception('insert_point 함수가 정의되지 않았습니다.');
    }
    
    $config = loadGameConfig();
    
    // 게임이 비활성화되어 있으면 종료
    if (!isset($config['game_status']) || $config['game_status'] != '1') {
        writeLog("게임이 비활성화 상태입니다.");
        exit;
    }
    
    $now = date('Y-m-d H:i:s');
    writeLog("현재 시간: {$now}");
    
    // ===================================
    // 1단계: 미처리 베팅들 강제 처리
    // ===================================
    writeLog("미처리 베팅 검색 중...");
    
    $unprocessed_sql = "
        SELECT DISTINCT b.round_id, b.round_number, r.dice1, r.dice2, r.dice3, r.total, r.is_high, r.is_odd
        FROM dice_game_bets b
        LEFT JOIN dice_game_rounds r ON b.round_id = r.round_id
        WHERE b.is_win IS NULL 
        AND r.status = 'completed' 
        AND r.dice1 IS NOT NULL
        ORDER BY b.round_number
    ";
    
    $unprocessed_result = sql_query($unprocessed_sql);
    
    if ($unprocessed_result) {
        $unprocessed_count = 0;
        while ($unprocessed = sql_fetch_array($unprocessed_result)) {
            writeLog("미처리 베팅 발견: {$unprocessed['round_number']}회차");
            $processed = processBettingResults($unprocessed['round_id'], $unprocessed['round_number'], $unprocessed['is_high'], $unprocessed['is_odd']);
            $unprocessed_count++;
        }
        
        if ($unprocessed_count == 0) {
            writeLog("미처리 베팅 없음");
        }
    } else {
        writeLog("ERROR: 미처리 베팅 조회 실패");
    }
    
    // ===================================
    // 2단계: 현재 진행중인 회차 조회
    // ===================================
    $current_round_sql = "SELECT * FROM dice_game_rounds WHERE status IN ('betting', 'waiting') ORDER BY round_number DESC LIMIT 1";
    $current_round = sql_fetch($current_round_sql);
    
    if ($current_round) {
        writeLog("현재 진행중인 회차: {$current_round['round_number']}회차 (상태: {$current_round['status']})");
        
        // ===================================
        // 3단계: 베팅 시간 종료 처리
        // ===================================
        if ($current_round['status'] === 'betting' && $now >= $current_round['end_time']) {
            $update_sql = "UPDATE dice_game_rounds SET status = 'waiting' WHERE round_id = {$current_round['round_id']}";
            if (sql_query($update_sql)) {
                writeLog("{$current_round['round_number']}회차 베팅 마감");
            } else {
                writeLog("ERROR: 베팅 마감 처리 실패");
            }
        }
        
        // ===================================
        // 4단계: 결과 발표 시간 처리
        // ===================================
        if ($current_round['status'] === 'waiting' && $now >= $current_round['result_time']) {
            writeLog("{$current_round['round_number']}회차 결과 발표 시간 도달");
            if (completeRound($current_round['round_id'], $current_round['round_number'], $config)) {
                writeLog("{$current_round['round_number']}회차 완료 및 정산 처리 성공");
            } else {
                writeLog("ERROR: {$current_round['round_number']}회차 완료 처리 실패");
            }
        }
        
        // ===================================
        // 5단계: 새 회차 생성 여부 확인
        // ===================================
        $check_current_sql = "SELECT * FROM dice_game_rounds WHERE round_id = {$current_round['round_id']}";
        $updated_round = sql_fetch($check_current_sql);
        
        if ($updated_round && $updated_round['status'] === 'completed') {
            writeLog("완료된 회차 감지, 새 회차 생성 시도");
            $new_round = createNewRound($config);
            if ($new_round) {
                writeLog("새 회차 생성 완료: {$new_round['round_number']}회차");
            } else {
                writeLog("ERROR: 새 회차 생성 실패");
            }
        }
    } else {
        // ===================================
        // 6단계: 새 회차 생성
        // ===================================
        writeLog("진행중인 회차가 없음, 새 회차 생성");
        $new_round = createNewRound($config);
        if ($new_round) {
            writeLog("새 회차 생성 완료: {$new_round['round_number']}회차");
        } else {
            writeLog("ERROR: 새 회차 생성 실패");
        }
    }
    
} catch (Exception $e) {
    writeLog("ERROR: " . $e->getMessage());
    writeLog("ERROR 파일: " . $e->getFile() . " 라인: " . $e->getLine());
} catch (Error $e) {
    writeLog("FATAL ERROR: " . $e->getMessage());
    writeLog("FATAL ERROR 파일: " . $e->getFile() . " 라인: " . $e->getLine());
}

writeLog("=== 게임 관리 크론잡 종료 ===");

// ===================================
// 회차 생성 함수
// ===================================

/**
 * 새로운 회차 생성
 * @param array $config 게임 설정
 * @return array|null 생성된 회차 정보
 */
function createNewRound($config) {
    try {
        writeLog("새 회차 생성 시작");
        
        $last_round_sql = "SELECT COALESCE(MAX(round_number), 0) + 1 as next_round FROM dice_game_rounds";
        $last_round_result = sql_fetch($last_round_sql);
        $next_round_number = $last_round_result['next_round'];
        
        writeLog("다음 회차 번호: {$next_round_number}");
        
        $now = date('Y-m-d H:i:s');
        $betting_time = isset($config['betting_time']) ? intval($config['betting_time']) : 90;
        $result_time = isset($config['result_time']) ? intval($config['result_time']) : 30;
        
        $end_time = date('Y-m-d H:i:s', strtotime("+{$betting_time} seconds"));
        $result_announce_time = date('Y-m-d H:i:s', strtotime("+{$betting_time} seconds +{$result_time} seconds"));
        
        writeLog("시간 설정: 시작={$now}, 마감={$end_time}, 결과={$result_announce_time}");
        
        $insert_sql = "
            INSERT INTO dice_game_rounds 
            (round_number, start_time, end_time, result_time, status, total_bet_amount, total_players, created_at) 
            VALUES 
            ({$next_round_number}, '{$now}', '{$end_time}', '{$result_announce_time}', 'betting', 0, 0, '{$now}')
        ";
        
        if (sql_query($insert_sql)) {
            $round_id = sql_insert_id();
            writeLog("회차 생성 성공: round_id={$round_id}");
            
            $new_round_sql = "SELECT * FROM dice_game_rounds WHERE round_id = {$round_id}";
            return sql_fetch($new_round_sql);
        } else {
            writeLog("ERROR: 회차 생성 SQL 실패");
            return null;
        }
        
    } catch (Exception $e) {
        writeLog("회차 생성 오류: " . $e->getMessage());
        return null;
    }
}

// ===================================
// 회차 완료 및 정산 함수
// ===================================

/**
 * 회차 완료 처리
 * @param int $round_id 회차 ID
 * @param int $round_number 회차 번호
 * @param array $config 게임 설정
 * @return bool 처리 성공 여부
 */
function completeRound($round_id, $round_number, $config) {
    try {
        writeLog("회차 완료 처리 시작: {$round_number}회차");
        
        // 주사위 굴리기
        $dice1 = rand(1, 6);
        $dice2 = rand(1, 6);
        $dice3 = rand(1, 6);
        $total = $dice1 + $dice2 + $dice3;
        
        $is_high = $total >= 11 ? 1 : 0;
        $is_odd = $total % 2 === 1 ? 1 : 0;
        
        writeLog("주사위 결과: {$dice1}, {$dice2}, {$dice3} = {$total} (" . ($is_high ? '대' : '소') . " " . ($is_odd ? '홀' : '짝') . ")");
        
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
        
        if (!sql_query($update_round_sql)) {
            writeLog("ERROR: 회차 결과 업데이트 실패");
            return false;
        }
        
        writeLog("회차 결과 업데이트 성공");
        
        // 베팅 결과 처리
        $bets_processed = processBettingResults($round_id, $round_number, $is_high, $is_odd);
        writeLog("베팅 처리 완료: {$bets_processed}건");
        
        return true;
        
    } catch (Exception $e) {
        writeLog("회차 완료 오류: " . $e->getMessage());
        return false;
    }
}

/**
 * 베팅 결과 처리 및 당첨금 지급
 * @param int $round_id 회차 ID
 * @param int $round_number 회차 번호
 * @param int $is_high 대소 결과
 * @param int $is_odd 홀짝 결과
 * @return int 처리된 베팅 수
 */
function processBettingResults($round_id, $round_number, $is_high, $is_odd) {
    try {
        writeLog("베팅 결과 처리 시작: {$round_number}회차");
        
        $config = loadGameConfig();
        $high_low_rate = isset($config['win_rate_high_low']) ? floatval($config['win_rate_high_low']) : 1.95;
        $odd_even_rate = isset($config['win_rate_odd_even']) ? floatval($config['win_rate_odd_even']) : 1.95;
        
        writeLog("배율 설정: 대소={$high_low_rate}, 홀짝={$odd_even_rate}");
        
        $bets_sql = "SELECT * FROM dice_game_bets WHERE round_id = {$round_id} AND is_win IS NULL";
        $bets_result = sql_query($bets_sql);
        
        if (!$bets_result) {
            writeLog("ERROR: 베팅 조회 실패");
            return 0;
        }
        
        $processed_count = 0;
        
        while ($bet = sql_fetch_array($bets_result)) {
            writeLog("베팅 처리 중: {$bet['mb_id']} - {$bet['bet_high_low']}/{$bet['bet_odd_even']} - {$bet['bet_amount']}P");
            
            $is_win = 0;
            $win_amount = 0;
            
            // 대소 체크
            $high_low_correct = ($bet['bet_high_low'] === 'high' && $is_high) || 
                               ($bet['bet_high_low'] === 'low' && !$is_high);
            
            // 홀짝 체크
            $odd_even_correct = ($bet['bet_odd_even'] === 'odd' && $is_odd) || 
                               ($bet['bet_odd_even'] === 'even' && !$is_odd);
            
            writeLog("결과 체크: 대소=" . ($high_low_correct ? "맞음" : "틀림") . ", 홀짝=" . ($odd_even_correct ? "맞음" : "틀림"));
            
            // 둘 다 맞으면 당첨
            if ($high_low_correct && $odd_even_correct) {
                $is_win = 1;
                $win_amount = intval($bet['bet_amount'] * $high_low_rate * $odd_even_rate);
                
                // 당첨금 지급
                $win_content = "주사위게임 당첨 ({$round_number}회차)";
                
                try {
                    // po_sum 없는 버전의 포인트 지급
                    $now = date('Y-m-d H:i:s');
                    $expire_date = '9999-12-31';
                    
                    $point_sql = "
                        INSERT INTO g5_point 
                        (mb_id, po_datetime, po_content, po_point, po_use_point, po_expired, po_expire_date, po_mb_point, po_rel_table, po_rel_id, po_rel_action)
                        VALUES 
                        ('{$bet['mb_id']}', '{$now}', '{$win_content}', {$win_amount}, 0, 0, '{$expire_date}', 0, 'dice_game_bets', '{$bet['bet_id']}', '당첨')
                    ";
                    
                    if (sql_query($point_sql)) {
                        writeLog("당첨금 지급 성공: {$bet['mb_id']} - {$bet['bet_amount']}P → {$win_amount}P");
                    } else {
                        writeLog("ERROR: 당첨금 지급 실패 - SQL 오류");
                    }
                } catch (Exception $e) {
                    writeLog("ERROR: 당첨금 지급 실패: " . $e->getMessage());
                }
            } else {
                writeLog("미당첨: {$bet['mb_id']}");
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
            
            if (sql_query($update_bet_sql)) {
                writeLog("베팅 결과 업데이트 성공: bet_id={$bet['bet_id']}");
            } else {
                writeLog("ERROR: 베팅 결과 업데이트 실패: bet_id={$bet['bet_id']}");
            }
            
            $processed_count++;
        }
        
        writeLog("베팅 처리 완료: 총 {$processed_count}건 처리");
        return $processed_count;
        
    } catch (Exception $e) {
        writeLog("베팅 처리 오류: " . $e->getMessage());
        return 0;
    }
}

?>