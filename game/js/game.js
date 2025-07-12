/*
* 파일명: game.js
* 위치: /game/js/game.js
* 기능: A/B/C 게임 클라이언트 (서버 중심)
* 작성일: 2025-01-07
* 수정일: 2025-01-07 (A/B/C 게임으로 전환)
*/

// ===================================
// 전역 변수
// ===================================
let selectedBets = {
    A: null,
    B: null,
    C: null
};
let countdownTimer = null;
let statusCheckTimer = null;
let currentUserPoint = 0;
let betHistory = [];
let currentRoundData = null;

// gameConfig 안전 체크
if (typeof gameConfig === 'undefined') {
    console.error('gameConfig가 정의되지 않았습니다.');
    window.gameConfig = {
        minBet: 1000,
        maxBet: 100000,
        userPoint: 0,
        roundId: 1,
        roundNumber: 1,
        endTime: new Date(Date.now() + 90000).toISOString(),
        gameInterval: 120,
        // A/B/C 게임 배율
        gameA1Rate: 2.0,
        gameA2Rate: 2.0,
        gameB1Rate: 2.0,
        gameB2Rate: 2.0,
        gameC1Rate: 2.0,
        gameC2Rate: 2.0
    };
}

// 게임별 배율 정보
const gameRates = {
    A: {
        1: gameConfig.gameA1Rate || 2.0,
        2: gameConfig.gameA2Rate || 2.0
    },
    B: {
        1: gameConfig.gameB1Rate || 2.0,
        2: gameConfig.gameB2Rate || 2.0
    },
    C: {
        1: gameConfig.gameC1Rate || 2.0,
        2: gameConfig.gameC2Rate || 2.0
    }
};

currentUserPoint = gameConfig.userPoint;

// ===================================
// 초기화
// ===================================
$(document).ready(function() {
    initializeGame();
    bindEvents();
    startStatusChecking();
});

/**
 * 게임 초기화
 */
function initializeGame() {
    updatePointDisplay(currentUserPoint);
    updateSelectionDisplay();
    updateSubmitButton();
    
    // 초기 상태 체크
    checkGameStatus();
}

/**
 * 이벤트 바인딩
 */
function bindEvents() {
    // 베팅 버튼 클릭
    $('.bet-button').on('click', function() {
        if (!currentRoundData || currentRoundData.status !== 'betting') return;
        
        const game = $(this).data('game');
        const option = $(this).data('option');
        
        selectBetOption(game, option, $(this));
    });
    
    // 베팅 금액 입력
    $('#betAmount').on('input', function() {
        updateExpectedWin();
        updateSubmitButton();
    });
    
    // 베팅 폼 제출
    $('#bettingForm').on('submit', function(e) {
        e.preventDefault();
        if (validateBet()) {
            submitBet();
        }
    });
}

// ===================================
// 서버 상태 체크 (핵심)
// ===================================
/**
 * 주기적 상태 체크 시작
 */
function startStatusChecking() {
    // 즉시 체크
    checkGameStatus();
    
    // 3초마다 상태 체크
    statusCheckTimer = setInterval(checkGameStatus, 3000);
}

/**
 * 서버 게임 상태 체크
 */
function checkGameStatus() {
    $.ajax({
        url: './status_check.php',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // 현재 회차 데이터 업데이트
                updateCurrentRound(response);
                
                // 결과가 있으면 표시
                if (response.result) {
                    showRoundResult(response.result);
                }
            }
        },
        error: function() {
            console.log('상태 체크 실패');
        }
    });
}

/**
 * 현재 회차 정보 업데이트
 * @param {object} roundData - 서버에서 받은 회차 데이터
 */
function updateCurrentRound(roundData) {
    const isNewRound = !currentRoundData || currentRoundData.round_number !== roundData.round_number;
    
    if (isNewRound) {
        // 새 회차 시작 - 이전 결과는 보존
        resetForNewRound();
        showNotification(`${roundData.round_number}회차가 시작되었습니다!`);
    }
    
    // 현재 회차 데이터 저장
    currentRoundData = roundData;
    
    // UI 업데이트
    updateRoundDisplay(roundData.round_number);
    updateGamePhase(roundData.phase);
    updateTimer(roundData.end_time);
    
    // 게임 설정 업데이트
    gameConfig.roundId = roundData.round_id;
    gameConfig.roundNumber = roundData.round_number;
    gameConfig.endTime = roundData.end_time;
}

