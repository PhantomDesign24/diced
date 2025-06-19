<?php
/*
 * 파일명: login.php
 * 위치: /game/login.php
 * 기능: 로그인 및 회원가입 통합 페이지 (탭 방식)
 * 작성일: 2025-06-12
 * 수정일: 2025-06-13
 */
include_once(__DIR__ . '/../common.php');

// 이미 로그인된 경우 게임 페이지로 리다이렉트
if ($is_member) {
    goto_url(G5_URL . '/index.php');
}

// ===================================
// 회원가입 성공 메시지 처리
// ===================================

session_start();
$register_success_message = '';
$msg_data = array();

// 회원가입 성공 메시지 확인
if (isset($_SESSION['register_success']) && $_SESSION['register_success']) {
    $msg_data = $_SESSION['register_message'];
    
    // 메시지 생성
    $register_success_message = "회원가입이 완료되었습니다!<br><br>";
    $register_success_message .= "아이디: " . htmlspecialchars($msg_data['mb_id']) . "<br>";
    $register_success_message .= "이름: " . htmlspecialchars($msg_data['mb_name']) . "<br>";
    
    if ($msg_data['welcome_point'] > 0) {
        $register_success_message .= "가입 축하 포인트: " . number_format($msg_data['welcome_point']) . "P<br><br>";
    }
    
    $register_success_message .= "로그인하여 이용해주세요.";
    
    // 세션 메시지 삭제
    unset($_SESSION['register_success']);
    unset($_SESSION['register_message']);
}

// ===================================
// 가입코드 조회 함수
// ===================================

/**
 * 관리자가 설정한 가입코드 조회
 * 
 * @return string 가입코드
 */
function getSignupCode() {
    global $g5;
    $sql = "SELECT config_value FROM dice_game_config WHERE config_key = 'signup_code'";
    $result = sql_fetch($sql);
    return $result ? $result['config_value'] : '';
}

$current_signup_code = getSignupCode();

// ===================================
// 로그인 처리 URL 설정
// ===================================
$login_action_url = G5_BBS_URL.'/login_check.php';
$register_action_url = G5_URL.'/register_process.php';
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>로그인 - 벨루나 게임</title>
    
    <!-- Bootstrap CSS 비동기 로드 -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"></noscript>
    
    <!-- Bootstrap Icons -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css"></noscript>

    <style>
        /* ===================================
         * 로그인 페이지 전체 스타일
         * =================================== */
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            min-height: 100vh !important;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* 메인 컨테이너 */
        .login-container {
            min-height: 100vh !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            padding: 20px !important;
        }

        /* ===================================
         * 로그인 카드 스타일
         * =================================== */
        
        /* 카드 기본 스타일 */
        .login-card {
            background: rgba(255, 255, 255, 0.95) !important;
            border-radius: 20px !important;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1) !important;
            backdrop-filter: blur(10px) !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            width: 100% !important;
            max-width: 450px !important;
            overflow: hidden !important;
        }

        /* 헤더 영역 */
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            padding: 30px 20px !important;
            text-align: center !important;
            color: white !important;
        }

        .login-title {
            font-size: 1.8rem !important;
            font-weight: 600 !important;
            margin: 0 !important;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3) !important;
        }
