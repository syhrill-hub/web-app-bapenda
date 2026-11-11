<?php
require_once __DIR__ . '/../includes/security_helpers.php';
bapenda_start_secure_session();
require_once '../config.php';
require_once 'includes/security.php';
require_once __DIR__ . '/../includes/upload_helper.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
require_permission('menu_settings_general');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = "Token keamanan tidak valid. Silakan coba lagi.";
    header("Location: index.php");
    exit;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'hero_tagline' => $_POST['hero_tagline'] ?? '',
        'hero_title' => $_POST['hero_title'] ?? '',
        'hero_desc' => $_POST['hero_desc'] ?? '',
        'hero_cta1_text' => $_POST['hero_cta1_text'] ?? '',
        'hero_cta1_link' => $_POST['hero_cta1_link'] ?? '#',
        'hero_cta2_text' => $_POST['hero_cta2_text'] ?? '',
        'hero_cta2_link' => $_POST['hero_cta2_link'] ?? '#',
        'footer_jam' => $_POST['footer_jam'] ?? '',
        'footer_email' => $_POST['footer_email'] ?? '',
        'footer_phone' => $_POST['footer_phone'] ?? '',
        'footer_address' => $_POST['footer_address'] ?? '',
        'footer_desc' => $_POST['footer_desc'] ?? '',
        'footer_social_twitter' => $_POST['footer_social_twitter'] ?? '',
        'footer_social_facebook' => $_POST['footer_social_facebook'] ?? '',
        'footer_social_youtube' => $_POST['footer_social_youtube'] ?? '',
        'footer_social_instagram' => $_POST['footer_social_instagram'] ?? '',
        'footer_copyright' => $_POST['footer_copyright'] ?? '',
        'about_title' => $_POST['about_title'] ?? '',
        'about_content' => $_POST['about_content'] ?? '',
        'about_cta1_text' => $_POST['about_cta1_text'] ?? '',
        'about_cta1_link' => $_POST['about_cta1_link'] ?? '',
        'about_cta2_text' => $_POST['about_cta2_text'] ?? '',
        'about_cta2_link' => $_POST['about_cta2_link'] ?? '',
        'cta_btn1_text' => $_POST['cta_btn1_text'] ?? '',
        'cta_btn1_link' => $_POST['cta_btn1_link'] ?? '',
        'cta_btn2_text' => $_POST['cta_btn2_text'] ?? '',
        'cta_btn2_link' => $_POST['cta_btn2_link'] ?? '',
    ];

    $upload_img_dir = __DIR__ . '/../assets/img/uploads';

    // Handle File Upload for Hero Backgrounds
    for ($i = 1; $i <= 3; $i++) {
        $file_key = 'hero_bg_file_' . $i;
        $bg_key = 'hero_bg' . $i;

        if (bapenda_has_uploaded_file($_FILES[$file_key] ?? [])) {
            $upErr = null;
            $saved = bapenda_handle_upload(
                $_FILES[$file_key],
                $upload_img_dir,
                'assets/img/uploads',
                ['jpg', 'jpeg', 'png', 'webp'],
                2 * 1024 * 1024,
                'hero_bg' . $i,
                $upErr
            );
            if ($saved !== null) {
                $settings[$bg_key] = $saved;
            } elseif (empty($message)) {
                $message = $upErr;
            }
        }
    }

    // Handle File Upload for About Image
    if (bapenda_has_uploaded_file($_FILES['about_image_file'] ?? [])) {
        $upErr = null;
        $saved = bapenda_handle_upload(
            $_FILES['about_image_file'],
            $upload_img_dir,
            'assets/img/uploads',
            ['jpg', 'jpeg', 'png', 'webp'],
            2 * 1024 * 1024,
            'about_img',
            $upErr
        );
        if ($saved !== null) {
            $settings['about_image'] = $saved;
        } elseif (empty($message)) {
            $message = $upErr;
        }
    }

    // Handle File Upload for Icon Images
    $icon_upload_fields = [
        'about_cta1_icon_file' => 'about_cta1_icon',
        'about_cta2_icon_file' => 'about_cta2_icon',
        'cta_btn1_icon_file'   => 'cta_btn1_icon',
        'cta_btn2_icon_file'   => 'cta_btn2_icon',
    ];
    for ($i = 1; $i <= 4; $i++) {
        $icon_upload_fields["social_icon_img_file_{$i}"] = "social_icon_img_{$i}";
    }

    foreach ($icon_upload_fields as $file_key => $setting_key) {
        if (bapenda_has_uploaded_file($_FILES[$file_key] ?? [])) {
            $upErr = null;
            $saved = bapenda_handle_upload(
                $_FILES[$file_key],
                $upload_img_dir,
                'assets/img/uploads',
                ['png', 'jpg', 'jpeg', 'webp'],
                512 * 1024,
                $setting_key,
                $upErr
            );
            if ($saved !== null) {
                $settings[$setting_key] = $saved;
            } elseif (empty($message)) {
                $message = $upErr;
            }
        }
    }

    // Social icon links
    for ($i = 1; $i <= 4; $i++) {
        $settings["social_link_{$i}"] = $_POST["social_link_{$i}"] ?? '';
    }

    foreach ($settings as $key => $val) {
        safe_settings_update($conn, $key, $val);
    }
    
    if (empty($message)) {
        $_SESSION['flash_success'] = "Pengaturan berhasil disimpan!";
    } else {
        $_SESSION['flash_error'] = $message;
    }
    header("Location: index.php");
    exit;
}

