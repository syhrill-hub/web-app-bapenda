<?php
/**
 * Shared Security Helpers (XSS sanitization, URL validation, CSS guards, Session hardening)
 */

if (!function_exists('bapenda_is_https')) {
    function bapenda_is_https()
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
            return true;
        }
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $proto = strtolower(trim(explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO'])[0]));
            if ($proto === 'https') {
                return true;
            }
        }
        if (!empty($_SERVER['HTTP_CF_VISITOR']) && strpos((string) $_SERVER['HTTP_CF_VISITOR'], '"https"') !== false) {
            return true;
        }
        return false;
    }
}

if (!function_exists('bapenda_send_security_headers')) {
    function bapenda_send_security_headers()
    {
        static $sent = false;
        if ($sent || PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }
        $sent = true;

        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }
}

bapenda_send_security_headers();

if (!function_exists('bapenda_start_secure_session')) {
    function bapenda_start_secure_session()
    {
        bapenda_send_security_headers();

        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        if (!headers_sent()) {
            @ini_set('session.use_strict_mode', '1');
            @ini_set('session.use_only_cookies', '1');
            @ini_set('session.cookie_httponly', '1');

            $is_https = bapenda_is_https();
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'secure' => $is_https,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        session_start();
    }
}

if (!function_exists('bapenda_csrf_token')) {
    function bapenda_csrf_token()
    {
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('bapenda_verify_csrf_token')) {
    function bapenda_verify_csrf_token($token)
    {
        return is_string($token)
            && isset($_SESSION['csrf_token'])
            && is_string($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('bapenda_safe_url')) {
    function bapenda_safe_url($url, $default = '#')
    {
        if (!is_string($url)) {
            return $default;
        }

        $url = str_replace(["\r", "\n", "\t", "\0"], '', trim($url));
        if ($url === '') {
            return $default;
        }

        // Reject protocol-relative external redirects (//evil.example)
        if (strpos($url, '//') === 0) {
            return $default;
        }

        $decoded = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $compact = preg_replace('/[\x00-\x20\x7F]+/', '', $decoded);

        if (preg_match('/^(?:javascript|vbscript|data|file):/i', (string) $compact)) {
            return $default;
        }

        $scheme = parse_url($decoded, PHP_URL_SCHEME);
        if ($scheme !== null && $scheme !== false) {
            if (!in_array(strtolower((string) $scheme), ['http', 'https', 'mailto', 'tel'], true)) {
                return $default;
            }
        }

        return $url;
    }
}

if (!function_exists('bapenda_safe_css_color')) {
    function bapenda_safe_css_color($color, $default = '#000000')
    {
        if (!is_string($color)) {
            return $default;
        }
        $color = trim($color);
        if (preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?([0-9a-fA-F]{2})?$/', $color)) {
            return $color;
        }
        if (preg_match('/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}(\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/i', $color)) {
            return $color;
        }
        if (preg_match('/^[a-zA-Z]{3,20}$/', $color)) {
            return strtolower($color);
        }
        return $default;
    }
}

if (!function_exists('bapenda_safe_css_font')) {
    function bapenda_safe_css_font($font, array $allowedFonts = [], $default = 'Plus Jakarta Sans')
    {
        if (!is_string($font)) {
            return $default;
        }
        $font = trim($font);
        if (!empty($allowedFonts)) {
            return in_array($font, $allowedFonts, true) ? $font : $default;
        }
        if (preg_match('/^[a-zA-Z0-9\s\-_]{2,64}$/', $font)) {
            return $font;
        }
        return $default;
    }
}

if (!function_exists('bapenda_safe_css_bg_url')) {
    function bapenda_safe_css_bg_url($url, $default = '')
    {
        $safe = bapenda_safe_url((string) $url, '');
        if ($safe === '') {
            return $default;
        }
        // Prevent breaking out of CSS url('...')
        return strtr($safe, [
            "'" => '%27',
            '"' => '%22',
            '(' => '%28',
            ')' => '%29',
            '\\' => '',
            ';' => '%3B',
            '<' => '%3C',
            '>' => '%3E',
        ]);
    }
}

if (!function_exists('bapenda_safe_hero_title')) {
    function bapenda_safe_hero_title($raw)
    {
        $normalized = preg_replace('/<br\s*\/?>/i', "\n", (string) $raw);
        $escaped = htmlspecialchars((string) $normalized, ENT_QUOTES, 'UTF-8');
        return nl2br($escaped, false);
    }
}

if (!function_exists('bapenda_json_encode')) {
    function bapenda_json_encode($value)
    {
        $encoded = json_encode(
            $value,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
        );
        return $encoded === false ? 'null' : $encoded;
    }
}

if (!function_exists('bapenda_sanitize_inline_style')) {
    function bapenda_sanitize_inline_style($style)
    {
        if (!is_string($style) || trim($style) === '') {
            return '';
        }

        $clean = preg_replace('/\/\*.*?\*\//s', '', $style);
        if (preg_match('/(?:expression|behavior|-moz-binding|@import|javascript:|vbscript:|data:|url\s*\()/i', (string) $clean)) {
            // Evaluate declarations individually below; reject dangerous global tokens
            $clean = (string) $clean;
        }

        $allowedProps = [
            'display', 'align-items', 'justify-content', 'gap', 'flex', 'flex-wrap', 'flex-direction',
            'grid-template-columns', 'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
            'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
            'background', 'background-color', 'color', 'border', 'border-top', 'border-right', 'border-bottom', 'border-left',
            'border-radius', 'text-decoration', 'font-weight', 'font-size', 'font-style', 'font-family',
            'text-align', 'line-height', 'width', 'max-width', 'min-width', 'height', 'max-height', 'min-height',
            'box-shadow', 'object-fit', 'overflow', 'list-style', 'vertical-align', 'white-space',
        ];

        $safeDecls = [];
        foreach (explode(';', (string) $clean) as $decl) {
            $parts = explode(':', $decl, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $prop = strtolower(trim($parts[0]));
            $val = trim($parts[1]);
            if ($prop === '' || $val === '' || !in_array($prop, $allowedProps, true)) {
                continue;
            }
            if (preg_match('/(?:expression|behavior|-moz-binding|@import|javascript:|vbscript:|data:|url\s*\(|[{}\\\\<>])/i', $val)) {
                continue;
            }
            $safeDecls[] = $prop . ': ' . $val;
        }

        return implode('; ', $safeDecls);
    }
}

if (!function_exists('bapenda_sanitize_html')) {
    function bapenda_sanitize_html($html)
    {
        if (!is_string($html) || trim($html) === '') {
            return '';
        }

        if (!class_exists('DOMDocument')) {
            return nl2br(htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8'));
        }

        $allowedTags = [
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'p', 'br', 'hr', 'strong', 'b', 'em', 'i', 'u', 's', 'del', 'ins', 'small', 'sub', 'sup', 'mark',
            'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'blockquote', 'pre', 'code',
            'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption',
            'div', 'span', 'section', 'article', 'header', 'main', 'aside', 'figure', 'figcaption',
            'a', 'img',
        ];

        $dropWithChildren = [
            'script', 'style', 'iframe', 'object', 'embed', 'applet',
            'svg', 'math', 'noscript', 'template', 'link', 'meta', 'base', 'form', 'input', 'button', 'textarea', 'select',
        ];

        $globalAttrs = ['class', 'id', 'title', 'dir', 'lang', 'style', 'aria-label', 'aria-hidden'];
        $tagAttrs = [
            'a' => ['href', 'target', 'rel'],
            'img' => ['src', 'alt', 'width', 'height', 'loading'],
            'th' => ['colspan', 'rowspan', 'scope'],
            'td' => ['colspan', 'rowspan'],
        ];

        $prevUseErrors = libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body><div id="__bapenda_sanitize_root__">' . $html . '</div></body></html>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prevUseErrors);

        $root = $dom->getElementById('__bapenda_sanitize_root__');
        if (!$root) {
            return '';
        }

        $walk = function (DOMNode $parent) use (&$walk, $allowedTags, $dropWithChildren, $globalAttrs, $tagAttrs) {
            $children = [];
            foreach ($parent->childNodes as $child) {
                $children[] = $child;
            }

            foreach ($children as $node) {
                if ($node instanceof DOMComment || $node instanceof DOMProcessingInstruction) {
                    $parent->removeChild($node);
                    continue;
                }

                if (!($node instanceof DOMElement)) {
                    continue;
                }

                $tag = strtolower($node->tagName);

                if (in_array($tag, $dropWithChildren, true)) {
                    $parent->removeChild($node);
                    continue;
                }

                // Recurse first
                $walk($node);

                if (!in_array($tag, $allowedTags, true)) {
                    // Unwrap benign unknown markup instead of silently dropping inner text
                    while ($node->firstChild) {
                        $parent->insertBefore($node->firstChild, $node);
                    }
                    $parent->removeChild($node);
                    continue;
                }

                $allowedForTag = array_merge($globalAttrs, $tagAttrs[$tag] ?? []);
                $attrNames = [];
                foreach ($node->attributes as $attr) {
                    $attrNames[] = $attr->name;
                }

                foreach ($attrNames as $attrName) {
                    $lowerAttr = strtolower($attrName);
                    if (strpos($lowerAttr, 'on') === 0 || !in_array($lowerAttr, $allowedForTag, true)) {
                        $node->removeAttribute($attrName);
                        continue;
                    }

                    $val = $node->getAttribute($attrName);
                    if ($lowerAttr === 'href') {
                        $node->setAttribute('href', bapenda_safe_url($val, '#'));
                    } elseif ($lowerAttr === 'src') {
                        $safeSrc = bapenda_safe_url($val, '');
                        if ($safeSrc === '') {
                            $node->removeAttribute('src');
                        } else {
                            $node->setAttribute('src', $safeSrc);
                        }
                    } elseif ($lowerAttr === 'target') {
                        if (!in_array($val, ['_blank', '_self'], true)) {
                            $node->removeAttribute('target');
                        }
                    } elseif ($lowerAttr === 'style') {
                        $safeStyle = bapenda_sanitize_inline_style($val);
                        if ($safeStyle === '') {
                            $node->removeAttribute('style');
                        } else {
                            $node->setAttribute('style', $safeStyle);
                        }
                    } elseif (in_array($lowerAttr, ['width', 'height', 'colspan', 'rowspan'], true)) {
                        if (!preg_match('/^\d{1,5}(?:%|px)?$/', trim($val))) {
                            $node->removeAttribute($attrName);
                        }
                    }
                }

                if ($tag === 'a' && $node->getAttribute('target') === '_blank') {
                    $node->setAttribute('rel', 'noopener noreferrer');
                }
            }
        };

        $walk($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return $output;
    }
}
?>
