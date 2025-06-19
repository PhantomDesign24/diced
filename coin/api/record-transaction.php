<?php
/*
 * 파일명: record-transaction.php
 * 위치: /api/record-transaction.php
 * 기능: 트랜잭션 기록 API
 * 작성일: 2024-12-27
 */

// ===================================
// 초기 설정
// ===================================

/* 필수 파일 포함 */
require_once(__DIR__ . '/../config/config.php');
require_once(__DIR__ . '/../includes/functions.php');

// JSON 응답 헤더
header('Content-Type: application/json');

// CORS 설정 (필요시)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// ===================================
// 요청 처리
// ===================================

/* POST 요청만 허용 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '허용되지 않은 메소드']);
    exit;
}

/* 입력 데이터 검증 */
$user_wallet = validateInput($_POST['user_wallet'] ?? '', 'address');
$network = validateInput($_POST['network'] ?? '');
$from_token = validateInput($_POST['from_token'] ?? '', 'token');
$to_token = validateInput($_POST['to_token'] ?? '', 'token');
$from_amount = validateInput($_POST['from_amount'] ?? 0, 'number');
$to_amount = validateInput($_POST['to_amount'] ?? 0, 'number');
$tx_hash = validateInput($_POST['tx_hash'] ?? '');
$status = validateInput($_POST['status'] ?? 'pending');
$error = validateInput($_POST['error'] ?? '');

// 필수 필드 확인
if (!$user_wallet || !$network || !$from_token || !$to_token || $from_amount <= 0) {
    echo json_encode(['success' => false, 'message' => '필수 정보가 누락되었습니다.']);
    exit;
}

// ===================================
// 트랜잭션 기록
// ===================================

try {
    $pdo = getDB();
    
    $stmt = $pdo->prepare("
        INSERT INTO transactions (
            user_wallet, network, from_token, to_token,
            from_amount, to_amount, tx_hash, status, error_message, created_at
        ) VALUES (
            :user_wallet, :network, :from_token, :to_token,
            :from_amount, :to_amount, :tx_hash, :status, :error_message, :created_at
        )
    ");
    
    $now = new DateTime('now', new DateTimeZone('Asia/Seoul'));
    
    $result = $stmt->execute([
        ':user_wallet' => $user_wallet,
        ':network' => $network,
        ':from_token' => $from_token,
        ':to_token' => $to_token,
        ':from_amount' => $from_amount,
        ':to_amount' => $to_amount,
        ':tx_hash' => $tx_hash,
        ':status' => $status,
        ':error_message' => $error ?: null,
        ':created_at' => $now->format('Y-m-d H:i:s')
    ]);
    
    if ($result) {
        $transaction_id = $pdo->lastInsertId();
        
        // 활동 로그 기록
        writeLog("Transaction recorded: ID=$transaction_id, User=$user_wallet, Amount=$from_amount $from_token");
        
        echo json_encode([
            'success' => true,
            'message' => '트랜잭션이 기록되었습니다.',
            'transaction_id' => $transaction_id
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => '트랜잭션 기록에 실패했습니다.'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("트랜잭션 기록 오류: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => '데이터베이스 오류가 발생했습니다.'
    ]);
}
?>