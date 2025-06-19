/*
* 파일명: game.js
* 위치: /game/js/game.js
* 기능: 서버 중심 게임 클라이언트 (이전결과 보존)
* 작성일: 2025-06-12
* 수정일: 2025-06-12
*/

// ===================================
// 전역 변수
// ===================================
let selectedHighLow = '';
let selectedOddEven = '';
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
        gameInterval: 120
    };
}

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
        
        const group = $(this).data('group');
        const value = $(this).data('value');
        
        selectBetOption(group, value, $(this));
    });
    
    // 베팅 금액 입력
    $('#betAmount').on('input', function() {
        updateTotalAmount();
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
function selectBetOption(group, value, button) {
    $(`[data-group="${group}"]`).removeClass('active');
    button.addClass('active');
    
    if (group === 'high_low') {
        selectedHighLow = value;
        $('#selectedHighLow').val(value);
    } else if (group === 'odd_even') {
        selectedOddEven = value;
        $('#selectedOddEven').val(value);
    }
    
    updateSelectionDisplay();
    updateSubmitButton();
}

/**
 * 현재 선택 표시 업데이트
 */
function updateSelectionDisplay() {
    let display = [];
    
    if (selectedHighLow) {
        display.push(selectedHighLow === 'high' ? '대' : '소');
    }
    
    if (selectedOddEven) {
        display.push(selectedOddEven === 'odd' ? '홀' : '짝');
    }
    
    const displayText = display.length > 0 ? display.join(' ') : '선택 안함';
    $('#currentSelection').text(displayText);
}

/**
 * 합산 금액 업데이트
 */
function updateTotalAmount() {
    const betAmount = parseInt($('#betAmount').val()) || 0;
    $('#totalBetAmount').text(number_format(betAmount) + 'P');
}

/**
 * 베팅 유효성 검증
 */
function validateBet() {
    if (!selectedHighLow || !selectedOddEven) {
        alert('대소와 홀짝을 모두 선택해주세요.');
        return false;
    }
    
    const betAmount = parseInt($('#betAmount').val());
    if (!betAmount || betAmount < gameConfig.minBet || betAmount > gameConfig.maxBet) {
        alert(`베팅 금액은 ${number_format(gameConfig.minBet)}P ~ ${number_format(gameConfig.maxBet)}P 사이여야 합니다.`);
        return false;
    }
    
    if (betAmount > currentUserPoint) {
        alert('보유 포인트가 부족합니다.');
        return false;
    }
    
    return true;
}
/**
 * 베팅 제출
 */
function submitBet() {
    const formData = {
        round_id: gameConfig.roundId,
        round_number: gameConfig.roundNumber,
        high_low: selectedHighLow,
        odd_even: selectedOddEven,
        bet_amount: parseInt($('#betAmount').val())
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
                betHistory.push({
                    high_low: selectedHighLow,
                    odd_even: selectedOddEven,
                    amount: formData.bet_amount,
                    time: new Date().toLocaleTimeString()
                });
                
                // 포인트 업데이트
                currentUserPoint = response.new_point;
                updatePointDisplay(currentUserPoint);
                
                // 베팅 내역 표시
                updateBetHistory();
                
                // 폼 리셋 (추가 베팅 가능)
                resetBettingForm();
                
                // 당첨 시 예상 당첨금 표시
                const expectedWin = Math.floor(formData.bet_amount * gameConfig.winRateHighLow * gameConfig.winRateOddEven);
                showNotification(`베팅이 완료되었습니다! 예상 당첨금: ${number_format(expectedWin)}P`);
                
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
        const highLowText = bet.high_low === 'high' ? '대' : '소';
        const oddEvenText = bet.odd_even === 'odd' ? '홀' : '짝';
        historyHtml += `
            <div class="small text-muted mb-1">
                ${index + 1}. ${highLowText} ${oddEvenText} - ${number_format(bet.amount)}P (${bet.time})
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
    const canSubmit = currentRoundData && 
                     currentRoundData.status === 'betting' && 
                     selectedHighLow && 
                     selectedOddEven && 
                     parseInt($('#betAmount').val()) >= gameConfig.minBet;
    
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
 * @param {object} result - 주사위 결과
 */
function showRoundResult(result) {
    // 현재 회차 결과를 별도 영역에 표시 (이전 결과와 분리)
    showCurrentRoundResult(result);
    
    // 내 베팅 결과 확인
    checkMyBetResults(result);
}

/**
 * 현재 회차 결과를 별도 영역에 표시
 * @param {object} result - 주사위 결과
 */
function showCurrentRoundResult(result) {
    // 현재 회차 결과 영역이 없으면 생성
    if ($('#currentRoundResult').length === 0) {
        const currentResultHtml = `
            <div class="card" id="currentRoundResult">
                <div class="card-body">
                    <h6 class="text-muted mb-3">현재 회차 결과</h6>
                    <div class="dice-container" id="currentDiceContainer"></div>
                    <div class="result-display" id="currentResultDisplay"></div>
                </div>
            </div>
        `;
        $('.dice-result').after(currentResultHtml);
    }
    
    // 주사위 표시
    updateCurrentRoundDice(result.dice1, result.dice2, result.dice3);
    
    // 결과 요약 표시
    const resultSummary = `${result.total} ${result.is_high ? '대' : '소'} ${result.is_odd ? '홀' : '짝'}`;
    $('#currentResultDisplay .result-summary').text(resultSummary);
    
    if ($('#currentResultDisplay .result-summary').length === 0) {
        $('#currentResultDisplay').html(`<div class="result-summary">${resultSummary}</div>`);
    }
}

/**
 * 현재 회차 주사위 표시 업데이트
 */
function updateCurrentRoundDice(dice1, dice2, dice3) {
    const diceValues = [dice1, dice2, dice3];
    
    let diceHtml = '';
    diceValues.forEach((value, index) => {
        diceHtml += `<div class="dice dice-${value} rolling" id="currentDice${index + 1}">${getDiceDotsHtml(value)}</div>`;
    });
    
    $('#currentDiceContainer').html(diceHtml);
    
    // 애니메이션 제거 (1초 후)
    setTimeout(() => {
        $('.dice.rolling').removeClass('rolling');
    }, 1000);
}

/**
 * 내 베팅 결과 확인
 * @param {object} result - 주사위 결과
 */
function checkMyBetResults(result) {
    if (betHistory.length === 0) return;
    
    let winCount = 0;
    let totalWinAmount = 0;
    
    betHistory.forEach(bet => {
        const highLowCorrect = (bet.high_low === 'high' && result.is_high) || 
                              (bet.high_low === 'low' && !result.is_high);
        const oddEvenCorrect = (bet.odd_even === 'odd' && result.is_odd) || 
                              (bet.odd_even === 'even' && !result.is_odd);
        
        if (highLowCorrect && oddEvenCorrect) {
            winCount++;
            totalWinAmount += Math.floor(bet.amount * 3.8); // 1.95 * 1.95 = 3.8배
        }
    });
    
    if (winCount > 0) {
        showNotification(`🎉 축하합니다! ${winCount}개 베팅 당첨! 예상 당첨금: ${number_format(totalWinAmount)}P`);
        // 당첨 효과
        $('#currentRoundResult').addClass('success-flash');
        setTimeout(() => $('#currentRoundResult').removeClass('success-flash'), 600);
    } else if (betHistory.length > 0) {
        showNotification('😢 아쉽습니다. 다음 기회에!');
    }
}

/**
 * 주사위 점 패턴 HTML 생성
 */
function getDiceDotsHtml(number) {
    let dots = '';
    for (let i = 0; i < number; i++) {
        dots += '<div class="dice-dot"></div>';
    }
    return dots;
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
    // 베팅 관련 데이터만 리셋 (이전 결과는 보존)
    betHistory = [];
    selectedHighLow = '';
    selectedOddEven = '';
    $('.bet-button').removeClass('active');
    $('#betAmount').val('');
    $('#selectedHighLow').val('');
    $('#selectedOddEven').val('');
    $('#betHistoryArea').remove(); // 베팅 내역만 제거
    $('#currentRoundResult').remove(); // 현재 회차 결과만 제거
    
    updateSelectionDisplay();
    updateTotalAmount();
}

/**
 * 베팅 폼만 리셋 (중복 베팅용)
 */
function resetBettingForm() {
    selectedHighLow = '';
    selectedOddEven = '';
    $('.bet-button').removeClass('active');
    $('#betAmount').val('');
    $('#selectedHighLow').val('');
    $('#selectedOddEven').val('');
    
    updateSelectionDisplay();
    updateTotalAmount();
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