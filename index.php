<?php require_once 'includes/header.php'; ?>
    <!-- Hero Section -->
    <section class="hero-section" style="position:relative;">
        <div class="hero-slider">
            <?php foreach ($hero_bgs as $index => $bg): ?>
            <div class="slide <?php echo $index === 0 ? 'active' : ''; ?>">
                <div class="slide-bg" style="background-image: url('<?php echo htmlspecialchars($bg); ?>');"></div>
                <div class="container">
                    <div class="slide-content">
                        <p class="tagline"><?php echo htmlspecialchars($hero_tagline); ?></p>
                        <h1><?php echo $hero_title; ?></h1>
                        <p><?php echo htmlspecialchars($hero_desc); ?></p>
                    </div>
                </div>
            </div>
            <?php
endforeach; ?>
        </div>

        <?php if (count($hero_bgs) > 1): ?>
        <button class="slider-btn prev-btn"><i class="fas fa-chevron-left"></i></button>
        <button class="slider-btn next-btn"><i class="fas fa-chevron-right"></i></button>

        <div class="slider-controls">
            <?php foreach ($hero_bgs as $index => $bg): ?>
            <div class="slider-dot <?php echo $index === 0 ? 'active' : ''; ?>"></div>
            <?php
    endforeach; ?>
        </div>
        <?php
