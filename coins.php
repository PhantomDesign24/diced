<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>실시간 코인 시세</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Noto Sans KR', sans-serif;
            background-color: #f5f5f5;
            padding: 10px;
            font-size: 14px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: #2b2f3a;
            color: white;
            padding: 12px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            font-size: 18px;
            font-weight: 600;
        }
        
        .update-info {
            font-size: 12px;
            color: #9ca3af;
        }
        
        .currency-tabs-container {
            display: flex;
            background: #f8f9fa;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .currency-tabs {
            display: flex;
            flex: 1;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
        }
        
        .currency-tabs::-webkit-scrollbar {
            height: 4px;
        }
        
        .currency-tabs::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        
        .currency-tabs::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 2px;
        }
        
        .currency-tab {
            padding: 10px 16px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 13px;
            font-weight: 500;
            color: #6b7280;
            transition: all 0.2s;
            white-space: nowrap;
            font-family: 'Noto Sans KR', sans-serif;
        }
        
        .currency-tab:hover {
            background: #e5e7eb;
        }
        
        .currency-tab.active {
            color: #2563eb;
            background: white;
            border-bottom: 2px solid #2563eb;
            margin-bottom: -1px;
        }
        
        .tab-controls {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0 12px;
            border-left: 1px solid #e5e7eb;
        }
        
        .collapse-toggle, .settings-toggle {
            background: rgba(0,0,0,0.05);
            color: #6b7280;
            border: none;
            padding: 6px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .collapse-toggle:hover, .settings-toggle:hover {
            background: rgba(0,0,0,0.1);
            color: #374151;
        }
        
        .settings-toggle.active {
            background: #2563eb;
            color: white;
        }
        
        .price-table-container {
            overflow-x: auto;
        }
        
        .price-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            min-width: 600px;
            transition: all 0.3s ease;
        }
        
        .price-table.collapsed tbody {
            display: none;
        }
        
        .price-table.compact th,
        .price-table.compact td {
            padding: 6px 8px;
            font-size: 12px;
        }
        
        .price-table th {
            background: #f8f9fa;
            padding: 10px 12px;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e5e7eb;
            font-size: 12px;
            white-space: nowrap;
        }
        
        .price-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #f3f4f6;
            font-family: 'Noto Sans KR', sans-serif;
            white-space: nowrap;
            transition: all 0.3s ease;
        }
        
        .price-table tr:hover {
            background: #f9fafb;
        }
        
        .price-table.highlight-changes .change-highlight {
            animation: highlight 1s ease-out;
        }
        
        @keyframes highlight {
            0% { background-color: #fef3c7; }
            100% { background-color: transparent; }
        }
        
        .exchange-name {
            font-weight: 500;
            color: #1f2937;
            font-size: 13px;
        }
        
        .price {
            font-weight: 400;
            color: #1f2937;
            font-size: 13px;
        }
        
        .currency-unit {
            font-size: 11px;
            color: #6b7280;
            margin-left: 3px;
        }
        
        .change-rate {
            font-weight: 500;
            font-size: 12px;
        }
        
        /* 커스텀 색상 적용 */
        .plus {
            color: var(--plus-color, #ef4444);
        }
        
        .minus {
            color: var(--minus-color, #3b82f6);
        }
        
        .no-data {
            color: #9ca3af;
            text-align: center;
        }
        
        .loading {
            text-align: center;
            padding: 30px;
            color: #6b7280;
        }
        
        .error {
            text-align: center;
            padding: 30px;
            color: #ef4444;
        }
        
        .spinner {
            border: 2px solid #f3f4f6;
            border-top: 2px solid #3b82f6;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .market-info {
            padding: 12px 16px;
            background: #f8f9fa;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #6b7280;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .market-stats {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .market-value {
            font-weight: 500;
            color: #1f2937;
            margin-left: 4px;
        }
        
        /* 사이드 설정 패널 */
        .settings-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.3);
            z-index: 1000;
            display: none;
            backdrop-filter: blur(2px);
        }
        
        .settings-sidebar {
            position: fixed;
            top: 0;
            right: -400px;
            width: 380px;
            height: 100vh;
            background: white;
            box-shadow: -2px 0 8px rgba(0,0,0,0.1);
            z-index: 1001;
            transition: right 0.3s ease;
            overflow-y: auto;
        }
        
        .settings-sidebar.open {
            right: 0;
        }
        
        .settings-header {
            padding: 16px 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .settings-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: #1f2937;
        }
        
        .settings-close {
            background: none;
            border: none;
            font-size: 18px;
            cursor: pointer;
            color: #6b7280;
            padding: 4px;
        }
        
        .settings-content {
            padding: 20px;
        }
        
        .settings-section {
            margin-bottom: 24px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .settings-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .settings-title {
            font-weight: 600;
            margin-bottom: 12px;
            color: #1f2937;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .column-list, .exchange-list {
            list-style: none;
            padding: 0;
            max-height: 200px;
            overflow-y: auto;
        }
        
        .column-item, .exchange-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            margin-bottom: 6px;
            cursor: move;
            transition: all 0.2s;
            background: #f9fafb;
        }
        
        .column-item:hover, .exchange-item:hover {
            background: #f3f4f6;
            border-color: #d1d5db;
        }
        
        .column-item.dragging, .exchange-item.dragging {
            opacity: 0.5;
        }
        
        .item-controls {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .visibility-toggle {
            width: 20px;
            height: 20px;
            border: 2px solid #d1d5db;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            transition: all 0.2s;
        }
        
        .visibility-toggle.active {
            background: #10b981;
            border-color: #10b981;
            color: white;
        }
        
        .drag-handle {
            color: #9ca3af;
            font-size: 14px;
            cursor: move;
        }
        
        .color-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }
        
        .color-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .color-option:hover {
            background: #f9fafb;
        }
        
        .color-option input[type="radio"] {
            margin: 0;
        }
        
        .color-preview {
            width: 18px;
            height: 18px;
            border-radius: 3px;
            border: 1px solid #d1d5db;
            flex-shrink: 0;
        }
        
        .option-label {
            font-size: 12px;
            flex: 1;
        }
        
        .checkbox-option {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            padding: 6px 0;
        }
        
        .checkbox-option input {
            margin: 0;
        }
        
        .reset-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            width: 100%;
            margin-top: 16px;
            transition: background 0.2s;
        }
        
        .reset-btn:hover {
            background: #dc2626;
        }
        
        /* 모바일 최적화 */
        @media (max-width: 768px) {
            body {
                padding: 5px;
                font-size: 12px;
            }
            
            .header {
                padding: 10px 12px;
            }
            
            .header h1 {
                font-size: 16px;
            }
            
            .update-info {
                font-size: 10px;
            }
            
            .currency-tabs {
                padding: 0 8px;
            }
            
            .currency-tab {
                padding: 8px 12px;
                font-size: 12px;
            }
            
            .tab-controls {
                padding: 0 8px;
            }
            
            .collapse-toggle, .settings-toggle {
                padding: 4px 6px;
                font-size: 10px;
            }
            
            .price-table {
                font-size: 11px;
                min-width: 500px;
            }
            
            .price-table th,
            .price-table td {
                padding: 6px 8px;
            }
            
            .market-info {
                padding: 8px 12px;
                font-size: 10px;
            }
            
            .market-stats {
                gap: 8px;
            }
            
            .settings-sidebar {
                width: 100%;
                right: -100%;
            }
            
            .currency-unit {
                font-size: 9px;
            }
            
            .change-rate {
                font-size: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .market-stats {
                flex-direction: column;
                align-items: flex-start;
                gap: 4px;
            }
            
            .price-table {
                min-width: 450px;
            }
            
            .tab-controls {
                flex-direction: column;
                gap: 4px;
                padding: 4px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>실시간 코인 시세</h1>
            <div class="update-info">
                마지막 업데이트: <span id="lastUpdate">-</span>
            </div>
        </div>
        
        <div class="currency-tabs-container">
            <div class="currency-tabs" id="currencyTabs">
                <button class="currency-tab active" data-currency="BTC">BTC</button>
                <button class="currency-tab" data-currency="ETH">ETH</button>
                <button class="currency-tab" data-currency="XRP">XRP</button>
                <button class="currency-tab" data-currency="ETC">ETC</button>
                <button class="currency-tab" data-currency="TRX">TRX</button>
                <button class="currency-tab" data-currency="BCH">BCH</button>
                <button class="currency-tab" data-currency="EOS">EOS</button>
                <button class="currency-tab" data-currency="ADA">ADA</button>
                <button class="currency-tab" data-currency="SOL">SOL</button>
                <button class="currency-tab" data-currency="DOGE">DOGE</button>
            </div>
            <div class="tab-controls">
                <button class="collapse-toggle" onclick="toggleTable()">
                    <span id="collapseIcon">📁</span>
                </button>
                <button class="settings-toggle" onclick="toggleSettings()">
                    ⚙️
                </button>
            </div>
        </div>
        
        <div id="priceContainer">
            <div class="loading">
                <div class="spinner"></div>
                <p>시세 정보를 불러오는 중...</p>
            </div>
        </div>
        
        <div class="market-info">
            <div class="market-stats">
                <div>
                    <span>전체 시장:</span>
                    <span class="market-value" id="totalMarketCap">-</span>
                </div>
                <div>
                    <span>24H 볼륨:</span>
                    <span class="market-value" id="totalVolume">-</span>
                </div>
                <div>
                    <span>비트 점유:</span>
                    <span class="market-value" id="btcDominance">-</span>
                </div>
            </div>
            <div>
                <span>환율:</span>
                <span class="market-value" id="exchangeRate">1,369.85</span>
                <span class="currency-unit">KRW/USD</span>
            </div>
        </div>
    </div>

    <!-- 설정 사이드바 -->
    <div class="settings-overlay" id="settingsOverlay" onclick="closeSettings()"></div>
    <div class="settings-sidebar" id="settingsSidebar">
        <div class="settings-header">
            <h3>⚙️ 설정</h3>
            <button class="settings-close" onclick="closeSettings()">×</button>
        </div>
        <div class="settings-content">
            <div class="settings-section">
                <div class="settings-title">📊 테이블 컬럼 구성</div>
                <ul class="column-list" id="columnList">
                    <!-- 동적으로 생성됨 -->
                </ul>
            </div>
            
            <div class="settings-section">
                <div class="settings-title">🏪 거래소 순서</div>
                <ul class="exchange-list" id="exchangeList">
                    <!-- 동적으로 생성됨 -->
                </ul>
            </div>
            
            <div class="settings-section">
                <div class="settings-title">🎨 색상 테마</div>
                <div class="color-options">
                    <div class="color-option">
                        <input type="radio" name="colorTheme" value="default" id="colorDefault" checked>
                        <div class="color-preview" style="background: linear-gradient(90deg, #ef4444 50%, #3b82f6 50%);"></div>
                        <label for="colorDefault" class="option-label">기본</label>
                    </div>
                    <div class="color-option">
                        <input type="radio" name="colorTheme" value="green-red" id="colorGreenRed">
                        <div class="color-preview" style="background: linear-gradient(90deg, #10b981 50%, #ef4444 50%);"></div>
                        <label for="colorGreenRed" class="option-label">초록/빨강</label>
                    </div>
                    <div class="color-option">
                        <input type="radio" name="colorTheme" value="purple-orange" id="colorPurpleOrange">
                        <div class="color-preview" style="background: linear-gradient(90deg, #8b5cf6 50%, #f97316 50%);"></div>
                        <label for="colorPurpleOrange" class="option-label">보라/주황</label>
                    </div>
                    <div class="color-option">
                        <input type="radio" name="colorTheme" value="blue-gold" id="colorBlueGold">
                        <div class="color-preview" style="background: linear-gradient(90deg, #3b82f6 50%, #f59e0b 50%);"></div>
                        <label for="colorBlueGold" class="option-label">파랑/금색</label>
                    </div>
                </div>
            </div>
            
            <div class="settings-section">
                <div class="settings-title">⚙️ 표시 옵션</div>
                <div class="checkbox-option">
                    <input type="checkbox" id="autoCollapse">
                    <label for="autoCollapse">자동 접기 모드</label>
                </div>
                <div class="checkbox-option">
                    <input type="checkbox" id="compactMode">
                    <label for="compactMode">컴팩트 모드</label>
                </div>
                <div class="checkbox-option">
                    <input type="checkbox" id="highlightChanges">
                    <label for="highlightChanges">변동 하이라이트</label>
                </div>
                <button class="reset-btn" onclick="resetSettings()">설정 초기화</button>
            </div>
        </div>
    </div>

    <script>
        let currentCurrency = 'BTC';
        let refreshInterval;
        let isLoading = false;
        let coinpanData = null;
        let lastUpdateTime = null;
        let isTableCollapsed = false;
        let previousPrices = {}; // 변동 하이라이트용
        
        // 사용자 설정
        let userSettings = {
            columnOrder: ['exchange', 'price_krw', 'price_usd', 'change_24h', 'korea_premium', 'volume_24h'],
            columnVisibility: {
                'exchange': true,
                'price_krw': true,
                'price_usd': true,
                'change_24h': true,
                'korea_premium': true,
                'volume_24h': true
            },
            exchangeOrder: ['bithumb', 'upbit', 'coinone', 'korbit', 'bitflyer', 'binance', 'bitfinex'],
            exchangeVisibility: {
                'bithumb': true,
                'upbit': true,
                'coinone': true,
                'korbit': true,
                'bitflyer': true,
                'binance': true,
                'bitfinex': true
            },
            colorTheme: 'default',
            autoCollapse: false,
            compactMode: false,
            highlightChanges: false
        };

        // 컬럼 정의 (24시간 고가/저가 제거)
        const columnDefinitions = {
            'exchange': { name: '거래소', icon: '🏪' },
            'price_krw': { name: '실시간 시세(KRW)', icon: '💰' },
            'price_usd': { name: '실시간 시세(USD)', icon: '💵' },
            'change_24h': { name: '24시간 변동률', icon: '📈' },
            'korea_premium': { name: '한국 프리미엄', icon: '🇰🇷' },
            'volume_24h': { name: '거래량', icon: '📊' }
        };

        // 숫자 포맷팅
        function formatNumber(num, decimals = 0) {
            if (typeof num !== 'number') return '-';
            return new Intl.NumberFormat('ko-KR', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals
            }).format(num);
        }

        // 큰 숫자 포맷팅
        function formatLargeNumber(num) {
            if (num >= 1000000000000) {
                return '$' + (num / 1000000000000).toFixed(2) + 'T';
            } else if (num >= 1000000000) {
                return '$' + (num / 1000000000).toFixed(2) + 'B';
            } else if (num >= 1000000) {
                return '$' + (num / 1000000).toFixed(2) + 'M';
            }
            return '$' + formatNumber(num);
        }

        // API 호출 (PHP 백엔드를 통해)
        async function fetchCoinpanData() {
            try {
                const response = await fetch('coinpan_api.php?action=all');
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.error || 'API 호출 실패');
                }
                
                return result.data;
            } catch (error) {
                console.error('API 호출 실패:', error);
                return null;
            }
        }

        // 시장 정보 업데이트
        function updateMarketInfo(data) {
            if (data && data.market_info) {
                const market = data.market_info;
                document.getElementById('totalMarketCap').textContent = formatLargeNumber(market.total_market_cap_usd);
                document.getElementById('totalVolume').textContent = formatLargeNumber(market.total_24h_volume_usd);
                document.getElementById('btcDominance').textContent = market.bitcoin_percentage.toFixed(2) + '%';
            }
            
            if (data && data.exchange_rates) {
                document.getElementById('exchangeRate').textContent = formatNumber(data.exchange_rates.usd_to_krw, 2);
            }
        }

        // 컬럼 데이터 생성
        function generateColumnData(columnKey, coinData, symbol, exchangeKey) {
            const currentPrice = coinData.price_krw;
            const previousPrice = previousPrices[exchangeKey + '_' + symbol];
            const hasChanged = previousPrice && previousPrice !== currentPrice;
            const highlightClass = userSettings.highlightChanges && hasChanged ? 'change-highlight' : '';
            
            switch (columnKey) {
                case 'exchange':
                    const exchangeNames = {
                        'bithumb': '빗썸', 'upbit': '업비트', 'coinone': '코인원', 'korbit': '코빗',
                        'bitflyer': '플라이어', 'binance': '바이낸스', 'bitfinex': '파이넥스'
                    };
                    return `<td class="exchange-name ${highlightClass}">${exchangeNames[exchangeKey]}</td>`;
                
                case 'price_krw':
                    return `<td class="price ${highlightClass}">${formatNumber(coinData.price_krw)}<span class="currency-unit">KRW</span></td>`;
                
                case 'price_usd':
                    return `<td class="price ${highlightClass}">${formatNumber(coinData.price_usd, 2)}<span class="currency-unit">USD</span></td>`;
                
                case 'change_24h':
                    const changeClass = coinData.change_24h_percent >= 0 ? 'plus' : 'minus';
                    const changeSymbol = coinData.change_24h_percent >= 0 ? '▲' : '▼';
                    return `<td class="change-rate ${changeClass} ${highlightClass}">
                        ${changeSymbol} ${formatNumber(Math.abs(coinData.change_24h))}
                        <span style="font-size: 11px;">(${coinData.change_24h_percent.toFixed(2)}%)</span>
                    </td>`;
                
                case 'korea_premium':
                    if (coinData.korea_premium_percent !== 0) {
                        const premiumClass = coinData.korea_premium_percent >= 0 ? 'plus' : 'minus';
                        const premiumSign = coinData.korea_premium_percent >= 0 ? '+' : '';
                        return `<td class="price ${highlightClass}">
                            <span class="${premiumClass}">
                                ${premiumSign}${formatNumber(coinData.korea_premium)}
                                <span style="font-size: 11px;">(${premiumSign}${coinData.korea_premium_percent.toFixed(2)}%)</span>
                            </span>
                        </td>`;
                    }
                    return `<td class="no-data ${highlightClass}">-</td>`;
                
                case 'volume_24h':
                    return `<td class="price ${highlightClass}">${formatNumber(coinData.volume_24h, 2)}<span class="currency-unit">${symbol}</span></td>`;
                
                default:
                    return `<td class="no-data ${highlightClass}">-</td>`;
            }
        }

        // 가격 표시
        function displayPrices(symbol) {
            const container = document.getElementById('priceContainer');
            
            if (!coinpanData || !coinpanData.prices) {
                container.innerHTML = '<div class="error">데이터를 불러올 수 없습니다.</div>';
                return;
            }

            // 이전 가격 저장 (하이라이트용)
            if (userSettings.highlightChanges) {
                userSettings.exchangeOrder.forEach(exchangeKey => {
                    const exchangeData = coinpanData.prices[exchangeKey];
                    const coinData = exchangeData && exchangeData.coins[symbol];
                    if (coinData && coinData.available) {
                        previousPrices[exchangeKey + '_' + symbol] = coinData.price_krw;
                    }
                });
            }

            // 테이블 헤더 구성
            let headerHtml = '';
            userSettings.columnOrder.forEach(columnKey => {
                if (userSettings.columnVisibility[columnKey] && columnDefinitions[columnKey]) {
                    const column = columnDefinitions[columnKey];
                    headerHtml += `<th>${column.icon} ${column.name}</th>`;
                }
            });

            const tableClasses = [
                'price-table',
                isTableCollapsed ? 'collapsed' : '',
                userSettings.compactMode ? 'compact' : '',
                userSettings.highlightChanges ? 'highlight-changes' : ''
            ].filter(Boolean).join(' ');

            let html = `
                <div class="price-table-container">
                    <table class="${tableClasses}">
                        <thead>
                            <tr>${headerHtml}</tr>
                        </thead>
                        <tbody>
            `;

            userSettings.exchangeOrder.forEach(exchangeKey => {
                if (!userSettings.exchangeVisibility[exchangeKey]) return;
                
                const exchangeData = coinpanData.prices[exchangeKey];
                const coinData = exchangeData && exchangeData.coins[symbol];
                
                if (coinData && coinData.available) {
                    let rowHtml = '';
                    userSettings.columnOrder.forEach(columnKey => {
                        if (userSettings.columnVisibility[columnKey] && columnDefinitions[columnKey]) {
                            rowHtml += generateColumnData(columnKey, coinData, symbol, exchangeKey);
                        }
                    });
                    html += `<tr>${rowHtml}</tr>`;
                } else {
                    // 데이터 없는 경우
                    let emptyCells = '';
                    userSettings.columnOrder.forEach(columnKey => {
                        if (userSettings.columnVisibility[columnKey] && columnDefinitions[columnKey]) {
                            if (columnKey === 'exchange') {
                                const exchangeNames = {
                                    'bithumb': '빗썸', 'upbit': '업비트', 'coinone': '코인원', 'korbit': '코빗',
                                    'bitflyer': '플라이어', 'binance': '바이낸스', 'bitfinex': '파이넥스'
                                };
                                emptyCells += `<td class="exchange-name">${exchangeNames[exchangeKey]}</td>`;
                            } else {
                                emptyCells += '<td class="no-data">-</td>';
                            }
                        }
                    });
                    html += `<tr>${emptyCells}</tr>`;
                }
            });

            html += '</tbody></table></div>';
            container.innerHTML = html;
            
            // 접기 아이콘 업데이트
            document.getElementById('collapseIcon').textContent = isTableCollapsed ? '📂' : '📁';
        }

        // 데이터 새로고침
        async function refreshAllPrices() {
            if (isLoading) return;
            
            isLoading = true;
            
            try {
                if (!coinpanData) {
                    document.getElementById('priceContainer').innerHTML = `
                        <div class="loading">
                            <div class="spinner"></div>
                            <p>시세 정보를 불러오는 중...</p>
                        </div>
                    `;
                }
                
                coinpanData = await fetchCoinpanData();
                
                if (coinpanData) {
                    updateMarketInfo(coinpanData);
                    displayPrices(currentCurrency);
                    lastUpdateTime = new Date().toLocaleTimeString('ko-KR');
                    document.getElementById('lastUpdate').textContent = lastUpdateTime;
                } else {
                    document.getElementById('priceContainer').innerHTML = 
                        '<div class="error">API 연결에 실패했습니다. 백엔드를 확인해주세요.</div>';
                }
            } catch (error) {
                console.error('데이터 새로고침 실패:', error);
                document.getElementById('priceContainer').innerHTML = 
                    '<div class="error">데이터를 불러올 수 없습니다.</div>';
            } finally {
                isLoading = false;
            }
        }

        // 설정 관련 함수들
        function loadSettings() {
            const saved = localStorage.getItem('cryptoSettings');
            if (saved) {
                const savedSettings = JSON.parse(saved);
                
                // 저장된 설정에서 유효하지 않은 컬럼 제거
                if (savedSettings.columnOrder) {
                    savedSettings.columnOrder = savedSettings.columnOrder.filter(col => columnDefinitions[col]);
                }
                
                // 누락된 새 컬럼 추가
                Object.keys(columnDefinitions).forEach(col => {
                    if (!savedSettings.columnOrder || !savedSettings.columnOrder.includes(col)) {
                        if (!savedSettings.columnOrder) savedSettings.columnOrder = [];
                        savedSettings.columnOrder.push(col);
                    }
                    if (!savedSettings.columnVisibility || savedSettings.columnVisibility[col] === undefined) {
                        if (!savedSettings.columnVisibility) savedSettings.columnVisibility = {};
                        savedSettings.columnVisibility[col] = true;
                    }
                });
                
                userSettings = { ...userSettings, ...savedSettings };
            }
            applySettings();
        }
        
        function saveSettings() {
            localStorage.setItem('cryptoSettings', JSON.stringify(userSettings));
        }
        
        function applySettings() {
            // 색상 테마 적용
            const colorThemes = {
                'default': { plus: '#ef4444', minus: '#3b82f6' },
                'green-red': { plus: '#10b981', minus: '#ef4444' },
                'purple-orange': { plus: '#8b5cf6', minus: '#f97316' },
                'blue-gold': { plus: '#3b82f6', minus: '#f59e0b' }
            };
            
            const theme = colorThemes[userSettings.colorTheme];
            document.documentElement.style.setProperty('--plus-color', theme.plus);
            document.documentElement.style.setProperty('--minus-color', theme.minus);
            
            // 설정 UI 업데이트
            document.getElementById('autoCollapse').checked = userSettings.autoCollapse;
            document.getElementById('compactMode').checked = userSettings.compactMode;
            document.getElementById('highlightChanges').checked = userSettings.highlightChanges;
            document.querySelector(`input[value="${userSettings.colorTheme}"]`).checked = true;
            
            // 자동 접기 모드
            if (userSettings.autoCollapse) {
                isTableCollapsed = true;
            }
        }

        function toggleSettings() {
            const overlay = document.getElementById('settingsOverlay');
            const sidebar = document.getElementById('settingsSidebar');
            const toggle = document.querySelector('.settings-toggle');
            
            const isOpen = sidebar.classList.contains('open');
            
            if (isOpen) {
                closeSettings();
            } else {
                overlay.style.display = 'block';
                sidebar.classList.add('open');
                toggle.classList.add('active');
            }
        }

        function closeSettings() {
            const overlay = document.getElementById('settingsOverlay');
            const sidebar = document.getElementById('settingsSidebar');
            const toggle = document.querySelector('.settings-toggle');
            
            overlay.style.display = 'none';
            sidebar.classList.remove('open');
            toggle.classList.remove('active');
        }

        function initializeSettingsPanel() {
            updateColumnList();
            updateExchangeList();
            
            // 색상 테마 변경 이벤트
            document.querySelectorAll('input[name="colorTheme"]').forEach(radio => {
                radio.addEventListener('change', (e) => {
                    userSettings.colorTheme = e.target.value;
                    applySettings();
                    saveSettings();
                });
            });
            
            // 표시 옵션 변경 이벤트
            ['autoCollapse', 'compactMode', 'highlightChanges'].forEach(option => {
                document.getElementById(option).addEventListener('change', (e) => {
                    userSettings[option] = e.target.checked;
                    if (option === 'autoCollapse' && e.target.checked) {
                        isTableCollapsed = true;
                    }
                    saveSettings();
                    displayPrices(currentCurrency);
                });
            });
        }

        function updateColumnList() {
            const list = document.getElementById('columnList');
            list.innerHTML = '';
            
            userSettings.columnOrder.forEach((columnKey) => {
                const column = columnDefinitions[columnKey];
                // 컬럼 정의가 없는 경우 스킵
                if (!column) {
                    console.warn(`컬럼 정의를 찾을 수 없습니다: ${columnKey}`);
                    return;
                }
                
                const li = document.createElement('li');
                li.className = 'column-item';
                li.draggable = true;
                li.dataset.column = columnKey;
                
                li.innerHTML = `
                    <span>${column.icon} ${column.name}</span>
                    <div class="item-controls">
                        <div class="visibility-toggle ${userSettings.columnVisibility[columnKey] ? 'active' : ''}" 
                             onclick="toggleColumnVisibility('${columnKey}')">${userSettings.columnVisibility[columnKey] ? '✓' : ''}</div>
                        <span class="drag-handle">⋮⋮</span>
                    </div>
                `;
                
                setupDragAndDrop(li, columnKey, 'column');
                list.appendChild(li);
            });
        }

        function updateExchangeList() {
            const list = document.getElementById('exchangeList');
            const exchangeNames = {
                'bithumb': '빗썸', 'upbit': '업비트', 'coinone': '코인원', 'korbit': '코빗',
                'bitflyer': '플라이어', 'binance': '바이낸스', 'bitfinex': '파이넥스'
            };
            
            list.innerHTML = '';
            userSettings.exchangeOrder.forEach((exchangeKey) => {
                const li = document.createElement('li');
                li.className = 'exchange-item';
                li.draggable = true;
                li.dataset.exchange = exchangeKey;
                
                li.innerHTML = `
                    <span>🏪 ${exchangeNames[exchangeKey]}</span>
                    <div class="item-controls">
                        <div class="visibility-toggle ${userSettings.exchangeVisibility[exchangeKey] ? 'active' : ''}" 
                             onclick="toggleExchangeVisibility('${exchangeKey}')">${userSettings.exchangeVisibility[exchangeKey] ? '✓' : ''}</div>
                        <span class="drag-handle">⋮⋮</span>
                    </div>
                `;
                
                setupDragAndDrop(li, exchangeKey, 'exchange');
                list.appendChild(li);
            });
        }

        function setupDragAndDrop(element, key, type) {
            element.addEventListener('dragstart', (e) => {
                e.dataTransfer.setData('text/plain', key);
                element.classList.add('dragging');
            });
            
            element.addEventListener('dragend', () => {
                element.classList.remove('dragging');
            });
            
            element.addEventListener('dragover', (e) => {
                e.preventDefault();
            });
            
            element.addEventListener('drop', (e) => {
                e.preventDefault();
                const draggedKey = e.dataTransfer.getData('text/plain');
                const orderArray = type === 'column' ? userSettings.columnOrder : userSettings.exchangeOrder;
                const targetIndex = orderArray.indexOf(key);
                const draggedIndex = orderArray.indexOf(draggedKey);
                
                // 배열 순서 변경
                orderArray.splice(draggedIndex, 1);
                orderArray.splice(targetIndex, 0, draggedKey);
                
                if (type === 'column') {
                    updateColumnList();
                } else {
                    updateExchangeList();
                }
                saveSettings();
                displayPrices(currentCurrency);
            });
        }

        function toggleColumnVisibility(columnKey) {
            userSettings.columnVisibility[columnKey] = !userSettings.columnVisibility[columnKey];
            updateColumnList();
            saveSettings();
            displayPrices(currentCurrency);
        }

        function toggleExchangeVisibility(exchangeKey) {
            userSettings.exchangeVisibility[exchangeKey] = !userSettings.exchangeVisibility[exchangeKey];
            updateExchangeList();
            saveSettings();
            displayPrices(currentCurrency);
        }

        function toggleTable() {
            isTableCollapsed = !isTableCollapsed;
            displayPrices(currentCurrency);
        }

        function resetSettings() {
            if (confirm('모든 설정을 초기화하시겠습니까?')) {
                localStorage.removeItem('cryptoSettings');
                userSettings = {
                    columnOrder: ['exchange', 'price_krw', 'price_usd', 'change_24h', 'korea_premium', 'volume_24h'],
                    columnVisibility: {
                        'exchange': true, 'price_krw': true, 'price_usd': true, 'change_24h': true,
                        'korea_premium': true, 'volume_24h': true
                    },
                    exchangeOrder: ['bithumb', 'upbit', 'coinone', 'korbit', 'bitflyer', 'binance', 'bitfinex'],
                    exchangeVisibility: {
                        'bithumb': true, 'upbit': true, 'coinone': true, 'korbit': true,
                        'bitflyer': true, 'binance': true, 'bitfinex': true
                    },
                    colorTheme: 'default',
                    autoCollapse: false,
                    compactMode: false,
                    highlightChanges: false
                };
                isTableCollapsed = false;
                previousPrices = {};
                applySettings();
                updateColumnList();
                updateExchangeList();
                displayPrices(currentCurrency);
            }
        }

        // 탭 변경 처리
        document.getElementById('currencyTabs').addEventListener('click', (e) => {
            if (e.target.classList.contains('currency-tab')) {
                document.querySelectorAll('.currency-tab').forEach(tab => {
                    tab.classList.remove('active');
                });
                e.target.classList.add('active');
                currentCurrency = e.target.dataset.currency;
                
                if (coinpanData) {
                    displayPrices(currentCurrency);
                }
            }
        });

        // 초기화
        document.addEventListener('DOMContentLoaded', () => {
            loadSettings();
            initializeSettingsPanel();
            refreshAllPrices();
            
            // 1분마다 자동 새로고침
            refreshInterval = setInterval(refreshAllPrices, 60000);
        });

        // 페이지 떠날 때 정리
        window.addEventListener('beforeunload', () => {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });
    </script>
</body>
</html>