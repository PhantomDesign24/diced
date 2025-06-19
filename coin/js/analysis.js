/*
 * 파일명: analysis.js
 * 위치: /js/analysis.js
 * 기능: 토큰 잔액 조회 및 트랜잭션 분석
 * 작성일: 2024-12-27
 */

// ===================================
// 상수 정의
// ===================================
const ETHERSCAN_API_KEY = 'YOUR_ETHERSCAN_API_KEY'; // 실제 API 키로 교체 필요
const TRONSCAN_API_KEY = 'YOUR_TRONSCAN_API_KEY'; // 실제 API 키로 교체 필요

// ERC-20 Transfer 이벤트 시그니처
const TRANSFER_TOPIC = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

// ===================================
// 분석 초기화
// ===================================

/* 분석 기능 초기화 */
function initAnalysis() {
    // 분석 시작 버튼 이벤트
    $('#startAnalysis').on('click', async function() {
        await performAnalysis();
    });
    
    // 보고서 생성 버튼 이벤트
    $('#generateReport').on('click', function() {
        generateReport();
    });
}

// ===================================
// 메인 분석 함수
// ===================================

/* 분석 수행 */
async function performAnalysis() {
    if (!currentAccount) {
        showError('먼저 지갑을 연결해주세요.');
        return;
    }
    
    showLoading();
    
    try {
        // 토큰 필터 및 기간 가져오기
        const tokenFilter = $('#tokenFilter').val();
        const timePeriod = $('#timePeriod').val();
        
        if (currentNetwork === 'ethereum') {
            await analyzeEthereum(currentAccount, tokenFilter, timePeriod);
        } else if (currentNetwork === 'tron') {
            await analyzeTron(currentAccount, tokenFilter, timePeriod);
        }
        
        // 보고서 버튼 표시
        $('#generateReport').removeClass('d-none');
        
    } catch (error) {
        console.error('분석 오류:', error);
        showError('분석 중 오류가 발생했습니다: ' + error.message);
    } finally {
        hideLoading();
    }
}

// ===================================
// 이더리움 분석
// ===================================

/* 이더리움 지갑 분석 */
async function analyzeEthereum(address, tokenFilter, timePeriod) {
    try {
        // 1. ETH 잔액 조회
        const ethBalance = await web3Instance.eth.getBalance(address);
        const ethBalanceInEther = web3Instance.utils.fromWei(ethBalance, 'ether');
        
        // 2. ERC-20 토큰 잔액 조회
        const tokenBalances = await getERC20Balances(address, tokenFilter);
        
        // 3. 트랜잭션 내역 조회
        const transactions = await getEthereumTransactions(address, timePeriod);
        
        // UI 업데이트
        displayTokenBalances(tokenBalances, ethBalanceInEther);
        displayTransactions(transactions);
        
    } catch (error) {
        throw new Error('이더리움 분석 실패: ' + error.message);
    }
}

/* ERC-20 토큰 잔액 조회 */
async function getERC20Balances(address, tokenFilter) {
    const tokenBalances = [];
    
    // 주요 ERC-20 토큰 목록 (실제로는 API나 DB에서 가져와야 함)
    const popularTokens = [
        { address: '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48', symbol: 'USDC', decimals: 6 },
        { address: '0xdAC17F958D2ee523a2206206994597C13D831ec7', symbol: 'USDT', decimals: 6 },
        { address: '0x6B175474E89094C44Da98b954EedeAC495271d0F', symbol: 'DAI', decimals: 18 },
        // 더 많은 토큰 추가 가능
    ];
    
    // ERC-20 ABI (balanceOf 함수만)
    const minABI = [
        {
            constant: true,
            inputs: [{name: "_owner", type: "address"}],
            name: "balanceOf",
            outputs: [{name: "balance", type: "uint256"}],
            type: "function"
        },
        {
            constant: true,
            inputs: [],
            name: "decimals",
            outputs: [{name: "", type: "uint8"}],
            type: "function"
        }
    ];
    
    for (const token of popularTokens) {
        try {
            // 토큰 필터 적용
            if (tokenFilter && !token.symbol.toLowerCase().includes(tokenFilter.toLowerCase())) {
                continue;
            }
            
            const contract = new web3Instance.eth.Contract(minABI, token.address);
            const balance = await contract.methods.balanceOf(address).call();
            
            if (balance > 0) {
                const formattedBalance = (balance / Math.pow(10, token.decimals)).toFixed(4);
                tokenBalances.push({
                    symbol: token.symbol,
                    address: token.address,
                    balance: formattedBalance,
                    rawBalance: balance
                });
            }
        } catch (error) {
            console.error(`토큰 ${token.symbol} 조회 오류:`, error);
        }
    }
    
    return tokenBalances;
}

