<?php
require_once 'config.php';
require_once 'tracker.php';

// Fetch settings
$settings = [];
$result = $conn->query("SELECT * FROM settings");
if ($result) {
    while($row = $result->fetch_assoc()){
        $settings[$row['key_name']] = $row['value'];
    }
}

function get_setting($key, $default, $settings) {
    return isset($settings[$key]) && $settings[$key] !== '' ? $settings[$key] : $default;
}

// Hero settings
$hero_tagline = get_setting('hero_tagline', 'BADAN PENDAPATAN DAERAH', $settings);
$hero_title = get_setting('hero_title', 'Melayani dengan Integritas,<br>Membangun Daerah Bersama', $settings);
$hero_desc = get_setting('hero_desc', 'Optimalisasi pendapatan daerah melalui pelayanan publik yang transparan, akuntabel, dan berbasis teknologi.', $settings);
$hero_cta1_text = get_setting('hero_cta1_text', 'Hubungi Kami', $settings);
$hero_cta1_link = get_setting('hero_cta1_link', '#kontak', $settings);
$hero_cta2_text = get_setting('hero_cta2_text', 'Tentang Kami', $settings);
$hero_cta2_link = get_setting('hero_cta2_link', '#tentang', $settings);

$hero_bgs = [];
$bg1 = get_setting('hero_bg1', '', $settings);
if (empty($bg1)) {
    $bg1 = get_setting('hero_bg', 'assets/img/hero-bg.png', $settings); // legacy
}
$hero_bgs[] = $bg1;

$bg2 = get_setting('hero_bg2', '', $settings);
if (!empty($bg2)) $hero_bgs[] = $bg2;

$bg3 = get_setting('hero_bg3', '', $settings);
if (!empty($bg3)) $hero_bgs[] = $bg3;


// About section
$about_title = get_setting('about_title', 'Selamat Datang di Badan Pendapatan Daerah', $settings);
$about_content = get_setting('about_content', 'Badan Pendapatan Daerah (BAPENDA) merupakan unsur pelaksana urusan pemerintahan daerah di bidang pendapatan daerah. BAPENDA bertugas melaksanakan fungsi penunjang urusan pemerintahan bidang keuangan sub pendapatan daerah untuk membantu Kepala Daerah dalam pengelolaan dan optimalisasi pendapatan asli daerah.', $settings);
$about_image = get_setting('about_image', 'assets/img/about.jpg', $settings);
$about_cta1_text = get_setting('about_cta1_text', 'Tugas & Fungsi', $settings);
$about_cta1_link = get_setting('about_cta1_link', '#', $settings);
$about_cta2_text = get_setting('about_cta2_text', 'Struktur Organisasi', $settings);
$about_cta2_link = get_setting('about_cta2_link', '#', $settings);

// Stats
$stat1_number = get_setting('stat1_number', '11', $settings);
$stat1_label = get_setting('stat1_label', 'Jenis Pajak Daerah', $settings);
$stat2_number = get_setting('stat2_number', '6', $settings);
$stat2_label = get_setting('stat2_label', 'Layanan Online', $settings);
$stat3_number = get_setting('stat3_number', '24/7', $settings);
$stat3_label = get_setting('stat3_label', 'Layanan Digital', $settings);

// CTA Section
$cta_title = get_setting('cta_title', 'Butuh Bantuan atau Informasi?', $settings);
$cta_desc  = get_setting('cta_desc', 'Hubungi kami untuk informasi lebih lanjut mengenai pajak daerah, retribusi, dan layanan BAPENDA lainnya.', $settings);
$cta_btn1_text = get_setting('cta_btn1_text', 'Kontak Kami', $settings);
$cta_btn1_link = get_setting('cta_btn1_link', '#kontak', $settings);
$cta_btn2_text = get_setting('cta_btn2_text', 'Laporan Pengaduan', $settings);
$cta_btn2_link = clean_url(get_setting('cta_btn2_link', 'pengaduan.php', $settings));

// Button / CTA icons (uploadable)
$about_cta1_icon = get_setting('about_cta1_icon', '', $settings);
$about_cta2_icon = get_setting('about_cta2_icon', '', $settings);
$cta_btn1_icon   = get_setting('cta_btn1_icon', '', $settings);
$cta_btn2_icon   = get_setting('cta_btn2_icon', '', $settings);

// Social media image icons (uploadable)
$social_icon_imgs = [];
for ($i = 1; $i <= 4; $i++) {
    $social_icon_imgs[$i] = get_setting("social_icon_img_{$i}", '', $settings);
}

