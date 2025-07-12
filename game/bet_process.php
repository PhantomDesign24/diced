<?php
/*
* 파일명: bet_process.php
* 위치: /game/bet_process.php
* 기능: A/B/C 게임 베팅 처리 로직
* 작성일: 2025-01-07
* 수정일: 2025-01-07 (A/B/C 게임으로 전환)
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
$bet_amount = isset($_POST['bet_amount']) ? intval($_POST['bet_amount']) : 0;
$bets = isset($_POST['bets']) ? $_POST['bets'] : [];

// 베팅 선택 검증
if (empty($bets) || !is_array($bets)) {
    echo json_encode([
        'success' => false,
        'message' => '베팅을 선택해주세요.'
    ]);
    exit;
}

// 유효한 게임 타입과 옵션
$valid_games = ['A', 'B', 'C'];
$valid_options = ['1', '2'];

// 베팅 데이터 검증 및 정리
$validated_bets = [];
foreach ($bets as $game_type => $options) {
    if (!in_array($game_type, $valid_games)) {
        echo json_encode([
            'success' => false,
            'message' => "잘못된 게임 타입입니다: {$game_type}"
        ]);
        exit;
    }
    
    if (!is_array($options)) {
        continue;
    }
    
    foreach ($options as $bet_option => $selected) {
        if (!in_array($bet_option, $valid_options)) {
            echo json_encode([
                'success' => false,
                'message' => "잘못된 베팅 옵션입니다: {$bet_option}"
            ]);
            exit;
        }
        
        // 선택된 베팅만 저장
        if ($selected == '1' || $selected == 1 || $selected === true) {
            $validated_bets[] = [
                'game_type' => $game_type,
                'bet_option' => $bet_option
            ];
        }
    }
}

// 선택된 베팅이 없는 경우
if (empty($validated_bets)) {
    echo json_encode([
        'success' => false,
        'message' => '베팅을 선택해주세요.'
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
        'message' => "베팅 금액은 " . number_format($min_bet) . "P ~ " . number_format($max_bet) . "P 사이여야 합니다."
    ]);
    exit;
}

// 총 베팅 금액 계산 (각 베팅당 bet_amount)
$total_bet_amount = $bet_amount * count($validated_bets);

// 회원 포인트 체크
$member_point = manual_get_point_sum_no_sum($member['mb_id']);

if ($member_point < $total_bet_amount) {
    echo json_encode([
        'success' => false,
        'message' => '보유 포인트가 부족합니다. (필요: ' . number_format($total_bet_amount) . 'P)',
        'debug' => [
            'current_point' => $member_point,
            'total_bet_amount' => $total_bet_amount,
            'bet_count' => count($validated_bets)
        ]
    ]);
    exit;
}

// ===================================
// 중복 베팅 체크
// ===================================
foreach ($validated_bets as $bet) {
    $check_sql = "
        SELECT bet_id FROM dice_game_bets 
        WHERE round_id = {$round_id} 
        AND mb_id = '{$member['mb_id']}'
        AND game_type = '{$bet['game_type']}'
    ";
    $existing = sql_fetch($check_sql);
    
    if ($existing) {
        echo json_encode([
            'success' => false,
            'message' => "{$bet['game_type']} 게임에 이미 베팅하셨습니다."
        ]);
        exit;
    }
}

// ===================================
// 베팅 처리
// ===================================
try {
    // 트랜잭션 시작
    sql_query("START TRANSACTION");
    
    // 베팅 전 포인트 확인
    $before_point = manual_get_point_sum_no_sum($member['mb_id']);
    
    // 포인트 차감
    $bet_details = [];
    foreach ($validated_bets as $bet) {
        $bet_details[] = "{$bet['game_type']}{$bet['bet_option']}";
    }
    $bet_description = implode(', ', $bet_details);
    
    $point_content = "{$round_number}회차 베팅 ({$bet_description})";
    $point_result = manual_insert_point_no_sum(
        $member['mb_id'], 
        -$total_bet_amount, 
        $point_content, 
        'dice_game_bets', 
        $round_id, 
        '베팅'
    );
    
    // 포인트 차감 실패 체크
    if (!$point_result) {
        sql_query("ROLLBACK");
        echo json_encode([
            'success' => false,
            'message' => '포인트 차감에 실패했습니다.'
        ]);
        exit;
    }
    
    // 각 베팅 정보 저장
    $now = date('Y-m-d H:i:s');
    $saved_bet_ids = [];
    
    foreach ($validated_bets as $bet) {
        $bet_sql = "
            INSERT INTO dice_game_bets 
            (round_id, mb_id, game_type, bet_option, bet_amount, status, created_at) 
            VALUES 
            ({$round_id}, '{$member['mb_id']}', '{$bet['game_type']}', '{$bet['bet_option']}', 
             {$bet_amount}, 'pending', '{$now}')
        ";
        
        if (!sql_query($bet_sql)) {
            sql_query("ROLLBACK");
            
            // 포인트 복구
            manual_insert_point_no_sum(
                $member['mb_id'], 
                $total_bet_amount, 
                "{$round_number}회차 베팅 취소", 
                'dice_game_bets', 
                $round_id, 
                '베팅취소'
            );
            
            echo json_encode([
                'success' => false,
                'message' => '베팅 정보 저장에 실패했습니다.'
            ]);
            exit;
        }
        
        $saved_bet_ids[] = sql_insert_id();
    }
    
    // 회차 정보 업데이트 (총 베팅금액과 참여자 수)
    $update_round_sql = "
        UPDATE dice_game_rounds 
        SET total_bet_amount = COALESCE(total_bet_amount, 0) + {$total_bet_amount},
            total_players = (
                SELECT COUNT(DISTINCT mb_id) 
                FROM dice_game_bets 
                WHERE round_id = {$round_id}
            ),
            updated_at = NOW()
        WHERE round_id = {$round_id}
    ";
    
    if (!sql_query($update_round_sql)) {
        sql_query("ROLLBACK");
        echo json_encode([
            'success' => false,
            'message' => '회차 정보 업데이트에 실패했습니다.'
        ]);
        exit;
    }
    
    // 트랜잭션 커밋
    sql_query("COMMIT");
    
} catch (Exception $e) {
    sql_query("ROLLBACK");
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

// 예상 당첨금 계산
$expected_win = 0;
foreach ($validated_bets as $bet) {
    $rate_key = "game_" . strtolower($bet['game_type']) . $bet['bet_option'] . "_rate";
    $rate = isset($config[$rate_key]) ? floatval($config[$rate_key]) : 2.0;
    $expected_win += floor($bet_amount * $rate);
}

$response = [
    'success' => true,
    'message' => '베팅이 완료되었습니다.',
    'bet_ids' => $saved_bet_ids,
    'round_number' => $round_number,
    'bets' => $validated_bets,
    'bet_description' => $bet_description,
    'bet_amount_per_game' => $bet_amount,
    'total_bet_amount' => $total_bet_amount,
    'before_point' => $before_point,
    'new_point' => $new_point,
    'point_deducted' => $before_point - $new_point,
    'expected_win' => $expected_win
];

echo json_encode($response);
?>