<?php

namespace Herbarium\Media;

use Herbarium\Core\Database;

class MediaManager
{
    private static string $uploadDir = '/var/www/html/uploads/media/';

    private static array $allowedMimes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf', 'text/csv', 'text/plain',
    ];

    private static int $maxSize = 10 * 1024 * 1024;

    public static function upload(array $file, int $userId, string $altText = '', string $caption = ''): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['error' => 'Upload error code: ' . $file['error']];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, self::$allowedMimes, true)) {
            return ['error' => 'File type not allowed: ' . $mimeType];
        }

        if ($file['size'] > self::$maxSize) {
            return ['error' => 'File too large. Maximum 10MB'];
        }

        $filename = 'media_' . bin2hex(random_bytes(12));
        $destPath = self::$uploadDir . $filename;

        if (!is_dir(self::$uploadDir)) {
            @mkdir(self::$uploadDir, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return ['error' => 'Failed to save file'];
        }

        Database::preparedExec(
            "INSERT INTO media (filename, original_name, mime_type, file_size, alt_text, caption, uploaded_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$filename, $file['name'], $mimeType, $file['size'], $altText, $caption, $userId]
        );

        $id = (int) Database::lastInsertId();

        return [
            'id'            => $id,
            'filename'      => $filename,
            'original_name' => $file['name'],
            'mime_type'     => $mimeType,
            'file_size'     => $file['size'],
        ];
    }

    public static function get(int $id): ?array
    {
        return Database::preparedFirst(
            "SELECT m.*, u.username as uploaded_by_name
             FROM media m LEFT JOIN users u ON m.uploaded_by = u.id
             WHERE m.id = ?",
            [$id]
        );
    }

    public static function list(int $page = 1, int $perPage = 20, ?string $mimeFilter = null): array
    {
        $offset = ($page - 1) * $perPage;
        $where  = '';
        $params = [];

        if ($mimeFilter !== null && $mimeFilter !== '') {
            $where    = "WHERE m.mime_type LIKE ?";
            $params[] = $mimeFilter . '%';
        }

        $total = (int) Database::preparedScalar(
            "SELECT COUNT(*) FROM media m {$where}",
            $params
        );

        $rows = Database::prepared(
            "SELECT m.*, u.username as uploaded_by_name
             FROM media m LEFT JOIN users u ON m.uploaded_by = u.id
             {$where}
             ORDER BY m.created_at DESC
             LIMIT ? OFFSET ?",
            array_merge($params, [$perPage, $offset])
        );

        return [
            'media'      => $rows,
            'pagination' => [
                'page'     => $page,
                'per_page' => $perPage,
                'total'    => $total,
                'pages'    => (int) ceil($total / max($perPage, 1)),
            ],
        ];
    }

    public static function update(int $id, string $altText, string $caption): bool
    {
        $affected = Database::preparedExec(
            "UPDATE media SET alt_text = ?, caption = ? WHERE id = ?",
            [$altText, $caption, $id]
        );
        return $affected > 0;
    }

    public static function delete(int $id): bool
    {
        $row = Database::preparedFirst("SELECT filename FROM media WHERE id = ?", [$id]);
        if ($row === null) {
            return false;
        }

        $filePath = self::$uploadDir . basename($row['filename']);
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        Database::preparedExec("DELETE FROM media WHERE id = ?", [$id]);
        return true;
    }

    public static function getFilePath(string $filename): ?string
    {
        $safeName = basename($filename);
        if (!preg_match('/^[a-zA-Z0-9_\-]+\.[a-zA-Z0-9]+$/', $safeName)) {
            return null;
        }
        $path = self::$uploadDir . $safeName;
        $realPath = realpath($path);
        if ($realPath === false || strpos($realPath, realpath(self::$uploadDir)) !== 0) {
            return null;
        }
        return $realPath;
    }
}
