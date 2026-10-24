<?php
require_once 'config.php';

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo '<div style="color:#8c97aa;font-size:13px">En az 2 karakter yaz.</div>';
    exit;
}

$like = '%' . $q . '%';
$stmt = db()->prepare("SELECT * FROM content WHERE title LIKE ? OR genres LIKE ? ORDER BY rating DESC LIMIT 20");
$stmt->bind_param("ss", $like, $like);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) $items[] = $row;
$stmt->close();

if (empty($items)) {
    echo '<div style="color:#8c97aa;font-size:13px;padding:20px 0">Sonuç bulunamadı: "' . e($q) . '"</div>';
    exit;
}

$type_labels = ['movie' => 'Film', 'series' => 'Dizi', 'anime' => 'Anime'];
$html = '';
foreach ($items as $c) {
    $url = content_url($c['id'], $c['title']);
    $html .= '<a href="' . $url . '" style="display:flex;gap:12px;padding:10px 5px;border-bottom:1px solid rgba(255,255,255,.06);cursor:pointer;transition:.15s" onmouseover="this.style.background=\'rgba(22,119,255,.05)\'" onmouseout="this.style.background=\'transparent\'">';
    $html .= '<img src="' . e($c['poster_url']) . '" style="width:40px;height:56px;border-radius:8px;object-fit:cover;flex-shrink:0">';
    $html .= '<div><div style="font-weight:600;font-size:14px;color:#eaf2ff">' . e($c['title']) . '</div>';
    $html .= '<div style="font-size:12px;color:#8c97aa;margin-top:2px">' . e($type_labels[$c['type']] ?? 'Film') . ' • ' . e($c['year']) . ' • <span style="color:#ffd24d"><i class="fas fa-star"></i> ' . e($c['rating']) . '</span></div></div>';
    $html .= '</a>';
}
echo $html;
