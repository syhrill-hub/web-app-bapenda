<?php
require_once __DIR__ . '/../includes/security_helpers.php';
bapenda_start_secure_session();
require_once '../config.php';
require_once 'includes/security.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
require_permission('menu_content_agenda');

$page_title = 'Manajemen Agenda Kegiatan';

// Hapus Agenda (CSRF POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = "Token tidak valid.";
        header("Location: agenda.php");
        exit;
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM agenda WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $_SESSION['flash_success'] = "Agenda berhasil dihapus!";
        } else {
            $_SESSION['flash_error'] = "Agenda tidak ditemukan.";
        }
        $stmt->close();
    }
    header("Location: agenda.php");
    exit;
}

// Tambah / Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_error'] = "Token tidak valid.";
        header("Location: agenda.php");
        exit;
    }
    
    $id = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_date = $_POST['event_date'] ?? '';
    $location = trim($_POST['location'] ?? '');
    
    if (empty($title) || empty($event_date)) {
        $_SESSION['flash_error'] = "Semua kolom yang wajib harus diisi!";
        header("Location: agenda.php");
        exit;
    }
    
    if ($_POST['action'] === 'add') {
        $stmt = $conn->prepare("INSERT INTO agenda (title, description, event_date, location) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $title, $description, $event_date, $location);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Agenda berhasil ditambahkan!";
        } else {
            error_log("Agenda insert error: " . $conn->error);
            $_SESSION['flash_error'] = "Gagal menambah agenda.";
        }
    } elseif ($_POST['action'] === 'edit' && $id > 0) {
        $stmt = $conn->prepare("UPDATE agenda SET title=?, description=?, event_date=?, location=? WHERE id=?");
        $stmt->bind_param("ssssi", $title, $description, $event_date, $location, $id);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Agenda berhasil diperbarui!";
        } else {
            $_SESSION['flash_error'] = "Gagal memperbarui agenda.";
        }
    }
    header("Location: agenda.php");
    exit;
}

require_once 'layout_header.php';

