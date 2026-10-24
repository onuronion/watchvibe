
<?php
// Watchvibe - Database Configuration & Core Functions
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);

define('DB_HOST', 'sql311.infinityfree.com');
define('DB_USER', 'if0_39551176');
define('DB_PASS', '0C5HdKYBZkAtpS');
define('DB_NAME', 'if0_39551176_wvibe');
define('SITE_NAME', 'Watchvibe');
define('SITE_URL', '');

function db() {
    static $conn = null;
    if ($conn === null) {
        $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die('Veritabanı bağlantısı kurulamadı. Lütfen setup.php dosyasını çalıştırın.');
        }
        $conn->set_charset('utf8mb4');
    }
    return $conn;
}

session_start();

function current_user() {
    if (empty($_SESSION['user_id'])) return null;
    return [
        'id'       => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? 'Kullanıcı',
        'email'    => $_SESSION['email'] ?? '',
    ];
}

function require_login() {
    if (!current_user()) {
        header('Location: login.php');
        exit;
    }
}

function e($s) {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function slug($text) {
    $text = trim($text);
    $text = str_replace(
        ['ç','Ç','ğ','Ğ','ı','İ','ö','Ö','ş','Ş','ü','Ü'],
        ['c','c','g','g','i','i','o','o','s','s','u','u'],
        $text
    );
    $text = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $text));
    $text = trim($text, '-');
    return $text ?: 'icerik';
}

function content_url($id, $title, $episode = null) {
    $s = slug($title);
    if ($episode !== null) {
        return "player.php?id=" . (int)$id . "&bolum=" . (int)$episode . "&" . $s;
    }
    return "player.php?id=" . (int)$id . "&" . $s;
}

function fetch_content($id) {
    $id = (int)$id;
    $r = db()->query("SELECT * FROM content WHERE id = $id");
    if ($r && $r->num_rows > 0) return $r->fetch_assoc();
    return null;
}

function fetch_episodes($content_id) {
    $content_id = (int)$content_id;
    $r = db()->query("SELECT * FROM episodes WHERE content_id = $content_id ORDER BY episode_number ASC");
    $eps = [];
    if ($r) while ($row = $r->fetch_assoc()) $eps[] = $row;
    return $eps;
}

function get_continue_watching($user_id) {
    $user_id = (int)$user_id;
    $sql = "SELECT w.*, c.title, c.poster_url, c.type, e.episode_number, e.title AS ep_title
            FROM watch_history w
            JOIN content c ON w.content_id = c.id
            LEFT JOIN episodes e ON w.episode_id = e.id
            WHERE w.user_id = $user_id
            ORDER BY w.updated_at DESC LIMIT 12";
    $r = db()->query($sql);
    $items = [];
    if ($r) while ($row = $r->fetch_assoc()) $items[] = $row;
    return $items;
}

function get_user_list($user_id, $list_type = 'watchlist') {
    $user_id = (int)$user_id;
    $list_type = db()->real_escape_string($list_type);
    $sql = "SELECT c.* FROM user_lists ul JOIN content c ON ul.content_id = c.id
            WHERE ul.user_id = $user_id AND ul.list_type = '$list_type'
            ORDER BY ul.created_at DESC";
    $r = db()->query($sql);
    $items = [];
    if ($r) while ($row = $r->fetch_assoc()) $items[] = $row;
    return $items;
}

function content_card($c) {
    $url = content_url($c['id'], $c['title']);
    $badge = '';
    if ($c['type'] === 'series') $badge = '<span class="card-badge">Dizi</span>';
    elseif ($c['type'] === 'anime') $badge = '<span class="card-badge">Anime</span>';
    return '<a class="card" href="' . $url . '">
        <img src="' . e($c['poster_url']) . '" alt="' . e($c['title']) . '" loading="lazy">
        <div class="card-gradient"></div>
        ' . $badge . '
        <div class="card-play"><div class="play-circle"><i class="fas fa-play"></i></div></div>
        <div class="card-info">
            <div class="card-title">' . e($c['title']) . '</div>
            <div class="card-meta"><span>' . e($c['year']) . '</span><span class="rating"><i class="fas fa-star"></i> ' . e($c['rating']) . '</span></div>
        </div>
    </a>';
}
