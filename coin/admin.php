<?php
/*
 * 파일명: admin.php
 * 위치: /admin.php
 * 기능: 관리자 설정 페이지
 * 작성일: 2024-12-27
 */

// ===================================
// 초기 설정
// ===================================

/* 필수 파일 포함 */
require_once(__DIR__ . '/config/config.php');
require_once(__DIR__ . '/includes/functions.php');

// 세션 시작
session_start();


// CSRF 토큰 생성
$csrf_token = generateCSRFToken();

// 현재 설정 로드
$settings = getAdminSettings();

// ===================================
// POST 처리
// ===================================

/* 설정 저장 처리 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF 토큰 검증
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        handleError('보안 토큰이 유효하지 않습니다.');
        header('Location: admin.php');
        exit;
    }
    
    // 메인 지갑 주소 검증
    $mainWallet = validateInput($_POST['main_wallet'] ?? '', 'address');
    if (!$mainWallet && !empty($_POST['main_wallet'])) {
        handleError('올바른 지갑 주소를 입력해주세요.');
        header('Location: admin.php');
        exit;
    }
    
    // 스왑 비율 처리
    $swapRates = [];
    if (isset($_POST['rate_from']) && isset($_POST['rate_to']) && isset($_POST['rate_value'])) {
        $rateCount = count($_POST['rate_from']);
        for ($i = 0; $i < $rateCount; $i++) {
            $from = validateInput($_POST['rate_from'][$i], 'token');
            $to = validateInput($_POST['rate_to'][$i], 'token');
            $value = validateInput($_POST['rate_value'][$i], 'number');
            
            if ($from && $to && $value > 0) {
                $key = $from . '_' . $to;
                $swapRates[$key] = $value;
            }
        }
    }
    
    // 설정 저장
    $saveData = [
        'main_wallet' => $mainWallet,
        'swap_rates' => $swapRates,
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ];
    
    if (saveAdminSettings($saveData)) {
        handleSuccess('설정이 성공적으로 저장되었습니다.');
    } else {
        handleError('설정 저장에 실패했습니다.');
    }
    
    header('Location: admin.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>관리자 - <?php echo SITE_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- ===================================
     * 네비게이션
     * =================================== -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-currency-exchange"></i> <?php echo SITE_NAME; ?>
            </a>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        <i class="bi bi-house"></i> 메인으로
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="logout.php">
                        <i class="bi bi-box-arrow-right"></i> 로그아웃
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- ===================================
     * 메인 컨테이너
     * =================================== -->
    <div class="container mt-5">
        <h2 class="mb-4"><i class="bi bi-gear"></i> 관리자 설정</h2>
        
        <?php if (isset($_SESSION['message'])): ?>
        <div class="alert alert-<?php echo $_SESSION['message']['type'] === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show">
            <?php 
            echo htmlspecialchars($_SESSION['message']['text']);
            unset($_SESSION['message']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form method="POST" action="admin.php">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <!-- 기본 설정 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-wallet2"></i> 기본 설정</h5>
                </div>
                <div class="card-body">
                    <!-- 메인 지갑 주소 -->
                    <div class="mb-3">
                        <label class="form-label">메인 지갑 주소</label>
                        <input type="text" class="form-control" name="main_wallet" 
                               value="<?php echo htmlspecialchars($settings['main_wallet'] ?? ''); ?>"
                               placeholder="0x... 또는 T...">
                        <small class="text-muted">사용자가 토큰을 전송할 메인 지갑 주소입니다.</small>
                    </div>
                    
                    <!-- 시스템 활성화 -->
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active"
                               <?php echo ($settings['is_active'] ?? 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">
                            스왑 시스템 활성화
                        </label>
                    </div>
                </div>
            </div>

            <!-- 스왑 비율 설정 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-arrow-left-right"></i> 스왑 비율 설정</h5>
                </div>
                <div class="card-body">
                    <div id="rateContainer">
                        <?php
                        $rates = $settings['swap_rates'] ?? [];
                        if (empty($rates)) {
                            // 기본 비율
                            $rates = [
                                'ETH_USDT' => 1800,
                                'ETH_USDC' => 1800,
                                'TRX_USDT' => 0.08
                            ];
                        }
                        
                        $i = 0;
                        foreach ($rates as $pair => $rate):
                            list($from, $to) = explode('_', $pair);
                        ?>
                        <div class="row mb-3 rate-row">
                            <div class="col-md-3">
                                <select class="form-select" name="rate_from[]">
                                    <option value="ETH" <?php echo $from === 'ETH' ? 'selected' : ''; ?>>ETH</option>
                                    <option value="TRX" <?php echo $from === 'TRX' ? 'selected' : ''; ?>>TRX</option>
                                    <option value="USDT" <?php echo $from === 'USDT' ? 'selected' : ''; ?>>USDT</option>
                                    <option value="USDC" <?php echo $from === 'USDC' ? 'selected' : ''; ?>>USDC</option>
                                </select>
                            </div>
                            <div class="col-md-1 text-center pt-2">
                                <i class="bi bi-arrow-right"></i>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="rate_to[]">
                                    <option value="USDT" <?php echo $to === 'USDT' ? 'selected' : ''; ?>>USDT</option>
                                    <option value="USDC" <?php echo $to === 'USDC' ? 'selected' : ''; ?>>USDC</option>
                                    <option value="ETH" <?php echo $to === 'ETH' ? 'selected' : ''; ?>>ETH</option>
                                    <option value="TRX" <?php echo $to === 'TRX' ? 'selected' : ''; ?>>TRX</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="number" class="form-control" name="rate_value[]" 
                                       value="<?php echo $rate; ?>" step="0.000001" placeholder="비율">
                            </div>
                            <div class="col-md-2">
                                <?php if ($i > 0): ?>
                                <button type="button" class="btn btn-danger btn-sm remove-rate">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php 
                        $i++;
                        endforeach; 
                        ?>
                    </div>
                    
                    <button type="button" class="btn btn-secondary" id="addRate">
                        <i class="bi bi-plus"></i> 비율 추가
                    </button>
                    
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i>
                        1 [From Token] = [Value] [To Token] 형식으로 입력하세요.
                        <br>예: 1 ETH = 1800 USDT
                    </div>
                </div>
            </div>

            <!-- 트랜잭션 통계 -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> 통계</h5>
                </div>
                <div class="card-body">
                    <?php
                    $pdo = getDB();
                    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(from_amount) as volume FROM transactions WHERE status = 'completed'");
                    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                    ?>
                    <div class="row">
                        <div class="col-md-6">
                            <h6>총 트랜잭션</h6>
                            <p class="h4"><?php echo number_format($stats['total'] ?? 0); ?>건</p>
                        </div>
                        <div class="col-md-6">
                            <h6>총 거래량</h6>
                            <p class="h4"><?php echo number_format($stats['volume'] ?? 0, 2); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 저장 버튼 -->
            <div class="text-end mb-5">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-save"></i> 설정 저장
                </button>
            </div>
        </form>
    </div>

    <!-- ===================================
     * 스크립트
     * =================================== -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    $(document).ready(function() {
        // 비율 추가
        $('#addRate').click(function() {
            const newRow = `
                <div class="row mb-3 rate-row">
                    <div class="col-md-3">
                        <select class="form-select" name="rate_from[]">
                            <option value="ETH">ETH</option>
                            <option value="TRX">TRX</option>
                            <option value="USDT">USDT</option>
                            <option value="USDC">USDC</option>
                        </select>
                    </div>
                    <div class="col-md-1 text-center pt-2">
                        <i class="bi bi-arrow-right"></i>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="rate_to[]">
                            <option value="USDT">USDT</option>
                            <option value="USDC">USDC</option>
                            <option value="ETH">ETH</option>
                            <option value="TRX">TRX</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="number" class="form-control" name="rate_value[]" 
                               step="0.000001" placeholder="비율">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-danger btn-sm remove-rate">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            $('#rateContainer').append(newRow);
        });
        
        // 비율 삭제
        $(document).on('click', '.remove-rate', function() {
            $(this).closest('.rate-row').remove();
        });
    });
    </script>
</body>
</html>