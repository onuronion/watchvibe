<?php
require_once 'config.php';

// ── Otomatik temizlik: 2 günden eski "yeni" işaretlerini kaldır ──
db()->query("UPDATE content SET is_new = 0 WHERE is_new = 1 AND created_at < NOW() - INTERVAL 2 DAY");

// Fetch content from DB
$featured = [];
$popular = [];
$new_releases = [];
$movies = [];
$series = [];
$anime = [];

$r = db()->query("SELECT * FROM content WHERE is_featured = 1 ORDER BY id ASC");
if ($r) while ($row = $r->fetch_assoc()) $featured[] = $row;

// Popülerler: bugünün tıklamalarına göre (fallback: rating)
$r = db()->query("SELECT c.*, COALESCE(cc.click_count, 0) AS today_clicks
                  FROM content c
                  LEFT JOIN content_clicks cc ON c.id = cc.content_id AND cc.click_date = CURDATE()
                  ORDER BY today_clicks DESC, c.rating DESC
                  LIMIT 6");
if ($r) while ($row = $r->fetch_assoc()) $popular[] = $row;

$r = db()->query("SELECT * FROM content WHERE is_new = 1 ORDER BY id DESC LIMIT 12");
if ($r) while ($row = $r->fetch_assoc()) $new_releases[] = $row;

$r = db()->query("SELECT * FROM content WHERE type = 'movie' ORDER BY id ASC LIMIT 12");
if ($r) while ($row = $r->fetch_assoc()) $movies[] = $row;

$r = db()->query("SELECT * FROM content WHERE type = 'series' ORDER BY id ASC LIMIT 12");
if ($r) while ($row = $r->fetch_assoc()) $series[] = $row;

$r = db()->query("SELECT * FROM content WHERE type = 'anime' ORDER BY id ASC LIMIT 12");
if ($r) while ($row = $r->fetch_assoc()) $anime[] = $row;

$user = current_user();
$continue = $user ? get_continue_watching($user['id']) : [];

// Benzersiz içerikleri filtrele - aynı içerikten sadece son izleneni göster
if (!empty($continue)) {
    $unique_continue = [];
    $seen_content_ids = [];
    foreach ($continue as $c) {
        $cid = (int)$c['content_id'];
        if (!in_array($cid, $seen_content_ids)) {
            $seen_content_ids[] = $cid;
            $unique_continue[] = $c;
        }
    }
    $continue = $unique_continue;
}

$type_labels = ['movie' => 'Film', 'series' => 'Dizi', 'anime' => 'Anime'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#05070d">
<meta name="description" content="Watchvibe - Film, dizi ve anime izleme platformu. En yeni filmler, popüler diziler ve anime dünyası tek yerde.">
<meta name="keywords" content="film izle, dizi izle, anime izle, watchvibe, online film, hd izle">
<meta property="og:title" content="Watchvibe - Film, Dizi ve Anime">
<meta property="og:description" content="En yeni filmler, popüler diziler ve anime dünyası tek yerde.">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Watchvibe">
<meta property="og:image" content="icon.png">
<link rel="icon" type="image/png" href="icon.png">
<link rel="apple-touch-icon" href="icon.png">
<title>Watchvibe - Film, Dizi ve Anime</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
:root{
--bg:#05070d;
--bg2:#090d18;
--card:#0d1322;
--card2:#111a2c;
--blue:#1677ff;
--blue2:#0d4fa8;
--text:#f5f8ff;
--muted:#8c97aa;
--border:rgba(255,255,255,.07);
--shadow:0 15px 45px rgba(0,0,0,.45);
}
html{scroll-behavior:smooth}
body{
background:var(--bg);
color:var(--text);
font-family:'Inter',system-ui,-apple-system,sans-serif;
overflow-x:hidden;
padding-bottom:85px;
font-weight:400;
}
body::-webkit-scrollbar{width:7px}
body::-webkit-scrollbar-thumb{background:#17243b;border-radius:20px}
body::-webkit-scrollbar-track{background:transparent}
button,input{font:inherit}
button{border:none;color:inherit;cursor:pointer}
a{text-decoration:none;color:inherit}
#app{min-height:100vh;background:radial-gradient(circle at top,#101c33 0%,#05070d 42%,#05070d 100%)}

/* Loading Screen */
.loading-screen{
position:fixed;top:0;left:0;width:100%;height:100%;
background:var(--bg);
display:flex;flex-direction:column;align-items:center;justify-content:center;
z-index:9999;transition:opacity .5s ease,visibility .5s ease;
}
.loading-screen.hidden{opacity:0;visibility:hidden}
.loading-logo{
width:100px;height:100px;margin-bottom:30px;
animation:pulse 2s ease-in-out infinite;
}
.loading-logo img{width:100%;height:100%;object-fit:contain}
.loading-spinner{
width:50px;height:50px;
border:3px solid rgba(22,119,255,.2);
border-top-color:var(--blue);
border-radius:50%;
animation:spin 1s linear infinite;
}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes pulse{
0%,100%{transform:scale(1);opacity:1}
50%{transform:scale(1.05);opacity:.8}
}

/* Topbar */
.topbar{
position:fixed;
top:0;left:0;width:100%;
height:70px;
display:flex;
align-items:center;
justify-content:space-between;
padding:0 5%;
z-index:100;
transition:.3s;
background:linear-gradient(to bottom,rgba(5,7,13,.95),rgba(5,7,13,.65),transparent);
}
.topbar.scrolled{
background:rgba(5,7,13,.96);
backdrop-filter:blur(18px);
border-bottom:1px solid var(--border);
}
.logo-img{height:40px;width:auto;display:block}
.nav-actions{display:flex;align-items:center;gap:10px}
.icon-btn{
width:42px;height:42px;
border-radius:13px;
background:rgba(255,255,255,.06);
border:1px solid var(--border);
display:grid;place-items:center;
transition:.2s;
font-size:16px;
color:var(--text);
}
.icon-btn:hover{background:rgba(22,119,255,.2);border-color:rgba(22,119,255,.5)}
.user-badge{
display:flex;align-items:center;gap:8px;
padding:7px 12px;border-radius:13px;
background:rgba(255,255,255,.06);
border:1px solid var(--border);
font-size:13px;font-weight:600;
}
.user-badge i{color:#4b9cff}
.login-btn{
padding:8px 18px;border-radius:13px;
background:linear-gradient(135deg,#1677ff,#0754c4);
font-weight:600;font-size:13px;
box-shadow:0 6px 18px rgba(22,119,255,.3);
transition:.2s;
}
.login-btn:hover{transform:translateY(-1px);box-shadow:0 8px 22px rgba(22,119,255,.4)}

/* Hero */
.hero{
height:min(720px,88vh);
min-height:580px;
position:relative;
overflow:hidden;
display:flex;
align-items:flex-end;
padding:0 5% 80px;
touch-action:pan-y;
}
.hero-bg{
position:absolute;inset:0;
background-size:cover;background-position:center;
opacity:.72;
transform:scale(1.04);
transition:opacity .8s,transform 5s ease;
}
.hero-bg.active{transform:scale(1);opacity:.78}
.hero-overlay{
position:absolute;inset:0;
background:
linear-gradient(90deg,rgba(5,7,13,.98) 0%,rgba(5,7,13,.72) 38%,rgba(5,7,13,.12) 72%),
linear-gradient(0deg,#05070d 0%,rgba(5,7,13,.65) 12%,transparent 55%),
linear-gradient(180deg,rgba(5,7,13,.65),transparent 25%);
}
.hero-content{position:relative;z-index:2;max-width:620px;animation:heroIn .6s ease}
@keyframes heroIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.hero-title{
font-size:clamp(38px,7vw,74px);
line-height:1.0;
letter-spacing:-2px;
font-weight:800;
margin-bottom:17px;
text-shadow:0 5px 30px rgba(0,0,0,.55);
}
.hero-meta{
display:flex;align-items:center;flex-wrap:wrap;gap:12px;
color:#c2cad7;font-size:14px;margin-bottom:15px;
}
.rating{color:#ffd24d;font-weight:600}
.hero-desc{
color:#b4bdca;
line-height:1.6;
font-size:15px;
max-width:570px;
margin-bottom:24px;
display:-webkit-box;
-webkit-line-clamp:3;
-webkit-box-orient:vertical;
overflow:hidden;
text-overflow:ellipsis;
}
.hero-buttons{display:flex;gap:11px;flex-wrap:wrap}
.primary-btn{
height:48px;padding:0 21px;border-radius:14px;
font-weight:600;display:flex;align-items:center;gap:9px;transition:.2s;
background:linear-gradient(135deg,#1677ff,#0754c4);
box-shadow:0 10px 28px rgba(22,119,255,.28);
}
.primary-btn:hover{transform:translateY(-2px);box-shadow:0 14px 32px rgba(22,119,255,.42)}
.hero-dots{position:absolute;z-index:4;bottom:30px;right:5%;display:flex;gap:7px}
.hero-dot{width:8px;height:8px;border-radius:10px;background:rgba(255,255,255,.35);transition:.3s;cursor:pointer}
.hero-dot.active{width:26px;background:var(--blue)}

/* Hero okları - sadece masaüstünde göster */
.hero-arrow{
position:absolute;top:50%;transform:translateY(-50%);
width:46px;height:46px;border-radius:50%;
background:rgba(13,19,34,.8);border:1px solid var(--border);
display:grid;place-items:center;z-index:5;transition:.2s;
font-size:18px;color:var(--text);backdrop-filter:blur(10px);
}
.hero-arrow:hover{background:rgba(22,119,255,.3);border-color:rgba(22,119,255,.5)}
.hero-arrow.prev{left:20px}
.hero-arrow.next{right:20px}

/* Sections */
.section{position:relative;margin-top:-5px;padding:0 5%;margin-bottom:38px}
.section-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:15px}
.section-title{font-size:21px;font-weight:700;letter-spacing:-.5px}
.section-title i{margin-right:6px}
.show-all{color:#5b9fff;font-size:13px;font-weight:600;background:transparent}

/* Slider with arrows */
.slider-container{position:relative}
.slider-arrow{
position:absolute;top:50%;transform:translateY(-50%);
width:40px;height:40px;border-radius:50%;
background:rgba(13,19,34,.9);border:1px solid var(--border);
display:grid;place-items:center;z-index:10;transition:.2s;
font-size:14px;color:var(--text);backdrop-filter:blur(10px);
}
.slider-arrow:hover{background:rgba(22,119,255,.3);border-color:rgba(22,119,255,.5)}
.slider-arrow.prev{left:-5px}
.slider-arrow.next{right:-5px}
.slider-arrow.hidden{display:none}
.slider{
display:flex;gap:13px;
overflow-x:auto;overflow-y:hidden;
padding:4px 0 14px;
scroll-snap-type:x proximity;
scrollbar-width:none;
scroll-behavior:smooth;
}
.slider::-webkit-scrollbar{display:none}

/* Cards */
.card{
position:relative;
flex:0 0 155px;height:232px;
border-radius:15px;overflow:hidden;
background:var(--card);
border:1px solid var(--border);
scroll-snap-align:start;transition:.25s;
box-shadow:0 8px 25px rgba(0,0,0,.2);
display:block;
}
.card:hover{transform:translateY(-5px) scale(1.015);border-color:rgba(22,119,255,.45)}
.card img{width:100%;height:100%;object-fit:cover;display:block;transition:.35s}
.card:hover img{transform:scale(1.06)}
.card-gradient{position:absolute;inset:0;background:linear-gradient(to top,rgba(3,5,10,.95),transparent 52%)}
.card-info{position:absolute;bottom:0;left:0;right:0;padding:13px 11px}
.card-title{font-size:14px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:5px}
.card-meta{font-size:11px;color:#aeb8c7;display:flex;gap:7px;align-items:center}
.card-play{
position:absolute;inset:0;
display:grid;place-items:center;
opacity:0;background:rgba(0,0,0,.18);transition:.25s;
}
.card:hover .card-play{opacity:1}
.play-circle{
width:46px;height:46px;border-radius:50%;
display:grid;place-items:center;
background:rgba(22,119,255,.92);
box-shadow:0 8px 25px rgba(0,0,0,.45);
font-size:14px;color:white;
}

/* Rank cards */
.rank-slider{gap:15px}
.rank-card{flex:0 0 220px;height:125px;position:relative;display:flex;align-items:center;cursor:pointer}
.rank-number{
font-size:105px;font-weight:800;letter-spacing:-12px;
color:transparent;-webkit-text-stroke:2px #263652;
line-height:1;position:absolute;left:0;z-index:0;
}
.rank-poster{
position:absolute;right:0;width:150px;height:125px;
border-radius:13px;overflow:hidden;
border:1px solid var(--border);
box-shadow:var(--shadow);z-index:1;
}
.rank-poster img{width:100%;height:100%;object-fit:cover}

/* Continue watching */
.continue-card{
flex:0 0 290px;height:165px;
border-radius:16px;overflow:hidden;position:relative;
background:#0d1421;border:1px solid var(--border);
display:block;
}
.continue-card img{width:100%;height:100%;object-fit:cover;opacity:.82}
.continue-overlay{position:absolute;inset:0;background:linear-gradient(to top,rgba(5,7,13,.96),rgba(5,7,13,.05) 70%)}
.continue-info{position:absolute;left:14px;right:14px;bottom:13px}
.continue-title{font-size:15px;font-weight:600;margin-bottom:9px}
.progress{height:4px;background:rgba(255,255,255,.18);border-radius:10px;overflow:hidden}
.progress span{display:block;height:100%;border-radius:inherit;background:var(--blue)}

/* Bottom nav */
.bottom-nav{
position:fixed;bottom:0;left:0;right:0;height:76px;
z-index:200;display:flex;align-items:center;justify-content:space-around;
background:rgba(7,10,17,.92);backdrop-filter:blur(20px);
border-top:1px solid var(--border);
padding-bottom:max(0px,env(safe-area-inset-bottom));
}
.nav-item{
background:transparent;display:flex;flex-direction:column;
align-items:center;gap:5px;font-size:10px;color:#788397;min-width:54px;
}
.nav-item i{font-size:20px}
.nav-item.active{color:#4b9cff}
.nav-item.active i{filter:drop-shadow(0 0 7px rgba(22,119,255,.5))}

/* Footer */
.footer{
background:var(--bg2);
border-top:1px solid var(--border);
padding:40px 5% 100px;
margin-top:20px;
}
.footer-content{
max-width:1200px;
margin:0 auto;
display:grid;
grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
gap:30px;
}
.footer-section h4{
font-size:14px;font-weight:700;
margin-bottom:15px;
color:#c2cad7;
}
.footer-section p{
font-size:13px;color:var(--muted);
line-height:1.6;
margin-bottom:10px;
}
.footer-links{list-style:none}
.footer-links li{margin-bottom:10px}
.footer-links a{
font-size:13px;color:var(--muted);
transition:.2s;
}
.footer-links a:hover{color:#4b9cff}
.footer-bottom{
max-width:1200px;
margin:30px auto 0;
padding-top:20px;
border-top:1px solid var(--border);
text-align:center;
font-size:12px;color:var(--muted);
}

/* Search overlay */
.search-overlay,.settings-overlay{
position:fixed;inset:0;
background:rgba(0,0,0,.72);backdrop-filter:blur(10px);
z-index:500;display:none;
align-items:flex-start;justify-content:center;
padding:90px 5% 20px;
}
.search-overlay.show,.settings-overlay.show{display:flex}
.modal{
width:min(650px,100%);
background:#0b101c;
border:1px solid var(--border);
border-radius:22px;
box-shadow:0 25px 80px rgba(0,0,0,.6);
padding:17px;
animation:modalIn .25s ease;
}
@keyframes modalIn{from{opacity:0;transform:translateY(-12px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}
.search-box{
display:flex;align-items:center;gap:10px;
background:#111a2a;border:1px solid rgba(255,255,255,.08);
border-radius:15px;padding:0 14px;height:54px;
}
.search-box input{flex:1;border:none;outline:none;background:transparent;color:white;font-size:16px}
.close-btn{
width:38px;height:38px;border-radius:11px;
background:rgba(255,255,255,.06);
display:grid;place-items:center;
}

/* Settings */
.settings-title{font-size:21px;font-weight:700;margin-bottom:20px}
.setting-row{display:flex;align-items:center;justify-content:space-between;padding:16px 0;border-bottom:1px solid var(--border)}
.setting-name{font-size:14px;font-weight:600}
.setting-desc{font-size:12px;color:var(--muted);margin-top:4px}
.switch{
width:48px;height:27px;border-radius:20px;
background:#202b3e;position:relative;transition:.2s;
}
.switch.active{background:var(--blue)}
.switch::after{
content:"";position:absolute;width:21px;height:21px;
top:3px;left:3px;border-radius:50%;background:white;transition:.2s;
}
.switch.active::after{left:24px}

/* Responsive */
@media(min-width:800px){
body{padding-bottom:0}
.bottom-nav{display:none}
.topbar{padding:0 7%}
.hero{padding-left:7%;padding-right:7%}
.section{padding:0 7%}
.card{flex-basis:175px;height:260px}
.footer{padding-bottom:40px}
.slider-arrow.prev{left:-15px}
.slider-arrow.next{right:-15px}
.hero-desc{
-webkit-line-clamp:4;
}
}
@media(max-width:520px){
.topbar{height:64px;padding:0 16px}
.logo-img{height:35px}
.hero{height:690px;min-height:620px;padding:0 16px 100px;align-items:flex-end}
.hero-bg{background-position:58% center;opacity:.55!important}
.hero-overlay{
background:
linear-gradient(90deg,rgba(5,7,13,.97) 0%,rgba(5,7,13,.72) 60%,rgba(5,7,13,.2)),
linear-gradient(0deg,#05070d 0%,rgba(5,7,13,.72) 16%,transparent 58%),
linear-gradient(180deg,rgba(5,7,13,.55),transparent 25%);
}
.hero-title{font-size:43px;letter-spacing:-2px}
.hero-desc{
font-size:13px;
line-height:1.55;
-webkit-line-clamp:2;
}
.hero-meta{font-size:12px}
.hero-buttons{width:100%}
.primary-btn{height:46px;padding:0 16px;font-size:13px}
.hero-dots{bottom:72px;right:16px}
.hero-arrow{display:none !important}
.section{padding:0 16px;margin-bottom:28px}
.section-title{font-size:18px}
.card{flex-basis:135px;height:202px;border-radius:13px}
.card-title{font-size:12px}
.card-info{padding:10px 9px}
.continue-card{flex-basis:250px;height:142px}
.rank-card{flex-basis:190px}
.rank-poster{width:130px;height:110px}
.rank-number{font-size:88px}
.loading-logo{width:80px;height:80px}
.slider-arrow{display:none}
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

<div id="app">
<header class="topbar" id="topbar">
<a href="index.php"><img src="logo.png" alt="Watchvibe" class="logo-img"></a>
<div class="nav-actions">
<a href="browse.php" class="icon-btn" aria-label="Keşfet"><i class="fas fa-compass"></i></a>
<button class="icon-btn" onclick="openSearch()" aria-label="Ara"><i class="fas fa-search"></i></button>
<?php if ($user): ?>
<a href="login.php" class="user-badge"><i class="fas fa-user"></i> <?php echo e($user['username']); ?></a>
<?php else: ?>
<a href="login.php" class="login-btn"><i class="fas fa-sign-in-alt"></i> Giriş Yap</a>
<?php endif; ?>
</div>
</header>

<section class="hero" id="heroSection">
<div class="hero-bg active" id="heroBg"></div>
<div class="hero-overlay"></div>
<div class="hero-content" id="heroContent"></div>
<div class="hero-dots" id="heroDots"></div>
<button class="hero-arrow prev" onclick="changeHero(heroIndex - 1 < 0 ? heroItems.length - 1 : heroIndex - 1)" aria-label="Önceki"><i class="fas fa-chevron-left"></i></button>
<button class="hero-arrow next" onclick="changeHero((heroIndex + 1) % heroItems.length)" aria-label="Sonraki"><i class="fas fa-chevron-right"></i></button>
</section>

<?php if (!empty($continue)): ?>
<section class="section">
<div class="section-head">
<h2 class="section-title"><i class="fas fa-play-circle"></i>İzlemeye Devam Et</h2>
</div>
<div class="slider-container">
<button class="slider-arrow prev" onclick="slideSlider(this, -1)" aria-label="Önceki"><i class="fas fa-chevron-left"></i></button>
<div class="slider" id="continueSlider">
<?php foreach ($continue as $c):
$ep_num = $c['episode_number'] ? (int)$c['episode_number'] : null;
$url = content_url($c['content_id'], $c['title'], $ep_num);
$progress = min(100, max(5, (int)$c['progress']));
?>
<a class="continue-card" href="<?php echo $url; ?>">
<img src="<?php echo e($c['poster_url']); ?>" alt="<?php echo e($c['title']); ?>" loading="lazy">
<div class="continue-overlay"></div>
<div class="continue-info">
<div class="continue-title"><?php echo e($c['title']); ?><?php if ($ep_num): ?> - <?php echo (int)$ep_num; ?>. Bölüm<?php endif; ?></div>
<div class="progress"><span style="width:<?php echo $progress; ?>%"></span></div>
</div>
</a>
<?php endforeach; ?>
</div>
<button class="slider-arrow next" onclick="slideSlider(this, 1)" aria-label="Sonraki"><i class="fas fa-chevron-right"></i></button>
</div>
</section>
<?php endif; ?>

<?php if (!empty($popular)): ?>
<section class="section">
<div class="section-head"><h2 class="section-title"><i class="fas fa-fire"></i> Bugünün Popülerleri</h2></div>
<div class="slider-container">
<button class="slider-arrow prev" onclick="slideSlider(this, -1)" aria-label="Önceki"><i class="fas fa-chevron-left"></i></button>
<div class="slider rank-slider" id="popularSlider">
<?php foreach ($popular as $i => $c):
$url = content_url($c['id'], $c['title']);
?>
<a class="rank-card" href="<?php echo $url; ?>">
<div class="rank-number"><?php echo $i + 1; ?></div>
<div class="rank-poster"><img src="<?php echo e($c['poster_url']); ?>" alt="<?php echo e($c['title']); ?>" loading="lazy"></div>
</a>
<?php endforeach; ?>
</div>
<button class="slider-arrow next" onclick="slideSlider(this, 1)" aria-label="Sonraki"><i class="fas fa-chevron-right"></i></button>
</div>
</section>
<?php endif; ?>

<?php if (!empty($new_releases)): ?>
<section class="section">
<div class="section-head"><h2 class="section-title"><i class="fas fa-bolt"></i> Yeni Eklenenler</h2></div>
<div class="slider-container">
<button class="slider-arrow prev" onclick="slideSlider(this, -1)" aria-label="Önceki"><i class="fas fa-chevron-left"></i></button>
<div class="slider" id="newReleasesSlider">
<?php foreach ($new_releases as $c) { echo content_card($c, true); } ?>
</div>
<button class="slider-arrow next" onclick="slideSlider(this, 1)" aria-label="Sonraki"><i class="fas fa-chevron-right"></i></button>
</div>
</section>
<?php endif; ?>

<?php if (!empty($movies)): ?>
<section class="section">
<div class="section-head"><h2 class="section-title"><i class="fas fa-film"></i> Filmler</h2><a class="show-all" href="browse.php?type=movie">Tümünü Gör</a></div>
<div class="slider-container">
<button class="slider-arrow prev" onclick="slideSlider(this, -1)" aria-label="Önceki"><i class="fas fa-chevron-left"></i></button>
<div class="slider" id="moviesSlider">
<?php foreach ($movies as $c) { echo content_card($c, true); } ?>
</div>
<button class="slider-arrow next" onclick="slideSlider(this, 1)" aria-label="Sonraki"><i class="fas fa-chevron-right"></i></button>
</div>
</section>
<?php endif; ?>

<?php if (!empty($series)): ?>
<section class="section">
<div class="section-head"><h2 class="section-title"><i class="fas fa-tv"></i> Diziler</h2><a class="show-all" href="browse.php?type=series">Tümünü Gör</a></div>
<div class="slider-container">
<button class="slider-arrow prev" onclick="slideSlider(this, -1)" aria-label="Önceki"><i class="fas fa-chevron-left"></i></button>
<div class="slider" id="seriesSlider">
<?php foreach ($series as $c) { echo content_card($c, true); } ?>
</div>
<button class="slider-arrow next" onclick="slideSlider(this, 1)" aria-label="Sonraki"><i class="fas fa-chevron-right"></i></button>
</div>
</section>
<?php endif; ?>

<?php if (!empty($anime)): ?>
<section class="section">
<div class="section-head"><h2 class="section-title"><i class="fas fa-dragon"></i> Anime</h2><a class="show-all" href="browse.php?type=anime">Tümünü Gör</a></div>
<div class="slider-container">
<button class="slider-arrow prev" onclick="slideSlider(this, -1)" aria-label="Önceki"><i class="fas fa-chevron-left"></i></button>
<div class="slider" id="animeSlider">
<?php foreach ($anime as $c) { echo content_card($c, true); } ?>
</div>
<button class="slider-arrow next" onclick="slideSlider(this, 1)" aria-label="Sonraki"><i class="fas fa-chevron-right"></i></button>
</div>
</section>
<?php endif; ?>

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
<?php if ($user): ?>
<li><a href="login.php?tab=continue">Profilim</a></li>
<li><a href="login.php?tab=list">Listem</a></li>
<li><a href="login.php?tab=settings">Ayarlar</a></li>
<li><a href="logout.php">Çıkış Yap</a></li>
<?php else: ?>
<li><a href="login.php">Giriş Yap</a></li>
<li><a href="login.php?tab=register">Kayıt Ol</a></li>
<?php endif; ?>
</ul>
</div>
</div>
<div class="footer-bottom">
<p>&copy; <?php echo date('Y'); ?> Watchvibe. Tüm hakları saklıdır.</p>
</div>
</footer>
</div>

<nav class="bottom-nav">
<a class="nav-item active" href="index.php"><i class="fas fa-house"></i><span>Ana Sayfa</span></a>
<a class="nav-item" href="browse.php"><i class="fas fa-compass"></i><span>Keşfet</span></a>
<a class="nav-item" href="<?php echo $user ? 'login.php?tab=list' : 'login.php'; ?>"><i class="fas fa-bookmark"></i><span>Listem</span></a>
<a class="nav-item" href="<?php echo $user ? 'login.php?tab=settings' : 'login.php'; ?>"><i class="fas fa-gear"></i><span>Ayarlar</span></a>
</nav>

<div class="search-overlay" id="searchOverlay" onclick="closeOverlay(event,'searchOverlay')">
<div class="modal" onclick="event.stopPropagation()">
<div class="search-box">
<i class="fas fa-search" style="color:#8c97aa;font-size:18px"></i>
<input id="searchInput" placeholder="Film, dizi veya anime ara..." oninput="searchContent()">
<button class="close-btn" onclick="closeSearch()"><i class="fas fa-times"></i></button>
</div>
<div id="searchResults" style="margin-top:15px;color:#8c97aa;font-size:13px">Aramak istediğin içeriğin adını yaz.</div>
</div>
</div>

<div class="settings-overlay" id="settingsOverlay" onclick="closeOverlay(event,'settingsOverlay')">
<div class="modal" onclick="event.stopPropagation()">
<div style="display:flex;justify-content:space-between;align-items:center">
<div class="settings-title">Ayarlar</div>
<button class="close-btn" onclick="closeSettings()"><i class="fas fa-times"></i></button>
</div>
<div class="setting-row">
<div>
<div class="setting-name">Otomatik Hero Döndürme</div>
<div class="setting-desc">Ana sayfadaki öne çıkan içerikleri otomatik değiştir</div>
</div>
<button class="switch active" id="setting-autorotate" onclick="toggleSetting(this)"></button>
</div>
<div class="setting-row">
<div>
<div class="setting-name">Yüksek Kalite Görseller</div>
<div class="setting-desc">Poster ve arka plan görsellerini yüksek kalitede yükle</div>
</div>
<button class="switch active" id="setting-hqimages" onclick="toggleSetting(this)"></button>
</div>
<div class="setting-row">
<div>
<div class="setting-name">Otomatik Oynatma</div>
<div class="setting-desc">İzleme sayfasında player otomatik başlasın</div>
</div>
<button class="switch" id="setting-autoplay" onclick="toggleSetting(this)"></button>
</div>
<div class="setting-row">
<div>
<div class="setting-name">Hesap</div>
<div class="setting-desc"><?php echo $user ? e($user['username']) . ' olarak giriş yapıldı' : 'Giriş yapmadınız'; ?></div>
</div>
<?php if ($user): ?>
<a href="logout.php" class="login-btn" style="background:rgba(248,113,113,.15);color:#f87171;border:1px solid rgba(248,113,113,.3)"><i class="fas fa-sign-out-alt"></i> Çıkış</a>
<?php else: ?>
<a href="login.php" class="login-btn"><i class="fas fa-sign-in-alt"></i> Giriş</a>
<?php endif; ?>
</div>
</div>
</div>

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

// ── Slider navigation ──
function slideSlider(btn, direction) {
    const container = btn.parentElement;
    const slider = container.querySelector('.slider');
    const items = slider.children;
    if (items.length === 0) return;
    const itemWidth = items[0].getBoundingClientRect().width + 13; // gap:13px
    slider.scrollBy({
        left: direction * itemWidth,
        behavior: 'smooth'
    });
}

// Update arrow visibility on scroll
function updateArrows(slider) {
    const container = slider.parentElement;
    const prevBtn = container.querySelector('.slider-arrow.prev');
    const nextBtn = container.querySelector('.slider-arrow.next');
    
    if (prevBtn) {
        if (slider.scrollLeft <= 10) {
            prevBtn.classList.add('hidden');
        } else {
            prevBtn.classList.remove('hidden');
        }
    }
    
    if (nextBtn) {
        if (slider.scrollLeft + slider.clientWidth >= slider.scrollWidth - 10) {
            nextBtn.classList.add('hidden');
        } else {
            nextBtn.classList.remove('hidden');
        }
    }
}

// Initialize arrows for all sliders
document.querySelectorAll('.slider').forEach(slider => {
    updateArrows(slider);
    slider.addEventListener('scroll', () => updateArrows(slider));
});

// ── Hero rotation ──
const heroItems = [
<?php foreach ($featured as $c):
$url = content_url($c['id'], $c['title']);
?>
{
title: <?php echo json_encode($c['title']); ?>,
meta: <?php echo json_encode($c['year'] . ' • ' . $c['duration'] . ' • ' . $c['genres']); ?>,
rating: <?php echo json_encode($c['rating']); ?>,
desc: <?php echo json_encode($c['description']); ?>,
image: <?php echo json_encode($c['backdrop_url']); ?>,
url: <?php echo json_encode($url); ?>
},
<?php endforeach; ?>
];

let heroIndex = 0;
let heroTimer = null;
let touchStartX = 0;
let touchEndX = 0;

function truncateText(text, maxLength) {
    if (text.length <= maxLength) return text;
    return text.substring(0, maxLength).trim() + '...';
}

function renderHero() {
if (!heroItems.length) return;
const item = heroItems[heroIndex];
const bg = document.getElementById('heroBg');
const content = document.getElementById('heroContent');
bg.classList.remove('active');
setTimeout(() => {
bg.style.backgroundImage = `url("${item.image}")`;
bg.classList.add('active');
}, 50);

// Mobilde daha kısa açıklama göster
const isMobile = window.innerWidth <= 520;
const truncatedDesc = isMobile ? truncateText(item.desc, 100) : truncateText(item.desc, 200);

content.innerHTML = `
<h1 class="hero-title">${item.title}</h1>
<div class="hero-meta"><span class="rating"><i class="fas fa-star"></i> ${item.rating}</span><span>${item.meta}</span><span>HD</span></div>
<p class="hero-desc">${truncatedDesc}</p>
<div class="hero-buttons">
<a class="primary-btn" href="${item.url}"><i class="fas fa-play"></i> Hemen İzle</a>
</div>`;
renderDots();
}

function renderDots() {
document.getElementById('heroDots').innerHTML = heroItems.map((_, i) =>
`<div class="hero-dot ${i === heroIndex ? 'active' : ''}" onclick="changeHero(${i})"></div>`
).join('');
}

function changeHero(i) {
if (i < 0) i = heroItems.length - 1;
if (i >= heroItems.length) i = 0;
heroIndex = i;
renderHero();
resetTimer();
}

function resetTimer() {
if (heroTimer) clearInterval(heroTimer);
const autoRotate = document.getElementById('setting-autorotate')?.classList.contains('active');
if (autoRotate) {
heroTimer = setInterval(() => {
heroIndex = (heroIndex + 1) % heroItems.length;
renderHero();
}, 6500);
}
}

// ── Touch/Swipe for Hero ──
const heroSection = document.getElementById('heroSection');

heroSection.addEventListener('touchstart', (e) => {
    touchStartX = e.changedTouches[0].screenX;
}, {passive: true});

heroSection.addEventListener('touchend', (e) => {
    touchEndX = e.changedTouches[0].screenX;
    handleSwipe();
}, {passive: true});

function handleSwipe() {
    const swipeThreshold = 50; // minimum swipe distance
    const swipeDistance = touchEndX - touchStartX;
    
    if (Math.abs(swipeDistance) > swipeThreshold) {
        if (swipeDistance > 0) {
            // Swipe right - previous
            changeHero(heroIndex - 1 < 0 ? heroItems.length - 1 : heroIndex - 1);
        } else {
            // Swipe left - next
            changeHero((heroIndex + 1) % heroItems.length);
        }
    }
}

// Window resize olduğunda hero'yu yeniden render et (mobil/masaüstü geçişi için)
window.addEventListener('resize', () => {
    renderHero();
});

renderHero();
resetTimer();

// ── Search ──
function openSearch() {
document.getElementById('searchOverlay').classList.add('show');
setTimeout(() => document.getElementById('searchInput').focus(), 150);
}
function closeSearch() {
document.getElementById('searchOverlay').classList.remove('show');
}
function openSettings() {
document.getElementById('settingsOverlay').classList.add('show');
}
function closeSettings() {
document.getElementById('settingsOverlay').classList.remove('show');
}
function closeOverlay(e, id) {
if (e.target.id === id) document.getElementById(id).classList.remove('show');
}

let searchTimeout;
function searchContent() {
clearTimeout(searchTimeout);
const q = document.getElementById('searchInput').value.trim();
const result = document.getElementById('searchResults');
if (!q) {
result.innerHTML = 'Aramak istediğin içeriğin adını yaz.';
return;
}
searchTimeout = setTimeout(() => {
fetch('search.php?q=' + encodeURIComponent(q))
.then(r => r.text())
.then(html => { result.innerHTML = html; })
.catch(() => { result.innerHTML = 'Arama hatası.'; });
}, 300);
}

// ── Settings ──
function toggleSetting(el) {
el.classList.toggle('active');
if (el.id === 'setting-autorotate') resetTimer();
localStorage.setItem(el.id, el.classList.contains('active') ? '1' : '0');
}

// Load saved settings
document.querySelectorAll('.switch').forEach(s => {
const saved = localStorage.getItem(s.id);
if (saved !== null) {
if (saved === '1') s.classList.add('active');
else s.classList.remove('active');
}
});

// ── Scroll ──
window.addEventListener('scroll', () => {
document.getElementById('topbar').classList.toggle('scrolled', window.scrollY > 30);
});

// Update arrows on window resize
window.addEventListener('resize', () => {
document.querySelectorAll('.slider').forEach(slider => updateArrows(slider));
});
</script>
</body>
</html>
