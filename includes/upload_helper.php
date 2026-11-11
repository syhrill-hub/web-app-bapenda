<?php
/**
 * Centralized Hardened Upload & Safe File Cleanup Helpers
 */

if (!function_exists('bapenda_has_uploaded_file')) {
    function bapenda_has_uploaded_file($file)
    {
        return is_array($file)
            && isset($file['error'])
            && (int) $file['error'] !== UPLOAD_ERR_NO_FILE;
    }
}

if (!function_exists('bapenda_ensure_upload_dir')) {
    function bapenda_ensure_upload_dir($absDir, $denyAllDirectHttp = false)
    {
        if (!is_dir($absDir)) {
            @mkdir($absDir, 0755, true);
        }

        if (!is_dir($absDir)) {
            return false;
        }

        $htaccessPath = rtrim($absDir, "/\\") . DIRECTORY_SEPARATOR . '.htaccess';
        if ($denyAllDirectHttp) {
            $wanted = "# Protect sensitive citizen/PII uploads from direct HTTP access\n"
                . "<IfModule mod_authz_core.c>\n"
                . "    Require all denied\n"
                . "</IfModule>\n"
                . "<IfModule !mod_authz_core.c>\n"
                . "    Order deny,allow\n"
                . "    Deny from all\n"
                . "</IfModule>\n";
            if (!file_exists($htaccessPath) || strpos((string) @file_get_contents($htaccessPath), 'Require all denied') === false) {
                @file_put_contents($htaccessPath, $wanted);
            }
        } else {
            $wanted = "# Disable script execution in upload directory\n"
                . "<FilesMatch \"\\.(?i:php[0-9]?|phtml|phar|pl|py|jsp|asp|sh|cgi|htaccess|htpasswd)$\">\n"
                . "    <IfModule mod_authz_core.c>\n"
                . "        Require all denied\n"
                . "    </IfModule>\n"
                . "    <IfModule !mod_authz_core.c>\n"
                . "        Order deny,allow\n"
                . "        Deny from all\n"
                . "    </IfModule>\n"
                . "</FilesMatch>\n";
            if (!file_exists($htaccessPath)) {
                @file_put_contents($htaccessPath, $wanted);
            }
        }

        $indexHtml = rtrim($absDir, "/\\") . DIRECTORY_SEPARATOR . 'index.html';
        if (!file_exists($indexHtml)) {
            @file_put_contents($indexHtml, '');
        }

        return true;
    }
}

if (!function_exists('bapenda_reencode_raster_image')) {
    /**
     * Strip EXIF metadata and embedded polyglot payloads from JPG/PNG/WebP uploads.
     * Note: Animated GIFs preserve frames only via raw copy, so GIF uses getimagesize() check.
     */
    function bapenda_reencode_raster_image($tmpPath, $destPath, $canonicalExt)
    {
        if ($canonicalExt === 'gif' || !function_exists('imagecreatetruecolor')) {
            return false;
        }

        if ($canonicalExt === 'jpg' && function_exists('imagecreatefromjpeg')) {
            $img = @imagecreatefromjpeg($tmpPath);
            if (!$img) {
                return null;
            }
            $ok = @imagejpeg($img, $destPath, 90);
            imagedestroy($img);
            return $ok;
        }

        if ($canonicalExt === 'png' && function_exists('imagecreatefrompng')) {
            $img = @imagecreatefrompng($tmpPath);
            if (!$img) {
                return null;
            }
            @imagealphablending($img, false);
            @imagesavealpha($img, true);
            $ok = @imagepng($img, $destPath, 6);
            imagedestroy($img);
            return $ok;
        }

        if ($canonicalExt === 'webp' && function_exists('imagecreatefromwebp') && function_exists('imagewebp')) {
            $img = @imagecreatefromwebp($tmpPath);
            if (!$img) {
                return null;
            }
            @imagealphablending($img, false);
            @imagesavealpha($img, true);
            $ok = @imagewebp($img, $destPath, 90);
            imagedestroy($img);
            return $ok;
        }

        return false;
    }
}

