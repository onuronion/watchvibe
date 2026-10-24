<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

$action = $_GET['action'] ?? 'dashboard';
$message = '';
$error = '';

// TMDB API ile içerik arama
if (isset($_GET['tmdb_search']) && !empty($_GET['tmdb_search'])) {
    $query = urlencode($_GET['tmdb_search']);
    $type = $_GET['tmdb_type'] ?? 'tv';
    $api_key = '1e5c0149b694325e5c2b8366f5bd8acd';
    
    $url_tr = "https://api.themoviedb.org/3/search/{$type}?api_key={$api_key}&query={$query}&language=tr-TR";
    $url_en = "https://api.themoviedb.org/3/search/{$type}?api_key={$api_key}&query={$query}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url_tr);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response_tr = curl_exec($ch);
    curl_close($ch);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url_en);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response_en = curl_exec($ch);
    curl_close($ch);
    
    $tmdb_results = [];
    $tr_data = json_decode($response_tr, true);
    $en_data = json_decode($response_en, true);
    
    if (isset($tr_data['results']) && isset($en_data['results'])) {
        foreach ($en_data['results'] as $key => $en_item) {
            $tr_item = $tr_data['results'][$key] ?? null;
            $tmdb_results[] = [
                'id' => $en_item['id'],
                'title' => $tr_item['name'] ?? $tr_item['title'] ?? $en_item['name'] ?? $en_item['title'] ?? '',
                'original_title' => $en_item['original_name'] ?? $en_item['original_title'] ?? '',
                'overview' => $tr_item['overview'] ?? $en_item['overview'] ?? '',
                'poster_path' => $en_item['poster_path'] ?? '',
                'backdrop_path' => $en_item['backdrop_path'] ?? '',
                'vote_average' => $en_item['vote_average'] ?? 0,
                'first_air_date' => $en_item['first_air_date'] ?? $en_item['release_date'] ?? '',
                'type' => $type
            ];
        }
    }
}

// TMDB'den içerik seçme
if (isset($_GET['tmdb_select']) && isset($_GET['tmdb_id'])) {
    $tmdb_id = (int)$_GET['tmdb_id'];
    $type = $_GET['tmdb_type'] ?? 'tv';
    $api_key = '1e5c0149b694325e5c2b8366f5bd8acd';
    
    $url = "https://api.themoviedb.org/3/{$type}/{$tmdb_id}?api_key={$api_key}&language=tr-TR";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if ($data && isset($data['id'])) {
        $title = $data['name'] ?? $data['title'] ?? '';
        $overview = $data['overview'] ?? '';
        $poster_path = $data['poster_path'] ?? '';
        $backdrop_path = $data['backdrop_path'] ?? '';
        $vote_average = $data['vote_average'] ?? 0;
        $year = substr($data['first_air_date'] ?? $data['release_date'] ?? date('Y'), 0, 4);
        
        $genres = [];
        if (isset($data['genres'])) {
            foreach ($data['genres'] as $g) {
                $genres[] = $g['name'];
            }
        }
        $genre_str = implode(', ', $genres);
        
        $poster_url = '';
        if ($poster_path) {
            $poster_url = 'https://wsrv.nl/?url=https://image.tmdb.org/t/p/w500' . $poster_path;
        }
        
        $backdrop_url = '';
        if ($backdrop_path) {
            $backdrop_url = 'https://wsrv.nl/?url=https://image.tmdb.org/t/p/original' . $backdrop_path;
        }
        
        $title_esc = db()->real_escape_string($title);
        $overview_esc = db()->real_escape_string($overview);
        $poster_url_esc = db()->real_escape_string($poster_url);
        $backdrop_url_esc = db()->real_escape_string($backdrop_url);
        $genre_str_esc = db()->real_escape_string($genre_str);
        
        $content_type = $type === 'tv' ? 'series' : 'movie';
        $embed_url = '';
        
        $sql = "INSERT INTO content (title, type, description, poster_url, backdrop_url, embed_url, year, duration, genres, rating, is_featured, is_popular, is_new) 
                VALUES ('$title_esc', '$content_type', '$overview_esc', '$poster_url_esc', '$backdrop_url_esc', '$embed_url', $year, '', '$genre_str_esc', $vote_average, 0, 0, 1)";
        
        if (db()->query($sql)) {
            $new_id = db()->insert_id;
            header("Location: admin.php?action=edit_content&id=$new_id&msg=" . urlencode('İçerik eklendi! Şimdi embed URL ekleyin.'));
            exit;
        } else {
            $error = 'Kayıt hatası: ' . db()->error;
        }
    }
}

