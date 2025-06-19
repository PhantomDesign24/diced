<?php
/*
* 파일명: round_pre_admin.php
* 위치: /game/round_pre_admin.php
* 기능: 회차별 미리 생성 및 결과 설정 관리
* 작성일: 2025-06-12
*/
include_once(__DIR__ . '/../common.php');

// 관리자 권한 확인
if (!$is_admin) {
    alert('관리자만 접근 가능합니다.');
    goto_url('./index.php');
}

// ===================================
// 설정값 조회
// ===================================
function getGameConfig($key, $default = '') {
    $sql = "SELECT config_value FROM dice_game_config WHERE config_key = '{$key}'";
    $result = sql_fetch($sql);
    return $result ? $result['config_value'] : $default;
}

$betting_time = (int)getGameConfig('betting_time', '90');
$result_time = (int)getGameConfig('result_time', '30');
$game_interval = $betting_time + $result_time;

// 실시간 설정 표시용 정보
$settings_info = [
    'betting_time' => $betting_time,
    'result_time' => $result_time,
    'game_interval' => $game_interval,
    'min_bet' => getGameConfig('min_bet', '1000'),
    'max_bet' => getGameConfig('max_bet', '100000'),
    'win_rate_high_low' => getGameConfig('win_rate_high_low', '1.95'),
    'win_rate_odd_even' => getGameConfig('win_rate_odd_even', '1.95')
];
$is_popup = isset($_GET['popup']) || (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'admin.php') !== false);

// ===================================
// POST 처리 (명시적 액션만 처리)
// ===================================
$message = '';
$message_type = '';

