<?php
/*
 * 파일명: coupang_cart_manager.php
 * 위치: /
 * 기능: 쿠팡 상품 장바구니 추가 및 최대 수량 확인
 * 작성일: 2025-01-19
 */

// ===================================
// 초기 설정
// ===================================

/* 에러 표시 설정 (개발용) */
error_reporting(E_ALL);
ini_set('display_errors', 1);

/* 세션 시작 */
session_start();

// ===================================
// 쿠팡 API 클래스
// ===================================

class CoupangCartManager {
    private $cookieFile;
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    
    /* 생성자 */
    public function __construct() {
        // 쿠키 파일 경로 설정
        $this->cookieFile = __DIR__ . '/coupang_cookies.txt';
    }
    
    /* 쿠팡 로그인 - 개선된 버전 */
    public function login($email, $password) {
        // 쿠키 파일 초기화
        if (file_exists($this->cookieFile)) {
            unlink($this->cookieFile);
        }
        
        // 1단계: 메인 페이지 접속 (초기 쿠키 설정)
        $this->curlRequest('https://www.coupang.com/');
        
        // 2단계: 로그인 페이지 접속
        $loginPageUrl = 'https://login.coupang.com/login/login.pang?rtnUrl=https%3A%2F%2Fwww.coupang.com%2Fnp%2Fpost%2Flogin';
        $loginPageResponse = $this->curlRequest($loginPageUrl);
        
        // CSRF 토큰 추출 시도
        $csrfToken = '';
        if (preg_match('/name="_csrf"\s+value="([^"]+)"/', $loginPageResponse['body'], $matches)) {
            $csrfToken = $matches[1];
        }
        
        // 3단계: 로그인 요청
        $loginUrl = 'https://login.coupang.com/login/loginProcess.pang';
        
        $loginData = [
            'email' => $email,
            'password' => $password,
            'returnUrl' => 'https://www.coupang.com/np/post/login',
            'rememberMe' => 'true',
            'validationPassed' => 'true'
        ];
        
        if ($csrfToken) {
            $loginData['_csrf'] = $csrfToken;
        }
        
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Origin: https://login.coupang.com',
            'Referer: https://login.coupang.com/login/login.pang',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: ko-KR,ko;q=0.9,en;q=0.8',
            'Cache-Control: no-cache',
            'Pragma: no-cache'
        ];
        
        $response = $this->curlRequest($loginUrl, 'POST', http_build_query($loginData), $headers);
        
        // 디버깅 정보
        error_log("Login Response Code: " . $response['code']);
        error_log("Login Response Headers: " . substr($response['header'], 0, 500));
        
        // 로그인 성공 여부 확인
        if ($response['code'] == 302 || $response['code'] == 200) {
            // 리다이렉트 URL 추출
            if (preg_match('/Location:\s*([^\r\n]+)/', $response['header'], $matches)) {
                $redirectUrl = trim($matches[1]);
                
                // 상대 경로인 경우 절대 경로로 변환
                if (strpos($redirectUrl, 'http') !== 0) {
                    $redirectUrl = 'https://www.coupang.com' . $redirectUrl;
                }
                
                // 리다이렉트 따라가기
                $this->curlRequest($redirectUrl);
            }
            
            // 메인 페이지 재접속
            $this->curlRequest('https://www.coupang.com/');
            
            // 로그인 상태 확인
            sleep(1); // 잠시 대기
            if ($this->checkLoginStatus()) {
                return [
                    'success' => true,
                    'message' => '로그인 성공'
                ];
            }
        }
        
        // 실패 원인 분석
        $errorMessage = '로그인 실패';
        
        if (strpos($response['body'], 'captcha') !== false || strpos($response['body'], 'CAPTCHA') !== false) {
            $errorMessage = '보안 문자(CAPTCHA) 인증이 필요합니다. 브라우저에서 직접 로그인 후 사용해주세요.';
        } elseif (strpos($response['body'], '비밀번호') !== false && strpos($response['body'], '일치하지') !== false) {
            $errorMessage = '이메일 또는 비밀번호가 올바르지 않습니다.';
        } elseif (strpos($response['body'], '보안') !== false || strpos($response['body'], '인증') !== false) {
            $errorMessage = '추가 보안 인증이 필요합니다. 브라우저에서 직접 로그인 후 사용해주세요.';
        }
        
