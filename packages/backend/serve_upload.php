<?php
// Streams a file from the upload folder (/uploads/... is rewritten here by .htaccess).
// Only known image/PDF types are served, always with nosniff, so an upload can never run as code.
require_once __DIR__ . '/uploads.php';

$rel = ltrim(str_replace('\\', '/', (string) ($_GET['path'] ?? '')), '/');
$abs = upload_resolve('/uploads/' . $rel);
if ($abs === null) app_error_page(404);

// Media-library and branding files are public. Rider photos are personal data: only the Super Admin, the rider's
// city council, the chairperson of the rider's stage and the rider themselves may load them. Others get a 404 so
// the response does not even confirm that the file exists.
if (str_starts_with($rel, 'riders/')) {
    require_once __DIR__ . '/auth.php';
    if (!rider_photo_allowed('/uploads/' . $rel)) app_error_page(404);
}
// Staff profile photos are shown inside the portals only.
if (str_starts_with($rel, 'avatars/')) {
    require_once __DIR__ . '/auth.php';
    if (!is_logged_in()) app_error_page(404);
}

$mime = upload_detect_mime($abs);
if (!isset(UPLOAD_IMAGE_TYPES[$mime]) && !isset(UPLOAD_DOCUMENT_TYPES[$mime])) app_error_page(404);

$mtime = filemtime($abs) ?: time();
$etag = '"' . md5($abs . $mtime . filesize($abs)) . '"';
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($abs));
header('Cache-Control: ' . (str_starts_with($rel, 'riders/') || str_starts_with($rel, 'avatars/') ? 'private, no-store' : 'public, max-age=86400'));
header('ETag: ' . $etag);
header('Content-Disposition: ' . ($mime === 'application/pdf' ? 'attachment' : 'inline') . '; filename="' . basename($abs) . '"');
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) { http_response_code(304); exit; }
readfile($abs);
