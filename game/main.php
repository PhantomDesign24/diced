<?php
/*
* 파일명: index.php
* 위치: /game/index.php
* 기능: 주사위 게임 메인 페이지 (미리 생성된 회차 시스템)
* 작성일: 2025-06-12
* 수정일: 2025-06-12
*/

// ===================================
// 그누보드 환경 설정
// ===================================
include_once('../common.php');

// 로그인 체크
if (!$is_member) {
    alert('로그인이 필요합니다.', G5_BBS_URL.'/login.php?url='.urlencode(G5_URL.'/game/'));
}

// ===================================
// 게임 설정 로드
// ===================================
$sql = "SELECT * FROM dice_game_config";
$result = sql_query($sql);
$config = array();
while ($row = sql_fetch_array($result)) {
    $config[$row['config_key']] = $row['config_value'];
}

// 게임 비활성화 체크
if ($config['game_status'] != '1') {
    alert('현재 게임이 중단되었습니다.', G5_URL);
}

// ===================================
// 현재 회차 정보 조회 및 상태 자동 전환
// ===================================

$now = time();

// 1단계: 만료된 waiting 회차들을 completed로 전환
$expired_waiting_sql = "
    UPDATE dice_game_rounds 
    SET status = 'completed' 
    WHERE status = 'waiting' 
    AND result_time <= NOW()
";
sql_query($expired_waiting_sql);

// 2단계: 만료된 betting 회차들을 waiting으로 전환
$expired_betting_sql = "
    UPDATE dice_game_rounds 
    SET status = 'waiting' 
    WHERE status = 'betting' 
    AND end_time <= NOW()
";
sql_query($expired_betting_sql);

// 3단계: 시작 시간이 된 scheduled 회차들을 betting으로 전환
$ready_scheduled_sql = "
    UPDATE dice_game_rounds 
    SET status = 'betting' 
    WHERE status = 'scheduled' 
    AND start_time <= NOW()
";
sql_query($ready_scheduled_sql);

// 4단계: 현재 진행중인 회차 조회 (betting 또는 waiting 상태)
$current_round_sql = "
    SELECT * FROM dice_game_rounds 
    WHERE status IN ('betting', 'waiting') 
    ORDER BY round_number ASC 
    LIMIT 1
";
$current_round = sql_fetch($current_round_sql);

// 5단계: 진행중인 회차가 없으면 다음 예정된 회차 확인
if (!$current_round) {
    $next_round_sql = "
        SELECT * FROM dice_game_rounds 
        WHERE status = 'scheduled' 
        ORDER BY start_time ASC 
        LIMIT 1
    ";
    $next_round = sql_fetch($next_round_sql);
    
    if ($next_round) {
        $start_time = strtotime($next_round['start_time']);
        
        // 시작 시간이 되었으면 즉시 betting 상태로 변경
        if ($now >= $start_time) {
            $update_sql = "UPDATE dice_game_rounds SET status = 'betting' WHERE round_id = {$next_round['round_id']}";
            if (sql_query($update_sql)) {
                $current_round = $next_round;
                $current_round['status'] = 'betting';
            }
        } else {
            // 아직 시작 시간이 안됨 - 대기 상태 표시
            $current_round = $next_round;
        }
    }
}

// 여전히 회차가 없으면 관리자에게 알림
if (!$current_round) {
    alert('진행할 수 있는 게임 회차가 없습니다.\n관리자가 회차를 생성할 때까지 기다려주세요.', G5_URL);
}

// ===================================
// 최근 완료된 회차 결과 조회
// ===================================
$last_result_sql = "
    SELECT * FROM dice_game_rounds 
    WHERE status = 'completed' 
    AND game_a_result IS NOT NULL 
    ORDER BY round_number DESC 
    LIMIT 1
";
$last_result = sql_fetch($last_result_sql);

// ===================================
// 회원 포인트 조회
// ===================================
$member_point = get_point_sum($member['mb_id']);