if (!function_exists('bapenda_handle_upload')) {
    /**
     * Validate and move an uploaded file safely.
     *
     * @param array       $file        Single entry from $_FILES
     * @param string      $destDir     Absolute filesystem destination directory
     * @param string      $webPrefix   URL/relative prefix stored in DB (e.g. 'assets/uploads/news/')
     * @param array       $allowedExts Whitelisted canonical extensions (e.g. ['jpg','jpeg','png','webp'])
     * @param int         $maxBytes    Max file size in bytes
     * @param string      $namePrefix  Filename prefix
     * @param string|null $errorMsg    Populated on validation/system failure
     * @param array       $opts        Options: strip_exif (bool), deny_http (bool), return_filename_only (bool)
     * @return string|null Relative path/filename on success, null on error
     */
    function bapenda_handle_upload(
        array $file,
        string $destDir,
        string $webPrefix,
        array $allowedExts,
        int $maxBytes,
        string $namePrefix = 'file',
        ?string &$errorMsg = null,
        array $opts = []
    ) {
        $errorMsg = null;

        if (!isset($file['error']) || is_array($file['error'])) {
            $errorMsg = 'Format upload tidak valid.';
            return null;
        }

        if ((int) $file['error'] !== UPLOAD_ERR_OK) {
            if ((int) $file['error'] === UPLOAD_ERR_INI_SIZE || (int) $file['error'] === UPLOAD_ERR_FORM_SIZE) {
                $errorMsg = 'Ukuran file melebihi batas maksimal server.';
            } else {
                $errorMsg = 'Terjadi kesalahan saat mengunggah file (kode: ' . (int) $file['error'] . ').';
            }
            return null;
        }

        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errorMsg = 'Sumber file upload tidak valid.';
            return null;
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            $mb = round($maxBytes / (1024 * 1024), 1);
            $errorMsg = "Ukuran file harus lebih dari 0 dan maksimal {$mb} MB.";
            return null;
        }

        $rawExt = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        $normalizedAllowed = array_map('strtolower', $allowedExts);
        if ($rawExt === 'jpeg' && in_array('jpg', $normalizedAllowed, true) && !in_array('jpeg', $normalizedAllowed, true)) {
            $normalizedAllowed[] = 'jpeg';
        }
        if ($rawExt === '' || !in_array($rawExt, $normalizedAllowed, true)) {
            $errorMsg = 'Ekstensi file tidak diizinkan (' . htmlspecialchars($rawExt ?: 'kosong', ENT_QUOTES, 'UTF-8') . ').';
            return null;
        }

        $detectedMime = '';
        if (function_exists('finfo_open')) {
            $finfo = @finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detectedMime = strtolower((string) @finfo_file($finfo, $file['tmp_name']));
                @finfo_close($finfo);
            }
        }
        if ($detectedMime === '' && function_exists('mime_content_type')) {
            $detectedMime = strtolower((string) @mime_content_type($file['tmp_name']));
        }

        // Canonical extension => [allowed MIMEs, canonical output ext, validator type]
        $rules = [
            'jpg' => [
                'mimes' => ['image/jpeg', 'image/pjpeg'],
                'ext' => 'jpg',
                'type' => 'image',
                'imagetype' => defined('IMAGETYPE_JPEG') ? IMAGETYPE_JPEG : 2,
            ],
            'jpeg' => [
                'mimes' => ['image/jpeg', 'image/pjpeg'],
                'ext' => 'jpg',
                'type' => 'image',
                'imagetype' => defined('IMAGETYPE_JPEG') ? IMAGETYPE_JPEG : 2,
            ],
            'png' => [
                'mimes' => ['image/png'],
                'ext' => 'png',
                'type' => 'image',
                'imagetype' => defined('IMAGETYPE_PNG') ? IMAGETYPE_PNG : 3,
            ],
            'webp' => [
                'mimes' => ['image/webp'],
                'ext' => 'webp',
                'type' => 'image',
                'imagetype' => defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : 18,
            ],
            'gif' => [
                'mimes' => ['image/gif'],
                'ext' => 'gif',
                'type' => 'image',
                'imagetype' => defined('IMAGETYPE_GIF') ? IMAGETYPE_GIF : 1,
            ],
            'ico' => [
                'mimes' => ['image/x-icon', 'image/vnd.microsoft.icon', 'image/ico'],
                'ext' => 'ico',
                'type' => 'ico',
            ],
            'pdf' => [
                'mimes' => ['application/pdf'],
                'ext' => 'pdf',
                'type' => 'pdf',
            ],
            'doc' => [
                'mimes' => ['application/msword', 'application/cdfv2', 'application/x-ole-storage', 'application/octet-stream'],
                'ext' => 'doc',
                'type' => 'ole',
            ],
            'xls' => [
                'mimes' => ['application/vnd.ms-excel', 'application/cdfv2', 'application/x-ole-storage', 'application/octet-stream'],
                'ext' => 'xls',
                'type' => 'ole',
            ],
            'ppt' => [
                'mimes' => ['application/vnd.ms-powerpoint', 'application/cdfv2', 'application/x-ole-storage', 'application/octet-stream'],
                'ext' => 'ppt',
                'type' => 'ole',
            ],
            'docx' => [
                'mimes' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
                'ext' => 'docx',
                'type' => 'zip_office',
            ],
            'xlsx' => [
                'mimes' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
                'ext' => 'xlsx',
                'type' => 'zip_office',
            ],
            'pptx' => [
                'mimes' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
                'ext' => 'pptx',
                'type' => 'zip_office',
            ],
        ];

        if (!isset($rules[$rawExt])) {
            $errorMsg = 'Tipe file tidak didukung.';
            return null;
        }

        $rule = $rules[$rawExt];
        if ($detectedMime === '' || !in_array($detectedMime, $rule['mimes'], true)) {
            $errorMsg = 'Isi file tidak sesuai dengan format ekstensi yang diizinkan.';
            return null;
        }

        // Magic bytes / structure verification
        $head = (string) @file_get_contents($file['tmp_name'], false, null, 0, 16);

        if ($rule['type'] === 'image') {
            $imgInfo = @getimagesize($file['tmp_name']);
            if ($imgInfo === false || empty($imgInfo[0]) || empty($imgInfo[1])) {
                $errorMsg = 'File bukan gambar raster yang valid.';
                return null;
            }
            if ((int) $imgInfo[2] !== (int) $rule['imagetype']) {
                $errorMsg = 'Header gambar tidak sesuai dengan ekstensi file.';
                return null;
            }
        } elseif ($rule['type'] === 'ico') {
            // ICO magic: 00 00 01 00, CUR: 00 00 02 00
            if (strlen($head) < 4 || (substr($head, 0, 4) !== "\x00\x00\x01\x00" && substr($head, 0, 4) !== "\x00\x00\x02\x00")) {
                $errorMsg = 'File ICO tidak valid.';
                return null;
            }
        } elseif ($rule['type'] === 'pdf') {
            if (strpos($head, '%PDF-') !== 0) {
                $errorMsg = 'Header dokumen PDF tidak valid.';
                return null;
            }
        } elseif ($rule['type'] === 'ole') {
            if (substr($head, 0, 8) !== "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
                $errorMsg = 'Header dokumen Office (.doc/.xls/.ppt) tidak valid.';
                return null;
            }
        } elseif ($rule['type'] === 'zip_office') {
            if (substr($head, 0, 4) !== "PK\x03\x04") {
                $errorMsg = 'Header dokumen Office OpenXML tidak valid.';
                return null;
            }
        }

        $denyHttp = !empty($opts['deny_http']);
        if (!bapenda_ensure_upload_dir($destDir, $denyHttp)) {
            $errorMsg = 'Direktori penyimpanan upload tidak dapat dibuat.';
            return null;
        }

        $safePrefix = preg_replace('/[^a-zA-Z0-9_-]/', '', $namePrefix);
        if ($safePrefix === '') {
            $safePrefix = 'file';
        }
        $randomSuffix = bin2hex(random_bytes(6));
        $canonicalExt = $rule['ext'];
        $filename = $safePrefix . '_' . time() . '_' . $randomSuffix . '.' . $canonicalExt;
        $targetPath = rtrim($destDir, "/\\") . DIRECTORY_SEPARATOR . $filename;

        $saved = false;
        if (!empty($opts['strip_exif']) && $rule['type'] === 'image' && in_array($canonicalExt, ['jpg', 'png', 'webp'], true)) {
            $reencoded = bapenda_reencode_raster_image($file['tmp_name'], $targetPath, $canonicalExt);
            if ($reencoded === null) {
                $errorMsg = 'Data gambar rusak atau tidak dapat diproses.';
                return null;
            }
            $saved = ($reencoded === true);
        }

        if (!$saved) {
            if (!@move_uploaded_file($file['tmp_name'], $targetPath)) {
                $errorMsg = 'Gagal menyimpan file yang diunggah.';
                return null;
            }
        }

        @chmod($targetPath, 0644);

        if (!empty($opts['return_filename_only'])) {
            return $filename;
        }

        $normPrefix = rtrim($webPrefix, '/');
        return $normPrefix !== '' ? $normPrefix . '/' . $filename : $filename;
    }
}

