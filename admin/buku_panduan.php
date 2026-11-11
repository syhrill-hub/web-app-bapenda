<?php
require_once __DIR__ . '/../includes/security_helpers.php';
bapenda_start_secure_session();
require_once '../config.php';
require_once 'includes/security.php';

require_admin_login($conn);


$page_title = 'Buku Panduan PDF';
require_once 'layout_header.php';
?>

<style>
/* Styling default untuk tampilan di layar (Layar Monitor) */
.panduan-container {
    background: #fff;
    padding: 40px;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-soft);
    margin-bottom: 30px;
    font-size: 15px;
    line-height: 1.7;
    color: #334155;
}

.panduan-header {
    text-align: center;
    margin-bottom: 40px;
    padding-bottom: 20px;
    border-bottom: 2px solid #e2e8f0;
}
.panduan-header h1 {
    margin: 0;
    color: var(--primary);
    font-size: 32px;
}
.panduan-header p {
    color: #64748b;
    font-size: 16px;
    margin-top: 10px;
}

.panduan-section {
    margin-bottom: 40px;
}
.panduan-section h2 {
    color: #1e293b;
    font-size: 24px;
    border-left: 4px solid var(--primary);
    padding-left: 15px;
    margin-bottom: 20px;
}
.panduan-section h3 {
    color: #0f172a;
    font-size: 18px;
    margin-top: 25px;
    margin-bottom: 10px;
}
.panduan-section ul {
    list-style-type: none;
    padding-left: 0;
}
.panduan-section ul li {
    position: relative;
    padding-left: 25px;
    margin-bottom: 12px;
}
.panduan-section ul li::before {
    content: '\f054';
    font-family: 'Font Awesome 5 Free';
    font-weight: 900;
    position: absolute;
    left: 0;
    top: 2px;
    color: var(--primary);
    font-size: 12px;
}
.panduan-cta {
    background: #f0f9ff;
    border-left: 4px solid #0ea5e9;
    padding: 20px;
    border-radius: 8px;
    margin-top: 30px;
}

/* ─────────────────────────────────────────────────────────
   STYLING KHUSUS UNTUK CETAK (PRINT TO PDF)
   ───────────────────────────────────────────────────────── */
