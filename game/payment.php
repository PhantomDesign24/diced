<?php
/*
* 파일명: payment.php
* 위치: /game/payment.php
* 기능: 충전/출금 메인 페이지 (admin.php와 연동)
* 작성일: 2025-06-12
* 수정일: 2025-06-12 (admin.php 연동)
*/

// ===================================
// 그누보드 환경 설정
// ===================================
include_once('./../common.php');

// 로그인 체크
if (!$is_member) {
    alert('로그인이 필요합니다.', G5_BBS_URL.'/login.php?url='.urlencode(G5_URL.'/game/payment.php'));
}

// ===================================
// 충전/출금 설정 로드 (dice_game_config 테이블 사용)
// ===================================

/**
 * 설정값 조회 함수
 * 
 * @param string $key 설정 키
 * @param string $default 기본값
 * @return string 설정값
 */
function getPaymentConfig($key, $default = '') {
    $sql = "SELECT config_value FROM payment_config WHERE config_key = '{$key}'";
    $result = sql_fetch($sql);
    return $result ? $result['config_value'] : $default;
}

// 충전/출금 기본 설정
$payment_config = array(
    'min_charge_amount' => getPaymentConfig('min_charge_amount', '10000'),
    'max_charge_amount' => getPaymentConfig('max_charge_amount', '1000000'),
    'min_withdraw_amount' => getPaymentConfig('min_withdraw_amount', '10000'),
    'max_withdraw_amount' => getPaymentConfig('max_withdraw_amount', '1000000'),
    'system_status' => getPaymentConfig('system_status', '1')
);

// 시스템 상태 체크
if ($payment_config['system_status'] != '1') {
    alert('현재 충전/출금 시스템이 점검 중입니다.', G5_URL);
}

// ===================================
// 관리자 계좌 정보 조회 (payment_admin_accounts 테이블에서)
// ===================================
$admin_account = sql_fetch("SELECT * FROM payment_admin_accounts WHERE is_active = 1 ORDER BY display_order ASC LIMIT 1");

// 계좌 정보가 설정되지 않은 경우
if (!$admin_account) {
    alert('관리자 계좌 정보가 설정되지 않았습니다. 관리자에게 문의하세요.', './index.php');
}

$admin_bank_name = $admin_account['bank_name'];
$admin_account_number = $admin_account['account_number'];
$admin_account_holder = $admin_account['account_holder'];

// ===================================
// 회원 포인트 및 최근 신청 내역
// ===================================
function manual_get_point_sum_no_sum($mb_id) {
    $sql = "SELECT COALESCE(SUM(po_point), 0) as total_point FROM g5_point WHERE mb_id = '{$mb_id}'";
    $result = sql_fetch($sql);
    return intval($result['total_point']);
}

$member_point = manual_get_point_sum_no_sum($member['mb_id']);

// 최근 신청 내역 (최근 5건) - payment_requests 테이블이 있다면 사용
$recent_requests = array();
$recent_requests_sql = "SHOW TABLES LIKE 'payment_requests'";
$table_exists = sql_query($recent_requests_sql);

if (sql_num_rows($table_exists) > 0) {
    $recent_requests_sql = "
        SELECT * FROM payment_requests 
        WHERE mb_id = '{$member['mb_id']}' 
        ORDER BY created_at DESC 
        LIMIT 5
    ";
    $recent_requests_result = sql_query($recent_requests_sql);
    while ($row = sql_fetch_array($recent_requests_result)) {
        $recent_requests[] = $row;
    }
}

