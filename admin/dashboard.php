<?php
session_start();
require_once '../config.php';
require_once 'includes/security.php';

// Cek autentikasi & sinkronisasi status/role user
require_admin_login($conn);

// Handle Reset Logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_logs') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $conn->query("TRUNCATE TABLE visitor_logs");
        $_SESSION['flash_success'] = "✅ Data pengunjung berhasil dikosongkan. Sistem sekarang merekam dari awal.";
        header("Location: dashboard.php");
        exit;
    } else {
        $_SESSION['flash_error'] = "Token tidak valid.";
        header("Location: dashboard.php");
        exit;
    }
}

$page_title = 'Dashboard Utama';
require_once 'layout_header.php';

// 1. Dapatkan metrik ringkasan
$metric_today = 0;
$metric_month = 0;
$metric_year = 0;
$metric_all = 0;

$res_today = $conn->query("SELECT COUNT(id) AS total FROM visitor_logs WHERE visit_date = CURDATE()");
if ($res_today && $row = $res_today->fetch_assoc()) $metric_today = $row['total'];

$res_month = $conn->query("SELECT COUNT(id) AS total FROM visitor_logs WHERE MONTH(visit_date) = MONTH(CURDATE()) AND YEAR(visit_date) = YEAR(CURDATE())");
if ($res_month && $row = $res_month->fetch_assoc()) $metric_month = $row['total'];

$res_year = $conn->query("SELECT COUNT(id) AS total FROM visitor_logs WHERE YEAR(visit_date) = YEAR(CURDATE())");
if ($res_year && $row = $res_year->fetch_assoc()) $metric_year = $row['total'];

$res_all = $conn->query("SELECT COUNT(id) AS total FROM visitor_logs");
if ($res_all && $row = $res_all->fetch_assoc()) $metric_all = $row['total'];

// 2. Data Grafik 7 Hari Terakhir
$chart_labels = [];
$chart_data = [];

$res_chart = $conn->query("
    SELECT visit_date, COUNT(id) AS total 
    FROM visitor_logs 
    WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) 
    GROUP BY visit_date 
    ORDER BY visit_date ASC
");

// Inisialisasi label 7 hari (termasuk yang 0 kunjungan)
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d M', strtotime($date));
    $chart_data[$date] = 0; // Default 0
}

if ($res_chart) {
    while ($row = $res_chart->fetch_assoc()) {
        $chart_data[$row['visit_date']] = $row['total'];
    }
}
$chart_values = array_values($chart_data);

// 3. Top 10 Halaman Terbanyak Dikunjungi
$top_pages = [];
$res_top = $conn->query("
    SELECT page_name, page_url, COUNT(id) AS total 
    FROM visitor_logs 
    GROUP BY page_url, page_name 
    ORDER BY total DESC 
    LIMIT 10
");
if ($res_top) {
    while ($row = $res_top->fetch_assoc()) {
        $top_pages[] = $row;
    }
}
?>

<style>
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.2rem;
    margin-bottom: 1.5rem;
}
.dash-card {
    background: #fff;
    border-radius: 16px;
    padding: 20px;
    display: -webkit-flex;
    display: flex;
    -webkit-align-items: center;
    align-items: center;
    gap: 14px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.05);
    border: 1px solid rgba(0,0,0,0.03);
    min-width: 0;
}
.dash-icon {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    display: -webkit-flex;
    display: flex;
    -webkit-align-items: center;
    align-items: center;
    -webkit-justify-content: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}
