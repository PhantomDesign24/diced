/*
* 파일명: install.sql
* 위치: /game/sql/install.sql
* 기능: 주사위 게임 관련 테이블 생성 (회차별 시스템)
* 작성일: 2025-06-12
* 수정일: 2025-06-12
*/

-- ===================================
-- 게임 설정 테이블
-- ===================================

CREATE TABLE IF NOT EXISTS `dice_game_config` (
  `config_key` varchar(50) NOT NULL,
  `config_value` varchar(255) NOT NULL,
  `config_desc` varchar(255) DEFAULT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 기본 설정값 입력
INSERT INTO `dice_game_config` (`config_key`, `config_value`, `config_desc`, `updated_at`) VALUES
('min_bet', '1000', '최소 베팅 금액', NOW()),
('max_bet', '100000', '최대 베팅 금액', NOW()),
('game_interval', '120', '게임 진행 간격 (초)', NOW()),
('betting_time', '90', '베팅 가능 시간 (초)', NOW()),
('result_time', '30', '결과 발표 시간 (초)', NOW()),
('win_rate_high_low', '1.95', '대소 당첨 배율', NOW()),
('win_rate_odd_even', '1.95', '홀짝 당첨 배율', NOW()),
('game_status', '1', '게임 활성화 상태 (1:활성, 0:비활성)', NOW());

-- ===================================
-- 게임 회차 테이블
-- ===================================

CREATE TABLE IF NOT EXISTS `dice_game_rounds` (
  `round_id` int(11) NOT NULL AUTO_INCREMENT,
  `round_number` int(11) NOT NULL COMMENT '회차 번호',
  `start_time` datetime NOT NULL COMMENT '회차 시작 시간',
  `end_time` datetime NOT NULL COMMENT '베팅 마감 시간',
  `result_time` datetime NOT NULL COMMENT '결과 발표 시간',
  `dice1` tinyint(1) DEFAULT NULL COMMENT '첫번째 주사위 결과',
  `dice2` tinyint(1) DEFAULT NULL COMMENT '두번째 주사위 결과',
  `dice3` tinyint(1) DEFAULT NULL COMMENT '세번째 주사위 결과',
  `total` tinyint(2) DEFAULT NULL COMMENT '주사위 합계',
  `is_high` tinyint(1) DEFAULT NULL COMMENT '대소 결과 (1:대, 0:소)',
  `is_odd` tinyint(1) DEFAULT NULL COMMENT '홀짝 결과 (1:홀, 0:짝)',
  `status` enum('betting','waiting','completed') NOT NULL DEFAULT 'betting' COMMENT '회차 상태',
  `total_bet_amount` int(11) NOT NULL DEFAULT 0 COMMENT '총 베팅 금액',
  `total_players` int(11) NOT NULL DEFAULT 0 COMMENT '총 참여자 수',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`round_id`),
  UNIQUE KEY `round_number` (`round_number`),
  KEY `idx_status` (`status`),
  KEY `idx_start_time` (`start_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================
-- 베팅 테이블
-- ===================================

CREATE TABLE IF NOT EXISTS `dice_game_bets` (
  `bet_id` int(11) NOT NULL AUTO_INCREMENT,
  `round_id` int(11) NOT NULL COMMENT '회차 ID',
  `round_number` int(11) NOT NULL COMMENT '회차 번호',
  `mb_id` varchar(20) NOT NULL COMMENT '회원 ID',
  `mb_name` varchar(255) NOT NULL COMMENT '회원 이름',
  `bet_high_low` enum('high','low') NOT NULL COMMENT '대소 베팅',
  `bet_odd_even` enum('odd','even') NOT NULL COMMENT '홀짝 베팅',
  `bet_amount` int(11) NOT NULL COMMENT '베팅 금액',
  `win_amount` int(11) NOT NULL DEFAULT 0 COMMENT '당첨 금액',
  `is_win` tinyint(1) DEFAULT NULL COMMENT '당첨 여부 (1:당첨, 0:실패, NULL:미정)',
  `created_at` datetime NOT NULL COMMENT '베팅 시간',
  `processed_at` datetime DEFAULT NULL COMMENT '정산 시간',
  PRIMARY KEY (`bet_id`),
  KEY `idx_round_id` (`round_id`),
  KEY `idx_mb_id` (`mb_id`),
  KEY `idx_round_number` (`round_number`),
  FOREIGN KEY (`round_id`) REFERENCES `dice_game_rounds` (`round_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================
-- 게임 히스토리 테이블 (회차별 통계)
-- ===================================

CREATE TABLE IF NOT EXISTS `dice_game_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `round_number` int(11) NOT NULL COMMENT '회차 번호',
  `total_bets` int(11) NOT NULL DEFAULT 0 COMMENT '총 베팅 수',
  `total_amount` bigint(20) NOT NULL DEFAULT 0 COMMENT '총 베팅 금액',
  `total_win_amount` bigint(20) NOT NULL DEFAULT 0 COMMENT '총 당첨 금액',
  `house_profit` bigint(20) NOT NULL DEFAULT 0 COMMENT '하우스 수익',
  `dice1` tinyint(1) NOT NULL COMMENT '주사위 1',
  `dice2` tinyint(1) NOT NULL COMMENT '주사위 2',
  `dice3` tinyint(1) NOT NULL COMMENT '주사위 3',
  `total` tinyint(2) NOT NULL COMMENT '합계',
  `result_high_low` enum('high','low') NOT NULL COMMENT '대소 결과',
  `result_odd_even` enum('odd','even') NOT NULL COMMENT '홀짝 결과',
  `completed_at` datetime NOT NULL COMMENT '완료 시간',
  PRIMARY KEY (`history_id`),
  UNIQUE KEY `round_number` (`round_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================
-- 실시간 상태 테이블
-- ===================================

CREATE TABLE IF NOT EXISTS `dice_game_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `current_round` int(11) NOT NULL COMMENT '현재 회차',
  `next_round_start` datetime NOT NULL COMMENT '다음 회차 시작 시간',
  `game_phase` enum('betting','waiting','result') NOT NULL DEFAULT 'betting' COMMENT '현재 게임 단계',
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 초기 상태 데이터 삽입
INSERT INTO `dice_game_status` (`current_round`, `next_round_start`, `game_phase`, `updated_at`) 
VALUES (1, DATE_ADD(NOW(), INTERVAL 2 MINUTE), 'betting', NOW());