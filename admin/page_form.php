<?php
session_start();
require_once '../config.php';
require_once 'includes/security.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}
require_permission('menu_content_pages');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['flash_error'] = "Token keamanan tidak valid. Silakan coba lagi.";
    header("Location: page_form.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$page_data = [
    'title' => '',
    'slug' => '',
    'content' => ''
];

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM pages WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $page_data = $result->fetch_assoc();
    } else {
        $_SESSION['flash_error'] = "Halaman tidak ditemukan.";
        header("Location: pages.php");
        exit;
    }
}

function create_slug($string) {
    $string = strtolower(trim($string));
    $string = preg_replace('/[^a-z0-9-]/', '-', $string);
    $string = preg_replace('/-+/', "-", $string);
    return trim($string, '-');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $slug = $_POST['slug'] ?? '';
    $content = $_POST['content'] ?? '';
    
    if (empty($slug)) {
        $slug = create_slug($title);
    } else {
        $slug = create_slug($slug);
    }
    
    // Check slug uniqueness
    $stmt = $conn->prepare("SELECT id FROM pages WHERE slug = ? AND id != ?");
    $stmt->bind_param("si", $slug, $id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $slug = $slug . '-' . time();
    }
    
    // Handle File Uploads
    if (!empty($_FILES['attachments']['name'][0])) {
        $upload_dir = '../assets/uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        
        $img_html = "";
        $doc_html = "";
        $img_count = 0;
        
        foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                $file_name = preg_replace('/[^a-zA-Z0-9.\-_]/', '', $_FILES['attachments']['name'][$key]);
                $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $new_fn = time() . '_' . rand(100,999) . '.' . $ext;
                
                if (move_uploaded_file($tmp_name, $upload_dir . $new_fn)) {
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'])) {
                        $img_html .= "<div style=\"background: white; border-radius:12px; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.08); transition:transform 0.3s ease; cursor:pointer;\" onmouseover=\"this.style.transform='translateY(-5px)'\" onmouseout=\"this.style.transform='translateY(0)'\">\n    <img src=\"assets/uploads/$new_fn\" alt=\"Galeri\" style=\"width:100%; height:auto; display:block; border-bottom: 1px solid #f1f5f9;\">\n    <div style=\"padding: 20px; text-align: center;\">\n        <h4 style=\"margin: 0; color: #334155; font-size: 16px;\">Ketik Judul Foto Di Sini</h4>\n        <p style=\"margin: 5px 0 0 0; color: #64748b; font-size: 13px;\">Tulis teks keterangan atau biarkan kosong...</p>\n    </div>\n</div>\n";
                        $img_count++;
                    } elseif (in_array($ext, ['pdf', 'doc', 'docx', 'xls', 'xlsx'])) {
                        $doc_html .= "<a href=\"assets/uploads/$new_fn\" target=\"_blank\" style=\"display:inline-flex; align-items:center; gap:10px; padding:12px 20px; background:var(--bg-light); color:var(--text-dark); border-radius:var(--radius-md); text-decoration:none; margin:10px 10px 10px 0; border:1px solid #e2e8f0;\"><i class=\"fas fa-file-alt\" style=\"color:var(--primary); font-size:24px;\"></i> <div><strong>Dokumen Tautan</strong><br><span style=\"font-size:12px; color:#64748b;\">Klik untuk Mengunduh / Membaca</span></div></a>\n";
                    }
                }
            }
        }
        
        if ($img_count > 0) {
            $content .= "\n\n<!-- Galeri Foto Otomatis -->\n<div style=\"display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 30px; margin-top: 30px;\">\n" . $img_html . "</div>\n";
        }
        if (!empty($doc_html)) {
            $content .= "\n\n<!-- Kumpulan Dokumen -->\n<div style=\"margin-top: 20px;\">\n" . $doc_html . "</div>\n";
        }
    }
    
    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE pages SET title = ?, slug = ?, content = ? WHERE id = ?");
        $stmt->bind_param("sssi", $title, $slug, $content, $id);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Halaman berhasil diperbarui!";
            header("Location: pages.php");
            exit;
        } else {
            $error = "Terjadi kesalahan saat memperbarui: " . $conn->error;
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO pages (title, slug, content) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $title, $slug, $content);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Halaman baru berhasil dibuat!";
            header("Location: pages.php");
            exit;
        } else {
            $error = "Terjadi kesalahan saat menyimpan: " . $conn->error;
        }
    }
}

