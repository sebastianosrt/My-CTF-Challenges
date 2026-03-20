<?php

namespace Herbarium\Content;

use Herbarium\Core\Database;

class ReportGenerator
{
    private array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function familyDistribution(): array
    {
        return Database::prepared(
            "SELECT family, COUNT(*) as count
             FROM specimens
             WHERE family IS NOT NULL AND family != ''
             GROUP BY family
             ORDER BY count DESC"
        );
    }

    public function collectionTimeline(): array
    {
        return Database::prepared(
            "SELECT DATE_FORMAT(collected_date, '%Y-%m') as month, COUNT(*) as count
             FROM specimens
             WHERE collected_date IS NOT NULL AND collected_date != ''
             GROUP BY month
             ORDER BY month ASC"
        );
    }

    public function topCollectors(int $limit = 10): array
    {
        return Database::prepared(
            "SELECT collector, COUNT(*) as count
             FROM specimens
             WHERE collector IS NOT NULL AND collector != ''
             GROUP BY collector
             ORDER BY count DESC
             LIMIT ?",
            [$limit]
        );
    }

    public function preservationBreakdown(): array
    {
        return Database::prepared(
            "SELECT preservation_method, COUNT(*) as count
             FROM specimens
             WHERE preservation_method IS NOT NULL AND preservation_method != ''
             GROUP BY preservation_method
             ORDER BY count DESC"
        );
    }

    public function importActivity(int $days = 30): array
    {
        $since = date('Y-m-d', strtotime("-{$days} days"));
        return Database::prepared(
            "SELECT DATE_FORMAT(created_at, '%Y-%m-%d') as date,
                    source_type,
                    SUM(records_imported) as total
             FROM import_logs
             WHERE created_at >= ? AND status = 'success'
             GROUP BY date, source_type
             ORDER BY date ASC",
            [$since]
        );
    }

    public function habitatSummary(): array
    {
        return Database::prepared(
            "SELECT habitat, COUNT(*) as count
             FROM specimens
             WHERE habitat IS NOT NULL AND habitat != ''
             GROUP BY habitat
             ORDER BY count DESC"
        );
    }

    public function userActivity(int $limit = 10): array
    {
        return Database::prepared(
            "SELECT u.username, u.display_name,
                    COUNT(DISTINCT s.id) as specimens_imported,
                    COUNT(DISTINCT a.id) as audit_actions
             FROM users u
             LEFT JOIN specimens s ON s.imported_by = u.id
             LEFT JOIN audit_log a ON a.user_id = u.id
             GROUP BY u.id
             ORDER BY specimens_imported DESC
             LIMIT ?",
            [$limit]
        );
    }

    public function genusDistribution(int $limit = 20): array
    {
        return Database::prepared(
            "SELECT genus, COUNT(*) as count
             FROM specimens
             WHERE genus IS NOT NULL AND genus != ''
             GROUP BY genus
             ORDER BY count DESC
             LIMIT ?",
            [$limit]
        );
    }

    public function sourceBreakdown(): array
    {
        return Database::prepared(
            "SELECT source, COUNT(*) as count
             FROM specimens
             GROUP BY source
             ORDER BY count DESC"
        );
    }

    public function generate(): array
    {
        return [
            'generated_at'   => date('Y-m-d\TH:i:s\Z'),
            'families'       => $this->familyDistribution(),
            'timeline'       => $this->collectionTimeline(),
            'top_collectors' => $this->topCollectors(),
            'preservation'   => $this->preservationBreakdown(),
            'habitats'       => $this->habitatSummary(),
            'user_activity'  => $this->userActivity(),
            'recent_imports' => $this->importActivity(),
            'genera'         => $this->genusDistribution(),
            'sources'        => $this->sourceBreakdown(),
        ];
    }

    public function __wakeup()
    {
        $this->filters = [];
        throw new \BadMethodCallException('Cannot unserialize ' . __CLASS__);
    }
}
