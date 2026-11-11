<?php
require_once __DIR__ . '/../includes/security_helpers.php';
bapenda_start_secure_session();
require_once '../config.php';
require_once 'includes/security.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
require_permission('menu_content_bantuan');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = "Token keamanan tidak valid. Silakan coba lagi.";
    header("Location: bantuan.php");
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings_to_save = [
        'cta_title'      => $_POST['cta_title'] ?? '',
        'cta_desc'       => $_POST['cta_desc'] ?? '',
        'cta_btn1_text'  => $_POST['cta_btn1_text'] ?? '',
        'cta_btn1_link'  => $_POST['cta_btn1_link'] ?? '',
        'cta_btn2_text'  => $_POST['cta_btn2_text'] ?? '',
        'cta_btn2_link'  => $_POST['cta_btn2_link'] ?? '',
        'wa_number'      => preg_replace('/[^0-9]/', '', $_POST['wa_number'] ?? ''),
        'wa_greeting'    => $_POST['wa_greeting'] ?? '',
    ];

    foreach ($settings_to_save as $key => $val) {
        safe_settings_update($conn, $key, $val);
    }
    $_SESSION['flash_success'] = "Pengaturan Pusat Bantuan (CTA) berhasil disimpan!";
    header("Location: bantuan.php");
    exit;
}

$message = '';
if (isset($_SESSION['flash_success'])) {
    $message = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// Fetch current
$settings = [];
$result = $conn->query("SELECT * FROM settings");
if ($result) {
    while($row = $result->fetch_assoc()){
        $settings[$row['key_name']] = $row['value'];
    }
}

$page_title = "Pusat Bantuan & Aksi (CTA)";
require_once 'layout_header.php'; 
?>

<?php if($message): ?>
    <div class="alert" style="background:#d1fae5; color:#065f46; padding:15px; border-radius:12px; margin-bottom:20px;">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<form method="post">
    <?php echo csrf_token_field(); ?>
    <div class="card">
        <div class="card-header">
            <h2><i class="fab fa-whatsapp" style="color:#25d366;margin-right:8px;"></i>Tombol "Hubungi Kami" (FAB WhatsApp)</h2>
            <div>
                <button type="submit" class="btn"><i class="fas fa-save"></i> Simpan Semua</button>
            </div>
        </div>

        <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:16px 20px; margin-bottom:20px;">
            <p style="margin:0; font-size:13px; color:#166534;">
                <i class="fas fa-info-circle" style="margin-right:6px;"></i>
                Isi nomor WhatsApp di bawah untuk mengaktifkan tombol <strong>"Hubungi Kami"</strong> di pojok kanan bawah website. Tombol akan langsung membuka chat WhatsApp dengan pesan awal yang sudah kamu atur.
                Warna tombol mengikuti tema warna yang sudah dipilih di <a href="theme.php" style="color:var(--primary);font-weight:600;">Kustomisasi Tema</a>.
            </p>
        </div>

        <div style="display:flex; gap:20px; flex-wrap:wrap;">
            <div class="form-group" style="flex:1; min-width:220px;">
                <label>
                    <i class="fab fa-whatsapp" style="color:#25d366;margin-right:5px;"></i>
                    Nomor WhatsApp (tanpa +62 atau 0 di depan)
                </label>
                <div style="display:flex; align-items:center; gap:0;">
                    <span style="background:#e2e8f0; border:1px solid #e2e8f0; border-right:none; padding:13px 14px; border-radius:var(--radius-md) 0 0 var(--radius-md); font-weight:700; color:#374151; font-size:14px;">+62</span>
                    <input type="text" name="wa_number"
                           class="form-control"
                           style="border-radius:0 var(--radius-md) var(--radius-md) 0; border-left:none;"
                           value="<?php echo htmlspecialchars($settings['wa_number'] ?? ''); ?>"
                           placeholder="8115551234"
                           inputmode="numeric">
                </div>
                <small style="color:#64748b; font-size:12px; margin-top:6px; display:block;">
                    Contoh: <code>81215551234</code> → akan dihubungi ke <code>+6281215551234</code>
                </small>
            </div>
            <div class="form-group" style="flex:2; min-width:280px;">
                <label>
                    <i class="fas fa-comment-dots" style="color:var(--primary);margin-right:5px;"></i>
                    Pesan Awal WhatsApp (Greeting)
                </label>
                <input type="text" name="wa_greeting"
                       class="form-control"
                       value="<?php echo htmlspecialchars($settings['wa_greeting'] ?? 'Halo Bapenda Kobar, saya ingin bertanya mengenai...'); ?>"
                       placeholder="Halo, saya ingin bertanya...">
                <small style="color:#64748b; font-size:12px; margin-top:6px; display:block;">
                    Pesan ini otomatis terisi di chat WA saat pengguna klik tombol "Hubungi Kami".
                </small>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Call-to-Action (Bagian Bawah Website)</h2>
        </div>

        <div class="form-group">
            <label>Judul Utama</label>
            <input type="text" name="cta_title" class="form-control" value="<?php echo htmlspecialchars($settings['cta_title'] ?? 'Butuh Bantuan atau Informasi?'); ?>">
        </div>
        
        <div class="form-group">
            <label>Deskripsi Singkat</label>
            <textarea name="cta_desc" class="form-control" rows="3"><?php echo htmlspecialchars($settings['cta_desc'] ?? 'Hubungi kami untuk informasi lebih lanjut mengenai pajak daerah, retribusi, dan layanan BAPENDA lainnya.'); ?></textarea>
        </div>
        
        <div style="display: flex; gap: 20px; flex-wrap:wrap;">
            <div class="form-group" style="flex: 1; min-width:200px;">
                <label>Teks Tombol 1 (Kontak)</label>
                <input type="text" name="cta_btn1_text" class="form-control" value="<?php echo htmlspecialchars($settings['cta_btn1_text'] ?? 'Kontak Kami'); ?>">
            </div>
            <div class="form-group" style="flex: 1; min-width:200px;">
                <label>Link Tombol 1</label>
                <input type="text" name="cta_btn1_link" class="form-control" value="<?php echo htmlspecialchars($settings['cta_btn1_link'] ?? '#kontak'); ?>">
            </div>
        </div>

        <div style="display: flex; gap: 20px; flex-wrap:wrap;">
            <div class="form-group" style="flex: 1; min-width:200px;">
                <label>Teks Tombol 2 (Pengaduan)</label>
                <input type="text" name="cta_btn2_text" class="form-control" value="<?php echo htmlspecialchars($settings['cta_btn2_text'] ?? 'Laporan Pengaduan'); ?>">
            </div>
            <div class="form-group" style="flex: 1; min-width:200px;">
                <label>Link Tombol 2</label>
                <input type="text" name="cta_btn2_link" class="form-control" value="<?php echo htmlspecialchars($settings['cta_btn2_link'] ?? '#'); ?>">
            </div>
        </div>
    </div>
</form>


<?php require_once 'layout_footer.php'; ?>
