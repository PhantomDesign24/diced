<?php
/*
* 파일명: debug_game_status.php
* 위치: /game/debug_game_status.php
* 기능: 실시간 게임 상태 디버깅
* 작성일: 2025-06-12
*/

include_once(__DIR__ . '/../common.php');

// 관리자만 접근 가능
if (!$is_admin) {
    alert('관리자만 접근 가능합니다.');
    goto_url('./index.php');
}

// 수동 상태 전환 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $action = $_POST['action'];
    $round_id = (int)($_POST['round_id'] ?? 0);
    
    if ($action === 'force_next_phase' && $round_id > 0) {
        $round = sql_fetch("SELECT * FROM dice_game_rounds WHERE round_id = {$round_id}");
        if ($round) {
            $new_status = '';
            if ($round['status'] === 'scheduled') {
                $new_status = 'betting';
            } elseif ($round['status'] === 'betting') {
                $new_status = 'waiting';
            } elseif ($round['status'] === 'waiting') {
                $new_status = 'completed';
            }
            
            if ($new_status) {
                sql_query("UPDATE dice_game_rounds SET status = '{$new_status}' WHERE round_id = {$round_id}");
                $message = "회차 {$round['round_number']}가 {$round['status']} → {$new_status}로 변경되었습니다.";
            }
        }
    }
}

// 현재 상태 조회
$now = date('Y-m-d H:i:s');
$current_rounds = array();

$sql = "
    SELECT *, 
           CASE 
               WHEN start_time > NOW() THEN '아직 시작 안됨'
               WHEN start_time <= NOW() AND end_time > NOW() THEN '베팅 가능 시간'
               WHEN end_time <= NOW() AND result_time > NOW() THEN '베팅 마감, 결과 대기'
               WHEN result_time <= NOW() THEN '결과 발표 시간'
           END as time_status,
           TIMESTAMPDIFF(SECOND, NOW(), start_time) as seconds_to_start,
           TIMESTAMPDIFF(SECOND, NOW(), end_time) as seconds_to_end,
           TIMESTAMPDIFF(SECOND, NOW(), result_time) as seconds_to_result
    FROM dice_game_rounds 
    WHERE round_number >= (
        SELECT COALESCE(MAX(round_number), 0) - 2 
        FROM dice_game_rounds 
        WHERE status = 'completed'
    )
    ORDER BY round_number ASC
    LIMIT 10
";

$result = sql_query($sql);
while ($row = sql_fetch_array($result)) {
    $current_rounds[] = $row;
}

