<?php
/*
* 파일명: payment_history.php
* 위치: /game/payment_history.php
* 기능: 충전/출금 신청 내역 페이지
* 작성일: 2025-06-12
*/

// ===================================
// 그누보드 환경 설정
// ===================================
include_once('./../common.php');

// 로그인 체크
if (!$is_member) {
    alert('로그인이 필요합니다.', G5_BBS_URL.'/login.php?url='.urlencode(G5_URL.'/game/payment_history.php'));
}

// ===================================
// 페이징 설정
// ===================================
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// 필터 설정
$filter_type = isset($_GET['type']) ? trim($_GET['type']) : 'all';
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : 'all';

// ===================================
// 검색 조건 생성
// ===================================
$where_conditions = ["mb_id = '{$member['mb_id']}'"];

if ($filter_type !== 'all' && in_array($filter_type, ['charge', 'withdraw'])) {
    $where_conditions[] = "request_type = '{$filter_type}'";
}

if ($filter_status !== 'all' && in_array($filter_status, ['pending', 'approved', 'completed', 'rejected'])) {
    $where_conditions[] = "status = '{$filter_status}'";
}

$where_clause = implode(' AND ', $where_conditions);

// ===================================
// 데이터 조회
// ===================================

/* 전체 건수 조회 */
$total_sql = "SELECT COUNT(*) as total FROM payment_requests WHERE {$where_clause}";
$total_result = sql_fetch($total_sql);
$total_count = $total_result['total'];
$total_pages = ceil($total_count / $per_page);

/* 신청 내역 조회 */
$history_sql = "
    SELECT * FROM payment_requests 
    WHERE {$where_clause}
    ORDER BY created_at DESC 
    LIMIT {$offset}, {$per_page}
";
$history_result = sql_query($history_sql);

/* 통계 데이터 조회 */
$stats_sql = "
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN request_type = 'charge' THEN amount ELSE 0 END) as total_charge,
        SUM(CASE WHEN request_type = 'withdraw' THEN amount ELSE 0 END) as total_withdraw,
        SUM(CASE WHEN request_type = 'charge' AND status = 'completed' THEN amount ELSE 0 END) as completed_charge,
        SUM(CASE WHEN request_type = 'withdraw' AND status = 'completed' THEN amount ELSE 0 END) as completed_withdraw,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count
    FROM payment_requests 
    WHERE mb_id = '{$member['mb_id']}'
";
$stats = sql_fetch($stats_sql);

// ===================================
// 회원 포인트 조회
// ===================================
function manual_get_point_sum_no_sum($mb_id) {
    $sql = "SELECT COALESCE(SUM(po_point), 0) as total_point FROM g5_point WHERE mb_id = '{$mb_id}'";
    $result = sql_fetch($sql);
    return intval($result['total_point']);
}

$member_point = manual_get_point_sum_no_sum($member['mb_id']);

// ===================================
// 헬퍼 함수들
// ===================================

/**
 * 상태 텍스트 반환
 */
function getStatusText($status) {
    $status_map = [
        'pending' => '대기중',
        'approved' => '승인됨',
        'completed' => '완료',
        'rejected' => '거부됨'
    ];
    return isset($status_map[$status]) ? $status_map[$status] : $status;
}

/**
 * 상태 클래스 반환
 */
function getStatusClass($status) {
    $class_map = [
        'pending' => 'badge-pending',
        'approved' => 'badge-win',
        'completed' => 'badge-win',
        'rejected' => 'badge-lose'
    ];
    return isset($class_map[$status]) ? $class_map[$status] : 'badge-pending';
}

