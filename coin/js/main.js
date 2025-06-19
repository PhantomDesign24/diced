/*
 * 파일명: main.js
 * 위치: /js/main.js
 * 기능: 메인 애플리케이션 초기화 및 공통 기능
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
// 메인 초기화
// ===================================

/* 애플리케이션 초기화 */
$(document).ready(function() {
    console.log('블록체인 지갑 분석기 초기화 시작');
    
    // 부트스트랩 툴팁 초기화
    initializeTooltips();
    
    // 네트워크 선택 UI 개선
    enhanceNetworkSelection();
    
    // 클립보드 복사 기능
    initializeClipboard();
    
    // 페이지 애니메이션
    initializeAnimations();
    
    console.log('초기화 완료');
});

// ===================================
// UI 개선 기능
// ===================================

/* 툴팁 초기화 */
function initializeTooltips() {
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
}

/* 네트워크 선택 UI 개선 */
function enhanceNetworkSelection() {
    $('.network-option').on('click', function() {
        // 라디오 버튼 체크
        $(this).find('input[type="radio"]').prop('checked', true).trigger('change');
        
        // 시각적 피드백
        $('.network-option').removeClass('border-primary bg-light');
        $(this).addClass('border-primary bg-light');
    });
}

/* 클립보드 복사 기능 */
function initializeClipboard() {
    // 지갑 주소 클릭 시 전체 주소 복사
    $(document).on('click', '#walletAddress', function() {
        const fullAddress = $(this).attr('title');
        if (fullAddress) {
            copyToClipboard(fullAddress);
            showInfo('주소가 클립보드에 복사되었습니다.');
        }
    });
    
    // 트랜잭션 해시 복사
    $(document).on('click', '.tx-hash', function(e) {
        if (!$(this).is('a')) {
            const text = $(this).text();
            copyToClipboard(text);
            showInfo('복사되었습니다.');
            e.preventDefault();
        }
    });
}

/* 클립보드에 복사 */
function copyToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text);
    } else {
        // 구형 브라우저 대응
        const textArea = document.createElement("textarea");
        textArea.value = text;
        textArea.style.position = "fixed";
        textArea.style.left = "-999999px";
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
        } catch (error) {
            console.error('복사 실패:', error);
        }
        document.body.removeChild(textArea);
    }
}

/* 페이지 애니메이션 초기화 */
function initializeAnimations() {
    // 스크롤 애니메이션
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -100px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);
    
    // 애니메이션 대상 요소 관찰
    document.querySelectorAll('.card').forEach(el => {
        observer.observe(el);
    });
}

// ===================================
// 유틸리티 함수
// ===================================

/* 숫자 포맷팅 */
function formatNumber(num, decimals = 2) {
    return parseFloat(num).toLocaleString('ko-KR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
}

/* 날짜 포맷팅 */
function formatDate(timestamp) {
    const date = new Date(timestamp);
    return date.toLocaleString('ko-KR');
}

/* 주소 축약 */
function shortenAddress(address, chars = 4) {
    return `${address.substring(0, chars + 2)}...${address.substring(address.length - chars)}`;
}

/* 디바운스 함수 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/* 쓰로틀 함수 */
function throttle(func, limit) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// ===================================
// 네트워크 상태 확인
// ===================================

/* 온라인/오프라인 상태 감지 */
window.addEventListener('online', function() {
    showSuccess('네트워크가 연결되었습니다.');
});

window.addEventListener('offline', function() {
    showError('네트워크 연결이 끊어졌습니다.');
});

// ===================================
// 개발자 도구 감지 (선택사항)
// ===================================

/* 개발자 도구 열림 감지 */
let devtools = {open: false, orientation: null};
const threshold = 160;

setInterval(function() {
    if (window.outerHeight - window.innerHeight > threshold || 
        window.outerWidth - window.innerWidth > threshold) {
        if (!devtools.open) {
            devtools.open = true;
            console.log('%c개발자 도구가 열렸습니다', 'color: red; font-size: 16px;');
            console.log('%c보안 경고: 이 콘솔을 사용하여 의심스러운 코드를 실행하지 마세요!', 'color: red; font-weight: bold;');
        }
    } else {
        devtools.open = false;
    }
}, 500);

// ===================================
// 성능 모니터링
// ===================================

/* 페이지 로드 성능 측정 */
window.addEventListener('load', function() {
    if (window.performance && window.performance.timing) {
        const loadTime = window.performance.timing.loadEventEnd - window.performance.timing.navigationStart;
        console.log(`페이지 로드 시간: ${loadTime}ms`);
    }
});