        return [
            'success' => false,
            'message' => $errorMessage,
            'debug' => [
                'status_code' => $response['code'],
                'has_csrf' => !empty($csrfToken)
            ]
        ];
    }
    
    /* URL에서 상품 정보 추출 */
    public function extractProductInfo($url) {
        // URL 패턴 매칭
        // https://www.coupang.com/vp/products/8135703822?itemId=23110133288&vendorItemId=90143413056
        
        $productInfo = [
            'productId' => null,
            'itemId' => null,
            'vendorItemId' => null
        ];
        
        // productId 추출
        if (preg_match('/\/products\/(\d+)/', $url, $matches)) {
            $productInfo['productId'] = $matches[1];
        }
        
        // URL 파라미터 파싱
        $urlParts = parse_url($url);
        if (isset($urlParts['query'])) {
            parse_str($urlParts['query'], $params);
            
            if (isset($params['itemId'])) {
                $productInfo['itemId'] = $params['itemId'];
            }
            if (isset($params['vendorItemId'])) {
                $productInfo['vendorItemId'] = $params['vendorItemId'];
            }
        }
        
        return $productInfo;
    }
    
    /* cURL 요청 함수 - 개선된 버전 */
    private function curlRequest($url, $method = 'GET', $data = null, $headers = []) {
        $ch = curl_init();
        
        // 기본 설정
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        
        // HTTP 버전 설정 (HTTP/1.1 사용)
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        
        // 헤더 설정
        $defaultHeaders = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: ko-KR,ko;q=0.9,en;q=0.8',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1'
        ];
        $headers = array_merge($defaultHeaders, $headers);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        // POST 요청 설정
        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? json_encode($data) : $data);
        }
        
        // 응답 헤더 포함
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        // 디버깅 옵션
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);
        
        $response = curl_exec($ch);
        
        if ($response === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            
            return [
                'code' => 0,
                'header' => '',
                'body' => '',
                'error' => $error,
                'errno' => $errno
            ];
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        
        $header = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        curl_close($ch);
        
        return [
            'code' => $httpCode,
            'header' => $header,
            'body' => $body,
            'error' => null
        ];
    }
    
    /* 장바구니에 상품 추가 */
    public function addToCart($productInfo, $quantity = 1) {
        // 장바구니 추가 API 엔드포인트
        $url = 'https://cart.coupang.com/cartApi/v2/cart-items';
        
        $data = [
            'items' => [[
                'productId' => $productInfo['productId'],
                'vendorItemId' => $productInfo['vendorItemId'],
                'itemId' => $productInfo['itemId'],
                'quantity' => $quantity
            ]]
        ];
        
        $response = $this->curlRequest($url, 'POST', $data);
        
        if ($response['code'] == 200 || $response['code'] == 201) {
            $result = json_decode($response['body'], true);
            return [
                'success' => true,
                'data' => $result
            ];
        } else {
            return [
                'success' => false,
                'code' => $response['code'],
                'message' => '장바구니 추가 실패',
                'body' => $response['body']
            ];
        }
    }
    
    /* 장바구니 정보 조회 */
    public function getCartInfo($cartItemId = null) {
        $url = 'https://cart.coupang.com/api/node/cart/content';
        if ($cartItemId) {
            $url .= '?cartItemIds=' . $cartItemId;
        }
        
        $response = $this->curlRequest($url);
        
        if ($response['code'] == 200) {
            $data = json_decode($response['body'], true);
            return [
                'success' => true,
                'data' => $data
            ];
        } else {
            return [
                'success' => false,
                'code' => $response['code'],
                'message' => '장바구니 조회 실패'
            ];
        }
    }
    
    /* 최대 구매 가능 수량 확인 */
    public function checkMaxQuantity($productInfo) {
        $result = [
            'maxQuantity' => 0,
            'remainQuantity' => 0,
            'maximumBuyForPerson' => 0
        ];
        
        // 1단계: 장바구니에 추가하여 정보 얻기
        $cartResult = $this->addToCart($productInfo, 1);
        
        if (!$cartResult['success']) {
            return ['success' => false, 'message' => '장바구니 추가 실패'];
        }
        
        // 2단계: 장바구니 정보 조회
        $cartInfo = $this->getCartInfo();
        
        if ($cartInfo['success'] && isset($cartInfo['data']['rData'])) {
            $cartData = $cartInfo['data']['rData']['shoppingCart'] ?? [];
            
            // 장바구니에서 해당 상품 찾기
            foreach ($cartData['skuBundleSet']['allBundleList'] ?? [] as $bundle) {
                foreach ($bundle['shoppingCartItemList'] ?? [] as $item) {
                    if ($item['vendorItemId'] == $productInfo['vendorItemId']) {
                        // 재고 정보
                        $result['remainQuantity'] = $item['quantityVO']['remainQuantity'] ?? 0;
                        
                        // 인당 최대 구매 수량
                        $result['maximumBuyForPerson'] = $item['policyVO']['maximumBuyForPerson'] ?? 0;
                        
                        // 실제 구매 가능 수량 (둘 중 작은 값)
                        $result['maxQuantity'] = min($result['remainQuantity'], $result['maximumBuyForPerson']);
                        
                        return [
                            'success' => true,
                            'data' => $result,
                            'productName' => $item['productName'] ?? '',
                            'salePrice' => $item['salePrice'] ?? 0
                        ];
                    }
                }
            }
        }
        
        return ['success' => false, 'message' => '상품 정보를 찾을 수 없습니다'];
    }
    
    /* 간단한 로그인 테스트 */
    public function simpleLogin($email, $password) {
        // 가장 기본적인 방법으로 시도
        $loginUrl = 'https://login.coupang.com/login/loginProcess.pang';
        
        $postData = sprintf(
            'email=%s&password=%s&returnUrl=%s',
            urlencode($email),
            urlencode($password),
            urlencode('https://www.coupang.com/')
        );
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $loginUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false, // 리다이렉트 수동 처리
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
                'Origin: https://login.coupang.com',
                'Referer: https://login.coupang.com/login/login.pang'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'message' => 'cURL 오류: ' . $error,
                'debug' => ['curl_error' => $error]
            ];
        }
        
        // 302 리다이렉트면 성공
        if ($httpCode == 302) {
            return [
                'success' => true,
                'message' => '로그인 성공 (세션이 저장되었습니다)'
            ];
        }
        
        return [
            'success' => false,
            'message' => '로그인 실패 (HTTP ' . $httpCode . ')',
            'debug' => ['status_code' => $httpCode]
        ];
    }
    public function checkLoginStatus() {
        $url = 'https://www.coupang.com/np/members/check-login-status';
        $response = $this->curlRequest($url);
        
        if ($response['code'] == 200) {
            $data = json_decode($response['body'], true);
            return $data['isLoggedIn'] ?? false;
        }
        
        return false;
    }
}

