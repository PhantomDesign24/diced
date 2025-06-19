/*
 * 파일명: wallet.js
 * 위치: /assets/js/wallet.js
 * 기능: 지갑 연결 및 관리
 * 작성일: 2024-12-27
 */

// ===================================
// 전역 변수
// ===================================
let currentAccount = null;
let currentNetwork = 'ethereum';
let web3Instance = null;
let tronWebInstance = null;

// ===================================
// 지갑 연결 초기화
// ===================================

/* 초기화 함수 */
function initWallet() {
    // localStorage에서 마지막 네트워크 복원
    const savedNetwork = localStorage.getItem('selectedNetwork');
    if (savedNetwork && (savedNetwork === 'ethereum' || savedNetwork === 'tron')) {
        currentNetwork = savedNetwork;
        $(`input[name="network"][value="${savedNetwork}"]`).prop('checked', true);
        $(`.network-option[data-network="${savedNetwork}"]`).addClass('border-primary bg-light');
    }
    
    // 네트워크 선택 이벤트
    $('input[name="network"]').on('change', function() {
        currentNetwork = $(this).val();
        console.log('선택된 네트워크:', currentNetwork);
        
        // 선택한 네트워크 저장
        localStorage.setItem('selectedNetwork', currentNetwork);
        
        // 네트워크 변경 시 지갑 상태 초기화
        if (currentAccount) {
            disconnectWallet();
        }
        
        // 새 네트워크에서 자동 연결 시도
        setTimeout(() => {
            checkWalletConnection();
        }, 500);
    });

    // 지갑 연결 버튼 이벤트
    $('#connectWallet').on('click', async function() {
        await connectWallet();
    });
    
    // 페이지 로드 시 지갑 상태 확인 (약간의 딜레이 후)
    setTimeout(() => {
        checkWalletConnection();
    }, 1000);
}

/* 지갑 연결 상태 확인 */
async function checkWalletConnection() {
    // 이더리움 (MetaMask)
    if (currentNetwork === 'ethereum' && typeof window.ethereum !== 'undefined') {
        try {
            const accounts = await window.ethereum.request({ method: 'eth_accounts' });
            if (accounts.length > 0) {
                // 이미 연결된 계정이 있으면 자동 연결
                currentAccount = accounts[0];
                web3Instance = new Web3(window.ethereum);
                
                // UI 업데이트
                updateWalletUI(currentAccount);
                
                // 이벤트 리스너 등록
                window.ethereum.on('accountsChanged', handleAccountsChanged);
                window.ethereum.on('chainChanged', () => window.location.reload());
                
                // 스왑 섹션 활성화
                enableSwapSection();
                
                console.log('기존 MetaMask 연결 복원:', currentAccount);
            }
        } catch (error) {
            console.error('지갑 상태 확인 오류:', error);
        }
    }
    
    // 트론 (TronLink)
    if (currentNetwork === 'tron' && typeof window.tronWeb !== 'undefined' && window.tronWeb.ready) {
        try {
            tronWebInstance = window.tronWeb;
            const account = tronWebInstance.defaultAddress.base58;
            
            if (account) {
                currentAccount = account;
                
                // UI 업데이트
                updateWalletUI(currentAccount);
                
                // 계정 변경 감지
                setInterval(checkTronAccountChange, 1000);
                
                // 스왑 섹션 활성화
                enableSwapSection();
                
                console.log('기존 TronLink 연결 복원:', currentAccount);
            }
        } catch (error) {
            console.error('TronLink 상태 확인 오류:', error);
        }
    }
}

/* 지갑 연결 */
async function connectWallet() {
    showLoading();
    
    try {
        if (currentNetwork === 'ethereum') {
            await connectMetaMask();
        } else if (currentNetwork === 'tron') {
            await connectTronLink();
        }
    } catch (error) {
        console.error('지갑 연결 오류:', error);
        showError('지갑 연결 실패: ' + error.message);
    } finally {
        hideLoading();
    }
}

// ===================================
// MetaMask 연결
// ===================================

/* MetaMask 연결 */
async function connectMetaMask() {
    // Web3 라이브러리 확인
    if (typeof Web3 === 'undefined') {
        throw new Error('Web3 라이브러리가 로드되지 않았습니다. 페이지를 새로고침해주세요.');
    }
    
    if (typeof window.ethereum === 'undefined') {
        throw new Error('MetaMask가 설치되어 있지 않습니다.');
    }

    try {
        // 계정 요청
        const accounts = await window.ethereum.request({ 
            method: 'eth_requestAccounts' 
        });
        
        if (accounts.length === 0) {
            throw new Error('연결할 계정이 없습니다.');
        }

        // Web3 인스턴스 생성 (1.x 버전 방식)
        web3Instance = new Web3(window.ethereum);
        currentAccount = accounts[0];

        // 네트워크 확인
        const networkId = await web3Instance.eth.net.getId();
        console.log('네트워크 ID:', networkId);
        
        // 메인넷이 아닌 경우 경고
        if (networkId !== 1) {
            showWarning('이더리움 메인넷에 연결해주세요.');
        }

        // UI 업데이트
        updateWalletUI(currentAccount);
        
        // 이벤트 리스너 등록
        window.ethereum.on('accountsChanged', handleAccountsChanged);
        window.ethereum.on('chainChanged', () => window.location.reload());
        
        showSuccess('MetaMask 지갑이 연결되었습니다.');
        
        // 스왑 섹션 활성화
        enableSwapSection();
        
    } catch (error) {
        throw new Error('MetaMask 연결 실패: ' + error.message);
    }
}

