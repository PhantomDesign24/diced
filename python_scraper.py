#!/usr/bin/env python3
"""
속도 최적화된 대부업체 정보 수집 파이썬 스크립트

이 스크립트는 금융감독원의 등록대부업체 통합조회 페이지에서 데이터를 수집하여
CSV 파일로 저장합니다. 속도 최적화를 위한 여러 기법이 적용되었습니다.
"""

import os
import time
import pandas as pd
from selenium import webdriver
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import TimeoutException, NoSuchElementException
from webdriver_manager.chrome import ChromeDriverManager
import concurrent.futures
import threading
from queue import Queue

# 설정
OUTPUT_DIR = os.path.dirname(os.path.abspath(__file__))
CSV_FILENAME = os.path.join(OUTPUT_DIR, 'moneylenders_data.csv')
LOG_FILENAME = os.path.join(OUTPUT_DIR, 'crawler_log.txt')
URL = "https://fines.fss.or.kr/fines/plis/moneyLenderSearch/MoneyLenderSearch.getMoneyLenderList.do"

# 페이지 설정
START_PAGE = 1
END_PAGE = 1051  # 필요에 따라 변경

# 병렬 처리 설정
MAX_WORKERS = 4  # 병렬 처리할 워커(브라우저) 수
PAGES_PER_BATCH = 25  # 한 번에 처리할 페이지 수
DELAY_BETWEEN_PAGES = 0.5  # 페이지 이동 간 딜레이 (초)
WAIT_TIMEOUT = 15  # 요소 대기 시간 (초)

# 성능 최적화 설정
DISABLE_IMAGES = True  # 이미지 로딩 비활성화
DISABLE_CSS = False  # CSS 로딩 비활성화 (테이블 구조에 영향을 줄 수 있음)
DISABLE_JAVASCRIPT = False  # JavaScript 비활성화 (페이지네이션에 필요할 수 있음)

# 통계 변수 (스레드 안전)
stats_lock = threading.Lock()
stats = {
    'total_processed': 0,
    'total_collected': 0,
    'new_records': 0,
    'updated_records': 0,
    'unchanged_records': 0,
    'pages_processed': 0,
    'pages_failed': 0
}

# 데이터 큐와 결과 관리
data_queue = Queue()
existing_data = None
existing_data_lock = threading.Lock()

# 로깅 함수
def log_message(message):
    """로그 메시지를 파일에 기록하고 콘솔에 출력합니다."""
    timestamp = time.strftime("%Y-%m-%d %H:%M:%S")
    log_entry = f"[{timestamp}] {message}\n"
    
    # 스레드 안전한 로깅
    with open(LOG_FILENAME, 'a', encoding='utf-8') as f:
        f.write(log_entry)
    
    print(message)

def setup_driver():
    """Selenium WebDriver를 설정합니다."""
    options = Options()
    options.add_argument('--headless')
    options.add_argument('--no-sandbox')
    options.add_argument('--disable-dev-shm-usage')
    options.add_argument('--disable-gpu')
    options.add_argument('--window-size=1920,1080')
    
    # 성능 최적화 옵션
    if DISABLE_IMAGES:
        options.add_argument('--blink-settings=imagesEnabled=false')
    if DISABLE_CSS:
        options.add_argument('--disable-css')
    if DISABLE_JAVASCRIPT:
        options.add_argument('--disable-javascript')
    
    # 캐시 최적화
    options.add_argument('--disk-cache-size=52428800')  # 50MB 캐시
    options.add_argument('--media-cache-size=52428800')  # 50MB 미디어 캐시
    
    # 메모리 최적화
    options.add_argument('--js-flags="--max_old_space_size=128"')  # JavaScript 메모리 제한
    
    # 불필요한 기능 비활성화
    prefs = {
        'profile.default_content_setting_values': {
            'notifications': 2,
            'plugins': 2,
            'popups': 2
        },
        'download.prompt_for_download': False,
        'download.directory_upgrade': True,
        'safebrowsing.enabled': False,
        'safebrowsing.disable_download_protection': True
    }
    options.add_experimental_option('prefs', prefs)
    
    # User-Agent 설정
    options.add_argument('--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36')
    
    try:
        # 자동으로 적합한 ChromeDriver 설치 및 사용
        service = Service(ChromeDriverManager().install())
        driver = webdriver.Chrome(service=service, options=options)
        return driver
    except Exception as e:
        log_message(f"WebDriver 설정 실패: {str(e)}")
        raise

