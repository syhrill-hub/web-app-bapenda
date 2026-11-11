<?php
require_once __DIR__ . '/../includes/security_helpers.php';
bapenda_start_secure_session();
require_once '../config.php';
require_once 'includes/security.php';
require_once __DIR__ . '/../includes/upload_helper.php';

if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }
require_permission('menu_content_bupati');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = "Token keamanan tidak valid.";
        header("Location: bupati.php"); exit;
    }

    $settings_to_save = [
        'bupati_nama'       => $_POST['bupati_nama'] ?? '',
        'bupati_jabatan'    => $_POST['bupati_jabatan'] ?? '',
        'wakil_nama'        => $_POST['wakil_nama'] ?? '',
        'wakil_jabatan'     => $_POST['wakil_jabatan'] ?? '',
        'bupati_show'       => isset($_POST['bupati_show']) ? '1' : '0',
    ];

    function handle_photo_upload($field_name, $prefix) {
        if (!bapenda_has_uploaded_file($_FILES[$field_name] ?? [])) {
            return null;
        }
        $upErr = null;
        $saved = bapenda_handle_upload(
            $_FILES[$field_name],
            __DIR__ . '/../assets/img/uploads',
            'assets/img/uploads',
            ['jpg', 'jpeg', 'png', 'webp'],
            3 * 1024 * 1024,
            $prefix,
            $upErr
        );
        if ($saved === null && $upErr) {
            $_SESSION['flash_error'] = $upErr;
        }
        return $saved;
    }

    $bupati_photo = handle_photo_upload('bupati_foto', 'bupati');
    if ($bupati_photo) $settings_to_save['bupati_foto'] = $bupati_photo;

    $wakil_photo = handle_photo_upload('wakil_foto', 'wakil');
    if ($wakil_photo) $settings_to_save['wakil_foto'] = $wakil_photo;

    foreach ($settings_to_save as $key => $val) {
        safe_settings_update($conn, $key, $val);
    }

    if (!isset($_SESSION['flash_error'])) {
        $_SESSION['flash_success'] = "Data Bupati & Wakil Bupati berhasil disimpan!";
    }
    header("Location: bupati.php"); exit;
}

// Flash messages
$flash_msg = ''; $flash_type = '';
if (isset($_SESSION['flash_success'])) { $flash_msg = $_SESSION['flash_success']; $flash_type = 'success'; unset($_SESSION['flash_success']); }
elseif (isset($_SESSION['flash_error'])) { $flash_msg = $_SESSION['flash_error']; $flash_type = 'error'; unset($_SESSION['flash_error']); }

// Load settings
$s = [];
$res = $conn->query("SELECT * FROM settings");
if ($res) while ($row = $res->fetch_assoc()) $s[$row['key_name']] = $row['value'];

$page_title = 'Foto Bupati & Wakil Bupati';
require_once 'layout_header.php';
?>

<?php if ($flash_msg): ?>
<div class="alert <?php echo $flash_type === 'error' ? 'alert-danger' : ''; ?>" style="margin-bottom:20px;">
    <?php echo htmlspecialchars($flash_msg); ?>
</div>
<?php endif; ?>