// ===================================
// 게임 상태 판정 및 관리자 설정 적용
// ===================================
$game_phase = 'waiting'; // 기본값
$time_remaining = 0;

// 관리자 설정에서 시간 가져오기
$betting_time = isset($config['betting_time']) ? intval($config['betting_time']) : 90;
$result_time_duration = isset($config['result_time']) ? intval($config['result_time']) : 30;
$game_interval = isset($config['game_interval']) ? intval($config['game_interval']) : ($betting_time + $result_time_duration);

if ($current_round) {
    $now = time();
    $start_time = strtotime($current_round['start_time']);
    $end_time = strtotime($current_round['end_time']);
    $result_time = strtotime($current_round['result_time']);
    
    if ($current_round['status'] === 'scheduled') {
        $game_phase = 'scheduled';
        $time_remaining = $start_time - $now;
    } elseif ($current_round['status'] === 'betting') {
        if ($now <= $end_time) {
            $game_phase = 'betting';
            $time_remaining = $end_time - $now;
        } else {
            $game_phase = 'waiting';
            $time_remaining = $result_time - $now;
        }
    } elseif ($current_round['status'] === 'waiting') {
        $game_phase = 'waiting';
        $time_remaining = $result_time - $now;
    } else {
        $game_phase = 'completed';
        $time_remaining = 0;
    }
}