/* 이더리움 트랜잭션 조회 */
async function getEthereumTransactions(address, timePeriod) {
    const transactions = [];
    
    try {
        // 블록 번호 계산 (대략적)
        const currentBlock = await web3Instance.eth.getBlockNumber();
        let fromBlock = currentBlock - 5000; // 기본값
        
        if (timePeriod === '7') {
            fromBlock = currentBlock - 50000; // 약 7일
        } else if (timePeriod === '30') {
            fromBlock = currentBlock - 220000; // 약 30일
        } else if (timePeriod === '90') {
            fromBlock = currentBlock - 660000; // 약 90일
        } else if (timePeriod === 'all') {
            fromBlock = 0;
        }
        
        // Transfer 이벤트 조회 (받은 토큰)
        const incomingLogs = await web3Instance.eth.getPastLogs({
            fromBlock: fromBlock,
            toBlock: 'latest',
            topics: [
                TRANSFER_TOPIC,
                null,
                '0x' + address.slice(2).padStart(64, '0')
            ]
        });
        
        // Transfer 이벤트 조회 (보낸 토큰)
        const outgoingLogs = await web3Instance.eth.getPastLogs({
            fromBlock: fromBlock,
            toBlock: 'latest',
            topics: [
                TRANSFER_TOPIC,
                '0x' + address.slice(2).padStart(64, '0'),
                null
            ]
        });
        
        // 트랜잭션 정리
        for (const log of incomingLogs) {
            transactions.push({
                hash: log.transactionHash,
                type: 'in',
                token: log.address,
                from: '0x' + log.topics[1].slice(26),
                to: address,
                block: log.blockNumber,
                logIndex: log.logIndex
            });
        }
        
        for (const log of outgoingLogs) {
            transactions.push({
                hash: log.transactionHash,
                type: 'out',
                token: log.address,
                from: address,
                to: '0x' + log.topics[2].slice(26),
                block: log.blockNumber,
                logIndex: log.logIndex
            });
        }
        
        // 최신순 정렬
        transactions.sort((a, b) => b.block - a.block);
        
        // 최대 100개만 반환
        return transactions.slice(0, 100);
        
    } catch (error) {
        console.error('트랜잭션 조회 오류:', error);
        return [];
    }
}

// ===================================
// 트론 분석
// ===================================

/* 트론 지갑 분석 */
async function analyzeTron(address, tokenFilter, timePeriod) {
    try {
        // 1. TRX 잔액 조회
        const trxBalance = await tronWebInstance.trx.getBalance(address);
        const trxBalanceInTRX = tronWebInstance.fromSun(trxBalance);
        
        // 2. TRC-20 토큰 잔액 조회
        const tokenBalances = await getTRC20Balances(address, tokenFilter);
        
        // 3. 트랜잭션 내역 조회
        const transactions = await getTronTransactions(address, timePeriod);
        
        // UI 업데이트
        displayTokenBalances(tokenBalances, trxBalanceInTRX, 'TRX');
        displayTransactions(transactions);
        
    } catch (error) {
        throw new Error('트론 분석 실패: ' + error.message);
    }
}

