<?php
/**
 * 암호화폐 시세 조회 클래스 - 수정된 버전
 */
class CryptoPriceTracker {
    private $exchanges = [];
    private $cache_time = 3; // 캐시 시간 (초) - 더 짧게 설정
    private $cache_dir = './cache/';
    private $debug = false; // 디버깅 모드
    private $exchange_rate = 1366.62; // USD/KRW 환율
    
    // 각 거래소별 지원 코인 목록
    private $supported_coins = [
        'upbit' => ['BTC', 'ETH', 'XRP', 'ETC', 'TRX', 'BCH', 'EOS', 'ADA', 'SOL', 'DOGE'],
        'bithumb' => ['BTC', 'ETH', 'XRP', 'ETC', 'TRX', 'BCH', 'EOS', 'ADA', 'SOL', 'DOGE'],
        'coinone' => ['BTC', 'ETH', 'XRP', 'ETC', 'TRX', 'BCH', 'ADA', 'SOL', 'DOGE'], // EOS 미지원
        'korbit' => ['BTC', 'ETH', 'XRP', 'ETC', 'BCH', 'EOS', 'ADA', 'SOL', 'DOGE'], // TRX 미지원
        'bitflyer' => ['BTC', 'ETH'], // 일본 거래소는 주요 코인만
        'binance' => ['BTC', 'ETH', 'XRP', 'ETC', 'TRX', 'BCH', 'EOS', 'ADA', 'SOL', 'DOGE'],
        'bitfinex' => ['BTC', 'ETH', 'XRP', 'ETC', 'TRX', 'BCH', 'EOS', 'ADA', 'SOL', 'DOGE']
    ];
    
    public function __construct() {
        // 캐시 디렉토리 생성
        if (!file_exists($this->cache_dir)) {
            mkdir($this->cache_dir, 0777, true);
        }
        
        // 거래소 API 설정
        $this->exchanges = [
            'upbit' => [
                'name' => '업비트',
                'api_url' => 'https://api.upbit.com/v1/ticker',
                'type' => 'upbit'
            ],
            'bithumb' => [
                'name' => '빗썸',
                'api_url' => 'https://api.bithumb.com/public/ticker/',
                'type' => 'bithumb'
            ],
            'coinone' => [
                'name' => '코인원',
                'api_url' => 'https://api.coinone.co.kr/public/v2/ticker_utc/',
                'type' => 'coinone'
            ],
            'korbit' => [
                'name' => '코빗',
                'api_url' => 'https://api.korbit.co.kr/v1/ticker',
                'type' => 'korbit'
            ],
            'bitflyer' => [
                'name' => '비트플라이어',
                'api_url' => 'https://api.bitflyer.com/v1/ticker',
                'type' => 'bitflyer'
            ],
            'binance' => [
                'name' => '바이낸스',
                'api_url' => 'https://api.binance.com/api/v3/ticker/24hr',
                'type' => 'binance'
            ],
            'bitfinex' => [
                'name' => '비트파이넥스',
                'api_url' => 'https://api-pub.bitfinex.com/v2/ticker/',
                'type' => 'bitfinex'
            ]
        ];
    }
    
    /**
     * 거래소가 특정 코인을 지원하는지 확인
     */
    private function isSupported($exchange, $symbol) {
        return isset($this->supported_coins[$exchange]) && 
               in_array($symbol, $this->supported_coins[$exchange]);
    }
    
    /**
     * 캐시 확인
     */
    private function getCache($key) {
        $cache_file = $this->cache_dir . $key . '.json';
        if (file_exists($cache_file)) {
            $mtime = filemtime($cache_file);
            if (time() - $mtime < $this->cache_time) {
                return json_decode(file_get_contents($cache_file), true);
            }
        }
        return null;
    }
    
    /**
     * 캐시 저장
     */
    private function setCache($key, $data) {
        $cache_file = $this->cache_dir . $key . '.json';
        file_put_contents($cache_file, json_encode($data));
    }
    
