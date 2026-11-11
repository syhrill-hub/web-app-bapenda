<?php
session_start();
require_once '../config.php';
require_once 'includes/security.php';
if (!isset($_SESSION['admin_id'])) { header("Location: login.php"); exit; }
require_permission('menu_content_services');

// ── Auto-migrate: add `image` column if missing ─────────────────────────────
$cols = $conn->query("SHOW COLUMNS FROM services LIKE 'image'");
if ($cols && $cols->num_rows === 0) {
    $conn->query("ALTER TABLE services ADD COLUMN image VARCHAR(255) DEFAULT '' AFTER icon");
}

// ── CSRF check ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = "Token keamanan tidak valid. Silakan coba lagi.";
    header("Location: services.php");
    exit;
}

// Message handler for session flash messages
$message = '';
if (isset($_SESSION['flash_success'])) {
    $message = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// ── Helper: handle image upload ───────────────────────────────────────────────
function handle_service_image($file_key) {
    if (empty($_FILES[$file_key]['name'])) return null;
    $file = $_FILES[$file_key];
    $allowed = ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml'];
    if (!in_array($file['type'], $allowed)) return null;
    if ($file['size'] > 2 * 1024 * 1024) return null;

    $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
    $name = 'svc_' . uniqid() . '.' . $ext;
    $dest = '../assets/uploads/' . $name;
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return 'assets/uploads/' . $name;
    }
    return null;
}

// ── Handle Add ────────────────────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] == 'add') {
    $title  = trim($_POST['title']);
    $desc   = trim($_POST['description']);
    $icon   = trim($_POST['icon'] ?? 'fas fa-concierge-bell');
    $url    = trim($_POST['url'] ?? '#');
    $order  = (int)($_POST['order_num'] ?? 1);
    $image  = handle_service_image('svc_image') ?? '';

    $stmt = $conn->prepare("INSERT INTO services (title, description, icon, image, url, order_num) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssi", $title, $desc, $icon, $image, $url, $order);
    $stmt->execute();
    $_SESSION['flash_success'] = "✅ Layanan <strong>" . htmlspecialchars($title) . "</strong> berhasil ditambahkan!";
    header("Location: services.php");
    exit;
}

// ── Handle Edit (save) ────────────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] == 'edit') {
    $id    = (int)$_POST['svc_id'];
    $title = trim($_POST['title']);
    $desc  = trim($_POST['description']);
    $icon  = trim($_POST['icon'] ?? '');
    $url   = trim($_POST['url'] ?? '#');
    $order = (int)($_POST['order_num'] ?? 1);

    $new_image = handle_service_image('svc_image');

    if ($new_image) {
        $old = $conn->prepare("SELECT image FROM services WHERE id=?");
        $old->bind_param("i", $id);
        $old->execute();
        $old_row = $old->get_result()->fetch_assoc();
        if (!empty($old_row['image']) && file_exists('../' . $old_row['image'])) {
            @unlink('../' . $old_row['image']);
        }
        $stmt = $conn->prepare("UPDATE services SET title=?, description=?, icon=?, image=?, url=?, order_num=? WHERE id=?");
        $stmt->bind_param("sssssii", $title, $desc, $icon, $new_image, $url, $order, $id);
    } else {
        $stmt = $conn->prepare("UPDATE services SET title=?, description=?, icon=?, url=?, order_num=? WHERE id=?");
        $stmt->bind_param("ssssii", $title, $desc, $icon, $url, $order, $id);
    }
    $stmt->execute();
    $_SESSION['flash_success'] = "✅ Layanan berhasil diperbarui!";
    header("Location: services.php");
    exit;
}

// ── Handle Remove Image ───────────────────────────────────────────────────────
if (isset($_POST['remove_image_action']) && $_POST['remove_image_action'] == '1') {
    $id = (int)$_POST['svc_id'];
    $row = $conn->query("SELECT image FROM services WHERE id=$id")->fetch_assoc();
    if (!empty($row['image']) && file_exists('../' . $row['image'])) {
        @unlink('../' . $row['image']);
    }
    $conn->query("UPDATE services SET image='' WHERE id=$id");
    header("Location: services.php?edit=" . $id);
    exit;
}