<form method="POST" action="bupati.php" enctype="multipart/form-data">
    <?php echo csrf_token_field(); ?>

    <div class="card">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <h2><i class="fas fa-user-tie" style="margin-right:8px;"></i>Foto Bupati &amp; Wakil Bupati</h2>
            <button type="submit" class="btn"><i class="fas fa-save" style="margin-right:7px;"></i>Simpan</button>
        </div>

        <p style="color:#64748b;font-size:14px;margin:0 0 24px;">
            Atur foto, nama, dan jabatan Bupati beserta Wakil Bupati yang ditampilkan di sisi kiri dan kanan hero banner halaman utama.
        </p>

        <!-- Toggle show/hide -->
        <div class="form-group" style="display:flex;align-items:center;gap:12px;margin-bottom:28px;padding:16px 20px;background:#f8fafc;border-radius:12px;border:1px solid #e2e8f0;">
            <label class="toggle-switch" style="position:relative;display:inline-block;width:48px;height:26px;flex-shrink:0;">
                <input type="checkbox" name="bupati_show" id="bupati_show" <?php echo ($s['bupati_show'] ?? '1') == '1' ? 'checked' : ''; ?> style="opacity:0;width:0;height:0;">
                <span style="position:absolute;cursor:pointer;inset:0;background:<?php echo ($s['bupati_show'] ?? '1') == '1' ? '#0ea5e9' : '#cbd5e1'; ?>;border-radius:26px;transition:.3s;" id="toggle-bg"></span>
                <span style="position:absolute;content:'';height:20px;width:20px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.3s;transform:<?php echo ($s['bupati_show'] ?? '1') == '1' ? 'translateX(22px)' : 'translateX(0)'; ?>;" id="toggle-knob"></span>
            </label>
            <div>
                <div style="font-weight:600;font-size:14px;color:#1e293b;">Tampilkan Foto di Hero Banner</div>
                <div style="font-size:12px;color:#64748b;">Aktifkan untuk menampilkan foto di halaman utama</div>
            </div>
        </div>

        <!-- Two-column layout -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:32px;">

            <!-- Bupati -->
            <div style="border:1px solid #e0f2fe;border-radius:16px;padding:24px;background:#f0f9ff;">
                <h3 style="color:#0369a1;margin:0 0 18px;font-size:15px;"><i class="fas fa-star" style="margin-right:6px;color:#f59e0b;"></i>Bupati</h3>

                <!-- Photo preview -->
                <div style="text-align:center;margin-bottom:18px;">
                    <div style="width:140px;height:180px;border-radius:14px;overflow:hidden;border:3px solid #0ea5e9;margin:0 auto 10px;background:#e0f2fe;display:flex;align-items:center;justify-content:center;">
                        <?php if (!empty($s['bupati_foto'])): ?>
                        <img src="../<?php echo htmlspecialchars($s['bupati_foto']); ?>" style="width:100%;height:100%;object-fit:cover;" id="prev-bupati">
                        <?php else: ?>
                        <img src="" style="width:100%;height:100%;object-fit:cover;display:none;" id="prev-bupati">
                        <i class="fas fa-user" style="font-size:3rem;color:#bae6fd;" id="icon-bupati"></i>
                        <?php endif; ?>
                    </div>
                    <label for="bupati_foto" style="cursor:pointer;background:#0ea5e9;color:#fff;border:none;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;display:inline-block;">
                        <i class="fas fa-camera" style="margin-right:6px;"></i>Ganti Foto
                    </label>
                    <input type="file" name="bupati_foto" id="bupati_foto" accept="image/*" style="display:none;" onchange="previewPhoto(this,'prev-bupati','icon-bupati')">
                    <div style="font-size:11.5px;color:#64748b;margin-top:6px;">JPG/PNG/WEBP, maks 3MB<br>Saran: foto potrait (3:4)</div>
                </div>

                <div class="form-group">
                    <label style="font-size:13px;font-weight:600;">Nama Lengkap</label>
                    <input type="text" name="bupati_nama" class="form-control" value="<?php echo htmlspecialchars($s['bupati_nama'] ?? ''); ?>" placeholder="Contoh: H. Nurhidayah, S.E., M.M.">
                </div>
                <div class="form-group" style="margin-top:14px;">
                    <label style="font-size:13px;font-weight:600;">Jabatan</label>
                    <input type="text" name="bupati_jabatan" class="form-control" value="<?php echo htmlspecialchars($s['bupati_jabatan'] ?? 'Bupati Kotawaringin Barat'); ?>" placeholder="Contoh: Bupati Kotawaringin Barat">
                </div>
            </div>

            <!-- Wakil Bupati -->
            <div style="border:1px solid #dcfce7;border-radius:16px;padding:24px;background:#f0fdf4;">
                <h3 style="color:#15803d;margin:0 0 18px;font-size:15px;"><i class="fas fa-star" style="margin-right:6px;color:#22c55e;"></i>Wakil Bupati</h3>

                <!-- Photo preview -->
                <div style="text-align:center;margin-bottom:18px;">
                    <div style="width:140px;height:180px;border-radius:14px;overflow:hidden;border:3px solid #22c55e;margin:0 auto 10px;background:#dcfce7;display:flex;align-items:center;justify-content:center;">
                        <?php if (!empty($s['wakil_foto'])): ?>
                        <img src="../<?php echo htmlspecialchars($s['wakil_foto']); ?>" style="width:100%;height:100%;object-fit:cover;" id="prev-wakil">
                        <?php else: ?>
                        <img src="" style="width:100%;height:100%;object-fit:cover;display:none;" id="prev-wakil">
                        <i class="fas fa-user" style="font-size:3rem;color:#86efac;" id="icon-wakil"></i>
                        <?php endif; ?>
                    </div>
                    <label for="wakil_foto" style="cursor:pointer;background:#22c55e;color:#fff;border:none;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;display:inline-block;">
                        <i class="fas fa-camera" style="margin-right:6px;"></i>Ganti Foto
                    </label>
                    <input type="file" name="wakil_foto" id="wakil_foto" accept="image/*" style="display:none;" onchange="previewPhoto(this,'prev-wakil','icon-wakil')">
                    <div style="font-size:11.5px;color:#64748b;margin-top:6px;">JPG/PNG/WEBP, maks 3MB<br>Saran: foto potrait (3:4)</div>
                </div>

                <div class="form-group">
                    <label style="font-size:13px;font-weight:600;">Nama Lengkap</label>
                    <input type="text" name="wakil_nama" class="form-control" value="<?php echo htmlspecialchars($s['wakil_nama'] ?? ''); ?>" placeholder="Contoh: Drs. Ahmadi Riansyah">
                </div>
                <div class="form-group" style="margin-top:14px;">
                    <label style="font-size:13px;font-weight:600;">Jabatan</label>
                    <input type="text" name="wakil_jabatan" class="form-control" value="<?php echo htmlspecialchars($s['wakil_jabatan'] ?? 'Wakil Bupati Kotawaringin Barat'); ?>" placeholder="Contoh: Wakil Bupati Kotawaringin Barat">
                </div>
            </div>

        </div><!-- end grid -->

        <!-- Preview section -->
        <div style="margin-top:28px;padding:18px;background:#1e293b;border-radius:12px;color:#fff;font-size:13px;text-align:center;">
            <i class="fas fa-eye" style="margin-right:6px;color:#38bdf8;"></i>
            Foto akan ditampilkan di <strong>sisi kiri</strong> (Bupati) dan <strong>sisi kanan</strong> (Wakil Bupati) dari banner utama halaman depan.
            <a href="../index.php" target="_blank" rel="noopener noreferrer" style="color:#38bdf8;margin-left:10px;text-decoration:none;"><i class="fas fa-external-link-alt" style="margin-right:4px;"></i>Lihat Halaman Utama</a>
        </div>

        <div style="margin-top:24px;text-align:right;">
            <button type="submit" class="btn"><i class="fas fa-save" style="margin-right:7px;"></i>Simpan Pengaturan</button>
        </div>
    </div>
</form>

<script>
// Live preview foto
function previewPhoto(input, imgId, iconId) {
    if (!input.files || !input.files[0]) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        var img = document.getElementById(imgId);
        img.src = e.target.result;
        img.style.display = 'block';
        var icon = document.getElementById(iconId);
        if (icon) icon.style.display = 'none';
    };
    reader.readAsDataURL(input.files[0]);
}

// Toggle switch styling
var chk = document.getElementById('bupati_show');
if (chk) {
    chk.addEventListener('change', function() {
        document.getElementById('toggle-bg').style.background = this.checked ? '#0ea5e9' : '#cbd5e1';
        document.getElementById('toggle-knob').style.transform = this.checked ? 'translateX(22px)' : 'translateX(0)';
    });
}
</script>

<?php require_once 'layout_footer.php'; ?>
