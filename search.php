<?php
require_once 'includes/header.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];

if (!empty($q)) {
    $search_term = "%" . addcslashes($q, "\\%_") . "%";

    // Cari di Berita (news)
    $stmt1 = $conn->prepare("SELECT id, title, left(content, 150) as snippet, 'Berita' as type, 'news_detail.php?id=' as url_prefix FROM news WHERE title LIKE ? OR content LIKE ? ORDER BY created_at DESC LIMIT 10");
    $stmt1->bind_param("ss", $search_term, $search_term);
    $stmt1->execute();
    $res1 = $stmt1->get_result();
    while($row = $res1->fetch_assoc()) {
        $row['url'] = $row['url_prefix'] . $row['id'];
        $results[] = $row;
    }

    // Cari di Dokumen (documents)
    $stmt2 = $conn->prepare("SELECT id, title, file_url as snippet, 'Dokumen' as type, '' as url_prefix FROM documents WHERE title LIKE ? ORDER BY created_at DESC LIMIT 10");
    $stmt2->bind_param("s", $search_term);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while($row = $res2->fetch_assoc()) {
        $row['url'] = "dokumen.php"; // Tautan ke halaman daftar dokumen
        $row['snippet'] = 'Unduh dokumen: ' . basename($row['snippet']);
        $results[] = $row;
    }

    // Cari di Halaman Statis (pages)
    $stmt3 = $conn->prepare("SELECT id, slug, title, left(content, 150) as snippet, 'Halaman Dasar' as type FROM pages WHERE title LIKE ? OR content LIKE ? ORDER BY created_at DESC LIMIT 10");
    $stmt3->bind_param("ss", $search_term, $search_term);
    $stmt3->execute();
    $res3 = $stmt3->get_result();
    while($row = $res3->fetch_assoc()) {
        $row['url'] = 'page.php?slug=' . rawurlencode((string)$row['slug']);
        $results[] = $row;
    }

    // Cari di Agenda (agenda)
    $stmt4 = $conn->prepare("SELECT id, title, description as snippet, 'Agenda' as type, 'index.php#' as url_prefix FROM agenda WHERE title LIKE ? OR description LIKE ? ORDER BY event_date DESC LIMIT 10");
    $stmt4->bind_param("ss", $search_term, $search_term);
    $stmt4->execute();
    $res4 = $stmt4->get_result();
    while($row = $res4->fetch_assoc()) {
        $row['url'] = "index.php"; // Agenda tampil di halaman depan
        $results[] = $row;
    }
}
?>

<div style="background: var(--bg-light); padding: 40px 0; min-height: 60vh;">
    <div class="container" style="max-width: 800px;">
        <h1 style="color:var(--text-dark); margin-bottom: 20px;">Hasil Pencarian</h1>
        
        <form action="search.php" method="GET" style="display:flex; gap:10px; margin-bottom:30px;">
            <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>" class="form-control" placeholder="Cari info perpajakan..." style="flex:1; padding:15px 20px; font-size:16px; border-radius:50px; border:2px solid #e2e8f0; outline:none;" required>
            <button type="submit" style="background:var(--primary); color:#fff; border:none; padding:0 30px; border-radius:50px; cursor:pointer; font-size:16px; font-weight:600;"><i class="fas fa-search"></i> Cari Ulang</button>
        </form>

        <?php if(empty($q)): ?>
            <div style="text-align:center; padding:50px; background:#fff; border-radius:12px; border:1px solid #e2e8f0;">
                <i class="fas fa-search" style="font-size:40px; color:#cbd5e1; margin-bottom:15px;"></i>
                <h3 style="color:#64748b;">Silakan masukkan kata kunci pencarian</h3>
            </div>
        <?php elseif(empty($results)): ?>
            <div style="text-align:center; padding:50px; background:#fff; border-radius:12px; border:1px solid #e2e8f0;">
                <i class="fas fa-exclamation-circle" style="font-size:40px; color:#cbd5e1; margin-bottom:15px;"></i>
                <h3 style="color:#64748b;">Pencarian "<?php echo htmlspecialchars($q); ?>" tidak ditemukan</h3>
                <p style="color:#94a3b8;">Coba gunakan kata kunci lain yang lebih umum, contoh: "PBB", "BPHTB", atau "Peraturan".</p>
            </div>
        <?php else: ?>
            <p style="color:#64748b; margin-bottom:15px;">Ditemukan <strong><?php echo count($results); ?></strong> hasil untuk "<?php echo htmlspecialchars($q); ?>"</p>
            
            <div style="display:flex; flex-direction:column; gap:15px;">
                <?php foreach($results as $r): ?>
                <a href="<?php echo htmlspecialchars($r['url']); ?>" style="display:block; background:#fff; padding:20px; border-radius:12px; border:1px solid #e2e8f0; text-decoration:none; transition:0.3s; box-shadow:0 4px 6px rgba(0,0,0,0.02);" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='#e2e8f0'">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                        <h3 style="margin:0; font-size:18px; color:var(--primary);"><?php echo htmlspecialchars($r['title']); ?></h3>
                        <span style="background:#f1f5f9; color:#64748b; padding:4px 10px; border-radius:20px; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px;"><?php echo htmlspecialchars($r['type']); ?></span>
                    </div>
                    <p style="margin:0; font-size:14px; color:#4a5568; line-height:1.5;">
                        <?php echo htmlspecialchars(strip_tags((string)$r['snippet']), ENT_QUOTES, 'UTF-8'); ?>...
                    </p>
                </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