// POST 요청이면서 명시적 action이 있을 때만 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    $action = trim($_POST['action']);
    
    // CSRF 방지를 위한 간단한 체크 (선택사항)
    $expected_actions = ['generate_rounds', 'update_round_result', 'delete_future_rounds'];
    if (!in_array($action, $expected_actions)) {
        $message = "잘못된 요청입니다.";
        $message_type = 'error';
    } else {
        try {
if ($action === 'generate_rounds') {
    $count = (int)($_POST['count'] ?? 50);
    $pattern = $_POST['pattern'] ?? 'random';
    
    // 시작 회차 번호 계산 - 모든 회차 중 최대값 찾기
    $max_round = sql_fetch("SELECT MAX(round_number) as max_round FROM dice_game_rounds");
    $start_round = ($max_round && $max_round['max_round']) ? $max_round['max_round'] + 1 : 1;
    
    // 시작 시간 계산 부분을 다음과 같이 수정
    $last_round_time = sql_fetch("
        SELECT result_time 
        FROM dice_game_rounds 
        WHERE status IN ('completed', 'betting', 'waiting', 'scheduled')
        ORDER BY result_time DESC 
        LIMIT 1
    ");
    
    // 시작 시간 계산 부분 수정
    if ($last_round_time) {
        // 마지막 회차 결과 시간 + 게임 간격 후부터 시작
        $base_time = strtotime($last_round_time['result_time']) + $game_interval;
        
        // 중요: 현재 시간보다 과거면 현재 시간 + 5분으로 설정
        $now = time();
        if ($base_time <= $now) {
            $base_time = $now + 300; // 5분 후부터 시작
        }
    } else {
        // 회차가 하나도 없으면 5분 후부터 시작
        $base_time = time() + 300;
    }
            
            for ($i = 0; $i < $count; $i++) {
                $round_number = $start_round + $i;
                
                // 시간 계산
                $round_start = $base_time + ($i * $game_interval);
                $round_end = $round_start + $betting_time;
                $round_result = $round_end + $result_time;
                
                $start_time_str = date('Y-m-d H:i:s', $round_start);
                $end_time_str = date('Y-m-d H:i:s', $round_end);
                $result_time_str = date('Y-m-d H:i:s', $round_result);
                
                // 결과값 생성
                if ($pattern === 'balanced') {
                    // 균형잡힌 패턴
                    $is_high = ($i % 2 == 0) ? 1 : 0;
                    $is_odd = (($i + 1) % 2 == 0) ? 1 : 0;
                    
                    if ($is_high && $is_odd) {
                        $combinations = [[5,3,3], [5,5,1], [6,3,2], [4,5,2]];
                    } elseif ($is_high && !$is_odd) {
                        $combinations = [[6,6,2], [5,5,2], [6,4,2], [5,3,4]];
                    } elseif (!$is_high && $is_odd) {
                        $combinations = [[3,3,3], [2,2,5], [1,4,4], [3,2,4]];
                    } else {
                        $combinations = [[2,2,2], [3,3,2], [1,1,4], [2,4,2]];
                    }
                    
                    $selected = $combinations[array_rand($combinations)];
                    $dice1 = $selected[0];
                    $dice2 = $selected[1];
                    $dice3 = $selected[2];
                    
                } elseif ($pattern === 'house_favor') {
                    // 하우스 유리 (70% 하우스 승리)
                    $house_win = ($i % 10 < 7);
                    
                    if ($house_win) {
                        $combinations = [
                            [1,1,1], [1,1,2], [1,2,2], [2,2,2],
                            [6,6,6], [6,6,5], [6,5,5], [5,5,5]
                        ];
                    } else {
                        $combinations = [
                            [4,4,4], [3,4,5], [4,3,4], [3,5,4],
                            [6,3,3], [5,4,3], [4,5,3], [3,6,3]
                        ];
                    }
                    
                    $selected = $combinations[array_rand($combinations)];
                    $dice1 = $selected[0];
                    $dice2 = $selected[1];
                    $dice3 = $selected[2];
                    
                } else {
                    // 완전 랜덤
                    $dice1 = rand(1, 6);
                    $dice2 = rand(1, 6);
                    $dice3 = rand(1, 6);
                }
                
                $total = $dice1 + $dice2 + $dice3;
                $is_high = $total >= 11 ? 1 : 0;
                $is_odd = $total % 2 ? 1 : 0;
                
                // 현재 시간보다 이전이면 completed, 이후면 scheduled
                $status = ($round_start <= time()) ? 'completed' : 'scheduled';
                
                $insert_sql = "
                    INSERT INTO dice_game_rounds 
                    (round_number, start_time, end_time, result_time, dice1, dice2, dice3, total, is_high, is_odd, status, created_at)
                    VALUES 
                    ({$round_number}, '{$start_time_str}', '{$end_time_str}', '{$result_time_str}', 
                     {$dice1}, {$dice2}, {$dice3}, {$total}, {$is_high}, {$is_odd}, '{$status}', NOW())
                ";
                
                sql_query($insert_sql);
            }
            
            $message = "{$count}개의 회차가 생성되었습니다. (시작 회차: {$start_round})";
            $message_type = 'success';
            
        } elseif ($action === 'update_round_result') {
            $round_id = (int)($_POST['round_id'] ?? 0);
            $dice1 = (int)($_POST['dice1'] ?? 1);
            $dice2 = (int)($_POST['dice2'] ?? 1);
            $dice3 = (int)($_POST['dice3'] ?? 1);
            
            if ($round_id > 0) {
                $total = $dice1 + $dice2 + $dice3;
                $is_high = $total >= 11 ? 1 : 0;
                $is_odd = $total % 2 ? 1 : 0;
                
                $update_sql = "
                    UPDATE dice_game_rounds 
                    SET dice1 = {$dice1}, dice2 = {$dice2}, dice3 = {$dice3}, 
                        total = {$total}, is_high = {$is_high}, is_odd = {$is_odd}
                    WHERE round_id = {$round_id}
                ";
                
                if (sql_query($update_sql)) {
                    $message = "회차 결과가 수정되었습니다.";
                    $message_type = 'success';
                } else {
                    $message = "수정 실패: " . sql_error();
                    $message_type = 'error';
                }
            }
            
} elseif ($action === 'delete_future_rounds') {
    $confirm = $_POST['confirm'] ?? '';
    if ($confirm === 'yes') {
        $now = date('Y-m-d H:i:s');
        
        // 삭제 전 개수 확인
        $count_sql = "SELECT COUNT(*) as cnt FROM dice_game_rounds WHERE start_time > '{$now}' AND status = 'scheduled'";
        $count_result = sql_fetch($count_sql);
        $before_count = $count_result ? $count_result['cnt'] : 0;
        
        // 삭제 실행
        $delete_sql = "DELETE FROM dice_game_rounds WHERE start_time > '{$now}' AND status = 'scheduled'";
        
        if (sql_query($delete_sql)) {
            // 그누보드 환경에서 안전한 영향받은 행 수 계산
            if ($before_count > 0) {
                // 삭제 후 다시 카운트
                $after_result = sql_fetch($count_sql);
                $after_count = $after_result ? $after_result['cnt'] : 0;
                $affected = $before_count - $after_count;
            } else {
                $affected = 0;
            }
            
            $message = "{$affected}개의 미래 회차가 삭제되었습니다.";
            $message_type = 'success';
        } else {
            $message = "삭제 실패: " . sql_error();
            $message_type = 'error';
        }
    }
}
            
        } catch (Exception $e) {
            $message = "오류: " . $e->getMessage();
            $message_type = 'error';
        }
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // POST지만 action이 없는 경우 - 실수 방지
    $message = "잘못된 요청입니다. 버튼을 통해 다시 시도해주세요.";
    $message_type = 'warning';
}

// ===================================
// 회차 조회
// ===================================

// 현재 진행 중인 회차
$current_round = sql_fetch("
    SELECT * FROM dice_game_rounds 
    WHERE status IN ('betting', 'waiting') 
    ORDER BY round_number DESC 
    LIMIT 1
");

// 미래 회차들 (50개)
$future_rounds = array();
$now = date('Y-m-d H:i:s');
$future_sql = "
    SELECT * FROM dice_game_rounds 
    WHERE start_time > '{$now}' AND status = 'scheduled'
    ORDER BY round_number ASC 
    LIMIT 50
";
$result = sql_query($future_sql);
while ($row = sql_fetch_array($result)) {
    $future_rounds[] = $row;
}

// 최근 완료된 회차들 (20개)
$completed_rounds = array();
$completed_sql = "
    SELECT * FROM dice_game_rounds 
    WHERE status = 'completed'
    ORDER BY round_number DESC 
    LIMIT 20
";
$result = sql_query($completed_sql);
while ($row = sql_fetch_array($result)) {
    $completed_rounds[] = $row;
}

// 통계
$stats = [
    'total_scheduled' => count($future_rounds),
    'total_completed' => count($completed_rounds),
    'current_round_number' => $current_round ? $current_round['round_number'] : 0
];

if (!empty($future_rounds)) {
    $high_count = array_sum(array_column($future_rounds, 'is_high'));
    $odd_count = array_sum(array_column($future_rounds, 'is_odd'));
    $stats['future_high_percentage'] = round(($high_count / count($future_rounds)) * 100, 1);
    $stats['future_odd_percentage'] = round(($odd_count / count($future_rounds)) * 100, 1);
}
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>회차별 미리 생성 관리</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
	<style>
		/* 팝업일 때 추가 스타일 */
		<?php if ($is_popup): ?>
		body {
			margin: 0;
			padding: 0;
		}
		.admin-header {
			padding: 1rem 0 !important;
			margin-bottom: 1rem !important;
		}
		.container {
			max-width: 100%;
			padding: 0 1rem;
		}
		<?php endif; ?>
	</style>
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .admin-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        
        .dice-display {
            display: inline-flex;
            gap: 3px;
        }
        
        .dice-num {
            width: 20px;
            height: 20px;
            background: #28a745;
            color: white;
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: bold;
        }
        
        .result-badges {
            display: flex;
            gap: 3px;
        }
        
        .table-sm td {
            padding: 0.3rem;
            font-size: 0.875rem;
            vertical-align: middle;
        }
        
        .time-badge {
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
        }
        
        .stats-card {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1), rgba(32, 201, 151, 0.1));
            border: 2px solid #28a745;
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
        }
        
        .stats-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: #28a745;
        }
        
        .edit-dice {
            width: 45px;
            height: 30px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin: 0 1px;
            font-size: 12px;
        }
        
        .status-current {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }
        
        .status-future {
            background: #e9ecef;
            color: #6c757d;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
    </style>
</head>

<body>
    <!-- 헤더 -->
	<div class="admin-header">
		<div class="container">
			<div class="d-flex justify-content-between align-items-center">
				<div>
					<h1 class="<?php echo $is_popup ? 'h3' : '' ?>">
						<i class="bi bi-calendar-week me-3"></i>회차별 미리 생성 관리
					</h1>
					<p class="mb-0 <?php echo $is_popup ? 'small' : '' ?>">게임 회차를 미리 생성하고 결과를 설정합니다</p>
				</div>
				<?php if ($is_popup): ?>
				<button type="button" class="btn btn-outline-light" onclick="window.close()">
					<i class="bi bi-x-lg me-1"></i>닫기
				</button>
				<?php endif; ?>
			</div>
		</div>
	</div>
    <div class="container">
        <!-- 알림 메시지 -->
        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : ($message_type === 'warning' ? 'warning' : 'danger') ?> alert-dismissible fade show">
            <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'exclamation-triangle') ?> me-2"></i>
            <?php echo htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- 현재 상황 알림 -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-info">
                    <h6><i class="bi bi-info-circle me-2"></i>현재 상황 요약</h6>
                    <ul class="mb-0">
                        <li><strong>예정된 회차:</strong> <?php echo count($future_rounds) ?>개</li>
                        <li><strong>현재 진행:</strong> <?php echo $current_round ? "{$current_round['round_number']}회차 ({$current_round['status']})" : "없음" ?></li>
                        <li><strong>완료된 회차:</strong> <?php echo count($completed_rounds) ?>개</li>
                        <li><strong>자동 생성:</strong> 비활성화 (수동 생성만 가능)</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- 네비게이션 -->
