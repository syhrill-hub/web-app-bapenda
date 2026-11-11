<?php
session_start();
require_once '../config.php';
require_once 'includes/security.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
require_permission('menu_content_popup');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = "Token keamanan tidak valid. Silakan coba lagi.";
    header("Location: popup.php");
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_settings = [];
    $res = $conn->query("SELECT * FROM settings");
    while($r = $res->fetch_assoc()) $current_settings[$r['key_name']] = $r['value'];

    $settings = [
        'popup_enabled'       => isset($_POST['popup_enabled']) ? '1' : '0',
        'popup_title'         => $_POST['popup_title'] ?? '',
        'popup_content'       => $_POST['popup_content'] ?? '',
        'popup_text_enabled'  => isset($_POST['popup_text_enabled']) ? '1' : '0',
        'popup_text_title'    => $_POST['popup_text_title'] ?? '',
        'popup_text_content'  => $_POST['popup_text_content'] ?? '',
        'popup_text_icon'     => $_POST['popup_text_icon'] ?? 'fas fa-bell',
        'popup_text_color'    => $_POST['popup_text_color'] ?? '#0ea5e9',
        'popup_text_btn'      => $_POST['popup_text_btn'] ?? 'Saya Mengerti',
    ];

    // Handle Image Upload for Popup
    if (isset($_FILES['popup_image']) && $_FILES['popup_image']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $max_size = 2 * 1024 * 1024; // 2MB
        $file_tmp = $_FILES['popup_image']['tmp_name'];
        $file_name = $_FILES['popup_image']['name'];
        $file_size = $_FILES['popup_image']['size'];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $file_type = finfo_file($finfo, $file_tmp);
        finfo_close($finfo);

        if (in_array($file_type, $allowed) && $file_size <= $max_size) {
            $upload_dir = '../assets/img/uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_fn = 'popup_' . time() . '.' . $ext;
            if (move_uploaded_file($file_tmp, $upload_dir . $new_fn)) {
                $settings['popup_image'] = 'assets/img/uploads/' . $new_fn;
            } else {
                $message = "Gagal mengunggah gambar.";
            }
        } else {
            $message = "Gambar tidak valid! Maks: 2MB, Format: JPG/PNG/WEBP.";
        }
    }

    foreach ($settings as $key => $val) {
        safe_settings_update($conn, $key, $val);
    }
    
    if (empty($message)) {
        $_SESSION['flash_success'] = "Pengaturan Pengumuman berhasil diperbarui!";
    } else {
        $_SESSION['flash_error'] = $message;
    }
    header("Location: popup.php");
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

// Fetch current
$settings = [];
$result = $conn->query("SELECT * FROM settings");
if ($result) {
    while($row = $result->fetch_assoc()){
        $settings[$row['key_name']] = $row['value'];
    }
}
?>

<?php 
$page_title = "Pengaturan Popup Pengumuman";
require_once 'layout_header.php'; 
?>

