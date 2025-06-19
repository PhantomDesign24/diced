<?php
/*
* 파일명: bet_process.php
* 위치: /game/bet_process.php
* 기능: 베팅 처리 로직 (po_sum 컬럼 없는 테이블용)
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
// 수동 포인트 처리 함수 (po_sum 없는 버전)
// ===================================

/**
 * 수동 포인트 지급/차감 (현재 테이블 구조에 맞춤)
 * @param string $mb_id 회원 ID
 * @param int $point 포인트 (음수면 차감)
 * @param string $content 내용
 * @param string $rel_table 관련 테이블
 * @param string $rel_id 관련 ID
 * @param string $rel_action 액션
 * @return bool 성공 여부
 */
function manual_insert_point_no_sum($mb_id, $point, $content, $rel_table = '', $rel_id = '', $rel_action = '') {
    try {
        $now = date('Y-m-d H:i:s');
        $expire_date = '9999-12-31';
        
        // po_sum 컬럼 없이 삽입
        $insert_sql = "
            INSERT INTO g5_point 
            (mb_id, po_datetime, po_content, po_point, po_use_point, po_expired, po_expire_date, po_mb_point, po_rel_table, po_rel_id, po_rel_action)
            VALUES 
            ('{$mb_id}', '{$now}', '{$content}', {$point}, 0, 0, '{$expire_date}', 0, '{$rel_table}', '{$rel_id}', '{$rel_action}')
        ";
        
        return sql_query($insert_sql);
        
    } catch (Exception $e) {
        return false;
    }
}

/**
 * 현재 포인트 조회 (po_sum 컬럼 없이)
 * @param string $mb_id 회원 ID
 * @return int 현재 포인트
 */
function manual_get_point_sum_no_sum($mb_id) {
    try {
        $sql = "SELECT COALESCE(SUM(po_point), 0) as total_point FROM g5_point WHERE mb_id = '{$mb_id}'";
        $result = sql_fetch($sql);
        return intval($result['total_point']);
    } catch (Exception $e) {
        return 0;
    }
}

// ===================================
// 입력값 검증
// ===================================

// 로그인 체크
if (!$is_member) {
    echo json_encode([
        'success' => false,
        'message' => '로그인이 필요합니다.'
    ]);
    exit;
}

// POST 데이터 검증
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => '잘못된 요청입니다.'
    ]);
    exit;
}

$round_id = isset($_POST['round_id']) ? intval($_POST['round_id']) : 0;
$round_number = isset($_POST['round_number']) ? intval($_POST['round_number']) : 0;
$high_low = isset($_POST['high_low']) ? trim($_POST['high_low']) : '';
$odd_even = isset($_POST['odd_even']) ? trim($_POST['odd_even']) : '';
$bet_amount = isset($_POST['bet_amount']) ? intval($_POST['bet_amount']) : 0;

// 베팅 타입 검증
$valid_high_low = ['high', 'low'];
$valid_odd_even = ['odd', 'even'];

if (!in_array($high_low, $valid_high_low) || !in_array($odd_even, $valid_odd_even)) {
    echo json_encode([
        'success' => false,
        'message' => '잘못된 베팅 타입입니다.'
    ]);
    exit;
}

// ===================================
// 게임 설정 로드
// ===================================
$sql = "SELECT * FROM dice_game_config";
$result = sql_query($sql);

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => '게임 설정을 불러올 수 없습니다.'
    ]);
    exit;
}

$config = array();
while ($row = sql_fetch_array($result)) {
    $config[$row['config_key']] = $row['config_value'];
}

// 게임 활성 상태 체크
if (!isset($config['game_status']) || $config['game_status'] != '1') {
    echo json_encode([
        'success' => false,
        'message' => '현재 게임이 중단되었습니다.'
    ]);
    exit;
}

// ===================================
// 회차 및 베팅 시간 검증
// ===================================
$round_sql = "SELECT * FROM dice_game_rounds WHERE round_id = {$round_id} AND status = 'betting'";
$round_info = sql_fetch($round_sql);

if (!$round_info) {
    echo json_encode([
        'success' => false,
        'message' => '유효하지 않은 회차이거나 베팅 시간이 종료되었습니다.'
    ]);
    exit;
}