.dash-info { min-width: 0; }
.dash-info h6 { margin: 0 0 4px; font-size: 12px; color: #64748b; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dash-info h3 { margin: 0; font-size: 1.4rem; font-weight: 800; color: #0f172a; line-height: 1; }

.dashboard-chart-grid {
    display: grid;
    grid-template-columns: 7fr 5fr;
    gap: 1.5rem;
}
.chart-outer {
    position: relative;
    height: 300px;
}
@media screen and (max-width: 1100px) {
    .dashboard-grid { grid-template-columns: repeat(2, 1fr); }
}
@media screen and (max-width: 992px) {
    .dashboard-chart-grid { grid-template-columns: 1fr; }
}
@media screen and (max-width: 768px) {
    .dashboard-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .dashboard-chart-grid { grid-template-columns: 1fr; gap: 14px; }
    .chart-outer { height: 220px; }
    .dash-card { padding: 14px 12px; gap: 10px; }
    .dash-icon { width: 44px; height: 44px; font-size: 18px; }
    .dash-info h3 { font-size: 1.2rem; }
}
@media screen and (max-width: 480px) {
    .dashboard-grid { grid-template-columns: 1fr; gap: 10px; }
    .chart-outer { height: 180px; }
    .dash-card { padding: 12px 10px; }
    .dash-info h3 { font-size: 1.1rem; }
}
.table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
.mb-4 { margin-bottom: 1.5rem; }
.text-muted { color: var(--text-muted, #64748b); }
.text-end { text-align: right; }
.text-center { text-align: center; }
.fw-bold { font-weight: 700; }
.badge { display: inline-block; padding: 0.35em 0.65em; font-size: 0.75em; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: 0.25rem; }
.bg-light { background-color: #f8f9fa; }
.text-dark { color: #212529; }
.py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
.px-3 { padding-left: 1rem; padding-right: 1rem; }
.py-4 { padding-top: 1.5rem; padding-bottom: 1.5rem; }
.table { width: 100%; margin-bottom: 1rem; color: #212529; border-collapse: separate; border-spacing: 0; }
.table th, .table td { padding: 0.75rem; vertical-align: top; border-top: 1px solid #dee2e6; }
.table-borderless tbody+tbody, .table-borderless td, .table-borderless th, .table-borderless thead th { border: 0; }
.align-middle { vertical-align: middle !important; }
</style>

<div class="content">
    
    <?php if (isset($_SESSION['flash_success'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Berhasil',
                text: '<?php echo addslashes($_SESSION['flash_success']); ?>',
                confirmButtonColor: 'var(--primary)',
                customClass: { popup: 'swal-custom-popup', confirmButton: 'swal-custom-btn' }
            });
        });
    </script>
    <?php unset($_SESSION['flash_success']); endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: '<?php echo addslashes($_SESSION['flash_error']); ?>',
                confirmButtonColor: 'var(--primary)',
                customClass: { popup: 'swal-custom-popup', confirmButton: 'swal-custom-btn' }
            });
        });
    </script>
    <?php unset($_SESSION['flash_error']); endif; ?>

    <div class="dashboard-header mb-4" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
        <div>
            <h2><i class="fas fa-chart-line" style="color:var(--primary);margin-right:10px;"></i> Dashboard Analitik</h2>
            <p class="text-muted" style="margin-bottom:0;">Statistik kunjungan pada website Bapenda secara real-time.</p>
        </div>
        <form method="post" style="margin:0;">
            <input type="hidden" name="action" value="reset_logs">
            <?php echo csrf_token_field(); ?>
            <button type="button" class="btn-danger" style="display:flex; align-items:center; gap:8px;" onclick="
                Swal.fire({
                    title: 'Reset Statistik?',
                    text: 'Semua rekaman data pengunjung akan dihapus secara permanen dari database. Ini disarankan dilakukan tepat sebelum website di-publish secara nyata ke publik.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#ef4444',
                    cancelButtonColor: 'var(--text-muted)',
                    confirmButtonText: 'Ya, Bersihkan Data!',
                    cancelButtonText: 'Batal',
                    customClass: { popup: 'swal-custom-popup', confirmButton: 'swal-custom-btn', cancelButton: 'swal-custom-btn' }
                }).then((result) => {
                    if (result.isConfirmed) {
                        this.closest('form').submit();
                    }
                });
            ">
                <i class="fas fa-trash-alt"></i> Bersihkan Data Log
            </button>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="dashboard-grid">
        <div class="dash-card">
            <div class="dash-icon" style="background:#e0f2fe;color:#0ea5e9;">
                <i class="fas fa-calendar-day"></i>
            </div>
            <div class="dash-info">
                <h6>Hari Ini</h6>
                <h3><?php echo number_format($metric_today, 0, ',', '.'); ?></h3>
            </div>
        </div>
        <div class="dash-card">
            <div class="dash-icon" style="background:#dcfce7;color:#16a34a;">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="dash-info">
                <h6>Bulan Ini</h6>
                <h3><?php echo number_format($metric_month, 0, ',', '.'); ?></h3>
            </div>
        </div>
        <div class="dash-card">
            <div class="dash-icon" style="background:#fef3c7;color:#d97706;">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="dash-info">
                <h6>Tahun Ini</h6>
                <h3><?php echo number_format($metric_year, 0, ',', '.'); ?></h3>
            </div>
        </div>
        <div class="dash-card">
            <div class="dash-icon" style="background:#f3e8ff;color:#9333ea;">
                <i class="fas fa-globe"></i>
            </div>
            <div class="dash-info">
                <h6>Total Pengunjung</h6>
                <h3><?php echo number_format($metric_all, 0, ',', '.'); ?></h3>
            </div>
        </div>
    </div>

    <div class="dashboard-chart-grid">
        <!-- Chart -->
        <div>
            <div class="card border-0 shadow-sm h-100" style="border-radius:18px;">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4" style="margin-top:0; color:#1e293b;">Grafik Pengunjung (7 Hari Terakhir)</h5>
                    <div class="chart-outer">
                        <canvas id="visitorChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Top Pages Table -->
        <div>
            <div class="card border-0 shadow-sm h-100" style="border-radius:18px;">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-4" style="margin-top:0; color:#1e293b;">Top 10 Halaman Dikunjungi</h5>
                    <div class="table-responsive">
                        <table class="table table-borderless align-middle">
                            <thead style="background:#f8fafc; border-radius:8px;">
                                <tr>
                                    <th style="color:#64748b;font-weight:600;font-size:13px;padding:12px; text-align:left;">Halaman / Menu</th>
                                    <th class="text-end" style="color:#64748b;font-weight:600;font-size:13px;padding:12px;">Views</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($top_pages) > 0): ?>
                                    <?php foreach ($top_pages as $page): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding:14px 12px;">
                                            <div style="font-weight:600; color:#0f172a; font-size:14px;"><?php echo htmlspecialchars($page['page_name']); ?></div>
                                            <div style="font-size:12px; color:#94a3b8;"><?php echo htmlspecialchars(substr($page['page_url'], 0, 40)); ?><?php echo strlen($page['page_url']) > 40 ? '...' : ''; ?></div>
                                        </td>
                                        <td class="text-end" style="padding:14px 12px; vertical-align: middle;">
                                            <span class="badge bg-light text-dark px-3 py-2" style="font-size:13px; border:1px solid #e2e8f0;"><?php echo number_format($page['total'], 0, ',', '.'); ?> <i class="fas fa-eye ms-1 text-muted" style="font-size:11px;"></i></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="2" class="text-center py-4 text-muted">Belum ada data kunjungan.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('visitorChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($chart_labels); ?>,
            datasets: [{
                label: 'Jumlah Kunjungan',
                data: <?php echo json_encode($chart_values); ?>,
                backgroundColor: 'rgba(14, 165, 233, 0.25)',
                borderColor: '#0ea5e9',
                borderWidth: 2,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    padding: 12,
                    titleFont: { size: 14, family: "'Poppins', sans-serif" },
                    bodyFont: { size: 13, family: "'Poppins', sans-serif" },
                    callbacks: {
                        label: function(context) {
                            return ' Pengunjung: ' + context.parsed.y;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9', drawBorder: false },
                    ticks: {
                        color: '#64748b',
                        font: { family: "'Poppins', sans-serif", size: 12 },
                        stepSize: 1,
                        precision: 0
                    }
                },
                x: {
                    grid: { display: false, drawBorder: false },
                    ticks: {
                        color: '#64748b',
                        font: { family: "'Poppins', sans-serif", size: 12 }
                    }
                }
            }
        }
    });
});
</script>

<?php require_once 'layout_footer.php'; ?>
