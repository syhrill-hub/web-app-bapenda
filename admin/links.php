<?php
require_once __DIR__ . '/../includes/security_helpers.php';
bapenda_start_secure_session();
require_once '../config.php';
require_once 'includes/security.php';
require_once __DIR__ . '/../includes/upload_helper.php';

if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }
require_permission('menu_settings_links');

// ── Auto-migrate: add `image` column if missing ─────────────────────────────
$cols = $conn->query("SHOW COLUMNS FROM links LIKE 'image'");
if ($cols && $cols->num_rows === 0) {
    $conn->query("ALTER TABLE links ADD COLUMN image VARCHAR(255) DEFAULT '' AFTER icon_class");
}

$uploads_abs_dir = __DIR__ . '/../assets/uploads';

// ── CSRF check ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = "Token keamanan tidak valid. Silakan coba lagi.";
    header("Location: links.php");
    exit;
}

// Message handler for session flash messages
$message = '';
if (isset($_SESSION['flash_success'])) {
    $message = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// ── Helper: handle image upload ───────────────────────────────────────────────
function handle_link_image($file_key, &$error_msg = null) {
    if (!bapenda_has_uploaded_file($_FILES[$file_key] ?? [])) {
        return null;
    }
    return bapenda_handle_upload(
        $_FILES[$file_key],
        __DIR__ . '/../assets/uploads',
        'assets/uploads',
        ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        2 * 1024 * 1024,
        'link',
        $error_msg,
        ['strip_exif' => true]
    );
}

// ── Handle Remove Image (run before edit action because button shares edit form) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_image_action']) && $_POST['remove_image_action'] === '1') {
    $id = (int)($_POST['link_id'] ?? 0);
    if ($id > 0) {
        $sel = $conn->prepare("SELECT image FROM links WHERE id=?");
        $sel->bind_param("i", $id);
        $sel->execute();
        $row = $sel->get_result()->fetch_assoc();
        $sel->close();
        if (!empty($row['image'])) {
            bapenda_safe_unlink($row['image'], $uploads_abs_dir);
        }
        $upd = $conn->prepare("UPDATE links SET image='' WHERE id=?");
        $upd->bind_param("i", $id);
        $upd->execute();
        $upd->close();
        $_SESSION['flash_success'] = "✅ Gambar tautan berhasil dihapus.";
    }
    header("Location: links.php?edit=" . $id);
    exit;
}

// ── Handle Delete (POST + CSRF) ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $id = (int)($_POST['link_id'] ?? 0);
    if ($id > 0) {
        $sel = $conn->prepare("SELECT image FROM links WHERE id=?");
        $sel->bind_param("i", $id);
        $sel->execute();
        $row = $sel->get_result()->fetch_assoc();
        $sel->close();
        if (!empty($row['image'])) {
            bapenda_safe_unlink($row['image'], $uploads_abs_dir);
        }
        $stmt = $conn->prepare("DELETE FROM links WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['flash_success'] = "✅ Link berhasil dihapus!";
    }
    header("Location: links.php");
    exit;
}

// ── Handle Add ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $title = trim($_POST['title'] ?? '');
    $url   = bapenda_safe_url(trim($_POST['url'] ?? '#'), '#');
    $icon  = preg_replace('/[^a-zA-Z0-9\s_-]/', '', trim($_POST['icon_class'] ?? 'fas fa-link'));
    if ($icon === '') $icon = 'fas fa-link';
    $order = (int)($_POST['order_num'] ?? 0);

    $upload_err = null;
    $image = '';
    if (bapenda_has_uploaded_file($_FILES['link_image'] ?? [])) {
        $uploaded = handle_link_image('link_image', $upload_err);
        if ($uploaded === null) {
            $_SESSION['flash_error'] = $upload_err ?: "Gagal mengunggah gambar tautan.";
            header("Location: links.php");
            exit;
        }
        $image = $uploaded;
    }

    $stmt = $conn->prepare("INSERT INTO links (title, url, icon_class, image, order_num) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $title, $url, $icon, $image, $order);
    $stmt->execute();
    $stmt->close();
    $_SESSION['flash_success'] = "✅ Link \"" . $title . "\" berhasil ditambahkan!";
    header("Location: links.php");
    exit;
}

