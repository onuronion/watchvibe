<?php
require_once 'config.php';
$user = current_user();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Watchvibe Kullanım Şartları - Platform kullanım koşulları, haklar ve sorumluluklar.">
<meta property="og:title" content="Kullanım Şartları - Watchvibe">
<meta property="og:site_name" content="Watchvibe">
<meta name="robots" content="index, follow">
<title>Kullanım Şartları - Watchvibe</title>
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
.loading-spinner{width:50px;height:50px;border:3px solid rgba(22,119,255,.2);border-top-color:var(--blue);border-radius:50%;animation:spin 1s linear infinite}
.loading-text{margin-top:20px;color:var(--muted);font-size:14px}
@keyframes spin{to{transform:rotate(360deg)}}

.topbar{position:fixed;top:0;left:0;width:100%;height:64px;display:flex;align-items:center;justify-content:space-between;padding:0 5%;z-index:100;background:rgba(5,7,13,.96);backdrop-filter:blur(18px);border-bottom:1px solid var(--border)}
.logo-img{height:35px;width:auto}
.nav-actions{display:flex;align-items:center;gap:10px}
.icon-btn{width:40px;height:40px;border-radius:12px;background:rgba(255,255,255,.06);border:1px solid var(--border);display:grid;place-items:center;font-size:15px}
.icon-btn:hover{background:rgba(22,119,255,.2)}

