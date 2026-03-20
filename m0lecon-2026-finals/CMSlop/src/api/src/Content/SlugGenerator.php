<?php

namespace Herbarium\Content;

use Herbarium\Core\Database;

class SlugGenerator
{
    public static function generate(string $title): string
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug === '' ? 'untitled' : $slug;
    }

    public static function unique(string $title, string $table, ?int $excludeId = null): string
    {
        $base = self::generate($title);
        $slug = $base;
        $counter = 2;

        while (!self::isAvailable($slug, $table, $excludeId)) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public static function isAvailable(string $slug, string $table, ?int $excludeId = null): bool
    {
        Database::assertValidTable($table);

        if ($excludeId !== null) {
            return !Database::preparedExists(
                "SELECT 1 FROM {$table} WHERE slug = ? AND id != ?",
                [$slug, $excludeId]
            );
        }

        return !Database::preparedExists(
            "SELECT 1 FROM {$table} WHERE slug = ?",
            [$slug]
        );
    }

    public function __wakeup(): void
    {
        throw new \BadMethodCallException('Deserialization not allowed');
    }
}
