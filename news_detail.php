<?php
require_once 'config.php';

$page_title = 'Berita';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php#berita");
    exit;
}

$id = intval($_GET['id']);
$stmt = $conn->prepare("SELECT * FROM news WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: index.php#berita");
    exit;
}

$news = $result->fetch_assoc();

require_once 'includes/header.php';
?>

<section class="news-detail-section" style="padding: 120px 0 80px; min-height: 60vh;">
    <div class="container" style="max-width: 900px;">
        <a href="index.php#berita" class="back-link" style="display: inline-flex; align-items: center; gap: 8px; color: var(--primary); font-weight: 500; margin-bottom: 30px; text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Kembali ke Berita
        </a>
        
        <article class="news-detail" style="background: #fff; border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-soft);">
            <?php if (!empty($news['image_url'])): ?>
            <div class="news-detail-image" style="width: 100%; height: 400px; background-image: url('<?php echo htmlspecialchars(bapenda_safe_css_bg_url($news['image_url'])); ?>'); background-size: cover; background-position: center;"></div>
            <?php endif; ?>
            
            <div class="news-detail-content" style="padding: 40px;">
                <div class="news-meta" style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px; color: var(--text-muted); font-size: 14px;">
                    <span><i class="far fa-calendar-alt"></i> <?php echo date('d M Y', strtotime($news['created_at'])); ?></span>
                </div>
                
                <h1 style="font-size: 32px; color: var(--text-dark); margin-bottom: 20px; line-height: 1.3;"><?php echo htmlspecialchars($news['title']); ?></h1>
                
                <div class="news-body" style="color: var(--text-light); font-size: 16px; line-height: 1.8;">
                    <?php echo nl2br(htmlspecialchars($news['content'])); ?>
                </div>
                
                <div style="margin-top: 40px; padding-top: 30px; border-top: 1px solid #edf2f7;">
                    <a href="index.php#berita" class="btn-outline" style="display: inline-flex; align-items: center; gap: 8px;">
                        <i class="fas fa-arrow-left"></i> Kembali ke Daftar Berita
                    </a>
                </div>
            </div>
        </article>
    </div>
</section>

<style>
.news-detail-section { background: var(--bg-light); }
.back-link:hover { transform: translateX(-5px); }
.news-body img { max-width: 100%; height: auto; border-radius: var(--radius-md); margin: 20px 0; }
.news-body h2, .news-body h3 { color: var(--text-dark); margin-top: 30px; margin-bottom: 15px; }
.news-body p { margin-bottom: 15px; }
.news-body ul, .news-body ol { margin-bottom: 15px; padding-left: 25px; }
.news-body li { margin-bottom: 8px; }
</style>

<?php require_once 'includes/footer.php'; ?>
