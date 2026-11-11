    <!-- Footer -->
    <footer class="footer" id="kontak">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-about">
                    <div class="footer-logo">
                        <img src="<?php echo htmlspecialchars(get_setting('footer_logo_image', get_setting('logo_image', 'assets/img/logo.png', $settings), $settings)); ?>" alt="Bapenda">
                        <span>Badan Pendapatan Daerah<br>Kotawaringin Barat</span>
                    </div>
                    <p><?php echo nl2br(htmlspecialchars($footer_desc)); ?></p>
                    <div class="footer-social">
                        <?php 
                        for($i=1; $i<=4; $i++): 
                            $s_img  = $social_icon_imgs[$i] ?? '';
                            $s_link = get_setting("social_link_{$i}", '', $settings);
                            if(!empty($s_img) && !empty($s_link) && $s_link !== '#'):
                        ?>
                        <a href="<?php echo htmlspecialchars($s_link); ?>" target="_blank">
                            <img src="<?php echo htmlspecialchars($s_img); ?>" alt="Sosial Media <?php echo $i; ?>" style="width:20px;height:20px;object-fit:contain;transition:all 0.3s;filter:grayscale(100%) opacity(0.6);" onmouseover="this.style.filter='grayscale(0%) opacity(1)'" onmouseout="this.style.filter='grayscale(100%) opacity(0.6)'">
                        </a>
                        <?php 
                            endif;
                        endfor; 
                        ?>
                    </div>
                </div>
                <div class="footer-col">
                    <h4>Program Kami</h4>
                    <ul class="footer-links">
                        <?php foreach($services as $s): ?>
                        <li><a href="<?php echo htmlspecialchars($s['url'] ?? '#'); ?>" target="_blank"><?php echo htmlspecialchars($s['title']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Link Terkait</h4>
                    <ul class="footer-links">
                        <?php foreach($related_links as $l): ?>
                        <li><a href="<?php echo htmlspecialchars($l['url']); ?>" target="_blank"><?php echo htmlspecialchars($l['title']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4>Hubungi Kami</h4>
                    <ul class="footer-contact">
                        <li>
                            <i class="fas fa-map-marker-alt"></i>
                            <span><?php echo htmlspecialchars($footer_address); ?></span>
                        </li>
                        <li>
                            <i class="fas fa-phone"></i>
                            <a href="tel:<?php echo htmlspecialchars(str_replace('-', '', $footer_phone)); ?>" style="opacity: 1;"><?php echo htmlspecialchars($footer_phone); ?></a>
                        </li>
                        <li>
                            <i class="fas fa-envelope"></i>
                            <a href="mailto:<?php echo htmlspecialchars($footer_email); ?>" style="opacity: 1;"><?php echo htmlspecialchars($footer_email); ?></a>
                        </li>
                        <li>
                            <i class="fas fa-clock"></i>
                            <span><?php echo htmlspecialchars($footer_jam); ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container">
                <p>&copy; <?php echo date('Y'); ?> <a href="#"><?php echo htmlspecialchars($footer_copyright); ?></a>. Hak Cipta Dilindungi.</p>
            </div>
        </div>
    </footer>

    <!-- FAB Chat — Hubungi Kami -->
    <?php
    $wa_num      = get_setting('wa_number', '', $settings);
    $wa_greeting = get_setting('wa_greeting', 'Halo Bapenda Kobar, saya ingin bertanya mengenai...', $settings);
    if (!empty($wa_num)) {
        // Format: strip non-numeric, ensure starts without 0
        $wa_clean = preg_replace('/[^0-9]/', '', $wa_num);
        $wa_url   = 'https://wa.me/62' . ltrim($wa_clean, '0') . '?text=' . rawurlencode($wa_greeting);
        $fab_href = $wa_url;
        $fab_icon = '<i class="fab fa-whatsapp"></i>';
        $fab_target = ' target="_blank" rel="noopener noreferrer"';
    } else {
        $fab_href   = '#kontak';
        $fab_icon   = '<i class="fas fa-comment-dots"></i>';
        $fab_target = '';
    }
    ?>
    <a href="<?php echo $fab_href; ?>" class="fab-chat"<?php echo $fab_target; ?> aria-label="Hubungi Kami via WhatsApp" style="background-color: var(--theme-color, #25d366);">
        <?php echo $fab_icon; ?> Hubungi Kami
    </a>


    <!-- Back to Top -->
    <a href="#" class="back-to-top" id="backToTop">
        <i class="fas fa-chevron-up"></i>
    </a>

    <!-- Modal Pesan Teks (Popup Notifikasi Teks) — tampil PERTAMA -->
    <?php if (get_setting('popup_text_enabled', '0', $settings) === '1'): ?>
    <?php
        $popup_text_title   = get_setting('popup_text_title', 'Informasi Penting', $settings);
        $popup_text_content = get_setting('popup_text_content', '', $settings);
        $popup_text_icon    = get_setting('popup_text_icon', 'fas fa-bell', $settings);
        $popup_text_color   = get_setting('popup_text_color', '#0ea5e9', $settings);
        $popup_text_btn     = get_setting('popup_text_btn', 'Saya Mengerti', $settings);
    ?>
    <div id="textAnnouncementModal" class="modal-overlay" style="z-index:100001;">
        <div class="modal-box" style="max-width:480px; text-align:center; padding:0; overflow:hidden; border-radius:20px;">
            <!-- Header berwarna -->
            <div style="background:<?php echo htmlspecialchars($popup_text_color); ?>; padding:30px 30px 20px; position:relative;">
                <button class="modal-close" id="closeTextModal" style="position:absolute; top:12px; right:12px; background:rgba(255,255,255,0.2); border:none; color:#fff; width:32px; height:32px; border-radius:50%; cursor:pointer; font-size:14px; display:flex; align-items:center; justify-content:center;">
                    <i class="fas fa-times"></i>
                </button>
                <div style="width:64px; height:64px; background:rgba(255,255,255,0.2); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 15px;">
                    <i class="<?php echo htmlspecialchars($popup_text_icon); ?>" style="font-size:28px; color:#fff;"></i>
                </div>
                <h3 style="color:#fff; font-size:20px; font-weight:700; margin:0;"><?php echo htmlspecialchars($popup_text_title); ?></h3>
            </div>
            <!-- Body pesan -->
            <div style="padding:25px 30px 30px; background:#fff;">
                <p style="color:#475569; font-size:15px; line-height:1.7; margin:0 0 25px;"><?php echo nl2br(htmlspecialchars($popup_text_content)); ?></p>
                <button id="understandTextModal" style="width:100%; padding:13px 20px; background:<?php echo htmlspecialchars($popup_text_color); ?>; color:#fff; border:none; border-radius:50px; font-size:15px; font-weight:600; cursor:pointer; letter-spacing:0.5px; transition:opacity 0.2s;" onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='1'">
                    <?php echo htmlspecialchars($popup_text_btn); ?>
                </button>
            </div>
        </div>
    </div>
    <style>
    #textAnnouncementModal .modal-box {
        animation: slideUpModal 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) both;
    }
    @keyframes slideUpModal {
        from { opacity:0; transform:translateY(40px) scale(0.95); }
        to   { opacity:1; transform:translateY(0) scale(1); }
    }
    </style>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Popup teks muncul PERTAMA — 500ms
        if (!sessionStorage.getItem('popupTextShown_bapenda')) {
            setTimeout(function() {
                var m = document.getElementById('textAnnouncementModal');
                if (m) m.classList.add('show');
            }, 500);
        }

        function closeTextPopup() {
            var m = document.getElementById('textAnnouncementModal');
            if (m) {
                m.classList.remove('show');
                sessionStorage.setItem('popupTextShown_bapenda', 'true');
            }
        }

        var btnC = document.getElementById('closeTextModal');
        var btnU = document.getElementById('understandTextModal');
        if (btnC) btnC.addEventListener('click', closeTextPopup);
        if (btnU) btnU.addEventListener('click', closeTextPopup);

        // Klik overlay luar untuk tutup
        var overlay = document.getElementById('textAnnouncementModal');
        if (overlay) {
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) closeTextPopup();
            });
        }
    });
    </script>
    <?php endif; ?>

    <!-- Modal Pengumuman (Popup Gambar) — tampil KEDUA -->
    <?php if (get_setting('popup_enabled', '0', $settings) === '1'): ?>
    <div id="announcementModal" class="modal-overlay">
        <div class="modal-box">
            <button class="modal-close" id="closeModal"><i class="fas fa-times"></i></button>
            <?php $popup_img = get_setting('popup_image', '', $settings); ?>
            <?php if (!empty($popup_img)): ?>
            <div class="modal-img">
                <img src="<?php echo htmlspecialchars($popup_img); ?>" alt="Pengumuman Bapenda">
            </div>
            <?php endif; ?>
            <div class="modal-content-body">
                <h3><?php echo htmlspecialchars(get_setting('popup_title', 'Pengumuman', $settings)); ?></h3>
                <p><?php echo nl2br(htmlspecialchars(get_setting('popup_content', '', $settings))); ?></p>
                <button class="btn-primary" id="understandModal" style="margin-top:25px; width:100%; border-radius:50px; text-align:center; display:block;">SAYA MENGERTI</button>
            </div>
        </div>
    </div>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Popup gambar muncul KEDUA — 1200ms (setelah popup teks)
        var imgDelay = <?php echo (get_setting('popup_text_enabled', '0', $settings) === '1') ? 1200 : 500; ?>;
        if (!sessionStorage.getItem('popupShown_bapenda')) {
            setTimeout(function() {
                var modal = document.getElementById('announcementModal');
                if(modal) modal.classList.add('show');
            }, imgDelay);
        }

        function closePopup() {
            var modal = document.getElementById('announcementModal');
            if(modal) {
                modal.classList.remove('show');
                sessionStorage.setItem('popupShown_bapenda', 'true');
            }
        }

        var btnClose = document.getElementById('closeModal');
        var btnUnderstand = document.getElementById('understandModal');

        if (btnClose) btnClose.addEventListener('click', closePopup);
        if (btnUnderstand) btnUnderstand.addEventListener('click', closePopup);
    });
    </script>
    <?php endif; ?>

    <script src="assets/js/main.js"></script>
</body>
</html>