@media print {
    /* Sembunyikan elemen bawaan Admin (Sidebar, Topbar, tombol) */
    .sidebar, .topbar, .btn-print-pdf, .admin-hamburger, .admin-overlay {
        display: none !important;
    }
    
    /* Paskan konten ke ukuran kertas */
    body {
        background: #fff !important;
        margin: 0;
        padding: 0;
    }
    .main-content {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    .panduan-container {
        box-shadow: none !important;
        border: none !important;
        padding: 0 !important;
        margin: 0 !important;
        border-radius: 0 !important;
    }
    
    /* Pengaturan Kertas (A4) */
    @page {
        size: A4 portrait;
        margin: 15mm;
    }
    
    /* Control Pemutusan Halaman (Page Break) */
    h2 {
        page-break-after: avoid;
    }
    .panduan-section {
        page-break-inside: avoid;
    }
    .page-break {
        page-break-before: always;
    }
}
</style>

<div class="content">
    
    <div style="display:flex; justify-content:flex-end; margin-bottom: 20px;">
        <button onclick="window.print()" class="btn btn-print-pdf" style="display:inline-flex; align-items:center; gap:8px;">
            <i class="fas fa-print"></i> Cetak ke PDF
        </button>
    </div>

    <!-- Area Konten Panduan Cetak -->
    <div class="panduan-container">
        <div class="panduan-header">
            <h1>Buku Panduan Penggunaan Aplikasi</h1>
            <p>Sistem Informasi dan Website Profil Bapenda Kotawaringin Barat</p>
        </div>

        <div class="panduan-cta" style="margin-bottom: 40px; margin-top:0;">
            <strong>Pendahuluan:</strong> Modul ini menjelaskan tata cara pengoperasian Sistem dan Website 
            Badan Pendapatan Daerah (Bapenda) bagi Administrator. Dengan panel admin yang dinamis ini, Anda bisa 
            mengelola seluruh tampilan antarmuka (UI) website, serta data internal instansi tanpa perlu pengetahuan koding.
        </div>

        <!-- BAGIAN 1: PENGATURAN UMUM -->
        <div class="panduan-section">
            <h2>I. Pengaturan Umum Website</h2>
            <p>Bagian ini mengatur semua detail mendasar (identitas) dari website yang tampil pada publik.</p>
            <ul>
                <li><strong>Pengaturan Umum (Dashboard -> Pengaturan Umum):</strong> Di sini Anda dapat mengubah Judul Aplikasi, Teks Tentang Bapenda, dan Visi Misi. Selain itu Anda juga dapat mengganti Logo Footer, alamat, email, serta seluruh tautan media sosial.</li>
                <li><strong>Kustomisasi Tema:</strong> Memungkinkan Anda mengubah warna utama (Primary Color) yang mendominasi tombol dan aksen website, serta mengubah font dasar website dan Admin. Sangat praktis jika instansi melakukan rebranding visual.</li>
                <li><strong>Header & Navigasi:</strong> Modul ini secara khusus dibuat untuk mengatur gambar "Hero/Slider" berganti-ganti di halaman depan (Home) dan mengelola menu navigasi dinamis yang tampil di atas website.</li>
                <li><strong>Popup Info:</strong> Berguna untuk memberikan pengumuman mendesak atau ucapan selamat hari raya. Mengunggah gambar ke popup akan otomatis menampilkannya kepada pengunjung (sekali tampil per hari bagi setiap pengguna).</li>
            </ul>
        </div>

        <!-- BAGIAN 2: MENGELOLA PROFIL INSTANSI -->
        <div class="panduan-section">
            <h2>II. Mengelola Profil Instansi</h2>
            <p>Sistem telah menyediakan menu bawaan untuk memberikan informasi transparansi mengenai profil internal Bapenda.</p>
            <ul>
                <li><strong>Foto Bupati & Wakil:</strong> Mengganti gambar profil Bupati dan Wakil Bupati terbaru beserta pesan/kutipan (Quote) mereka yang ditampilkan di halaman beranda.</li>
                <li><strong>Struktur Organisasi:</strong> Mengubah dan menyesuaikan gambar bagan organisasi instansi pada halaman "Struktur Organisasi".</li>
                <li><strong>Data Pegawai:</strong> Mengelola daftar lengkap seluruh Aparatur Sipil Negara (ASN) dan honorer Bapenda. Anda bisa menyortir dan menambahkan foto, Nama, NIP, Jabatan, Golongan bagi setiap profil yang nanti bisa dicari informasinya oleh publik.</li>
                <li><strong>Halaman Statis:</strong> Memudahkan pembuatan halaman tambahan secara suka-suka. (Misal: halaman "Visi Misi", "Sejarah", atau "Landasan Hukum") dengan editor teks berformat MS Word.</li>
            </ul>
        </div>

        <div class="page-break"></div>

        <!-- BAGIAN 3: LAYANAN, MEDIA & BERITA -->
        <div class="panduan-section">
            <h2>III. Layanan, Berita & Media Distribusi Pihak Ketiga</h2>
            <p>Website berfungsi sebagai pintu gerbang informasi. Beberapa fitur yang telah dibuat:</p>
            <ul>
                <li><strong>Manajemen Berita:</strong> Membuat artikel atau berita (Press Release). Berita yang diunggah akan tampil secara elegan di beranda utama (maksimal 3 berita terbaru) sekaligus menjadi pustaka lengkap di laman khusus Berita.</li>
                <li><strong>Manajemen Dokumen:</strong> Tempat mengunggah regulasi, peraturan daerah (PERDA), blangko pajak, dokumen SAKIP, dll dalam format PDF/Word agar warga dan wajib pajak dapat mengunduhnya dengan aman.</li>
                <li><strong>Manajemen Galeri:</strong> Membangun album foto online untuk mengabadikan momen/kegiatan Bapenda secara grid dinamis.</li>
                <li><strong>Layanan Utama:</strong> Modul untuk melakukan sinkronisasi dengan aplikasi mandiri (seperti Aplikasi SIPAD). Mengunggah gambar ikon aplikasi dan URL aplikasi pendaftaran yang Anda miliki.</li>
                <li><strong>Link Terkait:</strong> Kumpulan banner situs rekanan dan lembaga pemerintahan (seperti website Pemkab, website Dirjen Pajak, Bank daerah, dsb).</li>
                <li><strong>Pusat Bantuan (WhatsApp):</strong> Pengaturan nama operator, nomor kontak WA, serta jam kerja dukungan untuk live chat di halaman website yang akan langsung otomatis terhubung ke aplikasi WhatsApp penanya ke operator Anda.</li>
            </ul>
        </div>

        <!-- BAGIAN 4: STATISTIK DAN PAJAK DAERAH -->
        <div class="panduan-section">
            <h2>IV. Statistik Kinerja dan Pajak</h2>
            <p>Fitur untuk merekapitulasi data administratif pengunjung serta pelaporan pencapaian pajak.</p>
            <ul>
                <li><strong>Dashboard Analitik (Log Pengunjung):</strong> Menampilkan grafik jumlah orang (Visitors) yang masuk ke website hari ini, bulan ini, dan tahun ini. Anda juga bisa melihat "Top 10 Halaman" terfavorit. Jika diperlukan, log ini bisa dibersihkan sebelum perilisan dengan tombol "Bersihkan Data Log".</li>
                <li><strong>Target Pajak:</strong> Administrator dapat menetapkan jenis pajak (seperti: Pajak Hotel, Restoran, MBLB) dan menargetkan besaran anggaran tahunan secara real dengan penginputan jutaan atau miliaran rupiah.</li>
                <li><strong>Realisasi Pajak:</strong> Setiap bulan, Anda dapat merekam pemasukan yang telah diverifikasi untuk setiap rekening pajak. Website di halaman depan akan secara otomatis memproses grafik lingkaran (persentase) target berbanding realisasi sebagai wujud transparansi keuangan berbasis Dashboard Pelayanan Publik!</li>
            </ul>
        </div>

        <!-- BAGIAN 5: AKUN DAN KEAMANAN -->
        <div class="panduan-section">
            <h2>V. Akun dan Keamanan</h2>
            <ul>
                <li><strong>Profil Admin:</strong> Administrator bisa mengganti Username, Nama Lengkap, Email dan Password di modul "Profil Admin". Ini untuk memproteksi masuknya pihak tidak bertanggung jawab.</li>
                <li><strong>Fitur Pemblokiran Intrusi:</strong> Aplikasi dilengkapi Brute Force Protection. Jika administrator salah login hingga 5 kali beruntun, maka login akan dikunci 15 menit.</li>
                <li><strong>Pengamanan Serangan Jaringan:</strong> Sistem dilengkapi oleh pelindung serangan CSRF, XSS (Cross Site Scripting), dan SQL Injection PDO Parameterized yang menjamin sistem akan sulit dirusak dari luar.</li>
            </ul>
        </div>

        <div class="panduan-cta" style="margin-top: 50px; text-align:center; background:#f8fafc; border:none; border-top:1px dashed #cbd5e1; border-radius:0;">
            <strong style="color:var(--text-dark);">Panduan ini disajikan secara digital dalam bentuk modul terpadu.</strong><br>
            <span style="font-size:13px; color:#94a3b8;">Sistem Informasi Website Profil & Pajak Dashboard V.1.0</span>
        </div>

    </div>
</div>

<?php require_once 'layout_footer.php'; ?>