.container{max-width:900px;margin:0 auto;padding:90px 5% 40px}
.page-header{text-align:center;margin-bottom:40px}
.page-icon{font-size:48px;color:#4b9cff;margin-bottom:15px}
.page-title{font-size:32px;font-weight:800;margin-bottom:10px;letter-spacing:-1px}
.page-subtitle{color:var(--muted);font-size:15px;line-height:1.6}

.content-section{background:var(--card);border:1px solid var(--border);border-radius:16px;padding:30px;margin-bottom:20px}
.section-title{font-size:18px;font-weight:700;margin-bottom:15px;display:flex;align-items:center;gap:10px}
.section-title i{color:#4b9cff}
.section-text{color:#b4bdca;line-height:1.7;font-size:14px;margin-bottom:15px}
.section-list{list-style:none;margin-bottom:15px}
.section-list li{padding:10px 0;border-bottom:1px solid var(--border);color:#b4bdca;font-size:14px;line-height:1.6;display:flex;align-items:flex-start;gap:10px}
.section-list li:last-child{border-bottom:none}
.section-list li i{color:#4b9cff;margin-top:3px;font-size:12px}
.section-list li strong{color:#c2cad7}

.highlight-box{background:rgba(22,119,255,.08);border:1px solid rgba(22,119,255,.2);border-radius:12px;padding:20px;margin:20px 0}
.highlight-box h4{font-size:15px;font-weight:700;margin-bottom:10px;color:#8fc3ff}
.highlight-box p{font-size:13px;color:#b4bdca;line-height:1.6}

.warning-box{background:rgba(248,113,113,.08);border:1px solid rgba(248,113,113,.2);border-radius:12px;padding:20px;margin:20px 0}
.warning-box h4{font-size:15px;font-weight:700;margin-bottom:10px;color:#fca5a5}
.warning-box p{font-size:13px;color:#fca5a5;line-height:1.6}

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
}
@media(max-width:520px){
.container{padding:80px 16px 30px}
.content-section{padding:20px}
.page-title{font-size:26px}
.section-text{font-size:13px}
.section-list li{font-size:13px}
}
</style>
</head>
<body>
<!-- Loading Screen -->
<div class="loading-screen" id="loadingScreen">
    <div class="loading-spinner"></div>
    <div class="loading-text">Yükleniyor...</div>
</div>

<header class="topbar">
<a href="index.php"><img src="logo.png" alt="Watchvibe" class="logo-img"></a>
<div class="nav-actions">
<a href="browse.php" class="icon-btn"><i class="fas fa-compass"></i></a>
<a href="index.php" class="icon-btn"><i class="fas fa-house"></i></a>
</div>
</header>

<div class="container">
<div class="page-header">
<div class="page-icon"><i class="fas fa-file-contract"></i></div>
<h1 class="page-title">Kullanım Şartları</h1>
<p class="page-subtitle">Son güncelleme: <?php echo date('d.m.Y'); ?></p>
</div>

<div class="content-section">
<h2 class="section-title"><i class="fas fa-check-circle"></i> Kabul Edilen Şartlar</h2>
<p class="section-text">
Watchvibe platformunu kullanarak aşağıdaki kullanım şartlarını kabul etmiş olursunuz. Bu şartları kabul etmiyorsanız, lütfen platformu kullanmayınız.
</p>
<p class="section-text">
Watchvibe, bu kullanım şartlarını önceden bildirimde bulunmaksızın değiştirme hakkını saklı tutar. Değişiklikler sitemizde yayınlandığı andan itibaren geçerli olacaktır.
</p>
</div>

<div class="content-section">
<h2 class="section-title"><i class="fas fa-user"></i> Hesap Kullanımı</h2>
<ul class="section-list">
<li><i class="fas fa-check-circle"></i> <div><strong>Kayıt Bilgileri:</strong> Hesap oluştururken doğru, güncel ve eksiksiz bilgi vermelisiniz.</div></li>
<li><i class="fas fa-check-circle"></i> <div><strong>Hesap Güvenliği:</strong> Hesabınızın güvenliğinden siz sorumlusunuz. Şifrenizi kimseyle paylaşmayınız.</div></li>
<li><i class="fas fa-check-circle"></i> <div><strong>Yaş Sınırı:</strong> 18 yaşından küçük kullanıcılar, ebeveyn gözetiminde platformu kullanabilir.</div></li>
<li><i class="fas fa-check-circle"></i> <div><strong>Hesap Kapatma:</strong> Watchvibe, kullanım şartlarını ihlal eden hesapları önceden bildirimde bulunmaksızın kapatma hakkına sahiptir.</div></li>
</ul>
</div>

<div class="content-section">
<h2 class="section-title"><i class="fas fa-play-circle"></i> İçerik Kullanımı</h2>
<ul class="section-list">
<li><i class="fas fa-check-circle"></i> <div><strong>Kişisel Kullanım:</strong> Platformdaki içerikler yalnızca kişisel ve ticari olmayan kullanım içindir.</div></li>
<li><i class="fas fa-check-circle"></i> <div><strong>Telif Hakları:</strong> Tüm içeriklerin telif hakları ilgili sahiplerine aittir.</div></li>
<li><i class="fas fa-check-circle"></i> <div><strong>İçerik Değişikliği:</strong> Watchvibe, içerikleri önceden bildirimde bulunmaksızın kaldırma veya değiştirme hakkına sahiptir.</div></li>
<li><i class="fas fa-check-circle"></i> <div><strong>Üçüncü Taraf İçerik:</strong> Platformdaki içerikler üçüncü taraf kaynaklardan sağlanmaktadır.</div></li>
</ul>
</div>

<div class="content-section">
<h2 class="section-title"><i class="fas fa-ban"></i> Yasaklanan Aktiviteler</h2>
<ul class="section-list">
<li><i class="fas fa-times-circle"></i> <div>Platformu yasa dışı amaçlarla kullanmak</div></li>
<li><i class="fas fa-times-circle"></i> <div>Telif hakkı ihlali yapmak veya teşvik etmek</div></li>
<li><i class="fas fa-times-circle"></i> <div>Platformun güvenliğini tehlikeye atmak</div></li>
<li><i class="fas fa-times-circle"></i> <div>Diğer kullanıcıları taciz etmek veya rahatsız etmek</div></li>
<li><i class="fas fa-times-circle"></i> <div>Platformu otomatik araçlarla (bot, scraper vb.) kullanmak</div></li>
<li><i class="fas fa-times-circle"></i> <div>İçerikleri izinsiz olarak indirmek veya dağıtmak</div></li>
</ul>
<div class="warning-box">
<h4><i class="fas fa-exclamation-triangle"></i> Önemli Uyarı</h4>
<p>Yasaklanan aktivitelerin tespiti halinde hesabınız kalıcı olarak kapatılabilir ve yasal işlem başlatılabilir.</p>
</div>
</div>

<div class="content-section">
<h2 class="section-title"><i class="fas fa-shield-alt"></i> Sorumluluk Reddi</h2>
<p class="section-text">
Watchvibe, platformdaki içeriklerin doğruluğu, güvenilirliği veya kullanılabilirliği konusunda herhangi bir garanti vermemektedir. Platform "olduğu gibi" sunulmaktadır.
</p>
<p class="section-text">
Watchvibe, platformun kullanımından doğabilecek doğrudan veya dolaylı hiçbir zarardan sorumlu tutulamaz. Kullanıcılar platformu kendi sorumlulukları altında kullanmaktadır.
</p>
</div>

<div class="content-section">
<h2 class="section-title"><i class="fas fa-balance-scale"></i> Fikri Mülkiyet Hakları</h2>
<p class="section-text">
Watchvibe logosu, tasarımı ve platformun tüm görsel unsurları Watchvibe'e aittir. Bu unsurlar izinsiz kullanılamaz, kopyalanamaz veya dağıtılamaz.
</p>
<p class="section-text">
Platformdaki tüm film, dizi ve anime içeriklerinin telif hakları ilgili yapımcı ve dağıtıcı şirketlere aittir.
</p>
</div>

<div class="content-section">
<h2 class="section-title"><i class="fas fa-gavel"></i> Yürürlükteki Kanun</h2>
<p class="section-text">
Bu kullanım şartları, Türkiye Cumhuriyeti kanunlarına tabidir. Şartlarla ilgili herhangi bir uyuşmazlık durumunda Türkiye mahkemeleri yetkilidir.
</p>
</div>

<div class="content-section">
<h2 class="section-title"><i class="fas fa-envelope"></i> İletişim</h2>
<p class="section-text">
Kullanım şartlarıyla ilgili sorularınız için aşağıdaki adresten bizimle iletişime geçebilirsiniz:
</p>
<a href="mailto:info@watchvibe.com" style="display:inline-flex;align-items:center;gap:10px;padding:12px 20px;background:rgba(22,119,255,.1);border:1px solid rgba(22,119,255,.3);border-radius:10px;color:#8fc3ff;font-size:14px;font-weight:600;margin-top:10px;transition:.2s">
<i class="fas fa-envelope"></i> info@watchvibe.com
</a>
</div>
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

<!-- Bottom nav -->
<nav class="bottom-nav">
<a class="nav-item" href="index.php"><i class="fas fa-house"></i><span>Ana Sayfa</span></a>
<a class="nav-item" href="browse.php"><i class="fas fa-compass"></i><span>Keşfet</span></a>
<a class="nav-item" href="<?php echo $user ? 'login.php?tab=list' : 'login.php'; ?>"><i class="fas fa-bookmark"></i><span>Listem</span></a>
<a class="nav-item" href="<?php echo $user ? 'login.php?tab=settings' : 'login.php'; ?>"><i class="fas fa-gear"></i><span>Ayarlar</span></a>
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
</script>
</body>
</html>