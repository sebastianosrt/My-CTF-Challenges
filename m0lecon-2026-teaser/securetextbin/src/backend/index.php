<?php
header('Content-Type: application/json');

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!isset($_POST['file']) && empty($_FILES['file']['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or upload error']);
    exit;
}

$id = $_POST['id'];
$file = $_POST['file'] ?? null;
$contentType = $_POST['content_type'] ?? 'text/plain';
$fileName = uniqid();

if (!empty($_FILES['file']['name'])) {
    $fileName = basename($_FILES['file']['name']);
    $dot = strrpos($fileName, '.');
    if ($dot !== false) {
        $fileName = substr($fileName, 0, $dot); // remove extension
    }
}

if ($file === null) {
    $file = file_get_contents($_FILES['file']['tmp_name']);
}

if (strlen($file) > 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'File size exceeds size limit']);
    exit;
}
if (empty($id) || !is_numeric($id)) {
    $id = random_int(0, 999999999999);
}

try {
    $stmt = $pdo->prepare("INSERT INTO files (id, filename, content_type, file_data, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$id, $fileName, $contentType, $file]);
    
    echo json_encode([
        'success' => true,
        'id' => $id,
        'filename' => $fileName,
        'content_type' => $contentType
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to store file']);
}
