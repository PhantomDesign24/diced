<?php
/*
* 파일명: mypage.php
* 위치: /mypage.php
* 기능: 마이페이지 (회원정보 및 포인트 관리 + 관리자 메뉴)
* 작성일: 2025-06-12
* 수정일: 2025-06-12 (관리자 메뉴 추가)
*/

// ===================================
// 그누보드 환경 설정
// ===================================
include_once('../common.php');

// 로그인 체크
if (!$is_member) {
    alert('로그인이 필요합니다.', G5_BBS_URL.'/login.php?url='.urlencode(G5_URL.'/mypage.php'));
}

// ===================================
// 회원 정보 및 포인트 조회
// ===================================
$member_point = get_point_sum($member['mb_id']);

// 최근 베팅 내역 (간단히 3개만)
$recent_bets_sql = "
    SELECT b.*, r.dice1, r.dice2, r.dice3, r.total, r.is_high, r.is_odd 
    FROM dice_game_bets b
    LEFT JOIN dice_game_rounds r ON b.round_id = r.round_id
    WHERE b.mb_id = '{$member['mb_id']}' 
    ORDER BY b.created_at DESC 
    LIMIT 3
";
$recent_bets_result = sql_query($recent_bets_sql);

// 최근 포인트 내역 (간단히 5개만)
$recent_points_sql = "
    SELECT * FROM {$g5['point_table']} 
    WHERE mb_id = '{$member['mb_id']}' 
    ORDER BY po_datetime DESC 
    LIMIT 5
";
$recent_points_result = sql_query($recent_points_sql);

