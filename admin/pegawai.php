<?php
require_once __DIR__ . '/../includes/security_helpers.php';
bapenda_start_secure_session();
require_once '../config.php';
require_once 'includes/security.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
require_permission('menu_content_pegawai');

// Auto-create pegawai table
$conn->query("CREATE TABLE IF NOT EXISTS pegawai (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(255) NOT NULL,
    nip VARCHAR(50) DEFAULT '',
    jabatan VARCHAR(255) DEFAULT '',
    jenis ENUM('PNS','PPPK') NOT NULL DEFAULT 'PNS',
    order_num INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Handle DELETE (CSRF POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = "Token keamanan tidak valid.";
        header("Location: pegawai.php");
        exit;
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM pegawai WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $_SESSION['flash_success'] = "Data pegawai berhasil dihapus.";
        } else {
            $_SESSION['flash_error'] = "Gagal menghapus data pegawai.";
        }
        $stmt->close();
    }
    header("Location: pegawai.php");
    exit;
}

// Handle ADD / EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = "Token keamanan tidak valid.";
        header("Location: pegawai.php");
        exit;
    }

    $id       = (int)($_POST['id'] ?? 0);
    $nama     = trim($_POST['nama'] ?? '');
    $nip      = trim($_POST['nip'] ?? '');
    $jabatan  = trim($_POST['jabatan'] ?? '');
    $jenis    = in_array($_POST['jenis'] ?? '', ['PNS', 'PPPK']) ? $_POST['jenis'] : 'PNS';
    $order_num = (int)($_POST['order_num'] ?? 0);

    if (empty($nama)) {
        $_SESSION['flash_error'] = "Nama pegawai wajib diisi.";
        header("Location: pegawai.php" . ($id ? "?edit={$id}" : ""));
        exit;
    }

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE pegawai SET nama=?, nip=?, jabatan=?, jenis=?, order_num=? WHERE id=?");
        $stmt->bind_param("ssssii", $nama, $nip, $jabatan, $jenis, $order_num, $id);
        $msg = "Data pegawai berhasil diperbarui!";
    } else {
        $stmt = $conn->prepare("INSERT INTO pegawai (nama, nip, jabatan, jenis, order_num) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $nama, $nip, $jabatan, $jenis, $order_num);
        $msg = "Data pegawai berhasil ditambahkan!";
    }

    if ($stmt->execute()) {
        $_SESSION['flash_success'] = $msg;
    } else {
        $_SESSION['flash_error'] = "Gagal menyimpan data pegawai.";
    }
    header("Location: pegawai.php");
    exit;
}

// Flash messages
$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Fetch edit data
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM pegawai WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $edit_data = $stmt->get_result()->fetch_assoc();
}

// Fetch all employees
$pns_list  = [];
$pppk_list = [];
$res = $conn->query("SELECT * FROM pegawai ORDER BY jenis ASC, order_num ASC, id ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        if ($row['jenis'] === 'PNS')  $pns_list[]  = $row;
        else                           $pppk_list[] = $row;
    }
}

$page_title = "Data Pegawai";
require_once 'layout_header.php';
?>

<?php if ($flash_success): ?>
<div class="alert" style="background:#d1fae5; color:#065f46; padding:15px 20px; border-radius:12px; margin-bottom:20px; border-left:4px solid #10b981;">
    <i class="fas fa-check-circle" style="margin-right:8px;"></i><?php echo htmlspecialchars($flash_success); ?>
</div>
<?php endif; ?>
<?php if ($flash_error): ?>
<div class="alert" style="background:#fee2e2; color:#991b1b; padding:15px 20px; border-radius:12px; margin-bottom:20px; border-left:4px solid #ef4444;">
    <i class="fas fa-exclamation-circle" style="margin-right:8px;"></i><?php echo htmlspecialchars($flash_error); ?>
</div>
<?php endif; ?>

<div class="dashboard-header" style="margin-bottom:25px;">
    <h2 style="font-size:22px; color:var(--text-dark);">
        <i class="fas fa-users" style="color:var(--primary); margin-right:10px;"></i>
        Data Pegawai Bapenda
    </h2>
    <p style="color:#64748b; margin-top:5px;">
        Kelola data pegawai PNS dan PPPK. URL halaman publik:
        <a href="../data_pegawai.php" target="_blank" rel="noopener noreferrer" style="color:var(--primary); font-weight:600;">data_pegawai.php</a>
    </p>
