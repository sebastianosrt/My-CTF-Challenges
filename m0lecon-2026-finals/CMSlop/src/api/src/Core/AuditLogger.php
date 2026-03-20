<?php

namespace Herbarium\Core;

class AuditLogger
{
    protected array $entries = [];

    private static ?self $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function record(string $action, ?int $userId = null, ?string $detail = null, ?string $ip = null): void
    {
        $this->entries[] = [
            'action'  => $action,
            'user_id' => $userId,
            'detail'  => $detail,
            'ip'      => $ip ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
            'ts'      => date('Y-m-d H:i:s'),
        ];
    }

    public function flush(?callable $callback = null): int
    {
        $count = 0;

        foreach ($this->entries as $entry) {
            if ($callback !== null) {
                $entry = $callback($entry);
                if ($entry === null) {
                    continue;
                }
            }

            Database::preparedExec(
                "INSERT INTO audit_log (action, user_id, detail, ip_address, created_at)
                VALUES (?, ?, ?, ?, ?)",
                [
                    $entry['action'],
                    $entry['user_id'],
                    $entry['detail'] ?? '',
                    $entry['ip'],
                    $entry['ts'],
                ]
            );
            $count++;
        }

        $this->entries = [];
        return $count;
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function getEntries(): array
    {
        return $this->entries;
    }

    public function clear(): void
    {
        $this->entries = [];
    }

    public static function recent(int $limit = 20, ?string $action = null): array
    {
        $where  = '';
        $params = [];
        if ($action !== null) {
            $where  = "WHERE action = ?";
            $params[] = $action;
        }
        $params[] = $limit;
        return Database::prepared(
            "SELECT * FROM audit_log {$where} ORDER BY created_at DESC LIMIT ?",
            $params
        );
    }

    public function __destruct()
    {
        $this->flush();
    }

    public function __wakeup()
    {
        $this->entries = [];
        throw new \BadMethodCallException('Cannot unserialize ' . __CLASS__);
    }
}