def load_existing_data():
    """기존 CSV 파일이 있으면 로드합니다."""
    global existing_data
    
    if os.path.exists(CSV_FILENAME):
        try:
            df = pd.read_csv(CSV_FILENAME, encoding='utf-8-sig')
            log_message(f"기존 데이터 로드 완료: {len(df)}개 레코드")
            
            # 데이터 조회 최적화를 위해 딕셔너리로 변환
            existing_data = {}
            for _, row in df.iterrows():
                reg_num = row['register_number']
                if reg_num and reg_num != '':
                    existing_data[reg_num] = row.to_dict()
            
            return df
        except Exception as e:
            log_message(f"기존 데이터 로드 실패: {str(e)}")
    
    log_message("기존 데이터가 없습니다.")
    existing_data = {}
    return None

def navigate_to_page(driver, page_num):
    """특정 페이지로 이동합니다."""
    max_retries = 3
    retry_count = 0
    
    while retry_count < max_retries:
        try:
            # JavaScript로 페이지 이동
            driver.execute_script(f"movePage('{page_num}');")
            
            # 테이블이 로드될 때까지 기다림
            WebDriverWait(driver, WAIT_TIMEOUT).until(
                EC.presence_of_element_located((By.CLASS_NAME, "bbs_list"))
            )
            
            # 안정성을 위한 짧은 대기
            time.sleep(DELAY_BETWEEN_PAGES)
            
            return True
        except TimeoutException:
            retry_count += 1
            if retry_count >= max_retries:
                return False
                
            # 페이지 새로고침 후 재시도
            driver.refresh()
            time.sleep(2)
        except Exception as e:
            retry_count += 1
            if retry_count >= max_retries:
                return False
            time.sleep(2)
    
    return False

def extract_company_data(driver):
    """현재 페이지에서 대부업체 정보를 추출합니다."""
    try:
        # 테이블 찾기
        table = WebDriverWait(driver, WAIT_TIMEOUT).until(
            EC.presence_of_element_located((By.CLASS_NAME, "bbs_list"))
        )
        
        rows = table.find_elements(By.XPATH, "./tbody/tr")
        
        if not rows:
            return []
        
        companies = []
        
        for row in rows:
            cells = row.find_elements(By.TAG_NAME, "td")
            
            if len(cells) < 14:
                continue
            
            # 각 컬럼에서 데이터 추출
            company = {
                'register_number': cells[1].text.strip(),
                'company_name': ' '.join(cells[2].text.strip().split()),
                'corp_number': cells[3].text.strip(),
                'office_type': cells[4].text.strip(),
                'business_type': cells[5].text.strip(),
                'valid_period': cells[6].text.strip(),
                'representative': cells[7].text.strip(),
                'address_jibun': cells[8].text.strip(),
                'address_road': cells[9].text.strip(),
                'phone': cells[10].text.strip().replace('\n', ', '),
                'ad_phone': cells[11].text.strip().replace('\n', ', '),
                'register_agency': cells[12].text.strip(),
                'operation_status': cells[13].text.strip()
            }
            
            companies.append(company)
            
            # 스레드 안전한 통계 업데이트
            with stats_lock:
                stats['total_collected'] += 1
        
        return companies
    except Exception as e:
        return []

