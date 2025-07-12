<?php
/*
 * 파일명: admin.php
 * 위치: /game/admin.php
 * 기능: 통합 관리자 페이지 (회원/입출금/베팅/게임결과/계좌/가입코드 관리)
 * 작성일: 2025-06-12
 * 수정일: 2025-06-13 (payment_config 테이블 분리)
 */

include_once(__DIR__ . '/../common.php');

// 관리자 권한 확인
if (!$is_admin) {
    alert('관리자만 접근 가능합니다.');
    goto_url('./index.php');
}

// ===================================
// 공통 함수
// ===================================

/**
 * 게임 설정값 조회 함수 (dice_game_config 테이블용)
 * 
 * @param string $key 설정 키
 * @param string $default 기본값
 * @return string 설정값
 */
function getConfigValue($key, $default = '') {
    $escaped_key = sql_real_escape_string($key);
    $sql = "SELECT config_value FROM dice_game_config WHERE config_key = '{$escaped_key}'";
    $result = sql_fetch($sql);
    return $result ? $result['config_value'] : $default;
}

/**
 * 게임 설정값 업데이트 함수 (dice_game_config 테이블용)
 * 
 * @param string $key 설정 키
 * @param string $value 설정값
 * @return boolean 성공 여부
 */
function updateConfigValue($key, $value) {
    $escaped_key = sql_real_escape_string($key);
    $escaped_value = sql_real_escape_string($value);
    $sql = "UPDATE dice_game_config SET config_value = '{$escaped_value}', updated_at = NOW() WHERE config_key = '{$escaped_key}'";
    return sql_query($sql);
}

/**
 * 충전/출금 설정값 조회 함수 (payment_config 테이블용)
 * 
 * @param string $key 설정 키
 * @param string $default 기본값
 * @return string 설정값
 */
function getPaymentConfigValue($key, $default = '') {
    $escaped_key = sql_real_escape_string($key);
    $sql = "SELECT config_value FROM payment_config WHERE config_key = '{$escaped_key}'";
    $result = sql_fetch($sql);
    return $result ? $result['config_value'] : $default;
}

/**
 * 충전/출금 설정값 업데이트 함수 (payment_config 테이블용)
 * 
 * @param string $key 설정 키
 * @param string $value 설정값
 * @return boolean 성공 여부
 */
function updatePaymentConfigValue($key, $value) {
    $escaped_key = sql_real_escape_string($key);
    $escaped_value = sql_real_escape_string($value);
    $sql = "UPDATE payment_config SET config_value = '{$escaped_value}', updated_at = NOW() WHERE config_key = '{$escaped_key}'";
    return sql_query($sql);
}

// ===================================
// POST 요청 처리
// ===================================

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
// 방법 1: 포인트를 완전히 리셋하고 새로 설정
case 'update_member_info':
    $mb_id = $_POST['mb_id'] ?? '';
    $mb_password = $_POST['mb_password'] ?? '';
    $mb_name = $_POST['mb_name'] ?? '';
    $mb_point = (int)($_POST['mb_point'] ?? 0);
    $mb_1 = $_POST['mb_1'] ?? ''; // 입금은행
    $mb_2 = $_POST['mb_2'] ?? ''; // 계좌번호
    
    if (!empty($mb_id)) {
        $escaped_mb_id = sql_real_escape_string($mb_id);
        $escaped_mb_name = sql_real_escape_string($mb_name);
        $escaped_mb_1 = sql_real_escape_string($mb_1);
        $escaped_mb_2 = sql_real_escape_string($mb_2);
        
        // 현재 포인트 합계 계산
        $sum_point_sql = "SELECT SUM(po_point) as sum_point FROM {$g5['point_table']} WHERE mb_id = '{$escaped_mb_id}'";
        $sum_result = sql_fetch($sum_point_sql);
        $current_sum = (int)$sum_result['sum_point'];
        
        // 회원 정보 업데이트 (포인트 포함)
        if (!empty($mb_password)) {
            $mb_password_encrypted = get_encrypt_string($mb_password);
            $sql = "UPDATE {$g5['member_table']} SET 
                        mb_password = '{$mb_password_encrypted}',
                        mb_name = '{$escaped_mb_name}',
                        mb_point = {$mb_point},
                        mb_1 = '{$escaped_mb_1}',
                        mb_2 = '{$escaped_mb_2}'
                    WHERE mb_id = '{$escaped_mb_id}'";
        } else {
            $sql = "UPDATE {$g5['member_table']} SET 
                        mb_name = '{$escaped_mb_name}',
                        mb_point = {$mb_point},
                        mb_1 = '{$escaped_mb_1}',
                        mb_2 = '{$escaped_mb_2}'
                    WHERE mb_id = '{$escaped_mb_id}'";
        }
        
        sql_query($sql);
        
        // 포인트 차이 계산 (현재 g5_point 합계 기준)
        $point_diff = $mb_point - $current_sum;
        
        if ($point_diff != 0) {
            $po_content = "관리자 포인트 설정 (총 " . number_format($mb_point) . "P)";
            $po_datetime = G5_TIME_YMDHIS;
            
            // g5_point 테이블에 차액 추가
            $point_sql = "INSERT INTO {$g5['point_table']} 
                            (mb_id, po_datetime, po_content, po_point, po_use_point, po_expired, 
                             po_expire_date, po_mb_point, po_rel_table, po_rel_id, po_rel_action) 
                         VALUES 
                            ('{$escaped_mb_id}', '{$po_datetime}', '{$po_content}', {$point_diff}, 0, 0, 
                             '9999-12-31', {$mb_point}, '@passive', '{$member['mb_id']}', '관리자수정')";
            
            sql_query($point_sql);
        }
        
        $message = "회원 정보가 수정되었습니다. (포인트: " . number_format($mb_point) . "P)";
        $message_type = 'success';
    }
    break;		
case 'update_game_config':
    // 게임 기본 설정
    updateConfigValue('game_status', $_POST['game_status'] ?? '0');
    updateConfigValue('betting_time', $_POST['betting_time']);
    updateConfigValue('result_time', $_POST['result_time']);
    updateConfigValue('game_interval', $_POST['game_interval']);
    updateConfigValue('min_bet', $_POST['min_bet']);
    updateConfigValue('max_bet', $_POST['max_bet']);
    
    // A 게임 배율
    updateConfigValue('game_a1_rate', $_POST['game_a1_rate']);
    updateConfigValue('game_a2_rate', $_POST['game_a2_rate']);
    
    // B 게임 배율
    updateConfigValue('game_b1_rate', $_POST['game_b1_rate']);
    updateConfigValue('game_b2_rate', $_POST['game_b2_rate']);
    
    // C 게임 배율
    updateConfigValue('game_c1_rate', $_POST['game_c1_rate']);
    updateConfigValue('game_c2_rate', $_POST['game_c2_rate']);
    
    // 자동 회차 생성 설정
    updateConfigValue('auto_generate_rounds', $_POST['auto_generate_rounds'] ?? '0');
    updateConfigValue('auto_generate_interval', $_POST['auto_generate_interval']);
    updateConfigValue('auto_generate_count', $_POST['auto_generate_count']);
    
    $message = '게임 설정이 저장되었습니다.';
    $message_type = 'success';
    break;
                
case 'update_round_result':
    $round_id = (int)($_POST['round_id'] ?? 0);
    $game_a_result = $_POST['game_a_result'] ?? '';
    $game_b_result = $_POST['game_b_result'] ?? '';
    $game_c_result = $_POST['game_c_result'] ?? '';
    
    if ($round_id > 0 && in_array($game_a_result, ['1', '2']) && 
        in_array($game_b_result, ['1', '2']) && 
        in_array($game_c_result, ['1', '2'])) {
        
        $escaped_a = sql_real_escape_string($game_a_result);
        $escaped_b = sql_real_escape_string($game_b_result);
        $escaped_c = sql_real_escape_string($game_c_result);
        
        // 회차 정보 조회
        $round_info = sql_fetch("SELECT * FROM dice_game_rounds WHERE round_id = {$round_id}");
        
        if ($round_info) {
            // 상태에 관계없이 결과 업데이트 (진행중인 회차도 수정 가능)
            $sql = "UPDATE dice_game_rounds SET 
                        game_a_result = '{$escaped_a}', 
                        game_b_result = '{$escaped_b}', 
                        game_c_result = '{$escaped_c}',
                        updated_at = NOW()
                    WHERE round_id = {$round_id}";
            
            if (sql_query($sql)) {
                $message = "회차 #{$round_info['round_number']}의 결과가 수정되었습니다. (A{$game_a_result}, B{$game_b_result}, C{$game_c_result})";
                $message_type = 'success';
            } else {
                $message = "결과 수정 중 오류가 발생했습니다.";
                $message_type = 'error';
            }
        }
    }
    break;
case 'update_withdraw_account':
    $request_id = (int)($_POST['request_id'] ?? 0);
    $bank_name = $_POST['bank_name'] ?? '';
    $account_number = $_POST['account_number'] ?? '';
    $account_holder = $_POST['account_holder'] ?? '';
    
    if ($request_id > 0 && !empty($bank_name) && !empty($account_number) && !empty($account_holder)) {
        $escaped_bank_name = sql_real_escape_string($bank_name);
        $escaped_account_number = sql_real_escape_string($account_number);
        $escaped_account_holder = sql_real_escape_string($account_holder);
        
        // 출금 요청 정보 확인
        $request_info = sql_fetch("SELECT * FROM payment_requests WHERE request_id = {$request_id} AND request_type = 'withdraw'");
        
        if ($request_info) {
            // 계좌 정보 업데이트
            $update_sql = "UPDATE payment_requests SET 
                            bank_name = '{$escaped_bank_name}',
                            account_number = '{$escaped_account_number}',
                            account_holder = '{$escaped_account_holder}',
                            admin_memo = CONCAT(IFNULL(admin_memo, ''), '\n[', NOW(), '] 관리자가 계좌정보 수정')
                          WHERE request_id = {$request_id}";
            
            if (sql_query($update_sql)) {
                $message = "출금 계좌 정보가 수정되었습니다.";
                $message_type = 'success';
            } else {
                $message = "계좌 정보 수정 중 오류가 발생했습니다.";
                $message_type = 'error';
            }
        } else {
            $message = "출금 요청을 찾을 수 없습니다.";
            $message_type = 'error';
        }
    } else {
        $message = "필수 정보를 모두 입력해주세요.";
        $message_type = 'error';
    }
    break;
            case 'update_payment_status':
                $request_id = (int)($_POST['request_id'] ?? 0);
                $new_status = $_POST['new_status'] ?? '';
                if ($request_id > 0 && !empty($new_status)) {
                    $escaped_status = sql_real_escape_string($new_status);
                    
                    // 요청 정보 조회
                    $payment_info = sql_fetch("SELECT * FROM payment_requests WHERE request_id = {$request_id}");
                    
                    if ($payment_info) {
                        $old_status = $payment_info['status'];
                        $amount = (int)$payment_info['amount'];
                        $mb_id = $payment_info['mb_id'];
                        $escaped_mb_id = sql_real_escape_string($mb_id);
                        $now = date('Y-m-d H:i:s');
                        
                        // 상태 업데이트
                        $sql = "UPDATE payment_requests SET status = '{$escaped_status}', processed_at = NOW(), admin_id = '{$member['mb_id']}' WHERE request_id = {$request_id}";
                        sql_query($sql);
                        
                        // 포인트 처리 로직
                        if ($payment_info['request_type'] === 'charge') {
                            // 충전 처리
                            if ($old_status !== 'approved' && $new_status === 'approved') {
                                // 승인: 포인트 지급
                                insert_point($mb_id, $amount, "충전 승인 (관리자: {$member['mb_id']})");
                                $message = "충전이 승인되어 {$amount}P가 지급되었습니다.";
                            } else if ($old_status === 'approved' && $new_status !== 'approved') {
                                // 승인 취소: 포인트 차감
                                insert_point($mb_id, -$amount, "충전 승인 취소 (관리자: {$member['mb_id']})");
                                $message = "충전 승인이 취소되어 {$amount}P가 차감되었습니다.";
                            } else {
                                $message = "충전 상태가 변경되었습니다.";
                            }
                        } else if ($payment_info['request_type'] === 'withdraw') {
							// 출금 처리
							if ($old_status === 'pending' && $new_status === 'approved') {
								// 승인: 포인트 차감
								$member_info = get_member($mb_id);
								if ($member_info['mb_point'] >= $amount) {
									insert_point($mb_id, -$amount, "출금 승인 (#{$request_id})");
									$message = "출금이 승인되어 {$amount}P가 차감되었습니다.";
								} else {
									// 포인트 부족 시 승인 불가
									$message = "포인트가 부족하여 출금을 승인할 수 없습니다. (필요: " . number_format($amount) . "P, 보유: " . number_format($member_info['mb_point']) . "P)";
									$message_type = 'error';
									break; // 상태 업데이트하지 않음
								}
							} else if ($old_status === 'approved' && $new_status === 'pending') {
								// 승인→대기: 포인트 복구
								insert_point($mb_id, $amount, "출금 승인 취소로 인한 포인트 복구");
								$message = "출금 승인이 취소되어 포인트가 복구되었습니다.";
							} else if ($old_status === 'approved' && $new_status === 'rejected') {
								// 승인→거부: 포인트 복구
								insert_point($mb_id, $amount, "출금 거부로 인한 포인트 복구");
								$message = "출금이 거부되어 포인트가 복구되었습니다.";
							} else if ($old_status === 'rejected' && $new_status === 'approved') {
								// 거부→승인: 포인트 차감 (잔액 확인)
								$member_info = get_member($mb_id);
								if ($member_info['mb_point'] >= $amount) {
									insert_point($mb_id, -$amount, "출금 재승인 (#{$request_id})");
									$message = "출금이 승인되어 {$amount}P가 차감되었습니다.";
								} else {
									$message = "포인트가 부족하여 출금을 승인할 수 없습니다.";
									$message_type = 'error';
									break;
								}
							} else {
								$message = "출금 상태가 변경되었습니다.";
							}
						}
                        
                        $message_type = 'success';
                    }
                }
                break;
                
