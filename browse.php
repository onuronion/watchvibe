<?php
require_once 'config.php';

$type = $_GET['type'] ?? '';
$valid_types = ['movie', 'series', 'anime'];
$is_upcoming = ($type === 'upcoming');
$type_filter = in_array($type, $valid_types) ? $type : '';
$q = trim($_GET['q'] ?? '');
$selected_date = $_GET['date'] ?? '';
$genre = trim($_GET['genre'] ?? '');

$page_title = 'Tüm İçerikler - Watchvibe';
$heading = 'Tüm İçerikler';

if ($is_upcoming) {
    $heading = 'Yakında Eklenecekler';
    $page_title = $heading . ' - Watchvibe';
} elseif ($type_filter) {
    $type_labels = ['movie' => 'Filmler', 'series' => 'Diziler', 'anime' => 'Anime'];
    $heading = $type_labels[$type_filter] ?? 'İçerikler';
    $page_title = $heading . ' - Watchvibe';
} elseif ($genre) {
    $heading = $genre . ' İçerikleri';
    $page_title = $heading . ' - Watchvibe';
}

$items = [];

// Otomatik tür listesi
$all_genres = [];
if (!$is_upcoming && !$q) {
    $r = db()->query("SELECT DISTINCT genres FROM content WHERE genres IS NOT NULL AND genres != ''");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $parts = array_map('trim', explode(',', $row['genres']));
            foreach ($parts as $p) {
                if ($p !== '' && !in_array($p, $all_genres)) {
                    $all_genres[] = $p;
                }
            }
        }
    }
    sort($all_genres);
}

if ($is_upcoming) {
    $r = db()->query("SELECT * FROM upcoming_releases WHERE release_date >= CURDATE() ORDER BY release_date ASC");
    $upcoming_items = [];
    if ($r) while ($row = $r->fetch_assoc()) $upcoming_items[] = $row;

    $upcoming_by_date = [];
    foreach ($upcoming_items as $u) {
        $date = $u['release_date'];
        if (!isset($upcoming_by_date[$date])) $upcoming_by_date[$date] = [];
        $upcoming_by_date[$date][] = $u;
    }

    if ($selected_date && isset($upcoming_by_date[$selected_date])) {
        $items = $upcoming_by_date[$selected_date];
    } else {
        if (!empty($upcoming_by_date)) {
            $first_date = array_key_first($upcoming_by_date);
            $items = $upcoming_by_date[$first_date];
            $selected_date = $first_date;
        } else {
            $items = [];
            $selected_date = '';
        }
    }
} elseif ($q) {
    $like = '%' . $q . '%';
    $stmt = db()->prepare("SELECT * FROM content WHERE (title LIKE ? OR genres LIKE ?) ORDER BY rating DESC");
    $stmt->bind_param("ss", $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $items[] = $row;
    $stmt->close();
    $heading = '"' . $q . '" için sonuçlar';
    $page_title = $q . ' - Watchvibe';
} elseif ($genre) {
    $stmt = db()->prepare("SELECT * FROM content WHERE FIND_IN_SET(?, genres) ORDER BY rating DESC");
    $stmt->bind_param("s", $genre);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $items[] = $row;
    $stmt->close();
} elseif ($type_filter) {
    $stmt = db()->prepare("SELECT * FROM content WHERE type = ? ORDER BY rating DESC");
    $stmt->bind_param("s", $type_filter);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $items[] = $row;
    $stmt->close();
} else {
    $r = db()->query("SELECT * FROM content ORDER BY rating DESC");
    if ($r) while ($row = $r->fetch_assoc()) $items[] = $row;
}

$type_labels = ['movie' => 'Film', 'series' => 'Dizi', 'anime' => 'Anime'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?php echo e($heading); ?> - Watchvibe'de izle. En güncel filmler, diziler ve animeler HD kalitede.">
<meta property="og:title" content="<?php echo e($page_title); ?>">
<meta property="og:description" content="<?php echo e($heading); ?> - Watchvibe'de izle.">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Watchvibe">
<meta property="og:image" content="icon.png">
<meta name="robots" content="index, follow">
<link rel="icon" type="image/png" href="icon.png">
<link rel="apple-touch-icon" href="icon.png">
<title><?php echo e($page_title); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--bg:#05070d;--card:#0d1322;--blue:#1677ff;--text:#f5f8ff;--muted:#8c97aa;--border:rgba(255,255,255,.07)}
body{background:var(--bg);color:var(--text);font-family:'Inter',system-ui,sans-serif;overflow-x:hidden;padding-bottom:85px}
a{text-decoration:none;color:inherit}
button{border:none;cursor:pointer;font:inherit;color:inherit}

/* Loading Screen */
.loading-screen{position:fixed;top:0;left:0;width:100%;height:100%;background:var(--bg);display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:9999;transition:opacity .5s ease,visibility .5s ease}
.loading-screen.hidden{opacity:0;visibility:hidden}
.loading-logo{width:100px;height:100px;margin-bottom:30px;animation:pulse 2s ease-in-out infinite}
.loading-logo img{width:100%;height:100%;object-fit:contain}
.loading-spinner{width:50px;height:50px;border:3px solid rgba(22,119,255,.2);border-top-color:var(--blue);border-radius:50%;animation:spin 1s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes pulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.05);opacity:.8}}