// Footer
$footer_address = get_setting('footer_address', 'Jl. Pemuda No.148, Sekayu, Kec. Semarang Tengah, Kota Semarang, Jawa Tengah 50132', $settings);
$footer_phone = get_setting('footer_phone', '0822-2122-1400', $settings);
$footer_email = get_setting('footer_email', 'bapenda@semarangkota.go.id', $settings);
$footer_jam = get_setting('footer_jam', 'Senin - Rabu: 08:00 - 17:00 | Kamis: 08:00 - 16.00 | Jumat: 08:00 - 14:00', $settings);
$footer_desc = get_setting('footer_desc', 'Melayani dengan integritas dan membangun daerah bersama untuk mewujudkan pengelolaan pajak daerah yang transparan dan akuntabel.', $settings);
$footer_social_twitter = get_setting('footer_social_twitter', '#', $settings);
$footer_social_facebook = get_setting('footer_social_facebook', '#', $settings);
$footer_social_youtube = get_setting('footer_social_youtube', '#', $settings);
$footer_social_instagram = get_setting('footer_social_instagram', '#', $settings);
$footer_copyright = get_setting('footer_copyright', 'Badan Pendapatan Daerah Kotawaringin Barat', $settings);

// Header
$logo_text_1 = get_setting('logo_text_1', 'BAPENDA', $settings);
$logo_text_2 = get_setting('logo_text_2', 'Kotawaringin Barat', $settings);
$logo_image = get_setting('logo_image', 'assets/img/logo.png', $settings);

// Alert Banner
$alert_show = get_setting('header_alert_show', '1', $settings); // Default aktif untuk melihat hasilnya
$alert_text = get_setting('header_alert_text', 'Pemberitahuan: Batas akhir pembayaran PBB-P2 adalah tanggal 30 September.', $settings);
$alert_color = get_setting('header_alert_color', '#f59e0b', $settings); // Default amber/kuning

// Bupati & Wakil Bupati
$bupati_show    = get_setting('bupati_show', '1', $settings);
$bupati_foto    = get_setting('bupati_foto', '', $settings);
$bupati_nama    = get_setting('bupati_nama', '', $settings);
$bupati_jabatan = get_setting('bupati_jabatan', 'Bupati Kotawaringin Barat', $settings);
$wakil_foto     = get_setting('wakil_foto', '', $settings);
$wakil_nama     = get_setting('wakil_nama', '', $settings);
$wakil_jabatan  = get_setting('wakil_jabatan', 'Wakil Bupati Kotawaringin Barat', $settings);

function clean_url($url) {
    if (empty($url) || $url === '#') return '#';
    // Let external links remain untouched
    if (strpos($url, 'http') === 0) return $url;
    // Special handling for index.php
    if ($url === 'index.php') return './';
    $url = str_replace('index.php#', './#', $url);
    // Remove .php from end of string or before ? / #
    $url = preg_replace('/\.php(\?|#|$)/', '$1', $url);
    return $url;
}

$default_menus = ['Beranda', 'Profil', 'Layanan', 'Berita', 'PPID', 'Dokumen', 'Galeri', 'FAQ', 'Kontak'];
$default_links = ['index.php', '#', 'index.php#layanan', 'index.php#berita', '#', 'dokumen.php', 'gallery.php', '#', 'index.php#kontak'];
$menus = [];
for ($i = 1; $i <= 9; $i++) {
    $m_text = get_setting("menu_{$i}_text", $default_menus[$i-1] ?? '', $settings);
    $m_link = clean_url(get_setting("menu_{$i}_link", $default_links[$i-1] ?? '#', $settings));
    $menus[] = ['text' => $m_text, 'link' => $m_link];
}
// Dashboard Pajak – always appended as extra menu item
$menus[] = ['text' => 'Dashboard Pajak', 'link' => 'pajak_dashboard', 'is_pajak' => true];

$default_profil_subs = ['Tugas dan Fungsi', 'Visi dan Misi', 'Struktur Organisasi', 'Data Pegawai', ''];
$profil_subs = [];
for ($i = 1; $i <= 5; $i++) {
    $sub_text = get_setting("profil_sub_{$i}_text", $default_profil_subs[$i-1] ?? '', $settings);
    $sub_link = clean_url(get_setting("profil_sub_{$i}_link", '#', $settings));
    if (!empty($sub_text)) {
        $profil_subs[] = ['text' => $sub_text, 'link' => $sub_link];
    }
}

// Fetch services
$services = [];
$res_services = $conn->query("SELECT * FROM services ORDER BY order_num ASC LIMIT 6");
if ($res_services) {
    while($row = $res_services->fetch_assoc()) {
        $row['url'] = clean_url($row['url']);
        $services[] = $row;
    }
}

// Fetch links
$related_links = [];
$res_links = $conn->query("SELECT * FROM links ORDER BY order_num ASC");
if ($res_links) {
    while($row = $res_links->fetch_assoc()) {
        $row['url'] = clean_url($row['url']);
        $related_links[] = $row;
    }
}

