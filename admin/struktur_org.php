<?php
session_start();
require_once '../config.php';
require_once 'includes/security.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
require_permission('menu_content_struktur');

// Auto-create table structure for struktur_org setting (uses existing settings table)
// No new table needed.

$message = '';
$message_type = '';

// Handle DELETE (remove image)
if (isset($_GET['hapus_gambar'])) {
    if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = "Token keamanan tidak valid.";
    } else {
        // Get current image path
        $stmt = $conn->prepare("SELECT value FROM settings WHERE key_name = 'struktur_org_image'");
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $old_file = '../' . $row['value'];
            if (file_exists($old_file)) @unlink($old_file);
        }
        safe_settings_update($conn, 'struktur_org_image', '');
        $_SESSION['flash_success'] = "Gambar struktur organisasi berhasil dihapus.";
    }
    header("Location: struktur_org.php");
    exit;
}

// Handle UPLOAD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $message = "Token keamanan tidak valid.";
        $message_type = "error";
    } else {
        if (isset($_FILES['diagram']) && $_FILES['diagram']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../assets/uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $ext = strtolower(pathinfo($_FILES['diagram']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                // Delete old file
                $stmt = $conn->prepare("SELECT value FROM settings WHERE key_name = 'struktur_org_image'");
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    if (!empty($row['value'])) {
                        $old_file = '../' . $row['value'];
                        if (file_exists($old_file)) @unlink($old_file);
                    }
                }

                $new_fn = 'struktur_org_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['diagram']['tmp_name'], $upload_dir . $new_fn)) {
                    $image_url = 'assets/uploads/' . $new_fn;
                    if (safe_settings_update($conn, 'struktur_org_image', $image_url)) {
                        $_SESSION['flash_success'] = "Diagram struktur organisasi berhasil diperbarui!";
                    } else {
                        $_SESSION['flash_error'] = "Gagal menyimpan ke database.";
                    }
                } else {
                    $_SESSION['flash_error'] = "Gagal memindahkan file. Periksa izin folder uploads.";
                }
            } else {
                $_SESSION['flash_error'] = "Format file tidak valid. Gunakan JPG, PNG, atau WEBP.";
            }
        } else {
            $_SESSION['flash_error'] = "Tidak ada file yang dipilih untuk diunggah.";
        }
        header("Location: struktur_org.php");
        exit;
    }
}

// Get current image
$current_image = '';
$stmt = $conn->prepare("SELECT value FROM settings WHERE key_name = 'struktur_org_image'");
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) $current_image = $row['value'];

// Flash messages
if (isset($_SESSION['flash_success'])) {
    $message = $_SESSION['flash_success'];
    $message_type = 'success';
    unset($_SESSION['flash_success']);
} elseif (isset($_SESSION['flash_error'])) {
    $message = $_SESSION['flash_error'];
    $message_type = 'error';
    unset($_SESSION['flash_error']);
}

$page_title = "Struktur Organisasi";
require_once 'layout_header.php';
?>

<?php if ($message): ?>
<div class="alert <?php echo $message_type === 'error' ? 'alert-danger' : ''; ?>" style="
    background: <?php echo $message_type === 'error' ? '#fee2e2' : '#d1fae5'; ?>;
    color: <?php echo $message_type === 'error' ? '#991b1b' : '#065f46'; ?>;
    padding: 16px 20px; border-radius: 12px; margin-bottom: 25px;
    border-left: 4px solid <?php echo $message_type === 'error' ? '#ef4444' : '#10b981'; ?>;">
    <i class="fas <?php echo $message_type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>" style="margin-right:8px;"></i>
    <?php echo htmlspecialchars($message); ?>
</div>
<?php endif; ?>

<div class="dashboard-header" style="margin-bottom:25px;">
    <h2 style="font-size:22px; color:var(--text-dark);">
        <i class="fas fa-sitemap" style="color:var(--primary); margin-right:10px;"></i>
        Diagram Struktur Organisasi
    </h2>
    <p style="color:#64748b; margin-top:5px;">
        Upload gambar diagram struktur organisasi. Gambar akan ditampilkan secara penuh di halaman publik.
        <br>URL halaman publik: <a href="../struktur_organisasi.php" target="_blank" style="color:var(--primary); font-weight:600;">struktur_organisasi.php</a>
    </p>
