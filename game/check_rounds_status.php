<?php
/*
* 파일명: check_rounds_status.php
* 위치: /game/check_rounds_status.php
* 기능: 현재 회차 상태 확인 및 정리 도구
* 작성일: 2025-06-12
*/

include_once(__DIR__ . '/../common.php');

// 관리자 권한 확인
if (!$is_admin) {
    alert('관리자만 접근 가능합니다.');
    goto_url('./index.php');
}

// POST 처리
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'clear_all_future') {
        $result = sql_query("DELETE FROM dice_game_rounds WHERE status = 'scheduled'");
        if ($result) {
            $affected = sql_affected_rows();
            $message = "✅ {$affected}개의 예정된 회차가 삭제되었습니다.";
        } else {
            $message = "❌ 삭제 실패: " . sql_error();
        }
    } elseif ($action === 'clear_all_rounds') {
        // 베팅 데이터부터 삭제 (외래키 제약)
        sql_query("DELETE FROM dice_game_bets WHERE round_id IN (SELECT round_id FROM dice_game_rounds)");
        $result = sql_query("DELETE FROM dice_game_rounds");
        if ($result) {
            $affected = sql_affected_rows();
            $message = "⚠️ 모든 회차와 베팅 데이터가 삭제되었습니다. ({$affected}개 회차)";
        } else {
            $message = "❌ 삭제 실패: " . sql_error();
        }
    }
}

// 현재 상태 조회
$stats = array();

// 상태별 회차 개수
$status_sql = "SELECT status, COUNT(*) as count FROM dice_game_rounds GROUP BY status";
$result = sql_query($status_sql);
while ($row = sql_fetch_array($result)) {
    $stats[$row['status']] = $row['count'];
}

// 전체 회차 수
$total_rounds = sql_fetch("SELECT COUNT(*) as count FROM dice_game_rounds");
$stats['total'] = $total_rounds ? $total_rounds['count'] : 0;

// 최신 회차들
$latest_rounds = array();
$latest_sql = "SELECT * FROM dice_game_rounds ORDER BY round_number DESC LIMIT 10";
$result = sql_query($latest_sql);
while ($row = sql_fetch_array($result)) {
    $latest_rounds[] = $row;
}

// 미래 회차들 (시간순)
$future_rounds = array();
$now = date('Y-m-d H:i:s');
$future_sql = "SELECT * FROM dice_game_rounds WHERE start_time > '{$now}' ORDER BY start_time ASC LIMIT 20";
$result = sql_query($future_sql);
while ($row = sql_fetch_array($result)) {
    $future_rounds[] = $row;
}

