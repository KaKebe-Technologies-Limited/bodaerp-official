<?php
// POST /api/media-upload.php: upload endpoint for CKEditor (SimpleUploadAdapter) and the media library.
// Super Admin only. Response: {"url": "..."} on success, {"error": {"message": "..."}} on failure.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/uploads.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['error' => ['message' => 'Use POST.']]); exit; }
if (!is_logged_in() || ($_SESSION['role'] ?? '') !== 'super_admin') { http_response_code(403); echo json_encode(['error' => ['message' => 'Only administrators can upload files.']]); exit; }
csrf_verify();
if (!rate_limit('upload:' . $_SESSION['user_id'], 60, 600)) { http_response_code(429); echo json_encode(['error' => ['message' => 'Too many uploads. Wait a few minutes.']]); exit; }

$file = $_FILES['upload'] ?? $_FILES['file'] ?? null;
if (!$file) { http_response_code(400); echo json_encode(['error' => ['message' => 'No file received.']]); exit; }

$allowDocs = !empty($_POST['allow_documents']);
$r = store_upload($file, 'media', $allowDocs ? UPLOAD_IMAGE_TYPES + UPLOAD_DOCUMENT_TYPES : UPLOAD_IMAGE_TYPES, 5 * 1024 * 1024);
if (!$r['ok']) { http_response_code(422); echo json_encode(['error' => ['message' => $r['error']]]); exit; }

runQuery("INSERT INTO media (path, original_name, mime, size_bytes, width, height, alt_text, uploaded_by) VALUES (?,?,?,?,?,?,?,?)", [
    $r['path'], mb_substr(basename((string) $file['name']), 0, 255), $r['mime'], $r['size'], $r['width'], $r['height'],
    mb_substr(trim((string) ($_POST['alt_text'] ?? '')), 0, 255) ?: null, $_SESSION['user_id'],
]);
$mediaId = (int) db()->insert_id;   // read before audit_log(), which inserts its own row
audit_log('UPLOAD', 'media', (string) $mediaId, 'Uploaded ' . basename((string) $file['name']));
echo json_encode(['url' => media_url($r['path']), 'id' => $mediaId, 'path' => $r['path']]);
