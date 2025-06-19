<?php
/*
* 파일명: history.php
* 위치: /game/history.php
* 기능: 주사위 게임 히스토리 페이지 (깔끔한 디자인)
* 작성일: 2025-06-12
* 수정일: 2025-06-12
*/

// ===================================
// 그누보드 환경 설정
// ===================================
include_once('./../common.php');

// 로그인 체크
if (!$is_member) {
    alert('로그인이 필요합니다.', G5_BBS_URL.'/login.php?url='.urlencode(G5_URL.'/game/history.php'));
}

// ===================================
// 페이징 설정
// ===================================
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// ===================================
// 히스토리 데이터 조회
// ===================================

/* 전체 베팅 수 조회 */
$total_sql = "SELECT COUNT(*) as total FROM dice_game_bets WHERE mb_id = '{$member['mb_id']}'";
$total_result = sql_fetch($total_sql);
$total_count = $total_result['total'];
$total_pages = ceil($total_count / $per_page);

/* 베팅 히스토리 조회 (회차 정보와 함께) */
$history_sql = "
    SELECT 
        b.*,
        r.dice1, r.dice2, r.dice3, r.total as round_total,
        r.is_high, r.is_odd, r.status as round_status
    FROM dice_game_bets b
    LEFT JOIN dice_game_rounds r ON b.round_id = r.round_id
    WHERE b.mb_id = '{$member['mb_id']}' 
    ORDER BY b.created_at DESC 
    LIMIT {$offset}, {$per_page}
";
$history_result = sql_query($history_sql);

/* 통계 데이터 조회 */
$stats_sql = "
    SELECT 
        COUNT(*) as total_bets,
        SUM(bet_amount) as total_bet,
        SUM(win_amount) as total_win,
        SUM(CASE WHEN is_win = 1 THEN 1 ELSE 0 END) as win_count,
        COUNT(DISTINCT round_number) as total_rounds
    FROM dice_game_bets 
    WHERE mb_id = '{$member['mb_id']}'
";
$stats = sql_fetch($stats_sql);

// ===================================
// 헬퍼 함수들
// ===================================

/**
 * 베팅 타입을 한글로 변환
 * @param string $high_low 대소 베팅
 * @param string $odd_even 홀짝 베팅
 * @return string 한글 베팅 타입
 */
function getBetText($high_low, $odd_even) {
    $high_low_text = $high_low === 'high' ? '대' : '소';
    $odd_even_text = $odd_even === 'odd' ? '홀' : '짝';
    return $high_low_text . ' ' . $odd_even_text;
}

/**
 * 회차 결과를 한글로 변환
 * @param int $is_high 대소 결과
 * @param int $is_odd 홀짝 결과
 * @return string 한글 결과
 */
function getResultText($is_high, $is_odd) {
    $high_low_text = $is_high ? '대' : '소';
    $odd_even_text = $is_odd ? '홀' : '짝';
    return $high_low_text . ' ' . $odd_even_text;
}