// 베팅 통계
$bet_stats = sql_fetch("
    SELECT 
        COUNT(*) as total_bets,
        COUNT(DISTINCT mb_id) as unique_players,
        SUM(bet_amount) as total_bet_amount,
        SUM(CASE WHEN is_win = 1 THEN win_amount ELSE 0 END) as total_win_amount
    FROM dice_game_bets
");
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>회차 상태 확인</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        body { background-color: #f8f9fa; }
        .card { border: none; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1); margin-bottom: 1rem; }
        .status-badge { font-size: 0.75rem; }
        .text-scheduled { color: #6c757d; }
        .text-betting { color: #007bff; }
        .text-waiting { color: #ffc107; }
        .text-completed { color: #28a745; }
    </style>
</head>

<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2><i class="bi bi-search me-2"></i>회차 상태 확인</h2>
                <p class="text-muted">현재 데이터베이스의 회차 상태를 확인하고 정리합니다.</p>
                
                <!-- 알림 메시지 -->
                <?php if (!empty($message)): ?>
                <div class="alert alert-info">
                    <?php echo $message ?>
                </div>
                <?php endif; ?>
                
                <!-- 네비게이션 -->
                <div class="card">
                    <div class="card-body">
                        <a href="./round_pre_admin.php" class="btn btn-primary me-2">
                            <i class="bi bi-calendar-week me-1"></i>회차 관리
                        </a>
                        <a href="./admin.php" class="btn btn-outline-secondary me-2">
                            <i class="bi bi-speedometer2 me-1"></i>통합 관리
                        </a>
                        <a href="./index.php" class="btn btn-outline-success">
                            <i class="bi bi-dice-6 me-1"></i>게임 홈
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- 통계 -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-bar-chart me-2"></i>회차 통계
                    </div>
                    <div class="card-body">
                        <table class="table">
                            <tr>
                                <th>전체 회차:</th>
                                <td><strong><?php echo number_format($stats['total']) ?>개</strong></td>
                            </tr>
                            <tr>
                                <th>예정 회차:</th>
                                <td class="text-scheduled"><?php echo number_format($stats['scheduled'] ?? 0) ?>개</td>
                            </tr>
                            <tr>
                                <th>베팅중 회차:</th>
                                <td class="text-betting"><?php echo number_format($stats['betting'] ?? 0) ?>개</td>
                            </tr>
                            <tr>
                                <th>대기중 회차:</th>
                                <td class="text-waiting"><?php echo number_format($stats['waiting'] ?? 0) ?>개</td>
                            </tr>
                            <tr>
                                <th>완료된 회차:</th>
                                <td class="text-completed"><?php echo number_format($stats['completed'] ?? 0) ?>개</td>
                            </tr>
                        </table>
                        
                        <hr>
                        
                        <h6>베팅 통계</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>총 베팅 수:</th>
                                <td><?php echo number_format($bet_stats['total_bets'] ?? 0) ?>건</td>
                            </tr>
                            <tr>
                                <th>참여 회원:</th>
                                <td><?php echo number_format($bet_stats['unique_players'] ?? 0) ?>명</td>
                            </tr>
                            <tr>
                                <th>총 베팅액:</th>
                                <td><?php echo number_format($bet_stats['total_bet_amount'] ?? 0) ?>P</td>
                            </tr>
                            <tr>
                                <th>총 당첨금:</th>
                                <td><?php echo number_format($bet_stats['total_win_amount'] ?? 0) ?>P</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- 정리 도구 -->
                <div class="card">
                    <div class="card-header bg-warning">
                        <i class="bi bi-tools me-2"></i>정리 도구 (주의!)
                    </div>
                    <div class="card-body">
                        <p class="text-warning">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            아래 작업은 되돌릴 수 없습니다!
                        </p>
                        
                        <form method="post" class="mb-2">
                            <input type="hidden" name="action" value="clear_all_future">
                            <button type="submit" class="btn btn-warning w-100" 
                                    onclick="return confirm('모든 예정된 회차를 삭제하시겠습니까?\\n\\n현재 ' + <?php echo $stats['scheduled'] ?? 0 ?> + '개의 예정된 회차가 있습니다.')">
                                <i class="bi bi-trash me-1"></i>예정된 회차만 모두 삭제
                            </button>
                        </form>
                        
                        <form method="post">
                            <input type="hidden" name="action" value="clear_all_rounds">
                            <button type="submit" class="btn btn-danger w-100" 
                                    onclick="return confirm('⚠️ 경고! 모든 회차와 베팅 데이터를 삭제합니다!\\n\\n이 작업은 되돌릴 수 없습니다.\\n\\n정말로 삭제하시겠습니까?')">
                                <i class="bi bi-exclamation-triangle me-1"></i>모든 데이터 삭제 (초기화)
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- 회차 목록 -->
            <div class="col-md-6">
                <!-- 최신 회차들 -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-list me-2"></i>최신 회차 (최근 10개)
                    </div>
                    <div class="card-body">
                        <?php if (!empty($latest_rounds)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>회차</th>
                                        <th>상태</th>
                                        <th>시작시간</th>
                                        <th>결과</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($latest_rounds as $round): ?>
                                    <tr>
                                        <td><strong><?php echo $round['round_number'] ?></strong></td>
                                        <td>
                                            <span class="badge bg-<?php echo $round['status'] === 'scheduled' ? 'secondary' : ($round['status'] === 'betting' ? 'primary' : ($round['status'] === 'waiting' ? 'warning' : 'success')) ?> status-badge">
                                                <?php echo $round['status'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo date('m/d H:i', strtotime($round['start_time'])) ?></small>
                                        </td>
                                        <td>
                                            <?php if ($round['dice1']): ?>
                                                <small><?php echo $round['dice1'] ?>-<?php echo $round['dice2'] ?>-<?php echo $round['dice3'] ?> (<?php echo $round['total'] ?>)</small>
                                            <?php else: ?>
                                                <small class="text-muted">미정</small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-center text-muted">회차가 없습니다.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 미래 회차들 -->
                <?php if (!empty($future_rounds)): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-clock me-2"></i>앞으로 예정된 회차 (최대 20개)
                    </div>
                    <div class="card-body">
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>회차</th>
                                        <th>시작시간</th>
                                        <th>주사위</th>
                                        <th>결과</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($future_rounds as $round): ?>
                                    <tr>
                                        <td><?php echo $round['round_number'] ?></td>
                                        <td>
                                            <small><?php echo date('m/d H:i', strtotime($round['start_time'])) ?></small>
                                        </td>
                                        <td>
                                            <small><?php echo $round['dice1'] ?>-<?php echo $round['dice2'] ?>-<?php echo $round['dice3'] ?></small>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo $round['total'] ?>
                                                <span class="badge bg-<?php echo $round['is_high'] ? 'primary' : 'info' ?> badge-sm">
                                                    <?php echo $round['is_high'] ? '대' : '소' ?>
                                                </span>
                                                <span class="badge bg-<?php echo $round['is_odd'] ? 'success' : 'warning' ?> badge-sm">
                                                    <?php echo $round['is_odd'] ? '홀' : '짝' ?>
                                                </span>
                                            </small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 상태 해석 -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-question-circle me-2"></i>상태 설명
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>회차 상태</h6>
                                <ul class="list-unstyled">
                                    <li><span class="badge bg-secondary me-2">scheduled</span>예정됨 (시작 대기중)</li>
                                    <li><span class="badge bg-primary me-2">betting</span>베팅중 (베팅 가능)</li>
                                    <li><span class="badge bg-warning me-2">waiting</span>대기중 (베팅 마감, 결과 대기)</li>
                                    <li><span class="badge bg-success me-2">completed</span>완료됨 (결과 발표 및 정산 완료)</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>정상 상태</h6>
                                <ul class="list-unstyled">
                                    <li>✅ 예정된 회차가 10개 이상</li>
                                    <li>✅ 베팅중 회차가 0-1개</li>
                                    <li>✅ 대기중 회차가 0-1개</li>
                                    <li>⚠️ 예정된 회차가 5개 미만이면 추가 생성 필요</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>