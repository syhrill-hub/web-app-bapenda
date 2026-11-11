<?php
session_start();
require_once '../config.php';
require_once 'includes/security.php';

require_permission('menu_content_news');

/**
 * Safely deletes a local uploaded news image inside assets/ without allowing path traversal.
 */
function delete_news_image_file($image_url) {
    $rel = ltrim(str_replace('\\', '/', trim((string)$image_url)), '/');
    if ($rel === '' || strpos($rel, '..') !== false) {
        return;
    }
    $assets_root = realpath(__DIR__ . '/../assets');
    $candidate = realpath(__DIR__ . '/../' . $rel);
    if ($assets_root && $candidate && strpos($candidate, $assets_root . DIRECTORY_SEPARATOR) === 0 && is_file($candidate)) {
        @unlink($candidate);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = "Token keamanan tidak valid. Silakan coba lagi.";
    header("Location: news.php");
    exit;
}

$message = $_SESSION['flash_success'] ?? '';
$message_type = 'success';
unset($_SESSION['flash_success']);

if (!empty($_SESSION['flash_error'])) {
    $message = $_SESSION['flash_error'];
    $message_type = 'error';
    unset($_SESSION['flash_error']);
}

if ($message === '') {
    if (isset($_GET['success'])) $message = 'Berita berhasil dipublikasikan!';
    if (isset($_GET['updated'])) $message = 'Berita berhasil diperbarui!';
    if (isset($_GET['deleted'])) $message = 'Berita berhasil dihapus!';
    if (isset($_GET['error'])) {
        $message = 'Terjadi kesalahan.';
        $message_type = 'error';
    }
}

$edit_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$edit_data = ['id' => 0, 'title' => '', 'snippet' => '', 'content' => '', 'image_url' => ''];

if ($edit_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM news WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $edit_data = $result->fetch_assoc();
    } else {
        $_SESSION['flash_error'] = "Berita tidak ditemukan.";
        header("Location: news.php");
        exit;
    }
    $stmt->close();
}

// Proses Hapus Semua Berita (CSRF POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_all') {
    $image_paths = [];
    $res_imgs = $conn->query("SELECT DISTINCT image_url FROM news WHERE image_url IS NOT NULL AND image_url != ''");
    if ($res_imgs) {
        while ($img_row = $res_imgs->fetch_assoc()) {
            $image_paths[] = $img_row['image_url'];
        }
    }

    if ($conn->query("DELETE FROM news")) {
        $deleted_count = (int)$conn->affected_rows;
        foreach ($image_paths as $img_path) {
            delete_news_image_file($img_path);
        }
        $_SESSION['flash_success'] = $deleted_count > 0
            ? "Semua berita ($deleted_count berita) berhasil dihapus permanen."
            : "Tidak ada berita untuk dihapus.";
    } else {
        $_SESSION['flash_error'] = "Gagal menghapus semua berita.";
    }
    header("Location: news.php");
    exit;
}

// Proses Hapus Beberapa Berita Terpilih (CSRF POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_selected') {
    $raw_ids = explode(',', (string)($_POST['selected_ids'] ?? ''));
    $selected_ids = array_values(array_unique(array_filter(array_map('intval', $raw_ids), function ($id) {
        return $id > 0;
    })));

    if (empty($selected_ids)) {
        $_SESSION['flash_error'] = "Pilih minimal satu berita yang ingin dihapus.";
        header("Location: news.php");
        exit;
    }

    $id_list_sql = implode(',', $selected_ids);
    $image_paths = [];
    $res_imgs = $conn->query("SELECT DISTINCT image_url FROM news WHERE id IN ($id_list_sql) AND image_url IS NOT NULL AND image_url != ''");
    if ($res_imgs) {
        while ($img_row = $res_imgs->fetch_assoc()) {
            $image_paths[] = $img_row['image_url'];
        }
    }

    if ($conn->query("DELETE FROM news WHERE id IN ($id_list_sql)")) {
        $deleted_count = (int)$conn->affected_rows;
        foreach ($image_paths as $img_path) {
            $stmt_ref = $conn->prepare("SELECT COUNT(*) as c FROM news WHERE image_url = ?");
            $stmt_ref->bind_param("s", $img_path);
            $stmt_ref->execute();
            $ref_count = (int)$stmt_ref->get_result()->fetch_assoc()['c'];
            $stmt_ref->close();
            if ($ref_count === 0) {
                delete_news_image_file($img_path);
            }
        }
        $_SESSION['flash_success'] = "$deleted_count berita terpilih berhasil dihapus permanen.";
    } else {
        $_SESSION['flash_error'] = "Gagal menghapus berita terpilih.";
    }
    header("Location: news.php");
    exit;
}

