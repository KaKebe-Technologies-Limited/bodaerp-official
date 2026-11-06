<?php
// BodaERP - file uploads Files are checked by their real content (not the browser's claim), renamed to a
// random name and stored under upload_path(). They are only ever served by serve_upload.php, so nothing in
// the upload folder can run as PHP.
require_once __DIR__ . '/config/config.php';

const UPLOAD_IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
const UPLOAD_DOCUMENT_TYPES = ['application/pdf' => 'pdf'];

function upload_detect_mime(string $tmpPath): string {
    if (class_exists('finfo')) {
        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
        if ($mime !== '') return $mime;
    }
    $img = @getimagesize($tmpPath);
    if ($img && !empty($img['mime'])) return $img['mime'];
    $head = (string) @file_get_contents($tmpPath, false, null, 0, 5);
    return $head === '%PDF-' ? 'application/pdf' : 'application/octet-stream';
}

function upload_error_message(int $code): string {
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is larger than the server allows.',
        UPLOAD_ERR_PARTIAL => 'The upload was interrupted. Please try again.',
        UPLOAD_ERR_NO_FILE => 'No file was chosen.',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'The server could not save the file.',
        default => 'The upload failed.',
    };
}

function upload_protect_folder(string $dir): void {
    $guard = $dir . '/.htaccess';
    // Requests for /uploads/... are rewritten to serve_upload.php before Apache looks here; this file is
    // a second line of defence in case the folder is ever exposed directly.
    if (!is_file($guard)) @file_put_contents($guard, "Options -Indexes -ExecCGI\n"
        . "<FilesMatch \"(?i)\\.(php\\d?|phtml|phar|pht|phps|cgi|pl|py|sh|htm|html|svg|js)$\">\n    Require all denied\n</FilesMatch>\n");
}

/**
 * Validates and stores one uploaded file.
 * @param array<string,string> $allowed mime => extension
 * @return array{ok:bool,path?:string,mime?:string,size?:int,width?:int|null,height?:int|null,error?:string}
 *         path is relative to the site, e.g. /uploads/riders/2026/10/ab12cd.jpg
 */
function store_upload(array $file, string $folder, array $allowed = UPLOAD_IMAGE_TYPES, int $maxBytes = 2097152): array {
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) return ['ok' => false, 'error' => upload_error_message($err)];
    if (!is_uploaded_file($file['tmp_name'] ?? '')) return ['ok' => false, 'error' => 'The upload failed.'];
    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        return ['ok' => false, 'error' => 'The file must be smaller than ' . round($maxBytes / 1048576, 1) . ' MB.'];
    }
    $mime = upload_detect_mime($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        return ['ok' => false, 'error' => 'That file type is not allowed. Use ' . strtoupper(implode(', ', array_unique($allowed))) . '.'];
    }
    $width = $height = null;
    if (str_starts_with($mime, 'image/')) {
        $info = @getimagesize($file['tmp_name']);
        if (!$info) return ['ok' => false, 'error' => 'The image file is damaged or not a real image.'];
        [$width, $height] = $info;
    }

    $folder = trim(preg_replace('#[^a-z0-9/_-]#i', '', $folder), '/');
    $relDir = $folder . '/' . date('Y/m');
    $absDir = upload_path($relDir);
    if (!ensure_dir($absDir)) return ['ok' => false, 'error' => 'The upload folder is not writable.'];
    upload_protect_folder(upload_path());

    $name = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $absDir . '/' . $name)) {
        return ['ok' => false, 'error' => 'The server could not save the file.'];
    }
    @chmod($absDir . '/' . $name, 0644);
    return ['ok' => true, 'path' => '/uploads/' . $relDir . '/' . $name, 'mime' => $mime, 'size' => $size, 'width' => $width, 'height' => $height];
}

/**
 * Stores image bytes made in the browser (a drawn signature), with the same checks as store_upload(): real
 * content type, size limit, a readable image, a random name.
 */
