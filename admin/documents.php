<?php
require_once __DIR__ . '/../includes/security_helpers.php';
bapenda_start_secure_session();
require_once '../config.php';
require_once 'includes/security.php';
require_once __DIR__ . '/../includes/upload_helper.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmtSel = $conn->prepare("SELECT file_url FROM documents WHERE id = ? LIMIT 1");
        $stmtSel->bind_param("i", $id);
        $stmtSel->execute();
        $docRow = $stmtSel->get_result()->fetch_assoc();
        $stmtSel->close();

        $stmt = $conn->prepare("DELETE FROM documents WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            if ($docRow && !empty($docRow['file_url'])) {
                bapenda_safe_unlink($docRow['file_url'], __DIR__ . '/../assets/uploads', __DIR__ . '/..');
            }
            $_SESSION['flash_success'] = "Dokumen berhasil dihapus!";
        } else {
            $_SESSION['flash_error'] = "Dokumen tidak ditemukan.";
        }
        $stmt->close();
    }
    header("Location: documents.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if ($title === '') {
        $_SESSION['flash_error'] = "Nama dokumen wajib diisi.";
    } elseif (bapenda_has_uploaded_file($_FILES['file'] ?? [])) {
        $upErr = null;
        $file_url = bapenda_handle_upload(
            $_FILES['file'],
            __DIR__ . '/../assets/uploads',
            'assets/uploads',
            ['pdf', 'doc', 'docx', 'xls', 'xlsx'],
            15 * 1024 * 1024,
            'doc',
            $upErr
        );
        if ($file_url !== null) {
            $stmt = $conn->prepare("INSERT INTO documents (title, description, file_url) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $title, $description, $file_url);
            if ($stmt->execute()) {
                $_SESSION['flash_success'] = "Dokumen berhasil diunggah!";
            } else {
                bapenda_safe_unlink($file_url, __DIR__ . '/../assets/uploads', __DIR__ . '/..');
                $_SESSION['flash_error'] = "Gagal menyimpan metadata dokumen.";
            }
            $stmt->close();
        } else {
            $_SESSION['flash_error'] = $upErr;
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
        <?php echo htmlspecialchars($_SESSION['flash_success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['flash_success']); ?>
    </div>
<?php endif; ?>
<?php if(isset($_SESSION['flash_error'])): ?>
    <div class="alert" style="background:#fee2e2; color:#991b1b; padding:15px; border-radius:12px; margin-bottom:20px;">
        <?php echo htmlspecialchars($_SESSION['flash_error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['flash_error']); ?>
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
                        <a href="../<?php echo htmlspecialchars(bapenda_safe_url($item['file_url'], '#')); ?>" target="_blank" rel="noopener noreferrer" class="btn-outline" style="font-size:13px; padding:5px 15px;"><i class="fas fa-eye"></i> Pratinjau</a>
                    </td>
                    <td style="text-align:right;">
                        <form method="post" action="documents.php" style="display:inline;" onsubmit="return confirm('Hapus dokumen ini secara permanen?');">
                            <?php echo csrf_token_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int)$item['id']; ?>">
                            <button type="submit" class="btn-action delete"><i class="fas fa-trash"></i> Hapus</button>
                        </form>
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