// POST işlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_action = $_POST['post_action'] ?? '';
    
    // İçerik kaydetme
    if ($post_action === 'save_content') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $type = $_POST['type'] ?? 'movie';
        $description = trim($_POST['description'] ?? '');
        $poster_url = trim($_POST['poster_url'] ?? '');
        $backdrop_url = trim($_POST['backdrop_url'] ?? '');
        $embed_url = trim($_POST['embed_url'] ?? '');
        $year = (int)($_POST['year'] ?? date('Y'));
        $duration = trim($_POST['duration'] ?? '');
        $genres = trim($_POST['genres'] ?? '');
        $rating = (float)($_POST['rating'] ?? 0);
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $is_popular = isset($_POST['is_popular']) ? 1 : 0;
        $is_new = isset($_POST['is_new']) ? 1 : 0;
        
        $title = db()->real_escape_string($title);
        $type = db()->real_escape_string($type);
        $description = db()->real_escape_string($description);
        $poster_url = db()->real_escape_string($poster_url);
        $backdrop_url = db()->real_escape_string($backdrop_url);
        $embed_url = db()->real_escape_string($embed_url);
        $duration = db()->real_escape_string($duration);
        $genres = db()->real_escape_string($genres);
        
        if ($id > 0) {
            $sql = "UPDATE content SET title='$title', type='$type', description='$description', poster_url='$poster_url', backdrop_url='$backdrop_url', embed_url='$embed_url', year=$year, duration='$duration', genres='$genres', rating=$rating, is_featured=$is_featured, is_popular=$is_popular, is_new=$is_new WHERE id=$id";
        } else {
            $sql = "INSERT INTO content (title, type, description, poster_url, backdrop_url, embed_url, year, duration, genres, rating, is_featured, is_popular, is_new) VALUES ('$title', '$type', '$description', '$poster_url', '$backdrop_url', '$embed_url', $year, '$duration', '$genres', $rating, $is_featured, $is_popular, $is_new)";
        }
        
        if (db()->query($sql)) {
            $message = 'Kaydedildi.';
        } else {
            $error = 'Hata: ' . db()->error;
        }
    }
    
    // İçerik silme
    if ($post_action === 'delete_content') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db()->query("DELETE FROM episodes WHERE content_id = $id");
            db()->query("DELETE FROM user_lists WHERE content_id = $id");
            db()->query("DELETE FROM watch_history WHERE content_id = $id");
            db()->query("DELETE FROM content_players WHERE content_id = $id");
            if (db()->query("DELETE FROM content WHERE id = $id")) {
                $message = 'Silindi.';
            }
        }
    }
    
    // Bölüm ekleme
    if ($post_action === 'add_episode') {
        $content_id = (int)($_POST['content_id'] ?? 0);
        $episode_number = (int)($_POST['episode_number'] ?? 0);
        $season_number = (int)($_POST['season_number'] ?? 1);
        $title = db()->real_escape_string(trim($_POST['title'] ?? ''));
        $embed_url = db()->real_escape_string(trim($_POST['embed_url'] ?? ''));
        
        if ($content_id > 0 && $episode_number > 0 && !empty($embed_url)) {
            $sql = "INSERT INTO episodes (content_id, episode_number, season_number, title, embed_url) VALUES ($content_id, $episode_number, $season_number, '$title', '$embed_url')";
            if (db()->query($sql)) {
                $message = 'Bölüm eklendi.';
            } else {
                $error = 'Hata: ' . db()->error;
            }
        }
    }
    
    // Bölüm silme
    if ($post_action === 'delete_episode') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db()->query("DELETE FROM episode_players WHERE episode_id = $id");
            db()->query("DELETE FROM episodes WHERE id = $id");
            $message = 'Bölüm silindi.';
        }
    }
    
    // Yaklaşan ekleme
    if ($post_action === 'add_upcoming') {
        $title = db()->real_escape_string(trim($_POST['title'] ?? ''));
        $content_type = db()->real_escape_string($_POST['content_type'] ?? 'movie');
        $description = db()->real_escape_string(trim($_POST['description'] ?? ''));
        $release_date = db()->real_escape_string($_POST['release_date'] ?? '');
        
        if (!empty($title) && !empty($release_date)) {
            $sql = "INSERT INTO upcoming_releases (title, content_type, description, release_date) VALUES ('$title', '$content_type', '$description', '$release_date')";
            if (db()->query($sql)) {
                $message = 'Eklendi.';
            }
        }
    }
    
    // Yaklaşan silme
    if ($post_action === 'delete_upcoming') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            db()->query("DELETE FROM upcoming_releases WHERE id = $id");
            $message = 'Silindi.';
        }
    }
    
    // Player ekleme
    if ($post_action === 'add_player') {
        $content_id = (int)($_POST['content_id'] ?? 0);
        $episode_id = (int)($_POST['episode_id'] ?? 0);
        $name = db()->real_escape_string(trim($_POST['name'] ?? ''));
        $embed_url = db()->real_escape_string(trim($_POST['embed_url'] ?? ''));
        
        if (!empty($name) && !empty($embed_url)) {
            if ($episode_id > 0) {
                $sql = "INSERT INTO episode_players (episode_id, name, embed_url) VALUES ($episode_id, '$name', '$embed_url')";
            } elseif ($content_id > 0) {
                $sql = "INSERT INTO content_players (content_id, name, embed_url) VALUES ($content_id, '$name', '$embed_url')";
            }
            if (isset($sql) && db()->query($sql)) {
                $message = 'Player eklendi.';
            }
        }
    }
    
    // Player silme
    if ($post_action === 'delete_player') {
        $id = (int)($_POST['id'] ?? 0);
        $type = $_POST['player_type'] ?? 'content';
        if ($id > 0) {
            $table = $type === 'episode' ? 'episode_players' : 'content_players';
            db()->query("DELETE FROM $table WHERE id = $id");
            $message = 'Silindi.';
        }
    }
}

