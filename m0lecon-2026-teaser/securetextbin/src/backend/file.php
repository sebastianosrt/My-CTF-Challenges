<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$fileId = $_GET['id'] ?? null;

if (!$fileId || !is_numeric($fileId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing file ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT filename, content_type, file_data, created_at FROM files WHERE id = ?");
    $stmt->execute([$fileId]);
    
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
        exit;
    }
    
    header('Content-Type: ' . $file['content_type']);
    
    echo $file['file_data'];    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve file']);
}