// Proses Hapus Satu Berita (CSRF POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("SELECT image_url FROM news WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $stmt_del = $conn->prepare("DELETE FROM news WHERE id = ?");
        $stmt_del->bind_param("i", $id);
        if ($stmt_del->execute() && $stmt_del->affected_rows > 0) {
            if ($row && !empty($row['image_url'])) {
                $stmt_ref = $conn->prepare("SELECT COUNT(*) as c FROM news WHERE image_url = ?");
                $stmt_ref->bind_param("s", $row['image_url']);
                $stmt_ref->execute();
                $ref_count = (int)$stmt_ref->get_result()->fetch_assoc()['c'];
                $stmt_ref->close();
                if ($ref_count === 0) {
                    delete_news_image_file($row['image_url']);
                }
            }
            $_SESSION['flash_success'] = "Berita berhasil dihapus!";
        } else {
            $_SESSION['flash_error'] = "Berita tidak ditemukan atau gagal dihapus.";
        }
        $stmt_del->close();
    } else {
        $_SESSION['flash_error'] = "ID berita tidak valid.";
    }
    header("Location: news.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['add', 'edit'], true)) {
    $title = trim($_POST['title'] ?? '');
    $snippet = trim($_POST['snippet'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $image_url = $_POST['existing_image'] ?? '';
    $redirect_edit = ($_POST['action'] === 'edit' && !empty($_POST['id'])) ? "?edit=" . intval($_POST['id']) : "";
    
    if (empty($title) || empty($snippet) || empty($content)) {
        $_SESSION['flash_error'] = "Semua field wajib diisi.";
        header("Location: news.php" . $redirect_edit);
        exit;
    }
    
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $max_size = 5 * 1024 * 1024; // 5MB
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $file_type = mime_content_type($_FILES['image_file']['tmp_name']);
        $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));

        if ($_FILES['image_file']['size'] > $max_size) {
            $_SESSION['flash_error'] = "Ukuran gambar maksimal 5MB.";
            header("Location: news.php" . $redirect_edit);
            exit;
        }

        if (!in_array($file_type, $allowed_types, true) || !in_array($ext, $allowed_exts, true)) {
            $_SESSION['flash_error'] = "Format gambar tidak diizinkan! Gunakan JPG, PNG, GIF, atau WebP.";
            header("Location: news.php" . $redirect_edit);
            exit;
        }

        $filename = 'news_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = '../assets/uploads/news/' . $filename;
        
        if (!is_dir('../assets/uploads/news/')) {
            mkdir('../assets/uploads/news/', 0755, true);
        }
        
        if (move_uploaded_file($_FILES['image_file']['tmp_name'], $dest)) {
            if (!empty($_POST['existing_image'])) {
                delete_news_image_file($_POST['existing_image']);
            }
            $image_url = 'assets/uploads/news/' . $filename;
        }
    } elseif ($_POST['action'] === 'add' && $image_url === '') {
        $_SESSION['flash_error'] = "Gambar thumbnail wajib diunggah untuk berita baru.";
        header("Location: news.php");
        exit;
    }
    
    if ($_POST['action'] === 'add') {
        $stmt = $conn->prepare("INSERT INTO news (title, snippet, content, image_url) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $title, $snippet, $content, $image_url);
        if ($stmt->execute()) {
            header("Location: news.php?success=1");
            exit;
        }
    } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("UPDATE news SET title = ?, snippet = ?, content = ?, image_url = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $title, $snippet, $content, $image_url, $id);
        if ($stmt->execute()) {
            header("Location: news.php?updated=1");
            exit;
        }
    }
}