function store_generated_image(string $bytes, string $folder, array $allowed = UPLOAD_IMAGE_TYPES, int $maxBytes = 2097152): array {
    $size = strlen($bytes);
    if ($size === 0 || $size > $maxBytes) return ['ok' => false, 'error' => 'The image must be smaller than ' . round($maxBytes / 1048576, 1) . ' MB.'];
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
    if (!isset($allowed[$mime])) return ['ok' => false, 'error' => 'That image type is not allowed.'];
    $info = @getimagesizefromstring($bytes);
    if (!$info) return ['ok' => false, 'error' => 'The image is damaged.'];
    $folder = trim(preg_replace('#[^a-z0-9/_-]#i', '', $folder), '/');
    $relDir = $folder . '/' . date('Y/m');
    $absDir = upload_path($relDir);
    if (!ensure_dir($absDir)) return ['ok' => false, 'error' => 'The upload folder is not writable.'];
    upload_protect_folder(upload_path());
    $name = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    if (file_put_contents($absDir . '/' . $name, $bytes) !== $size) return ['ok' => false, 'error' => 'The server could not save the image.'];
    @chmod($absDir . '/' . $name, 0644);
    return ['ok' => true, 'path' => '/uploads/' . $relDir . '/' . $name, 'mime' => $mime, 'size' => $size, 'width' => $info[0], 'height' => $info[1]];
}

/** Deletes a stored upload given its site path (/uploads/...). Refuses anything outside the upload folder. */
function delete_upload(?string $sitePath): bool {
    $abs = upload_resolve($sitePath);
    return $abs !== null && @unlink($abs);
}

/** Maps /uploads/<rel> to a real file inside upload_path(), or null. Blocks ../ tricks. */
function upload_resolve(?string $sitePath): ?string {
    $rel = media_relative($sitePath);
    if ($rel === null || $rel === '' || str_contains($rel, '..') || str_contains($rel, "\0")) return null;
    $base = realpath(upload_path());
    $abs = realpath(upload_path($rel));
    if ($base === false || $abs === false || !is_file($abs)) return null;
    $base = str_replace('\\', '/', $base);
    $abs = str_replace('\\', '/', $abs);
    return str_starts_with($abs, $base . '/') ? $abs : null;
}

/** "riders/x.jpg" for any of: /uploads/riders/x.jpg, <BASE_URL>/uploads/riders/x.jpg, uploads/riders/x.jpg. */
function media_relative(?string $stored): ?string {
    if ($stored === null || trim($stored) === '') return null;
    $p = str_replace('\\', '/', trim($stored));
    if (preg_match('#^https?://#i', $p)) return null;
    if (BASE_URL !== '' && str_starts_with($p, BASE_URL . '/')) $p = substr($p, strlen(BASE_URL));
    $p = ltrim($p, '/');
    return str_starts_with($p, 'uploads/') ? substr($p, 8) : null;
}

/** Browser URL for a stored file path or asset path; $fallback (an /assets/... path) when empty. */
function media_url(?string $stored, string $fallback = '/assets/images/avatar-placeholder.png'): string {
    if ($stored === null || trim($stored) === '') return BASE_URL . $fallback;
    $p = str_replace('\\', '/', trim($stored));
    if (preg_match('#^https?://#i', $p)) return $p;
    if (BASE_URL !== '' && str_starts_with($p, BASE_URL . '/')) return $p;
    return BASE_URL . '/' . ltrim($p, '/');
}

/** Whether the signed-in user may load this rider photo or card signature (see serve_upload.php). */
function rider_photo_allowed(string $path): bool {
    if (!function_exists('is_logged_in') || !is_logged_in()) return false;
    $rider = fetchOne("SELECT id, city_id, stage_id, user_id FROM riders WHERE photo_path IN (?, ?) OR signature_path IN (?, ?) LIMIT 1", [$path, BASE_URL . $path, $path, BASE_URL . $path]);
    if (!$rider) return ($_SESSION['role'] ?? '') === 'super_admin';
    return match ($_SESSION['role'] ?? '') {
        'super_admin' => true,
        'city_admin'  => (string) $rider['city_id'] === (string) ($_SESSION['city_id'] ?? ''),
        'chairperson' => (int) $rider['stage_id'] === (int) ($_SESSION['stage_id'] ?? 0),
        'rider'       => (int) $rider['id'] === (int) ($_SESSION['rider_id'] ?? 0),
        default       => false,
    };
}

