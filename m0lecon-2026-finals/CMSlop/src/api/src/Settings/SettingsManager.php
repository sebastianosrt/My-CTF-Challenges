<?php

namespace Herbarium\Settings;

use Herbarium\Core\Database;

class SettingsManager
{
    private static array $defaults = [
        'site_name'        => 'Herbarium',
        'site_description' => 'Digital botanical archive and specimen management system',
        'items_per_page'   => '20',
        'allow_public_api' => '1',
        'maintenance_mode' => '0',
    ];

    public static function get(string $key): ?string
    {
        $row = Database::preparedFirst(
            "SELECT value FROM settings WHERE `key` = ?",
            [$key]
        );

        if ($row !== null) {
            return $row['value'];
        }

        return self::$defaults[$key] ?? null;
    }

    public static function set(string $key, string $value, ?int $userId = null): void
    {
        $exists = Database::preparedExists(
            "SELECT 1 FROM settings WHERE `key` = ?",
            [$key]
        );

        if ($exists) {
            Database::preparedExec(
                "UPDATE settings SET value = ?, updated_at = CURRENT_TIMESTAMP, updated_by = ? WHERE `key` = ?",
                [$value, $userId, $key]
            );
        } else {
            Database::preparedExec(
                "INSERT INTO settings (`key`, value, updated_by) VALUES (?, ?, ?)",
                [$key, $value, $userId]
            );
        }
    }

    public static function all(): array
    {
        $rows = Database::prepared(
            "SELECT s.*, u.username as updated_by_name
             FROM settings s LEFT JOIN users u ON s.updated_by = u.id
             ORDER BY s.`key` ASC"
        );

        $result = [];
        $dbKeys = [];
        foreach ($rows as $row) {
            $result[] = $row;
            $dbKeys[] = $row['key'];
        }

        foreach (self::$defaults as $key => $value) {
            if (!in_array($key, $dbKeys, true)) {
                $result[] = [
                    'key'              => $key,
                    'value'            => $value,
                    'description'      => '',
                    'updated_at'       => null,
                    'updated_by'       => null,
                    'updated_by_name'  => null,
                ];
            }
        }

        return $result;
    }

    public static function getMultiple(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = self::get($key);
        }
        return $result;
    }

    public static function getPublic(): array
    {
        $publicKeys = ['site_name', 'site_description', 'maintenance_mode'];
        return self::getMultiple($publicKeys);
    }

    public static function seed(): void
    {
        foreach (self::$defaults as $key => $value) {
            $exists = Database::preparedExists(
                "SELECT 1 FROM settings WHERE `key` = ?",
                [$key]
            );
            if (!$exists) {
                Database::preparedExec(
                    "INSERT INTO settings (`key`, value) VALUES (?, ?)",
                    [$key, $value]
                );
            }
        }
    }
}
