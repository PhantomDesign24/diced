<?php
/*
 * 파일명: coupang_selenium_system.php
 * 위치: /
 * 기능: Selenium을 활용한 쿠팡 장바구니 관리
 * 작성일: 2025-01-19
 */

// ===================================
// 초기 설정
// ===================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Composer autoload (selenium-webdriver 설치 필요)
// composer require php-webdriver/webdriver
require_once 'vendor/autoload.php';

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\Cookie;
use Facebook\WebDriver\Chrome\ChromeOptions;

// ===================================
// Selenium 쿠팡 관리 클래스
// ===================================

class CoupangSeleniumManager {
    private $driver;
    private $host = 'http://localhost:4444/wd/hub'; // Selenium 서버 주소
    private $isLoggedIn = false;
    private $sessionFile = __DIR__ . '/coupang_session.json';
    
    /* 생성자 */
    public function __construct() {
        $this->initDriver();
        $this->loadSession();
    }
    
    /* WebDriver 초기화 */
    private function initDriver() {
        $options = new ChromeOptions();
        
        // 헤드리스 모드 옵션 (선택사항)
        // $options->addArguments(['--headless']);
        
        // 기타 옵션
        $options->addArguments([
            '--disable-blink-features=AutomationControlled',
            '--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);
        
        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);
        
        try {
            $this->driver = RemoteWebDriver::create($this->host, $capabilities);
        } catch (\Exception $e) {
            throw new Exception("Selenium 서버 연결 실패: " . $e->getMessage());
        }
    }
    
    /* 세션 저장 */
    private function saveSession() {
        if ($this->isLoggedIn) {
            $cookies = $this->driver->manage()->getCookies();
            $sessionData = [
                'cookies' => $cookies,
                'timestamp' => time()
            ];
            file_put_contents($this->sessionFile, json_encode($sessionData));
        }
    }
    
    /* 세션 로드 */
    private function loadSession() {
        if (file_exists($this->sessionFile)) {
            $sessionData = json_decode(file_get_contents($this->sessionFile), true);
            
            // 24시간 이내의 세션만 사용
            if (isset($sessionData['timestamp']) && (time() - $sessionData['timestamp']) < 86400) {
                try {
                    // 쿠팡 메인 페이지 접속
                    $this->driver->get('https://www.coupang.com');
                    
                    // 저장된 쿠키 복원
                    foreach ($sessionData['cookies'] as $cookie) {
                        $this->driver->manage()->addCookie($cookie);
                    }
                    
                    // 페이지 새로고침
                    $this->driver->navigate()->refresh();
                    
                    // 로그인 상태 확인
                    $this->isLoggedIn = $this->checkLoginStatus();
                } catch (\Exception $e) {
                    $this->isLoggedIn = false;
                }
            }
        }
    }
    
    /* 로그인 */
    public function login($email, $password) {
        try {
            // 로그인 페이지로 이동
            $this->driver->get('https://login.coupang.com/login/login.pang');
            
            $wait = new WebDriverWait($this->driver, 10);
            
            // 이메일 입력
            $emailField = $wait->until(
                WebDriverExpectedCondition::presenceOfElementLocated(
                    WebDriverBy::id('login-email-input')
                )
            );
            $emailField->clear();
            $emailField->sendKeys($email);
            
            // 비밀번호 입력
            $passwordField = $this->driver->findElement(WebDriverBy::id('login-password-input'));
            $passwordField->clear();
            $passwordField->sendKeys($password);
            
            // 로그인 버튼 클릭
            $loginButton = $this->driver->findElement(WebDriverBy::cssSelector('.login__button'));
            $loginButton->click();
            
            // 로그인 완료 대기 (최대 10초)
            sleep(3);
            
            // CAPTCHA 확인
            try {
                $captcha = $this->driver->findElement(WebDriverBy::cssSelector('.captcha-wrap'));
                if ($captcha->isDisplayed()) {
                    return [
                        'success' => false,
                        'message' => 'CAPTCHA 인증이 필요합니다. 수동으로 해결해주세요.',
                        'needManual' => true
                    ];
                }
            } catch (\Exception $e) {
                // CAPTCHA 없음
            }
            
            // 로그인 성공 여부 확인
            $currentUrl = $this->driver->getCurrentURL();
            if (strpos($currentUrl, 'login.coupang.com') === false) {
                $this->isLoggedIn = true;
                $this->saveSession();
                return [
                    'success' => true,
                    'message' => '로그인 성공'
                ];
            }
            
            return [
                'success' => false,
                'message' => '로그인 실패. 이메일과 비밀번호를 확인해주세요.'
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '로그인 중 오류 발생: ' . $e->getMessage()
            ];
        }
    }
    
    /* 로그인 상태 확인 */
    private function checkLoginStatus() {
        try {
            $this->driver->get('https://www.coupang.com');
            sleep(1);
            
            // 로그인 버튼이 있으면 로그아웃 상태
            try {
                $loginButton = $this->driver->findElement(WebDriverBy::cssSelector('a[href*="login.coupang.com"]'));
                return false;
            } catch (\Exception $e) {
                // 로그인 버튼이 없으면 로그인 상태
                return true;
            }
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /* 상품 정보 및 최대 수량 확인 */
    public function checkProductMaxQuantity($productUrl) {
        if (!$this->isLoggedIn) {
            return [
                'success' => false,
                'message' => '로그인이 필요합니다.'
            ];
        }
        
        try {
            // 상품 페이지로 이동
            $this->driver->get($productUrl);
            
            $wait = new WebDriverWait($this->driver, 10);
            
            // 상품 정보 추출
            $productName = $wait->until(
                WebDriverExpectedCondition::presenceOfElementLocated(
                    WebDriverBy::cssSelector('.prod-buy-header__title')
                )
            )->getText();
            
            // 가격 정보
            try {
                $priceElement = $this->driver->findElement(WebDriverBy::cssSelector('.total-price strong'));
                $price = $priceElement->getText();
            } catch (\Exception $e) {
                $price = '가격 정보 없음';
            }
            
            // 수량 선택 박스 찾기
            try {
                $quantitySelect = $this->driver->findElement(WebDriverBy::cssSelector('.prod-quantity__input'));
                
                // JavaScript로 최대값 확인
                $maxQuantity = $this->driver->executeScript(
                    "return arguments[0].getAttribute('max') || arguments[0].options[arguments[0].options.length-1].value;",
                    [$quantitySelect]
                );
                
                // 재고 정보 확인 (상품 상세 정보에서)
                $remainQuantity = $maxQuantity; // 기본적으로 최대 구매 가능 수량과 동일
                
                return [
                    'success' => true,
                    'data' => [
                        'productName' => $productName,
                        'price' => $price,
                        'maxQuantity' => intval($maxQuantity),
                        'remainQuantity' => intval($remainQuantity)
                    ]
                ];
                
            } catch (\Exception $e) {
                // 수량 선택이 없는 경우 (품절 등)
                return [
                    'success' => false,
                    'message' => '수량 정보를 찾을 수 없습니다. (품절 상품일 수 있습니다)'
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '상품 정보 조회 실패: ' . $e->getMessage()
            ];
        }
    }
    
    /* 장바구니에 추가하여 정보 확인 (더 정확한 방법) */
    public function checkViaCart($productUrl) {
        if (!$this->isLoggedIn) {
            return [
                'success' => false,
                'message' => '로그인이 필요합니다.'
            ];
        }
        
        try {
            // 상품 페이지로 이동
            $this->driver->get($productUrl);
            
            $wait = new WebDriverWait($this->driver, 10);
            
            // 장바구니 담기 버튼 클릭
            $cartButton = $wait->until(
                WebDriverExpectedCondition::elementToBeClickable(
                    WebDriverBy::cssSelector('.prod-cart-btn')
                )
            );
            $cartButton->click();
            
            sleep(2);
            
            // 장바구니 페이지로 이동
            $this->driver->get('https://cart.coupang.com/cartView.pang');
            
            sleep(2);
            
            // 장바구니에서 정보 추출
            $cartItems = $this->driver->findElements(WebDriverBy::cssSelector('.cart-item'));
            
            if (count($cartItems) > 0) {
                $lastItem = $cartItems[count($cartItems) - 1];
                
                // 상품명
                $productName = $lastItem->findElement(WebDriverBy::cssSelector('.item-title'))->getText();
                
                // 수량 선택 박스에서 최대값 확인
                $quantitySelect = $lastItem->findElement(WebDriverBy::cssSelector('.quantity-select'));
                $options = $quantitySelect->findElements(WebDriverBy::tagName('option'));
                
                $maxQuantity = 0;
                foreach ($options as $option) {
                    $value = intval($option->getAttribute('value'));
                    if ($value > $maxQuantity) {
                        $maxQuantity = $value;
                    }
                }
                
                // 가격
                $priceText = $lastItem->findElement(WebDriverBy::cssSelector('.unit-price'))->getText();
                
                return [
                    'success' => true,
                    'data' => [
                        'productName' => $productName,
                        'price' => $priceText,
                        'maxQuantity' => $maxQuantity,
                        'method' => 'cart'
                    ]
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '장바구니 확인 실패: ' . $e->getMessage()
            ];
        }
    }
    
    /* 드라이버 종료 */
    public function quit() {
        if ($this->driver) {
            $this->driver->quit();
        }
    }
    
    /* 소멸자 */
    public function __destruct() {
        // 세션 유지를 위해 드라이버는 종료하지 않음
        // $this->quit();
    }
}

// ===================================
// AJAX 요청 처리
// ===================================

if (isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    try {
        $manager = new CoupangSeleniumManager();
        
        switch ($_POST['action']) {
            case 'login':
                $result = $manager->login($_POST['email'], $_POST['password']);
                echo json_encode($result);
                break;
                
            case 'check_product':
                $result = $manager->checkProductMaxQuantity($_POST['url']);
                
                // 첫 번째 방법이 실패하면 장바구니 방법 시도
                if (!$result['success']) {
                    $result = $manager->checkViaCart($_POST['url']);
                }
                
                echo json_encode($result);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => '잘못된 요청']);
        }
    } catch (\Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    
    exit;
}

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>쿠팡 Selenium 장바구니 관리</title>
    <style>
        /* =================================== 
         * 전체 스타일
         * =================================== */
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f5f5f5;
            padding: 20px;
            margin: 0;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .info-box {
            background-color: #e7f3ff;
            border: 1px solid #b8daff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }
        
        input[type="text"], input[type="email"], input[type="password"] {
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
        
        #result {
            margin-top: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 5px;
            display: none;
        }
        
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .loading { text-align: center; color: #666; }
        
        /* 설치 가이드 */
        .setup-guide {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        
        .setup-guide h3 {
            margin-top: 0;
            color: #856404;
        }
        
        .setup-guide code {
            background-color: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>쿠팡 Selenium 장바구니 관리</h1>
        
        <div class="setup-guide">
            <h3>⚙️ 초기 설정 필요</h3>
            <ol>
                <li><strong>Selenium Server 설치:</strong><br>
                    <code>java -jar selenium-server-standalone.jar</code>
                </li>
                <li><strong>ChromeDriver 설치:</strong><br>
                    시스템에 맞는 ChromeDriver 다운로드
                </li>
                <li><strong>PHP WebDriver 설치:</strong><br>
                    <code>composer require php-webdriver/webdriver</code>
                </li>
            </ol>
        </div>
        
        <div class="info-box">
            <strong>👤 관리자 계정 로그인</strong><br>
            한 번 로그인하면 세션이 유지되어 여러 사용자가 사용할 수 있습니다.
        </div>
        
        <!-- 로그인 섹션 -->
        <div id="loginSection">
            <h2>로그인</h2>
            <div class="form-group">
                <label for="email">이메일:</label>
                <input type="email" id="email" placeholder="example@email.com">
            </div>
            <div class="form-group">
                <label for="password">비밀번호:</label>
                <input type="password" id="password" placeholder="비밀번호">
            </div>
            <button onclick="login()">로그인</button>
        </div>
        
        <!-- 상품 조회 섹션 -->
        <div id="productSection" style="margin-top: 30px;">
            <h2>상품 조회</h2>
            <div class="form-group">
                <label for="productUrl">쿠팡 상품 URL:</label>
                <input type="text" id="productUrl" placeholder="https://www.coupang.com/vp/products/...">
            </div>
            <button onclick="checkProduct()">최대 구매 가능 수량 확인</button>
        </div>
        
        <div id="result"></div>
    </div>
    
    <script>
        /* 로그인 */
        async function login() {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim();
            const resultDiv = document.getElementById('result');
            
            if (!email || !password) {
                alert('이메일과 비밀번호를 입력해주세요.');
                return;
            }
            
            resultDiv.innerHTML = '<div class="loading">로그인 중... (최대 30초 소요)</div>';
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
                    resultDiv.innerHTML = '<h3 class="success">✅ 로그인 성공! 이제 상품을 조회할 수 있습니다.</h3>';
                } else {
                    resultDiv.innerHTML = `<h3 class="error">❌ ${data.message}</h3>`;
                    if (data.needManual) {
                        resultDiv.innerHTML += '<p>브라우저 창에서 직접 CAPTCHA를 해결한 후 다시 시도해주세요.</p>';
                    }
                }
            } catch (error) {
                resultDiv.innerHTML = `<h3 class="error">❌ 오류: ${error.message}</h3>`;
            }
        }
        
        /* 상품 확인 */
        async function checkProduct() {
            const url = document.getElementById('productUrl').value.trim();
            const resultDiv = document.getElementById('result');
            
            if (!url) {
                alert('상품 URL을 입력해주세요.');
                return;
            }
            
            resultDiv.innerHTML = '<div class="loading">상품 정보 조회 중...</div>';
            resultDiv.style.display = 'block';
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'check_product',
                        url: url
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <h3 class="success">✅ 조회 성공</h3>
                        <p><strong>상품명:</strong> ${data.data.productName}</p>
                        <p><strong>가격:</strong> ${data.data.price}</p>
                        <p><strong>최대 구매 가능 수량:</strong> <span style="font-size: 1.2em; color: #0073e6;">${data.data.maxQuantity}개</span></p>
                        ${data.data.method === 'cart' ? '<p><small>* 장바구니를 통해 확인됨</small></p>' : ''}
                    `;
                } else {
                    resultDiv.innerHTML = `<h3 class="error">❌ ${data.message}</h3>`;
                }
            } catch (error) {
                resultDiv.innerHTML = `<h3 class="error">❌ 오류: ${error.message}</h3>`;
            }
        }
    </script>
</body>
</html>