/**
 * 게임 단계에 따른 UI 업데이트
 * @param {string} phase - 게임 단계 (betting, waiting, result)
 */
function updateGamePhase(phase) {
    switch (phase) {
        case 'betting':
            enableBetting();
            break;
        case 'waiting':
            disableBetting();
            $('#submitBet').html('<i class="bi bi-clock me-2"></i>결과 대기중...');
            break;
        case 'result':
            disableBetting();
            $('#submitBet').html('<i class="bi bi-hourglass-split me-2"></i>결과 발표중...');
            break;
    }
}

/**
 * 타이머 업데이트
 * @param {string} endTime - 종료 시간
 */
function updateTimer(endTime) {
    if (countdownTimer) {
        clearInterval(countdownTimer);
    }
    
    countdownTimer = setInterval(() => {
        const now = new Date().getTime();
        const end = new Date(endTime).getTime();
        const timeLeft = end - now;
        
        if (timeLeft <= 0) {
            $('#countdown').text('00:00');
            return;
        }
        
        const minutes = Math.floor(timeLeft / 60000);
        const seconds = Math.floor((timeLeft % 60000) / 1000);
        
        $('#countdown').text(
            String(minutes).padStart(2, '0') + ':' + 
            String(seconds).padStart(2, '0')
        );
    }, 1000);
}

// ===================================
// 베팅 관련 함수
// ===================================
/**
 * 베팅 옵션 선택
 */
function selectBetOption(game, option, button) {
    // 같은 게임의 다른 버튼 비활성화
    $(`.bet-button[data-game="${game}"]`).removeClass('active');
    
    // 현재 버튼 활성화/비활성화 토글
    if (selectedBets[game] === option) {
        // 이미 선택된 것을 다시 클릭하면 선택 해제
        button.removeClass('active');
        selectedBets[game] = null;
    } else {
        // 새로운 선택
        button.addClass('active');
        selectedBets[game] = option;
    }
    
    updateSelectionDisplay();
    updateExpectedWin();
    updateSubmitButton();
}

/**
 * 현재 선택 표시 업데이트
 */
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

/**
 * 예상 당첨금 계산 및 표시
 */
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
    
    $('#expectedWin').text(number_format(totalWin) + 'P');
    
    if (rateDetails.length > 0) {
        $('#rateInfo').text('적용 배율: ' + rateDetails.join(', '));
    } else {
        $('#rateInfo').text('게임을 선택해주세요');
    }
}

/**
 * 베팅 유효성 검증
 */
function validateBet() {
    const hasSelection = Object.values(selectedBets).some(bet => bet !== null);
    
    if (!hasSelection) {
        alert('최소 하나의 게임을 선택해주세요.');
        return false;
    }
    
    const betAmount = parseInt($('#betAmount').val());
    const totalBets = Object.values(selectedBets).filter(bet => bet !== null).length;
    const totalAmount = betAmount * totalBets;
    
    if (!betAmount || betAmount < gameConfig.minBet || betAmount > gameConfig.maxBet) {
        alert(`베팅 금액은 ${number_format(gameConfig.minBet)}P ~ ${number_format(gameConfig.maxBet)}P 사이여야 합니다.`);
        return false;
    }
    
    if (totalAmount > currentUserPoint) {
        alert('보유 포인트가 부족합니다.');
        return false;
    }
    
    return true;
}

/**
 * 베팅 제출
 */
function submitBet() {
    // 선택된 베팅 정보를 배열로 변환
    let bets = {};
    for (let game in selectedBets) {
        if (selectedBets[game]) {
            if (!bets[game]) bets[game] = {};
            bets[game][selectedBets[game]] = 1;
        }
    }
    
    const formData = {
        round_id: gameConfig.roundId,
        round_number: gameConfig.roundNumber,
        bet_amount: parseInt($('#betAmount').val()),
        bets: bets
    };
    
    $('#submitBet').prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i>처리중...');
    
    $.ajax({
        url: './bet_process.php',
        type: 'POST',
        dataType: 'json',
        data: formData,
        success: function(response) {
            if (response.success) {
                // 베팅 내역에 추가
                const betDetails = [];
                for (let game in selectedBets) {
                    if (selectedBets[game]) {
                        betDetails.push({
                            game: game,
                            option: selectedBets[game],
                            amount: formData.bet_amount
                        });
                    }
                }
                
                betHistory.push({
                    bets: betDetails,
                    totalAmount: formData.bet_amount * betDetails.length,
                    time: new Date().toLocaleTimeString()
                });
                
                // 포인트 업데이트
                currentUserPoint = response.new_point;
                updatePointDisplay(currentUserPoint);
                
                // 베팅 내역 표시
                updateBetHistory();
                
                // 폼 리셋 (추가 베팅 가능)
                resetBettingForm();
                
                showNotification('베팅이 완료되었습니다!');
                
            } else {
                alert(response.message || '베팅 처리 중 오류가 발생했습니다.');
                $('#submitBet').prop('disabled', false).html('<i class="bi bi-play-circle me-2"></i>게임신청');
            }
        },
        error: function() {
            alert('서버 통신 오류가 발생했습니다.');
            $('#submitBet').prop('disabled', false).html('<i class="bi bi-play-circle me-2"></i>게임신청');
        }
    });
}

