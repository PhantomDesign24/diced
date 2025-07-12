<?php
/*
* 파일명: payment_process.php
* 위치: /game/payment_process.php
* 기능: 충전/출금 신청 처리
* 작성일: 2025-06-12
* 수정일: 2025-06-13 (출금 시 포인트 차감 제거)
*/

// ===================================
// 그누보드 환경 설정
// ===================================
include_once('./../common.php');

// JSON 응답 헤더 설정
header('Content-Type: application/json; charset=utf-8');

// ===================================
// 로그 함수
// ===================================
function writePaymentLog($message) {
    $log_file = __DIR__ . '/logs/payment.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[{$timestamp}] {$message}" . PHP_EOL;
    
    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
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

// POST 요청 확인
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => '잘못된 요청입니다.'
    ]);
    exit;
}

$request_type = isset($_POST['request_type']) ? trim($_POST['request_type']) : '';
$amount = isset($_POST['amount']) ? intval($_POST['amount']) : 0;

// 요청 타입 검증
if (!in_array($request_type, ['charge', 'withdraw'])) {
    echo json_encode([
        'success' => false,
        'message' => '잘못된 요청 타입입니다.'
    ]);
    exit;
}

writePaymentLog("신청 시작: {$member['mb_id']} - {$request_type} - {$amount}원");

// ===================================
// 시스템 설정 로드
// ===================================
$payment_config_sql = "SELECT * FROM payment_config";
$payment_config_result = sql_query($payment_config_sql);
$payment_config = array();
while ($row = sql_fetch_array($payment_config_result)) {
    $payment_config[$row['config_key']] = $row['config_value'];
}

// 시스템 상태 체크
if (!isset($payment_config['system_status']) || $payment_config['system_status'] != '1') {
    echo json_encode([
        'success' => false,
        'message' => '현재 충전/출금 시스템이 점검 중입니다.'
    ]);
    exit;
}

// ===================================
// 충전 처리
// ===================================
if ($request_type === 'charge') {
    $deposit_name = isset($_POST['deposit_name']) ? trim($_POST['deposit_name']) : '';
    
    // 입력값 검증
    if (empty($deposit_name)) {
        echo json_encode([
            'success' => false,
            'message' => '입금자명을 입력해주세요.'
        ]);
        exit;
    }
    
    $min_charge = intval($payment_config['min_charge_amount']);
    $max_charge = intval($payment_config['max_charge_amount']);
    
    if ($amount < $min_charge || $amount > $max_charge) {
        echo json_encode([
            'success' => false,
            'message' => "충전 금액은 " . number_format($min_charge) . "원 ~ " . number_format($max_charge) . "원 사이여야 합니다."
        ]);
        exit;
    }
    
    // 관리자 계좌 정보 조회
    $admin_account_sql = "SELECT * FROM payment_admin_accounts WHERE is_active = 1 ORDER BY display_order ASC LIMIT 1";
    $admin_account = sql_fetch($admin_account_sql);
    
    if (!$admin_account) {
        echo json_encode([
            'success' => false,
            'message' => '현재 이용 가능한 계좌가 없습니다. 관리자에게 문의하세요.'
        ]);
        exit;
    }
    
    $admin_bank_info = "{$admin_account['bank_name']} {$admin_account['account_number']} {$admin_account['account_holder']}";
    
    // 충전 신청 저장
    $now = date('Y-m-d H:i:s');
    $escaped_mb_id = sql_real_escape_string($member['mb_id']);
    $escaped_mb_name = sql_real_escape_string($member['mb_name']);
    $escaped_deposit_name = sql_real_escape_string($deposit_name);
    $escaped_admin_bank_info = sql_real_escape_string($admin_bank_info);
    
    $insert_sql = "
        INSERT INTO payment_requests 
        (mb_id, mb_name, request_type, amount, deposit_name, admin_bank_info, status, created_at) 
        VALUES 
        ('{$escaped_mb_id}', '{$escaped_mb_name}', 'charge', {$amount}, '{$escaped_deposit_name}', '{$escaped_admin_bank_info}', 'pending', '{$now}')
    ";
    
    if (sql_query($insert_sql)) {
        $request_id = sql_insert_id();
        writePaymentLog("충전 신청 성공: request_id={$request_id}");
        
        echo json_encode([
            'success' => true,
            'message' => '충전 신청이 완료되었습니다. 관리자 승인 후 포인트가 지급됩니다.',
            'request_id' => $request_id
        ]);
    } else {
        writePaymentLog("충전 신청 실패: SQL 오류");
        echo json_encode([
            'success' => false,
            'message' => '충전 신청 처리 중 오류가 발생했습니다.'
        ]);
    }
}