$message = '';
$msg_type = 'success';
if (isset($_SESSION['flash_success'])) {
    $message = $_SESSION['flash_success'];
    $msg_type = 'success';
    unset($_SESSION['flash_success']);
} elseif (isset($_SESSION['flash_error'])) {
    $message = $_SESSION['flash_error'];
    $msg_type = 'error';
    unset($_SESSION['flash_error']);
}

// Fetch current settings
$current_settings = [];
$result = $conn->query("SELECT * FROM settings");
if ($result) {
    while($row = $result->fetch_assoc()){
        $current_settings[$row['key_name']] = $row['value'];
    }
}
?>
<?php
$page_title = 'Pengaturan Umum';
require_once 'layout_header.php';
?>

<?php if($message): ?>
    <div class="alert" style="background:<?php echo $msg_type === 'error' ? '#fee2e2' : '#d1fae5'; ?>; color:<?php echo $msg_type === 'error' ? '#991b1b' : '#065f46'; ?>; padding:15px; border-radius:12px; margin-bottom:20px;">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <?php echo csrf_token_field(); ?>
    <!-- Hero Settings Card -->
    <div class="card">
        <div class="card-header">
            <h2>Konten Pahlawan (Hero)</h2>
        </div>
        
        <?php for($i=1; $i<=3; $i++): 
            $bg_key = 'hero_bg' . $i;
            $file_key = 'hero_bg_file_' . $i;
            $fallback = ($i == 1) ? ($current_settings['hero_bg'] ?? 'assets/img/hero-bg.png') : '';
            $current_bg = $current_settings[$bg_key] ?? $fallback;
        ?>
        <div class="form-group" style="padding: 15px; border: 1px dashed #cbd5e1; border-radius: var(--radius-md); margin-bottom: 15px;">
            <label>Gambar Latar Belakang <?php echo $i; ?></label>
            <?php if (!empty($current_bg)): ?>
            <div style="margin-bottom: 10px;">
                <img src="../<?php echo htmlspecialchars($current_bg); ?>" style="width: 250px; height: 120px; object-fit: cover; border-radius: 8px; border: 1px solid #e2e8f0; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
            </div>
            <?php endif; ?>
            <input type="file" name="<?php echo $file_key; ?>" class="form-control" accept="image/jpeg, image/png, image/webp">
            <small style="color: #64748b; font-size: 13px; margin-top: 8px; display: inline-block;">Format: JPG, PNG, WEBP. Maks 2MB.</small>
        </div>
        <?php endfor; ?>
        
        <div class="form-group">
            <label>Label Atas (Contoh: BADAN PENDAPATAN DAERAH)</label>
            <input type="text" name="hero_tagline" class="form-control" value="<?php echo htmlspecialchars($current_settings['hero_tagline'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label>Judul Utama (Bisa gunakan &lt;br&gt; untuk baris baru)</label>
            <input type="text" name="hero_title" class="form-control" value="<?php echo htmlspecialchars($current_settings['hero_title'] ?? ''); ?>">
        </div>
        <div class="form-group">
            <label>Deskripsi Pendek</label>
            <textarea name="hero_desc" class="form-control"><?php echo htmlspecialchars($current_settings['hero_desc'] ?? ''); ?></textarea>
        </div>
        
        <hr style="margin: 30px 0; border: 0; border-top: 1px dashed #e2e8f0;">
        
        <div style="display: flex; gap: 20px;">
            <div class="form-group" style="flex: 1;">
                <label>Teks Tombol 1 (Kuning)</label>
                <input type="text" name="hero_cta1_text" class="form-control" value="<?php echo htmlspecialchars($current_settings['hero_cta1_text'] ?? ''); ?>">
            </div>
            <div class="form-group" style="flex: 1;">
                <label>Link Tombol 1</label>
                <input type="text" name="hero_cta1_link" class="form-control" value="<?php echo htmlspecialchars($current_settings['hero_cta1_link'] ?? ''); ?>">
            </div>
        </div>

        <div style="display: flex; gap: 20px;">
            <div class="form-group" style="flex: 1;">
                <label>Teks Tombol 2 (Garis Tengah)</label>
                <input type="text" name="hero_cta2_text" class="form-control" value="<?php echo htmlspecialchars($current_settings['hero_cta2_text'] ?? ''); ?>">
            </div>
            <div class="form-group" style="flex: 1;">
                <label>Link Tombol 2</label>
                <input type="text" name="hero_cta2_link" class="form-control" value="<?php echo htmlspecialchars($current_settings['hero_cta2_link'] ?? ''); ?>">
            </div>
        </div>
    </div> <!-- End Hero Card -->

    <!-- About / Tentang Settings Card -->
    <div class="card" style="margin-top: 30px;">
        <div class="card-header">
            <h2>Tentang Bapenda (About Section)</h2>
        </div>
        
        <div class="form-group">
            <label>Gambar Tentang Bapenda</label>
            <div style="margin-bottom: 10px;">
                <img src="../<?php echo htmlspecialchars($current_settings['about_image'] ?? 'assets/img/about.jpg'); ?>" style="width: 250px; height: 180px; object-fit: cover; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 10px rgba(0,0,0,0.05);">
            </div>
            <input type="file" name="about_image_file" class="form-control" accept="image/jpeg, image/png, image/webp">
            <small style="color: #64748b; font-size: 13px; margin-top: 8px; display: inline-block;">Format: JPG, PNG, WEBP. Maks 2MB.</small>
        </div>

        <div class="form-group">
            <label>Judul Bagian Tentang</label>
            <input type="text" name="about_title" class="form-control" value="<?php echo htmlspecialchars($current_settings['about_title'] ?? 'Selamat Datang di Badan Pendapatan Daerah'); ?>">
        </div>
        
        <div class="form-group">
            <label>Isi Konten (Deskripsi)</label>
            <textarea name="about_content" class="form-control" rows="5"><?php echo htmlspecialchars($current_settings['about_content'] ?? 'Badan Pendapatan Daerah (BAPENDA) merupakan unsur pelaksana urusan pemerintahan daerah di bidang pendapatan daerah...'); ?></textarea>
        </div>
        
        <div style="display: flex; gap: 20px; align-items: flex-start;">
            <div style="flex: 1;">
                <div class="form-group">
                    <label>Teks Tombol 1 (Biru)</label>
                    <input type="text" name="about_cta1_text" class="form-control" value="<?php echo htmlspecialchars($current_settings['about_cta1_text'] ?? 'Tugas & Fungsi'); ?>">
                </div>
                <div class="form-group">
                    <label>Link Tombol 1</label>
                    <input type="text" name="about_cta1_link" class="form-control" value="<?php echo htmlspecialchars($current_settings['about_cta1_link'] ?? '#'); ?>">
                </div>
            </div>
            <div style="flex: 1;">
                <div class="form-group" style="padding: 15px; border: 1px dashed #cbd5e1; border-radius: var(--radius-md);">
                    <label>Ikon Tombol 1</label>
                    <?php $icon1 = $current_settings['about_cta1_icon'] ?? ''; if (!empty($icon1)): ?>
                    <div style="margin-bottom:8px;"><img src="../<?php echo htmlspecialchars($icon1); ?>" style="width:32px;height:32px;object-fit:contain;border:1px solid #e2e8f0;border-radius:6px;padding:4px;"></div>
                    <?php endif; ?>
                    <input type="file" name="about_cta1_icon_file" class="form-control" accept="image/png,image/svg+xml,image/jpeg,image/webp">
                    <small style="color:#64748b;font-size:12px;">PNG/SVG/JPG. Maks 512KB. Default: ikon daftar.</small>
                </div>
            </div>
        </div>

        <div style="display: flex; gap: 20px; align-items: flex-start;">
            <div style="flex: 1;">
                <div class="form-group">
                    <label>Teks Tombol 2 (Garis Tepi)</label>
                    <input type="text" name="about_cta2_text" class="form-control" value="<?php echo htmlspecialchars($current_settings['about_cta2_text'] ?? 'Struktur Organisasi'); ?>">
                </div>
                <div class="form-group">
                    <label>Link Tombol 2</label>
                    <input type="text" name="about_cta2_link" class="form-control" value="<?php echo htmlspecialchars($current_settings['about_cta2_link'] ?? '#'); ?>">
                </div>
            </div>
            <div style="flex: 1;">
                <div class="form-group" style="padding: 15px; border: 1px dashed #cbd5e1; border-radius: var(--radius-md);">
                    <label>Ikon Tombol 2</label>
                    <?php $icon2 = $current_settings['about_cta2_icon'] ?? ''; if (!empty($icon2)): ?>
                    <div style="margin-bottom:8px;"><img src="../<?php echo htmlspecialchars($icon2); ?>" style="width:32px;height:32px;object-fit:contain;border:1px solid #e2e8f0;border-radius:6px;padding:4px;"></div>
                    <?php endif; ?>
                    <input type="file" name="about_cta2_icon_file" class="form-control" accept="image/png,image/svg+xml,image/jpeg,image/webp">
                    <small style="color:#64748b;font-size:12px;">PNG/SVG/JPG. Maks 512KB. Default: ikon organisasi.</small>
                </div>
            </div>
        </div>
    </div> <!-- End About Card -->

    <!-- CTA Section Settings Card -->
    <div class="card" style="margin-top: 30px;">
        <div class="card-header">
            <h2>Bagian Ajakan (CTA Section)</h2>
        </div>
        <div class="form-group">
            <label>Judul CTA</label>
            <input type="text" name="cta_title" class="form-control" value="<?php echo htmlspecialchars($current_settings['cta_title'] ?? 'Butuh Bantuan atau Informasi?'); ?>">
        </div>
        <div class="form-group">
            <label>Deskripsi CTA</label>
            <textarea name="cta_desc" class="form-control"><?php echo htmlspecialchars($current_settings['cta_desc'] ?? ''); ?></textarea>
        </div>
        <div style="display: flex; gap: 20px; align-items: flex-start;">
            <div style="flex: 1;">
                <div class="form-group">
                    <label>Teks Tombol 1</label>
                    <input type="text" name="cta_btn1_text" class="form-control" value="<?php echo htmlspecialchars($current_settings['cta_btn1_text'] ?? 'Kontak Kami'); ?>">
                </div>
                <div class="form-group">
                    <label>Link Tombol 1</label>
                    <input type="text" name="cta_btn1_link" class="form-control" value="<?php echo htmlspecialchars($current_settings['cta_btn1_link'] ?? '#kontak'); ?>">
                </div>
            </div>
            <div style="flex: 1;">
                <div class="form-group" style="padding: 15px; border: 1px dashed #cbd5e1; border-radius: var(--radius-md);">
                    <label>Ikon Tombol 1</label>
                    <?php $cta1_icon = $current_settings['cta_btn1_icon'] ?? ''; if (!empty($cta1_icon)): ?>
                    <div style="margin-bottom:8px;"><img src="../<?php echo htmlspecialchars($cta1_icon); ?>" style="width:32px;height:32px;object-fit:contain;border:1px solid #e2e8f0;border-radius:6px;padding:4px;"></div>
                    <?php endif; ?>
                    <input type="file" name="cta_btn1_icon_file" class="form-control" accept="image/png,image/svg+xml,image/jpeg,image/webp">
                    <small style="color:#64748b;font-size:12px;">PNG/SVG/JPG. Maks 512KB. Default: ikon telepon.</small>
                </div>
            </div>
        </div>
        <div style="display: flex; gap: 20px; align-items: flex-start;">
            <div style="flex: 1;">
                <div class="form-group">
                    <label>Teks Tombol 2</label>
                    <input type="text" name="cta_btn2_text" class="form-control" value="<?php echo htmlspecialchars($current_settings['cta_btn2_text'] ?? 'Laporan Pengaduan'); ?>">
                </div>
                <div class="form-group">
                    <label>Link Tombol 2</label>
                    <input type="text" name="cta_btn2_link" class="form-control" value="<?php echo htmlspecialchars($current_settings['cta_btn2_link'] ?? '#'); ?>">
                </div>
            </div>
            <div style="flex: 1;">
                <div class="form-group" style="padding: 15px; border: 1px dashed #cbd5e1; border-radius: var(--radius-md);">
                    <label>Ikon Tombol 2</label>
                    <?php $cta2_icon = $current_settings['cta_btn2_icon'] ?? ''; if (!empty($cta2_icon)): ?>
                    <div style="margin-bottom:8px;"><img src="../<?php echo htmlspecialchars($cta2_icon); ?>" style="width:32px;height:32px;object-fit:contain;border:1px solid #e2e8f0;border-radius:6px;padding:4px;"></div>
                    <?php endif; ?>
                    <input type="file" name="cta_btn2_icon_file" class="form-control" accept="image/png,image/svg+xml,image/jpeg,image/webp">
                    <small style="color:#64748b;font-size:12px;">PNG/SVG/JPG. Maks 512KB. Default: ikon pengaduan.</small>
                </div>
            </div>
        </div>
    </div> <!-- End CTA Section Card -->

    <!-- Contact & Top Bar Settings Card -->
    <div class="card" style="margin-top: 30px;">
        <div class="card-header">
            <h2>Informasi Kontak (Top Bar & Footer)</h2>
        </div>
        
        <div class="form-group">
            <label>Jadwal Operasional (Ikon Jam)</label>
            <input type="text" name="footer_jam" class="form-control" placeholder="Contoh: Senin - Rabu: 08:00 - 17:00..." value="<?php echo htmlspecialchars($current_settings['footer_jam'] ?? 'Senin - Rabu: 08:00 - 17:00 | Kamis: 08:00 - 16.00 | Jumat: 08:00 - 14:00'); ?>">
        </div>
        
        <div style="display: flex; gap: 20px;">
            <div class="form-group" style="flex: 1;">
                <label>Alamat Email (Ikon Amplop)</label>
                <input type="email" name="footer_email" class="form-control" placeholder="Contoh: bapenda@semarangkota.go.id" value="<?php echo htmlspecialchars($current_settings['footer_email'] ?? 'bapenda@semarangkota.go.id'); ?>">
            </div>
            <div class="form-group" style="flex: 1;">
                <label>Nomor Telepon (Ikon Telepon)</label>
                <input type="text" name="footer_phone" class="form-control" placeholder="Contoh: 0822-2122-1400" value="<?php echo htmlspecialchars($current_settings['footer_phone'] ?? '0822-2122-1400'); ?>">
            </div>
        </div>
        
        <div class="form-group" style="margin-top: 20px;">
            <label>Alamat Lengkap</label>
            <textarea name="footer_address" class="form-control" style="min-height: 80px;"><?php echo htmlspecialchars($current_settings['footer_address'] ?? 'Jl. Pemuda No.148, Sekayu, Kec. Semarang Tengah, Kota Semarang, Jawa Tengah 50132'); ?></textarea>
        </div>
    </div> <!-- End Contact Card -->

    <!-- Footer Description & Social Media Card -->
    <div class="card" style="margin-top: 30px;">
        <div class="card-header">
            <h2>Pengaturan Logo & Jajaran Footer Bawah </h2>
        </div>
        
        <div class="form-group" style="padding: 15px; border: 1px dashed #cbd5e1; border-radius: var(--radius-md); margin-bottom: 25px;">
            <label>Gambar Logo Footer (Opsional)</label>
            <?php $f_logo = $current_settings['footer_logo_image'] ?? ($current_settings['logo_image'] ?? 'assets/img/logo.png'); ?>
            <div style="margin-bottom: 10px;">
                <img src="../<?php echo htmlspecialchars($f_logo); ?>" style="height: 60px; object-fit: contain; padding: 10px; border-radius: var(--radius-md); border: 1px solid #e2e8f0; background: #f8fafc;">
            </div>
            <input type="file" name="footer_logo_file" class="form-control" accept="image/jpeg, image/png, image/webp, image/svg+xml">
            <small style="color: #64748b; font-size: 13px; margin-top: 8px; display: inline-block;">Biarkan kosong jika tetap ingin menggunakan logo Header utama di bawah. Format: JPG, PNG, WEBP, SVG.</small>
        </div>

        <div class="form-group">
            <label>Teks Deskripsi Pendek di Bawah Logo Bapenda</label>
            <textarea name="footer_desc" class="form-control" style="min-height: 80px;"><?php echo htmlspecialchars($current_settings['footer_desc'] ?? 'Melayani dengan integritas dan membangun daerah bersama untuk mewujudkan pengelolaan pajak daerah yang transparan dan akuntabel.'); ?></textarea>
        </div>
        
        <hr style="margin: 30px 0; border: 0; border-top: 1px dashed #e2e8f0;">
        <h3 style="font-size: 15px; margin-bottom: 8px; color: #4a5568;">Ikon Sosial Media (Upload Gambar)</h3>
        <p style="color:#64748b; font-size:13px; line-height:1.5; margin-bottom:20px;">Upload gambar ikon untuk setiap platform sosial media (PNG/SVG/JPG, maks 512KB). Kosongkan tautan URL jika ingin menyembunyikan ikon tersebut.</p>
        
        <?php 
        $default_social_labels = ['Facebook', 'Twitter / X', 'YouTube', 'Instagram'];
        for($i=1; $i<=4; $i++): 
            $s_img = $current_settings["social_icon_img_{$i}"] ?? '';
        ?>
        <div style="display: flex; gap: 20px; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 15px;">
            <div style="width: 30px; font-weight: bold; color: var(--primary);">#<?php echo $i; ?></div>
            <div class="form-group" style="flex: 1; margin: 0;">
                <label>Ikon <?php echo $default_social_labels[$i-1]; ?></label>
                <?php if (!empty($s_img)): ?>
                <div style="margin-bottom:6px;"><img src="../<?php echo htmlspecialchars($s_img); ?>" style="width:32px;height:32px;object-fit:contain;border:1px solid #e2e8f0;border-radius:6px;padding:4px;background:#f8fafc;"></div>
                <?php endif; ?>
                <input type="file" name="social_icon_img_file_<?php echo $i; ?>" class="form-control" accept="image/png,image/svg+xml,image/jpeg,image/webp">
            </div>
            <div class="form-group" style="flex: 2; margin: 0;">
                <label>Tautan URL Profil Tujuan</label>
                <input type="text" name="social_link_<?php echo $i; ?>" class="form-control" placeholder="https://" value="<?php echo htmlspecialchars($current_settings["social_link_{$i}"] ?? ''); ?>">
            </div>
        </div>
        <?php endfor; ?>
        
        <hr style="margin: 30px 0; border: 0; border-top: 1px dashed #e2e8f0;">
        
        <div class="form-group">
            <label>Teks Hak Cipta (Copyright)</label>
            <input type="text" name="footer_copyright" class="form-control" value="<?php echo htmlspecialchars($current_settings['footer_copyright'] ?? 'Badan Pendapatan Daerah Kotawaringin Barat'); ?>">
        </div>
    </div> <!-- End Footer Details Card -->
    
    <div style="text-align: right; margin-bottom: 40px;">
        <button type="submit" class="btn" style="font-size: 16px; padding: 12px 30px;"><i class="fas fa-save"></i> Simpan Semua Perubahan</button>
    </div>
</form>

<?php require_once 'layout_footer.php'; ?>
