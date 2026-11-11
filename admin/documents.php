<?php
session_start();
require_once '../config.php';
require_once 'includes/security.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
require_permission('menu_content_documents');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = "Token keamanan tidak valid. Silakan coba lagi.";
    header("Location: documents.php");
    exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM documents WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) $_SESSION['flash_success'] = "Dokumen berhasil dihapus!";
    header("Location: documents.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../assets/uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        
        $file_name = preg_replace('/[^a-zA-Z0-9.\-_]/', '', $_FILES['file']['name']);
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $new_fn = 'doc_' . time() . '_' . rand(100,999) . '.' . $ext;
        
        if (in_array($ext, ['pdf', 'doc', 'docx', 'xls', 'xlsx'])) {
            if (move_uploaded_file($_FILES['file']['tmp_name'], $upload_dir . $new_fn)) {
                $file_url = 'assets/uploads/' . $new_fn;
                $stmt = $conn->prepare("INSERT INTO documents (title, description, file_url) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $title, $description, $file_url);
                if ($stmt->execute()) {
                    $_SESSION['flash_success'] = "Dokumen berhasil diunggah!";
                }
            } else {
                $_SESSION['flash_error'] = "Gagal memindahkan file yang diunggah.";
            }
        } else {
            $_SESSION['flash_error'] = "Format dokumen tidak valid. Harus PDF, DOC, DOCX, XLS, XLSX.";
        }
    } else {
        $_SESSION['flash_error'] = "Dokumen wajib dilampirkan.";
    }
    header("Location: documents.php");
    exit;
}

$items = [];
$res = $conn->query("SELECT * FROM documents ORDER BY created_at DESC");
if ($res) {
    while($r = $res->fetch_assoc()) $items[] = $r;
}

$page_title = "Manajemen Dokumen PPID";
require_once 'layout_header.php';
?>

<?php if(isset($_SESSION['flash_success'])): ?>
    <div class="alert" style="background:#d1fae5; color:#065f46; padding:15px; border-radius:12px; margin-bottom:20px;">
        <?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
    </div>
<?php endif; ?>
<?php if(isset($_SESSION['flash_error'])): ?>
    <div class="alert" style="background:#fee2e2; color:#991b1b; padding:15px; border-radius:12px; margin-bottom:20px;">
        <?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2>Tambah Dokumen Unduhan Publik</h2>
    </div>
    <form method="post" enctype="multipart/form-data">
        <?php echo csrf_token_field(); ?>
        <div class="form-group">
            <label>Nama / Judul Dokumen <span style="color:red;">*</span></label>
            <input type="text" name="title" class="form-control" placeholder="Contoh: Laporan Keuangan 2025" required>
        </div>
        <div class="form-group">
            <label>Keterangan Tambahan / Nomor Surat (Opsional)</label>
            <input type="text" name="description" class="form-control" placeholder="Contoh: SK Nomor 123/2025 (Deskripsi Singkat)">
        </div>
        <div class="form-group">
            <label>Unggah Berkas <span style="color:red;">*</span></label>
            <input type="file" name="file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx" required>
            <small style="color:#64748b; font-size:13px; margin-top:5px; display:inline-block;">Format yang diizinkan hanya PDF, Word, atau Excel.</small>
        </div>
        <button type="submit" class="btn"><i class="fas fa-upload"></i> Unggah Ke Sistem</button>
    </form>
</div>

<div class="card" style="margin-top: 30px;">
    <div class="card-header">
        <h2>Daftar Arsip Dokumen</h2>
        <div style="font-size:14px; text-decoration: underline; color: var(--primary);">
            URL Menu: <strong>dokumen.php</strong>
        </div>
    </div>
    <div style="background:white; border:1px solid #e2e8f0; border-radius:var(--radius-md); overflow:hidden;">
        <table class="table">
            <thead>
                <tr>
                    <th>Nama Dokumen</th>
                    <th>Tautan File (Direct)</th>
                    <th style="text-align:right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($items as $item): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($item['title']); ?></strong><br>
                        <span style="color:#64748b; font-size:13px;"><?php echo htmlspecialchars($item['description']); ?></span>
                    </td>
                    <td>
                        <a href="../<?php echo htmlspecialchars($item['file_url']); ?>" target="_blank" class="btn-outline" style="font-size:13px; padding:5px 15px;"><i class="fas fa-eye"></i> Pratinjau</a>
                    </td>
                    <td style="text-align:right;">
                        <a href="documents.php?delete=<?php echo $item['id']; ?>" class="btn-action delete" onclick="return confirm('Hapus dokumen ini secara permanen?');"><i class="fas fa-trash"></i> Hapus</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($items)): ?>
                <tr><td colspan="3" style="text-align:center; color:#64748b; padding:20px;">Belum ada dokumen yang diunggah.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'layout_footer.php'; ?>
