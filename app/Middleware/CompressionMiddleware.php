<?php
// app/Middleware/CompressionMiddleware.php
class CompressionMiddleware {
    public static function enable() {
        if (!ob_get_level() && extension_loaded('zlib') && !headers_sent()) {
            if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false) {
                ob_start('ob_gzhandler');
            } else {
                ob_start();
            }
        }
    }
    
    public static function setHeaders() {
        if (!headers_sent()) {
            // Cache headers para assets estáticos
            if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf)$/i', $_SERVER['REQUEST_URI'])) {
                header('Cache-Control: public, max-age=31536000'); // 1 ano
                header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
            } else {
                // Cache para páginas HTML
                header('Cache-Control: public, max-age=300'); // 5 minutos
            }
            
            // Security headers
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            
            // Content Security Policy básico
            $csp = "default-src 'self'; " .
                   "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; " .
                   "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
                   "font-src 'self' https://fonts.gstatic.com; " .
                   "img-src 'self' data: https:; " .
                   "connect-src 'self';";
            
            header("Content-Security-Policy: $csp");
        }
    }
}