// 게임 설정
$config = array();
$config_sql = "SELECT * FROM dice_game_config";
$result = sql_query($config_sql);
while ($row = sql_fetch_array($result)) {
    $config[$row['config_key']] = $row['config_value'];
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>게임 상태 디버그</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        .status-scheduled { background-color: #e9ecef; }
        .status-betting { background-color: #d1ecf1; }
        .status-waiting { background-color: #fff3cd; }
        .status-completed { background-color: #d4edda; }
        .time-future { color: #6c757d; }
        .time-current { color: #198754; font-weight: bold; }
        .time-past { color: #dc3545; }
    </style>
    
    <script>
        // 5초마다 자동 새로고침
        setTimeout(() => location.reload(), 5000);
    </script>
</head>

<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <h2><i class="bi bi-bug me-2"></i>게임 상태 실시간 디버그</h2>
                <p class="text-muted">현재 시간: <strong><?php echo $now ?></strong> (5초마다 자동 새로고침)</p>
                
                <?php if (!empty($message)): ?>
                <div class="alert alert-success"><?php echo $message ?></div>
                <?php endif; ?>
                
                <!-- 네비게이션 -->
                <div class="card mb-3">
                    <div class="card-body">
                        <a href="./round_pre_admin.php" class="btn btn-primary me-2">
                            <i class="bi bi-calendar-week me-1"></i>회차 관리
                        </a>
                        <a href="./index.php" class="btn btn-outline-success me-2">
                            <i class="bi bi-dice-6 me-1"></i>게임 홈
                        </a>
                        <a href="./simple_cron.php?manual=1" class="btn btn-outline-secondary" target="_blank">
                            <i class="bi bi-play me-1"></i>크론잡 실행
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- 게임 설정 -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-gear me-2"></i>게임 설정
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <th>게임 상태:</th>
                                <td>
                                    <span class="badge bg-<?php echo $config['game_status'] == '1' ? 'success' : 'danger' ?>">
                                        <?php echo $config['game_status'] == '1' ? '활성' : '비활성' ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>베팅 시간:</th>
                                <td><?php echo $config['betting_time'] ?? '90' ?>초</td>
                            </tr>
                            <tr>
                                <th>결과 시간:</th>
                                <td><?php echo $config['result_time'] ?? '30' ?>초</td>
                            </tr>
                            <tr>
                                <th>게임 간격:</th>
                                <td><?php echo ((int)($config['betting_time'] ?? 90) + (int)($config['result_time'] ?? 30)) ?>초</td>
                            </tr>
                            <tr>
                                <th>최소 베팅:</th>
                                <td><?php echo number_format($config['min_bet'] ?? 1000) ?>P</td>
                            </tr>
                            <tr>
                                <th>최대 베팅:</th>
                                <td><?php echo number_format($config['max_bet'] ?? 100000) ?>P</td>
                            </tr>
                            <tr>
                                <th>대소 배율:</th>
                                <td><?php echo $config['win_rate_high_low'] ?? '1.95' ?>배</td>
                            </tr>
                            <tr>
                                <th>홀짝 배율:</th>
                                <td><?php echo $config['win_rate_odd_even'] ?? '1.95' ?>배</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- 회차 상태 -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-list me-2"></i>회차 상태 (최근 10개)
                    </div>
                    <div class="card-body">
                        <?php if (!empty($current_rounds)): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>회차</th>
                                        <th>상태</th>
                                        <th>시간 상태</th>
                                        <th>시작시간</th>
                                        <th>마감시간</th>
                                        <th>결과시간</th>
                                        <th>주사위</th>
                                        <th>액션</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($current_rounds as $round): ?>
                                    <tr class="status-<?php echo $round['status'] ?>">
                                        <td><strong><?php echo $round['round_number'] ?></strong></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $round['status'] === 'scheduled' ? 'secondary' : 
                                                    ($round['status'] === 'betting' ? 'primary' : 
                                                    ($round['status'] === 'waiting' ? 'warning' : 'success')) 
                                            ?>">
                                                <?php echo $round['status'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="<?php 
                                                if ($round['seconds_to_start'] > 0) echo 'time-future';
                                                elseif ($round['seconds_to_end'] > 0) echo 'time-current';
                                                else echo 'time-past';
                                            ?>">
                                                <?php echo $round['time_status'] ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small><?php echo date('H:i:s', strtotime($round['start_time'])) ?></small>
                                            <?php if ($round['seconds_to_start'] > 0): ?>
                                                <br><small class="text-muted">(<?php echo $round['seconds_to_start'] ?>초 후)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo date('H:i:s', strtotime($round['end_time'])) ?></small>
                                            <?php if ($round['seconds_to_end'] > 0 && $round['seconds_to_start'] <= 0): ?>
                                                <br><small class="text-warning">(<?php echo $round['seconds_to_end'] ?>초 후 마감)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small><?php echo date('H:i:s', strtotime($round['result_time'])) ?></small>
                                            <?php if ($round['seconds_to_result'] > 0 && $round['seconds_to_end'] <= 0): ?>
                                                <br><small class="text-danger">(<?php echo $round['seconds_to_result'] ?>초 후 결과)</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($round['dice1']): ?>
                                                <small><?php echo $round['dice1'] ?>-<?php echo $round['dice2'] ?>-<?php echo $round['dice3'] ?></small>
                                                <br><small class="text-muted">(<?php echo $round['total'] ?>)</small>
                                            <?php else: ?>
                                                <small class="text-muted">미정</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($round['status'] !== 'completed'): ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="action" value="force_next_phase">
                                                <input type="hidden" name="round_id" value="<?php echo $round['round_id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary" 
                                                        onclick="return confirm('강제로 다음 단계로 진행하시겠습니까?')">
                                                    <i class="bi bi-skip-forward"></i>
                                                </button>
                                            </form>
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
            </div>
        </div>

        <!-- 상태 설명 -->
        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-info-circle me-2"></i>상태 설명
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>회차 상태</h6>
                                <ul class="list-unstyled">
                                    <li><span class="badge bg-secondary me-2">scheduled</span>예정됨</li>
                                    <li><span class="badge bg-primary me-2">betting</span>베팅중</li>
                                    <li><span class="badge bg-warning me-2">waiting</span>베팅마감, 결과대기</li>
                                    <li><span class="badge bg-success me-2">completed</span>완료됨</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>시간 색상</h6>
                                <ul class="list-unstyled">
                                    <li><span class="time-future">회색</span>: 미래 시간</li>
                                    <li><span class="time-current">녹색</span>: 현재 진행중</li>
                                    <li><span class="time-past">빨강</span>: 지난 시간</li>
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