// Verileri çek
$contents = [];
$r = db()->query("SELECT * FROM content ORDER BY id DESC");
if ($r) while ($row = $r->fetch_assoc()) $contents[] = $row;

$episodes = [];
$r = db()->query("SELECT e.*, c.title as content_title FROM episodes e LEFT JOIN content c ON e.content_id = c.id ORDER BY e.content_id DESC");
if ($r) while ($row = $r->fetch_assoc()) $episodes[] = $row;

$upcoming = [];
$r = db()->query("SELECT * FROM upcoming_releases ORDER BY release_date ASC");
if ($r) while ($row = $r->fetch_assoc()) $upcoming[] = $row;

$stats = [
    'total_content' => count($contents),
    'total_episodes' => count($episodes),
    'total_upcoming' => count($upcoming),
    'total_users' => 0
];
$r = db()->query("SELECT COUNT(*) as cnt FROM users");
if ($r && $row = $r->fetch_assoc()) $stats['total_users'] = $row['cnt'];

$edit_content = null;
if ($action === 'edit_content' && isset($_GET['id'])) {
    $edit_id = (int)$_GET['id'];
    $r = db()->query("SELECT * FROM content WHERE id = $edit_id");
    if ($r && $row = $r->fetch_assoc()) $edit_content = $row;
}

$msg_from_url = $_GET['msg'] ?? '';
if ($msg_from_url) $message = $msg_from_url;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Admin Panel - Watchvibe</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#0a0e17;color:#f5f8ff;font-family:'Inter',sans-serif}
a{text-decoration:none;color:inherit}
button{cursor:pointer;font:inherit;border:none}
input,select,textarea{font:inherit}

