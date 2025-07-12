<?php
/*
 * 파일명: cron_auto_round_generator.php
 * 위치: /game/cron_auto_round_generator.php
 * 기능: 자동으로 회차를 생성하는 크론잡
 * 작성일: 2025-01-07
 */

include_once(__DIR__ . '/../common.php');

// ===================================
// 설정값 조회
// ===================================
function getConfig($key, $default = '') {
    $sql = "SELECT config_value FROM dice_game_config WHERE config_key = '{$key}'";
    $result = sql_fetch($sql);
    return $result ? $result['config_value'] : $default;
}

// ===================================
// 로그 함수
// ===================================
function autoGenLog($message, $data = []) {
    $log_file = __DIR__ . '/logs/auto_generator_' . date('Y-m-d') . '.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $log_entry = date('Y-m-d H:i:s') . ' | ' . $message;
    if (!empty($data)) {
        $log_entry .= ' | ' . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    $log_entry .= PHP_EOL;
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
    // 웹 실행시 화면 출력
    if (php_sapi_name() !== 'cli') {
        echo date('H:i:s') . " - " . $message;
        if (!empty($data)) {
            echo " " . json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        echo "<br>";
        flush();
    }
}

try {
    autoGenLog("=== 자동 회차 생성 시작 ===");
    
    // 자동 생성 활성화 확인
    $auto_generate = getConfig('auto_generate_rounds', '0');
    if ($auto_generate !== '1') {
        autoGenLog("자동 생성 비활성화 상태");
        exit;
    }
    
    // 설정값 가져오기
    $generate_count = (int)getConfig('auto_generate_count', '20');
    $betting_time = (int)getConfig('betting_time', '90');
    $result_time = (int)getConfig('result_time', '30');
    $game_interval = $betting_time + $result_time;
    
    // 현재 예정된 회차 수 확인
    $future_count_sql = "
        SELECT COUNT(*) as count 
        FROM dice_game_rounds 
        WHERE status = 'scheduled' 
        AND start_time > NOW()
    ";
    $future_count_result = sql_fetch($future_count_sql);
    $scheduled_count = $future_count_result ? $future_count_result['count'] : 0;
    
    autoGenLog("현재 예정된 회차 수", ['count' => $scheduled_count]);
    
    // 생성이 필요한지 확인 (예정된 회차가 10개 미만일 때)
    if ($scheduled_count >= 10) {
        autoGenLog("충분한 예정 회차 존재. 생성 스킵");
        exit;
    }
    
    // 생성할 회차 수 계산
    $rounds_to_generate = min($generate_count, 50 - $scheduled_count);
    autoGenLog("생성할 회차 수", ['count' => $rounds_to_generate]);
    
    // 시작 회차 번호 계산
    $max_round = sql_fetch("SELECT MAX(round_number) as max_round FROM dice_game_rounds");
    $start_round = ($max_round && $max_round['max_round']) ? $max_round['max_round'] + 1 : 1;
    
    // 시작 시간 계산
    $last_round_time = sql_fetch("
        SELECT result_time 
        FROM dice_game_rounds 
        WHERE status IN ('completed', 'betting', 'waiting', 'scheduled')
        ORDER BY result_time DESC 
        LIMIT 1
    ");
    
    if ($last_round_time) {
        $base_time = strtotime($last_round_time['result_time']) + $game_interval;
        $now = time();
        if ($base_time <= $now) {
            $base_time = $now + 300; // 5분 후부터 시작
        }
    } else {
        $base_time = time() + 300;
    }
    
    // 회차 생성
    $generated = 0;
    for ($i = 0; $i < $rounds_to_generate; $i++) {
        $round_number = $start_round + $i;
        
        // 시간 계산
        $round_start = $base_time + ($i * $game_interval);
        $round_end = $round_start + $betting_time;
        $round_result = $round_end + $result_time;
        
        $start_time_str = date('Y-m-d H:i:s', $round_start);
        $end_time_str = date('Y-m-d H:i:s', $round_end);
        $result_time_str = date('Y-m-d H:i:s', $round_result);
        
        // 랜덤 결과 생성
        $game_a_result = rand(1, 2);
        $game_b_result = rand(1, 2);
        $game_c_result = rand(1, 2);
        
        // 회차 삽입
        $insert_sql = "
            INSERT INTO dice_game_rounds 
            (round_number, start_time, end_time, result_time, status, 
             game_a_result, game_b_result, game_c_result, created_at)
            VALUES 
            ({$round_number}, '{$start_time_str}', '{$end_time_str}', '{$result_time_str}', 
             'scheduled', '{$game_a_result}', '{$game_b_result}', '{$game_c_result}', NOW())
        ";
        
        if (sql_query($insert_sql)) {
            $generated++;
            autoGenLog("회차 생성 성공", [
                'round' => $round_number,
                'start' => $start_time_str,
                'results' => "A{$game_a_result}, B{$game_b_result}, C{$game_c_result}"
            ]);
        } else {
            autoGenLog("회차 생성 실패", ['round' => $round_number, 'error' => sql_error()]);
        }
    }
    
    autoGenLog("=== 자동 회차 생성 완료 ===", ['generated' => $generated]);
    
} catch (Exception $e) {
    autoGenLog("❌ 오류 발생", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}

// 웹 실행시 완료 메시지
if (php_sapi_name() !== 'cli') {
    echo "<hr>";
    echo "<h3>✅ 자동 회차 생성 완료</h3>";
    echo "<p><a href='./round_pre_admin.php'>회차 관리로 이동</a></p>";
}
?>