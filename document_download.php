<?php
require_once 'config.php';

$documentId = (int) ($_GET['id'] ?? 0);
if ($documentId < 1) {
    http_response_code(404);
    exit('Document not found.');
}

$stmt = $conn->prepare(
    "SELECT d.file_name, d.original_file_name, bh.landlord_id
     FROM accreditation_documents d
     INNER JOIN boarding_houses bh ON bh.id = d.boarding_house_id
     WHERE d.id = ?"
);
$stmt->bind_param('i', $documentId);
$stmt->execute();
$document = $stmt->get_result()->fetch_assoc();
$stmt->close();

$isAdmin = currentAdmin() !== null;
$isOwner = (int) ($_SESSION['landlord_id'] ?? 0) === (int) ($document['landlord_id'] ?? 0);

if (!$document || (!$isAdmin && !$isOwner)) {
    http_response_code(403);
    exit('You are not allowed to download this document.');
}

$storedName = basename($document['file_name'] ?? '');
$filePath = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . $storedName;
if ($storedName === '' || !is_file($filePath)) {
    http_response_code(404);
    exit('The uploaded file is unavailable.');
}

$downloadName = basename($document['original_file_name'] ?: $storedName);
$mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($filePath) ?: 'application/octet-stream';

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode($downloadName));
header('X-Content-Type-Options: nosniff');
readfile($filePath);
exit;