case 'update_bet_amount':
    $bet_id = (int)($_POST['bet_id'] ?? 0);
    $new_amount = (int)($_POST['new_amount'] ?? 0);
    
    if ($bet_id > 0 && $new_amount >= 0) {
        // 기존 베팅 정보 조회
        $bet_info = sql_fetch("SELECT * FROM dice_game_bets WHERE bet_id = {$bet_id}");
        
        if ($bet_info) {
            $mb_id = $bet_info['mb_id'];
            $old_amount = (int)$bet_info['bet_amount'];
            $amount_diff = $new_amount - $old_amount; // 차액 계산
            
            // 회원 포인트 조회
            $member_info = sql_fetch("SELECT mb_point FROM {$g5['member_table']} WHERE mb_id = '{$mb_id}'");
            $current_point = (int)$member_info['mb_point'];
            
            // 포인트 차액 처리
            if ($amount_diff > 0) {
                // 베팅금액 증가 - 추가 포인트 차감
                if ($current_point < $amount_diff) {
                    $message = "회원의 보유 포인트가 부족합니다. (필요: " . number_format($amount_diff) . "P, 보유: " . number_format($current_point) . "P)";
                    $message_type = 'error';
                    break;
                }
            }
            
            // 트랜잭션 시작
            sql_query("START TRANSACTION");
            
            try {
                // 1. 베팅 금액 업데이트
                $bet_update_sql = "UPDATE dice_game_bets SET bet_amount = {$new_amount} WHERE bet_id = {$bet_id}";
                sql_query($bet_update_sql);
                
                // 2. 회원 포인트 업데이트
                $new_member_point = $current_point - $amount_diff;
                $point_update_sql = "UPDATE {$g5['member_table']} SET mb_point = {$new_member_point} WHERE mb_id = '{$mb_id}'";
                sql_query($point_update_sql);
                
                // 3. 포인트 내역 기록
                $now = date('Y-m-d H:i:s');
                if ($amount_diff > 0) {
                    // 추가 차감
                    $point_content = "베팅금액 수정 (추가 차감)";
                    $point_amount = -$amount_diff;
                } else {
                    // 환불
                    $point_content = "베팅금액 수정 (일부 환불)";
                    $point_amount = abs($amount_diff);
                }
                
                $point_log_sql = "
                    INSERT INTO {$g5['point_table']} SET
                        mb_id = '{$mb_id}',
                        po_datetime = '{$now}',
                        po_content = '{$point_content}',
                        po_point = {$point_amount},
                        po_use_point = 0,
                        po_expired = 0,
                        po_expire_date = '9999-12-31',
                        po_mb_point = {$new_member_point},
                        po_rel_table = 'dice_game_bets',
                        po_rel_id = '{$bet_id}',
                        po_rel_action = 'bet_amount_update'
                ";
                sql_query($point_log_sql);
                
                // 4. 당첨금이 있는 경우 재계산 (A/B/C 게임용)
                if ($bet_info['status'] == 'win' && $bet_info['win_amount'] > 0) {
                    // 해당 게임의 배율 조회
                    $game_type = strtolower($bet_info['game_type']);
                    $bet_option = $bet_info['bet_option'];
                    $rate_key = "game_{$game_type}{$bet_option}_rate";
                    
                    $rate_sql = "SELECT config_value FROM dice_game_config WHERE config_key = '{$rate_key}'";
                    $rate_result = sql_fetch($rate_sql);
                    $rate = $rate_result ? floatval($rate_result['config_value']) : 2.0;
                    
                    // 새로운 당첨금 계산
                    $new_win_amount = floor($new_amount * $rate);
                    
                    // 당첨금 업데이트
                    $win_update_sql = "UPDATE dice_game_bets SET win_amount = {$new_win_amount} WHERE bet_id = {$bet_id}";
                    sql_query($win_update_sql);
                    
                    // 당첨금 차액도 포인트에 반영
                    $old_win_amount = (int)$bet_info['win_amount'];
                    $win_diff = $new_win_amount - $old_win_amount;
                    
                    if ($win_diff != 0) {
                        $final_member_point = $new_member_point + $win_diff;
                        sql_query("UPDATE {$g5['member_table']} SET mb_point = {$final_member_point} WHERE mb_id = '{$mb_id}'");
                        
                        // 당첨금 차액 포인트 내역
                        $win_point_sql = "
                            INSERT INTO {$g5['point_table']} SET
                                mb_id = '{$mb_id}',
                                po_datetime = '{$now}',
                                po_content = '베팅금액 수정에 따른 당첨금 조정',
                                po_point = {$win_diff},
                                po_use_point = 0,
                                po_expired = 0,
                                po_expire_date = '9999-12-31',
                                po_mb_point = {$final_member_point},
                                po_rel_table = 'dice_game_bets',
                                po_rel_id = '{$bet_id}',
                                po_rel_action = 'win_amount_update'
                        ";
                        sql_query($win_point_sql);
                    }
                }
                
                // 커밋
                sql_query("COMMIT");
                
                $message = "베팅금액이 " . number_format($old_amount) . "원에서 " . number_format($new_amount) . "원으로 수정되었습니다.";
                if ($amount_diff > 0) {
                    $message .= " (추가 차감: " . number_format($amount_diff) . "P)";
                } else if ($amount_diff < 0) {
                    $message .= " (환불: " . number_format(abs($amount_diff)) . "P)";
                }
                $message_type = 'success';
                
            } catch (Exception $e) {
                sql_query("ROLLBACK");
                $message = "베팅금액 수정 중 오류가 발생했습니다: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
    break;            case 'update_payment_config':
                $configs = [
                    'system_status' => $_POST['system_status'] ?? '1',
                    'min_charge_amount' => (int)($_POST['min_charge_amount'] ?? 10000),
                    'max_charge_amount' => (int)($_POST['max_charge_amount'] ?? 1000000),
                    'min_withdraw_amount' => (int)($_POST['min_withdraw_amount'] ?? 10000),
                    'max_withdraw_amount' => (int)($_POST['max_withdraw_amount'] ?? 1000000),
                    'charge_fee_rate' => (float)($_POST['charge_fee_rate'] ?? 0),
                    'withdraw_fee_rate' => (float)($_POST['withdraw_fee_rate'] ?? 0),
                    'withdraw_fee_fixed' => (int)($_POST['withdraw_fee_fixed'] ?? 0),
                    'auto_approval_limit' => (int)($_POST['auto_approval_limit'] ?? 100000),
                    'business_hours_start' => $_POST['business_hours_start'] ?? '09:00',
                    'business_hours_end' => $_POST['business_hours_end'] ?? '18:00',
                    'weekend_processing' => $_POST['weekend_processing'] ?? '0'
                ];
                
                foreach ($configs as $key => $value) {
                    updatePaymentConfigValue($key, $value); // payment_config 테이블 사용
                }
                
                $message = "충전/출금 설정이 업데이트되었습니다.";
                $message_type = 'success';
                break;
                
            case 'update_signup_code':
                $signup_code = $_POST['signup_code'] ?? '';
                $signup_enabled = isset($_POST['signup_enabled']) ? '1' : '0';
                $welcome_point = (int)($_POST['welcome_point'] ?? 10000);
                
                if (!empty($signup_code)) {
                    updateConfigValue('signup_code', $signup_code);
                    updateConfigValue('signup_enabled', $signup_enabled);
                    updateConfigValue('signup_welcome_point', $welcome_point);
                    
                    $message = "가입코드 설정이 업데이트되었습니다.";
                    $message_type = 'success';
                }
                break;
                
            case 'update_admin_account':
                $bank_name = $_POST['bank_name'] ?? '';
                $account_number = $_POST['account_number'] ?? '';
                $account_holder = $_POST['account_holder'] ?? '';
                
                if (!empty($bank_name) && !empty($account_number) && !empty($account_holder)) {
                    $escaped_bank_name = sql_real_escape_string($bank_name);
                    $escaped_account_number = sql_real_escape_string($account_number);
                    $escaped_account_holder = sql_real_escape_string($account_holder);
                    $now = date('Y-m-d H:i:s');
                    
                    // 기존 활성 계좌를 비활성화
                    $deactivate_sql = "UPDATE payment_admin_accounts SET is_active = 0";
                    sql_query($deactivate_sql);
                    
                    // 새로운 계좌 정보 추가 또는 업데이트
                    $check_sql = "SELECT account_id FROM payment_admin_accounts WHERE account_number = '{$escaped_account_number}'";
                    $existing = sql_fetch($check_sql);
                    
                    if ($existing) {
                        // 기존 계좌 업데이트
                        $update_sql = "UPDATE payment_admin_accounts SET 
                                          bank_name = '{$escaped_bank_name}',
                                          account_holder = '{$escaped_account_holder}',
                                          is_active = 1,
                                          display_order = 1
                                      WHERE account_id = {$existing['account_id']}";
                        sql_query($update_sql);
                    } else {
                        // 새 계좌 추가
                        $insert_sql = "INSERT INTO payment_admin_accounts 
                                      (bank_name, account_number, account_holder, is_active, display_order, created_at) 
                                      VALUES 
                                      ('{$escaped_bank_name}', '{$escaped_account_number}', '{$escaped_account_holder}', 1, 1, '{$now}')";
                        sql_query($insert_sql);
                    }
                    
                    $message = "관리자 계좌 정보가 업데이트되었습니다.";
                    $message_type = 'success';
                }
                break;
        }
    } catch (Exception $e) {
        $message = "오류가 발생했습니다: " . $e->getMessage();
        $message_type = 'error';
    }
}

// ===================================
// 데이터 조회
// ===================================

/* 전체 통계 */
$total_members = sql_fetch("SELECT COUNT(*) as cnt FROM {$g5['member_table']} WHERE mb_leave_date = ''")['cnt'] ?? 0;
$total_bets = sql_fetch("SELECT COUNT(*) as cnt FROM dice_game_bets")['cnt'] ?? 0;
$total_rounds = sql_fetch("SELECT COUNT(*) as cnt FROM dice_game_rounds")['cnt'] ?? 0;
$pending_charges = sql_fetch("SELECT COUNT(*) as cnt FROM payment_requests WHERE request_type = 'charge' AND status = 'pending'")['cnt'] ?? 0;
$pending_withdrawals = sql_fetch("SELECT COUNT(*) as cnt FROM payment_requests WHERE request_type = 'withdraw' AND status = 'pending'")['cnt'] ?? 0;

/* 전체 회원 목록 (mb_1: 입금은행, mb_2: 계좌번호 포함) */
$all_members = array();
$sql = "SELECT mb_id, mb_name, mb_level, mb_point, mb_datetime, mb_1, mb_2 FROM {$g5['member_table']} WHERE mb_leave_date = '' ORDER BY mb_datetime DESC";
$result = sql_query($sql);
while ($row = sql_fetch_array($result)) {
    $all_members[] = $row;
}

/* 전체 충전 요청 (입금관리용) */
$all_charge_requests = array();
$sql = "SELECT * FROM payment_requests WHERE request_type = 'charge' ORDER BY created_at DESC LIMIT 100";
$result = sql_query($sql);
while ($row = sql_fetch_array($result)) {
    $all_charge_requests[] = $row;
}

/* 전체 출금 요청 (출금관리용) */
$all_withdrawal_requests = array();
$sql = "SELECT * FROM payment_requests WHERE request_type = 'withdraw' ORDER BY created_at DESC LIMIT 100";
$result = sql_query($sql);
while ($row = sql_fetch_array($result)) {
    $all_withdrawal_requests[] = $row;
}