endif; ?>

        <?php if ($bupati_show == '1' && (!empty($bupati_foto) || !empty($bupati_nama) || !empty($wakil_foto) || !empty($wakil_nama))): ?>
        <!-- Bupati photo cards overlay -->
        <style>
        @keyframes floatPejabat {
            0% { transform: translateY(-50%) translateY(0px); }
            50% { transform: translateY(-50%) translateY(-12px); }
            100% { transform: translateY(-50%) translateY(0px); }
        }
        .pejabat-card {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 350px;
            animation: floatPejabat 6s ease-in-out infinite;
        }
        .pejabat-card.kiri { left: 4%; }
        .pejabat-card.kanan { right: 4%; animation-delay: 1.5s; }
        
        .pejabat-foto-wrap {
            position: relative;
            width: 290px;
            height: 380px;
            border-radius: 20px 20px 0 0;
            overflow: hidden;
            border: 4px solid rgba(255,255,255,0.7);
            border-bottom: none;
            box-shadow: 0 16px 45px rgba(0,0,0,0.4);
            background: rgba(14,165,233,0.15);
            backdrop-filter: blur(4px);
            transition: transform 0.4s ease;
        }
        /* Efek glass/kilap bergerak saat hover */
        .pejabat-foto-wrap::after {
            content: '';
            position: absolute;
            top: 0;
            left: -150%;
            width: 60%;
            height: 100%;
            background: linear-gradient(to right, rgba(255,255,255,0) 0%, rgba(255,255,255,0.5) 50%, rgba(255,255,255,0) 100%);
            transform: skewX(-25deg);
            z-index: 2;
            transition: left 0.7s ease-in-out;
        }
        .pejabat-card:hover .pejabat-foto-wrap::after {
            left: 150%;
        }
        .pejabat-card:hover .pejabat-foto-wrap {
            transform: scale(1.02);
        }
        
        .pejabat-foto-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: top center;
            transition: transform 0.5s ease;
        }
        .pejabat-card:hover .pejabat-foto-wrap img {
            transform: scale(1.05); /* Sedikit membesar ke dalam */
        }
        .pejabat-foto-wrap .no-photo {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 5rem;
            color: rgba(255,255,255,0.5);
        }
        .pejabat-info {
            width: 290px;
            background: linear-gradient(135deg, rgba(3,105,161,0.95), rgba(14,165,233,0.9));
            backdrop-filter: blur(8px);
            padding: 20px 18px 22px;
            border-radius: 0 0 20px 20px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.3);
            border-top: none;
            box-shadow: 0 12px 36px rgba(0,0,0,0.35);
            transition: transform 0.4s ease;
        }
        .pejabat-card:hover .pejabat-info {
            transform: scale(1.02);
            background: linear-gradient(135deg, rgba(2,88,136,0.95), rgba(12,143,204,0.9));
        }
        .pejabat-info .nama {
            color: #fff;
            font-size: 17px;
            font-weight: 800;
            line-height: 1.35;
            margin-bottom: 5px;
            letter-spacing: 0.3px;
        }
        .pejabat-info .jabatan {
            color: rgba(255,255,255,0.9);
            font-size: 14px;
            font-weight: 500;
            line-height: 1.4;
        }
        /* ── Medium desktop (≤ 1300px) ─────────────────── */
        @media (max-width: 1300px) {
            .pejabat-foto-wrap { width: 250px; height: 330px; }
            .pejabat-info { width: 250px; }
            .pejabat-card { width: 300px; }
        }

        /* ── Small desktop & Tablet (≤ 1100px) — mencegah tabrakan dengan merubah layout ── */
        @media (max-width: 1100px) {
            .hero-section {
                height: 85vh !important;
                min-height: 700px !important;
                max-height: 1000px !important;
            }
            .pejabat-card { 
                top: 20px; 
                transform: none;
                width: 120px;
                animation: none;
            }
            /* Posisikan foto berdampingan di tengah atas */
            .pejabat-card.kiri { left: calc(50% - 130px); margin-left: auto; }
            .pejabat-card.kanan { right: calc(50% - 130px); margin-right: auto; }
            
            .pejabat-foto-wrap { width: 120px; height: 160px; border-width: 2px; }
            .pejabat-foto-wrap::after { display: none; }
            
            .pejabat-info { 
                width: 120px; 
                height: 65px; /* Kunci tinggi agar simetris kiri-kanan */
                padding: 4px; 
                border-radius: 0 0 10px 10px; 
                display: flex; 
                flex-direction: column; 
                justify-content: center; 
            }
            .pejabat-info .nama { font-size: 10px; margin-bottom: 2px; }
            .pejabat-info .jabatan { font-size: 9px; line-height: 1.1; }
            
            /* Gunakan absolute positioning agar dijamin turun ke bawah layar tanpa diinterupsi flexbox */
            .slide .container {
                position: absolute !important;
                bottom: 40px !important;
                left: 0 !important;
                right: 0 !important;
                height: auto !important;
                display: block !important;
                padding-bottom: 0 !important;
            }
            .slide-content { 
                margin-top: 0 !important; 
                max-width: 90% !important; 
                margin-left: auto !important; margin-right: auto !important; 
            }
        }

        /* ── Mobile (≤ 600px) — sesuaikan ukuran pejabat card agar ringkas di atas ── */
        @media (max-width: 600px) {
            .hero-section {
                height: 80vh !important; /* Tambah sedikit agar tidak sempit */
                min-height: 600px !important;
                max-height: 900px !important;
            }
            .pejabat-card {
                top: 25px;
                transform: none;
                width: 105px;
                animation: none;
                display: flex !important;
            }
            .pejabat-card.kiri { left: calc(50% - 110px); margin-left: auto; }
            .pejabat-card.kanan { right: calc(50% - 110px); margin-right: auto; }
            
            .pejabat-foto-wrap { width: 105px; height: 135px; border-width: 2px; }
            .pejabat-foto-wrap::after { display: none; }
            
            .pejabat-info { 
                width: 105px; 
                height: 55px; /* Kunci tinggi agar simetris kiri-kanan di HP kecil */
                padding: 4px; 
                border-radius: 0 0 10px 10px; 
                display: flex; 
                flex-direction: column; 
                justify-content: center; 
            }
            .pejabat-info .nama { font-size: 9px; margin-bottom: 2px; }
            .pejabat-info .jabatan { font-size: 8px; line-height: 1.1; }
            
            /* Tetap pastikan teks terkunci di bawah pada HP kecil */
            .slide .container {
                position: absolute !important;
                bottom: 40px !important;
                left: 0 !important;
                right: 0 !important;
                height: auto !important;
                display: block !important;
            }
            .slide-content {
                margin-top: 0 !important;
                max-width: 95% !important;
                margin-left: auto !important;
                margin-right: auto !important;
            }
        }
        </style>

        <!-- Bupati (kiri) -->
        <?php if (!empty($bupati_foto) || !empty($bupati_nama)): ?>
        <div class="pejabat-card kiri">
            <div class="pejabat-foto-wrap">
                <?php if (!empty($bupati_foto)): ?>
                <img src="<?php echo htmlspecialchars($bupati_foto); ?>" alt="<?php echo htmlspecialchars($bupati_nama); ?>">
                <?php
        else: ?>
                <div class="no-photo"><i class="fas fa-user"></i></div>
                <?php
        endif; ?>
            </div>
            <div class="pejabat-info">
                <?php if (!empty($bupati_nama)): ?>
                <div class="nama"><?php echo htmlspecialchars($bupati_nama); ?></div>
                <?php
        endif; ?>
                <div class="jabatan"><?php echo htmlspecialchars($bupati_jabatan); ?></div>
            </div>
        </div>
        <?php
    endif; ?>

        <!-- Wakil Bupati (kanan) -->
        <?php if (!empty($wakil_foto) || !empty($wakil_nama)): ?>
        <div class="pejabat-card kanan">
            <div class="pejabat-foto-wrap">
                <?php if (!empty($wakil_foto)): ?>
                <img src="<?php echo htmlspecialchars($wakil_foto); ?>" alt="<?php echo htmlspecialchars($wakil_nama); ?>">
                <?php
        else: ?>
                <div class="no-photo"><i class="fas fa-user"></i></div>
                <?php
        endif; ?>
            </div>
            <div class="pejabat-info">
                <?php if (!empty($wakil_nama)): ?>
                <div class="nama"><?php echo htmlspecialchars($wakil_nama); ?></div>
                <?php
        endif; ?>
                <div class="jabatan"><?php echo htmlspecialchars($wakil_jabatan); ?></div>
            </div>
        </div>
        <?php
    endif; ?>

        <?php