// ── Handle Edit (save) ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $id    = (int)($_POST['link_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $url   = bapenda_safe_url(trim($_POST['url'] ?? '#'), '#');
    $icon  = preg_replace('/[^a-zA-Z0-9\s_-]/', '', trim($_POST['icon_class'] ?? ''));
    $order = (int)($_POST['order_num'] ?? 0);

    $upload_err = null;
    $new_image = null;
    if (bapenda_has_uploaded_file($_FILES['link_image'] ?? [])) {
        $new_image = handle_link_image('link_image', $upload_err);
        if ($new_image === null) {
            $_SESSION['flash_error'] = $upload_err ?: "Gagal mengunggah gambar tautan.";
            header("Location: links.php?edit=" . $id);
            exit;
        }
    }

    if ($new_image) {
        $old = $conn->prepare("SELECT image FROM links WHERE id=?");
        $old->bind_param("i", $id);
        $old->execute();
        $old_row = $old->get_result()->fetch_assoc();
        $old->close();
        if (!empty($old_row['image'])) {
            bapenda_safe_unlink($old_row['image'], $uploads_abs_dir);
        }
        $stmt = $conn->prepare("UPDATE links SET title=?, url=?, icon_class=?, image=?, order_num=? WHERE id=?");
        $stmt->bind_param("ssssii", $title, $url, $icon, $new_image, $order, $id);
    } else {
        $stmt = $conn->prepare("UPDATE links SET title=?, url=?, icon_class=?, order_num=? WHERE id=?");
        $stmt->bind_param("sssii", $title, $url, $icon, $order, $id);
    }
    $stmt->execute();
    $stmt->close();
    $_SESSION['flash_success'] = "✅ Link berhasil diperbarui!";
    header("Location: links.php");
    exit;
}

// ── Fetch edit target ─────────────────────────────────────────────────────────
$edit_row = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM links WHERE id=?");
    $stmt->bind_param("i", $eid);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res) $edit_row = $res->fetch_assoc();
    $stmt->close();
}

$links = $conn->query("SELECT * FROM links ORDER BY order_num ASC");

$page_title = 'Kelola Link Terkait';
require_once 'layout_header.php';
?>

<style>
.link-img-preview {
    width: 60px; height: 60px;
    object-fit: contain;
    border-radius: 10px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    padding: 4px;
}
.icon-preview-box {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 8px;
    padding: 10px 14px;
    background: #f0f9ff;
    border-radius: 10px;
    border: 1px solid #bae6fd;
    font-size: 13px;
    color: #0369a1;
}
.icon-preview-box i { font-size: 22px; color: var(--primary); }
.upload-zone {
    border: 2px dashed #bae6fd;
    border-radius: 12px;
    padding: 16px 14px;
    text-align: center;
    background: #f0f9ff;
    cursor: pointer;
    transition: 0.2s;
    position: relative;
}
.upload-zone:hover { border-color: var(--primary); background: #e0f2fe; }
.upload-zone input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
    width: 100%;
    height: 100%;
}
.upload-zone .upload-hint { font-size: 12px; color: #64748b; margin-top: 4px; }
.link-table-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.tab-toggle { display: flex; gap: 8px; margin-bottom: 14px; }
.tab-btn {
    padding: 7px 16px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    color: #64748b;
    transition: 0.2s;
}
.tab-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }
.icon-tab, .img-tab { display: none; }
.icon-tab.show, .img-tab.show { display: block; }
</style>

<?php if ($message): ?>
<div class="alert"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
<?php endif; ?>