/**
 * 베팅 내역 표시 업데이트
 */
function updateBetHistory() {
    if (betHistory.length === 0) return;
    
    let historyHtml = '<div id="betHistoryArea" class="mt-3 p-3 bg-light rounded"><h6 class="mb-2">이번 회차 베팅 내역:</h6>';
    betHistory.forEach((bet, index) => {
        const betText = bet.bets.map(b => `${b.game}${b.option}`).join(', ');
        historyHtml += `
            <div class="small text-muted mb-1">
                ${index + 1}. ${betText} - ${number_format(bet.totalAmount)}P (${bet.time})
            </div>
        `;
    });
    historyHtml += '</div>';
    
    // 기존 베팅 내역 제거 후 새로 추가
    $('#betHistoryArea').remove();
    $('#bettingForm .card-body').append(historyHtml);
}

/**
 * 제출 버튼 상태 업데이트
 */
function updateSubmitButton() {
    const hasSelection = Object.values(selectedBets).some(bet => bet !== null);
    const validAmount = parseInt($('#betAmount').val()) >= gameConfig.minBet;
    const canSubmit = currentRoundData && 
                     currentRoundData.status === 'betting' && 
                     hasSelection && 
                     validAmount;
    
    $('#submitBet').prop('disabled', !canSubmit);
    
    if (betHistory.length > 0) {
        $('#submitBet').html(`<i class="bi bi-plus-circle me-2"></i>추가 베팅 (${betHistory.length}회 완료)`);
    } else {
        $('#submitBet').html('<i class="bi bi-play-circle me-2"></i>게임신청');
    }
}

// ===================================
// 결과 표시
// ===================================
/**
 * 회차 결과 표시
 * @param {object} result - 게임 결과
 */
function showRoundResult(result) {
    // 현재 회차 결과를 별도 영역에 표시
    showCurrentRoundResult(result);
    
    // 내 베팅 결과 확인
    checkMyBetResults(result);
}

/**
 * 현재 회차 결과를 별도 영역에 표시
 * @param {object} result - 게임 결과
 */