// Fetch news
$news_items = [];
$res_news = $conn->query("SELECT * FROM news ORDER BY created_at DESC LIMIT 3");
if ($res_news) {
    while($row = $res_news->fetch_assoc()) {
        $news_items[] = $row;
    }
}

// Fetch agenda (upcoming)
$agenda_items = [];
$res_agenda = $conn->query("SELECT * FROM agenda WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 4");
if ($res_agenda) {
    while($row = $res_agenda->fetch_assoc()) {
        $agenda_items[] = $row;
    }
}

// Theme settings
$thm_primary = get_setting('theme_primary_color', '#0ea5e9', $settings);
$thm_font = get_setting('theme_font_family', 'Poppins', $settings);
$thm_radius_lg = get_setting('theme_border_radius', '24', $settings);
$thm_radius_md = intval($thm_radius_lg) / 2;
$thm_primary_glow = $thm_primary . '4D';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <?php
    $base_dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $base_href = $base_dir . '/';
    ?>
    <base href="<?php echo htmlspecialchars($base_href); ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="theme-color" content="#0ea5e9">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="mobile-web-app-capable" content="yes">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - Bapenda Kobar' : 'Bapenda Kotawaringin Barat'; ?></title>
    <meta name="description" content="Portal Layanan Badan Pendapatan Daerah Kabupaten Kotawaringin Barat">
    <?php $site_favicon = get_setting('favicon_image', '', $settings); if (!empty($site_favicon)): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($site_favicon); ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($thm_font); ?>:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo filemtime('assets/css/style.css'); ?>">
    <style>
        :root {
            --primary: <?php echo htmlspecialchars($thm_primary); ?> !important;
            --secondary: <?php echo htmlspecialchars($thm_primary); ?> !important;
            --shadow-glow: 0 10px 25px -5px <?php echo htmlspecialchars($thm_primary_glow); ?> !important;
            --radius-md: <?php echo $thm_radius_md; ?>px !important;
            --radius-lg: <?php echo htmlspecialchars($thm_radius_lg); ?>px !important;
            <?php if ($thm_radius_lg == '4'): ?>
            --radius-pill: 8px !important;
            <?php endif; ?>
        }
        body { font-family: '<?php echo htmlspecialchars($thm_font); ?>', sans-serif !important; }
    </style>
