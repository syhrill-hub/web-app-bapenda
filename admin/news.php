<?php
session_start();
require_once '../config.php';
require_once 'includes/security.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
require_permission('menu_content_news');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = "Token keamanan tidak valid. Silakan coba lagi.";
    header("Location: news.php");
    exit;
}

$message = '';
$message_type = 'success';

if (isset($_GET['success'])) $message = 'Berita berhasil dipublikasikan!';
if (isset($_GET['updated'])) $message = 'Berita berhasil diperbarui!';
if (isset($_GET['deleted'])) { $message = 'Berita berhasil dihapus!'; }
if (isset($_GET['error'])) { 
    $message = $_SESSION['flash_error'] ?? 'Terjadi kesalahan.';
    $message_type = 'error';
    unset($_SESSION['flash_error']);
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
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $stmt = $conn->prepare("SELECT image_url FROM news WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if (!empty($row['image_url'])) {
            $file_path = '../' . $row['image_url'];
            if (file_exists($file_path)) @unlink($file_path);
        }
    }
    $stmt = $conn->prepare("DELETE FROM news WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: news.php?deleted=1");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $title = trim($_POST['title'] ?? '');
    $snippet = trim($_POST['snippet'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $image_url = $_POST['existing_image'] ?? '';
    
    if (empty($title) || empty($snippet) || empty($content)) {
        $_SESSION['flash_error'] = "Semua field wajib diisi.";
        header("Location: news.php" . ($edit_id > 0 ? "?edit={$edit_id}" : ""));
        exit;
    }
    
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = mime_content_type($_FILES['image_file']['tmp_name']);
        
        if (in_array($file_type, $allowed_types)) {
            $ext = pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION);
            $filename = 'news_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = '../assets/uploads/news/' . $filename;
            
            if (!is_dir('../assets/uploads/news/')) {
                mkdir('../assets/uploads/news/', 0755, true);
            }
            
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $dest)) {
                if (!empty($_POST['existing_image']) && file_exists('../' . $_POST['existing_image'])) {
                    @unlink('../' . $_POST['existing_image']);
                }
                $image_url = 'assets/uploads/news/' . $filename;
            }
        }
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

$page_title = $edit_id > 0 ? 'Edit Berita' : 'Kelola Berita';
require_once 'layout_header.php';
?>

<?php if($message): ?>
<div class="alert <?php echo $message_type === 'error' ? 'alert-danger' : ''; ?>"><?php echo htmlspecialchars($message); ?></div>
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
    <div class="card-header">
        <h2>Daftar Berita</h2>
    </div>
    <div style="overflow-x: auto;">
        <table>
            <tr>
                <th width="140">Thumbnail</th>
                <th>Judul &amp; Ringkasan Berita</th>
                <th width="140" style="text-align:center;">Aksi</th>
            </tr>
            <?php while($row = $news_list->fetch_assoc()): ?>
            <tr>
                <td>
                    <?php if(!empty($row['image_url'])): ?>
                        <img src="../<?php echo htmlspecialchars($row['image_url']); ?>" class="preview" style="width:120px; height:80px; object-fit:cover; border-radius:6px;">
                    <?php else: ?>
                        <div style="width:120px;height:80px;background:#eee;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#aaa;font-size:12px;">No Image</div>
                    <?php endif; ?>
                </td>
                <td>
                    <strong style="color: var(--primary); font-size:16px;"><?php echo htmlspecialchars($row['title']); ?></strong><br>
                    <small style="color:var(--text-muted);">Diunggah pada: <?php echo date('d M Y, H:i', strtotime($row['created_at'])); ?></small><br>
                    <p style="font-size: 13px; color:#555; margin-top:8px; line-height: 1.5;"><?php echo htmlspecialchars($row['snippet']); ?></p>
                </td>
                <td style="text-align:center;">
                    <a href="?edit=<?php echo $row['id']; ?>" class="btn-action edit" title="Edit" style="background:#dbeafe; color:#1d4ed8; padding:8px 12px; border-radius:6px; margin-bottom:5px; display:inline-block; text-decoration:none;"><i class="fas fa-edit"></i></a>
                    <a href="?delete=<?php echo $row['id']; ?>" class="btn-danger" onclick="return confirm('Anda yakin ingin menghapus berita ini secara permanen?');"><i class="fas fa-trash"></i> Hapus</a>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php if($news_list->num_rows === 0): ?>
            <tr>
                <td colspan="3" style="text-align:center; color:#64748b; padding:30px;">Belum ada berita yang dipublikasikan.</td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<script>
function previewImage(input) {
    const file = input.files[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) {
        alert('Ukuran file terlalu besar. Maksimal 5MB.');
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