// "/* 최근 베팅 내역 */" 부분을 찾아서 다음으로 교체:

/* 최근 베팅 내역 */
$recent_bets = array();
$sql = "SELECT b.*, r.round_number, r.game_a_result, r.game_b_result, r.game_c_result, r.status as round_status 
        FROM dice_game_bets b 
        LEFT JOIN dice_game_rounds r ON b.round_id = r.round_id 
        ORDER BY b.created_at DESC LIMIT 20";
$result = sql_query($sql);
while ($row = sql_fetch_array($result)) {
    $recent_bets[] = $row;
}

/* 현재 진행중인 회차 정보 */
$current_round = sql_fetch("SELECT * FROM dice_game_rounds WHERE status IN ('betting', 'waiting') ORDER BY round_number DESC LIMIT 1");
$recent_rounds = array();
$sql = "SELECT * FROM dice_game_rounds WHERE status = 'completed' ORDER BY round_number DESC LIMIT 10";
$result = sql_query($sql);
while ($row = sql_fetch_array($result)) {
    $recent_rounds[] = $row;
}

/* 미리 설정된 회차들 조회 */
$scheduled_rounds = array();
$scheduled_sql = "SELECT * FROM dice_game_rounds WHERE status = 'scheduled' ORDER BY round_number ASC LIMIT 30";
$result = sql_query($scheduled_sql);
while ($row = sql_fetch_array($result)) {
    $scheduled_rounds[] = $row;
}

/* 진행중인 회차 정보 */
$active_round = sql_fetch("SELECT * FROM dice_game_rounds WHERE status IN ('betting', 'waiting') ORDER BY round_number DESC LIMIT 1");

/* 최근 완료 회차 통계 */
$recent_stats = sql_fetch("
    SELECT 
        COUNT(*) as total_rounds,
        SUM(CASE WHEN is_high = 1 THEN 1 ELSE 0 END) as high_count,
        SUM(CASE WHEN is_odd = 1 THEN 1 ELSE 0 END) as odd_count,
        SUM(total_bet_amount) as total_bet_sum,
        SUM(total_players) as total_players_sum
    FROM dice_game_rounds 
    WHERE status = 'completed' 
    AND result_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
");

/* 현재 설정값들 */
$current_settings = array(
    // 게임 설정 (dice_game_config 테이블)
    'game_status' => getConfigValue('game_status', '1'),
    'min_bet' => getConfigValue('min_bet', '1000'),
    'max_bet' => getConfigValue('max_bet', '100000'),
    'betting_time' => getConfigValue('betting_time', '60'),
    'result_time' => getConfigValue('result_time', '30'),
    'game_interval' => getConfigValue('game_interval', '120'),
    'win_rate_high_low' => getConfigValue('win_rate_high_low', '1.95'),
    'win_rate_odd_even' => getConfigValue('win_rate_odd_even', '1.95'),
    
    // 충전/출금 설정 (payment_config 테이블)
    'system_status' => getPaymentConfigValue('system_status', '1'),
    'min_charge_amount' => getPaymentConfigValue('min_charge_amount', '10000'),
    'max_charge_amount' => getPaymentConfigValue('max_charge_amount', '1000000'),
    'min_withdraw_amount' => getPaymentConfigValue('min_withdraw_amount', '10000'),
    'max_withdraw_amount' => getPaymentConfigValue('max_withdraw_amount', '1000000'),
    'charge_fee_rate' => getPaymentConfigValue('charge_fee_rate', '0'),
    'withdraw_fee_rate' => getPaymentConfigValue('withdraw_fee_rate', '0'),
    'withdraw_fee_fixed' => getPaymentConfigValue('withdraw_fee_fixed', '0'),
    'auto_approval_limit' => getPaymentConfigValue('auto_approval_limit', '100000'),
    'business_hours_start' => getPaymentConfigValue('business_hours_start', '09:00'),
    'business_hours_end' => getPaymentConfigValue('business_hours_end', '18:00'),
    'weekend_processing' => getPaymentConfigValue('weekend_processing', '0'),
    
    // 가입 설정 (dice_game_config 테이블)
    'signup_code' => getConfigValue('signup_code', ''),
    'signup_enabled' => getConfigValue('signup_enabled', '1'),
    'signup_welcome_point' => getConfigValue('signup_welcome_point', '10000'),
    'daily_signup_limit' => getConfigValue('daily_signup_limit', '100'),
);

/* 관리자 계좌 정보 조회 (payment_admin_accounts 테이블에서) */
$admin_account = sql_fetch("SELECT * FROM payment_admin_accounts WHERE is_active = 1 ORDER BY display_order ASC LIMIT 1");
$admin_bank_name = $admin_account ? $admin_account['bank_name'] : '';
$admin_account_number = $admin_account ? $admin_account['account_number'] : '';
$admin_account_holder = $admin_account ? $admin_account['account_holder'] : '';

// 현재 설정값에 admin 계좌 정보도 추가 (계좌변경 탭에서 사용)
$current_settings['admin_bank_name'] = $admin_bank_name;
$current_settings['admin_account_number'] = $admin_account_number;
$current_settings['admin_account_holder'] = $admin_account_holder;
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>통합 관리자 페이지 - 주사위 게임</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- DataTables CSS -->
    <link href="https://cdn.datatables.net/1.13.0/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <style>
        /* ===================================
         * 관리자 페이지 전체 스타일
         * =================================== */
        
        body {
            background-color: #f8f9fa !important;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
        }

        /* 헤더 영역 */
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            color: white !important;
            padding: 2rem 0 !important;
            margin-bottom: 2rem !important;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1) !important;
        }

        .admin-title {
            font-size: 2.2rem !important;
            font-weight: 700 !important;
            margin: 0 !important;
        }

        .admin-subtitle {
            opacity: 0.9 !important;
            margin-top: 0.5rem !important;
        }

        /* ===================================
         * 통계 카드 스타일
         * =================================== */
        
        .stats-grid {
            margin-bottom: 2rem !important;
        }

        .stats-card {
            background: white !important;
            border-radius: 12px !important;
            padding: 1.5rem !important;
            margin-bottom: 1rem !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08) !important;
            border: none !important;
            transition: transform 0.2s ease !important;
        }

        .stats-card:hover {
            transform: translateY(-2px) !important;
        }

        .stats-icon {
            width: 60px !important;
            height: 60px !important;
            border-radius: 12px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-size: 1.5rem !important;
            margin-bottom: 1rem !important;
        }

        .stats-number {
            font-size: 2rem !important;
            font-weight: 700 !important;
            margin-bottom: 0.25rem !important;
        }

        .stats-label {
            color: #6c757d !important;
            font-size: 0.9rem !important;
            font-weight: 500 !important;
        }

        .stats-members { background: linear-gradient(135deg, #17a2b8, #20c997) !important; }
        .stats-bets { background: linear-gradient(135deg, #fd7e14, #ffc107) !important; }
        .stats-rounds { background: linear-gradient(135deg, #6f42c1, #e83e8c) !important; }
        .stats-charges { background: linear-gradient(135deg, #28a745, #20c997) !important; }
        .stats-withdrawals { background: linear-gradient(135deg, #dc3545, #fd7e14) !important; }

        /* ===================================
         * 탭 네비게이션 스타일
         * =================================== */
        
        .admin-tabs {
            background: white !important;
            border-radius: 12px !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08) !important;
            margin-bottom: 2rem !important;
            overflow: hidden !important;
        }

        .nav-tabs {
            border-bottom: 1px solid #dee2e6 !important;
            margin: 0 !important;
        }

        .nav-tabs .nav-link {
            border: none !important;
            border-radius: 0 !important;
            padding: 1.25rem 1.5rem !important;
            font-weight: 500 !important;
            color: #495057 !important;
            background: transparent !important;
            transition: all 0.3s ease !important;
            position: relative !important;
        }

        .nav-tabs .nav-link:hover {
            color: #667eea !important;
            background: rgba(102, 126, 234, 0.05) !important;
        }

        .nav-tabs .nav-link.active {
            color: #667eea !important;
            background: rgba(102, 126, 234, 0.1) !important;
            border-bottom: 3px solid #667eea !important;
        }

        .nav-tabs .nav-link i {
            margin-right: 0.5rem !important;
        }

        /* ===================================
         * 탭 콘텐츠 스타일
         * =================================== */
        
        .tab-content {
            background: white !important;
            border-radius: 0 0 12px 12px !important;
            padding: 2rem !important;
        }

        /* 섹션 제목 */
        .section-title {
            font-size: 1.4rem !important;
            font-weight: 600 !important;
            color: #495057 !important;
            margin-bottom: 1.5rem !important;
            padding-bottom: 0.75rem !important;
            border-bottom: 2px solid #e9ecef !important;
        }

        /* ===================================
         * 테이블 스타일
         * =================================== */
        
        .table-container {
            background: white !important;
            border-radius: 8px !important;
            overflow: hidden !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06) !important;
        }

        .table {
            margin-bottom: 0 !important;
        }

        .table thead th {
            background: #f8f9fa !important;
            font-weight: 600 !important;
            color: #495057 !important;
            border-bottom: 2px solid #dee2e6 !important;
            padding: 1rem 0.75rem !important;
        }

        .table tbody td {
            padding: 0.875rem 0.75rem !important;
            vertical-align: middle !important;
        }

        /* ===================================
         * 폼 스타일
         * =================================== */
        
        .form-section {
            background: #f8f9fa !important;
            border-radius: 8px !important;
            padding: 1.5rem !important;
            margin-bottom: 1.5rem !important;
        }

        .form-label {
            font-weight: 600 !important;
            color: #495057 !important;
            margin-bottom: 0.5rem !important;
        }

        .form-control, .form-select {
            border-radius: 6px !important;
            border: 1px solid #ced4da !important;
            padding: 0.75rem !important;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea !important;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25) !important;
        }

        /* ===================================
         * 버튼 스타일
         * =================================== */
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            border: none !important;
            border-radius: 6px !important;
            font-weight: 600 !important;
            padding: 0.75rem 1.5rem !important;
        }

        .btn-primary:hover {
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4) !important;
        }

        .btn-sm {
            padding: 0.375rem 0.75rem !important;
            font-size: 0.875rem !important;
        }

        /* 상태 배지 */
        .badge {
            font-size: 0.75rem !important;
            padding: 0.375rem 0.75rem !important;
            border-radius: 6px !important;
        }

        /* ===================================
         * 알림 메시지 스타일
         * =================================== */
        
        .alert {
            border-radius: 8px !important;
            border: none !important;
            font-weight: 500 !important;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1) !important;
            color: #155724 !important;
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.1) !important;
            color: #721c24 !important;
        }

        /* ===================================
         * 반응형 스타일
         * =================================== */
        
        @media (max-width: 768px) {
            .admin-header {
                padding: 1.5rem 0 !important;
            }
            
            .admin-title {
                font-size: 1.8rem !important;
            }
            
            .nav-tabs .nav-link {
                padding: 1rem !important;
                font-size: 0.9rem !important;
            }
            
            .tab-content {
                padding: 1.5rem 1rem !important;
            }
            
            .stats-card {
                margin-bottom: 1rem !important;
            }
        }

        /* ===================================
         * DataTables 커스텀 스타일
         * =================================== */
        
        .dataTables_wrapper .dataTables_length select,
        .dataTables_wrapper .dataTables_filter input {
            border-radius: 6px !important;
            border: 1px solid #ced4da !important;
        }

        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #667eea !important;
            border-color: #667eea !important;
        }
    </style>
</head>

<body>
    <!-- 헤더 -->
    <div class="admin-header">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="admin-title">
                        <i class="bi bi-speedometer2 me-3"></i>통합 관리자
                    </h1>
                    <p class="admin-subtitle mb-0">주사위 게임 전체 시스템 관리</p>
                </div>
                <div>
                    <a href="./index.php" class="btn btn-outline-light">
                        <i class="bi bi-house-fill me-2"></i>게임 홈
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <!-- 알림 메시지 -->
        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
            <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
            <?php echo htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- 통계 대시보드 -->
        <div class="stats-grid">
            <div class="row">
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stats-card">
                        <div class="stats-icon stats-members text-white">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div class="stats-number"><?php echo number_format($total_members) ?></div>
                        <div class="stats-label">전체 회원</div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stats-card">
                        <div class="stats-icon stats-bets text-white">
                            <i class="bi bi-dice-6-fill"></i>
                        </div>
                        <div class="stats-number"><?php echo number_format($total_bets) ?></div>
                        <div class="stats-label">총 베팅</div>
                    </div>
                </div>
                <div class="col-lg-2 col-md-4 col-sm-6">
                    <div class="stats-card">
                        <div class="stats-icon stats-rounds text-white">
                            <i class="bi bi-clock-history"></i>
                        </div>
                        <div class="stats-number"><?php echo number_format($total_rounds) ?></div>
                        <div class="stats-label">총 회차</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="stats-card">
                        <div class="stats-icon stats-charges text-white">
                            <i class="bi bi-arrow-down-circle-fill"></i>
                        </div>
                        <div class="stats-number"><?php echo number_format($pending_charges) ?></div>
                        <div class="stats-label">대기 충전</div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 col-sm-6">
                    <div class="stats-card">
                        <div class="stats-icon stats-withdrawals text-white">
                            <i class="bi bi-arrow-up-circle-fill"></i>
                        </div>
                        <div class="stats-number"><?php echo number_format($pending_withdrawals) ?></div>
                        <div class="stats-label">대기 출금</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 탭 네비게이션 -->
<!-- 탭 네비게이션 -->
<div class="admin-tabs">
    <ul class="nav nav-tabs" id="adminTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="members-tab" data-bs-toggle="tab" data-bs-target="#members" type="button" role="tab">
                <i class="bi bi-people-fill"></i>회원관리
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="charges-tab" data-bs-toggle="tab" data-bs-target="#charges" type="button" role="tab">
                <i class="bi bi-arrow-down-circle"></i>입금관리
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="withdrawals-tab" data-bs-toggle="tab" data-bs-target="#withdrawals" type="button" role="tab">
                <i class="bi bi-arrow-up-circle"></i>출금관리
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="bets-tab" data-bs-toggle="tab" data-bs-target="#bets" type="button" role="tab">
                <i class="bi bi-dice-5"></i>베팅내역
            </button>
        </li>
        <!-- 여기에 회차관리 탭 추가 -->
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="rounds-tab" data-bs-toggle="tab" data-bs-target="#rounds" type="button" role="tab">
                <i class="bi bi-calendar-check"></i>회차관리
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="payment-config-tab" data-bs-toggle="tab" data-bs-target="#payment-config" type="button" role="tab">
                <i class="bi bi-gear-wide-connected"></i>충전/출금 설정
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="game-config-tab" data-bs-toggle="tab" data-bs-target="#game-config" type="button" role="tab">
                <i class="bi bi-controller"></i>게임 설정
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="account-tab" data-bs-toggle="tab" data-bs-target="#account" type="button" role="tab">
                <i class="bi bi-bank"></i>계좌변경
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="signup-tab" data-bs-toggle="tab" data-bs-target="#signup" type="button" role="tab">
                <i class="bi bi-key"></i>가입코드
            </button>
        </li>
    </ul>
            <!-- 탭 콘텐츠 -->
            <div class="tab-content" id="adminTabContent">
                
                <!-- 회원관리 탭 -->
                <div class="tab-pane fade show active" id="members" role="tabpanel">
                    <h3 class="section-title">회원관리</h3>
                    
                    <!-- 회원 목록 -->
                    <div class="table-container">
                        <table class="table" id="membersTable">
                            <thead>
                                <tr>
                                    <th>아이디</th>
                                    <th>이름</th>
                                    <th>레벨</th>
                                    <th>보유포인트</th>
                                    <th>입금은행</th>
                                    <th>계좌번호</th>
                                    <th>가입일</th>
                                    <th>관리</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_members as $member): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($member['mb_id']) ?></td>
                                    <td><?php echo htmlspecialchars($member['mb_name']) ?></td>
                                    <td>
                                        <?php 
                                        $level_text = $member['mb_level'] == 10 ? '최고관리자' : ($member['mb_level'] == 1 ? '관리자' : ($member['mb_level'] == 2 ? '일반회원' : '차단'));
                                        $level_class = $member['mb_level'] >= 10 ? 'bg-danger' : ($member['mb_level'] == 1 ? 'bg-warning' : ($member['mb_level'] == 2 ? 'bg-success' : 'bg-secondary'));
                                        ?>
                                        <span class="badge <?php echo $level_class ?>"><?php echo $level_text ?></span>
                                    </td>
                                    <td class="text-end"><?php echo number_format($member['mb_point']) ?>P</td>
                                    <td><?php echo htmlspecialchars($member['mb_1'] ?: '-') ?></td>
                                    <td><?php echo htmlspecialchars($member['mb_2'] ?: '-') ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($member['mb_datetime'])) ?></td>
                                    <td>
                                        <button class="btn btn-outline-primary btn-sm" onclick="editMember('<?php echo $member['mb_id'] ?>')">
                                            <i class="bi bi-pencil-square"></i> 수정
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 회원 수정 모달 -->
                <div class="modal fade" id="memberEditModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="bi bi-person-gear me-2"></i>회원 정보 수정
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <form id="memberEditForm" method="post">
                                    <input type="hidden" name="action" value="update_member_info">
                                    <input type="hidden" name="mb_id" id="edit_mb_id">
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">아이디</label>
                                            <input type="text" class="form-control" id="edit_mb_id_display" disabled>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">이름</label>
                                            <input type="text" class="form-control" name="mb_name" id="edit_mb_name" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">비밀번호 <small class="text-muted">(변경시만 입력)</small></label>
                                            <input type="password" class="form-control" name="mb_password" id="edit_mb_password" placeholder="변경하지 않으려면 비워두세요">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">보유포인트</label>
                                            <input type="number" class="form-control" name="mb_point" id="edit_mb_point" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">입금은행</label>
                                            <select class="form-select" name="mb_1" id="edit_mb_1">
                                                <option value="">선택안함</option>
                                                <option value="국민은행">국민은행</option>
                                                <option value="신한은행">신한은행</option>
                                                <option value="우리은행">우리은행</option>
                                                <option value="하나은행">하나은행</option>
                                                <option value="기업은행">기업은행</option>
                                                <option value="농협">농협</option>
                                                <option value="SC제일은행">SC제일은행</option>
                                                <option value="씨티은행">씨티은행</option>
                                                <option value="대구은행">대구은행</option>
                                                <option value="부산은행">부산은행</option>
                                                <option value="경남은행">경남은행</option>
                                                <option value="광주은행">광주은행</option>
                                                <option value="전북은행">전북은행</option>
                                                <option value="제주은행">제주은행</option>
                                                <option value="카카오뱅크">카카오뱅크</option>
                                                <option value="케이뱅크">케이뱅크</option>
                                                <option value="토스뱅크">토스뱅크</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">계좌번호</label>
                                            <input type="text" class="form-control" name="mb_2" id="edit_mb_2" placeholder="계좌번호 입력">
                                        </div>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="bi bi-x-circle me-1"></i>취소
                                </button>
                                <button type="button" class="btn btn-primary" onclick="saveMemberInfo()">
                                    <i class="bi bi-check-circle me-1"></i>저장
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 입금관리 탭 -->
                <div class="tab-pane fade" id="charges" role="tabpanel">
                    <h3 class="section-title">입금관리</h3>
                    
                    <div class="table-container">
                        <table class="table" id="chargesTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>회원</th>
                                    <th>금액</th>
                                    <th>입금자명</th>
                                    <th>신청일</th>
                                    <th>상태</th>
                                    <th>관리</th>
                                </tr>
                            </thead>
								<tbody>
									<?php foreach ($all_charge_requests as $request): ?>
									<tr>
										<td><?php echo $request['request_id'] ?></td>
										<td><?php echo htmlspecialchars($request['mb_id']) ?></td>
										<td><?php echo number_format($request['amount']) ?>원</td>
										<td><?php echo htmlspecialchars($request['deposit_name'] ?? '') ?></td>
										<td><?php echo date('Y-m-d H:i', strtotime($request['created_at'])) ?></td>
										<td>
											<?php 
											$status_text = $request['status'] === 'pending' ? '대기' : ($request['status'] === 'approved' ? '승인' : '거부');
											$status_class = $request['status'] === 'pending' ? 'bg-warning' : ($request['status'] === 'approved' ? 'bg-success' : 'bg-danger');
											?>
											<span class="badge <?php echo $status_class ?>"><?php echo $status_text ?></span>
										</td>
										<td>
											<form method="post" class="d-inline">
												<input type="hidden" name="action" value="update_payment_status">
												<input type="hidden" name="request_id" value="<?php echo $request['request_id'] ?>">
												
												<?php if ($request['status'] === 'pending'): ?>
													<button type="submit" name="new_status" value="approved" class="btn btn-success btn-sm me-1">
														<i class="bi bi-check"></i>승인
													</button>
													<button type="submit" name="new_status" value="rejected" class="btn btn-danger btn-sm">
														<i class="bi bi-x"></i>거부
													</button>
												<?php elseif ($request['status'] === 'approved'): ?>
													<button type="submit" name="new_status" value="pending" class="btn btn-warning btn-sm me-1">
														<i class="bi bi-arrow-clockwise"></i>대기
													</button>
													<button type="submit" name="new_status" value="rejected" class="btn btn-danger btn-sm">
														<i class="bi bi-x"></i>거부
													</button>
													<br>
													<small class="text-success">
														승인: <?php echo date('m/d H:i', strtotime($request['processed_at'] ?? $request['updated_at'])) ?>
													</small>
												<?php else: // rejected ?>
													<button type="submit" name="new_status" value="pending" class="btn btn-warning btn-sm me-1">
														<i class="bi bi-arrow-clockwise"></i>대기
													</button>
													<button type="submit" name="new_status" value="approved" class="btn btn-success btn-sm">
														<i class="bi bi-check"></i>승인
													</button>
													<br>
													<small class="text-danger">
														거부: <?php echo date('m/d H:i', strtotime($request['processed_at'] ?? $request['updated_at'])) ?>
													</small>
												<?php endif; ?>
											</form>
										</td>
									</tr>
									<?php endforeach; ?>
								</tbody>
						</table>
                    </div>
                </div>

