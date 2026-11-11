<?php
require_once 'includes/header.php';
require_once __DIR__ . '/includes/upload_helper.php';

$pengaduan_upload_dir = __DIR__ . '/assets/uploads/pengaduan';
bapenda_ensure_upload_dir($pengaduan_upload_dir, true);

$success_msg = '';
$ticket_no_baru = '';
$error_msg = '';

// Check and add identity_file column
$check_col = $conn->query("SHOW COLUMNS FROM pengaduan LIKE 'identity_file'");
if($check_col && $check_col->num_rows == 0){
    $conn->query("ALTER TABLE pengaduan ADD COLUMN identity_file VARCHAR(255) NULL AFTER message");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!bapenda_verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_msg = "Sesi keamanan tidak valid. Silakan muat ulang halaman dan coba lagi.";
    } else {
        $name = trim($_POST['name'] ?? '');
        $nik = trim($_POST['nik'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $nik_digits = preg_replace('/\D/', '', $nik);
        $phone_digits = preg_replace('/\D/', '', $phone);

        // Session submit throttle (maksimal 3 laporan per 10 menit)
        $now = time();
        $post_times = $_SESSION['pengaduan_post_times'] ?? [];
        if (!is_array($post_times)) {
            $post_times = [];
        }
        $post_times = array_values(array_filter($post_times, function ($ts) use ($now) {
            return is_int($ts) && ($now - $ts) < 600;
        }));

        if (count($post_times) >= 3) {
            $error_msg = "Terlalu banyak pengiriman laporan dalam waktu singkat. Silakan coba lagi beberapa menit lagi.";
        } elseif ($name === '' || $nik === '' || $phone === '' || $subject === '' || $message === '') {
            $error_msg = "Semua bidang (Nama, NIK, HP, Subjek, dan Pesan) wajib diisi!";
        } elseif (!preg_match('/^\d{12,20}$/', $nik_digits)) {
            $error_msg = "Format NIK tidak valid (harus 12 sampai 20 digit angka).";
        } elseif (!preg_match('/^\d{8,18}$/', $phone_digits)) {
            $error_msg = "Nomor HP / WhatsApp tidak valid (harus 8 sampai 18 digit angka).";
        } elseif (!bapenda_has_uploaded_file($_FILES['identity_file'] ?? [])) {
            $error_msg = "File identitas wajib diunggah!";
        } else {
            $post_times[] = $now;
            $_SESSION['pengaduan_post_times'] = $post_times;

            $upErr = null;
            $identity_file = bapenda_handle_upload(
                $_FILES['identity_file'] ?? [],
                $pengaduan_upload_dir,
                '',
                ['jpg', 'jpeg', 'png', 'pdf'],
                5 * 1024 * 1024,
                'idcard',
                $upErr,
                [
                    'strip_exif' => true,
                    'deny_http' => true,
                    'return_filename_only' => true,
                ]
            );

            if ($identity_file === null) {
                $error_msg = $upErr ?: "Gagal mengunggah file identitas (gunakan JPG, PNG, atau PDF maksimal 5MB).";
            } else {
                // Generate cryptographically random 19-char ticket number (<= varchar(20))
                $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
                $rand_bytes = random_bytes(8);
                $code8 = '';
                for ($i = 0; $i < 8; $i++) {
                    $code8 .= $alphabet[ord($rand_bytes[$i]) & 31];
                }
                $ticket_no = 'TKT-' . date('ymd') . '-' . $code8;

                $stmt = $conn->prepare("INSERT INTO pengaduan (ticket_no, name, nik, phone, subject, message, identity_file) VALUES (?, ?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("sssssss", $ticket_no, $name, $nik_digits, $phone_digits, $subject, $message, $identity_file);
                    if ($stmt->execute()) {
                        $success_msg = "Pengaduan Anda telah berhasil dikirim!";
                        $ticket_no_baru = $ticket_no;
                    } else {
                        bapenda_safe_unlink($identity_file, $pengaduan_upload_dir);
                        $error_msg = "Terjadi kesalahan saat menyimpan pengaduan. Coba lagi.";
                    }
                    $stmt->close();
                } else {
                    bapenda_safe_unlink($identity_file, $pengaduan_upload_dir);
                    $error_msg = "Terjadi kesalahan saat menyimpan pengaduan. Coba lagi.";
                }
            }
        }
    }
}

// Fitur Tracking Tiket (memerlukan verifikasi 4 digit akhir NIK / No. HP)
$track_result = null;
$track_error = '';
if (isset($_GET['track']) && trim($_GET['track']) !== '') {
    $track_no = trim($_GET['track']);
    $verif = preg_replace('/\D/', '', (string)($_GET['verif'] ?? ''));

    $now = time();
    $track_times = $_SESSION['pengaduan_track_times'] ?? [];
    if (!is_array($track_times)) {
        $track_times = [];
    }
    $track_times = array_values(array_filter($track_times, function ($ts) use ($now) {
        return is_int($ts) && ($now - $ts) < 300;
    }));

    if (count($track_times) >= 10) {
        $track_error = "Terlalu banyak percobaan pelacakan. Silakan tunggu beberapa menit.";
    } else {
        $track_times[] = $now;
        $_SESSION['pengaduan_track_times'] = $track_times;

        if (!preg_match('/^\d{4}$/', $verif)) {
            $track_error = "Tiket tidak ditemukan atau kode verifikasi tidak cocok.";
        } else {
            $stmt = $conn->prepare("SELECT * FROM pengaduan WHERE ticket_no = ? LIMIT 1");
            if ($stmt) {
                $stmt->bind_param("s", $track_no);
                $stmt->execute();
                $candidate = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($candidate) {
                    $db_nik = preg_replace('/\D/', '', (string)($candidate['nik'] ?? ''));
                    $db_phone = preg_replace('/\D/', '', (string)($candidate['phone'] ?? ''));
                    $nik_ok = (strlen($db_nik) >= 4) && hash_equals(substr($db_nik, -4), $verif);
                    $phone_ok = (strlen($db_phone) >= 4) && hash_equals(substr($db_phone, -4), $verif);
                    if ($nik_ok || $phone_ok) {
                        unset($candidate['nik'], $candidate['phone'], $candidate['name'], $candidate['identity_file'], $candidate['message']);
                        $track_result = $candidate;
                    }
                }
            }
            if (!$track_result) {
                $track_error = "Tiket tidak ditemukan atau kode verifikasi tidak cocok.";
            }
        }
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
                    <form method="GET" action="pengaduan.php" style="display:flex; flex-wrap:wrap; gap:10px;">
                        <input type="text" name="track" placeholder="Contoh: TKT-26..." class="form-control" style="flex:2; min-width:140px;" required value="<?php echo isset($_GET['track']) ? htmlspecialchars($_GET['track'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                        <input type="text" name="verif" maxlength="4" pattern="\d{4}" inputmode="numeric" placeholder="4 digit akhir NIK / HP" class="form-control" style="flex:1; min-width:130px;" required title="Masukkan 4 digit terakhir NIK atau Nomor HP yang digunakan saat melapor" value="<?php echo isset($_GET['verif']) ? htmlspecialchars($_GET['verif'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                        <button type="submit" class="btn-primary" style="border:none; border-radius:8px; padding:0 20px; min-height:44px; cursor:pointer;"><i class="fas fa-arrow-right"></i></button>
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
                                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="pengaduan.php" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(bapenda_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
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