// 페이지 제목 설정
$g5['title'] = '마이페이지';
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="title" content="<?php echo $g5['title']; ?>">
    <title><?php echo $g5['title']; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo G5_URL?>/game/css/game.css">

    <style>
        /* 기본 스타일 */
        * {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        body {
            background: #f8f9fa;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            padding-bottom: 80px;
        }
        
        .main-container {
            max-width: 420px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
            position: relative;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        
        /* 헤더 영역 */
        .header-section {
            background: #ff4757;
            color: white;
            padding: 20px;
            text-align: center;
            position: relative;
        }
        
        .header-title {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
        }
        
        .header-subtitle {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 4px;
        }
        
        /* 관리자 헤더 (관리자인 경우) */
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        }
        
        /* 프로필 섹션 */
        .profile-section {
            background: white;
            padding: 24px 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .profile-card {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .profile-avatar {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #ff4757, #ff3742);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: 700;
            flex-shrink: 0;
            box-shadow: 0 4px 15px rgba(255, 71, 87, 0.3);
        }
        
        /* 관리자 아바타 */
        .admin-avatar {
            background: linear-gradient(135deg, #667eea, #764ba2) !important;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3) !important;
        }
        
        .profile-info {
            flex: 1;
        }
        
        .profile-name {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 4px;
        }
        
        .profile-id {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 8px;
        }
        
        .profile-level {
            display: inline-block;
            background: #fff3cd;
            color: #856404;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        /* 관리자 레벨 배지 */
        .admin-level {
            background: linear-gradient(135deg, #667eea, #764ba2) !important;
            color: white !important;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3) !important;
        }
        
        /* 포인트 카드 */
        .point-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        }
        
        .point-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 8px;
        }
        
        .point-amount {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 16px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .point-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .point-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .point-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            transform: translateY(-1px);
        }
        
        /* 관리자 전용 섹션 */
        .admin-section {
            padding: 20px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
            border-top: 3px solid #667eea;
            border-bottom: 1px solid #e9ecef;
        }
        
        .admin-title {
            font-size: 18px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .admin-menu-list {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .admin-menu-item {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 16px 12px;
            text-align: center;
            text-decoration: none;
            color: #495057;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .admin-menu-item:hover {
            border-color: #667eea;
            color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }
        
        .admin-menu-icon {
            font-size: 24px;
            margin-bottom: 8px;
            display: block;
        }
        
        .admin-menu-text {
            font-size: 13px;
            font-weight: 600;
        }
        
        /* 메뉴 섹션 */
        .menu-section {
            padding: 20px;
        }
        
        .menu-title {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 16px;
        }
        
        .menu-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 0;
            border-bottom: 1px solid #f1f3f4;
            color: #333;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .menu-item:hover {
            color: #ff4757;
            background: #fff5f5;
            margin: 0 -20px;
            padding: 16px 20px;
            border-radius: 8px;
            border-bottom: 1px solid transparent;
        }
        
        .menu-item:last-child {
            border-bottom: none;
        }
        
        .menu-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .menu-icon {
            width: 40px;
            height: 40px;
            background: #f8f9fa;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #6c757d;
            transition: all 0.2s ease;
        }
        
        .menu-item:hover .menu-icon {
            background: #ff4757;
            color: white;
        }
        
        .menu-text {
            font-size: 16px;
            font-weight: 500;
        }
        
        .menu-arrow {
            color: #6c757d;
            font-size: 16px;
            transition: all 0.2s ease;
        }
        
        .menu-item:hover .menu-arrow {
            color: #ff4757;
            transform: translateX(3px);
        }
        
        /* 최근 활동 섹션 */
        .recent-section {
            padding: 20px;
            border-top: 8px solid #f8f9fa;
        }
        
        .recent-title {
            font-size: 16px;
            font-weight: 700;
            color: #333;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .recent-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f1f3f4;
            font-size: 14px;
        }
        
        .recent-item:last-child {
            border-bottom: none;
        }
        
        .recent-left {
            flex: 1;
        }
        
        .recent-desc {
            color: #495057;
            margin-bottom: 4px;
        }
        
        .recent-time {
            color: #6c757d;
            font-size: 12px;
        }
        
        .recent-amount {
            font-weight: 600;
        }
        
        .recent-amount.positive {
            color: #28a745;
        }
        
        .recent-amount.negative {
            color: #dc3545;
        }
        
        .more-link {
            text-align: center;
            margin-top: 16px;
        }
        
        .more-btn {
            color: #ff4757;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }
        
        .more-btn:hover {
            color: #ff3742;
            text-decoration: underline;
        }
        
        /* 로그아웃 버튼 */
        .logout-section {
            padding: 20px;
            border-top: 8px solid #f8f9fa;
        }
        
        .logout-btn {
            width: 100%;
            background: #6c757d;
            border: none;
            color: white;
            padding: 14px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }
        
        /* 반응형 */
        @media (max-width: 375px) {
            .profile-section,
            .menu-section,
            .recent-section,
            .logout-section,
            .admin-section {
                padding: 16px;
            }
            
            .point-card {
                padding: 16px;
            }
            
            .point-amount {
                font-size: 24px;
            }
            
            .admin-menu-list {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- 헤더 영역 -->
        <div class="header-section <?php echo $is_admin ? 'admin-header' : '' ?>">
            <h1 class="header-title">
                <?php if ($is_admin): ?>
                    <i class="bi bi-shield-check me-2"></i>관리자 페이지
                <?php else: ?>
                    👤 마이페이지
                <?php endif; ?>
            </h1>
            <p class="header-subtitle">
                <?php echo $is_admin ? '시스템 관리 및 내 정보 확인' : '내 정보와 활동 내역을 확인하세요' ?>
            </p>
        </div>
        
        <!-- 프로필 섹션 -->
        <div class="profile-section">
            <div class="profile-card">
                <div class="profile-avatar <?php echo $is_admin ? 'admin-avatar' : '' ?>">
                    <?php if ($is_admin): ?>
                        <i class="bi bi-shield-check"></i>
                    <?php else: ?>
                        <?php echo strtoupper(substr($member['mb_id'], 0, 1)); ?>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <h2 class="profile-name"><?php echo $member['mb_name']; ?></h2>
                    <p class="profile-id">ID: <?php echo $member['mb_id']; ?></p>
                    <span class="profile-level <?php echo $is_admin ? 'admin-level' : '' ?>">
                        <?php echo $is_admin ? '시스템 관리자' : '일반회원' ?>
                    </span>
                </div>
            </div>
            
            <!-- 포인트 카드 -->
            <div class="point-card">
                <div class="point-label">보유 포인트</div>
                <div class="point-amount"><?php echo number_format($member_point); ?>P</div>
                <div class="point-actions">
                    <a href="<?php echo G5_URL; ?>/game/payment.php" class="point-btn">
                        <i class="bi bi-plus-circle me-1"></i>충전
                    </a>
                    <a href="<?php echo G5_URL; ?>/game/payment.php" class="point-btn">
                        <i class="bi bi-dash-circle me-1"></i>출금
                    </a>
                </div>
            </div>
        </div>
        
        <!-- 관리자 전용 섹션 -->
        <?php if ($is_admin): ?>
        <div class="admin-section">
            <h3 class="admin-title">
                <i class="bi bi-speedometer2"></i>
                관리자 도구
            </h3>
            <div class="admin-menu-list">
                <a href="<?php echo G5_URL; ?>/game/admin.php" class="admin-menu-item">
                    <i class="bi bi-gear-wide-connected admin-menu-icon"></i>
                    <div class="admin-menu-text">통합 관리</div>
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- 메뉴 섹션 -->
        <div class="menu-section">
            <h3 class="menu-title">메뉴</h3>
            <div class="menu-list">
                <a href="<?php echo G5_URL; ?>/game/payment.php" class="menu-item">
                    <div class="menu-left">
                        <div class="menu-icon">
                            <i class="bi bi-credit-card"></i>
                        </div>
                        <span class="menu-text">충전하기</span>
                    </div>
                    <i class="bi bi-chevron-right menu-arrow"></i>
                </a>
                
                <a href="<?php echo G5_URL; ?>/game/payment.php" class="menu-item">
                    <div class="menu-left">
                        <div class="menu-icon">
                            <i class="bi bi-bank"></i>
                        </div>
                        <span class="menu-text">출금하기</span>
                    </div>
                    <i class="bi bi-chevron-right menu-arrow"></i>
                </a>
                
                <a href="<?php echo G5_URL; ?>/game/payment_history.php" class="menu-item">
                    <div class="menu-left">
                        <div class="menu-icon">
                            <i class="bi bi-list-ul"></i>
                        </div>
                        <span class="menu-text">입출금 내역</span>
                    </div>
                    <i class="bi bi-chevron-right menu-arrow"></i>
                </a>
                
                <a href="<?php echo G5_URL; ?>/game/history.php" class="menu-item">
                    <div class="menu-left">
                        <div class="menu-icon">
                            <i class="bi bi-dice-6"></i>
                        </div>
                        <span class="menu-text">베팅 내역</span>
                    </div>
                    <i class="bi bi-chevron-right menu-arrow"></i>
                </a>
            </div>
        </div>
        
        <!-- 최근 활동 -->
        <div class="recent-section">
            <h3 class="recent-title">
                <i class="bi bi-clock-history text-primary"></i>
                최근 베팅 내역
            </h3>
            <?php if (sql_num_rows($recent_bets_result) > 0): ?>
                <?php while($bet = sql_fetch_array($recent_bets_result)): ?>
                <div class="recent-item">
                    <div class="recent-left">
                        <div class="recent-desc">
                            <?php echo $bet['round_number']; ?>회차 
                            <?php echo ($bet['bet_high_low'] == 'high' ? '대' : '소'); ?> 
                            <?php echo ($bet['bet_odd_even'] == 'odd' ? '홀' : '짝'); ?>
                            <?php if ($bet['is_win'] === '1'): ?>
                                <span class="badge bg-success ms-2">당첨</span>
                            <?php elseif ($bet['is_win'] === '0'): ?>
                                <span class="badge bg-danger ms-2">실패</span>
                            <?php else: ?>
                                <span class="badge bg-secondary ms-2">대기</span>
                            <?php endif; ?>
                        </div>
                        <div class="recent-time"><?php echo date('m/d H:i', strtotime($bet['created_at'])); ?></div>
                    </div>
                    <div class="recent-amount negative">-<?php echo number_format($bet['bet_amount']); ?>P</div>
                </div>
                <?php endwhile; ?>
                <div class="more-link">
                    <a href="<?php echo G5_URL; ?>/game/history.php" class="more-btn">전체 베팅 내역 보기</a>
                </div>
            <?php else: ?>
                <div class="text-center text-muted py-3">
                    <i class="bi bi-inbox" style="font-size: 2rem; opacity: 0.5;"></i>
                    <p class="mt-2 mb-0">베팅 내역이 없습니다</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- 최근 포인트 내역 -->
        <div class="recent-section">
            <h3 class="recent-title">
                <i class="bi bi-coin text-warning"></i>
                최근 포인트 내역
            </h3>
            <?php if (sql_num_rows($recent_points_result) > 0): ?>
                <?php while($point = sql_fetch_array($recent_points_result)): ?>
                <div class="recent-item">
                    <div class="recent-left">
                        <div class="recent-desc"><?php echo $point['po_content']; ?></div>
                        <div class="recent-time"><?php echo date('m/d H:i', strtotime($point['po_datetime'])); ?></div>
                    </div>
                    <div class="recent-amount <?php echo $point['po_point'] > 0 ? 'positive' : 'negative'; ?>">
                        <?php echo $point['po_point'] > 0 ? '+' : ''; ?><?php echo number_format($point['po_point']); ?>P
                    </div>
                </div>
                <?php endwhile; ?>
                <div class="more-link">
                    <a href="<?php echo G5_BBS_URL; ?>/point.php" class="more-btn">전체 포인트 내역 보기</a>
                </div>
            <?php else: ?>
                <div class="text-center text-muted py-3">
                    <i class="bi bi-wallet" style="font-size: 2rem; opacity: 0.5;"></i>
                    <p class="mt-2 mb-0">포인트 내역이 없습니다</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- 로그아웃 -->
        <div class="logout-section">
            <button type="button" class="logout-btn" onclick="confirmLogout()">
                <i class="bi bi-box-arrow-right me-2"></i>로그아웃
            </button>
        </div>
    </div>
    
    <!-- 하단 퀵메뉴 include -->
    <?php include_once('./menu.php'); ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 로그아웃 확인
        function confirmLogout() {
            if (confirm('정말 로그아웃 하시겠습니까?')) {
                location.href = '<?php echo G5_BBS_URL; ?>/logout.php';
            }
        }
        
        // 포인트 카드 클릭 효과
        document.querySelector('.point-card').addEventListener('click', function(e) {
            if (!e.target.closest('.point-btn')) {
                this.style.transform = 'scale(0.98)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 150);
            }
        });
        
        // 관리자 메뉴 클릭 효과
        <?php if ($is_admin): ?>
        document.querySelectorAll('.admin-menu-item').forEach(item => {
            item.addEventListener('click', function(e) {
                this.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 150);
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>

<?php
include_once(G5_PATH.'/tail.sub.php');
?>