<!-- 출금관리 탭 -->
<!-- 출금관리 탭 수정 -->
<div class="tab-pane fade" id="withdrawals" role="tabpanel">
    <h3 class="section-title">출금관리</h3>
    
    <div class="table-container">
        <table class="table" id="withdrawalsTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>회원</th>
                    <th>금액</th>
                    <th>은행</th>
                    <th>계좌번호</th>
                    <th>예금주</th>
                    <th>신청일</th>
                    <th>상태</th>
                    <th>관리</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_withdrawal_requests as $request): ?>
                <tr>
                    <td><?php echo $request['request_id'] ?></td>
                    <td><?php echo htmlspecialchars($request['mb_id']) ?></td>
                    <td><?php echo number_format($request['amount']) ?>원</td>
                    <td><?php echo htmlspecialchars($request['bank_name'] ?? '') ?></td>
                    <td><?php echo htmlspecialchars($request['account_number'] ?? '') ?></td>
                    <td><?php echo htmlspecialchars($request['account_holder'] ?? '') ?></td>
                    <td><?php echo date('Y-m-d H:i', strtotime($request['created_at'])) ?></td>
                    <td>
                        <?php 
                        $status_text = $request['status'] === 'pending' ? '대기' : ($request['status'] === 'approved' ? '승인' : '거부');
                        $status_class = $request['status'] === 'pending' ? 'bg-warning' : ($request['status'] === 'approved' ? 'bg-success' : 'bg-danger');
                        ?>
                        <span class="badge <?php echo $status_class ?>"><?php echo $status_text ?></span>
                    </td>
                    <td>
                        <div class="d-flex gap-1">
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="update_payment_status">
                                <input type="hidden" name="request_id" value="<?php echo $request['request_id'] ?>">
                                
                                <?php if ($request['status'] === 'pending'): ?>
                                    <button type="submit" name="new_status" value="approved" class="btn btn-success btn-sm">
                                        <i class="bi bi-check"></i>
                                    </button>
                                    <button type="submit" name="new_status" value="rejected" class="btn btn-danger btn-sm">
                                        <i class="bi bi-x"></i>
                                    </button>
                                <?php elseif ($request['status'] === 'approved'): ?>
                                    <button type="submit" name="new_status" value="pending" class="btn btn-warning btn-sm">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                    <button type="submit" name="new_status" value="rejected" class="btn btn-danger btn-sm">
                                        <i class="bi bi-x"></i>
                                    </button>
                                <?php else: // rejected ?>
                                    <button type="submit" name="new_status" value="pending" class="btn btn-warning btn-sm">
                                        <i class="bi bi-arrow-clockwise"></i>
                                    </button>
                                    <button type="submit" name="new_status" value="approved" class="btn btn-success btn-sm">
                                        <i class="bi bi-check"></i>
                                    </button>
                                <?php endif; ?>
                            </form>
                            
                            <!-- 계좌 수정 버튼 추가 -->
                            <button type="button" class="btn btn-outline-primary btn-sm" 
                                    onclick="editWithdrawAccount(<?php echo $request['request_id'] ?>, 
                                             '<?php echo htmlspecialchars($request['bank_name'] ?? '', ENT_QUOTES) ?>', 
                                             '<?php echo htmlspecialchars($request['account_number'] ?? '', ENT_QUOTES) ?>', 
                                             '<?php echo htmlspecialchars($request['account_holder'] ?? '', ENT_QUOTES) ?>')">
                                <i class="bi bi-pencil"></i>
                            </button>
                        </div>
                        
                        <?php if ($request['status'] === 'approved'): ?>
                            <small class="text-success d-block mt-1">
                                승인: <?php echo date('m/d H:i', strtotime($request['processed_at'] ?? $request['updated_at'])) ?>
                            </small>
                        <?php elseif ($request['status'] === 'rejected'): ?>
                            <small class="text-danger d-block mt-1">
                                거부: <?php echo date('m/d H:i', strtotime($request['processed_at'] ?? $request['updated_at'])) ?>
                            </small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 출금 계좌 수정 모달 -->