function showCurrentRoundResult(result) {
    // 현재 회차 결과 영역이 없으면 생성
    if ($('#currentRoundResult').length === 0) {
        const currentResultHtml = `
            <div class="card" id="currentRoundResult">
                <div class="card-body">
                    <h6 class="text-muted mb-3">현재 회차 결과</h6>
                    <div class="row text-center" id="currentResultDisplay">
                        <div class="col-4">
                            <div class="result-card border rounded p-3">
                                <h6 class="text-primary mb-2">A 게임</h6>
                                <div class="result-value fs-3 fw-bold" id="gameAResult">-</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="result-card border rounded p-3">
                                <h6 class="text-success mb-2">B 게임</h6>
                                <div class="result-value fs-3 fw-bold" id="gameBResult">-</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="result-card border rounded p-3">
                                <h6 class="text-warning mb-2">C 게임</h6>
                                <div class="result-value fs-3 fw-bold" id="gameCResult">-</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        $('.dice-result').after(currentResultHtml);
    }
    
    // 결과 표시 (애니메이션 효과)
    setTimeout(() => {
        $('#gameAResult').text('A' + result.game_a_result).addClass('animate__animated animate__bounceIn');
    }, 100);
    
    setTimeout(() => {
        $('#gameBResult').text('B' + result.game_b_result).addClass('animate__animated animate__bounceIn');
    }, 300);
    
    setTimeout(() => {
        $('#gameCResult').text('C' + result.game_c_result).addClass('animate__animated animate__bounceIn');
    }, 500);
}

/**
 * 내 베팅 결과 확인
 * @param {object} result - 게임 결과
 */
function checkMyBetResults(result) {
    if (betHistory.length === 0) return;
    
    let totalWinAmount = 0;
    let winDetails = [];
    
    betHistory.forEach(history => {
        history.bets.forEach(bet => {
            let isWin = false;
            let winRate = 1;
            
            // 게임별 당첨 확인
            if (bet.game === 'A' && bet.option == result.game_a_result) {
                isWin = true;
                winRate = gameRates.A[bet.option];
            } else if (bet.game === 'B' && bet.option == result.game_b_result) {
                isWin = true;
                winRate = gameRates.B[bet.option];
            } else if (bet.game === 'C' && bet.option == result.game_c_result) {
                isWin = true;
                winRate = gameRates.C[bet.option];
            }
            
            if (isWin) {
                const winAmount = Math.floor(bet.amount * winRate);
                totalWinAmount += winAmount;
                winDetails.push(`${bet.game}${bet.option} (${number_format(winAmount)}P)`);
            }
        });
    });
    
    if (winDetails.length > 0) {
        showNotification(`🎉 축하합니다! 당첨 내역: ${winDetails.join(', ')}, 총 당첨금: ${number_format(totalWinAmount)}P`);
        // 당첨 효과
        $('#currentRoundResult').addClass('success-flash');
        setTimeout(() => $('#currentRoundResult').removeClass('success-flash'), 600);
    } else if (betHistory.length > 0) {
        showNotification('😢 아쉽습니다. 다음 기회에!');
    }
}

// ===================================
// UI 제어 함수
// ===================================
/**
 * 베팅 활성화
 */
function enableBetting() {
    $('.bet-button').prop('disabled', false);
    $('#betAmount').prop('disabled', false);
    updateSubmitButton();
}

/**
 * 베팅 비활성화
 */
function disableBetting() {
    $('.bet-button').prop('disabled', true);
    $('#betAmount').prop('disabled', true);
    $('#submitBet').prop('disabled', true);
}

/**
 * 회차 표시 업데이트
 */
function updateRoundDisplay(roundNumber) {
    $('h5:contains("회차")').text(roundNumber + '회차');
    $('#roundId').val(gameConfig.roundId);
    $('#roundNumber').val(gameConfig.roundNumber);
}

/**
 * 새 회차를 위한 리셋 - 이전 결과는 보존
 */
function resetForNewRound() {
    // 베팅 관련 데이터만 리셋
    betHistory = [];
    selectedBets = { A: null, B: null, C: null };
    $('.bet-button').removeClass('active');
    $('#betAmount').val('');
    $('#betHistoryArea').remove();
    $('#currentRoundResult').remove();
    
    updateSelectionDisplay();
    updateExpectedWin();
}

/**
 * 베팅 폼만 리셋 (중복 베팅용)
 */
function resetBettingForm() {
    selectedBets = { A: null, B: null, C: null };
    $('.bet-button').removeClass('active');
    $('#betAmount').val('');
    
    updateSelectionDisplay();
    updateExpectedWin();
    updateSubmitButton();
}

/**
 * 포인트 표시 업데이트
 */
function updatePointDisplay(point) {
    $('#userMoney').text(number_format(point) + 'P').addClass('point-change');
    setTimeout(() => $('#userMoney').removeClass('point-change'), 800);
    
    // 게임 설정도 업데이트
    gameConfig.userPoint = point;
    gameConfig.maxBet = Math.min(gameConfig.maxBet, point);
    
    // 베팅 입력 최대값 업데이트
    $('#betAmount').attr('max', gameConfig.maxBet);
}

/**
 * 알림 메시지 표시
 */
function showNotification(message) {
    const toastHtml = `
        <div class="toast align-items-center text-white bg-info border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    if (!$('#toast-container').length) {
        $('body').append('<div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3"></div>');
    }
    
    const $toast = $(toastHtml);
    $('#toast-container').append($toast);
    
    const toast = new bootstrap.Toast($toast[0]);
    toast.show();
    
    $toast.on('hidden.bs.toast', function() {
        $(this).remove();
    });
}

/**
 * 숫자 포맷팅
 */
function number_format(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

// ===================================
// 페이지 종료 시 정리
// ===================================
$(window).on('beforeunload', function() {
    if (countdownTimer) {
        clearInterval(countdownTimer);
    }
    if (statusCheckTimer) {
        clearInterval(statusCheckTimer);
    }
});