endif; ?>
    </section>

    <!-- Services Section -->
    <section class="services-section" id="layanan">
        <div class="container">
            <div class="section-header">
                <h2>Layanan Pajak Daerah</h2>
                <p>Temukan berbagai layanan perpajakan daerah yang dapat membantu Anda</p>
            </div>
            <div class="services-grid">
                <?php
$default_services = [
    ['title' => 'E-SPTPD', 'icon' => 'fas fa-file-invoice', 'desc' => 'Elektronik SPTPD untuk transaksi online', 'url' => '#'],
    ['title' => 'e-SPPT PBB', 'icon' => 'fas fa-building', 'desc' => 'Pengecekan SPPT secara Online', 'url' => '#'],
    ['title' => 'LAKONE', 'icon' => 'fas fa-laptop', 'desc' => 'Layanan Pajak Daerah Online', 'url' => '#'],
    ['title' => 'E-Sumpah PBB', 'icon' => 'fas fa-handshake', 'desc' => 'Unggah mandiri pelayanan PBB', 'url' => '#'],
    ['title' => 'e-PBB', 'icon' => 'fas fa-receipt', 'desc' => 'Cek Tagihan dan Pembayaran PBB via QRIS', 'url' => '#'],
    ['title' => 'e-BPHTB', 'icon' => 'fas fa-home', 'desc' => 'Sistem digitalBPHTB daerah', 'url' => '#'],
];

