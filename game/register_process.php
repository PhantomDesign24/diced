<?php
/*
 * 파일명: register_process.php
 * 위치: /game/register_process.php
 * 기능: 회원가입 처리 및 가입코드 검증
 * 작성일: 2025-06-12
 * 수정일: 2025-06-13
 */

include_once(__DIR__ . '/../common.php');

// POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    alert('잘못된 접근입니다.');
    exit;
}

// ===================================
// 입력값 검증 및 정제
// ===================================

/* 입력 데이터 받기 */
$mb_id = trim($_POST['mb_id'] ?? '');
$mb_password = trim($_POST['mb_password'] ?? '');
$mb_name = trim($_POST['mb_name'] ?? '');
$signup_code = trim($_POST['signup_code'] ?? '');

/* 기본 유효성 검사 */
if (empty($mb_id) || empty($mb_password) || empty($mb_name) || empty($signup_code)) {
    alert('모든 항목을 입력해주세요.');
    goto_url('./login.php');
}

// ===================================
// 아이디 검증
// ===================================

/* 아이디 형식 검증 */
if (!preg_match('/^[a-zA-Z0-9]{3,20}$/', $mb_id)) {
    alert('아이디는 영문, 숫자로 3-20자리여야 합니다.');
    goto_url('./login.php');
}

/* 아이디 중복 검사 */
$escaped_mb_id = sql_real_escape_string($mb_id);
$sql = "SELECT mb_id FROM {$g5['member_table']} WHERE mb_id = '{$escaped_mb_id}'";
$result = sql_fetch($sql);

if ($result) {
    alert('이미 사용중인 아이디입니다.');
    goto_url(G5_URL . '/login.php');
}

// ===================================
// 비밀번호 검증
// ===================================

/* 비밀번호 길이 검증 */
if (strlen($mb_password) < 6) {
    alert('비밀번호는 6자 이상이어야 합니다.');
    goto_url('./login.php');
}

// ===================================
// 가입코드 검증
// ===================================

/**
 * 관리자가 설정한 가입코드 조회
 * 
 * @return string 설정된 가입코드
 */
function getConfiguredSignupCode() {
    $sql = "SELECT config_value FROM dice_game_config WHERE config_key = 'signup_code'";
    $result = sql_fetch($sql);
    return $result ? $result['config_value'] : '';
}

/* 가입코드 확인 */
$configured_code = getConfiguredSignupCode();

if (empty($configured_code)) {
    alert('현재 회원가입이 제한되어 있습니다. 관리자에게 문의하세요.');
    goto_url('./login.php');
}

if ($signup_code !== $configured_code) {
    alert('가입코드가 올바르지 않습니다.');
    goto_url('./login.php');
}

// ===================================
// 회원가입 처리
// ===================================

/* 현재 시간 */
$now = date('Y-m-d H:i:s');

/* 비밀번호 암호화 */
$mb_password_encrypted = get_encrypt_string($mb_password);

/* 기본 회원 정보 설정 */
$mb_level = 2; // 일반 회원 권한
$mb_point = 0; // 초기 포인트
$mb_datetime = $now;

// IP 주소 가져오기 (get_real_ip 함수 대체)
if (function_exists('get_real_ip')) {
    $mb_ip = get_real_ip();
} else {
    $mb_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    // 프록시를 통한 접속인 경우 실제 IP 확인
    if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $mb_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
        $mb_ip = $_SERVER['HTTP_CLIENT_IP'];
    }
}

// ===================================
// 그누보드5 기본 회원가입 함수 사용
// ===================================

/* 회원 정보 배열 구성 */
$member_data = array(
    'mb_id' => $mb_id,
    'mb_password' => $mb_password_encrypted,
    'mb_name' => $mb_name,
    'mb_nick' => $mb_name,
    'mb_email' => '',
    'mb_homepage' => '',
    'mb_level' => $mb_level,
    'mb_sex' => '',
    'mb_birth' => '',
    'mb_tel' => '',
    'mb_hp' => '',
    'mb_certify' => '',
    'mb_adult' => 0,
    'mb_dupinfo' => '',
    'mb_zip1' => '',
    'mb_zip2' => '',
    'mb_addr1' => '',
    'mb_addr2' => '',
    'mb_addr3' => '',
    'mb_addr_jibeon' => '',
    'mb_signature' => '',
    'mb_recommend' => '',
    'mb_point' => $mb_point,
    'mb_today_login' => $now,
    'mb_login_ip' => $mb_ip,
    'mb_datetime' => $mb_datetime,
    'mb_ip' => $mb_ip,
    'mb_leave_date' => '',
    'mb_intercept_date' => '',
    'mb_email_certify' => $now,
    'mb_email_certify2' => '',
    'mb_memo' => '주사위 게임 회원가입',
    'mb_lost_certify' => '',
    'mb_mailling' => 1,
    'mb_sms' => 0,
    'mb_open' => 0,
    'mb_open_date' => $now,
    'mb_profile' => ''
);