<div class="modal fade" id="withdrawAccountEditModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-bank me-2"></i>출금 계좌 정보 수정
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="withdrawAccountEditForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_withdraw_account">
                    <input type="hidden" name="request_id" id="edit_withdraw_request_id">
                    
                    <div class="mb-3">
                        <label class="form-label">은행명</label>
                        <select class="form-select" name="bank_name" id="edit_withdraw_bank_name" required>
                            <option value="">은행 선택</option>
                            <option value="국민은행">국민은행</option>
                            <option value="신한은행">신한은행</option>
                            <option value="우리은행">우리은행</option>
                            <option value="하나은행">하나은행</option>
                            <option value="기업은행">기업은행</option>
                            <option value="농협">농협</option>
                            <option value="SC제일은행">SC제일은행</option>
                            <option value="씨티은행">씨티은행</option>
                            <option value="대구은행">대구은행</option>
                            <option value="부산은행">부산은행</option>
                            <option value="경남은행">경남은행</option>
                            <option value="광주은행">광주은행</option>
                            <option value="전북은행">전북은행</option>
                            <option value="제주은행">제주은행</option>
                            <option value="새마을금고">새마을금고</option>
                            <option value="신협">신협</option>
                            <option value="카카오뱅크">카카오뱅크</option>
                            <option value="케이뱅크">케이뱅크</option>
                            <option value="토스뱅크">토스뱅크</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">계좌번호</label>
                        <input type="text" class="form-control" name="account_number" 
                               id="edit_withdraw_account_number" placeholder="계좌번호 입력" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">예금주명</label>
                        <input type="text" class="form-control" name="account_holder" 
                               id="edit_withdraw_account_holder" placeholder="예금주명 입력" required>
                    </div>
                    
                    <div class="alert alert-warning">
                        <small>
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            계좌 정보 수정 시 신중하게 확인해주세요. 잘못된 계좌로 송금될 수 있습니다.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>취소
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>수정
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// 출금 계좌 수정 모달
function editWithdrawAccount(requestId, bankName, accountNumber, accountHolder) {
    document.getElementById('edit_withdraw_request_id').value = requestId;
    document.getElementById('edit_withdraw_bank_name').value = bankName;
    document.getElementById('edit_withdraw_account_number').value = accountNumber;
    document.getElementById('edit_withdraw_account_holder').value = accountHolder;
    
    const modal = new bootstrap.Modal(document.getElementById('withdrawAccountEditModal'));
    modal.show();
}

// 폼 제출 전 확인
document.getElementById('withdrawAccountEditForm').addEventListener('submit', function(e) {
    const bankName = document.getElementById('edit_withdraw_bank_name').value;
    const accountNumber = document.getElementById('edit_withdraw_account_number').value;
    const accountHolder = document.getElementById('edit_withdraw_account_holder').value;
    
    if (!confirm(`출금 계좌 정보를 다음과 같이 수정하시겠습니까?\n\n은행: ${bankName}\n계좌번호: ${accountNumber}\n예금주: ${accountHolder}`)) {
        e.preventDefault();
    }
});
</script>

<!-- 베팅내역 탭 -->
<div class="tab-pane fade" id="bets" role="tabpanel">
    <h3 class="section-title">베팅내역</h3>
    
    <div class="table-container">
        <table class="table" id="betsTable">
            <thead>
                <tr>
                    <th>회차</th>
                    <th>회원</th>
                    <th>게임</th>
                    <th>선택</th>
                    <th>베팅금액</th>
                    <th>게임결과</th>
                    <th>당첨금</th>
                    <th>상태</th>
                    <th>베팅시간</th>
                    <th>관리</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_bets as $bet): ?>
                <tr>
                    <td><?php echo $bet['round_number'] ?></td>
                    <td><?php echo htmlspecialchars($bet['mb_id']) ?></td>
                    <td>
                        <?php 
                        $game_color = $bet['game_type'] == 'A' ? 'primary' : ($bet['game_type'] == 'B' ? 'success' : 'warning');
                        ?>
                        <span class="badge bg-<?php echo $game_color ?>"><?php echo $bet['game_type'] ?>게임</span>
                    </td>
                    <td>
                        <span class="badge bg-secondary"><?php echo $bet['game_type'] . $bet['bet_option'] ?></span>
                    </td>
                    <td class="text-end"><?php echo number_format($bet['bet_amount']) ?>원</td>
                    <td>
                        <?php if ($bet['round_status'] == 'completed' && $bet['game_a_result']): ?>
                            <?php 
                            $game_result = '';
                            switch($bet['game_type']) {
                                case 'A': $game_result = $bet['game_a_result']; break;
                                case 'B': $game_result = $bet['game_b_result']; break;
                                case 'C': $game_result = $bet['game_c_result']; break;
                            }
                            ?>
                            <span class="badge bg-info"><?php echo $bet['game_type'] . $game_result ?></span>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($bet['win_amount'] > 0): ?>
                        <span class="text-success"><?php echo number_format($bet['win_amount']) ?>원</span>
                        <?php else: ?>
                        <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php 
                        $status_text = '';
                        $status_class = '';
                        switch($bet['status']) {
                            case 'pending':
                                $status_text = '대기';
                                $status_class = 'bg-warning';
                                break;
                            case 'win':
                                $status_text = '당첨';
                                $status_class = 'bg-success';
                                break;
                            case 'lose':
                                $status_text = '실패';
                                $status_class = 'bg-danger';
                                break;
                            case 'cancelled':
                                $status_text = '취소';
                                $status_class = 'bg-secondary';
                                break;
                        }
                        ?>
                        <span class="badge <?php echo $status_class ?>"><?php echo $status_text ?></span>
                    </td>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($bet['created_at'])) ?></td>
                    <td>
                        <button class="btn btn-outline-primary btn-sm" 
                                onclick="editBetAmount(<?php echo $bet['bet_id'] ?>, <?php echo $bet['bet_amount'] ?>, '<?php echo $bet['mb_id'] ?>')">
                            <i class="bi bi-pencil"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- 베팅금액 수정 모달 -->
<div class="modal fade" id="betEditModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">베팅금액 수정</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="betEditForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_bet_amount">
                    <input type="hidden" name="bet_id" id="edit_bet_id">
                    
                    <div class="mb-3">
                        <label class="form-label">회원 ID</label>
                        <input type="text" class="form-control" id="edit_bet_mb_id" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">현재 베팅금액</label>
                        <input type="text" class="form-control" id="edit_bet_current" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">새로운 베팅금액</label>
                        <input type="number" class="form-control" name="new_amount" id="edit_bet_new" 
                               min="0" required onchange="calculateDifference()">
                    </div>
                    
                    <div class="alert alert-info">
                        <small>
                            <i class="bi bi-info-circle me-1"></i>
                            <span id="bet_diff_info">변경사항이 없습니다.</span>
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="submit" class="btn btn-primary">수정</button>
                </div>
            </form>
        </div>
    </div>
</div>



<?php
// admin.php에서 베팅내역 탭 다음에 추가할 회차관리 탭

// ===================================
// 회차 관련 데이터 조회 (POST 처리 전에 추가)
// ===================================

/* 미리 설정된 회차들 조회 */
$scheduled_rounds = array();
$scheduled_sql = "SELECT * FROM dice_game_rounds WHERE status = 'scheduled' ORDER BY round_number ASC LIMIT 30";
$result = sql_query($scheduled_sql);
while ($row = sql_fetch_array($result)) {
    $scheduled_rounds[] = $row;
}

/* 진행중인 회차 정보 */
$active_round = sql_fetch("SELECT * FROM dice_game_rounds WHERE status IN ('betting', 'waiting') ORDER BY round_number DESC LIMIT 1");

