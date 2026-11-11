<?php
require_once 'includes/header.php';

$success_msg = '';
$ticket_no_baru = '';
$error_msg = '';

// Check and add identity_file column
$check_col = $conn->query("SHOW COLUMNS FROM pengaduan LIKE 'identity_file'");
if($check_col && $check_col->num_rows == 0){
    $conn->query("ALTER TABLE pengaduan ADD COLUMN identity_file VARCHAR(255) NULL AFTER message");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $nik = trim($_POST['nik'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    // Form Validation
    if (empty($name) || empty($nik) || empty($phone) || empty($subject) || empty($message)) {
        $error_msg = "Semua bidang (Nama, NIK, HP, Subjek, dan Pesan) wajib diisi!";
    } else {
        $identity_file = '';
        if (isset($_FILES['identity_file']) && $_FILES['identity_file']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'assets/uploads/pengaduan/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            $file_name = preg_replace('/[^a-zA-Z0-9.\-_]/', '', $_FILES['identity_file']['name']);
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $identity_file = time() . '_' . rand(100,999) . '.' . $ext;
            
            if (!move_uploaded_file($_FILES['identity_file']['tmp_name'], $upload_dir . $identity_file)) {
                $error_msg = "Gagal mengunggah file identitas.";
            }
        } else {
            $error_msg = "File identitas wajib diunggah!";
        }

        if (empty($error_msg)) {
            // Generate Ticket
            $ticket_no = 'TKT-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 4));
            
            $stmt = $conn->prepare("INSERT INTO pengaduan (ticket_no, name, nik, phone, subject, message, identity_file) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $ticket_no, $name, $nik, $phone, $subject, $message, $identity_file);
            
            if ($stmt->execute()) {
                $success_msg = "Pengaduan Anda telah berhasil dikirim!";
                $ticket_no_baru = $ticket_no;
            } else {
                $error_msg = "Terjadi kesalahan saat menyimpan pengaduan. Coba lagi.";
            }
        }
    }
}

// Fitur Tracking Tiket
$track_result = null;
$track_error = '';
if (isset($_GET['track']) && !empty(trim($_GET['track']))) {
    $track_no = trim($_GET['track']);
    $stmt = $conn->prepare("SELECT * FROM pengaduan WHERE ticket_no = ?");
    $stmt->bind_param("s", $track_no);
    $stmt->execute();
    $track_result = $stmt->get_result()->fetch_assoc();
    if (!$track_result) {
        $track_error = "Nomor tiket tidak ditemukan. Harap periksa kembali.";
    }
}
?>

<div style="background: var(--bg-light); padding: 40px 0;">
    <div class="container">
        <!-- Breadcrumb -->
        <div style="margin-bottom: 20px; font-size: 14px; color: var(--text-muted);">
            <a href="index.php" style="color: var(--primary); text-decoration: none;"><i class="fas fa-home"></i> Beranda</a> 
            <i class="fas fa-chevron-right" style="font-size: 10px; margin: 0 10px;"></i>
            <span>Layanan Pengaduan Masyarakat</span>
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 30px;">
            <!-- Info Kolom Kiri -->
            <div style="flex: 1; min-width: 300px;">
                <h1 style="color: var(--text-dark); margin-top: 0; font-size: 32px; font-weight: 800; line-height: 1.2;">Layanan Aspirasi dan Pengaduan Online</h1>
                <p style="color: var(--text-muted); font-size: 16px; margin-top: 15px; line-height: 1.6;">
                    Laporkan keluhan, saran, atau asuransi pelayanan publik langsung kepada Badan Pendapatan Daerah. Kami menjamin kerahasiaan data diri Anda.
                </p>
                
                <div style="background: #fff; padding: 25px; border-radius: 12px; margin-top: 30px; border-left: 4px solid var(--primary); box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
                    <h3 style="color: #0f172a; font-size: 16px; margin-top: 0;"><i class="fas fa-id-card" style="color:var(--primary);"></i> Verifikasi Identitas</h3>
                    <p style="font-size: 14px; color: #4a5568; margin-bottom: 0;">Sistem kami mewajibkan Anda untuk mengisi identitas (Nama, NIK, No HP) sesuai KTP beserta unggahan bukti identitas. Hal ini diperlukan untuk mencegah laporan palsu/spam dan mempermudah tindak lanjut. Data Anda dijamin kerahasiaannya.</p>
                </div>

                <!-- Lacak Tiket Widget -->
                <div style="background: #fff; padding: 25px; border-radius: 12px; margin-top: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
                    <h3 style="color: #0f172a; font-size: 18px; margin-top: 0; margin-bottom:15px;"><i class="fas fa-search" style="color:#64748b;"></i> Lacak Laporan Anda</h3>
                    <form method="GET" action="pengaduan.php" style="display:flex; gap:10px;">
                        <input type="text" name="track" placeholder="Contoh: TKT-2026..." class="form-control" style="flex:1;" required value="<?php echo isset($_GET['track']) ? htmlspecialchars($_GET['track']) : ''; ?>">
                        <button type="submit" class="btn-primary" style="border:none; border-radius:8px; padding:0 20px; cursor:pointer;"><i class="fas fa-arrow-right"></i></button>
                    </form>
                    
                    <?php if(!empty($track_error)): ?>
                        <div style="margin-top:10px; color:#ef4444; font-size:13px;"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($track_error); ?></div>
                    <?php endif; ?>

                    <?php if($track_result): ?>
                        <div style="margin-top:20px; padding-top:20px; border-top:1px solid #e2e8f0;">
                            <h4 style="margin:0 0 10px; color:var(--primary); font-size:15px;">Hasil Pencarian: #<?php echo htmlspecialchars($track_result['ticket_no']); ?></h4>
                            <p style="margin:0 0 5px; font-size:13px; color:#4a5568;"><strong>Subjek:</strong> <?php echo htmlspecialchars($track_result['subject']); ?></p>
                            <p style="margin:0 0 15px; font-size:13px; color:#4a5568;"><strong>Dilaporkan pada:</strong> <?php echo date('d M Y, H:i', strtotime($track_result['created_at'])); ?></p>
                            
                            <div style="margin-bottom:15px;">
                                <strong style="font-size:13px; display:block; margin-bottom:5px;">Status Saat Ini:</strong>
                                <?php if($track_result['status'] === 'pending'): ?>
                                    <span style="display:inline-block; padding:5px 12px; background:#fef2f2; color:#ef4444; border-radius:20px; font-size:12px; font-weight:700;"><i class="fas fa-circle" style="font-size:8px;"></i> Menunggu Respons</span>
                                <?php elseif($track_result['status'] === 'processed'): ?>
                                    <span style="display:inline-block; padding:5px 12px; background:#fef3c7; color:#d97706; border-radius:20px; font-size:12px; font-weight:700;"><i class="fas fa-spinner fa-spin"></i> Sedang Diproses</span>
                                <?php else: ?>
                                    <span style="display:inline-block; padding:5px 12px; background:#dcfce7; color:#16a34a; border-radius:20px; font-size:12px; font-weight:700;"><i class="fas fa-check"></i> Laporan Selesai</span>
                                <?php endif; ?>
                            </div>

                            <?php if(!empty($track_result['admin_response'])): ?>
                            <div style="background:#f1f5f9; border-left:4px solid #3b82f6; padding:15px; border-radius:4px; margin-top:15px;">
                                <strong style="display:block; font-size:13px; margin-bottom:8px; color:#1e293b;"><i class="fas fa-reply"></i> Tanggapan Bapenda:</strong>
                                <p style="margin:0; font-size:13px; color:#334155; line-height:1.5;"><?php echo nl2br(htmlspecialchars($track_result['admin_response'])); ?></p>
                                <?php if(isset($track_result['updated_at']) && $track_result['updated_at']): ?>
                                <small style="display:block; margin-top:8px; color:#94a3b8; font-size:11px;">Dibalas pada: <?php echo date('d M Y, H:i', strtotime($track_result['updated_at'])); ?></small>
                                <?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div style="font-size:13px; color:#64748b; font-style:italic; margin-top:10px;">Belum ada tanggapan/solusi dari pihak Bapenda.</div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Form Kolom Kanan -->
            <div style="flex: 1.5; min-width: 300px;">
                <div style="background: #fff; border-radius: 16px; padding: 35px; box-shadow: 0 20px 40px -10px rgba(0,0,0,0.05);">
                    
                    <?php if(!empty($success_msg)): ?>
                        <div style="background: #dcfce7; padding: 25px; border-radius: 12px; text-align: center; margin-bottom: 20px;">
                            <i class="fas fa-check-circle" style="font-size: 40px; color: #16a34a; margin-bottom: 15px;"></i>
                            <h3 style="margin: 0 0 10px; color: #166534;">Berhasil Terkirim!</h3>
                            <p style="margin: 0; color: #166534;">Nomor Tiket Laporan Anda:</p>
                            <div style="display:inline-block; margin-top:10px; padding:8px 20px; background:#fff; font-size:24px; font-weight:800; color:#16a34a; border-radius:8px; border:2px dashed #bbf7d0;">
                                <?php echo htmlspecialchars($ticket_no_baru); ?>
                            </div>
                            <p style="margin-top: 15px; font-size: 13px; color: #166534;">Harap simpan nomor tiket ini jika sewaktu-waktu ditanyakan oleh petugas kami.</p>
                        </div>
                    <?php else: ?>
                    
                        <?php if(!empty($error_msg)): ?>
                            <div style="background: #fee2e2; color: #ef4444; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                                <i class="fas fa-exclamation-triangle"></i> <?php echo $error_msg; ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="pengaduan.php" enctype="multipart/form-data">
                            <h3 style="margin-top:0; margin-bottom: 20px; font-size:20px; color: var(--text-dark); border-bottom: 1px solid #e2e8f0; padding-bottom: 15px;">Formulir Laporan</h3>
                            
                            <div style="display:flex; gap:15px; margin-bottom:15px;">
                                <div style="flex:1;">
                                    <label style="display:block; margin-bottom:8px; font-weight:600; font-size:13px; color:#4a5568;">Nama Lengkap Sesuai KTP <span style="color:red">*</span></label>
                                    <input type="text" name="name" class="form-control" placeholder="Nama Lengkap" required>
                                </div>
                                <div style="flex:1;">
                                    <label style="display:block; margin-bottom:8px; font-weight:600; font-size:13px; color:#4a5568;">NIK <span style="color:red">*</span></label>
                                    <input type="text" name="nik" class="form-control" placeholder="16 Digit NIK" required>
                                </div>
                            </div>

                            <div style="margin-bottom:15px;">
                                <label style="display:block; margin-bottom:8px; font-weight:600; font-size:13px; color:#4a5568;">Nomor HP / WhatsApp <span style="color:red">*</span></label>
                                <input type="number" name="phone" class="form-control" placeholder="Cth: 08123456789" required>
                            </div>

                            <div style="margin-bottom:15px;">
                                <label style="display:block; margin-bottom:8px; font-weight:600; font-size:13px; color:#4a5568;">Upload Identitas (KTP/SIM) <span style="color:red">*</span></label>
                                <input type="file" name="identity_file" class="form-control" accept="image/*,.pdf" style="padding: 9px 15px; background: #fff;" required>
                                <small style="color: #64748b; font-size: 12px; margin-top:5px; display:block;">Pilih file gambar (.jpg, .png) atau .pdf. Bukti identitas wajib diunggah.</small>
                            </div>

                            <div style="margin-bottom:15px;">
                                <label style="display:block; margin-bottom:8px; font-weight:600; font-size:13px; color:#4a5568;">Subjek / Judul Laporan <span style="color:red">*</span></label>
                                <input type="text" name="subject" class="form-control" required placeholder="Singkat, Padat, Jelas. Cth: Masalah Layanan PBB">
                            </div>

                            <div style="margin-bottom:20px;">
                                <label style="display:block; margin-bottom:8px; font-weight:600; font-size:13px; color:#4a5568;">Isi Laporan <span style="color:red">*</span></label>
                                <textarea name="message" class="form-control" rows="5" required placeholder="Jelaskan detail waktu kejadian, kronologi, siapa yang terlibat, dan apa masalah/saran Anda..."></textarea>
                            </div>

                            <button type="submit" class="btn-primary" style="width:100%; border:none; padding:15px; font-size:16px; border-radius:10px; cursor:pointer;">Kirim Laporan Pengaduan <i class="fas fa-paper-plane" style="margin-left:5px;"></i></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Override default inputs for cleaner look on public side */
.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font-family: inherit;
    font-size: 14px;
    background: #f8fafc;
    transition: 0.3s;
    box-sizing: border-box;
}
.form-control:focus {
    outline: none;
    border-color: var(--primary);
    background: #fff;
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}
</style>

<?php require_once 'includes/footer.php'; ?>
