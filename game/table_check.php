<?php
/*
* 파일명: table_check.php
* 위치: /game/table_check.php
* 기능: 게임 테이블 상태 확인 및 생성
* 작성일: 2025-06-12
*/

// ===================================
// 그누보드 환경 설정
// ===================================
include_once('./../common.php');

// 관리자만 접근 가능
if (!$is_admin) {
    alert('관리자만 접근할 수 있습니다.', G5_URL);
}

echo "<h2>게임 테이블 상태 확인</h2>";
echo "<style>
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
.error { color: red; font-weight: bold; }
.success { color: green; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
</style>";

// ===================================
// 테이블 존재 확인
// ===================================
echo "<h3>1. 현재 테이블 상태</h3>";

$required_tables = [
    'dice_game_config',
    'dice_game_rounds', 
    'dice_game_bets',
    'dice_game_history',
    'dice_game_daily_stats'
];

echo "<table>";
echo "<tr><th>테이블명</th><th>상태</th><th>레코드 수</th></tr>";

$missing_tables = [];
foreach ($required_tables as $table) {
    // 테이블 존재 확인
    $check_sql = "SHOW TABLES LIKE '{$table}'";
    $check_result = sql_query($check_sql);
    
    if (sql_num_rows($check_result) > 0) {
        // 레코드 수 확인
        $count_sql = "SELECT COUNT(*) as cnt FROM {$table}";
        $count_result = sql_fetch($count_sql);
        $record_count = $count_result['cnt'];
        
        echo "<tr>";
        echo "<td>{$table}</td>";
        echo "<td class='success'>존재</td>";
        echo "<td>{$record_count}</td>";
        echo "</tr>";
    } else {
        echo "<tr>";
        echo "<td>{$table}</td>";
        echo "<td class='error'>없음</td>";
        echo "<td>-</td>";
        echo "</tr>";
        $missing_tables[] = $table;
    }
}
echo "</table>";

// ===================================
// 테이블 생성
// ===================================
if (!empty($missing_tables)) {
    echo "<h3>2. 누락된 테이블 생성</h3>";
    
    if (isset($_GET['create_tables'])) {
        foreach ($missing_tables as $table) {
            echo "<p>테이블 생성 중: {$table}...</p>";
            
            $create_sql = '';
            
            switch ($table) {
                case 'dice_game_config':
                    $create_sql = "
                        CREATE TABLE dice_game_config (
                            config_key varchar(50) NOT NULL PRIMARY KEY,
                            config_value varchar(255) NOT NULL,
                            config_desc varchar(255) DEFAULT NULL,
                            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    ";
                    break;
                    
                case 'dice_game_rounds':
                    $create_sql = "
                        CREATE TABLE dice_game_rounds (
                            round_id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                            round_number int(11) NOT NULL,
                            start_time datetime NOT NULL,
                            end_time datetime NOT NULL,
                            result_time datetime NOT NULL,
                            dice1 tinyint(1) DEFAULT NULL,
                            dice2 tinyint(1) DEFAULT NULL,
                            dice3 tinyint(1) DEFAULT NULL,
                            total tinyint(2) DEFAULT NULL,
                            is_high tinyint(1) DEFAULT NULL,
                            is_odd tinyint(1) DEFAULT NULL,
                            status enum('betting','waiting','completed') DEFAULT 'betting',
                            total_bet_amount int(11) DEFAULT 0,
                            total_players int(11) DEFAULT 0,
                            created_at datetime DEFAULT CURRENT_TIMESTAMP,
                            UNIQUE KEY unique_round (round_number),
                            KEY idx_status (status),
                            KEY idx_round_number (round_number)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    ";
                    break;
                    
                case 'dice_game_bets':
                    $create_sql = "
                        CREATE TABLE dice_game_bets (
                            bet_id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                            round_id int(11) NOT NULL,
                            round_number int(11) NOT NULL,
                            mb_id varchar(20) NOT NULL,
                            mb_name varchar(255) NOT NULL,
                            bet_high_low enum('high','low') NOT NULL,
                            bet_odd_even enum('odd','even') NOT NULL,
                            bet_amount int(11) NOT NULL,
                            win_amount int(11) DEFAULT 0,
                            is_win tinyint(1) DEFAULT NULL,
                            created_at datetime DEFAULT CURRENT_TIMESTAMP,
                            processed_at datetime DEFAULT NULL,
                            KEY idx_round (round_id),
                            KEY idx_member (mb_id),
                            KEY idx_round_number (round_number),
                            KEY idx_processed (is_win, processed_at)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    ";
                    break;
                    
                case 'dice_game_history':
                    $create_sql = "
                        CREATE TABLE dice_game_history (
                            history_id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                            round_number int(11) NOT NULL,
                            total_bets int(11) DEFAULT 0,
                            total_amount int(11) DEFAULT 0,
                            total_win_amount int(11) DEFAULT 0,
                            house_profit int(11) DEFAULT 0,
                            dice1 tinyint(1) NOT NULL,
                            dice2 tinyint(1) NOT NULL,
                            dice3 tinyint(1) NOT NULL,
                            total tinyint(2) NOT NULL,
                            result_high_low enum('high','low') NOT NULL,
                            result_odd_even enum('odd','even') NOT NULL,
                            completed_at datetime DEFAULT CURRENT_TIMESTAMP,
                            UNIQUE KEY unique_round (round_number),
                            KEY idx_completed (completed_at)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    ";
                    break;
                    
                case 'dice_game_daily_stats':
                    $create_sql = "
                        CREATE TABLE dice_game_daily_stats (
                            stat_id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                            stat_date date NOT NULL,
                            total_games int(11) DEFAULT 0,
                            total_bet_amount int(11) DEFAULT 0,
                            total_win_amount int(11) DEFAULT 0,
                            house_profit int(11) DEFAULT 0,
                            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                            UNIQUE KEY unique_date (stat_date)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    ";
                    break;
            }
            
            if ($create_sql && sql_query($create_sql)) {
                echo "<p class='success'>{$table} 테이블 생성 완료</p>";
            } else {
                echo "<p class='error'>{$table} 테이블 생성 실패</p>";
            }
        }
        
        // 기본 설정 데이터 추가
        if (in_array('dice_game_config', $missing_tables)) {
            echo "<p>기본 설정 데이터 추가 중...</p>";
            $config_data = [
                ['min_bet', '1000', '최소 베팅 금액'],
                ['max_bet', '100000', '최대 베팅 금액'],
                ['betting_time', '90', '베팅 시간(초)'],
                ['result_time', '30', '결과 발표 시간(초)'],
                ['win_rate_high_low', '1.95', '대소 배율'],
                ['win_rate_odd_even', '1.95', '홀짝 배율'],
                ['game_status', '1', '게임 활성화 (1:활성, 0:비활성)'],
                ['game_interval', '120', '게임 간격(초)']
            ];
            
            foreach ($config_data as $config) {
                $insert_config_sql = "
                    INSERT INTO dice_game_config (config_key, config_value, config_desc) 
                    VALUES ('{$config[0]}', '{$config[1]}', '{$config[2]}')
                ";
                sql_query($insert_config_sql);
            }
            echo "<p class='success'>기본 설정 데이터 추가 완료</p>";
        }
        
        echo "<script>setTimeout(function(){ location.href='table_check.php'; }, 2000);</script>";
        
    } else {
        echo "<p class='error'>다음 테이블들이 누락되었습니다: " . implode(', ', $missing_tables) . "</p>";
        echo "<p><a href='?create_tables=1' style='background: #dc3545; color: white; padding: 10px; text-decoration: none;'>누락된 테이블 생성</a></p>";
    }
} else {
    echo "<p class='success'>모든 필수 테이블이 존재합니다.</p>";
}

// ===================================
// 포인트 테이블 확인
// ===================================
echo "<h3>3. 포인트 테이블 확인</h3>";
$point_table_sql = "SHOW TABLES LIKE 'g5_point'";
$point_table_result = sql_query($point_table_sql);

if (sql_num_rows($point_table_result) > 0) {
    echo "<p class='success'>g5_point 테이블 존재</p>";
    
    // 포인트 테이블 구조 확인
    $point_structure_sql = "DESCRIBE g5_point";
    $point_structure = sql_query($point_structure_sql);
    
    echo "<table>";
    echo "<tr><th>컬럼명</th><th>타입</th><th>NULL</th><th>키</th><th>기본값</th></tr>";
    while ($column = sql_fetch_array($point_structure)) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p class='error'>g5_point 테이블이 없습니다!</p>";
}

?>

<p><a href="diagnosis.php">진단 도구로 돌아가기</a></p>