</div>

<!-- Form Tambah / Edit -->
<div class="card" style="margin-bottom:28px;">
    <div class="card-header">
        <h2>
            <i class="fas <?php echo $edit_data ? 'fa-edit' : 'fa-user-plus'; ?>" style="margin-right:8px;"></i>
            <?php echo $edit_data ? 'Edit Data Pegawai' : 'Tambah Pegawai Baru'; ?>
        </h2>
    </div>
    <form method="POST" action="pegawai.php">
        <?php echo csrf_token_field(); ?>
        <?php if ($edit_data): ?>
        <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
        <?php endif; ?>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
            <div class="form-group">
                <label>Nama Pegawai <span style="color:red;">*</span></label>
                <input type="text" name="nama" class="form-control" placeholder="Nama lengkap pegawai"
                       value="<?php echo htmlspecialchars($edit_data['nama'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label>NIP</label>
                <input type="text" name="nip" class="form-control" placeholder="Nomor Induk Pegawai"
                       value="<?php echo htmlspecialchars($edit_data['nip'] ?? ''); ?>">
            </div>
        </div>
        <div style="display:grid; grid-template-columns:2fr 1fr 1fr; gap:20px;">
            <div class="form-group">
                <label>Jabatan</label>
                <input type="text" name="jabatan" class="form-control" placeholder="Jabatan / posisi pegawai"
                       value="<?php echo htmlspecialchars($edit_data['jabatan'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label>Jenis Pegawai <span style="color:red;">*</span></label>
                <select name="jenis" class="form-control">
                    <option value="PNS"  <?php echo ($edit_data['jenis'] ?? 'PNS') === 'PNS'  ? 'selected' : ''; ?>>PNS</option>
                    <option value="PPPK" <?php echo ($edit_data['jenis'] ?? '') === 'PPPK' ? 'selected' : ''; ?>>PPPK</option>
                </select>
            </div>
            <div class="form-group">
                <label>Urutan</label>
                <input type="number" name="order_num" class="form-control" placeholder="0"
                       value="<?php echo (int)($edit_data['order_num'] ?? 0); ?>" min="0">
            </div>
        </div>

        <div style="display:flex; gap:10px; margin-top:5px;">
            <button type="submit" class="btn">
                <i class="fas <?php echo $edit_data ? 'fa-save' : 'fa-plus'; ?>" style="margin-right:8px;"></i>
                <?php echo $edit_data ? 'Simpan Perubahan' : 'Tambah Pegawai'; ?>
            </button>
            <?php if ($edit_data): ?>
            <a href="pegawai.php" class="btn-secondary" style="display:inline-flex; align-items:center; gap:8px; padding:14px 20px;">
                <i class="fas fa-times"></i> Batal Edit
            </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Statistics Summary -->
<div style="display:grid; grid-template-columns:repeat(3,1fr); gap:15px; margin-bottom:25px;">
    <div style="background:#fff; border-radius:var(--radius-md); padding:20px; text-align:center; box-shadow:var(--shadow-soft); border-left:4px solid var(--primary);">
        <div style="font-size:2rem; font-weight:700; color:var(--primary);"><?php echo count($pns_list); ?></div>
        <div style="font-size:13px; color:#64748b; font-weight:600; margin-top:4px;">Total PNS</div>
    </div>
    <div style="background:#fff; border-radius:var(--radius-md); padding:20px; text-align:center; box-shadow:var(--shadow-soft); border-left:4px solid #f59e0b;">
        <div style="font-size:2rem; font-weight:700; color:#f59e0b;"><?php echo count($pppk_list); ?></div>
        <div style="font-size:13px; color:#64748b; font-weight:600; margin-top:4px;">Total PPPK</div>
    </div>
    <div style="background:#fff; border-radius:var(--radius-md); padding:20px; text-align:center; box-shadow:var(--shadow-soft); border-left:4px solid #10b981;">
        <div style="font-size:2rem; font-weight:700; color:#10b981;"><?php echo count($pns_list) + count($pppk_list); ?></div>
        <div style="font-size:13px; color:#64748b; font-weight:600; margin-top:4px;">Total Pegawai</div>
    </div>