    /**
     * CURL을 사용한 HTTP 요청
     */
    private function fetchDataCurl($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15); // 타임아웃 늘림
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Accept-Language: en-US,en;q=0.9',
            'Cache-Control: no-cache'
        ]);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // 디버그: HTTP 코드와 에러 정보 로깅
        if ($this->debug) {
            error_log("CURL Debug - URL: $url, HTTP Code: $http_code, Error: $error, Response: " . substr($response, 0, 200));
        }
        
        if ($error || $http_code !== 200) {
            return false;
        }
        
        return $response;
    }
    
    /**
     * CURL 멀티 핸들을 사용한 병렬 요청
     */
    private function fetchMultiData($urls) {
        $multi = curl_multi_init();
        $curl_handles = [];
        
        foreach ($urls as $key => $url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
            
            curl_multi_add_handle($multi, $ch);
            $curl_handles[$key] = $ch;
        }
        
        // 모든 요청 실행
        $running = null;
        do {
            curl_multi_exec($multi, $running);
            curl_multi_select($multi);
        } while ($running > 0);
        
        // 결과 수집
        $results = [];
        foreach ($curl_handles as $key => $ch) {
            $response = curl_multi_getcontent($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($http_code === 200 && $response) {
                $results[$key] = $response;
            } else {
                $results[$key] = null;
            }
            
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }
        
        curl_multi_close($multi);
        return $results;
    }
    
    /**
     * 모든 거래소 시세 조회 (병렬 처리)
     */
    public function getAllPrices($symbol = 'BTC') {
        // 먼저 최신 환율 정보를 가져오기
        $market_info = $this->getMarketInfo();
        if ($market_info && isset($market_info['exchange_rate'])) {
            $this->exchange_rate = $market_info['exchange_rate'];
        }
        
        // 캐시 확인
        $cache_key = strtolower($symbol) . '_all';
        $cached = $this->getCache($cache_key);
        if ($cached) {
            // 캐시된 데이터도 최신 환율로 USD 가격 재계산
            if (isset($cached['exchanges'])) {
                foreach ($cached['exchanges'] as &$exchange) {
                    if (isset($exchange['price_krw'])) {
                        $exchange['price_usd'] = round($exchange['price_krw'] / $this->exchange_rate, 2);
                    }
                }
            }
            $cached['exchange_rate'] = $this->exchange_rate;
            return $cached;
        }
        
        $result = [
            'symbol' => $symbol,
            'timestamp' => date('Y-m-d H:i:s'),
            'exchange_rate' => $this->exchange_rate,
            'exchanges' => []
        ];
        
        // 지원하는 거래소만 URL 생성
        $urls = [];
        
        // 업비트
        if ($this->isSupported('upbit', $symbol)) {
            $urls['upbit'] = 'https://api.upbit.com/v1/ticker?markets=KRW-' . $symbol;
        }
        
        // 빗썸
        if ($this->isSupported('bithumb', $symbol)) {
            $urls['bithumb'] = 'https://api.bithumb.com/public/ticker/' . $symbol . '_KRW';
        }
        
        // 코인원
        if ($this->isSupported('coinone', $symbol)) {
            $urls['coinone'] = 'https://api.coinone.co.kr/public/v2/ticker_new/KRW/' . $symbol;
        }
        
        // 코빗
        if ($this->isSupported('korbit', $symbol)) {
            $urls['korbit'] = 'https://api.korbit.co.kr/v1/ticker?currency_pair=' . strtolower($symbol) . '_krw';
        }
        
        // 비트플라이어
        if ($this->isSupported('bitflyer', $symbol)) {
            $urls['bitflyer'] = 'https://api.bitflyer.com/v1/ticker?product_code=' . $symbol . '_USD';
        }
        
        // 바이낸스
        if ($this->isSupported('binance', $symbol)) {
            $urls['binance'] = 'https://api.binance.com/api/v3/ticker/24hr?symbol=' . $symbol . 'USDT';
        }
        
        // 비트파이넥스
        if ($this->isSupported('bitfinex', $symbol)) {
            $urls['bitfinex'] = 'https://api-pub.bitfinex.com/v2/ticker/t' . $symbol . 'USD';
        }
        
        // 병렬로 모든 API 호출
        $responses = $this->fetchMultiData($urls);
        
        // 각 거래소별 응답 처리
        foreach ($responses as $exchange => $response) {
            if ($response) {
                $price = $this->parseExchangeResponse($exchange, $symbol, $response);
                if ($price) {
                    $result['exchanges'][$exchange] = $price;
                }
            }
        }
        
        // 한국 프리미엄 계산
        if (isset($result['exchanges']['binance'])) {
            $binance_krw = $result['exchanges']['binance']['price_krw'];
            
            foreach ($result['exchanges'] as $key => &$exchange) {
                if (!isset($exchange['is_global']) || !$exchange['is_global']) {
                    $premium = $exchange['price_krw'] - $binance_krw;
                    $premium_rate = ($binance_krw > 0) ? round(($premium / $binance_krw) * 100, 2) : 0;
                    
                    $exchange['korea_premium'] = [
                        'amount' => round($premium),
                        'rate' => $premium_rate
                    ];
                }
            }
        }
        
        // 캐시 저장
        $this->setCache($cache_key, $result);
        
        return $result;
    }
    
    /**
     * 시장 정보 가져오기 (환율, 시가총액 등)
     */
    public function getMarketInfo() {
        // 환율은 실시간성이 중요하므로 캐시 체크를 짧게 하거나 무시
        $cache_key = 'market_info';
        $cached = $this->getCache($cache_key);
        
        // 캐시된 데이터가 있어도 5초 이상 지나면 새로 가져오기
        if ($cached) {
            $cache_file = $this->cache_dir . $cache_key . '.json';
            $cache_age = time() - filemtime($cache_file);
            if ($cache_age < 5) {
                return $cached;
            }
        }
        
        $market_info = [
            'exchange_rate' => $this->exchange_rate,
            'total_market_cap' => 0,
            'total_volume_24h' => 0,
            'bitcoin_dominance' => 0,
            'debug_info' => []
        ];
        
        // 네이버 공식 환율 + 백업 API들
        $exchange_rate_apis = [
            [
                'name' => '네이버 환율',
                'url' => 'https://m.search.naver.com/p/csearch/content/qapirender.nhn?key=calculator&pkid=141&q=%ED%99%98%EC%9C%A8&where=m&u1=keb&u6=standardUnit&u7=0&u3=USD&u4=KRW&u8=down&u2=1',
                'parser' => 'naver'
            ],
            [
                'name' => '업비트 USDT',
                'url' => 'https://api.upbit.com/v1/ticker?markets=KRW-USDT',
                'parser' => 'upbit'
            ],
            [
                'name' => 'ExchangeRate-API',
                'url' => 'https://api.exchangerate-api.com/v4/latest/USD',
                'parser' => 'general'
            ]
        ];
        
        foreach ($exchange_rate_apis as $api) {
            $market_info['debug_info'][] = $api['name'] . " API 호출: " . $api['url'];
            $rate_response = $this->fetchDataCurl($api['url']);
            
            if ($rate_response) {
                $rate_data = json_decode($rate_response, true);
                $market_info['debug_info'][] = "응답 받음 (" . $api['name'] . "): " . substr($rate_response, 0, 100);
                
                // JSON 파싱 에러 체크
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $market_info['debug_info'][] = $api['name'] . " JSON 파싱 오류: " . json_last_error_msg();
                    continue;
                }
                
                $rate = null;
                
                switch ($api['parser']) {
                    case 'naver':
                        if (isset($rate_data['country'][1]['value'])) {
                            // 네이버 응답에서 KRW 값 추출 (쉼표 제거)
                            $rate_string = str_replace(',', '', $rate_data['country'][1]['value']);
                            $rate = round(floatval($rate_string), 2);
                            $market_info['debug_info'][] = "네이버 환율 파싱 성공: " . $rate . "원";
                        } else {
                            $market_info['debug_info'][] = "네이버 응답 구조: " . json_encode($rate_data);
                        }
                        break;
                        
                    case 'coingecko':
                        if (isset($rate_data['usd']['krw'])) {
                            $rate = round($rate_data['usd']['krw'], 2);
                            $market_info['debug_info'][] = "CoinGecko 파싱 성공: usd.krw = " . $rate;
                        } else {
                            $market_info['debug_info'][] = "CoinGecko 구조 확인: " . json_encode($rate_data);
                        }
                        break;
                        
                    case 'upbit':
                        if (is_array($rate_data) && isset($rate_data[0]['trade_price'])) {
                            // USDT 가격을 환율로 사용 (USDT ≈ 1 USD)
                            $rate = round($rate_data[0]['trade_price'], 2);
                            $market_info['debug_info'][] = "업비트 USDT 가격: " . $rate . "원";
                        }
                        break;
                        
                    case 'general':
                        if (isset($rate_data['rates']['KRW'])) {
                            $rate = round($rate_data['rates']['KRW'], 2);
                            $market_info['debug_info'][] = "일반 API KRW 환율: " . $rate;
                        }
                        break;
                }
                
                if ($rate) {
                    $market_info['exchange_rate'] = $rate;
                    $this->exchange_rate = $rate;
                    $market_info['debug_info'][] = "환율 성공 (" . $api['name'] . "): " . $rate;
                    break; // 성공하면 다른 API 시도 안함
                } else {
                    $market_info['debug_info'][] = $api['name'] . " 파싱 실패 - rate가 null";
                }
            } else {
                $market_info['debug_info'][] = $api['name'] . " API 호출 실패 - 응답 없음";
            }
        }
        
        // CoinGecko API로 전체 시장 정보 가져오기
        $market_url = 'https://api.coingecko.com/api/v3/global';
        $market_info['debug_info'][] = "CoinGecko 시장 정보 API 호출: " . $market_url;
        $market_response = $this->fetchDataCurl($market_url);
        
        if ($market_response) {
            $market_data = json_decode($market_response, true);
            $market_info['debug_info'][] = "CoinGecko 시장 정보 응답: " . substr($market_response, 0, 200);
            
            if (isset($market_data['data'])) {
                $data = $market_data['data'];
                $market_info['total_market_cap'] = $data['total_market_cap']['usd'] ?? 0;
                $market_info['total_volume_24h'] = $data['total_volume']['usd'] ?? 0;
                $market_info['bitcoin_dominance'] = $data['market_cap_percentage']['btc'] ?? 0;
                $market_info['debug_info'][] = "시장 정보 파싱 성공 - 시총: " . number_format($market_info['total_market_cap']) . ", 거래량: " . number_format($market_info['total_volume_24h']) . ", BTC 점유: " . $market_info['bitcoin_dominance'] . "%";
            } else {
                $market_info['debug_info'][] = "CoinGecko 시장 정보 구조 오류: " . json_encode($market_data);
            }
        } else {
            $market_info['debug_info'][] = "CoinGecko 시장 정보 API 호출 실패";
            
            // CoinGecko 실패 시 기본값 설정
            $market_info['total_market_cap'] = 3500000000000; // 3.5조 달러 (대략적 추정값)
            $market_info['total_volume_24h'] = 120000000000;  // 1200억 달러 (대략적 추정값)
            $market_info['bitcoin_dominance'] = 62.0;        // 62% (대략적 추정값)
            $market_info['debug_info'][] = "기본 시장 정보 사용";
        }
        
        // 캐시 저장 (환율은 5초간만 유지)
        $this->setCache($cache_key, $market_info);
        
        return $market_info;
    }
    
    /**
     * 거래소별 응답 파싱
     */
    private function parseExchangeResponse($exchange, $symbol, $response) {
        $data = json_decode($response, true);
        
        switch ($exchange) {
            case 'upbit':
                if (isset($data[0])) {
                    $coin = $data[0];
                    return [
                        'exchange' => '업비트',
                        'exchange_key' => 'upbit',
                        'symbol' => $symbol,
                        'price_krw' => $coin['trade_price'],
                        'price_usd' => $coin['trade_price'] / $this->exchange_rate,
                        'change_rate' => round($coin['signed_change_rate'] * 100, 2),
                        'change_price' => $coin['signed_change_price'],
                        'volume' => round($coin['acc_trade_volume_24h'], 4)
                    ];
                }
                break;
                
            case 'bithumb':
                if (isset($data['status']) && $data['status'] === '0000') {
                    return [
                        'exchange' => '빗썸',
                        'exchange_key' => 'bithumb',
                        'symbol' => $symbol,
                        'price_krw' => floatval($data['data']['closing_price']),
                        'price_usd' => floatval($data['data']['closing_price']) / $this->exchange_rate,
                        'change_rate' => floatval($data['data']['fluctate_rate_24H']),
                        'change_price' => floatval($data['data']['fluctate_24H']),
                        'volume' => round(floatval($data['data']['units_traded_24H']), 4)
                    ];
                }
                break;
                
            case 'coinone':
                if (isset($data['result']) && $data['result'] === 'success' && isset($data['tickers'][0])) {
                    $ticker = $data['tickers'][0];
                    $last_price = floatval($ticker['last']);
                    $first_price = floatval($ticker['first']);
                    $change_price = $last_price - $first_price;
                    $change_rate = ($first_price > 0) ? round(($change_price / $first_price) * 100, 2) : 0;
                    
                    return [
                        'exchange' => '코인원',
                        'exchange_key' => 'coinone',
                        'symbol' => $symbol,
                        'price_krw' => $last_price,
                        'price_usd' => $last_price / $this->exchange_rate,
                        'change_rate' => $change_rate,
                        'change_price' => $change_price,
                        'volume' => round(floatval($ticker['target_volume']), 4)
                    ];
                }
                break;
                
            case 'korbit':
                if (isset($data['last'])) {
                    $last_price = floatval($data['last']);
                    $volume = isset($data['volume']) ? floatval($data['volume']) : 0;
                    
                    // detailed API도 함께 호출
                    $detailed_url = 'https://api.korbit.co.kr/v1/ticker/detailed?currency_pair=' . strtolower($symbol) . '_krw';
                    $detailed_response = $this->fetchDataCurl($detailed_url);
                    
                    $change_price = 0;
                    $change_rate = 0;
                    
                    if ($detailed_response) {
                        $detailed = json_decode($detailed_response, true);
                        if (isset($detailed['change'])) {
                            $change_price = floatval($detailed['change']);
                            $prev_price = $last_price - $change_price;
                            if ($prev_price > 0) {
                                $change_rate = round(($change_price / $prev_price) * 100, 2);
                            }
                        }
                    }
                    
                    return [
                        'exchange' => '코빗',
                        'exchange_key' => 'korbit',
                        'symbol' => $symbol,
                        'price_krw' => $last_price,
                        'price_usd' => $last_price / $this->exchange_rate,
                        'change_rate' => $change_rate,
                        'change_price' => $change_price,
                        'volume' => round($volume, 2)
                    ];
                }
                break;
                
            case 'bitflyer':
                if (isset($data['ltp'])) {
                    // USD로 직접 가져오므로 변환 불필요
                    $price_usd = floatval($data['ltp']);
                    $price_krw = $price_usd * $this->exchange_rate; // USD → KRW
                    
                    // BitFlyer는 24시간 변동률 정보가 없으므로 0으로 설정
                    $change_rate = 0;
                    $change_price = 0;
                    
                    return [
                        'exchange' => '플라이어',
                        'exchange_key' => 'bitflyer',
                        'symbol' => $symbol,
                        'price_krw' => round($price_krw),
                        'price_usd' => round($price_usd, 2),
                        'change_rate' => $change_rate,
                        'change_price' => $change_price,
                        'volume' => round(floatval($data['volume_by_product'] ?? 0), 2),
                        'is_global' => true,
                        'note' => 'USD 직접 거래'
                    ];
                }
                break;
                
            case 'binance':
                if (isset($data['lastPrice'])) {
                    return [
                        'exchange' => '바이낸스',
                        'exchange_key' => 'binance',
                        'symbol' => $symbol,
                        'price_krw' => floatval($data['lastPrice']) * $this->exchange_rate,
                        'price_usd' => floatval($data['lastPrice']),
                        'change_rate' => floatval($data['priceChangePercent']),
                        'change_price' => floatval($data['priceChange']) * $this->exchange_rate,
                        'volume' => round(floatval($data['volume']), 4),
                        'is_global' => true
                    ];
                }
                break;
                
            case 'bitfinex':
                if (is_array($data) && count($data) >= 7) {
                    $last_price = $data[6];
                    $daily_change = $data[4];
                    $daily_change_perc = $data[5] * 100;
                    $volume = $data[7];
                    
                    return [
                        'exchange' => '파이넥스',
                        'exchange_key' => 'bitfinex',
                        'symbol' => $symbol,
                        'price_krw' => $last_price * $this->exchange_rate,
                        'price_usd' => $last_price,
                        'change_rate' => round($daily_change_perc, 2),
                        'change_price' => $daily_change * $this->exchange_rate,
                        'volume' => round($volume, 4),
                        'is_global' => true
                    ];
                }
                break;
        }
        
        return null;
    }
}

// API 엔드포인트
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$tracker = new CryptoPriceTracker();

// 요청 파라미터 처리
$action = $_GET['action'] ?? 'single';
$symbol = strtoupper($_GET['symbol'] ?? 'BTC');

try {
    switch ($action) {
        case 'single':
            $result = $tracker->getAllPrices($symbol);
            break;
            
        case 'market_info':
            $result = $tracker->getMarketInfo();
            break;
            
        default:
            $result = ['error' => 'Invalid action'];
    }
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
?>