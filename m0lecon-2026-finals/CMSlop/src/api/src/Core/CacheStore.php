<?php

namespace Herbarium\Core;

class CacheStore
{
    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function get(string $key)
    {
        $row = Database::prepared(
            "SELECT value FROM cache WHERE `key` = ? AND expires_at > NOW() LIMIT 1",
            [$key]
        );
        if (empty($row)) {
            return null;
        }
        return json_decode($row[0]['value'], true);
    }

    public function set(string $key, $value, int $ttlSeconds = 3600): void
    {
        $json    = json_encode($value);
        $expires = date('Y-m-d H:i:s', time() + $ttlSeconds);

        Database::preparedExec(
            "REPLACE INTO cache (`key`, value, expires_at) VALUES (?, ?, ?)",
            [$key, $json, $expires]
        );
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function delete(string $key): void
    {
        Database::preparedExec("DELETE FROM cache WHERE `key` = ?", [$key]);
    }

    public function remember(string $key, int $ttl, callable $callback)
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }
        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    public function flush(): int
    {
        return Database::preparedExec("DELETE FROM cache WHERE expires_at <= NOW()");
    }

    public function clear(): int
    {
        return Database::preparedExec("DELETE FROM cache");
    }

    public function count(): int
    {
        return (int) Database::preparedScalar(
            "SELECT COUNT(*) FROM cache WHERE expires_at > NOW()"
        );
    }

    public function keys(): array
    {
        $rows = Database::prepared(
            "SELECT `key` FROM cache WHERE expires_at > NOW() ORDER BY `key` ASC"
        );
        return array_column($rows, 'key');
    }

    public function __wakeup()
    {
        throw new \BadMethodCallException('Cannot unserialize ' . __CLASS__);
    }
}
