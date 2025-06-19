<?php
/*
 * 파일명: force_test.php  
 * 기능: 강제로 미리 결과 시스템 테스트
 */

include_once(__DIR__ . '/../common.php');

if (!$is_admin) {
    exit('관리자만 접근 가능');
}

echo "<h2>🧪 강제 테스트</h2>";

// 현재 회차 확인
$current = sql_fetch("SELECT * FROM dice_game_rounds WHERE status IN ('betting', 'waiting') ORDER BY round_number DESC LIMIT 1");
$next_round = $current ? $current['round_number'] + 1 : 69;

echo "<p>다음 회차: <strong>{$next_round}</strong></p>";

// 1. 다음 회차에 미리 결과 강제 생성
echo "<h3>1. 미리 결과 강제 생성</h3>";

// 기존 결과 삭제
sql_query("DELETE FROM dice_game_pre_results WHERE round_number = {$next_round}");

// 특별한 결과 생성 (6-6-6으로 확실히 구분되게)
$sql = "
    INSERT INTO dice_game_pre_results 
    (round_number, dice1, dice2, dice3, total, is_high, is_odd, estimated_time, created_at, created_by) 
    VALUES 
    ({$next_round}, 6, 6, 6, 18, 1, 0, NOW(), NOW(), 'force_test')
";
sql_query($sql);

echo "<p>✅ {$next_round}회차에 <strong>6-6-6 (합계 18, 대+짝)</strong> 결과를 강제로 설정했습니다.</p>";

// 2. 설정값 강제 활성화
echo "<h3>2. 설정값 강제 활성화</h3>";

sql_query("
    INSERT INTO dice_game_config (config_key, config_value, config_desc, updated_at) VALUES
    ('use_pre_results', '1', '미리 설정된 결과 사용 여부', NOW()),
    ('auto_generate_results', '1', '결과 자동 생성 여부', NOW())
    ON DUPLICATE KEY UPDATE 
        config_value = '1',
        updated_at = NOW()
");

echo "<p>✅ 미리 결과 사용이 강제로 활성화되었습니다.</p>";

// 3. 현재 설정 확인
echo "<h3>3. 현재 설정 확인</h3>";
$configs = sql_query("SELECT * FROM dice_game_config WHERE config_key LIKE '%pre%' OR config_key = 'use_pre_results'");
while ($row = sql_fetch_array($configs)) {
    echo "<p>{$row['config_key']}: <strong>{$row['config_value']}</strong></p>";
}

// 4. 크론잡 수동 실행 링크
echo "<h3>4. 테스트 방법</h3>";
echo "<ol>";
echo "<li><a href='./cron_game_manager_pre_results.php?manual=1' target='_blank' style='background: red; color: white; padding: 10px; text-decoration: none;'>🚀 새 크론잡 수동 실행</a></li>";
echo "<li>게임 페이지에서 {$next_round}회차 진행 확인</li>";
echo "<li>결과가 <strong>6-6-6</strong>으로 나오는지 확인</li>";
echo "<li>만약 다른 결과가 나오면 아직 기존 크론잡이 실행되고 있는 것</li>";
echo "</ol>";

// 5. 실시간 모니터링
echo "<h3>5. 실시간 모니터링</h3>";
echo "<p><a href='javascript:void(0)' onclick='startMonitoring()' style='background: blue; color: white; padding: 10px; text-decoration: none;'>📊 실시간 모니터링 시작</a></p>";

echo "<div id='monitoring' style='display: none; background: #f0f0f0; padding: 20px; margin: 20px 0;'>";
echo "<h4>모니터링 중...</h4>";
echo "<div id='status'></div>";
echo "</div>";

?>

<script>
let monitoringInterval;

function startMonitoring() {
    document.getElementById('monitoring').style.display = 'block';
    
    monitoringInterval = setInterval(function() {
        fetch('./status_check.php')
            .then(response => response.json())
            .then(data => {
                const statusDiv = document.getElementById('status');
                statusDiv.innerHTML = `
                    <p><strong>현재 시간:</strong> ${new Date().toLocaleString()}</p>
                    <p><strong>현재 회차:</strong> ${data.round_number}</p>
                    <p><strong>상태:</strong> ${data.status}</p>
                    <p><strong>남은 시간:</strong> ${data.time_left}초</p>
                    ${data.dice1 ? `<p><strong>결과:</strong> ${data.dice1}-${data.dice2}-${data.dice3} (합계: ${data.total})</p>` : ''}
                `;
                
                // 다음 회차가 완료되면 결과 확인
                if (data.round_number == <?php echo $next_round ?> && data.status === 'completed') {
                    if (data.dice1 == 6 && data.dice2 == 6 && data.dice3 == 6) {
                        statusDiv.innerHTML += '<p style="color: green; font-weight: bold;">✅ 성공! 미리 설정된 결과(6-6-6)가 사용되었습니다!</p>';
                    } else {
                        statusDiv.innerHTML += '<p style="color: red; font-weight: bold;">❌ 실패! 다른 결과가 나왔습니다. 아직 기존 크론잡이 실행되고 있습니다.</p>';
                    }
                    clearInterval(monitoringInterval);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }, 3000);
}
</script>