$services_to_show = !empty($services) ? $services : $default_services;
foreach ($services_to_show as $s):
?>
                <a href="<?php echo htmlspecialchars($s['url'] ?? '#'); ?>" class="service-card" target="_blank">
                    <div class="service-icon">
                        <?php if (!empty($s['image'])): ?>
                        <img src="<?php echo htmlspecialchars($s['image']); ?>"
                             alt="<?php echo htmlspecialchars($s['title']); ?>"
                             style="width:52px; height:52px; object-fit:contain;">
                        <?php
    else: ?>
                        <i class="<?php echo htmlspecialchars($s['icon'] ?? 'fas fa-file'); ?>"></i>
                        <?php
    endif; ?>
                    </div>
                    <h3><?php echo htmlspecialchars($s['title']); ?></h3>
                    <p><?php echo htmlspecialchars($s['description'] ?? $s['desc'] ?? ''); ?></p>
                </a>
                <?php
endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Agenda Section -->
    <?php if(!empty($agenda_items)): ?>
    <section class="agenda-section" style="padding: 60px 0; background: #f8fafc;">
        <div class="container">
            <div class="section-header">
                <h2>Agenda & Jatuh Tempo Pajak</h2>
                <p>Jadwal kegiatan terbaru dan batas waktu pembayaran pajak daerah</p>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
                <?php foreach($agenda_items as $ag): ?>
                <div style="background: #fff; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); border-left: 5px solid var(--primary);">
                    <div style="font-size:12px; font-weight:700; color:var(--primary); margin-bottom:8px; text-transform:uppercase; letter-spacing:1px;">
                        <i class="far fa-calendar"></i> <?php echo date('d M Y - H:i', strtotime($ag['event_date'])); ?>
                    </div>
                    <h3 style="margin:0 0 10px; font-size:18px; color:#1e293b;"><?php echo htmlspecialchars($ag['title']); ?></h3>
                    <?php if(!empty($ag['location'])): ?>
                    <div style="font-size:14px; color:#64748b; margin-bottom:8px;">
                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($ag['location']); ?>
                    </div>
                    <?php endif; ?>
                    <p style="margin:0; font-size:14px; color:#4a5568; line-height:1.5;">
                        <?php echo htmlspecialchars($ag['description']); ?>
                    </p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- About Section -->
    <section class="about-section" id="tentang">
        <div class="container">
            <div class="about-grid">
                <div class="about-image">
                    <img src="<?php echo htmlspecialchars($about_image); ?>" alt="Tentang Bapenda">
                </div>
                <div class="about-content">
                    <h2><?php echo htmlspecialchars($about_title); ?></h2>
                    <p><?php echo nl2br(htmlspecialchars($about_content)); ?></p>
                    <div class="btn-group">
                        <?php if (!empty($about_cta1_text)): ?>
                        <a href="<?php echo htmlspecialchars($about_cta1_link); ?>" class="btn-navy">
                            <?php if (!empty($about_cta1_icon)): ?>
                                <img src="<?php echo htmlspecialchars($about_cta1_icon); ?>" class="btn-icon" alt="">
                            <?php
    else: ?>
                                <i class="fas fa-tasks"></i>
                            <?php
    endif; ?>
                            <?php echo htmlspecialchars($about_cta1_text); ?>
                        </a>
                        <?php
endif; ?>
                        
                        <?php if (!empty($about_cta2_text)): ?>
                        <a href="<?php echo htmlspecialchars($about_cta2_link); ?>" class="btn-outline">
                            <?php if (!empty($about_cta2_icon)): ?>
                                <img src="<?php echo htmlspecialchars($about_cta2_icon); ?>" class="btn-icon" alt="">
                            <?php
    else: ?>
                                <i class="fas fa-sitemap"></i>
                            <?php
    endif; ?>
                            <?php echo htmlspecialchars($about_cta2_text); ?>
                        </a>
                        <?php
endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Related Links Section -->
    <section class="links-section">
        <div class="container">
            <div class="section-header">
                <h2>Layanan Pemerintah Daerah</h2>
                <p>Akses cepat ke website terkait</p>
            </div>
            <div class="links-grid">
                <?php
