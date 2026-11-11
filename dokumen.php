<?php
require_once 'config.php';

$page_title = "Dokumen Publik";
require_once 'includes/header.php';

$items = [];
$res = $conn->query("SELECT * FROM documents ORDER BY created_at DESC");
if ($res) {
    while($r = $res->fetch_assoc()) $items[] = $r;
}
?>

<div style="background-color: var(--primary); color: white; padding: 60px 0 40px 0; text-align: center;">
    <div class="container">
        <h1 style="font-size: 2.5rem; font-weight: 700; margin-bottom: 15px;">Dokumen Publik</h1>
        <div style="width: 60px; height: 4px; background: white; margin: 0 auto; border-radius: 2px; opacity: 0.5;"></div>
    </div>
</div>

<section style="padding: 60px 0; min-height: 50vh; background-color: #f8fafc;">
    <div class="container">
        
        <div style="background: white; padding: 40px; border-radius: var(--radius-md); box-shadow: 0 4px 15px rgba(0,0,0,0.03); max-width: 900px; margin: 0 auto;">
            <?php if(empty($items)): ?>
            <div style="text-align:center; padding: 50px 0; color:#64748b;">
                <i class="fas fa-folder-open" style="font-size:4rem; margin-bottom:20px; opacity:0.5;"></i>
                <h3>Belum ada dokumen tersedia</h3>
            </div>
            <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:15px;">
                <?php foreach($items as $item): ?>
                <a href="<?php echo htmlspecialchars(bapenda_safe_url($item['file_url'] ?? '#', '#')); ?>" target="_blank" rel="noopener noreferrer" style="display:flex; align-items:center; padding:20px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; text-decoration:none; transition:all 0.2s ease;" onmouseover="this.style.background='white'; this.style.boxShadow='0 4px 15px rgba(0,0,0,0.05)'" onmouseout="this.style.background='#f8fafc'; this.style.boxShadow='none'">
                    <i class="fas fa-file-pdf" style="font-size:32px; color:var(--primary); margin-right:20px;"></i>
                    <div style="flex:1;">
                        <h4 style="margin:0 0 5px 0; color:var(--text-dark); font-size:16px;"><?php echo htmlspecialchars($item['title']); ?></h4>
                        <p style="margin:0; color:#64748b; font-size:14px;"><?php echo htmlspecialchars($item['description']); ?></p>
                    </div>
                    <div style="color:var(--primary); font-weight:600; font-size:14px;">
                        <i class="fas fa-download"></i> Unduh
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