$agendas = [];
$res = $conn->query("SELECT * FROM agenda ORDER BY event_date ASC");
if($res){
    while($row = $res->fetch_assoc()){
        $agendas[] = $row;
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
        <i class="fas fa-exclamation-circle" style="margin-right:8px;"></i> <?php echo htmlspecialchars($_SESSION['flash_error'], ENT_QUOTES, 'UTF-8'); unset($_SESSION['flash_error']); ?>
    </div>
    <?php endif; ?>

    <div class="d-flex" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h2><i class="fas fa-calendar-alt" style="color:var(--primary);margin-right:10px;"></i> Agenda Kegiatan & Jatuh Tempo</h2>
        <button class="btn" onclick="openModal('addModal')"><i class="fas fa-plus"></i> Tambah Agenda</button>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead style="background:#f8fafc;">
                        <tr>
                            <th>Tgl/Waktu Exec</th>
                            <th>Nama Agenda</th>
                            <th>Lokasi</th>
                            <th>Status/Deskripsi</th>
                            <th style="min-width:140px; text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($agendas as $a): 
                            $is_past = strtotime($a['event_date']) < time();
                            $badge = $is_past ? '<span class="badge" style="background:#f1f5f9;color:#64748b;border-radius:4px;">Selesai</span>' : '<span class="badge" style="background:#dcfce7;color:#16a34a;border-radius:4px;">Akan Datang</span>';
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo date('d M Y', strtotime($a['event_date'])); ?></strong><br>
                                <small style="color:#64748b;"><i class="fas fa-clock"></i> <?php echo date('H:i', strtotime($a['event_date'])); ?> WIB</small>
                            </td>
                            <td><strong><?php echo htmlspecialchars($a['title']); ?></strong></td>
                            <td><i class="fas fa-map-marker-alt text-muted"></i> <?php echo htmlspecialchars($a['location']); ?></td>
                            <td>
                                <?php echo $badge; ?>
                                <p style="margin:5px 0 0; font-size:12px; color:#64748b;"><?php echo htmlspecialchars($a['description']); ?></p>
                            </td>
                            <td style="text-align:right;">
                                <button type="button" class="btn-action edit" onclick="editAgenda(<?php echo htmlspecialchars(bapenda_json_encode($a), ENT_QUOTES, 'UTF-8'); ?>)"><i class="fas fa-edit"></i></button>
                                <form method="post" action="agenda.php" style="display:inline;" onsubmit="return confirm('Hapus agenda ini?');">
                                    <?php echo csrf_token_field(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                                    <button type="submit" class="btn-action delete"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if(empty($agendas)): ?>
                        <tr><td colspan="5" style="text-align:center;">Tidak ada agenda yang dijadwalkan.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah/Edit -->
<div id="agendaModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:999; align-items:center; justify-content:center;">
    <div style="background:#fff; width:100%; max-width:550px; border-radius:12px; overflow:hidden;">
        <div style="padding:20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
            <h3 id="modalTitle" style="margin:0; font-size:18px;">Form Agenda</h3>
            <button type="button" onclick="document.getElementById('agendaModal').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:18px;"><i class="fas fa-times"></i></button>
        </div>
        <form method="post" action="agenda.php" style="padding:20px;">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="id" id="formId" value="">
            <?php echo csrf_token_field(); ?>
            
            <div class="form-group">
                <label>Nama Agenda / Event <span style="color:red">*</span></label>
                <input type="text" name="title" id="formTitle" class="form-control" required placeholder="Cth: Jatuh Tempo PBB 2026">
            </div>
            <div style="display:flex; gap:15px; margin-bottom:20px;">
                <div style="flex:1;">
                    <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#4a5568;">Waktu Pelaksanaan <span style="color:red">*</span></label>
                    <input type="datetime-local" name="event_date" id="formDate" class="form-control" required>
                </div>
                <div style="flex:1;">
                    <label style="display:block;margin-bottom:8px;font-weight:600;font-size:14px;color:#4a5568;">Lokasi</label>
                    <input type="text" name="location" id="formLocation" class="form-control" placeholder="Cth: Aula Bapenda">
                </div>
            </div>
            <div class="form-group">
                <label>Deskripsi Singkat</label>
                <textarea name="description" id="formDesc" class="form-control" rows="3" placeholder="Informasi tambahan terkait agenda..."></textarea>
            </div>
            
            <div style="text-align:right; margin-top:20px;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('agendaModal').style.display='none'" style="margin-right:10px;">Batal</button>
                <button type="submit" class="btn"><i class="fas fa-save"></i> Simpan Agenda</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal() {
    document.getElementById('modalTitle').innerText = 'Tambah Agenda Baru';
    document.getElementById('formAction').value = 'add';
    document.getElementById('formId').value = '';
    document.getElementById('formTitle').value = '';
    document.getElementById('formDate').value = '';
    document.getElementById('formLocation').value = '';
    document.getElementById('formDesc').value = '';
    document.getElementById('agendaModal').style.display = 'flex';
}

function editAgenda(data) {
    document.getElementById('modalTitle').innerText = 'Edit Agenda';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('formId').value = data.id;
    document.getElementById('formTitle').value = data.title;
    // Format for datetime-local
    let dt = new Date(data.event_date);
    let dtString = dt.getFullYear() + '-' + ('0' + (dt.getMonth()+1)).slice(-2) + '-' + ('0' + dt.getDate()).slice(-2) + 'T' + ('0' + dt.getHours()).slice(-2) + ':' + ('0' + dt.getMinutes()).slice(-2);
    document.getElementById('formDate').value = dtString;
    document.getElementById('formLocation').value = data.location;
    document.getElementById('formDesc').value = data.description;
    
    document.getElementById('agendaModal').style.display = 'flex';
}
</script>

<?php require_once 'layout_footer.php'; ?>