// ===================================
// 페이지 헤더 (CSS 로드 제어)
// ===================================
$g5['title'] = '주사위 게임 히스토리';
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="title" content="<?php echo $g5['title']; ?>">
    <title>게임 히스토리</title>
    
    <!-- Bootstrap CSS 비동기 로드 -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"></noscript>
    
    <!-- Bootstrap Icons -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css"></noscript>
    
    <!-- 게임 CSS -->
    <link rel="stylesheet" href="<?php echo G5_URL?>/game/css/game.css">
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
                            <h4 class="mb-0 fw-bold">베팅 히스토리</h4>
                        </div>
                        <a href="./index.php" class="btn btn-primary btn-sm">
                            <i class="bi bi-arrow-left me-1"></i>게임으로
                        </a>
                    </div>
                </div>
            </div>

            <!-- 통계 카드 -->
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-3">나의 게임 통계</h6>
                    
                    <!-- 메인 통계 -->
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <div class="stats-item">
                                <div class="stats-number text-primary"><?php echo number_format($stats['total_rounds']); ?></div>
                                <div class="stats-label">참여회차</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stats-item">
                                <div class="stats-number text-info"><?php echo number_format($stats['total_bets']); ?></div>
                                <div class="stats-label">총 베팅</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 수익 정보 -->
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <div class="stats-item">
                                <div class="stats-number text-success"><?php echo number_format($stats['win_count']); ?></div>
                                <div class="stats-label">승리</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="stats-item">
                                <div class="stats-number">
                                    <?php echo $stats['total_bets'] > 0 ? number_format(($stats['win_count'] / $stats['total_bets']) * 100, 1) . '%' : '0%'; ?>
                                </div>
                                <div class="stats-label">승률</div>
                            </div>
                        </div>

                    </div>
                        <div class="col-12">
                            <div class="stats-item">
                                <div class="stats-number <?php echo ($stats['total_win'] - $stats['total_bet']) >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo number_format($stats['total_win'] - $stats['total_bet']); ?>P
                                </div>
                                <div class="stats-label">수익</div>
                            </div>
                        </div>
                </div>
            </div>

            <!-- 히스토리 목록 -->
            <div class="card">
                <div class="card-body">
                    <?php if ($total_count > 0): ?>
                        <div class="history-list">
                            <?php while ($row = sql_fetch_array($history_result)): ?>
                                <!-- 히스토리 아이템 -->
                                <div class="history-item">
                                    <div class="history-header">
                                        <div class="round-info">
                                            <span class="round-number">#<?php echo $row['round_number']; ?></span>
                                            <span class="round-date"><?php echo date('m/d H:i', strtotime($row['created_at'])); ?></span>
                                        </div>
                                        <div class="result-badge">
                                            <?php if ($row['is_win'] === '1'): ?>
                                                <span class="badge badge-win">
                                                    <i class="bi bi-trophy me-1"></i>승리
                                                </span>
                                            <?php elseif ($row['is_win'] === '0'): ?>
                                                <span class="badge badge-lose">
                                                    <i class="bi bi-x-circle me-1"></i>패배
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-pending">
                                                    <i class="bi bi-clock me-1"></i>대기
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if ($row['round_status'] === 'completed'): ?>
                                        <!-- 주사위 결과 -->
                                        <div class="dice-result-row">
                                            <div class="dice-container-mini">
                                                <div class="dice-mini dice-<?php echo $row['dice1']; ?>">
                                                    <?php for($i = 0; $i < $row['dice1']; $i++): ?>
                                                        <div class="dice-dot-mini"></div>
                                                    <?php endfor; ?>
                                                </div>
                                                <div class="dice-mini dice-<?php echo $row['dice2']; ?>">
                                                    <?php for($i = 0; $i < $row['dice2']; $i++): ?>
                                                        <div class="dice-dot-mini"></div>
                                                    <?php endfor; ?>
                                                </div>
                                                <div class="dice-mini dice-<?php echo $row['dice3']; ?>">
                                                    <?php for($i = 0; $i < $row['dice3']; $i++): ?>
                                                        <div class="dice-dot-mini"></div>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            <div class="result-text">
                                                <strong><?php echo $row['round_total']; ?> <?php echo getResultText($row['is_high'], $row['is_odd']); ?></strong>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- 베팅 정보 -->
                                    <div class="bet-info-row">
                                        <div class="bet-details">
                                            <span class="bet-type">내 베팅: <strong><?php echo getBetText($row['bet_high_low'], $row['bet_odd_even']); ?></strong></span>
                                            <span class="bet-amount"><?php echo number_format($row['bet_amount']); ?>P</span>
                                        </div>
                                        <?php if ($row['is_win'] === '1'): ?>
                                            <div class="win-amount">
                                                당첨: <strong class="text-success"><?php echo number_format($row['win_amount']); ?>P</strong>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <!-- 히스토리 없음 -->
                        <div class="empty-state">
                            <i class="bi bi-inbox empty-icon"></i>
                            <h5 class="empty-title">베팅 기록이 없습니다</h5>
                            <p class="empty-text">첫 게임을 시작해보세요!</p>
                            <a href="./index.php" class="btn btn-primary">
                                <i class="bi bi-play-circle me-1"></i>첫 베팅하기
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 페이징 -->
            <?php if ($total_pages > 1): ?>
                <div class="card">
                    <div class="card-body">
                        <nav aria-label="히스토리 페이지 네비게이션">
                            <ul class="pagination justify-content-center mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>">
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
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>">
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
</body>
</html>