// ── Handle Delete ─────────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $row = $conn->query("SELECT image FROM services WHERE id=$id")->fetch_assoc();
    if (!empty($row['image']) && file_exists('../' . $row['image'])) {
        @unlink('../' . $row['image']);
    }
    $stmt = $conn->prepare("DELETE FROM services WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $_SESSION['flash_success'] = "✅ Layanan berhasil dihapus!";
    header("Location: services.php");
    exit;
}

// ── Fetch edit target ─────────────────────────────────────────────────────────
$edit_row = null;
if (isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $res = $conn->query("SELECT * FROM services WHERE id=$eid");
    if ($res) $edit_row = $res->fetch_assoc();
}

$services = $conn->query("SELECT * FROM services ORDER BY order_num ASC");

$page_title = 'Kelola Layanan Utama';
require_once 'layout_header.php';
?>

<style>
.svc-img-preview {
    width: 64px; height: 64px;
    object-fit: contain;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    padding: 6px;
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
.icon-preview-box i { font-size: 26px; color: var(--primary); }
.upload-zone {
    border: 2px dashed #bae6fd;
    border-radius: 12px;
    padding: 18px 14px;
    text-align: center;
    background: #f0f9ff;
    cursor: pointer;
    transition: 0.2s;
    position: relative;
    display: block;
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
.img-preview-new-row { display: none; align-items: center; gap: 10px; padding: 10px; background: #f0fdf4; border-radius: 10px; border: 1px solid #bbf7d0; margin-top: 10px; }
</style>

<?php if ($message): ?>
<div class="alert"><?php echo $message; ?></div>
<?php endif; ?>

<?php if (isset($_SESSION['flash_error'])): ?>
<div class="alert alert-danger"><?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?></div>
<?php endif; ?>

<!-- ── Add / Edit Form ──────────────────────────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <h2>
            <?php if ($edit_row): ?>
            <i class="fas fa-edit" style="color:var(--primary);margin-right:8px;"></i>Edit Layanan: <em><?php echo htmlspecialchars($edit_row['title']); ?></em>
            <?php else: ?>
            <i class="fas fa-plus-circle" style="color:var(--primary);margin-right:8px;"></i>Tambah Layanan Utama Baru
            <?php endif; ?>
        </h2>
    </div>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="<?php echo $edit_row ? 'edit' : 'add'; ?>">
        <?php if ($edit_row): ?>
        <input type="hidden" name="svc_id" value="<?php echo $edit_row['id']; ?>">
        <?php endif; ?>
        <?php echo csrf_token_field(); ?>

        <!-- Row 1: Judul + URL -->
        <div style="display:flex; gap:15px; flex-wrap:wrap;">
            <div class="form-group" style="flex:2; min-width:220px;">
                <label>Judul Layanan</label>
                <input type="text" name="title" class="form-control"
                       value="<?php echo htmlspecialchars($edit_row['title'] ?? ''); ?>"
                       placeholder="Cth: E-SPTPD" required>
            </div>
            <div class="form-group" style="flex:3; min-width:240px;">
                <label>Link URL Tujuan</label>
                <input type="text" name="url" class="form-control"
                       value="<?php echo htmlspecialchars($edit_row['url'] ?? '#'); ?>"
                       placeholder="https:// atau #" required>
            </div>
            <div class="form-group" style="flex:0 0 110px;">
                <label>Urutan</label>
                <input type="number" name="order_num" class="form-control"
                       value="<?php echo $edit_row['order_num'] ?? 1; ?>" required>
            </div>
        </div>

        <!-- Deskripsi -->
        <div class="form-group">
            <label>Deskripsi Singkat</label>
            <textarea name="description" class="form-control" style="min-height:80px;" required><?php echo htmlspecialchars($edit_row['description'] ?? ''); ?></textarea>
        </div>

        <!-- Icon section -->
        <div class="form-group" style="margin-top:6px;">
            <label style="margin-bottom:10px; display:block; font-weight:700;">
                <i class="fas fa-image" style="color:var(--primary);margin-right:6px;"></i>Icon Layanan
                <span style="font-weight:400; color:#64748b; font-size:13px; margin-left:8px;">— gunakan gambar ATAU kelas FontAwesome</span>
            </label>

            <!-- Tab switcher -->
            <div class="tab-toggle" id="svcTabToggle">
                <button type="button" class="tab-btn active" onclick="svcSwitchTab('img')">
                    <i class="fas fa-image" style="margin-right:5px;"></i>Upload Gambar
                </button>
                <button type="button" class="tab-btn" onclick="svcSwitchTab('icon')">
                    <i class="fas fa-icons" style="margin-right:5px;"></i>Font Awesome Class
                </button>
            </div>

            <!-- Tab: Upload Gambar -->
            <div class="img-tab show" id="svcImgTab">
                <?php $cur_img = $edit_row['image'] ?? ''; ?>
                <?php if ($edit_row && !empty($cur_img)): ?>
                <div style="display:flex; align-items:center; gap:14px; margin-bottom:12px; padding:12px; background:#f8fafc; border-radius:10px; border:1px solid #e2e8f0;">
                    <img src="../<?php echo htmlspecialchars($cur_img); ?>" class="svc-img-preview" alt="icon">
                    <div>
                        <div style="font-size:13px; font-weight:600; color:#0f172a; margin-bottom:4px;">Gambar icon saat ini</div>
                        <div style="font-size:11px; color:#94a3b8; margin-bottom:8px;"><?php echo htmlspecialchars($cur_img); ?></div>
                        <button type="submit" name="remove_image_action" value="1" class="btn-danger" onclick="return confirm('Hapus gambar icon ini?')" style="font-size:12px; padding:5px 12px; border:none; border-radius:4px; font-weight:600; cursor:pointer;">
                            <i class="fas fa-trash"></i> Hapus Gambar
                        </button>
                    </div>
                </div>
                <?php endif; ?>
                <label class="upload-zone">
                    <input type="file" name="svc_image" accept="image/*" onchange="svcPreviewImg(this)">
                    <i class="fas fa-cloud-upload-alt" style="font-size:30px; color:#94a3b8; margin-bottom:6px; display:block;"></i>
                    <div style="font-weight:600; color:#374151; font-size:13px;">Klik atau drag gambar ke sini</div>
                    <div class="upload-hint">PNG, JPG, GIF, WebP, SVG · Maks 2MB · Rekomendasi: 80×80px format square</div>
                </label>
                <div id="svcImgPreviewNew" class="img-preview-new-row">
                    <img id="svcNewImgEl" src="" style="width:52px; height:52px; object-fit:contain; border-radius:10px;">
                    <span style="font-size:13px; color:#166534; font-weight:600;">Gambar baru dipilih — siap diunggah</span>
                </div>
            </div>

            <!-- Tab: FontAwesome class -->
            <div class="icon-tab" id="svcIconTab">
                <input type="text" name="icon" id="svcFaInput" class="form-control"
                       value="<?php echo htmlspecialchars($edit_row['icon'] ?? 'fas fa-concierge-bell'); ?>"
                       placeholder="fas fa-concierge-bell"
                       oninput="svcUpdateFaPreview(this.value)">
                <div class="icon-preview-box" id="svcFaPreviewBox">
                    <i class="<?php echo htmlspecialchars($edit_row['icon'] ?? 'fas fa-concierge-bell'); ?>" id="svcFaPreviewIcon"></i>
                    <span>Preview icon · <a href="https://fontawesome.com/icons" target="_blank" style="color:var(--primary);">Cari kelas Font Awesome</a></span>
                </div>
            </div>
        </div>

        <div style="display:flex; gap:12px; margin-top:20px; flex-wrap:wrap;">
            <button type="submit" class="btn">
                <i class="fas <?php echo $edit_row ? 'fa-save' : 'fa-plus'; ?>" style="margin-right:6px;"></i>
                <?php echo $edit_row ? 'Simpan Perubahan' : 'Tambah Layanan'; ?>
            </button>
            <?php if ($edit_row): ?>
            <a href="services.php" class="btn-secondary" style="display:inline-flex; align-items:center; padding:14px 22px;">
                <i class="fas fa-times" style="margin-right:6px;"></i>Batal
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- ── List ──────────────────────────────────────────────────────────────────── -->
<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-list" style="color:var(--primary);margin-right:8px;"></i>Daftar Layanan Saat Ini</h2>
    </div>
    <div style="overflow-x: auto;">
        <table>
            <tr>
                <th style="width:80px; text-align:center;">Icon</th>
                <th>Detail Layanan</th>
                <th>URL Tujuan</th>
                <th style="width:80px; text-align:center;">Urutan</th>
                <th style="width:130px; text-align:center;">Aksi</th>
            </tr>
            <?php while ($row = $services->fetch_assoc()): ?>
            <tr>
                <td style="text-align:center;">
                    <?php if (!empty($row['image'])): ?>
                    <img src="../<?php echo htmlspecialchars($row['image']); ?>"
                         style="width:48px; height:48px; object-fit:contain; border-radius:10px; border:1px solid #e2e8f0; background:#f8fafc; padding:4px;">
                    <?php elseif (!empty($row['icon'])): ?>
                    <i class="<?php echo htmlspecialchars($row['icon']); ?>"
                       style="font-size:26px; color:var(--accent);"></i>
                    <?php else: ?>
                    <i class="fas fa-concierge-bell" style="font-size:26px; color:#cbd5e1;"></i>
                    <?php endif; ?>
                </td>
                <td>
                    <strong style="color:var(--primary); font-size:15px;"><?php echo htmlspecialchars($row['title']); ?></strong><br>
                    <p style="font-size:13px; color:#64748b; margin-top:4px; margin-bottom:0;"><?php echo htmlspecialchars($row['description']); ?></p>
                </td>
                <td>
                    <a href="<?php echo htmlspecialchars($row['url']); ?>" target="_blank"
                       style="color:var(--primary); text-decoration:none; font-size:13px; word-break:break-all;">
                        <?php echo htmlspecialchars(substr($row['url'], 0, 45)) . (strlen($row['url']) > 45 ? '…' : ''); ?>
                    </a>
                </td>
                <td style="text-align:center; font-weight:700;"><?php echo (int)$row['order_num']; ?></td>
                <td style="text-align:center;">
                    <a href="?edit=<?php echo $row['id']; ?>" class="btn-action edit">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="?delete=<?php echo $row['id']; ?>" class="btn-danger"
                       onclick="return confirm('Hapus layanan \'<?php echo addslashes($row['title']); ?>\'?')"
                       style="font-size:12px; padding:6px 10px;">
                        <i class="fas fa-trash"></i>
                    </a>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>

<script>
// ── Tab Switcher ──────────────────────────────────────────────────────────────
function svcSwitchTab(tab) {
    var btns   = document.querySelectorAll('#svcTabToggle .tab-btn');
    var imgTab = document.getElementById('svcImgTab');
    var icnTab = document.getElementById('svcIconTab');

    if (tab === 'img') {
        btns[0].classList.add('active');  btns[1].classList.remove('active');
        imgTab.classList.add('show');     icnTab.classList.remove('show');
    } else {
        btns[1].classList.add('active');  btns[0].classList.remove('active');
        icnTab.classList.add('show');     imgTab.classList.remove('show');
    }
}

// If editing and has icon (non-default) but no image, default to FA tab
(function() {
    var faVal  = document.getElementById('svcFaInput');
    var hasImg = document.querySelector('.svc-img-preview');
    var defaultIcons = ['fas fa-concierge-bell', 'fas fa-file-invoice', ''];
    if (faVal && !hasImg && !defaultIcons.includes(faVal.value.trim())) {
        svcSwitchTab('icon');
    }
})();

// ── FA Icon live preview ──────────────────────────────────────────────────────
function svcUpdateFaPreview(val) {
    var el = document.getElementById('svcFaPreviewIcon');
    if (el) el.className = val;
}

// ── Image upload preview ──────────────────────────────────────────────────────
function svcPreviewImg(input) {
    var preview = document.getElementById('svcImgPreviewNew');
    var img     = document.getElementById('svcNewImgEl');
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
