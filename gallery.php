<?php
require_once 'config.php';

$page_title = "Galeri Publik";
require_once 'includes/header.php';

$items = [];
$res = $conn->query("SELECT * FROM gallery ORDER BY created_at DESC");
if ($res) {
    while($r = $res->fetch_assoc()) $items[] = $r;
}
?>

<div style="background-color: #ffffff; padding: 60px 0 20px 0; text-align: center;">
    <div class="container">
        <h1 style="font-size: 2.8rem; font-weight: 700; margin-bottom: 5px; color: #3a1015;">Galeri Bapenda Kobar</h1>
    </div>
</div>

<section style="padding: 20px 0 80px 0; min-height: 50vh; background-color: #f8fafc;">
    <div class="container">
        
        <?php if(empty($items)): ?>
        <div style="text-align:center; padding: 100px 0; color:#64748b;">
            <i class="fas fa-images" style="font-size:4rem; margin-bottom:20px; opacity:0.5;"></i>
            <h3>Belum ada foto galeri</h3>
        </div>
        <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 30px;">
            <?php foreach($items as $item): ?>
            <div style="background: white; border-radius:12px; overflow:hidden; box-shadow:0 4px 15px rgba(0,0,0,0.08); transition:transform 0.3s ease; cursor:pointer;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>" style="width:100%; height:auto; display:block; border-bottom: 1px solid #f1f5f9;">
                <div style="padding: 20px; text-align: center;">
                    <h4 style="margin: 0; color: #1e293b; font-size: 16px; font-weight:600;"><?php echo htmlspecialchars($item['title']); ?></h4>
                    <?php if(!empty($item['description'])): ?>
                    <p style="margin: 8px 0 0 0; color: #64748b; font-size: 14px; line-height:1.5;"><?php echo htmlspecialchars($item['description']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
