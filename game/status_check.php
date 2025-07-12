<?php
/*
* 파일명: status_check.php
* 위치: /game/status_check.php
* 기능: 게임 상태 체크 API (미리 생성된 회차 시스템)
* 작성일: 2025-06-12
* 수정일: 2025-06-12
*/

// ===================================
// 그누보드 환경 설정
// ===================================
include_once('../common.php');

// JSON 응답 헤더 설정
header('Content-Type: application/json; charset=utf-8');

// ===================================
// 상태 자동 전환 (적극적 처리)
// ===================================

$now = time();
$now_mysql = date('Y-m-d H:i:s');
$status_changes = [];

// 1단계: 만료된 waiting 회차들을 completed로 전환
$expired_waiting_sql = "
    UPDATE dice_game_rounds 
    SET status = 'completed' 
    WHERE status = 'waiting' 
    AND result_time <= '{$now_mysql}'
";
if (sql_query($expired_waiting_sql)) {
    // 그누보드의 sql_affected_rows 함수 사용 또는 MySQL 함수 직접 사용
    if (function_exists('sql_affected_rows')) {
        $affected = sql_affected_rows();
    } else {
        // 그누보드 환경에서 affected rows 확인하는 다른 방법
        $check_result = sql_fetch("SELECT ROW_COUNT() as affected");
        $affected = $check_result ? $check_result['affected'] : 0;
    }
    
    if ($affected > 0) {
        $status_changes[] = "waiting -> completed: {$affected}개";
    }
}

// 2단계: 만료된 betting 회차들을 waiting으로 전환
$expired_betting_sql = "
    UPDATE dice_game_rounds 
    SET status = 'waiting' 
    WHERE status = 'betting' 
    AND end_time <= '{$now_mysql}'
";
if (sql_query($expired_betting_sql)) {
    if (function_exists('sql_affected_rows')) {
        $affected = sql_affected_rows();
    } else {
        $check_result = sql_fetch("SELECT ROW_COUNT() as affected");
        $affected = $check_result ? $check_result['affected'] : 0;
    }
    
    if ($affected > 0) {
        $status_changes[] = "betting -> waiting: {$affected}개";
    }
}

// 3단계: 시작 시간이 된 scheduled 회차들을 betting으로 전환
$ready_scheduled_sql = "
    UPDATE dice_game_rounds 
    SET status = 'betting' 
    WHERE status = 'scheduled' 
    AND start_time <= '{$now_mysql}'
";
if (sql_query($ready_scheduled_sql)) {
    if (function_exists('sql_affected_rows')) {
        $affected = sql_affected_rows();
    } else {
        $check_result = sql_fetch("SELECT ROW_COUNT() as affected");
        $affected = $check_result ? $check_result['affected'] : 0;
    }
    
    if ($affected > 0) {
        $status_changes[] = "scheduled -> betting: {$affected}개";
    }
}

// ===================================
// 현재 회차 정보 조회
// ===================================

// 현재 진행중인 회차 조회 (betting 또는 waiting 상태)
$current_round_sql = "
    SELECT * FROM dice_game_rounds 
    WHERE status IN ('betting', 'waiting') 
    ORDER BY round_number ASC 
    LIMIT 1
";
$current_round = sql_fetch($current_round_sql);

// 진행중인 회차가 없으면 다음 예정된 회차 확인
if (!$current_round) {
	$next_round_sql = "
		SELECT * FROM dice_game_rounds 
		WHERE status = 'scheduled' 
		ORDER BY round_number ASC 
		LIMIT 1
	";
    $next_round = sql_fetch($next_round_sql);
    
    if ($next_round) {
        $start_time = strtotime($next_round['start_time']);
        
        // 시작 시간이 되었으면 즉시 betting 상태로 변경
        if ($now >= $start_time) {
            $update_sql = "UPDATE dice_game_rounds SET status = 'betting' WHERE round_id = {$next_round['round_id']}";
            if (sql_query($update_sql)) {
                $current_round = $next_round;
                $current_round['status'] = 'betting';
                $status_changes[] = "즉시 전환: scheduled -> betting (회차 {$next_round['round_number']})";
            }
        } else {
            // 아직 시작 시간이 안됨
            $current_round = $next_round;
        }
    }
}