/* TRC-20 토큰 잔액 조회 */
async function getTRC20Balances(address, tokenFilter) {
    const tokenBalances = [];
    
    // 주요 TRC-20 토큰 목록
    const popularTokens = [
        { address: 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t', symbol: 'USDT', decimals: 6 },
        { address: 'TEkxiTehnzSmSe2XqrBj4w32RUN966rdz8', symbol: 'USDC', decimals: 6 },
        // 더 많은 토큰 추가 가능
    ];
    
    for (const token of popularTokens) {
        try {
            // 토큰 필터 적용
            if (tokenFilter && !token.symbol.toLowerCase().includes(tokenFilter.toLowerCase())) {
                continue;
            }
            
            const contract = await tronWebInstance.contract().at(token.address);
            const balance = await contract.balanceOf(address).call();
            
            if (balance > 0) {
                const formattedBalance = (balance / Math.pow(10, token.decimals)).toFixed(4);
                tokenBalances.push({
                    symbol: token.symbol,
                    address: token.address,
                    balance: formattedBalance,
                    rawBalance: balance.toString()
                });
            }
        } catch (error) {
            console.error(`토큰 ${token.symbol} 조회 오류:`, error);
        }
    }
    
    return tokenBalances;
}

/* 트론 트랜잭션 조회 */
async function getTronTransactions(address, timePeriod) {
    const transactions = [];
    
    try {
        // TronGrid API 사용 (무료 API 제한 있음)
        const response = await fetch(`https://api.trongrid.io/v1/accounts/${address}/transactions/trc20?limit=100`);
        const data = await response.json();
        
        if (data.data) {
            for (const tx of data.data) {
                transactions.push({
                    hash: tx.transaction_id,
                    type: tx.to === address ? 'in' : 'out',
                    token: tx.token_info.address,
                    tokenSymbol: tx.token_info.symbol,
                    from: tx.from,
                    to: tx.to,
                    value: tx.value,
                    timestamp: tx.block_timestamp
                });
            }
        }
        
        return transactions;
        
    } catch (error) {
        console.error('트론 트랜잭션 조회 오류:', error);
        return [];
    }
}

// ===================================
// UI 표시 함수
// ===================================

/* 토큰 잔액 표시 */
function displayTokenBalances(tokenBalances, nativeBalance, nativeSymbol = 'ETH') {
    let html = `
        <table class="table table-hover token-table">
            <thead>
                <tr>
                    <th>토큰</th>
                    <th>잔액</th>
                    <th>주소</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>${nativeSymbol}</strong>
                    </td>
                    <td>${parseFloat(nativeBalance).toFixed(4)}</td>
                    <td>-</td>
                </tr>
    `;
    
    for (const token of tokenBalances) {
        html += `
            <tr>
                <td>
                    <strong>${token.symbol}</strong>
                </td>
                <td>${token.balance}</td>
                <td>
                    <span class="tx-hash text-truncate-address" title="${token.address}">
                        ${token.address}
                    </span>
                </td>
            </tr>
        `;
    }
    
    html += `
            </tbody>
        </table>
    `;
    
    $('#tokenBalances').html(html);
}

/* 트랜잭션 내역 표시 */
function displayTransactions(transactions) {
    if (transactions.length === 0) {
        $('#transactionHistory').html('<p class="text-muted text-center">트랜잭션 내역이 없습니다.</p>');
        return;
    }
    
    let html = `
        <table class="table table-sm transaction-table">
            <thead>
                <tr>
                    <th>해시</th>
                    <th>타입</th>
                    <th>토큰</th>
                    <th>From</th>
                    <th>To</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    for (const tx of transactions.slice(0, 50)) { // 최대 50개만 표시
        const typeClass = tx.type === 'in' ? 'tx-in' : 'tx-out';
        const typeIcon = tx.type === 'in' ? '↓' : '↑';
        
        html += `
            <tr>
                <td>
                    <a href="${getExplorerUrl(tx.hash)}" target="_blank" class="tx-hash">
                        ${tx.hash.substring(0, 10)}...
                    </a>
                </td>
                <td>
                    <span class="${typeClass}">${typeIcon} ${tx.type.toUpperCase()}</span>
                </td>
                <td>${tx.tokenSymbol || '토큰'}</td>
                <td class="tx-hash">${tx.from.substring(0, 10)}...</td>
                <td class="tx-hash">${tx.to.substring(0, 10)}...</td>
            </tr>
        `;
    }
    
    html += `
            </tbody>
        </table>
    `;
    
    $('#transactionHistory').html(html);
}

/* 익스플로러 URL 생성 */
function getExplorerUrl(txHash) {
    if (currentNetwork === 'ethereum') {
        return `https://etherscan.io/tx/${txHash}`;
    } else if (currentNetwork === 'tron') {
        return `https://tronscan.org/#/transaction/${txHash}`;
    }
    return '#';
}

// ===================================
// 보고서 생성
// ===================================

/* 보고서 생성 */
function generateReport() {
    showInfo('보고서 생성 기능은 준비 중입니다.');
    // TODO: PDF 또는 Excel 보고서 생성 기능 구현
}

// ===================================
// 초기화
// ===================================

/* 문서 준비 시 초기화 */
$(document).ready(function() {
    initAnalysis();
});