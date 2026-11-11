<?php
session_start();
require_once '../config.php';
require_once 'includes/security.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
require_permission('menu_settings_header');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = "Token keamanan tidak valid. Silakan coba lagi.";
    header("Location: header.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'logo_text_1' => $_POST['logo_text_1'] ?? '',
        'logo_text_2' => $_POST['logo_text_2'] ?? '',
        'header_alert_show' => isset($_POST['header_alert_show']) ? '1' : '0',
        'header_alert_text' => $_POST['header_alert_text'] ?? '',
        'header_alert_color' => $_POST['header_alert_color'] ?? '#f59e0b'
    ];
    
    // Process up to 9 menu items
    for ($i = 1; $i <= 9; $i++) {
        $settings["menu_{$i}_text"] = $_POST["menu_{$i}_text"] ?? '';
        $settings["menu_{$i}_link"] = $_POST["menu_{$i}_link"] ?? '#';
    }
    
    // Process Profile sub-menus
    for ($i = 1; $i <= 5; $i++) {
        $settings["profil_sub_{$i}_text"] = $_POST["profil_sub_{$i}_text"] ?? '';
        $settings["profil_sub_{$i}_link"] = $_POST["profil_sub_{$i}_link"] ?? '#';
    }

    if (isset($_FILES['logo_image']) && $_FILES['logo_image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
        $max_size = 2 * 1024 * 1024; // 2MB
        $file_tmp = $_FILES['logo_image']['tmp_name'];
        $file_name = $_FILES['logo_image']['name'];
        $file_size = $_FILES['logo_image']['size'];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $file_type = finfo_file($finfo, $file_tmp);
        finfo_close($finfo);

        if (in_array($file_type, $allowed) && $file_size <= $max_size) {
            $upload_dir = '../assets/img/uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_fn = 'logo_' . time() . '.' . $ext;
            if (move_uploaded_file($file_tmp, $upload_dir . $new_fn)) {
                $settings['logo_image'] = 'assets/img/uploads/' . $new_fn;
            } else {
                $_SESSION['flash_error'] = "Gagal mengunggah logo.";
            }
        } else {
            $_SESSION['flash_error'] = "Format/Ukuran Logo tidak valid! Maks: 2MB, Format: JPG/PNG/WEBP/SVG.";
        }
    }

    if (isset($_FILES['favicon_image']) && $_FILES['favicon_image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/x-icon', 'image/svg+xml', 'image/vnd.microsoft.icon'];
        $max_size = 500 * 1024; // 500KB
        $file_tmp = $_FILES['favicon_image']['tmp_name'];
        $file_name = $_FILES['favicon_image']['name'];
        $file_size = $_FILES['favicon_image']['size'];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $file_type = finfo_file($finfo, $file_tmp);
        finfo_close($finfo);

        if (in_array($file_type, $allowed) && $file_size <= $max_size) {
            $upload_dir = '../assets/img/uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_fn = 'favicon_' . time() . '.' . $ext;
            if (move_uploaded_file($file_tmp, $upload_dir . $new_fn)) {
                $settings['favicon_image'] = 'assets/img/uploads/' . $new_fn;
            } else {
                $_SESSION['flash_error'] = "Gagal mengunggah favicon.";
            }
        } else {
            $_SESSION['flash_error'] = "Format/Ukuran Favicon tidak valid! Maks: 500KB, Format: JPG/PNG/WEBP/ICO/SVG.";
        }
    }

    foreach ($settings as $key => $val) {
        safe_settings_update($conn, $key, $val);
    }
    
    if (!isset($_SESSION['flash_error'])) {
        $_SESSION['flash_success'] = "Pengaturan Header & Menu berhasil diperbarui!";
    }
    header("Location: header.php");
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

$settings = [];
$result = $conn->query("SELECT * FROM settings");
if ($result) {
    while($row = $result->fetch_assoc()){
        $settings[$row['key_name']] = $row['value'];
    }
}

// Defaults for menu matching user screenshot
$default_menus = ['Beranda', 'Profil', 'Layanan', 'Berita', 'PPID', 'Dokumen', 'Galeri', 'FAQ', 'Kontak'];
$default_profil_subs = ['Tugas dan Fungsi', 'Visi dan Misi', 'Struktur Organisasi', 'Data Pegawai', ''];

$page_title = "Header & Navigasi";
require_once 'layout_header.php'; 
?>

<?php if($message): ?>
    <div class="alert" style="background:<?php echo $msg_type === 'error' ? '#fee2e2' : '#d1fae5'; ?>; color:<?php echo $msg_type === 'error' ? '#991b1b' : '#065f46'; ?>; padding:15px; border-radius:12px; margin-bottom:20px;">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <?php echo csrf_token_field(); ?>
    <div class="card">
        <div class="card-header">
            <h2>Peringatan / Pemberitahuan Atas (Alert Banner)</h2>
            <div>
                <button type="submit" class="btn"><i class="fas fa-save"></i> Simpan Pengaturan</button>
            </div>
        </div>

        <div style="background:var(--bg-light); padding:20px; border-radius:var(--radius-md); margin-bottom:25px; border:1px solid rgba(0,0,0,0.03);">
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                <input type="checkbox" name="header_alert_show" value="1" <?php echo (isset($settings['header_alert_show']) && $settings['header_alert_show'] == '1') ? 'checked' : ''; ?> style="width:20px; height:20px; accent-color:var(--primary);">
                <span style="font-weight:600; color:var(--text-dark); font-size:16px;">Aktifkan Alert Banner di Atas Header</span>
            </label>
        </div>

        <div class="form-group">
            <label>Teks Pemberitahuan</label>
            <input type="text" name="header_alert_text" class="form-control" value="<?php echo htmlspecialchars($settings['header_alert_text'] ?? 'Pemberitahuan: Batas akhir pembayaran PBB-P2 adalah tanggal 30 September.'); ?>">
        </div>

        <div class="form-group">
            <label>Warna Latar Banner</label>
            <div style="display:flex; align-items:center; gap:12px;">
                <input type="color" name="header_alert_color" value="<?php echo htmlspecialchars($settings['header_alert_color'] ?? '#f59e0b'); ?>" style="width:50px; height:42px; border:1px solid #e2e8f0; border-radius:8px; cursor:pointer; padding:2px;">
            </div>
        </div>
    </div>

    <div class="card" style="margin-top: 30px;">
        <div class="card-header">
            <h2>Pengaturan Logo Website</h2>
        </div>
        
        <div class="form-group">
            <label>Gambar Logo</label>
            <div style="margin-bottom: 10px;">
                <img src="../<?php echo htmlspecialchars($settings['logo_image'] ?? 'assets/img/logo.png'); ?>" style="height: 60px; object-fit: contain; padding: 10px; border-radius: var(--radius-md); border: 1px solid #e2e8f0; background: #f8fafc;">
            </div>
            <input type="file" name="logo_image" class="form-control" accept="image/*">
            <small style="color: #64748b; font-size: 13px; margin-top: 8px; display: inline-block;">Format: JPG, PNG, WEBP, SVG. Maks: 2MB. Saran: Gambar berlatar transparan.</small>
        </div>

        <div class="form-group" style="padding: 15px; border: 1px dashed #cbd5e1; border-radius: var(--radius-md); background: #f8fafc;">
            <label>Favicon (Logo Ikon Profil di Tab Browser)</label>
            <div style="margin-bottom: 10px;">
                <?php if(!empty($settings['favicon_image'])): ?>
                <img src="../<?php echo htmlspecialchars($settings['favicon_image']); ?>" style="height: 32px; width: 32px; object-fit: contain; padding: 5px; border-radius: 4px; border: 1px solid #e2e8f0; background: #fff;">
                <?php else: ?>
                <span class="badge" style="background:#e2e8f0;color:#64748b;padding:5px 10px;border-radius:4px;font-size:12px;"><i class="fas fa-image"></i> Belum ada Favicon</span>
                <?php endif; ?>
            </div>
            <input type="file" name="favicon_image" class="form-control" accept="image/jpeg,image/png,image/webp,image/x-icon,image/svg+xml">
            <small style="color: #64748b; font-size: 13px; margin-top: 8px; display: inline-block;">Format: PNG, ICO, JPG, WEBP, SVG. Maks: 500KB. Gambar ini akan muncul di samping tab nama website. Gunakan ukuran presisi kotak (contoh: 64x64 / 128x128px).</small>
        </div>
        
        <div style="display: flex; gap: 20px;">
            <div class="form-group" style="flex: 1;">
                <label>Teks Logo Baris 1 (Utama)</label>
                <input type="text" name="logo_text_1" class="form-control" value="<?php echo htmlspecialchars($settings['logo_text_1'] ?? 'BAPENDA'); ?>">
            </div>
            <div class="form-group" style="flex: 1;">
                <label>Teks Logo Baris 2 (Sub-teks)</label>
                <input type="text" name="logo_text_2" class="form-control" value="<?php echo htmlspecialchars($settings['logo_text_2'] ?? 'Kotawaringin Barat'); ?>">
            </div>
        </div>
    </div>

    <div class="card" style="margin-top: 30px;">
        <div class="card-header">
            <h2>Menu Navigasi Atas</h2>
        </div>
        <p style="color: #64748b; margin-bottom: 25px; line-height: 1.6;">Kosongkan nama menu jika Anda ingin menyembunyikannya. Anda bisa mengatur menu mana saja yang ingin dimunculkan beserta link tujuannya.</p>

        <?php 
        $default_menus = ['Beranda', 'Profil', 'Layanan', 'Berita', 'PPID', 'Dokumen', 'Galeri', 'FAQ', 'Kontak'];
        $default_links = ['index.php', '#', 'index.php#layanan', 'index.php#berita', '#', 'dokumen.php', 'gallery.php', '#', 'index.php#kontak'];
        for ($i = 1; $i <= 9; $i++): 
        ?>
        <div style="display: flex; gap: 20px; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 15px;">
            <div style="width: 30px; font-weight: bold; color: var(--primary);">#<?php echo $i; ?></div>
            <div class="form-group" style="flex: 1; margin: 0;">
                <input type="text" name="menu_<?php echo $i; ?>_text" class="form-control" placeholder="Nama Menu (misal: <?php echo $default_menus[$i-1] ?? 'Menu'; ?>)" value="<?php echo htmlspecialchars($settings["menu_{$i}_text"] ?? (isset($default_menus[$i-1]) ? $default_menus[$i-1] : '')); ?>">
            </div>
            <div class="form-group" style="flex: 2; margin: 0;">
                <input type="text" name="menu_<?php echo $i; ?>_link" class="form-control" placeholder="Tautan Tipe URL/Anchor (misal: index.php#berita)" value="<?php echo htmlspecialchars($settings["menu_{$i}_link"] ?? ($default_links[$i-1] ?? '#')); ?>">
            </div>
        </div>
        <?php endfor; ?>
    </div>

    <div class="card" style="margin-top: 30px;">
        <div class="card-header">
            <h2>Sub-Menu "Profil" (Dropdown)</h2>
        </div>
        <p style="color: #64748b; margin-bottom: 25px; line-height: 1.6;">Item-item di bawah ini akan muncul sebagai *dropdown* (menu turunan) secara otomatis di bawah menu yang bernama <strong>Profil</strong>.</p>

        <?php for ($i = 1; $i <= 5; $i++): ?>
        <div style="display: flex; gap: 20px; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 15px;">
            <div style="width: 30px; font-weight: bold; color: var(--secondary);"><i class="fas fa-level-down-alt" style="margin-left:5px;"></i></div>
            <div class="form-group" style="flex: 1; margin: 0;">
                <input type="text" name="profil_sub_<?php echo $i; ?>_text" class="form-control" placeholder="Sub-menu (misal: <?php echo $default_profil_subs[$i-1] ?? 'Sub-Menu'; ?>)" value="<?php echo htmlspecialchars($settings["profil_sub_{$i}_text"] ?? ($default_profil_subs[$i-1] ?? '')); ?>">
            </div>
            <div class="form-group" style="flex: 2; margin: 0;">
                <input type="text" name="profil_sub_<?php echo $i; ?>_link" class="form-control" placeholder="Link Sub-menu" value="<?php echo htmlspecialchars($settings["profil_sub_{$i}_link"] ?? '#'); ?>">
            </div>
        </div>
        <?php endfor; ?>
    </div>
</form>

<?php require_once 'layout_footer.php'; ?>