def update_data(new_data_batch):
    """
    새 데이터를 기존 데이터와 비교하여 변경 사항을 찾고 업데이트합니다.
    """
    global existing_data
    
    if not new_data_batch:
        return
    
    # 업데이트할 새 데이터와 변경된 데이터 저장용
    new_records = []
    updated_records = []
    unchanged_count = 0
    
    # 각 레코드 처리
    for company in new_data_batch:
        reg_num = company['register_number']
        
        if not reg_num or reg_num == '':
            continue
            
        with existing_data_lock:
            if reg_num in existing_data:
                # 기존 레코드가 있는 경우
                old_record = existing_data[reg_num]
                
                # 변경 사항 확인
                changed = False
                for key, value in company.items():
                    if str(value) != str(old_record.get(key, '')):
                        changed = True
                        break
                
                if changed:
                    # 변경 사항이 있으면 업데이트
                    updated_records.append(company)
                    existing_data[reg_num] = company  # 메모리 내 데이터 업데이트
                    with stats_lock:
                        stats['updated_records'] += 1
                else:
                    # 변경 사항이 없으면 스킵
                    unchanged_count += 1
                    with stats_lock:
                        stats['unchanged_records'] += 1
            else:
                # 새 레코드인 경우
                new_records.append(company)
                existing_data[reg_num] = company  # 메모리 내 데이터에 추가
                with stats_lock:
                    stats['new_records'] += 1
    
    # 데이터 큐에 저장 (주기적으로 파일에 기록)
    if new_records or updated_records:
        data_queue.put((new_records, updated_records))

def process_page_batch(page_range):
    """
    주어진 페이지 범위를 처리합니다.
    이 함수는 각 스레드/프로세스에서 실행됩니다.
    """
    # 각 워커마다 새 브라우저 인스턴스 생성
    driver = setup_driver()
    
    try:
        # 초기 페이지 접속
        driver.get(URL)
        WebDriverWait(driver, WAIT_TIMEOUT).until(
            EC.presence_of_element_located((By.CLASS_NAME, "bbs_list"))
        )
        
        # 첫 페이지가 시작 페이지가 아닌 경우 해당 페이지로 이동
        if page_range[0] > 1:
            if not navigate_to_page(driver, page_range[0]):
                log_message(f"시작 페이지 {page_range[0]} 이동 실패")
                with stats_lock:
                    stats['pages_failed'] += len(page_range)
                return
        
        # 수집한 회사 데이터
        collected_companies = []
        
        # 각 페이지 처리
        for page in page_range:
            if page > page_range[0]:
                # 첫 페이지가 아닌 경우 페이지 이동
                if not navigate_to_page(driver, page):
                    with stats_lock:
                        stats['pages_failed'] += 1
                    continue
            
            # 현재 페이지에서 데이터 추출
            companies = extract_company_data(driver)
            
            if companies:
                collected_companies.extend(companies)
                with stats_lock:
                    stats['pages_processed'] += 1
            else:
                with stats_lock:
                    stats['pages_failed'] += 1
                    
            with stats_lock:
                stats['total_processed'] += 1
        
        # 수집한 데이터 업데이트
        if collected_companies:
            update_data(collected_companies)
            
    except Exception as e:
        log_message(f"페이지 배치 {page_range} 처리 오류: {str(e)}")
    finally:
        # 브라우저 종료
        driver.quit()

def save_data_worker():
    """
    데이터 큐에서 새로운 데이터를 주기적으로 가져와 파일에 저장합니다.
    """
    global existing_data
    
    # 파일이 없으면 생성
    if not os.path.exists(CSV_FILENAME):
        with open(CSV_FILENAME, 'w', encoding='utf-8-sig') as f:
            f.write(','.join([
                'register_number', 'company_name', 'corp_number', 'office_type',
                'business_type', 'valid_period', 'representative', 'address_jibun',
                'address_road', 'phone', 'ad_phone', 'register_agency', 'operation_status'
            ]) + '\n')
    
    # 기존 CSV 파일 로드
    df = pd.read_csv(CSV_FILENAME, encoding='utf-8-sig') if os.path.exists(CSV_FILENAME) else pd.DataFrame()
    
    while True:
        try:
            # 큐에서 데이터 가져오기 (새 레코드, 업데이트된 레코드)
            new_records, updated_records = data_queue.get(timeout=5)
            
            # 데이터 처리
            if new_records or updated_records:
                # 기존 레코드에서 업데이트할 레코드 제거
                if updated_records and not df.empty:
                    update_reg_nums = [record['register_number'] for record in updated_records]
                    df = df[~df['register_number'].isin(update_reg_nums)]
                
                # 새 레코드와 업데이트된 레코드 추가
                new_df = pd.DataFrame(new_records + updated_records)
                if df.empty:
                    df = new_df
                else:
                    df = pd.concat([df, new_df], ignore_index=True)
                
                # CSV 파일에 저장
                df.to_csv(CSV_FILENAME, index=False, encoding='utf-8-sig')
                log_message(f"데이터 저장 완료: 신규 {len(new_records)}개, 업데이트 {len(updated_records)}개")
            
            # 작업 완료 표시
            data_queue.task_done()
            
        except Exception as e:
            # 타임아웃이나 오류 발생 시 (작업 완료 확인용)
            if data_queue.empty() and threading.active_count() <= 2:  # 메인 스레드와 현재 스레드만 남음
                break
    
    log_message("데이터 저장 작업 완료")