$page_title = $id > 0 ? "Edit Halaman" : "Buat Halaman Baru";
require_once 'layout_header.php';
?>

<?php if(isset($error)): ?>
    <div class="alert" style="background:#fee2e2; color:#991b1b; padding:15px; border-radius:12px; margin-bottom:20px;">
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2><?php echo $page_title; ?></h2>
        <a href="pages.php" class="btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
    </div>
    
    <form method="post" enctype="multipart/form-data">
        <?php echo csrf_token_field(); ?>
        <div class="form-group">
            <label>Judul Halaman <span style="color:red;">*</span></label>
            <input type="text" name="title" id="pageTitle" class="form-control" value="<?php echo htmlspecialchars($page_data['title']); ?>" required>
        </div>
        
        <div class="form-group">
            <label>Unggah Gambar Galeri / Dokumen PDF (Pilih Banyak File Tahan Tombol CTRL)</label>
            <div style="background:#f8fafc; border:1px dashed #cbd5e1; padding:20px; border-radius:var(--radius-md); text-align:center;">
                <input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx" style="max-width:300px; margin:0 auto; display:block;">
                <p style="color:#64748b; font-size:13px; margin:10px 0 0 0;">Jika Anda memilih file, sistem akan mengunggah dan <strong>otomatis merakitnya menjadi Galeri Foto atau Tombol Dokumen</strong> di akhir paragraf konten Anda pada saat menekan Simpan.</p>
            </div>
        </div>
        
        <div class="form-group">
            <label>Tautan URL (Slug) - <i>Opsional, akan dibuat otomatis jika kosong</i></label>
            <div style="display:flex; align-items:center; background:#f8fafc; border:1px solid #e2e8f0; border-radius:var(--radius-md); padding:0 15px;">
                <span style="color:#64748b; font-size:14px; margin-right:5px;">page.php?slug=</span>
                <input type="text" name="slug" id="pageSlug" style="border:none; outline:none; background:transparent; padding:12px 0; width:100%; font-family:monospace; color:var(--primary);" value="<?php echo htmlspecialchars($page_data['slug']); ?>" placeholder="contoh-judul-halaman">
            </div>
            <small style="color:#64748b; font-size:13px; margin-top:5px; display:inline-block;">Gunakan lowercase karakter dan simbol strip "-" sebagai spasi.</small>
        </div>
        
        <div class="form-group">
            <label>Isi Konten Halaman <span style="color:red;">*</span></label>
            <textarea name="content" class="form-control" rows="18" placeholder="Masukkan konten teks laporan Anda di sini..." required style="font-family: monospace; font-size: 14px; line-height: 1.6; padding: 15px;"><?php echo htmlspecialchars($page_data['content']); ?></textarea>
            <small style="color:#64748b; font-size:13px; margin-top:8px; display:inline-block;">Mendukung tag HTML standar (&lt;h3&gt;, &lt;p&gt;, &lt;strong&gt;, &lt;ul&gt;, dst).</small>
        </div>
        
        <div style="margin-top: 30px;">
            <button type="submit" class="btn" style="width: 100%; padding: 15px; font-size: 16px;"><i class="fas fa-save"></i> <?php echo $id > 0 ? 'Simpan Perubahan' : 'Buat Halaman'; ?></button>
        </div>
    </form>
</div>

<script>
// Auto-generate slug helper
document.getElementById('pageTitle').addEventListener('keyup', function() {
    <?php if($id == 0): // Only auto-fill if new ?>
    let text = this.value;
    text = text.toLowerCase().trim()
        .replace(/[^a-z0-9 -]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-');
    document.getElementById('pageSlug').value = text;
    <?php endif; ?>
});
</script>

<?php require_once 'layout_footer.php'; ?>