<?php if($message): ?>
    <div class="alert" style="background:<?php echo $msg_type === 'error' ? '#fee2e2' : '#d1fae5'; ?>; color:<?php echo $msg_type === 'error' ? '#991b1b' : '#065f46'; ?>; padding:15px; border-radius:12px; margin-bottom:20px;">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <?php echo csrf_token_field(); ?>

    <!-- ================================================ -->
    <!-- POPUP 1: GAMBAR / BANNER -->
    <!-- ================================================ -->
    <div class="card" style="margin-bottom:30px;">
        <div class="card-header">
            <h2><i class="fas fa-image" style="margin-right:8px; color:var(--primary);"></i> Popup Pengumuman (Gambar/Banner)</h2>
            <div>
                <button type="submit" class="btn"><i class="fas fa-save"></i> Simpan Semua</button>
            </div>
        </div>

        <div style="background:var(--bg-light); padding:20px; border-radius:var(--radius-md); margin-bottom:25px; border:1px solid rgba(0,0,0,0.03);">
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                <input type="checkbox" name="popup_enabled" value="1" <?php echo (isset($settings['popup_enabled']) && $settings['popup_enabled'] == '1') ? 'checked' : ''; ?> style="width:20px; height:20px; accent-color:var(--primary);">
                <span style="font-weight:600; color:var(--text-dark); font-size:16px;">Aktifkan Popup Gambar di Halaman Utama</span>
            </label>
        </div>

        <div class="form-group">
            <label>Gambar Banner/Poster Pengumuman (Opsional)</label>
            <div style="margin-bottom:10px;">
                <img src="../<?php echo htmlspecialchars($settings['popup_image'] ?? 'assets/img/hero-bg.png'); ?>" style="width:250px; height:140px; object-fit:cover; border-radius:var(--radius-md); border:1px solid #e2e8f0; box-shadow:var(--shadow-soft);">
            </div>
            <input type="file" name="popup_image" class="form-control" accept="image/jpeg, image/png, image/webp">
            <small style="color:#64748b; font-size:13px; margin-top:8px; display:inline-block;">Format: JPG, PNG, WEBP. Maks: 2MB.</small>
        </div>

        <div class="form-group">
            <label>Judul Pengumuman</label>
            <input type="text" name="popup_title" class="form-control" value="<?php echo htmlspecialchars($settings['popup_title'] ?? 'Pengumuman Penting Bapenda'); ?>">
        </div>

        <div class="form-group">
            <label>Isi Pesan / Deskripsi Singkat</label>
            <textarea name="popup_content" class="form-control" rows="4"><?php echo htmlspecialchars($settings['popup_content'] ?? ''); ?></textarea>
        </div>
    </div>

    <!-- ================================================ -->
    <!-- POPUP 2: NOTIFIKASI PESAN TEKS -->
    <!-- ================================================ -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-comment-alt" style="margin-right:8px; color:#8b5cf6;"></i> Popup Notifikasi Pesan Teks</h2>
        </div>

        <div style="background:var(--bg-light); padding:20px; border-radius:var(--radius-md); margin-bottom:25px; border:1px solid rgba(0,0,0,0.03);">
            <label style="display:flex; align-items:center; gap:10px; cursor:pointer;">
                <input type="checkbox" name="popup_text_enabled" value="1" <?php echo (isset($settings['popup_text_enabled']) && $settings['popup_text_enabled'] == '1') ? 'checked' : ''; ?> style="width:20px; height:20px; accent-color:#8b5cf6;">
                <span style="font-weight:600; color:var(--text-dark); font-size:16px;">Aktifkan Popup Notifikasi Teks di Halaman Utama</span>
            </label>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
            <div class="form-group" style="margin-bottom:0;">
                <label>Judul Pesan</label>
                <input type="text" name="popup_text_title" class="form-control" value="<?php echo htmlspecialchars($settings['popup_text_title'] ?? 'Informasi Penting'); ?>">
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label>Teks Tombol</label>
                <input type="text" name="popup_text_btn" class="form-control" value="<?php echo htmlspecialchars($settings['popup_text_btn'] ?? 'Saya Mengerti'); ?>">
            </div>
        </div>

        <div class="form-group" style="margin-top:20px;">
            <label>Isi Pesan</label>
            <textarea name="popup_text_content" class="form-control" rows="5" placeholder="Tulis isi pesan notifikasi di sini..."><?php echo htmlspecialchars($settings['popup_text_content'] ?? ''); ?></textarea>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
            <div class="form-group" style="margin-bottom:0;">
                <label>Ikon (Font Awesome class)</label>
                <input type="text" name="popup_text_icon" class="form-control" value="<?php echo htmlspecialchars($settings['popup_text_icon'] ?? 'fas fa-bell'); ?>" placeholder="Contoh: fas fa-bell">
                <small style="color:#64748b; font-size:13px; margin-top:6px; display:inline-block;">
                    Contoh: <code>fas fa-bell</code>, <code>fas fa-info-circle</code>, <code>fas fa-exclamation-triangle</code>, <code>fas fa-bullhorn</code>
                </small>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label>Warna Header Popup</label>
                <div style="display:flex; align-items:center; gap:12px;">
                    <input type="color" name="popup_text_color" value="<?php echo htmlspecialchars($settings['popup_text_color'] ?? '#0ea5e9'); ?>" style="width:50px; height:42px; border:1px solid #e2e8f0; border-radius:8px; cursor:pointer; padding:2px;">
                    <input type="text" id="popup_text_color_hex" class="form-control" value="<?php echo htmlspecialchars($settings['popup_text_color'] ?? '#0ea5e9'); ?>" placeholder="#0ea5e9" style="max-width:130px;" readonly>
                </div>
                <small style="color:#64748b; font-size:13px; margin-top:6px; display:inline-block;">Warna latar header dan tombol popup teks.</small>
            </div>
        </div>

        <!-- Preview -->
        <div style="margin-top:25px; padding:20px; background:var(--bg-light); border-radius:var(--radius-md); border:1px solid rgba(0,0,0,0.05);">
            <p style="font-size:13px; font-weight:600; color:#64748b; margin-bottom:12px;">PREVIEW POPUP TEKS:</p>
            <div style="max-width:380px; border-radius:16px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.12);">
                <div id="preview_header" style="background:<?php echo htmlspecialchars($settings['popup_text_color'] ?? '#0ea5e9'); ?>; padding:20px; text-align:center;">
                    <i id="preview_icon" class="<?php echo htmlspecialchars($settings['popup_text_icon'] ?? 'fas fa-bell'); ?>" style="font-size:24px; color:#fff; display:block; margin-bottom:8px;"></i>
                    <strong id="preview_title" style="color:#fff; font-size:16px;"><?php echo htmlspecialchars($settings['popup_text_title'] ?? 'Informasi Penting'); ?></strong>
                </div>
                <div style="padding:16px 20px; background:#fff;">
                    <p id="preview_content" style="color:#64748b; font-size:13px; margin:0 0 14px; line-height:1.6;"><?php echo htmlspecialchars(mb_substr($settings['popup_text_content'] ?? 'Isi pesan akan tampil di sini...', 0, 120)); ?></p>
                    <div id="preview_btn" style="background:<?php echo htmlspecialchars($settings['popup_text_color'] ?? '#0ea5e9'); ?>; color:#fff; text-align:center; padding:10px; border-radius:50px; font-size:13px; font-weight:600;">
                        <?php echo htmlspecialchars($settings['popup_text_btn'] ?? 'Saya Mengerti'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
// Sync color picker <-> text input, dan update preview real-time
(function(){
    var colorInput  = document.querySelector('input[name="popup_text_color"]');
    var hexInput    = document.getElementById('popup_text_color_hex');
    var titleInput  = document.querySelector('input[name="popup_text_title"]');
    var iconInput   = document.querySelector('input[name="popup_text_icon"]');
    var contentArea = document.querySelector('textarea[name="popup_text_content"]');
    var btnInput    = document.querySelector('input[name="popup_text_btn"]');

    var prevHeader  = document.getElementById('preview_header');
    var prevIcon    = document.getElementById('preview_icon');
    var prevTitle   = document.getElementById('preview_title');
    var prevContent = document.getElementById('preview_content');
    var prevBtn     = document.getElementById('preview_btn');

    function updatePreview() {
        var color = colorInput.value;
        hexInput.value = color;
        if (prevHeader) prevHeader.style.background = color;
        if (prevBtn) prevBtn.style.background = color;
        if (prevTitle) prevTitle.textContent = titleInput.value || 'Judul Pesan';
        if (prevContent) prevContent.textContent = (contentArea.value || 'Isi pesan...').substring(0, 120);
        if (prevBtn) prevBtn.textContent = btnInput.value || 'Saya Mengerti';
        if (prevIcon) { prevIcon.className = iconInput.value || 'fas fa-bell'; }
    }

    if (colorInput) colorInput.addEventListener('input', updatePreview);
    if (titleInput) titleInput.addEventListener('input', updatePreview);
    if (iconInput)  iconInput.addEventListener('input', updatePreview);
    if (contentArea) contentArea.addEventListener('input', updatePreview);
    if (btnInput)   btnInput.addEventListener('input', updatePreview);
})();
</script>

<?php require_once 'layout_footer.php'; ?>