/* 최근 완료 회차 통계 */
$recent_stats = sql_fetch("
    SELECT 
        COUNT(*) as total_rounds,
        SUM(CASE WHEN is_high = 1 THEN 1 ELSE 0 END) as high_count,
        SUM(CASE WHEN is_odd = 1 THEN 1 ELSE 0 END) as odd_count,
        SUM(total_bet_amount) as total_bet_sum,
        SUM(total_players) as total_players_sum
    FROM dice_game_rounds 
    WHERE status = 'completed' 
    AND result_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
");

?>

<!-- 회차관리 탭 -->
<div class="tab-pane fade" id="rounds" role="tabpanel">
    <h3 class="section-title">회차관리</h3>
    
    <!-- 회차 관리 버튼 -->
    <div class="alert alert-info mb-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h6 class="mb-1"><i class="bi bi-calendar-plus me-2"></i>회차 미리 생성 관리</h6>
                <p class="mb-0 small">게임 회차를 미리 생성하고 A/B/C 게임 결과를 설정할 수 있습니다.</p>
                <small class="text-muted">※ 미리 생성된 회차는 설정된 시간에 자동으로 진행됩니다.</small>
            </div>
            <div class="col-md-4 text-end">
                <button type="button" class="btn btn-info" onclick="openRoundPreAdmin()">
                    <i class="bi bi-calendar-week me-2"></i>미리 생성 관리
                </button>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- 현재 진행 상황 -->
        <div class="col-lg-4">
            <!-- 현재 진행중인 회차 -->
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-play-circle me-2"></i>현재 진행중인 회차
                </div>
                <div class="card-body">
                    <?php if ($active_round): ?>
                    <h4 class="text-center text-primary mb-3"><?php echo $active_round['round_number'] ?>회차</h4>
                    <table class="table table-sm">
                        <tr>
                            <td>상태:</td>
                            <td>
                                <span class="badge bg-<?php echo $active_round['status'] === 'betting' ? 'success' : 'warning' ?>">
                                    <?php echo $active_round['status'] === 'betting' ? '베팅중' : '대기중' ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td>시작:</td>
                            <td><?php echo date('H:i:s', strtotime($active_round['start_time'])) ?></td>
                        </tr>
                        <tr>
                            <td>마감:</td>
                            <td><?php echo date('H:i:s', strtotime($active_round['end_time'])) ?></td>
                        </tr>
                        <tr>
                            <td>결과발표:</td>
                            <td><?php echo date('H:i:s', strtotime($active_round['result_time'])) ?></td>
                        </tr>
                        <tr>
                            <td>참여자:</td>
                            <td><?php echo number_format($active_round['total_players'] ?? 0) ?>명</td>
                        </tr>
                        <tr>
                            <td>총 베팅:</td>
                            <td><?php echo number_format($active_round['total_bet_amount'] ?? 0) ?>원</td>
                        </tr>
                    </table>
                    
                    <?php if (!empty($active_round['game_a_result'])): ?>
                    <div class="text-center mt-3">
                        <small class="text-info">
                            <i class="bi bi-info-circle me-1"></i>
                            미리 설정된 결과: 
                            A<?php echo $active_round['game_a_result'] ?> 
                            B<?php echo $active_round['game_b_result'] ?> 
                            C<?php echo $active_round['game_c_result'] ?>
                        </small>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-pause-circle text-muted" style="font-size: 3rem;"></i>
                        <p class="mt-2 text-muted">진행중인 회차가 없습니다</p>
                    </div>
                    <?php endif; ?>
            <!-- 진행중인 회차 결과 수정 버튼 추가 -->
            <?php if ($active_round): ?>
            <div class="card mt-3">
                <div class="card-header bg-warning text-white">
                    <i class="bi bi-pencil-square me-2"></i>진행중인 회차 결과 수정
                </div>
                <div class="card-body">
                    <button type="button" class="btn btn-warning w-100" 
                            onclick="editActiveRoundResult(<?php echo $active_round['round_id'] ?>, <?php echo $active_round['round_number'] ?>, 
                                '<?php echo $active_round['game_a_result'] ?>', 
                                '<?php echo $active_round['game_b_result'] ?>', 
                                '<?php echo $active_round['game_c_result'] ?>')">
                        <i class="bi bi-pencil me-2"></i>
                        <?php echo $active_round['round_number'] ?>회차 결과 수정
                    </button>
                    <small class="text-muted d-block mt-2">
                        * 진행중인 회차의 결과를 미리 설정할 수 있습니다.
                    </small>
                </div>
            </div>
            <?php endif; ?>
                </div>
            </div>
            
            <!-- 24시간 통계 -->
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-graph-up me-2"></i>최근 24시간 통계
                </div>
                <div class="card-body">
                    <?php 
                    // A/B/C 게임 통계 조회
                    $abc_stats = sql_fetch("
                        SELECT 
                            COUNT(DISTINCT r.round_id) as total_rounds,
                            SUM(CASE WHEN r.game_a_result = '1' THEN 1 ELSE 0 END) as a1_count,
                            SUM(CASE WHEN r.game_b_result = '1' THEN 1 ELSE 0 END) as b1_count,
                            SUM(CASE WHEN r.game_c_result = '1' THEN 1 ELSE 0 END) as c1_count,
                            SUM(r.total_players) as total_players_sum
                        FROM dice_game_rounds r
                        WHERE r.status = 'completed' 
                        AND r.result_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    ");
                    
                    if ($abc_stats && $abc_stats['total_rounds'] > 0): 
                        $a1_rate = round(($abc_stats['a1_count'] / $abc_stats['total_rounds']) * 100, 1);
                        $b1_rate = round(($abc_stats['b1_count'] / $abc_stats['total_rounds']) * 100, 1);
                        $c1_rate = round(($abc_stats['c1_count'] / $abc_stats['total_rounds']) * 100, 1);
                    ?>
                    <div class="row text-center">
                        <div class="col-6">
                            <h5><?php echo $abc_stats['total_rounds'] ?></h5>
                            <small class="text-muted">총 회차</small>
                        </div>
                        <div class="col-6">
                            <h5><?php echo number_format($abc_stats['total_players_sum']) ?></h5>
                            <small class="text-muted">총 참여자</small>
                        </div>
                    </div>
                    <hr>
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-primary" style="width: <?php echo $a1_rate ?>%">
                                    <?php echo $a1_rate ?>%
                                </div>
                            </div>
                            <small class="text-muted">A1 비율</small>
                        </div>
                        <div class="col-4">
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-success" style="width: <?php echo $b1_rate ?>%">
                                    <?php echo $b1_rate ?>%
                                </div>
                            </div>
                            <small class="text-muted">B1 비율</small>
                        </div>
                        <div class="col-4">
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-warning" style="width: <?php echo $c1_rate ?>%">
                                    <?php echo $c1_rate ?>%
                                </div>
                            </div>
                            <small class="text-muted">C1 비율</small>
                        </div>
                    </div>
                    <?php else: ?>
                    <p class="text-center text-muted mb-0">통계 데이터가 없습니다</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- 미리 설정된 회차 목록 -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-calendar3 me-2"></i>예정된 회차 (최대 30개)</span>
                    <span class="badge bg-success"><?php echo count($scheduled_rounds) ?>개</span>
                </div>
                <div class="card-body">
                    <?php if (!empty($scheduled_rounds)): ?>
                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table class="table table-hover table-sm">
                            <thead class="sticky-top bg-white">
                                <tr>
                                    <th width="10%">회차</th>
                                    <th width="25%">시작시간</th>
                                    <th width="10%">A게임</th>
                                    <th width="10%">B게임</th>
                                    <th width="10%">C게임</th>
                                    <th width="25%">메모</th>
                                    <th width="10%">관리</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($scheduled_rounds as $round): ?>
                                <tr>
                                    <td class="text-center">
                                        <strong><?php echo $round['round_number'] ?></strong>
                                    </td>
                                    <td>
                                        <small>
                                            <?php echo date('m/d H:i', strtotime($round['start_time'])) ?>
                                            <span class="text-muted">~<?php echo date('H:i', strtotime($round['result_time'])) ?></span>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary">A<?php echo $round['game_a_result'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success">B<?php echo $round['game_b_result'] ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-warning">C<?php echo $round['game_c_result'] ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $time_diff = strtotime($round['start_time']) - time();
                                        if ($time_diff > 0) {
                                            $hours = floor($time_diff / 3600);
                                            $minutes = floor(($time_diff % 3600) / 60);
                                            echo "<small class='text-muted'>{$hours}시간 {$minutes}분 후</small>";
                                        }
                                        ?>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                onclick="editRoundResultABC(<?php echo $round['round_id'] ?>, <?php echo $round['round_number'] ?>)">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                        <h5 class="mt-3 text-muted">예정된 회차가 없습니다</h5>
                        <p class="text-muted">'미리 생성 관리' 버튼을 클릭하여 회차를 생성해주세요.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- 최근 완료 회차 -->
            <div class="card mt-3">
                <div class="card-header">
                    <i class="bi bi-check2-all me-2"></i>최근 완료된 회차
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm" id="completedRoundsTable">
                            <thead>
                                <tr>
                                    <th>회차</th>
                                    <th>완료시간</th>
                                    <th>A게임</th>
                                    <th>B게임</th>
                                    <th>C게임</th>
                                    <th>참여자</th>
                                    <th>총 베팅</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_rounds as $round): ?>
                                <tr>
                                    <td><?php echo $round['round_number'] ?></td>
                                    <td><?php echo date('m/d H:i', strtotime($round['result_time'])) ?></td>
                                    <td>
                                        <span class="badge bg-primary">A<?php echo $round['game_a_result'] ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success">B<?php echo $round['game_b_result'] ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-warning">C<?php echo $round['game_c_result'] ?></span>
                                    </td>
                                    <td><?php echo number_format($round['total_players']) ?>명</td>
                                    <td><?php echo number_format($round['total_bet_amount']) ?>원</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- 회차 수정 모달 (A/B/C 게임용) -->
<div class="modal fade" id="roundEditModalABC" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">회차 결과 수정</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="roundEditFormABC">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_round_result">
                    <input type="hidden" name="round_id" id="edit_round_id_abc">
                    
                    <h5 class="text-center mb-3">
                        <span id="edit_round_number_abc"></span>회차
                    </h5>
                    
                    <div class="row">
                        <div class="col-4">
                            <label class="form-label text-primary">A 게임</label>
                            <select class="form-select" name="game_a_result" id="edit_game_a" required>
                                <option value="1">A1</option>
                                <option value="2">A2</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="form-label text-success">B 게임</label>
                            <select class="form-select" name="game_b_result" id="edit_game_b" required>
                                <option value="1">B1</option>
                                <option value="2">B2</option>
                            </select>
                        </div>
                        <div class="col-4">
                            <label class="form-label text-warning">C 게임</label>
                            <select class="form-select" name="game_c_result" id="edit_game_c" required>
                                <option value="1">C1</option>
                                <option value="2">C2</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-3 text-center p-3 bg-light rounded">
                        <div id="game_preview">
                            <h5>선택된 결과</h5>
                            <div>
                                <span class="badge bg-primary me-1" id="preview_game_a">A1</span>
                                <span class="badge bg-success me-1" id="preview_game_b">B1</span>
                                <span class="badge bg-warning" id="preview_game_c">C1</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning mt-3" id="active_round_warning" style="display:none;">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        진행중인 회차의 결과를 수정하면 베팅 결과에 영향을 줍니다.
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

<script>
// 회차 결과 수정 모달 (A/B/C 게임용)
function editRoundResultABC(roundId, roundNumber) {
    document.getElementById('edit_round_id_abc').value = roundId;
    document.getElementById('edit_round_number_abc').textContent = roundNumber;
    
    // 현재 값 가져오기 (scheduled_rounds 데이터 활용)
    const roundData = <?php echo json_encode($scheduled_rounds); ?>;
    const round = roundData.find(r => r.round_id == roundId);
    
    if (round) {
        document.getElementById('edit_game_a').value = round.game_a_result || '1';
        document.getElementById('edit_game_b').value = round.game_b_result || '1';
        document.getElementById('edit_game_c').value = round.game_c_result || '1';
        updateGamePreview();
    }
    
    // 경고 메시지 숨기기
    document.getElementById('active_round_warning').style.display = 'none';
    
    const modal = new bootstrap.Modal(document.getElementById('roundEditModalABC'));
    modal.show();
}

// 진행중인 회차 수정 함수
function editActiveRoundResult(roundId, roundNumber, gameA, gameB, gameC) {
    document.getElementById('edit_round_id_abc').value = roundId;
    document.getElementById('edit_round_number_abc').textContent = roundNumber;
    
    // 현재 값 설정
    document.getElementById('edit_game_a').value = gameA || '1';
    document.getElementById('edit_game_b').value = gameB || '1';
    document.getElementById('edit_game_c').value = gameC || '1';
    
    // 경고 메시지 표시
    document.getElementById('active_round_warning').style.display = 'block';
    
    updateGamePreview();
    
    const modal = new bootstrap.Modal(document.getElementById('roundEditModalABC'));
    modal.show();
}

// 게임 미리보기 업데이트
function updateGamePreview() {
    const gameA = document.getElementById('edit_game_a').value;
    const gameB = document.getElementById('edit_game_b').value;
    const gameC = document.getElementById('edit_game_c').value;
    
    document.getElementById('preview_game_a').textContent = 'A' + gameA;
    document.getElementById('preview_game_b').textContent = 'B' + gameB;
    document.getElementById('preview_game_c').textContent = 'C' + gameC;
}

// 이벤트 리스너
document.getElementById('edit_game_a').addEventListener('change', updateGamePreview);
document.getElementById('edit_game_b').addEventListener('change', updateGamePreview);
document.getElementById('edit_game_c').addEventListener('change', updateGamePreview);
// 완료된 회차 DataTable
$(document).ready(function() {
    $('#completedRoundsTable').DataTable({
        language: {
            "lengthMenu": "페이지당 _MENU_ 개씩 보기",
            "zeroRecords": "데이터가 없습니다",
            "info": "총 _TOTAL_개 중 _START_에서 _END_까지 표시",
            "infoEmpty": "데이터가 없습니다",
            "infoFiltered": "(전체 _MAX_개 중 검색결과)",
            "search": "검색:",
            "paginate": {
                "first": "처음",
                "last": "마지막",
                "next": "다음",
                "previous": "이전"
            }
        },
        pageLength: 10,
        order: [[0, 'desc']]
    });
});

// 베팅금액 수정 모달
function editBetAmount(betId, currentAmount, mbId) {
    document.getElementById('edit_bet_id').value = betId;
    document.getElementById('edit_bet_mb_id').value = mbId;
    document.getElementById('edit_bet_current').value = currentAmount.toLocaleString() + '원';
    document.getElementById('edit_bet_new').value = currentAmount;
    
    calculateDifference();
    
    const modal = new bootstrap.Modal(document.getElementById('betEditModal'));
    modal.show();
}

// 차액 계산 및 표시
function calculateDifference() {
    const currentText = document.getElementById('edit_bet_current').value;
    const current = parseInt(currentText.replace(/[^0-9]/g, ''));
    const newAmount = parseInt(document.getElementById('edit_bet_new').value) || 0;
    const diff = newAmount - current;
    
    const infoElement = document.getElementById('bet_diff_info');
    
    if (diff > 0) {
        infoElement.innerHTML = `<span class="text-danger">회원 포인트에서 ${diff.toLocaleString()}P가 추가로 차감됩니다.</span>`;
    } else if (diff < 0) {
        infoElement.innerHTML = `<span class="text-success">회원에게 ${Math.abs(diff).toLocaleString()}P가 환불됩니다.</span>`;
    } else {
        infoElement.innerHTML = '변경사항이 없습니다.';
    }
}

