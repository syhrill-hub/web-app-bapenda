<?php
session_start();
require_once '../config.php';
require_once 'includes/security.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
require_permission('menu_content_gallery');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = "Token keamanan tidak valid. Silakan coba lagi.";
    header("Location: gallery.php");
    exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM gallery WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $_SESSION['flash_success'] = "Foto berhasil dihapus!";
    }
    header("Location: gallery.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../assets/uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        
        $file_name = preg_replace('/[^a-zA-Z0-9.\-_]/', '', $_FILES['image']['name']);
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $new_fn = 'gallery_' . time() . '_' . rand(100,999) . '.' . $ext;
        
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $new_fn)) {
                $image_url = 'assets/uploads/' . $new_fn;
                $stmt = $conn->prepare("INSERT INTO gallery (title, description, image_url) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $title, $description, $image_url);
                if ($stmt->execute()) {
                    $_SESSION['flash_success'] = "Foto berhasil ditambahkan ke Galeri!";
                } else {
                    $_SESSION['flash_error'] = "Gagal menyimpan ke database.";
                }
            } else {
                $_SESSION['flash_error'] = "Gagal memindahkan file yang diunggah.";
            }
        } else {
            $_SESSION['flash_error'] = "Format file tidak valid. Harus gambar (JPG, PNG, WEBP).";
        }
    } else {
        $_SESSION['flash_error'] = "Gambar wajib diunggah.";
    }
    header("Location: gallery.php");
    exit;
}

$items = [];
$res = $conn->query("SELECT * FROM gallery ORDER BY created_at DESC");
if ($res) {
    while($r = $res->fetch_assoc()) $items[] = $r;
}

$page_title = "Manajemen Galeri";
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
        <h2>Tambah Foto Ke Galeri</h2>
    </div>
    <p style="color:#64748b; line-height: 1.6; margin-bottom: 20px;">Formulir ini dirancang khusus dan jauh lebih sederhana dari Halaman Statis. Anda cukup mengunggah 1 foto dan memberi keterangan. Berkas foto akan otomatis direntang penuh (tidak dipotong) seperti halnya desain poster kalender pajak.</p>
    <form method="post" enctype="multipart/form-data">
        <?php echo csrf_token_field(); ?>
        <div class="form-group">
            <label>Judul Foto / Poster <span style="color:red;">*</span></label>
            <input type="text" name="title" class="form-control" placeholder="Contoh: Jadwal Jatuh Tempo PBB" required>
        </div>
        <div class="form-group">
            <label>Keterangan Singkat (Deskripsi opsional)</label>
            <textarea name="description" class="form-control" rows="3" placeholder="Contoh: Periode jatuh tempo diperpanjang sampai..."></textarea>
        </div>
        <div class="form-group">
            <label>Unggah Gambar <span style="color:red;">*</span></label>
            <input type="file" name="image" class="form-control" accept="image/*" required>
        </div>
        <button type="submit" class="btn"><i class="fas fa-upload"></i> Tambahkan ke Galeri Publik</button>
    </form>
</div>

<div class="card" style="margin-top: 30px;">
    <div class="card-header">
        <h2>Daftar Foto Galeri Saat Ini</h2>
        <div style="font-size:14px; text-decoration: underline; color: var(--primary);">
            URL Menu: <strong>gallery.php</strong>
        </div>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px;">
        <?php foreach($items as $item): ?>
        <div style="border:1px solid #e2e8f0; border-radius:var(--radius-md); overflow:hidden;">
            <div style="background:#f8fafc; text-align:center;">
                <img src="../<?php echo htmlspecialchars($item['image_url']); ?>" style="width:100%; height:200px; object-fit:cover; display:block;">
            </div>
            <div style="padding:15px; background: white;">
                <h4 style="margin:0 0 5px 0; font-size:16px; color:var(--text-dark);"><?php echo htmlspecialchars($item['title']); ?></h4>
                <p style="margin:0 0 15px 0; font-size:13px; color:#64748b; line-height:1.5;"><?php echo htmlspecialchars($item['description']); ?></p>
                <a href="gallery.php?delete=<?php echo $item['id']; ?>" class="btn-action delete" onclick="return confirm('Hapus foto ini?');" style="display:inline-block; margin-top:10px;"><i class="fas fa-trash"></i> Hapus</a>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($items)): ?>
        <p style="color:#64748b;">Belum ada foto di galeri Anda.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'layout_footer.php'; ?>
