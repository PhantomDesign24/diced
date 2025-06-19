/*
 * 파일명: wallet.js
 * 위치: /js/wallet.js
 * 기능: 지갑 연결 및 관리 기능
 * 작성일: 2024-12-27
 */

// ===================================
// 전역 변수
// ===================================
let currentNetwork = 'ethereum';
let currentAccount = null;
let web3Instance = null;
let tronWebInstance = null;

// ===================================
// 지갑 연결 관리
// ===================================

/* 지갑 연결 초기화 */
async function initWalletConnection() {
    // 네트워크 선택 이벤트
    $('input[name="network"]').on('change', function() {
        currentNetwork = $(this).val();
        console.log('선택된 네트워크:', currentNetwork);
    });

    // 지갑 연결 버튼 이벤트
    $('#connectWallet').on('click', async function() {
        await connectWallet();
    });
}

/* 지갑 연결 함수 */
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
        showError('지갑 연결에 실패했습니다: ' + error.message);
    } finally {
        hideLoading();
    }
}

// ===================================
// MetaMask (이더리움) 연결
// ===================================

/* MetaMask 연결 */
async function connectMetaMask() {
    // MetaMask 설치 확인
    if (typeof window.ethereum === 'undefined') {
        throw new Error('MetaMask가 설치되어 있지 않습니다. MetaMask를 설치해주세요.');
    }

    try {
        // 계정 요청
        const accounts = await window.ethereum.request({ 
            method: 'eth_requestAccounts' 
        });
        
        if (accounts.length === 0) {
            throw new Error('연결할 계정이 없습니다.');
        }

        // Web3 인스턴스 생성
        web3Instance = new Web3(window.ethereum);
        currentAccount = accounts[0];

        // 네트워크 확인
        const chainId = await web3Instance.eth.getChainId();
        console.log('현재 체인 ID:', chainId);

        // UI 업데이트
        updateWalletUI(currentAccount, 'Ethereum');
        
        // 계정 변경 감지
        window.ethereum.on('accountsChanged', handleAccountsChanged);
        
        // 네트워크 변경 감지
        window.ethereum.on('chainChanged', handleChainChanged);
        
        showSuccess('MetaMask 지갑이 연결되었습니다.');
        
    } catch (error) {
        throw new Error('MetaMask 연결 실패: ' + error.message);
    }
}

/* MetaMask 계정 변경 핸들러 */
function handleAccountsChanged(accounts) {
    if (accounts.length === 0) {
        // 연결 해제
        disconnectWallet();
    } else {
        currentAccount = accounts[0];
        updateWalletUI(currentAccount, 'Ethereum');
    }
}

/* MetaMask 체인 변경 핸들러 */
function handleChainChanged(chainId) {
    // 페이지 새로고침
    window.location.reload();
}

// ===================================
// TronLink (트론) 연결
// ===================================

/* TronLink 연결 */
async function connectTronLink() {
    // TronLink 설치 확인
    if (typeof window.tronWeb === 'undefined') {
        throw new Error('TronLink가 설치되어 있지 않습니다. TronLink를 설치해주세요.');
    }

    // TronLink 준비 대기
    let attempts = 0;
    while (!window.tronWeb.ready && attempts < 10) {
        await new Promise(resolve => setTimeout(resolve, 200));
        attempts++;
    }

    if (!window.tronWeb.ready) {
        throw new Error('TronLink가 준비되지 않았습니다. 잠시 후 다시 시도해주세요.');
    }

    try {
        // TronWeb 인스턴스 저장
        tronWebInstance = window.tronWeb;
        
        // 현재 계정 가져오기
        const account = tronWebInstance.defaultAddress.base58;
        if (!account) {
            throw new Error('TronLink에 로그인해주세요.');
        }

        currentAccount = account;

        // UI 업데이트
        updateWalletUI(currentAccount, 'Tron');
        
        // 계정 변경 감지
        setInterval(checkTronAccountChange, 1000);
        
        showSuccess('TronLink 지갑이 연결되었습니다.');
        
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
            updateWalletUI(currentAccount, 'Tron');
        }
        lastTronAccount = currentTronAccount;
    }
}

// ===================================
// UI 업데이트
// ===================================

/* 지갑 UI 업데이트 */
function updateWalletUI(address, network) {
    // 주소 표시 (축약)
    const shortAddress = address.substring(0, 6) + '...' + address.substring(address.length - 4);
    
    $('#walletAddress').text(shortAddress);
    $('#walletAddress').attr('title', address); // 전체 주소는 툴팁으로
    
    // 지갑 정보 표시
    $('#walletInfo').removeClass('d-none');
    $('#analysisSection').removeClass('d-none').addClass('fade-in');
    
    // 연결 버튼 텍스트 변경
    $('#connectWallet').html('<i class="bi bi-check-circle"></i> ' + network + ' 연결됨');
    $('#connectWallet').prop('disabled', true);
}

/* 지갑 연결 해제 */
function disconnectWallet() {
    currentAccount = null;
    web3Instance = null;
    tronWebInstance = null;
    
    $('#walletInfo').addClass('d-none');
    $('#analysisSection').addClass('d-none');
    $('#connectWallet').html('<i class="bi bi-link-45deg"></i> 지갑 연결');
    $('#connectWallet').prop('disabled', false);
    
    showInfo('지갑 연결이 해제되었습니다.');
}

// ===================================
// 유틸리티 함수
// ===================================

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
    const alert = `
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    $('.main-container').prepend(alert);
    setTimeout(() => $('.alert').fadeOut(), 5000);
}

/* 에러 메시지 */
function showError(message) {
    const alert = `
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-circle"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    $('.main-container').prepend(alert);
}

/* 정보 메시지 */
function showInfo(message) {
    const alert = `
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="bi bi-info-circle"></i> ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    $('.main-container').prepend(alert);
    setTimeout(() => $('.alert').fadeOut(), 5000);
}

// ===================================
// 초기화
// ===================================

/* 문서 준비 시 초기화 */
$(document).ready(function() {
    initWalletConnection();
});