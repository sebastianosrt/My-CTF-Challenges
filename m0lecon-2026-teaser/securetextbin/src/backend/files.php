<?php
header('Content-Type: application/json');

require_once __DIR__ . '/db.php';

try {
    $stmt = $pdo->prepare("SELECT id, filename, content_type, created_at FROM files ORDER BY created_at DESC");
    $stmt->execute();
    
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'files' => $files
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to retrieve files']);
}