<!-- 네비게이션 부분 수정 -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <nav>
                    <?php if ($is_popup): ?>
                    <button type="button" class="btn btn-outline-secondary me-2" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise me-1"></i>새로고침
                    </button>
                    <button type="button" class="btn btn-outline-danger" onclick="if(confirm('창을 닫으시겠습니까?')) window.close()">
                        <i class="bi bi-x-circle me-1"></i>창 닫기
                    </button>
                    <?php else: ?>
                    <a href="./admin.php" class="btn btn-outline-primary me-2">
                        <i class="bi bi-speedometer2 me-1"></i>통합 관리
                    </a>
                    <a href="./debug_preset_result.php" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-bug me-1"></i>진단 도구
                    </a>
                    <a href="./index.php" class="btn btn-outline-success">
                        <i class="bi bi-dice-6 me-1"></i>게임 홈
                    </a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </div>
</div>
        <div class="row">
            <!-- 제어 패널 -->
            <div class="col-lg-4">
                <!-- 현재 상태 -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-info-circle me-2"></i>현재 상태
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-12 mb-3">
                                <h5>현재 회차: <span class="text-success"><?php echo $stats['current_round_number'] ?></span></h5>
                                <?php if ($current_round): ?>
                                <small class="text-muted">
                                    상태: <?php echo $current_round['status'] ?><br>
                                    <?php echo date('H:i', strtotime($current_round['start_time'])) ?> ~ <?php echo date('H:i', strtotime($current_round['result_time'])) ?>
                                </small>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="stats-card">
                                    <div class="stats-number"><?php echo $stats['total_scheduled'] ?></div>
                                    <div class="small text-muted">예정 회차</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stats-card">
                                    <div class="stats-number"><?php echo $stats['total_completed'] ?></div>
                                    <div class="small text-muted">완료 회차</div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (isset($stats['future_high_percentage'])): ?>
                        <div class="row mt-2">
                            <div class="col-6">
                                <div class="stats-card">
                                    <div class="stats-number"><?php echo $stats['future_high_percentage'] ?>%</div>
                                    <div class="small text-muted">미래 대 비율</div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stats-card">
                                    <div class="stats-number"><?php echo $stats['future_odd_percentage'] ?>%</div>
                                    <div class="small text-muted">미래 홀 비율</div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mt-3 text-center">
                            <small class="text-info">
                                <i class="bi bi-info-circle me-1"></i>
                                현재 설정: 베팅 <?php echo $settings_info['betting_time'] ?>초 + 결과 <?php echo $settings_info['result_time'] ?>초 = 총 <?php echo $settings_info['game_interval'] ?>초 간격
                            </small>
                            <br>
                            <small class="text-muted">
                                배율: 대소 <?php echo $settings_info['win_rate_high_low'] ?>배, 홀짝 <?php echo $settings_info['win_rate_odd_even'] ?>배
                            </small>
                        </div>
                    </div>
                </div>

                <!-- 회차 생성 -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-plus-circle me-2"></i>회차 생성
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="action" value="generate_rounds">
                            
                            <div class="mb-3">
                                <label class="form-label">생성 개수</label>
                                <select class="form-select" name="count">
                                    <option value="20">20회차</option>
                                    <option value="50" selected>50회차</option>
                                    <option value="100">100회차</option>
                                    <option value="200">200회차 (하루)</option>
                                </select>
                                <small class="text-muted">
                                    시작 시간은 마지막 회차 다음으로 자동 설정됩니다
                                </small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">결과 패턴</label>
                                <select class="form-select" name="pattern">
                                    <option value="random">완전 랜덤</option>
                                    <option value="balanced">균형잡힌 (50:50)</option>
                                    <option value="house_favor">하우스 유리 (70%)</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100" 
                                    onclick="return confirm('새로운 회차들을 생성하시겠습니까?\\n\\n연속적인 시간으로 자동 생성됩니다.')">
                                <i class="bi bi-plus-circle me-1"></i>회차 생성
                            </button>
                        </form>
                    </div>
                </div>

                <!-- 관리 도구 -->
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-tools me-2"></i>관리 도구
                    </div>
                    <div class="card-body">
                        <form method="post" class="mb-2">
                            <input type="hidden" name="action" value="delete_future_rounds">
                            <input type="hidden" name="confirm" value="yes">
                            <button type="submit" class="btn btn-danger w-100" 
                                    onclick="return confirm('모든 미래 회차를 삭제하시겠습니까? 이 작업은 되돌릴 수 없습니다.')">
                                <i class="bi bi-trash me-1"></i>미래 회차 모두 삭제
                            </button>
                        </form>
                        
                        <a href="./simple_cron.php?manual=1" class="btn btn-outline-primary w-100" target="_blank">
                            <i class="bi bi-play-circle me-1"></i>크론잡 실행 (새 창)
                        </a>
                    </div>
                </div>
            </div>

            <!-- 회차 목록 -->
            <div class="col-lg-8">
                <!-- 미래 회차 -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-clock me-2"></i>예정된 회차 (최대 50개)</span>
                        <span class="badge bg-success"><?php echo count($future_rounds) ?>개</span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($future_rounds)): ?>
                        <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                            <table class="table table-hover table-sm">
                                <thead class="sticky-top bg-white">
                                    <tr>
                                        <th>회차</th>
                                        <th>시작시간</th>
                                        <th>주사위</th>
                                        <th>결과</th>
                                        <th>수정</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($future_rounds as $round): ?>
                                    <tr class="status-future">
                                        <td><strong><?php echo $round['round_number'] ?></strong></td>
                                        <td>
                                            <span class="badge bg-info time-badge">
                                                <?php echo date('m/d H:i', strtotime($round['start_time'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="dice-display">
                                                <div class="dice-num"><?php echo $round['dice1'] ?></div>
                                                <div class="dice-num"><?php echo $round['dice2'] ?></div>
                                                <div class="dice-num"><?php echo $round['dice3'] ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo $round['total'] ?></strong>
                                            <div class="result-badges">
                                                <span class="badge bg-<?php echo $round['is_high'] ? 'primary' : 'info' ?> badge-sm">
                                                    <?php echo $round['is_high'] ? '대' : '소' ?>
                                                </span>
                                                <span class="badge bg-<?php echo $round['is_odd'] ? 'success' : 'warning' ?> badge-sm">
                                                    <?php echo $round['is_odd'] ? '홀' : '짝' ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editModal<?php echo $round['round_id'] ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- 수정 모달 -->
                                    <div class="modal fade" id="editModal<?php echo $round['round_id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title"><?php echo $round['round_number'] ?>회차 결과 수정</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="post">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="action" value="update_round_result">
                                                        <input type="hidden" name="round_id" value="<?php echo $round['round_id'] ?>">
                                                        
                                                        <div class="row">
                                                            <div class="col-4">
                                                                <label class="form-label">주사위 1</label>
                                                                <input type="number" class="form-control edit-dice" name="dice1" 
                                                                       value="<?php echo $round['dice1'] ?>" min="1" max="6" required>
                                                            </div>
                                                            <div class="col-4">
                                                                <label class="form-label">주사위 2</label>
                                                                <input type="number" class="form-control edit-dice" name="dice2" 
                                                                       value="<?php echo $round['dice2'] ?>" min="1" max="6" required>
                                                            </div>
                                                            <div class="col-4">
                                                                <label class="form-label">주사위 3</label>
                                                                <input type="number" class="form-control edit-dice" name="dice3" 
                                                                       value="<?php echo $round['dice3'] ?>" min="1" max="6" required>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mt-3 text-center">
                                                            <div id="preview<?php echo $round['round_id'] ?>" class="text-muted">
                                                                현재: <?php echo $round['total'] ?> 
                                                                (<?php echo $round['is_high'] ? '대' : '소' ?>, 
                                                                <?php echo $round['is_odd'] ? '홀' : '짝' ?>)
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mt-2 text-center">
                                                            <small class="text-info">
                                                                예정 시간: <?php echo date('Y-m-d H:i', strtotime($round['start_time'])) ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                                                        <button type="submit" class="btn btn-primary">저장</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-calendar-x" style="font-size: 3rem; color: #ccc;"></i>
                            <h5 class="mt-3 text-muted">예정된 회차가 없습니다</h5>
                            <p class="text-muted">좌측 패널에서 회차를 생성해주세요.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 완료된 회차 -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-check2-all me-2"></i>완료된 회차 (최근 20개)</span>
                        <span class="badge bg-secondary"><?php echo count($completed_rounds) ?>개</span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($completed_rounds)): ?>
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-hover table-sm">
                                <thead class="sticky-top bg-white">
                                    <tr>
                                        <th>회차</th>
                                        <th>완료시간</th>
                                        <th>주사위</th>
                                        <th>결과</th>
                                        <th>참여</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($completed_rounds as $round): ?>
                                    <tr class="status-completed">
                                        <td><strong><?php echo $round['round_number'] ?></strong></td>
                                        <td>
                                            <span class="badge bg-success time-badge">
                                                <?php echo date('m/d H:i', strtotime($round['result_time'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="dice-display">
                                                <div class="dice-num"><?php echo $round['dice1'] ?></div>
                                                <div class="dice-num"><?php echo $round['dice2'] ?></div>
                                                <div class="dice-num"><?php echo $round['dice3'] ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo $round['total'] ?></strong>
                                            <div class="result-badges">
                                                <span class="badge bg-<?php echo $round['is_high'] ? 'primary' : 'info' ?> badge-sm">
                                                    <?php echo $round['is_high'] ? '대' : '소' ?>
                                                </span>
                                                <span class="badge bg-<?php echo $round['is_odd'] ? 'success' : 'warning' ?> badge-sm">
                                                    <?php echo $round['is_odd'] ? '홀' : '짝' ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <small><?php echo $round['total_players'] ?? 0 ?>명</small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-3">
                            <i class="bi bi-hourglass-split text-muted" style="font-size: 2rem;"></i>
                            <p class="mt-2 text-muted">완료된 회차가 없습니다</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 주사위 값 변경 시 미리보기 업데이트
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.edit-dice').forEach(input => {
                input.addEventListener('input', function() {
                    const modal = this.closest('.modal');
                    const roundId = modal.id.replace('editModal', '');
                    const dice1 = parseInt(modal.querySelector('input[name="dice1"]').value) || 1;
                    const dice2 = parseInt(modal.querySelector('input[name="dice2"]').value) || 1;
                    const dice3 = parseInt(modal.querySelector('input[name="dice3"]').value) || 1;
                    
                    const total = dice1 + dice2 + dice3;
                    const isHigh = total >= 11;
                    const isOdd = total % 2 === 1;
                    
                    const preview = modal.querySelector(`#preview${roundId}`);
                    preview.innerHTML = `미리보기: ${total} (${isHigh ? '대' : '소'}, ${isOdd ? '홀' : '짝'})`;
                    preview.className = 'text-primary fw-bold';
                });
            });
        });
    </script>
	<?php if (!empty($message) && $message_type === 'success' && $is_popup): ?>
<script>
    // 부모창에 변경사항 알림
    if (window.opener && !window.opener.closed) {
        window.opener.postMessage('roundsUpdated', '*');
    }
</script>
<?php endif; ?>
<script>
    // 팝업 크기 자동 조정
    <?php if ($is_popup): ?>
    window.onload = function() {
        // 내용에 맞게 창 크기 조정 (선택사항)
        const bodyHeight = document.body.scrollHeight;
        const windowHeight = window.innerHeight;
        if (bodyHeight > windowHeight) {
            window.resizeTo(window.outerWidth, bodyHeight + 100);
        }
    };
    <?php endif; ?>
</script>
</body>
</html>