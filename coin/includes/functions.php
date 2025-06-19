<?php
/*
 * 파일명: functions.php
 * 위치: /includes/functions.php
 * 기능: 공통 함수 모음
 * 작성일: 2024-12-27
 */

// ===================================
// 관리자 설정 관련 함수
// ===================================

/* 관리자 설정 가져오기 */
function getAdminSettings() {
    $pdo = getDB();
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM admin_settings WHERE id = 1");
        $stmt->execute();
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($settings) {
            // JSON 디코드
            $settings['swap_rates'] = json_decode($settings['swap_rates'], true) ?: [];
            $settings['allowed_tokens'] = json_decode($settings['allowed_tokens'], true) ?: [];
            return $settings;
        }
        
        // 기본값 반환
        return [
            'main_wallet' => '',
            'swap_rates' => [],
            'allowed_tokens' => [],
            'is_active' => 1
        ];
        
    } catch (PDOException $e) {
        error_log("관리자 설정 조회 오류: " . $e->getMessage());
        return [];
    }
}

/* 관리자 설정 저장 */
function saveAdminSettings($data) {
    $pdo = getDB();
    
    try {
        // JSON 인코딩
        $swap_rates = json_encode($data['swap_rates'] ?? []);
        $allowed_tokens = json_encode($data['allowed_tokens'] ?? []);
        
        $stmt = $pdo->prepare("
            INSERT INTO admin_settings (id, main_wallet, swap_rates, allowed_tokens, is_active, updated_at)
            VALUES (1, :main_wallet, :swap_rates, :allowed_tokens, :is_active, :updated_at)
            ON DUPLICATE KEY UPDATE
                main_wallet = VALUES(main_wallet),
                swap_rates = VALUES(swap_rates),
                allowed_tokens = VALUES(allowed_tokens),
                is_active = VALUES(is_active),
                updated_at = VALUES(updated_at)
        ");
        
        $now = new DateTime('now', new DateTimeZone('Asia/Seoul'));
        
        return $stmt->execute([
            ':main_wallet' => $data['main_wallet'] ?? '',
            ':swap_rates' => $swap_rates,
            ':allowed_tokens' => $allowed_tokens,
            ':is_active' => $data['is_active'] ?? 1,
            ':updated_at' => $now->format('Y-m-d H:i:s')
        ]);
        
    } catch (PDOException $e) {
        error_log("관리자 설정 저장 오류: " . $e->getMessage());
        return false;
    }
}

// ===================================
// 트랜잭션 관련 함수
// ===================================

/* 트랜잭션 기록 */
function recordTransaction($data) {
    $pdo = getDB();
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO transactions (
                user_wallet, network, from_token, to_token, 
                from_amount, to_amount, tx_hash, status, created_at
            ) VALUES (
                :user_wallet, :network, :from_token, :to_token,
                :from_amount, :to_amount, :tx_hash, :status, :created_at
            )
        ");
        
        $now = new DateTime('now', new DateTimeZone('Asia/Seoul'));
        
        return $stmt->execute([
            ':user_wallet' => $data['user_wallet'],
            ':network' => $data['network'],
            ':from_token' => $data['from_token'],
            ':to_token' => $data['to_token'],
            ':from_amount' => $data['from_amount'],
            ':to_amount' => $data['to_amount'],
            ':tx_hash' => $data['tx_hash'] ?? '',
            ':status' => $data['status'] ?? 'pending',
            ':created_at' => $now->format('Y-m-d H:i:s')
        ]);
        
    } catch (PDOException $e) {
        error_log("트랜잭션 기록 오류: " . $e->getMessage());
        return false;
    }
}

/* 최근 트랜잭션 조회 */
function getRecentTransactions($limit = 10) {
    $pdo = getDB();
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM transactions 
            ORDER BY created_at DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("트랜잭션 조회 오류: " . $e->getMessage());
        return [];
    }
}

/* 사용자별 트랜잭션 조회 */
function getUserTransactions($wallet, $limit = 50) {
    $pdo = getDB();
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM transactions 
            WHERE user_wallet = :wallet 
            ORDER BY created_at DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':wallet', $wallet);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("사용자 트랜잭션 조회 오류: " . $e->getMessage());
        return [];
    }
}

// ===================================
// 보안 관련 함수
// ===================================

/* 입력값 검증 */
function validateInput($input, $type = 'string') {
    switch ($type) {
        case 'address':
            // 이더리움 주소 검증
            if (preg_match('/^0x[a-fA-F0-9]{40}$/', $input)) {
                return $input;
            }
            // 트론 주소 검증
            if (preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $input)) {
                return $input;
            }
            return false;
            
        case 'number':
            return is_numeric($input) ? floatval($input) : false;
            
        case 'token':
            return preg_match('/^[A-Z0-9]{2,10}$/', $input) ? $input : false;
            
        default:
            return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
}

/* CSRF 토큰 생성 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/* CSRF 토큰 검증 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ===================================
// 유틸리티 함수
// ===================================

/* 주소 축약 */
function shortenAddress($address, $chars = 6) {
    if (strlen($address) < ($chars * 2 + 2)) {
        return $address;
    }
    return substr($address, 0, $chars + 2) . '...' . substr($address, -$chars);
}

/* 숫자 포맷팅 */
function formatNumber($number, $decimals = 4) {
    return number_format($number, $decimals, '.', ',');
}

/* 토큰 정보 가져오기 */
function getTokenInfo($network, $symbol) {
    $tokens = SUPPORTED_TOKENS[$network] ?? [];
    return $tokens[$symbol] ?? null;
}

/* 스왑 비율 계산 */
function calculateSwapAmount($fromAmount, $fromToken, $toToken, $rates) {
    // 직접 비율이 있는 경우
    $key = $fromToken . '_' . $toToken;
    if (isset($rates[$key])) {
        $rate = $rates[$key];
        $toAmount = $fromAmount * $rate;
        
        // 수수료 적용
        $fee = $toAmount * (SWAP_FEE_PERCENT / 100);
        return $toAmount - $fee;
    }
    
    // 역방향 비율 계산
    $reverseKey = $toToken . '_' . $fromToken;
    if (isset($rates[$reverseKey])) {
        $rate = 1 / $rates[$reverseKey];
        $toAmount = $fromAmount * $rate;
        
        // 수수료 적용
        $fee = $toAmount * (SWAP_FEE_PERCENT / 100);
        return $toAmount - $fee;
    }
    
    return 0;
}

/* 로그 기록 */
function writeLog($message, $type = 'info') {
    $logFile = __DIR__ . '/../logs/' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$type] $message" . PHP_EOL;
    
    // 로그 디렉토리 생성
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

/* 관리자 권한 체크 */
function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

/* 관리자 로그인 체크 */
function checkAdminAuth() {
    if (!isAdmin()) {
        header('Location: login.php');
        exit;
    }
}
?>