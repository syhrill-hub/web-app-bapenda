<?php
require_once 'config.php';

// Fetch org structure image from settings
$struktur_img = '';
$stmt = $conn->prepare("SELECT value FROM settings WHERE key_name = 'struktur_org_image'");
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) $struktur_img = $row['value'];
}

// Fetch site settings & navigation (uses includes/header.php)
$page_title = "Struktur Organisasi";
require_once 'includes/header.php';
?>

<style>
/* Page Banner */
.page-banner {
    background: linear-gradient(135deg, var(--primary) 0%, #075985 100%);
    padding: 50px 0 50px;
    margin-top: 110px;
    text-align: center;
    color: #fff;
    position: relative;
    overflow: hidden;
}
.page-banner::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -20%;
    width: 60%;
    height: 200%;
    background: rgba(255,255,255,0.04);
    border-radius: 50%;
}
.page-banner h1 {
    font-size: 2.4rem;
    font-weight: 800;
    margin: 0 0 10px 0;
    letter-spacing: -0.5px;
}
.page-banner p {
    font-size: 1rem;
    opacity: 0.85;
    margin: 0;
}
.breadcrumb-nav {
    font-size: 13px;
    opacity: 0.75;
    margin-bottom: 15px;
}
.breadcrumb-nav a { color: #fff; text-decoration: none; }
.breadcrumb-nav a:hover { text-decoration: underline; }

/* Diagram Section */
.diagram-section {
    padding: 60px 0 80px;
    background: #f8fafc;
    min-height: 60vh;
}
.diagram-wrapper {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 8px 40px rgba(0,0,0,0.06);
    padding: 30px;
    text-align: center;
    overflow: hidden;
}
.diagram-img {
    max-width: 100%;
    height: auto;
    border-radius: 12px;
    cursor: zoom-in;
    transition: transform 0.3s ease;
    display: block;
    margin: 0 auto;
}
.diagram-img:hover {
    transform: scale(1.005);
}
.diagram-caption {
    margin-top: 20px;
    font-size: 13px;
    color: #94a3b8;
}
.no-diagram {
    padding: 80px 20px;
    text-align: center;
    color: #94a3b8;
}
.no-diagram i {
    font-size: 4rem;
    color: #cbd5e1;
    display: block;
    margin-bottom: 20px;
}

/* Lightbox */
.lightbox-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.92);
    z-index: 9999;
    cursor: zoom-out;
    align-items: center;
    justify-content: center;
}
.lightbox-overlay.active {
    display: flex;
}
.lightbox-overlay img {
    max-width: 95vw;
    max-height: 92vh;
    border-radius: 8px;
    box-shadow: 0 25px 80px rgba(0,0,0,0.5);
    object-fit: contain;
}
.lightbox-close {
    position: fixed;
    top: 20px;
    right: 25px;
    color: #fff;
    font-size: 2rem;
    cursor: pointer;
    background: rgba(255,255,255,0.1);
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: 0.2s;
    border: none;
}
.lightbox-close:hover {
    background: rgba(255,255,255,0.25);
}
.zoom-hint {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    color: var(--primary);
    border-radius: 50px;
    padding: 8px 18px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 20px;
}

@media (max-width: 768px) {
    .page-banner h1 { font-size: 1.8rem; }
    .page-banner { margin-top: 90px; }
    .diagram-wrapper { padding: 15px; }
}
</style>

<!-- Page Banner -->
<div class="page-banner">
    <div class="container">
        <div class="breadcrumb-nav">
            <a href="index.php">Beranda</a> &rsaquo; Profil &rsaquo; Struktur Organisasi
        </div>
        <h1><i class="fas fa-sitemap" style="margin-right:12px; opacity:0.85;"></i>Struktur Organisasi</h1>
        <p>Badan Pendapatan Daerah Kabupaten Kotawaringin Barat</p>
    </div>
</div>

<!-- Diagram Section -->
<section class="diagram-section">
    <div class="container">
        <div class="diagram-wrapper">
            <?php if (!empty($struktur_img)): ?>
            <div class="zoom-hint">
                <i class="fas fa-search-plus"></i> Klik gambar untuk memperbesar
            </div>
            <img src="<?php echo htmlspecialchars(bapenda_safe_url($struktur_img, ''), ENT_QUOTES, 'UTF-8'); ?>"
                 alt="Struktur Organisasi Bapenda Kotawaringin Barat"
                 class="diagram-img"
                 id="diagram-img"
                 onclick="openLightbox(this.src)">
            <div class="diagram-caption">
                <i class="fas fa-info-circle" style="margin-right:5px;"></i>
                Diagram Struktur Organisasi Badan Pendapatan Daerah Kabupaten Kotawaringin Barat
            </div>
            <?php else: ?>
            <div class="no-diagram">
                <i class="fas fa-sitemap"></i>
                <h3 style="font-size:1.4rem; color:#64748b; margin-bottom:10px;">Diagram Belum Tersedia</h3>
                <p style="max-width:400px; margin:0 auto; line-height:1.7;">
                    Diagram struktur organisasi sedang dalam proses pembaruan.<br>
                    Silakan kunjungi kembali halaman ini dalam beberapa waktu.
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Lightbox -->
<div class="lightbox-overlay" id="lightbox" onclick="closeLightbox()">
    <button class="lightbox-close" onclick="closeLightbox()" title="Tutup">
        <i class="fas fa-times"></i>
    </button>
    <img id="lightbox-img" src="" alt="Struktur Organisasi" onclick="event.stopPropagation()">
</div>

<script>
function openLightbox(src) {
    document.getElementById('lightbox-img').src = src;
    document.getElementById('lightbox').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('lightbox').classList.remove('active');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeLightbox();
});
</script>

<?php require_once 'includes/footer.php'; ?>