$g5['title'] = '충전/출금';
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="title" content="<?php echo $g5['title']; ?>">
    <title>충전/출금</title>
    
    <!-- Bootstrap CSS 비동기 로드 -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"></noscript>
    
    <!-- Bootstrap Icons -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css"></noscript>
    
    <!-- 게임 CSS -->
    <link rel="stylesheet" href="<?php echo G5_URL?>/game/css/game.css">
    
    <!-- 추가 스타일 -->
    <style>
        /* 베팅 버튼 스타일 (게임 CSS에서 누락된 경우 대비) */
        .bet-button {
            background: #f8f9fa !important;
            border: 2px solid #e9ecef !important;
            color: #495057 !important;
            border-radius: 8px !important;
            transition: all 0.2s ease !important;
        }
        
        .bet-button:hover {
            background: #007bff !important;
            border-color: #007bff !important;
            color: white !important;
            transform: translateY(-1px) !important;
        }
        
        .bet-button:active,
        .bet-button.active {
            background: #0056b3 !important;
            border-color: #0056b3 !important;
            color: white !important;
        }
        
        /* 입력 그룹 스타일 */
        .input-group-text {
            border-right: none !important;
            background-color: #fff !important;
        }
        
        .form-control {
            border-left: none !important;
            padding-left: 0 !important;
        }
        
        .form-control:focus {
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25) !important;
            border-color: #80bdff !important;
        }
        
        /* 계좌 정보 스타일 개선 */
        .stats-item {
            background: #f8f9fa !important;
            border: 1px solid #e9ecef !important;
            border-radius: 8px !important;
            padding: 15px !important;
        }
        
        /* 버튼 선택 효과 */
        .amount-selected {
            background: #007bff !important;
            border-color: #007bff !important;
            color: white !important;
        }

        /* 관리자 계좌 정보 강조 스타일 */
        .admin-account-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: white !important;
            border: none !important;
            border-radius: 12px !important;
            padding: 20px !important;
            margin-bottom: 15px !important;
        }

        .admin-account-card .fw-bold {
            font-size: 1.1rem !important;
        }

        .admin-account-card .text-primary {
            color: #fff !important;
            font-size: 1.3rem !important;
            font-weight: 700 !important;
        }

        .admin-account-card small {
            opacity: 0.9 !important;
        }

        .admin-account-card .btn-outline-primary {
            border-color: rgba(255, 255, 255, 0.5) !important;
            color: white !important;
        }

        .admin-account-card .btn-outline-primary:hover {
            background: rgba(255, 255, 255, 0.1) !important;
            border-color: white !important;
        }
    </style>
</head>