</div>

<!-- Current Image Preview -->
<div class="card" style="margin-bottom:25px;">
    <div class="card-header">
        <h2><i class="fas fa-image" style="margin-right:8px;"></i>Diagram Saat Ini</h2>
    </div>
    <?php if (!empty($current_image)): ?>
    <div style="text-align:center; background:#f8fafc; border-radius:12px; padding:20px; margin-bottom:20px; border:2px dashed #e2e8f0;">
        <img src="../<?php echo htmlspecialchars($current_image); ?>" alt="Struktur Organisasi"
             style="max-width:100%; max-height:500px; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,0.08); object-fit:contain;">
    </div>
    <div style="text-align:center;">
        <a href="struktur_org.php?hapus_gambar=1&csrf_token=<?php echo generate_csrf_token(); ?>"
           class="btn-danger"
           onclick="return confirm('Hapus gambar struktur organisasi ini?');"
           style="display:inline-flex; align-items:center; gap:8px;">
            <i class="fas fa-trash"></i> Hapus Gambar
        </a>
    </div>
    <?php else: ?>
    <div style="text-align:center; padding:50px 20px; background:#f8fafc; border-radius:12px; border:2px dashed #e2e8f0; color:#94a3b8;">
        <i class="fas fa-image" style="font-size:3rem; display:block; margin-bottom:15px; color:#cbd5e1;"></i>
        <p style="margin:0; font-size:15px;">Belum ada gambar yang diunggah.<br>
        <small>Silakan upload diagram di bawah ini.</small></p>
    </div>
    <?php endif; ?>
</div>

<!-- Upload Form -->
<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-upload" style="margin-right:8px;"></i><?php echo !empty($current_image) ? 'Ganti Diagram' : 'Upload Diagram'; ?></h2>
    </div>
    <p style="color:#64748b; margin-bottom:22px; line-height:1.6;">
        Upload file gambar diagram struktur organisasi dalam format <strong>JPG, PNG, atau WEBP</strong>.
        Gunakan gambar beresolusi tinggi (minimal 1200px lebar) agar terlihat tajam.
        <?php if (!empty($current_image)): ?>
        <br><span style="color:#f59e0b;"><i class="fas fa-info-circle"></i> Mengunggah gambar baru akan menggantikan gambar yang ada saat ini.</span>
        <?php endif; ?>
    </p>

    <form method="POST" enctype="multipart/form-data">
        <?php echo csrf_token_field(); ?>
        <div class="form-group">
            <label style="font-weight:600; color:#4a5568; font-size:14px;">
                File Gambar Diagram <span style="color:red;">*</span>
            </label>
            <input type="file" name="diagram" class="form-control" accept="image/*" required
                   style="padding:12px; cursor:pointer;"
                   onchange="previewImage(this)">
            <small style="color:#94a3b8; margin-top:6px; display:block;">
                Format: JPG, PNG, WEBP &nbsp;|&nbsp; Ukuran maks: <?php echo ini_get('upload_max_filesize'); ?>
            </small>
        </div>

        <!-- Live Preview -->
        <div id="preview-box" style="display:none; text-align:center; background:#f8fafc;
             border-radius:12px; padding:15px; border:2px dashed #bae6fd; margin-bottom:20px;">
            <p style="margin:0 0 10px 0; font-size:13px; color:#64748b; font-weight:600;">
                <i class="fas fa-eye" style="margin-right:5px;"></i>Preview Sebelum Upload:
            </p>
            <img id="preview-img" src="" alt="Preview"
                 style="max-width:100%; max-height:400px; border-radius:8px; box-shadow:0 4px 15px rgba(0,0,0,0.08);">
        </div>

        <button type="submit" class="btn">
            <i class="fas fa-cloud-upload-alt" style="margin-right:8px;"></i>
            <?php echo !empty($current_image) ? 'Ganti & Simpan Diagram' : 'Upload Diagram'; ?>
        </button>
    </form>
</div>

<script>
function previewImage(input) {
    const box = document.getElementById('preview-box');
    const img = document.getElementById('preview-img');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            box.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        box.style.display = 'none';
    }
}
</script>

<?php require_once 'layout_footer.php'; ?>
