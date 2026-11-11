    </div> <!-- .main-content -->
    
    <!-- SweetAlert2 for Modern Custom Dialogs -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .swal-custom-popup {
            border-radius: var(--radius-lg) !important;
            padding: 1.5em 2em !important;
            font-family: inherit !important;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1) !important;
            border: 1px solid rgba(0,0,0,0.05) !important;
        }
        .swal-custom-btn {
            border-radius: var(--radius-pill) !important;
            padding: 12px 28px !important;
            font-weight: 600 !important;
            font-size: 14px !important;
            letter-spacing: 0.5px !important;
            transition: all 0.3s ease !important;
        }
        .swal-custom-btn:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15) !important;
        }
        .swal2-title {
            color: var(--primary) !important;
            font-size: 22px !important;
            margin-bottom: 15px !important;
        }
        .swal2-html-container {
            color: var(--text-muted) !important;
            font-size: 15px !important;
            line-height: 1.6 !important;
        }
        .swal2-icon {
            border-color: var(--accent) !important;
            color: var(--accent) !important;
        }
        .swal2-icon.swal2-warning { border-color: #f59e0b !important; color: #f59e0b !important; }
        .swal2-icon.swal2-error { border-color: #ef4444 !important; color: #ef4444 !important; }
        .swal2-icon.swal2-success { border-color: #10b981 !important; color: #10b981 !important; }
        .swal2-icon.swal2-question { border-color: #3b82f6 !important; color: #3b82f6 !important; }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Find all elements using native return confirm('...')
        const confirmElements = document.querySelectorAll('[onclick*="return confirm"]');
        
        confirmElements.forEach(function(el) {
            const onclickAttr = el.getAttribute('onclick');
            const match = onclickAttr.match(/confirm\(['"](.*?)['"]\)/);
            
            if (match && match[1]) {
                const message = match[1];
                
                // Remove the native onclick handler
                el.removeAttribute('onclick');
                
                // Attach our SweetAlert2 listener
                el.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    Swal.fire({
                        title: 'Konfirmasi',
                        text: message,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: 'var(--primary)',
                        cancelButtonColor: '#ef4444',
                        confirmButtonText: 'Ya, Lanjutkan',
                        cancelButtonText: 'Batal',
                        reverseButtons: true, // Cancel on the left, Confirm on the right
                        customClass: {
                            popup: 'swal-custom-popup',
                            confirmButton: 'swal-custom-btn',
                            cancelButton: 'swal-custom-btn'
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Proceed based on element type
                            if (el.tagName === 'A') {
                                window.location.href = el.href;
                            } else if (el.tagName === 'BUTTON' || el.tagName === 'INPUT') {
                                const parentForm = el.closest('form');
                                if (parentForm) {
                                    // If button has name/value (like remove_image_action), add hidden input so it gets submitted
                                    if (el.name) {
                                        const hiddenInput = document.createElement('input');
                                        hiddenInput.type = 'hidden';
                                        hiddenInput.name = el.name;
                                        hiddenInput.value = el.value || '1';
                                        parentForm.appendChild(hiddenInput);
                                    }
                                    parentForm.submit();
                                }
                            }
                        }
                    });
                });
            }
        });
    });
    </script>
</body>
</html>