</div>

<!-- PNS Table -->
<div class="card" style="margin-bottom:25px;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h2><i class="fas fa-id-badge" style="margin-right:8px; color:#0ea5e9;"></i>Daftar PNS</h2>
        <span style="background:#dbeafe; color:#1d4ed8; padding:5px 14px; border-radius:50px; font-size:13px; font-weight:700;">
            <?php echo count($pns_list); ?> Orang
        </span>
    </div>
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:50px;">No</th>
                    <th>Nama</th>
                    <th>NIP</th>
                    <th>Jabatan</th>
                    <th style="text-align:right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pns_list)): ?>
                <tr><td colspan="5" style="text-align:center; padding:30px; color:#94a3b8;">Belum ada data PNS.</td></tr>
                <?php else: ?>
                <?php foreach ($pns_list as $i => $p): ?>
                <tr>
                    <td style="color:#94a3b8; font-size:13px;"><?php echo $i + 1; ?></td>
                    <td><strong style="color:var(--text-dark);"><?php echo htmlspecialchars($p['nama']); ?></strong></td>
                    <td style="font-family:monospace; font-size:13px; color:#64748b;"><?php echo htmlspecialchars($p['nip']); ?></td>
                    <td style="color:#475569;"><?php echo htmlspecialchars($p['jabatan']); ?></td>
                    <td style="text-align:right; white-space:nowrap;">
                        <a href="pegawai.php?edit=<?php echo (int)$p['id']; ?>" class="btn-action edit" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form method="post" action="pegawai.php" style="display:inline;" onsubmit="return confirm('Hapus data pegawai ini?');">
                            <?php echo csrf_token_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                            <button type="submit" class="btn-action delete" title="Hapus">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr style="background:#f0f9ff;">
                    <td colspan="4" style="font-weight:700; color:var(--primary); padding:12px 15px;">
                        <i class="fas fa-users" style="margin-right:6px;"></i>Total PNS
                    </td>
                    <td style="font-weight:700; color:var(--primary); text-align:right; padding:12px 15px;">
                        <?php echo count($pns_list); ?> Orang
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- PPPK Table -->
<div class="card">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <h2><i class="fas fa-id-card" style="margin-right:8px; color:#f59e0b;"></i>Daftar PPPK</h2>
        <span style="background:#fef3c7; color:#d97706; padding:5px 14px; border-radius:50px; font-size:13px; font-weight:700;">
            <?php echo count($pppk_list); ?> Orang
        </span>
    </div>
    <div style="overflow-x:auto;">
        <table class="table">
            <thead>
                <tr>
                    <th style="width:50px;">No</th>
                    <th>Nama</th>
                    <th>NIP</th>
                    <th>Jabatan</th>
                    <th style="text-align:right;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pppk_list)): ?>
                <tr><td colspan="5" style="text-align:center; padding:30px; color:#94a3b8;">Belum ada data PPPK.</td></tr>
                <?php else: ?>
                <?php foreach ($pppk_list as $i => $p): ?>
                <tr>
                    <td style="color:#94a3b8; font-size:13px;"><?php echo $i + 1; ?></td>
                    <td><strong style="color:var(--text-dark);"><?php echo htmlspecialchars($p['nama']); ?></strong></td>
                    <td style="font-family:monospace; font-size:13px; color:#64748b;"><?php echo htmlspecialchars($p['nip']); ?></td>
                    <td style="color:#475569;"><?php echo htmlspecialchars($p['jabatan']); ?></td>
                    <td style="text-align:right; white-space:nowrap;">
                        <a href="pegawai.php?edit=<?php echo (int)$p['id']; ?>" class="btn-action edit" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form method="post" action="pegawai.php" style="display:inline;" onsubmit="return confirm('Hapus data pegawai ini?');">
                            <?php echo csrf_token_field(); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                            <button type="submit" class="btn-action delete" title="Hapus">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <tr style="background:#fffbeb;">
                    <td colspan="4" style="font-weight:700; color:#d97706; padding:12px 15px;">
                        <i class="fas fa-users" style="margin-right:6px;"></i>Total PPPK
                    </td>
                    <td style="font-weight:700; color:#d97706; text-align:right; padding:12px 15px;">
                        <?php echo count($pppk_list); ?> Orang
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'layout_footer.php'; ?>
