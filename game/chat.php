<?php
/*
* 파일명: main.php
* 위치: /main.php (메인페이지)
* 기능: 데이팅 앱 스타일 메인페이지 (그누보드5 기반)
* 작성일: 2025-06-12
* 수정일: 2025-06-12
*/

// ===================================
// 그누보드 환경 설정
// ===================================
include_once('../common.php');

// 페이지 제목 설정
$g5['title'] = '메인페이지';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>새로운 매칭</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
        <link rel="stylesheet" href="<?php echo G5_URL?>/game/css/game.css">

    <style>
        /* 기본 스타일 */
        * {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        body {
            background: #f8f9fa;
            min-height: 100vh;
            margin: 0;
            padding: 0;
            padding-bottom: 80px;
        }
        
        .main-container {
            max-width: 420px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
            position: relative;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        
        /* 헤더 영역 */
        .header-section {
            background: #ff4757;
            color: white;
            padding: 20px;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(255, 71, 87, 0.2);
        }
        
        .header-title {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
        }
        
        .header-subtitle {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 4px;
        }
        
        /* 검색 및 필터 섹션 */
        .search-section {
            background: white;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
            position: sticky;
            top: 80px;
            z-index: 99;
        }
        
        .search-box {
            position: relative;
            margin-bottom: 16px;
        }
        
        .search-input {
            width: 100%;
            padding: 12px 16px 12px 45px;
            border: 2px solid #e9ecef;
            border-radius: 25px;
            background: #f8f9fa;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #ff4757;
            background: white;
            box-shadow: 0 0 0 3px rgba(255, 71, 87, 0.1);
        }
        
        .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 16px;
        }
        
        /* 필터 태그 */
        .filter-tags {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 8px;
        }
        
        .filter-tag {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #495057;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .filter-tag.active {
            background: #ff4757;
            border-color: #ff4757;
            color: white;
        }
        
        .filter-tag:hover {
            background: #e9ecef;
        }
        
        .filter-tag.active:hover {
            background: #ff3742;
        }
        
        /* 프로필 리스트 섹션 */
        .profile-list-section {
            padding: 20px;
        }
        
        .section-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin: 0;
        }
        
        .result-count {
            font-size: 14px;
            color: #6c757d;
        }
        
        /* 프로필 카드 리스트 */
        .profile-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .profile-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border: 1px solid #f1f3f4;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            position: relative;
        }
        
        .profile-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
            color: inherit;
            text-decoration: none;
        }
        
        .profile-header {
            position: relative;
            height: 280px;
            background-size: cover;
            background-position: center;
            overflow: hidden;
        }
        
        .profile-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.7));
            color: white;
            padding: 24px 16px 16px;
        }
        
        .profile-name {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .profile-age {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 6px;
        }
        
        .profile-location {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 13px;
            opacity: 0.8;
        }
        
        /* 상태 표시 */
        .online-status {
            position: absolute;
            top: 16px;
            left: 16px;
            background: #2ed573;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .online-dot {
            width: 6px;
            height: 6px;
            background: white;
            border-radius: 50%;
            animation: blink 1.5s infinite;
        }
        
        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0.3; }
        }
        
        .new-badge {
            position: absolute;
            top: 16px;
            right: 16px;
            background: #ff4757;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        /* 좋아요 버튼 */
        .like-button {
            position: absolute;
            bottom: 16px;
            right: 16px;
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            color: #ff4757;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .like-button:hover {
            background: #ff4757;
            color: white;
            transform: scale(1.1);
        }
        
        .like-button.liked {
            background: #ff4757;
            color: white;
        }
        
        /* 프로필 정보 */
        .profile-info {
            padding: 16px;
        }
        
        .profile-intro {
            font-size: 14px;
            color: #495057;
            line-height: 1.5;
            margin-bottom: 12px;
        }
        
        .profile-interests {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .interest-tag {
            background: #f8f9fa;
            color: #495057;
            padding: 4px 10px;
            border-radius: 16px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .interest-tag.highlight {
            background: #fff3cd;
            color: #856404;
        }
        
        /* 마지막 활동 시간 */
        .last-activity {
            position: absolute;
            top: 16px;
            right: 16px;
            background: rgba(0, 0, 0, 0.6);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            backdrop-filter: blur(10px);
        }
        
        /* 빈 상태 */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .empty-icon {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .empty-title {
            font-size: 18px;
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
        }
        
        .empty-text {
            font-size: 14px;
            color: #6c757d;
        }
        
        /* 반응형 */
        @media (max-width: 375px) {
            .profile-list-section {
                padding: 16px;
            }
            
            .search-section {
                padding: 16px;
            }
            
            .profile-header {
                height: 240px;
            }
            
            .profile-info {
                padding: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- 헤더 영역 -->
        <div class="header-section">
            <h1 class="header-title">💕 새로운 매칭</h1>
            <p class="header-subtitle">특별한 인연을 찾아보세요</p>
        </div>
        
        <!-- 검색 및 필터 섹션 -->
        <div class="search-section">
            <div class="search-box">
                <i class="bi bi-search search-icon"></i>
                <input type="text" class="search-input" placeholder="이름이나 관심사로 검색..." id="searchInput">
            </div>
            
            <div class="filter-tags">
                <span class="filter-tag active" data-filter="all">전체</span>
                <span class="filter-tag" data-filter="online">온라인</span>
                <span class="filter-tag" data-filter="new">신규</span>
                <span class="filter-tag" data-filter="nearby">근처</span>
                <span class="filter-tag" data-filter="verified">인증</span>
                <span class="filter-tag" data-filter="20s">20대</span>
                <span class="filter-tag" data-filter="30s">30대</span>
            </div>
        </div>
        
        <!-- 프로필 리스트 섹션 -->
        <div class="profile-list-section">
            <div class="section-info">
                <h2 class="section-title">추천 프로필</h2>
                <span class="result-count" id="resultCount">12명</span>
            </div>
            
            <div class="profile-list" id="profileList">
<!-- 프로필 카드 01 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="profile-card" data-category="new 20s">
    <div class="profile-header" style="background-image: url('<?php echo G5_URL?>/game/img/경기 성남시 박주희.jpg');">
        <div class="online-status"><div class="online-dot"></div>온라인</div>
        <div class="new-badge">NEW</div>
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="profile-overlay">
            <h3 class="profile-name">박주희</h3>
            <p class="profile-age">24세</p>
            <div class="profile-location"><i class="bi bi-geo-alt-fill"></i> 성남시, 경기</div>
        </div>
    </div>
    <div class="profile-info">
        <p class="profile-intro">안녕하세요! 좋은 인연을 찾고 있는 박주희입니다 😊</p>
        <div class="profile-interests">
            <span class="interest-tag highlight">사진</span>
            <span class="interest-tag">카페</span>
            <span class="interest-tag">독서</span>
            <span class="interest-tag">요리</span>
        </div>
    </div>
</a>

<!-- 프로필 카드 02 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="profile-card" data-category="new 20s">
    <div class="profile-header" style="background-image: url('<?php echo G5_URL?>/game/img/경기 세종시 정윤주.jpg');">
        <div class="online-status"><div class="online-dot"></div>온라인</div>
        <div class="new-badge">NEW</div>
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="profile-overlay">
            <h3 class="profile-name">정윤주</h3>
            <p class="profile-age">26세</p>
            <div class="profile-location"><i class="bi bi-geo-alt-fill"></i> 세종시, 경기</div>
        </div>
    </div>
    <div class="profile-info">
        <p class="profile-intro">안녕하세요! 좋은 인연을 찾고 있는 정윤주입니다 😊</p>
        <div class="profile-interests">
            <span class="interest-tag highlight">헬스</span>
            <span class="interest-tag">여행</span>
            <span class="interest-tag">사진</span>
            <span class="interest-tag">영화</span>
        </div>
    </div>
</a>

<!-- 프로필 카드 03 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="profile-card" data-category="new 20s">
    <div class="profile-header" style="background-image: url('<?php echo G5_URL?>/game/img/경기 시흥시 이하얀.jpg');">
        <div class="online-status"><div class="online-dot"></div>온라인</div>
        <div class="new-badge">NEW</div>
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="profile-overlay">
            <h3 class="profile-name">이하얀</h3>
            <p class="profile-age">23세</p>
            <div class="profile-location"><i class="bi bi-geo-alt-fill"></i> 시흥시, 경기</div>
        </div>
    </div>
    <div class="profile-info">
        <p class="profile-intro">안녕하세요! 좋은 인연을 찾고 있는 이하얀입니다 😊</p>
        <div class="profile-interests">
            <span class="interest-tag highlight">요리</span>
            <span class="interest-tag">독서</span>
            <span class="interest-tag">카페</span>
            <span class="interest-tag">사진</span>
        </div>
    </div>
</a>

<!-- 프로필 카드 04 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="profile-card" data-category="new 20s">
    <div class="profile-header" style="background-image: url('<?php echo G5_URL?>/game/img/광주 북구 황하나.jpg');">
        <div class="online-status"><div class="online-dot"></div>온라인</div>
        <div class="new-badge">NEW</div>
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="profile-overlay">
            <h3 class="profile-name">황하나</h3>
            <p class="profile-age">25세</p>
            <div class="profile-location"><i class="bi bi-geo-alt-fill"></i> 북구, 광주</div>
        </div>
    </div>
    <div class="profile-info">
        <p class="profile-intro">안녕하세요! 좋은 인연을 찾고 있는 황하나입니다 😊</p>
        <div class="profile-interests">
            <span class="interest-tag highlight">영화</span>
            <span class="interest-tag">카페</span>
            <span class="interest-tag">헬스</span>
            <span class="interest-tag">요리</span>
        </div>
    </div>
</a>

<!-- 프로필 카드 05 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="profile-card" data-category="new 20s">
    <div class="profile-header" style="background-image: url('<?php echo G5_URL?>/game/img/대구 수성구 강연수.jpg');">
        <div class="online-status"><div class="online-dot"></div>온라인</div>
        <div class="new-badge">NEW</div>
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="profile-overlay">
            <h3 class="profile-name">강연수</h3>
            <p class="profile-age">27세</p>
            <div class="profile-location"><i class="bi bi-geo-alt-fill"></i> 수성구, 대구</div>
        </div>
    </div>
    <div class="profile-info">
        <p class="profile-intro">안녕하세요! 좋은 인연을 찾고 있는 강연수입니다 😊</p>
        <div class="profile-interests">
            <span class="interest-tag highlight">여행</span>
            <span class="interest-tag">독서</span>
            <span class="interest-tag">요리</span>
            <span class="interest-tag">헬스</span>
        </div>
    </div>
</a>

<!-- 프로필 카드 06 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="profile-card" data-category="new 20s">
    <div class="profile-header" style="background-image: url('<?php echo G5_URL?>/game/img/대구 중구 윤민정.jpg');">
        <div class="online-status"><div class="online-dot"></div>온라인</div>
        <div class="new-badge">NEW</div>
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="profile-overlay">
            <h3 class="profile-name">윤민정</h3>
            <p class="profile-age">22세</p>
            <div class="profile-location"><i class="bi bi-geo-alt-fill"></i> 중구, 대구</div>
        </div>
    </div>
    <div class="profile-info">
        <p class="profile-intro">안녕하세요! 좋은 인연을 찾고 있는 윤민정입니다 😊</p>
        <div class="profile-interests">
            <span class="interest-tag highlight">카페</span>
            <span class="interest-tag">문학</span>
            <span class="interest-tag">사진</span>
            <span class="interest-tag">영화</span>
        </div>
    </div>
</a>

<!-- 프로필 카드 07 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="profile-card" data-category="new 20s">
    <div class="profile-header" style="background-image: url('<?php echo G5_URL?>/game/img/부산 동래구 김민영.jpg');">
        <div class="online-status"><div class="online-dot"></div>온라인</div>
        <div class="new-badge">NEW</div>
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="profile-overlay">
            <h3 class="profile-name">김민영</h3>
            <p class="profile-age">24세</p>
            <div class="profile-location"><i class="bi bi-geo-alt-fill"></i> 동래구, 부산</div>
        </div>
    </div>
    <div class="profile-info">
        <p class="profile-intro">안녕하세요! 좋은 인연을 찾고 있는 김민영입니다 😊</p>
        <div class="profile-interests">
            <span class="interest-tag highlight">와인</span>
            <span class="interest-tag">요리</span>
            <span class="interest-tag">카페</span>
            <span class="interest-tag">독서</span>
        </div>
    </div>
</a>

<!-- 프로필 카드 08 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="profile-card" data-category="new 20s">
    <div class="profile-header" style="background-image: url('<?php echo G5_URL?>/game/img/부산 중구 이미래.jpg');">
        <div class="online-status"><div class="online-dot"></div>온라인</div>
        <div class="new-badge">NEW</div>
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="profile-overlay">
            <h3 class="profile-name">이미래</h3>
            <p class="profile-age">21세</p>
            <div class="profile-location"><i class="bi bi-geo-alt-fill"></i> 중구, 부산</div>
        </div>
    </div>
    <div class="profile-info">
        <p class="profile-intro">안녕하세요! 좋은 인연을 찾고 있는 이미래입니다 😊</p>
        <div class="profile-interests">
            <span class="interest-tag highlight">여행</span>
            <span class="interest-tag">언어</span>
            <span class="interest-tag">카페</span>
            <span class="interest-tag">사진</span>
        </div>
    </div>
</a>

<!-- 프로필 카드 09 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="profile-card" data-category="new 20s">
    <div class="profile-header" style="background-image: url('<?php echo G5_URL?>/game/img/서울 강남구 김윤정.jpg');">
        <div class="online-status"><div class="online-dot"></div>온라인</div>
        <div class="new-badge">NEW</div>
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="profile-overlay">
            <h3 class="profile-name">김윤정</h3>
            <p class="profile-age">28세</p>
            <div class="profile-location"><i class="bi bi-geo-alt-fill"></i> 강남구, 서울</div>
        </div>
    </div>
    <div class="profile-info">
        <p class="profile-intro">안녕하세요! 좋은 인연을 찾고 있는 김윤정입니다 😊</p>
        <div class="profile-interests">
            <span class="interest-tag highlight">음악</span>
            <span class="interest-tag">사진</span>
            <span class="interest-tag">독서</span>
            <span class="interest-tag">영화</span>
        </div>
    </div>
</a>

<!-- 프로필 카드 10 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="profile-card" data-category="new 20s">
    <div class="profile-header" style="background-image: url('<?php echo G5_URL?>/game/img/서울 관악구 민수진.jpg');">
        <div class="online-status"><div class="online-dot"></div>온라인</div>
        <div class="new-badge">NEW</div>
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="profile-overlay">
            <h3 class="profile-name">민수진</h3>
            <p class="profile-age">26세</p>
            <div class="profile-location"><i class="bi bi-geo-alt-fill"></i> 관악구, 서울</div>
        </div>
    </div>
    <div class="profile-info">
        <p class="profile-intro">안녕하세요! 좋은 인연을 찾고 있는 민수진입니다 😊</p>
        <div class="profile-interests">
            <span class="interest-tag highlight">카페</span>
            <span class="interest-tag">요리</span>
            <span class="interest-tag">여행</span>
            <span class="interest-tag">헬스</span>
        </div>
    </div>
</a>

<!-- 프로필 카드 11 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="profile-card" data-category="new 20s">
    <div class="profile-header" style="background-image: url('<?php echo G5_URL?>/game/img/서울 노원구 김소현.jpg');">
        <div class="online-status"><div class="online-dot"></div>온라인</div>
        <div class="new-badge">NEW</div>
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="profile-overlay">
            <h3 class="profile-name">김소현</h3>
            <p class="profile-age">25세</p>
            <div class="profile-location"><i class="bi bi-geo-alt-fill"></i> 노원구, 서울</div>
        </div>
    </div>
    <div class="profile-info">
        <p class="profile-intro">안녕하세요! 좋은 인연을 찾고 있는 김소현입니다 😊</p>
        <div class="profile-interests">
            <span class="interest-tag highlight">영화</span>
            <span class="interest-tag">음악</span>
            <span class="interest-tag">브런치</span>
            <span class="interest-tag">요가</span>
        </div>
    </div>
</a>

<!-- 프로필 카드 12 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="profile-card" data-category="new 20s">
    <div class="profile-header" style="background-image: url('<?php echo G5_URL?>/game/img/서울 마포구 강빛나.png');">
        <div class="online-status"><div class="online-dot"></div>온라인</div>
        <div class="new-badge">NEW</div>
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="profile-overlay">
            <h3 class="profile-name">강빛나</h3>
            <p class="profile-age">23세</p>
            <div class="profile-location"><i class="bi bi-geo-alt-fill"></i> 마포구, 서울</div>
        </div>
    </div>
    <div class="profile-info">
        <p class="profile-intro">안녕하세요! 좋은 인연을 찾고 있는 강빛나입니다 😊</p>
        <div class="profile-interests">
            <span class="interest-tag highlight">문학</span>
            <span class="interest-tag">카페</span>
            <span class="interest-tag">사진</span>
            <span class="interest-tag">요리</span>
        </div>
    </div>
</a>


<!-- 프로필 카드 13 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="profile-card" data-category="new 20s">
    <div class="profile-header" style="background-image: url('<?php echo G5_URL?>/game/img/울산 중구 서예빈.jpg');">
        <div class="online-status"><div class="online-dot"></div>온라인</div>
        <div class="new-badge">NEW</div>
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="profile-overlay">
            <h3 class="profile-name">서예빈</h3>
            <p class="profile-age">24세</p>
            <div class="profile-location"><i class="bi bi-geo-alt-fill"></i> 중구, 울산</div>
        </div>
    </div>
    <div class="profile-info">
        <p class="profile-intro">안녕하세요! 좋은 인연을 찾고 있는 서예빈입니다 😊</p>
        <div class="profile-interests">
            <span class="interest-tag highlight">사진</span>
            <span class="interest-tag">여행</span>
            <span class="interest-tag">독서</span>
            <span class="interest-tag">헬스</span>
        </div>
    </div>
</a>


<!-- 프로필 카드 14 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="profile-card" data-category="new 20s">
    <div class="profile-header" style="background-image: url('<?php echo G5_URL?>/game/img/광주 광산구 최유미.jpg');">
        <div class="online-status"><div class="online-dot"></div>온라인</div>
        <div class="new-badge">NEW</div>
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="profile-overlay">
            <h3 class="profile-name">최유미</h3>
            <p class="profile-age">22세</p>
            <div class="profile-location"><i class="bi bi-geo-alt-fill"></i> 광산구, 광주</div>
        </div>
    </div>
    <div class="profile-info">
        <p class="profile-intro">안녕하세요! 좋은 인연을 찾고 있는 최유미입니다 😊</p>
        <div class="profile-interests">
            <span class="interest-tag highlight">사진</span>
            <span class="interest-tag">여행</span>
            <span class="interest-tag">독서</span>
            <span class="interest-tag">헬스</span>
        </div>
    </div>
</a>

            </div>
        </div>
    </div>
    <!-- 본인인증 모달 -->
<div class="modal fade" id="authModal" tabindex="-1" aria-labelledby="authModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center pb-5">
                <i class="bi bi-shield-check text-primary" style="font-size: 60px;"></i>
                <h5 class="modal-title mt-3 mb-3" id="authModalLabel">본인인증이 필요합니다</h5>
                <p class="text-muted mb-4">
                    본인인증 후 서비스 이용이 가능합니다.
                </p>
                <button type="button" class="btn btn-primary px-5" onclick="proceedToGame()">
                    확인
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 모달 관련 스크립트 -->
<script>
    // 게임 링크 URL 저장 변수
    let gameUrl = '';
    
    // 모든 게임 링크에 이벤트 리스너 추가
    document.addEventListener('DOMContentLoaded', function() {
        // 모든 profile-card 링크에 클릭 이벤트 추가
        const gameLinks = document.querySelectorAll('.profile-card');
        
        gameLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault(); // 기본 링크 동작 방지
                gameUrl = this.getAttribute('href'); // 링크 URL 저장
                
                // Bootstrap 모달 표시
                const authModal = new bootstrap.Modal(document.getElementById('authModal'));
                authModal.show();
            });
        });
    });
    
    // 확인 버튼 클릭 시 게임 페이지로 이동
    function proceedToGame() {
        if (gameUrl) {
            window.location.href = gameUrl;
        }
    }
</script>
    <!-- 하단 퀵메뉴 include -->
    <?php include_once('./menu.php'); ?>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 좋아요 토글 기능
        function toggleLike(button) {
            button.classList.toggle('liked');
            
            // 하트 애니메이션
            button.style.transform = 'scale(1.3)';
            setTimeout(() => {
                button.style.transform = 'scale(1)';
            }, 200);
        }
        
        // 필터 기능
        document.querySelectorAll('.filter-tag').forEach(tag => {
            tag.addEventListener('click', function() {
                // 활성 태그 변경
                document.querySelectorAll('.filter-tag').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // 필터링
                const filter = this.dataset.filter;
                const cards = document.querySelectorAll('.profile-card');
                let visibleCount = 0;
                
                cards.forEach(card => {
                    if (filter === 'all' || card.dataset.category.includes(filter)) {
                        card.style.display = 'block';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });
                
                // 결과 수 업데이트
                document.getElementById('resultCount').textContent = `${visibleCount}명`;
            });
        });
        
        // 검색 기능
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const cards = document.querySelectorAll('.profile-card');
            let visibleCount = 0;
            
            cards.forEach(card => {
                const name = card.querySelector('.profile-name').textContent.toLowerCase();
                const intro = card.querySelector('.profile-intro').textContent.toLowerCase();
                const interests = Array.from(card.querySelectorAll('.interest-tag')).map(tag => tag.textContent.toLowerCase()).join(' ');
                
                if (name.includes(searchTerm) || intro.includes(searchTerm) || interests.includes(searchTerm)) {
                    card.style.display = 'block';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // 결과 수 업데이트
            document.getElementById('resultCount').textContent = `${visibleCount}명`;
            
            // 빈 결과 처리
            if (visibleCount === 0 && searchTerm.length > 0) {
                showEmptyState();
            } else {
                hideEmptyState();
            }
        });
        
        // 빈 상태 표시
        function showEmptyState() {
            if (!document.querySelector('.empty-state')) {
                const emptyHTML = `
                    <div class="empty-state">
                        <i class="bi bi-heart empty-icon"></i>
                        <h3 class="empty-title">검색 결과가 없습니다</h3>
                        <p class="empty-text">다른 검색어를 시도해보세요</p>
                    </div>
                `;
                document.getElementById('profileList').insertAdjacentHTML('afterend', emptyHTML);
            }
        }
        
        // 빈 상태 숨기기
        function hideEmptyState() {
            const emptyState = document.querySelector('.empty-state');
            if (emptyState) {
                emptyState.remove();
            }
        }
    </script>
</body>
</html>