def create_page_batches(start_page, end_page, batch_size):
    """페이지를 배치로 나눕니다."""
    batches = []
    for i in range(start_page, end_page + 1, batch_size):
        batches.append(range(i, min(i + batch_size, end_page + 1)))
    return batches

def format_time(seconds):
    """시간을 hh:mm:ss 형식으로 포맷팅합니다."""
    m, s = divmod(int(seconds), 60)
    h, m = divmod(m, 60)
    return f"{h:02d}:{m:02d}:{s:02d}" if h else f"{m:02d}:{s:02d}"

def main():
    """메인 함수"""
    start_time = time.time()
    log_message("대부업체 정보 수집 시작")
    log_message(f"처리 범위: {START_PAGE}에서 {END_PAGE}까지, {MAX_WORKERS}개 워커로 병렬 처리")
    
    # 기존 데이터 로드
    load_existing_data()
    
    # 데이터 저장 스레드 시작
    save_thread = threading.Thread(target=save_data_worker, daemon=True)
    save_thread.start()
    
    # 페이지 배치 생성
    page_batches = create_page_batches(START_PAGE, END_PAGE, PAGES_PER_BATCH)
    log_message(f"총 {len(page_batches)}개 배치로 나누어 처리합니다.")
    
    # 병렬 처리 시작
    with concurrent.futures.ThreadPoolExecutor(max_workers=MAX_WORKERS) as executor:
        future_to_batch = {executor.submit(process_page_batch, batch): batch for batch in page_batches}
        completed = 0
        
        # 진행 상황 모니터링
        for future in concurrent.futures.as_completed(future_to_batch):
            batch = future_to_batch[future]
            completed += 1
            
            try:
                # 예외가 있으면 처리
                future.result()
            except Exception as e:
                log_message(f"배치 {batch} 처리 중 예외 발생: {str(e)}")
            
            # 진행 상황 업데이트
            elapsed = time.time() - start_time
            if completed > 0:
                avg_time_per_batch = elapsed / completed
                remaining_batches = len(page_batches) - completed
                estimated_remaining = avg_time_per_batch * remaining_batches
            else:
                estimated_remaining = 0
            
            log_message(
                f"진행 상황: {completed}/{len(page_batches)} 배치 완료 | "
                f"처리된 페이지: {stats['pages_processed']}/{END_PAGE - START_PAGE + 1} | "
                f"수집: {stats['total_collected']}개 | "
                f"예상 남은 시간: {format_time(estimated_remaining)}"
            )
    
    # 모든 데이터가 저장될 때까지 대기
    data_queue.join()
    
    # 수행 시간 계산
    total_time = time.time() - start_time
    
    log_message(
        f"크롤링 완료!\n"
        f"- 처리된 페이지: {stats['pages_processed']}/{END_PAGE - START_PAGE + 1}\n"
        f"- 실패한 페이지: {stats['pages_failed']}\n"
        f"- 총 수집 레코드: {stats['total_collected']}개\n"
        f"- 신규 레코드: {stats['new_records']}개\n"
        f"- 변경된 레코드: {stats['updated_records']}개\n"
        f"- 변경 없는 레코드: {stats['unchanged_records']}개\n"
        f"- 총 소요 시간: {format_time(total_time)}\n"
        f"- 평균 처리 속도: {stats['total_processed'] / total_time:.2f} 페이지/초"
    )

if __name__ == "__main__":
    main()