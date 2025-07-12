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
    <meta name="title" content="<?php echo $g5['title']; ?>">
    <title><?php echo $g5['title']; ?></title>
    
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
            position: relative;
        }
        
        .logo {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .header-title {
            font-size: 18px;
            font-weight: 600;
            opacity: 0.9;
        }
        
        /* 메인 배너 */
        .main-banner {
            background: white;
            color: #333;
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid #e9ecef;
        }
        
        .banner-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #333;
        }
        
        .banner-subtitle {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        .banner-btn {
            background: #ff4757;
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .banner-btn:hover {
            background: #ff3742;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(255, 71, 87, 0.3);
        }
        
        /* 채팅 리스트 섹션 */
        .chat-section {
            padding: 20px;
            background: white;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #333;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* 채팅 카드 그리드 */
        .chat-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .chat-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            border: 1px solid #f1f3f4;
        }
        
        .chat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
            color: inherit;
            text-decoration: none;
        }
        
        .card-image {
            width: 100%;
            height: 180px;
            background-size: cover;
            background-position: center;
            position: relative;
        }
        
        .card-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.7));
            color: white;
            padding: 20px 15px 15px;
        }
        
        .card-name {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .card-location {
            font-size: 12px;
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        /* 좋아요 버튼 */
        .like-button {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 32px;
            height: 32px;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
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
        
        /* 온라인 상태 */
        .online-indicator {
            position: absolute;
            bottom: 45px;
            left: 12px;
            width: 10px;
            height: 10px;
            background: #2ed573;
            border: 2px solid white;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(46, 213, 115, 0.7); }
            70% { box-shadow: 0 0 0 8px rgba(46, 213, 115, 0); }
            100% { box-shadow: 0 0 0 0 rgba(46, 213, 115, 0); }
        }
        
        /* 반응형 */
        @media (max-width: 375px) {
            .chat-grid {
                gap: 12px;
            }
            
            .card-image {
                height: 160px;
            }
            
            .card-overlay {
                padding: 15px 12px 12px;
            }
            
            .card-name {
                font-size: 14px;
            }
            
            .chat-section {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- 헤더 영역 -->
        <div class="header-section">
            <div class="logo">💕 VelLuna</div>
            <div class="header-title">새로운 인연을 찾아보세요</div>
        </div>
        
        <!-- 메인 배너 -->
        <div class="main-banner">
            <h2 class="banner-title">✨ 특별한 만남이 기다려요</h2>
            <p class="banner-subtitle">지금 바로 채팅을 시작해보세요!</p>
            <a href="<?php echo G5_URL; ?>/game/main.php" class="banner-btn">
                <i class="bi bi-chat-heart me-2"></i>채팅 시작하기
            </a>
        </div>
        
        <!-- 채팅 리스트 -->
        <div class="chat-section">
            <h3 class="section-title">
                <i class="bi bi-people-fill text-primary"></i>
                새로운 매칭
            </h3>
            
            <div class="chat-grid">
<!-- 채팅 카드 1 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="chat-card">
    <div class="card-image" style="background-image: url('<?php echo G5_URL?>/game/img/경기 성남시 박주희.jpg');">
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="online-indicator"></div>
        <div class="card-overlay">
            <div class="card-name">박주희, 24</div>
            <div class="card-location">
                <i class="bi bi-geo-alt-fill"></i>
                성남시, 경기
            </div>
        </div>
    </div>
</a>

<!-- 채팅 카드 2 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="chat-card">
    <div class="card-image" style="background-image: url('<?php echo G5_URL?>/game/img/경기 세종시 정윤주.jpg');">
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="online-indicator"></div>
        <div class="card-overlay">
            <div class="card-name">정윤주, 26</div>
            <div class="card-location">
                <i class="bi bi-geo-alt-fill"></i>
                세종시, 경기
            </div>
        </div>
    </div>
</a>

<!-- 채팅 카드 3 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="chat-card">
    <div class="card-image" style="background-image: url('<?php echo G5_URL?>/game/img/경기 시흥시 이하얀.jpg');">
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="online-indicator"></div>
        <div class="card-overlay">
            <div class="card-name">이하얀, 23</div>
            <div class="card-location">
                <i class="bi bi-geo-alt-fill"></i>
                시흥시, 경기
            </div>
        </div>
    </div>
</a>

<!-- 채팅 카드 4 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="chat-card">
    <div class="card-image" style="background-image: url('<?php echo G5_URL?>/game/img/광주 북구 황하나.jpg');">
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="online-indicator"></div>
        <div class="card-overlay">
            <div class="card-name">황하나, 25</div>
            <div class="card-location">
                <i class="bi bi-geo-alt-fill"></i>
                북구, 광주
            </div>
        </div>
    </div>
</a>

<!-- 채팅 카드 5 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="chat-card">
    <div class="card-image" style="background-image: url('<?php echo G5_URL?>/game/img/대구 수성구 강연수.jpg');">
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="online-indicator"></div>
        <div class="card-overlay">
            <div class="card-name">강연수, 27</div>
            <div class="card-location">
                <i class="bi bi-geo-alt-fill"></i>
                수성구, 대구
            </div>
        </div>
    </div>
</a>

<!-- 채팅 카드 6 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="chat-card">
    <div class="card-image" style="background-image: url('<?php echo G5_URL?>/game/img/대구 중구 윤민정.jpg');">
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="online-indicator"></div>
        <div class="card-overlay">
            <div class="card-name">윤민정, 22</div>
            <div class="card-location">
                <i class="bi bi-geo-alt-fill"></i>
                중구, 대구
            </div>
        </div>
    </div>
</a>

<!-- 채팅 카드 7 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="chat-card">
    <div class="card-image" style="background-image: url('<?php echo G5_URL?>/game/img/부산 동래구 김민영.jpg');">
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="online-indicator"></div>
        <div class="card-overlay">
            <div class="card-name">김민영, 24</div>
            <div class="card-location">
                <i class="bi bi-geo-alt-fill"></i>
                동래구, 부산
            </div>
        </div>
    </div>
</a>

<!-- 채팅 카드 8 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="chat-card">
    <div class="card-image" style="background-image: url('<?php echo G5_URL?>/game/img/부산 중구 이미래.jpg');">
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="online-indicator"></div>
        <div class="card-overlay">
            <div class="card-name">이미래, 21</div>
            <div class="card-location">
                <i class="bi bi-geo-alt-fill"></i>
                중구, 부산
            </div>
        </div>
    </div>
</a>

<!-- 채팅 카드 9 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="chat-card">
    <div class="card-image" style="background-image: url('<?php echo G5_URL?>/game/img/서울 강남구 김윤정.jpg');">
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="online-indicator"></div>
        <div class="card-overlay">
            <div class="card-name">김윤정, 28</div>
            <div class="card-location">
                <i class="bi bi-geo-alt-fill"></i>
                강남구, 서울
            </div>
        </div>
    </div>
</a>

<!-- 채팅 카드 10 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="chat-card">
    <div class="card-image" style="background-image: url('<?php echo G5_URL?>/game/img/서울 관악구 민수진.jpg');">
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="online-indicator"></div>
        <div class="card-overlay">
            <div class="card-name">민수진, 26</div>
            <div class="card-location">
                <i class="bi bi-geo-alt-fill"></i>
                관악구, 서울
            </div>
        </div>
    </div>
</a>

<!-- 채팅 카드 11 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="chat-card">
    <div class="card-image" style="background-image: url('<?php echo G5_URL?>/game/img/서울 노원구 김소현.jpg');">
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="online-indicator"></div>
        <div class="card-overlay">
            <div class="card-name">김소현, 25</div>
            <div class="card-location">
                <i class="bi bi-geo-alt-fill"></i>
                노원구, 서울
            </div>
        </div>
    </div>
</a>

<!-- 채팅 카드 12 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="chat-card">
    <div class="card-image" style="background-image: url('<?php echo G5_URL?>/game/img/서울 마포구 강빛나.png');">
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="online-indicator"></div>
        <div class="card-overlay">
            <div class="card-name">강빛나, 23</div>
            <div class="card-location">
                <i class="bi bi-geo-alt-fill"></i>
                마포구, 서울
            </div>
        </div>
    </div>
</a>

<!-- 채팅 카드 13 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="chat-card">
    <div class="card-image" style="background-image: url('<?php echo G5_URL?>/game/img/울산 중구 서예빈.jpg');">
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="online-indicator"></div>
        <div class="card-overlay">
            <div class="card-name">서예빈, 24</div>
            <div class="card-location">
                <i class="bi bi-geo-alt-fill"></i>
                중구, 울산
            </div>
        </div>
    </div>
</a>

<!-- 채팅 카드 14 -->
<a href="<?php echo G5_URL; ?>/game/main.php" class="chat-card">
    <div class="card-image" style="background-image: url('<?php echo G5_URL?>/game/img/광주 광산구 최유미.jpg');">
        <button class="like-button" onclick="event.preventDefault(); event.stopPropagation(); toggleLike(this)">
            <i class="bi bi-heart-fill"></i>
        </button>
        <div class="online-indicator"></div>
        <div class="card-overlay">
            <div class="card-name">최유미, 22</div>
            <div class="card-location">
                <i class="bi bi-geo-alt-fill"></i>
                광산구, 광주
            </div>
        </div>
    </div>
</a>

</div>

<!-- 메인페이지 하단에 추가할 모달 및 스크립트 -->

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

<!-- 기존 script 태그 안에 추가 -->
<script>
    // 게임 링크 URL 저장 변수
    let gameUrl = '';
    
    // 모든 게임 링크에 이벤트 리스너 추가
    document.addEventListener('DOMContentLoaded', function() {
        // 모든 chat-card 링크와 banner-btn에 클릭 이벤트 추가
        const gameLinks = document.querySelectorAll('.chat-card, .banner-btn');
        
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
    
    // 기존 좋아요 토글 기능
    function toggleLike(button) {
        button.classList.toggle('liked');
        
        // 하트 애니메이션
        button.style.transform = 'scale(1.3)';
        setTimeout(() => {
            button.style.transform = 'scale(1)';
        }, 200);
    }
</script>

            </div>
        </div>
    </div>
    
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
    </script>
</body>
</html>

<?php
include_once(G5_PATH.'/tail.sub.php');
?>