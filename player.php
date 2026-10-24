<?php
require_once 'config.php';

$id = (int)($_GET['id'] ?? 0);
$content = fetch_content($id);

if (!$content) {
    http_response_code(404);
    die('<!DOCTYPE html><html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><link rel="icon" type="image/png" href="icon.png"><title>İçerik bulunamadı - Watchvibe</title><style>body{background:#05070d;color:#f5f8ff;font-family:system-ui;display:flex;align-items:center;justify-content:center;min-height:100vh;text-align:center}a{color:#4b9cff}</style></head><body><div><h1>İçerik bulunamadı</h1><p><a href="index.php">Ana sayfaya dön</a></p></div></body></html>');
}

$user = current_user();
$episodes = fetch_episodes($id);
$current_ep = isset($_GET['bolum']) ? (int)$_GET['bolum'] : 0;
$current_ep_id = null;
$current_player = isset($_GET['player']) ? (int)$_GET['player'] : 0;

if ($current_ep > 0) {
    foreach ($episodes as $ep) {
        if ((int)$ep['episode_number'] === $current_ep) {
            $current_ep_id = (int)$ep['id'];
            break;
        }
    }
}

// Tıklama kaydı
$today = date('Y-m-d');
$stmt = db()->prepare("INSERT INTO content_clicks (content_id, click_date, click_count) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE click_count = click_count + 1");
$stmt->bind_param("is", $id, $today);
$stmt->execute();
$stmt->close();

// Fetch players
$players = [];
if ($current_ep_id) {
    $r = db()->query("SELECT * FROM episode_players WHERE episode_id = $current_ep_id ORDER BY id ASC");
} else {
    $r = db()->query("SELECT * FROM content_players WHERE content_id = $id ORDER BY id ASC");
}
if ($r) while ($row = $r->fetch_assoc()) $players[] = $row;

if (empty($players)) {
    $players[] = ['id' => 0, 'name' => 'Varsayılan Player', 'embed_url' => $content['embed_url']];
}

$current_player_id = 0;
$embed_url = $players[0]['embed_url'];
$current_player_name = $players[0]['name'];

if ($current_player > 0) {
    foreach ($players as $p) {
        if ((int)$p['id'] === $current_player) {
            $current_player_id = $current_player;
            $embed_url = $p['embed_url'];
            $current_player_name = $p['name'];
            break;
        }
    }
}

// Episode fallback
if ($content['type'] !== 'movie' && !empty($episodes)) {
    if ($current_ep_id) {
        foreach ($episodes as $ep) {
            if ((int)$ep['id'] === $current_ep_id) {
                $embed_url = $ep['embed_url'];
                break;
            }
        }
    }
    if (empty($embed_url) && !empty($episodes)) {
        $embed_url = $episodes[0]['embed_url'];
        $current_ep = (int)$episodes[0]['episode_number'];
        $current_ep_id = (int)$episodes[0]['id'];
    }
}

// İzleme durumu
$is_watched = false;
$in_list = false;
$initial_progress = 0;

if ($user) {
    $uid = (int)$user['id'];
    $r = db()->query("SELECT id FROM user_lists WHERE user_id = $uid AND content_id = $id AND list_type = 'watched'");
    $is_watched = $r && $r->num_rows > 0;
    
    $r = db()->query("SELECT id FROM user_lists WHERE user_id = $uid AND content_id = $id AND list_type = 'watchlist'");
    $in_list = $r && $r->num_rows > 0;
    
    $check = db()->query("SELECT progress FROM watch_history WHERE user_id = $uid AND content_id = $id AND " . ($current_ep_id ? "episode_id = $current_ep_id" : "episode_id IS NULL"));
    if ($check && $row = $check->fetch_assoc()) $initial_progress = (int)$row['progress'];
}