$news_list = $conn->query("SELECT * FROM news ORDER BY created_at DESC");
$total_news = $news_list ? (int)$news_list->num_rows : 0;

$page_title = $edit_id > 0 ? 'Edit Berita' : 'Kelola Berita';
require_once 'layout_header.php';
?>

<style>
.news-toast {
    position: fixed;
    top: 24px;
    right: 24px;
    z-index: 10000;
    min-width: 320px;
    max-width: 440px;
    background: #ffffff;
    border-radius: 16px;
    box-shadow: 0 20px 45px -10px rgba(15, 23, 42, 0.22), 0 0 0 1px rgba(148, 163, 184, 0.16);
    padding: 16px 18px;
    display: flex;
    align-items: flex-start;
    gap: 14px;
    overflow: hidden;
    animation: newsToastIn 0.35s cubic-bezier(0.16, 1, 0.3, 1);
    transition: opacity 0.25s ease, transform 0.25s ease;
}
.news-toast.is-hiding {
    opacity: 0;
    transform: translateY(-10px) scale(0.98);
}
.news-toast-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.news-toast.success .news-toast-icon {
    background: linear-gradient(135deg, #dcfce7, #bbf7d0);
    color: #15803d;
}
.news-toast.error .news-toast-icon {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #b91c1c;
}
.news-toast-title {
    font-weight: 700;
    font-size: 14px;
    color: #0f172a;
    margin-bottom: 2px;
}
.news-toast-msg {
    font-size: 13px;
    color: #475569;
    line-height: 1.45;
}
.news-toast-close {
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    font-size: 15px;
    padding: 2px 4px;
    margin-left: auto;
}
.news-toast-close:hover { color: #334155; }
.news-toast-progress {
    position: absolute;
    left: 0;
    bottom: 0;
    height: 3px;
    width: 100%;
    transform-origin: left;
    animation: newsToastProgress 5s linear forwards;
}
.news-toast.success .news-toast-progress { background: linear-gradient(90deg, #22c55e, #16a34a); }
.news-toast.error .news-toast-progress { background: linear-gradient(90deg, #ef4444, #dc2626); }

.news-alert-banner {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 20px;
    border-radius: 14px;
    margin-bottom: 24px;
    box-shadow: 0 8px 24px -6px rgba(15, 23, 42, 0.08);
    animation: newsToastIn 0.3s ease;
}
.news-alert-banner.success {
    background: linear-gradient(135deg, #f0fdf4, #dcfce7);
    border: 1px solid #86efac;
    color: #166534;
}
.news-alert-banner.error {
    background: linear-gradient(135deg, #fef2f2, #fee2e2);
    border: 1px solid #fca5a5;
    color: #991b1b;
}

/* Custom Confirmation Modal */
.confirm-modal-backdrop {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 10001;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(4px);
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.confirm-modal-backdrop.is-open {
    display: flex;
    animation: modalFadeIn 0.2s ease;
}
.confirm-modal-card {
    background: #ffffff;
    width: 100%;
    max-width: 460px;
    border-radius: 20px;
    padding: 30px 28px 24px;
    box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.32);
    text-align: center;
    animation: modalPopIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}
.confirm-modal-badge {
    width: 68px;
    height: 68px;
    margin: 0 auto 18px;
    border-radius: 50%;
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #dc2626;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    box-shadow: 0 0 0 8px rgba(239, 68, 68, 0.1);
}
.confirm-modal-title {
    margin: 0 0 10px;
    font-size: 20px;
    font-weight: 700;
    color: #0f172a;
}
.confirm-modal-desc {
    margin: 0 0 14px;
    font-size: 14px;
    color: #475569;
    line-height: 1.6;
}
.confirm-modal-highlight {
    display: inline-block;
    background: #fff1f2;
    color: #be123c;
    border: 1px solid #fecdd3;
    border-radius: 10px;
    padding: 8px 14px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 16px;
    max-width: 100%;
    word-break: break-word;
}
.confirm-modal-tip {
    background: #f8fafc;
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 12px;
    color: #64748b;
    margin-bottom: 22px;
    line-height: 1.5;
}
.confirm-modal-actions {
    display: flex;
    gap: 12px;
    justify-content: center;
}
.btn-modal-cancel {
    flex: 1;
    padding: 12px 18px;
    border-radius: 12px;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    color: #334155;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: 0.2s;
}
.btn-modal-cancel:hover { background: #e2e8f0; }
.btn-modal-confirm {
    flex: 1;
    padding: 12px 18px;
    border-radius: 12px;
    border: none;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: #ffffff;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    box-shadow: 0 8px 20px -4px rgba(220, 38, 38, 0.45);
    transition: 0.2s;
}
.btn-modal-confirm:hover {
    transform: translateY(-1px);
    box-shadow: 0 12px 24px -4px rgba(220, 38, 38, 0.55);
}

.news-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 18px;
    padding: 14px 16px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}
.news-search-wrap {
    position: relative;
    flex: 1;
    min-width: 240px;
}
.news-search-wrap i {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
}
.news-search-input {
    width: 100%;
    padding: 10px 14px 10px 38px;
    border: 1px solid #cbd5e1;
    border-radius: 10px;
    font-size: 13px;
    background: #ffffff;
    box-sizing: border-box;
}
.news-search-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.12);
}
.btn-bulk-delete {
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #fecaca;
    padding: 9px 16px;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s;
}
.btn-bulk-delete:hover:not(:disabled) {
    background: #dc2626;
    color: #ffffff;
}
.btn-bulk-delete:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}

@keyframes newsToastIn {
    from { opacity: 0; transform: translateY(-12px) scale(0.96); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}
@keyframes newsToastProgress {
    from { transform: scaleX(1); }
    to { transform: scaleX(0); }
}
@keyframes modalFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
@keyframes modalPopIn {
    from { opacity: 0; transform: scale(0.92); }
    to { opacity: 1; transform: scale(1); }
}
</style>

<?php if ($message): ?>
<div class="news-alert-banner <?php echo $message_type === 'error' ? 'error' : 'success'; ?>">
    <i class="fas <?php echo $message_type === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>" style="font-size: 20px;"></i>
    <div style="flex:1; font-size:14px; font-weight:600;"><?php echo htmlspecialchars($message); ?></div>
</div>
<div id="serverToast" class="news-toast <?php echo $message_type === 'error' ? 'error' : 'success'; ?>" role="alert">
    <div class="news-toast-icon"><i class="fas <?php echo $message_type === 'error' ? 'fa-exclamation-triangle' : 'fa-check'; ?>"></i></div>
    <div>
        <div class="news-toast-title"><?php echo $message_type === 'error' ? 'Terjadi Kesalahan' : 'Berhasil!'; ?></div>
        <div class="news-toast-msg"><?php echo htmlspecialchars($message); ?></div>
    </div>
    <button type="button" class="news-toast-close" onclick="dismissToast('serverToast')">&times;</button>
    <div class="news-toast-progress"></div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2><?php echo $edit_id > 0 ? 'Edit Berita' : 'Publikasi Berita Baru'; ?></h2>
        <?php if($edit_id > 0): ?>
        <a href="news.php" class="btn-secondary" style="padding:8px 16px; border-radius:var(--radius-md); font-size:13px; text-decoration:none; color:var(--text-muted);"><i class="fas fa-plus"></i> Tambah Baru</a>
        <?php endif; ?>
    </div>

    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="<?php echo $edit_id > 0 ? 'edit' : 'add'; ?>">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <?php if($edit_id > 0): ?>
        <input type="hidden" name="id" value="<?php echo $edit_id; ?>">
        <?php endif; ?>
        
        <input type="hidden" name="existing_image" value="<?php echo htmlspecialchars($edit_data['image_url']); ?>">

        <div class="form-group">
            <label>Judul Berita</label>
            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($edit_data['title']); ?>" required>
        </div>

        <div class="form-group">
            <label>Upload Gambar Thumbnail</label>
            <?php if(!empty($edit_data['image_url'])): ?>
            <div style="margin-bottom: 10px;">
                <img src="../<?php echo htmlspecialchars($edit_data['image_url']); ?>" style="max-width: 200px; max-height: 150px; border-radius: 8px; border: 1px solid #e2e8f0;">
                <p style="font-size: 12px; color: #64748b; margin-top: 5px;">Gambar saat ini</p>
            </div>
            <?php endif; ?>
            <div style="border: 2px dashed var(--border-color, #d0e4f0); border-radius: 10px; padding: 20px; text-align: center; background: var(--bg-light, #f8fbff); cursor: pointer;" onclick="document.getElementById('image_file').click()">
                <img id="preview_img" src="" alt="" style="max-width: 100%; max-height: 200px; border-radius: 8px; display: none; margin-bottom: 10px;">
                <div id="upload_placeholder">
                    <i class="fas fa-cloud-upload-alt" style="font-size: 2rem; color: var(--primary, #3b9ddd); display: block; margin-bottom: 8px;"></i>
                    <p style="margin:0; color: var(--text-muted, #888); font-size: 14px;">Klik untuk pilih gambar (Opsional jika mengedit)<br><small>JPG, PNG, GIF, WebP — maks. 5MB</small></p>
                </div>
                <input type="file" id="image_file" name="image_file" accept="image/*" style="display:none" <?php echo $edit_id > 0 ? '' : 'required'; ?> onchange="previewImage(this)">
            </div>
            <p id="file_name_label" style="font-size: 12px; color: var(--text-muted, #888); margin-top: 6px;"></p>
        </div>

        <div class="form-group">
            <label>Ringkasan (Snippet yang tampil di depan)</label>
            <textarea name="snippet" class="form-control" required><?php echo htmlspecialchars($edit_data['snippet']); ?></textarea>
        </div>

        <div class="form-group">
            <label>Isi Konten Lengkap (HTML diperbolehkan)</label>
            <textarea name="content" class="form-control" style="min-height: 200px;" required><?php echo htmlspecialchars($edit_data['content']); ?></textarea>
        </div>

        <button type="submit" class="btn" style="margin-top: 10px;">
            <i class="fas fa-<?php echo $edit_id > 0 ? 'save' : 'paper-plane'; ?>"></i> 
            <?php echo $edit_id > 0 ? 'Simpan Perubahan' : 'Publikasikan Sekarang'; ?>
        </button>
    </form>
</div>

<div class="card" style="margin-top: 30px;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
        <h2>Daftar Berita (<?php echo $total_news; ?>)</h2>
        <?php if ($total_news > 0): ?>
        <div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
            <button type="button" id="bulkDeleteBtn" class="btn-bulk-delete" disabled onclick="openConfirmBulkDelete()">
                <i class="fas fa-check-square"></i> Hapus Terpilih (<span id="selectedCountBadge">0</span>)
            </button>
            <button type="button" class="btn-danger" onclick="openConfirmDeleteAll(<?php echo $total_news; ?>)">
                <i class="fas fa-trash-alt"></i> Hapus Semua Berita
            </button>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($total_news > 0): ?>
    <div class="news-toolbar">
        <div class="news-search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="newsSearchInput" class="news-search-input" placeholder="Cari judul atau ringkasan berita... (misal: Pinjaman, Cairin, Bapenda)" oninput="filterNewsRows()">
        </div>
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <span id="visibleCountInfo" style="font-size:12px; color:#64748b; font-weight:600;">Menampilkan <?php echo $total_news; ?> dari <?php echo $total_news; ?> berita</span>
            <button type="button" class="btn-secondary" style="cursor:pointer;" onclick="toggleSelectVisibleNews()" id="selectVisibleBtn">
                <i class="fas fa-tasks"></i> Pilih Semua yang Tampil
            </button>
        </div>
    </div>
    <?php endif; ?>

    <div style="overflow-x: auto;">
        <table id="newsTable">
            <tr>
                <?php if ($total_news > 0): ?>
                <th width="42" style="text-align:center;">
                    <input type="checkbox" id="masterNewsCheckbox" onchange="toggleMasterVisible(this.checked)" title="Pilih semua yang tampil" style="width:16px; height:16px; cursor:pointer;">
                </th>
                <?php endif; ?>
                <th width="140">Thumbnail</th>
                <th>Judul &amp; Ringkasan Berita</th>
                <th width="140" style="text-align:center;">Aksi</th>
            </tr>
            <?php if ($news_list): while($row = $news_list->fetch_assoc()):
                $row_title = (string)($row['title'] ?? '');
                $row_snippet = (string)($row['snippet'] ?? '');
                $row_date = !empty($row['created_at']) ? date('d M Y, H:i', strtotime($row['created_at'])) : '-';
                $search_blob = mb_strtolower(trim($row_title . ' ' . $row_snippet . ' ' . $row_date), 'UTF-8');
            ?>
            <tr class="news-row" data-search="<?php echo htmlspecialchars($search_blob, ENT_QUOTES, 'UTF-8'); ?>">
                <td style="text-align:center; vertical-align:middle;">
                    <input type="checkbox" class="news-checkbox" value="<?php echo (int)$row['id']; ?>" onchange="updateBulkSelection()" style="width:16px; height:16px; cursor:pointer;">
                </td>
                <td>
                    <?php if(!empty($row['image_url'])): ?>
                        <img src="../<?php echo htmlspecialchars((string)$row['image_url']); ?>" class="preview" style="width:120px; height:80px; object-fit:cover; border-radius:6px;">
                    <?php else: ?>
                        <div style="width:120px;height:80px;background:#eee;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:12px;">No Image</div>
                    <?php endif; ?>
                </td>
                <td>
                    <strong style="color: var(--primary); font-size:16px;"><?php echo htmlspecialchars($row_title); ?></strong><br>
                    <small style="color:var(--text-muted);">Diunggah pada: <?php echo htmlspecialchars($row_date); ?></small><br>
                    <p style="font-size: 13px; color:#555; margin-top:8px; line-height: 1.5;"><?php echo htmlspecialchars($row_snippet); ?></p>
                </td>
                <td style="text-align:center;">
                    <a href="?edit=<?php echo (int)$row['id']; ?>" class="btn-action edit" title="Edit" style="background:#dbeafe; color:#1d4ed8; padding:8px 12px; border-radius:6px; margin-bottom:5px; display:inline-block; text-decoration:none;"><i class="fas fa-edit"></i></a>
                    <button type="button" class="btn-danger" data-id="<?php echo (int)$row['id']; ?>" data-title="<?php echo htmlspecialchars($row_title, ENT_QUOTES, 'UTF-8'); ?>" onclick="openConfirmDeleteSingle(this)"><i class="fas fa-trash"></i> Hapus</button>
                </td>
            </tr>
            <?php endwhile; endif; ?>
            <?php if($total_news === 0): ?>
            <tr>
                <td colspan="3" style="text-align:center; color:#64748b; padding:30px;">Belum ada berita yang dipublikasikan.</td>
            </tr>
            <?php else: ?>
            <tr id="noFilterMatchRow" style="display:none;">
                <td colspan="4" style="text-align:center; color:#64748b; padding:30px;">Tidak ada berita yang cocok dengan kata kunci pencarian.</td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- Form Hapus Tersembunyi (CSRF POST) -->
<form id="deleteNewsForm" method="post" action="news.php" style="display:none;">
    <input type="hidden" name="action" id="deleteFormAction" value="delete">
    <input type="hidden" name="id" id="deleteNewsId" value="">
    <input type="hidden" name="selected_ids" id="deleteSelectedIds" value="">
    <?php echo csrf_token_field(); ?>
</form>

<!-- Modal Konfirmasi Menarik -->
<div id="newsConfirmModal" class="confirm-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="confirmModalTitle">
    <div class="confirm-modal-card">
        <div class="confirm-modal-badge">
            <i class="fas fa-trash-alt"></i>
        </div>
        <h3 id="confirmModalTitle" class="confirm-modal-title">Konfirmasi Hapus</h3>
        <p id="confirmModalDesc" class="confirm-modal-desc"></p>
        <div id="confirmModalHighlight" class="confirm-modal-highlight" style="display:none;"></div>
        <div id="confirmModalTip" class="confirm-modal-tip" style="display:none;"></div>
        <div class="confirm-modal-actions">
            <button type="button" class="btn-modal-cancel" onclick="closeConfirmModal()">Batal</button>
            <button type="button" id="confirmModalProceedBtn" class="btn-modal-confirm"><i class="fas fa-trash-alt"></i> Ya, Hapus Permanen</button>
        </div>
    </div>
</div>

<script>
let pendingConfirmAction = null;

function dismissToast(toastId) {
    const el = document.getElementById(toastId);
    if (!el) return;
    el.classList.add('is-hiding');
    setTimeout(() => { if (el.parentNode) el.parentNode.removeChild(el); }, 250);
}

function showClientToast(type, title, text) {
    const existing = document.getElementById('clientToast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.id = 'clientToast';
    toast.className = 'news-toast ' + (type === 'error' ? 'error' : 'success');
    toast.setAttribute('role', 'alert');

    const iconWrap = document.createElement('div');
    iconWrap.className = 'news-toast-icon';
    iconWrap.innerHTML = type === 'error'
        ? '<i class="fas fa-exclamation-triangle"></i>'
        : '<i class="fas fa-check"></i>';

    const content = document.createElement('div');
    const tEl = document.createElement('div');
    tEl.className = 'news-toast-title';
    tEl.textContent = title;
    const mEl = document.createElement('div');
    mEl.className = 'news-toast-msg';
    mEl.textContent = text;
    content.appendChild(tEl);
    content.appendChild(mEl);

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'news-toast-close';
    closeBtn.innerHTML = '&times;';
    closeBtn.onclick = () => dismissToast('clientToast');

    const progress = document.createElement('div');
    progress.className = 'news-toast-progress';

    toast.appendChild(iconWrap);
    toast.appendChild(content);
    toast.appendChild(closeBtn);
    toast.appendChild(progress);
    document.body.appendChild(toast);

    setTimeout(() => dismissToast('clientToast'), 5000);
}

if (document.getElementById('serverToast')) {
    setTimeout(() => dismissToast('serverToast'), 5000);
}

function showConfirmModal(opts) {
    document.getElementById('confirmModalTitle').textContent = opts.title;
    document.getElementById('confirmModalDesc').textContent = opts.desc;

    const hl = document.getElementById('confirmModalHighlight');
    if (opts.highlight) {
        hl.textContent = opts.highlight;
        hl.style.display = 'inline-block';
    } else {
        hl.style.display = 'none';
    }

    const tip = document.getElementById('confirmModalTip');
    if (opts.tip) {
        tip.textContent = opts.tip;
        tip.style.display = 'block';
    } else {
        tip.style.display = 'none';
    }

    pendingConfirmAction = opts.onConfirm;
    document.getElementById('newsConfirmModal').classList.add('is-open');
}

function closeConfirmModal() {
    document.getElementById('newsConfirmModal').classList.remove('is-open');
    pendingConfirmAction = null;
}

document.getElementById('confirmModalProceedBtn').addEventListener('click', function() {
    if (typeof pendingConfirmAction === 'function') {
        pendingConfirmAction();
    }
});

document.getElementById('newsConfirmModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeConfirmModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeConfirmModal();
    }
});

function openConfirmDeleteSingle(btn) {
    const id = btn.getAttribute('data-id');
    const title = btn.getAttribute('data-title') || 'Berita ini';
    showConfirmModal({
        title: 'Hapus Berita?',
        desc: 'Berita dan file thumbnail terkait akan dihapus permanen dari website.',
        highlight: title,
        tip: '',
        onConfirm: function() {
            document.getElementById('deleteFormAction').value = 'delete';
            document.getElementById('deleteNewsId').value = id;
            document.getElementById('deleteSelectedIds').value = '';
            document.getElementById('deleteNewsForm').submit();
        }
    });
}

function openConfirmBulkDelete() {
    const checked = Array.from(document.querySelectorAll('.news-checkbox:checked')).map(cb => cb.value);
    if (checked.length === 0) return;

    showConfirmModal({
        title: 'Hapus Berita Terpilih?',
        desc: 'Berita yang Anda centang akan dihapus secara permanen dari database.',
        highlight: checked.length + ' berita terpilih akan dihapus',
        tip: 'Berita lain yang tidak dicentang akan tetap aman tersimpan.',
        onConfirm: function() {
            document.getElementById('deleteFormAction').value = 'delete_selected';
            document.getElementById('deleteNewsId').value = '';
            document.getElementById('deleteSelectedIds').value = checked.join(',');
            document.getElementById('deleteNewsForm').submit();
        }
    });
}

function openConfirmDeleteAll(totalCount) {
    showConfirmModal({
        title: 'Hapus Semua Berita?',
        desc: 'PERINGATAN: Seluruh berita beserta file gambarnya akan dihapus permanen dan tidak bisa dikembalikan.',
        highlight: 'Total ' + totalCount + ' berita akan dihapus sekaligus',
        tip: 'Tips: Jika masih ada berita kegiatan Bapenda asli yang ingin dipertahankan, klik Batal lalu gunakan kotak Pencarian + "Hapus Terpilih".',
        onConfirm: function() {
            document.getElementById('deleteFormAction').value = 'delete_all';
            document.getElementById('deleteNewsId').value = '';
            document.getElementById('deleteSelectedIds').value = '';
            document.getElementById('deleteNewsForm').submit();
        }
    });
}

function filterNewsRows() {
    const query = (document.getElementById('newsSearchInput').value || '').trim().toLowerCase();
    const rows = document.querySelectorAll('.news-row');
    let visibleCount = 0;

    rows.forEach(row => {
        const hay = row.getAttribute('data-search') || '';
        const match = query === '' || hay.includes(query);
        row.style.display = match ? '' : 'none';
        if (match) {
            visibleCount++;
        } else {
            const cb = row.querySelector('.news-checkbox');
            if (cb) cb.checked = false;
        }
    });

    const info = document.getElementById('visibleCountInfo');
    if (info) {
        info.textContent = 'Menampilkan ' + visibleCount + ' dari ' + rows.length + ' berita';
    }
    const emptyRow = document.getElementById('noFilterMatchRow');
    if (emptyRow) {
        emptyRow.style.display = visibleCount === 0 ? '' : 'none';
    }
    updateBulkSelection();
}

function updateBulkSelection() {
    const allCbs = Array.from(document.querySelectorAll('.news-checkbox'));
    const visibleCbs = allCbs.filter(cb => cb.closest('tr').style.display !== 'none');
    const checkedCount = allCbs.filter(cb => cb.checked).length;

    const badge = document.getElementById('selectedCountBadge');
    const bulkBtn = document.getElementById('bulkDeleteBtn');
    const masterCb = document.getElementById('masterNewsCheckbox');
    const toggleBtn = document.getElementById('selectVisibleBtn');

    if (badge) badge.textContent = checkedCount;
    if (bulkBtn) bulkBtn.disabled = checkedCount === 0;

    const allVisibleChecked = visibleCbs.length > 0 && visibleCbs.every(cb => cb.checked);
    if (masterCb) masterCb.checked = allVisibleChecked;
    if (toggleBtn) {
        toggleBtn.innerHTML = allVisibleChecked
            ? '<i class="fas fa-times-circle"></i> Batal Pilih'
            : '<i class="fas fa-tasks"></i> Pilih Semua yang Tampil';
    }
}

function toggleMasterVisible(checked) {
    document.querySelectorAll('.news-row').forEach(row => {
        if (row.style.display !== 'none') {
            const cb = row.querySelector('.news-checkbox');
            if (cb) cb.checked = checked;
        }
    });
    updateBulkSelection();
}

function toggleSelectVisibleNews() {
    const visibleCbs = Array.from(document.querySelectorAll('.news-row'))
        .filter(row => row.style.display !== 'none')
        .map(row => row.querySelector('.news-checkbox'))
        .filter(Boolean);
    const shouldCheck = visibleCbs.some(cb => !cb.checked);
    toggleMasterVisible(shouldCheck);
}

function previewImage(input) {
    const file = input.files[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) {
        showClientToast('error', 'Ukuran Gambar Terlalu Besar', 'Maksimal ukuran file thumbnail adalah 5MB.');
        input.value = '';
        return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
        const img = document.getElementById('preview_img');
        img.src = e.target.result;
        img.style.display = 'block';
        document.getElementById('upload_placeholder').style.display = 'none';
    };
    reader.readAsDataURL(file);
    document.getElementById('file_name_label').textContent = '📎 ' + file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
}
</script>

<?php require_once 'layout_footer.php'; ?>
