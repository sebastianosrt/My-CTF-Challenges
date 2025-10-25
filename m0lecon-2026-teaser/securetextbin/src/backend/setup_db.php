<?php

$host = getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('DB_NAME') ?: 'filestore';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    exit;
}

$createTable = "
CREATE TABLE IF NOT EXISTS files (
    id BIGINT UNSIGNED PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    content_type VARCHAR(255) NOT NULL,
    file_data MEDIUMBLOB NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$pdo->exec($createTable);

// Clear files older than 5 minutes using MySQL event scheduler
try {
    $pdo->exec("SET GLOBAL event_scheduler = ON");
    $pdo->exec(
        "CREATE EVENT IF NOT EXISTS purge_old_files " .
        "ON SCHEDULE EVERY 1 MINUTE " .
        "DO DELETE FROM files WHERE created_at < (NOW() - INTERVAL 5 MINUTE)"
    );
} catch (PDOException $e) {
    // Swallow to avoid breaking container startup if events are disabled
}
