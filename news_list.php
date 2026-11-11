<?php
require_once 'config.php';

$page_title = 'Berita Terbaru';

$result = $conn->query("SELECT * FROM news ORDER BY created_at DESC");
$all_news = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $all_news[] = $row;
    }
}

require_once 'includes/header.php';
?>

<section class="news-list-section" style="padding: 120px 0 80px; min-height: 70vh; background: var(--bg-light);">
    <div class="container">
        <div class="section-header" style="text-align: center; margin-bottom: 50px;">
            <h2 style="font-size: 36px; color: var(--primary); margin-bottom: 15px;">Berita Terbaru</h2>
            <p style="font-size: 16px; color: var(--text-light); max-width: 600px; margin: 0 auto;">Ikuti berita dan informasi terkini dari Badan Pendapatan Daerah Kotawaringin Barat</p>
        </div>
        
        <?php if (empty($all_news)): ?>
        <div class="news-empty" style="text-align: center; padding: 60px 20px; background: #fff; border-radius: var(--radius-lg); box-shadow: var(--shadow-soft);">
            <i class="fas fa-newspaper" style="font-size: 60px; color: #e2e8f0; margin-bottom: 20px;"></i>
            <h3 style="color: var(--text-dark); margin-bottom: 10px;">Belum Ada Berita</h3>
            <p style="color: var(--text-muted);">Saat ini belum ada berita yang dipublikasikan.</p>
        </div>
        <?php else: ?>
        <div class="news-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 30px;">
            <?php foreach ($all_news as $n): ?>
            <div class="news-card" style="background: #fff; border-radius: var(--radius-lg); overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.02); border: 1px solid rgba(0,0,0,0.02); transition: var(--transition);">
                <div class="news-image" style="height: 220px; background-image: url('<?php echo htmlspecialchars($n['image_url']); ?>'); background-size: cover; background-position: center; position: relative;">
                    <span class="news-date-badge" style="position: absolute; bottom: 15px; left: 15px; background: rgba(255,255,255,0.9); backdrop-filter: blur(5px); color: var(--primary); padding: 8px 15px; border-radius: var(--radius-md); box-shadow: 0 4px 10px rgba(0,0,0,0.05); font-size: 13px; font-weight: 600;">
                        <i class="far fa-calendar-alt"></i> <?php echo date('d M Y', strtotime($n['created_at'])); ?>
                    </span>
                </div>
                <div class="news-content" style="padding: 25px;">
                    <h3 style="font-size: 20px; color: var(--primary); margin-bottom: 15px; line-height: 1.4;"><?php echo htmlspecialchars($n['title']); ?></h3>
                    <p style="font-size: 14px; color: var(--text-light); line-height: 1.7; margin-bottom: 20px; display: -webkit-box; -webkit-line-clamp: 3; line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;"><?php echo htmlspecialchars($n['snippet']); ?></p>
                    <a href="berita/detail/<?php echo (int)$n['id']; ?>" class="read-more" style="display: inline-flex; align-items: center; gap: 8px; font-weight: 600; color: var(--secondary); font-size: 14px;">
                        Baca Selengkapnya <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div style="text-align: center; margin-top: 50px;">
            <a href="index.php" class="btn-outline"><i class="fas fa-arrow-left" style="margin-right: 8px;"></i> Kembali ke Beranda</a>
        </div>
    </div>
</section>

<style>
.news-card:hover { transform: translateY(-10px); box-shadow: var(--shadow-soft); }
</style>

<?php require_once 'includes/footer.php'; ?>