.topbar{position:fixed;top:0;left:0;width:100%;height:64px;display:flex;align-items:center;justify-content:space-between;padding:0 5%;z-index:100;background:rgba(5,7,13,.96);backdrop-filter:blur(18px);border-bottom:1px solid var(--border)}
.logo-img{height:35px;width:auto}
.nav-actions{display:flex;align-items:center;gap:10px}
.icon-btn{width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,.06);border:1px solid var(--border);display:grid;place-items:center;font-size:15px}
.icon-btn:hover{background:rgba(22,119,255,.2)}

.search-box{display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.06);border:1px solid var(--border);border-radius:12px;padding:0 12px;height:40px;min-width:200px}
.search-box input{flex:1;border:none;outline:none;background:transparent;color:white;font-size:14px;min-width:80px}
.search-box button{background:none;color:#8c97aa;font-size:14px;padding:5px}

.filters{display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap}
.filter-btn{padding:9px 18px;border-radius:10px;font-size:13px;font-weight:600;background:rgba(255,255,255,.06);border:1px solid var(--border);transition:.2s;white-space:nowrap}
.filter-btn.active{background:var(--blue);border-color:var(--blue)}
.filter-btn:hover{border-color:rgba(22,119,255,.4)}

/* Tür filtresi kapsayıcısı ve oklar */
.genre-filter-wrapper{position:relative;margin-bottom:24px}
.genre-scroll{display:flex;gap:8px;overflow-x:auto;scrollbar-width:none;padding-bottom:5px;scroll-behavior:smooth}
.genre-scroll::-webkit-scrollbar{display:none}
.genre-btn{padding:7px 14px;border-radius:20px;font-size:12px;font-weight:500;background:rgba(255,255,255,.04);border:1px solid var(--border);transition:.2s;white-space:nowrap;color:var(--muted);flex:0 0 auto}
.genre-btn.active{background:rgba(22,119,255,.2);border-color:rgba(22,119,255,.5);color:#8fc3ff}
.genre-btn:hover{border-color:rgba(22,119,255,.4);color:white}

.genre-arrow{position:absolute;top:50%;transform:translateY(-50%);width:32px;height:32px;border-radius:50%;background:rgba(13,19,34,.9);border:1px solid var(--border);display:grid;place-items:center;z-index:5;color:var(--text);font-size:13px;transition:.2s;backdrop-filter:blur(10px)}
.genre-arrow:hover{background:rgba(22,119,255,.3);border-color:rgba(22,119,255,.5)}
.genre-arrow.left{left:-8px}
.genre-arrow.right{right:-8px}
.genre-arrow.hidden{display:none}

.container{max-width:1200px;margin:0 auto;padding:90px 5% 40px}
.page-title{font-size:26px;font-weight:800;margin-bottom:20px;letter-spacing:-1px}
.page-title i{margin-right:8px;color:#4b9cff}

/* Takvim stilleri */
.calendar-wrapper{margin-bottom:30px}
.calendar-nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.calendar-title{font-size:16px;font-weight:600;color:#c2cad7}
.calendar-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:6px}
.calendar-day{aspect-ratio:1;border-radius:10px;background:rgba(255,255,255,.03);border:1px solid var(--border);display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;transition:.2s;padding:5px}
.calendar-day:hover{background:rgba(22,119,255,.1);border-color:rgba(22,119,255,.4)}
.calendar-day.has-content{background:rgba(22,119,255,.15);border-color:rgba(22,119,255,.4)}
.calendar-day.selected{background:var(--blue);border-color:var(--blue);color:white}
.calendar-day .day-num{font-size:14px;font-weight:700}
.calendar-day .day-month{font-size:10px;color:var(--muted);text-transform:uppercase}
.calendar-day.selected .day-month{color:rgba(255,255,255,.8)}
.calendar-day .day-dot{width:6px;height:6px;border-radius:50%;background:var(--blue);margin-top:4px}
.calendar-day.selected .day-dot{background:white}
.calendar-day.empty{visibility:hidden}

.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px}
.card{position:relative;border-radius:15px;overflow:hidden;background:var(--card);border:1px solid var(--border);transition:.25s;display:block}
.card:hover{transform:translateY(-4px);border-color:rgba(22,119,255,.45)}
.card img{width:100%;aspect-ratio:2/3;object-fit:cover;display:block;transition:.35s}
.card:hover img{transform:scale(1.05)}
.card-gradient{position:absolute;inset:0;background:linear-gradient(to top,rgba(3,5,10,.95),transparent 52%)}
.card-info{position:absolute;bottom:0;left:0;right:0;padding:12px 10px}
.card-title{font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:3px}
.card-meta{font-size:11px;color:#aeb8c7;display:flex;gap:6px;align-items:center}
.card-meta .rating{color:#ffd24d}
.empty{text-align:center;padding:60px;color:var(--muted)}
.empty i{font-size:48px;margin-bottom:15px;opacity:.3}

/* Footer */
.footer{background:#090d18;border-top:1px solid var(--border);padding:30px 5% 100px;margin-top:40px}
.footer-content{max-width:1200px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:25px}
.footer-section h4{font-size:13px;font-weight:700;margin-bottom:12px;color:#c2cad7}
.footer-section p{font-size:12px;color:var(--muted);line-height:1.6;margin-bottom:8px}
.footer-links{list-style:none}
.footer-links li{margin-bottom:8px}
.footer-links a{font-size:12px;color:var(--muted);transition:.2s}
.footer-links a:hover{color:#4b9cff}
.footer-bottom{max-width:1200px;margin:25px auto 0;padding-top:15px;border-top:1px solid var(--border);text-align:center;font-size:11px;color:var(--muted)}

/* Bottom nav */
.bottom-nav{position:fixed;bottom:0;left:0;right:0;height:70px;z-index:200;display:flex;align-items:center;justify-content:space-around;background:rgba(7,10,17,.92);backdrop-filter:blur(20px);border-top:1px solid var(--border);padding-bottom:max(0px,env(safe-area-inset-bottom))}
.nav-item{background:transparent;display:flex;flex-direction:column;align-items:center;gap:4px;font-size:10px;color:#788397;min-width:50px}
.nav-item i{font-size:18px}
.nav-item.active{color:#4b9cff}

@media(min-width:800px){
body{padding-bottom:0}
.bottom-nav{display:none}
.footer{padding-bottom:30px}
.genre-arrow{display:grid} /* masaüstünde okları göster */
}
@media(max-width:799px){
.genre-arrow{display:none} /* mobilde okları gizle, dokunmatik kaydırma kullan */
}
@media(max-width:520px){
.grid{grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:10px}
.search-box{min-width:140px}
.search-box input{font-size:13px}
.container{padding:80px 16px 30px}
.page-title{font-size:22px}
.filter-btn{padding:8px 14px;font-size:12px}
.card-title{font-size:12px}
.card-info{padding:10px 8px}
.loading-logo{width:80px;height:80px}
.calendar-grid{grid-template-columns:repeat(5,1fr)}
}
</style>
</head>
<body>
<!-- Loading Screen -->
<div class="loading-screen" id="loadingScreen">
    <div class="loading-logo">
        <img src="icon.png" alt="Watchvibe" onerror="this.style.display='none'">
    </div>
    <div class="loading-spinner"></div>
</div>

<header class="topbar">
<a href="index.php"><img src="logo.png" alt="Watchvibe" class="logo-img"></a>
<div class="nav-actions">
<form method="GET" action="browse.php" class="search-box">
<i class="fas fa-search" style="color:#8c97aa;font-size:14px"></i>
<input type="text" name="q" placeholder="Ara..." value="<?php echo e($q); ?>">
<?php if ($q): ?>
<button type="button" onclick="window.location.href='browse.php'" style="cursor:pointer"><i class="fas fa-times"></i></button>
<?php endif; ?>
</form>
<a href="index.php" class="icon-btn"><i class="fas fa-house"></i></a>
</div>
</header>

<div class="container">
<h1 class="page-title">
<?php if ($is_upcoming): ?><i class="fas fa-calendar-alt"></i>
<?php elseif ($q): ?><i class="fas fa-search"></i>
<?php elseif ($type_filter === 'movie'): ?><i class="fas fa-film"></i>
<?php elseif ($type_filter === 'series'): ?><i class="fas fa-tv"></i>
<?php elseif ($type_filter === 'anime'): ?><i class="fas fa-dragon"></i>
<?php elseif ($genre): ?><i class="fas fa-tags"></i>
<?php else: ?><i class="fas fa-list"></i><?php endif; ?>
<?php echo e($heading); ?>
</h1>

<?php if (!$q): ?>
<div class="filters">
<a class="filter-btn <?php echo !$type_filter && !$is_upcoming && !$genre ? 'active' : ''; ?>" href="browse.php">Tümü</a>
<a class="filter-btn <?php echo $type === 'movie' ? 'active' : ''; ?>" href="browse.php?type=movie">Filmler</a>
<a class="filter-btn <?php echo $type === 'series' ? 'active' : ''; ?>" href="browse.php?type=series">Diziler</a>
<a class="filter-btn <?php echo $type === 'anime' ? 'active' : ''; ?>" href="browse.php?type=anime">Anime</a>
<a class="filter-btn <?php echo $is_upcoming ? 'active' : ''; ?>" href="browse.php?type=upcoming"><i class="fas fa-calendar-alt"></i> Yakında</a>
</div>

<?php if (!$is_upcoming && !empty($all_genres)): ?>
<div class="genre-filter-wrapper">
    <button class="genre-arrow left" onclick="scrollGenres(-1)" aria-label="Sola kaydır"><i class="fas fa-chevron-left"></i></button>
    <div class="genre-scroll" id="genreScroll">
        <a class="genre-btn <?php echo !$genre ? 'active' : ''; ?>" href="browse.php<?php echo $type_filter ? '?type=' . $type_filter : ''; ?>">Tüm Türler</a>
        <?php foreach ($all_genres as $g): ?>
        <a class="genre-btn <?php echo $genre === $g ? 'active' : ''; ?>" href="browse.php?genre=<?php echo urlencode($g); ?><?php echo $type_filter ? '&type=' . $type_filter : ''; ?>"><?php echo e($g); ?></a>
        <?php endforeach; ?>
    </div>
    <button class="genre-arrow right" onclick="scrollGenres(1)" aria-label="Sağa kaydır"><i class="fas fa-chevron-right"></i></button>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if ($is_upcoming): ?>
    <?php if (!empty($upcoming_by_date)): ?>
        <div class="calendar-wrapper">
            <div class="calendar-nav">
                <span class="calendar-title"><i class="fas fa-calendar-alt"></i> <?php echo date('F Y'); ?></span>
                <a href="browse.php?type=upcoming" class="filter-btn" style="padding:6px 12px;font-size:12px;">Bugün</a>
            </div>
            <div class="calendar-grid">
                <?php
                $today = new DateTime();
                $start = clone $today;
                $start->modify('monday this week');
                $end = clone $start;
                $end->modify('+34 days');

                $current = clone $start;
                while ($current <= $end) {
                    $dateStr = $current->format('Y-m-d');
                    $hasContent = isset($upcoming_by_date[$dateStr]);
                    $isSelected = ($dateStr === $selected_date);
                    $isPast = $current < $today;
                    $dayNum = $current->format('j');
                    $monthShort = $current->format('M');
                    $class = 'calendar-day';
                    if ($hasContent) $class .= ' has-content';
                    if ($isSelected) $class .= ' selected';
                    if ($isPast) $class .= ' empty';
                    echo '<a href="browse.php?type=upcoming&date=' . $dateStr . '" class="' . $class . '">';
                    echo '<span class="day-num">' . $dayNum . '</span>';
                    echo '<span class="day-month">' . $monthShort . '</span>';
                    if ($hasContent) {
                        echo '<span class="day-dot"></span>';
                    }
                    echo '</a>';
                    $current->modify('+1 day');
                }
                ?>
            </div>
        </div>
        <h2 style="font-size:18px;margin-bottom:15px;"><?php echo date('d M Y', strtotime($selected_date)); ?> eklenecekler</h2>
    <?php endif; ?>
<?php endif; ?>

<?php if (empty($items)): ?>
<div class="empty"><i class="fas fa-search"></i><p>Sonuç bulunamadı.</p></div>
<?php else: ?>
<div class="grid">
<?php foreach ($items as $c):
    if ($is_upcoming) {
        $url = '#';
        $poster = '';
        $title = e($c['title']);
        $meta = e($c['content_type']) . ' • ' . date('d M', strtotime($c['release_date']));
    } else {
        $url = content_url($c['id'], $c['title']);
        $poster = $c['poster_url'];
        $title = e($c['title']);
        $meta = e($c['year']) . ' <span class="rating"><i class="fas fa-star"></i> ' . e($c['rating']) . '</span>';
    }
?>
<a class="card" href="<?php echo $url; ?>" <?php echo $is_upcoming ? 'style="pointer-events:none;opacity:0.8;"' : ''; ?>>
    <?php if (!empty($poster)): ?>
        <img src="<?php echo e($poster); ?>" alt="<?php echo $title; ?>" loading="lazy">
    <?php else: ?>
        <div style="width:100%;aspect-ratio:2/3;background:linear-gradient(145deg,#1a2332,#0d1322);display:flex;align-items:center;justify-content:center;font-size:40px;color:#4b5563;"><i class="fas fa-film"></i></div>
    <?php endif; ?>
    <div class="card-gradient"></div>
    <div class="card-info">
        <div class="card-title"><?php echo $title; ?></div>
        <div class="card-meta"><?php echo $meta; ?></div>
    </div>
</a>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>

<!-- Footer -->
<footer class="footer">
<div class="footer-content">
<div class="footer-section">
<h4>Watchvibe</h4>
<p>Film, dizi ve anime izleme platformu. En yeni içerikler, popüler yapımlar ve daha fazlası.</p>
</div>
<div class="footer-section">
<h4>Hızlı Bağlantılar</h4>
<ul class="footer-links">
<li><a href="index.php">Ana Sayfa</a></li>
<li><a href="browse.php">Keşfet</a></li>
<li><a href="browse.php?type=movie">Filmler</a></li>
<li><a href="browse.php?type=series">Diziler</a></li>
<li><a href="browse.php?type=anime">Anime</a></li>
</ul>
</div>
<div class="footer-section">
<h4>Yasal</h4>
<ul class="footer-links">
<li><a href="terms.php">Kullanım Şartları</a></li>
<li><a href="privacy.php">Gizlilik Politikası</a></li>
<li><a href="cookies.php">Çerez Politikası</a></li>
<li><a href="dmca.php">DMCA</a></li>
<li><a href="contact.php">İletişim</a></li>
</ul>
</div>
<div class="footer-section">
<h4>Hesap</h4>
<ul class="footer-links">
<li><a href="login.php">Giriş Yap</a></li>
<li><a href="login.php?tab=register">Kayıt Ol</a></li>
</ul>
</div>
</div>
<div class="footer-bottom">
<p>&copy; <?php echo date('Y'); ?> Watchvibe. Tüm hakları saklıdır.</p>
</div>
</footer>

<!-- Bottom nav -->
<nav class="bottom-nav">
<a class="nav-item" href="index.php"><i class="fas fa-house"></i><span>Ana Sayfa</span></a>
<a class="nav-item active" href="browse.php"><i class="fas fa-compass"></i><span>Keşfet</span></a>
<a class="nav-item" href="login.php"><i class="fas fa-bookmark"></i><span>Listem</span></a>
<a class="nav-item" href="login.php"><i class="fas fa-gear"></i><span>Ayarlar</span></a>
</nav>

<script>
// Loading screen
window.addEventListener('load', function() {
    setTimeout(function() {
        document.getElementById('loadingScreen').classList.add('hidden');
    }, 500);
});

// Fallback: hide loading screen after 3 seconds
setTimeout(function() {
    document.getElementById('loadingScreen').classList.add('hidden');
}, 3000);

// Tür kaydırma fonksiyonu
function scrollGenres(direction) {
    const container = document.getElementById('genreScroll');
    const scrollAmount = container.clientWidth * 0.7;
    container.scrollBy({
        left: direction * scrollAmount,
        behavior: 'smooth'
    });
}

// Ok görünürlüğünü güncelle
function updateGenreArrows() {
    const container = document.getElementById('genreScroll');
    if (!container) return;
    const leftArrow = document.querySelector('.genre-arrow.left');
    const rightArrow = document.querySelector('.genre-arrow.right');
    if (!leftArrow || !rightArrow) return;
    
    // Sol ok: başlangıçta gizli
    if (container.scrollLeft <= 10) {
        leftArrow.classList.add('hidden');
    } else {
        leftArrow.classList.remove('hidden');
    }
    
    // Sağ ok: sona gelince gizli
    if (container.scrollLeft + container.clientWidth >= container.scrollWidth - 10) {
        rightArrow.classList.add('hidden');
    } else {
        rightArrow.classList.remove('hidden');
    }
}

// Kaydırma olayını dinle
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('genreScroll');
    if (container) {
        container.addEventListener('scroll', updateGenreArrows);
        updateGenreArrows();
    }
});
</script>
</body>
</html>