$g5['title'] = '충전/출금 내역';
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="title" content="<?php echo $g5['title']; ?>">
    <title>충전/출금 내역</title>
    
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
        .filter-button {
            background: #f8f9fa !important;
            border: 1px solid #e9ecef !important;
            color: #495057 !important;
            border-radius: 20px !important;
            padding: 8px 16px !important;
            margin: 2px !important;
            transition: all 0.2s ease !important;
            font-size: 0.9rem !important;
        }
        
        .filter-button:hover {
            background: #e9ecef !important;
            border-color: #dee2e6 !important;
        }
        
        .filter-button.active {
            background: #007bff !important;
            border-color: #007bff !important;
            color: white !important;
        }
        
        .request-detail {
            background: #f8f9fa !important;
            border-radius: 8px !important;
            padding: 10px !important;
            margin-top: 10px !important;
            font-size: 0.9rem !important;
        }
        
        .status-pending { background: #fff3cd !important; color: #856404 !important; }
        .status-approved { background: #d1ecf1 !important; color: #0c5460 !important; }
        .status-completed { background: #d4edda !important; color: #155724 !important; }
        .status-rejected { background: #f8d7da !important; color: #721c24 !important; }
    </style>
</head>

<body class="game-body">
    <div class="game-wrapper">
        <!-- ===================================
        히스토리 컨테이너
        =================================== -->
        <div class="container-fluid game-container">
            
            <!-- 헤더 -->
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-clock-history me-2" style="font-size: 24px; color: #007bff;"></i>
                            <h4 class="mb-0 fw-bold">충전/출금 내역</h4>
                        </div>
                        <div class="text-end">
                            <small class="text-muted">현재 포인트</small>
                            <div class="h6 mb-0 text-primary fw-bold"><?php echo number_format($member_point); ?>P</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 통계 카드 -->
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-3">나의 거래 통계</h6>
                    
                    <!-- 메인 통계 -->
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <div class="stats-item">
                                <div class="stats-number text-primary"><?php echo number_format($stats['total_requests']); ?></div>
                                <div class="stats-label">총 신청</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stats-item">
                                <div class="stats-number text-warning"><?php echo number_format($stats['pending_count']); ?></div>
                                <div class="stats-label">대기중</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 금액 통계 -->
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="stats-item">
                                <div class="stats-number text-success"><?php echo number_format($stats['completed_charge']); ?>원</div>
                                <div class="stats-label">총 충전</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stats-item">
                                <div class="stats-number text-info"><?php echo number_format($stats['completed_withdraw']); ?>원</div>
                                <div class="stats-label">총 출금</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 필터 -->
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-3">필터</h6>
                    
                    <!-- 타입 필터 -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">거래 유형</label>
                        <div>
                            <button class="btn filter-button <?php echo $filter_type === 'all' ? 'active' : ''; ?>" 
                                    onclick="applyFilter('type', 'all')">전체</button>
                            <button class="btn filter-button <?php echo $filter_type === 'charge' ? 'active' : ''; ?>" 
                                    onclick="applyFilter('type', 'charge')">충전</button>
                            <button class="btn filter-button <?php echo $filter_type === 'withdraw' ? 'active' : ''; ?>" 
                                    onclick="applyFilter('type', 'withdraw')">출금</button>
                        </div>
                    </div>
                    
                    <!-- 상태 필터 -->
                    <div class="mb-0">
                        <label class="form-label fw-bold">처리 상태</label>
                        <div>
                            <button class="btn filter-button <?php echo $filter_status === 'all' ? 'active' : ''; ?>" 
                                    onclick="applyFilter('status', 'all')">전체</button>
                            <button class="btn filter-button <?php echo $filter_status === 'pending' ? 'active' : ''; ?>" 
                                    onclick="applyFilter('status', 'pending')">대기중</button>
                            <button class="btn filter-button <?php echo $filter_status === 'completed' ? 'active' : ''; ?>" 
                                    onclick="applyFilter('status', 'completed')">완료</button>
                            <button class="btn filter-button <?php echo $filter_status === 'rejected' ? 'active' : ''; ?>" 
                                    onclick="applyFilter('status', 'rejected')">거부</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 신청 내역 목록 -->
            <div class="card">
                <div class="card-body">
                    <?php if ($total_count > 0): ?>
                        <h6 class="text-muted mb-3">
                            신청 내역 (총 <?php echo number_format($total_count); ?>건)
                        </h6>
                        
                        <div class="history-list">
                            <?php while ($request = sql_fetch_array($history_result)): ?>
                                <!-- 신청 내역 아이템 -->
                                <div class="history-item">
                                    <div class="history-header">
                                        <div class="round-info">
                                            <span class="round-number">
                                                <?php echo $request['request_type'] === 'charge' ? '충전' : '출금'; ?>
                                            </span>
                                            <span class="round-date"><?php echo date('m/d H:i', strtotime($request['created_at'])); ?></span>
                                        </div>
                                        <div class="result-badge">
                                            <span class="badge <?php echo getStatusClass($request['status']); ?>">
                                                <?php echo getStatusText($request['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- 기본 정보 -->
                                    <div class="bet-info-row">
                                        <div class="bet-details">
                                            <span class="bet-type">금액: <strong><?php echo number_format($request['amount']); ?>원</strong></span>
                                        </div>
                                    </div>
                                    
                                    <!-- 상세 정보 -->
                                    <div class="request-detail">
                                        <?php if ($request['request_type'] === 'charge'): ?>
                                            <div class="row">
                                                <div class="col-6">
                                                    <small class="text-muted">입금자명</small><br>
                                                    <strong><?php echo htmlspecialchars($request['deposit_name']); ?></strong>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">입금계좌</small><br>
                                                    <small><?php echo htmlspecialchars($request['admin_bank_info']); ?></small>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="row">
                                                <div class="col-12 mb-2">
                                                    <small class="text-muted">출금계좌</small><br>
                                                    <strong><?php echo htmlspecialchars($request['bank_name']); ?> <?php echo htmlspecialchars($request['account_number']); ?></strong>
                                                </div>
                                                <div class="col-6">
                                                    <small class="text-muted">예금주</small><br>
                                                    <?php echo htmlspecialchars($request['account_holder']); ?>
                                                </div>
                                                <?php if ($request['processed_at']): ?>
                                                <div class="col-6">
                                                    <small class="text-muted">처리일시</small><br>
                                                    <?php echo date('m/d H:i', strtotime($request['processed_at'])); ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($request['admin_memo']): ?>
                                            <div class="mt-2">
                                                <small class="text-muted">관리자 메모</small><br>
                                                <small><?php echo htmlspecialchars($request['admin_memo']); ?></small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($request['reject_reason']): ?>
                                            <div class="mt-2">
                                                <small class="text-danger">거부 사유</small><br>
                                                <small class="text-danger"><?php echo htmlspecialchars($request['reject_reason']); ?></small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <!-- 내역 없음 -->
                        <div class="empty-state">
                            <i class="bi bi-inbox empty-icon"></i>
                            <h5 class="empty-title">신청 내역이 없습니다</h5>
                            <p class="empty-text">
                                <?php if ($filter_type !== 'all' || $filter_status !== 'all'): ?>
                                    선택한 조건에 해당하는 내역이 없습니다.
                                <?php else: ?>
                                    첫 충전 또는 출금을 신청해보세요!
                                <?php endif; ?>
                            </p>
                            <a href="./payment.php" class="btn btn-primary">
                                <i class="bi bi-wallet2 me-1"></i>충전/출금 하기
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 페이징 -->
            <?php if ($total_pages > 1): ?>
                <div class="card">
                    <div class="card-body">
                        <nav aria-label="내역 페이지 네비게이션">
                            <ul class="pagination justify-content-center mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo buildPageUrl($page - 1); ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="<?php echo buildPageUrl($i); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="<?php echo buildPageUrl($page + 1); ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 하단 메뉴 -->
            <div class="row g-2">
                <div class="col-4">
                    <a href="./payment.php" class="btn btn-primary w-100">
                        <i class="bi bi-wallet2 me-1"></i>충전/출금
                    </a>
                </div>
                <div class="col-4">
                    <a href="./index.php" class="btn btn-outline-primary w-100">
                        <i class="bi bi-dice-6 me-1"></i>게임
                    </a>
                </div>
                <div class="col-4">
                    <a href="<?php echo G5_URL; ?>" class="btn btn-outline-dark w-100">
                        <i class="bi bi-house me-1"></i>홈
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php include_once('menu.php');?>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // ===================================
        // 필터 적용
        // ===================================
        function applyFilter(filterType, value) {
            const url = new URL(window.location);
            url.searchParams.set(filterType, value);
            url.searchParams.set('page', '1'); // 페이지를 1로 리셋
            window.location.href = url.toString();
        }
    </script>
</body>
</html>

<?php
// ===================================
// 헬퍼 함수: 페이지 URL 생성
// ===================================
function buildPageUrl($pageNum) {
    global $filter_type, $filter_status;
    $params = array();
    $params['page'] = $pageNum;
    if ($filter_type !== 'all') $params['type'] = $filter_type;
    if ($filter_status !== 'all') $params['status'] = $filter_status;
    return '?' . http_build_query($params);
}
?>