<?php if (isset($_SESSION['flash_error'])): ?>
<div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['flash_error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['flash_error']); ?></div>
<?php endif; ?>

<!-- ── Add / Edit Form ───────────────────────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <h2>
            <?php if ($edit_row): ?>
            <i class="fas fa-edit" style="color:var(--primary);margin-right:8px;"></i>Edit Tautan: <em><?php echo htmlspecialchars($edit_row['title']); ?></em>
            <?php else: ?>
            <i class="fas fa-plus-circle" style="color:var(--primary);margin-right:8px;"></i>Tambah Tautan Pintas Baru
            <?php endif; ?>
        </h2>
    </div>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="<?php echo $edit_row ? 'edit' : 'add'; ?>">
        <?php if ($edit_row): ?>
        <input type="hidden" name="link_id" value="<?php echo $edit_row['id']; ?>">
        <?php endif; ?>
        <?php echo csrf_token_field(); ?>

        <div style="display:flex; gap:15px; flex-wrap:wrap; align-items:flex-start;">

            <!-- Judul -->
            <div style="flex:2; min-width:200px;" class="form-group">
                <label>Judul Tautan</label>
                <input type="text" name="title" class="form-control"
                       value="<?php echo htmlspecialchars($edit_row['title'] ?? ''); ?>"
                       placeholder="Cth: Bayar PBB Online" required>
            </div>

            <!-- URL -->
            <div style="flex:2; min-width:200px;" class="form-group">
                <label>URL / Link Tujuan</label>
                <input type="text" name="url" class="form-control"
                       value="<?php echo htmlspecialchars($edit_row['url'] ?? ''); ?>"
                       placeholder="https://" required>
            </div>

            <!-- Urutan -->
            <div style="flex:0 0 110px;" class="form-group">
                <label>Urutan</label>
                <input type="number" name="order_num" class="form-control"
                       value="<?php echo $edit_row['order_num'] ?? 0; ?>">
            </div>

        </div>

        <!-- Icon section -->
        <div class="form-group" style="margin-top:10px;">
            <label style="margin-bottom:10px; display:block; font-weight:700;">
                <i class="fas fa-image" style="color:var(--primary);margin-right:6px;"></i>Icon Tampilan
                <span style="font-weight:400; color:#64748b; font-size:13px; margin-left:8px;">— gunakan gambar ATAU kelas FontAwesome</span>
            </label>

            <!-- Tab switcher -->
            <div class="tab-toggle" id="iconTabToggle">
                <button type="button" class="tab-btn active" onclick="switchTab('img')">
                    <i class="fas fa-image" style="margin-right:5px;"></i>Upload Gambar
                </button>
                <button type="button" class="tab-btn" onclick="switchTab('icon')">
                    <i class="fas fa-icons" style="margin-right:5px;"></i>Font Awesome Class
                </button>
            </div>

            <!-- Tab: Upload Gambar -->
            <div class="img-tab show" id="imgTab">
                <?php
                $current_image = $edit_row['image'] ?? '';
                ?>
                <?php if ($edit_row && !empty($current_image)): ?>
                <div style="display:flex; align-items:center; gap:14px; margin-bottom:12px; padding:12px; background:#f8fafc; border-radius:10px; border:1px solid #e2e8f0;">
                    <img src="../<?php echo htmlspecialchars($current_image); ?>" class="link-img-preview" alt="icon">
                    <div>
                        <div style="font-size:13px; font-weight:600; color:#0f172a; margin-bottom:4px;">Gambar saat ini</div>
                        <div style="font-size:11px; color:#94a3b8; margin-bottom:8px;"><?php echo htmlspecialchars($current_image); ?></div>
                        <button type="submit" name="remove_image_action" value="1" class="btn-danger" onclick="return confirm('Hapus gambar ini?')" style="font-size:12px; padding:5px 12px; border:none; border-radius:4px; font-weight:600; cursor:pointer;">
                            <i class="fas fa-trash"></i> Hapus Gambar
                        </button>
                    </div>
                </div>
                <?php endif; ?>
                <label class="upload-zone">
                    <input type="file" name="link_image" accept=".jpg,.jpeg,.png,.gif,.webp" onchange="previewLinkImg(this)">
                    <i class="fas fa-cloud-upload-alt" style="font-size:28px; color:#94a3b8; margin-bottom:6px;"></i>
                    <div style="font-weight:600; color:#374151; font-size:13px;">Klik atau drag gambar ke sini</div>
                    <div class="upload-hint">PNG, JPG, GIF, WebP · Maks 2MB · Rekomendasi: 60×60px atau format square</div>
                </label>
                <div id="imgPreviewNew" style="display:none; margin-top:10px; display:flex; align-items:center; gap:10px; padding:10px; background:#f0fdf4; border-radius:10px; border:1px solid #bbf7d0;">
                    <img id="newImgPreviewEl" src="" style="width:50px; height:50px; object-fit:contain; border-radius:8px;">
                    <span style="font-size:13px; color:#166534; font-weight:600;">Gambar baru dipilih — siap diunggah</span>
                </div>
            </div>

            <!-- Tab: FontAwesome class -->
            <div class="icon-tab" id="iconTab">
                <input type="text" name="icon_class" id="faInput" class="form-control"
                       value="<?php echo htmlspecialchars($edit_row['icon_class'] ?? 'fas fa-link'); ?>"
                       placeholder="fas fa-link"
                       oninput="updateFaPreview(this.value)">
                <div class="icon-preview-box" id="faPreviewBox">
                    <i class="<?php echo htmlspecialchars($edit_row['icon_class'] ?? 'fas fa-link'); ?>" id="faPreviewIcon"></i>
                    <span>Preview icon · <a href="https://fontawesome.com/icons" target="_blank" rel="noopener noreferrer" style="color:var(--primary);">Cari kelas FA</a></span>
                </div>
            </div>
        </div>

        <div style="display:flex; gap:12px; margin-top:20px; flex-wrap:wrap;">
            <button type="submit" class="btn">
                <i class="fas <?php echo $edit_row ? 'fa-save' : 'fa-plus'; ?>" style="margin-right:6px;"></i>
                <?php echo $edit_row ? 'Simpan Perubahan' : 'Tambah Link'; ?>
            </button>
            <?php if ($edit_row): ?>
            <a href="links.php" class="btn-secondary" style="display:inline-flex; align-items:center; padding:14px 22px;">
                <i class="fas fa-times" style="margin-right:6px;"></i>Batal
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- ── List ──────────────────────────────────────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-list" style="color:var(--primary);margin-right:8px;"></i>Daftar Tautan Saat Ini</h2>
    </div>
    <div style="overflow-x: auto;">
        <table>
            <tr>
                <th style="width:80px; text-align:center;">Icon / Gambar</th>
                <th>Judul Tautan</th>
                <th>URL Tujuan</th>
                <th style="width:80px; text-align:center;">Urutan</th>
                <th style="width:130px; text-align:center;">Aksi</th>
            </tr>
            <?php while ($row = $links->fetch_assoc()): ?>
            <tr>
                <td style="text-align:center;">
                    <div class="link-table-icon">
                        <?php if (!empty($row['image'])): ?>
                        <img src="../<?php echo htmlspecialchars($row['image']); ?>"
                             style="width:44px; height:44px; object-fit:contain; border-radius:8px; border:1px solid #e2e8f0; background:#f8fafc; padding:3px;">
                        <?php elseif (!empty($row['icon_class'])): ?>
                        <i class="<?php echo htmlspecialchars($row['icon_class']); ?>"
                           style="font-size:22px; color:var(--primary);"></i>
                        <?php else: ?>
                        <i class="fas fa-link" style="font-size:22px; color:#cbd5e1;"></i>
                        <?php endif; ?>
                    </div>
                </td>
                <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                <td>
                    <a href="<?php echo htmlspecialchars(bapenda_safe_url($row['url'], '#'), ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer"
                       style="color:var(--primary); text-decoration:none; font-size:13px; word-break:break-all;">
                        <?php echo htmlspecialchars(substr($row['url'], 0, 50)) . (strlen($row['url']) > 50 ? '…' : ''); ?>
                    </a>
                </td>
                <td style="text-align:center;"><?php echo (int)$row['order_num']; ?></td>
                <td style="text-align:center;">
                    <a href="?edit=<?php echo (int)$row['id']; ?>" class="btn-action edit">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <form method="POST" action="links.php" style="display:inline;"
                          onsubmit="return confirm(this.dataset.confirm);"
                          data-confirm="Hapus tautan '<?php echo htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8'); ?>'?">
                        <?php echo csrf_token_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="link_id" value="<?php echo (int)$row['id']; ?>">
                        <button type="submit" class="btn-danger" style="font-size:12px; padding:6px 10px; border:none; cursor:pointer;" title="Hapus">
                            <i class="fas fa-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>

<script>
// ── Tab Switcher ──────────────────────────────────────────────────────────────
function switchTab(tab) {
    var btns   = document.querySelectorAll('#iconTabToggle .tab-btn');
    var imgTab = document.getElementById('imgTab');
    var icnTab = document.getElementById('iconTab');

    if (tab === 'img') {
        btns[0].classList.add('active'); btns[1].classList.remove('active');
        imgTab.classList.add('show');    icnTab.classList.remove('show');
    } else {
        btns[1].classList.add('active'); btns[0].classList.remove('active');
        icnTab.classList.add('show');    imgTab.classList.remove('show');
    }
}

// If existing icon_class but no image, default to FA tab
(function() {
    var faVal = document.getElementById('faInput');
    var imgUp = document.querySelector('input[name="link_image"]');
    var hasImg = document.querySelector('.link-img-preview');
    if (faVal && faVal.value && faVal.value.trim() !== 'fas fa-link' && !hasImg) {
        switchTab('icon');
    }
})();

// ── FA Icon live preview ──────────────────────────────────────────────────────
function updateFaPreview(val) {
    var el = document.getElementById('faPreviewIcon');
    if (el) {
        el.className = val;
    }
}

// ── Image upload preview ──────────────────────────────────────────────────────
function previewLinkImg(input) {
    var preview = document.getElementById('imgPreviewNew');
    var img     = document.getElementById('newImgPreviewEl');
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            preview.style.display = 'flex';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php require_once 'layout_footer.php'; ?>
