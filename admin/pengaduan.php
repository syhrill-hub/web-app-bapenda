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
require_permission('menu_report_pengaduan');

$pengaduan_upload_dir = __DIR__ . '/../assets/uploads/pengaduan';
bapenda_ensure_upload_dir($pengaduan_upload_dir, true);

$page_title = 'Layanan Pengaduan';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = "Token keamanan tidak valid.";
        header("Location: pengaduan.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // Update Status & Tanggapan
    if ($action === 'update_status') {
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $admin_response = trim((string)($_POST['admin_response'] ?? ''));

        if ($id > 0 && in_array($status, ['pending', 'processed', 'completed'], true)) {
            $stmt = $conn->prepare("UPDATE pengaduan SET status=?, admin_response=? WHERE id=?");
            if ($stmt) {
                $stmt->bind_param("ssi", $status, $admin_response, $id);
                if ($stmt->execute()) {
                    $_SESSION['flash_success'] = "Status & Tanggapan berhasil disimpan!";
                } else {
                    $_SESSION['flash_error'] = "Gagal menyimpan tanggapan.";
                }
                $stmt->close();
            }
        } else {
            $_SESSION['flash_error'] = "Data pengaduan atau status tidak valid.";
        }
        header("Location: pengaduan.php");
        exit;
    }

    // Hapus Laporan (POST + CSRF + cleanup identity_file)
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $identity_file = '';
            $sel = $conn->prepare("SELECT identity_file FROM pengaduan WHERE id = ? LIMIT 1");
            if ($sel) {
                $sel->bind_param("i", $id);
                $sel->execute();
                $res_sel = $sel->get_result();
                $row_sel = $res_sel ? $res_sel->fetch_assoc() : null;
                $identity_file = (string)($row_sel['identity_file'] ?? '');
                $sel->close();
            }

            $stmt = $conn->prepare("DELETE FROM pengaduan WHERE id=?");
            if ($stmt) {
                $stmt->bind_param("i", $id);
                if ($stmt->execute()) {
                    if ($identity_file !== '') {
                        bapenda_safe_unlink($identity_file, $pengaduan_upload_dir);
                    }
                    $_SESSION['flash_success'] = "Laporan berhasil dihapus!";
                } else {
                    $_SESSION['flash_error'] = "Gagal menghapus laporan.";
                }
                $stmt->close();
            }
        }
        header("Location: pengaduan.php");
        exit;
    }
}

require_once 'layout_header.php';

$pengaduan = [];
$res = $conn->query("SELECT * FROM pengaduan ORDER BY created_at DESC");
if($res){
    while($row = $res->fetch_assoc()){
        $pengaduan[] = $row;
    }
}
?>

