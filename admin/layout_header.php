<?php
if (!function_exists('has_permission')) {
    require_once __DIR__ . '/includes/security.php';
}
if (isset($conn)) {
    require_admin_login($conn);
}
$current_page = basename($_SERVER['PHP_SELF']);

if (!isset($global_settings)) {
    $global_settings = [];
    $res_global = $conn->query("SELECT key_name, value FROM settings");
    if ($res_global) {
        while($row = $res_global->fetch_assoc()){
            $global_settings[$row['key_name']] = $row['value'];
        }
    }
}
$thm_primary = $global_settings['theme_primary_color'] ?? '#0ea5e9';
$thm_font = $global_settings['theme_font_family'] ?? 'Poppins';
$thm_radius_lg = $global_settings['theme_border_radius'] ?? '24';
$thm_radius_md = intval($thm_radius_lg) / 2;
$thm_primary_glow = $thm_primary . '4D'; // 30% opacity in hex
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="theme-color" content="#0ea5e9">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title><?php echo $page_title ?? 'Admin Panel'; ?> - Bapenda Kobar</title>
    <?php $site_favicon = $global_settings['favicon_image'] ?? ''; if (!empty($site_favicon)): ?>
    <link rel="icon" href="../<?php echo htmlspecialchars($site_favicon); ?>">
    <?php endif; ?>
    <link href="https://fonts.googleapis.com/css2?family=<?php echo urlencode($thm_font); ?>:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: <?php echo htmlspecialchars($thm_primary); ?>;
            --accent: #f59e0b;
            --sidebar-width: 260px;
            --bg-light: #f8fafc;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --shadow-soft: 0 20px 40px -10px rgba(0,0,0,0.04);
            --shadow-glow: 0 10px 25px -5px <?php echo htmlspecialchars($thm_primary_glow); ?>;
            --radius-md: <?php echo $thm_radius_md; ?>px;
            --radius-lg: <?php echo htmlspecialchars($thm_radius_lg); ?>px;
            --radius-pill: 50px;
        }
        body { 
            font-family: '<?php echo htmlspecialchars($thm_font); ?>', sans-serif; 
            background: var(--bg-light); 
            margin: 0; 
            color: var(--text-dark);
            display: flex;
        }
        
        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: #fff;
            height: 100vh;
            position: fixed;
            box-shadow: 4px 0 24px rgba(0,0,0,0.03);
            display: flex;
            flex-direction: column;
            z-index: 100;
            border-right: 1px solid rgba(0,0,0,0.02);
        }
        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid #edf2f7;
            text-align: center;
        }
        .sidebar-header strong {
            font-size: 20px;
            color: var(--primary);
            display: block;
        }
        .sidebar-header span {
            font-size: 12px;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .sidebar-menu {
            padding: 20px 0;
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }
        /* Thin scrollbar for sidebar */
        .sidebar-menu::-webkit-scrollbar { width: 4px; }
        .sidebar-menu::-webkit-scrollbar-track { background: transparent; }
        .sidebar-menu::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 99px; }
        .sidebar-menu::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            margin: 5px 15px;
            border-radius: var(--radius-md);
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            transition: 0.3s;
        }
        .menu-item i {
            width: 25px;
            font-size: 18px;
            margin-right: 10px;
        }
        .menu-item:hover {
            background: #f0f9ff;
            color: var(--primary);
            transform: translateX(3px);
        }
        .menu-item.active {
            background: #f0f9ff;
            color: var(--primary);
            box-shadow: 0 4px 10px rgba(14, 165, 233, 0.1);
        }
        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid #edf2f7;
        }
        .btn-logout {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            border: none;
            cursor: pointer;
            font-family: inherit;
            font-size: 14px;
            background: #fee2e2;
            color: #ef4444;
            padding: 12px;
            border-radius: var(--radius-md);
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
        }
        .btn-logout:hover {
            background: #ef4444;
            color: #fff;
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            padding: 40px;
            min-height: 100vh;
            box-sizing: border-box;
        }
        
        /* Topbar Header inside Main */
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .topbar h1 {
            margin: 0;
            font-size: 24px;
            color: var(--text-dark);
        }
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .btn-view-site {
            background: #fff;
            color: var(--text-dark);
            border: 1px solid #e2e8f0;
            padding: 8px 15px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: 0.2s;
        }
        .btn-view-site:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        /* Shared Components */
        .card {
            background: #fff;
            padding: 35px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            border: 1px solid rgba(0,0,0,0.02);
            margin-bottom: 30px;
        }
        .card-header {
            border-bottom: 1px solid #edf2f7;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .card-header h2 {
            margin: 0;
            font-size: 18px;
            color: var(--primary);
        }
        
        /* Forms */
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #4a5568; font-size: 14px; }
        .form-control { width: 100%; padding: 14px 18px; border: 1px solid #e2e8f0; border-radius: var(--radius-md); box-sizing: border-box; font-family: 'Poppins', sans-serif; transition: 0.3s; background: #f8fafc; }
        .form-control:focus { outline: none; border-color: var(--primary); background: #fff; box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.1); }
        textarea.form-control { resize: vertical; min-height: 100px; }
        
        /* Buttons */
        .btn { background: var(--primary); color: #fff; border: none; padding: 14px 28px; border-radius: var(--radius-pill); cursor: pointer; font-size: 15px; font-weight: 600; transition: 0.3s; display: inline-block; text-decoration: none; box-shadow: var(--shadow-glow);}
        .btn:hover { background: #0284c7; transform: translateY(-3px); box-shadow: 0 15px 35px rgba(14, 165, 233, 0.4); }
        .btn-danger { background: #fee2e2; color: #ef4444; padding: 10px 18px; border-radius: var(--radius-pill); font-size: 13px; text-decoration: none; border: none; cursor: pointer; display:inline-block; font-weight: 600; transition: 0.3s;}
        .btn-danger:hover { background: #ef4444; color: #fff; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);}
        .btn-action { padding: 8px 12px; border-radius: 6px; border: none; cursor: pointer; display: inline-block; text-decoration: none; transition: 0.3s; font-size: 13px; margin-right: 5px; font-family: inherit;}
        .btn-action.edit { background: #dbeafe; color: #1d4ed8; }
        .btn-action.edit:hover { background: #1d4ed8; color: #fff; }
        .btn-action.delete { background: #fee2e2; color: #ef4444; }
        .btn-action.delete:hover { background: #ef4444; color: #fff; }
        .btn-secondary { background: var(--bg-light); color: var(--text-muted); padding: 10px 18px; border-radius: var(--radius-pill); font-size: 13px; text-decoration: none; border: 1px solid #e2e8f0; font-weight: 600; transition: 0.3s;}
        
        /* Tables */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border-bottom: 1px solid #edf2f7; padding: 15px; text-align: left; vertical-align: top; }
        th { color: #a0aec0; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; font-weight: 600; }
        
        .alert { padding: 15px 20px; background: #c6f6d5; color: #22543d; margin-bottom: 25px; border-radius: 8px; font-weight: 500; border-left: 4px solid #38a169; }
        .alert-danger { background: #fee2e2; color: #c53030; border-left-color: #e53e3e; }
        img.preview { width: 120px; height: 80px; object-fit: cover; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }

        /* ── Admin Hamburger ──────────────────── */
        .admin-hamburger {
            display: none;
            background: none;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 18px;
            cursor: pointer;
            color: var(--text-dark);
            transition: 0.2s;
            min-width: 44px;
            min-height: 44px;
            align-items: center;
            justify-content: center;
            gap: 6px;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
        }
        .admin-hamburger:hover, .admin-hamburger:active { background: #f0f9ff; color: var(--primary); }

        /* ── Admin Sidebar Overlay ─────────────── */
        .admin-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 99;
            -webkit-tap-highlight-color: transparent;
        }
        .admin-overlay.active { display: block; }

        /* ── Responsive: Tablet (≤ 992px) ─────── */
        @media screen and (max-width: 992px) {
            :root { --sidebar-width: 220px; }
            .main-content { padding: 25px; }
            .card { padding: 25px; }
        }

        /* ── Responsive: Mobile (≤ 768px) ─────── */
        @media screen and (max-width: 768px) {
            body { display: block; }

            .admin-hamburger {
                display: -webkit-inline-flex;
                display: inline-flex;
            }

            .sidebar {
                position: fixed;
                left: calc(-1 * var(--sidebar-width));
                top: 0;
                height: 100vh;
                height: 100dvh;
                z-index: 100;
                -webkit-transition: left 0.3s ease;
                transition: left 0.3s ease;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
            }
            .sidebar.active { left: 0; }

            .main-content {
                margin-left: 0;
                padding: 15px;
                width: 100%;
                box-sizing: border-box;
                overflow-x: hidden;
            }

            .topbar {
                -webkit-flex-wrap: wrap;
                flex-wrap: wrap;
                gap: 10px;
                margin-bottom: 20px;
            }
            .topbar h1 { font-size: 18px; }

            .topbar-right {
                -webkit-flex-wrap: wrap;
                flex-wrap: wrap;
                gap: 8px;
                width: 100%;
                -webkit-justify-content: flex-end;
                justify-content: flex-end;
            }

            .card { padding: 16px 14px; margin-bottom: 16px; }

            /* Dashboard grid: 2 columns on mobile tablet */
            .dashboard-grid {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 12px !important;
            }

            /* Chart grid: single column */
            .dashboard-chart-grid {
                grid-template-columns: 1fr !important;
                gap: 16px !important;
            }

            /* Chart height */
            #visitorChart {
                max-height: 220px;
            }

            /* Tables scroll horizontally */
            .table-responsive {
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch;
                max-width: 100%;
            }
            .card div[style*="overflow-x"] {
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch;
            }
            table { min-width: 480px; }

            /* Full-width form inputs */
            .form-control { font-size: 16px; } /* prevents iOS zoom on focus */

            /* Buttons */
            .btn { padding: 12px 20px; font-size: 14px; }
            .topbar .btn-view-site { font-size: 13px; }
        }

        /* ── Responsive: Small phone (≤ 480px) ─ */
        @media screen and (max-width: 480px) {
            .main-content { padding: 12px 10px; }
            .topbar h1 { font-size: 15px; }
            .btn-view-site { font-size: 11px; padding: 6px 10px; }
            .card { padding: 14px 12px; }
            .card-header h2 { font-size: 16px; }
            th, td { padding: 10px 8px; font-size: 13px; }

            /* Single column cards on small phone */
            .dashboard-grid {
                grid-template-columns: 1fr !important;
                gap: 10px !important;
            }

            /* Smaller chart */
            #visitorChart { max-height: 180px; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <strong>Bapenda Kobar</strong>
            <span>Admin Panel</span>
        </div>
        <div class="sidebar-menu">
            <a href="dashboard" class="menu-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> Dashboard Analitik
            </a>
            
            <?php if(has_permission(['menu_users', 'menu_roles'])): ?>
            <?php if(has_permission('menu_users')): ?>
            <a href="users" class="menu-item <?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users-cog"></i> Manajemen Pengguna
            </a>
            <?php endif; ?>
            <?php if(has_permission('menu_roles')): ?>
            <a href="roles" class="menu-item <?php echo $current_page == 'roles.php' ? 'active' : ''; ?>">
                <i class="fas fa-id-badge"></i> Grup Hak Akses
            </a>
            <?php endif; ?>
            <?php endif; ?>

            <a href="profil" class="menu-item <?php echo $current_page == 'profil.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-shield"></i> Profil Admin
            </a>

            <?php if(has_permission(['menu_settings_general', 'menu_settings_theme', 'menu_settings_header', 'menu_settings_links'])): ?>
            <?php if(has_permission('menu_settings_general')): ?>
            <a href="index" class="menu-item <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i> Pengaturan Umum
            </a>
            <?php endif; ?>
            <?php if(has_permission('menu_settings_theme')): ?>
            <a href="theme" class="menu-item <?php echo $current_page == 'theme.php' ? 'active' : ''; ?>">
                <i class="fas fa-paint-brush"></i> Kustomisasi Tema
            </a>
            <?php endif; ?>
            <?php if(has_permission('menu_settings_header')): ?>
            <a href="header" class="menu-item <?php echo $current_page == 'header.php' ? 'active' : ''; ?>">
                <i class="fas fa-compass"></i> Header & Navigasi
            </a>
            <?php endif; ?>
            <?php if(has_permission('menu_settings_links')): ?>
            <a href="links" class="menu-item <?php echo $current_page == 'links.php' ? 'active' : ''; ?>">
                <i class="fas fa-link"></i> Link Terkait
            </a>
            <?php endif; ?>
            <?php endif; ?>

            <?php if(has_permission(['menu_content_bupati', 'menu_content_struktur', 'menu_content_pegawai', 'menu_content_pages', 'menu_content_gallery', 'menu_content_documents', 'menu_content_agenda', 'menu_content_bantuan', 'menu_content_services', 'menu_content_news', 'menu_content_popup'])): ?>
            <div style="padding: 14px 20px 4px; font-size: 10px; text-transform: uppercase; letter-spacing: 1.2px; color: #cbd5e1; font-weight: 700;">
                Konten Instansi & Publikasi
            </div>
            <?php if(has_permission('menu_content_bupati')): ?>
            <a href="bupati" class="menu-item <?php echo $current_page == 'bupati.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-tie"></i> Foto Pimpinan
            </a>
            <?php endif; ?>
            <?php if(has_permission('menu_content_struktur')): ?>
            <a href="struktur_org" class="menu-item <?php echo $current_page == 'struktur_org.php' ? 'active' : ''; ?>">
                <i class="fas fa-sitemap"></i> Struktur Organisasi
            </a>
            <?php endif; ?>
            <?php if(has_permission('menu_content_pegawai')): ?>
            <a href="pegawai" class="menu-item <?php echo $current_page == 'pegawai.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Data Pegawai
            </a>
            <?php endif; ?>
            <?php if(has_permission('menu_content_pages')): ?>
            <a href="pages" class="menu-item <?php echo $current_page == 'pages.php' || $current_page == 'page_form.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i> Halaman Statis
            </a>
            <?php endif; ?>
            <?php if(has_permission('menu_content_gallery')): ?>
            <a href="gallery" class="menu-item <?php echo $current_page == 'gallery.php' ? 'active' : ''; ?>">
                <i class="fas fa-images"></i> Manajemen Galeri
            </a>
            <?php endif; ?>
            <?php if(has_permission('menu_content_documents')): ?>
            <a href="documents" class="menu-item <?php echo $current_page == 'documents.php' ? 'active' : ''; ?>">
                <i class="fas fa-folder-open"></i> Dokumen PPID
            </a>
            <?php endif; ?>
            <?php if(has_permission('menu_content_agenda')): ?>
            <a href="agenda" class="menu-item <?php echo $current_page == 'agenda.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i> Agenda Kegiatan
            </a>
            <?php endif; ?>
            <?php if(has_permission('menu_content_bantuan')): ?>
            <a href="bantuan" class="menu-item <?php echo $current_page == 'bantuan.php' ? 'active' : ''; ?>">
                <i class="fas fa-headset"></i> Pusat Bantuan
            </a>
            <?php endif; ?>
            <?php if(has_permission('menu_content_services')): ?>
            <a href="services" class="menu-item <?php echo $current_page == 'services.php' ? 'active' : ''; ?>">
                <i class="fas fa-concierge-bell"></i> Layanan Utama
            </a>
            <?php endif; ?>
            <?php if(has_permission('menu_content_news')): ?>
            <a href="news" class="menu-item <?php echo $current_page == 'news.php' ? 'active' : ''; ?>">
                <i class="fas fa-newspaper"></i> Berita Terbaru
            </a>
            <?php endif; ?>
            <?php if(has_permission('menu_content_popup')): ?>
            <a href="popup" class="menu-item <?php echo $current_page == 'popup.php' ? 'active' : ''; ?>">
                <i class="fas fa-bullhorn"></i> Popup Info
            </a>
            <?php endif; ?>
            <?php endif; ?>

            <?php if(has_permission('menu_report_pengaduan')): ?>
            <a href="pengaduan" class="menu-item <?php echo $current_page == 'pengaduan.php' ? 'active' : ''; ?>">
                <i class="fas fa-envelope-open-text"></i> Laporan Pengaduan
            </a>
            <?php endif; ?>

            <?php if(has_permission(['menu_tax_target', 'menu_tax_realisasi'])): ?>
            <div style="padding: 14px 20px 4px; font-size: 10px; text-transform: uppercase; letter-spacing: 1.2px; color: #cbd5e1; font-weight: 700;">
                Pajak Daerah
            </div>
            <?php if(has_permission('menu_tax_target')): ?>
            <a href="pajak_target" class="menu-item <?php echo $current_page == 'pajak_target.php' ? 'active' : ''; ?>">
                <i class="fas fa-bullseye"></i> Target Pajak
            </a>
            <?php endif; ?>
            <?php if(has_permission('menu_tax_realisasi')): ?>
            <a href="pajak_realisasi" class="menu-item <?php echo $current_page == 'pajak_realisasi.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Realisasi Pajak
            </a>
            <?php endif; ?>
            <?php endif; ?>

            <div style="padding: 14px 20px 4px; font-size: 10px; text-transform: uppercase; letter-spacing: 1.2px; color: #cbd5e1; font-weight: 700;">
                Bantuan & Panduan
            </div>
            <a href="buku_panduan" class="menu-item <?php echo $current_page == 'buku_panduan.php' ? 'active' : ''; ?>">
                <i class="fas fa-book"></i> Buku Panduan
            </a>
        </div>
        <div class="sidebar-footer">
            <form method="post" action="logout.php" style="margin:0;">
                <?php echo csrf_token_field(); ?>
                <button type="submit" class="btn-logout">
                    <i class="fas fa-sign-out-alt" style="margin-right:8px;"></i> Keluar Panel
                </button>
            </form>
        </div>
    </div>
    
    <!-- Admin Sidebar Overlay -->
    <div class="admin-overlay" id="admin-overlay"></div>

    <div class="main-content">
        <div class="topbar">
            <div style="display:flex; align-items:center; gap:12px;">
                <button class="admin-hamburger" id="admin-hamburger" aria-label="Toggle Menu">
                    <i class="fas fa-bars"></i>
                </button>
                <h1><?php echo htmlspecialchars($page_title ?? 'Dashboard', ENT_QUOTES, 'UTF-8'); ?></h1>
            </div>
            <div class="topbar-right">
                <span style="color: var(--text-muted); font-size: 14px; font-weight: 500;">Halo, <?php echo htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Admin'); ?></span>
                <a href="../" target="_blank" rel="noopener noreferrer" class="btn-view-site">
                    <i class="fas fa-external-link-alt" style="margin-right:5px;"></i> Lihat Website
                </a>
            </div>
        </div>

    <script>
    (function() {
        var hamburger = document.getElementById('admin-hamburger');
        var sidebar   = document.querySelector('.sidebar');
        var overlay   = document.getElementById('admin-overlay');

        function openSidebar() {
            if (sidebar)  sidebar.classList.add('active');
            if (overlay)  overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeSidebar() {
            if (sidebar)  sidebar.classList.remove('active');
            if (overlay)  overlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        if (hamburger) hamburger.addEventListener('click', openSidebar);
        if (overlay)   overlay.addEventListener('click', closeSidebar);

        // Auto-close when clicking a menu link on mobile
        document.querySelectorAll('.menu-item').forEach(function(item) {
            item.addEventListener('click', function() {
                if (window.innerWidth <= 768) closeSidebar();
            });
        });
    })();
    </script>
