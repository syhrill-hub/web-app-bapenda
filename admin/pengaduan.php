<?php
session_start();
require_once '../config.php';
require_once 'includes/security.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
require_permission('menu_report_pengaduan');

$page_title = 'Layanan Pengaduan';

// Update Status & Tanggapan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $id = $_POST['id'] ?? 0;
    $status = $_POST['status'] ?? '';
    $admin_response = $_POST['admin_response'] ?? '';
    
    if (in_array($status, ['pending', 'processed', 'completed'])) {
        $stmt = $conn->prepare("UPDATE pengaduan SET status=?, admin_response=? WHERE id=?");
        $stmt->bind_param("ssi", $status, $admin_response, $id);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Status & Tanggapan berhasil disimpan!";
        }
    }
    header("Location: pengaduan.php");
    exit;
}

// Hapus Laporan
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM pengaduan WHERE id=?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['flash_success'] = "Laporan berhasil dihapus!";
    }
    header("Location: pengaduan.php");
    exit;
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
        <i class="fas fa-check-circle" style="margin-right:8px;"></i> <?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
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
                                    <a href="../assets/uploads/pengaduan/<?php echo htmlspecialchars($p['identity_file']); ?>" target="_blank" style="display:inline-block; font-size:12px; color:#1d4ed8; background:#eff6ff; padding:4px 8px; border-radius:4px; text-decoration:none;"><i class="fas fa-id-card"></i> Lihat Identitas</a>
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
                                    <button class="btn-action edit" onclick="openResponseModal(<?php echo $p['id']; ?>, '<?php echo $p['status']; ?>', '<?php echo htmlspecialchars(addslashes($p['admin_response'] ?? '')); ?>')" title="Update & Tanggapi"><i class="<?php echo !empty($p['admin_response']) ? 'fas fa-eye' : 'fas fa-reply'; ?>"></i></button>
                                    
                                    <button class="btn-action delete" onclick="if(confirm('Hapus laporan ini permanen?')) window.location.href='pengaduan.php?delete=<?php echo $p['id']; ?>'"><i class="fas fa-trash"></i></button>
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
function openResponseModal(id, currentStatus, currentResponse) {
    document.getElementById('modal_id').value = id;
    document.getElementById('modal_status').value = currentStatus;
    document.getElementById('modal_response').value = currentResponse;
    document.getElementById('responseModal').style.display = 'flex';
}
</script>

<?php require_once 'layout_footer.php'; ?>