// 폼 제출 전 확인
document.getElementById('betEditForm').addEventListener('submit', function(e) {
    const currentText = document.getElementById('edit_bet_current').value;
    const current = parseInt(currentText.replace(/[^0-9]/g, ''));
    const newAmount = parseInt(document.getElementById('edit_bet_new').value) || 0;
    const mbId = document.getElementById('edit_bet_mb_id').value;
    
    if (!confirm(`${mbId} 회원의 베팅금액을 ${current.toLocaleString()}원에서 ${newAmount.toLocaleString()}원으로 수정하시겠습니까?`)) {
        e.preventDefault();
    }
});
</script>
<!-- 게임 설정 탭 -->
<div class="tab-pane fade" id="game-config" role="tabpanel">
    <h3 class="section-title">게임 시스템 설정</h3>
    
    <!-- 회차 미리 생성 관리 버튼 추가 -->
    <div class="alert alert-info mb-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h6 class="mb-1"><i class="bi bi-calendar-plus me-2"></i>회차 미리 생성 관리</h6>
                <p class="mb-0 small">게임 회차를 미리 생성하고 주사위 결과값을 설정할 수 있습니다.</p>
                <small class="text-muted">※ 미리 생성된 회차는 설정된 시간에 자동으로 진행됩니다.</small>
            </div>
            <div class="col-md-4 text-end">
                <button type="button" class="btn btn-info btn-lg" onclick="openRoundPreAdmin()">
                    <i class="bi bi-calendar-week me-2"></i>회차 관리
                </button>
            </div>
        </div>
    </div>
    
    <form method="post">
        <input type="hidden" name="action" value="update_game_config">
        
        <div class="row">
            <div class="col-md-6">
                <div class="form-section">
                    <h5><i class="bi bi-gear me-2"></i>게임 기본 설정</h5>
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="game_status" 
                                       id="game_status" value="1" <?php echo $current_settings['game_status'] == '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="game_status">
                                    게임 시스템 활성화
                                </label>
                            </div>
                        </div>
                        <div class="col-4">
                            <label class="form-label">베팅 시간(초)</label>
                            <input type="number" class="form-control" name="betting_time" 
                                   value="<?php echo $current_settings['betting_time'] ?>" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label">결과 시간(초)</label>
                            <input type="number" class="form-control" name="result_time" 
                                   value="<?php echo $current_settings['result_time'] ?>" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label">게임 간격(초)</label>
                            <input type="number" class="form-control" name="game_interval" 
                                   value="<?php echo $current_settings['game_interval'] ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h5><i class="bi bi-currency-dollar me-2"></i>베팅 금액 설정</h5>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">최소 베팅 금액</label>
                            <input type="number" class="form-control" name="min_bet" 
                                   value="<?php echo $current_settings['min_bet'] ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">최대 베팅 금액</label>
                            <input type="number" class="form-control" name="max_bet" 
                                   value="<?php echo $current_settings['max_bet'] ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h5><i class="bi bi-arrow-repeat me-2"></i>자동 회차 생성 설정</h5>
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="auto_generate_rounds" 
                                       id="auto_generate_rounds" value="1" <?php echo getConfigValue('auto_generate_rounds', '0') == '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="auto_generate_rounds">
                                    자동 회차 생성 활성화
                                </label>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label">생성 간격(분)</label>
                            <input type="number" class="form-control" name="auto_generate_interval" 
                                   value="<?php echo getConfigValue('auto_generate_interval', '5') ?>" min="1" max="60">
                        </div>
                        <div class="col-6">
                            <label class="form-label">생성 개수</label>
                            <input type="number" class="form-control" name="auto_generate_count" 
                                   value="<?php echo getConfigValue('auto_generate_count', '20') ?>" min="1" max="100">
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="form-section">
                    <h5><i class="bi bi-trophy me-2"></i>게임별 당첨 배율 설정</h5>
                    
                    <!-- A 게임 배율 -->
                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label class="form-label fw-bold text-primary">A 게임 (주사위 합계 기준)</label>
                            <small class="text-muted d-block">합계 3-10: A1 / 합계 11-18: A2</small>
                        </div>
                        <div class="col-6">
                            <label class="form-label">A1 배율</label>
                            <input type="number" class="form-control" name="game_a1_rate" 
                                   value="<?php echo getConfigValue('game_a1_rate', '2.0') ?>" step="0.01" min="1.01" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">A2 배율</label>
                            <input type="number" class="form-control" name="game_a2_rate" 
                                   value="<?php echo getConfigValue('game_a2_rate', '2.0') ?>" step="0.01" min="1.01" required>
                        </div>
                    </div>

                    <!-- B 게임 배율 -->
                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label class="form-label fw-bold text-success">B 게임 (홀짝 기준)</label>
                            <small class="text-muted d-block">홀수: B1 / 짝수: B2</small>
                        </div>
                        <div class="col-6">
                            <label class="form-label">B1 배율</label>
                            <input type="number" class="form-control" name="game_b1_rate" 
                                   value="<?php echo getConfigValue('game_b1_rate', '2.0') ?>" step="0.01" min="1.01" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">B2 배율</label>
                            <input type="number" class="form-control" name="game_b2_rate" 
                                   value="<?php echo getConfigValue('game_b2_rate', '2.0') ?>" step="0.01" min="1.01" required>
                        </div>
                    </div>

                    <!-- C 게임 배율 -->
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold text-warning">C 게임 (첫 주사위 기준)</label>
                            <small class="text-muted d-block">첫 주사위 1-3: C1 / 첫 주사위 4-6: C2</small>
                        </div>
                        <div class="col-6">
                            <label class="form-label">C1 배율</label>
                            <input type="number" class="form-control" name="game_c1_rate" 
                                   value="<?php echo getConfigValue('game_c1_rate', '2.0') ?>" step="0.01" min="1.01" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">C2 배율</label>
                            <input type="number" class="form-control" name="game_c2_rate" 
                                   value="<?php echo getConfigValue('game_c2_rate', '2.0') ?>" step="0.01" min="1.01" required>
                        </div>
                    </div>

                    <div class="col-12 mt-3">
                        <div class="alert alert-info">
                            <small>
                                <i class="bi bi-info-circle me-1"></i>
                                각 게임별로 독립적으로 베팅 가능하며, 여러 게임에 동시 베팅도 가능합니다.
                            </small>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h5><i class="bi bi-clock me-2"></i>현재 게임 상태</h5>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-<?php echo $current_settings['game_status'] == '1' ? 'success' : 'danger' ?> fs-6">
                                <?php echo $current_settings['game_status'] == '1' ? '게임 활성화' : '게임 비활성화' ?>
                            </span>
                        </div>
                        <div>
                            <a href="./diagnosis.php" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-tools me-1"></i>게임 진단
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-check-circle me-2"></i>게임 설정 저장
            </button>
        </div>
    </form>
</div>

<!-- 페이지 하단 스크립트 부분에 추가 -->
<script>
// 회차 미리 생성 관리 팝업 열기
function openRoundPreAdmin() {
    const width = 1200;
    const height = 800;
    const left = (screen.width - width) / 2;
    const top = (screen.height - height) / 2;
    
    const popup = window.open(
        './round_pre_admin.php?popup=1', 
        'roundPreAdmin',
        `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`
    );
    
    if (!popup) {
        alert('팝업 차단이 감지되었습니다.\n브라우저 설정에서 팝업을 허용해주세요.');
    }
}