// ===================================
// 페이지 헤더
// ===================================
$g5['title'] = '주사위 게임';
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="title" content="<?php echo $g5['title']; ?>">
    <title>주사위 게임</title>
    
    <!-- Bootstrap CSS 비동기 로드 -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"></noscript>
    
    <!-- Bootstrap Icons -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css"></noscript>
    
    <!-- 게임 CSS -->
    <link rel="stylesheet" href="<?php echo G5_URL?>/game/css/game.css?ver=<?php echo time();?>">
    <!-- 인라인 CSS (타이머 및 기본 스타일) -->
    <style>
        .game-body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .game-wrapper {
            padding: 1rem;
        }
        
        .game-container {
            max-width: 500px;
            margin: 0 auto;
        }
        
        .round-info {
            background: linear-gradient(135deg, rgba(255,255,255,0.1), rgba(255,255,255,0.05));
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 1rem;
        }
            /* A/B/C 게임 베팅 버튼 스타일 추가 */
    .bet-button {
        border: 2px solid #dee2e6 !important;
        background: #ffffff !important;
        color: #495057 !important;
        transition: all 0.3s ease !important;
        cursor: pointer !important;
    }
    
    .bet-button:hover:not(:disabled) {
        transform: translateY(-1px) !important;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
    }
    
    /* A 게임 */
    .bet-button[data-game="A"]:hover:not(:disabled) {
        border-color: #0d6efd !important;
        background-color: #e7f1ff !important;
    }
    
    .bet-button[data-game="A"].active {
        border-color: #0d6efd !important;
        background-color: #0d6efd !important;
        color: #ffffff !important;
    }
    
    /* B 게임 */
    .bet-button[data-game="B"]:hover:not(:disabled) {
        border-color: #198754 !important;
        background-color: #d1e7dd !important;
    }
    
    .bet-button[data-game="B"].active {
        border-color: #198754 !important;
        background-color: #198754 !important;
        color: #ffffff !important;
    }
    
    /* C 게임 */
    .bet-button[data-game="C"]:hover:not(:disabled) {
        border-color: #ffc107 !important;
        background-color: #fff3cd !important;
    }
    
    .bet-button[data-game="C"].active {
        border-color: #ffc107 !important;
        background-color: #ffc107 !important;
        color: #212529 !important;
    }
    
    .bet-button.active small {
        opacity: 0.9;
    }
    
    .bet-button:disabled {
        opacity: 0.6 !important;
        cursor: not-allowed !important;
    }
        .timer-display {
            font-size: 1.5rem;
            font-weight: bold;
            font-family: 'Courier New', monospace;
            color: #fff;
            text-shadow: 0 0 10px rgba(255,255,255,0.5);
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            background: rgba(255,255,255,0.95);
            margin-bottom: 1rem;
        }
        
        .bet-button {
            border: 2px solid #e9ecef;
            background: #fff;
            color: #495057;
            transition: all 0.3s ease;
        }
        
        .bet-button:hover {
            border-color: #667eea;
            background: #667eea;
            color: #fff;
        }
        
        .bet-button.active {
            border-color: #667eea;
            background: #667eea;
            color: #fff;
        }
        
        /* A/B/C 게임 결과 표시 스타일 */
        .game-result-box {
            transition: transform 0.2s;
        }
        
        .game-result-box:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .game-result-box h6 {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .game-result-box h3 {
            font-weight: bold;
            letter-spacing: 1px;
        }
        
        .dice-container {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .dice {
            width: 60px;
            height: 60px;
            background: #fff;
            border: 2px solid #333;
            border-radius: 8px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        
        .dice-dot {
            width: 8px;
            height: 8px;
            background: #333;
            border-radius: 50%;
            margin: 1px;
        }
        
        .result-summary {
            text-align: center;
            font-size: 1.2rem;
            font-weight: bold;
            color: #495057;
        }
        
        .input-group-text {
            border-right: none;
            background-color: #fff;
        }
        
        .form-control {
            border-left: none;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #ffc107, #ff8800);
            border: none;
            color: #fff;
            font-weight: bold;
        }
        
        .btn-warning:hover {
            background: linear-gradient(135deg, #ff8800, #ffc107);
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.4);
        }
    </style>
</head>

<body class="game-body">
    <div class="game-wrapper">
        <div class="container-fluid game-container">
        
        <!-- 회차 정보 및 타이머 헤더 -->
        <div class="card round-info text-white border-0">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-dice-6 me-3" style="font-size: 28px;"></i>
                        <div>
                            <?php if ($game_phase === 'scheduled'): ?>
                                <p class="mb-1 opacity-75">다음 회차 준비중</p>
                                <h5 class="mb-0 fw-bold"><?php echo $current_round['round_number']; ?>회차</h5>
                            <?php else: ?>
                                <p class="mb-1 opacity-75">현재 진행중인 회차</p>
                                <h5 class="mb-0 fw-bold"><?php echo $current_round['round_number']; ?>회차</h5>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <?php if ($game_phase === 'scheduled'): ?>
                            <p class="mb-1 opacity-75">시작까지</p>
                            <div class="timer-display" id="countdown">--:--</div>
                            <small class="text-white-50">
                                <?php echo date('H:i', strtotime($current_round['start_time'])); ?>에 시작
                            </small>
                        <?php elseif ($game_phase === 'betting'): ?>
                            <p class="mb-1 opacity-75">베팅 마감까지</p>
                            <div class="timer-display" id="countdown">--:--</div>
                            <small class="text-white-50">
                                <?php echo date('H:i', strtotime($current_round['end_time'])); ?>에 마감
                            </small>
                        <?php elseif ($game_phase === 'waiting'): ?>
                            <p class="mb-1 opacity-75">결과 발표까지</p>
                            <div class="timer-display" id="countdown">--:--</div>
                            <small class="text-white-50">
                                <?php echo date('H:i', strtotime($current_round['result_time'])); ?>에 발표
                            </small>
                        <?php else: ?>
                            <p class="mb-1 opacity-75">상태</p>
                            <div class="timer-display">완료</div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- 게임 상태 표시 -->
                <div class="mt-2">
                    <?php if ($game_phase === 'scheduled'): ?>
                        <span class="badge bg-secondary">대기중</span>
                        <small class="text-white-50 ms-2">
                            <?php echo date('H:i', strtotime($current_round['start_time'])); ?>에 시작 예정
                        </small>
                    <?php elseif ($game_phase === 'betting'): ?>
                        <span class="badge bg-success">베팅 가능</span>
                        <small class="text-white-50 ms-2">지금 베팅하세요!</small>
                    <?php elseif ($game_phase === 'waiting'): ?>
                        <span class="badge bg-warning">베팅 마감</span>
                        <small class="text-white-50 ms-2">결과 발표 대기중</small>
                    <?php endif; ?>
                    
                    <!-- 디버깅용 시간 정보 (관리자만) -->
                    <?php if ($is_admin): ?>
                    <div class="mt-1">
                        <small class="text-white-50" style="font-size: 0.7rem;">
                            현재: <?php echo date('H:i:s'); ?> | 
                            시작: <?php echo date('H:i:s', strtotime($current_round['start_time'])); ?> | 
                            마감: <?php echo date('H:i:s', strtotime($current_round['end_time'])); ?> | 
                            결과: <?php echo date('H:i:s', strtotime($current_round['result_time'])); ?>
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 최근 결과 표시 -->
        <div class="card dice-result">
            <div class="card-body">
                <?php if ($last_result && isset($last_result['game_a_result'])): ?>
                    <h6 class="text-muted mb-3">이전 회차 결과 (<?php echo $last_result['round_number']; ?>회차)</h6>
                    <div class="text-center">
                        <div class="row mb-3">
                            <div class="col-4">
                                <div class="game-result-box bg-primary text-white p-3 rounded">
                                    <h6 class="mb-1">A 게임</h6>
                                    <h3 class="mb-0">A<?php echo $last_result['game_a_result']; ?></h3>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="game-result-box bg-success text-white p-3 rounded">
                                    <h6 class="mb-1">B 게임</h6>
                                    <h3 class="mb-0">B<?php echo $last_result['game_b_result']; ?></h3>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="game-result-box bg-warning text-dark p-3 rounded">
                                    <h6 class="mb-1">C 게임</h6>
                                    <h3 class="mb-0">C<?php echo $last_result['game_c_result']; ?></h3>
                                </div>
                            </div>
                        </div>
                        <small class="text-muted">
                            결과 발표: <?php echo date('H:i', strtotime($last_result['result_time'])); ?>
                        </small>
                    </div>
                <?php else: ?>
                    <h6 class="text-muted mb-3">이전 회차 결과</h6>
                    <div class="text-center py-3">
                        <i class="bi bi-hourglass-split text-muted mb-2" style="font-size: 2rem;"></i>
                        <p class="text-muted mb-0">아직 완료된 회차가 없습니다</p>
                        <small class="text-muted">첫 회차가 진행중입니다</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 베팅 영역 -->
        <?php if ($game_phase === 'betting'): ?>
<div class="card">
    <div class="card-body">
        <h6 class="text-muted mb-3">베팅 선택</h6>
        
        <!-- A 게임 선택 -->
        <div class="mb-3">
            <label class="form-label fw-bold text-primary">
                <i class="bi bi-dice-1 me-1"></i>A 게임
            </label>
            <div class="row">
                <div class="col-6">
                    <button type="button" class="btn bet-button w-100 py-3" 
                            data-game="A" data-option="1">
                        <div class="fw-bold">A1</div>
                        <small class="text-muted">배율 x<?php echo $config['game_a1_rate'] ?? '2.0'; ?></small>
                    </button>
                </div>
                <div class="col-6">
                    <button type="button" class="btn bet-button w-100 py-3" 
                            data-game="A" data-option="2">
                        <div class="fw-bold">A2</div>
                        <small class="text-muted">배율 x<?php echo $config['game_a2_rate'] ?? '2.0'; ?></small>
                    </button>
                </div>
            </div>
        </div>

        <!-- B 게임 선택 -->
        <div class="mb-3">
            <label class="form-label fw-bold text-success">
                <i class="bi bi-dice-2 me-1"></i>B 게임
            </label>
            <div class="row">
                <div class="col-6">
                    <button type="button" class="btn bet-button w-100 py-3" 
                            data-game="B" data-option="1">
                        <div class="fw-bold">B1</div>
                        <small class="text-muted">배율 x<?php echo $config['game_b1_rate'] ?? '2.0'; ?></small>
                    </button>
                </div>
                <div class="col-6">
                    <button type="button" class="btn bet-button w-100 py-3" 
                            data-game="B" data-option="2">
                        <div class="fw-bold">B2</div>
                        <small class="text-muted">배율 x<?php echo $config['game_b2_rate'] ?? '2.0'; ?></small>
                    </button>
                </div>
            </div>
        </div>

        <!-- C 게임 선택 -->
        <div class="mb-3">
            <label class="form-label fw-bold text-warning">
                <i class="bi bi-dice-3 me-1"></i>C 게임
            </label>
            <div class="row">
                <div class="col-6">
                    <button type="button" class="btn bet-button w-100 py-3" 
                            data-game="C" data-option="1">
                        <div class="fw-bold">C1</div>
                        <small class="text-muted">배율 x<?php echo $config['game_c1_rate'] ?? '2.0'; ?></small>
                    </button>
                </div>
                <div class="col-6">
                    <button type="button" class="btn bet-button w-100 py-3" 
                            data-game="C" data-option="2">
                        <div class="fw-bold">C2</div>
                        <small class="text-muted">배율 x<?php echo $config['game_c2_rate'] ?? '2.0'; ?></small>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 베팅 폼 -->
<form id="bettingForm">
    <div class="card">
        <div class="card-body">
            <!-- 현재 선택 표시 -->
            <div class="row mb-3">
                <div class="col-6">
                    <label class="form-label">현재 선택</label>
                    <p id="currentSelection" class="mb-0">선택 안함</p>
                </div>
                <div class="col-6">
                    <label class="form-label">보유머니</label>
                    <p class="mb-0" id="userMoney"><?php echo number_format($member_point); ?>P</p>
                </div>
            </div>

            <!-- 베팅 금액 입력 -->
            <div class="mb-3">
                <label class="form-label">베팅금액</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-currency-dollar text-primary"></i>
                    </span>
                    <input type="number" class="form-control border-start-0" id="betAmount" 
                           placeholder="베팅할 포인트를 입력하세요"
                           min="<?php echo $config['min_bet']; ?>" 
                           max="<?php echo min($config['max_bet'], $member_point); ?>">
                </div>
                <small class="text-muted">
                    최소: <?php echo number_format($config['min_bet']); ?>P, 
                    최대: <?php echo number_format(min($config['max_bet'], $member_point)); ?>P
                </small>
            </div>

            <!-- 예상 당첨금 -->
            <div class="mb-3">
                <label class="form-label">예상 당첨금</label>
                <p id="expectedWin" class="h5">0P</p>
                <small class="text-muted" id="rateInfo">선택한 게임의 배율이 적용됩니다</small>
            </div>

            <!-- 히든 필드들 -->
            <input type="hidden" id="roundId" name="round_id" value="<?php echo $current_round['round_id']; ?>">
            <input type="hidden" id="roundNumber" name="round_number" value="<?php echo $current_round['round_number']; ?>">

            <!-- 베팅 버튼 -->
            <button type="submit" class="btn btn-warning w-100 py-3" id="submitBet" disabled>
                <i class="bi bi-play-circle me-2"></i>게임신청
            </button>
        </div>
    </div>
</form>

<?php else: ?>
<!-- 베팅 불가 상태 (기존과 동일) -->
<div class="card">
    <div class="card-body text-center">
        <?php if ($game_phase === 'scheduled'): ?>
            <i class="bi bi-clock text-muted mb-3" style="font-size: 3rem;"></i>
            <h5 class="text-muted">게임 시작 대기중</h5>
            <p class="text-muted mb-0">
                <?php echo date('H:i', strtotime($current_round['start_time'])); ?>에 
                <?php echo $current_round['round_number']; ?>회차가 시작됩니다
            </p>
        <?php elseif ($game_phase === 'waiting'): ?>
            <i class="bi bi-hourglass-split text-warning mb-3" style="font-size: 3rem;"></i>
            <h5 class="text-warning">베팅 마감</h5>
            <p class="text-muted mb-0">결과 발표를 기다려주세요</p>
        <?php else: ?>
            <i class="bi bi-exclamation-triangle text-muted mb-3" style="font-size: 3rem;"></i>
            <h5 class="text-muted">게임 준비중</h5>
            <p class="text-muted mb-0">잠시 후 다시 시도해주세요</p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

        <!-- 하단 메뉴 -->
        <div class="row g-2 mt-3">
            <div class="col-6">
                <a href="./history.php" class="btn btn-outline-dark w-100">
                    <i class="bi bi-clock-history me-1"></i>히스토리
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
    
    <?php include_once('menu.php'); ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
// ===================================
// 게임 설정 변수 (관리자 설정 연동)
// ===================================
const gameConfig = {
    minBet: <?php echo $config['min_bet']; ?>,
    maxBet: <?php echo min($config['max_bet'], $member_point); ?>,
    userPoint: <?php echo $member_point; ?>,
    roundId: <?php echo $current_round['round_id']; ?>,
    roundNumber: <?php echo $current_round['round_number']; ?>,
    endTime: '<?php echo $current_round['end_time']; ?>',
    resultTime: '<?php echo $current_round['result_time']; ?>',
    startTime: '<?php echo $current_round['start_time']; ?>',
    gamePhase: '<?php echo $game_phase; ?>',
    gameInterval: <?php echo $game_interval; ?>,
    bettingTime: <?php echo $betting_time; ?>,
    resultTimeDuration: <?php echo $result_time_duration; ?>,
    // A/B/C 게임 배율 추가
    gameA1Rate: <?php echo $config['game_a1_rate'] ?? '2.0'; ?>,
    gameA2Rate: <?php echo $config['game_a2_rate'] ?? '2.0'; ?>,
    gameB1Rate: <?php echo $config['game_b1_rate'] ?? '2.0'; ?>,
    gameB2Rate: <?php echo $config['game_b2_rate'] ?? '2.0'; ?>,
    gameC1Rate: <?php echo $config['game_c1_rate'] ?? '2.0'; ?>,
    gameC2Rate: <?php echo $config['game_c2_rate'] ?? '2.0'; ?>
};
        
        // ===================================
        // 타이머 설정 및 시작
        // ===================================
        let countdownTimer = null;
        
        function startCountdown() {
            console.log('🕐 타이머 시작 함수 호출됨');
            
            if (countdownTimer) {
                clearInterval(countdownTimer);
                console.log('기존 타이머 정리됨');
            }
            
            // 타겟 시간 결정
            let targetTime;
            let targetLabel;
            
            if (gameConfig.gamePhase === 'scheduled') {
                targetTime = new Date(gameConfig.startTime).getTime();
                targetLabel = '시작까지';
            } else if (gameConfig.gamePhase === 'betting') {
                targetTime = new Date(gameConfig.endTime).getTime();
                targetLabel = '베팅 마감까지';
            } else if (gameConfig.gamePhase === 'waiting') {
                targetTime = new Date(gameConfig.resultTime).getTime();
                targetLabel = '결과 발표까지';
            } else {
                console.log('게임 완료 상태 - 타이머 중단');
                $('#countdown').text('완료');
                return;
            }
            
            console.log('🎯 타이머 설정:', {
                phase: gameConfig.gamePhase,
                targetLabel: targetLabel,
                targetTime: new Date(targetTime).toLocaleString(),
                currentTime: new Date().toLocaleString(),
                gameConfig: gameConfig
            });
            
            function updateTimer() {
                const now = new Date().getTime();
                const timeLeft = targetTime - now;
                
                if (timeLeft <= 0) {
                    console.log('⏰ 타이머 완료 - 페이지 새로고침 예정');
                    $('#countdown').text('00:00');
                    setTimeout(() => {
                        console.log('🔄 페이지 새로고침 실행');
                        location.reload();
                    }, 1000);
                    return;
                }
                
                const minutes = Math.floor(timeLeft / 60000);
                const seconds = Math.floor((timeLeft % 60000) / 1000);
                
                const display = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
                $('#countdown').text(display);
                
                // 10초마다 콘솔에 현재 상태 출력
                if (seconds % 10 === 0 && timeLeft % 60000 < 1000) {
                    console.log('⏱️ 타이머 상태:', {
                        timeLeft: timeLeft,
                        display: display,
                        phase: gameConfig.gamePhase
                    });
                }
            }
            
            // 즉시 한번 실행
            updateTimer();
            
            // 1초마다 업데이트
            countdownTimer = setInterval(updateTimer, 1000);
            console.log('✅ 타이머 인터벌 설정 완료');
        }
        
        // ===================================
        // 페이지 로드 시 초기화
        // ===================================
        $(document).ready(function() {
            console.log('📄 페이지 로드 완료');
            console.log('🎮 게임 설정:', gameConfig);
            
            // jQuery와 DOM 요소 확인
            if ($('#countdown').length === 0) {
                console.error('❌ #countdown 요소를 찾을 수 없습니다!');
                return;
            }
            
            console.log('✅ #countdown 요소 발견:', $('#countdown'));
            
            // 즉시 시간 표시 테스트
            $('#countdown').text('로딩중...');
            
            // 타이머 시작
            setTimeout(() => {
                console.log('🚀 타이머 시작 (1초 지연 후)');
                startCountdown();
            }, 1000);
            
            // 관리자 설정 확인 로그
            console.log('⚙️ 게임 설정 확인:', {
                베팅시간: gameConfig.bettingTime + '초',
                결과시간: gameConfig.resultTimeDuration + '초', 
                게임간격: gameConfig.gameInterval + '초',
                현재단계: gameConfig.gamePhase,
                시작시간: gameConfig.startTime,
                마감시간: gameConfig.endTime,
                결과시간: gameConfig.resultTime
            });
        });
        
// ===================================
// 베팅 관련 스크립트 (A/B/C 게임용)
// ===================================
<?php if ($game_phase === 'betting'): ?>
// 선택된 베팅 정보를 저장하는 객체
let selectedBets = {
    A: null,
    B: null,
    C: null
};

// 게임별 배율 정보
const gameRates = {
    A: {
        1: <?php echo $config['game_a1_rate'] ?? '2.0'; ?>,
        2: <?php echo $config['game_a2_rate'] ?? '2.0'; ?>
    },
    B: {
        1: <?php echo $config['game_b1_rate'] ?? '2.0'; ?>,
        2: <?php echo $config['game_b2_rate'] ?? '2.0'; ?>
    },
    C: {
        1: <?php echo $config['game_c1_rate'] ?? '2.0'; ?>,
        2: <?php echo $config['game_c2_rate'] ?? '2.0'; ?>
    }
};

// 베팅 버튼 클릭 이벤트
$('.bet-button').on('click', function() {
    const game = $(this).data('game');
    const option = $(this).data('option');
    
    // 같은 게임의 다른 버튼 비활성화
    $(`.bet-button[data-game="${game}"]`).removeClass('active');
    
    // 현재 버튼 활성화/비활성화 토글
    if (selectedBets[game] === option) {
        // 이미 선택된 것을 다시 클릭하면 선택 해제
        $(this).removeClass('active');
        selectedBets[game] = null;
    } else {
        // 새로운 선택
        $(this).addClass('active');
        selectedBets[game] = option;
    }
    
    updateSelectionDisplay();
    updateExpectedWin();
    updateSubmitButton();
});

// 베팅 금액 입력 이벤트
$('#betAmount').on('input', function() {
    updateExpectedWin();
    updateSubmitButton();
});

// 현재 선택 표시 업데이트
function updateSelectionDisplay() {
    let selections = [];
    
    for (let game in selectedBets) {
        if (selectedBets[game]) {
            selections.push(`${game}${selectedBets[game]}`);
        }
    }
    
    if (selections.length > 0) {
        $('#currentSelection').text(selections.join(', '));
    } else {
        $('#currentSelection').text('선택 안함');
    }
}

// 예상 당첨금 계산 및 표시
function updateExpectedWin() {
    const betAmount = parseInt($('#betAmount').val()) || 0;
    
    if (betAmount === 0) {
        $('#expectedWin').text('0P');
        $('#rateInfo').text('선택한 게임의 배율이 적용됩니다');
        return;
    }
    
    let totalWin = 0;
    let rateDetails = [];
    
    // 각 게임별로 예상 당첨금 계산
    for (let game in selectedBets) {
        if (selectedBets[game]) {
            const rate = gameRates[game][selectedBets[game]];
            const win = Math.floor(betAmount * rate);
            totalWin += win;
            rateDetails.push(`${game}${selectedBets[game]} (x${rate})`);
        }
    }
    
    $('#expectedWin').text(totalWin.toLocaleString() + 'P');
    
    if (rateDetails.length > 0) {
        $('#rateInfo').text('적용 배율: ' + rateDetails.join(', '));
    } else {
        $('#rateInfo').text('게임을 선택해주세요');
    }
}

// 제출 버튼 활성화/비활성화
function updateSubmitButton() {
    const betAmount = parseInt($('#betAmount').val()) || 0;
    const hasSelection = Object.values(selectedBets).some(bet => bet !== null);
    const validAmount = betAmount >= gameConfig.minBet && betAmount <= gameConfig.maxBet;
    
    $('#submitBet').prop('disabled', !hasSelection || !validAmount);
}

// 폼 제출 이벤트
// 폼 제출 이벤트
$('#bettingForm').on('submit', function(e) {
    e.preventDefault();
    
    // 선택된 베팅 정보를 배열로 변환
    let bets = {};
    for (let game in selectedBets) {
        if (selectedBets[game]) {
            if (!bets[game]) bets[game] = {};
            bets[game][selectedBets[game]] = 1;
        }
    }
    
    // FormData 객체 생성
    const formData = new FormData();
    formData.append('round_id', gameConfig.roundId);
    formData.append('round_number', gameConfig.roundNumber);
    formData.append('bet_amount', parseInt($('#betAmount').val()));
    
    // bets 데이터를 개별적으로 추가
    for (let game in bets) {
        for (let option in bets[game]) {
            formData.append(`bets[${game}][${option}]`, bets[game][option]);
        }
    }
    
    $('#submitBet').prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i>처리중...');
    
    $.ajax({
        url: './bet_process.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                alert('베팅이 완료되었습니다!');
                location.reload();
            } else {
                alert(response.message || '베팅 처리 중 오류가 발생했습니다.');
                $('#submitBet').prop('disabled', false).html('<i class="bi bi-play-circle me-2"></i>게임신청');
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', status, error);
            console.error('Response:', xhr.responseText);
            alert('서버 통신 오류가 발생했습니다.');
            $('#submitBet').prop('disabled', false).html('<i class="bi bi-play-circle me-2"></i>게임신청');
        }
    });
});
<?php endif; ?>
    </script>
    
    <!-- 게임 JS는 인라인으로 처리 -->
</body>
</html>

<?php include_once(G5_PATH.'/tail.sub.php'); ?>