if (!$current_round) {
    echo json_encode([
        'success' => false,
        'message' => '진행중인 회차가 없습니다.'
    ]);
    exit;
}

// ===================================
// 게임 단계 판정
// ===================================
$start_time = strtotime($current_round['start_time']);
$end_time = strtotime($current_round['end_time']);
$result_time = strtotime($current_round['result_time']);

$phase = 'scheduled';
$status_changed = false;

if ($current_round['status'] === 'scheduled') {
    if ($now >= $start_time) {
        // scheduled -> betting 전환
        $update_sql = "UPDATE dice_game_rounds SET status = 'betting' WHERE round_id = {$current_round['round_id']}";
        if (sql_query($update_sql)) {
            $current_round['status'] = 'betting';
            $phase = 'betting';
            $status_changed = true;
        }
    } else {
        $phase = 'scheduled';
    }
} elseif ($current_round['status'] === 'betting') {
    if ($now > $end_time) {
        // betting -> waiting 전환
        $update_sql = "UPDATE dice_game_rounds SET status = 'waiting' WHERE round_id = {$current_round['round_id']}";
        if (sql_query($update_sql)) {
            $current_round['status'] = 'waiting';
            $phase = 'waiting';
            $status_changed = true;
        }
    } else {
        $phase = 'betting';
    }
} elseif ($current_round['status'] === 'waiting') {
    if ($now > $result_time) {
        // waiting -> completed 전환은 크론잡에서 처리
        $phase = 'result';
    } else {
        $phase = 'waiting';
    }
}

// ===================================
// 응답 데이터 생성
// ===================================
$response = [
    'success' => true,
    'round_id' => $current_round['round_id'],
    'round_number' => $current_round['round_number'],
    'phase' => $phase,
    'status' => $current_round['status'],
    'start_time' => $current_round['start_time'],
    'end_time' => $current_round['end_time'],
    'result_time' => $current_round['result_time'],
    'total_players' => $current_round['total_players'] ?? 0,
    'total_bet_amount' => $current_round['total_bet_amount'] ?? 0
];

// 현재 시간 정보 추가
$response['server_time'] = date('Y-m-d H:i:s');
$response['timestamps'] = [
    'now' => $now,
    'start' => $start_time,
    'end' => $end_time,
    'result' => $result_time
];

// 상태 변경 로그 추가
if (!empty($status_changes)) {
    $response['status_changes'] = $status_changes;
    $response['status_changed'] = true;
} else {
    $response['status_changed'] = false;
}

// 타이머 계산
if ($phase === 'scheduled') {
    $response['time_remaining'] = max(0, $start_time - $now);
    $response['target_time'] = $current_round['start_time'];
} elseif ($phase === 'betting') {
    $response['time_remaining'] = max(0, $end_time - $now);
    $response['target_time'] = $current_round['end_time'];
} elseif ($phase === 'waiting') {
    $response['time_remaining'] = max(0, $result_time - $now);
    $response['target_time'] = $current_round['result_time'];
} else {
    $response['time_remaining'] = 0;
    $response['target_time'] = $current_round['result_time'];
}

// 결과가 나온 경우 결과 정보 포함
if ($current_round['dice1'] !== null && $current_round['status'] === 'completed') {
    $response['result'] = [
        'dice1' => (int)$current_round['dice1'],
        'dice2' => (int)$current_round['dice2'],
        'dice3' => (int)$current_round['dice3'],
        'total' => (int)$current_round['total'],
        'is_high' => (int)$current_round['is_high'],
        'is_odd' => (int)$current_round['is_odd']
    ];
    $response['phase'] = 'completed';
}

// ===================================
// 다음 회차 정보 (옵션)
// ===================================
if ($current_round['status'] === 'completed') {
    $next_round = sql_fetch("
        SELECT * FROM dice_game_rounds 
        WHERE status = 'scheduled' 
        ORDER BY start_time ASC 
        LIMIT 1
    ");
    
    if ($next_round) {
        $response['next_round'] = [
            'round_id' => $next_round['round_id'],
            'round_number' => $next_round['round_number'],
            'start_time' => $next_round['start_time']
        ];
    }
}

echo json_encode($response);
?>