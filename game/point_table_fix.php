<?php
/*
* 파일명: point_table_fix.php
* 위치: /game/point_table_fix.php
* 기능: 포인트 테이블 구조 확인 및 수정
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

echo "<h2>포인트 테이블 구조 확인 및 수정</h2>";
echo "<style>
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
.error { color: red; font-weight: bold; }
.success { color: green; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
</style>";

// ===================================
// 1. 포인트 테이블 구조 확인
// ===================================
echo "<h3>1. g5_point 테이블 구조</h3>";

$describe_sql = "DESCRIBE g5_point";
$describe_result = sql_query($describe_sql);

if ($describe_result) {
    echo "<table>";
    echo "<tr><th>컬럼명</th><th>타입</th><th>NULL</th><th>키</th><th>기본값</th><th>Extra</th></tr>";
    
    $columns = [];
    while ($column = sql_fetch_array($describe_result)) {
        $columns[] = $column['Field'];
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "<td>" . (isset($column['Extra']) ? $column['Extra'] : '') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // 필수 컬럼 확인
    $required_columns = [
        'po_id', 'mb_id', 'po_datetime', 'po_content', 'po_point', 
        'po_use_point', 'po_expired', 'po_expire_date', 'po_sum'
    ];
    
    echo "<h4>필수 컬럼 확인</h4>";
    $missing_columns = [];
    foreach ($required_columns as $req_col) {
        if (in_array($req_col, $columns)) {
            echo "<p class='success'>{$req_col}: 존재</p>";
        } else {
            echo "<p class='error'>{$req_col}: 누락</p>";
            $missing_columns[] = $req_col;
        }
    }
    
} else {
    echo "<p class='error'>g5_point 테이블 구조를 확인할 수 없습니다.</p>";
}

// ===================================
// 2. 수동 포인트 삽입 테스트
// ===================================
echo "<h3>2. 수동 포인트 삽입 테스트</h3>";

if (isset($_GET['test_insert']) && $is_member) {
    // 현재 포인트 확인
    $current_point_sql = "SELECT COALESCE(SUM(po_point), 0) as total FROM g5_point WHERE mb_id = '{$member['mb_id']}'";
    $current_point_result = sql_fetch($current_point_sql);
    $current_point = intval($current_point_result['total']);
    
    echo "<p>현재 포인트: " . number_format($current_point) . "P</p>";
    
    // 최소한의 데이터로 테스트
    $test_amount = 1;
    $test_content = "포인트 테스트";
    $new_sum = $current_point + $test_amount;
    $now = date('Y-m-d H:i:s');
    
    // 방법 1: 모든 컬럼 명시
    echo "<h4>방법 1: 모든 컬럼 명시</h4>";
    $insert_sql1 = "
        INSERT INTO g5_point 
        (mb_id, po_datetime, po_content, po_point, po_use_point, po_expired, po_expire_date, po_sum)
        VALUES 
        ('{$member['mb_id']}', '{$now}', '{$test_content}', {$test_amount}, 0, 0, '9999-12-31', {$new_sum})
    ";
    
    echo "<pre>" . htmlspecialchars($insert_sql1) . "</pre>";
    
    $result1 = sql_query($insert_sql1);
    if ($result1) {
        echo "<p class='success'>방법 1 성공</p>";
        
        // 포인트 확인
        $after_point1 = get_point_sum($member['mb_id']);
        echo "<p>삽입 후 포인트: " . number_format($after_point1) . "P</p>";
        
    } else {
        echo "<p class='error'>방법 1 실패</p>";
        if (function_exists('sql_error')) {
            $error = sql_error();
            echo "<p class='error'>오류: {$error}</p>";
        }
    }
    
    // 방법 2: 필수 컬럼만
    echo "<h4>방법 2: 필수 컬럼만</h4>";
    $test_amount2 = 1;
    $new_sum2 = get_point_sum($member['mb_id']) + $test_amount2;
    
    $insert_sql2 = "
        INSERT INTO g5_point 
        (mb_id, po_datetime, po_content, po_point, po_sum)
        VALUES 
        ('{$member['mb_id']}', '{$now}', '{$test_content} 2', {$test_amount2}, {$new_sum2})
    ";
    
    echo "<pre>" . htmlspecialchars($insert_sql2) . "</pre>";
    
    $result2 = sql_query($insert_sql2);
    if ($result2) {
        echo "<p class='success'>방법 2 성공</p>";
        
        // 포인트 확인
        $after_point2 = get_point_sum($member['mb_id']);
        echo "<p>삽입 후 포인트: " . number_format($after_point2) . "P</p>";
        
    } else {
        echo "<p class='error'>방법 2 실패</p>";
        if (function_exists('sql_error')) {
            $error = sql_error();
            echo "<p class='error'>오류: {$error}</p>";
        }
    }
    
    // 방법 3: insert_point 함수 다시 테스트
    echo "<h4>방법 3: insert_point 함수 재테스트</h4>";
    $test_amount3 = 1;
    
    // 다양한 파라미터 조합 테스트
    $test_cases = [
        ['dice_game_bets', 0, '베팅'],
        ['dice_game', 0, '게임'],
        ['board', 0, '포인트'],
        ['point', 0, '적립'],
        ['', 0, ''],
    ];
    
    foreach ($test_cases as $i => $case) {
        echo "<p><strong>테스트 케이스 " . ($i + 1) . ":</strong> rel_table='{$case[0]}', rel_id={$case[1]}, rel_action='{$case[2]}'</p>";
        
        $before_test = get_point_sum($member['mb_id']);
        $result = insert_point($member['mb_id'], $test_amount3, "insert_point 테스트 " . ($i + 1), $case[0], $case[1], $case[2]);
        $after_test = get_point_sum($member['mb_id']);
        
        if ($result && $after_test == $before_test + $test_amount3) {
            echo "<p class='success'>테스트 케이스 " . ($i + 1) . " 성공!</p>";
            echo "<p>이 파라미터를 사용하세요: rel_table='{$case[0]}', rel_id={$case[1]}, rel_action='{$case[2]}'</p>";
            break;
        } else {
            echo "<p class='error'>테스트 케이스 " . ($i + 1) . " 실패</p>";
        }
    }
    
} else {
    echo "<p><a href='?test_insert=1'>수동 포인트 삽입 테스트 실행</a></p>";
}

// ===================================
// 3. 최근 포인트 이력
// ===================================
echo "<h3>3. 최근 포인트 이력 (최근 5건)</h3>";

if ($is_member) {
    $recent_sql = "
        SELECT * FROM g5_point 
        WHERE mb_id = '{$member['mb_id']}' 
        ORDER BY po_datetime DESC 
        LIMIT 5
    ";
    $recent_result = sql_query($recent_sql);
    
    echo "<table>";
    echo "<tr><th>일시</th><th>내용</th><th>포인트</th><th>누적</th></tr>";
    
    while ($recent = sql_fetch_array($recent_result)) {
        echo "<tr>";
        echo "<td>{$recent['po_datetime']}</td>";
        echo "<td>{$recent['po_content']}</td>";
        echo "<td>" . number_format($recent['po_point']) . "</td>";
        echo "<td>" . number_format($recent['po_sum']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// ===================================
// 4. 권한 및 설정 확인
// ===================================
echo "<h3>4. 시스템 정보</h3>";
echo "<table>";
echo "<tr><th>항목</th><th>값</th></tr>";
echo "<tr><td>현재 사용자</td><td>" . (function_exists('get_current_user') ? get_current_user() : '확인불가') . "</td></tr>";
echo "<tr><td>MySQL 버전</td><td>";
$mysql_version = sql_fetch("SELECT VERSION() as version");
echo $mysql_version ? $mysql_version['version'] : '확인불가';
echo "</td></tr>";
echo "<tr><td>포인트 테이블 레코드 수</td><td>";
$count_result = sql_fetch("SELECT COUNT(*) as cnt FROM g5_point");
echo $count_result ? number_format($count_result['cnt']) : '확인불가';
echo "</td></tr>";
echo "</table>";

?>

<p><a href="diagnosis.php">진단 도구로 돌아가기</a></p>