// 베팅 마감 시간 체크
$now = date('Y-m-d H:i:s');
if ($now > $round_info['end_time']) {
    echo json_encode([
        'success' => false,
        'message' => '베팅 시간이 종료되었습니다.'
    ]);
    exit;
}

// ===================================
// 베팅 금액 검증
// ===================================
$min_bet = isset($config['min_bet']) ? intval($config['min_bet']) : 1000;
$max_bet = isset($config['max_bet']) ? intval($config['max_bet']) : 100000;

if ($bet_amount < $min_bet || $bet_amount > $max_bet) {
    echo json_encode([
        'success' => false,
        'message' => "베팅 금액은 {$min_bet}P ~ {$max_bet}P 사이여야 합니다."
    ]);
    exit;
}

// 회원 포인트 체크
$member_point = manual_get_point_sum_no_sum($member['mb_id']);

if ($member_point < $bet_amount) {
    echo json_encode([
        'success' => false,
        'message' => '보유 포인트가 부족합니다.',
        'debug' => [
            'current_point' => $member_point,
            'bet_amount' => $bet_amount
        ]
    ]);
    exit;
}

// ===================================
// 베팅 처리
// ===================================
try {
    // 베팅 전 포인트 확인
    $before_point = manual_get_point_sum_no_sum($member['mb_id']);
    
    // 포인트 차감
    $point_content = "주사위게임 베팅 ({$round_number}회차)";
    $point_result = manual_insert_point_no_sum($member['mb_id'], -$bet_amount, $point_content, 'dice_game_bets', '0', '베팅');
    
    // 포인트 차감 실패 체크
    if (!$point_result) {
        echo json_encode([
            'success' => false,
            'message' => '포인트 차감에 실패했습니다.',
            'debug' => [
                'before_point' => $before_point,
                'bet_amount' => $bet_amount,
                'member_id' => $member['mb_id']
            ]
        ]);
        exit;
    }
    
    // 포인트 차감 확인
    $after_point = manual_get_point_sum_no_sum($member['mb_id']);
    
    if ($after_point != ($before_point - $bet_amount)) {
        echo json_encode([
            'success' => false,
            'message' => '포인트 차감 확인 실패',
            'debug' => [
                'before' => $before_point,
                'after' => $after_point,
                'expected' => $before_point - $bet_amount,
                'bet_amount' => $bet_amount
            ]
        ]);
        exit;
    }
    
    // 베팅 정보 저장
    $now = date('Y-m-d H:i:s');
    $bet_sql = "
        INSERT INTO dice_game_bets 
        (round_id, round_number, mb_id, mb_name, bet_high_low, bet_odd_even, bet_amount, created_at) 
        VALUES 
        ({$round_id}, {$round_number}, '{$member['mb_id']}', '{$member['mb_name']}', '{$high_low}', '{$odd_even}', {$bet_amount}, '{$now}')
    ";
    
    if (!sql_query($bet_sql)) {
        // 베팅 저장 실패 시 포인트 복구
        manual_insert_point_no_sum($member['mb_id'], $bet_amount, "주사위게임 베팅 취소 ({$round_number}회차)", 'dice_game_bets', '0', '베팅취소');
        
        echo json_encode([
            'success' => false,
            'message' => '베팅 정보 저장에 실패했습니다.'
        ]);
        exit;
    }
    
    $bet_id = sql_insert_id();
    
    // 회차 정보 업데이트
    $update_round_sql = "
        UPDATE dice_game_rounds 
        SET total_bet_amount = COALESCE(total_bet_amount, 0) + {$bet_amount},
            total_players = COALESCE(total_players, 0) + 1
        WHERE round_id = {$round_id}
    ";
    sql_query($update_round_sql);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '베팅 처리 중 오류가 발생했습니다.',
        'error' => $e->getMessage()
    ]);
    exit;
}

// ===================================
// 응답 데이터 생성
// ===================================

// 최신 포인트 조회
$new_point = manual_get_point_sum_no_sum($member['mb_id']);

$response = [
    'success' => true,
    'message' => '베팅이 완료되었습니다.',
    'bet_id' => $bet_id,
    'round_number' => $round_number,
    'bet_high_low' => $high_low,
    'bet_odd_even' => $odd_even,
    'bet_amount' => $bet_amount,
    'before_point' => $before_point,
    'new_point' => $new_point,
    'point_deducted' => $before_point - $new_point
];

echo json_encode($response);
?>