/* 회원 테이블에 필요한 추가 필드들 */
for ($i = 1; $i <= 10; $i++) {
    $member_data["mb_{$i}"] = '';
}

try {
    /* 회원 데이터 삽입을 위한 SQL 구성 */
    $fields = array();
    $values = array();
    
    foreach ($member_data as $field => $value) {
        $fields[] = $field;
        if (is_numeric($value)) {
            $values[] = $value;
        } else {
            $values[] = "'" . sql_real_escape_string($value) . "'";
        }
    }
    
    $sql = "INSERT INTO {$g5['member_table']} (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
    
    $result = sql_query($sql);
    
    if (!$result) {
        throw new Exception('회원가입 처리 중 데이터베이스 오류가 발생했습니다.');
    }
    
    // ===================================
    // 가입 완료 처리
    // ===================================
    
    /* 가입 축하 포인트 지급 */
    $welcome_point_sql = "SELECT config_value FROM dice_game_config WHERE config_key = 'signup_welcome_point'";
    $welcome_point_result = sql_fetch($welcome_point_sql);
    $welcome_point = $welcome_point_result ? (int)$welcome_point_result['config_value'] : 10000;
    
    if ($welcome_point > 0) {
        // 포인트 지급
        $point_sql = "
            INSERT INTO {$g5['point_table']} SET
                mb_id = '{$escaped_mb_id}',
                po_datetime = '{$now}',
                po_content = '회원가입 축하 포인트',
                po_point = {$welcome_point},
                po_use_point = 0,
                po_expired = 0,
                po_expire_date = '9999-12-31',
                po_mb_point = {$welcome_point},
                po_rel_table = '@member',
                po_rel_id = '{$escaped_mb_id}',
                po_rel_action = 'signup'
        ";
        sql_query($point_sql);
        
        // 회원 테이블의 포인트도 업데이트
        $update_sql = "UPDATE {$g5['member_table']} SET mb_point = {$welcome_point} WHERE mb_id = '{$escaped_mb_id}'";
        sql_query($update_sql);
    }
    
    /* 회원가입 로그 기록 */
    $log_content = "[회원가입] ID: {$mb_id}, 이름: {$mb_name}, IP: {$mb_ip}, 시간: {$now}";
    $log_file = __DIR__ . '/logs/member_register.log';
    
    // 로그 디렉토리가 없으면 생성
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    
    @file_put_contents($log_file, $log_content . PHP_EOL, FILE_APPEND | LOCK_EX);
    
    // ===================================
    // 성공 메시지 및 리다이렉트
    // ===================================
    
    /* 세션에 성공 메시지 저장 */
    session_start();
    $_SESSION['register_success'] = true;
    $_SESSION['register_message'] = array(
        'mb_id' => $mb_id,
        'mb_name' => $mb_name,
        'welcome_point' => $welcome_point
    );
    
    // 로그인 페이지로 리다이렉트
    goto_url('./login.php');
    
} catch (Exception $e) {
    // ===================================
    // 오류 처리
    // ===================================
    
    /* 오류 로그 기록 */
    $error_content = "[회원가입 오류] ID: {$mb_id}, 오류: " . $e->getMessage() . ", IP: {$mb_ip}, 시간: {$now}";
    $error_log_file = __DIR__ . '/logs/member_register_error.log';
    
    // 로그 디렉토리가 없으면 생성
    $error_log_dir = dirname($error_log_file);
    if (!is_dir($error_log_dir)) {
        @mkdir($error_log_dir, 0755, true);
    }
    
    @file_put_contents($error_log_file, $error_content . PHP_EOL, FILE_APPEND | LOCK_EX);
    
    alert('회원가입 처리 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.');
    goto_url('./login.php');
}
?>