/* 계정 변경 핸들러 */
function handleAccountsChanged(accounts) {
    if (accounts.length === 0) {
        disconnectWallet();
    } else {
        currentAccount = accounts[0];
        updateWalletUI(currentAccount);
        // updateBalances는 swap.js에 있으므로 존재 여부 확인
        if (typeof updateBalances === 'function') {
            updateBalances();
        }
    }
}

// ===================================
// TronLink 연결
// ===================================

/* TronLink 연결 */
async function connectTronLink() {
    if (typeof window.tronWeb === 'undefined') {
        throw new Error('TronLink가 설치되어 있지 않습니다.');
    }

    // TronLink 준비 대기
    let attempts = 0;
    while (!window.tronWeb.ready && attempts < 10) {
        await new Promise(resolve => setTimeout(resolve, 200));
        attempts++;
    }

    if (!window.tronWeb.ready) {
        throw new Error('TronLink가 준비되지 않았습니다.');
    }

    try {
        tronWebInstance = window.tronWeb;
        
        const account = tronWebInstance.defaultAddress.base58;
        if (!account) {
            throw new Error('TronLink에 로그인해주세요.');
        }

        currentAccount = account;
        
        // UI 업데이트
        updateWalletUI(currentAccount);
        
        // 계정 변경 감지
        setInterval(checkTronAccountChange, 1000);
        
        showSuccess('TronLink 지갑이 연결되었습니다.');
        
        // 스왑 섹션 활성화
        enableSwapSection();
        
    } catch (error) {
        throw new Error('TronLink 연결 실패: ' + error.message);
    }
}

/* TronLink 계정 변경 감지 */
let lastTronAccount = null;
function checkTronAccountChange() {
    if (window.tronWeb && window.tronWeb.defaultAddress.base58) {
        const currentTronAccount = window.tronWeb.defaultAddress.base58;
        if (lastTronAccount && lastTronAccount !== currentTronAccount) {
            currentAccount = currentTronAccount;
            updateWalletUI(currentAccount);
            if (typeof updateBalances === 'function') {
                updateBalances();
            }
        }
        lastTronAccount = currentTronAccount;
    }
}

// ===================================
// UI 업데이트
// ===================================

/* 지갑 UI 업데이트 */
function updateWalletUI(address) {
    // 주소 표시
    const shortAddress = shortenAddress(address);
    $('#userWalletAddress').text(shortAddress);
    $('#userWalletAddress').attr('title', address);
    
    // 네비게이션 지갑 상태
    $('#walletStatus').html(`<i class="bi bi-wallet2"></i> ${shortAddress}`);
    
    // 지갑 정보 표시
    $('#walletInfo').removeClass('d-none');
    
    // 연결 버튼 변경
    $('#connectWallet').html('<i class="bi bi-check-circle"></i> 지갑 연결됨')
                       .prop('disabled', true)
                       .removeClass('btn-primary')
                       .addClass('btn-success');
}

/* 지갑 연결 해제 */
function disconnectWallet() {
    currentAccount = null;
    web3Instance = null;
    tronWebInstance = null;
    
    $('#walletInfo').addClass('d-none');
    $('#swapSection').addClass('d-none');
    $('#walletStatus').html('<i class="bi bi-wallet2"></i> 지갑 미연결');
    
    $('#connectWallet').html('<i class="bi bi-wallet2"></i> 지갑 연결하기')
                       .prop('disabled', false)
                       .removeClass('btn-success')
                       .addClass('btn-primary');
    
    showInfo('지갑 연결이 해제되었습니다.');
}

/* 스왑 섹션 활성화 */
function enableSwapSection() {
    $('#swapSection').removeClass('d-none');
    
    // swap.js의 함수들이 로드되었는지 확인
    if (typeof updateBalances === 'function') {
        updateBalances();
    }
    
    if (typeof loadTransactionHistory === 'function') {
        loadTransactionHistory();
    }
}

// ===================================
// 유틸리티 함수
// ===================================

/* 주소 축약 */
function shortenAddress(address, chars = 6) {
    return address.substring(0, chars + 2) + '...' + address.substring(address.length - chars);
}

/* 로딩 표시 */
function showLoading() {
    $('.loading-spinner').show();
}

/* 로딩 숨김 */
function hideLoading() {
    $('.loading-spinner').hide();
}

/* 성공 메시지 */
function showSuccess(message) {
    showToast(message, 'success');
}

/* 에러 메시지 */
function showError(message) {
    showToast(message, 'danger');
}

/* 경고 메시지 */
function showWarning(message) {
    showToast(message, 'warning');
}

/* 정보 메시지 */
function showInfo(message) {
    showToast(message, 'info');
}

/* 토스트 메시지 표시 */
function showToast(message, type = 'info') {
    const toast = `
        <div class="toast align-items-center text-white bg-${type} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    // 토스트 컨테이너가 없으면 생성
    if ($('.toast-container').length === 0) {
        $('body').append('<div class="toast-container position-fixed top-0 end-0 p-3"></div>');
    }
    
    const $toast = $(toast);
    $('.toast-container').append($toast);
    
    const bsToast = new bootstrap.Toast($toast[0]);
    bsToast.show();
    
    // 5초 후 자동 제거
    setTimeout(() => {
        $toast.remove();
    }, 5000);
}

// ===================================
// 초기화
// ===================================

/* 문서 준비 시 초기화 */
$(document).ready(function() {
    initWallet();
});