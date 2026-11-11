<?php
require_once 'config.php';

// Auto-create table
$conn->query("CREATE TABLE IF NOT EXISTS pegawai (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(255) NOT NULL,
    nip VARCHAR(50) DEFAULT '',
    jabatan VARCHAR(255) DEFAULT '',
    jenis ENUM('PNS','PPPK') NOT NULL DEFAULT 'PNS',
    order_num INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Fetch PNS
$pns_list = [];
$res = $conn->query("SELECT * FROM pegawai WHERE jenis='PNS' ORDER BY order_num ASC, id ASC");
if ($res)
    while ($row = $res->fetch_assoc())
        $pns_list[] = $row;

// Fetch PPPK
$pppk_list = [];
$res = $conn->query("SELECT * FROM pegawai WHERE jenis='PPPK' ORDER BY order_num ASC, id ASC");
if ($res)
    while ($row = $res->fetch_assoc())
        $pppk_list[] = $row;

$total_pns = count($pns_list);
$total_pppk = count($pppk_list);
$total_all = $total_pns + $total_pppk;

$page_title = "Data Pegawai";
require_once 'includes/header.php';
?>

<style>
/* Page Banner */
.page-banner {
    background: linear-gradient(135deg, var(--primary) 0%, #075985 100%);
    padding: 50px 0 50px;
    margin-top: 110px;
    text-align: center;
    color: #fff;
    position: relative;
    overflow: hidden;
}
.page-banner::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -10%;
    width: 50%;
    height: 200%;
    background: rgba(255,255,255,0.04);
    border-radius: 50%;
}
.page-banner h1 {
    font-size: 2.4rem;
    font-weight: 800;
    margin: 0 0 10px 0;
    letter-spacing: -0.5px;
}
.page-banner p {
    font-size: 1rem;
    opacity: 0.85;
    margin: 0;
}
.breadcrumb-nav {
    font-size: 13px;
    opacity: 0.75;
    margin-bottom: 15px;
}
.breadcrumb-nav a { color: #fff; text-decoration: none; }
.breadcrumb-nav a:hover { text-decoration: underline; }

/* Stats Bar */
.stats-bar {
    background: #fff;
    border-bottom: 1px solid #f1f5f9;
    padding: 20px 0;
}
.pegawai-stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0;
    max-width: 600px;
    margin: 0 auto;
    text-align: center;
}
.pegawai-stat-item {
    padding: 10px 20px;
}
.pegawai-stat-item:not(:last-child) {
    border-right: 1px solid #f1f5f9;
}
.pegawai-stat-number {
    font-size: 2.2rem;
    font-weight: 800;
    line-height: 1;
    margin-bottom: 4px;
}
.pegawai-stat-label {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #94a3b8;
}

/* Content Section */
.pegawai-section {
    padding: 50px 0 80px;
    background: #f8fafc;
}
.section-title {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 18px;
}
.section-title h2 {
    font-size: 1.35rem;
    font-weight: 800;
    margin: 0;
}
.badge-count {
    border-radius: 50px;
    padding: 5px 16px;
    font-size: 13px;
    font-weight: 700;
}
.badge-pns  { background: #dbeafe; color: #1d4ed8; }
.badge-pppk { background: #fef3c7; color: #d97706; }

/* Table */
.table-card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 25px rgba(0,0,0,0.04);
    overflow: hidden;
    margin-bottom: 35px;
}
.table-responsive { overflow-x: auto; }
.data-table {
    width: 100%;
    border-collapse: collapse;
}
.data-table thead th {
    background: #f8fafc;
    padding: 14px 18px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #94a3b8;
    border-bottom: 2px solid #f1f5f9;
    white-space: nowrap;
}
.data-table tbody tr {
    border-bottom: 1px solid #f8fafc;
    transition: background 0.15s;
}
.data-table tbody tr:hover { background: #f8fafc; }
.data-table tbody td {
    padding: 14px 18px;
    vertical-align: middle;
    color: #334155;
    font-size: 14px;
}
.col-no { width: 50px; text-align: center; color: #cbd5e1 !important; font-weight: 600; }
.col-nip { font-family: 'Courier New', monospace; font-size: 13px !important; color: #64748b !important; }
.td-name { font-weight: 700; color: #1e293b !important; }
.td-jabatan { color: #475569 !important; }
.total-row td {
    font-weight: 700;
    padding: 14px 18px;
    font-size: 14px;
    border-top: 2px solid #f1f5f9;
}
.no-data {
    text-align: center;
    padding: 50px 20px;
    color: #94a3b8;
    font-size: 14px;
}
.no-data i { font-size: 2.5rem; display: block; margin-bottom: 12px; color: #e2e8f0; }

/* Grand Total */
.grand-total-box {
    background: linear-gradient(135deg, var(--primary), #075985);
    color: #fff;
    border-radius: 16px;
    padding: 28px 35px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 10px;
    box-shadow: 0 8px 25px rgba(14,165,233,0.3);
}
.grand-total-box .gt-label {
    font-size: 1rem;
    font-weight: 600;
    opacity: 0.9;
}
.grand-total-box .gt-value {
    font-size: 2.5rem;
    font-weight: 800;
    letter-spacing: -0.5px;
}
.grand-total-box .gt-sub {
    font-size: 13px;
    opacity: 0.75;
    margin-top: 3px;
}

@media (max-width: 768px) {
    .page-banner h1 { font-size: 1.8rem; }
    .page-banner { margin-top: 90px; }
    .pegawai-stats-grid { grid-template-columns: repeat(3,1fr); }
    .pegawai-stat-number { font-size: 1.6rem; }
    .grand-total-box { flex-direction: column; text-align: center; gap: 10px; }
}
</style>

<!-- Page Banner -->
<div class="page-banner">
    <div class="container">
        <div class="breadcrumb-nav">
            <a href="index.php">Beranda</a> &rsaquo; Profil &rsaquo; Data Pegawai
        </div>
        <h1><i class="fas fa-users" style="margin-right:12px; opacity:0.85;"></i>Data Pegawai</h1>
        <p>Badan Pendapatan Daerah Kabupaten Kotawaringin Barat</p>
    </div>
</div>

<!-- Stats Bar -->
<div class="stats-bar">
    <div class="container">
        <div class="pegawai-stats-grid">
            <div class="pegawai-stat-item">
                <div class="pegawai-stat-number" style="color:var(--primary);"><?php echo $total_pns; ?></div>
                <div class="pegawai-stat-label">PNS</div>
            </div>
            <div class="pegawai-stat-item">
                <div class="pegawai-stat-number" style="color:#f59e0b;"><?php echo $total_pppk; ?></div>
                <div class="pegawai-stat-label">PPPK</div>
            </div>
            <div class="pegawai-stat-item">
                <div class="pegawai-stat-number" style="color:#10b981;"><?php echo $total_all; ?></div>
                <div class="pegawai-stat-label">Total</div>
            </div>
        </div>
    </div>
</div>

<!-- Tables -->
<section class="pegawai-section">
    <div class="container">

        <!-- PNS Table -->
        <div class="section-title">
            <h2 style="color:var(--primary);"><i class="fas fa-id-badge" style="margin-right:8px;"></i>Pegawai Negeri Sipil (PNS)</h2>
            <span class="badge-count badge-pns"><?php echo $total_pns; ?> Orang</span>
        </div>
        <div class="table-card">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="col-no">No</th>
                            <th>Nama</th>
                            <th>NIP</th>
                            <th>Jabatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pns_list)): ?>
                        <tr>
                            <td colspan="4" class="no-data">
                                <i class="fas fa-users"></i>
                                Data PNS belum tersedia.
                            </td>
                        </tr>
                        <?php
else: ?>
                        <?php foreach ($pns_list as $i => $p): ?>
                        <tr>
                            <td class="col-no"><?php echo $i + 1; ?></td>
                            <td class="td-name"><?php echo htmlspecialchars($p['nama']); ?></td>
                            <td class="col-nip"><?php echo !empty($p['nip']) ? htmlspecialchars($p['nip']) : '<span style="color:#cbd5e1;">-</span>'; ?></td>
                            <td class="td-jabatan"><?php echo !empty($p['jabatan']) ? htmlspecialchars($p['jabatan']) : '<span style="color:#cbd5e1;">-</span>'; ?></td>
                        </tr>
                        <?php
    endforeach; ?>
                        <tr class="total-row" style="background:#f0f9ff;">
                            <td colspan="3" style="color:var(--primary);">
                                <i class="fas fa-users" style="margin-right:6px;"></i> Jumlah PNS
                            </td>
                            <td style="color:var(--primary); font-size:1.1rem;"><?php echo $total_pns; ?> Orang</td>
                        </tr>
                        <?php
endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PPPK Table -->
        <div class="section-title" style="margin-top:15px;">
            <h2 style="color:#d97706;"><i class="fas fa-id-card" style="margin-right:8px;"></i>Pegawai Pemerintah dengan Perjanjian Kerja (PPPK)</h2>
            <span class="badge-count badge-pppk"><?php echo $total_pppk; ?> Orang</span>
        </div>
        <div class="table-card">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="col-no">No</th>
                            <th>Nama</th>
                            <th>NIP</th>
                            <th>Jabatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pppk_list)): ?>
                        <tr>
                            <td colspan="4" class="no-data">
                                <i class="fas fa-users"></i>
                                Data PPPK belum tersedia.
                            </td>
                        </tr>
                        <?php
else: ?>
                        <?php foreach ($pppk_list as $i => $p): ?>
                        <tr>
                            <td class="col-no"><?php echo $i + 1; ?></td>
                            <td class="td-name"><?php echo htmlspecialchars($p['nama']); ?></td>
                            <td class="col-nip"><?php echo !empty($p['nip']) ? htmlspecialchars($p['nip']) : '<span style="color:#cbd5e1;">-</span>'; ?></td>
                            <td class="td-jabatan"><?php echo !empty($p['jabatan']) ? htmlspecialchars($p['jabatan']) : '<span style="color:#cbd5e1;">-</span>'; ?></td>
                        </tr>
                        <?php
    endforeach; ?>
                        <tr class="total-row" style="background:#fffbeb;">
                            <td colspan="3" style="color:#d97706;">
                                <i class="fas fa-users" style="margin-right:6px;"></i> Jumlah PPPK
                            </td>
                            <td style="color:#d97706; font-size:1.1rem;"><?php echo $total_pppk; ?> Orang</td>
                        </tr>
                        <?php
endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Grand Total -->
        <div class="grand-total-box">
            <div>
                <div class="gt-label"><i class="fas fa-users" style="margin-right:8px;"></i>Total Seluruh Pegawai</div>
                <div class="gt-sub">PNS + PPPK</div>
            </div>
            <div style="text-align:right;">
                <div class="gt-value"><?php echo $total_all; ?></div>
                <div class="gt-sub">Orang</div>
            </div>
        </div>

    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
