-- 
-- 파일명: pre_result_table.sql
-- 위치: /game/sql/pre_result_table.sql
-- 기능: 미리 게임 결과 설정용 테이블 생성
-- 작성일: 2025-06-12
--

-- ===================================
-- 미리 설정된 게임 결과 테이블
-- ===================================

CREATE TABLE IF NOT EXISTS `dice_game_pre_results` (
  `pre_result_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '미리 결과 ID',
  `round_number` int(11) NOT NULL COMMENT '회차 번호',
  `dice1` int(1) NOT NULL COMMENT '주사위 1 (1-6)',
  `dice2` int(1) NOT NULL COMMENT '주사위 2 (1-6)',
  `dice3` int(1) NOT NULL COMMENT '주사위 3 (1-6)',
  `total` int(2) NOT NULL COMMENT '주사위 합계 (3-18)',
  `is_high` tinyint(1) NOT NULL COMMENT '대소 결과 (1:대, 0:소)',
  `is_odd` tinyint(1) NOT NULL COMMENT '홀짝 결과 (1:홀, 0:짝)',
  `estimated_time` datetime NOT NULL COMMENT '예상 실행 시간',
  `is_used` tinyint(1) NOT NULL DEFAULT 0 COMMENT '사용 여부 (1:사용됨, 0:대기)',
  `used_at` datetime DEFAULT NULL COMMENT '실제 사용 시간',
  `created_at` datetime NOT NULL COMMENT '생성 시간',
  `created_by` varchar(20) DEFAULT NULL COMMENT '생성자 ID',
  PRIMARY KEY (`pre_result_id`),
  UNIQUE KEY `round_number` (`round_number`),
  KEY `idx_estimated_time` (`estimated_time`),
  KEY `idx_is_used` (`is_used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='미리 설정된 게임 결과';

-- ===================================
-- 게임 결과 패턴 템플릿 테이블 (선택사항)
-- ===================================

CREATE TABLE IF NOT EXISTS `dice_game_result_patterns` (
  `pattern_id` int(11) NOT NULL AUTO_INCREMENT COMMENT '패턴 ID',
  `pattern_name` varchar(50) NOT NULL COMMENT '패턴 이름',
  `pattern_desc` text COMMENT '패턴 설명',
  `high_ratio` decimal(5,2) NOT NULL DEFAULT 50.00 COMMENT '대 비율 (%)',
  `odd_ratio` decimal(5,2) NOT NULL DEFAULT 50.00 COMMENT '홀 비율 (%)',
  `win_ratio` decimal(5,2) NOT NULL DEFAULT 50.00 COMMENT '플레이어 승률 (%)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '활성화 여부',
  `created_at` datetime NOT NULL COMMENT '생성 시간',
  PRIMARY KEY (`pattern_id`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='게임 결과 패턴 템플릿';

-- ===================================
-- 기본 패턴 데이터 삽입
-- ===================================

INSERT INTO `dice_game_result_patterns` 
(`pattern_name`, `pattern_desc`, `high_ratio`, `odd_ratio`, `win_ratio`, `created_at`) VALUES
('완전 랜덤', '완전히 랜덤한 결과', 50.00, 50.00, 50.00, NOW()),
('균형잡힌', '대소/홀짝이 균형잡힌 결과', 50.00, 50.00, 50.00, NOW()),
('하우스 유리', '하우스가 유리한 결과 (플레이어 승률 30%)', 45.00, 45.00, 30.00, NOW()),
('플레이어 유리', '플레이어가 유리한 결과 (플레이어 승률 70%)', 55.00, 55.00, 70.00, NOW()),
('대 편향', '대가 많이 나오는 결과', 70.00, 50.00, 50.00, NOW()),
('소 편향', '소가 많이 나오는 결과', 30.00, 50.00, 50.00, NOW()),
('홀 편향', '홀이 많이 나오는 결과', 50.00, 70.00, 50.00, NOW()),
('짝 편향', '짝이 많이 나오는 결과', 50.00, 30.00, 50.00, NOW())
ON DUPLICATE KEY UPDATE 
    pattern_desc = VALUES(pattern_desc),
    high_ratio = VALUES(high_ratio),
    odd_ratio = VALUES(odd_ratio),
    win_ratio = VALUES(win_ratio);

-- ===================================
-- 크론잡 관리자 함수 수정용 설정
-- ===================================

-- 미리 설정된 결과 사용 여부 설정
INSERT INTO dice_game_config (config_key, config_value, config_desc, updated_at) VALUES
('use_pre_results', '1', '미리 설정된 결과 사용 여부 (1:사용, 0:랜덤)', NOW()),
('auto_generate_results', '1', '결과 자동 생성 여부 (1:자동, 0:수동)', NOW()),
('pre_result_buffer', '10', '미리 준비할 결과 개수', NOW())
ON DUPLICATE KEY UPDATE 
    config_desc = VALUES(config_desc),
    updated_at = NOW();

-- ===================================
-- 유용한 조회 쿼리들 (주석)
-- ===================================

-- 다음 10개 회차의 미리 설정된 결과 조회
-- SELECT * FROM dice_game_pre_results 
-- WHERE is_used = 0 AND round_number > (SELECT COALESCE(MAX(round_number), 0) FROM dice_game_rounds)
-- ORDER BY round_number ASC LIMIT 10;

-- 특정 시간대의 결과 통계
-- SELECT 
--     COUNT(*) as total_rounds,
--     SUM(is_high) as high_count,
--     SUM(is_odd) as odd_count,
--     ROUND(AVG(is_high) * 100, 1) as high_percentage,
--     ROUND(AVG(is_odd) * 100, 1) as odd_percentage
-- FROM dice_game_pre_results 
-- WHERE estimated_time BETWEEN '2025-06-12 20:00:00' AND '2025-06-12 21:00:00';

-- 사용되지 않은 오래된 결과 정리
-- DELETE FROM dice_game_pre_results 
-- WHERE is_used = 0 AND estimated_time < DATE_SUB(NOW(), INTERVAL 1 DAY);

-- 패턴별 결과 생성 예시 (프로시저로 구현 가능)
-- DELIMITER //
-- CREATE PROCEDURE GenerateBalancedResults(IN start_round INT, IN count INT)
-- BEGIN
--     DECLARE i INT DEFAULT 0;
--     DECLARE round_num INT;
--     DECLARE dice_total INT;
--     DECLARE is_high_val TINYINT;
--     DECLARE is_odd_val TINYINT;
--     
--     WHILE i < count DO
--         SET round_num = start_round + i;
--         -- 균형잡힌 패턴 로직
--         SET is_high_val = IF(i % 2 = 0, 1, 0);
--         SET is_odd_val = IF((i + 1) % 2 = 0, 1, 0);
--         -- ... 주사위 조합 생성 로직
--         SET i = i + 1;
--     END WHILE;
-- END //
-- DELIMITER ;