// 팝업에서 변경사항이 있을 때 알림 (선택사항)
window.addEventListener('message', function(e) {
    if (e.data === 'roundsUpdated') {
        // 회차가 업데이트되었다는 알림 표시
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-success alert-dismissible fade show';
        alertDiv.innerHTML = `
            <i class="bi bi-check-circle me-2"></i>
            회차 정보가 업데이트되었습니다.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.querySelector('.container-fluid').insertBefore(alertDiv, document.querySelector('.container-fluid').firstChild);
    }
});
</script>

                <!-- 충전/출금 설정 탭 -->
                <div class="tab-pane fade" id="payment-config" role="tabpanel">
                    <h3 class="section-title">충전/출금 시스템 설정</h3>
                    
                    <form method="post">
                        <input type="hidden" name="action" value="update_payment_config">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-section">
                                    <h5><i class="bi bi-gear me-2"></i>기본 설정</h5>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="system_status" 
                                                       id="system_status" value="1" <?php echo $current_settings['system_status'] == '1' ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="system_status">
                                                    충전/출금 시스템 활성화
                                                </label>
                                            </div>
                                        </div>
                                      
                                        <div class="col-6">
                                            <label class="form-label">업무 시작 시간</label>
                                            <input type="time" class="form-control" name="business_hours_start" 
                                                   value="<?php echo $current_settings['business_hours_start'] ?>">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">업무 종료 시간</label>
                                            <input type="time" class="form-control" name="business_hours_end" 
                                                   value="<?php echo $current_settings['business_hours_end'] ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h5><i class="bi bi-arrow-down-circle me-2"></i>충전 설정</h5>
                                    <div class="row g-3">
                                        <div class="col-6">
                                            <label class="form-label">최소 충전 금액</label>
                                            <input type="number" class="form-control" name="min_charge_amount" 
                                                   value="<?php echo $current_settings['min_charge_amount'] ?>" required>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">최대 충전 금액</label>
                                            <input type="number" class="form-control" name="max_charge_amount" 
                                                   value="<?php echo $current_settings['max_charge_amount'] ?>" required>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">충전 수수료율 (%)</label>
                                            <input type="number" class="form-control" name="charge_fee_rate" 
                                                   value="<?php echo $current_settings['charge_fee_rate'] ?>" step="0.01">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-section">
                                    <h5><i class="bi bi-arrow-up-circle me-2"></i>출금 설정</h5>
                                    <div class="row g-3">
                                        <div class="col-6">
                                            <label class="form-label">최소 출금 금액</label>
                                            <input type="number" class="form-control" name="min_withdraw_amount" 
                                                   value="<?php echo $current_settings['min_withdraw_amount'] ?>" required>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">최대 출금 금액</label>
                                            <input type="number" class="form-control" name="max_withdraw_amount" 
                                                   value="<?php echo $current_settings['max_withdraw_amount'] ?>" required>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">출금 수수료율 (%)</label>
                                            <input type="number" class="form-control" name="withdraw_fee_rate" 
                                                   value="<?php echo $current_settings['withdraw_fee_rate'] ?>" step="0.01">
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">출금 고정 수수료</label>
                                            <input type="number" class="form-control" name="withdraw_fee_fixed" 
                                                   value="<?php echo $current_settings['withdraw_fee_fixed'] ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h5><i class="bi bi-check-circle me-2"></i>자동 승인 설정</h5>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label">자동 승인 한도 (0: 수동승인만)</label>
                                            <input type="number" class="form-control" name="auto_approval_limit" 
                                                   value="<?php echo $current_settings['auto_approval_limit'] ?>">
                                            <small class="text-muted">이 금액 이하의 충전/출금은 자동으로 승인됩니다.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-circle me-2"></i>충전/출금 설정 저장
                            </button>
                        </div>
                    </form>
                </div>

<!-- 게임 설정 탭 -->
<div class="tab-pane fade" id="game-config" role="tabpanel">
    <h3 class="section-title">게임 시스템 설정</h3>
    
    <!-- 회차 미리 생성 관리 버튼 추가 -->
    <div class="alert alert-info mb-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h6 class="mb-1"><i class="bi bi-calendar-plus me-2"></i>회차 미리 생성 관리</h6>
                <p class="mb-0 small">게임 회차를 미리 생성하고 주사위 결과값을 설정할 수 있습니다.</p>
                <small class="text-muted">※ 미리 생성된 회차는 설정된 시간에 자동으로 진행됩니다.</small>
            </div>
            <div class="col-md-4 text-end">
                <button type="button" class="btn btn-info btn-lg" onclick="openRoundPreAdmin()">
                    <i class="bi bi-calendar-week me-2"></i>회차 관리
                </button>
            </div>
        </div>
    </div>
    
    <form method="post">
        <input type="hidden" name="action" value="update_game_config">
        
        <div class="row">
            <div class="col-md-6">
                <div class="form-section">
                    <h5><i class="bi bi-gear me-2"></i>게임 기본 설정</h5>
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="game_status" 
                                       id="game_status" value="1" <?php echo $current_settings['game_status'] == '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="game_status">
                                    게임 시스템 활성화
                                </label>
                            </div>
                        </div>
                        <div class="col-4">
                            <label class="form-label">베팅 시간(초)</label>
                            <input type="number" class="form-control" name="betting_time" 
                                   value="<?php echo $current_settings['betting_time'] ?>" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label">결과 시간(초)</label>
                            <input type="number" class="form-control" name="result_time" 
                                   value="<?php echo $current_settings['result_time'] ?>" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label">게임 간격(초)</label>
                            <input type="number" class="form-control" name="game_interval" 
                                   value="<?php echo $current_settings['game_interval'] ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h5><i class="bi bi-currency-dollar me-2"></i>베팅 금액 설정</h5>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">최소 베팅 금액</label>
                            <input type="number" class="form-control" name="min_bet" 
                                   value="<?php echo $current_settings['min_bet'] ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">최대 베팅 금액</label>
                            <input type="number" class="form-control" name="max_bet" 
                                   value="<?php echo $current_settings['max_bet'] ?>" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="form-section">
                    <h5><i class="bi bi-trophy me-2"></i>당첨 배율 설정</h5>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">대소 배율</label>
                            <input type="number" class="form-control" name="win_rate_high_low" 
                                   value="<?php echo $current_settings['win_rate_high_low'] ?>" step="0.01" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">홀짝 배율</label>
                            <input type="number" class="form-control" name="win_rate_odd_even" 
                                   value="<?php echo $current_settings['win_rate_odd_even'] ?>" step="0.01" required>
                        </div>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <small>
                                    <i class="bi bi-info-circle me-1"></i>
                                    복합 베팅 시 실제 배율: 대소배율 × 홀짝배율 = <?php echo round($current_settings['win_rate_high_low'] * $current_settings['win_rate_odd_even'], 2) ?>배
                                </small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h5><i class="bi bi-clock me-2"></i>현재 게임 상태</h5>
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="badge bg-<?php echo $current_settings['game_status'] == '1' ? 'success' : 'danger' ?> fs-6">
                                <?php echo $current_settings['game_status'] == '1' ? '게임 활성화' : '게임 비활성화' ?>
                            </span>
                        </div>
                        <div>
                            <a href="./diagnosis.php" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-tools me-1"></i>게임 진단
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <button type="submit" class="btn btn-primary btn-lg">
                <i class="bi bi-check-circle me-2"></i>게임 설정 저장
            </button>
        </div>
    </form>
</div>

<!-- 페이지 하단 스크립트 부분에 추가 -->
<script>
// 회차 미리 생성 관리 팝업 열기
function openRoundPreAdmin() {
    const width = 1200;
    const height = 800;
    const left = (screen.width - width) / 2;
    const top = (screen.height - height) / 2;
    
    const popup = window.open(
        './round_pre_admin.php?popup=1', 
        'roundPreAdmin',
        `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`
    );
    
    if (!popup) {
        alert('팝업 차단이 감지되었습니다.\n브라우저 설정에서 팝업을 허용해주세요.');
    }
}

// 팝업에서 변경사항이 있을 때 알림 (선택사항)
window.addEventListener('message', function(e) {
    if (e.data === 'roundsUpdated') {
        // 회차가 업데이트되었다는 알림 표시
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-success alert-dismissible fade show';
        alertDiv.innerHTML = `
            <i class="bi bi-check-circle me-2"></i>
            회차 정보가 업데이트되었습니다.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.querySelector('.container-fluid').insertBefore(alertDiv, document.querySelector('.container-fluid').firstChild);
    }
});
</script>

                <!-- 계좌변경 탭 -->
                <div class="tab-pane fade" id="account" role="tabpanel">
                    <h3 class="section-title">관리자 계좌 변경</h3>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-section">
                                <form method="post">
                                    <input type="hidden" name="action" value="update_admin_account">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">은행명</label>
                                            <input type="text" class="form-control" name="bank_name" 
                                                   value="<?php echo htmlspecialchars($current_settings['admin_bank_name']) ?>" 
                                                   placeholder="예: 국민은행" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">계좌번호</label>
                                            <input type="text" class="form-control" name="account_number" 
                                                   value="<?php echo htmlspecialchars($current_settings['admin_account_number']) ?>" 
                                                   placeholder="예: 123456-78-901234" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">예금주</label>
                                            <input type="text" class="form-control" name="account_holder" 
                                                   value="<?php echo htmlspecialchars($current_settings['admin_account_holder']) ?>" 
                                                   placeholder="예: 홍길동" required>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-check-circle me-2"></i>계좌 정보 저장
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="alert alert-info">
                                <h6><i class="bi bi-info-circle me-2"></i>현재 등록된 계좌</h6>
                                <?php if ($admin_account): ?>
                                <div class="mb-2">
                                    <strong><?php echo htmlspecialchars($admin_bank_name) ?></strong><br>
                                    <span class="text-primary fw-bold"><?php echo htmlspecialchars($admin_account_number) ?></span><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($admin_account_holder) ?></small>
                                </div>
                                <small class="text-success">
                                    <i class="bi bi-check-circle me-1"></i>활성화됨
                                </small>
                                <?php else: ?>
                                <p class="mb-0 text-warning">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    등록된 계좌가 없습니다.
                                </p>
                                <?php endif; ?>
                                
                                <hr class="my-2">
                                <ul class="mb-0 small">
                                    <li>회원들의 충전용 계좌입니다</li>
                                    <li>payment.php에 자동 표시됩니다</li>
                                    <li>변경 즉시 적용됩니다</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 가입코드 관리 탭 -->
                <div class="tab-pane fade" id="signup" role="tabpanel">
                    <h3 class="section-title">가입코드 관리</h3>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="form-section">
                                <form method="post">
                                    <input type="hidden" name="action" value="update_signup_code">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">가입코드</label>
                                            <input type="text" class="form-control" name="signup_code" 
                                                   value="<?php echo htmlspecialchars($current_settings['signup_code']) ?>" 
                                                   placeholder="가입 시 필요한 코드" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">가입 축하 포인트</label>
                                            <input type="number" class="form-control" name="welcome_point" 
                                                   value="<?php echo $current_settings['signup_welcome_point'] ?>" 
                                                   placeholder="신규 가입자 지급 포인트" required>
                                        </div>
                                        <div class="col-12">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="signup_enabled" 
                                                       id="signup_enabled" <?php echo $current_settings['signup_enabled'] == '1' ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="signup_enabled">
                                                    회원가입 허용
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-check-circle me-2"></i>가입코드 설정 저장
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="alert alert-info">
                                <h6><i class="bi bi-info-circle me-2"></i>가입코드 안내</h6>
                                <ul class="mb-0 small">
                                    <li>회원가입 시 필수 입력</li>
                                    <li>영문, 숫자 조합 권장</li>
                                    <li>정기적으로 변경 권장</li>
                                    <li>스팸 가입 방지 효과</li>
                                </ul>
                            </div>
                            
                            <div class="alert alert-warning">
                                <h6><i class="bi bi-exclamation-triangle me-2"></i>현재 상태</h6>
                                <p class="mb-2">
                                    가입코드: <strong><?php echo htmlspecialchars($current_settings['signup_code']) ?></strong>
                                </p>
                                <p class="mb-0">
                                    상태: 
                                    <span class="badge bg-<?php echo $current_settings['signup_enabled'] == '1' ? 'success' : 'danger' ?>">
                                        <?php echo $current_settings['signup_enabled'] == '1' ? '활성화' : '비활성화' ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- jQuery & Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.0/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.0/js/dataTables.bootstrap5.min.js"></script>

    <script>
        // ===================================
        // 폼 검증 및 확인
        // ===================================
        
        // 회원 정보 수정 확인
        $('form').on('submit', function(e) {
            const action = $(this).find('input[name="action"]').val();
            
            if (action === 'update_member_info') {
                const mbId = $(this).find('input[name="mb_id"]').val();
                if (!confirm(`${mbId} 회원의 정보를 수정하시겠습니까?`)) {
                    e.preventDefault();
                }
                return; // 다른 확인은 하지 않음
            }
            
            // 입출금 승인/거부 확인
            if (action === 'update_payment_status') {
                const status = $(this).find('button[type="submit"]:focus').val();
                const statusText = status === 'approved' ? '승인' : '거부';
                
                if (!confirm(`해당 요청을 ${statusText}하시겠습니까?`)) {
                    e.preventDefault();
                }
            }
            
            // 게임 설정 변경 확인
            if (action === 'update_game_config') {
                if (!confirm('게임 설정을 변경하시겠습니까?\n진행 중인 게임에 즉시 적용됩니다.')) {
                    e.preventDefault();
                }
            }
            
            // 계좌 정보 변경 확인
            if (action === 'update_admin_account') {
                if (!confirm('관리자 계좌 정보를 변경하시겠습니까?\n회원들에게 즉시 노출됩니다.')) {
                    e.preventDefault();
                }
            }
        });

        // ===================================
        // 실시간 통계 업데이트 (선택사항)
        // ===================================
        
        function updateStats() {
            // 5분마다 통계 업데이트 (AJAX 호출)
            // 서버 부하를 고려하여 필요시에만 구현
        }

        // ===================================
        // 키보드 단축키
        // ===================================
        
        $(document).keydown(function(e) {
            // Ctrl + 숫자로 탭 전환
            if (e.ctrlKey) {
                let tabIndex = -1;
                switch(e.which) {
                    case 49: tabIndex = 0; break; // Ctrl + 1: 회원관리
                    case 50: tabIndex = 1; break; // Ctrl + 2: 입금관리
                    case 51: tabIndex = 2; break; // Ctrl + 3: 출금관리
                    case 52: tabIndex = 3; break; // Ctrl + 4: 베팅내역
                    case 53: tabIndex = 4; break; // Ctrl + 5: 게임결과
                    case 54: tabIndex = 5; break; // Ctrl + 6: 계좌변경
                    case 55: tabIndex = 6; break; // Ctrl + 7: 가입코드
                }
                
                if (tabIndex >= 0) {
                    const tabs = document.querySelectorAll('#adminTabs .nav-link');
                    if (tabs[tabIndex]) {
                        tabs[tabIndex].click();
                    }
                    e.preventDefault();
                }
            }
        });

        // ===================================
        // 페이지 로드 완료 알림
        // ===================================
        
        $(document).ready(function() {
            // 로딩 완료 후 첫 번째 탭에 포커스
            setTimeout(function() {
                $('.nav-tabs .nav-link.active').focus();
            }, 500);
            
            // 툴팁 초기화 (Bootstrap 5)
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // ===================================
        // 자동 새로고침 (선택사항)
        // ===================================
        
        // 5분마다 자동 새로고침 (개발 완료 후 활성화)
        /*
        setInterval(function() {
            if (confirm('최신 데이터를 불러오시겠습니까?')) {
                location.reload();
            }
        }, 300000); // 5분
        */

        // ===================================
        // 브라우저 호환성 확인
        // ===================================
        
        if (!window.bootstrap) {
            console.warn('Bootstrap JS가 로드되지 않았습니다.');
        }
        
        if (!window.jQuery) {
            console.warn('jQuery가 로드되지 않았습니다.');
        }
		// ===================================
        
        $(document).ready(function() {
            // 한국어 설정
            const koreanLanguage = {
                "lengthMenu": "페이지당 _MENU_ 개씩 보기",
                "zeroRecords": "데이터가 없습니다",
                "info": "총 _TOTAL_개 중 _START_에서 _END_까지 표시",
                "infoEmpty": "데이터가 없습니다",
                "infoFiltered": "(전체 _MAX_개 중 검색결과)",
                "search": "검색:",
                "paginate": {
                    "first": "처음",
                    "last": "마지막",
                    "next": "다음",
                    "previous": "이전"
                }
            };

            // 각 테이블 초기화
            $('#membersTable').DataTable({
                language: koreanLanguage,
                pageLength: 15,
                order: [[6, 'desc']], // 가입일 기준 내림차순
                columnDefs: [
                    { orderable: false, targets: [7] }, // 관리 컬럼 정렬 비활성화
                    { className: "text-center", targets: [2, 7] },
                    { className: "text-end", targets: [3] }
                ]
            });

            $('#chargesTable').DataTable({
                language: koreanLanguage,
                pageLength: 10,
                order: [[4, 'desc']], // 신청일 기준 내림차순
                columnDefs: [
                    { orderable: false, targets: [6] } // 관리 컬럼 정렬 비활성화
                ]
            });

            $('#withdrawalsTable').DataTable({
                language: koreanLanguage,
                pageLength: 10,
                order: [[6, 'desc']], // 신청일 기준 내림차순
                columnDefs: [
                    { orderable: false, targets: [8] } // 관리 컬럼 정렬 비활성화
                ]
            });

            $('#betsTable').DataTable({
                language: koreanLanguage,
                pageLength: 15,
                order: [[8, 'desc']], // 베팅시간 기준 내림차순
                columnDefs: [
                    { className: "text-center", targets: [0, 2, 3, 7] }
                ]
            });

            $('#resultsTable').DataTable({
                language: koreanLanguage,
                pageLength: 10,
                order: [[0, 'desc']], // 회차 기준 내림차순
                columnDefs: [
                    { className: "text-center", targets: [0, 1, 2, 3, 4, 5] }
                ]
            });
        });

        // ===================================
        // 회원 편집 함수
        // ===================================
        
        function editMember(mbId) {
            // 해당 회원 정보를 찾아서 모달에 표시
            const memberData = <?php echo json_encode($all_members); ?>;
            const member = memberData.find(m => m.mb_id === mbId);
            
            if (member) {
                document.getElementById('edit_mb_id').value = member.mb_id;
                document.getElementById('edit_mb_id_display').value = member.mb_id;
                document.getElementById('edit_mb_name').value = member.mb_name;
                document.getElementById('edit_mb_point').value = member.mb_point;
                document.getElementById('edit_mb_1').value = member.mb_1 || '';
                document.getElementById('edit_mb_2').value = member.mb_2 || '';
                document.getElementById('edit_mb_password').value = ''; // 비밀번호는 비워둠
                
                // 모달 열기
                const modal = new bootstrap.Modal(document.getElementById('memberEditModal'));
                modal.show();
            }
        }

        function saveMemberInfo() {
            const form = document.getElementById('memberEditForm');
            const mbId = document.getElementById('edit_mb_id').value;
            const mbName = document.getElementById('edit_mb_name').value;
            const mbPoint = document.getElementById('edit_mb_point').value;
            
            if (!mbId || !mbName || !mbPoint) {
                alert('필수 항목을 모두 입력해주세요.');
                return;
            }
            
            if (!confirm(`${mbId} 회원의 정보를 수정하시겠습니까?`)) {
                return;
            }
            
            form.submit();
        }

        // ===================================
        //
    </script>
</body>
</html>