// ===================================
// 메인 처리 로직
// ===================================

/* AJAX 요청 처리 */
if (isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $manager = new CoupangCartManager();
    
    switch ($_POST['action']) {
        case 'login':
            if (empty($_POST['email']) || empty($_POST['password'])) {
                echo json_encode(['success' => false, 'message' => '이메일과 비밀번호를 입력해주세요']);
                exit;
            }
            
            // 간단한 로그인 시도
            $result = $manager->simpleLogin($_POST['email'], $_POST['password']);
            
            // 실패 시 기존 방법 시도
            if (!$result['success']) {
                $result = $manager->login($_POST['email'], $_POST['password']);
            }
            
            echo json_encode($result);
            exit;
            
        case 'check_max_quantity':
            // 로그인 상태 확인
            if (!$manager->checkLoginStatus()) {
                echo json_encode(['success' => false, 'message' => '로그인이 필요합니다']);
                exit;
            }
            
            if (empty($_POST['url'])) {
                echo json_encode(['success' => false, 'message' => 'URL이 필요합니다']);
                exit;
            }
            
            // URL에서 상품 정보 추출
            $productInfo = $manager->extractProductInfo($_POST['url']);
            
            if (!$productInfo['productId'] || !$productInfo['vendorItemId']) {
                echo json_encode(['success' => false, 'message' => '올바른 쿠팡 상품 URL이 아닙니다']);
                exit;
            }
            
            // 최대 수량 확인
            $result = $manager->checkMaxQuantity($productInfo);
            echo json_encode($result);
            exit;
            
        case 'check_login':
            $isLoggedIn = $manager->checkLoginStatus();
            echo json_encode(['success' => true, 'isLoggedIn' => $isLoggedIn]);
            exit;
    }
}

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>쿠팡 장바구니 관리 시스템</title>
    <style>
        /* =================================== 
         * 전체 스타일
         * =================================== */
        
        /* 기본 스타일 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
        }
        
        /* 컨테이너 */
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        /* 헤더 */
        h1 {
            color: #333;
            margin-bottom: 30px;
            text-align: center;
        }
        
        /* 폼 요소 */
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        input[type="text"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
        }
        
        button {
            background-color: #0073e6;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        button:hover {
            background-color: #0052a3;
        }
        
        button:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        
        /* 결과 영역 */
        #result {
            margin-top: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 5px;
            display: none;
        }
        
        .success {
            color: #28a745;
        }
        
        .error {
            color: #dc3545;
        }
        
        /* 로딩 */
        .loading {
            text-align: center;
            color: #666;
        }
        
        /* 안내 메시지 */
        .notice {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>쿠팡 장바구니 관리 시스템</h1>
        
        <!-- 로그인 폼 -->
        <div id="loginForm">
            <div class="notice">
                <strong>쿠팡 계정으로 로그인하세요</strong>
            </div>
            
            <div class="form-group">
                <label for="email">이메일:</label>
                <input type="email" id="email" placeholder="example@email.com">
            </div>
            
            <div class="form-group">
                <label for="password">비밀번호:</label>
                <input type="password" id="password" placeholder="비밀번호 입력">
            </div>
            
            /* 로그인 상태 확인 */
        <button onclick="login()">로그인</button>
        <button onclick="showAlternativeMethod()" style="margin-left: 10px; background-color: #6c757d;">다른 방법 사용</button>
        </div>
        
        <!-- 메인 기능 (로그인 후 표시) -->
        <div id="mainFunction" style="display: none;">
            <div class="notice" style="background-color: #d4edda; border-color: #c3e6cb; color: #155724;">
                <strong>✅ 로그인되었습니다!</strong><br>
                이제 상품 URL을 입력하여 최대 구매 가능 수량을 확인할 수 있습니다.
            </div>
            
            <div class="form-group">
                <label for="productUrl">쿠팡 상품 URL 입력:</label>
                <input type="text" id="productUrl" placeholder="https://www.coupang.com/vp/products/..." value="https://www.coupang.com/vp/products/8135703822?itemId=23110133288&vendorItemId=90143413056">
            </div>
            
            <button onclick="checkMaxQuantity()">최대 구매 가능 수량 확인</button>
            <button onclick="logout()" style="margin-left: 10px; background-color: #dc3545;">로그아웃</button>
        </div>
        
        <div id="result"></div>
    </div>
    
    <script>
        // ===================================
        // JavaScript 처리
        // ===================================
        
        /* 페이지 로드 시 로그인 상태 확인 */
        window.onload = async function() {
            checkInitialLoginStatus();
        }
        
        /* 초기 로그인 상태 확인 */
        async function checkInitialLoginStatus() {
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'check_login'
                    })
                });
                
                const data = await response.json();
                
                if (data.isLoggedIn) {
                    document.getElementById('loginForm').style.display = 'none';
                    document.getElementById('mainFunction').style.display = 'block';
                }
            } catch (error) {
                console.error('로그인 상태 확인 실패:', error);
            }
        }
        
        /* 로그인 */
        async function login() {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim();
            const resultDiv = document.getElementById('result');
            
            if (!email || !password) {
                alert('이메일과 비밀번호를 입력해주세요.');
                return;
            }
            
            resultDiv.innerHTML = '<div class="loading">로그인 중...</div>';
            resultDiv.style.display = 'block';
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'login',
                        email: email,
                        password: password
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = '<h3 class="success">✅ 로그인 성공!</h3>';
                    setTimeout(() => {
                        document.getElementById('loginForm').style.display = 'none';
                        document.getElementById('mainFunction').style.display = 'block';
                        resultDiv.style.display = 'none';
                    }, 1000);
                } else {
                    let debugInfo = '';
                    if (data.debug) {
                        debugInfo = `<br><small>상태 코드: ${data.debug.status_code}</small>`;
                    }
                    resultDiv.innerHTML = `
                        <h3 class="error">❌ ${data.message}</h3>
                        ${debugInfo}
                        <br><br>
                        <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 10px;">
                            <strong>대체 방법:</strong><br>
                            1. 브라우저에서 쿠팡에 직접 로그인<br>
                            2. 개발자 도구(F12) > Application > Cookies<br>
                            3. 쿠팡 쿠키 중 'PCID' 값 복사<br>
                            4. 아래 '다른 방법 사용' 버튼 클릭
                        </div>
                    `;
                }
            } catch (error) {
                resultDiv.innerHTML = `<h3 class="error">❌ 로그인 실패: ${error.message}</h3>`;
            }
        }
        
        /* 로그아웃 */
        function logout() {
            if (confirm('로그아웃 하시겠습니까?')) {
                // 쿠키 파일 삭제를 위해 페이지 새로고침
                document.getElementById('loginForm').style.display = 'block';
                document.getElementById('mainFunction').style.display = 'none';
                document.getElementById('result').innerHTML = '';
                document.getElementById('email').value = '';
                document.getElementById('password').value = '';
            }
        }
        
        /* 최대 수량 확인 */
        async function checkMaxQuantity() {
            const url = document.getElementById('productUrl').value.trim();
            const resultDiv = document.getElementById('result');
            
            if (!url) {
                alert('상품 URL을 입력해주세요.');
                return;
            }
            
            // 로딩 표시
            resultDiv.innerHTML = '<div class="loading">처리 중...</div>';
            resultDiv.style.display = 'block';
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'check_max_quantity',
                        url: url
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <h3 class="success">✅ 조회 성공</h3>
                        <p><strong>상품명:</strong> ${data.productName}</p>
                        <p><strong>판매가:</strong> ${data.salePrice.toLocaleString()}원</p>
                        <p><strong>재고 수량:</strong> ${data.data.remainQuantity}개</p>
                        <p><strong>인당 최대 구매 수량:</strong> ${data.data.maximumBuyForPerson}개</p>
                        <p><strong>실제 구매 가능 수량:</strong> <span style="font-size: 1.2em; color: #0073e6;">${data.data.maxQuantity}개</span></p>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <h3 class="error">❌ 조회 실패</h3>
                        <p>${data.message}</p>
                    `;
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <h3 class="error">❌ 오류 발생</h3>
                    <p>${error.message}</p>
                `;
            }
        }
        
        /* 대체 방법 표시 */
        function showAlternativeMethod() {
            const resultDiv = document.getElementById('result');
            resultDiv.innerHTML = `
                <div style="background-color: #e7f3ff; padding: 20px; border-radius: 5px; margin-top: 20px;">
                    <h3>🔐 수동 쿠키 설정 방법</h3>
                    <ol style="margin-top: 10px; line-height: 1.8;">
                        <li>브라우저에서 <a href="https://www.coupang.com" target="_blank">쿠팡</a>에 로그인</li>
                        <li>개발자 도구 열기 (F12)</li>
                        <li>Application 또는 Storage 탭 선택</li>
                        <li>Cookies > www.coupang.com 클릭</li>
                        <li>다음 쿠키 값들을 찾아서 메모:
                            <ul style="margin-top: 5px;">
                                <li><code>PCID</code></li>
                                <li><code>sid</code></li>
                                <li><code>session-id</code></li>
                            </ul>
                        </li>
                    </ol>
                    <div style="margin-top: 15px;">
                        <label>쿠키 값 입력 (PCID=값;sid=값 형식):</label>
                        <input type="text" id="manualCookie" style="width: 100%; margin-top: 5px;" placeholder="PCID=xxxxx;sid=xxxxx">
                        <button onclick="setManualCookie()" style="margin-top: 10px;">쿠키 설정</button>
                    </div>
                </div>
            `;
            resultDiv.style.display = 'block';
        }
        
        /* 수동 쿠키 설정 */
        async function setManualCookie() {
            const cookieValue = document.getElementById('manualCookie').value.trim();
            if (!cookieValue) {
                alert('쿠키 값을 입력해주세요.');
                return;
            }
            
            // 쿠키 파일에 직접 저장하는 방식으로 처리
            alert('이 기능은 서버 측 구현이 필요합니다. 현재는 브라우저에서 직접 로그인 후 사용해주세요.');
        }
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                if (document.getElementById('loginForm').style.display !== 'none') {
                    login();
                } else if (document.getElementById('mainFunction').style.display !== 'none') {
                    checkMaxQuantity();
                }
            }
        });
    </script>
</body>
</html>