</head>
<body>
    <!-- Alert Banner -->
    <?php if($alert_show == '1' && !empty($alert_text)): ?>
    <div style="background-color: <?php echo htmlspecialchars($alert_color); ?>; color: #fff; text-align: center; padding: 10px 15px; font-size: 14px; position: relative; z-index: 1001;">
        <div class="container" style="display: flex; justify-content: space-between; align-items: center;">
            <div style="flex-grow: 1;">
                <i class="fas fa-bullhorn" style="margin-right: 8px;"></i>
                <?php echo htmlspecialchars($alert_text); ?>
            </div>
            <button onclick="this.parentElement.parentElement.style.display='none'" style="background:none;border:none;color:#fff;cursor:pointer;opacity:0.8;font-size:16px;" aria-label="Tutup Peringatan">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <header class="header">
        <div class="header-top">
            <div class="container">
                <div>
                    <i class="fas fa-clock" style="margin-right: 8px;"></i>
                    <?php echo htmlspecialchars($footer_jam); ?>
                </div>
                <div>
                    <a href="mailto:<?php echo htmlspecialchars($footer_email); ?>">
                        <i class="fas fa-envelope" style="margin-right: 5px;"></i>
                        <?php echo htmlspecialchars($footer_email); ?>
                    </a>
                    <a href="tel:<?php echo htmlspecialchars(str_replace('-', '', $footer_phone)); ?>">
                        <i class="fas fa-phone" style="margin-right: 5px;"></i>
                        <?php echo htmlspecialchars($footer_phone); ?>
                    </a>
                </div>
            </div>
        </div>
        <div class="header-main">
            <div class="container">
                <a href="#" class="logo-box">
                    <img src="<?php echo htmlspecialchars($logo_image); ?>" alt="Logo" class="logo">
                    <div class="logo-text">
                        <strong><?php echo htmlspecialchars($logo_text_1); ?></strong>
                        <span><?php echo htmlspecialchars($logo_text_2); ?></span>
                    </div>
                </a>
                
                <button class="mobile-toggle" id="mobile-toggle" aria-label="Buka Menu" aria-expanded="false" aria-controls="nav-menu">
                    <i class="fas fa-bars"></i>
                </button>

                <nav class="navbar" role="navigation" aria-label="Menu Utama">
                    <ul class="nav-menu" id="nav-menu" role="menubar">
                        <button class="nav-close-btn" id="nav-close-btn" aria-label="Tutup Menu">
                            <i class="fas fa-times"></i>
                        </button>
                        <?php foreach($menus as $m): ?>
                            <?php if(!empty($m['text'])): ?>
                                <li class="nav-item">
                                    <?php if(!empty($m['is_pajak'])): ?>
                                    <a href="<?php echo htmlspecialchars($m['link']); ?>" class="nav-link nav-link-pajak">
                                        <i class="fas fa-chart-bar" style="margin-right:5px;font-size:13px;"></i><?php echo htmlspecialchars($m['text']); ?>
                                    </a>
                                    <?php else: ?>
                                    <a href="<?php echo htmlspecialchars($m['link']); ?>" class="nav-link"><?php echo htmlspecialchars($m['text']); ?></a>
                                    <?php if(strtolower($m['text']) == 'profil'): ?>
                                    <?php if(!empty($profil_subs)): ?>
                                    <div class="nav-dropdown">
                                        <?php foreach($profil_subs as $psub): ?>
                                        <a href="<?php echo htmlspecialchars($psub['link']); ?>"><?php echo htmlspecialchars($psub['text']); ?></a>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php elseif(strtolower($m['text']) == 'layanan'): ?>
                                    <div class="nav-dropdown">
                                        <?php foreach($services as $s): ?>
                                        <a href="<?php echo htmlspecialchars($s['url']); ?>" target="_blank"><?php echo htmlspecialchars($s['title']); ?></a>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                </li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                    <div class="social-icons">
                        <?php if(!empty($footer_social_twitter) && $footer_social_twitter !== '#'): ?>
                        <a href="<?php echo htmlspecialchars($footer_social_twitter); ?>" aria-label="Twitter"><i class="fab fa-x-twitter"></i></a>
                        <?php endif; ?>
                        <?php if(!empty($footer_social_facebook) && $footer_social_facebook !== '#'): ?>
                        <a href="<?php echo htmlspecialchars($footer_social_facebook); ?>" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <?php endif; ?>
                        <?php if(!empty($footer_social_youtube) && $footer_social_youtube !== '#'): ?>
                        <a href="<?php echo htmlspecialchars($footer_social_youtube); ?>" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                        <?php endif; ?>
                        <?php if(!empty($footer_social_instagram) && $footer_social_instagram !== '#'): ?>
                        <a href="<?php echo htmlspecialchars($footer_social_instagram); ?>" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <?php endif; ?>
                        
                        <!-- Search Icon -->
                        <div class="search-btn-nav" style="display:flex; align-items:center; justify-content:center; width:35px; height:35px; margin-left:15px; background:rgba(14,165,233,0.1); border-radius:50%; cursor:pointer; transition:0.3s;" onclick="document.getElementById('search-overlay').style.display='flex'">
                            <i class="fas fa-search" style="color:var(--primary); font-size:14px;"></i>
                        </div>
                    </div>
                </nav>
            </div>
        </div>
    </header>

    <!-- Search Overlay -->
    <div id="search-overlay" style="display:none; position:fixed; inset:0; background:rgba(255,255,255,0.98); z-index:99999; align-items:flex-start; justify-content:center; padding-top:15vh; backdrop-filter:blur(5px);">
        <div style="width:100%; max-width:600px; padding:20px; text-align:center; position:relative;">
            <button onclick="document.getElementById('search-overlay').style.display='none'" style="position:absolute; top:-60px; right:0; background:none; border:none; font-size:28px; color:#64748b; cursor:pointer; transition:0.3s;"><i class="fas fa-times"></i></button>
            <h2 style="color:var(--text-dark); margin-bottom:20px; font-weight:800; font-size:28px;">Apa yang ingin Anda cari?</h2>
            <form action="search.php" method="GET" style="position:relative; width:100%;">
                <input type="text" name="q" placeholder="Ketik kata kunci pencarian..." style="width:100%; padding:20px 30px; font-size:18px; border:2px solid #e2e8f0; border-radius:50px; outline:none; box-shadow:0 15px 35px rgba(0,0,0,0.05); transition:0.3s;" required>
                <button type="submit" style="position:absolute; right:12px; top:12px; bottom:12px; background:var(--primary); color:#fff; border:none; padding:0 30px; border-radius:40px; cursor:pointer; font-size:16px; font-weight:600; transition:0.3s;"><i class="fas fa-search" style="margin-right:8px;"></i> Cari</button>
            </form>
            <p style="margin-top:20px; font-size:14px; color:#94a3b8;">Cari berita, pengumuman, peraturan daerah, formulir PBB, dll.</p>
        </div>
    </div>

    <!-- Mobile Nav Overlay -->
    <div class="nav-overlay" id="nav-overlay"></div>
