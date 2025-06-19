/*
 * 파일명: main.js
 * 위치: /assets/js/main.js
 * 기능: 메인 스크립트 및 공통 기능
 * 작성일: 2024-12-27
 */

// ===================================
// 전역 설정
// ===================================

/* 전역 에러 핸들러 */
window.addEventListener('error', function(event) {
    console.error('전역 에러:', event.error);
    hideLoading();
});

/* Promise 거부 핸들러 */
window.addEventListener('unhandledrejection', function(event) {
    console.error('처리되지 않은 Promise 거부:', event.reason);
    hideLoading();
});

// ===================================
// 초기화
// ===================================

/* 메인 초기화 */
$(document).ready(function() {
    console.log('토큰 스왑 시스템 초기화');
    
    // 네트워크 선택 UI
    enhanceNetworkSelection();
    
    // 클립보드 복사
    initClipboard();
    
    // 주소 복사 버튼
    initAddressCopy();
    
    // 실시간 업데이트
    if (typeof currentAccount !== 'undefined' && currentAccount) {
        startRealtimeUpdates();
    }
});

// ===================================
// UI 개선
// ===================================

/* 네트워크 선택 UI 개선 */
function enhanceNetworkSelection() {
    $('.network-option').on('click', function() {
        $(this).find('input[type="radio"]').prop('checked', true).trigger('change');
        
        $('.network-option').removeClass('border-primary bg-light');
        $(this).addClass('border-primary bg-light');
    });
}

/* 클립보드 초기화 */
function initClipboard() {
    // 지갑 주소 클릭 시 복사
    $(document).on('click', '#userWalletAddress, #mainWalletAddress', function() {
        const address = $(this).attr('title') || $(this).text();
        copyToClipboard(address);
    });
}

/* 주소 복사 버튼 */
function initAddressCopy() {
    window.copyAddress = function(address) {
        copyToClipboard(address);
    };
}

/* 클립보드에 복사 */
function copyToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(() => {
            showInfo('주소가 복사되었습니다.');
        });
    } else {
        // 구형 브라우저 대응
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        
        try {
            document.execCommand('copy');
            showInfo('주소가 복사되었습니다.');
        } catch (err) {
            showError('복사에 실패했습니다.');
        }
        
        document.body.removeChild(textarea);
    }
}

// ===================================
// 실시간 업데이트
// ===================================

/* 실시간 업데이트 시작 */
function startRealtimeUpdates() {
    // 30초마다 잔액 업데이트
    setInterval(() => {
        if (typeof updateBalances === 'function') {
            updateBalances();
        }
    }, 30000);
    
    // 1분마다 거래 내역 업데이트
    setInterval(() => {
        if (typeof loadTransactionHistory === 'function') {
            loadTransactionHistory();
        }
    }, 60000);
}

// ===================================
// 숫자 포맷팅
// ===================================

/* 숫자 포맷팅 */
window.formatNumber = function(num, decimals = 4) {
    return parseFloat(num).toLocaleString('ko-KR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
};

/* 큰 숫자 축약 */
window.abbreviateNumber = function(num) {
    if (num >= 1e9) return (num / 1e9).toFixed(2) + 'B';
    if (num >= 1e6) return (num / 1e6).toFixed(2) + 'M';
    if (num >= 1e3) return (num / 1e3).toFixed(2) + 'K';
    return num.toFixed(2);
};

// ===================================
// 네트워크 상태
// ===================================

/* 온라인/오프라인 감지 */
window.addEventListener('online', function() {
    showSuccess('네트워크가 연결되었습니다.');
});

window.addEventListener('offline', function() {
    showError('네트워크 연결이 끊어졌습니다.');
});

// ===================================
// 보안
// ===================================

/* 페이지 이탈 경고 */
let isTransactionPending = false;

window.setTransactionPending = function(pending) {
    isTransactionPending = pending;
};

window.addEventListener('beforeunload', function(e) {
    if (isTransactionPending) {
        const message = '진행 중인 트랜잭션이 있습니다. 페이지를 나가시겠습니까?';
        e.returnValue = message;
        return message;
    }
});

// ===================================
// 디버그
// ===================================

/* 디버그 모드 */
const DEBUG = true; // 프로덕션에서는 false로 변경

if (DEBUG) {
    console.log('디버그 모드 활성화');
    console.log('CONFIG:', CONFIG);
}

// ===================================
// 성능 모니터링
// ===================================

/* 페이지 로드 성능 */
window.addEventListener('load', function() {
    if (window.performance && window.performance.timing) {
        const loadTime = window.performance.timing.loadEventEnd - 
                        window.performance.timing.navigationStart;
        console.log(`페이지 로드 시간: ${loadTime}ms`);
    }
});