.layout{display:flex;min-height:100vh}
.sidebar{width:240px;background:#0d1322;border-right:1px solid rgba(255,255,255,.08);padding:20px;position:fixed;top:0;left:0;bottom:0;overflow-y:auto;z-index:10}
.sidebar h2{font-size:18px;margin-bottom:25px;color:#4b9cff}
.sidebar a{display:flex;align-items:center;gap:10px;padding:11px 13px;border-radius:10px;font-size:14px;color:#8c97aa;margin-bottom:4px;transition:.2s}
.sidebar a:hover{background:rgba(255,255,255,.05);color:#fff}
.sidebar a.active{background:rgba(22,119,255,.15);color:#4b9cff}
.sidebar a i{width:20px;text-align:center}

.main{margin-left:240px;padding:25px;flex:1}
.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;flex-wrap:wrap;gap:10px}
.top h1{font-size:24px}
.top h1 span{color:#4b9cff}

.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:25px}
.stat{background:#111827;border:1px solid rgba(255,255,255,.08);border-radius:12px;padding:18px;display:flex;align-items:center;gap:12px}
.stat i{font-size:26px;color:#4b9cff}
.stat h3{font-size:22px;font-weight:800}
.stat p{font-size:11px;color:#8c97aa}

.card{background:#111827;border:1px solid rgba(255,255,255,.08);border-radius:14px;padding:22px;margin-bottom:20px}
.card h3{font-size:17px;margin-bottom:18px;display:flex;align-items:center;gap:8px}
.card h3 i{color:#4b9cff}

.grid2{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}
.fg{margin-bottom:14px}
.fg label{display:block;font-size:12px;font-weight:600;margin-bottom:5px;color:#c2cad7}
.fg input,.fg select,.fg textarea{width:100%;padding:10px 12px;border-radius:9px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);color:#fff;font-size:13px;outline:none}
.fg select option{background:#111827}
.fg textarea{min-height:90px;resize:vertical}

.checkboxes{display:flex;gap:15px;flex-wrap:wrap;margin-bottom:15px}
.checkboxes label{display:flex;align-items:center;gap:5px;font-size:13px;cursor:pointer}
.checkboxes input{width:15px;height:15px;accent-color:#1677ff}

.btn{padding:10px 18px;border-radius:9px;font-weight:600;font-size:13px;display:inline-flex;align-items:center;gap:7px;transition:.2s}
.btn-blue{background:#1677ff;color:#fff}
.btn-blue:hover{background:#0d5fd6}
.btn-green{background:#10b981;color:#fff}
.btn-red{background:rgba(239,68,68,.15);color:#f87171;border:1px solid rgba(239,68,68,.3)}
.btn-sm{padding:6px 10px;font-size:11px;border-radius:7px}

.alert{padding:13px 16px;border-radius:10px;font-size:13px;margin-bottom:18px;word-break:break-word}
.alert-ok{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.25);color:#34d399}
.alert-err{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.25);color:#f87171}

table{width:100%;border-collapse:collapse;font-size:12px}
th{background:rgba(22,119,255,.08);padding:10px;text-align:left;color:#8fc3ff;font-weight:600;white-space:nowrap}
td{padding:10px;border-bottom:1px solid rgba(255,255,255,.08);color:#b4bdca}
.thumb{width:35px;height:50px;object-fit:cover;border-radius:5px}

.tmdb-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:15px}
.tmdb-card{background:rgba(255,255,255,.03);border:1px solid rgba(255,255,255,.08);border-radius:12px;overflow:hidden;transition:.2s}
.tmdb-card:hover{border-color:rgba(22,119,255,.4)}
.tmdb-card img{width:100%;aspect-ratio:2/3;object-fit:cover}
.tmdb-card-info{padding:12px}
.tmdb-card-title{font-size:13px;font-weight:700;margin-bottom:5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.tmdb-card-meta{font-size:11px;color:#8c97aa;margin-bottom:8px}
.tmdb-card-desc{font-size:11px;color:#b4bdca;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;margin-bottom:10px;line-height:1.4}
.tmdb-select-btn{width:100%;padding:8px;border-radius:7px;background:#1677ff;color:#fff;font-size:12px;font-weight:600;text-align:center;display:block}

@media(max-width:700px){
.sidebar{width:60px;padding:12px 8px}
.sidebar h2,.sidebar a span{display:none}
.sidebar a{justify-content:center;padding:10px}
.main{margin-left:60px;padding:15px 10px}
.stats{grid-template-columns:repeat(2,1fr)}
.tmdb-grid{grid-template-columns:repeat(auto-fill,minmax(140px,1fr))}
}
</style>
</head>
<body>
<div class="layout">
<aside class="sidebar">
<h2><i class="fas fa-compact-disc"></i> Admin</h2>
<a href="admin.php"><i class="fas fa-chart-bar"></i><span>Dashboard</span></a>
<a href="admin.php?action=contents"><i class="fas fa-film"></i><span>İçerikler</span></a>
<a href="admin.php?action=add_content"><i class="fas fa-plus-circle"></i><span>İçerik Ekle</span></a>
<a href="admin.php?action=tmdb"><i class="fas fa-database"></i><span>TMDB'den Ekle</span></a>
<a href="admin.php?action=episodes"><i class="fas fa-list"></i><span>Bölümler</span></a>
<a href="admin.php?action=upcoming"><i class="fas fa-calendar"></i><span>Yaklaşanlar</span></a>
<a href="admin.php?action=players"><i class="fas fa-play"></i><span>Playerlar</span></a>
<a href="index.php" target="_blank"><i class="fas fa-external-link"></i><span>Siteyi Gör</span></a>
</aside>

<main class="main">
<div class="top">
<h1>Admin <span>Panel</span></h1>
<a href="admin.php" class="btn btn-blue"><i class="fas fa-sync"></i> Yenile</a>
</div>

<?php if ($message): ?><div class="alert alert-ok">✅ <?php echo $message; ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-err">❌ <?php echo $error; ?></div><?php endif; ?>

<?php if ($action === 'dashboard'): ?>
<div class="stats">
<div class="stat"><i class="fas fa-film"></i><div><h3><?php echo $stats['total_content']; ?></h3><p>İçerik</p></div></div>
<div class="stat"><i class="fas fa-list"></i><div><h3><?php echo $stats['total_episodes']; ?></h3><p>Bölüm</p></div></div>
<div class="stat"><i class="fas fa-calendar"></i><div><h3><?php echo $stats['total_upcoming']; ?></h3><p>Yaklaşan</p></div></div>
<div class="stat"><i class="fas fa-users"></i><div><h3><?php echo $stats['total_users']; ?></h3><p>Kullanıcı</p></div></div>
</div>

<div class="card">
<h3><i class="fas fa-star"></i> Hero Slider İçerikleri (Öne Çıkanlar)</h3>
<p style="font-size:13px;color:#8c97aa;margin-bottom:15px">Ana sayfada dönen slider'da gösterilecek içerikler. Bir içeriği öne çıkarmak için "İçerikler" bölümünden düzenleyip "Öne Çıkan" kutusunu işaretleyin.</p>
<table>
<tr><th>Poster</th><th>Başlık</th><th>Tür</th><th>Durum</th><th>İşlem</th></tr>
<?php foreach ($contents as $c): if ($c['is_featured']): ?>
<tr>
<td><img src="<?php echo e($c['poster_url']); ?>" class="thumb"></td>
<td><?php echo e($c['title']); ?></td>
<td><?php echo $c['type'] === 'movie' ? 'Film' : ($c['type'] === 'series' ? 'Dizi' : 'Anime'); ?></td>
<td><span style="color:#34d399">⭐ Aktif</span></td>
<td><a href="admin.php?action=edit_content&id=<?php echo $c['id']; ?>" class="btn btn-blue btn-sm"><i class="fas fa-edit"></i> Düzenle</a></td>
</tr>
<?php endif; endforeach; ?>
</table>
</div>

<div class="card">
<h3><i class="fas fa-fire"></i> Son Eklenenler</h3>
<table>
<tr><th>Poster</th><th>Başlık</th><th>Tür</th><th>Yıl</th><th>Puan</th><th></th></tr>
<?php foreach (array_slice($contents, 0, 10) as $c): ?>
<tr>
<td><img src="<?php echo e($c['poster_url']); ?>" class="thumb"></td>
<td><?php echo e($c['title']); ?></td>
<td><?php echo $c['type'] === 'movie' ? 'Film' : ($c['type'] === 'series' ? 'Dizi' : 'Anime'); ?></td>
<td><?php echo $c['year']; ?></td>
<td><?php echo $c['rating']; ?></td>
<td><a href="admin.php?action=edit_content&id=<?php echo $c['id']; ?>" class="btn btn-blue btn-sm"><i class="fas fa-edit"></i></a></td>
</tr>
<?php endforeach; ?>
</table>
</div>

<?php elseif ($action === 'tmdb'): ?>
<div class="card">
<h3><i class="fas fa-database"></i> TMDB'den İçerik Ara</h3>
<form method="GET">
<input type="hidden" name="action" value="tmdb">
<div class="grid2">
<div class="fg"><label>Arama</label><input type="text" name="tmdb_search" value="<?php echo e($_GET['tmdb_search'] ?? ''); ?>" placeholder="Dizi/Film adı..." required></div>
<div class="fg"><label>Tür</label><select name="tmdb_type">
<option value="tv" <?php echo ($_GET['tmdb_type'] ?? '') === 'tv' ? 'selected' : ''; ?>>Dizi</option>
<option value="movie" <?php echo ($_GET['tmdb_type'] ?? '') === 'movie' ? 'selected' : ''; ?>>Film</option>
</select></div>
</div>
<button class="btn btn-blue"><i class="fas fa-search"></i> Ara</button>
</form>
</div>

<?php if (isset($tmdb_results) && !empty($tmdb_results)): ?>
<div class="card">
<h3><i class="fas fa-list"></i> Sonuçlar (<?php echo count($tmdb_results); ?>)</h3>
<div class="tmdb-grid">
<?php foreach ($tmdb_results as $item): ?>
<div class="tmdb-card">
<?php if ($item['poster_path']): ?>
<img src="https://wsrv.nl/?url=https://image.tmdb.org/t/p/w300<?php echo $item['poster_path']; ?>" alt="<?php echo e($item['title']); ?>">
<?php else: ?>
<div style="aspect-ratio:2/3;background:#1a2332;display:flex;align-items:center;justify-content:center;color:#8c97aa;font-size:30px"><i class="fas fa-image"></i></div>
<?php endif; ?>
<div class="tmdb-card-info">
<div class="tmdb-card-title"><?php echo e($item['title']); ?></div>
<div class="tmdb-card-meta">⭐ <?php echo $item['vote_average']; ?> | <?php echo $item['first_air_date']; ?></div>
<?php if ($item['overview']): ?>
<div class="tmdb-card-desc"><?php echo e($item['overview']); ?></div>
<?php endif; ?>
<a href="admin.php?action=tmdb&tmdb_select=1&tmdb_id=<?php echo $item['id']; ?>&tmdb_type=<?php echo $item['type']; ?>" class="tmdb-select-btn" onclick="return confirm('Bu içeriği eklemek istediğinizden emin misiniz?')">➕ Ekle</a>
</div>
</div>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>

<?php elseif ($action === 'contents'): ?>
<div class="card">
<h3><i class="fas fa-film"></i> Tüm İçerikler (<?php echo count($contents); ?>)</h3>
<table>
<tr><th>Poster</th><th>Başlık</th><th>Tür</th><th>Yıl</th><th>Puan</th><th>Öne Çıkan</th><th>İşlem</th></tr>
<?php foreach ($contents as $c): ?>
<tr>
<td><img src="<?php echo e($c['poster_url']); ?>" class="thumb"></td>
<td><?php echo e($c['title']); ?></td>
<td><?php echo $c['type'] === 'movie' ? 'Film' : ($c['type'] === 'series' ? 'Dizi' : 'Anime'); ?></td>
<td><?php echo $c['year']; ?></td>
<td><?php echo $c['rating']; ?></td>
<td><?php echo $c['is_featured'] ? '⭐' : '-'; ?></td>
<td style="display:flex;gap:5px">
<a href="admin.php?action=edit_content&id=<?php echo $c['id']; ?>" class="btn btn-blue btn-sm"><i class="fas fa-edit"></i></a>
<form method="POST" onsubmit="return confirm('Silinsin mi?')" style="display:inline">
<input type="hidden" name="post_action" value="delete_content">
<input type="hidden" name="id" value="<?php echo $c['id']; ?>">
<button class="btn btn-red btn-sm"><i class="fas fa-trash"></i></button>
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
</div>

<?php elseif ($action === 'add_content' || $action === 'edit_content'): ?>
<div class="card">
<h3><i class="fas fa-<?php echo $edit_content ? 'edit' : 'plus'; ?>"></i> <?php echo $edit_content ? 'Düzenle: ' . e($edit_content['title']) : 'Yeni İçerik'; ?></h3>
<form method="POST">
<input type="hidden" name="post_action" value="save_content">
<input type="hidden" name="id" value="<?php echo $edit_content ? $edit_content['id'] : 0; ?>">

<div class="grid2">
<div class="fg"><label>Başlık *</label><input type="text" name="title" value="<?php echo $edit_content ? e($edit_content['title']) : ''; ?>" required></div>
<div class="fg"><label>Tür *</label><select name="type">
<option value="movie" <?php echo $edit_content && $edit_content['type'] === 'movie' ? 'selected' : ''; ?>>Film</option>
<option value="series" <?php echo $edit_content && $edit_content['type'] === 'series' ? 'selected' : ''; ?>>Dizi</option>
<option value="anime" <?php echo $edit_content && $edit_content['type'] === 'anime' ? 'selected' : ''; ?>>Anime</option>
</select></div>
<div class="fg"><label>Yıl</label><input type="number" name="year" value="<?php echo $edit_content ? $edit_content['year'] : date('Y'); ?>"></div>
<div class="fg"><label>Süre</label><input type="text" name="duration" value="<?php echo $edit_content ? e($edit_content['duration']) : ''; ?>"></div>
</div>

<div class="fg"><label>Poster URL *</label><input type="text" name="poster_url" value="<?php echo $edit_content ? e($edit_content['poster_url']) : ''; ?>" required></div>
<div class="fg"><label>Backdrop URL (Hero için geniş görsel)</label><input type="text" name="backdrop_url" value="<?php echo $edit_content ? e($edit_content['backdrop_url']) : ''; ?>"></div>
<div class="fg"><label>Embed URL *</label><input type="text" name="embed_url" value="<?php echo $edit_content ? e($edit_content['embed_url']) : ''; ?>" required></div>

<div class="grid2">
<div class="fg"><label>Türler</label><input type="text" name="genres" value="<?php echo $edit_content ? e($edit_content['genres']) : ''; ?>"></div>
<div class="fg"><label>Puan</label><input type="number" step="0.1" min="0" max="10" name="rating" value="<?php echo $edit_content ? $edit_content['rating'] : '8.0'; ?>"></div>
</div>

<div class="fg"><label>Açıklama</label><textarea name="description"><?php echo $edit_content ? e($edit_content['description']) : ''; ?></textarea></div>

<div class="checkboxes">
<label><input type="checkbox" name="is_featured" <?php echo $edit_content && $edit_content['is_featured'] ? 'checked' : ''; ?>> ⭐ Öne Çıkan (Hero Slider'da göster)</label>
<label><input type="checkbox" name="is_popular" <?php echo $edit_content && $edit_content['is_popular'] ? 'checked' : ''; ?>> Popüler</label>
<label><input type="checkbox" name="is_new" <?php echo $edit_content && $edit_content['is_new'] ? 'checked' : ''; ?>> Yeni</label>
</div>

<button class="btn btn-blue">💾 Kaydet</button>
</form>
</div>

<?php elseif ($action === 'episodes'): ?>
<div class="card">
<h3><i class="fas fa-list"></i> Bölümler (<?php echo count($episodes); ?>)</h3>
<table>
<tr><th>Dizi</th><th>Sezon</th><th>Bölüm</th><th></th></tr>
<?php foreach ($episodes as $ep): ?>
<tr>
<td><?php echo e($ep['content_title'] ?? '-'); ?></td>
<td>S<?php echo $ep['season_number']; ?></td>
<td>B<?php echo $ep['episode_number']; ?></td>
<td><form method="POST" onsubmit="return confirm('Silinsin mi?')"><input type="hidden" name="post_action" value="delete_episode"><input type="hidden" name="id" value="<?php echo $ep['id']; ?>"><button class="btn btn-red btn-sm"><i class="fas fa-trash"></i></button></form></td>
</tr>
<?php endforeach; ?>
</table>
</div>

<div class="card">
<h3><i class="fas fa-plus"></i> Bölüm Ekle</h3>
<form method="POST">
<input type="hidden" name="post_action" value="add_episode">
<div class="grid2">
<div class="fg"><label>Dizi *</label><select name="content_id" required><option value="">Seç...</option><?php foreach ($contents as $c): if ($c['type'] !== 'movie'): ?><option value="<?php echo $c['id']; ?>"><?php echo e($c['title']); ?></option><?php endif; endforeach; ?></select></div>
<div class="fg"><label>Sezon</label><input type="number" name="season_number" value="1" min="1"></div>
<div class="fg"><label>Bölüm No *</label><input type="number" name="episode_number" required min="1"></div>
</div>
<div class="fg"><label>Embed URL *</label><input type="text" name="embed_url" required></div>
<button class="btn btn-blue">Ekle</button>
</form>
</div>

<?php elseif ($action === 'upcoming'): ?>
<div class="card">
<h3><i class="fas fa-calendar"></i> Yaklaşanlar (<?php echo count($upcoming); ?>)</h3>
<table>
<tr><th>Başlık</th><th>Tür</th><th>Tarih</th><th></th></tr>
<?php foreach ($upcoming as $u): ?>
<tr>
<td><?php echo e($u['title']); ?></td>
<td><?php echo $u['content_type']; ?></td>
<td><?php echo date('d.m.Y', strtotime($u['release_date'])); ?></td>
<td><form method="POST" onsubmit="return confirm('Silinsin mi?')"><input type="hidden" name="post_action" value="delete_upcoming"><input type="hidden" name="id" value="<?php echo $u['id']; ?>"><button class="btn btn-red btn-sm"><i class="fas fa-trash"></i></button></form></td>
</tr>
<?php endforeach; ?>
</table>
</div>

<div class="card">
<h3><i class="fas fa-plus"></i> Yaklaşan Ekle</h3>
<form method="POST">
<input type="hidden" name="post_action" value="add_upcoming">
<div class="grid2">
<div class="fg"><label>Başlık *</label><input type="text" name="title" required></div>
<div class="fg"><label>Tür</label><select name="content_type"><option value="movie">Film</option><option value="series">Dizi</option><option value="anime">Anime</option></select></div>
<div class="fg"><label>Tarih *</label><input type="date" name="release_date" required></div>
</div>
<button class="btn btn-blue">Ekle</button>
</form>
</div>

<?php elseif ($action === 'players'): ?>
<div class="card">
<h3><i class="fas fa-play"></i> Player Ekle</h3>
<form method="POST">
<input type="hidden" name="post_action" value="add_player">
<div class="grid2">
<div class="fg"><label>İçerik</label><select name="content_id"><option value="0">Seç...</option><?php foreach ($contents as $c): ?><option value="<?php echo $c['id']; ?>"><?php echo e($c['title']); ?></option><?php endforeach; ?></select></div>
<div class="fg"><label>Bölüm</label><select name="episode_id"><option value="0">Seç...</option><?php foreach ($episodes as $ep): ?><option value="<?php echo $ep['id']; ?>"><?php echo e($ep['content_title'] ?? ''); ?> S<?php echo $ep['season_number']; ?>B<?php echo $ep['episode_number']; ?></option><?php endforeach; ?></select></div>
<div class="fg"><label>Player Adı *</label><input type="text" name="name" required></div>
</div>
<div class="fg"><label>Embed URL *</label><input type="text" name="embed_url" required></div>
<button class="btn btn-blue">Ekle</button>
</form>
</div>

<?php endif; ?>
</main>
</div>
</body>
</html>