<div class="content">
    <?php if (isset($_SESSION['flash_success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle" style="margin-right:8px;"></i> <?php echo htmlspecialchars($_SESSION['flash_success'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['flash_success']); ?>
    </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle" style="margin-right:8px;"></i> <?php echo htmlspecialchars($_SESSION['flash_error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['flash_error']); ?>
    </div>
    <?php endif; ?>

    <div class="d-flex" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2><i class="fas fa-envelope-open-text" style="color:var(--primary);margin-right:10px;"></i> Kotak Masuk Pengaduan</h2>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead style="background:#f8fafc;">
                        <tr>
                            <th>Tiket & Tanggal</th>
                            <th>Pelapor</th>
                            <th>Subjek & Pesan</th>
                            <th>Status</th>
                            <th style="min-width:140px; text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($pengaduan as $p): ?>
                        <tr>
                            <td>
                                <strong>#<?php echo htmlspecialchars($p['ticket_no']); ?></strong><br>
                                <small style="color:#64748b;"><i class="fas fa-clock"></i> <?php echo date('d M Y, H:i', strtotime($p['created_at'])); ?></small>
                            </td>
                            <td>
                                <strong><?php echo !empty($p['name']) ? htmlspecialchars($p['name']) : '-'; ?></strong><br>
                                <?php if(!empty($p['nik'])): ?>
                                <small style="color:#64748b;">NIK: <?php echo htmlspecialchars($p['nik']); ?></small><br>
                                <?php endif; ?>
                                <?php if(!empty($p['phone'])): ?>
                                <small style="color:#64748b;">HP: <?php echo htmlspecialchars($p['phone']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td style="max-width:300px;">
                                <strong><?php echo htmlspecialchars($p['subject']); ?></strong>
                                <p style="margin:5px 0 5px; font-size:13px; color:#4a5568; line-height:1.4;">
                                    <?php echo nl2br(htmlspecialchars(substr($p['message'], 0, 100))); ?>...
                                </p>
                                <?php if(!empty($p['identity_file'])): ?>
                                    <a href="pengaduan_file.php?id=<?php echo (int)$p['id']; ?>" target="_blank" rel="noopener noreferrer" style="display:inline-block; font-size:12px; color:#1d4ed8; background:#eff6ff; padding:4px 8px; border-radius:4px; text-decoration:none;"><i class="fas fa-id-card"></i> Lihat Identitas</a>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($p['status'] === 'pending'): ?>
                                    <span class="badge" style="background:#fef2f2;color:#ef4444;border-radius:4px;"><i class="fas fa-circle" style="font-size:8px;"></i> Menunggu</span>
                                <?php elseif($p['status'] === 'processed'): ?>
                                    <span class="badge" style="background:#fef3c7;color:#d97706;border-radius:4px;"><i class="fas fa-spinner fa-spin" style="font-size:10px;"></i> Diproses</span>
                                <?php else: ?>
                                    <span class="badge" style="background:#dcfce7;color:#16a34a;border-radius:4px;"><i class="fas fa-check"></i> Selesai</span>
                                <?php endif; ?>
                            </td>
                                <td style="text-align:right;">
                                    <button type="button" class="btn-action edit" data-id="<?php echo (int)$p['id']; ?>" data-status="<?php echo htmlspecialchars($p['status'], ENT_QUOTES, 'UTF-8'); ?>" data-response="<?php echo htmlspecialchars($p['admin_response'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" onclick="openResponseModalFromBtn(this)" title="Update & Tanggapi"><i class="<?php echo !empty($p['admin_response']) ? 'fas fa-eye' : 'fas fa-reply'; ?>"></i></button>

                                    <form method="POST" action="pengaduan.php" style="display:inline;" onsubmit="return confirm('Hapus laporan ini permanen?');">
                                        <?php echo csrf_token_field(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int)$p['id']; ?>">
                                        <button type="submit" class="btn-action delete" title="Hapus"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($pengaduan)): ?>
                        <tr><td colspan="5" style="text-align:center;">Tidak ada pesan pengaduan yang masuk.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Update Status & Tanggapan -->
<div id="responseModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; width:100%; max-width:500px; border-radius:12px; padding:25px; position:relative; box-shadow:0 15px 35px rgba(0,0,0,0.15);">
        <button type="button" onclick="document.getElementById('responseModal').style.display='none'" style="position:absolute; top:20px; right:20px; background:none; border:none; font-size:20px; cursor:pointer; color:#94a3b8;"><i class="fas fa-times"></i></button>
        <h3 style="margin-top:0; color:var(--text-dark); margin-bottom:20px;"><i class="fas fa-reply" style="color:var(--primary);"></i> Tanggapi & Update Status</h3>
        
        <form method="POST" action="pengaduan.php">
            <?php echo csrf_token_field(); ?>
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="id" id="modal_id" value="">
            
            <div class="form-group" style="margin-bottom:15px;">
                <label style="display:block; margin-bottom:8px; font-weight:600; color:#4a5568;">Status Keluhan</label>
                <select name="status" id="modal_status" class="form-control" style="width:100%; padding:10px 15px; border-radius:8px; border:1px solid #cbd5e1;">
                    <option value="pending">Menunggu (Pending)</option>
                    <option value="processed">Sedang Diproses</option>
                    <option value="completed">Selesai (Completed)</option>
                </select>
            </div>
            
            <div class="form-group" style="margin-bottom:20px;">
                <label style="display:block; margin-bottom:8px; font-weight:600; color:#4a5568;">Tanggapan / Solusi Bapenda</label>
                <textarea name="admin_response" id="modal_response" class="form-control" rows="4" style="width:100%; padding:15px; border-radius:8px; border:1px solid #cbd5e1; resize:vertical;" placeholder="Tuliskan keterangan tindakan, balasan, atau solusi yang diberikan..."></textarea>
                <small style="color:#64748b; font-size:12px; margin-top:5px; display:block;">Pesan ini akan bisa dibaca oleh pengadu saat melakukan tracking tiket.</small>
            </div>
            
            <button type="submit" class="btn" style="width:100%; padding:12px; border-radius:8px; display:flex; justify-content:center; align-items:center; gap:10px; font-size:15px;"><i class="fas fa-save"></i> Simpan Tanggapan</button>
        </form>
    </div>
</div>

<script>
function openResponseModalFromBtn(btn) {
    document.getElementById('modal_id').value = btn.dataset.id || '';
    document.getElementById('modal_status').value = btn.dataset.status || 'pending';
    document.getElementById('modal_response').value = btn.dataset.response || '';
    document.getElementById('responseModal').style.display = 'flex';
}
</script>

<?php require_once 'layout_footer.php'; ?>