// POST işlemleri
if ($user && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $uid = (int)$user['id'];
    if ($action === 'add_list') db()->query("INSERT IGNORE INTO user_lists (user_id, content_id, list_type) VALUES ($uid, $id, 'watchlist')");
    if ($action === 'remove_list') db()->query("DELETE FROM user_lists WHERE user_id = $uid AND content_id = $id AND list_type = 'watchlist'");
    if ($action === 'mark_watched') db()->query("INSERT IGNORE INTO user_lists (user_id, content_id, list_type) VALUES ($uid, $id, 'watched')");
    if ($action === 'unmark_watched') db()->query("DELETE FROM user_lists WHERE user_id = $uid AND content_id = $id AND list_type = 'watched'");
    
    if ($action === 'add_comment') {
        $comment_text = trim($_POST['comment'] ?? '');
        $is_spoiler = isset($_POST['is_spoiler']) ? 1 : 0;
        if (mb_strlen($comment_text) >= 2) {
            $stmt = db()->prepare("INSERT INTO comments (user_id, content_id, comment, is_spoiler, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("iisi", $uid, $id, $comment_text, $is_spoiler);
            $stmt->execute();
            $stmt->close();
        }
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?id=' . $id . ($current_ep > 0 ? '&bolum=' . $current_ep : '') . '#comments');
        exit;
    }
    
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// Yorumları çek
$comments = [];
$r = db()->query("SELECT c.*, u.username FROM comments c JOIN users u ON c.user_id = u.id WHERE c.content_id = $id ORDER BY c.created_at DESC");
if ($r) while ($row = $r->fetch_assoc()) $comments[] = $row;

$type_labels = ['movie' => 'Film', 'series' => 'Dizi', 'anime' => 'Anime'];

// SEO başlık
$seo_title = e($content['title']) . ' İzle (' . e($content['year']) . ') - Dublaj/Altyazı Ultra HD Tek Parça - Watchvibe';
if ($current_ep > 0 && $content['type'] !== 'movie') {
    $seo_title = e($content['title']) . ' ' . $current_ep . '. Bölüm İzle (' . e($content['year']) . ') - Dublaj/Altyazı Ultra HD Tek Parça - Watchvibe';
}
$seo_desc = mb_substr(strip_tags($content['description']), 0, 160);

// Sezonlar
$seasons = [];
if (!empty($episodes)) {
    foreach ($episodes as $ep) {
        $season = isset($ep['season_number']) ? (int)$ep['season_number'] : 1;
        if (!isset($seasons[$season])) $seasons[$season] = [];
        $seasons[$season][] = $ep;
    }
    ksort($seasons);
}

$bg_image = !empty($content['backdrop_url']) ? $content['backdrop_url'] : $content['poster_url'];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?php echo e($seo_desc); ?>">
<meta property="og:title" content="<?php echo $seo_title; ?>">
<meta property="og:description" content="<?php echo e($seo_desc); ?>">
<meta property="og:image" content="<?php echo e($bg_image); ?>">
<meta property="og:type" content="video.movie">
<meta property="og:site_name" content="Watchvibe">
<meta name="robots" content="index, follow">
<link rel="icon" type="image/png" href="icon.png">
<link rel="apple-touch-icon" href="icon.png">
<title><?php echo $seo_title; ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--bg:#05070d;--card:#0d1322;--blue:#1677ff;--text:#f5f8ff;--muted:#8c97aa;--border:rgba(255,255,255,.07)}

html{min-height:100%}
body{
    background:var(--bg);
    color:var(--text);
    font-family:'Inter',system-ui,sans-serif;
    overflow-x:hidden;
    padding-bottom:85px;
    min-height:100vh;
    position:relative;
}

a{text-decoration:none;color:inherit}
button{border:none;cursor:pointer;font:inherit;color:inherit}

/* YÜKLEME */
.loading-screen{position:fixed;top:0;left:0;width:100%;height:100%;background:var(--bg);display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:9999;transition:opacity .5s ease,visibility .5s ease}
.loading-screen.hidden{opacity:0;visibility:hidden}
.loading-logo{width:100px;height:100px;margin-bottom:30px;animation:pulse 2s ease-in-out infinite}
.loading-logo img{width:100%;height:100%;object-fit:contain}
.loading-spinner{width:50px;height:50px;border:3px solid rgba(22,119,255,.2);border-top-color:var(--blue);border-radius:50%;animation:spin 1s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes pulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.05);opacity:.8}}

/* ARKA PLAN */
body::before{
    content:'';
    position:fixed;
    top:0;left:0;
    width:100%;
    height:100vh;
    background-image:url('<?php echo e($bg_image); ?>');
    background-size:cover;
    background-position:center top;
    background-repeat:no-repeat;
    opacity:.4;
    z-index:0;
    pointer-events:none;
}