$default_links = [
    ['title' => 'Pemkot Semarang', 'image' => 'assets/img/link-logo.png', 'url' => 'https://semarangkota.go.id/'],
    ['title' => 'Solusi AWP', 'image' => 'assets/img/link-logo.png', 'url' => '#'],
    ['title' => 'Laporan Gratifikasi', 'image' => 'assets/img/link-logo.png', 'url' => '#'],
    ['title' => 'Provinsi Jawa Tengah', 'image' => 'assets/img/link-logo.png', 'url' => 'https://jatengprov.go.id/'],
];

$links_to_show = !empty($related_links) ? $related_links : $default_links;
foreach ($links_to_show as $l):
?>
                <a href="<?php echo htmlspecialchars($l['url']); ?>" class="link-card" target="_blank">
                    <img src="<?php echo htmlspecialchars($l['image'] ?? 'assets/img/link-logo.png'); ?>" alt="<?php echo htmlspecialchars($l['title']); ?>">
                    <span><?php echo htmlspecialchars($l['title']); ?></span>
                </a>
                <?php
endforeach; ?>
            </div>
        </div>
    </section>

    <!-- News Section -->
    <section class="news-section" id="berita">
        <div class="container">
            <div class="section-header">
                <h2>Berita Terbaru</h2>
                <p>Ikuti berita dan informasi terkini dari Badan Pendapatan Daerah</p>
            </div>
            <div class="news-grid">
                <?php if (empty($news_items)): ?>
                <div class="news-card">
                    <div class="news-image" style="background-image: url('assets/img/news-placeholder.jpg');">
                        <span class="news-date-badge">-</span>
                    </div>
                    <div class="news-content">
                        <h3>Belum ada berita</h3>
                        <p>Silakan tambahkan berita melalui panel admin.</p>
                        <a href="#" class="read-more">Baca Selengkapnya <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                <?php
else: ?>
                <?php foreach ($news_items as $n): ?>
                <div class="news-card">
                    <div class="news-image" style="background-image: url('<?php echo htmlspecialchars($n['image_url']); ?>');">
                        <span class="news-date-badge">
                            <i class="far fa-calendar-alt"></i> <?php echo date('d M Y', strtotime($n['created_at'])); ?>
                        </span>
                    </div>
                    <div class="news-content">
                        <h3><?php echo htmlspecialchars($n['title']); ?></h3>
                        <p><?php echo htmlspecialchars($n['snippet']); ?></p>
                        <a href="berita/detail/<?php echo (int)$n['id']; ?>" class="read-more">Baca Selengkapnya <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                <?php
    endforeach; ?>
                <?php
endif; ?>
            </div>
            <div style="text-align: center; margin-top: 50px;">
                <a href="berita" class="btn-outline">Lihat Semua Berita</a>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <h2><?php echo htmlspecialchars($cta_title); ?></h2>
            <p><?php echo nl2br(htmlspecialchars($cta_desc)); ?></p>
            <div class="cta-buttons">
                <?php if (!empty($cta_btn1_text)): ?>
                <a href="<?php echo htmlspecialchars($cta_btn1_link); ?>" class="btn-primary">
                    <?php if (!empty($cta_btn1_icon)): ?>
                        <img src="<?php echo htmlspecialchars($cta_btn1_icon); ?>" class="btn-icon" alt="">
                    <?php
    else: ?>
                        <i class="fas fa-phone"></i>
                    <?php
    endif; ?>
                    <?php echo htmlspecialchars($cta_btn1_text); ?>
                </a>
                <?php
endif; ?>
                
                <?php if (!empty($cta_btn2_text)): ?>
                <a href="<?php echo htmlspecialchars($cta_btn2_link); ?>" class="btn-secondary" target="_blank">
                    <?php if (!empty($cta_btn2_icon)): ?>
                        <img src="<?php echo htmlspecialchars($cta_btn2_icon); ?>" class="btn-icon" alt="">
                    <?php
    else: ?>
                        <i class="fas fa-exclamation-circle"></i>
                    <?php
    endif; ?>
                    <?php echo htmlspecialchars($cta_btn2_text); ?>
                </a>
                <?php
endif; ?>
            </div>
        </div>
    </section>

<?php require_once 'includes/footer.php'; ?>