.nav-item { width:50%; }
.nav-link { width:100%; }
        .login-subtitle {
            font-size: 0.9rem !important;
            opacity: 0.9 !important;
            margin-top: 5px !important;
        }

        /* ===================================
         * 탭 네비게이션 스타일
         * =================================== */
        
        /* 탭 컨테이너 */
        .tab-container {
            background: white !important;
            border-bottom: 1px solid #eee !important;
        }

        /* 탭 버튼 */
        .nav-tabs {
            border-bottom: none !important;
            margin: 0 !important;
        }

        .nav-tabs .nav-link {
            border: none !important;
            border-radius: 0 !important;
            padding: 18px 30px !important;
            font-weight: 500 !important;
            color: #666 !important;
            background: transparent !important;
            transition: all 0.3s ease !important;
            flex: 1 !important;
            text-align: center !important;
        }

        .nav-tabs .nav-link:hover {
            color: #667eea !important;
            background: rgba(102, 126, 234, 0.05) !important;
        }

        .nav-tabs .nav-link.active {
            color: #667eea !important;
            background: rgba(102, 126, 234, 0.1) !important;
            border-bottom: 3px solid #667eea !important;
        }

        /* ===================================
         * 폼 영역 스타일
         * =================================== */
        
        /* 폼 컨테이너 */
        .form-container {
            padding: 30px !important;
        }

        /* 입력 그룹 스타일 */
        .input-group {
            margin-bottom: 20px !important;
        }

        .input-group-text {
            border-right: none !important;
            background-color: #fff !important;
            border-color: #ddd !important;
            color: #667eea !important;
            width: 50px !important;
            justify-content: center !important;
        }

        .form-control {
            border-left: none !important;
            padding-left: 10px !important;
            border-color: #ddd !important;
            height: 50px !important;
            font-size: 0.95rem !important;
        }

        .form-control:focus {
            border-color: #667eea !important;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25) !important;
        }

        .form-control:focus + .input-group-text,
        .input-group-text:has(+ .form-control:focus) {
            border-color: #667eea !important;
        }

        /* ===================================
         * 버튼 스타일
         * =================================== */
        
        /* 제출 버튼 */
        .btn-submit {
            width: 100% !important;
            height: 50px !important;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            border: none !important;
            border-radius: 8px !important;
            color: white !important;
            font-size: 1.05rem !important;
            font-weight: 600 !important;
            margin-top: 10px !important;
            transition: all 0.3s ease !important;
            cursor: pointer !important;
        }

        .btn-submit:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4) !important;
        }

        .btn-submit:active {
            transform: translateY(0) !important;
        }

        /* ===================================
         * 추가 기능 영역
         * =================================== */
        
        /* 로그인 옵션 */
        .login-options {
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            margin-top: 20px !important;
            font-size: 0.9rem !important;
        }

        .auto-login {
            display: flex !important;
            align-items: center !important;
        }

        .auto-login input[type="checkbox"] {
            margin-right: 8px !important;
        }

        .forgot-link {
            color: #667eea !important;
            text-decoration: none !important;
        }

        .forgot-link:hover {
            text-decoration: underline !important;
        }

        /* ===================================
         * 반응형 스타일
         * =================================== */
        
        @media (max-width: 768px) {
            .login-container {
                padding: 10px !important;
            }
            
            .login-card {
                margin: 10px !important;
            }
            
            .nav-tabs .nav-link {
                padding: 15px 20px !important;
                font-size: 0.9rem !important;
            }
            
            .form-container {
                padding: 20px !important;
            }
        }

        /* ===================================
         * 알림 메시지 스타일
         * =================================== */
        
        .alert-custom {
            border-radius: 8px !important;
            border: none !important;
            margin-bottom: 20px !important;
        }

        .alert-info {
            background: rgba(102, 126, 234, 0.1) !important;
            color: #667eea !important;
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.1) !important;
            color: #dc3545 !important;
        }

        /* ===================================
         * 커스텀 토스트 팝업 스타일
         * =================================== */
        
        /* 토스트 컨테이너 */
        .custom-toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            pointer-events: none;
        }
        
        /* 토스트 기본 스타일 */
        .custom-toast {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1), 
                        0 0 0 1px rgba(255, 255, 255, 0.2);
            padding: 0;
            margin-bottom: 15px;
            min-width: 350px;
            max-width: 450px;
            pointer-events: all;
            transform: translateX(400px);
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            overflow: hidden;
        }
        
        .custom-toast.show {
            transform: translateX(0);
        }
        
        /* 토스트 헤더 */
        .custom-toast-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .custom-toast-header i {
            font-size: 1.2rem;
            margin-right: 10px;
        }
        
        /* 토스트 바디 */
        .custom-toast-body {
            padding: 20px;
            color: #333;
            line-height: 1.6;
        }
        
        .custom-toast-body strong {
            color: #667eea;
            font-weight: 600;
        }
        
        /* 닫기 버튼 */
        .custom-toast-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            opacity: 0.8;
            transition: opacity 0.2s;
            padding: 0;
            margin-left: 15px;
        }
        
        .custom-toast-close:hover {
            opacity: 1;
        }
        
        /* 진행 바 */
        .custom-toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            width: 100%;
            animation: progress 5s linear forwards;
        }
        
        @keyframes progress {
            from {
                width: 100%;
            }
            to {
                width: 0%;
            }
        }
        
        /* 포인트 강조 */
        .point-highlight {
            background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
            color: #333;
            padding: 2px 8px;
            border-radius: 5px;
            font-weight: 700;
            display: inline-block;
            margin: 5px 0;
        }
        
        /* 반응형 */
        @media (max-width: 480px) {
            .custom-toast-container {
                top: 10px;
                right: 10px;
                left: 10px;
            }
            
            .custom-toast {
                min-width: unset;
                max-width: unset;
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="login-card">
            <!-- 헤더 영역 -->
            <div class="login-header">
                <h1 class="login-title">벨루나</h1>
                <p class="login-subtitle">로그인 후 시작하세요</p>
            </div>

            <!-- 탭 네비게이션 -->
            <div class="tab-container">
                <ul class="nav nav-tabs" id="authTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="login-tab" data-bs-toggle="tab" data-bs-target="#login-pane" type="button" role="tab">
                            로그인
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="register-tab" data-bs-toggle="tab" data-bs-target="#register-pane" type="button" role="tab">
                            회원가입
                        </button>
                    </li>
                </ul>
            </div>

            <!-- 탭 콘텐츠 -->
            <div class="tab-content">
                <!-- 로그인 폼 -->
                <div class="tab-pane fade show active" id="login-pane" role="tabpanel">
                    <div class="form-container">
                        <form name="flogin" action="<?php echo $login_action_url ?>" method="post" onsubmit="return validateLogin(this);">
                            <input type="hidden" name="url" value="<?php echo get_text($_GET['url'] ?? G5_URL . '/index.php') ?>">
                            
                            <!-- 아이디 입력 -->
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-person-fill"></i>
                                </span>
                                <input type="text" class="form-control" name="mb_id" id="login_id" 
                                       placeholder="아이디를 입력하세요" required maxlength="20">
                            </div>

                            <!-- 비밀번호 입력 -->
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-lock-fill"></i>
                                </span>
                                <input type="password" class="form-control" name="mb_password" id="login_pw" 
                                       placeholder="비밀번호를 입력하세요" required maxlength="20">
                            </div>

                            <!-- 로그인 옵션 -->
                            <div class="login-options">
                                <label class="auto-login">
                                    <input type="checkbox" name="auto_login" id="login_auto_login">
                                    자동로그인
                                </label>
                                <a href="<?php echo G5_BBS_URL ?>/password_lost.php" class="forgot-link">
                                    ID/PW 찾기
                                </a>
                            </div>

                            <!-- 로그인 버튼 -->
                            <button type="submit" class="btn-submit">
                                <i class="bi bi-box-arrow-in-right me-2"></i>로그인
                            </button>
                        </form>
                    </div>
                </div>

                <!-- 회원가입 폼 -->
                <div class="tab-pane fade" id="register-pane" role="tabpanel">
                    <div class="form-container">
                        <?php if (empty($current_signup_code)): ?>
                        <div class="alert alert-info alert-custom">
                            <i class="bi bi-info-circle me-2"></i>
                            현재 가입코드가 설정되지 않았습니다. 관리자에게 문의하세요.
                        </div>
                        <?php endif; ?>

                        <form name="fregister" action="<?php echo $register_action_url ?>" method="post" onsubmit="return validateRegister(this);">
                            
                            <!-- 아이디 입력 -->
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-person-badge-fill"></i>
                                </span>
                                <input type="text" class="form-control" name="mb_id" id="reg_id" 
                                       placeholder="아이디 (영문, 숫자 3-20자)" required 
                                       maxlength="20" pattern="[a-zA-Z0-9]{3,20}">
                            </div>

                            <!-- 비밀번호 입력 -->
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-shield-lock-fill"></i>
                                </span>
                                <input type="password" class="form-control" name="mb_password" id="reg_pw" 
                                       placeholder="비밀번호 (6자 이상)" required minlength="6" maxlength="20">
                            </div>

                            <!-- 이름 입력 -->
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-card-text"></i>
                                </span>
                                <input type="text" class="form-control" name="mb_name" id="reg_name" 
                                       placeholder="이름을 입력하세요" required maxlength="20">
                            </div>

                            <!-- 가입코드 입력 -->
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="bi bi-key-fill"></i>
                                </span>
                                <input type="text" class="form-control" name="signup_code" id="reg_code" 
                                       placeholder="가입코드를 입력하세요" required maxlength="50">
                            </div>

                            <!-- 회원가입 버튼 -->
                            <button type="submit" class="btn-submit">
                                <i class="bi bi-person-plus-fill me-2"></i>회원가입
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // ===================================
        // 로그인 폼 검증
        // ===================================
        
        /**
         * 로그인 폼 유효성 검사
         * 
         * @param {HTMLFormElement} form 로그인 폼
         * @return {boolean} 검증 결과
         */
        function validateLogin(form) {
            const id = form.mb_id.value.trim();
            const password = form.mb_password.value.trim();
            
            if (!id) {
                alert('아이디를 입력하세요.');
                form.mb_id.focus();
                return false;
            }
            
            if (!password) {
                alert('비밀번호를 입력하세요.');
                form.mb_password.focus();
                return false;
            }
            
            return true;
        }

        // ===================================
        // 회원가입 폼 검증
        // ===================================
        
        /**
         * 회원가입 폼 유효성 검사
         * 
         * @param {HTMLFormElement} form 회원가입 폼
         * @return {boolean} 검증 결과
         */
        function validateRegister(form) {
            const id = form.mb_id.value.trim();
            const password = form.mb_password.value.trim();
            const name = form.mb_name.value.trim();
            const code = form.signup_code.value.trim();
            
            // 아이디 검증
            if (!id) {
                alert('아이디를 입력하세요.');
                form.mb_id.focus();
                return false;
            }
            
            if (!/^[a-zA-Z0-9]{3,20}$/.test(id)) {
                alert('아이디는 영문, 숫자로 3-20자리여야 합니다.');
                form.mb_id.focus();
                return false;
            }
            
            // 비밀번호 검증
            if (!password) {
                alert('비밀번호를 입력하세요.');
                form.mb_password.focus();
                return false;
            }
            
            if (password.length < 6) {
                alert('비밀번호는 6자 이상이어야 합니다.');
                form.mb_password.focus();
                return false;
            }
            
            // 이름 검증
            if (!name) {
                alert('이름을 입력하세요.');
                form.mb_name.focus();
                return false;
            }
            
            // 가입코드 검증
            if (!code) {
                alert('가입코드를 입력하세요.');
                form.signup_code.focus();
                return false;
            }
            
            <?php if (!empty($current_signup_code)): ?>
            if (code !== '<?php echo addslashes($current_signup_code) ?>') {
                alert('가입코드가 올바르지 않습니다.');
                form.signup_code.focus();
                return false;
            }
            <?php endif; ?>
            
            return true;
        }

        // ===================================
        // 자동로그인 경고
        // ===================================
        
        document.addEventListener('DOMContentLoaded', function() {
            const autoLoginCheckbox = document.getElementById('login_auto_login');
            
            if (autoLoginCheckbox) {
                autoLoginCheckbox.addEventListener('click', function() {
                    if (this.checked) {
                        const confirmed = confirm(
                            '자동로그인을 사용하시면 다음부터 회원아이디와 비밀번호를 입력하실 필요가 없습니다.\n\n' +
                            '공공장소에서는 개인정보가 유출될 수 있으니 사용을 자제하여 주십시오.\n\n' +
                            '자동로그인을 사용하시겠습니까?'
                        );
                        
                        if (!confirmed) {
                            this.checked = false;
                        }
                    }
                });
            }
        });

        // ===================================
        // 탭 전환 시 폼 초기화
        // ===================================
        
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('#authTabs button[data-bs-toggle="tab"]');
            
            tabs.forEach(tab => {
                tab.addEventListener('shown.bs.tab', function(event) {
                    // 활성화된 탭의 첫 번째 입력 필드에 포커스
                    const targetPane = document.querySelector(event.target.getAttribute('data-bs-target'));
                    const firstInput = targetPane.querySelector('input[type="text"]');
                    
                    if (firstInput) {
                        setTimeout(() => firstInput.focus(), 100);
                    }
                });
            });
        });
    </script>

    <?php if (!empty($register_success_message)): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 커스텀 토스트 HTML 생성
        const toastHtml = `
            <div class="custom-toast-container">
                <div class="custom-toast" id="successToast">
                    <div class="custom-toast-header">
                        <span>
                            <i class="bi bi-check-circle-fill"></i>
                            회원가입 완료
                        </span>
                        <button type="button" class="custom-toast-close" onclick="closeToast()">
                            <i class="bi bi-x"></i>
                        </button>
                    </div>
                    <div class="custom-toast-body">
                        <div style="text-align: center; padding: 10px 0;">
                            <i class="bi bi-party-popper" style="font-size: 3rem; color: #667eea;"></i>
                            <h5 style="margin: 15px 0; color: #333;">환영합니다!</h5>
                        </div>
                        <div style="border-top: 1px solid #eee; padding-top: 15px; margin-top: 15px;">
                            <p><strong>아이디:</strong> <?php echo htmlspecialchars($msg_data['mb_id'] ?? ''); ?></p>
                            <p><strong>이름:</strong> <?php echo htmlspecialchars($msg_data['mb_name'] ?? ''); ?></p>
                            <?php if (($msg_data['welcome_point'] ?? 0) > 0): ?>
                            <p style="margin-top: 10px;">
                                <strong>가입 축하 포인트:</strong> 
                                <span class="point-highlight">
                                    <i class="bi bi-coin"></i> <?php echo number_format($msg_data['welcome_point']); ?>P
                                </span>
                            </p>
                            <?php endif; ?>
                            <p style="margin-top: 15px; text-align: center; color: #666; font-size: 0.9rem;">
                                <i class="bi bi-info-circle"></i> 로그인하여 이용해주세요
                            </p>
                        </div>
                    </div>
                    <div class="custom-toast-progress"></div>
                </div>
            </div>
        `;
        
        // 토스트를 body에 추가
        document.body.insertAdjacentHTML('beforeend', toastHtml);
        
        // 토스트 표시 애니메이션
        setTimeout(() => {
            const toast = document.getElementById('successToast');
            if (toast) {
                toast.classList.add('show');
            }
        }, 100);
        
        // 5초 후 자동으로 닫기
        const autoCloseTimeout = setTimeout(() => {
            closeToast();
        }, 5000);
        
        // 전역 함수로 토스트 닫기 정의
        window.closeToast = function() {
            clearTimeout(autoCloseTimeout);
            const toast = document.getElementById('successToast');
            if (toast) {
                toast.classList.remove('show');
                setTimeout(() => {
                    const container = document.querySelector('.custom-toast-container');
                    if (container) {
                        container.remove();
                    }
                }, 300);
            }
        };
    });
    </script>
    <?php endif; ?>
</body>
</html>