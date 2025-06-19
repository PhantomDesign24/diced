-- ===================================
-- 충전/출금 시스템 테이블 생성
-- ===================================

-- 1. 충전/출금 신청 테이블
CREATE TABLE payment_requests (
    request_id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    mb_id varchar(20) NOT NULL,
    mb_name varchar(255) NOT NULL,
    request_type enum('charge','withdraw') NOT NULL COMMENT '요청 타입 (charge:충전, withdraw:출금)',
    amount int(11) NOT NULL COMMENT '신청 금액',
    bank_name varchar(50) DEFAULT NULL COMMENT '은행명',
    account_number varchar(50) DEFAULT NULL COMMENT '계좌번호',
    account_holder varchar(100) DEFAULT NULL COMMENT '예금주명',
    deposit_name varchar(100) DEFAULT NULL COMMENT '입금자명 (충전시)',
    admin_bank_info varchar(255) DEFAULT NULL COMMENT '관리자 계좌 정보',
    status enum('pending','approved','rejected','completed') DEFAULT 'pending' COMMENT '상태',
    admin_id varchar(20) DEFAULT NULL COMMENT '처리 관리자',
    admin_memo text DEFAULT NULL COMMENT '관리자 메모',
    reject_reason text DEFAULT NULL COMMENT '거부 사유',
    processed_at datetime DEFAULT NULL COMMENT '처리 시간',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    KEY idx_member (mb_id),
    KEY idx_type (request_type),
    KEY idx_status (status),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='충전/출금 신청';

-- 2. 시스템 설정 테이블
CREATE TABLE payment_config (
    config_key varchar(50) NOT NULL PRIMARY KEY,
    config_value varchar(255) NOT NULL,
    config_desc varchar(255) DEFAULT NULL,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='충전/출금 설정';

-- 3. 관리자 계좌 정보 테이블
CREATE TABLE payment_admin_accounts (
    account_id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    bank_name varchar(50) NOT NULL COMMENT '은행명',
    account_number varchar(50) NOT NULL COMMENT '계좌번호',
    account_holder varchar(100) NOT NULL COMMENT '예금주명',
    is_active tinyint(1) DEFAULT 1 COMMENT '사용 여부',
    display_order int(11) DEFAULT 0 COMMENT '표시 순서',
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    KEY idx_active (is_active),
    KEY idx_order (display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='관리자 계좌 정보';

-- 4. 충전/출금 통계 테이블
CREATE TABLE payment_daily_stats (
    stat_id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    stat_date date NOT NULL,
    total_charge_requests int(11) DEFAULT 0 COMMENT '총 충전 신청 건수',
    total_charge_amount int(11) DEFAULT 0 COMMENT '총 충전 신청 금액',
    completed_charge_requests int(11) DEFAULT 0 COMMENT '완료된 충전 건수',
    completed_charge_amount int(11) DEFAULT 0 COMMENT '완료된 충전 금액',
    total_withdraw_requests int(11) DEFAULT 0 COMMENT '총 출금 신청 건수',
    total_withdraw_amount int(11) DEFAULT 0 COMMENT '총 출금 신청 금액',
    completed_withdraw_requests int(11) DEFAULT 0 COMMENT '완료된 출금 건수',
    completed_withdraw_amount int(11) DEFAULT 0 COMMENT '완료된 출금 금액',
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_date (stat_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='일일 충전/출금 통계';

-- ===================================
-- 기본 설정 데이터 삽입
-- ===================================

INSERT INTO payment_config (config_key, config_value, config_desc) VALUES
('min_charge_amount', '10000', '최소 충전 금액'),
('max_charge_amount', '1000000', '최대 충전 금액'),
('min_withdraw_amount', '10000', '최소 출금 금액'),
('max_withdraw_amount', '1000000', '최대 출금 금액'),
('charge_fee_rate', '0', '충전 수수료율 (%)'),
('withdraw_fee_rate', '0', '출금 수수료율 (%)'),
('withdraw_fee_fixed', '0', '출금 고정 수수료'),
('system_status', '1', '시스템 상태 (1:활성, 0:비활성)'),
('business_hours_start', '09:00', '업무 시작 시간'),
('business_hours_end', '18:00', '업무 종료 시간'),
('weekend_processing', '0', '주말 처리 여부 (1:처리, 0:불가)'),
('auto_approval_limit', '100000', '자동 승인 한도 (0:수동승인만)');

-- ===================================
-- 샘플 관리자 계좌 정보
-- ===================================

INSERT INTO payment_admin_accounts (bank_name, account_number, account_holder, is_active, display_order) VALUES
('국민은행', '123456-78-901234', '게임사이트', 1, 1),
('신한은행', '234-567-890123', '게임사이트', 1, 2),
('우리은행', '1002-345-678901', '게임사이트', 0, 3);

-- ===================================
-- 인덱스 추가 (성능 최적화)
-- ===================================

-- 신청 테이블 복합 인덱스
ALTER TABLE payment_requests ADD INDEX idx_member_status (mb_id, status);
ALTER TABLE payment_requests ADD INDEX idx_type_status (request_type, status);
ALTER TABLE payment_requests ADD INDEX idx_status_created (status, created_at);

-- 설정 테이블은 이미 PRIMARY KEY가 있어서 추가 인덱스 불필요

-- 계좌 테이블 복합 인덱스
ALTER TABLE payment_admin_accounts ADD INDEX idx_active_order (is_active, display_order);