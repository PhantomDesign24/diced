<?php
/*
 * 파일명: get-transactions.php
 * 위치: /api/get-transactions.php
 * 기능: 트랜잭션 조회 API
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

// CORS 설정
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// ===================================
// 파라미터 처리
// ===================================

/* 파라미터 가져오기 */
$limit = min(100, max(1, intval($_GET['limit'] ?? 10)));
$offset = max(0, intval($_GET['offset'] ?? 0));
$user_wallet = validateInput($_GET['user_wallet'] ?? '', 'address');
$network = validateInput($_GET['network'] ?? '');
$status = validateInput($_GET['status'] ?? '');

// ===================================
// 트랜잭션 조회
// ===================================

try {
    $pdo = getDB();
    
    // 기본 쿼리
    $sql = "SELECT * FROM transactions WHERE 1=1";
    $params = [];
    
    // 조건 추가
    if ($user_wallet) {
        $sql .= " AND user_wallet = :user_wallet";
        $params[':user_wallet'] = $user_wallet;
    }
    
    if ($network) {
        $sql .= " AND network = :network";
        $params[':network'] = $network;
    }
    
    if ($status) {
        $sql .= " AND status = :status";
        $params[':status'] = $status;
    }
    
    // 정렬 및 제한
    $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    
    $stmt = $pdo->prepare($sql);
    
    // 파라미터 바인딩
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 총 개수 조회
    $countSql = "SELECT COUNT(*) as total FROM transactions WHERE 1=1";
    if ($user_wallet) {
        $countSql .= " AND user_wallet = :user_wallet";
    }
    if ($network) {
        $countSql .= " AND network = :network";
    }
    if ($status) {
        $countSql .= " AND status = :status";
    }
    
    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $key => $value) {
        if ($key !== ':limit' && $key !== ':offset') {
            $countStmt->bindValue($key, $value);
        }
    }
    $countStmt->execute();
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // 응답
    echo json_encode([
        'success' => true,
        'data' => $transactions,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
    
} catch (PDOException $e) {
    error_log("트랜잭션 조회 오류: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => '데이터베이스 오류가 발생했습니다.',
        'data' => []
    ]);
}
?>