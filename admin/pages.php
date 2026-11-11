<?php
require_once __DIR__ . '/../includes/security_helpers.php';
bapenda_start_secure_session();
require_once '../config.php';
require_once 'includes/security.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
require_permission('menu_content_pages');

// Auto create pages table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS pages (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(255) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Handle Delete (CSRF POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = "Token keamanan tidak valid.";
        header("Location: pages.php");
        exit;
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM pages WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $_SESSION['flash_success'] = "Halaman berhasil dihapus!";
        } else {
            $_SESSION['flash_error'] = "Gagal menghapus halaman.";
        }
        $stmt->close();
    }
    header("Location: pages.php");
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

// Fetch pages
$pages = [];
$res = $conn->query("SELECT id, slug, title, created_at FROM pages ORDER BY created_at DESC");
if ($res) {
    while($row = $res->fetch_assoc()) {
        $pages[] = $row;
    }
}

$page_title = "Manajemen Halaman";
require_once 'layout_header.php';
?>

<?php if($message): ?>
    <div class="alert" style="background:<?php echo $msg_type === 'error' ? '#fee2e2' : '#d1fae5'; ?>; color:<?php echo $msg_type === 'error' ? '#991b1b' : '#065f46'; ?>; padding:15px; border-radius:12px; margin-bottom:20px;">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <h2>Daftar Halaman Statis</h2>
    <a href="page_form.php" class="btn"><i class="fas fa-plus"></i> Buat Halaman Baru</a>
</div>

<div class="card" style="padding: 0; overflow: hidden;">
    <table class="table">
        <thead>
            <tr>
                <th>Judul Halaman</th>
                <th>Tautan (URL)</th>
                <th>Dibuat Pada</th>
                <th style="text-align: right;">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($pages)): ?>
            <tr>
                <td colspan="4" style="text-align: center; padding: 30px; color: #64748b;">Belum ada halaman yang dibuat.</td>
            </tr>
            <?php else: ?>
                <?php foreach($pages as $p): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($p['title']); ?></strong></td>
                    <td>
                        <a href="../page.php?slug=<?php echo htmlspecialchars(rawurlencode((string)$p['slug'])); ?>" target="_blank" rel="noopener noreferrer" style="color: var(--primary); text-decoration: none;">
                            <i class="fas fa-external-link-alt" style="font-size: 12px;"></i> page.php?slug=<?php echo htmlspecialchars($p['slug']); ?>
                        </a>
                        <button type="button" data-url="page.php?slug=<?php echo htmlspecialchars(rawurlencode((string)$p['slug']), ENT_QUOTES, 'UTF-8'); ?>" onclick="navigator.clipboard.writeText(this.dataset.url); alert('Tautan disalin!');" style="background:none; border:none; cursor:pointer; color:#64748b; margin-left:10px;" title="Salin URL"><i class="far fa-copy"></i></button>
                    </td>
                    <td><?php echo date('d M Y, H:i', strtotime($p['created_at'])); ?></td>
                    <td style="text-align: right;">
                        <a href="page_form.php?id=<?php echo (int)$p['id']; ?>" class="btn-action edit" title="Edit"><i class="fas fa-edit"></i></a>
                        <form method="post" action="pages.php" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus halaman ini secara permanen?');">
                            <?php echo csrf_token_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                            <button type="submit" class="btn-action delete" title="Hapus"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'layout_footer.php'; ?>