body::after{
    content:'';
    position:fixed;
    top:0;left:0;
    width:100%;
    height:100vh;
    background:linear-gradient(
        180deg,
        rgba(5,7,13,.25) 0%,
        rgba(5,7,13,.5) 20%,
        rgba(5,7,13,.7) 40%,
        rgba(5,7,13,.85) 60%,
        rgba(5,7,13,.95) 80%,
        #05070d 95%,
        #05070d 100%
    );
    z-index:0;
    pointer-events:none;
}

.content-wrap{position:relative;z-index:1}

.topbar{position:fixed;top:0;left:0;width:100%;height:64px;display:flex;align-items:center;justify-content:space-between;padding:0 5%;z-index:100;background:rgba(5,7,13,.96);backdrop-filter:blur(18px);border-bottom:1px solid var(--border)}
.logo-img{height:35px;width:auto}
.nav-actions{display:flex;align-items:center;gap:10px}
.icon-btn{width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,.06);border:1px solid var(--border);display:grid;place-items:center;font-size:15px}
.icon-btn:hover{background:rgba(22,119,255,.2)}

.content-header{width:100%;max-width:1200px;margin:0 auto;padding:90px 5% 25px}
.content-header-inner{display:flex;gap:20px;flex-wrap:wrap;align-items:flex-start}
.content-poster{flex:0 0 140px;width:140px;border-radius:12px;overflow:hidden;border:1px solid var(--border);box-shadow:0 8px 25px rgba(0,0,0,.3)}
.content-poster img{width:100%;height:auto;display:block}
.content-header-info{flex:1;min-width:250px}
.content-title{font-size:26px;font-weight:800;margin-bottom:10px;letter-spacing:-1px}
.content-meta{display:flex;align-items:center;flex-wrap:wrap;gap:8px;color:#c2cad7;font-size:14px;margin-bottom:14px}
.badge{padding:4px 10px;border-radius:6px;font-size:12px;font-weight:600;background:rgba(22,119,255,.15);border:1px solid rgba(22,119,255,.3);color:#8fc3ff}
.rating{color:#ffd24d;font-weight:600}
.content-description{color:#b4bdca;line-height:1.7;font-size:15px;margin-bottom:16px}
.content-actions{display:flex;gap:10px;flex-wrap:wrap}
.action-btn{padding:11px 18px;border-radius:11px;font-weight:600;font-size:14px;display:flex;align-items:center;gap:8px;transition:.2s}
.action-btn.secondary{background:rgba(255,255,255,.08);border:1px solid var(--border)}
.action-btn.secondary:hover{background:rgba(255,255,255,.15)}
.action-btn.danger{background:rgba(248,113,113,.12);border:1px solid rgba(248,113,113,.25);color:#f87171}
.action-btn.watched-active{background:rgba(16,185,129,.25);border:1px solid rgba(16,185,129,.5);color:#34d399;font-weight:700}

.player-section{width:100%;max-width:1200px;margin:0 auto;padding:0 5%}
.player-wrapper{position:relative;width:100%;aspect-ratio:16/9;border-radius:14px;overflow:hidden;background:#000;border:1px solid var(--border);box-shadow:0 20px 60px rgba(0,0,0,.5)}
.player-wrapper iframe{width:100%;height:100%;border:none;display:block}

.player-selector{display:flex;align-items:center;gap:10px;margin-top:14px;flex-wrap:wrap}
.player-selector-label{font-size:14px;font-weight:600;color:var(--muted)}
.player-selector-buttons{display:flex;gap:8px;flex-wrap:wrap}
.player-btn{padding:9px 14px;border-radius:9px;background:rgba(255,255,255,.05);border:1px solid var(--border);font-size:13px;font-weight:600;transition:.2s;display:flex;align-items:center;gap:6px}
.player-btn:hover{background:rgba(22,119,255,.15)}
.player-btn.active{background:var(--blue);border-color:var(--blue);color:white}

.episode-selector{margin-top:15px;background:rgba(13,19,34,.9);border:1px solid var(--border);border-radius:12px;overflow:hidden;backdrop-filter:blur(10px)}
.episode-selector-header{padding:16px 18px;display:flex;align-items:center;justify-content:space-between;cursor:pointer;background:rgba(255,255,255,.03)}
.episode-selector-title{font-size:16px;font-weight:700}
.episode-selector-toggle{transition:.3s}
.episode-selector.open .episode-selector-toggle{transform:rotate(180deg)}
.episode-selector-body{max-height:0;overflow:hidden;transition:max-height .4s ease}
.episode-selector.open .episode-selector-body{max-height:400px;overflow-y:auto}

.season-tabs{display:flex;gap:6px;padding:14px 18px 0;overflow-x:auto;scrollbar-width:none}
.season-tab{padding:8px 14px;border-radius:8px;background:rgba(255,255,255,.05);font-size:13px;font-weight:600;white-space:nowrap;transition:.2s}
.season-tab.active{background:var(--blue);color:white}

.episode-list{padding:14px 18px 18px}
.episode-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(50px,1fr));gap:7px}
.episode-item{aspect-ratio:1;display:grid;place-items:center;border-radius:8px;background:rgba(255,255,255,.05);font-size:13px;font-weight:600;transition:.2s}
.episode-item:hover{background:rgba(22,119,255,.2)}
.episode-item.active{background:var(--blue);color:white}

.episode-jump{display:flex;align-items:center;gap:10px;padding:0 18px 14px}
.episode-jump-input{flex:1;height:42px;padding:0 12px;border-radius:8px;background:rgba(255,255,255,.05);border:1px solid var(--border);color:white;font-size:14px;outline:none}
.episode-jump-btn{height:42px;padding:0 14px;border-radius:8px;background:var(--blue);font-size:13px;font-weight:600}

.login-hint{background:rgba(22,119,255,.08);border:1px solid rgba(22,119,255,.2);border-radius:12px;padding:16px;margin-top:15px;text-align:center;font-size:13px;color:#8fc3ff}
.login-hint a{color:#4b9cff;font-weight:600;text-decoration:underline}

/* YORUMLAR */
.comments-section{width:100%;max-width:1200px;margin:30px auto 0;padding:0 5%}
.comments-title{font-size:20px;font-weight:700;margin-bottom:20px}
.comment-form{background:rgba(13,19,34,.9);border:1px solid var(--border);border-radius:14px;padding:18px;margin-bottom:25px}
.comment-form textarea{width:100%;min-height:80px;background:rgba(255,255,255,.05);border:1px solid var(--border);border-radius:10px;color:white;padding:12px;font-size:14px;resize:vertical;outline:none}
.comment-form textarea:focus{border-color:rgba(22,119,255,.5)}
.comment-form-bottom{display:flex;align-items:center;justify-content:space-between;margin-top:12px;flex-wrap:wrap;gap:10px}
.spoiler-check{display:flex;align-items:center;gap:8px;font-size:13px;color:var(--muted)}
.spoiler-check input{width:16px;height:16px;accent-color:var(--blue)}
.comment-submit{padding:10px 20px;border-radius:10px;background:var(--blue);font-weight:600;font-size:14px}
.comment-item{background:rgba(13,19,34,.8);border:1px solid var(--border);border-radius:12px;padding:16px;margin-bottom:12px}
.comment-header{display:flex;align-items:center;gap:8px;margin-bottom:8px}
.comment-username{font-weight:600;font-size:14px}
.comment-date{font-size:11px;color:var(--muted)}
.comment-text{font-size:14px;color:#c2cad7;line-height:1.6}
.comment-spoiler .comment-text{filter:blur(6px);transition:filter .3s;cursor:pointer}
.comment-spoiler .comment-text:hover{filter:blur(0)}
.comment-spoiler.revealed .comment-text{filter:blur(0)}

.footer{background:rgba(9,13,24,.95);border-top:1px solid var(--border);padding:30px 5% 100px;margin-top:30px}
.footer-content{max-width:1200px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:25px}
.footer-section h4{font-size:13px;font-weight:700;margin-bottom:12px;color:#c2cad7}
.footer-section p{font-size:12px;color:var(--muted);line-height:1.6;margin-bottom:8px}
.footer-links{list-style:none}
.footer-links li{margin-bottom:8px}
.footer-links a{font-size:12px;color:var(--muted)}
.footer-links a:hover{color:#4b9cff}
.footer-bottom{max-width:1200px;margin:25px auto 0;padding-top:15px;border-top:1px solid var(--border);text-align:center;font-size:11px;color:var(--muted)}

.bottom-nav{position:fixed;bottom:0;left:0;right:0;height:70px;z-index:200;display:flex;align-items:center;justify-content:space-around;background:rgba(7,10,17,.92);backdrop-filter:blur(20px);border-top:1px solid var(--border)}
.nav-item{background:transparent;display:flex;flex-direction:column;align-items:center;gap:4px;font-size:10px;color:#788397;min-width:50px}
.nav-item i{font-size:18px}
.nav-item.active{color:#4b9cff}

@media(min-width:800px){
body{padding-bottom:0}
.bottom-nav{display:none}
.footer{padding-bottom:30px}
}
@media(max-width:700px){
.content-header{padding:70px 16px 15px}
.content-poster{flex:0 0 90px;width:90px}
.content-title{font-size:20px}
.content-meta{font-size:12px}
.content-description{font-size:13px}
.action-btn{padding:9px 14px;font-size:12px}
.player-section{padding:0 16px}
.episode-grid{grid-template-columns:repeat(auto-fill,minmax(42px,1fr));gap:5px}
.episode-item{font-size:11px}
.loading-logo{width:80px;height:80px}
.comments-section{padding:0 16px}
}
</style>
</head>
<body>
<div class="loading-screen" id="loadingScreen">
    <div class="loading-logo">
        <img src="icon.png" alt="Watchvibe" onerror="this.style.display='none'">
    </div>
    <div class="loading-spinner"></div>
</div>

<div class="content-wrap">
<header class="topbar">
<a href="index.php"><img src="logo.png" alt="Watchvibe" class="logo-img"></a>
<div class="nav-actions">
<a href="index.php" class="icon-btn"><i class="fas fa-house"></i></a>
<a href="browse.php" class="icon-btn"><i class="fas fa-compass"></i></a>
</div>
</header>

<div class="content-header">
<div class="content-header-inner">
<div class="content-poster">
<img src="<?php echo e($content['poster_url']); ?>" alt="<?php echo e($content['title']); ?>">
</div>
<div class="content-header-info">
<h1 class="content-title"><?php echo e($content['title']); ?><?php if ($current_ep > 0): ?> - <?php echo $current_ep; ?>. Bölüm<?php endif; ?></h1>
<div class="content-meta">
<span class="badge"><?php echo e($type_labels[$content['type']] ?? 'Film'); ?></span>
<span class="rating"><i class="fas fa-star"></i> <?php echo e($content['rating']); ?></span>
<span><?php echo e($content['year']); ?></span>
<span><?php echo e($content['genres']); ?></span>
</div>
<p class="content-description"><?php echo e($content['description']); ?></p>
<div class="content-actions">
<?php if ($user): ?>
<form method="POST" style="display:inline">
<input type="hidden" name="action" value="<?php echo $in_list ? 'remove_list' : 'add_list'; ?>">
<button type="submit" class="action-btn <?php echo $in_list ? 'danger' : 'secondary'; ?>">
<i class="fas fa-bookmark"></i>
<?php echo $in_list ? '✓ Listemde' : 'Listeme Ekle'; ?>
</button>
</form>
<form method="POST" style="display:inline">
<input type="hidden" name="action" value="<?php echo $is_watched ? 'unmark_watched' : 'mark_watched'; ?>">
<button type="submit" class="action-btn <?php echo $is_watched ? 'watched-active' : 'secondary'; ?>">
<i class="fas fa-<?php echo $is_watched ? 'check-circle' : 'check'; ?>"></i>
<?php echo $is_watched ? '✓ İzlendi' : 'İzledim'; ?>
</button>
</form>
<?php else: ?>
<a href="login.php" class="action-btn secondary"><i class="fas fa-bookmark"></i> Listeme Ekle</a>
<?php endif; ?>
</div>
</div>
</div>
</div>

<div class="player-section">
<div class="player-wrapper">
<iframe src="<?php echo e($embed_url); ?>" allow="autoplay; encrypted-media; fullscreen" allowfullscreen frameborder="0"></iframe>
</div>

<?php if (count($players) > 1): ?>
<div class="player-selector">
<span class="player-selector-label"><i class="fas fa-tv"></i> Player:</span>
<div class="player-selector-buttons">
<?php foreach ($players as $p):
$p_url = content_url($content['id'], $content['title'], $current_ep > 0 ? $current_ep : null) . '&player=' . (int)$p['id'];
?>
<a class="player-btn <?php echo $current_player_id === (int)$p['id'] ? 'active' : ''; ?>" href="<?php echo $p_url; ?>">
<i class="fas fa-play-circle"></i> <?php echo e($p['name']); ?>
</a>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>

<?php if (!empty($episodes)): ?>
<div class="episode-selector open" id="episodeSelector">
<div class="episode-selector-header" onclick="toggleEpisodeSelector()">
<div class="episode-selector-title"><i class="fas fa-list"></i>Bölümler</div>
<i class="fas fa-chevron-down episode-selector-toggle"></i>
</div>
<div class="episode-selector-body">
<div class="episode-jump">
<input type="number" class="episode-jump-input" id="episodeJumpInput" placeholder="Bölüm no..." min="1" max="<?php echo count($episodes); ?>">
<button class="episode-jump-btn" onclick="jumpToEpisode()"><i class="fas fa-arrow-right"></i> Git</button>
</div>

<?php if (count($seasons) > 1): ?>
<div class="season-tabs" id="seasonTabs">
<?php foreach ($seasons as $season_num => $season_eps): ?>
<button class="season-tab <?php echo $season_num === 1 ? 'active' : ''; ?>" onclick="switchSeason(<?php echo $season_num; ?>)">Sezon <?php echo $season_num; ?></button>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php foreach ($seasons as $season_num => $season_eps): ?>
<div class="episode-list season-<?php echo $season_num; ?>" <?php echo $season_num !== 1 ? 'style="display:none"' : ''; ?>>
<div class="episode-grid">
<?php foreach ($season_eps as $ep):
$ep_url = content_url($content['id'], $content['title'], (int)$ep['episode_number']);
?>
<a class="episode-item <?php echo $current_ep === (int)$ep['episode_number'] ? 'active' : ''; ?>" href="<?php echo $ep_url; ?>"><?php echo (int)$ep['episode_number']; ?></a>
<?php endforeach; ?>
</div>
</div>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>

<?php if (!$user): ?>
<div class="login-hint"><i class="fas fa-info-circle"></i> İzleme geçmişini kaydetmek için <a href="login.php">giriş yap</a> veya <a href="login.php?tab=register">kayıt ol</a>.</div>
<?php endif; ?>
</div>

<!-- YORUMLAR BÖLÜMÜ -->
<div class="comments-section" id="comments">
    <h2 class="comments-title"><i class="fas fa-comments"></i> Yorumlar (<?php echo count($comments); ?>)</h2>
    
    <?php if ($user): ?>
    <form method="POST" class="comment-form">
        <input type="hidden" name="action" value="add_comment">
        <textarea name="comment" placeholder="Yorumunuzu yazın..." required minlength="2"></textarea>
        <div class="comment-form-bottom">
            <label class="spoiler-check">
                <input type="checkbox" name="is_spoiler" value="1"> Spoiler içeriyor
            </label>
            <button type="submit" class="comment-submit"><i class="fas fa-paper-plane"></i> Gönder</button>
        </div>
    </form>
    <?php else: ?>
    <div class="login-hint"><i class="fas fa-info-circle"></i> Yorum yapmak için <a href="login.php">giriş yapın</a>.</div>
    <?php endif; ?>

    <?php if (!empty($comments)): ?>
        <?php foreach ($comments as $c): ?>
        <div class="comment-item <?php echo $c['is_spoiler'] ? 'comment-spoiler' : ''; ?>">
            <div class="comment-header">
                <span class="comment-username"><?php echo e($c['username']); ?></span>
                <span class="comment-date"><?php echo date('d M Y H:i', strtotime($c['created_at'])); ?></span>
            </div>
            <div class="comment-text" <?php if ($c['is_spoiler']): ?>onclick="this.closest('.comment-item').classList.add('revealed')"<?php endif; ?>><?php echo e($c['comment']); ?></div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="login-hint"><i class="fas fa-comment-slash"></i> Henüz yorum yapılmamış. İlk yorumu sen yap!</div>
    <?php endif; ?>
</div>

<footer class="footer">
<div class="footer-content">
<div class="footer-section"><h4>Watchvibe</h4><p>Film, dizi ve anime izleme platformu.</p></div>
<div class="footer-section"><h4>Hızlı Bağlantılar</h4><ul class="footer-links"><li><a href="index.php">Ana Sayfa</a></li><li><a href="browse.php">Keşfet</a></li><li><a href="browse.php?type=movie">Filmler</a></li><li><a href="browse.php?type=series">Diziler</a></li><li><a href="browse.php?type=anime">Anime</a></li></ul></div>
<div class="footer-section"><h4>Yasal</h4><ul class="footer-links"><li><a href="terms.php">Kullanım Şartları</a></li><li><a href="privacy.php">Gizlilik Politikası</a></li><li><a href="cookies.php">Çerez Politikası</a></li><li><a href="dmca.php">DMCA</a></li><li><a href="contact.php">İletişim</a></li></ul></div>
<div class="footer-section"><h4>Hesap</h4><ul class="footer-links"><?php if ($user): ?><li><a href="login.php">Profilim</a></li><li><a href="logout.php">Çıkış Yap</a></li><?php else: ?><li><a href="login.php">Giriş Yap</a></li><li><a href="login.php?tab=register">Kayıt Ol</a></li><?php endif; ?></ul></div>
</div>
<div class="footer-bottom"><p>&copy; <?php echo date('Y'); ?> Watchvibe. Tüm hakları saklıdır.</p></div>
</footer>

<nav class="bottom-nav">
<a class="nav-item" href="index.php"><i class="fas fa-house"></i><span>Ana Sayfa</span></a>
<a class="nav-item" href="browse.php"><i class="fas fa-compass"></i><span>Keşfet</span></a>
<a class="nav-item" href="<?php echo $user ? 'login.php?tab=list' : 'login.php'; ?>"><i class="fas fa-bookmark"></i><span>Listem</span></a>
<a class="nav-item" href="<?php echo $user ? 'login.php?tab=settings' : 'login.php'; ?>"><i class="fas fa-gear"></i><span>Ayarlar</span></a>
</nav>
</div>

    
<script type="text/javascript">
   var uid = '504907';
   var wid = '757441';
   var pop_tag = document.createElement('script');pop_tag.src='//cdn.popcash.net/show.js';document.body.appendChild(pop_tag);
   pop_tag.onerror = function() {pop_tag = document.createElement('script');pop_tag.src='//cdn2.popcash.net/show.js';document.body.appendChild(pop_tag)};
</script>
    
    
<script>
window.addEventListener('load', function() {
    setTimeout(function() { document.getElementById('loadingScreen').classList.add('hidden'); }, 500);
});
setTimeout(function() { document.getElementById('loadingScreen').classList.add('hidden'); }, 3000);

function toggleEpisodeSelector() { document.getElementById('episodeSelector').classList.toggle('open'); }
function switchSeason(s) {
    document.querySelectorAll('.season-tab').forEach(t => t.classList.remove('active'));
    event.target.classList.add('active');
    document.querySelectorAll('.episode-list').forEach(l => l.style.display = 'none');
    document.querySelector('.season-' + s).style.display = 'block';
}
function jumpToEpisode() {
    const n = parseInt(document.getElementById('episodeJumpInput').value);
    if (n > 0) window.location.href = '<?php echo content_url($content['id'], $content['title']); ?>&bolum=' + n;
}
<?php if ($user): ?>
let progress = <?php echo $initial_progress; ?>;
setInterval(() => {
    progress = Math.min(100, progress + 2);
    const fd = new FormData();
    fd.append('user_id', <?php echo (int)$user['id']; ?>);
    fd.append('content_id', <?php echo $id; ?>);
    fd.append('episode_id', <?php echo $current_ep_id ? $current_ep_id : 'null'; ?> || '');
    fd.append('progress', progress);
    fetch('api.php', {method:'POST', body: fd}).catch(()=>{});
}, 5000);
<?php endif; ?>
</script>
</body>
</html>