// ===================================
// 출금 처리
// ===================================
else if ($request_type === 'withdraw') {
    $bank_name = isset($_POST['bank_name']) ? trim($_POST['bank_name']) : '';
    $account_number = isset($_POST['account_number']) ? trim($_POST['account_number']) : '';
    $account_holder = isset($_POST['account_holder']) ? trim($_POST['account_holder']) : '';
    
    // 입력값 검증
    if (empty($bank_name) || empty($account_number) || empty($account_holder)) {
        echo json_encode([
            'success' => false,
            'message' => '계좌 정보를 모두 입력해주세요.'
        ]);
        exit;
    }
    
    $min_withdraw = intval($payment_config['min_withdraw_amount']);
    $max_withdraw = intval($payment_config['max_withdraw_amount']);
    
    if ($amount < $min_withdraw || $amount > $max_withdraw) {
        echo json_encode([
            'success' => false,
            'message' => "출금 금액은 " . number_format($min_withdraw) . "원 ~ " . number_format($max_withdraw) . "원 사이여야 합니다."
        ]);
        exit;
    }
    
    // 포인트 확인 (차감하지 않고 확인만)
    $member_point = get_point_sum($member['mb_id']);
    
    if ($member_point < $amount) {
        echo json_encode([
            'success' => false,
            'message' => '보유 포인트가 부족합니다. (보유: ' . number_format($member_point) . 'P)'
        ]);
        exit;
    }
    
    // 미처리 출금 신청 확인
    $escaped_mb_id = sql_real_escape_string($member['mb_id']);
    $pending_withdraw_sql = "
        SELECT COUNT(*) as cnt 
        FROM payment_requests 
        WHERE mb_id = '{$escaped_mb_id}' 
        AND request_type = 'withdraw' 
        AND status = 'pending'
    ";
    $pending_withdraw_result = sql_fetch($pending_withdraw_sql);
    
    if ($pending_withdraw_result['cnt'] > 0) {
        echo json_encode([
            'success' => false,
            'message' => '이미 처리 중인 출금 신청이 있습니다.'
        ]);
        exit;
    }
    
    // 출금 신청 저장 (포인트 차감 없이)
    $now = date('Y-m-d H:i:s');
    $escaped_mb_name = sql_real_escape_string($member['mb_name']);
    $escaped_bank_name = sql_real_escape_string($bank_name);
    $escaped_account_number = sql_real_escape_string($account_number);
    $escaped_account_holder = sql_real_escape_string($account_holder);
    
    $insert_sql = "
        INSERT INTO payment_requests 
        (mb_id, mb_name, request_type, amount, bank_name, account_number, account_holder, status, created_at) 
        VALUES 
        ('{$escaped_mb_id}', '{$escaped_mb_name}', 'withdraw', {$amount}, '{$escaped_bank_name}', '{$escaped_account_number}', '{$escaped_account_holder}', 'pending', '{$now}')
    ";
    
    if (sql_query($insert_sql)) {
        $request_id = sql_insert_id();
        writePaymentLog("출금 신청 성공: request_id={$request_id} (포인트 차감 없음)");
        
        echo json_encode([
            'success' => true,
            'message' => '출금 신청이 완료되었습니다. 관리자 승인 후 포인트가 차감되고 계좌로 입금됩니다.',
            'request_id' => $request_id
        ]);
    } else {
        writePaymentLog("출금 신청 실패: SQL 오류");
        echo json_encode([
            'success' => false,
            'message' => '출금 신청 처리 중 오류가 발생했습니다.'
        ]);
    }
}

?>