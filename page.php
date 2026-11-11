<?php
require_once 'config.php';

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header("Location: index.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM pages WHERE slug = ?");
$stmt->bind_param("s", $slug);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    header("HTTP/1.0 404 Not Found");
    $page_title = "Halaman Tidak Ditemukan";
    require_once 'includes/header.php';
    echo '<section style="padding: 100px 0; text-align: center; min-height: 50vh;">';
    echo '<div class="container">';
    echo '<h1 style="font-size: 4rem; color: var(--primary); margin-bottom: 20px;">404</h1>';
    echo '<h3>Maaf, Halaman Tidak Ditemukan</h3>';
    echo '<p style="color: #64748b; margin-bottom: 30px;">Tautan yang Anda tuju mungkin rusak atau halaman telah dihapus/belum dipublikasikan.</p>';
    echo '<a href="index.php" class="btn">Kembali ke Beranda</a>';
    echo '</div></section>';
    require_once 'includes/footer.php';
    exit;
}

$page_data = $res->fetch_assoc();
$page_title = $page_data['title'];

// Header Inclusion
require_once 'includes/header.php';
?>

<div style="background-color: #ffffff; padding: 60px 0 20px 0; text-align: center;">
    <div class="container">
        <h1 style="font-size: 2.8rem; font-weight: 700; margin-bottom: 5px; color: #3a1015;"><?php echo htmlspecialchars($page_data['title']); ?></h1>
    </div>
</div>

<section style="padding: 20px 0 80px 0; min-height: 50vh; background-color: #f8fafc;">
    <div class="container">
        <div style="line-height: 1.8; color: #334155; margin: 0 auto;">
            <?php echo $page_data['content']; // Render HTML konten bebas dari wewenang Admin ?>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