if (!function_exists('bapenda_safe_unlink')) {
    /**
     * Safely delete an uploaded file only if it resolves strictly inside $allowedAbsDir.
     */
    function bapenda_safe_unlink($storedPath, $allowedAbsDir, $projectRootAbs = null)
    {
        if (!is_string($storedPath) || trim($storedPath) === '') {
            return false;
        }

        $baseAllowed = realpath($allowedAbsDir);
        if ($baseAllowed === false || !is_dir($baseAllowed)) {
            return false;
        }

        $clean = str_replace('\\', '/', trim($storedPath));
        $clean = ltrim($clean, '/');
        if (strpos($clean, '../') === 0 || strpos($clean, '..') !== false) {
            return false;
        }

        $candidate = null;
        if ($projectRootAbs !== null) {
            $root = realpath($projectRootAbs);
            if ($root !== false) {
                $try = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $clean);
                $candidate = realpath($try);
            }
        }

        if ($candidate === false || $candidate === null) {
            // Fallback for filename-only columns (e.g. pengaduan.identity_file)
            $tryName = $baseAllowed . DIRECTORY_SEPARATOR . basename($clean);
            $candidate = realpath($tryName);
        }

        if ($candidate === false || !is_file($candidate)) {
            return false;
        }

        $prefix = rtrim($baseAllowed, "/\\") . DIRECTORY_SEPARATOR;
        if (strpos($candidate, $prefix) !== 0) {
            return false;
        }

        return @unlink($candidate);
    }
}
?>