<body class="game-body">
    <div class="game-wrapper">
        <!-- ===================================
        결제 시스템 컨테이너
        =================================== -->
        <div class="container-fluid game-container">
            
            <!-- 헤더 -->
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-wallet2 me-2" style="font-size: 24px; color: #007bff;"></i>
                            <h4 class="mb-0 fw-bold">충전/출금</h4>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">보유 포인트</small>
                            <div class="h5 mb-0 text-primary fw-bold"><?php echo number_format($member_point); ?>P</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 탭 선택 -->
            <div class="card">
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6">
                            <button class="btn btn-primary w-100 py-3" id="chargeBtn" onclick="showTab('charge')">
                                <i class="bi bi-plus-circle me-2"></i>충전
                            </button>
                        </div>
                        <div class="col-6">
                            <button class="btn btn-outline-primary w-100 py-3" id="withdrawBtn" onclick="showTab('withdraw')">
                                <i class="bi bi-dash-circle me-2"></i>출금
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 충전 섹션 -->
            <div id="chargeSection">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted mb-3">충전 금액 선택</h6>
                        
                        <form id="chargeForm">
                            <!-- 금액 버튼들 -->
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <button type="button" class="btn bet-button w-100 py-3" onclick="selectAmount('charge', 10000)">
                                        <div class="fw-bold">10,000원</div>
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button type="button" class="btn bet-button w-100 py-3" onclick="selectAmount('charge', 30000)">
                                        <div class="fw-bold">30,000원</div>
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button type="button" class="btn bet-button w-100 py-3" onclick="selectAmount('charge', 50000)">
                                        <div class="fw-bold">50,000원</div>
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button type="button" class="btn bet-button w-100 py-3" onclick="selectAmount('charge', 100000)">
                                        <div class="fw-bold">100,000원</div>
                                    </button>
                                </div>
                            </div>

                            <!-- 직접 입력 -->
                            <div class="mb-3">
                                <label class="form-label">직접 입력</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0">
                                        <i class="bi bi-currency-dollar text-primary"></i>
                                    </span>
                                    <input type="number" class="form-control border-start-0" 
                                           id="chargeAmount" name="amount" 
                                           placeholder="충전할 금액을 입력하세요"
                                           min="<?php echo $payment_config['min_charge_amount']; ?>"
                                           max="<?php echo $payment_config['max_charge_amount']; ?>">
                                </div>
                                <small class="text-muted">
                                    최소: <?php echo number_format($payment_config['min_charge_amount']); ?>원, 
                                    최대: <?php echo number_format($payment_config['max_charge_amount']); ?>원
                                </small>
                            </div>

                            <!-- 입금자명 -->
                            <div class="mb-3">
                                <label class="form-label">입금자명</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0">
                                        <i class="bi bi-person text-primary"></i>
                                    </span>
                                    <input type="text" class="form-control border-start-0" 
                                           id="depositName" name="deposit_name" 
                                           placeholder="입금하실 분의 성함"
                                           value="<?php echo $member['mb_name']; ?>">
                                </div>
                            </div>

                            <input type="hidden" name="request_type" value="charge">
                            
                            <button type="submit" class="btn btn-warning w-100 py-3">
                                <i class="bi bi-plus-circle me-2"></i>충전 신청
                            </button>
                        </form>
                    </div>
                </div>

                <!-- 관리자 계좌 정보 (admin.php에서 설정한 정보) -->
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted mb-3">
                            <i class="bi bi-bank me-2"></i>입금 계좌 정보
                        </h6>
                        
                        <div class="admin-account-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold mb-1"><?php echo htmlspecialchars($admin_bank_name); ?></div>
                                    <div class="text-primary fw-bold mb-1" style="font-size: 1.3rem;">
                                        <?php echo htmlspecialchars($admin_account_number); ?>
                                    </div>
                                    <small><?php echo htmlspecialchars($admin_account_holder); ?></small>
                                </div>
                                <button type="button" class="btn btn-outline-primary btn-sm" 
                                        onclick="copyAccount('<?php echo htmlspecialchars($admin_account_number); ?>')">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                        </div>

                        <div class="alert alert-info mt-3">
                            <small>
                                <i class="bi bi-info-circle me-1"></i>
                                위 계좌로 입금 후 충전 신청해주세요. 관리자 확인 후 포인트가 지급됩니다.
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 출금 섹션 -->
            <div id="withdrawSection" style="display: none;">
                <div class="card">
                    <div class="card-body">
                        <h6 class="text-muted mb-3">출금 금액 선택</h6>
                        
                        <form id="withdrawForm">
                            <!-- 금액 버튼들 -->
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <button type="button" class="btn bet-button w-100 py-3" onclick="selectAmount('withdraw', 10000)">
                                        <div class="fw-bold">10,000원</div>
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button type="button" class="btn bet-button w-100 py-3" onclick="selectAmount('withdraw', 50000)">
                                        <div class="fw-bold">50,000원</div>
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button type="button" class="btn bet-button w-100 py-3" onclick="selectAmount('withdraw', 100000)">
                                        <div class="fw-bold">100,000원</div>
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button type="button" class="btn bet-button w-100 py-3" onclick="selectAmount('withdraw', Math.min(<?php echo $member_point; ?>, <?php echo $payment_config['max_withdraw_amount']; ?>))">
                                        <div class="fw-bold">전액출금</div>
                                    </button>
                                </div>
                            </div>

                            <!-- 직접 입력 -->
                            <div class="mb-3">
                                <label class="form-label">직접 입력</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0">
                                        <i class="bi bi-currency-dollar text-success"></i>
                                    </span>
                                    <input type="number" class="form-control border-start-0" 
                                           id="withdrawAmount" name="amount" 
                                           placeholder="출금할 금액을 입력하세요"
                                           min="<?php echo $payment_config['min_withdraw_amount']; ?>"
                                           max="<?php echo min($member_point, $payment_config['max_withdraw_amount']); ?>">
                                </div>
                                <small class="text-muted">
                                    최소: <?php echo number_format($payment_config['min_withdraw_amount']); ?>원, 
                                    최대: <?php echo number_format(min($member_point, $payment_config['max_withdraw_amount'])); ?>원
                                </small>
                            </div>

                            <!-- 은행 선택 -->
                            <div class="mb-3">
                                <label class="form-label">은행</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0">
                                        <i class="bi bi-bank text-success"></i>
                                    </span>
                                    <select class="form-control border-start-0" id="bankName" name="bank_name" required>
                                        <option value="">은행 선택</option>
                                        <option value="국민은행">국민은행</option>
                                        <option value="신한은행">신한은행</option>
                                        <option value="우리은행">우리은행</option>
                                        <option value="하나은행">하나은행</option>
                                        <option value="농협은행">농협은행</option>
                                        <option value="기업은행">기업은행</option>
                                        <option value="새마을금고">새마을금고</option>
                                        <option value="신협">신협</option>
                                        <option value="케이뱅크">케이뱅크</option>
                                        <option value="카카오뱅크">카카오뱅크</option>
                                        <option value="토스뱅크">토스뱅크</option>
                                    </select>
                                </div>
                            </div>

                            <!-- 계좌번호 -->
                            <div class="mb-3">
                                <label class="form-label">계좌번호</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0">
                                        <i class="bi bi-credit-card text-success"></i>
                                    </span>
                                    <input type="text" class="form-control border-start-0" 
                                           id="accountNumber" name="account_number" 
                                           placeholder="계좌번호 (- 없이 입력)" required>
                                </div>
                            </div>

                            <!-- 예금주명 -->
                            <div class="mb-3">
                                <label class="form-label">예금주명</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0">
                                        <i class="bi bi-person text-success"></i>
                                    </span>
                                    <input type="text" class="form-control border-start-0" 
                                           id="accountHolder" name="account_holder" 
                                           placeholder="예금주명" 
                                           value="<?php echo $member['mb_name']; ?>" required>
                                </div>
                            </div>

                            <input type="hidden" name="request_type" value="withdraw">
                            
                            <button type="submit" class="btn btn-warning w-100 py-3">
                                <i class="bi bi-bank me-2"></i>출금 신청
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- 최근 신청 내역 -->
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-3">최근 신청 내역</h6>
                    
                    <?php if (!empty($recent_requests)): ?>
                        <div class="history-list">
                            <?php foreach ($recent_requests as $request): ?>
                                <div class="history-item">
                                    <div class="history-header">
                                        <div class="round-info">
                                            <span class="round-number">
                                                <?php echo $request['request_type'] == 'charge' ? '충전' : '출금'; ?>
                                            </span>
                                            <span class="round-date"><?php echo date('m/d H:i', strtotime($request['created_at'])); ?></span>
                                        </div>
                                        <div class="result-badge">
                                            <?php 
                                            $status_class = [
                                                'pending' => 'badge-pending',
                                                'approved' => 'badge-win', 
                                                'completed' => 'badge-win',
                                                'rejected' => 'badge-lose'
                                            ];
                                            $status_text = [
                                                'pending' => '대기중',
                                                'approved' => '승인',
                                                'completed' => '완료',
                                                'rejected' => '거부'
                                            ];
                                            ?>
                                            <span class="badge <?php echo $status_class[$request['status']]; ?>">
                                                <?php echo $status_text[$request['status']]; ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <div class="bet-info-row">
                                        <div class="bet-details">
                                            <span class="bet-type">금액: <strong><?php echo number_format($request['amount']); ?>원</strong></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="payment_history.php" class="btn btn-outline-primary btn-sm">
                                전체 내역 보기
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="bi bi-inbox empty-icon"></i>
                            <h5 class="empty-title">신청 내역이 없습니다</h5>
                            <p class="empty-text">첫 충전 또는 출금을 신청해보세요!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 하단 메뉴 -->
            <div class="row g-2">
                <div class="col-6">
                    <a href="./index.php" class="btn btn-primary w-100">
                        <i class="bi bi-dice-6 me-1"></i>게임하기
                    </a>
                </div>
                <div class="col-6">
                    <a href="<?php echo G5_URL; ?>" class="btn btn-outline-dark w-100">
                        <i class="bi bi-house me-1"></i>홈으로
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // ===================================
        // 탭 전환 (주사위 게임과 동일한 스타일)
        // ===================================
        function showTab(tabName) {
            // 버튼 스타일 변경
            const chargeBtn = document.getElementById('chargeBtn');
            const withdrawBtn = document.getElementById('withdrawBtn');
            
            if (tabName === 'charge') {
                chargeBtn.className = 'btn btn-primary w-100 py-3';
                withdrawBtn.className = 'btn btn-outline-primary w-100 py-3';
                document.getElementById('chargeSection').style.display = 'block';
                document.getElementById('withdrawSection').style.display = 'none';
            } else {
                chargeBtn.className = 'btn btn-outline-primary w-100 py-3';
                withdrawBtn.className = 'btn btn-primary w-100 py-3';
                document.getElementById('chargeSection').style.display = 'none';
                document.getElementById('withdrawSection').style.display = 'block';
            }
        }
        
        // ===================================
        // 금액 선택 (주사위 게임과 동일한 스타일)
        // ===================================
        function selectAmount(type, amount) {
            // 모든 금액 버튼에서 선택 효과 제거
            document.querySelectorAll('.bet-button').forEach(btn => {
                btn.classList.remove('amount-selected', 'active');
            });
            
            // 클릭된 버튼에 선택 효과 추가
            event.target.closest('.bet-button').classList.add('amount-selected', 'active');
            
            // 입력 필드에 금액 설정
            document.getElementById(type + 'Amount').value = amount;
        }
        
        // ===================================
        // 계좌번호 복사
        // ===================================
        function copyAccount(accountNumber) {
            navigator.clipboard.writeText(accountNumber).then(() => {
                alert('계좌번호가 복사되었습니다: ' + accountNumber);
            }).catch(() => {
                alert('계좌번호: ' + accountNumber);
            });
        }
        
        // ===================================
        // 충전 신청 처리
        // ===================================
        document.getElementById('chargeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const amount = document.getElementById('chargeAmount').value;
            const depositName = document.getElementById('depositName').value;
            
            if (!amount || amount < <?php echo $payment_config['min_charge_amount']; ?>) {
                alert('최소 충전 금액은 <?php echo number_format($payment_config['min_charge_amount']); ?>원입니다.');
                return;
            }
            
            if (!depositName.trim()) {
                alert('입금자명을 입력해주세요.');
                return;
            }
            
            if (confirm(`${parseInt(amount).toLocaleString()}원 충전을 신청하시겠습니까?`)) {
                submitPaymentRequest('charge', amount, depositName);
            }
        });
        
        // ===================================
        // 출금 신청 처리
        // ===================================
        document.getElementById('withdrawForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const amount = document.getElementById('withdrawAmount').value;
            const bankName = document.getElementById('bankName').value;
            const accountNumber = document.getElementById('accountNumber').value;
            const accountHolder = document.getElementById('accountHolder').value;
            
            if (!amount || amount < <?php echo $payment_config['min_withdraw_amount']; ?>) {
                alert('최소 출금 금액은 <?php echo number_format($payment_config['min_withdraw_amount']); ?>원입니다.');
                return;
            }
            
            if (amount > <?php echo $member_point; ?>) {
                alert('보유 포인트보다 많은 금액은 출금할 수 없습니다.');
                return;
            }
            
            if (!bankName || !accountNumber || !accountHolder) {
                alert('계좌 정보를 모두 입력해주세요.');
                return;
            }
            
            if (confirm(`${parseInt(amount).toLocaleString()}원 출금을 신청하시겠습니까?`)) {
                submitPaymentRequest('withdraw', amount, null, bankName, accountNumber, accountHolder);
            }
        });
        
        // ===================================
        // 결제 신청 제출
        // ===================================
        function submitPaymentRequest(type, amount, depositName, bankName, accountNumber, accountHolder) {
            const formData = new FormData();
            formData.append('request_type', type);
            formData.append('amount', amount);
            
            if (type === 'charge') {
                formData.append('deposit_name', depositName);
            } else {
                formData.append('bank_name', bankName);
                formData.append('account_number', accountNumber);
                formData.append('account_holder', accountHolder);
            }
            
            fetch('payment_process.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('오류: ' + data.message);
                }
            })
            .catch(error => {
                alert('처리 중 오류가 발생했습니다.');
                console.error('Error:', error);
            });
        }
    </script>
</body>
</html>